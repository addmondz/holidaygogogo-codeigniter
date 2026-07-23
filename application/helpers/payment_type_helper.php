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
