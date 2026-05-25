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
 *      still feed the headline card (so partition only holds for the
 *      headline, and the per-agent table sum can be < the headline).
 *   8. Per-agent attribution is by primary SalesAgent (TC1), NOT
 *      SalesAgent2, regardless of InsertDate — this is the one TC LEAD
 *      card that intentionally bypasses the TC1/TC2 cutoff rule.
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
    TeamLeadID INTEGER
)");
$pdo->exec("CREATE TABLE source (
    SourceID INTEGER PRIMARY KEY,
    Name TEXT
)");

// Team A = lead 10 with agents 11, 12. Team B = lead 20 with agent 21.
// Solo agent 99 has no team lead.
$pdo->exec("INSERT INTO admin VALUES
    (10, 'Lead A',   NULL),
    (11, 'Agent A1', 10),
    (12, 'Agent A2', 10),
    (20, 'Lead B',   NULL),
    (21, 'Agent B1', 20),
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

$month_start = '2026-05-01';
$month_end   = '2026-05-31';
$cutoff      = '2026-06-01'; // TC2 cutoff — not used by this card, but we
                             // mix InsertDates around it to lock that
                             // attribution stays on SalesAgent (TC1).

// Booking grid:
//  - 1, 2 : Agent A1, SELF GEN (id 1)              -> A's self-gen RM 1500
//  - 3    : Agent A2, '  self gen  ' (id 2)        -> A's self-gen RM 700 (normalisation)
//  - 4    : Agent A2, NULL source                  -> A's company RM 400 (NULL -> Company)
//  - 5    : Agent B1, WHATSAPP (id 3)              -> B's company RM 1200
//  - 6    : Solo, SELF GENERATED (id 4)            -> Solo's company RM 250 (substring must NOT match)
//  - 7    : Agent A1, SELF GEN, but on/after cutoff with SalesAgent2 set
//           -> still credited to SalesAgent (TC1 = 11) because this card
//              bypasses the TC1/TC2 rule. RM 900 to A's self-gen.
//  - 8    : NULL SalesAgent, SELF GEN              -> headline only (Self Gen +1, RM 50)
//  - 9    : cancelled                              -> excluded
//  - 10   : draft                                  -> excluded
//  - 11   : quotation                              -> excluded
//  - 12   : out of window                          -> excluded
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

// Booking 7 is dated 2026-06-15 (on/after the TC2 cutoff) and exists to
// prove attribution stays on SalesAgent for this card. Widen the month
// window to include it for the headline + per-agent assertions below.
$ms = '2026-05-01';
$me = '2026-06-30';

// Headline split. The bucket predicate is the same one the controller
// uses, only with TRIM placed outside UPPER so SQLite matches MySQL's
// behaviour on '  self gen  '.
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

// Per-agent breakdown grouped by team lead.
$sql_agent = "
    SELECT
        a.AdminID,
        a.Name AS agent_name,
        COALESCE(tl.Name, 'Unassigned') AS team_lead_name,
        SUM(CASE WHEN UPPER(TRIM(s.Name)) = :sg1 THEN 1 ELSE 0 END) AS self_gen_cnt,
        COALESCE(SUM(CASE WHEN UPPER(TRIM(s.Name)) = :sg2 THEN b.NetTotal ELSE 0 END), 0) AS self_gen_total,
        SUM(CASE WHEN UPPER(TRIM(s.Name)) <> :sg3 OR s.Name IS NULL THEN 1 ELSE 0 END) AS company_cnt,
        COALESCE(SUM(CASE WHEN UPPER(TRIM(s.Name)) <> :sg4 OR s.Name IS NULL THEN b.NetTotal ELSE 0 END), 0) AS company_total,
        COUNT(*) AS total_cnt
    FROM booking b
    INNER JOIN admin a ON a.AdminID = b.SalesAgent
    LEFT JOIN admin tl ON tl.AdminID = a.TeamLeadID
    LEFT JOIN source s ON s.SourceID = b.Source
    WHERE b.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
      AND b.CancelStatus = 'N'
      AND b.Status != 'N'
      AND b.InsertDate BETWEEN :ms AND :me
      AND b.SalesAgent IS NOT NULL AND b.SalesAgent > 0
    GROUP BY a.AdminID, a.Name, team_lead_name
    ORDER BY (team_lead_name = 'Unassigned'), team_lead_name ASC, a.Name ASC
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
// Self Gen rows: 1, 2, 3 (whitespace-norm), 7 (post-cutoff), 8 (no agent) = 5
// Self Gen RM:   1000 + 500 + 700 + 900 + 50 = 3150
// Company rows:  4 (NULL), 5 (WHATSAPP), 6 (SELF GENERATED — substring trap) = 3
// Company RM:    400 + 1200 + 250 = 1850
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
// Expected per-agent rows (in order Lead A's agents first, then Lead B, then Solo):
//   Agent A1 (id 11), team Lead A:
//     self_gen_cnt = 3 (rows 1,2,7 — row 7 stays with TC1 across cutoff)
//     self_gen_total = 1000+500+900 = 2400
//     company_cnt    = 0
//   Agent A2 (id 12), team Lead A:
//     self_gen_cnt = 1 (row 3 — normalisation)
//     self_gen_total = 700
//     company_cnt    = 1 (row 4 — NULL source)
//     company_total  = 400
//   Agent B1 (id 21), team Lead B:
//     self_gen_cnt = 0
//     company_cnt  = 1 (row 5)
//     company_total= 1200
//   Solo (id 99), Unassigned:
//     self_gen_cnt = 0 (row 6 'SELF GENERATED' must NOT match SELF GEN substring trap)
//     company_cnt  = 1
//     company_total= 250
// Per-agent SUM(self_gen) = 4 (row 8 with NULL SalesAgent excluded).
// Per-agent SUM(company)  = 3.
assert_eq('agent rows count', 4, count($agents));

$by_id = array();
foreach ($agents as $a) { $by_id[(int) $a['AdminID']] = $a; }

assert_eq('A1 team',           'Lead A', $by_id[11]['team_lead_name']);
assert_eq('A1 self_gen count', 3,        (int)   $by_id[11]['self_gen_cnt']);
assert_eq('A1 self_gen total', 2400.0,   (float) $by_id[11]['self_gen_total']);
assert_eq('A1 company count',  0,        (int)   $by_id[11]['company_cnt']);

assert_eq('A2 team',           'Lead A', $by_id[12]['team_lead_name']);
assert_eq('A2 self_gen count', 1,        (int)   $by_id[12]['self_gen_cnt']);
assert_eq('A2 company count',  1,        (int)   $by_id[12]['company_cnt']);
assert_eq('A2 company total',  400.0,    (float) $by_id[12]['company_total']);

assert_eq('B1 team',           'Lead B', $by_id[21]['team_lead_name']);
assert_eq('B1 company count',  1,        (int)   $by_id[21]['company_cnt']);
assert_eq('B1 company total',  1200.0,   (float) $by_id[21]['company_total']);

assert_eq('Solo team',           'Unassigned', $by_id[99]['team_lead_name']);
assert_eq('Solo self_gen count (substring trap)', 0, (int) $by_id[99]['self_gen_cnt']);
assert_eq('Solo company count',  1,                  (int) $by_id[99]['company_cnt']);

// Headline-to-per-agent reconciliation: the per-agent sum is the headline
// MINUS the NULL-SalesAgent rows (row 8, RM 50 self-gen).
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

// Team-grouping order check — the controller's ORDER BY must keep agents
// sharing a team lead in consecutive rows, with Unassigned last, so the
// view's rowspan grouping works without re-sorting.
$order_keys = array();
foreach ($agents as $a) { $order_keys[] = $a['team_lead_name']; }
assert_eq('team-grouped order', array('Lead A', 'Lead A', 'Lead B', 'Unassigned'), $order_keys);

echo "\nAll assertions passed.\n";
