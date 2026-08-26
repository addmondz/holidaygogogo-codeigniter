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

if (!function_exists('payment_received_credit_total')) {
	/**
	 * Total money received against a booking from a set of approved payment rows.
	 * A CUSTOMER REFUND is a debit that gives money back, so it subtracts; every
	 * other type adds its Credit. Rows may be objects or arrays.
	 */
	function payment_received_credit_total($rows)
	{
		$total = 0.0;
		foreach ($rows as $row) {
			$type   = is_array($row) ? (isset($row['Type']) ? $row['Type'] : '') : (isset($row->Type) ? $row->Type : '');
			$credit = is_array($row) ? (isset($row['Credit']) ? $row['Credit'] : 0) : (isset($row->Credit) ? $row->Credit : 0);
			$debit  = is_array($row) ? (isset($row['Debit']) ? $row['Debit'] : 0) : (isset($row->Debit) ? $row->Debit : 0);
			if ($type === 'CUSTOMER REFUND') {
				$total -= (float) $debit;
			} else {
				$total += (float) $credit;
			}
		}
		return $total;
	}
}
