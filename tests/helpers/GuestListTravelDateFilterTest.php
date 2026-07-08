<?php
/**
 * Run with: php tests/helpers/GuestListTravelDateFilterTest.php
 *
 * Covers guest_list_travel_date_filter_value() — the pure builder behind the
 * Guest List "click a travel date to group everyone travelling then" feature.
 * The value it produces MUST round-trip through guest_list_parse_date_range()
 * (the shared filter parser) so a clicked link reproduces the exact travel
 * window the row displayed.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/guest_contact_helper.php';

$assertions = array();

// Both ends present -> "DD/MM/YYYY - DD/MM/YYYY" ---------------------------
$assertions['both ends -> full range'] =
    guest_list_travel_date_filter_value('2026-01-05', '2026-01-09') === '05/01/2026 - 09/01/2026';

// Start only -> collapse to a single-day range ----------------------------
$assertions['start only -> same day range'] =
    guest_list_travel_date_filter_value('2026-01-05', '') === '05/01/2026 - 05/01/2026';

// End only -> collapse to a single-day range ------------------------------
$assertions['end only -> same day range'] =
    guest_list_travel_date_filter_value('', '2026-01-09') === '09/01/2026 - 09/01/2026';

// Zero / empty dates are not a usable filter ------------------------------
$assertions['both empty -> empty string'] =
    guest_list_travel_date_filter_value('', '') === '';
$assertions['zero dates -> empty string'] =
    guest_list_travel_date_filter_value('0000-00-00', '0000-00-00') === '';

// Round-trip: value -> parser gives back the same Y-m-d window ------------
$rt = guest_list_parse_date_range(guest_list_travel_date_filter_value('2026-01-05', '2026-01-09'));
$assertions['round-trips through parser'] =
    is_array($rt) && $rt[0] === '2026-01-05' && $rt[1] === '2026-01-09';

$rt1 = guest_list_parse_date_range(guest_list_travel_date_filter_value('2026-01-05', ''));
$assertions['single-day round-trips'] =
    is_array($rt1) && $rt1[0] === '2026-01-05' && $rt1[1] === '2026-01-05';

// --- report ---------------------------------------------------------------
$failed = 0;
foreach ($assertions as $name => $ok) {
    if (!$ok) { $failed++; echo "FAIL: {$name}\n"; }
}
if ($failed === 0) {
    echo "OK: all " . count($assertions) . " assertions passed\n";
    exit(0);
}
echo "\n{$failed} assertion(s) failed\n";
exit(1);
