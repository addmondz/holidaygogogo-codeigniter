<?php
/**
 * Run with: php tests/helpers/BookingSearchStatusScopeTest.php
 *
 * Locks the contract that the booking listing's GLOBAL SEARCH box (the
 * DataTables "Search:" input, sent as search[value]) searches across EVERY
 * booking, not just the default landing scope.
 *
 * Bug shape this guards against:
 *   apply_status_filter() defaults the listing to AfterSalesService='PENDING'
 *   when no explicit status is chosen. Without an exception for the search box,
 *   a global search only ever scanned pending bookings -- so searching for a
 *   destination (or customer, agent, ...) whose bookings were mostly confirmed
 *   or completed returned few/no rows and looked broken ("search destination
 *   not working"). The fix drops the PENDING gate when a search term is present
 *   while still excluding cancelled bookings (CancelStatus='N').
 */

$model_path = __DIR__ . '/../../application/models/Booking_Model.php';
if (!is_file($model_path)) {
    echo "FAIL  cannot locate Booking_Model.php at {$model_path}\n";
    exit(1);
}
$source = file_get_contents($model_path);

// Extract the body of apply_status_filter().
if (!preg_match('/function\s+apply_status_filter\s*\([^)]*\)\s*\{/', $source, $m, PREG_OFFSET_CAPTURE)) {
    echo "FAIL  apply_status_filter() not found in Booking_Model.php\n";
    exit(1);
}
$brace_start = $m[0][1] + strlen($m[0][0]) - 1;
$depth = 0;
$body_end = null;
for ($i = $brace_start, $n = strlen($source); $i < $n; $i++) {
    $c = $source[$i];
    if ($c === '{') {
        $depth++;
    } elseif ($c === '}') {
        $depth--;
        if ($depth === 0) {
            $body_end = $i;
            break;
        }
    }
}
if ($body_end === null) {
    echo "FAIL  could not delimit apply_status_filter() body\n";
    exit(1);
}
$body = substr($source, $brace_start, $body_end - $brace_start + 1);

// The search-bypass branch must appear BEFORE the fall-through to the full
// status where() so it short-circuits the PENDING default.
$search_pos = strpos($body, "input->get('search[value]')");
$full_where_pos = strpos($body, 'booking_status_filter_full_where');

$assertions = [];

$assertions['apply_status_filter checks the global search term'] =
    $search_pos !== false;

$assertions['search branch keeps CancelStatus = N (excludes cancelled)'] =
    (bool) preg_match("/search_value[\s\S]{0,200}?where\(\s*'booking\.CancelStatus'\s*,\s*'N'\s*\)/", $body);

$assertions['search branch short-circuits before the PENDING default'] =
    $search_pos !== false && $full_where_pos !== false && $search_pos < $full_where_pos;

$assertions['default (no search / no status) still applies the full status where'] =
    $full_where_pos !== false;

// The expanded global-search WHERE must cover the previously-missing columns so
// that "all columns" is honoured (spot-check the ones that were absent before).
if (!preg_match('/function\s+apply_booking_filters\s*\([^)]*\)\s*\{/', $source, $mf, PREG_OFFSET_CAPTURE)) {
    echo "FAIL  apply_booking_filters() not found\n";
    exit(1);
}
$fstart = $mf[0][1];
$search_block = substr($source, $fstart);
$search_block = substr($search_block, 0, strpos($search_block, 'These filters can ignore others') ?: strlen($search_block));

foreach ([
    "or_like('sa2_admin.Name'"          => 'Sales Agent 2',
    "or_like('source.Name'"             => 'Source',
    "or_like('booking.ChatLanguage'"    => 'Chat Language',
    "or_like('category.Name'"           => 'Destination',
    "or_like('booking.NetTotal'"        => 'Net Sales',
    "or_like('booking.StartDate'"       => 'Start Date',
] as $needle => $label) {
    $assertions["global search covers column: {$label}"] = strpos($search_block, $needle) !== false;
}

// The count query must join sa2_admin so the Sales Agent 2 search clause resolves.
$assertions['Count_Bookings_Filtered joins sa2_admin for the search clause'] =
    (bool) preg_match('/Count_Bookings_Filtered[\s\S]*?join\([^)]*sa2_admin/i', $source);

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "{$failed} assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
