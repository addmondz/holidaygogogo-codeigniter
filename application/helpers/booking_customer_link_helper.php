<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Booking Customer Link Helper
 *
 * Pure logic backing the Customer-name cell on the booking listing
 * (Booking::ajax_list). The name is wrapped in a link to that customer's
 * dashboard (Customer/Update?customer_id=<CustomerID>) so it can be opened
 * straight from the listing.
 *
 * The link is gated by the same rule as the Customer menu item
 * (admin_can_access_setting_module('customer', ...)) — the caller passes the
 * resolved boolean — and is only built when the booking actually has a linked
 * customer master (a positive CustomerID); otherwise the plain, HTML-escaped
 * name is returned so nothing dead-ends or leaks markup.
 *
 * Kept free of CodeIgniter/DB so it can be unit tested under SQLite :memory:
 * (see tests/helpers/BookingCustomerProfileLinkTest.php).
 */

if (!function_exists('booking_customer_profile_link')) {
    /**
     * Render the booking listing Customer cell.
     *
     * @param string   $name              The booking customer name (booking.Customer).
     * @param int|null $customer_id       The linked customer master id (booking.CustomerID).
     * @param bool     $can_view_customer Whether the viewer can access Customer/Update.
     * @param string   $base_url          Site base URL (CI base_url()), trailing slash ok.
     * @return string Ready-to-render HTML for the cell.
     */
    function booking_customer_profile_link($name, $customer_id, $can_view_customer, $base_url = '')
    {
        $safe_name = htmlspecialchars((string) $name, ENT_QUOTES);

        if (!$can_view_customer) {
            return $safe_name;
        }

        $id = (int) $customer_id;
        if ($id < 1) {
            return $safe_name;
        }

        $href = rtrim((string) $base_url, '/') . '/Customer/Update?customer_id=' . $id;

        return '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '"'
            . ' target="_blank" title="Open customer profile">' . $safe_name . '</a>';
    }
}
