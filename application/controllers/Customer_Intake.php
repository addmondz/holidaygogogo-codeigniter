<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Customer Intake (public)
 *
 * Tokenised, unauthenticated form that lets a customer submit booking details
 * (name as per IC/passport, contact, IC/passport no, travel date, remarks,
 * rooms with per-child/baby ages) before staff finalise the booking.
 *
 * Extends CI_Controller directly (NOT MY_Controller) so the auth gate is
 * bypassed — same pattern as Booking_Confirmation, Guest_List, Travel_Voucher.
 */
class Customer_Intake extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Booking_Customer_Intake_Model');
        $this->load->helper(array('url', 'form', 'customer_intake'));
    }

    /**
     * GET /customer-intake/{token}
     * Renders the intake form, or the thank-you page if already locked.
     */
    public function index($token = null)
    {
        $context = $this->Booking_Customer_Intake_Model->get_by_token($token);
        if (empty($context)) {
            show_404();
            return;
        }

        if (!empty($context->intake_id) && (int) $context->locked === 1) {
            $this->load->view('customer_intake/thanks', array(
                'context'      => $context,
                'submitted_at' => $context->submitted_at,
            ));
            return;
        }

        // Pre-fill the overlapping fields from the admin's draft so the customer
        // starts from what staff already entered (they can override any of it).
        $prefill = array(
            'booking_name'      => !empty($context->Customer)  ? $context->Customer  : '',
            'contact_number'    => !empty($context->Mobile)    ? $context->Mobile    : '',
            'travel_start_date' => !empty($context->StartDate) ? $context->StartDate : '',
            'travel_end_date'   => !empty($context->EndDate)   ? $context->EndDate   : '',
        );

        $this->load->view('customer_intake/form', array(
            'token'   => $token,
            'context' => $context,
            'errors'  => array(),
            'old'     => array(),
            'prefill' => $prefill,
        ));
    }

    /**
     * POST /customer-intake/{token}/submit
     */
    public function submit($token = null)
    {
        $context = $this->Booking_Customer_Intake_Model->get_by_token($token);
        if (empty($context)) {
            show_404();
            return;
        }

        if (!empty($context->intake_id) && (int) $context->locked === 1) {
            redirect('customer-intake/' . urlencode($token));
            return;
        }

        $post = $this->input->post();
        $errors = $this->validate_payload($post);
        if (!empty($errors)) {
            $this->load->view('customer_intake/form', array(
                'token'   => $token,
                'context' => $context,
                'errors'  => $errors,
                'old'     => $post,
                'prefill' => array(),
            ));
            return;
        }

        $fields = array(
            'booking_name'      => trim((string) $post['booking_name']),
            'contact_number'    => trim((string) $post['contact_number']),
            'ic_passport_no'    => trim((string) $post['ic_passport_no']),
            'travel_start_date' => trim((string) $post['travel_start_date']),
            'travel_end_date'   => trim((string) $post['travel_end_date']),
            'special_remarks'   => isset($post['special_remarks']) ? trim((string) $post['special_remarks']) : null,
        );

        $rooms = $this->normalise_rooms(isset($post['rooms']) && is_array($post['rooms']) ? $post['rooms'] : array());

        $ok = $this->Booking_Customer_Intake_Model->save_submission((int) $context->booking_id, $fields, $rooms);
        if ($ok === false) {
            // Either a lock race condition or a DB failure — surface the lock state.
            redirect('customer-intake/' . urlencode($token));
            return;
        }

        redirect('customer-intake/' . urlencode($token));
    }

    /**
     * Server-side validation. Returns a map of field => message.
     */
    private function validate_payload($post)
    {
        $errors = array();
        $required = array(
            'booking_name'      => 'Booking name is required.',
            'contact_number'    => 'Contact number is required.',
            'ic_passport_no'    => 'IC / Passport number is required.',
            'travel_start_date' => 'Travel start date is required.',
            'travel_end_date'   => 'Travel end date is required.',
        );
        foreach ($required as $key => $msg) {
            if (empty($post[$key]) || trim((string) $post[$key]) === '') {
                $errors[$key] = $msg;
            }
        }

        if (empty($errors['contact_number']) && strlen(preg_replace('/\D+/', '', $post['contact_number'])) < 7) {
            $errors['contact_number'] = 'Contact number looks too short.';
        }

        $today_ts = strtotime(date('Y-m-d'));
        $start_ts = null;
        $end_ts   = null;
        if (empty($errors['travel_start_date'])) {
            $start_ts = strtotime($post['travel_start_date']);
            if ($start_ts === false) {
                $errors['travel_start_date'] = 'Travel start date is invalid.';
            } elseif ($start_ts < $today_ts) {
                $errors['travel_start_date'] = 'Travel start date cannot be in the past.';
            }
        }
        if (empty($errors['travel_end_date'])) {
            $end_ts = strtotime($post['travel_end_date']);
            if ($end_ts === false) {
                $errors['travel_end_date'] = 'Travel end date is invalid.';
            } elseif ($start_ts !== null && $start_ts !== false && $end_ts < $start_ts) {
                $errors['travel_end_date'] = 'Travel end date cannot be before the start date.';
            }
        }

        $rooms = isset($post['rooms']) && is_array($post['rooms']) ? $post['rooms'] : array();
        $normalised = $this->normalise_rooms($rooms);
        if (empty($normalised)) {
            $errors['rooms'] = 'Please add at least one room.';
        }

        return $errors;
    }

    /**
     * Strip the room payload down to validated rows. Drops empty rooms and
     * clamps ages to non-negative integers.
     */
    private function normalise_rooms(array $rooms)
    {
        $out = array();
        foreach ($rooms as $row) {
            if (!is_array($row)) { continue; }
            $type = isset($row['room_type']) ? trim((string) $row['room_type']) : '';
            $adults = isset($row['adult_count']) ? max(0, (int) $row['adult_count']) : 0;
            $child_ages = $this->clean_age_list(isset($row['child_ages']) ? $row['child_ages'] : null);
            $baby_ages  = $this->clean_age_list(isset($row['baby_ages']) ? $row['baby_ages'] : null);

            // Drop rows that are entirely blank.
            if ($type === '' && $adults === 0 && empty($child_ages) && empty($baby_ages)) {
                continue;
            }

            $out[] = array(
                'room_type'   => $type,
                'adult_count' => $adults,
                'child_ages'  => $child_ages,
                'baby_ages'   => $baby_ages,
            );
        }
        return $out;
    }

    /**
     * Accepts an array of ages or a comma-separated string. Returns a clean
     * comma-separated string of non-negative integers (or null when empty).
     */
    private function clean_age_list($input)
    {
        if ($input === null || $input === '') {
            return null;
        }
        $list = is_array($input) ? $input : explode(',', (string) $input);
        $clean = array();
        foreach ($list as $val) {
            $val = trim((string) $val);
            if ($val === '') { continue; }
            if (!ctype_digit($val)) { continue; }
            $clean[] = (string) (int) $val;
        }
        if (empty($clean)) {
            return null;
        }
        return implode(',', $clean);
    }
}
