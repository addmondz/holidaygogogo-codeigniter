<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('ghl_build_lead_ownership_rows')) {
    function ghl_build_lead_ownership_rows(array $lead, array $replyOwners, $calculatedAt, $assignmentOwners = array())
    {
        $owners = array();
        $assignedTo = isset($lead['assigned_to_user_id']) ? trim((string) $lead['assigned_to_user_id']) : '';

        if (!is_array($assignmentOwners)) {
            $assignmentOwners = array();
        }

        if (empty($assignmentOwners) && $assignedTo !== '') {
            $assignmentOwners[] = array(
                'owner_user_id' => $assignedTo,
                'assigned_at' => null,
            );
        }

        foreach ($assignmentOwners as $assignmentOwner) {
            $ownerUserId = isset($assignmentOwner['owner_user_id'])
                ? trim((string) $assignmentOwner['owner_user_id'])
                : (isset($assignmentOwner['user_id']) ? trim((string) $assignmentOwner['user_id']) : '');

            if ($ownerUserId === '') {
                continue;
            }

            if (!isset($owners[$ownerUserId])) {
                $owners[$ownerUserId] = array(
                    'owner_user_id' => $ownerUserId,
                    'assigned_at' => null,
                    'is_assigned_owner' => 0,
                    'is_reply_owner' => 0,
                    'outbound_reply_count' => 0,
                );
            }

            $owners[$ownerUserId]['is_assigned_owner'] = 1;
            if (!empty($assignmentOwner['assigned_at'])) {
                $existingAssignedAt = !empty($owners[$ownerUserId]['assigned_at'])
                    ? strtotime($owners[$ownerUserId]['assigned_at'])
                    : null;
                $candidateAssignedAt = strtotime((string) $assignmentOwner['assigned_at']);

                if ($candidateAssignedAt !== false
                    && ($existingAssignedAt === null || $candidateAssignedAt > $existingAssignedAt)) {
                    $owners[$ownerUserId]['assigned_at'] = (string) $assignmentOwner['assigned_at'];
                }
            }
        }

        foreach ($replyOwners as $replyOwner) {
            $ownerUserId = isset($replyOwner['owner_user_id'])
                ? trim((string) $replyOwner['owner_user_id'])
                : (isset($replyOwner['user_id']) ? trim((string) $replyOwner['user_id']) : '');

            if ($ownerUserId === '') {
                continue;
            }

            $replyCount = isset($replyOwner['outbound_reply_count'])
                ? (int) $replyOwner['outbound_reply_count']
                : (isset($replyOwner['reply_count']) ? (int) $replyOwner['reply_count'] : 0);

            if (!isset($owners[$ownerUserId])) {
                $owners[$ownerUserId] = array(
                    'owner_user_id' => $ownerUserId,
                    'assigned_at' => null,
                    'is_assigned_owner' => 0,
                    'is_reply_owner' => 0,
                    'outbound_reply_count' => 0,
                );
            }

            $owners[$ownerUserId]['is_reply_owner'] = 1;
            $owners[$ownerUserId]['outbound_reply_count'] = max(
                (int) $owners[$ownerUserId]['outbound_reply_count'],
                $replyCount
            );
        }

        $rows = array();
        foreach ($owners as $owner) {
            $rows[] = array(
                'processed_lead_id' => (int) $lead['id'],
                'conversation_id' => (string) $lead['conversation_id'],
                'contact_id' => isset($lead['contact_id']) && $lead['contact_id'] !== '' ? (string) $lead['contact_id'] : null,
                'lead_started_at' => (string) $lead['lead_started_at'],
                'lead_ended_at' => !empty($lead['lead_ended_at']) ? (string) $lead['lead_ended_at'] : null,
                'owner_user_id' => $owner['owner_user_id'],
                'assigned_to_user_id' => $assignedTo !== '' ? $assignedTo : null,
                'assigned_at' => ((int) $owner['is_assigned_owner'] === 1 && !empty($owner['assigned_at'])) ? (string) $owner['assigned_at'] : null,
                'is_assigned_owner' => (int) $owner['is_assigned_owner'],
                'is_reply_owner' => (int) $owner['is_reply_owner'],
                'outbound_reply_count' => (int) $owner['outbound_reply_count'],
                'tracked_message_count' => isset($lead['tracked_message_count']) ? (int) $lead['tracked_message_count'] : 0,
                'responded_message_count' => isset($lead['responded_message_count']) ? (int) $lead['responded_message_count'] : 0,
                'avg_first_5_response_seconds' => isset($lead['avg_first_5_response_seconds']) && $lead['avg_first_5_response_seconds'] !== null ? (int) $lead['avg_first_5_response_seconds'] : null,
                'recent_tracked_message_count' => isset($lead['recent_tracked_message_count']) ? (int) $lead['recent_tracked_message_count'] : 0,
                'recent_responded_message_count' => isset($lead['recent_responded_message_count']) ? (int) $lead['recent_responded_message_count'] : 0,
                'avg_recent_5_response_seconds' => isset($lead['avg_recent_5_response_seconds']) && $lead['avg_recent_5_response_seconds'] !== null ? (int) $lead['avg_recent_5_response_seconds'] : null,
                'follow_up_status' => isset($lead['follow_up_status']) && $lead['follow_up_status'] !== '' ? (string) $lead['follow_up_status'] : 'pending',
                'is_converted' => isset($lead['is_converted']) ? (int) $lead['is_converted'] : 0,
                'booking_id' => !empty($lead['booking_id']) ? (int) $lead['booking_id'] : null,
                'converted_at' => !empty($lead['converted_at']) ? (string) $lead['converted_at'] : null,
                'is_bot_bounce' => isset($lead['is_bot_bounce']) ? (int) $lead['is_bot_bounce'] : 0,
                'calculated_at' => (string) $calculatedAt,
            );
        }

        usort($rows, function($a, $b) {
            return strcmp($a['owner_user_id'], $b['owner_user_id']);
        });

        return $rows;
    }
}
