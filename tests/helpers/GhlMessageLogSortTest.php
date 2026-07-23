<?php
/**
 * Run with: php tests/helpers/GhlMessageLogSortTest.php
 *
 * Locks the Date/Time column sort toggle on the Message Log report:
 *
 *   1. ghl_message_log_normalize_sort() — only an explicit 'asc' flips the
 *      table to oldest-first; everything else stays 'desc' (newest-first), so
 *      the value reaching ORDER BY is always one of two safe literals.
 *   2. The ORDER BY direction flips together for the timestamp AND the id
 *      tiebreak, mirrored here in portable SQLite (the model runs on MySQL).
 *   3. The "Time Taken" column stays correct when the page is sorted ASC: the
 *      controller fetches one extra OLDER leading row and reuses the
 *      newest-first gap annotator by flipping the slice to DESC and back.
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

// --- 1) normalize_sort: default desc, only explicit asc flips. ---
assert_eq('normalize "asc" => asc',   'asc',  ghl_message_log_normalize_sort('asc'));
assert_eq('normalize "ASC" => asc',   'asc',  ghl_message_log_normalize_sort('ASC'));
assert_eq('normalize " Asc " => asc', 'asc',  ghl_message_log_normalize_sort(' Asc '));
assert_eq('normalize "desc" => desc', 'desc', ghl_message_log_normalize_sort('desc'));
assert_eq('normalize "" => desc',     'desc', ghl_message_log_normalize_sort(''));
assert_eq('normalize junk => desc',   'desc', ghl_message_log_normalize_sort('nonsense'));
assert_eq('normalize null => desc',   'desc', ghl_message_log_normalize_sort(null));

// --- 1b) normalize_sort_column: default date, only explicit direction flips. ---
assert_eq('normalize col "direction" => direction', 'direction', ghl_message_log_normalize_sort_column('direction'));
assert_eq('normalize col "DIRECTION" => direction', 'direction', ghl_message_log_normalize_sort_column('DIRECTION'));
assert_eq('normalize col "date" => date',           'date',      ghl_message_log_normalize_sort_column('date'));
assert_eq('normalize col "" => date',               'date',      ghl_message_log_normalize_sort_column(''));
assert_eq('normalize col junk => date',             'date',      ghl_message_log_normalize_sort_column('body'));

// --- 2) ORDER BY flips, mirrored in SQLite (date column and direction column). ---
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE ghl_messages (id INTEGER PRIMARY KEY, body TEXT, direction TEXT, date_added TEXT)");
// ids 11 and 12 share a timestamp so the id tiebreak is observable.
$pdo->exec("INSERT INTO ghl_messages (id, body, direction, date_added) VALUES
    (10, 'first',  'outbound', '2026-06-14 08:00:00'),
    (11, 'second', 'inbound',  '2026-06-15 09:00:00'),
    (12, 'third',  'outbound', '2026-06-15 09:00:00'),
    (13, 'fourth', 'inbound',  '2026-06-16 23:59:59')
");

/** Mirror of Ghl_Messages_Log's date-column ORDER BY for a given direction. */
$ordered_ids = function ($dir) use ($pdo) {
    $sql = "SELECT id FROM ghl_messages ORDER BY date_added {$dir}, id {$dir}";
    return array_map('intval', array_column($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC), 'id'));
};

// desc: newest first, id DESC on the shared-timestamp pair (12 before 11).
assert_eq('desc newest-first with id DESC tiebreak',
    array(13, 12, 11, 10), $ordered_ids('DESC'));
// asc: oldest first, id ASC on the shared-timestamp pair (11 before 12).
assert_eq('asc oldest-first with id ASC tiebreak',
    array(10, 11, 12, 13), $ordered_ids('ASC'));

/** Mirror of the direction-column ORDER BY: group by direction, newest-first within. */
$ordered_by_direction = function ($dir) use ($pdo) {
    $sql = "SELECT id FROM ghl_messages ORDER BY direction {$dir}, date_added DESC, id DESC";
    return array_map('intval', array_column($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC), 'id'));
};

// asc: Inbound group first (13 then 11, newest-first), then Outbound (12 then 10).
assert_eq('direction ASC groups Inbound first, newest-first within',
    array(13, 11, 12, 10), $ordered_by_direction('ASC'));
// desc: Outbound group first (12 then 10), then Inbound (13 then 11).
assert_eq('direction DESC groups Outbound first, newest-first within',
    array(12, 10, 13, 11), $ordered_by_direction('DESC'));

// --- 3) "Time Taken" stays correct on an ASC page (reverse-trick). ---
// Four same-day messages, 20s apart each. The controller, for ASC, fetches the
// page in ASC with one extra OLDER leading row, then reverses -> newest-first
// (extra row at the bottom) -> attach gaps -> slice -> reverse back to ASC.
$all = array(
    array('message_timestamp' => '2026-06-19 09:00:00', 'body' => 'm0'),
    array('message_timestamp' => '2026-06-19 09:00:20', 'body' => 'm1'),
    array('message_timestamp' => '2026-06-19 09:00:40', 'body' => 'm2'),
    array('message_timestamp' => '2026-06-19 09:01:00', 'body' => 'm3'),
);
$per_page = 2;

// Simulate the controller's ASC branch for page 2 (offset 2): fetch offset-1=1,
// limit per_page+1=3 in ASC, then reverse / attach / slice / reverse.
$offset = 2;
$lead = $offset > 0 ? 1 : 0;
$asc_slice = array_slice($all, $offset - $lead, $per_page + $lead); // rows the model returns, ASC
$newest_first = array_reverse($asc_slice);                          // extra OLDER row now at bottom
$annotated = array_reverse(ghl_message_log_attach_reply_gaps($newest_first, $per_page));

assert_eq('asc page shows the right rows, oldest-first',
    array('m2', 'm3'), array_column($annotated, 'body'));
// Both rows measure against their OLDER predecessor (m1->m2, m2->m3): 20s each.
assert_eq('asc row0 (m2) gap vs its older predecessor', '20s', $annotated[0]['reply_gap_label']);
assert_eq('asc row1 (m3) gap vs its older predecessor', '20s', $annotated[1]['reply_gap_label']);

// ASC page 1 (offset 0): no leading predecessor, so the very first message is blank.
$offset = 0;
$lead = $offset > 0 ? 1 : 0;
$asc_slice = array_slice($all, $offset - $lead, $per_page + $lead);
$newest_first = array_reverse($asc_slice);
$annotated = array_reverse(ghl_message_log_attach_reply_gaps($newest_first, $per_page));
assert_eq('asc page1 rows', array('m0', 'm1'), array_column($annotated, 'body'));
assert_eq('asc page1 first-ever message has blank gap', '', $annotated[0]['reply_gap_label']);
assert_eq('asc page1 second message gap vs m0', '20s', $annotated[1]['reply_gap_label']);

echo "\nAll assertions passed.\n";
