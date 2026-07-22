<?php
/**
 * Run with: php tests/helpers/CampaignGhlSyncServiceTest.php
 *
 * Pins the pure logic in GhlCampaignSyncService that can be exercised without
 * a live GHL endpoint or a CodeIgniter bootstrap. The DB lookup and the
 * full sync_campaign() loop are exercised manually end-to-end against a real
 * sub-account (see plan file).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

if (!class_exists('CI_Model')) {
    class CI_Model {}
}

require_once __DIR__ . '/../../application/libraries/GhlCampaignSyncService.php';

/**
 * Stubs the CI superglobal so the service can be instantiated, and overrides
 * the DB lookup with a programmable return value. Lets us drive
 * find_or_create_contact() through every branch.
 */
class StubGhlCampaignSyncService extends GhlCampaignSyncService
{
    public $lookupReturn = null;

    public function __construct($config = array())
    {
        $this->httpTransport = isset($config['httpTransport']) && is_callable($config['httpTransport'])
            ? $config['httpTransport']
            : null;
    }

    protected function lookup_existing_ghl_contact($phone_key, $email)
    {
        return $this->lookupReturn;
    }
}

$assertions = array();
$transportCalls = array();
$fakeTransport = function($method, $url, $payload, $headers) use (&$transportCalls) {
    $transportCalls[] = array('method' => $method, 'url' => $url, 'payload' => $payload, 'headers' => $headers);
    if (strpos($url, '/contacts/') !== false && strpos($url, '/tags') === false) {
        return array('status' => 201, 'body' => array('contact' => array('id' => 'ghl_new_42')), 'error' => null);
    }
    if (strpos($url, '/tags') !== false) {
        return array('status' => 201, 'body' => array(), 'error' => null);
    }
    return array('status' => 500, 'body' => array(), 'error' => 'unexpected url');
};

// -- build_tag_name ---------------------------------------------------------
$assertions['tag with default prefix']           = GhlCampaignSyncService::build_tag_name('', 42) === 'campaign-42';
$assertions['tag with custom prefix']            = GhlCampaignSyncService::build_tag_name('promo-', 7) === 'promo-7';
$assertions['tag coerces id to int']             = GhlCampaignSyncService::build_tag_name('p-', '15abc') === 'p-15';
$assertions['fixed tag overrides prefix']        = GhlCampaignSyncService::build_tag_name('campaign-', 42, 'whatsapp-blast') === 'whatsapp-blast';
$assertions['blank fixed tag falls back']        = GhlCampaignSyncService::build_tag_name('campaign-', 42, '   ') === 'campaign-42';

// -- normalize_phone_key ----------------------------------------------------
$assertions['phone strips non-digits to last 9'] = GhlCampaignSyncService::normalize_phone_key('+60 12-345 6789') === '123456789';
$assertions['phone short kept as is']            = GhlCampaignSyncService::normalize_phone_key('+60123') === '60123';
$assertions['phone empty returns empty']         = GhlCampaignSyncService::normalize_phone_key('') === '';
$assertions['phone null returns empty']          = GhlCampaignSyncService::normalize_phone_key(null) === '';

// -- build_create_contact_payload ------------------------------------------
$p = GhlCampaignSyncService::build_create_contact_payload('LOC1', array(
    'GuestName'  => 'Alice Wong',
    'ContactNum' => '+60123456789',
    'Email'      => 'ALICE@X.COM',
));
$assertions['payload firstName split']  = ($p['firstName'] === 'Alice');
$assertions['payload lastName split']   = ($p['lastName']  === 'Wong');
$assertions['payload phone kept']       = ($p['phone']     === '+60123456789');
$assertions['payload email lowercased'] = ($p['email']     === 'alice@x.com');
$assertions['payload locationId set']   = ($p['locationId'] === 'LOC1');

$p2 = GhlCampaignSyncService::build_create_contact_payload('LOC1', array('GuestName' => 'Bob', 'ContactNum' => '', 'Email' => ''));
$assertions['payload omits empty phone']  = !array_key_exists('phone', $p2);
$assertions['payload omits empty email']  = !array_key_exists('email', $p2);
$assertions['payload omits empty last']   = !array_key_exists('lastName', $p2);
$assertions['payload single-word first']  = ($p2['firstName'] === 'Bob');

