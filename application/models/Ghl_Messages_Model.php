<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Ghl_Messages_Model extends CI_Model
{
    public function upsert_message($data)
    {
        $messageId = isset($data['message_id']) ? trim((string) $data['message_id']) : '';
        $now = $this->get_code_datetime();

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
                    'updated_at' => $now,
                ));

            return $updated ? 'updated' : false;
        }

        $insert['created_at'] = $now;
        $insert['updated_at'] = $now;
        $inserted = $this->db->insert('ghl_messages', $insert);
        return $inserted ? 'inserted' : false;
    }

    /**
     * Given a page's phone numbers, return the set of those that have at least
     * one stored WhatsApp message — so the listing only shows a "message log"
     * icon when a conversation actually exists. Keyed by normalized digits.
     *
     * One query, matched via index-friendly exact IN on from_number/to_number
     * (see ghl_message_log_helper.php).
     *
     * @param string[] $page_phones Listing rows' phones (any format).
     * @return array<string,bool> [normalized_digits => true] for phones with a log.
     */
    public function Phones_With_Messages($page_phones)
    {
        $this->load->helper('ghl_message_log');

        $candidates = array();
        foreach ((array) $page_phones as $phone) {
            foreach (ghl_message_log_phone_candidates($phone) as $c) {
                $candidates[$c] = true;
            }
        }
        if (empty($candidates)) {
            return array();
        }
        $candidates = array_keys($candidates);
        $placeholders = implode(',', array_fill(0, count($candidates), '?'));

        $rows = $this->db->query("
            SELECT DISTINCT from_number, to_number
            FROM ghl_messages
            WHERE from_number IN ($placeholders)
               OR to_number IN ($placeholders)
        ", array_merge($candidates, $candidates))->result_array();

        $out = array();
        foreach (ghl_message_log_match_phones($rows, $page_phones) as $digits) {
            $out[$digits] = true;
        }
        return $out;
    }

    /**
     * Convenience wrapper for the Guest List / GHL Leads listing: derive each
     * guest row's wa-digits (CallingCode + ContactNum) and return the set that
     * has a stored conversation. Keyed by normalized digits.
     *
     * @param array $guests Guest rows (objects with CallingCode, ContactNum).
     * @return array<string,bool>
     */
    public function Phones_With_Messages_For_Guests($guests)
    {
        if (empty($guests)) {
            return array();
        }
        $this->load->helper('guest_contact');
        $phones = array();
        foreach ($guests as $g) {
            $digits = guest_contact_wa_digits(
                isset($g->CallingCode) ? (string) $g->CallingCode : '',
                (string) $g->ContactNum
            );
            if ($digits !== '') {
                $phones[] = $digits;
            }
        }
        return $this->Phones_With_Messages($phones);
    }

    /**
     * The full WhatsApp conversation for a single phone, oldest first, shaped
     * for the chat modal. Outbound messages carry the agent's GHL user name.
     *
     * @param string $phone A phone in any format.
     * @param int    $limit Max messages returned.
     * @return array<int,array{side:string,author:string,body:string,time:string,type:string}>
     */
    public function Conversation_By_Phone($phone, $limit = 500)
    {
        $this->load->helper('ghl_message_log');

        $candidates = ghl_message_log_phone_candidates($phone);
        if (empty($candidates)) {
            return array();
        }
        $placeholders = implode(',', array_fill(0, count($candidates), '?'));
        $limit = max(1, (int) $limit);

        $rows = $this->db->query("
            SELECT gm.direction, gm.body, gm.message_type, gm.date_added,
                   gu.Name AS agent_name, gc.contact_name
            FROM ghl_messages gm
            LEFT JOIN ghl_users gu ON gu.UserID = gm.user_id
            LEFT JOIN ghl_conversations gc ON gc.conversation_id = gm.conversation_id
            WHERE gm.from_number IN ($placeholders)
               OR gm.to_number IN ($placeholders)
            ORDER BY gm.date_added ASC, gm.id ASC
            LIMIT {$limit}
        ", array_merge($candidates, $candidates))->result_array();

        $out = array();
        foreach ($rows as $row) {
            $out[] = ghl_message_log_shape_message($row);
        }
        return $out;
    }

    private function get_code_datetime()
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur')))
            ->format('Y-m-d H:i:s');
    }
}
