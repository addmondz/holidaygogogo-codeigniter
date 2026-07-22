<?php
/**
 * Run with: php tests/helpers/CancelledBookingExcludedFromConversionTest.php
 *
 * Option-1 cancellation guard: a converted lead whose linked booking is later
 * cancelled (CancelStatus='Y') or voided (Status<>'Y') must STOP counting as a
 * conversion, with no flag recalculation. Proves lead_conversion_active_booking_sql()
 * drops cancelled bookings in BOTH conversion-count paths:
 *   - the gated TC1/TC2 fragment (Lead Dashboard / Lead Ownership), and
 *   - the no-gate '1=1' path used by the booking summary YTD Conversion card.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/lead_conversion_credit_helper.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    SalesAgent INTEGER,
    SalesAgent2 INTEGER,
    InsertDate TEXT,
    CancelStatus TEXT,
    Status TEXT
)");
$pdo->exec("CREATE TABLE ghl_processed_leads (
    id INTEGER PRIMARY KEY,
    assigned_to_user_id TEXT,
    is_converted INTEGER,
    booking_id INTEGER
)");
$pdo->exec("CREATE TABLE admin (AdminID INTEGER PRIMARY KEY, Email TEXT, Status TEXT)");
$pdo->exec("CREATE TABLE admin_lead_dashboard_agents (AdminID INTEGER, GhlUserID TEXT)");

$pdo->exec("INSERT INTO admin VALUES (99, 'hccs@x', 'Y')");
$pdo->exec("INSERT INTO admin_lead_dashboard_agents (AdminID, GhlUserID) VALUES (99, 'ghl-hccs')");

// All bookings: HC CS = TC2, post-cutoff so the gated fragment credits TC2.
$pdo->exec("INSERT INTO booking VALUES
    (2001, NULL, 99, '2026-06-15 12:30:00', 'N', 'Y'),  /* active     -> COUNT */
    (2002, NULL, 99, '2026-06-15 12:30:00', 'Y', 'Y'),  /* cancelled  -> NO    */
    (2003, NULL, 99, '2026-06-15 12:30:00', 'N', 'N')   /* voided     -> NO    */
");
$pdo->exec("INSERT INTO ghl_processed_leads VALUES
    (1, 'ghl-hccs', 1, 2001),
    (2, 'ghl-hccs', 1, 2002),
    (3, 'ghl-hccs', 1, 2003)
");

$active   = lead_conversion_active_booking_sql('pl');
$gated    = lead_conversion_credit_sql_fragment();

$assertions = [];

// Gated path WITH cancellation guard: only the active booking (lead 1) counts.
$sql = "SELECT SUM(CASE WHEN pl.is_converted = 1 AND pl.booking_id IS NOT NULL
            AND {$gated} AND {$active} THEN 1 ELSE 0 END) AS n
        FROM ghl_processed_leads pl";
$assertions['Gated + guard counts only active booking = 1'] =
    ((int) $pdo->query($sql)->fetch(PDO::FETCH_ASSOC)['n']) === 1;

// No-gate ('1=1') path (YTD card) WITH guard: still only the active booking.
$sql = "SELECT SUM(CASE WHEN pl.is_converted = 1 AND pl.booking_id IS NOT NULL
            AND 1=1 AND {$active} THEN 1 ELSE 0 END) AS n
        FROM ghl_processed_leads pl";
$assertions['No-gate + guard counts only active booking = 1'] =
    ((int) $pdo->query($sql)->fetch(PDO::FETCH_ASSOC)['n']) === 1;

// Baseline (no guard) would wrongly count all three -> proves the guard matters.
$sql = "SELECT SUM(CASE WHEN pl.is_converted = 1 AND pl.booking_id IS NOT NULL
            AND 1=1 THEN 1 ELSE 0 END) AS n
        FROM ghl_processed_leads pl";
$assertions['Baseline without guard over-counts = 3'] =
    ((int) $pdo->query($sql)->fetch(PDO::FETCH_ASSOC)['n']) === 3;

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) { $failed++; }
}
echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
