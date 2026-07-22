<?php
/**
 * Run with: php tests/helpers/NotificationVisibilityFilterTest.php
 *
 * Locks the contract for Notification_Model::_apply_visibility_filter
 * (exercised through Get_Unread_Count, the leanest public entry point).
 *
 * The notification bell/page scopes the front-line roles (SALES AGENT level
 * 20, OP level 40) to the bookings they sit on. The seat check must match
 * EITHER seat (SalesAgent OR BookingOP), never the single seat implied by the
 * viewer's current level — otherwise a staff member moved between roles
 * (e.g. TC -> OP) loses the notifications on bookings they were assigned to
 * under their old role.
 *
 * Higher roles (OWNER, FINANCE, TEAM LEAD, TC, OP TEAM LEAD) keep their
 * unrestricted view: no per-seat booking clause is added for them.
 *
 * Regression guard: the previous implementation gated level 20 to SalesAgent
 * only and level 40 to BookingOP only, silently hiding old-role bookings.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

if (!class_exists('CI_Model')) {
    class CI_Model {}
}

// Records where() clauses and answers the model's raw level lookup with a
// configurable level. count_all_results() returns 0 (count is irrelevant).
class NotifRecordingDbFacade
{
    public $where_clauses = [];
    public $level;

    public function __construct($level) { $this->level = $level; }

    public function select($cols) { return $this; }
    public function join($table, $cond, $type = 'inner') { return $this; }
    public function order_by($field, $dir = 'ASC') { return $this; }
    public function limit($limit, $offset = 0) { return $this; }

    public function where($field, $value = null, $escape = true)
    {
        $this->where_clauses[] = ($escape === false)
            ? $field
            : trim($field) . ' = ' . var_export($value, true);
        return $this;
    }

    // _apply_visibility_filter fetches the viewer's level via a raw query.
    public function query($sql, $params = [])
    {
        $level = $this->level;
        return new class($level) {
            private $level;
            public function __construct($level) { $this->level = $level; }
            public function row() {
                return $this->level === null ? null : (object)['level' => $this->level];
            }
        };
    }

    public function count_all_results($table) { return 0; }
}

require_once __DIR__ . '/../../application/models/Notification_Model.php';

function make_notif_model($level)
{
    $nm = new Notification_Model();
    $nm->db = new NotifRecordingDbFacade($level);
    return $nm;
}

$assertions = [];
function assert_true($label, $cond) {
    global $assertions;
    $assertions[$label] = (bool)$cond;
    if (!$cond) { echo "    FAILED: $label" . PHP_EOL; }
}

$UID = 7;

// --- Front-line roles: scoped to either seat -----------------------------
foreach (['SALES AGENT (20)' => 20, 'OP (40)' => 40] as $label => $level) {
    $nm = make_notif_model($level);
    $nm->Get_Unread_Count($UID);
    $sql = implode(' ', $nm->db->where_clauses);

    // The booking-owner clause must accept BOTH seats.
    assert_true("$label: booking clause checks SalesAgent seat",
        strpos($sql, "notification.owner_type != 'booking' OR booking.SalesAgent = $UID") !== false);
    assert_true("$label: booking clause checks BookingOP seat",
        strpos($sql, "booking.BookingOP = $UID") !== false);
    // The remark-type clause always references both seats too.
    assert_true("$label: remark clause references both seats",
        strpos($sql, "notification.type != 'remark' OR booking.SalesAgent = $UID OR booking.BookingOP = $UID") !== false);
}

// --- Higher roles: unrestricted (no per-seat booking clause) -------------
foreach (['OWNER (10)' => 10, 'FINANCE (30)' => 30, 'TC (50)' => 50] as $label => $level) {
    $nm = make_notif_model($level);
    $nm->Get_Unread_Count($UID);
    $sql = implode(' ', $nm->db->where_clauses);

    assert_true("$label: no per-seat booking-owner restriction added",
        strpos($sql, "notification.owner_type != 'booking'") === false);
    // The remark-type clause still applies to everyone.
    assert_true("$label: remark clause still references both seats",
        strpos($sql, "notification.type != 'remark' OR booking.SalesAgent = $UID OR booking.BookingOP = $UID") !== false);
}

// --- report ---------------------------------------------------------------

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) { $failed++; }
}
echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
