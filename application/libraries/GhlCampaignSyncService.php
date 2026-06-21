<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pushes a campaign roster into GoHighLevel by ensuring each guest exists
 * as a GHL contact and enrolling them in the campaign's GHL workflow, which
 * triggers the WhatsApp send action configured inside that workflow.
 *
 * The work is split into three worker-driven phases so a large roster can be
 * processed across several short-lived requests instead of one long one:
 *   - enqueue_run()       : validates input and creates a Status='pending' row
 *                           in campaign_ghl_sync_log; called from the web
 *                           request the user clicked from.
 *   - process_chunk()     : processes N guests starting at CurrentOffset and
 *                           advances the cursor; called repeatedly by the
 *                           cron worker until done.
 *   - complete_run()      : flips the run to completed/failed terminal status.
 *
 * sync_campaign() is the legacy synchronous wrapper that chains all three;
 * kept for CLI / test ergonomics. The HTTP transport is injectable so the
 * pure logic can be exercised by tests/helpers/CampaignGhlSyncServiceTest.php
 * without hitting the network.
 */
class GhlCampaignSyncService
{
    const BASE_URL          = 'https://services.leadconnectorhq.com';
    const DEFAULT_TAG_PREFIX = 'campaign-';
    const DEFAULT_API_VERSION = '2021-07-28';

    protected $CI;
    protected $httpTransport;
    // Microsecond delay between per-guest processing inside a chunk. Default
    // ~200ms gives ~5 guests/sec, well under GHL's rate limit even when each
    // guest costs 3 HTTP calls. Tests inject 0 for speed.
    protected $per_guest_delay_us = 200000;

    public function __construct($config = array())
    {
        if (function_exists('get_instance')) {
            $this->CI =& get_instance();
            $this->CI->load->model('Campaign_Model');
            $this->CI->load->model('Campaign_Ghl_Sync_Model');
            $this->CI->load->model('Ghl_Sync_Model');
        }

        $this->httpTransport = isset($config['httpTransport']) && is_callable($config['httpTransport'])
            ? $config['httpTransport']
            : array($this, 'curlRequest');

        if (isset($config['per_guest_delay_us'])) {
            $this->per_guest_delay_us = max(0, (int) $config['per_guest_delay_us']);
        }
    }

    public function getConfig()
    {
        $token = function_exists('get_env') ? (string) get_env('GHL_API_TOKEN') : '';
        $locationId = function_exists('get_env') ? (string) get_env('GHL_LOCATION_ID') : '';
        $apiVersion = function_exists('get_env') ? (string) get_env('GHL_API_VERSION') : '';
        $tagPrefix = function_exists('get_env') ? (string) get_env('GHL_CAMPAIGN_TAG_PREFIX') : '';
        $fixedTag = function_exists('get_env') ? (string) get_env('GHL_CAMPAIGN_FIXED_TAG') : '';

        return array(
            'base_url'    => self::BASE_URL,
            'token'       => $token,
            'location_id' => $locationId,
            'api_version' => $apiVersion !== '' ? $apiVersion : self::DEFAULT_API_VERSION,
            'tag_prefix'  => $tagPrefix !== '' ? $tagPrefix : self::DEFAULT_TAG_PREFIX,
            'fixed_tag'   => trim($fixedTag),
        );
    }

    /**
     * Compose the tag to apply. If GHL_CAMPAIGN_FIXED_TAG is set, every
     * campaign uses that single tag (one GHL workflow handles all campaigns,
     * since GHL's Contact Tag trigger is exact-match). Otherwise falls back
     * to per-campaign `{prefix}{id}`.
     */
    public static function build_tag_name($prefix, $campaign_id, $fixed_tag = '')
    {
        $fixed_tag = trim((string) $fixed_tag);
        if ($fixed_tag !== '') {
            return $fixed_tag;
        }
        $prefix = (string) $prefix;
        if ($prefix === '') {
            $prefix = self::DEFAULT_TAG_PREFIX;
        }
        return $prefix . (int) $campaign_id;
    }

