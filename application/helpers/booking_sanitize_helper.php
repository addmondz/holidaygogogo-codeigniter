<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Booking Sanitize Helper
 *
 * Normalisations applied to the posted `booking` payload before it is written
 * to the database, centralised so the model and the unit tests agree.
 */

if (!function_exists('booking_nullable_int_fk_fields')) {
    /**
     * Booking columns that are nullable INT foreign keys and can be *cleared*
     * from the admin form (the dropdown offers a blank "--SELECT--" option).
     *
     * When cleared, the browser serialises the value as an empty string ''.
     * MySQL under STRICT_TRANS_TABLES rejects '' for an INT column
     * ("Incorrect integer value: '' for column ..."), so these must be coerced
     * back to NULL before the write.
     *
     *   - BookingOP   -> admin.AdminID (FK fk_booking_booking_op_admin)
     *   - SalesAgent2 -> admin.AdminID
     *
     * @return string[]
     */
    function booking_nullable_int_fk_fields()
    {
        return ['BookingOP', 'SalesAgent2'];
    }
}

if (!function_exists('nullify_empty_booking_fk')) {
    /**
     * Coerce empty-string nullable-int-FK fields to NULL across an
     * update_batch-shaped booking payload (array of associative rows).
     *
     * Only rewrites keys that are present and exactly '' — a real integer id or
     * an absent key is left untouched.
     *
     * @param array $booking_data e.g. $this->input->post('booking')
     * @return array the same payload with '' FK values replaced by null
     */
    function nullify_empty_booking_fk($booking_data)
    {
        if (empty($booking_data) || !is_array($booking_data)) {
            return $booking_data;
        }

        $fk_fields = booking_nullable_int_fk_fields();

        foreach ($booking_data as $key => $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($fk_fields as $field) {
                if (isset($row[$field]) && $row[$field] === '') {
                    $booking_data[$key][$field] = null;
                }
            }
        }

        return $booking_data;
    }
}
