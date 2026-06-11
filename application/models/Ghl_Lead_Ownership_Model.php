<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Ghl_Lead_Ownership_Model extends CI_Model
{
    const OWNERSHIP_DELETE_BATCH_SIZE = 500;

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

    public function reset_ownership_data()
    {
        return $this->db->empty_table('ghl_lead_ownership');
    }

    public function get_processed_lead_batch($limit = null, $lastLeadId = 0, $leadId = null, $conversationId = null)
    {
        $limit = $limit !== null ? max(1, (int) $limit) : null;
        $lastLeadId = max(0, (int) $lastLeadId);

        $clauses = array();
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
                pl.assigned_to_user_id,
                pl.lead_started_at,
                pl.lead_ended_at,
                pl.tracked_message_count,
                pl.responded_message_count,
                pl.avg_first_5_response_seconds,
                pl.recent_tracked_message_count,
                pl.recent_responded_message_count,
                pl.avg_recent_5_response_seconds,
                pl.follow_up_status,
                pl.is_converted,
                pl.booking_id,
                pl.converted_at
            FROM ghl_processed_leads pl
            WHERE " . implode(' AND ', $clauses) . "
            ORDER BY pl.id ASC" . $limitSql . "
            ",
            $params
        )->result_array();
    }

    public function get_reply_owners_for_leads($leadIds, $replyThreshold = 2)
    {
        $leadIds = array_values(array_filter(array_map('intval', (array) $leadIds), function($id) {
            return $id > 0;
        }));

        if (empty($leadIds)) {
            return array();
        }

        $replyThreshold = max(0, (int) $replyThreshold);
        $messageTimeColumn = $this->escape_identifier($this->get_message_time_column());
        $placeholders = implode(',', array_fill(0, count($leadIds), '?'));
        $params = $leadIds;
        $params[] = $replyThreshold;

        $rows = $this->db->query(
            "
            SELECT
                pl.id AS processed_lead_id,
                gm.user_id AS owner_user_id,
                COUNT(*) AS outbound_reply_count
            FROM ghl_processed_leads pl
            INNER JOIN ghl_messages gm
                ON gm.conversation_id = pl.conversation_id
               AND gm.{$messageTimeColumn} >= pl.lead_started_at
               AND (
                   pl.lead_ended_at IS NULL
                   OR gm.{$messageTimeColumn} < pl.lead_ended_at
               )
            WHERE pl.id IN ({$placeholders})
              AND gm.direction = 'outbound'
              AND gm.user_id IS NOT NULL
              AND gm.user_id <> ''
            GROUP BY pl.id, gm.user_id
            HAVING COUNT(*) > ?
            ORDER BY pl.id ASC, gm.user_id ASC
            ",
            $params
        )->result_array();

        $map = array();
        foreach ($rows as $row) {
            $leadId = (int) $row['processed_lead_id'];
            if (!isset($map[$leadId])) {
                $map[$leadId] = array();
            }

            $map[$leadId][] = array(
                'owner_user_id' => $row['owner_user_id'],
                'outbound_reply_count' => (int) $row['outbound_reply_count'],
            );
        }

        return $map;
    }

    public function get_assignment_history_for_leads($leads)
    {
        if (!$this->db->table_exists('ghl_lead_assignment_history')) {
            return array();
        }

        $conversationIds = array();
        foreach ((array) $leads as $lead) {
            if (!empty($lead['conversation_id'])) {
                $conversationIds[(string) $lead['conversation_id']] = (string) $lead['conversation_id'];
            }
        }

        if (empty($conversationIds)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($conversationIds), '?'));
        $rows = $this->db->query(
            "
            SELECT
                conversation_id,
                owner_user_id,
                assigned_at,
                unassigned_at
            FROM ghl_lead_assignment_history
            WHERE conversation_id IN ({$placeholders})
            ORDER BY conversation_id ASC, owner_user_id ASC, assigned_at ASC
            ",
            array_values($conversationIds)
        )->result_array();

        $map = array();
        foreach ($rows as $row) {
            $conversationId = (string) $row['conversation_id'];
            if (!isset($map[$conversationId])) {
                $map[$conversationId] = array();
            }
            $map[$conversationId][] = $row;
        }

        return $map;
    }

    public function resolve_assigned_at_for_lead($lead, $assignmentRows)
    {
        $assignedTo = isset($lead['assigned_to_user_id']) ? trim((string) $lead['assigned_to_user_id']) : '';
        if ($assignedTo === '') {
            return null;
        }

        $leadStart = !empty($lead['lead_started_at']) ? strtotime($lead['lead_started_at']) : false;
        if ($leadStart === false) {
            return null;
        }

        $leadEnd = !empty($lead['lead_ended_at']) ? strtotime($lead['lead_ended_at']) : null;
        $bestAssignedAt = null;
        $bestAssignedTs = null;

        foreach ((array) $assignmentRows as $row) {
            if (!isset($row['owner_user_id']) || (string) $row['owner_user_id'] !== $assignedTo) {
                continue;
            }

            $assignedTs = !empty($row['assigned_at']) ? strtotime($row['assigned_at']) : false;
            if ($assignedTs === false) {
                continue;
            }

            $unassignedTs = !empty($row['unassigned_at']) ? strtotime($row['unassigned_at']) : null;
            if ($leadEnd !== null && $assignedTs > $leadEnd) {
                continue;
            }
            if ($unassignedTs !== null && $unassignedTs < $leadStart) {
                continue;
            }

            if ($bestAssignedTs === null || $assignedTs > $bestAssignedTs) {
                $bestAssignedTs = $assignedTs;
                $bestAssignedAt = $row['assigned_at'];
            }
        }

        return $bestAssignedAt;
    }

    public function replace_ownership_for_leads($leadIds, $ownershipRows)
    {
        $leadIds = array_values(array_unique(array_filter(array_map('intval', (array) $leadIds), function($id) {
            return $id > 0;
        })));

        if (empty($leadIds)) {
            return true;
        }

        $this->db->trans_start();

        foreach (array_chunk($leadIds, self::OWNERSHIP_DELETE_BATCH_SIZE) as $leadIdBatch) {
            $placeholders = implode(',', array_fill(0, count($leadIdBatch), '?'));
            $this->db->query(
                "DELETE FROM ghl_lead_ownership WHERE processed_lead_id IN ({$placeholders})",
                $leadIdBatch
            );
        }

        if (!empty($ownershipRows)) {
            if (!$this->db->field_exists('assigned_at', 'ghl_lead_ownership')) {
                foreach ($ownershipRows as &$ownershipRow) {
                    unset($ownershipRow['assigned_at']);
                }
                unset($ownershipRow);
            }
            $this->db->insert_batch('ghl_lead_ownership', $ownershipRows);
        }

        $this->db->trans_complete();

        return $this->db->trans_status();
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
