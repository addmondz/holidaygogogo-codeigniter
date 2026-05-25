<?php
/**
 * Run with: php tests/helpers/RemarkNotificationInsertTest.php
 *
 * Exercises Notification_Model::Create_Remark_Notifications_For_Users (the
 * explicit @mention path) and Create_Remark_Notifications (the auto-notify
 * path for the booking's SalesAgent + BookingOP) end-to-end against an
 * in-memory sqlite stand-in for the production tables.
 *
 * Contract being locked:
 *   - One 'remark' notification per active recipient. Inactive (Status='N')
 *     or non-existent admin rows are silently dropped.
 *   - The commenter is never notified, even if they appear in the tagged set
 *     or are the SalesAgent / BookingOP of the booking.
 *   - Idempotent on (user_id, remark_id): re-running the same call inserts
 *     no duplicate row.
 *   - When SalesAgent == BookingOP, only one row is inserted for the auto path.
 *   - Message is "{CommenterName} added a remark"; type='remark';
 *     owner_type='booking'.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

if (!class_exists('CI_Model')) {
    class CI_Model {}
}

// A small chainable query-builder facade backed by PDO/sqlite. Covers the
// surface used by the two methods under test (select/where/where_in/get/
// insert/insert_id) — extend cautiously if production code adds new calls.
class TestDbFacade
{
    public $pdo;
    private $where = [];
    private $params = [];
    private $select = '*';
    private $last_insert_id = null;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function select($cols) { $this->select = $cols; return $this; }

    public function where($field, $value = null)
    {
        $this->where[] = "$field = ?";
        $this->params[] = $value;
        return $this;
    }

    public function get($table)
    {
        $sql = "SELECT {$this->select} FROM $table";
        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
        $this->reset();
        return new class($rows) {
            private $rows;
            public function __construct($rows) { $this->rows = $rows; }
            public function result() { return $this->rows; }
            public function row() { return $this->rows[0] ?? null; }
        };
    }

    public function insert($table, $data)
    {
        $cols = array_keys($data);
        $placeholders = array_fill(0, count($cols), '?');
        $sql = "INSERT INTO $table (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        $this->last_insert_id = (int)$this->pdo->lastInsertId();
        return true;
    }

    public function insert_id() { return $this->last_insert_id; }

    private function reset()
    {
        $this->where = [];
        $this->params = [];
        $this->select = '*';
    }
}

// --- seed schema ---------------------------------------------------------

function fresh_pdo()
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE TABLE admin (
        AdminID INTEGER PRIMARY KEY,
        Name TEXT,
        Status TEXT
    )");
    $pdo->exec("CREATE TABLE booking (
        BookingID INTEGER PRIMARY KEY,
        BookingNumber TEXT,
        Customer TEXT,
        SalesAgent INTEGER,
        BookingOP INTEGER
    )");
    $pdo->exec("CREATE TABLE notification (
        NotificationID INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        type TEXT,
        owner_type TEXT,
        owner_id INTEGER,
        remark_id INTEGER,
        message TEXT,
        is_read INTEGER DEFAULT 0,
        created_at TEXT
    )");

    // 100 = commenter 'Alice'
    // 200 = Ernida (active) — primary target user
    // 300 = Bob (active, SalesAgent of test bookings)
    // 400 = OpUser (active, BookingOP)
    // 500 = Inactive (Status='N')
    $pdo->exec("INSERT INTO admin VALUES
        (100, 'Alice',    'Y'),
        (200, 'Ernida',   'Y'),
        (300, 'Bob',      'Y'),
        (400, 'OpUser',   'Y'),
        (500, 'Inactive', 'N')
    ");

    return $pdo;
}

require_once __DIR__ . '/../../application/models/Notification_Model.php';

function make_model(PDO $pdo)
{
    $nm = new Notification_Model();
    $nm->db = new TestDbFacade($pdo);
    return $nm;
}

function row_count(PDO $pdo, $where_sql = '1=1', $params = [])
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notification WHERE $where_sql");
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

$assertions = [];
function assert_eq($label, $expected, $actual) {
    global $assertions;
    $ok = ($expected === $actual);
    $assertions[$label] = $ok;
    if (!$ok) {
        echo "    expected: " . var_export($expected, true) . PHP_EOL;
        echo "    actual:   " . var_export($actual, true) . PHP_EOL;
    }
}

// ============================================================
// Create_Remark_Notifications_For_Users (@mention path)
// ============================================================

// --- happy path: one active tagged user gets one row ---------------------
{
    $pdo = fresh_pdo();
    $pdo->exec("INSERT INTO booking VALUES (1, 'BK001', 'Cust', 999, 999)");
    $nm = make_model($pdo);
    $created = $nm->Create_Remark_Notifications_For_Users(1, 10, 100, 'hi @ernida', [200]);

    assert_eq('forUsers: returns 1', 1, $created);
    assert_eq('forUsers: one row for ernida',
        1, row_count($pdo, 'user_id=? AND remark_id=?', [200, 10]));

    $row = $pdo->query("SELECT type, owner_type, owner_id, message FROM notification WHERE user_id=200")->fetch(PDO::FETCH_OBJ);
    assert_eq('forUsers: type=remark',          'remark',  $row->type);
    assert_eq('forUsers: owner_type=booking',   'booking', $row->owner_type);
    assert_eq('forUsers: owner_id=1',           1,         (int)$row->owner_id);
    assert_eq('forUsers: message has commenter name',
        'Alice added a remark', $row->message);
}

// --- commenter is excluded even if in $user_ids --------------------------
{
    $pdo = fresh_pdo();
    $pdo->exec("INSERT INTO booking VALUES (1, 'BK001', 'Cust', 999, 999)");
    $nm = make_model($pdo);
    $created = $nm->Create_Remark_Notifications_For_Users(1, 11, 100, 'self-tag', [100, 200]);

    assert_eq('forUsers: self-tag excluded (returns 1)', 1, $created);
    assert_eq('forUsers: no row for commenter',
        0, row_count($pdo, 'user_id=? AND remark_id=?', [100, 11]));
    assert_eq('forUsers: row for other tagged user',
        1, row_count($pdo, 'user_id=? AND remark_id=?', [200, 11]));
}

// --- inactive admin is silently dropped ----------------------------------
{
    $pdo = fresh_pdo();
    $pdo->exec("INSERT INTO booking VALUES (1, 'BK001', 'Cust', 999, 999)");
    $nm = make_model($pdo);
    $created = $nm->Create_Remark_Notifications_For_Users(1, 12, 100, 'hi @inactive @ernida', [500, 200]);

    assert_eq('forUsers: inactive dropped (returns 1)', 1, $created);
    assert_eq('forUsers: no row for inactive (id=500)',
        0, row_count($pdo, 'user_id=?', [500]));
    assert_eq('forUsers: row for ernida',
        1, row_count($pdo, 'user_id=? AND remark_id=?', [200, 12]));
}

// --- dedupe on (user_id, remark_id) --------------------------------------
{
    $pdo = fresh_pdo();
    $pdo->exec("INSERT INTO booking VALUES (1, 'BK001', 'Cust', 999, 999)");
    $nm = make_model($pdo);
    $first  = $nm->Create_Remark_Notifications_For_Users(1, 13, 100, 'first',  [200]);
    $second = $nm->Create_Remark_Notifications_For_Users(1, 13, 100, 'second', [200]);

    assert_eq('forUsers: first run inserts 1', 1, $first);
    assert_eq('forUsers: re-run inserts 0',    0, $second);
    assert_eq('forUsers: still only 1 row for that (user, remark)',
        1, row_count($pdo, 'user_id=? AND remark_id=?', [200, 13]));
}

// --- unknown admin id silently dropped -----------------------------------
{
    $pdo = fresh_pdo();
    $pdo->exec("INSERT INTO booking VALUES (1, 'BK001', 'Cust', 999, 999)");
    $nm = make_model($pdo);
    $created = $nm->Create_Remark_Notifications_For_Users(1, 14, 100, 'who?', [99999]);

    assert_eq('forUsers: unknown admin -> 0 inserted', 0, $created);
    assert_eq('forUsers: no rows', 0, row_count($pdo));
}

// ============================================================
// Create_Remark_Notifications (SA + BookingOP auto-notify)
// ============================================================

// --- SA + OP both notified, both distinct from commenter ----------------
{
    $pdo = fresh_pdo();
    $pdo->exec("INSERT INTO booking VALUES (1, 'BK001', 'Cust', 300, 400)");
    $nm = make_model($pdo);
    $created = $nm->Create_Remark_Notifications(1, 20, 100, 'auto-notify check');

    assert_eq('auto: SA + OP -> returns 2', 2, $created);
    assert_eq('auto: SA notified',
        1, row_count($pdo, 'user_id=? AND remark_id=?', [300, 20]));
    assert_eq('auto: OP notified',
        1, row_count($pdo, 'user_id=? AND remark_id=?', [400, 20]));
}

// --- commenter is the SA -> only OP notified ----------------------------
{
    $pdo = fresh_pdo();
    $pdo->exec("INSERT INTO booking VALUES (1, 'BK001', 'Cust', 100, 400)");
    $nm = make_model($pdo);
    $created = $nm->Create_Remark_Notifications(1, 21, 100, 'sa is commenter');

    assert_eq('auto: SA==commenter -> only OP -> returns 1', 1, $created);
    assert_eq('auto: no row for commenter (SA seat)',
        0, row_count($pdo, 'user_id=? AND remark_id=?', [100, 21]));
    assert_eq('auto: OP still notified',
        1, row_count($pdo, 'user_id=? AND remark_id=?', [400, 21]));
}

// --- SA == OP -> collapse to one row -----------------------------------
{
    $pdo = fresh_pdo();
    $pdo->exec("INSERT INTO booking VALUES (1, 'BK001', 'Cust', 300, 300)");
    $nm = make_model($pdo);
    $created = $nm->Create_Remark_Notifications(1, 22, 100, 'SA == OP');

    assert_eq('auto: SA==OP -> returns 1', 1, $created);
    assert_eq('auto: exactly one row for that admin',
        1, row_count($pdo, 'user_id=? AND remark_id=?', [300, 22]));
}

// --- SA inactive -> skipped, OP still notified --------------------------
{
    $pdo = fresh_pdo();
    $pdo->exec("INSERT INTO booking VALUES (1, 'BK001', 'Cust', 500, 400)");
    $nm = make_model($pdo);
    $created = $nm->Create_Remark_Notifications(1, 23, 100, 'inactive SA');

    assert_eq('auto: inactive SA skipped -> returns 1', 1, $created);
    assert_eq('auto: no row for inactive SA',
        0, row_count($pdo, 'user_id=? AND remark_id=?', [500, 23]));
    assert_eq('auto: OP still notified',
        1, row_count($pdo, 'user_id=? AND remark_id=?', [400, 23]));
}

// --- no SA, no OP -> nothing inserted, returns 0 -----------------------
{
    $pdo = fresh_pdo();
    $pdo->exec("INSERT INTO booking VALUES (1, 'BK001', 'Cust', NULL, NULL)");
    $nm = make_model($pdo);
    $created = $nm->Create_Remark_Notifications(1, 24, 100, 'no assignees');

    assert_eq('auto: no SA/OP -> returns 0', 0, $created);
    assert_eq('auto: no rows', 0, row_count($pdo));
}

// --- missing booking -> returns 0, no crash ----------------------------
{
    $pdo = fresh_pdo();
    $nm = make_model($pdo);
    $created = $nm->Create_Remark_Notifications(99, 25, 100, 'no booking');

    assert_eq('auto: missing booking -> returns 0', 0, $created);
    assert_eq('auto: no rows', 0, row_count($pdo));
}

// --- dedupe on (user_id, remark_id) for auto path ----------------------
{
    $pdo = fresh_pdo();
    $pdo->exec("INSERT INTO booking VALUES (1, 'BK001', 'Cust', 300, 400)");
    $nm = make_model($pdo);
    $first  = $nm->Create_Remark_Notifications(1, 26, 100, 'first');
    $second = $nm->Create_Remark_Notifications(1, 26, 100, 'second');

    assert_eq('auto: first run -> 2', 2, $first);
    assert_eq('auto: re-run -> 0',    0, $second);
    assert_eq('auto: still only 1 row for SA', 1, row_count($pdo, 'user_id=? AND remark_id=?', [300, 26]));
    assert_eq('auto: still only 1 row for OP', 1, row_count($pdo, 'user_id=? AND remark_id=?', [400, 26]));
}

// --- report ---------------------------------------------------------------

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) { $failed++; }
}
echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
