<?php
/**
 * Run with: php tests/helpers/PendingReviewSummaryCardTest.php
 *
 * Locks the contract that the "Travel Completed - Pending Review" card has
 * moved off the sales agent's dashboard and into the booking-listing summary
 * panel for TC users (level 20 / 50).
 *
 * Three pieces must agree:
 *   1) booking/_summary_cards.php renders the card inside the TC ($show_tc)
 *      branch, wired to id="sc-pending-review-count" and a link element
 *      id="sc-pending-review-link" that the AJAX response can fill in.
 *   2) controllers/Booking.php's ajax_summary_cards() populates
 *      $cards['pending_review'] with at least {count, link} for the TC branch,
 *      and the link points at the existing PR filter (?status=PR) so the user
 *      lands on the same set of bookings the count reflects.
 *   3) views/dashboard.php no longer contains the level-20 "Travel Completed -
 *      Pending Review" tile (it lives in the summary panel now). The TC LEAD /
 *      OWNER copy further down the dashboard is untouched.
 *
 * Also pins the PR status filter so the card-count query and the listing query
 * agree on what "pending review" means:
 *   PR == CancelStatus='N' AND AfterSalesService='PENDING' AND Status='Y'
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$root = __DIR__ . '/../..';

$paths = [
    'summary'     => $root . '/application/views/booking/_summary_cards.php',
    'controller'  => $root . '/application/controllers/Booking.php',
    'dashboard'   => $root . '/application/views/dashboard.php',
    'filter_help' => $root . '/application/helpers/booking_status_filter_helper.php',
];
foreach ($paths as $k => $p) {
    if (!is_file($p)) {
        echo "FAIL  cannot locate {$k} at {$p}\n";
        exit(1);
    }
}
$summary    = file_get_contents($paths['summary']);
$controller = file_get_contents($paths['controller']);
$dashboard  = file_get_contents($paths['dashboard']);
$filter     = file_get_contents($paths['filter_help']);

$assertions = [];

// --- 1) Summary panel (TC section) renders the new card ---------------------

// Locate the $show_tc branch so we can scope the markup checks to it. We do a
// permissive search: from the first `if($show_tc)` block to the next `<?php }`
// at the same level. This is a static check, not exact, but enough to catch
// regressions that put the markup outside the TC branch.
$tc_start = strpos($summary, 'if($show_tc)');
$assertions['_summary_cards.php has a $show_tc branch'] = ($tc_start !== false);

if ($tc_start !== false) {
    $tc_section_end = strpos($summary, 'TC LEAD', $tc_start);
    $tc_section = $tc_section_end !== false
        ? substr($summary, $tc_start, $tc_section_end - $tc_start)
        : substr($summary, $tc_start);

    $assertions['Pending Review card header lives inside the TC branch'] =
        strpos($tc_section, 'Travel Completed - Pending Review') !== false;
    $assertions['Pending Review count element id is sc-pending-review-count'] =
        strpos($tc_section, 'id="sc-pending-review-count"') !== false;
    $assertions['Pending Review link element id is sc-pending-review-link'] =
        strpos($tc_section, 'id="sc-pending-review-link"') !== false;
}

// JS handler must read c.pending_review and update both count + link, so the
// AJAX payload contract stays in lockstep with the markup.
$assertions['_summary_cards.php JS reads c.pending_review.count into the card'] =
    strpos($summary, "setText('sc-pending-review-count', c.pending_review.count)") !== false;
$assertions['_summary_cards.php JS reads c.pending_review.link into the card'] =
    (bool) preg_match("/setLink\(\s*'sc-pending-review-link'\s*,\s*c\.pending_review\.link\s*\)/", $summary);

// --- 2) Booking controller populates the card for TC ------------------------

// Bound the check to the ajax_summary_cards() function body so unrelated uses
// of "pending_review" in the file don't accidentally satisfy the assertion.
$assertions['ajax_summary_cards() is still defined on Booking controller'] =
    (bool) preg_match('/function\s+ajax_summary_cards\s*\(/', $controller, $fm, PREG_OFFSET_CAPTURE);

if (!empty($fm)) {
    // Walk balanced braces to find the function body.
    $fn_brace = strpos($controller, '{', $fm[0][1]);
    if ($fn_brace !== false) {
        $depth = 0;
        $end = null;
        for ($i = $fn_brace, $n = strlen($controller); $i < $n; $i++) {
            if ($controller[$i] === '{') {
                $depth++;
            } elseif ($controller[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }
        if ($end !== null) {
            $fn_body = substr($controller, $fn_brace, $end - $fn_brace + 1);

            $assertions['ajax_summary_cards() populates $cards[\'pending_review\']'] =
                strpos($fn_body, "\$cards['pending_review']") !== false;

            // The link must point at the PR filter on /Booking, so clicking the
            // card lands on exactly the rows that fed the count.
            $assertions['Pending Review card link uses ?status=PR'] =
                strpos($fn_body, "'status' => 'PR'") !== false;
        }
    }
}

// --- 3) Dashboard no longer renders the level-20 Pending Review tile --------

// Restrict to the sales-agent block (the `if($this->session->level == 20)` arm
// that opens near the top of the file). The TC LEAD copy that appears later
// in the file is intentionally left alone.
// The level==20 dashboard branch is structured as if/else inside PHP tags,
// so we scope to the first "} else {" after the opening if. The else branch
// renders the TC LEAD / OWNER tiles (a separate Pending Review card with its
// own $pending_reviews variable) which is intentionally untouched here.
$lvl20_start = strpos($dashboard, "session->level == 20");
$assertions['dashboard.php has a level==20 section'] = ($lvl20_start !== false);

if ($lvl20_start !== false) {
    $lvl20_end = strpos($dashboard, '<?php } else { ?>', $lvl20_start);
    $lvl20_section = $lvl20_end !== false
        ? substr($dashboard, $lvl20_start, $lvl20_end - $lvl20_start)
        : substr($dashboard, $lvl20_start);

    $assertions['dashboard.php level==20 section no longer renders Pending Review tile'] =
        strpos($lvl20_section, 'Travel Completed - Pending Review') === false;
    $assertions['dashboard.php level==20 section no longer references $sales_agent_pending_reviews'] =
        strpos($lvl20_section, 'sales_agent_pending_reviews') === false;
}

// --- 4) PR filter still matches the dashboard query semantics ---------------

// The card count and the listing land on the same rows only as long as the
// PR filter remains: CancelStatus='N' AND AfterSalesService='PENDING' AND
// Status='Y'. If anyone changes that triple, the card and the listing drift.
if (preg_match("/case\s+'PR'\s*:\s*return\s*\[(.*?)\];/s", $filter, $pm)) {
    $pr_arr = $pm[1];
    $assertions['PR filter still asserts CancelStatus N'] =
        strpos($pr_arr, "CancelStatus = 'N'") !== false;
    $assertions['PR filter still asserts AfterSalesService PENDING'] =
        strpos($pr_arr, "AfterSalesService = 'PENDING'") !== false;
    $assertions['PR filter still asserts booking.Status Y'] =
        strpos($pr_arr, "booking.Status = 'Y'") !== false;
} else {
    $assertions["PR case is present in booking_status_filter_helper.php"] = false;
}

// --- Report -----------------------------------------------------------------

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "{$failed} assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
