<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * TC sales commission — pure money maths for the Booking dashboard
 * "Sales Commission (Month)" card (Booking::ajax_summary_cards, TC branch).
 *
 * Commission is the TC's own rate (admin.CommissionPercent) applied to the
 * NetTotal of the completed bookings they are credited on. Kept pure (no CI/DB)
 * so the percentage arithmetic and its edge cases are unit-testable in isolation
 * from the SQL that sums the eligible bookings.
 */

if (!function_exists('tc_commission_amount')) {
	/**
	 * Commission earned = base sales * percent / 100, rounded to 2 dp.
	 *
	 * A non-positive base or percent yields 0 (no negative commission, and an
	 * unset rate simply earns nothing rather than erroring).
	 *
	 * @param float|int|string $net_total_sum    summed NetTotal of eligible bookings
	 * @param float|int|string $commission_percent the TC's commission rate (e.g. 5 = 5%)
	 * @return float
	 */
	function tc_commission_amount($net_total_sum, $commission_percent)
	{
		$base = (float) $net_total_sum;
		$pct  = (float) $commission_percent;
		if ($base <= 0 || $pct <= 0) {
			return 0.0;
		}
		return round($base * $pct / 100, 2);
	}
}

if (!function_exists('tc_commission_percent_label')) {
	/**
	 * Human label for a commission rate: trims trailing zeros so 5.00 -> "5%"
	 * and 2.50 -> "2.5%".
	 *
	 * @param float|int|string $commission_percent
	 * @return string
	 */
	function tc_commission_percent_label($commission_percent)
	{
		$pct = (float) $commission_percent;
		$s   = rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.');
		return ($s === '' ? '0' : $s) . '%';
	}
}
