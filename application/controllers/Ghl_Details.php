<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ghl_Details extends MY_Controller
{
    public function index()
    {
        $phone = trim((string) $this->input->get('phone'));
        $digits = preg_replace('/\D+/', '', $phone);
        $phoneSql = $this->normalized_phone_sql('gc.phone');
        $phoneSql2 = $this->normalized_phone_sql('gc2.phone');
        $fromSql = $this->normalized_phone_sql('gm.from_number');
        $toSql = $this->normalized_phone_sql('gm.to_number');

        $state = $this->db->query("
            SELECT *
            FROM ghl_processing_state
            WHERE processor_name = 'ghl_leads_processor'
            LIMIT 1
        ")->row_array();

        $summary = array(
            'conversations' => $this->one("SELECT COUNT(*) c FROM ghl_conversations"),
            'conversations_without_leads' => $this->one("
                SELECT COUNT(*) c
                FROM ghl_conversations gc
                LEFT JOIN ghl_processed_leads pl ON pl.conversation_id = gc.conversation_id
                WHERE pl.id IS NULL
            "),
            'conversations_with_uncovered_inbound' => $this->one("
                SELECT COUNT(DISTINCT gm.conversation_id) c
                FROM ghl_messages gm
                LEFT JOIN ghl_processed_leads pl
                  ON pl.conversation_id = gm.conversation_id
                 AND pl.lead_started_at <= gm.date_added
                 AND (pl.lead_ended_at IS NULL OR pl.lead_ended_at > gm.date_added)
                WHERE gm.direction = 'inbound'
                  AND gm.conversation_id IS NOT NULL
                  AND gm.conversation_id <> ''
                  AND pl.id IS NULL
            "),
            'uncovered_inbound_1d' => $this->one($this->uncovered_count_sql(1)),
            'uncovered_inbound_3d' => $this->one($this->uncovered_count_sql(3)),
            'uncovered_inbound_7d' => $this->one($this->uncovered_count_sql(7)),
            'messages_after_processor_row' => $this->one("
                SELECT COUNT(*) c
                FROM ghl_messages
                WHERE id > ?
            ", array(!empty($state['last_processed_message_row_id']) ? (int) $state['last_processed_message_row_id'] : 0)),
        );

        $phoneData = array();
        if ($digits !== '') {
            $phoneData['summary'] = $this->db->query("
                SELECT
                    COUNT(DISTINCT gc.conversation_id) conversations,
                    COUNT(DISTINCT pl.id) leads_created,
                    COUNT(DISTINCT gm.id) messages,
                    COUNT(DISTINCT CASE WHEN gm.direction = 'inbound' THEN gm.id END) inbound_messages,
                    COUNT(DISTINCT CASE WHEN gm.direction = 'outbound' THEN gm.id END) outbound_messages,
                    MIN(CASE WHEN gm.direction = 'inbound' THEN gm.date_added END) first_inbound,
                    MIN(CASE WHEN gm.direction = 'outbound' THEN gm.date_added END) first_outbound
                FROM ghl_conversations gc
                LEFT JOIN ghl_processed_leads pl ON pl.conversation_id = gc.conversation_id
                LEFT JOIN ghl_messages gm ON gm.conversation_id = gc.conversation_id
                WHERE {$phoneSql} LIKE ?
            ", array('%' . $digits . '%'))->row_array();

            $phoneData['summary']['uncovered_inbound_messages'] = $this->one("
                SELECT COUNT(*) c
                FROM ghl_messages gm
                LEFT JOIN ghl_processed_leads pl
                  ON pl.conversation_id = gm.conversation_id
                 AND pl.lead_started_at <= gm.date_added
                 AND (pl.lead_ended_at IS NULL OR pl.lead_ended_at > gm.date_added)
                WHERE gm.direction = 'inbound'
                  AND ({$fromSql} LIKE ? OR {$toSql} LIKE ?)
                  AND pl.id IS NULL
            ", array('%' . $digits . '%', '%' . $digits . '%'));

            $phoneData['messages'] = $this->db->query("
                SELECT direction, user_id, message_type,
                       LEFT(REPLACE(REPLACE(body, CHAR(10), ' '), CHAR(13), ' '), 220) body,
                       from_number, to_number, date_added, date_updated, updated_at
                FROM ghl_messages gm
                WHERE {$fromSql} LIKE ? OR {$toSql} LIKE ?
                ORDER BY date_added ASC, id ASC
                LIMIT 300
            ", array('%' . $digits . '%', '%' . $digits . '%'))->result_array();

            $phoneData['leads'] = $this->db->query("
                SELECT pl.id lead_id, gc.contact_name, gc.phone, pl.conversation_id,
                       COALESCE(NULLIF(current_gu.Name, ''), NULLIF(gc.assigned_to, ''), NULLIF(gu.Name, ''), NULLIF(pl.assigned_to_user_id, ''), 'Unassigned') assigned_to,
                       CASE
                           WHEN EXISTS (
                               SELECT 1
                               FROM ghl_processed_leads pl2
                               LEFT JOIN ghl_conversations gc2 ON gc2.conversation_id = pl2.conversation_id
                               WHERE {$phoneSql2} LIKE ?
                                 AND pl2.lead_started_at < pl.lead_started_at
                               LIMIT 1
                           ) THEN 'Existing Lead'
                           ELSE 'New Lead'
                       END lead_type,
                       pl.lead_started_at, pl.lead_ended_at,
                       pl.tracked_message_count tracked_messages,
                       pl.responded_message_count responded_messages,
                       CASE WHEN pl.is_converted = 1 THEN 'Converted' ELSE 'Open' END status,
                       GROUP_CONCAT(DISTINCT CONCAT(
                           COALESCE(NULLIF(owner_gu.Name, ''), glo.owner_user_id),
                           ' (',
                           CASE
                               WHEN glo.is_assigned_owner = 1 THEN 'Assigned Owner'
                               WHEN glo.is_reply_owner = 1 THEN 'Reply Owner'
                               ELSE 'Owner'
                           END,
                           ')'
                       ) ORDER BY owner_gu.Name SEPARATOR ', ') lead_under
                FROM ghl_processed_leads pl
                LEFT JOIN ghl_conversations gc ON gc.conversation_id = pl.conversation_id
                LEFT JOIN ghl_users current_gu ON current_gu.UserID = gc.assigned_to
                LEFT JOIN ghl_users gu ON gu.UserID = pl.assigned_to_user_id
                LEFT JOIN ghl_lead_ownership glo ON glo.processed_lead_id = pl.id
                LEFT JOIN ghl_users owner_gu ON owner_gu.UserID = glo.owner_user_id
                WHERE {$phoneSql} LIKE ?
                GROUP BY pl.id, gc.contact_name, gc.phone, pl.conversation_id, assigned_to, lead_type,
                         pl.lead_started_at, pl.lead_ended_at, pl.tracked_message_count,
                         pl.responded_message_count, pl.is_converted
                ORDER BY pl.lead_started_at DESC
            ", array('%' . $digits . '%', '%' . $digits . '%'))->result_array();

            $phoneData['uncovered_inbound'] = $this->db->query("
                SELECT gm.conversation_id, gm.from_number, gm.to_number, gm.date_added, gm.updated_at,
                       LEFT(REPLACE(REPLACE(gm.body, CHAR(10), ' '), CHAR(13), ' '), 220) body
                FROM ghl_messages gm
                LEFT JOIN ghl_processed_leads pl
                  ON pl.conversation_id = gm.conversation_id
                 AND pl.lead_started_at <= gm.date_added
                 AND (pl.lead_ended_at IS NULL OR pl.lead_ended_at > gm.date_added)
                WHERE gm.direction = 'inbound'
                  AND ({$fromSql} LIKE ? OR {$toSql} LIKE ?)
                  AND pl.id IS NULL
                ORDER BY gm.date_added DESC
                LIMIT 100
            ", array('%' . $digits . '%', '%' . $digits . '%'))->result_array();
        }

        $uncovered = $this->db->query("
            SELECT gm.conversation_id, gc.contact_name, gc.phone, COUNT(*) uncovered_messages,
                   MIN(gm.date_added) first_uncovered_at, MAX(gm.date_added) last_uncovered_at,
                   MAX(gm.id) max_message_id
            FROM ghl_messages gm
            LEFT JOIN ghl_conversations gc ON gc.conversation_id = gm.conversation_id
            LEFT JOIN ghl_processed_leads pl
              ON pl.conversation_id = gm.conversation_id
             AND pl.lead_started_at <= gm.date_added
             AND (pl.lead_ended_at IS NULL OR pl.lead_ended_at > gm.date_added)
            WHERE gm.direction = 'inbound'
              AND gm.conversation_id IS NOT NULL
              AND gm.conversation_id <> ''
              AND pl.id IS NULL
            GROUP BY gm.conversation_id, gc.contact_name, gc.phone
            ORDER BY last_uncovered_at DESC
            LIMIT 100
        ")->result_array();

        echo '<!doctype html><html><head><title>GHL Details</title></head><body>';
        echo '<h1>GHL Details</h1>';
        echo '<form method="get"><input name="phone" value="' . html_escape($phone) . '" placeholder="phone"><button>Search</button></form>';
        echo '<p>Search digits: ' . html_escape($digits) . '</p>';

        if ($digits !== '') {
            $this->table('Phone Report', array($phoneData['summary']));
            $this->table('Leads Created', $phoneData['leads']);
            $this->table('Uncovered Inbound Messages', $phoneData['uncovered_inbound']);
            $this->table('Messages', $phoneData['messages']);
            echo '</body></html>';
            return;
        }

        $this->table('Summary', array($summary));
        $this->table('Processor State', $state ? array($state) : array());
        $this->table('Latest Conversations With Inbound Messages Not Covered By Any Lead', $uncovered);
        echo '</body></html>';
    }

    private function one($sql, $params = array())
    {
        $row = $this->db->query($sql, $params)->row_array();
        return !empty($row['c']) ? (int) $row['c'] : 0;
    }

    private function uncovered_count_sql($days)
    {
        $days = max(1, (int) $days);
        $now = $this->get_code_datetime();
        $cutoffTimestamp = strtotime($now . ' -' . $days . ' days');
        $cutoff = $cutoffTimestamp !== false
            ? date('Y-m-d H:i:s', $cutoffTimestamp)
            : $now;
        $safeCutoff = $this->db->escape($cutoff);
        $safeNow = $this->db->escape($now);

        return "
            SELECT COUNT(*) c
            FROM ghl_messages gm
            LEFT JOIN ghl_processed_leads pl
              ON pl.conversation_id = gm.conversation_id
             AND pl.lead_started_at <= gm.date_added
             AND (pl.lead_ended_at IS NULL OR pl.lead_ended_at > gm.date_added)
            WHERE gm.direction = 'inbound'
              AND gm.date_added >= {$safeCutoff}
              AND gm.date_added < {$safeNow}
              AND pl.id IS NULL
        ";
    }

    private function normalized_phone_sql($column)
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$column}, '+', ''), ' ', ''), '-', ''), '(', ''), ')', '')";
    }

    private function get_code_datetime()
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur')))
            ->format('Y-m-d H:i:s');
    }

    private function table($title, $rows)
    {
        echo '<h2>' . html_escape($title) . '</h2>';
        if (empty($rows)) {
            echo '<p>No data.</p>';
            return;
        }

        echo '<table border="1" cellpadding="4" cellspacing="0"><thead><tr>';
        foreach (array_keys($rows[0]) as $key) {
            echo '<th>' . html_escape($key) . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $value) {
                echo '<td>' . nl2br(html_escape((string) $value)) . '</td>';
            }
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
}