// -- extract_contact_id -----------------------------------------------------
$assertions['extract from contact.id']  = GhlCampaignSyncService::extract_contact_id(array('contact' => array('id'  => 'x1'))) === 'x1';
$assertions['extract from contact._id'] = GhlCampaignSyncService::extract_contact_id(array('contact' => array('_id' => 'x2'))) === 'x2';
$assertions['extract from top-level id']= GhlCampaignSyncService::extract_contact_id(array('id' => 'x3')) === 'x3';
$assertions['extract returns null on miss'] = GhlCampaignSyncService::extract_contact_id(array()) === null;

// -- find_or_create_contact: skipped when no phone + no email ---------------
$svc = new StubGhlCampaignSyncService(array('httpTransport' => $fakeTransport));
$out = $svc->find_or_create_contact(
    array('GuestName' => 'X', 'ContactNum' => '', 'Email' => '', 'DedupKey' => 'row:1'),
    array('base_url' => GhlCampaignSyncService::BASE_URL, 'token' => 't', 'location_id' => 'L', 'api_version' => 'v', 'tag_prefix' => 'campaign-')
);
$assertions['skipped when no phone+email'] = ($out['action'] === 'skipped' && $out['contact_id'] === null);

// -- find_or_create_contact: matched when lookup finds existing contact -----
$svc = new StubGhlCampaignSyncService(array('httpTransport' => $fakeTransport));
$svc->lookupReturn = 'ghl_existing_99';
$out = $svc->find_or_create_contact(
    array('GuestName' => 'Alice', 'ContactNum' => '+60123', 'Email' => '', 'DedupKey' => '123'),
    array('base_url' => GhlCampaignSyncService::BASE_URL, 'token' => 't', 'location_id' => 'L', 'api_version' => 'v', 'tag_prefix' => 'campaign-')
);
$assertions['matched returns existing id'] = ($out['action'] === 'matched' && $out['contact_id'] === 'ghl_existing_99');

// -- find_or_create_contact: created when lookup misses and POST returns 201 -
$transportCalls = array();
$svc = new StubGhlCampaignSyncService(array('httpTransport' => $fakeTransport));
$svc->lookupReturn = null;
$out = $svc->find_or_create_contact(
    array('GuestName' => 'Bob Lee', 'ContactNum' => '+60111', 'Email' => 'bob@x.com', 'DedupKey' => 'b'),
    array('base_url' => GhlCampaignSyncService::BASE_URL, 'token' => 't', 'location_id' => 'L', 'api_version' => 'v', 'tag_prefix' => 'campaign-')
);
$assertions['created when POST 201']           = ($out['action'] === 'created' && $out['contact_id'] === 'ghl_new_42');
$assertions['POST went to /contacts/']         = (strpos($transportCalls[0]['url'], '/contacts/') !== false);
$assertions['POST method is POST']             = ($transportCalls[0]['method'] === 'POST');
$assertions['POST carries bearer auth header'] = !empty(array_filter($transportCalls[0]['headers'], function($h) {
    return strpos($h, 'Authorization: Bearer ') === 0;
}));

// -- find_or_create_contact: failed on 4xx ----------------------------------
$failTransport = function($m, $u, $p, $h) {
    return array('status' => 400, 'body' => array('message' => 'bad payload'), 'error' => 'bad payload');
};
$svc = new StubGhlCampaignSyncService(array('httpTransport' => $failTransport));
$svc->lookupReturn = null;
$out = $svc->find_or_create_contact(
    array('GuestName' => 'C', 'ContactNum' => '+60111', 'Email' => '', 'DedupKey' => 'c'),
    array('base_url' => GhlCampaignSyncService::BASE_URL, 'token' => 't', 'location_id' => 'L', 'api_version' => 'v', 'tag_prefix' => 'campaign-')
);
$assertions['failed on 4xx']             = ($out['action'] === 'failed' && $out['contact_id'] === null);
$assertions['failed includes http_status'] = ($out['http_status'] === 400);

