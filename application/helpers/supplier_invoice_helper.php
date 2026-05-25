<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Payment.Type values that represent money paid OUT to a supplier and therefore
 * reduce an outstanding supplier invoice balance. Mirrors the same list used by
 * Payment_Model::Read_Supplier_Payments_Breakdown().
 */
function supplier_invoice_supplier_payment_types()
{
    return [
        'SUPPLIER PAYMENT (DEPOSIT)',
        'SUPPLIER PAYMENT (FULL)',
        'SUPPLIER PAYMENT (ADDITIONAL)',
    ];
}

/**
 * Correlated subquery that returns the total Debit (RM) paid against the
 * booking_supplier_invoice row aliased as `bsi` in the outer query.
 *
 * Match key is (SupplierID, InvoiceNumber). Only approved (Status='Y') payments
 * of supplier-payment types count, so pending / cancelled payments don't make
 * an invoice look paid prematurely.
 */
function supplier_invoice_paid_subquery_sql()
{
    $types = "'" . implode("','", supplier_invoice_supplier_payment_types()) . "'";
    return "SELECT COALESCE(SUM(p.Debit), 0)
            FROM payment p
            WHERE p.SupplierID    = bsi.SupplierID
              AND p.InvoiceNumber = bsi.InvoiceNumber
              AND p.Status        = 'Y'
              AND p.Type IN ({$types})";
}
