<?php
/**
 * Run with: php tests/helpers/BestCancellationRateCardTest.php
 *
 * Locks the GROUP-BY query behind the "Compare the Best" sub-line under the
 * TC "Cancellation Rate (Month)" card.
 *
 * Differences from the BC/Sales leaderboard queries:
 *   - CancelStatus filter is dropped because cancelled rows are needed to
 *     compute the rate. The agent's own cancellation_rate query (Booking.php
 *     lines 722-731) does the same.
 *   - HAVING applies a minimum-sample threshold of 3 BCs so an agent with one
 *     BC and zero cancellations doesn't dominate at 0%.
 *   - Best = lowest rate. ORDER BY rate ASC, agent_name ASC.
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
    (40, 'Diana'),
    (50, 'Eve')
");

// Alice: 5 BCs, 0 cancelled        -> 0%   (best, top by name among 0% rows)
// Eve:   3 BCs, 0 cancelled        -> 0%   (tie with Alice, but 'Eve' > 'Alice')
// Diana: 4 BCs, 2 cancelled        -> 50%
// Bob:   3 BCs, 3 cancelled        -> 100%
// Carol: 1 BC,  0 cancelled        -> 0% but EXCLUDED (HAVING total < 3)
$pdo->exec("INSERT INTO booking VALUES
    /* Alice: 5 BCs, all not cancelled */
    (1,  10, 20, '2026-05-01', 100, 'BOOKING CONFIRMATION', 'N', 'P'),
    (2,  10, 20, '2026-05-02', 100, 'BOOKING CONFIRMATION', 'N', 'P'),
    (3,  10, 20, '2026-05-03', 100, 'BOOKING CONFIRMATION', 'N', 'P'),
    (4,  10, 20, '2026-05-04', 100, 'BOOKING CONFIRMATION', 'N', 'P'),
    (5,  10, 20, '2026-05-05', 100, 'BOOKING CONFIRMATION', 'N', 'P'),
    /* Eve: 3 BCs, all not cancelled */
    (6,  50, 20, '2026-05-06', 100, 'BOOKING CONFIRMATION', 'N', 'P'),
    (7,  50, 20, '2026-05-07', 100, 'BOOKING CONFIRMATION', 'N', 'P'),
    (8,  50, 20, '2026-05-08', 100, 'BOOKING CONFIRMATION', 'N', 'P'),
    /* Diana: 4 BCs, 2 cancelled */
    (9,  40, 20, '2026-05-09', 100, 'BOOKING CONFIRMATION', 'N', 'P'),
    (10, 40, 20, '2026-05-10', 100, 'BOOKING CONFIRMATION', 'N', 'P'),
    (11, 40, 20, '2026-05-11', 100, 'BOOKING CONFIRMATION', 'Y', 'P'),
    (12, 40, 20, '2026-05-12', 100, 'BOOKING CONFIRMATION', 'Y', 'P'),
    /* Bob: 3 BCs, all cancelled */
    (13, 20, 30, '2026-05-13', 100, 'BOOKING CONFIRMATION', 'Y', 'P'),
    (14, 20, 30, '2026-05-14', 100, 'BOOKING CONFIRMATION', 'Y', 'P'),
    (15, 20, 30, '2026-05-15', 100, 'BOOKING CONFIRMATION', 'Y', 'P'),
    /* Carol: 1 BC -> below threshold */
    (16, 30, 20, '2026-05-16', 100, 'BOOKING CONFIRMATION', 'N', 'P'),
    /* Draft and quotation must be excluded entirely */
    (17, 10, 20, '2026-05-17', 100, 'BOOKING CONFIRMATION', 'N', 'N'),
    (18, 10, 20, '2026-05-18', 100, 'QUOTATION',            'N', 'P')
");

$agent_expr = lead_conversion_credit_agent_expr();

$sql = "
    SELECT
        {$agent_expr} AS credited_agent_id,
        admin.Name AS agent_name,
        COUNT(*) AS total,
        SUM(CASE WHEN booking.CancelStatus = 'Y' THEN 1 ELSE 0 END) AS cancelled
    FROM booking
    LEFT JOIN admin ON admin.AdminID = {$agent_expr}
    WHERE booking.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
      AND booking.Status != 'N'
      AND booking.InsertDate BETWEEN :ms AND :me
    GROUP BY credited_agent_id, agent_name
    HAVING credited_agent_id IS NOT NULL AND credited_agent_id > 0 AND COUNT(*) >= 3
    ORDER BY (CAST(SUM(CASE WHEN booking.CancelStatus='Y' THEN 1 ELSE 0 END) AS REAL) / COUNT(*)) ASC,
             agent_name ASC
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

// Carol must be filtered out by HAVING.
assert_eq('agents above threshold', 4, count($rows));

// Best (lowest rate): Alice at 0% (alphabetical before Eve).
assert_eq('best agent name',      'Alice', (string) $rows[0]['agent_name']);
assert_eq('best agent total',     5,        (int)    $rows[0]['total']);
assert_eq('best agent cancelled', 0,        (int)    $rows[0]['cancelled']);

assert_eq('rank #2 name',         'Eve',   (string) $rows[1]['agent_name']);
assert_eq('rank #2 cancelled',    0,        (int)    $rows[1]['cancelled']);

assert_eq('rank #3 name',         'Diana', (string) $rows[2]['agent_name']);
assert_eq('rank #3 cancelled',    2,        (int)    $rows[2]['cancelled']);

assert_eq('worst rank name',      'Bob',   (string) $rows[3]['agent_name']);
assert_eq('worst rank cancelled', 3,        (int)    $rows[3]['cancelled']);

// Confirm Carol (1 BC) is absent.
$names = array_map(function($r) { return (string) $r['agent_name']; }, $rows);
assert_eq('carol filtered out (below threshold)', false, in_array('Carol', $names, true));

echo "\nAll assertions passed.\n";