// -- find_or_create_contact: duplicate-contact rejection is recovered -------
$dupTransport = function($m, $u, $p, $h) {
    return array(
        'status' => 400,
        'body' => array(
            'message' => 'This location does not allow duplicated contacts.',
            'meta'    => array('contactId' => 'ghl_existing_dup_77'),
        ),
        'error' => 'This location does not allow duplicated contacts.',
    );
};
$svc = new StubGhlCampaignSyncService(array('httpTransport' => $dupTransport));
$svc->lookupReturn = null;
$out = $svc->find_or_create_contact(
    array('GuestName' => 'Kelvin', 'ContactNum' => '+60111', 'Email' => '', 'DedupKey' => 'k'),
    array('base_url' => GhlCampaignSyncService::BASE_URL, 'token' => 't', 'location_id' => 'L', 'api_version' => 'v', 'tag_prefix' => 'campaign-')
);
$assertions['duplicate recovers to matched']     = ($out['action'] === 'matched');
$assertions['duplicate uses existing contact id']= ($out['contact_id'] === 'ghl_existing_dup_77');

$assertions['extract_dup_id from meta.contactId']  = GhlCampaignSyncService::extract_duplicate_contact_id(array('meta' => array('contactId' => 'a'))) === 'a';
$assertions['extract_dup_id from contactId']       = GhlCampaignSyncService::extract_duplicate_contact_id(array('contactId' => 'b')) === 'b';
$assertions['extract_dup_id returns null on miss'] = GhlCampaignSyncService::extract_duplicate_contact_id(array('message' => 'x')) === null;

// -- apply_tag: happy path --------------------------------------------------
$transportCalls = array();
$svc = new StubGhlCampaignSyncService(array('httpTransport' => $fakeTransport));
$r = $svc->apply_tag('ghl_xyz', 'campaign-7', array('base_url' => GhlCampaignSyncService::BASE_URL, 'token' => 't', 'location_id' => 'L', 'api_version' => 'v'));
$assertions['apply_tag ok=true on 201']      = ($r['ok'] === true && $r['http_status'] === 201);
$assertions['apply_tag hits /tags endpoint'] = (substr($transportCalls[0]['url'], -strlen('/ghl_xyz/tags')) === '/ghl_xyz/tags');
$assertions['apply_tag payload has tag']     = ($transportCalls[0]['payload'] === array('tags' => array('campaign-7')));

// -- apply_tag: error path --------------------------------------------------
$svc = new StubGhlCampaignSyncService(array('httpTransport' => $failTransport));
$r = $svc->apply_tag('ghl_xyz', 'campaign-7', array('base_url' => GhlCampaignSyncService::BASE_URL, 'token' => 't', 'location_id' => 'L', 'api_version' => 'v'));
$assertions['apply_tag ok=false on 400']   = ($r['ok'] === false);
$assertions['apply_tag bubbles message']   = ($r['message'] === 'bad payload' || strpos($r['message'], 'HTTP') === 0);

// -- build_update_contact_payload -------------------------------------------
$up = GhlCampaignSyncService::build_update_contact_payload(array(
    'GuestName'  => 'Kelvin Chong Mun Hau',
    'ContactNum' => '+60123456789',
    'Email'      => 'KELVIN@X.COM',
));
$assertions['update payload firstName']      = ($up['firstName'] === 'Kelvin');
$assertions['update payload lastName joined']= ($up['lastName']  === 'Chong Mun Hau');
$assertions['update payload phone preserved']= ($up['phone']     === '+60123456789');
$assertions['update payload email lowered']  = ($up['email']     === 'kelvin@x.com');
$assertions['update payload omits locationId']= !array_key_exists('locationId', $up);

$upBlank = GhlCampaignSyncService::build_update_contact_payload(array('GuestName' => '', 'ContactNum' => '', 'Email' => ''));
$assertions['update payload empty when all blank'] = ($upBlank === array());

