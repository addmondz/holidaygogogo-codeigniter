<?php
/**
 * Run with: php tests/helpers/RemarkVisibilityFilterTest.php
 *
 * Locks the contract for Remark_Model::_Apply_Visibility_Filter (exercised
 * through Get_All_Remarks_Count, which is the leanest public entry point).
 *
 * The Messages/Remarks dropdown must show a staff member every booking they
 * sit on — whether as SalesAgent (TC seat) OR BookingOP (OP seat) — plus any
 * remark they were @mentioned on. Crucially this must NOT depend on the
 * viewer's *current* level. A staff member moved TC -> OP keeps seeing the
 * remarks on their old TC-seat bookings; the filter looks at "are you on this
 * booking", never at "what is your job title today".
 *
 * Regression guard: the previous implementation branched on $user_level and,
 * for levels 20 (SALES AGENT) and 40 (OP), checked only ONE seat — so a
 * role switch silently hid old bookings from the Messages list.
 *
 * Why a recording facade and not real SQL: the production join string embeds
 * a `"booking"` double-quoted literal, which SQLite parses as an identifier
 * rather than a string. The existing Notification_Model test sidesteps joins
 * for the same reason. We instead capture every WHERE clause the model hands
 * the query builder and assert the visibility contract on the SQL fragment.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

if (!class_exists('CI_Model')) {
    class CI_Model {}
}

// Records every where()/join() the model issues. count_all_results() returns 0
// (the row count is irrelevant here) and resets state like CodeIgniter does.
class RecordingDbFacade
{
    public $where_clauses = [];

    public function select($cols) { return $this; }

    public function join($table, $cond, $type = 'inner') { return $this; }

    public function order_by($field, $dir = 'ASC') { return $this; }

    public function limit($limit, $offset = 0) { return $this; }

    // CodeIgniter where(): when $escape === false the $field is raw SQL and
    // $value is ignored. Otherwise it's "field [op] value".
    public function where($field, $value = null, $escape = true)
    {
        if ($escape === false) {
            $this->where_clauses[] = $field;
        } else {
            $this->where_clauses[] = trim($field) . ' = ' . var_export($value, true);
        }
        return $this;
    }

    public function count_all_results($table)
    {
        return 0;
    }
}

require_once __DIR__ . '/../../application/models/Remark_Model.php';

function make_remark_model()
{
    $rm = new Remark_Model();
    $rm->db = new RecordingDbFacade();
    return $rm;
}

$assertions = [];
function assert_true($label, $cond) {
    global $assertions;
    $assertions[$label] = (bool)$cond;
    if (!$cond) {
        echo "    FAILED: $label" . PHP_EOL;
    }
}

// The whole point of the fix: the visibility contract is identical across
// every level. We run the same assertions for SALES AGENT, OP, TC and the
// no-level fallback.
$LEVELS = [
    'SALES AGENT (20)' => 20,
    'OP (40)'          => 40,
    'TC (50)'          => 50,
    'no level (null)'  => null,
];

$UID = 7;

foreach ($LEVELS as $label => $level) {
    $rm = make_remark_model();
    $rm->Get_All_Remarks_Count(1, $UID, $level);

    // Join the raw fragments so we can scan for the seat checks regardless of
    // how many separate where() calls the model made.
    $sql = implode(' ', $rm->db->where_clauses);

    assert_true("$label: checks SalesAgent seat",
        strpos($sql, "booking.SalesAgent = $UID") !== false);
    assert_true("$label: checks BookingOP seat",
        strpos($sql, "booking.BookingOP = $UID") !== false);
    assert_true("$label: honours @mention (notification EXISTS)",
        strpos($sql, 'EXISTS') !== false && strpos($sql, 'n.user_id = ' . $UID) !== false);
}

// --- report ---------------------------------------------------------------

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) { $failed++; }
}
echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
