<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Booking Draft Helper
 *
 * Shared contract for the "Save as customer intake draft" flow:
 *
 *   - A draft booking is parked in status SAD ("SAVE AS DRAFT"). While in that
 *     state the admin booking form locks every field except a small whitelist
 *     (see draft_editable_fields), and the customer fills the public intake.
 *   - Once the customer submits, staff graduate the booking with one of two
 *     buttons -> PB ("PENDING BC") or PBC ("PENDING BC CONFIRMATION").
 *
 * Centralised here so the view (field disabling), the controller (graduate
 * transition + server-side write guard) and the unit tests all agree.
 */

if (!function_exists('is_draft_status')) {
    /**
     * Is this status the parked customer-intake draft?
     *
     * @param string|null $status booking.Status code
     * @return bool
     */
    function is_draft_status($status)
    {
        return $status === 'SAD';
    }
}

if (!function_exists('draft_editable_fields')) {
    /**
     * Canonical whitelist of booking-row columns a draft save is allowed to
     * write. Everything else on the form is disabled while the booking sits in
     * SAD, and the controller strips any non-whitelisted column server-side so
     * a tampered request cannot change locked fields.
     *
     * NOTE: travel date posts as StartDate/EndDate (the form's #TravelDate input
     * is split into the two columns before submit), so both appear here.
     *
     * @return string[]
     */
    function draft_editable_fields()
    {
        return array(
            'Customer',
            'Mobile',
            'CountryCodeID',
            'SalesAgent2',
            'Source',
            'ChatLanguage',
            'Destination',
            'StartDate',
            'EndDate',
            'DepositDeadline',
            'FullPaymentDeadline',
            'BookingFormText',
            'ChatSummary',
        );
    }
}

if (!function_exists('resolve_graduate_status')) {
    /**
     * Map the posted graduate-button value to a valid target status. Returns
     * null for anything that is not an explicit graduate choice, so a plain
     * draft save leaves the booking in SAD.
     *
     *   'PB'  -> PENDING BC
     *   'PBC' -> PENDING BC CONFIRMATION
     *
     * @param string|null $posted
     * @return string|null 'PB' | 'PBC' | null
     */
    function resolve_graduate_status($posted)
    {
        if ($posted === 'PB' || $posted === 'PBC') {
            return $posted;
        }
        return null;
    }
}