// -- update_contact: PUT goes through with auth & payload -------------------
$putCalls = array();
$putTransport = function($method, $url, $payload, $headers) use (&$putCalls) {
    $putCalls[] = array('method' => $method, 'url' => $url, 'payload' => $payload, 'headers' => $headers);
    return array('status' => 200, 'body' => array('contact' => array('id' => 'ghl_xyz')), 'error' => null);
};
$svc = new StubGhlCampaignSyncService(array('httpTransport' => $putTransport));
$r = $svc->update_contact('ghl_xyz',
    array('GuestName' => 'Bob Lee', 'ContactNum' => '+60111', 'Email' => 'b@x.com'),
    array('base_url' => GhlCampaignSyncService::BASE_URL, 'token' => 't', 'location_id' => 'L', 'api_version' => 'v')
);
$assertions['update_contact ok on 200']          = ($r['ok'] === true && $r['http_status'] === 200);
$assertions['update_contact uses PUT']           = ($putCalls[0]['method'] === 'PUT');
$assertions['update_contact hits /contacts/id']  = (substr($putCalls[0]['url'], -strlen('/contacts/ghl_xyz')) === '/contacts/ghl_xyz');
$assertions['update_contact carries phone']      = ($putCalls[0]['payload']['phone'] === '+60111');
$assertions['update_contact carries bearer']     = !empty(array_filter($putCalls[0]['headers'], function($h) {
    return strpos($h, 'Authorization: Bearer ') === 0;
}));

// -- update_contact: no-op when payload is empty ----------------------------
$putCalls = array();
$svc = new StubGhlCampaignSyncService(array('httpTransport' => $putTransport));
$r = $svc->update_contact('ghl_xyz',
    array('GuestName' => '', 'ContactNum' => '', 'Email' => ''),
    array('base_url' => GhlCampaignSyncService::BASE_URL, 'token' => 't', 'location_id' => 'L', 'api_version' => 'v')
);
$assertions['update_contact no-op when blank ok'] = ($r['ok'] === true && count($putCalls) === 0);

// -- enrol_in_workflow: happy path ------------------------------------------
$enrolCalls = array();
$enrolTransport = function($method, $url, $payload, $headers) use (&$enrolCalls) {
    $enrolCalls[] = array('method' => $method, 'url' => $url, 'payload' => $payload, 'headers' => $headers);
    return array('status' => 200, 'body' => array(), 'error' => null);
};
$svc = new StubGhlCampaignSyncService(array('httpTransport' => $enrolTransport));
$r = $svc->enrol_in_workflow('ghl_abc', 'wf_xyz', array(
    'base_url' => GhlCampaignSyncService::BASE_URL, 'token' => 't', 'location_id' => 'L', 'api_version' => 'v'
));
$assertions['enrol_in_workflow ok on 200']             = ($r['ok'] === true && $r['http_status'] === 200);
$assertions['enrol_in_workflow uses POST']             = ($enrolCalls[0]['method'] === 'POST');
$assertions['enrol_in_workflow hits correct path']     = (substr($enrolCalls[0]['url'], -strlen('/contacts/ghl_abc/workflow/wf_xyz')) === '/contacts/ghl_abc/workflow/wf_xyz');
$assertions['enrol_in_workflow carries bearer header'] = !empty(array_filter($enrolCalls[0]['headers'], function($h) {
    return strpos($h, 'Authorization: Bearer ') === 0;
}));

// -- enrol_in_workflow: error path ------------------------------------------
$enrolFail = function($m, $u, $p, $h) {
    return array('status' => 404, 'body' => array('message' => 'workflow not found'), 'error' => 'workflow not found');
};
$svc = new StubGhlCampaignSyncService(array('httpTransport' => $enrolFail));
$r = $svc->enrol_in_workflow('ghl_abc', 'wf_missing', array(
    'base_url' => GhlCampaignSyncService::BASE_URL, 'token' => 't', 'location_id' => 'L', 'api_version' => 'v'
));
$assertions['enrol_in_workflow ok=false on 404']  = ($r['ok'] === false && $r['http_status'] === 404);
$assertions['enrol_in_workflow message bubbles']  = ($r['message'] === 'workflow not found');

