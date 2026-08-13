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

if (!function_exists('costing_multiplier_types')) {
    /**
     * How a cost line scales, code => human label. Stored on the item master and
     * copied onto each snapshot cost row. "Per Day" multiplies by the package
     * duration_days, "Per Pax" by the booking total_pax, "Fixed" by 1.
     *
     * @return array<string,string>
     */
    function costing_multiplier_types()
    {
        return [
            'per_day' => 'Per Day',
            'per_pax' => 'Per Pax',
            'fixed'   => 'Fixed',
        ];
    }
}

if (!function_exists('costing_normalize_multiplier_type')) {
    /**
     * Clamp a multiplier type to a known code, defaulting to 'fixed'.
     *
     * @param mixed $type
     * @return string one of per_day|per_pax|fixed
     */
    function costing_normalize_multiplier_type($type)
    {
        $type = strtolower(trim((string) $type));
        return array_key_exists($type, costing_multiplier_types()) ? $type : 'fixed';
    }
}

if (!function_exists('costing_multiplier_count')) {
    /**
     * The count a line is multiplied by, from its multiplier type. Per-day uses
     * the package duration, per-pax uses the booking pax, fixed is always 1.
     * Never below 0.
     *
     * @param string $multiplier_type
     * @param int    $duration_days
     * @param int    $total_pax
     * @return int
     */
    function costing_multiplier_count($multiplier_type, $duration_days, $total_pax)
    {
        switch (costing_normalize_multiplier_type($multiplier_type)) {
            case 'per_day':
                return max(0, (int) $duration_days);
            case 'per_pax':
                return max(0, (int) $total_pax);
            case 'fixed':
            default:
                return 1;
        }
    }
}

if (!function_exists('costing_row_myr')) {
    /**
     * Convert one cost line the way the cost template shows it: the foreign Cost
     * is converted to MYR and the per-unit bank charge is baked in ("MYR (convert)
     * — already include bank charges"), then multiplied by the count (No of Day /
     * No of pax) to give the line Total in MYR.
     *
     *   myr_per_unit = round(cost_foreign * rate, 2) + bank_charges_myr
     *   total_myr    = round(myr_per_unit * count, 2)
     *
     * @param float $cost_foreign     unit cost in the row's currency
     * @param float $rate             rate_to_myr for that currency (>=0)
     * @param float $bank_charges_myr per-unit bank charge already in MYR (>=0)
     * @param float $count            No of Day / No of pax / 1
     * @return array ['myr_per_unit','total_myr'] (floats, 2dp)
     */
    function costing_row_myr($cost_foreign, $rate, $bank_charges_myr, $count)
    {
        $rate = (float) $rate;
        if ($rate < 0) {
            $rate = 0.0;
        }
        $bank = max(0.0, (float) $bank_charges_myr);
        $count = max(0.0, (float) $count);

        $myr_per_unit = round((float) $cost_foreign * $rate, 2) + $bank;
        $total_myr    = round($myr_per_unit * $count, 2);

        return [
            'myr_per_unit' => round($myr_per_unit, 2),
            'total_myr'    => $total_myr,
        ];
    }
}

if (!function_exists('costing_row_totals')) {
    /**
     * Resolve a cost row's MYR figures, preferring a FROZEN per-unit value saved
     * with the row over recomputing from the (master/live) rate.
     *
     * The "MYR (convert)" column already bakes in the bank charge; once a row is
     * saved we persist that per-unit figure in its own column so it never drifts
     * when the exchange-rate master or bank charge changes later. When a frozen
     * value is present ($stored_myr_per_unit not null/''), it wins and only the
     * line total is derived from it. When absent (legacy pre-freeze rows), fall
     * back to converting from the rate + bank charge like costing_row_myr().
     *
     * @param float|null $stored_myr_per_unit frozen per-unit MYR, or null/'' to compute
     * @param float $cost_foreign
     * @param float $rate
     * @param float $bank_charges_myr
     * @param float $count
     * @return array ['myr_per_unit','total_myr'] (floats, 2dp)
     */
    function costing_row_totals($stored_myr_per_unit, $cost_foreign, $rate, $bank_charges_myr, $count)
    {
        if ($stored_myr_per_unit !== null && $stored_myr_per_unit !== '') {
            $per_unit = round(max(0.0, (float) $stored_myr_per_unit), 2);
            return [
                'myr_per_unit' => $per_unit,
                'total_myr'    => round($per_unit * max(0.0, (float) $count), 2),
            ];
        }

        return costing_row_myr($cost_foreign, $rate, $bank_charges_myr, $count);
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
