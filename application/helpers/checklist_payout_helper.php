<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Checklist Pay-out Helper
 *
 * Shared rule for the "Supplier Pay-out Checklist Due Soon" card and its
 * drill-down list: decide whether a payout checklist branch must require the
 * checklist to be explicitly assigned to the product
 * (product_package_checklist) or qualifies by the line's pay-out date alone.
 *
 * The booking checklist modal AUTO-ADDS the "Payment Out To Supplier (deposit)"
 * checklist whenever the line has a PaymentOutSupplierDeposit date, regardless
 * of the product's assigned checklists (see Booking::_build_checklist_groups
 * and booking_flow_helper::are_all_checklists_completed). The "(full)" checklist
 * is NOT auto-added — it only shows when assigned to the product.
 *
 * So to match the modal row-for-row, a DEPOSIT line qualifies by its deposit
 * date alone (no assignment join), while a FULL line still requires the product
 * to carry the full checklist. Both call sites already gate on
 * "bp.<date_col> IS NOT NULL", so dropping the assignment join for deposit does
 * not widen the set beyond lines that actually have a deposit deadline.
 */

if (!function_exists('checklist_payout_requires_assignment')) {
    /**
     * Whether a payout branch on the given date column must require the
     * checklist to be assigned to the product.
     *
     * @param string $date_col booking_product date column driving the branch
     *                          (PaymentOutSupplierFull | PaymentOutSupplierDeposit)
     * @return bool true = require product_package_checklist assignment (full);
     *              false = qualify by the line's date alone (deposit)
     */
    function checklist_payout_requires_assignment($date_col)
    {
        return $date_col !== 'PaymentOutSupplierDeposit';
    }
}

if (!function_exists('checklist_payout_assignment_join_sql')) {
    /**
     * The product_package_checklist JOIN fragment for a payout branch, or an
     * empty string when the branch qualifies by date alone (deposit).
     *
     * Emitted as raw SQL for interpolation into the card/drill-down queries;
     * $checklist_id is cast to int so the fragment carries no user input.
     *
     * @param int    $checklist_id package_checklist.ID for this branch
     * @param string $date_col     branch date column (see above)
     * @return string SQL JOIN fragment (leading space) or '' for deposit
     */
    function checklist_payout_assignment_join_sql($checklist_id, $date_col)
    {
        if (!checklist_payout_requires_assignment($date_col)) {
            return '';
        }
        return ' JOIN product_package_checklist ppc ON ppc.product_id = bp.ProductID'
            . " AND JSON_CONTAINS(ppc.package_checklist_json, '" . (int) $checklist_id . "')";
    }
}
