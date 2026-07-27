<?php
/**
 * Run with: php tests/helpers/ActiveLeadsByTagTest.php
 *
 * Locks the per-tag SQL behind the TC LEAD "Active Leads by Tag" card. For
 * each allowlisted tag (destination / language / race) the card shows the
 * count of distinct unconverted leads carrying that tag on the conversation
 * — so two invariants must hold:
 *
 *   1. Only unconverted leads count (is_converted = 0). Converted leads
 *      already have a booking and are no longer "active" work for the TC.
 *   2. Matching is case-insensitive exact-string against the tags array.
 *      "Redang", "redang" and "REDANG" all count for the "redang" tag, but
 *      "redang052026" does not (we don't want a sub-string match to silently
 *      lump campaign-specific tags into the headline destination total).
 *
 * Production runs against MySQL using JSON_CONTAINS(tags_json, JSON_QUOTE(?)).
 * This test runs against SQLite's json_each(), which is the functionally
 * equivalent way to test array-membership in a JSON column.
 */
if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE ghl_processed_leads (
    id INTEGER PRIMARY KEY,
    conversation_id TEXT,
    is_converted INTEGER
)");
$pdo->exec("CREATE TABLE ghl_conversations (
    id INTEGER PRIMARY KEY,
    conversation_id TEXT,
    tags_json TEXT
)");

$pdo->exec("INSERT INTO ghl_processed_leads VALUES
    /* 1 unconverted, tags include redang        -> counts for redang     */
    (1, 'c-1',  0),
    /* 2 unconverted, tags include REDANG (caps) -> counts for redang     */
    (2, 'c-2',  0),
    /* 3 CONVERTED, tags include redang          -> EXCLUDED               */
    (3, 'c-3',  1),
    /* 4 unconverted, tags include tioman+muslim -> counts twice (dest+race) */
    (4, 'c-4',  0),
    /* 5 unconverted, tags include redang052026  -> EXCLUDED (substring)   */
    (5, 'c-5',  0),
    /* 6 unconverted, no conversation row at all -> EXCLUDED               */
    (6, 'c-6',  0),
    /* 7 unconverted, tags has redang TWICE      -> counts ONCE for redang */
    (7, 'c-7',  0),
    /* 8 unconverted, no tags                    -> EXCLUDED               */
    (8, 'c-8',  0)
");

$pdo->exec("INSERT INTO ghl_conversations VALUES
    (1, 'c-1', '[\"redang\", \"active contacts\"]'),
    (2, 'c-2', '[\"REDANG\"]'),
    (3, 'c-3', '[\"redang\"]'),
    (4, 'c-4', '[\"tioman\", \"muslim\"]'),
    (5, 'c-5', '[\"redang052026\"]'),
    (7, 'c-7', '[\"redang\", \"redang\"]'),
    (8, 'c-8', '[]')
");

// Production SQL pattern (per-tag query) — SQLite flavour using json_each.
// The MySQL flavour is:
//   COUNT(DISTINCT pl.id) FROM ghl_processed_leads pl
//   LEFT JOIN ghl_conversations gc ON gc.conversation_id = pl.conversation_id
//   WHERE pl.is_converted = 0
//     AND JSON_CONTAINS(LOWER(gc.tags_json), JSON_QUOTE(LOWER(?)))
$sql = "
    SELECT COUNT(DISTINCT pl.id) AS lead_count
    FROM ghl_processed_leads pl
    LEFT JOIN ghl_conversations gc ON gc.conversation_id = pl.conversation_id
    WHERE pl.is_converted = 0
      AND EXISTS (
        SELECT 1 FROM json_each(gc.tags_json)
        WHERE LOWER(json_each.value) = LOWER(:tag)
      )
";

function count_for_tag(PDO $pdo, $sql, $tag) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(':tag' => $tag));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int) $row['lead_count'];
}

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// redang: rows 1, 2 (case-insensitive), 7 (de-duped) — NOT row 3 (converted),
// NOT row 5 (substring match must not trigger).
assert_eq('redang count (case-insensitive, distinct leads only)', 3,
    count_for_tag($pdo, $sql, 'redang'));

// tioman: row 4 only.
assert_eq('tioman count',  1, count_for_tag($pdo, $sql, 'tioman'));

// muslim (race): row 4 only — same lead as tioman, but counted separately
// per dimension so destination total != race total. That's the whole point
// of having three independent tables.
assert_eq('muslim count',  1, count_for_tag($pdo, $sql, 'muslim'));

// A tag that exists in NO conversation should return 0 cleanly.
assert_eq('unknown tag returns 0', 0, count_for_tag($pdo, $sql, 'mars'));

// And the converted lead must never count, even when its tag matches.
// (Strictly redundant with the redang assertion above, but the invariant
// is critical enough to call out explicitly.)
$pdo->exec("UPDATE ghl_processed_leads SET is_converted = 1 WHERE id IN (1, 2, 7)");
assert_eq('all converted -> redang count is 0', 0,
    count_for_tag($pdo, $sql, 'redang'));

echo "\nAll assertions passed.\n";
