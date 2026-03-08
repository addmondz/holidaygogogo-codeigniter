<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ghl_Sync_Model extends CI_Model
{
    public function create_log($data)
    {
        $this->db->insert('ghl_sync_run_log', $data);
        return (int) $this->db->insert_id();
    }

    public function upsert_conversation_by_conversation_id($data)
    {
        $conversationId = isset($data['ConversationID']) ? trim((string) $data['ConversationID']) : '';

        if ($conversationId === '') {
            $inserted = $this->db->insert('ghl_conversations', $data);
            return $inserted ? 'inserted' : false;
        }

        $existing = $this->db
            ->select('ID')
            ->from('ghl_conversations')
            ->where('ConversationID', $conversationId)
            ->limit(1)
            ->get()
            ->row_array();

        if (!empty($existing['ID'])) {
            $updated = $this->db
                ->where('ID', (int) $existing['ID'])
                ->update('ghl_conversations', $data);
            return $updated ? 'updated' : false;
        }

        $inserted = $this->db->insert('ghl_conversations', $data);
        return $inserted ? 'inserted' : false;
    }

    /**
     * Max LastMessageDate for this location (resume cursor for next sync).
     * Returns MySQL datetime string or null if no rows.
     */
    public function get_max_last_message_date_for_location($locationId)
    {
        $row = $this->db
            ->select_max('LastMessageDate')
            ->from('ghl_conversations')
            ->where('LocationID', $locationId)
            ->limit(1)
            ->get()
            ->row_array();
        return isset($row['LastMessageDate']) && $row['LastMessageDate'] !== null
            ? $row['LastMessageDate']
            : null;
    }
}
