<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pure tour-code generation for costing packages. No DB, no HTTP, so it stays
 * unit-testable (see tests/helpers/CostingTourCodeHelperTest.php).
 *
 * Format: qu-YYMM-XXXX
 *   qu   - fixed prefix (quotation)
 *   YYMM - 2-digit year + 2-digit month of creation
 *   XXXX - zero-padded running sequence, restarting each YYMM period
 *
 * e.g. the 3rd package created in Aug 2026 -> qu-2608-0003
 *
 * The model passes in the current YYMM period plus every existing tour_code for
 * that period; these functions only format/parse, so the date lookup and the DB
 * query stay outside the tested surface.
 */

if (!function_exists('costing_tour_code_prefix')) {
    /**
     * @return string the fixed tour-code prefix.
     */
    function costing_tour_code_prefix()
    {
        return 'qu';
    }
}

if (!function_exists('costing_tour_code_format')) {
    /**
     * Build a tour code from a YYMM period and a sequence number.
     *
     * @param string $period   4-char YYMM string (e.g. "2608").
     * @param int    $sequence 1-based running number within the period.
     * @return string qu-YYMM-XXXX
     */
    function costing_tour_code_format($period, $sequence)
    {
        return costing_tour_code_prefix()
            . '-' . $period
            . '-' . str_pad((string) max(0, (int) $sequence), 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('costing_tour_code_sequence')) {
    /**
     * Extract the sequence number from a code belonging to the given period.
     * Returns 0 when the code does not match qu-<period>-<digits> (any other
     * period, blank, or a hand-typed code with a non-numeric tail).
     *
     * @param string $code
     * @param string $period 4-char YYMM string.
     * @return int
     */
    function costing_tour_code_sequence($code, $period)
    {
        $prefix = costing_tour_code_prefix() . '-' . $period . '-';
        $code = trim((string) $code);
        if (stripos($code, $prefix) !== 0) {
            return 0;
        }

        $tail = substr($code, strlen($prefix));
        if ($tail === '' || !ctype_digit($tail)) {
            return 0;
        }

        return (int) $tail;
    }
}

if (!function_exists('costing_tour_code_next')) {
    /**
     * Next tour code for a period = highest existing sequence in that period + 1,
     * formatted. Ignores codes from other periods / hand-typed codes.
     *
     * @param array<int,string> $existing_codes all tour_codes to scan.
     * @param string            $period         4-char YYMM string.
     * @return string qu-YYMM-XXXX
     */
    function costing_tour_code_next($existing_codes, $period)
    {
        $max = 0;
        foreach ((array) $existing_codes as $code) {
            $seq = costing_tour_code_sequence($code, $period);
            if ($seq > $max) {
                $max = $seq;
            }
        }

        return costing_tour_code_format($period, $max + 1);
    }
}
