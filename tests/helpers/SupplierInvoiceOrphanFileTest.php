<?php
/**
 * Run with: php tests/helpers/SupplierInvoiceOrphanFileTest.php
 *
 * Guards supplier_invoice_delete_orphan_file(): when a brand-new invoice row is
 * dropped on save (missing Supplier / Invoice #), the file already uploaded for
 * it must be reclaimed so it doesn't leak on disk — but ONLY files that really
 * live inside the supplier-invoice upload dir, never an arbitrary path supplied
 * by a tampered request.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
// FCPATH is the project root for the helper's path resolution. Point it at a
// throwaway sandbox so the test can create/remove real files safely.
$sandbox = sys_get_temp_dir() . '/si_orphan_' . getmypid() . '/';
if (!defined('FCPATH')) {
    define('FCPATH', $sandbox);
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

$reldir   = supplier_invoice_upload_reldir();           // assets/upload/supplier_invoice/
$uploaddir = supplier_invoice_upload_dir();             // FCPATH + reldir
@mkdir($uploaddir, 0777, true);

// --- happy path: a real upload inside the dir is deleted ---
$rel  = $reldir . 'orphan_invoice.pdf';
file_put_contents(FCPATH . $rel, 'dummy');
check('file inside upload dir exists before', is_file(FCPATH . $rel));
check('delete returns true for a contained file',
    supplier_invoice_delete_orphan_file($rel) === true);
check('file is gone after delete', !is_file(FCPATH . $rel));

// --- empty / missing inputs ---
check('empty path -> false', supplier_invoice_delete_orphan_file('') === false);
check('whitespace path -> false', supplier_invoice_delete_orphan_file('   ') === false);
check('non-existent file -> false',
    supplier_invoice_delete_orphan_file($reldir . 'nope.pdf') === false);

// --- containment: a traversal path to a file OUTSIDE the dir must NOT delete ---
$outside = FCPATH . 'secret.txt';
file_put_contents($outside, 'keep me');
$traversal = $reldir . '../../secret.txt';
check('traversal outside dir -> false',
    supplier_invoice_delete_orphan_file($traversal) === false);
check('outside file is preserved', is_file($outside));

// cleanup sandbox
@unlink($outside);
@rmdir($uploaddir);
@rmdir(dirname(rtrim($uploaddir, '/')));
@rmdir(dirname(dirname(rtrim($uploaddir, '/'))));
@rmdir(rtrim(FCPATH, '/'));

echo "\n" . ($failures === 0 ? "OK" : "{$failures} FAILURE(S)") . "\n";
exit($failures === 0 ? 0 : 1);