// ===========================================================================
// Background-worker phase tests: enqueue_run / process_chunk / complete_run.
// These drive the new chunked sync flow with in-memory fakes for the CI
// superglobal, the two models, and the env config so the pure orchestration
// logic can be exercised without a CodeIgniter bootstrap.
// ===========================================================================

if (!function_exists('get_env')) {
    function get_env($key)
    {
        return isset($GLOBALS['FAKE_ENV'][$key]) ? $GLOBALS['FAKE_ENV'][$key] : '';
    }
}
if (!function_exists('get_instance')) {
    function get_instance() { return $GLOBALS['FAKE_CI']; }
}

class FakeCampaignModel
{
    public $campaign = null;     // stdClass with Status, GhlWorkflowID
    public $total    = 0;        // returned by Count_Campaign_Guests
    public $guests   = array();  // full list; chunked below
    public function Read_Campaign($id)                  { return $this->campaign; }
    public function Count_Campaign_Guests($id)          { return (int) $this->total; }
    public function Read_Campaign_Guests($id)           { return $this->guests; }
    public function Read_Campaign_Guests_Chunk($id, $offset, $limit) {
        return array_slice($this->guests, (int) $offset, (int) $limit);
    }
}

// Stand-in for Campaign_Ghl_Sync_Model. Records every call and keeps the
// run_summary row in memory so the worker can read offsets back.
class FakeSyncLogModel
{
    public $logs = array();
    public $run = array(
        'RunID'         => 'run-1',
        'CampaignID'    => 0,
        'CurrentOffset' => 0,
        'TotalGuests'   => 0,
        'MatchedCount'  => 0,
        'CreatedCount'  => 0,
        'EnrolledCount' => 0,
        'FailedCount'   => 0,
        'WorkflowID'    => '',
        'Status'        => 'pending',
        'CompletedAt'   => null,
        'ClaimedAt'     => null,
    );
    public function log_row($data) { $this->logs[] = $data; return count($this->logs); }
    public function create_run_summary($campaign_id, $run_id, $workflow_id, $total_guests, $admin_id)
    {
        $this->run['RunID']        = $run_id;
        $this->run['CampaignID']   = (int) $campaign_id;
        $this->run['TotalGuests']  = (int) $total_guests;
        $this->run['CurrentOffset'] = 0;
        $this->run['WorkflowID']   = $workflow_id;
        $this->run['Status']       = 'pending';
        return 1;
    }
    public function increment_run_counters($run_id, $deltas, $current_offset)
    {
        $this->run['MatchedCount']  += isset($deltas['matched'])  ? (int) $deltas['matched']  : 0;
        $this->run['CreatedCount']  += isset($deltas['created'])  ? (int) $deltas['created']  : 0;
        $this->run['EnrolledCount'] += isset($deltas['enrolled']) ? (int) $deltas['enrolled'] : 0;
        $this->run['FailedCount']   += isset($deltas['failed'])   ? (int) $deltas['failed']   : 0;
        $this->run['CurrentOffset']  = (int) $current_offset;
        return 1;
    }
    public function finalize_run($run_id, $status, $completed_at = null)
    {
        $this->run['Status']      = $status;
        $this->run['CompletedAt'] = $completed_at ?: '2026-05-17 00:00:00';
        $this->run['ClaimedAt']   = null;
        return 1;
    }
    public function get_run_state($run_id) { return (object) $this->run; }
}
class FakeGhlSyncModel
{
    public function generate_run_id($prefix) { return $prefix . '-fixed'; }
}

class FakeLoader { public function model($_) {} }
class FakeCI
{
    public $Campaign_Model;
    public $Campaign_Ghl_Sync_Model;
    public $Ghl_Sync_Model;
    public $load;
    public function __construct()
    {
        $this->Campaign_Model          = new FakeCampaignModel();
        $this->Campaign_Ghl_Sync_Model = new FakeSyncLogModel();
        $this->Ghl_Sync_Model          = new FakeGhlSyncModel();
        $this->load                    = new FakeLoader();
    }
}

