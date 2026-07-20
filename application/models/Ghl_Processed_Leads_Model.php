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

    public function get_next_conversation_batch($processorName, $conversationLimit, $upperBound = null)
    {
        $conversationLimit = max(1, (int) $conversationLimit);
        $scanLimit = min(max($conversationLimit * 20, 1000), 10000);
        $state = $this->get_processor_state($processorName);
        $timeColumn = $this->escape_identifier($this->get_message_time_column());

        $cursorProcessedAt = $state['last_processed_at'];
        $cursorMessageRowId = $state['last_processed_message_row_id'];
        $batchUpperBound = $upperBound !== null && $upperBound !== ''
            ? (string) $upperBound
            : $this->get_code_datetime();

        $conversations = array();
        $selectedConversationIds = array();
        $safeCursorProcessedAt = $cursorProcessedAt;
        $safeCursorMessageRowId = $cursorMessageRowId;

        while (true) {
            $rows = $this->db->query(
                "
                SELECT id, conversation_id, updated_at
                FROM ghl_messages
                WHERE (
                    updated_at > ?
                    OR (updated_at = ? AND id > ?)
                    OR id > ?
                )
                  AND updated_at < ?
                  AND {$timeColumn} < ?
                  AND conversation_id IS NOT NULL
                  AND conversation_id <> ''
                ORDER BY id ASC
                LIMIT ?
                ",
                array(
                    $cursorProcessedAt,
                    $cursorProcessedAt,
                    $cursorMessageRowId,
                    $cursorMessageRowId,
                    $batchUpperBound,
                    $batchUpperBound,
                    $scanLimit,
                )
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

                    if ((string) $row['updated_at'] > $safeCursorProcessedAt) {
                        $safeCursorProcessedAt = (string) $row['updated_at'];
                    }
                    if ((int) $row['id'] > $safeCursorMessageRowId) {
                        $safeCursorMessageRowId = (int) $row['id'];
                    }
                    continue;
                }

                if (isset($selectedConversationIds[$conversationId])) {
                    if ((string) $row['updated_at'] > $safeCursorProcessedAt) {
                        $safeCursorProcessedAt = (string) $row['updated_at'];
                    }
                    if ((int) $row['id'] > $safeCursorMessageRowId) {
                        $safeCursorMessageRowId = (int) $row['id'];
                    }
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
            if ((string) $lastRow['updated_at'] > $cursorProcessedAt) {
                $cursorProcessedAt = (string) $lastRow['updated_at'];
            }
            if ((int) $lastRow['id'] > $cursorMessageRowId) {
                $cursorMessageRowId = (int) $lastRow['id'];
            }
        }

        return array(
            'conversations' => $conversations,
            'cursor' => array(
                'last_processed_at' => $safeCursorProcessedAt,
                'last_processed_message_row_id' => $safeCursorMessageRowId,
            ),
        );
    }

    public function get_rebuild_conversation_batch($lastConversationId, $conversationLimit, $upperBound = null)
    {
        $conversationLimit = max(1, (int) $conversationLimit);
        $lastConversationId = (string) $lastConversationId;
        $timeColumn = $this->escape_identifier($this->get_message_time_column());

        $clauses = array(
            'conversation_id IS NOT NULL',
            "conversation_id <> ''",
            'conversation_id > ?',
        );
        $params = array($lastConversationId);

        if ($upperBound !== null && $upperBound !== '') {
            $clauses[] = 'updated_at < ?';
            $params[] = (string) $upperBound;
            $clauses[] = "{$timeColumn} < ?";
            $params[] = (string) $upperBound;
        }

        $params[] = $conversationLimit;

        return $this->db->query(
            "
            SELECT conversation_id, MIN(id) AS first_new_message_row_id
            FROM ghl_messages
            WHERE " . implode(' AND ', $clauses) . "
            GROUP BY conversation_id
            ORDER BY conversation_id ASC
            LIMIT ?
            ",
            $params
        )->result_array();
    }

    public function get_uncovered_recent_inbound_conversation_batch($daysBack, $conversationLimit, $upperBound = null)
    {
        $daysBack = max(1, (int) $daysBack);
        $conversationLimit = max(1, (int) $conversationLimit);
        $timeColumn = $this->escape_identifier($this->get_message_time_column());

        $batchUpperBound = $upperBound !== null && $upperBound !== ''
            ? (string) $upperBound
            : $this->get_code_datetime();
        $cutoffTimestamp = strtotime($batchUpperBound . ' -' . $daysBack . ' days');
        $cutoff = $cutoffTimestamp !== false
            ? date('Y-m-d H:i:s', $cutoffTimestamp)
            : date('Y-m-d H:i:s', strtotime('-' . $daysBack . ' days'));

        return $this->db->query(
            "
            SELECT
                m.conversation_id,
                MIN(m.id) AS first_new_message_row_id
            FROM ghl_messages m
            LEFT JOIN ghl_processed_leads pl
              ON pl.conversation_id = m.conversation_id
             AND pl.lead_started_at <= m.{$timeColumn}
             AND (pl.lead_ended_at IS NULL OR pl.lead_ended_at > m.{$timeColumn})
            WHERE m.direction = 'inbound'
              AND m.{$timeColumn} >= ?
              AND m.{$timeColumn} < ?
              AND m.conversation_id IS NOT NULL
              AND m.conversation_id <> ''
              AND pl.id IS NULL
            GROUP BY m.conversation_id
            ORDER BY MIN(m.{$timeColumn}) ASC, MIN(m.id) ASC
            LIMIT ?
            ",
            array($cutoff, $batchUpperBound, $conversationLimit)
        )->result_array();
    }

    public function get_processing_completion_cursor($upperBound = null)
    {
        $timeColumn = $this->escape_identifier($this->get_message_time_column());
        $clauses = array(
            'conversation_id IS NOT NULL',
            "conversation_id <> ''",
        );
        $params = array();

        if ($upperBound !== null && $upperBound !== '') {
            $clauses[] = 'updated_at < ?';
            $params[] = (string) $upperBound;
            $clauses[] = "{$timeColumn} < ?";
            $params[] = (string) $upperBound;
        }

        $row = $this->db->query(
            "
            SELECT id, updated_at
            FROM ghl_messages
            WHERE " . implode(' AND ', $clauses) . "
            ORDER BY updated_at DESC, id DESC
            LIMIT 1
            ",
            $params
        )->row_array();

        if (empty($row)) {
            return array(
                'last_processed_at' => '1970-01-01 00:00:00',
                'last_processed_message_row_id' => 0,
            );
        }

        return array(
            'last_processed_at' => (string) $row['updated_at'],
            'last_processed_message_row_id' => (int) $row['id'],
        );
    }

    public function get_current_processing_upper_bound()
    {
        return $this->get_code_datetime();
    }

    public function get_conversation_messages($conversationId, $updatedBefore = null)
    {
        $timeColumn = $this->escape_identifier($this->get_message_time_column());
        $clauses = array('conversation_id = ?');
        $params = array((string) $conversationId);

        if ($updatedBefore !== null && $updatedBefore !== '') {
            $clauses[] = 'updated_at < ?';
            $params[] = (string) $updatedBefore;
            $clauses[] = "{$timeColumn} < ?";
            $params[] = (string) $updatedBefore;
        }

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
            WHERE " . implode(' AND ', $clauses) . "
            ORDER BY {$timeColumn} ASC, id ASC
            ",
            $params
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
            ->select('lead_started_at, first_customer_message_id, is_converted, booking_id, converted_at, assigned_to_user_id')
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
                'booking_id' => !empty($row['booking_id']) ? (int) $row['booking_id'] : null,
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
            $now = $this->get_code_datetime();
            foreach ($leads as &$lead) {
                if (!array_key_exists('created_at', $lead) || $lead['created_at'] === null || $lead['created_at'] === '') {
                    $lead['created_at'] = $now;
                }
                if (!array_key_exists('updated_at', $lead) || $lead['updated_at'] === null || $lead['updated_at'] === '') {
                    $lead['updated_at'] = $now;
                }
            }
            unset($lead);

            // Drop is_bot_bounce for DBs that have not run the 20260717 migration.
            if (!$this->db->field_exists('is_bot_bounce', 'ghl_processed_leads')) {
                foreach ($leads as &$lead) {
                    unset($lead['is_bot_bounce']);
                }
                unset($lead);
            }

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

    public function reset_conversion_data()
    {
        return $this->db
            ->set('is_converted', 0)
            ->set('booking_id', null)
            ->set('converted_at', null)
            ->update('ghl_processed_leads');
    }

    public function get_open_lead_conversion_batch($limit = null, $lastLeadId = 0, $leadId = null, $conversationId = null)
    {
        $limit = $limit !== null ? max(1, (int) $limit) : null;
        $lastLeadId = max(0, (int) $lastLeadId);

        $clauses = array('pl.is_converted = 0');
        $params = array();

        if ($leadId !== null) {
            $clauses[] = 'pl.id = ?';
            $params[] = (int) $leadId;
        } elseif ($conversationId !== null && $conversationId !== '') {
            $clauses[] = 'pl.conversation_id = ?';
            $params[] = (string) $conversationId;
        } else {
            $clauses[] = 'pl.id > ?';
            $params[] = $lastLeadId;
        }

        $limitSql = '';
        if ($limit !== null) {
            $limitSql = ' LIMIT ?';
            $params[] = $limit;
        }

        return $this->db->query(
            "
            SELECT
                pl.id,
                pl.conversation_id,
                pl.contact_id,
                pl.lead_started_at,
                COALESCE(NULLIF(gc.phone, ''), NULLIF(gcv.phone, '')) AS lead_phone
            FROM ghl_processed_leads pl
            LEFT JOIN ghl_contacts gc ON gc.contact_id = pl.contact_id
            LEFT JOIN ghl_conversations gcv ON gcv.conversation_id = pl.conversation_id
            WHERE " . implode(' AND ', $clauses) . "
            ORDER BY pl.id ASC" . $limitSql . "
            ",
            $params
        )->result_array();
    }

    public function find_first_booking_conversion($phoneVariants, $leadStartedAt, $convertedBefore = null)
    {
        $phoneVariants = array_values(array_filter(array_unique(array_map('strval', (array) $phoneVariants))));
        $leadStartedAt = trim((string) $leadStartedAt);

        if (empty($phoneVariants) || $leadStartedAt === '') {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($phoneVariants), '?'));
        $params = array($leadStartedAt);
        $convertedBeforeSql = '';
        if ($convertedBefore !== null && $convertedBefore !== '') {
            $convertedBeforeSql = ' AND b.InsertDate < ?';
            $params[] = (string) $convertedBefore;
        }
        $params = array_merge($params, $phoneVariants, $phoneVariants, $phoneVariants);

        $row = $this->db->query(
            "
            SELECT
                b.BookingID,
                b.BookingNumber,
                b.Customer,
                b.Mobile,
                b.Mobile2,
                b.InsertDate AS converted_at
            FROM booking b
            LEFT JOIN customer c ON c.CustomerID = b.CustomerID
            WHERE b.InsertDate >= ?
              {$convertedBeforeSql}
              AND b.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
              AND (
                  b.Mobile IN ({$placeholders})
                  OR b.Mobile2 IN ({$placeholders})
                  OR c.phone_number IN ({$placeholders})
              )
            ORDER BY b.InsertDate ASC, b.BookingID ASC
            LIMIT 1
            ",
            $params
        )->row_array();

        return !empty($row) ? $row : null;
    }

    public function mark_lead_as_converted($leadId, $bookingId, $convertedAt)
    {
        return $this->db
            ->where('id', (int) $leadId)
            ->update(
                'ghl_processed_leads',
                array(
                    'is_converted' => 1,
                    'booking_id' => (int) $bookingId > 0 ? (int) $bookingId : null,
                    'converted_at' => $convertedAt,
                    'updated_at' => $this->get_code_datetime(),
                )
            );
    }

    public function get_processor_state($processorName)
    {
        $row = $this->db
            ->select('last_processed_at, last_processed_message_row_id')
            ->from('ghl_processing_state')
            ->where('processor_name', (string) $processorName)
            ->limit(1)
            ->get()
            ->row_array();

        if (empty($row)) {
            return array(
                'last_processed_at' => '1970-01-01 00:00:00',
                'last_processed_message_row_id' => 0,
            );
        }

        $lastProcessedAt = !empty($row['last_processed_at'])
            ? (string) $row['last_processed_at']
            : '1970-01-01 00:00:00';
        $lastProcessedMessageRowId = isset($row['last_processed_message_row_id'])
            ? (int) $row['last_processed_message_row_id']
            : 0;

        // Older code saved this value with PHP's timezone while ghl_messages.updated_at
        // is DB-generated. Normalize future cursors to the stored message row's DB
        // timestamp so later upserts to older rows are still picked up.
        $maxMessageUpdatedAt = $this->get_max_message_updated_at();
        if ($maxMessageUpdatedAt !== null && $lastProcessedAt > $maxMessageUpdatedAt) {
            $messageCursor = $this->get_message_row_cursor($lastProcessedMessageRowId);
            $lastProcessedAt = $messageCursor['last_processed_at'];
            $lastProcessedMessageRowId = $messageCursor['last_processed_message_row_id'];
        }

        return array(
            'last_processed_at' => $lastProcessedAt,
            'last_processed_message_row_id' => $lastProcessedMessageRowId,
        );
    }

    public function save_processor_state($processorName, $lastProcessedMessageRowId, $lastProcessedAt = null)
    {
        $payload = array(
            'processor_name' => (string) $processorName,
            'last_processed_message_row_id' => (int) $lastProcessedMessageRowId,
            'last_processed_at' => $lastProcessedAt !== null && $lastProcessedAt !== ''
                ? (string) $lastProcessedAt
                : $this->get_code_datetime(),
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

    protected function get_code_datetime()
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur')))
            ->format('Y-m-d H:i:s');
    }

    protected function get_message_row_cursor($messageRowId)
    {
        $messageRowId = max(0, (int) $messageRowId);
        if ($messageRowId <= 0) {
            return array(
                'last_processed_at' => '1970-01-01 00:00:00',
                'last_processed_message_row_id' => 0,
            );
        }

        $row = $this->db->query(
            "
            SELECT id, updated_at
            FROM ghl_messages
            WHERE id = ?
            LIMIT 1
            ",
            array($messageRowId)
        )->row_array();

        if (empty($row['updated_at'])) {
            return array(
                'last_processed_at' => '1970-01-01 00:00:00',
                'last_processed_message_row_id' => 0,
            );
        }

        return array(
            'last_processed_at' => (string) $row['updated_at'],
            'last_processed_message_row_id' => isset($row['id']) ? (int) $row['id'] : 0,
        );
    }

    protected function get_max_message_updated_at()
    {
        $row = $this->db
            ->query('SELECT MAX(updated_at) AS max_updated_at FROM ghl_messages')
            ->row_array();

        return !empty($row['max_updated_at']) ? (string) $row['max_updated_at'] : null;
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
