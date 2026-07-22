<?php
/**
 * Run with: php tests/helpers/OwnerCancellationByAgentTest.php
 *
 * Locks the SQL behind the OWNER matrix "Cancellation %" column. The Booking
 * controller builds this via Report_Model->Cancellation_By_Agent($start, $end),
 * which is the single-agent cancellation rule (cancellation_rate_exclude_-
 * duplicate_clause) re-grouped per credited TC slot. This test replicates that
 * query shape.
 *
 * Rules verified:
 *   - "BOOKING - DUPLICATED BOOKING" cancellations excluded from BOTH the
 *     cancelled count (numerator) and the total population (denominator).
 *   - Per-agent grouping by the credited slot across the 2026-06-01 cutoff
 *     (pre -> SalesAgent, on/after -> SalesAgent2).
 *   - Scoped to the TC sales-agent role (admin Level 20/50) via INNER JOIN on
 *     the credited slot: a BC credited to a non-TC admin is excluded entirely.
 *   - An agent with zero qualifying BCs is absent (rate handled as 0.0 in PHP).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/cancellation_rate_helper.php';
require_once __DIR__ . '/../../application/helpers/lead_conversion_credit_helper.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE cancellation_reason (
    CancellationReasonID INTEGER PRIMARY KEY,
    Name TEXT
)");
$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    InsertDate TEXT,
    BookingConfirmationTitle TEXT,
    CancelStatus TEXT,
    Status TEXT,
    CancellationReasonID INTEGER,
    SalesAgent INTEGER,
    SalesAgent2 INTEGER
)");
// Level mirrors the production ENUM('10','20',...) — a TEXT/string column, so the
// scope filter uses string literals IN ('20','50') (integer literals would match
// ENUM index positions, not values).
$pdo->exec("CREATE TABLE admin (
    AdminID INTEGER PRIMARY KEY,
    Name TEXT,
    Level TEXT
)");

// 10 = Jane (L20), 11 = Ben (L50), 12 = Op (L40 — NOT a TC agent).
$pdo->exec("INSERT INTO admin VALUES
    (10, 'Jane', '20'),
    (11, 'Ben',  '50'),
    (12, 'Op',   '40')
");

$pdo->exec("INSERT INTO cancellation_reason VALUES
    (3, 'BOOKING - DUPLICATED BOOKING'),
    (4, 'CUSTOMER - NO RESPONSE')
");

// Jane (10) — all pre-cutoff (credited via SalesAgent):
//   2 active, 1 genuine cancel (reason 4), 1 duplicate cancel (reason 3 -> excluded)
//   => total 3, cancelled 1.
// Ben (11) — all on/after cutoff (credited via SalesAgent2):
//   1 active, 1 cancel with NULL reason (kept as genuine)
//   => total 2, cancelled 1.
$pdo->exec("INSERT INTO booking VALUES
    (1, '2026-05-01', 'BOOKING CONFIRMATION', 'N', 'P', NULL, 10, 99),
    (2, '2026-05-02', 'BOOKING CONFIRMATION', 'N', 'P', NULL, 10, 99),
    (3, '2026-05-03', 'BOOKING CONFIRMATION', 'Y', 'P', 4,    10, 99),
    (4, '2026-05-04', 'BOOKING CONFIRMATION', 'Y', 'P', 3,    10, 99),
    (5, '2026-06-10', 'BOOKING CONFIRMATION', 'N', 'P', NULL, 99, 11),
    (6, '2026-06-11', 'BOOKING CONFIRMATION', 'Y', 'P', NULL, 99, 11),
    /* credited to Op (L40) — excluded entirely by the Level 20/50 join */
    (7, '2026-05-05', 'BOOKING CONFIRMATION', 'Y', 'P', NULL, 12, 99)
");

$agent_expr = lead_conversion_credit_agent_expr('booking');
$exclude    = cancellation_rate_exclude_duplicate_clause('booking');

// Mirror of Report_Model::Cancellation_By_Agent() (incl. the Level 20/50 join).
$run = function ($pdo, $agent_expr, $exclude, $start, $end) {
    $sql = "
        SELECT {$agent_expr} AS admin_id,
               COUNT(*) AS total,
               SUM(CASE WHEN booking.CancelStatus='Y' THEN 1 ELSE 0 END) AS cancelled
        FROM booking
        INNER JOIN admin ON admin.AdminID = {$agent_expr} AND admin.Level IN ('20','50')
        WHERE booking.BookingConfirmationTitle='BOOKING CONFIRMATION'
          AND booking.Status!='N'
          AND {$exclude}
          AND booking.InsertDate BETWEEN ? AND ?
        GROUP BY admin_id
        HAVING admin_id IS NOT NULL AND admin_id > 0
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($start, $end));
    $out = array();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[(int) $r['admin_id']] = array(
            'total'     => (int) $r['total'],
            'cancelled' => (int) $r['cancelled'],
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

$rows = $run($pdo, $agent_expr, $exclude, '2026-05-01', '2026-06-30');

// Op (L40) row #7 excluded by the Level 20/50 join -> only Jane & Ben remain.
assert_eq('two credited TC agents (Op excluded)', 2, count($rows));
assert_eq('Op (L40) not present', false, isset($rows[12]));

// Jane: duplicate (#4) dropped from both -> total 3 (1,2,3), cancelled 1 (#3).
assert_eq('Jane total excludes duplicate',     3, $rows[10]['total']);
assert_eq('Jane cancelled excludes duplicate', 1, $rows[10]['cancelled']);

// Ben: null-reason cancel kept -> total 2, cancelled 1.
assert_eq('Ben total',     2, $rows[11]['total']);
assert_eq('Ben cancelled', 1, $rows[11]['cancelled']);

// Per-agent rate derived in PHP, with a total>0 guard (never divide by zero).
$rate = function ($t, $c) { return $t > 0 ? round($c / $t * 100, 1) : 0.0; };
assert_eq('Jane rate 33.3% (1/3)', 33.3, $rate($rows[10]['total'], $rows[10]['cancelled']));
assert_eq('Ben rate 50.0% (1/2)',  50.0, $rate($rows[11]['total'], $rows[11]['cancelled']));
assert_eq('zero-BC agent guard -> 0.0', 0.0, $rate(0, 0));

echo "\nAll assertions passed.\n";
