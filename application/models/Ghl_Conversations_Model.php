<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Ghl_Conversations_Model extends CI_Model
{
    public function upsert_conversation($data)
    {
        $conversationId = isset($data['conversation_id']) ? trim((string) $data['conversation_id']) : '';
        $now = $this->get_code_datetime();

        if ($conversationId === '') {
            return false;
        }

        $existing = $this->db
            ->select('id, assigned_to, date_added, date_updated')
            ->from('ghl_conversations')
            ->where('conversation_id', $conversationId)
            ->limit(1)
            ->get()
            ->row_array();

        $columns = array(
            'conversation_id',
            'location_id',
            'contact_id',
            'assigned_to',
            'full_name',
            'contact_name',
            'company_name',
            'phone',
            'conversation_type',
            'inbox',
            'unread_count',
            'last_message_type',
            'last_message_body',
            'last_message_direction',
            'last_outbound_message_action',
            'last_internal_comment',
            'is_last_message_internal_comment',
            'date_added',
            'date_updated',
            'last_message_date',
            'last_inbound_whatsapp_message_date',
            'last_manual_message_date',
            'followers_json',
            'mentions_json',
            'tags_json',
            'scoring_json',
            'sort_json',
            'attributed_json',
            'raw_json',
        );

        $insert = array();
        foreach ($columns as $column) {
            $insert[$column] = array_key_exists($column, $data) ? $data[$column] : null;
        }

        if (!empty($existing['id'])) {
            $updated = $this->db
                ->where('id', (int) $existing['id'])
                ->update('ghl_conversations', array(
                    'location_id' => $insert['location_id'],
                    'contact_id' => $insert['contact_id'],
                    'assigned_to' => $insert['assigned_to'],
                    'full_name' => $insert['full_name'],
                    'contact_name' => $insert['contact_name'],
                    'company_name' => $insert['company_name'],
                    'phone' => $insert['phone'],
                    'conversation_type' => $insert['conversation_type'],
                    'inbox' => $insert['inbox'],
                    'unread_count' => $insert['unread_count'],
                    'last_message_type' => $insert['last_message_type'],
                    'last_message_body' => $insert['last_message_body'],
                    'last_message_direction' => $insert['last_message_direction'],
                    'last_outbound_message_action' => $insert['last_outbound_message_action'],
                    'last_internal_comment' => $insert['last_internal_comment'],
                    'is_last_message_internal_comment' => $insert['is_last_message_internal_comment'],
                    'date_added' => $insert['date_added'],
                    'date_updated' => $insert['date_updated'],
                    'last_message_date' => $insert['last_message_date'],
                    'last_inbound_whatsapp_message_date' => $insert['last_inbound_whatsapp_message_date'],
                    'last_manual_message_date' => $insert['last_manual_message_date'],
                    'followers_json' => $insert['followers_json'],
                    'mentions_json' => $insert['mentions_json'],
                    'tags_json' => $insert['tags_json'],
                    'scoring_json' => $insert['scoring_json'],
                    'sort_json' => $insert['sort_json'],
                    'attributed_json' => $insert['attributed_json'],
                    'raw_json' => $insert['raw_json'],
                    'updated_at' => $now,
                ));

            if ($updated) {
                $this->record_assignment_change_if_needed(
                    $conversationId,
                    isset($existing['assigned_to']) ? $existing['assigned_to'] : null,
                    $insert['assigned_to']
                );
            }

            return $updated ? 'updated' : false;
        }

        $insert['created_at'] = $now;
        $insert['updated_at'] = $now;
        $inserted = $this->db->insert('ghl_conversations', $insert);
        if ($inserted) {
            $this->record_initial_assignment($conversationId, $insert['assigned_to'], $insert);
        }
        return $inserted ? 'inserted' : false;
    }

    private function record_assignment_change_if_needed($conversationId, $oldAssignedTo, $newAssignedTo)
    {
        if (!$this->assignment_history_table_exists()) {
            return;
        }

        $oldAssignedTo = trim((string) $oldAssignedTo);
        $newAssignedTo = trim((string) $newAssignedTo);

        if ($oldAssignedTo === $newAssignedTo) {
            return;
        }

        $changedAt = $this->get_code_datetime();

        if ($oldAssignedTo !== '') {
            $this->close_open_assignment($conversationId, $oldAssignedTo, $changedAt);
        }

        if ($newAssignedTo !== '') {
            $this->insert_assignment_if_no_open_row($conversationId, $newAssignedTo, $changedAt);
        }
    }

    private function record_initial_assignment($conversationId, $assignedTo, $data)
    {
        if (!$this->assignment_history_table_exists()) {
            return;
        }

        $assignedTo = trim((string) $assignedTo);
        if ($assignedTo === '') {
            return;
        }

        $assignedAt = !empty($data['date_updated'])
            ? $data['date_updated']
            : (!empty($data['date_added']) ? $data['date_added'] : $this->get_code_datetime());

        $this->insert_assignment_if_no_open_row($conversationId, $assignedTo, $assignedAt);
    }

    private function close_open_assignment($conversationId, $ownerUserId, $unassignedAt)
    {
        $this->db
            ->where('conversation_id', (string) $conversationId)
            ->where('owner_user_id', (string) $ownerUserId)
            ->where('unassigned_at IS NULL', null, false)
            ->update('ghl_lead_assignment_history', array(
                'unassigned_at' => $unassignedAt,
                'updated_at' => $this->get_code_datetime(),
            ));
    }

    private function insert_assignment_if_no_open_row($conversationId, $ownerUserId, $assignedAt)
    {
        $existing = $this->db
            ->select('id')
            ->from('ghl_lead_assignment_history')
            ->where('conversation_id', (string) $conversationId)
            ->where('owner_user_id', (string) $ownerUserId)
            ->where('unassigned_at IS NULL', null, false)
            ->limit(1)
            ->get()
            ->row_array();

        if (!empty($existing['id'])) {
            return;
        }

        $now = $this->get_code_datetime();
        $this->db->insert('ghl_lead_assignment_history', array(
            'conversation_id' => (string) $conversationId,
            'owner_user_id' => (string) $ownerUserId,
            'assigned_at' => $assignedAt,
            'created_at' => $now,
            'updated_at' => $now,
        ));
    }

    private function assignment_history_table_exists()
    {
        return $this->db->table_exists('ghl_lead_assignment_history');
    }

    private function get_code_datetime()
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur')))
            ->format('Y-m-d H:i:s');
    }
}
