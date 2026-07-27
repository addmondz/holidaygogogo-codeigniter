<?php
/**
 * Run with: php tests/helpers/BestSalesCardTest.php
 *
 * Locks the GROUP-BY query behind the "Compare the Best" sub-line under the
 * TC "Total Sales (Month)" card. Same shape as the BC leaderboard query but
 * the metric is SUM(NetTotal). Cancelled rows must NOT contribute to either
 * the count or the sum so the leaderboard agrees with the agent's own card.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/lead_conversion_credit_helper.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE admin (
    AdminID INTEGER PRIMARY KEY,
    Name TEXT
)");
$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    SalesAgent INTEGER,
    SalesAgent2 INTEGER,
    InsertDate TEXT,
    NetTotal REAL,
    BookingConfirmationTitle TEXT,
    CancelStatus TEXT,
    Status TEXT
)");

$pdo->exec("INSERT INTO admin VALUES
    (10, 'Alice'),
    (20, 'Bob'),
    (30, 'Carol')
");

// Alice 1000, Bob 500, Carol 500 (Bob/Carol tied -> name ASC -> Bob above Carol).
// Cancelled / draft / quotation rows must NOT contribute to the totals.
$pdo->exec("INSERT INTO booking VALUES
    (1, 10, 20, '2026-05-03', 1000, 'BOOKING CONFIRMATION', 'N', 'P'),
    (2, 10, 20, '2026-05-10', 9999, 'BOOKING CONFIRMATION', 'Y', 'P'),    /* cancelled - excluded */
    (3, 20, 10, '2026-05-15',  500, 'BOOKING CONFIRMATION', 'N', 'P'),
    (4, 30, 10, '2026-05-20',  500, 'BOOKING CONFIRMATION', 'N', 'P'),
    (5, 10, 20, '2026-05-15', 9999, 'BOOKING CONFIRMATION', 'N', 'N'),    /* draft - excluded */
    (6, 10, 20, '2026-05-15', 9999, 'QUOTATION',            'N', 'P')     /* quotation - excluded */
");

$agent_expr = lead_conversion_credit_agent_expr();

$sql = "
    SELECT
        {$agent_expr} AS credited_agent_id,
        admin.Name AS agent_name,
        COALESCE(SUM(booking.NetTotal), 0) AS total_sales
    FROM booking
    LEFT JOIN admin ON admin.AdminID = {$agent_expr}
    WHERE booking.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
      AND booking.CancelStatus = 'N'
      AND booking.Status != 'N'
      AND booking.InsertDate BETWEEN :ms AND :me
    GROUP BY credited_agent_id, agent_name
    HAVING credited_agent_id IS NOT NULL AND credited_agent_id > 0
    ORDER BY total_sales DESC, agent_name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':ms' => '2026-05-01', ':me' => '2026-05-31']);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

assert_eq('distinct credited agents', 3, count($rows));
assert_eq('best agent name',  'Alice', (string) $rows[0]['agent_name']);
assert_eq('best agent total', 1000.0,  (float)  $rows[0]['total_sales']);

// Tied at 500: Bob (alphabetical) before Carol.
assert_eq('tie-break #2 name',  'Bob',   (string) $rows[1]['agent_name']);
assert_eq('tie-break #2 total', 500.0,   (float)  $rows[1]['total_sales']);
assert_eq('tie-break #3 name',  'Carol', (string) $rows[2]['agent_name']);
assert_eq('tie-break #3 total', 500.0,   (float)  $rows[2]['total_sales']);

echo "\nAll assertions passed.\n";