// Subclass that opts into real CI wiring (so the orchestration code can talk
// to the fake models) and exposes a tunable HTTP transport.
class WorkerStubService extends GhlCampaignSyncService
{
    public function __construct($config = array())
    {
        $this->CI = $GLOBALS['FAKE_CI'];
        $this->httpTransport = isset($config['httpTransport']) ? $config['httpTransport'] : null;
        $this->per_guest_delay_us = 0;
    }
    // Bypass the DB cache lookup so process_chunk always hits the POST /contacts/
    // path (or the duplicate path if the transport returns 400) — keeps these
    // tests focused on the orchestration, not on contact-lookup mechanics.
    protected function lookup_existing_ghl_contact($p, $e) { return null; }
}

$GLOBALS['FAKE_ENV'] = array(
    'GHL_API_TOKEN'   => 'token',
    'GHL_LOCATION_ID' => 'loc',
);
$GLOBALS['FAKE_CI']  = new FakeCI();

// -- enqueue_run: rejects when GHL not configured ---------------------------
$GLOBALS['FAKE_ENV']['GHL_API_TOKEN'] = '';
$svc = new WorkerStubService();
$r = $svc->enqueue_run(1, 9);
$assertions['enqueue_run rejects when no GHL_API_TOKEN'] = ($r['ok'] === false && strpos($r['message'], 'GHL is not configured') !== false);
$GLOBALS['FAKE_ENV']['GHL_API_TOKEN'] = 'token';

// -- enqueue_run: rejects when workflow id missing --------------------------
$GLOBALS['FAKE_CI']->Campaign_Model->campaign = (object) array('Status' => 'Y', 'GhlWorkflowID' => '');
$GLOBALS['FAKE_CI']->Campaign_Model->total = 3;
$svc = new WorkerStubService();
$r = $svc->enqueue_run(1, 9);
$assertions['enqueue_run rejects empty GhlWorkflowID'] = ($r['ok'] === false && strpos($r['message'], 'Set the GHL Workflow ID') !== false);

// -- enqueue_run: rejects when no guests ------------------------------------
$GLOBALS['FAKE_CI']->Campaign_Model->campaign = (object) array('Status' => 'Y', 'GhlWorkflowID' => 'wf_1');
$GLOBALS['FAKE_CI']->Campaign_Model->total = 0;
$svc = new WorkerStubService();
$r = $svc->enqueue_run(1, 9);
$assertions['enqueue_run rejects empty roster'] = ($r['ok'] === false && strpos($r['message'], 'no guests') !== false);

// -- enqueue_run: happy path creates pending run with total ----------------
$GLOBALS['FAKE_CI']->Campaign_Model->campaign = (object) array('Status' => 'Y', 'GhlWorkflowID' => 'wf_42');
$GLOBALS['FAKE_CI']->Campaign_Model->total = 25;
$GLOBALS['FAKE_CI']->Campaign_Ghl_Sync_Model = new FakeSyncLogModel();
$svc = new WorkerStubService();
$r = $svc->enqueue_run(7, 9);
$assertions['enqueue_run ok=true']             = ($r['ok'] === true);
$assertions['enqueue_run carries total']       = ($r['total'] === 25);
$assertions['enqueue_run carries workflow_id'] = ($r['workflow_id'] === 'wf_42');
$assertions['enqueue_run carries run_id']      = (strpos($r['run_id'], 'campaign_sync_7') === 0);
$assertions['enqueue_run created run row in pending state'] = (
    $GLOBALS['FAKE_CI']->Campaign_Ghl_Sync_Model->run['Status']       === 'pending' &&
    $GLOBALS['FAKE_CI']->Campaign_Ghl_Sync_Model->run['CurrentOffset'] === 0 &&
    $GLOBALS['FAKE_CI']->Campaign_Ghl_Sync_Model->run['TotalGuests']   === 25
);

