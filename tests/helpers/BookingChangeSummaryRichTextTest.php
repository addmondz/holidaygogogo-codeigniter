<?php
/**
 * Run with: php tests/helpers/BookingChangeSummaryRichTextTest.php
 *
 * Verifies booking-update notifications summarise rich-text fields as
 * "Field updated" instead of dumping HTML markup into the message.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/booking_change_summary_helper.php';

$log_rows = [
    [
        'Column'      => 'NetTotal',
        'CurrentData' => '15710.00',
        'NewData'     => '15580.00',
    ],
    [
        'Column'      => 'BookingConfirmationFooter',
        'CurrentData' => '<p><span style="font-size: 10pt;">Old footer.</span></p>',
        'NewData'     => '<p><span style="font-size: 10pt;">New footer.</span></p>',
    ],
];

$summary = build_booking_change_summary($log_rows, [], [], [], [], []);

$assertions = [
    'shows money diff'              => strpos($summary, 'Net Total RM 15,710.00 → RM 15,580.00') !== false,
    'shows footer as "updated"'     => strpos($summary, 'Booking Confirmation Footer updated') !== false,
    'no raw HTML "<" leaks'         => strpos($summary, '<') === false,
    'no "span" markup leaks'        => strpos($summary, 'span') === false,
];

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . 'Summary: ' . $summary . PHP_EOL . PHP_EOL;
echo $failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n";
exit($failed === 0 ? 0 : 1);
