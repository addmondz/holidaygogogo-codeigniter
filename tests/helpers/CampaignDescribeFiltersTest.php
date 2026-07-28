<?php
/**
 * Run with: php tests/helpers/CampaignDescribeFiltersTest.php
 *
 * Pins Campaign_Model::Describe_Filters — the pure formatter that turns a
 * stored filter snapshot into an ordered list of human-readable
 * {label, values[]} rows for the campaign View page. ID-based filters
 * (destination / source / joined_campaign) resolve against passed lookups;
 * everything else is already display-ready.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

if (!class_exists('CI_Model')) {
    class CI_Model {}
}

require_once __DIR__ . '/../../application/models/Campaign_Model.php';

$lookups = array(
    'destination'     => array('1' => 'A FAMOSA', '2' => 'BANJARAN'),
    'source'          => array('1' => 'WHATSAPP'),
    'joined_campaign' => array('9' => 'Raya Blast'),
);

$assertions = [];

// -- full mixed snapshot -----------------------------------------------------
$rows = Campaign_Model::Describe_Filters(json_encode(array(
    'type'          => 'guest',
    'bc_type'       => 'BOOKING CONFIRMATION',
    'destination'   => array('1', '2'),
    'source'        => array('1'),
    'customer_type' => array('Chinese'),
    'gender'        => array('Male'),
    'min_purchases' => '2',
    'ltv'           => '10000-20000',
    'family_kids'   => '1',
    'campaign_mode' => 'exclude',
)), $lookups);

// index by label for easy lookup
$by = array();
foreach ($rows as $r) { $by[$r['label']] = $r['values']; }

$assertions['type mapped to label']      = isset($by['Guest Type']) && $by['Guest Type'] === array('Booking Guest');
$assertions['bc_type mapped to label']   = isset($by['Booking Type']) && $by['Booking Type'] === array('Booking Confirmation (BC)');
$assertions['destination ids resolved']  = $by['Destination'] === array('A FAMOSA', 'BANJARAN');
$assertions['source id resolved']        = $by['Source'] === array('WHATSAPP');
$assertions['customer type passthrough'] = $by['Customer Type'] === array('Chinese');
$assertions['gender passthrough']        = $by['Gender'] === array('Male');
$assertions['purchase count formatted']  = $by['Purchase Count'] === array('2\xc3\x97 and above') || $by['Purchase Count'][0] === "2\xc3\x97 and above";
$assertions['ltv mapped']                = $by['Lifetime Booking Value'][0] === "RM10k \xe2\x80\x93 20k";
$assertions['family kids shown as flag'] = isset($by['Family with kids']) && $by['Family with kids'] === array('Yes');
$assertions['campaign_mode not a row']   = !isset($by['campaign_mode']) && !isset($by['Campaign Filter']);

// -- "Has email address" toggle shown as a Yes flag when ticked --------------
$he = Campaign_Model::Describe_Filters(json_encode(array('has_email' => '1')), $lookups);
$assertions['has_email shown as flag'] = $he[0]['label'] === 'Has email address' && $he[0]['values'] === array('Yes');
$heOff = Campaign_Model::Describe_Filters(json_encode(array('source' => array('1'), 'has_email' => '')), $lookups);
$heOffLabels = array_map(function ($r) { return $r['label']; }, $heOff);
$assertions['has_email empty omitted'] = !in_array('Has email address', $heOffLabels, true);

// -- Type dropdown also offers "Customer" (records from the customer master) ---
$cust = Campaign_Model::Describe_Filters(json_encode(array('type' => 'customer')), $lookups);
$assertions['type customer mapped'] = $cust[0]['label'] === 'Guest Type' && $cust[0]['values'] === array('Customer');

// -- ordering follows the form layout (type before destination before segments)
$labels = array_map(function ($r) { return $r['label']; }, $rows);
$posType = array_search('Guest Type', $labels);
$posDest = array_search('Destination', $labels);
$posLtv  = array_search('Lifetime Booking Value', $labels);
$assertions['ordered type<dest<ltv'] = ($posType < $posDest) && ($posDest < $posLtv);

// -- bc_type PI/QU map, unknown value falls back to raw -----------------------
$pi = Campaign_Model::Describe_Filters(json_encode(array('bc_type' => 'PROFORMA INVOICE')), $lookups);
$assertions['bc_type PI mapped'] = $pi[0]['label'] === 'Booking Type' && $pi[0]['values'] === array('Proforma Invoice (PI)');
$bcRaw = Campaign_Model::Describe_Filters(json_encode(array('bc_type' => 'WEIRD')), $lookups);
$assertions['bc_type unknown falls back'] = $bcRaw[0]['values'] === array('WEIRD');

// -- unknown ids fall back to the raw id ------------------------------------
$fallback = Campaign_Model::Describe_Filters(json_encode(array(
    'destination' => array('999'),
)), array('destination' => array()));
$assertions['unknown id falls back'] = $fallback[0]['values'] === array('999');

// -- unchecked segment flags are omitted ------------------------------------
$flags = Campaign_Model::Describe_Filters(json_encode(array(
    'source'      => array('1'),
    'family_kids' => '',
    'cancelled'   => '0',
)), $lookups);
$flagLabels = array_map(function ($r) { return $r['label']; }, $flags);
$assertions['empty flag omitted']  = !in_array('Family with kids', $flagLabels, true);
$assertions['zero flag omitted']   = !in_array('Has cancelled BC', $flagLabels, true);

// -- joined campaign carries the include/exclude mode -----------------------
$camp = Campaign_Model::Describe_Filters(json_encode(array(
    'joined_campaign' => array('9'),
    'campaign_mode'   => 'exclude',
)), $lookups);
$assertions['joined campaign labelled exclude'] = $camp[0]['label'] === 'Campaign (Exclude)' && $camp[0]['values'] === array('Raya Blast');

// -- empty / null input ------------------------------------------------------
$assertions['empty json => empty'] = Campaign_Model::Describe_Filters('', $lookups) === array();
$assertions['null => empty']       = Campaign_Model::Describe_Filters(null, $lookups) === array();
$assertions['array input accepted'] = count(Campaign_Model::Describe_Filters(array('gender' => array('Female')), $lookups)) === 1;

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) { $failed++; }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
