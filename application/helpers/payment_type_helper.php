<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Payment-type classification helpers.
 *
 * Kept as a single source of truth for "which payment types are addressed to a
 * supplier" so the model, the sync layer, and the form stay in agreement.
 */

if (!function_exists('payment_type_uses_supplier')) {
	/**
	 * True for payment-out types whose payee is a supplier (stored as
	 * payment.SupplierID and synced to AutoCount as that supplier's creditor
	 * account). This is the set that shows a Supplier selector on the form.
	 *
	 * "AGENT COMMISSION FROM SUPPLIER" is a Payment Out (PV): we pay agent
	 * commission and book it against the chosen supplier's creditor account.
	 */
	function payment_type_uses_supplier($type)
	{
		return in_array($type, array(
			'SUPPLIER PAYMENT (DEPOSIT)',
			'SUPPLIER PAYMENT (FULL)',
			'SUPPLIER PAYMENT (ADDITIONAL)',
			'AGENT COMMISSION FROM SUPPLIER',
		), true);
	}
}

if (!function_exists('payment_received_credit_types')) {
	/**
	 * Payment types that settle a booking's NetTotal — i.e. the money-in rows
	 * that reduce Customer Outstanding and show up under "Received (RM)".
	 *
	 * "AGENT COMMISSION FROM SUPPLIER" is included: when it arrives as money in
	 * (Credit > 0) it settles this booking's sale, so it must count as received
	 * — same as the "In (RM)" / "Total Payment In" totals already do. Its
	 * money-out variant (Credit = 0) contributes nothing to the credit sum, so
	 * listing it here is harmless.
	 */
	function payment_received_credit_types()
	{
		return array(
			'ADDITIONAL PAYMENT',
			'DEPOSIT',
			'FULL',
			'CUSTOMER REFUND',
			'AGENT COMMISSION FROM SUPPLIER',
		);
	}
}

if (!function_exists('payment_row_field')) {
	/** Read a field from a payment row that may be an object or an array. */
	function payment_row_field($row, $field, $default = null)
	{
		if (is_array($row)) {
			return isset($row[$field]) ? $row[$field] : $default;
		}
		return isset($row->$field) ? $row->$field : $default;
	}
}

if (!function_exists('payment_normal_customer_credit_types')) {
	/**
	 * The customer-side money-in types (deposit / full / additional). Their
	 * presence is what tells a *normal* booking apart from a *commission-fee*
	 * booking whose sale is settled purely by supplier commission.
	 */
	function payment_normal_customer_credit_types()
	{
		return array('DEPOSIT', 'FULL', 'ADDITIONAL PAYMENT');
	}
}

if (!function_exists('payment_rows_have_customer_credit')) {
	/**
	 * True when the row set contains a real customer payment (deposit/full/
	 * additional with Credit > 0). Rows are assumed already approved unless they
	 * carry a Status field, in which case only Status='Y' rows count.
	 *
	 * This is the guard that keeps "AGENT COMMISSION FROM SUPPLIER" from being
	 * treated as customer settlement on a NORMAL booking: there the commission is
	 * extra income sitting on top of the customer's own payment, so it must not
	 * reduce their outstanding. Only when there is NO customer credit at all — a
	 * commission-fee booking like BC-2608-0141 — does the commission settle it.
	 */
	function payment_rows_have_customer_credit($rows)
	{
		$normal = payment_normal_customer_credit_types();
		foreach ($rows as $row) {
			$status = payment_row_field($row, 'Status', 'Y');
			$credit = (float) payment_row_field($row, 'Credit', 0);
			$type   = payment_row_field($row, 'Type', '');
			if ($status === 'Y' && $credit > 0 && in_array($type, $normal, true)) {
				return true;
			}
		}
		return false;
	}
}

if (!function_exists('payment_received_credit_total')) {
	/**
	 * Total money received against a booking from a set of approved payment rows
	 * (drives the Payment page "Received (RM)" / "Customer Outstanding").
	 *
	 * A CUSTOMER REFUND is a debit that gives money back, so it subtracts; the
	 * normal customer types add their Credit. A money-in AGENT COMMISSION FROM
	 * SUPPLIER only counts when the booking has no customer credit of its own
	 * (see payment_rows_have_customer_credit) — otherwise it is left out so a
	 * normal booking's outstanding is not understated.
	 */
	function payment_received_credit_total($rows)
	{
		$has_customer_credit = payment_rows_have_customer_credit($rows);
		$total = 0.0;
		foreach ($rows as $row) {
			$type   = payment_row_field($row, 'Type', '');
			$credit = (float) payment_row_field($row, 'Credit', 0);
			$debit  = (float) payment_row_field($row, 'Debit', 0);
			if ($type === 'CUSTOMER REFUND') {
				$total -= $debit;
			} elseif ($type === 'AGENT COMMISSION FROM SUPPLIER') {
				if (!$has_customer_credit) {
					$total += $credit;
				}
			} else {
				$total += $credit;
			}
		}
		return $total;
	}
}

if (!function_exists('payment_approved_customer_credit')) {
	/**
	 * Approved credit that settles a booking's NetTotal, for outstanding/PO and
	 * status logic. Sums Status='Y', Credit>0 rows, excluding SUPPLIER REFUND
	 * (a debit) and — on a normal booking — AGENT COMMISSION FROM SUPPLIER. When
	 * the booking has NO customer credit, the money-in commission settles it.
	 *
	 * PHP mirror of booking_settled_credit_sql(); keep the two in lock-step.
	 */
	function payment_approved_customer_credit($rows)
	{
		$has_customer_credit = payment_rows_have_customer_credit($rows);
		$total = 0.0;
		foreach ($rows as $row) {
			$status = payment_row_field($row, 'Status', 'Y');
			$credit = (float) payment_row_field($row, 'Credit', 0);
			$type   = payment_row_field($row, 'Type', '');
			if ($status !== 'Y' || $credit <= 0 || $type === 'SUPPLIER REFUND') {
				continue;
			}
			if ($type === 'AGENT COMMISSION FROM SUPPLIER') {
				if (!$has_customer_credit) {
					$total += $credit;
				}
			} else {
				$total += $credit;
			}
		}
		return $total;
	}
}

if (!function_exists('booking_settled_credit_sql')) {
	/**
	 * SQL expression for a booking's settled credit (excludes commission on a
	 * normal booking, counts it only when there is no customer credit). Used in
	 * the "NetTotal - settled > 0" outstanding/PO checks. Assumes the outer query
	 * exposes booking.BookingID. SQL mirror of payment_approved_customer_credit().
	 */
	function booking_settled_credit_sql()
	{
		$customer = "COALESCE((SELECT SUM(p.Credit) FROM payment p"
			. " WHERE p.BookingID = booking.BookingID"
			. " AND p.Status = 'Y' AND p.Credit > 0"
			. " AND (p.Type IS NULL OR p.Type != 'AGENT COMMISSION FROM SUPPLIER')), 0)";
		$commission = "COALESCE((SELECT SUM(p.Credit) FROM payment p"
			. " WHERE p.BookingID = booking.BookingID"
			. " AND p.Status = 'Y' AND p.Credit > 0"
			. " AND p.Type = 'AGENT COMMISSION FROM SUPPLIER'), 0)";
		return "(" . $customer . " + CASE WHEN " . $customer . " = 0 THEN " . $commission . " ELSE 0 END)";
	}
}
