<?php
/**
 * Run with: php tests/helpers/SupplierInvoiceFileTypeTest.php
 *
 * Locks the attachment-type whitelist for supplier-invoice file uploads. The
 * booking form lets staff attach an invoice document to each supplier-invoice
 * row; the only acceptable payloads are PDFs / office docs / images. Scripts and
 * executables must be rejected, and double-extension tricks like
 * "invoice.pdf.php" must NOT slip through (only the FINAL extension counts).
 *
 * Bug shape it guards against: a future edit widening supplier_invoice_allowed_types()
 * to admit an executable extension, or the matcher checking a non-final
 * extension so a .php payload disguised as .pdf gets through.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
if (!defined('FCPATH')) {
    define('FCPATH', __DIR__ . '/');
}
require_once __DIR__ . '/../../application/helpers/supplier_invoice_helper.php';

$failures = 0;
function check($label, $cond) {
    global $failures;
    if ($cond) {
        echo "PASS  {$label}\n";
    } else {
        $failures++;
        echo "FAIL  {$label}\n";
    }
}

// --- accepted extensions (incl. mixed case) ---
$accept = [
    'invoice.pdf', 'INVOICE.PDF', 'Invoice.Pdf',
    'scan.jpg', 'scan.jpeg', 'scan.PNG',
    'note.doc', 'note.docx', 'sheet.xls', 'sheet.xlsx',
    'my invoice 2026.pdf',
];
foreach ($accept as $f) {
    check("allows {$f}", supplier_invoice_is_allowed_file($f) === true);
}

// --- rejected: scripts / executables / unknown / extension-less ---
$reject = [
    'shell.php', 'shell.phtml', 'shell.php5', 'app.exe', 'run.sh', 'lib.so',
    'page.html', 'page.htm', 'vector.svg', 'data.csv', 'archive.zip',
    'invoice.pdf.php',   // double extension — final ext is php
    'invoice.jpg.exe',   // double extension — final ext is exe
    'noextension',
    '',
    '.htaccess',         // dotfile -> pathinfo ext is "htaccess"
];
foreach ($reject as $f) {
    check("rejects " . ($f === '' ? '(empty)' : $f), supplier_invoice_is_allowed_file($f) === false);
}

// --- allowed_types stays document/image only (no script/exe tokens) ---
$types = explode('|', supplier_invoice_allowed_types());
$banned = ['php', 'phtml', 'exe', 'sh', 'html', 'htm', 'svg', 'js'];
foreach ($banned as $b) {
    check("allowed_types excludes {$b}", !in_array($b, $types, true));
}
check('allowed_types includes pdf', in_array('pdf', $types, true));

// --- mime mapping ---
check('pdf mime', supplier_invoice_file_mime('a.pdf') === 'application/pdf');
check('jpeg mime', supplier_invoice_file_mime('a.JPEG') === 'image/jpeg');
check('png mime', supplier_invoice_file_mime('a.png') === 'image/png');
check('unknown mime falls back', supplier_invoice_file_mime('a.bin') === 'application/octet-stream');

// --- relative dir is under the invoice subfolder ---
check('reldir is the supplier_invoice subfolder',
    supplier_invoice_upload_reldir() === 'assets/upload/supplier_invoice/');

echo "\n" . ($failures === 0 ? "OK" : "{$failures} FAILURE(S)") . "\n";
exit($failures === 0 ? 0 : 1);
