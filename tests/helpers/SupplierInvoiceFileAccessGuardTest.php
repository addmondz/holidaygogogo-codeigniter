<?php
/**
 * Run with: php tests/helpers/SupplierInvoiceFileAccessGuardTest.php
 *
 * Locks the security contract for supplier-invoice file attachments on the
 * booking form. Invoice documents are commercially sensitive (supplier cost /
 * pricing) and must NEVER be reachable by customers or by staff without the
 * 'AB' (All Booking) code — not even by guessing the file URL.
 *
 * The whole Booking controller already sits behind admin login (MY_Controller
 * redirects anyone without admin_id to Login), so customers cannot reach these
 * endpoints at all. On top of that:
 *
 *   - Upload_Supplier_Invoice_File()  must gate on the 'AB' access_control code,
 *     validate the extension via the supplier_invoice helper whitelist, and
 *     store into the deny-protected dir (supplier_invoice_ensure_upload_dir()).
 *   - Supplier_Invoice_File()         must gate on 'AB', look the row up, and
 *     stream the bytes with readfile() (never expose the raw asset URL).
 *
 * Bug shape it guards against: a future edit dropping the 'AB' gate from either
 * endpoint (re-opening sensitive files), serving from outside the invoice dir
 * without a containment check, or linking the file directly via base_url so the
 * deny-all .htaccess can be bypassed.
 */

$controller_path = __DIR__ . '/../../application/controllers/Booking.php';
if (!is_file($controller_path)) {
    echo "FAIL  cannot locate {$controller_path}\n";
    exit(1);
}
$source = file_get_contents($controller_path);

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

// Extract a function body by name (balanced-brace scan from its opening {).
function body_of($source, $name) {
    if (!preg_match('/function\s+' . preg_quote($name, '/') . '\s*\([^)]*\)\s*\{/', $source, $m, PREG_OFFSET_CAPTURE)) {
        return null;
    }
    $start = $m[0][1] + strlen($m[0][0]) - 1;
    $depth = 0;
    for ($i = $start, $n = strlen($source); $i < $n; $i++) {
        if ($source[$i] === '{') { $depth++; }
        elseif ($source[$i] === '}') { $depth--; if ($depth === 0) { return substr($source, $start, $i - $start + 1); } }
    }
    return null;
}

$upload   = body_of($source, 'Upload_Supplier_Invoice_File');
$download = body_of($source, 'Supplier_Invoice_File');

check('Upload_Supplier_Invoice_File() exists', $upload !== null);
check('Supplier_Invoice_File() exists',        $download !== null);

if ($upload !== null) {
    check('upload gates on AB access_control',
        strpos($upload, "'AB'") !== false && strpos($upload, 'access_control') !== false);
    check('upload validates extension via whitelist helper',
        strpos($upload, 'supplier_invoice_is_allowed_file') !== false);
    check('upload stores into the deny-protected dir',
        strpos($upload, 'supplier_invoice_ensure_upload_dir') !== false);
    check('upload uses encrypt_name',
        strpos($upload, "encrypt_name") !== false);
}

if ($download !== null) {
    check('download gates on AB access_control',
        strpos($download, "'AB'") !== false && strpos($download, 'access_control') !== false);
    check('download looks the invoice row up',
        strpos($download, 'Booking_Supplier_Invoice_Model') !== false
        || strpos($download, 'Get_By_Id') !== false);
    check('download streams via readfile (not a public URL)',
        strpos($download, 'readfile') !== false);
    check('download performs a path-containment check (realpath)',
        strpos($download, 'realpath') !== false);
}

echo "\n" . ($failures === 0 ? "OK" : "{$failures} FAILURE(S)") . "\n";
exit($failures === 0 ? 0 : 1);
