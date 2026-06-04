<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Booking Customer Intake Model
 *
 * CRUD around the booking_customer_intake / booking_customer_intake_room tables
 * populated by the public customer-intake form.
 */
class Booking_Customer_Intake_Model extends CI_Model
{
    /**
     * Resolve booking + intake state via booking.Token. Used by the public
     * Customer_Intake controller to map a shared link to a booking.
     *
     * @param string $token booking.Token
     * @return object|null { booking_id, BookingNumber, Customer, Status, intake_id|null, locked|null, submitted_at|null }
     */
    function get_by_token($token)
    {
        if (empty($token)) {
            return null;
        }
        $this->db->select('booking.BookingID AS booking_id, booking.BookingNumber, booking.Customer, booking.Status,'
            . ' booking.Mobile, booking.StartDate, booking.EndDate,'
            . ' booking_customer_intake.id AS intake_id,'
            . ' booking_customer_intake.locked,'
            . ' booking_customer_intake.submitted_at');
        $this->db->from('booking');
        $this->db->join('booking_customer_intake', 'booking_customer_intake.booking_id = booking.BookingID', 'left');
        $this->db->where('booking.Token', $token);
        $this->db->limit(1);
        return $this->db->get()->row();
    }

    /**
     * Return the intake row and its rooms for a booking, or null if none.
     *
     * @param int $booking_id
     * @return array|null ['intake' => stdClass, 'rooms' => stdClass[]]
     */
    function get_by_booking_id($booking_id)
    {
        $intake = $this->db
            ->from('booking_customer_intake')
            ->where('booking_id', (int) $booking_id)
            ->get()->row();
        if (empty($intake)) {
            return null;
        }
        $rooms = $this->db
            ->from('booking_customer_intake_room')
            ->where('intake_id', (int) $intake->id)
            ->order_by('sort_order', 'ASC')
            ->order_by('id', 'ASC')
            ->get()->result();
        return array(
            'intake' => $intake,
            'rooms'  => $rooms,
        );
    }

    /**
     * Is this booking's intake locked (one-shot has already happened)?
     *
     * @param int $booking_id
     * @return bool
     */
    function is_locked($booking_id)
    {
        $row = $this->db
            ->select('locked')
            ->from('booking_customer_intake')
            ->where('booking_id', (int) $booking_id)
            ->get()->row();
        if (empty($row)) {
            return false;
        }
        return (int) $row->locked === 1;
    }

    /**
     * Transactional save of a customer intake. Refuses to overwrite a locked
     * row (one-shot rule chosen by the user).
     *
     * @param int   $booking_id
     * @param array $fields  Keys: booking_name, contact_number, ic_passport_no, travel_start_date, travel_end_date, special_remarks
     * @param array $rooms   Each: ['room_type'=>string, 'adult_count'=>int, 'child_ages'=>string|null, 'baby_ages'=>string|null]
     * @return int|false Inserted intake id on success, false on lock conflict / db failure.
     */
    function save_submission($booking_id, array $fields, array $rooms)
    {
        if ($this->is_locked($booking_id)) {
            return false;
        }

        $this->db->trans_start();

        // Remove any unlocked stale row (defensive — UNIQUE(booking_id) would
        // otherwise block re-submit from a not-yet-locked draft).
        $this->db->where('booking_id', (int) $booking_id)->delete('booking_customer_intake');

        $intake_data = array(
            'booking_id'        => (int) $booking_id,
            'booking_name'      => isset($fields['booking_name'])      ? $fields['booking_name']      : '',
            'contact_number'    => isset($fields['contact_number'])    ? $fields['contact_number']    : '',
            'ic_passport_no'    => isset($fields['ic_passport_no'])    ? $fields['ic_passport_no']    : '',
            'travel_start_date' => isset($fields['travel_start_date']) ? $fields['travel_start_date'] : null,
            'travel_end_date'   => isset($fields['travel_end_date'])   ? $fields['travel_end_date']   : null,
            'special_remarks'   => isset($fields['special_remarks'])   ? $fields['special_remarks']   : null,
            'submitted_at'      => date('Y-m-d H:i:s'),
            'locked'            => 1,
        );
        $this->db->insert('booking_customer_intake', $intake_data);
        $intake_id = (int) $this->db->insert_id();

        $sort_order = 0;
        foreach ($rooms as $room) {
            $this->db->insert('booking_customer_intake_room', array(
                'intake_id'    => $intake_id,
                'room_type'    => isset($room['room_type'])   ? $room['room_type']   : '',
                'adult_count'  => isset($room['adult_count']) ? (int) $room['adult_count'] : 0,
                'child_ages'   => isset($room['child_ages'])  ? $room['child_ages']  : null,
                'baby_ages'    => isset($room['baby_ages'])   ? $room['baby_ages']   : null,
                'sort_order'   => $sort_order++,
            ));
        }

        // Materialise the intake into the booking-side state so the staff edit
        // form picks it up via its existing readers (Room Management table reads
        // guest_list_room; pax fields read booking.Adult/Children/Infant).
        $this->load->helper('customer_intake');
        $totals = compute_intake_pax_totals($rooms);

        // Pax totals: always overwrite — the intake is the customer's
        // authoritative submission at draft (SAD) time. The `feedback_booking_pax`
        // rule about not touching these from room edits still applies to
        // staff-side edits; intake submission is the initial-population case.
        $this->db->where('BookingID', (int) $booking_id)->update('booking', array(
            'Adult'    => (string) $totals['adult'],
            'Children' => (string) $totals['child'],
            'Infant'   => (string) $totals['baby'],
        ));

        // Guest-list rooms: seed from the customer's submission unless staff have
        // already entered a *real* room (one with pax). Empty placeholder rooms —
        // e.g. the booking form's default "ROOM 1" with 0/0/0 — must NOT block the
        // seed, so they are cleared first. A room with any pax is treated as real
        // staff data and left untouched (the customer's rooms are then skipped).
        $existing_rooms = $this->db
            ->select('id, adult_count, child_count, infant_count')
            ->where('booking_id', (int) $booking_id)
            ->where('Status', 'Y')
            ->get('guest_list_room')->result();
        $has_real_room = false;
        $empty_room_ids = array();
        foreach ($existing_rooms as $er) {
            if (((int) $er->adult_count + (int) $er->child_count + (int) $er->infant_count) > 0) {
                $has_real_room = true;
            } else {
                $empty_room_ids[] = (int) $er->id;
            }
        }
        if (!$has_real_room) {
            // Drop empty placeholders (they carry no guests) so the customer's
            // submission becomes the authoritative room table.
            if (!empty($empty_room_ids)) {
                $this->db->where_in('id', $empty_room_ids)->delete('guest_list_room');
            }
            foreach ($rooms as $room) {
                $child_count = _intake_count_ages(isset($room['child_ages']) ? $room['child_ages'] : null);
                $baby_count  = _intake_count_ages(isset($room['baby_ages'])  ? $room['baby_ages']  : null);
                $room_name = isset($room['room_type']) ? strtoupper((string) $room['room_type']) : '';
                $this->db->insert('guest_list_room', array(
                    'booking_id'   => (int) $booking_id,
                    'room_name'    => $room_name,
                    'adult_count'  => isset($room['adult_count']) ? (int) $room['adult_count'] : 0,
                    'child_count'  => $child_count,
                    'infant_count' => $baby_count,
                    'Status'       => 'Y',
                    'InsertBy'     => null,
                    'InsertDate'   => date('Y-m-d H:i:s'),
                ));
            }
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            return false;
        }
        return $intake_id;
    }
}
