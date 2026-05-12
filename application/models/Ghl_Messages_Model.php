<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Ghl_Messages_Model extends CI_Model
{
    public function upsert_message($data)
    {
        $messageId = isset($data['message_id']) ? trim((string) $data['message_id']) : '';

        if ($messageId === '') {
            return false;
        }

        $existing = $this->db
            ->select('id')
            ->from('ghl_messages')
            ->where('message_id', $messageId)
            ->limit(1)
            ->get()
            ->row_array();

        $columns = array(
            'message_id',
            'location_id',
            'conversation_id',
            'contact_id',
            'user_id',
            'alt_id',
            'direction',
            'status',
            'message_type_code',
            'message_type',
            'content_type',
            'body',
            'from_number',
            'to_number',
            'date_added',
            'date_updated',
            'attachments_json',
            'meta_json',
            'raw_json',
        );

        $insert = array();
        foreach ($columns as $column) {
            $insert[$column] = array_key_exists($column, $data) ? $data[$column] : null;
        }

        if (!empty($existing['id'])) {
            $updated = $this->db
                ->where('id', (int) $existing['id'])
                ->update('ghl_messages', array(
                    'location_id' => $insert['location_id'],
                    'conversation_id' => $insert['conversation_id'],
                    'contact_id' => $insert['contact_id'],
                    'user_id' => $insert['user_id'],
                    'alt_id' => $insert['alt_id'],
                    'direction' => $insert['direction'],
                    'status' => $insert['status'],
                    'message_type_code' => $insert['message_type_code'],
                    'message_type' => $insert['message_type'],
                    'content_type' => $insert['content_type'],
                    'body' => $insert['body'],
                    'from_number' => $insert['from_number'],
                    'to_number' => $insert['to_number'],
                    'date_added' => $insert['date_added'],
                    'date_updated' => $insert['date_updated'],
                    'attachments_json' => $insert['attachments_json'],
                    'meta_json' => $insert['meta_json'],
                    'raw_json' => $insert['raw_json'],
                ));

            return $updated ? 'updated' : false;
        }

        $inserted = $this->db->insert('ghl_messages', $insert);
        return $inserted ? 'inserted' : false;
    }
}
