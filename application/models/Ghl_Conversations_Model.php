<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Ghl_Conversations_Model extends CI_Model
{
    public function upsert_conversation($data)
    {
        $conversationId = isset($data['conversation_id']) ? trim((string) $data['conversation_id']) : '';

        if ($conversationId === '') {
            return false;
        }

        $existing = $this->db
            ->select('id')
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
                ));

            return $updated ? 'updated' : false;
        }

        $inserted = $this->db->insert('ghl_conversations', $insert);
        return $inserted ? 'inserted' : false;
    }
}
