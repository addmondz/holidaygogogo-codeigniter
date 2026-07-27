<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pure formatting behind the Lead Reply Activity dashboard's per-number modal.
 *
 * Clicking any lead-count column (New Lead Picked Up, Lead Responded, Transfer
 * Out Lead, Today Handling Lead) opens a modal listing the individual leads
 * behind that number — which lead belongs to that owner. The controller pulls
 * raw per-lead rows from Report_Model (the SAME queries that produce the counts,
 * so the list matches the number), then hands them here to shape the JSON the
 * modal renders. Kept DB-free / CI-free so it is unit-testable in isolation —
 * see tests/helpers/LeadReplyActivityLeadsModalTest.php.
 *
 * Each metric reads its own "activity" timestamp (when the lead entered that
 * count) so the modal's date column stays meaningful per column.
 */

if (!function_exists('lead_reply_activity_leads_activity_field')) {
    /**
     * The raw-row timestamp field that represents "when this lead entered the
     * count", per metric. Unknown metric => first-contact (picked-up default).
     */
    function lead_reply_activity_leads_activity_field($metric)
    {
        switch ((string) $metric) {
            case 'responded':
            case 'today_handling':
                return 'first_reply_at';
            case 'transfer_out':
            case 'helped_reply':
                return 'reply_created_at';
            case 'picked_up':
            default:
                return 'lead_started_at';
        }
    }
}

if (!function_exists('lead_reply_activity_leads_datetime_label')) {
    /**
     * "d M Y h:i A" label for a timestamp, or '-' when missing/unparseable.
     */
    function lead_reply_activity_leads_datetime_label($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '-';
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return '-';
        }
        return date('d M Y h:i A', $ts);
    }
}

if (!function_exists('lead_reply_activity_today_handling_leads')) {
    /**
     * "Today Handling" lead set = the leads the owner replied to that were NOT
     * transferred out (still on the owner's plate). Set difference of the
     * responded rows minus the transfer-out rows, keyed by processed_lead_id.
     *
     * @param array $respondedRows raw responded rows (each has processed_lead_id)
     * @param array $transferRows  raw transfer-out rows (each has processed_lead_id)
     * @return array the responded rows whose lead is not in the transfer-out set
     */
    function lead_reply_activity_today_handling_leads($respondedRows, $transferRows)
    {
        $transferred = array();
        foreach ((array) $transferRows as $row) {
            $row = (array) $row;
            if (isset($row['processed_lead_id'])) {
                $transferred[(string) $row['processed_lead_id']] = true;
            }
        }

        $out = array();
        foreach ((array) $respondedRows as $row) {
            $row = (array) $row;
            $leadId = isset($row['processed_lead_id']) ? (string) $row['processed_lead_id'] : '';
            if ($leadId !== '' && isset($transferred[$leadId])) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }
}

if (!function_exists('lead_reply_activity_leads_format')) {
    /**
     * Shape raw per-lead DB rows into the modal's JSON rows.
     *
     * @param array  $rows   each row keyed (missing keys tolerated):
     *   contact_name, phone, conversation_id, owner_name, and the metric's
     *   activity field (lead_started_at / first_reply_at / reply_created_at).
     * @param string $metric picked_up | responded | transfer_out | helped_reply | today_handling
     * @return array list of display rows:
     *   contact_name, phone, conversation_id, owner_name, activity_label
     */
    function lead_reply_activity_leads_format($rows, $metric)
    {
        $activityField = lead_reply_activity_leads_activity_field($metric);
        $out = array();

        foreach ((array) $rows as $row) {
            $row = (array) $row;

            $contact = isset($row['contact_name']) ? trim((string) $row['contact_name']) : '';
            if ($contact === '') {
                $contact = 'Unknown Contact';
            }

            $phone = isset($row['phone']) ? trim((string) $row['phone']) : '';
            if ($phone === '') {
                $phone = 'No phone';
            }

            $activityAt = isset($row[$activityField]) ? $row[$activityField] : '';

            $out[] = array(
                'contact_name'    => $contact,
                'phone'           => $phone,
                'conversation_id' => isset($row['conversation_id']) ? (string) $row['conversation_id'] : '',
                'owner_name'      => isset($row['owner_name']) ? (string) $row['owner_name'] : '',
                'activity_label'  => lead_reply_activity_leads_datetime_label($activityAt),
            );
        }

        return $out;
    }
}
