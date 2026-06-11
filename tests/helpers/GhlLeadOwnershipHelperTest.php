<?php
/**
 * Run with: php tests/helpers/GhlLeadOwnershipHelperTest.php
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/ghl_lead_ownership_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label}\n";
        return;
    }

    echo "  FAIL  {$label}: expected " . var_export($expected, true)
        . ", got " . var_export($actual, true) . "\n";
    exit(1);
}

$lead = array(
    'id' => 10,
    'conversation_id' => 'conv_1',
    'contact_id' => 'contact_1',
    'assigned_to_user_id' => 'agent_a',
    'lead_started_at' => '2026-06-02 17:45:00',
    'lead_ended_at' => null,
    'tracked_message_count' => 5,
    'responded_message_count' => 3,
    'avg_first_5_response_seconds' => 120,
    'recent_tracked_message_count' => 5,
    'recent_responded_message_count' => 3,
    'avg_recent_5_response_seconds' => 90,
    'is_converted' => 1,
    'booking_id' => 88,
    'converted_at' => '2026-06-03 10:00:00',
);

$rows = ghl_build_lead_ownership_rows(
    $lead,
    array(
        array('owner_user_id' => 'agent_a', 'outbound_reply_count' => 4),
        array('owner_user_id' => 'agent_b', 'outbound_reply_count' => 3),
    ),
    '2026-06-03 12:00:00'
);

assert_eq('assigned plus reply owner produces two rows', 2, count($rows));
assert_eq('assigned agent is merged into one row', 'agent_a', $rows[0]['owner_user_id']);
assert_eq('assigned flag is retained', 1, $rows[0]['is_assigned_owner']);
assert_eq('reply flag is retained for assigned replier', 1, $rows[0]['is_reply_owner']);
assert_eq('reply count is retained for assigned replier', 4, $rows[0]['outbound_reply_count']);
assert_eq('second reply owner is credited', 'agent_b', $rows[1]['owner_user_id']);
assert_eq('second reply owner is not assigned owner', 0, $rows[1]['is_assigned_owner']);
assert_eq('second reply owner reply flag', 1, $rows[1]['is_reply_owner']);

$reassignedRows = ghl_build_lead_ownership_rows(
    array_merge($lead, array('assigned_to_user_id' => 'agent_b')),
    array(),
    '2026-06-03 12:00:00',
    array(
        array('owner_user_id' => 'agent_a', 'assigned_at' => '2026-06-02 17:45:00'),
        array('owner_user_id' => 'agent_b', 'assigned_at' => '2026-06-03 09:00:00'),
    )
);

assert_eq('reassigned lead keeps first assignee', 2, count($reassignedRows));
assert_eq('first assignee owner id', 'agent_a', $reassignedRows[0]['owner_user_id']);
assert_eq('first assignee is assigned owner', 1, $reassignedRows[0]['is_assigned_owner']);
assert_eq('first assignee assigned_at retained', '2026-06-02 17:45:00', $reassignedRows[0]['assigned_at']);
assert_eq('current assignee owner id', 'agent_b', $reassignedRows[1]['owner_user_id']);
assert_eq('current assigned_to_user_id retained on first row', 'agent_b', $reassignedRows[0]['assigned_to_user_id']);

$unassignedRows = ghl_build_lead_ownership_rows(
    array_merge($lead, array('id' => 11, 'assigned_to_user_id' => '')),
    array(array('owner_user_id' => 'agent_c', 'outbound_reply_count' => 5)),
    '2026-06-03 12:00:00'
);

assert_eq('unassigned lead can still be owned by reply threshold', 1, count($unassignedRows));
assert_eq('unassigned reply owner id', 'agent_c', $unassignedRows[0]['owner_user_id']);
assert_eq('unassigned assigned_to_user_id is null', null, $unassignedRows[0]['assigned_to_user_id']);

echo "GhlLeadOwnershipHelperTest completed.\n";
