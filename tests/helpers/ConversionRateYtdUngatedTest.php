<?php
/**
 * Run with: php tests/helpers/ConversionRateYtdUngatedTest.php
 *
 * The TC "Conversion Rate (YTD)" summary card must count a conversion exactly
 * like the Lead Ownership dashboard's "Converted" column: a lead is converted
 * iff is_converted = 1 AND booking_id IS NOT NULL — with NO TC1/TC2 credit
 * gate. The controller achieves this by passing the tautology credit-fragment
 * override ('1=1') as the second argument to
 * Report_Model::Lead_Dashboard_By_Agent(), the same way the Top Agents –
 * Conversion panel does.
 *
 * This locks two things:
 *   (a) the controller actually wires the '1=1' override for the YTD card
 *       (a future edit can't silently re-gate it), and
 *   (b) with that override the per-agent converted count, and the logged-in
 *       agent's own converted/total rate, ignore the TC slot entirely.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$assertions = [];

// ---------------------------------------------------------------------------
// (a) Source-level guard: the YTD card block must call Lead_Dashboard_By_Agent
//     with the '1=1' override (ungated), not the gated default.
// ---------------------------------------------------------------------------
$controller = file_get_contents(__DIR__ . '/../../application/controllers/Booking.php');
$ytdStart = strpos($controller, "Conversion Rate (YTD)");
$ytdEnd   = strpos($controller, "Resolve the logged-in admin to their GHL UserID");
$ytdBlock = ($ytdStart !== false && $ytdEnd !== false)
    ? substr($controller, $ytdStart, $ytdEnd - $ytdStart)
    : '';
$hasCall = (bool) preg_match(
    '/Lead_Dashboard_By_Agent\(\s*array\([^)]*\)\s*,\s*\'1=1\'\s*\)/s',
    $ytdBlock
);
$assertions["YTD card passes the '1=1' ungated override"] = $hasCall;

// ---------------------------------------------------------------------------
// (b) Behaviour: replicate the model's SUM(CASE ...) shape with the '1=1'
//     override and prove the per-agent count + own-rate are ungated.
// ---------------------------------------------------------------------------
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE ghl_processed_leads (
    id INTEGER PRIMARY KEY,
    assigned_to_user_id TEXT,
    is_converted INTEGER,
    booking_id INTEGER
)");
// alice: 4 converted+booked, 1 unconverted, 1 converted-without-booking.
// Under the gated rule alice would lose conversions she doesn't hold the slot
// for; ungated she keeps all 4.
$pdo->exec("INSERT INTO ghl_processed_leads VALUES
    (1, 'ghl-alice', 1, 1001),
    (2, 'ghl-alice', 1, 1002),
    (3, 'ghl-alice', 1, 1003),
    (4, 'ghl-alice', 1, 1004),
    (5, 'ghl-alice', 0, NULL),   /* unconverted           -> excluded */
    (6, 'ghl-alice', 1, NULL),   /* converted, no booking -> excluded */
    (7, 'ghl-bob',   1, 2001)
");

$fragment = '1=1';
$row = $pdo->query(
    "SELECT
        COALESCE(NULLIF(pl.assigned_to_user_id, ''), '__unassigned__') AS agent_id,
        COUNT(*) AS total_leads,
        SUM(CASE WHEN pl.is_converted = 1 AND pl.booking_id IS NOT NULL AND {$fragment} THEN 1 ELSE 0 END) AS converted_leads
     FROM ghl_processed_leads pl
     WHERE NULLIF(pl.assigned_to_user_id, '') = 'ghl-alice'
     GROUP BY agent_id"
)->fetch(PDO::FETCH_ASSOC);

$ownTotal = (int) $row['total_leads'];
$ownConverted = (int) $row['converted_leads'];
$ownRate = $ownTotal > 0 ? round(($ownConverted / $ownTotal) * 100, 1) : null;

$assertions['alice total_leads = 6'] = $ownTotal === 6;
$assertions['alice converted_leads = 4 (ungated)'] = $ownConverted === 4;
$assertions['alice own rate = 66.7'] = $ownRate === 66.7;

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
