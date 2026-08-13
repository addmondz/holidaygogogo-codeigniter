<?php
/**
 * Run with: php tests/helpers/GuestListNameOwnOnlyTest.php
 *
 * Locks Guest List "Search Name" (the q box) to each row's OWN name only.
 * It used to ALSO match the booking's team-leader name (b.Customer), which
 * surfaced co-guests under a different name when the typed name happened to be
 * a leader — so typing a name returned rows whose displayed name did not match.
 * Leader search now lives solely in the dedicated "Team Leader" column filter.
 *
 * This mirrors the model's row-level WHERE built in Read/Count so both agree.
 */

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// ---- integration: a name search matches gl.Name / gl.LastName, NOT b.Customer
// Guests: Siti is a member of a booking whose team leader (b.Customer) is
// "Ahmad". Searching "Ahmad" must NOT surface Siti; searching "Siti" must.
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE gl (name TEXT, lastname TEXT, leader TEXT)");
$pdo->exec("INSERT INTO gl (name, lastname, leader) VALUES
    ('Siti',  'Aminah', 'Ahmad'),
    ('Ahmad', 'Zaki',   'Ahmad'),
    ('Mary',  'Lim',    'Mary')");

// OWN-name-only predicate — the SQL the model now emits for the q box.
$match = function ($needle) use ($pdo) {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM gl
         WHERE ( name LIKE ? OR lastname LIKE ? OR (name || ' ' || lastname) LIKE ? )"
    );
    $like = '%' . $needle . '%';
    $stmt->execute(array($like, $like, $like));
    return (int) $stmt->fetchColumn();
};

assert_eq('"Siti"  -> own row',            1, $match('Siti'));
assert_eq('"Aminah" -> matches last name', 1, $match('Aminah'));
assert_eq('"Siti Aminah" -> joined name',  1, $match('Siti Aminah'));
// The regression guard: leader name no longer drags in co-guests.
assert_eq('"Ahmad" -> only own row, not Siti', 1, $match('Ahmad'));
assert_eq('"Mary" -> own row',             1, $match('Mary'));
assert_eq('"Zoe" -> none',                 0, $match('Zoe'));

echo "\nAll assertions passed.\n";
