<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pure helpers for the Costing package per-day itinerary. Each day carries a
 * plain-text title plus five rich-text (TinyMCE) HTML blocks — description,
 * meal plan, notes, special remark and terms & conditions — that render on the
 * customer Quotation PDF. Kept free of the CI super-object so the model can
 * reuse them and PHPUnit can drive them directly.
 */

if (!function_exists('costing_itinerary_html_fields')) {
    /**
     * The five rich-text columns on costing_itinerary_days, in display order.
     *
     * @return string[]
     */
    function costing_itinerary_html_fields()
    {
        return array('description', 'meal_plan', 'notes', 'special_remark', 'terms_and_conditions');
    }
}

if (!function_exists('costing_itinerary_html_is_blank')) {
    /**
     * True when TinyMCE HTML carries no visible content. Empty editors emit
     * markup like "<p></p>", "<p><br></p>" or "<p>&nbsp;</p>" — treating those
     * as blank stops phantom rows/fields from being stored. Any <img> counts as
     * content (an image-only block is meaningful).
     *
     * @param string|null $html
     * @return bool
     */
    function costing_itinerary_html_is_blank($html)
    {
        $html = (string) $html;
        if (stripos($html, '<img') !== false) {
            return false;
        }
        $text = html_entity_decode($html, ENT_QUOTES);
        // Normalise non-breaking spaces (raw and decoded) to plain spaces.
        $text = str_replace(array("\xc2\xa0", "\xa0"), ' ', $text);
        $text = strip_tags($text);
        return trim($text) === '';
    }
}

if (!function_exists('costing_itinerary_prepare_row')) {
    /**
     * Normalise one posted itinerary row into an insert-ready shape. Title is
     * trimmed plain text; each HTML field is trimmed and nulled when visually
     * blank. 'is_empty' is true only when the title AND all five HTML fields are
     * blank, so the caller can skip the row entirely. 'day_number' is 0 when not
     * supplied — the caller assigns a sequential fallback.
     *
     * @param array $row
     * @return array {day_number:int, title:?string, <html fields>:?string, is_empty:bool}
     */
    function costing_itinerary_prepare_row($row)
    {
        $row = (array) $row;

        $title = trim((string) (isset($row['title']) ? $row['title'] : ''));
        $out = array(
            'day_number' => isset($row['day_number']) && (int) $row['day_number'] > 0 ? (int) $row['day_number'] : 0,
            'title'      => $title !== '' ? $title : null,
        );

        $all_blank = ($title === '');
        foreach (costing_itinerary_html_fields() as $field) {
            $val = trim((string) (isset($row[$field]) ? $row[$field] : ''));
            if ($val !== '' && costing_itinerary_html_is_blank($val)) {
                $val = '';
            }
            $out[$field] = $val !== '' ? $val : null;
            if ($val !== '') {
                $all_blank = false;
            }
        }

        $out['is_empty'] = $all_blank;
        return $out;
    }
}
