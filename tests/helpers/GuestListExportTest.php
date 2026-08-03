<?php
/**
 * Run with: php tests/helpers/GuestListExportTest.php
 *
 * Covers the pure column/format logic behind the Guest List / GHL Leads /
 * Manual Leads Excel export (application/helpers/guest_list_export_helper.php):
 * the per-mode column set and each cell's display formatting (phone, tags, DOB,
 * destination, guest type). The spreadsheet writer itself is not exercised here.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/guest_contact_helper.php';
require_once __DIR__ . '/../../application/helpers/ghl_lead_tags_helper.php';
require_once __DIR__ . '/../../application/helpers/guest_list_export_helper.php';

$US = "\x1f"; // calling code <-> mobile
$RS = "\x1e"; // phone <-> phone

$assertions = array();

// ---- column sets per mode ----------------------------------------------
$guest_headers  = array_column(guest_list_export_columns('guest'), 0);
$ghl_headers    = array_column(guest_list_export_columns('ghl'), 0);
$manual_headers = array_column(guest_list_export_columns('manual'), 0);

$assertions['guest has ALT NAME + EMAIL']  = in_array('ALT NAME', $guest_headers, true) && in_array('EMAIL', $guest_headers, true);
$assertions['guest has DESTINATION']       = in_array('DESTINATION', $guest_headers, true);
$assertions['guest has no TAGS']           = !in_array('TAGS', $guest_headers, true);
$assertions['ghl has TYPE column']         = in_array('TYPE', $ghl_headers, true);
$assertions['manual has no TYPE column']   = !in_array('TYPE', $manual_headers, true);
$assertions['ghl+manual have TAGS/DOB']    = in_array('TAGS', $ghl_headers, true) && in_array('DATE OF BIRTH', $manual_headers, true);
$assertions['leads have no EMAIL column']  = !in_array('EMAIL', $ghl_headers, true) && !in_array('EMAIL', $manual_headers, true);
$assertions['unknown mode -> guest cols']  = array_column(guest_list_export_columns('whatever'), 0) === $guest_headers;

// ---- phone cell: merged multi-number row -------------------------------
$multi = (object) array('ContactNumbers' => '+60' . $US . '0169546738' . $RS . '+60' . $US . '0198887777');
$assertions['phone multi joined w/ " / "'] = guest_list_export_cell($multi, '__phone') === '+60 169546738 / +60 198887777';

// ---- phone cell: single-number fallback (no ContactNumbers) -------------
$single = (object) array('ContactNum' => '0169546738', 'CallingCode' => '+60');
$assertions['phone single fallback']       = guest_list_export_cell($single, '__phone') === '+60 169546738';

// ---- phone cell: no number -> empty ------------------------------------
$nophone = (object) array('ContactNum' => '', 'CallingCode' => '');
$assertions['phone empty -> ""']           = guest_list_export_cell($nophone, '__phone') === '';

// ---- Tags: flatten GROUP_CONCAT of JSON arrays, drop [system] tags ------
$tagged = (object) array('Tags' => "[\"redang\",\"active contacts\"]\n[\"[whatsapp] - lead capture\",\"redang\"]");
$assertions['tags flattened + deduped']    = guest_list_export_cell($tagged, 'Tags') === 'redang, active contacts';
$assertions['tags empty -> ""']            = guest_list_export_cell((object) array('Tags' => null), 'Tags') === '';

// ---- Type: Manual stays Manual, everything else -> GHL ------------------
$assertions['type Manual']                 = guest_list_export_cell((object) array('Type' => 'Manual'), 'Type') === 'Manual';
$assertions['type default -> GHL']         = guest_list_export_cell((object) array('Type' => ''), 'Type') === 'GHL';

// ---- DOB: formatted / invalid dropped ----------------------------------
$assertions['dob formatted d M Y']         = guest_list_export_cell((object) array('DOB' => '1990-05-14'), 'DOB') === date('d M Y', strtotime('1990-05-14'));
$assertions['dob 0000 -> ""']              = guest_list_export_cell((object) array('DOB' => '0000-00-00'), 'DOB') === '';
$assertions['dob blank -> ""']             = guest_list_export_cell((object) array('DOB' => ''), 'DOB') === '';

// ---- Destination: split '||', trim, dedupe, join ', ' -------------------
$dest = (object) array('Destination' => 'Redang||Redang|| - ||Langkawi');
$assertions['destination deduped/joined']  = guest_list_export_cell($dest, 'Destination') === 'Redang, Langkawi';

// ---- GuestType: title-cased --------------------------------------------
$assertions['guesttype ADULT -> Adult']    = guest_list_export_cell((object) array('GuestType' => 'ADULT'), 'GuestType') === 'Adult';
$assertions['guesttype unknown passthru']  = guest_list_export_cell((object) array('GuestType' => 'STAFF'), 'GuestType') === 'STAFF';

// ---- matrix shape: header count == row cell count ----------------------
$rows = array(
    (object) array('Name' => 'Ali', 'Tags' => null, 'Type' => 'Manual', 'Gender' => 'M', 'Language' => 'EN', 'Race' => 'Malay', 'Nationality' => 'MY', 'DOB' => '', 'ContactNum' => '+60123', 'CallingCode' => ''),
);
$mx = guest_list_export_matrix($rows, 'ghl');
$assertions['matrix headers == ghl cols']  = $mx['headers'] === $ghl_headers;
$assertions['matrix row width == headers']  = count($mx['rows'][0]) === count($mx['headers']);
$assertions['matrix name cell correct']     = $mx['rows'][0][0] === 'Ali';
$assertions['matrix empty rows -> []']      = guest_list_export_matrix(array(), 'manual')['rows'] === array();

// ---- report -------------------------------------------------------------
$failed = 0;
foreach ($assertions as $label => $ok) {
    if (!$ok) {
        $failed++;
        echo "FAIL: {$label}\n";
    }
}
if ($failed === 0) {
    echo 'OK: all ' . count($assertions) . " assertions passed\n";
    exit(0);
}
echo "{$failed} of " . count($assertions) . " assertions FAILED\n";
exit(1);
