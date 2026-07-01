<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Net Profit / Net Profit Margin visibility on the booking list, the summary
 * total, and the Excel export.
 *
 * These figures are hidden from the front-line roles that may see bookings but
 * not the company's margins: SALES AGENT (20) and MARKETING (60). Every other
 * role (Owner, Finance, OP, OP Team Lead, TC, Team Lead) sees profit as before.
 *
 * Keep this in one place so the on-screen columns, the summary card, and the
 * downloaded spreadsheet can never disagree about who sees profit.
 */
if ( ! function_exists('admin_hides_profit'))
{
    function admin_hides_profit($level)
    {
        return in_array((int) $level, array(20, 60), TRUE);
    }
}
