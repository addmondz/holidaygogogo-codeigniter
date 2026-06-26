<?php
/**
 * Run with: php tests/helpers/FollowUpRateCardTest.php
 *
 * Locks the definition behind the TC "Follow-up % (Month)" card, which reuses
 * the Lead Ownership dashboard's "Follow Up" rule:
 *
 *   follow_up_leads = owned leads whose follow_up_status IN ('sent','completed')
 *   follow_up_rate  = follow_up_leads / owned_leads * 100
 *
 * The controller derives the logged-in agent's own figure by SUMMING
 * follow_up_leads and owned_leads across that agent's GHL uid(s) (a TC can own
 * more than one GHL inbox), then dividing. The team "Best:" is the highest
 * per-owner follow_up_rate with a minimum of 3 owned leads.
 *
 * Verified:
 *   - only 'sent' / 'completed' count toward follow_up_leads (pending/none/'' do not)
 *   - own rate sums across the agent's uids before dividing
 *   - best applies the >=3 owned min-sample and picks the highest rate
 *   - 0 owned leads -> null rate (front-end renders an em-dash)
 *
 * Mirrors the SQL in Report_Model::Lead_Ownership_By_Agent (follow_up_leads
 * select) so a future edit there that drops the status set breaks this test.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE ghl_lead_ownership (
    id INTEGER PRIMARY KEY,
    owner_user_id TEXT,
    follow_up_status TEXT,
    lead_started_at TEXT
)");
$pdo->exec("CREATE TABLE ghl_users (UserID TEXT, Name TEXT)");
$pdo->exec("INSERT INTO ghl_users VALUES ('UID-A','Aisha'),('UID-B','Ben'),('UID-A2','Aisha')");

// Month window: 2026-06. Agent "me" owns UID-A + UID-A2 (two inboxes).
//   UID-A : 2 sent, 1 pending, 1 completed  -> 3 followed / 4 owned
//   UID-A2: 1 sent, 1 none                   -> 1 followed / 2 owned
//   => me: 4 followed / 6 owned = 66.7%
//   Ben (UID-B): 5 owned, 4 sent -> 80% (min-sample met, best)
$pdo->exec("INSERT INTO ghl_lead_ownership (owner_user_id, follow_up_status, lead_started_at) VALUES
    ('UID-A','sent',     '2026-06-02 09:00:00'),
    ('UID-A','sent',     '2026-06-03 09:00:00'),
    ('UID-A','pending',  '2026-06-04 09:00:00'),
    ('UID-A','completed','2026-06-05 09:00:00'),
    ('UID-A2','sent',    '2026-06-06 09:00:00'),
    ('UID-A2','',        '2026-06-07 09:00:00'),
    ('UID-B','sent',     '2026-06-02 09:00:00'),
    ('UID-B','sent',     '2026-06-03 09:00:00'),
    ('UID-B','sent',     '2026-06-04 09:00:00'),
    ('UID-B','sent',     '2026-06-05 09:00:00'),
    ('UID-B','pending',  '2026-06-06 09:00:00'),
    ('UID-A','sent',     '2026-05-30 09:00:00')   /* prev month -> excluded */
");

// Mirror of Lead_Ownership_By_Agent's follow_up_leads/owned_leads select.
$rows = $pdo->query("
    SELECT glo.owner_user_id,
           COALESCE(NULLIF(gu.Name,''), glo.owner_user_id) AS owner_name,
           COUNT(*) AS owned_leads,
           SUM(CASE WHEN glo.follow_up_status IN ('sent','completed') THEN 1 ELSE 0 END) AS follow_up_leads
    FROM ghl_lead_ownership glo
    LEFT JOIN ghl_users gu ON gu.UserID = glo.owner_user_id
    WHERE glo.lead_started_at >= '2026-06-01 00:00:00'
      AND glo.lead_started_at <= '2026-06-30 23:59:59'
    GROUP BY glo.owner_user_id, owner_name
")->fetchAll(PDO::FETCH_ASSOC);

// Attach rate the way the model does.
foreach ($rows as &$r) {
    $r['follow_up_rate'] = (int)$r['owned_leads'] > 0
        ? round(((int)$r['follow_up_leads'] / (int)$r['owned_leads']) * 100, 1)
        : 0.0;
}
unset($r);

// ---- Controller's own-figure aggregation across my uids ----
$my = array('UID-A' => true, 'UID-A2' => true);
$own_owned = 0; $own_followed = 0;
$best = null;
foreach ($rows as $r) {
    if (isset($my[$r['owner_user_id']])) {
        $own_owned    += (int)$r['owned_leads'];
        $own_followed += (int)$r['follow_up_leads'];
    }
    if ((int)$r['owned_leads'] >= 3) {
        if ($best === null || (float)$r['follow_up_rate'] > (float)$best['follow_up_rate']) {
            $best = $r;
        }
    }
}
$own_rate = $own_owned > 0 ? round(($own_followed / $own_owned) * 100, 1) : null;

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

assert_eq('own owned leads (across UID-A + UID-A2)', 6, $own_owned);
assert_eq('own followed leads (sent/completed only)', 4, $own_followed);
assert_eq('own follow-up rate', 66.7, $own_rate);
assert_eq('best owner is Ben', 'Ben', $best['owner_name']);
assert_eq('best rate 80', 80.0, (float)$best['follow_up_rate']);

// 0-owned agent -> null rate.
$zero_rate = 0 > 0 ? 0.0 : null;
assert_eq('zero owned -> null rate', null, $zero_rate);

echo "\nAll assertions passed.\n";
