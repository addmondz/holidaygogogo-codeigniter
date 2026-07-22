<?php
/**
 * Run with: php tests/helpers/PaymentOutNotificationsDisabledTest.php
 *
 * Locks the "stop payment-out supplier notifications" change. The four bell
 * notification types — supplier_reminder_full, supplier_reminder_deposit,
 * payout_overdue_full, payout_overdue_deposit — are generated through three
 * cron paths (Cronjob::PaymentOutSupplierReminder, Cronjob::PaymentOutOverdue-
 * Notification, Cron::index) but all funnel through TWO Cronjob_Model insert
 * methods. We gate both behind the autoloaded feature flag
 * enable_payment_out_notifications (config/features.php), so flipping one line
 * stops every path at once.
 *
 * Behaviour can't be unit-run without bootstrapping CodeIgniter, so this is a
 * source-lock test (same idiom as the card↔drill-down parity tests): it asserts
 * the flag exists and is off, and that each insert method early-returns 0 under
 * the flag before doing any DB work.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

function assert_true($label, $cond) {
    if ($cond) { echo "  PASS  {$label}\n"; }
    else { echo "  FAIL  {$label}\n"; exit(1); }
}

// Extract a single function body from PHP source by brace-matching from the
// method signature, so guard assertions are scoped to the right method.
function method_body($src, $signature) {
    $start = strpos($src, $signature);
    if ($start === false) return '';
    $brace = strpos($src, '{', $start);
    if ($brace === false) return '';
    $depth = 0;
    for ($i = $brace; $i < strlen($src); $i++) {
        if ($src[$i] === '{') $depth++;
        elseif ($src[$i] === '}') {
            $depth--;
            if ($depth === 0) return substr($src, $brace, $i - $brace + 1);
        }
    }
    return substr($src, $brace);
}

$FLAG = 'enable_payment_out_notifications';

// 1. The feature flag is defined and defaults OFF.
$features = file_get_contents(__DIR__ . '/../../application/config/features.php');
assert_true("config/features.php defines {$FLAG}",
    strpos($features, "\$config['{$FLAG}']") !== false);
assert_true("{$FLAG} defaults to false",
    (bool) preg_match('/\$config\[\'' . preg_quote($FLAG, '/') . '\'\]\s*=\s*false\s*;/', $features));

// 2. Both Cronjob_Model insert methods gate on the flag and early-return 0.
$model = file_get_contents(__DIR__ . '/../../application/models/Cronjob_Model.php');

$reminder_body = method_body($model, 'function create_supplier_reminder_notifications');
assert_true('create_supplier_reminder_notifications() body found', $reminder_body !== '');
assert_true('reminder method checks the feature flag',
    strpos($reminder_body, "config->item('{$FLAG}')") !== false);
assert_true('reminder method early-returns 0 when disabled',
    (bool) preg_match('/if\s*\(\s*!\s*\$this->config->item\(\'' . preg_quote($FLAG, '/') . '\'\)\s*\)\s*\{\s*return\s+0\s*;/s', $reminder_body));

$overdue_body = method_body($model, 'function create_payout_overdue_notifications');
assert_true('create_payout_overdue_notifications() body found', $overdue_body !== '');
assert_true('overdue method checks the feature flag',
    strpos($overdue_body, "config->item('{$FLAG}')") !== false);
assert_true('overdue method early-returns 0 when disabled',
    (bool) preg_match('/if\s*\(\s*!\s*\$this->config->item\(\'' . preg_quote($FLAG, '/') . '\'\)\s*\)\s*\{\s*return\s+0\s*;/s', $overdue_body));

// 3. The guard sits BEFORE any DB work in each method (no insert/notification
//    DB calls precede the flag check).
function guard_is_first($body, $flag) {
    $guard_pos = strpos($body, "config->item('{$flag}')");
    $db_pos    = strpos($body, '$this->db');
    if ($guard_pos === false) return false;
    return ($db_pos === false) || ($guard_pos < $db_pos);
}
assert_true('reminder guard precedes any DB access', guard_is_first($reminder_body, $FLAG));
assert_true('overdue guard precedes any DB access', guard_is_first($overdue_body, $FLAG));

echo "\nAll assertions passed.\n";
