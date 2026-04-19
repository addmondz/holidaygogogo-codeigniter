<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Ghl_Processed_Leads_Model extends CI_Model
{
    protected $messageTimeColumn = null;
    protected $activeLockName = null;

    public function acquire_processor_lock($processorName, $timeoutSeconds)
    {
        $lockName = 'ghl:' . trim((string) $processorName);
        $timeoutSeconds = max(0, (int) $timeoutSeconds);

        $row = $this->db
            ->query('SELECT GET_LOCK(?, ?) AS lock_status', array($lockName, $timeoutSeconds))
            ->row_array();

        if (!isset($row['lock_status']) || (int) $row['lock_status'] !== 1) {
            return false;
        }

        $this->activeLockName = $lockName;
        return true;
    }

    public function release_processor_lock($processorName)
    {
        $lockName = $this->activeLockName !== null
            ? $this->activeLockName
            : 'ghl:' . trim((string) $processorName);

        $this->db->query('SELECT RELEASE_LOCK(?)', array($lockName));
        $this->activeLockName = null;
    }

    public function get_next_conversation_batch($processorName, $conversationLimit)
    {
        $conversationLimit = max(1, (int) $conversationLimit);
        $scanLimit = min(max($conversationLimit * 20, 1000), 10000);
        $state = $this->get_processor_state($processorName);

        $cursorMessageRowId = $state['last_processed_message_row_id'];

        $conversations = array();
        $selectedConversationIds = array();
        $safeCursorMessageRowId = $cursorMessageRowId;

        while (true) {
            $rows = $this->db->query(
                "
                SELECT id, conversation_id
                FROM ghl_messages
                WHERE id > ?
                  AND conversation_id IS NOT NULL
                  AND conversation_id <> ''
                ORDER BY id ASC
                LIMIT ?
                ",
                array($cursorMessageRowId, $scanLimit)
            )->result_array();

            if (empty($rows)) {
                break;
            }

            $hitBoundary = false;

            foreach ($rows as $row) {
                $conversationId = (string) $row['conversation_id'];

                if (count($conversations) < $conversationLimit) {
                    if (!isset($selectedConversationIds[$conversationId])) {
                        $selectedConversationIds[$conversationId] = true;
                        $conversations[] = array(
                            'conversation_id' => $conversationId,
                            'first_new_message_row_id' => (int) $row['id'],
                        );
                    }

                    $safeCursorMessageRowId = (int) $row['id'];
                    continue;
                }

                if (isset($selectedConversationIds[$conversationId])) {
                    $safeCursorMessageRowId = (int) $row['id'];
                    continue;
                }

                $hitBoundary = true;
                break;
            }

            if ($hitBoundary) {
                break;
            }

            if (count($rows) < $scanLimit) {
                break;
            }

            $lastRow = $rows[count($rows) - 1];
            $cursorMessageRowId = (int) $lastRow['id'];
        }

        return array(
            'conversations' => $conversations,
            'cursor' => array(
                'last_processed_message_row_id' => $safeCursorMessageRowId,
            ),
        );
    }

    public function get_conversation_messages($conversationId)
    {
        $timeColumn = $this->escape_identifier($this->get_message_time_column());

        return $this->db->query(
            "
            SELECT
                id,
                message_id,
                conversation_id,
                contact_id,
                direction,
                user_id,
                {$timeColumn} AS message_timestamp
            FROM ghl_messages
            WHERE conversation_id = ?
            ORDER BY {$timeColumn} ASC, id ASC
            ",
            array((string) $conversationId)
        )->result_array();
    }

    public function get_conversation_assigned_to($conversationId)
    {
        $row = $this->db
            ->select('assigned_to')
            ->from('ghl_conversations')
            ->where('conversation_id', (string) $conversationId)
            ->limit(1)
            ->get()
            ->row_array();

        return !empty($row['assigned_to']) ? (string) $row['assigned_to'] : null;
    }

    public function get_existing_conversion_map($conversationId)
    {
        $rows = $this->db
            ->select('lead_started_at, first_customer_message_id, is_converted, converted_at, assigned_to_user_id')
            ->from('ghl_processed_leads')
            ->where('conversation_id', (string) $conversationId)
            ->order_by('lead_started_at', 'ASC')
            ->get()
            ->result_array();

        $map = array();
        foreach ($rows as $row) {
            $key = $row['lead_started_at'] . '|' . $row['first_customer_message_id'];
            $map[$key] = array(
                'assigned_to_user_id' => $row['assigned_to_user_id'],
                'is_converted' => (int) $row['is_converted'],
                'converted_at' => $row['converted_at'],
            );
        }

        return $map;
    }

    public function get_existing_lead_starts($conversationId)
    {
        return $this->db
            ->select('lead_started_at, first_customer_message_id, assigned_to_user_id')
            ->from('ghl_processed_leads')
            ->where('conversation_id', (string) $conversationId)
            ->order_by('lead_started_at', 'ASC')
            ->get()
            ->result_array();
    }

    public function replace_conversation_leads($conversationId, $leads)
    {
        $this->db->trans_start();

        $this->db
            ->where('conversation_id', (string) $conversationId)
            ->delete('ghl_processed_leads');

        if (!empty($leads)) {
            $this->db->insert_batch('ghl_processed_leads', $leads);
        }

        $this->db->trans_complete();

        return $this->db->trans_status();
    }

    public function reset_processing_data()
    {
        $this->db->trans_start();

        $this->db->empty_table('ghl_processed_leads');
        $this->db->empty_table('ghl_processing_state');

        $this->db->trans_complete();

        return $this->db->trans_status();
    }

    public function get_processor_state($processorName)
    {
        $row = $this->db
            ->select('last_processed_message_row_id')
            ->from('ghl_processing_state')
            ->where('processor_name', (string) $processorName)
            ->limit(1)
            ->get()
            ->row_array();

        if (empty($row)) {
            return array(
                'last_processed_message_row_id' => 0,
            );
        }

        return array(
            'last_processed_message_row_id' => isset($row['last_processed_message_row_id']) ? (int) $row['last_processed_message_row_id'] : 0,
        );
    }

    public function save_processor_state($processorName, $lastProcessedMessageRowId)
    {
        $payload = array(
            'processor_name' => (string) $processorName,
            'last_processed_message_row_id' => (int) $lastProcessedMessageRowId,
            'last_processed_at' => date('Y-m-d H:i:s'),
        );

        $existing = $this->db
            ->select('id')
            ->from('ghl_processing_state')
            ->where('processor_name', (string) $processorName)
            ->limit(1)
            ->get()
            ->row_array();

        if (!empty($existing['id'])) {
            return $this->db
                ->where('id', (int) $existing['id'])
                ->update('ghl_processing_state', $payload);
        }

        return $this->db->insert('ghl_processing_state', $payload);
    }

    protected function get_message_time_column()
    {
        if ($this->messageTimeColumn !== null) {
            return $this->messageTimeColumn;
        }

        $fields = $this->db->list_fields('ghl_messages');

        if (in_array('timestamp', $fields, true)) {
            $this->messageTimeColumn = 'timestamp';
            return $this->messageTimeColumn;
        }

        if (in_array('date_added', $fields, true)) {
            $this->messageTimeColumn = 'date_added';
            return $this->messageTimeColumn;
        }

        show_error('Unable to detect message timestamp column on ghl_messages.', 500);
    }

    protected function escape_identifier($identifier)
    {
        return '`' . str_replace('`', '', (string) $identifier) . '`';
    }
}
