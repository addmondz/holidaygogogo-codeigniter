<?php
/**
 * Run with: php tests/helpers/GhlMessageLogContactNameFilterTest.php
 *
 * Locks the SQL contract behind the NAME half of the Message Log contact filter.
 * GHL stores a contact's display name in from_number / to_number when it holds no
 * phone number for them (real data: "Siew Chin Yap", "HolidayGoGoGo", "Jenny
 * Lim"). The old digits-only filter dropped any letter-bearing search and returned
 * the whole log; the fix classifies each term (ghl_message_log_normalize_contact_
 * terms) and matches names as case-insensitive free text against the raw columns,
 * while numbers still compare against a digit-normalised from/to.
 *
 * Mirrors Report_Model::ghl_message_contact_clause() in portable SQLite (the model
 * runs MySQL) so the two stay in lock-step.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require __DIR__ . '/../../application/helpers/ghl_messages_log_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE ghl_messages (
    id INTEGER PRIMARY KEY, from_number TEXT, to_number TEXT, body TEXT, date_added TEXT
)");

// Two leads GHL only knows by NAME (no phone), plus one plain-number lead. The
// office side reads "HolidayGoGoGo" on the outbound leg -- exactly the shape in
// the screenshot that motivated this fix.
$pdo->exec("INSERT INTO ghl_messages (id, from_number, to_number, body, date_added) VALUES
    (1, 'HolidayGoGoGo', 'Siew Chin Yap', 'quote sent',     '2026-06-14 08:00:00'),
    (2, 'Siew Chin Yap', 'HolidayGoGoGo', 'thanks',         '2026-06-14 08:05:00'),
    (3, 'HolidayGoGoGo', 'Jenny Lim',     'other lead',     '2026-06-14 09:00:00'),
    (4, '+60123365799',  '+60102956786',  'number lead',    '2026-06-14 10:00:00')
");

$start = '2026-06-14 00:00:00';
$end   = '2026-06-14 23:59:59';

// Exact mirror of Report_Model::normalize_phone_sql().
$normExpr = function ($col) {
    return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($col,'+',''),' ',''),'-',''),'(',''),')','')";
};

/**
 * Mirror of Report_Model::ghl_message_contact_clause(): typed terms in, a WHERE
 * fragment out, name terms matching raw text and phone terms matching normalised
 * digits, all OR-ed. Case-insensitivity: SQLite LIKE is already ASCII-insensitive,
 * matching MySQL's default collation.
 */
function build_contact_clause($contact, $normExpr, array &$binds) {
    $terms = ghl_message_log_normalize_contact_terms($contact);
    if (empty($terms)) { return ''; }
    $ors = array();
    foreach ($terms as $term) {
        $binds[] = '%' . $term['value'] . '%';
        $binds[] = '%' . $term['value'] . '%';
        if ($term['type'] === 'name') {
            $ors[] = "(gm.from_number LIKE ? OR gm.to_number LIKE ?)";
        } else {
            $ors[] = "({$normExpr('gm.from_number')} LIKE ? OR {$normExpr('gm.to_number')} LIKE ?)";
        }
    }
    return ' AND (' . implode(' OR ', $ors) . ')';
}

$run = function ($contact) use ($pdo, $normExpr, $start, $end) {
    $binds = array($start, $end);
    $clause = build_contact_clause($contact, $normExpr, $binds);
    $stmt = $pdo->prepare("SELECT gm.id FROM ghl_messages gm
        WHERE gm.date_added >= ? AND gm.date_added <= ? {$clause} ORDER BY gm.id ASC");
    $stmt->execute($binds);
    return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
};

// THE BUG: a name search now returns that contact's two-way thread (ids 1 & 2),
// where before it silently matched nothing and the whole log came back.
assert_eq('name filter returns both directions of that thread', array(1, 2),
    $run('Siew Chin Yap'));

// Case-insensitive, mirroring MySQL's default collation.
assert_eq('name filter is case-insensitive', array(1, 2),
    $run('siew chin yap'));

// A partial name still matches (LIKE %term%).
assert_eq('partial name matches', array(1, 2),
    $run('Siew'));

// A different name only matches its own row.
assert_eq('other name matches only its own row', array(3),
    $run('Jenny Lim'));

// A number still works exactly as before (digits normalised).
assert_eq('number filter still matches the numeric lead', array(4),
    $run('+6010-295 6786'));

// Name + number compose (OR): Siew's thread plus the numeric lead.
assert_eq('name + number compose', array(1, 2, 4),
    $run('Siew Chin Yap, 60123365799'));

// No filter -> every in-window row (clause adds nothing).
assert_eq('empty filter returns all rows', array(1, 2, 3, 4), $run(''));

echo "\nAll assertions passed.\n";