// -- process_chunk: advances offset through 3 chunks ------------------------
function make_guests($n) {
    $g = array();
    for ($i = 1; $i <= $n; $i++) {
        $g[] = (object) array(
            'DedupKey'   => 'k' . $i,
            'GuestName'  => 'G' . $i,
            'ContactNum' => '+60111' . $i,
            'Email'      => '',
        );
    }
    return $g;
}
$GLOBALS['FAKE_CI']->Campaign_Model->campaign = (object) array('Status' => 'Y', 'GhlWorkflowID' => 'wf_42');
$GLOBALS['FAKE_CI']->Campaign_Model->total    = 25;
$GLOBALS['FAKE_CI']->Campaign_Model->guests   = make_guests(25);
$GLOBALS['FAKE_CI']->Campaign_Ghl_Sync_Model  = new FakeSyncLogModel();
$GLOBALS['FAKE_CI']->Campaign_Ghl_Sync_Model->run['TotalGuests'] = 25;

$okTransport = function($method, $url, $payload, $headers) {
    if (strpos($url, '/workflow/') !== false) {
        return array('status' => 200, 'body' => array(), 'error' => null);
    }
    return array('status' => 201, 'body' => array('contact' => array('id' => 'ghl_x')), 'error' => null);
};
$svc = new WorkerStubService(array('httpTransport' => $okTransport));

$c1 = $svc->process_chunk(7, 'run-1', 0, 10);
$c2 = $svc->process_chunk(7, 'run-1', $c1['next_offset'], 10);
$c3 = $svc->process_chunk(7, 'run-1', $c2['next_offset'], 10);

$assertions['process_chunk first chunk processed 10']  = ($c1['processed'] === 10 && $c1['next_offset'] === 10 && $c1['done'] === false);
$assertions['process_chunk second chunk processed 10'] = ($c2['processed'] === 10 && $c2['next_offset'] === 20 && $c2['done'] === false);
$assertions['process_chunk final chunk processed 5']   = ($c3['processed'] === 5  && $c3['next_offset'] === 25 && $c3['done'] === true);

// -- process_chunk: increments counters via model ---------------------------
$assertions['process_chunk created counter accumulated'] = (
    $GLOBALS['FAKE_CI']->Campaign_Ghl_Sync_Model->run['CreatedCount'] === 25
);
$assertions['process_chunk enrolled counter accumulated'] = (
    $GLOBALS['FAKE_CI']->Campaign_Ghl_Sync_Model->run['EnrolledCount'] === 25
);
$assertions['process_chunk offset cursor advanced to 25'] = (
    $GLOBALS['FAKE_CI']->Campaign_Ghl_Sync_Model->run['CurrentOffset'] === 25
);

// -- process_chunk: a failing guest doesn't abort the chunk -----------------
$GLOBALS['FAKE_CI']->Campaign_Model->guests  = make_guests(3);
$GLOBALS['FAKE_CI']->Campaign_Model->total   = 3;
$GLOBALS['FAKE_CI']->Campaign_Ghl_Sync_Model = new FakeSyncLogModel();
$GLOBALS['FAKE_CI']->Campaign_Ghl_Sync_Model->run['TotalGuests'] = 3;

$callIndex = 0;
$mixedTransport = function($method, $url, $payload, $headers) use (&$callIndex) {
    $callIndex++;
    // For the second guest's create POST (3rd or 4th call depending), fail.
    if (strpos($url, '/workflow/') === false && $callIndex === 3) {
        return array('status' => 500, 'body' => array('message' => 'boom'), 'error' => 'boom');
    }
    if (strpos($url, '/workflow/') !== false) {
        return array('status' => 200, 'body' => array(), 'error' => null);
    }
    return array('status' => 201, 'body' => array('contact' => array('id' => 'ghl_y')), 'error' => null);
};
$svc = new WorkerStubService(array('httpTransport' => $mixedTransport));
$c   = $svc->process_chunk(7, 'run-2', 0, 10);
$assertions['process_chunk continues past a failed guest'] = (
    $c['processed'] === 3 && $c['next_offset'] === 3 && $c['done'] === true
);
$assertions['process_chunk failed counter incremented for the bad guest'] = (
    $GLOBALS['FAKE_CI']->Campaign_Ghl_Sync_Model->run['FailedCount'] >= 1
);

