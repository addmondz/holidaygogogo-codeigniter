<?php
/**
 * Run with: php tests/helpers/GhlLeadSourceScopeTest.php
 *
 * Locks the lead-source + per-creator WHERE fragment that splits the two lead
 * pages built on the shared GHL branch (Guests_Model::Build_Branches):
 *   - GHL Leads page (mode 'ghl')    → synced leads only (manual rows excluded).
 *   - Manual Leads page (mode 'manual') → manual rows only, AND — unless the
 *     viewer is a view-all role (Owner 10 / Team Lead 25 / Marketing 60) — only
 *     the leads that viewer created (gc.created_by = their admin id).
 *   - Campaign picker (mode 'all')   → no lead-source filter (keeps both).
 * Pure so it unit-tests without booting CodeIgniter or a database.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require __DIR__ . '/../../application/helpers/guest_contact_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

function assert_contains($label, $needle, $haystack) {
    assert_eq($label, true, strpos($haystack, $needle) !== false);
}

function assert_missing($label, $needle, $haystack) {
    assert_eq($label, false, strpos($haystack, $needle) !== false);
}

// ---- GHL page: exclude manual rows, no creator scope, no params ------------
list($sql, $params) = guest_list_ghl_lead_source_scope('ghl', 20, 7);
assert_contains('ghl excludes manual', "lead_source", $sql);
assert_contains('ghl uses <> manual',  "<> 'manual'", $sql);
assert_missing('ghl has no creator scope', 'created_by', $sql);
assert_eq('ghl has no params', array(), $params);

// ---- Manual page as a plain agent (level 20): manual-only + own creator ----
list($sql, $params) = guest_list_ghl_lead_source_scope('manual', 20, 7);
assert_contains('manual keeps manual only', "lead_source = 'manual'", $sql);
assert_contains('manual scopes creator',    'gc.created_by = ?', $sql);
assert_eq('manual binds admin id', array(7), $params);

// ---- Manual page as a TC (level 50): still own-creator scoped --------------
list($sql, $params) = guest_list_ghl_lead_source_scope('manual', 50, 3);
assert_contains('tc scopes creator', 'gc.created_by = ?', $sql);
assert_eq('tc binds admin id', array(3), $params);

// ---- Manual page as a view-all role: NO creator scope ----------------------
foreach (array(10, 25, 60) as $lvl) {
    list($sql, $params) = guest_list_ghl_lead_source_scope('manual', $lvl, 99);
    assert_contains("level {$lvl} keeps manual only", "lead_source = 'manual'", $sql);
    assert_missing("level {$lvl} sees all (no creator scope)", 'created_by', $sql);
    assert_eq("level {$lvl} has no params", array(), $params);
}

// ---- Campaign picker (mode 'all'): no lead-source filter at all -------------
list($sql, $params) = guest_list_ghl_lead_source_scope('all', 10, 1);
assert_eq('all mode empty sql', '', trim($sql));
assert_eq('all mode no params', array(), $params);

echo "\nAll GhlLeadSourceScope assertions passed.\n";
