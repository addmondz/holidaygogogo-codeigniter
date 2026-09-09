<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pure helpers for the Costing Quotation hotel-pricing + flight-schedule tables
 * (the customer-facing Quotation PDF, screenshot layout from the 9 Sep 2026
 * feedback). Each HOTEL row is a name + twin/triple price + single supplement;
 * each FLIGHT row is a travel date + sector + flight no + timing + duration. The
 * surrounding free-text (pricing basis, notes, expiry, flight fare price) applies
 * to the WHOLE quote and lives on costing_packages.
 *
 * Kept free of the CI super-object so the model can reuse them and PHPUnit can
 * drive them directly. All values are plain text (no rich HTML), so "blank" is
 * simply an empty trim.
 */

if (!function_exists('costing_quote_price_normalize')) {
    /**
     * Normalise a posted price into a float, or null when blank/non-numeric.
     * Accepts "1,999", "RM 1999", "1999.00", etc.
     *
     * @param mixed $value
     * @return float|null
     */
    function costing_quote_price_normalize($value)
    {
        if (is_array($value)) {
            return null;
        }
        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);
        if ($clean === '' || $clean === '-' || $clean === '.') {
            return null;
        }
        if (!is_numeric($clean)) {
            return null;
        }
        return round((float) $clean, 2);
    }
}

if (!function_exists('costing_quote_hotel_prepare_row')) {
    /**
     * Normalise one posted hotel row into an insert-ready shape. 'is_empty' is
     * true only when the name AND both prices are blank, so the caller can skip
     * the row entirely.
     *
     * @param array $row
     * @return array {hotel_name:?string, twin_triple_price:?float, single_supp_price:?float, is_empty:bool}
     */
    function costing_quote_hotel_prepare_row($row)
    {
        $row = (array) $row;

        $name  = trim((string) (isset($row['hotel_name']) ? $row['hotel_name'] : ''));
        $twin  = costing_quote_price_normalize(isset($row['twin_triple_price']) ? $row['twin_triple_price'] : null);
        $single = costing_quote_price_normalize(isset($row['single_supp_price']) ? $row['single_supp_price'] : null);

        return array(
            'hotel_name'        => $name !== '' ? $name : null,
            'twin_triple_price' => $twin,
            'single_supp_price' => $single,
            'is_empty'          => ($name === '' && $twin === null && $single === null),
        );
    }
}

if (!function_exists('costing_quote_flight_prepare_row')) {
    /**
     * Normalise one posted flight row into an insert-ready shape. 'is_empty' is
     * true only when every field is blank.
     *
     * @param array $row
     * @return array {travel_date:?string, sector:?string, flight_no:?string, timing:?string, duration:?string, is_empty:bool}
     */
    function costing_quote_flight_prepare_row($row)
    {
        $row = (array) $row;
        $fields = array('travel_date', 'sector', 'flight_no', 'timing', 'duration');

        $out = array();
        $all_blank = true;
        foreach ($fields as $field) {
            $val = trim((string) (isset($row[$field]) ? $row[$field] : ''));
            $out[$field] = $val !== '' ? $val : null;
            if ($val !== '') {
                $all_blank = false;
            }
        }
        $out['is_empty'] = $all_blank;
        return $out;
    }
}

if (!function_exists('costing_quote_level_fields')) {
    /**
     * The quote-level free-text columns on costing_packages, in a stable order.
     * quote_flight_price is a decimal; the rest are text.
     *
     * @return string[]
     */
    function costing_quote_level_fields()
    {
        return array(
            'quote_pricing_basis',
            'quote_travel_date_note',
            'quote_hotel_note',
            'quote_flight_title',
            'quote_flight_price',
            'quote_flight_fare_note',
            'quote_flight_expiry',
            'quote_footer_notes',
        );
    }
}

if (!function_exists('costing_quote_prepare_level')) {
    /**
     * Normalise the posted quote-level fields into a package-update shape. Text
     * fields are trimmed and nulled when blank; quote_flight_price is normalised
     * to a float (or null). Only known columns are returned, so arbitrary posted
     * keys can never reach the update.
     *
     * @param array $post
     * @return array
     */
    function costing_quote_prepare_level($post)
    {
        $post = (array) $post;
        $out  = array();
        foreach (costing_quote_level_fields() as $column) {
            if ($column === 'quote_flight_price') {
                $out[$column] = costing_quote_price_normalize(isset($post[$column]) ? $post[$column] : null);
                continue;
            }
            $val = trim((string) (isset($post[$column]) ? $post[$column] : ''));
            $out[$column] = $val !== '' ? $val : null;
        }
        return $out;
    }
}

if (!function_exists('costing_quote_default_footer_notes')) {
    /**
     * The default boilerplate footer notes shown (highlighted) at the bottom of
     * the quote. Seeded into the editor when the package has none saved yet, and
     * used verbatim on the PDF when the package field is blank. One note per line.
     *
     * @return string
     */
    function costing_quote_default_footer_notes()
    {
        return implode("\n", array(
            'Note: Pricing quoted as Group Rate. Please verify the fare breakdown before you accept the fare quote. Seats are limited and subject to availability.',
            '*Fares available on a first-come-first serve basis or it will expire. Seats are not guaranteed until booked. Taxes are subject to change.',
            '*All flight schedules are correct at the time of publication and dissemination; however, these are subject to change without prior notice.',
            '*Each flight sector quote reduction or increase for the number of passengers will affect the air fare & need to re-quote accordingly.',
            '*Seat subject to availability & fare subject to change without prior notice; NO seat HOLD on this stage.',
        ));
    }
}

if (!function_exists('costing_quote_footer_note_lines')) {
    /**
     * Split a stored footer-notes blob into trimmed, non-empty lines for display.
     * Falls back to the default boilerplate when nothing is saved.
     *
     * @param string|null $stored
     * @return string[]
     */
    function costing_quote_footer_note_lines($stored)
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            $stored = costing_quote_default_footer_notes();
        }
        $lines = preg_split('/\r\n|\r|\n/', $stored);
        $out = array();
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $out[] = $line;
            }
        }
        return $out;
    }
}
