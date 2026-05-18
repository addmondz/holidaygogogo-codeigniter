<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Booking Change Summary Helper
 *
 * Builds a short, human-readable summary of what changed on a booking,
 * for use in the booking-update notification message.
 */

if (!function_exists('_bcs_field_label')) {
    function _bcs_field_label($column)
    {
        static $map = [
            'Customer'                  => 'Customer',
            'Customer2'                 => 'Customer 2',
            'CustomerMobile'            => 'Mobile',
            'CustomerMobile2'           => 'Mobile 2',
            'CountryCodeID'             => 'Country Code',
            'CountryCodeID2'            => 'Country Code 2',
            'StartDate'                 => 'Start Date',
            'EndDate'                   => 'End Date',
            'DepositDeadline'           => 'Deposit Deadline',
            'FullPaymentDeadline'       => 'Full Payment Deadline',
            'AdditionalPaymentDeadline' => 'Additional Payment Deadline',
            'NetTotal'                  => 'Net Total',
            'Subtotal'                  => 'Subtotal',
            'Discount'                  => 'Discount',
            'DepositPercentage'         => 'Deposit %',
            'DepositMode'               => 'Deposit Mode',
            'DepositFixedAmount'        => 'Deposit Fixed Amount',
            'BookingNumber'             => 'Booking No.',
            'ReservationNumber'         => 'Reservation No.',
            'Destination'               => 'Destination',
            'SalesAgent'                => 'TC',
            'SalesAgent2'               => 'TC 2',
            'BookingOP'                 => 'Operations',
            'Status'                    => 'Status',
            'CancelStatus'              => 'Cancel Status',
            'LockStatus'                => 'Lock Status',
            'AfterSalesService'         => 'After-sales',
            'PartialRefund'             => 'Partial Refund',
            'BookingRemark'             => 'Booking Remark',
            'ChatLanguage'              => 'Chat Language',
            'Source'                    => 'Source',
            'Tag'                       => 'Tag',
            'TravelInsuranceStatus'     => 'Travel Insurance',
            'KeyContacts'               => 'Key Contacts',
            'SpecialRemarks'            => 'Special Remarks',
            'BookingConfirmationFooter' => 'Booking Confirmation Footer',
            'BookingConfirmationHeader' => 'Booking Confirmation Header',
            'BookingConfirmationTitle'  => 'Booking Confirmation Title',
            'TravelVoucherFooter'       => 'Travel Voucher Footer',
            'ProductSequence'           => 'Product Order',
        ];
        return isset($map[$column]) ? $map[$column] : $column;
    }
}

if (!function_exists('_bcs_is_rich_text_column')) {
    function _bcs_is_rich_text_column($column)
    {
        static $cols = [
            'BookingConfirmationFooter',
            'BookingConfirmationHeader',
            'BookingConfirmationTitle',
            'TravelVoucherFooter',
            'KeyContacts',
            'SpecialRemarks',
            'BookingRemark',
        ];
        return in_array($column, $cols, true);
    }
}

if (!function_exists('_bcs_format_value')) {
    function _bcs_format_value($column, $value)
    {
        if ($value === null || $value === '') {
            return '(empty)';
        }
        $money_cols = ['NetTotal', 'Subtotal', 'Discount', 'DepositFixedAmount'];
        if (in_array($column, $money_cols, true) && is_numeric($value)) {
            return 'RM ' . number_format((float)$value, 2);
        }
        $date_cols = ['StartDate', 'EndDate', 'DepositDeadline', 'FullPaymentDeadline', 'AdditionalPaymentDeadline'];
        if (in_array($column, $date_cols, true)) {
            $ts = strtotime((string)$value);
            if ($ts !== false) {
                return date('d/m/Y', $ts);
            }
        }
        $str = (string)$value;
        if (strpos($str, '<') !== false) {
            $str = trim(preg_replace('/\s+/', ' ', strip_tags($str)));
            $str = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (strlen($str) > 40) {
            $str = substr($str, 0, 37) . '...';
        }
        return $str;
    }
}

if (!function_exists('_bcs_product_name')) {
    function _bcs_product_name($row)
    {
        $arr = is_object($row) ? get_object_vars($row) : (array)$row;
        if (!empty($arr['Name'])) {
            return (string)$arr['Name'];
        }
        if (!empty($arr['ProductCode'])) {
            return (string)$arr['ProductCode'];
        }
        return 'Product';
    }
}

if (!function_exists('_bcs_lookup_product_names')) {
    /**
     * Look up booking_product Names for a list of BookingProductIDs.
     * Returns [BookingProductID => Name].
     */
    function _bcs_lookup_product_names(array $ids)
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids))));
        if (empty($ids)) {
            return [];
        }
        $CI =& get_instance();
        $CI->db->select('BookingProductID, Name');
        $CI->db->where_in('BookingProductID', $ids);
        $rows = $CI->db->get('booking_product')->result();
        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r->BookingProductID] = !empty($r->Name) ? $r->Name : 'Product';
        }
        return $out;
    }
}

