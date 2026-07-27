<?php
/**
 * Run with: php tests/helpers/BestBcCreatedCardTest.php
 *
 * Locks the GROUP-BY query behind the "Compare the Best" sub-line under the
 * TC "BC Created (Month)" card. The leaderboard universe must match the
 * agent's own BC count exactly (same filters, same TC1/TC2 credited-slot
 * rule, same date window) so the comparison is apples-to-apples.
 *
 * Invariants:
 *   - Aggregation is by credited agent under the TC1/TC2 cutoff:
 *       pre-cutoff InsertDate  -> SalesAgent  (TC1)
 *       on/after cutoff        -> SalesAgent2 (TC2)
 *   - Cancelled, draft, and quotation rows are excluded (same as own card).
 *   - Out-of-window rows are excluded.
 *   - Rows where the credited slot is empty/zero are dropped (HAVING).
 *   - Tie-break is alphabetical by admin.Name ASC, deterministic.
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
    (30, 'Carol'),
    (40, 'Diana')
");

// Cutoff is 2026-06-01. Pre-cutoff credits SalesAgent, on/after credits SalesAgent2.
// Alice (10): rows 1,2,3 -> 3 BCs (Alice = top).
// Bob   (20): rows 4,5   -> 2 BCs.
// Carol (30): rows 6,7   -> 2 BCs (tied with Bob -> alphabetical Bob ranks above).
// Diana (40): 0 BCs.
$pdo->exec("INSERT INTO booking VALUES
    (1,  10,  20,  '2026-05-03', 100, 'BOOKING CONFIRMATION', 'N', 'P'),    /* pre, Alice via SA */
    (2,  10,  20,  '2026-05-10', 200, 'BOOKING CONFIRMATION', 'N', 'P'),    /* pre, Alice via SA */
    (3,  20,  10,  '2026-06-15', 300, 'BOOKING CONFIRMATION', 'N', 'P'),    /* post, Alice via SA2 */
    (4,  20,  10,  '2026-05-12', 400, 'BOOKING CONFIRMATION', 'N', 'P'),    /* pre, Bob via SA */
    (5,  10,  20,  '2026-06-05', 500, 'BOOKING CONFIRMATION', 'N', 'P'),    /* post, Bob via SA2 */
    (6,  10,  30,  '2026-06-10', 600, 'BOOKING CONFIRMATION', 'N', 'P'),    /* post, Carol via SA2 */
    (7,  30,  10,  '2026-05-20', 700, 'BOOKING CONFIRMATION', 'N', 'P'),    /* pre, Carol via SA */
    (8,  10,  20,  '2026-05-15', 999, 'BOOKING CONFIRMATION', 'Y', 'P'),    /* cancelled - excluded */
    (9,  10,  20,  '2026-05-15', 999, 'BOOKING CONFIRMATION', 'N', 'N'),    /* draft - excluded */
    (10, 10,  20,  '2026-05-15', 999, 'QUOTATION',            'N', 'P'),    /* quotation - excluded */
    (11, 10,  20,  '2026-04-30', 999, 'BOOKING CONFIRMATION', 'N', 'P'),    /* before window */
    (12, 10,  20,  '2026-07-01', 999, 'BOOKING CONFIRMATION', 'N', 'P'),    /* after window */
    (13, NULL,20,  '2026-05-15', 999, 'BOOKING CONFIRMATION', 'N', 'P'),    /* pre, SA null - excluded */
    (14, 10,  0,   '2026-06-15', 999, 'BOOKING CONFIRMATION', 'N', 'P')     /* post, SA2=0 - excluded */
");

$agent_expr = lead_conversion_credit_agent_expr();

$sql = "
    SELECT
        {$agent_expr} AS credited_agent_id,
        admin.Name AS agent_name,
        COUNT(*) AS bc_count
    FROM booking
    LEFT JOIN admin ON admin.AdminID = {$agent_expr}
    WHERE booking.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
      AND booking.CancelStatus = 'N'
      AND booking.Status != 'N'
      AND booking.InsertDate BETWEEN :ms AND :me
    GROUP BY credited_agent_id, agent_name
    HAVING credited_agent_id IS NOT NULL AND credited_agent_id > 0
    ORDER BY bc_count DESC, agent_name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':ms' => '2026-05-01', ':me' => '2026-06-30']);
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

assert_eq('best agent name',       'Alice', (string) $rows[0]['agent_name']);
assert_eq('best agent BC count',   3,        (int)    $rows[0]['bc_count']);
assert_eq('best agent id',         10,       (int)    $rows[0]['credited_agent_id']);

// Tie-break: Bob and Carol both at 2 BCs, alphabetical ASC puts Bob above Carol.
assert_eq('tie-break #2 name', 'Bob',   (string) $rows[1]['agent_name']);
assert_eq('tie-break #2 cnt',  2,        (int)    $rows[1]['bc_count']);
assert_eq('tie-break #3 name', 'Carol', (string) $rows[2]['agent_name']);
assert_eq('tie-break #3 cnt',  2,        (int)    $rows[2]['bc_count']);

echo "\nAll assertions passed.\n";
