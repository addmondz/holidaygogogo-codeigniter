<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Slow Conversion Helper
 *
 * Pure (DB-free, unit-testable) logic behind the booking form's "Slow
 * Conversion Reasons" card. The card lets a user tag WHY a booking was slow to
 * convert, but only surfaces for bookings that actually were slow:
 *
 *   - The booking must have CONVERTED (reached Pending Payment), so the
 *     saved-as-draft -> Pending Payment gap is a real, finished number.
 *   - That gap must exceed 24 hours.
 *
 * The gap itself is computed in response_time_helper.php
 * (calculate_submitted_to_payment_seconds); this helper only decides the
 * "slow?" verdict and turns the user's reason multi-select into the minimal
 * junction-table writes.
 */

if (!defined('SLOW_CONVERSION_THRESHOLD_SECONDS')) {
    // 24 hours. A booking is "slow" only when it took strictly longer than this.
    define('SLOW_CONVERSION_THRESHOLD_SECONDS', 86400);
}

if (!function_exists('is_slow_conversion')) {
    /**
     * Decide whether a booking's saved-as-draft -> Pending Payment gap counts
     * as a slow conversion (and so should show the reasons card).
     *
     * Returns false for a null/non-numeric/negative total - a booking that
     * hasn't converted yet has a null total and is never "slow", and negative
     * (clock skew) is treated defensively as not-slow. The threshold is strict:
     * exactly 24h is NOT slow.
     *
     * @param int|null $total_seconds     SAD -> P gap in seconds (null if not converted)
     * @param int      $threshold_seconds slow boundary, default 24h
     * @return bool
     */
    function is_slow_conversion($total_seconds, $threshold_seconds = SLOW_CONVERSION_THRESHOLD_SECONDS)
    {
        if ($total_seconds === null || !is_numeric($total_seconds)) {
            return false;
        }
        return (int) $total_seconds > (int) $threshold_seconds;
    }
}

if (!function_exists('diff_selected_reason_ids')) {
    /**
     * Compare the reason ids currently stored for a booking against the ids the
     * user just submitted, and return the minimal change set:
     *
     *   ['add' => [...ids to INSERT], 'remove' => [...ids to DELETE]]
     *
     * Both sides are normalised to a set of unique positive ints first, so
     * string posts ('5'), duplicates, blanks and zero/non-numeric values don't
     * produce spurious writes. Output arrays are re-indexed (array_values) so
     * they compare cleanly and batch cleanly.
     *
     * @param array $current   reason ids already stored for the booking
     * @param array $submitted reason ids from the multi-select POST
     * @return array{add:int[], remove:int[]}
     */
    function diff_selected_reason_ids(array $current, array $submitted)
    {
        $normalise = function (array $ids) {
            $out = array();
            foreach ($ids as $id) {
                if (!is_numeric($id)) {
                    continue;
                }
                $id = (int) $id;
                if ($id > 0) {
                    $out[$id] = true; // key dedupes
                }
            }
            return array_keys($out);
        };

        $cur = $normalise($current);
        $sub = $normalise($submitted);

        return array(
            'add'    => array_values(array_diff($sub, $cur)),
            'remove' => array_values(array_diff($cur, $sub)),
        );
    }
}
