<?php
/**
 * Run with: php tests/helpers/GhlLeadPostConversionSplitTest.php
 *
 * Locks the GHL lead segmentation decision that Cron::process_single_ghl_conversation()
 * defers to (ghl_should_start_new_processed_lead() in
 * application/helpers/ghl_lead_segmentation_helper.php).
 *
 * Business rule (2026-06-25): after a lead has converted, an ongoing
 * conversation must NOT immediately spawn a brand-new lead. It only counts as
 * a new lead once the conversation has gone quiet for 24h — i.e. there is a
 * >=24h rolling gap between consecutive messages. This prevents same-day
 * follow-on chatter after a booking confirmation from inflating the lead count.
 *
 * Unconverted leads keep the original 90-day inactivity split.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/ghl_lead_segmentation_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label}\n";
        return;
    }

    echo "  FAIL  {$label}: expected " . var_export($expected, true)
        . ", got " . var_export($actual, true) . "\n";
    exit(1);
}

$DAY = 86400;

$currentLead = array(
    'lead_started_at' => '2026-06-10 09:00:00',
    'first_customer_message_id' => 'msg_first',
);
$conversionKey = $currentLead['lead_started_at'] . '|' . $currentLead['first_customer_message_id'];
$convertedConversions = array(
    $conversionKey => array('converted_at' => '2026-06-10 10:00:00'),
);
$noConversions = array();

// --- Guard: no current lead never starts a new one --------------------------
assert_eq(
    'null current lead does not start a new lead',
    false,
    ghl_should_start_new_processed_lead(null, $noConversions, null, strtotime('2026-06-10 12:00:00'))
);

// --- Unconverted lead: 90-day inactivity split is preserved -----------------
assert_eq(
    'unconverted lead with 1-day gap stays the same lead',
    false,
    ghl_should_start_new_processed_lead(
        $currentLead,
        $noConversions,
        strtotime('2026-06-10 11:00:00'),
        strtotime('2026-06-11 11:00:00')
    )
);

assert_eq(
    'unconverted lead with 91-day gap starts a new lead',
    true,
    ghl_should_start_new_processed_lead(
        $currentLead,
        $noConversions,
        strtotime('2026-06-10 11:00:00'),
        strtotime('2026-06-10 11:00:00') + (91 * $DAY)
    )
);

// --- Converted lead: NEW 24h post-conversion rule ---------------------------
assert_eq(
    'converted lead with same-day follow-on (1h gap) stays the same lead',
    false,
    ghl_should_start_new_processed_lead(
        $currentLead,
        $convertedConversions,
        strtotime('2026-06-10 11:00:00'),
        strtotime('2026-06-10 12:00:00')
    )
);

assert_eq(
    'converted lead with 25h gap starts a new lead',
    true,
    ghl_should_start_new_processed_lead(
        $currentLead,
        $convertedConversions,
        strtotime('2026-06-10 11:00:00'),
        strtotime('2026-06-11 12:00:00')
    )
);

assert_eq(
    'converted lead with exactly 24h gap starts a new lead',
    true,
    ghl_should_start_new_processed_lead(
        $currentLead,
        $convertedConversions,
        strtotime('2026-06-10 11:00:00'),
        strtotime('2026-06-10 11:00:00') + $DAY
    )
);

assert_eq(
    'converted lead one second under 24h stays the same lead',
    false,
    ghl_should_start_new_processed_lead(
        $currentLead,
        $convertedConversions,
        strtotime('2026-06-10 11:00:00'),
        strtotime('2026-06-10 11:00:00') + $DAY - 1
    )
);

// --- Messages before the conversion timestamp use the 90-day rule -----------
assert_eq(
    'pre-conversion message with small gap does not split on 24h',
    false,
    ghl_should_start_new_processed_lead(
        $currentLead,
        $convertedConversions,
        strtotime('2026-06-10 09:30:00'),
        strtotime('2026-06-10 09:45:00')
    )
);

// --- Out-of-order messages never split --------------------------------------
assert_eq(
    'out-of-order message (negative gap) never starts a new lead',
    false,
    ghl_should_start_new_processed_lead(
        $currentLead,
        $convertedConversions,
        strtotime('2026-06-11 12:00:00'),
        strtotime('2026-06-10 12:00:00')
    )
);

echo "All GhlLeadPostConversionSplit tests passed.\n";
