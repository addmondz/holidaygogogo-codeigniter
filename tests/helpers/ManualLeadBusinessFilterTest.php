<?php
/**
 * Run with: php tests/helpers/ManualLeadBusinessFilterTest.php
 *
 * Locks the Manual Leads business-field filters on the GHL branch (see
 * Guests_Model::Build_Branches). Client Type / Number of Pax / State are
 * multi-select IN over the stored label; Nature of Business is a free-text LIKE.
 * Modeled in SQLite so the WHERE semantics are pinned independent of MySQL.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
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

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE ghl_contacts (
    id INTEGER PRIMARY KEY, lead_source TEXT,
    client_type TEXT, number_of_pax TEXT, state TEXT, nature_of_business TEXT
)");
$pdo->exec("INSERT INTO ghl_contacts VALUES
    (1,'manual','HRDC','11-20','Selangor','Travel Agency'),
    (2,'manual','Leisure','1-10','Johor','Manufacturing Sdn Bhd'),
    (3,'manual','Meeting','100+','Kuala Lumpur','Education Services'),
    (4,'manual','HRDC','21-30','Selangor','IT Consulting')");

// helper mirroring Append_In_Clause + the LIKE fragment
function run($pdo, $where, $params) {
    $st = $pdo->prepare("SELECT id FROM ghl_contacts WHERE 1=1 $where ORDER BY id");
    $st->execute($params);
    return array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id'));
}

// Client Type IN — single value
assert_eq('client_type=HRDC', array(1,4), run($pdo, " AND client_type IN (?)", array('HRDC')));
// Client Type IN — multi value (OR within the filter)
assert_eq('client_type in (HRDC,Meeting)', array(1,3,4), run($pdo, " AND client_type IN (?,?)", array('HRDC','Meeting')));
// Number of Pax IN
assert_eq('pax=100+', array(3), run($pdo, " AND number_of_pax IN (?)", array('100+')));
// State IN
assert_eq('state=Selangor', array(1,4), run($pdo, " AND state IN (?)", array('Selangor')));
// Nature of Business LIKE (partial, case-insensitive substring)
assert_eq('nature LIKE %Sdn%', array(2), run($pdo, " AND nature_of_business LIKE ?", array('%Sdn%')));
assert_eq('nature LIKE %consulting%', array(4), run($pdo, " AND nature_of_business LIKE ?", array('%Consulting%')));
// Combined: HRDC + Selangor narrows to 2 rows
assert_eq('client+state combo', array(1,4), run($pdo, " AND client_type IN (?) AND state IN (?)", array('HRDC','Selangor')));

// ---- Lead Status filter: current column OR any dated update-log entry -------
// gc.lead_status is the CURRENT status; lead_status_log holds dated updates.
$pdo->exec("ALTER TABLE ghl_contacts ADD COLUMN lead_status TEXT");
$pdo->exec("ALTER TABLE ghl_contacts ADD COLUMN dedup TEXT");
$pdo->exec("UPDATE ghl_contacts SET lead_status='New', dedup='ghl:'||id");
$pdo->exec("UPDATE ghl_contacts SET lead_status='Closed' WHERE id=3");
$pdo->exec("CREATE TABLE lead_status_log (LogID INTEGER PRIMARY KEY, dedup_key TEXT, LeadStatus TEXT, Status TEXT)");
// lead 2 has NO current 'Won' but a dated update to 'Won'; lead 4 current 'New' only
$pdo->exec("INSERT INTO lead_status_log (dedup_key,LeadStatus,Status) VALUES ('ghl:2','Won','Y'),('ghl:1','Contacted','N')");

function run_status($pdo, $statuses) {
    $ph = implode(',', array_fill(0, count($statuses), '?'));
    $sql = "SELECT id FROM ghl_contacts gc WHERE ( gc.lead_status IN ($ph)
        OR EXISTS (SELECT 1 FROM lead_status_log lsl WHERE lsl.Status='Y' AND lsl.dedup_key=gc.dedup AND lsl.LeadStatus IN ($ph)) )
        ORDER BY id";
    $st=$pdo->prepare($sql); $st->execute(array_merge($statuses,$statuses));
    return array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC),'id'));
}
// 'New' matches all current-New (1,2,4) — 3 is 'Closed'
assert_eq('status current New', array(1,2,4), run_status($pdo, array('New')));
// 'Won' matches lead 2 via its dated update log only (its current status is New)
assert_eq('status via update log', array(2), run_status($pdo, array('Won')));
// soft-deleted log entry ('Contacted', Status=N) never matches
assert_eq('deleted log ignored', array(), run_status($pdo, array('Contacted')));
// 'Closed' current + 'Won' update -> leads 2 and 3
assert_eq('status multi', array(2,3), run_status($pdo, array('Closed','Won')));

echo "\nAll ManualLeadBusinessFilter assertions passed.\n";
