<?php
/**
 * Run with: php tests/helpers/ResolveBookingChecklistTeamLeadsTest.php
 *
 * Locks the contract for resolve_booking_checklist_team_leads(): it maps a
 * booking's SalesAgent/BookingOP to the two team-lead slots that gate checklist
 * ticking, but ONLY honours a lead pointer that still resolves to an ACTIVE
 * admin of the role that slot requires:
 *   - tc1_tl  comes from SalesAgent.TeamLeadID   -> must be a Level-25, Status-Y lead
 *   - op_tl   comes from BookingOP.OpTeamLeadID  -> must be a Level-45, Status-Y lead
 *
 * This mirrors the admin listing/edit form, which already hide a TeamLeadID
 * that points to anyone who is not an active Level-25 lead. Without the filter,
 * a stale pointer (e.g. CHEN.TeamLeadID -> HANI, who is a Level-45 OP lead, not
 * a sales lead; or a since-disabled lead) would silently grant checklist rights
 * the UI shows as unassigned.
 *
 * Exercised via a minimal get_instance() shim + sqlite-backed db facade — no
 * full CodeIgniter bootstrap.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

// Thin facade over PDO exposing the chained query-builder surface used by the
// function under test: select(), where_in(), get('table')->result().
class TestDbFacade
{
    public $pdo;
    private $where_in = null;
    private $select = '*';

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function select($cols) { $this->select = $cols; return $this; }

    public function where_in($field, array $values)
    {
        $place = implode(',', array_fill(0, count($values), '?'));
        $this->where_in = [$field, $place, array_values($values)];
        return $this;
    }

    public function get($table)
    {
        $sql = "SELECT {$this->select} FROM $table";
        $params = [];
        if ($this->where_in !== null) {
            [$field, $place, $vals] = $this->where_in;
            $sql .= " WHERE $field IN ($place)";
            $params = $vals;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
        $this->where_in = null;
        $this->select = '*';
        return new class($rows) {
            private $rows;
            public function __construct($rows) { $this->rows = $rows; }
            public function result() { return $this->rows; }
        };
    }
}

// get_instance() shim returning an object whose ->db is the facade.
$GLOBALS['__ci_instance'] = null;
if (!function_exists('get_instance')) {
    function &get_instance() { return $GLOBALS['__ci_instance']; }
}

require_once __DIR__ . '/../../application/helpers/booking_flow_helper.php';

// --- seed admin table ----------------------------------------------------

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE admin (
    AdminID INTEGER PRIMARY KEY,
    Name TEXT,
    Level TEXT,
    Status TEXT,
    TeamLeadID INTEGER,
    OpTeamLeadID INTEGER
)");

// Leads / agents:
//  20 HANI    L45 Y  -> active OP lead (NOT a valid sales lead)
//  25 SARAH   L25 Y  -> active sales lead (valid tc1)
//  27 KELVIN  L25 D  -> disabled sales lead (invalid)
//  45 OPLEAD  L45 Y  -> active OP lead (valid op)
//  46 OPGONE  L45 D  -> disabled OP lead (invalid)
//
// Agents:
//  30 CHEN    L40, TeamLeadID=20 (HANI L45)   -> stale: tc1 must be NULL
//   4 NATASHA L20, TeamLeadID=27 (KELVIN L25 D)-> disabled: tc1 must be NULL
//   7 GOODSA  L20, TeamLeadID=25 (SARAH L25 Y) -> valid: tc1 = 25
//   9 OPSTAFF L40, OpTeamLeadID=45 (valid)     -> valid: op = 45
//  90 OPSTALE L40, OpTeamLeadID=46 (OPGONE D)  -> invalid: op NULL
//  91 OPWRONG L40, OpTeamLeadID=25 (SARAH L25) -> wrong level: op NULL
//  29 JESIKA  L40, TeamLeadID=0 (orphan)       -> tc1 NULL
$pdo->exec("INSERT INTO admin VALUES
    (20, 'HANI',    '45', 'Y', NULL, NULL),
    (25, 'SARAH',   '25', 'Y', NULL, NULL),
    (27, 'KELVIN',  '25', 'D', NULL, NULL),
    (45, 'OPLEAD',  '45', 'Y', NULL, NULL),
    (46, 'OPGONE',  '45', 'D', NULL, NULL),
    (30, 'CHEN',    '40', 'Y', 20,   NULL),
    (4,  'NATASHA', '20', 'Y', 27,   NULL),
    (7,  'GOODSA',  '20', 'Y', 25,   NULL),
    (9,  'OPSTAFF', '40', 'Y', NULL, 45),
    (90, 'OPSTALE', '40', 'Y', NULL, 46),
    (91, 'OPWRONG', '40', 'Y', NULL, 25),
    (29, 'JESIKA',  '40', 'Y', 0,    NULL)
");

$ci = new stdClass();
$ci->db = new TestDbFacade($pdo);
$GLOBALS['__ci_instance'] = $ci;

function assert_eq($label, $expected, $actual) {
    global $assertions;
    $ok = ($expected === $actual);
    $assertions[$label] = $ok;
    if (!$ok) {
        echo "    expected: " . var_export($expected, true) . PHP_EOL;
        echo "    actual:   " . var_export($actual, true) . PHP_EOL;
    }
}

$assertions = [];

// CHEN's TeamLeadID points to HANI (L45) — not a valid sales lead -> NULL.
// This is the BC-2606-0042 case: Hani must NOT be resolved as CHEN's tc1 lead.
assert_eq(
    'CHEN.TeamLeadID -> HANI (L45) is NOT a valid sales lead -> tc1 NULL',
    ['tc1_tl' => null, 'op_tl' => null],
    resolve_booking_checklist_team_leads(['SalesAgent' => 30, 'BookingOP' => null])
);

// Disabled sales lead -> dropped.
assert_eq(
    'NATASHA.TeamLeadID -> KELVIN (L25, Status D) -> tc1 NULL',
    ['tc1_tl' => null, 'op_tl' => null],
    resolve_booking_checklist_team_leads(['SalesAgent' => 4, 'BookingOP' => null])
);

// Valid active Level-25 sales lead -> honoured.
assert_eq(
    'GOODSA.TeamLeadID -> SARAH (L25, Y) -> tc1 = 25',
    ['tc1_tl' => 25, 'op_tl' => null],
    resolve_booking_checklist_team_leads(['SalesAgent' => 7, 'BookingOP' => null])
);

// Valid active Level-45 OP lead -> honoured.
assert_eq(
    'OPSTAFF.OpTeamLeadID -> OPLEAD (L45, Y) -> op = 45',
    ['tc1_tl' => null, 'op_tl' => 45],
    resolve_booking_checklist_team_leads(['SalesAgent' => null, 'BookingOP' => 9])
);

// Disabled OP lead -> dropped.
assert_eq(
    'OPSTALE.OpTeamLeadID -> OPGONE (L45, D) -> op NULL',
    ['tc1_tl' => null, 'op_tl' => null],
    resolve_booking_checklist_team_leads(['SalesAgent' => null, 'BookingOP' => 90])
);

// OP slot pointing to a Level-25 admin (wrong role) -> dropped.
assert_eq(
    'OPWRONG.OpTeamLeadID -> SARAH (L25, wrong role for op slot) -> op NULL',
    ['tc1_tl' => null, 'op_tl' => null],
    resolve_booking_checklist_team_leads(['SalesAgent' => null, 'BookingOP' => 91])
);

// Orphan pointer TeamLeadID = 0 -> NULL.
assert_eq(
    'JESIKA.TeamLeadID = 0 (orphan) -> tc1 NULL',
    ['tc1_tl' => null, 'op_tl' => null],
    resolve_booking_checklist_team_leads(['SalesAgent' => 29, 'BookingOP' => null])
);

// Both slots valid on one booking.
assert_eq(
    'GOODSA (sales) + OPSTAFF (op) -> tc1 = 25, op = 45',
    ['tc1_tl' => 25, 'op_tl' => 45],
    resolve_booking_checklist_team_leads(['SalesAgent' => 7, 'BookingOP' => 9])
);

// Unassigned booking -> both NULL.
assert_eq(
    'unassigned booking -> both NULL',
    ['tc1_tl' => null, 'op_tl' => null],
    resolve_booking_checklist_team_leads(['SalesAgent' => null, 'BookingOP' => null])
);

// Empty / null booking -> both NULL.
assert_eq(
    'null booking -> both NULL',
    ['tc1_tl' => null, 'op_tl' => null],
    resolve_booking_checklist_team_leads(null)
);

// Object booking shape also supported.
assert_eq(
    'object booking: CHEN -> tc1 NULL (HANI invalid)',
    ['tc1_tl' => null, 'op_tl' => null],
    resolve_booking_checklist_team_leads((object) ['SalesAgent' => 30, 'BookingOP' => null])
);

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
