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

/**
 * Relative (FCPATH-based) directory where supplier-invoice attachments live.
 * Files here are NEVER linked to directly — they are streamed only through the
 * auth-gated Booking::Supplier_Invoice_File() endpoint, and a deny-all
 * .htaccess (written by supplier_invoice_ensure_upload_dir()) blocks direct
 * HTTP fetches under Apache. The trailing slash is included.
 */
function supplier_invoice_upload_reldir()
{
    return 'assets/upload/supplier_invoice/';
}

/**
 * Absolute filesystem directory for supplier-invoice attachments.
 */
function supplier_invoice_upload_dir()
{
    return FCPATH . supplier_invoice_upload_reldir();
}

/**
 * CodeIgniter Upload library allowed_types for supplier-invoice attachments.
 * Documents and images only — deliberately excludes scripts/executables.
 */
function supplier_invoice_allowed_types()
{
    return 'pdf|jpg|jpeg|png|doc|docx|xls|xlsx';
}

/**
 * Case-insensitive final-extension whitelist check for an attachment filename.
 * Tests only the LAST extension so double-extension tricks such as
 * "invoice.pdf.php" are rejected (final ext "php" is not whitelisted). Empty /
 * extension-less names are rejected.
 */
function supplier_invoice_is_allowed_file($filename)
{
    $ext = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
    if ($ext === '') {
        return false;
    }
    return in_array($ext, explode('|', supplier_invoice_allowed_types()), true);
}

/**
 * Whether a posted supplier-invoice row carries the columns the DB requires for
 * an INSERT. `booking_supplier_invoice.SupplierID` and `InvoiceNumber` are both
 * NOT NULL, so a row missing either cannot be persisted.
 *
 * The booking form lets staff attach a file to a brand-new invoice row before
 * picking a supplier / typing an invoice number (and on a parked draft the
 * supplier + number inputs are locked entirely). Such a row would reach
 * Booking_Supplier_Invoice_Model::Create() with SupplierID = null and blow up
 * the whole booking save with a NOT NULL violation — surfacing to the user as
 * "Booking Record … Could Not Be Updated". Callers use this to drop incomplete
 * rows instead of attempting an invalid insert.
 *
 * Accepts either an array or an object row (the model decodes posted rows to
 * objects via json_decode(json_encode())).
 */
function supplier_invoice_row_is_complete($row)
{
    if (is_array($row)) {
        $supplier_id    = isset($row['SupplierID']) ? $row['SupplierID'] : null;
        $invoice_number = isset($row['InvoiceNumber']) ? $row['InvoiceNumber'] : '';
    } elseif (is_object($row)) {
        $supplier_id    = isset($row->SupplierID) ? $row->SupplierID : null;
        $invoice_number = isset($row->InvoiceNumber) ? $row->InvoiceNumber : '';
    } else {
        return false;
    }

    return (int) $supplier_id > 0 && trim((string) $invoice_number) !== '';
}

/**
 * Safely delete a supplier-invoice attachment given its stored relative
 * (FCPATH-based) path. Used to reclaim a file that was uploaded for a brand-new
 * invoice row which then could not be persisted (an incomplete row dropped by
 * Booking_Supplier_Invoice_Model::Create()), so the upload doesn't leak on disk.
 *
 * A realpath containment check guarantees only files that actually live inside
 * the supplier-invoice upload directory are removed — a tampered path such as
 * "../../application/config/config.php" resolves outside the dir and is refused.
 *
 * Returns true only when a file was unlinked; false for an empty path, a file
 * outside the upload dir, a missing file, or a failed unlink.
 */
function supplier_invoice_delete_orphan_file($rel_path)
{
    $rel_path = trim((string) $rel_path);
    if ($rel_path === '') {
        return false;
    }
    $full = realpath(FCPATH . $rel_path);
    $base = realpath(supplier_invoice_upload_dir());
    if ($full === false || $base === false) {
        return false;
    }
    // The resolved file must sit strictly inside the upload directory.
    $base_with_sep = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($full, $base_with_sep) !== 0) {
        return false;
    }
    if (!is_file($full)) {
        return false;
    }
    return @unlink($full);
}

/**
 * Map an attachment filename to a Content-Type for inline streaming. Falls back
 * to application/octet-stream for anything not in the whitelist.
 */
function supplier_invoice_file_mime($filename)
{
    $map = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
    $ext = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
    return isset($map[$ext]) ? $map[$ext] : 'application/octet-stream';
}

/**
 * Ensure the supplier-invoice upload directory exists and is shielded from
 * direct web access by a deny-all .htaccess (covers Apache 2.2 and 2.4).
 * Self-healing: the guard file is (re)written whenever it is missing, so the
 * security control can never silently disappear. Returns the directory path.
 */
function supplier_invoice_ensure_upload_dir()
{
    $dir = supplier_invoice_upload_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $htaccess = $dir . '.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents(
            $htaccess,
            "Order allow,deny\nDeny from all\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n"
        );
    }
    return $dir;
}
