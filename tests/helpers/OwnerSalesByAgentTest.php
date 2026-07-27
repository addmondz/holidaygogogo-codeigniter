<?php
/**
 * Run with: php tests/helpers/OwnerSalesByAgentTest.php
 *
 * Locks the SQL behind the OWNER matrix "Sales" column — credited BC value per
 * TC sales agent over the period, with NO fully-paid gate (period sales = actual
 * BC value, matching the TC Sales-Actual rule). The Booking controller builds
 * this inside owner_agent_matrix() reusing the credited-slot expression from
 * lead_conversion_credit_helper. This test replicates that query shape.
 *
 * Rules verified:
 *   - Credited-slot crossover at the 2026-06-01 cutoff: pre-cutoff credits
 *     SalesAgent (TC1), on/after credits SalesAgent2 (TC2).
 *   - Excludes QUOTATION / PROFORMA (BookingConfirmationTitle), CancelStatus='Y',
 *     Status='N', and NetTotal <= 0.
 *   - SUM(NetTotal) grouped per credited admin; only Level 20/50 admins included.
 *   - An agent with no qualifying sales is absent.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/lead_conversion_credit_helper.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    InsertDate TEXT,
    BookingConfirmationTitle TEXT,
    CancelStatus TEXT,
    Status TEXT,
    NetTotal REAL,
    SalesAgent INTEGER,
    SalesAgent2 INTEGER
)");
// Level mirrors the production ENUM('10','20',...) — a TEXT/string column, so the
// scope filter must use string literals IN ('20','50') (integer literals match
// ENUM index positions, not values — the live bug this guards against).
$pdo->exec("CREATE TABLE admin (
    AdminID INTEGER PRIMARY KEY,
    Name TEXT,
    Level TEXT
)");

// 10 = Jane (L20), 11 = Ben (L50 team lead), 12 = Op (L40, NOT a TC agent).
$pdo->exec("INSERT INTO admin VALUES
    (10, 'Jane', '20'),
    (11, 'Ben',  '50'),
    (12, 'Op',   '40')
");

$pdo->exec("INSERT INTO booking VALUES
    /* pre-cutoff -> credit SalesAgent (TC1). Jane gets these. */
    (1, '2026-05-10', 'BOOKING CONFIRMATION', 'N', 'P', 1000, 10, 99),
    (2, '2026-05-20', 'BOOKING CONFIRMATION', 'N', 'P', 2000, 10, 99),
    /* on/after cutoff -> credit SalesAgent2 (TC2). Ben (L50) gets this. */
    (3, '2026-06-05', 'BOOKING CONFIRMATION', 'N', 'P', 3000, 99, 11),
    /* on/after cutoff, TC2 = Jane */
    (4, '2026-06-06', 'BOOKING CONFIRMATION', 'N', 'P', 500,  99, 10),
    /* excluded: QUOTATION title */
    (5, '2026-05-11', 'QUOTATION',            'N', 'P', 9999, 10, 99),
    /* excluded: cancelled */
    (6, '2026-05-12', 'BOOKING CONFIRMATION', 'Y', 'P', 9999, 10, 99),
    /* excluded: deleted Status='N' */
    (7, '2026-05-13', 'BOOKING CONFIRMATION', 'N', 'N', 9999, 10, 99),
    /* excluded: NetTotal <= 0 */
    (8, '2026-05-14', 'BOOKING CONFIRMATION', 'N', 'P', 0,    10, 99),
    /* excluded: credited admin (12) is Level 40, not a TC agent */
    (9, '2026-06-09', 'BOOKING CONFIRMATION', 'N', 'P', 7000, 99, 12),
    /* out of window */
    (10,'2026-04-01', 'BOOKING CONFIRMATION', 'N', 'P', 8000, 10, 99)
");

$agent_expr = lead_conversion_credit_agent_expr('booking');

// Mirror of the owner_agent_matrix() credited-sales query (no paid gate).
// Live MySQL uses CAST(booking.InsertDate AS DATE); SQLite compares the
// date-only TEXT directly, same as CancellationRateExcludesDuplicateTest.
$run = function ($pdo, $agent_expr, $start, $end) {
    $sql = "
        SELECT {$agent_expr} AS admin_id,
               admin.Name AS agent_name,
               COALESCE(SUM(booking.NetTotal), 0) AS total_sales
        FROM booking
        INNER JOIN admin ON admin.AdminID = {$agent_expr} AND admin.Level IN ('20','50')
        WHERE booking.BookingConfirmationTitle='BOOKING CONFIRMATION'
          AND booking.CancelStatus='N'
          AND booking.Status!='N'
          AND booking.NetTotal > 0
          AND booking.InsertDate BETWEEN ? AND ?
        GROUP BY admin_id, agent_name
        HAVING admin_id IS NOT NULL AND admin_id > 0
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($start, $end));
    $out = array();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[(int) $r['admin_id']] = array(
            'name'  => $r['agent_name'],
            'sales' => (float) $r['total_sales'],
        );
    }
    return $out;
};

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got "      . var_export($actual, true) . "\n";
        exit(1);
    }
}

$rows = $run($pdo, $agent_expr, '2026-05-01', '2026-06-30');

// Jane: TC1 on #1 (1000) + #2 (2000) + TC2 on #4 (500) = 3500.
// Ben:  TC2 on #3 (3000).
// Op (L40) excluded; #5-#8, #10 excluded.
assert_eq('two credited TC agents', 2, count($rows));
assert_eq('Jane credited sales (TC1+TC2 across cutoff)', 3500.0, $rows[10]['sales']);
assert_eq('Ben (L50) credited sales', 3000.0, $rows[11]['sales']);
assert_eq('Op (L40) not present', false, isset($rows[12]));

echo "\nAll assertions passed.\n";
