<?php
/**
 * Run with: php tests/helpers/FerryPendingCardTest.php
 *
 * Locks the wiring of the OP "Pending Ferry Transfer (This & Next Month)"
 * summary card so its count and its drill-down listing resolve to the SAME
 * set of bookings, mirroring the Pending Insurance Checklist card.
 *
 * The card counts BCs that have an active line whose product carries a
 * "Book Ferry Transfer" package_checklist with no completion record yet
 * (disable_checklist_payment_out=0 — the modal/filter rule), RESTRICTED to
 * trips travelling this month or next. The travel window must use the SAME
 * range-overlap predicate as the generic ?travel_date filter so the card and
 * its ?checklist_filter=<ferry ids>&travel_date=<window> drill-down agree:
 *
 *   - count query: range-overlap of (StartDate, EndDate) vs [1st of month,
 *     last day of next month];
 *   - link carries BOTH checklist_filter (ferry ids) AND travel_date (window);
 *   - apply_checklist_filter() resolves checklist_filter generically, and the
 *     generic travel_date block applies the same overlap clause.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$root = __DIR__ . '/../..';

$paths = [
    'controller' => $root . '/application/controllers/Booking.php',
    'model'      => $root . '/application/models/Booking_Model.php',
    'view'       => $root . '/application/views/booking/_summary_cards.php',
];
foreach ($paths as $k => $p) {
    if (!is_file($p)) {
        echo "  FAIL  cannot locate {$k} at {$p}\n";
        exit(1);
    }
}

$controller = file_get_contents($paths['controller']);
$model      = file_get_contents($paths['model']);
$view       = file_get_contents($paths['view']);

$assertions = [];

// --- 1) Controller builds the card --------------------------------------
$assertions['controller defines $cards[\'ferry_pending\']'] =
    strpos($controller, "\$cards['ferry_pending']") !== false;

// Matches the named checklist.
$assertions['controller matches the "Book Ferry Transfer" checklist'] =
    (bool) preg_match("/->like\('name',\s*'Book Ferry Transfer'/", $controller);

// Reuses the same pending-checklist subquery shape as the insurance card.
$assertions['count query excludes lines with a completion record (NOT EXISTS)'] =
    strpos($controller, 'booking_checklist_completion bcc') !== false
    && stripos($controller, 'NOT EXISTS') !== false;
$assertions['count query honours disable_checklist_payment_out = 0'] =
    strpos($controller, 'bp.disable_checklist_payment_out = 0') !== false;

// Travel window: range-overlap on StartDate/EndDate (matches generic travel_date).
$assertions['count query applies a StartDate/EndDate range-overlap window'] =
    (bool) preg_match('/booking\.StartDate\s*<=\s*\?\s*AND\s*booking\.EndDate\s*>=\s*\?/', $controller)
    && (bool) preg_match('/booking\.StartDate\s*>=\s*\?\s*AND\s*booking\.StartDate\s*<=\s*\?/', $controller);

// Window = 1st of this month -> last day of next month.
$assertions['window starts at the 1st of this month'] =
    strpos($controller, '$ferry_window_start = $month_start') !== false;
$assertions['window ends on the last day of next month'] =
    (bool) preg_match("/\\\$ferry_window_end\s*=\s*date\('Y-m-t',\s*strtotime\('first day of next month'\)\)/", $controller);

// --- 2) Drill-down link carries BOTH filters so card == list ------------
if (preg_match("/\\\$cards\['ferry_pending'\]\s*=\s*array\((.*?)\);/s", $controller, $m)) {
    $link_block = $m[1];
    $assertions['link carries checklist_filter (ferry ids)'] =
        strpos($link_block, "'checklist_filter'") !== false
        && strpos($link_block, '$ferry_ids') !== false;
    $assertions['link carries the travel_date window'] =
        strpos($link_block, "'travel_date'") !== false
        && strpos($link_block, '$ferry_window_start') !== false
        && strpos($link_block, '$ferry_window_end') !== false;
} else {
    $assertions["\$cards['ferry_pending'] assignment is locatable"] = false;
}

// --- 3) Model resolves both filters generically -------------------------
$assertions['model has apply_checklist_filter() for checklist_filter'] =
    strpos($model, 'function apply_checklist_filter') !== false;
$assertions['model applies a generic travel_date range-overlap filter'] =
    strpos($model, "\$this->input->get('travel_date')") !== false
    && (bool) preg_match('/`StartDate`\s*<=.*`EndDate`\s*>=/s', $model);

// --- 4) View renders the card + JS wires it -----------------------------
$assertions['view has the ferry card link anchor'] =
    strpos($view, "id=\"sc-ferry-pending-link\"") !== false;
$assertions['view has the ferry count target'] =
    strpos($view, "id=\"sc-ferry-pending-count\"") !== false;
$assertions['view has the ferry popover icon'] =
    strpos($view, "id=\"pop-ferry-pending\"") !== false;
$assertions['view JS sets count + link from c.ferry_pending'] =
    strpos($view, "c.ferry_pending") !== false
    && strpos($view, "setText('sc-ferry-pending-count'") !== false
    && strpos($view, "setLink('sc-ferry-pending-link'") !== false;

// --- Report --------------------------------------------------------------
$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "{$failed} assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
