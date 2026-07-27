<?php
/**
 * Run with: php tests/helpers/MentionHandleResolveTest.php
 *
 * Locks the contract for @mention parsing + handle resolution that powers
 * Booking internal-remark notifications:
 *
 *   - Regex /@([a-z0-9]+)/ extracts only lowercase handles from remark content
 *     (uppercase "@Ernida" does not match — handles come from autocomplete,
 *     which always inserts lowercase).
 *   - Build_Admin_Handles() rule: strip non-[a-z0-9] from admin.Name, lowercase.
 *     Empty name → "user{AdminID}". On collision, every colliding admin gets
 *     AdminID suffixed.
 *   - Resolve_Handles_To_User_Ids() returns AdminIDs only for active admins
 *     (Status='Y') whose handle matches an input handle (lowercased + deduped).
 *     Unknown / inactive handles are silently dropped.
 *
 * The Notification_Model methods are exercised directly via a minimal CI_Model
 * stub + sqlite-backed $this->db facade — no full CodeIgniter bootstrap.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

// --- minimal CI shim so Notification_Model loads -------------------------

if (!class_exists('CI_Model')) {
    class CI_Model {}
}

// Thin facade over PDO that exposes only the chained query-builder surface
// used by the methods under test: where(), get('table')->result()/->row().
class TestDbFacade
{
    public $pdo;
    private $where = [];
    private $params = [];
    private $select = '*';

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

    private function reset()
    {
        $this->where = [];
        $this->params = [];
        $this->select = '*';
    }
}

// --- seed schema + admins ------------------------------------------------

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE admin (
    AdminID INTEGER PRIMARY KEY,
    Name TEXT,
    Status TEXT
)");

// id=1 'Ernida'      -> handle 'ernida' (the reported case)
// id=2 'Ernida Ali'  -> handle 'ernidaali'
// id=3 'Ernida Bee'  -> handle 'ernidabee'
// id=4 'Bob'         -> collides with id=6 -> 'bob4'
// id=5 'Carol'       -> inactive, handle 'carol' but never resolves
// id=6 'B.O.B!'      -> strips to 'bob' -> collision -> 'bob6'
// id=7 '!!!'         -> empties -> fallback 'user7'
$pdo->exec("INSERT INTO admin VALUES
    (1, 'Ernida',     'Y'),
    (2, 'Ernida Ali', 'Y'),
    (3, 'Ernida Bee', 'Y'),
    (4, 'Bob',        'Y'),
    (5, 'Carol',      'N'),
    (6, 'B.O.B!',     'Y'),
    (7, '!!!',        'Y')
");

require_once __DIR__ . '/../../application/models/Notification_Model.php';

$nm = new Notification_Model();
$nm->db = new TestDbFacade($pdo);

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

// --- Build_Admin_Handles rule -------------------------------------------

$admins_in = [];
foreach ([[1,'Ernida'],[2,'Ernida Ali'],[3,'Ernida Bee'],[4,'Bob'],[5,'Carol'],[6,'B.O.B!'],[7,'!!!']] as $r) {
    $o = new stdClass(); $o->AdminID = $r[0]; $o->Name = $r[1]; $admins_in[] = $o;
}
$enriched = $nm->Build_Admin_Handles($admins_in);
$by_id = [];
foreach ($enriched as $a) { $by_id[$a->AdminID] = $a->handle; }

assert_eq('handle: Ernida -> ernida',         'ernida',     $by_id[1]);
assert_eq('handle: Ernida Ali -> ernidaali',  'ernidaali',  $by_id[2]);
assert_eq('handle: Ernida Bee -> ernidabee',  'ernidabee',  $by_id[3]);
assert_eq('handle: Bob collides -> bob4',     'bob4',       $by_id[4]);
assert_eq('handle: Carol (inactive) -> carol','carol',      $by_id[5]);
assert_eq('handle: B.O.B! collides -> bob6',  'bob6',       $by_id[6]);
assert_eq('handle: !!! -> user7 fallback',    'user7',      $by_id[7]);

// --- regex extraction from remark content --------------------------------

$re = '/@([a-z0-9]+)/';

preg_match_all($re, 'hello @ernida how are you?', $m);
assert_eq('regex: single @ernida', ['ernida'], $m[1]);

preg_match_all($re, '@ernida and @bob4 please review', $m);
assert_eq('regex: two handles', ['ernida', 'bob4'], $m[1]);

preg_match_all($re, 'no mention here', $m);
assert_eq('regex: no mention -> empty', [], $m[1]);

preg_match_all($re, '@Ernida (capital E)', $m);
// '@' followed by uppercase 'E' fails [a-z0-9]+ entirely (no \i flag), and
// there is no other '@' in the string. The contract: typed-by-hand uppercase
// mentions silently fail to notify. The autocomplete always inserts lowercase
// handles, so this only bites users who manually type @Foo instead of picking.
assert_eq('regex: @Ernida (capital E) -> no match', [], $m[1]);

preg_match_all($re, '@ernida@bob4', $m);
assert_eq('regex: adjacent mentions', ['ernida', 'bob4'], $m[1]);

preg_match_all($re, 'email like foo@bar.com', $m);
// Real email leaks would tag user 'bar' if such an admin existed. Resolution
// silently drops unknowns, so this is acceptable — just document the behavior.
assert_eq('regex: bare email-like @bar captured', ['bar'], $m[1]);

// --- Resolve_Handles_To_User_Ids -----------------------------------------

assert_eq('resolve: [ernida] -> [1]',           [1],     $nm->Resolve_Handles_To_User_Ids(['ernida']));
assert_eq('resolve: [ernidaali] -> [2]',        [2],     $nm->Resolve_Handles_To_User_Ids(['ernidaali']));
// IDs come back in admin-row order (ascending AdminID), not input-handle order
assert_eq('resolve: [bob4, ernida] -> [1, 4]',  [1, 4],  $nm->Resolve_Handles_To_User_Ids(['bob4', 'ernida']));
assert_eq('resolve: dedupe ernida x2 -> [1]',   [1],     $nm->Resolve_Handles_To_User_Ids(['ernida', 'ernida']));
assert_eq('resolve: inactive carol dropped',    [],      $nm->Resolve_Handles_To_User_Ids(['carol']));
assert_eq('resolve: unknown handle dropped',    [],      $nm->Resolve_Handles_To_User_Ids(['nobody']));
assert_eq('resolve: empty input',               [],      $nm->Resolve_Handles_To_User_Ids([]));
assert_eq('resolve: bare @bob (no suffix) drops since collision forces bob4/bob6',
    [], $nm->Resolve_Handles_To_User_Ids(['bob']));

// Mixed-case handle inputs are lowercased before matching (defensive — the
// regex above wouldn't produce uppercase, but external callers might).
assert_eq('resolve: ERNIDA lowercased -> [1]', [1], $nm->Resolve_Handles_To_User_Ids(['ERNIDA']));

// --- report ---------------------------------------------------------------

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) { $failed++; }
}
echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
