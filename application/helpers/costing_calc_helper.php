<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pure costing/quotation math. No DB, no HTTP, no date functions, so it stays
 * unit-testable on plain PHP + SQLite (see tests/helpers/CostingCalcHelperTest.php).
 *
 * A costing is built from cost rows priced in any currency. A frozen per-costing
 * rate snapshot (1 <CUR> = X MYR) converts every row to MYR. Margin is a
 * markup on cost (percentage only, no manual selling price):
 *
 *   selling = cost x (1 + margin%/100)
 *   profit  = cost x (margin%/100)
 */

if (!function_exists('costing_categories')) {
    /**
     * The five fixed cost categories, code => human label. Shared by the item
     * master, the package cost template, and the snapshot so there is one list.
     *
     * @return array<string,string>
     */
    function costing_categories()
    {
        return [
            'flight'        => 'Flight',
            'accommodation' => 'Accommodation',
            'other'         => 'Other',
            'tour_leader'   => 'Tour Leader',
            'miscellaneous' => 'Miscellaneous',
        ];
    }
}

if (!function_exists('costing_line_to_myr')) {
    /**
     * Convert one cost row to MYR using the frozen snapshot rate map.
     *
     * @param array $line     needs currency_code, unit_cost, quantity
     * @param array $rate_map currency_code (upper) => rate_to_myr
     * @return float MYR total (unit_cost * quantity * rate), 2dp. Missing rate -> 0.
     */
    function costing_line_to_myr($line, $rate_map)
    {
        $code = strtoupper(trim((string) (isset($line['currency_code']) ? $line['currency_code'] : '')));
        $unit = (float) (isset($line['unit_cost']) ? $line['unit_cost'] : 0);
        $qty  = isset($line['quantity']) ? (float) $line['quantity'] : 1.0;

        if ($code === '' || !isset($rate_map[$code])) {
            return 0.0;
        }

        $rate = (float) $rate_map[$code];
        if ($rate <= 0) {
            return 0.0;
        }

        return round($unit * $qty * $rate, 2);
    }
}

if (!function_exists('costing_sum_by_category')) {
    /**
     * Sum cost rows to MYR, grouped by the five categories, plus a grand total.
     *
     * @param array $lines
     * @param array $rate_map
     * @return array flight/accommodation/other/tour_leader/miscellaneous/total (floats)
     */
    function costing_sum_by_category($lines, $rate_map)
    {
        $out = [];
        foreach (array_keys(costing_categories()) as $cat) {
            $out[$cat] = 0.0;
        }
        $out['total'] = 0.0;

        if (!is_array($lines)) {
            return $out;
        }

        foreach ($lines as $line) {
            $cat = strtolower(trim((string) (isset($line['category']) ? $line['category'] : '')));
            if (!array_key_exists($cat, $out) || $cat === 'total') {
                $cat = 'miscellaneous';
            }
            $myr = costing_line_to_myr($line, $rate_map);
            $out[$cat]   = round($out[$cat] + $myr, 2);
            $out['total'] = round($out['total'] + $myr, 2);
        }

        return $out;
    }
}

if (!function_exists('costing_apply_markup')) {
    /**
     * Markup-on-cost. Margin is a percentage (20 => 20%), never negative.
     *
     * @param float $total_cost_myr
     * @param float $margin_percent
     * @return array cost, margin_percent, profit, selling (all MYR floats, 2dp)
     */
    function costing_apply_markup($total_cost_myr, $margin_percent)
    {
        $cost   = round((float) $total_cost_myr, 2);
        $margin = (float) $margin_percent;
        if ($margin < 0) {
            $margin = 0.0;
        }

        $profit  = round($cost * ($margin / 100), 2);
        $selling = round($cost * (1 + $margin / 100), 2);

        return [
            'cost'           => $cost,
            'margin_percent' => $margin,
            'profit'         => $profit,
            'selling'        => $selling,
        ];
    }
}

if (!function_exists('costing_build_snapshot_rows')) {
    /**
     * Build the distinct-currency snapshot skeleton from the cost rows, pre-filling
     * each rate from the daily feed. MYR is always forced to 1.0; a currency with
     * no feed value gets 0 (manual entry required). Remark starts blank.
     *
     * @param array $lines           cost rows, each with currency_code
     * @param array $currency_lookup currency_code (upper) => currency_id
     * @param array $prefill         currency_code (upper) => rate_to_myr from the feed
     * @return array list of ['currency_id','currency_code','rate_to_myr','remark']
     */
    function costing_build_snapshot_rows($lines, $currency_lookup, $prefill)
    {
        $lookup = [];
        foreach ((array) $currency_lookup as $code => $id) {
            $lookup[strtoupper(trim((string) $code))] = (int) $id;
        }
        $feed = [];
        foreach ((array) $prefill as $code => $rate) {
            $feed[strtoupper(trim((string) $code))] = (float) $rate;
        }

        $rows = [];
        $seen = [];
        foreach ((array) $lines as $line) {
            $code = strtoupper(trim((string) (isset($line['currency_code']) ? $line['currency_code'] : '')));
            if ($code === '' || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;

            $rate = isset($feed[$code]) ? (float) $feed[$code] : 0.0;
            $rows[] = [
                'currency_id'   => isset($lookup[$code]) ? (int) $lookup[$code] : 0,
                'currency_code' => $code,
                'rate_to_myr'   => costing_normalize_rate($code, $rate),
                'remark'        => '',
            ];
        }

        return $rows;
    }
}

if (!function_exists('costing_normalize_rate')) {
    /**
     * Normalise a user-entered / prefilled rate: MYR is always 1.0, non-positive
     * becomes 0.0, everything else is clamped to 8 decimal places.
     *
     * @param string $currency_code
     * @param mixed  $rate
     * @return float
     */
    function costing_normalize_rate($currency_code, $rate)
    {
        if (strtoupper(trim((string) $currency_code)) === 'MYR') {
            return 1.0;
        }
        $rate = (float) $rate;
        if ($rate <= 0) {
            return 0.0;
        }
        return round($rate, 8);
    }
}
