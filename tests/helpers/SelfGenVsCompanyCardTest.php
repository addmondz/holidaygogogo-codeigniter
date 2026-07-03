<?php
/**
 * Run with: php tests/helpers/SelfGenVsCompanyCardTest.php
 *
 * Locks the SQL behind the TC LEAD "Self Gen vs Company (Month)" headline
 * card and the "Sales by Agent – Self Gen vs Company (Month)" per-agent
 * table.
 *
 * Bucketing rule (mirrors the controller, with the SQLite ANSI-equivalent
 * of the production MySQL CASE):
 *   - "Self Gen": UPPER(TRIM(source.Name)) = 'SELF GEN'
 *   - "Company":  any other source name OR source.Name IS NULL
 *
 * Invariants this test locks:
 *   1. Self Gen + Company = total BC count (partition).
 *   2. Self Gen RM + Company RM = total NetTotal (partition).
 *   3. NULL source falls into Company, NOT Self Gen.
 *   4. Whitespace / case variations on 'SELF GEN' still count as Self Gen.
 *   5. Cancelled / draft / quotation rows are excluded.
 *   6. Out-of-window rows are excluded.
 *   7. Per-agent table excludes rows with NULL SalesAgent, but those rows
 *      still feed the headline card.
 *   8. Per-agent attribution is by primary SalesAgent (TC1), NOT
 *      SalesAgent2, regardless of InsertDate.
 *   9. Per-agent rows are grouped by the SalesAgent's Team (admin.TeamID ->
 *      team.Name), agents with no Team collapse to "Unassigned" (sorted last).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    SalesAgent INTEGER,
    SalesAgent2 INTEGER,
    Source INTEGER,
    NetTotal REAL,
    BookingConfirmationTitle TEXT,
    CancelStatus TEXT,
    Status TEXT,
    InsertDate TEXT
)");
$pdo->exec("CREATE TABLE admin (
    AdminID INTEGER PRIMARY KEY,
    Name TEXT,
    TeamID INTEGER
)");
$pdo->exec("CREATE TABLE team (
    TeamID INTEGER PRIMARY KEY,
    Name TEXT
)");
$pdo->exec("CREATE TABLE source (
    SourceID INTEGER PRIMARY KEY,
    Name TEXT
)");

// Team 1 = "Team A" with agents 11, 12. Team 2 = "Team B" with agent 21.
// Solo agent 99 has no team.
$pdo->exec("INSERT INTO team VALUES (1, 'Team A'), (2, 'Team B')");
$pdo->exec("INSERT INTO admin VALUES
    (11, 'Agent A1', 1),
    (12, 'Agent A2', 1),
    (21, 'Agent B1', 2),
    (99, 'Solo',     NULL)
");

// Source 1 = SELF GEN. Source 2 = mixed case + whitespace (normalises to
// SELF GEN). Source 3 = whatsapp (company). Source 4 = a deliberate
// substring trap that must NOT match.
$pdo->exec("INSERT INTO source VALUES
    (1, 'SELF GEN'),
    (2, '  self gen  '),
    (3, 'WHATSAPP'),
    (4, 'SELF GENERATED')
");

// Booking grid (see per-agent expectations below):
$pdo->exec("INSERT INTO booking VALUES
    (1, 11, NULL, 1,    1000.00, 'BOOKING CONFIRMATION', 'N', 'P', '2026-05-03'),
    (2, 11, NULL, 1,     500.00, 'BOOKING CONFIRMATION', 'N', 'P', '2026-05-04'),
    (3, 12, NULL, 2,     700.00, 'BOOKING CONFIRMATION', 'N', 'P', '2026-05-10'),
    (4, 12, NULL, NULL,  400.00, 'BOOKING CONFIRMATION', 'N', 'P', '2026-05-12'),
    (5, 21, NULL, 3,    1200.00, 'BOOKING CONFIRMATION', 'N', 'P', '2026-05-15'),
    (6, 99, NULL, 4,     250.00, 'BOOKING CONFIRMATION', 'N', 'P', '2026-05-18'),
    (7, 11,   42, 1,     900.00, 'BOOKING CONFIRMATION', 'N', 'P', '2026-06-15'),
    (8, NULL, NULL,1,     50.00, 'BOOKING CONFIRMATION', 'N', 'P', '2026-05-20'),
    (9, 11, NULL, 1,    9999.00, 'BOOKING CONFIRMATION', 'Y', 'P', '2026-05-03'),
    (10,11, NULL, 1,    9999.00, 'BOOKING CONFIRMATION', 'N', 'N', '2026-05-03'),
    (11,11, NULL, 1,    9999.00, 'QUOTATION',            'N', 'P', '2026-05-03'),
    (12,11, NULL, 1,    9999.00, 'BOOKING CONFIRMATION', 'N', 'P', '2026-04-30')
");

// Booking 7 is dated 2026-06-15; widen the window to include it so the
// per-agent assertions prove attribution stays on SalesAgent (TC1).
$ms = '2026-05-01';
$me = '2026-06-30';

$selfGen = 'SELF GEN';
$sql_head = "
    SELECT
        SUM(CASE WHEN UPPER(TRIM(s.Name)) = :sg1 THEN 1 ELSE 0 END) AS self_gen_cnt,
        COALESCE(SUM(CASE WHEN UPPER(TRIM(s.Name)) = :sg2 THEN b.NetTotal ELSE 0 END), 0) AS self_gen_total,
        SUM(CASE WHEN UPPER(TRIM(s.Name)) <> :sg3 OR s.Name IS NULL THEN 1 ELSE 0 END) AS company_cnt,
        COALESCE(SUM(CASE WHEN UPPER(TRIM(s.Name)) <> :sg4 OR s.Name IS NULL THEN b.NetTotal ELSE 0 END), 0) AS company_total
    FROM booking b
    LEFT JOIN source s ON s.SourceID = b.Source
    WHERE b.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
      AND b.CancelStatus = 'N'
      AND b.Status != 'N'
      AND b.InsertDate BETWEEN :ms AND :me
";
$stmt = $pdo->prepare($sql_head);
$stmt->execute(array(
    ':sg1' => $selfGen, ':sg2' => $selfGen, ':sg3' => $selfGen, ':sg4' => $selfGen,
    ':ms'  => $ms,      ':me'  => $me,
));
$head = $stmt->fetch(PDO::FETCH_ASSOC);

// Per-agent breakdown grouped by Team.
$sql_agent = "
    SELECT
        a.AdminID,
        a.Name AS agent_name,
        COALESCE(t.Name, 'Unassigned') AS team_name,
        SUM(CASE WHEN UPPER(TRIM(s.Name)) = :sg1 THEN 1 ELSE 0 END) AS self_gen_cnt,
        COALESCE(SUM(CASE WHEN UPPER(TRIM(s.Name)) = :sg2 THEN b.NetTotal ELSE 0 END), 0) AS self_gen_total,
        SUM(CASE WHEN UPPER(TRIM(s.Name)) <> :sg3 OR s.Name IS NULL THEN 1 ELSE 0 END) AS company_cnt,
        COALESCE(SUM(CASE WHEN UPPER(TRIM(s.Name)) <> :sg4 OR s.Name IS NULL THEN b.NetTotal ELSE 0 END), 0) AS company_total,
        COUNT(*) AS total_cnt
    FROM booking b
    INNER JOIN admin a ON a.AdminID = b.SalesAgent
    LEFT JOIN team t ON t.TeamID = a.TeamID
    LEFT JOIN source s ON s.SourceID = b.Source
    WHERE b.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
      AND b.CancelStatus = 'N'
      AND b.Status != 'N'
      AND b.InsertDate BETWEEN :ms AND :me
      AND b.SalesAgent IS NOT NULL AND b.SalesAgent > 0
    GROUP BY a.AdminID, a.Name, team_name
    ORDER BY (team_name = 'Unassigned'), team_name ASC, a.Name ASC
";
$stmt = $pdo->prepare($sql_agent);
$stmt->execute(array(
    ':sg1' => $selfGen, ':sg2' => $selfGen, ':sg3' => $selfGen, ':sg4' => $selfGen,
    ':ms'  => $ms,      ':me'  => $me,
));
$agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// --- Headline assertions ---
assert_eq('headline self_gen count', 5,      (int)   $head['self_gen_cnt']);
assert_eq('headline self_gen total', 3150.0, (float) $head['self_gen_total']);
assert_eq('headline company count',  3,      (int)   $head['company_cnt']);
assert_eq('headline company total',  1850.0, (float) $head['company_total']);
assert_eq(
    'partition: self_gen + company count = total in-window BCs',
    8, (int) $head['self_gen_cnt'] + (int) $head['company_cnt']
);
assert_eq(
    'partition: self_gen + company total = sum NetTotal',
    5000.0, (float) $head['self_gen_total'] + (float) $head['company_total']
);

// --- Per-agent assertions ---
assert_eq('agent rows count', 4, count($agents));

$by_id = array();
foreach ($agents as $a) { $by_id[(int) $a['AdminID']] = $a; }

assert_eq('A1 team',           'Team A', $by_id[11]['team_name']);
assert_eq('A1 self_gen count', 3,        (int)   $by_id[11]['self_gen_cnt']);
assert_eq('A1 self_gen total', 2400.0,   (float) $by_id[11]['self_gen_total']);
assert_eq('A1 company count',  0,        (int)   $by_id[11]['company_cnt']);

assert_eq('A2 team',           'Team A', $by_id[12]['team_name']);
assert_eq('A2 self_gen count', 1,        (int)   $by_id[12]['self_gen_cnt']);
assert_eq('A2 company count',  1,        (int)   $by_id[12]['company_cnt']);
assert_eq('A2 company total',  400.0,    (float) $by_id[12]['company_total']);

assert_eq('B1 team',           'Team B', $by_id[21]['team_name']);
assert_eq('B1 company count',  1,        (int)   $by_id[21]['company_cnt']);
assert_eq('B1 company total',  1200.0,   (float) $by_id[21]['company_total']);

assert_eq('Solo team',           'Unassigned', $by_id[99]['team_name']);
assert_eq('Solo self_gen count (substring trap)', 0, (int) $by_id[99]['self_gen_cnt']);
assert_eq('Solo company count',  1,                  (int) $by_id[99]['company_cnt']);

// Headline-to-per-agent reconciliation: per-agent sum = headline MINUS the
// NULL-SalesAgent rows (row 8, RM 50 self-gen).
$pa_sg_cnt = 0; $pa_sg_tot = 0.0; $pa_co_cnt = 0; $pa_co_tot = 0.0;
foreach ($agents as $a) {
    $pa_sg_cnt += (int)   $a['self_gen_cnt'];
    $pa_sg_tot += (float) $a['self_gen_total'];
    $pa_co_cnt += (int)   $a['company_cnt'];
    $pa_co_tot += (float) $a['company_total'];
}
assert_eq('per-agent self_gen cnt = headline - NULL-agent',
          (int) $head['self_gen_cnt'] - 1, $pa_sg_cnt);
assert_eq('per-agent self_gen RM  = headline - NULL-agent',
          (float) $head['self_gen_total'] - 50.0, $pa_sg_tot);
assert_eq('per-agent company cnt = headline',
          (int) $head['company_cnt'], $pa_co_cnt);
assert_eq('per-agent company RM  = headline',
          (float) $head['company_total'], $pa_co_tot);

// Team-grouping order check — agents sharing a Team stay consecutive, with
// Unassigned last, so the view's rowspan grouping works without re-sorting.
$order_keys = array();
foreach ($agents as $a) { $order_keys[] = $a['team_name']; }
assert_eq('team-grouped order', array('Team A', 'Team A', 'Team B', 'Unassigned'), $order_keys);

echo "\nAll assertions passed.\n";