if (!function_exists('_bcs_meaningful_update_keys')) {
    /**
     * Returns the keys on a posted booking_products[1] row that represent an
     * actual field change (i.e. excluding the row identifier and audit cols).
     */
    function _bcs_meaningful_update_keys(array $row)
    {
        $meta = ['BookingProductID', 'UpdateBy', 'UpdateDate'];
        return array_values(array_diff(array_keys($row), $meta));
    }
}

if (!function_exists('_bcs_operational_only_keys')) {
    /**
     * booking_product keys that are internal ops state, not customer-facing
     * booking terms. A row touching only these keys must not count as a real
     * product change (otherwise it would force BC re-approval).
     */
    function _bcs_operational_only_keys()
    {
        return [
            'disable_checklist_payment_out',
            'PaymentOutSupplierFull',
            'PaymentOutSupplierDeposit',
        ];
    }
}

if (!function_exists('booking_products_have_changes')) {
    /**
     * True if any booking_products POST array represents a real change.
     * Mirrors the operational-only rule in build_booking_change_summary() so
     * the notification trigger and message formatting cannot drift apart.
     */
    function booking_products_have_changes($products_create, $products_update, $products_delete)
    {
        if (is_array($products_create) && !empty($products_create)) {
            return true;
        }
        if (is_array($products_delete) && !empty($products_delete)) {
            return true;
        }
        if (is_array($products_update) && !empty($products_update)) {
            $operational_only = _bcs_operational_only_keys();
            foreach ($products_update as $row) {
                $arr = is_object($row) ? get_object_vars($row) : (array)$row;
                $keys = _bcs_meaningful_update_keys($arr);
                if (empty($keys) || empty(array_diff($keys, $operational_only))) {
                    continue;
                }
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('build_booking_update_notification_summary')) {
    /**
     * Scoped summary for the booking-updated bell notification: only travel-date
     * (StartDate / EndDate) booking-log entries plus product create/update/delete.
     * Other field diffs and customer-type changes are intentionally excluded.
     */
    function build_booking_update_notification_summary(
        $log_rows,
        $products_create,
        $products_update,
        $products_delete,
        $max_len = 240
    ) {
        $travel_only = [];
        if (is_array($log_rows)) {
            foreach ($log_rows as $row) {
                $arr = is_object($row) ? get_object_vars($row) : (array)$row;
                $col = isset($arr['Column']) ? $arr['Column'] : null;
                if ($col === 'StartDate' || $col === 'EndDate') {
                    $travel_only[] = $arr;
                }
            }
        }
        return build_booking_change_summary(
            $travel_only,
            $products_create,
            $products_update,
            $products_delete,
            [],
            [],
            $max_len
        );
    }
}

if (!function_exists('build_booking_change_summary')) {
    /**
     * Build a short summary like:
     *   "Net Total: RM 1,200.00 -> RM 1,500.00; Start Date: 01/05/2026 -> 02/05/2026; +1 product: Hotel A"
     *
     * @param array $log_rows           POST 'booking_log' (each row: Column, CurrentData, NewData)
     * @param array $products_create    POST 'booking_products'[0]
     * @param array $products_update    POST 'booking_products'[1]
     * @param array $products_delete    POST 'booking_products'[2]
     * @param array $old_customer_types Pre-Sync rows from Booking_Customer_Type_Model::Read_By_Booking()
     * @param array $new_customer_type_ids POST 'customer_type' (array of IDs)
     * @param int   $max_len            Soft cap on output length
     * @return string
     */
    function build_booking_change_summary(
        $log_rows,
        $products_create,
        $products_update,
        $products_delete,
        $old_customer_types,
        $new_customer_type_ids,
        $max_len = 240
    ) {
        $entries = [];

        // Booking field changes
        if (is_array($log_rows)) {
            foreach ($log_rows as $row) {
                $arr = is_object($row) ? get_object_vars($row) : (array)$row;
                $col = isset($arr['Column']) ? $arr['Column'] : null;
                if (empty($col) || $col === 'Subtotal') {
                    continue;
                }
                $cur = isset($arr['CurrentData']) ? $arr['CurrentData'] : null;
                $new = isset($arr['NewData']) ? $arr['NewData'] : null;
                if ((string)$cur === (string)$new) {
                    continue;
                }
                if (_bcs_is_rich_text_column($col)) {
                    $entries[] = _bcs_field_label($col) . ' updated';
                    continue;
                }
                $entries[] = _bcs_field_label($col) . ' '
                    . _bcs_format_value($col, $cur) . ' → ' . _bcs_format_value($col, $new);
            }
        }

        // Products created
        if (is_array($products_create) && !empty($products_create)) {
            $names = array_map('_bcs_product_name', $products_create);
            foreach ($names as $n) {
                $entries[] = 'Added ' . $n;
            }
        }

        // Products updated. The client posts ONE row per dirty field, plus a sweep
        // row carrying disable_checklist_payment_out for every existing product on
        // every save (booking.php:3315-3319). Skip rows whose meaningful keys are
        // entirely operational-only (sweep field or supplier payout deadlines —
        // see _bcs_operational_only_keys()), then dedupe by BookingProductID.
        $update_ids = [];
        if (is_array($products_update) && !empty($products_update)) {
            $operational_only = _bcs_operational_only_keys();
            foreach ($products_update as $row) {
                $arr = is_object($row) ? get_object_vars($row) : (array)$row;
                $keys = _bcs_meaningful_update_keys($arr);
                if (empty($keys) || empty(array_diff($keys, $operational_only))) {
                    continue;
                }
                if (!empty($arr['BookingProductID'])) {
                    $update_ids[(int)$arr['BookingProductID']] = true;
                }
            }
        }

        // Products deleted (POST'd as Status='N' rows)
        $delete_ids = [];
        if (is_array($products_delete) && !empty($products_delete)) {
            foreach ($products_delete as $row) {
                $arr = is_object($row) ? get_object_vars($row) : (array)$row;
                if (!empty($arr['BookingProductID'])) {
                    $delete_ids[(int)$arr['BookingProductID']] = true;
                }
            }
        }

        // One DB lookup for all referenced product names
        $name_map = _bcs_lookup_product_names(
            array_merge(array_keys($update_ids), array_keys($delete_ids))
        );
        foreach (array_keys($update_ids) as $id) {
            $entries[] = 'Updated ' . (isset($name_map[$id]) ? $name_map[$id] : 'Product');
        }
        foreach (array_keys($delete_ids) as $id) {
            $entries[] = 'Removed ' . (isset($name_map[$id]) ? $name_map[$id] : 'Product');
        }

        // Customer-type diff
        $old_ids   = [];
        $old_names = [];
        if (is_array($old_customer_types)) {
            foreach ($old_customer_types as $row) {
                $arr = is_object($row) ? get_object_vars($row) : (array)$row;
                if (!empty($arr['CustomerTypeID'])) {
                    $id = (int)$arr['CustomerTypeID'];
                    $old_ids[] = $id;
                    $old_names[$id] = !empty($arr['Name']) ? $arr['Name'] : ('#' . $id);
                }
            }
        }
        $new_ids = [];
        if (is_array($new_customer_type_ids)) {
            foreach ($new_customer_type_ids as $v) {
                if (is_numeric($v) && (int)$v > 0) {
                    $new_ids[] = (int)$v;
                }
            }
        }
        $old_set = array_unique($old_ids);
        $new_set = array_unique($new_ids);
        $added   = array_diff($new_set, $old_set);
        $removed = array_diff($old_set, $new_set);
        if (!empty($added) || !empty($removed)) {
            $CI =& get_instance();
            $added_names = [];
            if (!empty($added)) {
                $CI->db->select('CustomerTypeID, Name');
                $CI->db->where_in('CustomerTypeID', $added);
                $rows = $CI->db->get('customer_type')->result();
                foreach ($rows as $r) {
                    $added_names[] = $r->Name;
                }
            }
            $removed_names = [];
            foreach ($removed as $id) {
                $removed_names[] = isset($old_names[$id]) ? $old_names[$id] : ('#' . $id);
            }
            if (!empty($added_names)) {
                $entries[] = 'Added customer type ' . implode(', ', $added_names);
            }
            if (!empty($removed_names)) {
                $entries[] = 'Removed customer type ' . implode(', ', $removed_names);
            }
        }

        if (empty($entries)) {
            return '';
        }

        // Length cap with "(+N more)" tail
        $sep = ' • ';
        $out = '';
        $shown = 0;
        foreach ($entries as $i => $e) {
            $candidate = ($out === '') ? $e : ($out . $sep . $e);
            if (strlen($candidate) > $max_len) {
                break;
            }
            $out = $candidate;
            $shown++;
        }
        $remaining = count($entries) - $shown;
        if ($remaining > 0) {
            if ($out === '') {
                $out = $entries[0];
                $remaining = count($entries) - 1;
            }
            if ($remaining > 0) {
                $out .= ' (+' . $remaining . ' more)';
            }
        }
        return $out;
    }
}
