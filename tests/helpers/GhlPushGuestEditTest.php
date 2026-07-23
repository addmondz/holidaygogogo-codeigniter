<?php
/**
 * Run with: php tests/helpers/GhlPushGuestEditTest.php
 *
 * Pins GhlCampaignSyncService::push_guest_edit() — the best-effort push of a
 * Guest List inline edit (mobile / name / email) back to the matching GHL
 * contact. Driven without a live GHL endpoint by stubbing the DB lookup and
 * injecting a fake HTTP transport, mirroring CampaignGhlSyncServiceTest.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

if (!class_exists('CI_Model')) {
    class CI_Model {}
}

require_once __DIR__ . '/../../application/libraries/GhlCampaignSyncService.php';

/**
 * Overrides the two boundaries push_guest_edit() touches: the DB contact
 * lookup and (via httpTransport) the outbound HTTP. $CI stays null so the
 * local ghl_contacts write is skipped — proving the method survives without
 * a CodeIgniter DB handle.
 */
class StubPushGhlService extends GhlCampaignSyncService
{
    public $lookupReturn = null;
    public $lookupArgs   = null;

    public function __construct($config = array())
    {
        $this->httpTransport = isset($config['httpTransport']) && is_callable($config['httpTransport'])
            ? $config['httpTransport']
            : null;
    }

    protected function lookup_existing_ghl_contact($phone_key, $email)
    {
        $this->lookupArgs = array('phone_key' => $phone_key, 'email' => $email);
        return $this->lookupReturn;
    }
}

$assertions = array();

$CONFIG = array(
    'base_url'    => GhlCampaignSyncService::BASE_URL,
    'token'       => 'tok',
    'location_id' => 'LOC1',
    'api_version' => '2021-07-28',
    'tag_prefix'  => 'campaign-',
);

$makeTransport = function (&$calls, $status = 200) {
    return function ($method, $url, $payload, $headers) use (&$calls, $status) {
        $calls[] = array('method' => $method, 'url' => $url, 'payload' => $payload);
        return array('status' => $status, 'body' => array('contact' => array('id' => 'c1')), 'error' => $status < 400 ? null : 'boom');
    };
};

// 1) Not configured -> skipped, no HTTP -----------------------------------
$calls = array();
$svc = new StubPushGhlService(array('httpTransport' => $makeTransport($calls)));
$svc->lookupReturn = 'c1';
$out = $svc->push_guest_edit(
    array('phone_key' => '123456789'),
    array('ContactNum' => '+60123456789'),
    array('base_url' => GhlCampaignSyncService::BASE_URL, 'token' => '', 'location_id' => '', 'api_version' => 'v')
);
$assertions['unconfigured -> skipped']       = ($out['action'] === 'skipped');
$assertions['unconfigured -> reason']        = ($out['reason'] === 'not_configured');
$assertions['unconfigured -> no HTTP call']  = (count($calls) === 0);

// 2) Mobile edit, contact matched, PUT 200 -> updated ----------------------
$calls = array();
$svc = new StubPushGhlService(array('httpTransport' => $makeTransport($calls)));
$svc->lookupReturn = 'ghl_77';
$out = $svc->push_guest_edit(array('phone_key' => '123456789'), array('ContactNum' => '+60123456789'), $CONFIG);
$assertions['mobile match -> updated']       = ($out['action'] === 'updated' && $out['contact_id'] === 'ghl_77');
$assertions['mobile -> one PUT call']        = (count($calls) === 1 && $calls[0]['method'] === 'PUT');
$assertions['mobile -> PUT to /contacts/id'] = (strpos($calls[0]['url'], '/contacts/ghl_77') !== false);
$assertions['mobile -> payload has phone']   = (isset($calls[0]['payload']['phone']) && $calls[0]['payload']['phone'] === '+60123456789');
$assertions['mobile -> lookup by phone_key'] = ($svc->lookupArgs['phone_key'] === '123456789');

// 3) Guest not in GHL (lookup miss) -> skipped, no HTTP --------------------
$calls = array();
$svc = new StubPushGhlService(array('httpTransport' => $makeTransport($calls)));
$svc->lookupReturn = null;
$out = $svc->push_guest_edit(array('phone_key' => '999999999'), array('ContactNum' => '+60111'), $CONFIG);
$assertions['no GHL contact -> skipped']     = ($out['action'] === 'skipped');
$assertions['no GHL contact -> reason']      = ($out['reason'] === 'no_contact');
$assertions['no GHL contact -> no HTTP']     = (count($calls) === 0);

// 4) Nothing syncable in the row -> skipped, no HTTP ----------------------
$calls = array();
$svc = new StubPushGhlService(array('httpTransport' => $makeTransport($calls)));
$svc->lookupReturn = 'ghl_77';
$out = $svc->push_guest_edit(array('phone_key' => '123456789'), array('ContactNum' => ''), $CONFIG);
$assertions['empty row -> skipped']          = ($out['action'] === 'skipped');
$assertions['empty row -> reason']           = ($out['reason'] === 'nothing_to_sync');
$assertions['empty row -> no HTTP']          = (count($calls) === 0);

// 5) Name edit -> firstName/lastName payload ------------------------------
$calls = array();
$svc = new StubPushGhlService(array('httpTransport' => $makeTransport($calls)));
$svc->lookupReturn = 'ghl_77';
$out = $svc->push_guest_edit(array('phone_key' => '123456789'), array('GuestName' => 'Alice Wong'), $CONFIG);
$assertions['name -> updated']               = ($out['action'] === 'updated');
$assertions['name -> firstName']             = ($calls[0]['payload']['firstName'] === 'Alice');
$assertions['name -> lastName']              = ($calls[0]['payload']['lastName'] === 'Wong');
$assertions['name -> no phone in payload']   = (!isset($calls[0]['payload']['phone']));

// 6) Email edit -> lowercased email payload -------------------------------
$calls = array();
$svc = new StubPushGhlService(array('httpTransport' => $makeTransport($calls)));
$svc->lookupReturn = 'ghl_77';
$out = $svc->push_guest_edit(array('phone_key' => '123456789'), array('Email' => 'A@X.COM'), $CONFIG);
$assertions['email -> updated']              = ($out['action'] === 'updated');
$assertions['email -> lowercased']           = ($calls[0]['payload']['email'] === 'a@x.com');

// 7) PUT fails (HTTP 400) -> failed ---------------------------------------
$calls = array();
$svc = new StubPushGhlService(array('httpTransport' => $makeTransport($calls, 400)));
$svc->lookupReturn = 'ghl_77';
$out = $svc->push_guest_edit(array('phone_key' => '123456789'), array('ContactNum' => '+60123'), $CONFIG);
$assertions['PUT 400 -> failed']             = ($out['action'] === 'failed');
$assertions['PUT 400 -> http_status 400']    = ((int) $out['http_status'] === 400);

// 8) Email fallback lookup when phone_key blank ---------------------------
$calls = array();
$svc = new StubPushGhlService(array('httpTransport' => $makeTransport($calls)));
$svc->lookupReturn = 'ghl_77';
$out = $svc->push_guest_edit(array('phone_key' => '', 'email' => 'B@Y.COM'), array('Email' => 'b@y.com'), $CONFIG);
$assertions['email lookup arg lowercased']   = ($svc->lookupArgs['email'] === 'b@y.com');

// Report -------------------------------------------------------------------
$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