    /**
     * Last 9 digits of any phone string (matches Guests_Model->Dedup_Key_Expr
     * normalization so a Booking Guest's phone matches a GHL contact's phone).
     */
    public static function normalize_phone_key($phone)
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $phone);
        if ($digits === '' || $digits === null) {
            return '';
        }
        return strlen($digits) <= 9 ? $digits : substr($digits, -9);
    }

    /**
     * Build the JSON body for POST /contacts/. Public + static for tests.
     */
    public static function build_create_contact_payload($location_id, $row)
    {
        $name = isset($row['GuestName']) ? trim((string) $row['GuestName']) : '';
        $parts = preg_split('/\s+/', $name, 2);
        $first = isset($parts[0]) ? $parts[0] : '';
        $last  = isset($parts[1]) ? $parts[1] : '';

        $payload = array('locationId' => (string) $location_id);
        if ($first !== '') { $payload['firstName'] = $first; }
        if ($last  !== '') { $payload['lastName']  = $last;  }

        if (!empty($row['ContactNum'])) {
            $payload['phone'] = (string) $row['ContactNum'];
        }
        if (!empty($row['Email'])) {
            $payload['email'] = strtolower(trim((string) $row['Email']));
        }
        return $payload;
    }

    /**
     * Build the JSON body for PUT /contacts/{contactId} — refreshes phone,
     * email, firstName, lastName from the latest campaign_guests row. Empty
     * fields are omitted so we don't blank out existing GHL data with NULLs.
     */
    public static function build_update_contact_payload($row)
    {
        $payload = array();
        $name = isset($row['GuestName']) ? trim((string) $row['GuestName']) : '';
        if ($name !== '') {
            $parts = preg_split('/\s+/', $name, 2);
            if (isset($parts[0]) && $parts[0] !== '') { $payload['firstName'] = $parts[0]; }
            if (isset($parts[1]) && $parts[1] !== '') { $payload['lastName']  = $parts[1]; }
        }
        if (!empty($row['ContactNum'])) {
            $payload['phone'] = (string) $row['ContactNum'];
        }
        if (!empty($row['Email'])) {
            $payload['email'] = strtolower(trim((string) $row['Email']));
        }
        return $payload;
    }

    /**
     * Validate the campaign + config and create the queued run row. Returns
     * the run_id the caller can hand to the worker (or to a poller). No GHL
     * HTTP calls. Safe to call from the web request that the user clicked from.
     *
     * Returns:
     *   array(
     *     'ok'           => bool,
     *     'run_id'       => string,        // when ok
     *     'workflow_id'  => string,        // when ok
     *     'total'        => int,           // when ok
     *     'message'      => string|null,   // when !ok
     *   )
     */
    public function enqueue_run($campaign_id, $admin_id = null)
    {
        $config = $this->getConfig();
        if ($config['token'] === '' || $config['location_id'] === '') {
            return array(
                'ok'      => false,
                'message' => 'GHL is not configured (missing GHL_API_TOKEN or GHL_LOCATION_ID).',
            );
        }

        $campaign = $this->CI->Campaign_Model->Read_Campaign((int) $campaign_id);
        if (empty($campaign) || $campaign->Status !== 'Y') {
            return array('ok' => false, 'message' => 'Campaign not found.');
        }

        $workflow_id = isset($campaign->GhlWorkflowID) ? trim((string) $campaign->GhlWorkflowID) : '';
        if ($workflow_id === '') {
            return array(
                'ok'      => false,
                'message' => 'Set the GHL Workflow ID on this campaign before syncing.',
            );
        }

        $total = $this->CI->Campaign_Model->Count_Campaign_Guests((int) $campaign_id);
        if ($total <= 0) {
            return array('ok' => false, 'message' => 'Campaign has no guests to sync.');
        }

        $run_id = $this->CI->Ghl_Sync_Model->generate_run_id('campaign_sync_' . (int) $campaign_id);
        $this->CI->Campaign_Ghl_Sync_Model->create_run_summary(
            (int) $campaign_id,
            $run_id,
            $workflow_id,
            (int) $total,
            $admin_id
        );

        return array(
            'ok'          => true,
            'run_id'      => $run_id,
            'workflow_id' => $workflow_id,
            'total'       => (int) $total,
        );
    }

    /**
     * Process up to $limit guests starting from $offset for a previously
     * enqueued run. Updates per-run counters and CurrentOffset atomically.
     * The chunk size and per-guest delay are chosen so a chunk finishes well
     * under the PHP/curl timeout; the cron worker calls this in a loop.
     *
     * Returns:
     *   array(
     *     'processed'   => int,
     *     'next_offset' => int,
     *     'done'        => bool,
     *   )
     */
    public function process_chunk($campaign_id, $run_id, $offset, $limit, $admin_id = null)
    {
        $config = $this->getConfig();
        $campaign = $this->CI->Campaign_Model->Read_Campaign((int) $campaign_id);
        $workflow_id = ($campaign && isset($campaign->GhlWorkflowID)) ? trim((string) $campaign->GhlWorkflowID) : '';

        $guests = $this->CI->Campaign_Model->Read_Campaign_Guests_Chunk(
            (int) $campaign_id,
            (int) $offset,
            (int) $limit
        );

        $processed = 0;
        $deltas    = array('matched' => 0, 'created' => 0, 'enrolled' => 0, 'failed' => 0);

        foreach ($guests as $g) {
            $row = (array) $g;
            $this->process_one_guest($row, $campaign_id, $run_id, $workflow_id, $config, $admin_id, $deltas);
            $processed++;

            if ($this->per_guest_delay_us > 0 && function_exists('usleep')) {
                usleep($this->per_guest_delay_us);
            }
        }

        $next_offset = (int) $offset + $processed;
        $this->CI->Campaign_Ghl_Sync_Model->increment_run_counters((string) $run_id, $deltas, $next_offset);

        $state = $this->CI->Campaign_Ghl_Sync_Model->get_run_state((string) $run_id);
        $total = $state ? (int) $state->TotalGuests : 0;
        $done  = $processed === 0 || ($total > 0 && $next_offset >= $total);

        return array(
            'processed'   => $processed,
            'next_offset' => $next_offset,
            'done'        => $done,
        );
    }

    // Per-guest work — extracted from the old monolithic loop so the chunk
    // worker and the legacy sync_campaign() wrapper share the exact same
    // per-guest semantics. Mutates $deltas by reference.
    protected function process_one_guest($row, $campaign_id, $run_id, $workflow_id, $config, $admin_id, &$deltas)
    {
        $resolved = $this->find_or_create_contact($row, $config);

        if (empty($resolved['contact_id'])) {
            $action = $resolved['action'];
            if ($action !== 'skipped') {
                $deltas['failed']++;
            }
            $this->CI->Campaign_Ghl_Sync_Model->log_row(array(
                'CampaignID'   => (int) $campaign_id,
                'RunID'        => $run_id,
                'DedupKey'     => $row['DedupKey'],
                'GhlContactID' => null,
                'Action'       => $action,
                'Message'      => $resolved['message'],
                'HttpStatus'   => isset($resolved['http_status']) ? $resolved['http_status'] : null,
                'InsertBy'     => $admin_id,
            ));
            return;
        }

        if ($resolved['action'] === 'matched') {
            $deltas['matched']++;
        } elseif ($resolved['action'] === 'created') {
            $deltas['created']++;
        }

        $this->CI->Campaign_Ghl_Sync_Model->log_row(array(
            'CampaignID'   => (int) $campaign_id,
            'RunID'        => $run_id,
            'DedupKey'     => $row['DedupKey'],
            'GhlContactID' => $resolved['contact_id'],
            'Action'       => $resolved['action'],
            'Message'      => isset($resolved['message']) ? $resolved['message'] : null,
            'HttpStatus'   => isset($resolved['http_status']) ? $resolved['http_status'] : null,
            'InsertBy'     => $admin_id,
        ));

        // For matched contacts, refresh phone/email/name on the GHL side so a
        // number we updated locally overwrites the stale value there. Created
        // contacts already carry these fields in their POST body.
        if ($resolved['action'] === 'matched') {
            $upd = $this->update_contact($resolved['contact_id'], $row, $config);
            if (!$upd['ok'] && (int) $upd['http_status'] !== 0) {
                $this->CI->Campaign_Ghl_Sync_Model->log_row(array(
                    'CampaignID'   => (int) $campaign_id,
                    'RunID'        => $run_id,
                    'DedupKey'     => $row['DedupKey'],
                    'GhlContactID' => $resolved['contact_id'],
                    'Action'       => 'failed',
                    'Message'      => 'Contact update failed: ' . $upd['message'],
                    'HttpStatus'   => $upd['http_status'],
                    'InsertBy'     => $admin_id,
                ));
            }
        }

        $enr = $this->enrol_in_workflow($resolved['contact_id'], $workflow_id, $config);
        if ($enr['ok']) {
            $deltas['enrolled']++;
            $this->CI->Campaign_Ghl_Sync_Model->log_row(array(
                'CampaignID'   => (int) $campaign_id,
                'RunID'        => $run_id,
                'DedupKey'     => $row['DedupKey'],
                'GhlContactID' => $resolved['contact_id'],
                'Action'       => 'workflow_enrolled',
                'Message'      => $workflow_id,
                'HttpStatus'   => $enr['http_status'],
                'InsertBy'     => $admin_id,
            ));
        } else {
            $deltas['failed']++;
            $this->CI->Campaign_Ghl_Sync_Model->log_row(array(
                'CampaignID'   => (int) $campaign_id,
                'RunID'        => $run_id,
                'DedupKey'     => $row['DedupKey'],
                'GhlContactID' => $resolved['contact_id'],
                'Action'       => 'failed',
                'Message'      => 'Workflow enrolment failed: ' . $enr['message'],
                'HttpStatus'   => $enr['http_status'],
                'InsertBy'     => $admin_id,
            ));
        }
    }

    /**
     * Drain remaining chunks for a run until done or the time budget is
     * exhausted. Designed for the cron worker: each cron tick claims a run,
     * runs to completion within its budget, and either finalizes (done) or
     * leaves Status='running' for the next tick to resume from CurrentOffset.
     *
     * $time_budget_seconds=0 means "no budget, drain everything" — useful for
     * the legacy sync_campaign() wrapper and for tests.
     */
    public function run_to_completion($campaign_id, $run_id, $admin_id = null, $time_budget_seconds = 50, $chunk_size = 10)
    {
        $state = $this->CI->Campaign_Ghl_Sync_Model->get_run_state((string) $run_id);
        if (!$state) {
            return array('ok' => false, 'done' => false, 'message' => 'Run not found.');
        }
        $offset = (int) $state->CurrentOffset;
        $started = microtime(true);

        while (true) {
            $chunk = $this->process_chunk((int) $campaign_id, (string) $run_id, $offset, (int) $chunk_size, $admin_id);
            $offset = (int) $chunk['next_offset'];
            if (!empty($chunk['done'])) {
                return array('ok' => true, 'done' => true, 'next_offset' => $offset);
            }
            // Empty chunk but not done means the campaign has no more rows at
            // this offset (e.g. guests deleted mid-run). Treat as done.
            if ((int) $chunk['processed'] === 0) {
                return array('ok' => true, 'done' => true, 'next_offset' => $offset);
            }
            if ($time_budget_seconds > 0 && (microtime(true) - $started) >= $time_budget_seconds) {
                return array('ok' => true, 'done' => false, 'next_offset' => $offset);
            }
        }
    }

    /**
     * Terminal transition for a run. Idempotent — calling this on an
     * already-completed run just refreshes CompletedAt. Returns the run
     * state row.
     */
    public function complete_run($run_id, $admin_id = null)
    {
        $state = $this->CI->Campaign_Ghl_Sync_Model->get_run_state((string) $run_id);
        if (!$state) {
            return null;
        }
        $status = ((int) $state->EnrolledCount > 0) ? 'completed' : 'failed';
        $this->CI->Campaign_Ghl_Sync_Model->finalize_run((string) $run_id, $status);
        return $this->CI->Campaign_Ghl_Sync_Model->get_run_state((string) $run_id);
    }

    /**
     * Legacy synchronous entry point. Kept so the existing CLI wrapper, tests,
     * and any external caller continue to work end-to-end. The web controller
     * no longer calls this — it enqueue_runs and lets the cron worker drain.
     *
     * Returns the same shape as before plus 'failures' (capped at 5 from the
     * latest in-memory run for backwards-compatible UX).
     */
    public function sync_campaign($campaign_id, $admin_id = null)
    {
        $enq = $this->enqueue_run((int) $campaign_id, $admin_id);
        if (empty($enq['ok'])) {
            return array('ok' => false, 'message' => $enq['message']);
        }

        // time_budget=0 means drain regardless of duration. Suitable for the
        // CLI/test wrapper; not safe to call from a web request.
        $this->run_to_completion((int) $campaign_id, $enq['run_id'], $admin_id, 0);
        $final = $this->complete_run($enq['run_id'], $admin_id);

        return array(
            'ok'           => $final && (int) $final->EnrolledCount > 0,
            'run_id'       => $enq['run_id'],
            'workflow_id'  => $enq['workflow_id'],
            'matched'      => $final ? (int) $final->MatchedCount  : 0,
            'created'      => $final ? (int) $final->CreatedCount  : 0,
            'enrolled'     => $final ? (int) $final->EnrolledCount : 0,
            'failed'       => $final ? (int) $final->FailedCount   : 0,
            'skipped'      => 0, // skipped guests are logged but not in the run_summary counter set
            'failures'     => array(),
            'completed_at' => $final && $final->CompletedAt ? $final->CompletedAt : $this->getCodeDateTime(),
        );
    }

    /**
     * POST /contacts/{contact_id}/workflow/{workflow_id} — enrol a contact in
     * a workflow directly. Returns array(ok, http_status, message).
     */
    public function enrol_in_workflow($contact_id, $workflow_id, $config)
    {
        $path = '/contacts/' . rawurlencode($contact_id) . '/workflow/' . rawurlencode($workflow_id);
        $response = $this->ghl_post($path, new stdClass(), $config);
        $ok = ($response['http_status'] >= 200 && $response['http_status'] < 300);
        return array(
            'ok'          => $ok,
            'http_status' => $response['http_status'],
            'message'     => $ok ? null : ($response['error'] ?: ('HTTP ' . $response['http_status'])),
        );
    }

    /**
     * Resolve a campaign_guests row to a GHL contact_id, creating one if needed.
     * Returns array(action, contact_id|null, message|null, http_status|null).
     */
    public function find_or_create_contact($row, $config)
    {
        $phone_key = self::normalize_phone_key(isset($row['ContactNum']) ? $row['ContactNum'] : null);
        $email     = isset($row['Email']) ? strtolower(trim((string) $row['Email'])) : '';

        if ($phone_key === '' && $email === '') {
            return array(
                'action'      => 'skipped',
                'contact_id'  => null,
                'message'     => 'No phone or email to identify guest.',
                'http_status' => null,
            );
        }

        $existing = $this->lookup_existing_ghl_contact($phone_key, $email);
        if ($existing !== null) {
            return array(
                'action'      => 'matched',
                'contact_id'  => $existing,
                'message'     => null,
                'http_status' => null,
            );
        }

        $payload  = self::build_create_contact_payload($config['location_id'], $row);
        $response = $this->ghl_post('/contacts/', $payload, $config);

        if ($response['http_status'] >= 200 && $response['http_status'] < 300) {
            $contact_id = $this->extract_contact_id($response['body']);
            if ($contact_id === null) {
                return array(
                    'action'      => 'failed',
                    'contact_id'  => null,
                    'message'     => 'GHL did not return a contact id.',
                    'http_status' => $response['http_status'],
                );
            }
            return array(
                'action'      => 'created',
                'contact_id'  => $contact_id,
                'message'     => null,
                'http_status' => $response['http_status'],
            );
        }

        // Duplicate-contact rejection: the sub-account has "Allow Duplicate
        // Contacts" turned off. GHL returns 400/422 with the existing contact
        // id in the response body so we can recover gracefully.
        $duplicate_id = self::extract_duplicate_contact_id($response['body']);
        if ($duplicate_id !== null) {
            return array(
                'action'      => 'matched',
                'contact_id'  => $duplicate_id,
                'message'     => 'Existing contact (duplicate-protection).',
                'http_status' => $response['http_status'],
            );
        }

        return array(
            'action'      => 'failed',
            'contact_id'  => null,
            'message'     => $response['error'] ?: ('HTTP ' . $response['http_status']),
            'http_status' => $response['http_status'],
        );
    }

    /**
     * GHL's "does not allow duplicated contacts" error embeds the existing
     * contactId in different shapes depending on the endpoint version.
     */
    public static function extract_duplicate_contact_id($body)
    {
        if (!is_array($body)) { return null; }
        $candidates = array(
            isset($body['meta']['contactId'])   ? $body['meta']['contactId']   : null,
            isset($body['meta']['contact_id'])  ? $body['meta']['contact_id']  : null,
            isset($body['contactId'])           ? $body['contactId']           : null,
            isset($body['contact']['id'])       ? $body['contact']['id']       : null,
            isset($body['existingContactId'])   ? $body['existingContactId']   : null,
        );
        foreach ($candidates as $c) {
            if (!empty($c)) { return (string) $c; }
        }
        return null;
    }

    /**
     * PUT /contacts/{contact_id} — pushes the latest phone/email/name to GHL.
     * Returns array(ok, http_status, message).
     */
    public function update_contact($contact_id, $row, $config)
    {
        $payload = self::build_update_contact_payload($row);
        if (empty($payload)) {
            return array('ok' => true, 'http_status' => 0, 'message' => 'No fields to update.');
        }
        $response = $this->ghl_request('PUT', '/contacts/' . rawurlencode($contact_id), $payload, $config);
        $ok = ($response['http_status'] >= 200 && $response['http_status'] < 300);
        return array(
            'ok'          => $ok,
            'http_status' => $response['http_status'],
            'message'     => $ok ? null : ($response['error'] ?: ('HTTP ' . $response['http_status'])),
        );
    }

    /**
     * POST /contacts/{contact_id}/tags
     */
    public function apply_tag($contact_id, $tag, $config)
    {
        $response = $this->ghl_post('/contacts/' . rawurlencode($contact_id) . '/tags', array(
            'tags' => array($tag),
        ), $config);

        $ok = ($response['http_status'] >= 200 && $response['http_status'] < 300);
        return array(
            'ok'          => $ok,
            'http_status' => $response['http_status'],
            'message'     => $ok ? null : ($response['error'] ?: ('HTTP ' . $response['http_status'])),
        );
    }

    public static function extract_contact_id($body)
    {
        if (!is_array($body)) { return null; }
        if (isset($body['contact']['id']))  { return (string) $body['contact']['id']; }
        if (isset($body['contact']['_id'])) { return (string) $body['contact']['_id']; }
        if (isset($body['id']))             { return (string) $body['id']; }
        return null;
    }

    protected function lookup_existing_ghl_contact($phone_key, $email)
    {
        if (!$this->CI) {
            return null;
        }
        $db = $this->CI->db;

        if ($phone_key !== '') {
            // Match the same last-9-digit normalization Guests_Model uses, so a
            // booking guest's phone resolves to the same GHL contact as the
            // merged guest list view would.
            $sql = "SELECT contact_id FROM ghl_contacts
                    WHERE RIGHT(REGEXP_REPLACE(IFNULL(phone, ''), '[^0-9]', ''), 9) = ?
                    LIMIT 1";
            $row = $db->query($sql, array($phone_key))->row_array();
            if (!empty($row['contact_id'])) {
                return (string) $row['contact_id'];
            }
        }

        if ($email !== '') {
            $sql = "SELECT contact_id FROM ghl_contacts WHERE LOWER(email) = ? LIMIT 1";
            $row = $db->query($sql, array($email))->row_array();
            if (!empty($row['contact_id'])) {
                return (string) $row['contact_id'];
            }
        }

        return null;
    }

    protected function ghl_post($path, $payload, $config)
    {
        return $this->ghl_request('POST', $path, $payload, $config);
    }

    protected function ghl_request($method, $path, $payload, $config)
    {
        $url = rtrim($config['base_url'], '/') . $path;
        $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $config['token'],
            'Version: ' . $config['api_version'],
        );
        $transport = $this->httpTransport;
        $resp = call_user_func($transport, strtoupper($method), $url, $payload, $headers);

        return array(
            'http_status' => isset($resp['status']) ? (int) $resp['status'] : 0,
            'body'        => isset($resp['body']) ? $resp['body'] : array(),
            'error'       => isset($resp['error']) ? $resp['error'] : null,
        );
    }

    protected function getCodeDateTime($format = 'Y-m-d H:i:s')
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur')))->format($format);
    }

    public function curlRequest($method, $url, $payload = array(), $headers = array())
    {
        $ch = curl_init();
        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        } elseif (strtoupper($method) !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            if (!empty($payload)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            }
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) { $decoded = array(); }

        if ($error === null && $status >= 400) {
            $error = isset($decoded['message']) ? $decoded['message'] : 'HTTP ' . $status;
        }

        return array('status' => $status, 'body' => $decoded, 'error' => $error);
    }
}
