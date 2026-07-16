<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Summarise a guest's bookings into the profile-level figures shown on the
 * Customer Profile page (Guests/View): total pax, total sales, plus the most
 * recent Source and Customer Type. Kept pure (no DB / no CI) so it can be unit
 * tested and reused by the model.
 *
 * @param array $bookings  Booking rows for one guest, MOST RECENT FIRST. Each is
 *                         an object that may carry ->Adult, ->Children, ->Infant,
 *                         ->NetTotal, ->SourceName, ->CustomerType.
 * @return array{total_pax:int, total_sales:float, source:string, customer_type:string}
 */
if (!function_exists('guest_profile_summary')) {
	function guest_profile_summary($bookings)
	{
		$total_pax     = 0;
		$total_sales   = 0.0;
		$source        = '';
		$customer_type = '';

		foreach ((array) $bookings as $b) {
			$adult    = isset($b->Adult)    ? (int) $b->Adult    : 0;
			$children = isset($b->Children) ? (int) $b->Children : 0;
			$infant   = isset($b->Infant)   ? (int) $b->Infant   : 0;
			$total_pax   += $adult + $children + $infant;
			$total_sales += isset($b->NetTotal) ? (float) $b->NetTotal : 0.0;

			// Source / Customer Type reflect the most recent booking that has a
			// value (bookings arrive newest-first, so the first non-empty wins).
			if ($source === '' && isset($b->SourceName) && trim((string) $b->SourceName) !== '') {
				$source = trim((string) $b->SourceName);
			}
			if ($customer_type === '' && isset($b->CustomerType) && trim((string) $b->CustomerType) !== '') {
				$customer_type = trim((string) $b->CustomerType);
			}
		}

		return array(
			'total_pax'     => $total_pax,
			'total_sales'   => $total_sales,
			'source'        => $source,
			'customer_type' => $customer_type,
		);
	}
}