// -- run_to_completion: respects a tight time budget ------------------------
$GLOBALS['FAKE_CI']->Campaign_Model->guests = make_guests(50);
$GLOBALS['FAKE_CI']->Campaign_Model->total  = 50;
$GLOBALS['FAKE_CI']->Campaign_Ghl_Sync_Model = new FakeSyncLogModel();
$GLOBALS['FAKE_CI']->Campaign_Ghl_Sync_Model->run['TotalGuests'] = 50;

$slowTransport = function($method, $url, $payload, $headers) {
    usleep(60000); // 60ms — exceeds the 50ms budget after 1 chunk
    if (strpos($url, '/workflow/') !== false) {
        return array('status' => 200, 'body' => array(), 'error' => null);
    }
    return array('status' => 201, 'body' => array('contact' => array('id' => 'ghl_z')), 'error' => null);
};
$svc = new WorkerStubService(array('httpTransport' => $slowTransport));
$start = microtime(true);
$r = $svc->run_to_completion(7, 'run-3', 9, /*budget*/ 0.05, /*chunk*/ 5);
$elapsed = microtime(true) - $start;
$assertions['run_to_completion returns done=false when budget exhausted'] = ($r['done'] === false && $r['next_offset'] > 0 && $r['next_offset'] < 50);
$assertions['run_to_completion exits within ~2x the budget']              = ($elapsed < 5.0);

// -- complete_run: flips to completed when enrolled > 0 ---------------------
$GLOBALS['FAKE_CI']->Campaign_Ghl_Sync_Model = new FakeSyncLogModel();
$GLOBALS['FAKE_CI']->Campaign_Ghl_Sync_Model->run['EnrolledCount'] = 5;
$svc = new WorkerStubService();
$svc->complete_run('run-4', 9);
$assertions['complete_run flips to completed when enrolled > 0'] = (
    $GLOBALS['FAKE_CI']->Campaign_Ghl_Sync_Model->run['Status'] === 'completed'
);

// -- complete_run: flips to failed when nothing enrolled --------------------
$GLOBALS['FAKE_CI']->Campaign_Ghl_Sync_Model = new FakeSyncLogModel();
$GLOBALS['FAKE_CI']->Campaign_Ghl_Sync_Model->run['EnrolledCount'] = 0;
$GLOBALS['FAKE_CI']->Campaign_Ghl_Sync_Model->run['FailedCount']   = 3;
$svc = new WorkerStubService();
$svc->complete_run('run-5', 9);
$assertions['complete_run flips to failed when nothing enrolled'] = (
    $GLOBALS['FAKE_CI']->Campaign_Ghl_Sync_Model->run['Status'] === 'failed'
);

// -- sync_campaign wrapper: full pipeline still passes through chunks -------
$GLOBALS['FAKE_CI']->Campaign_Model->campaign = (object) array('Status' => 'Y', 'GhlWorkflowID' => 'wf_99');
$GLOBALS['FAKE_CI']->Campaign_Model->total    = 6;
$GLOBALS['FAKE_CI']->Campaign_Model->guests   = make_guests(6);
$GLOBALS['FAKE_CI']->Campaign_Ghl_Sync_Model  = new FakeSyncLogModel();
$svc = new WorkerStubService(array('httpTransport' => $okTransport));
$r = $svc->sync_campaign(7, 9);
$assertions['sync_campaign wrapper ok=true with 6 enrolled']  = ($r['ok'] === true && $r['enrolled'] === 6);
$assertions['sync_campaign wrapper run row finalized']         = ($GLOBALS['FAKE_CI']->Campaign_Ghl_Sync_Model->run['Status'] === 'completed');
$assertions['sync_campaign wrapper offset reached total']      = ($GLOBALS['FAKE_CI']->Campaign_Ghl_Sync_Model->run['CurrentOffset'] === 6);

// -- Report -----------------------------------------------------------------
$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) { $failed++; }
}
echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
