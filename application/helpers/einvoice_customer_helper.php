<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * E-Invoice Customer Helper
 *
 * Pure decision logic for the e-invoice submit flow: when a customer submits an
 * e-invoice request, each pax's requested name + phone is compared against the
 * customer recorded on the booking. If the pax looks like a DIFFERENT person, a
 * new customer record is created (and queued to AutoCount) by the caller.
 *
 * Business rule (updated 2026-08-24):
 *   - A pax is treated as a new customer when the name OR the phone differs
 *     from the booking customer. The pax is the same customer ONLY when BOTH
 *     the name AND the phone match.
 *   - Name match  : case- and whitespace-insensitive.
 *   - Phone match : last-9-digit key (shares customer_phone_dedup_key so any
 *                   format collapses to the same key).
 *   - A pax with a blank name or blank phone is never a new customer.
 *
 * Kept free of CodeIgniter/DB so it can be unit-tested under plain PHP
 * (see tests/helpers/EinvoiceCustomerMatchTest.php). The DB side (skip-if-phone-
 * exists dedup + insert) lives in Invoice_Split_Model::Create_Customers_For_New_Pax.
 */

if (!function_exists('einvoice_name_key')) {
    /**
     * Normalise a name for comparison: trim + collapse inner whitespace + upper.
     *
     * @param string $name
     * @return string Normalised key ('' when blank).
     */
    function einvoice_name_key($name)
    {
        $name = preg_replace('/\s+/', ' ', trim((string) $name));
        return strtoupper($name);
    }
}

if (!function_exists('einvoice_pax_needs_new_customer')) {
    /**
     * Whether a single e-invoice pax should become a new customer record.
     *
     * True when the pax name OR phone differs from the booking customer (i.e.
     * NOT a new customer only when BOTH match). Requires the pax to carry a
     * non-blank name and phone.
     *
     * @param string $pax_name      Requested pax name.
     * @param string $pax_phone     Requested pax phone.
     * @param string $booking_name  booking.Customer.
     * @param string $booking_phone booking.Mobile.
     * @return bool
     */
    function einvoice_pax_needs_new_customer($pax_name, $pax_phone, $booking_name, $booking_phone)
    {
        // Shared last-9-digit phone key (customer_dedup_helper). Available under
        // both CI (auto-loaded) and the standalone test (required directly).
        if (!function_exists('customer_phone_dedup_key')) {
            require_once __DIR__ . '/customer_dedup_helper.php';
        }

        $pax_name_key  = einvoice_name_key($pax_name);
        $pax_phone_key = customer_phone_dedup_key($pax_phone);

        // A pax with no usable name or phone can't identify a new customer.
        if ($pax_name_key === '' || $pax_phone_key === '') {
            return false;
        }

        $name_differs  = $pax_name_key !== einvoice_name_key($booking_name);
        $phone_differs = $pax_phone_key !== customer_phone_dedup_key($booking_phone);

        return $name_differs || $phone_differs;
    }
}
