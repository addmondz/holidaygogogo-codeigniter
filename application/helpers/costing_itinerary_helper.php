<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pure helpers for the Costing package itinerary.
 *
 * Each DAY carries a plain-text title, one rich-text (TinyMCE) HTML block
 * (description) and a multi-select Meal Plan (stored as comma-separated slugs).
 * Notes, Special Remark and Terms & Conditions apply to the WHOLE itinerary (one
 * set per package), not per day, so they live on costing_packages and are
 * handled by the itinerary-level helpers below.
 *
 * Kept free of the CI super-object so the model can reuse them and PHPUnit can
 * drive them directly.
 */

if (!function_exists('costing_itinerary_html_fields')) {
    /**
     * The per-day rich-text columns on costing_itinerary_days, in display order.
     * (Meal plan is a multi-select, handled separately.)
     *
     * @return string[]
     */
    function costing_itinerary_html_fields()
    {
        return array('description');
    }
}

if (!function_exists('costing_meal_plan_options')) {
    /**
     * The selectable Meal Plan options, slug => label, in display order. A day
     * can have several (breakfast + lunch + dinner), so these render as
     * checkboxes and store as a comma-separated slug list.
     *
     * @return array<string,string>
     */
    function costing_meal_plan_options()
    {
        return array(
            'breakfast'           => 'Breakfast',
            'lunch'               => 'Lunch',
            'dinner'              => 'Dinner',
            'tea_break'           => 'Tea Break',
            'supper'              => 'Supper',
            'special_arrangement' => 'Special Arrangement',
        );
    }
}

if (!function_exists('costing_meal_plan_normalize')) {
    /**
     * Normalise a posted Meal Plan selection into a stored slug string. Accepts
     * the checkbox array (['breakfast','dinner']) or an existing comma-separated
     * string. Keeps only valid slugs, de-duplicated, in canonical option order.
     * Returns null when nothing valid is selected.
     *
     * @param array|string|null $selected
     * @return string|null
     */
    function costing_meal_plan_normalize($selected)
    {
        if (is_string($selected)) {
            $selected = explode(',', $selected);
        }
        $picked = array();
        foreach ((array) $selected as $slug) {
            $picked[trim((string) $slug)] = true;
        }

        $out = array();
        foreach (array_keys(costing_meal_plan_options()) as $slug) {
            if (isset($picked[$slug])) {
                $out[] = $slug;
            }
        }

        return empty($out) ? null : implode(',', $out);
    }
}

if (!function_exists('costing_meal_plan_labels')) {
    /**
     * Human-readable Meal Plan labels for display, from a stored slug string.
     * Falls back to the stripped raw text for legacy (pre-select) HTML values so
     * nothing already saved disappears.
     *
     * @param string|null $stored
     * @return string[]
     */
    function costing_meal_plan_labels($stored)
    {
        $options = costing_meal_plan_options();
        $slugs = array_map('trim', explode(',', (string) $stored));

        $labels = array();
        foreach (array_keys($options) as $slug) {
            if (in_array($slug, $slugs, true)) {
                $labels[] = $options[$slug];
            }
        }

        if (empty($labels)) {
            $legacy = trim(strip_tags((string) $stored));
            if ($legacy !== '') {
                $labels[] = $legacy;
            }
        }

        return $labels;
    }
}

if (!function_exists('costing_itinerary_level_fields')) {
    /**
     * The itinerary-level rich-text columns on costing_packages, in display
     * order. Shared once beneath the whole itinerary rather than per day.
     *
     * @return string[]
     */
    function costing_itinerary_level_fields()
    {
        return array('itinerary_notes');
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
     * Normalise one posted itinerary day row into an insert-ready shape. Title is
     * trimmed plain text; each HTML field is trimmed and nulled when visually
     * blank; meal_plan is a multi-select normalised to a slug list. 'is_empty' is
     * true only when the title AND description AND meal plan are all blank, so the
     * caller can skip the row entirely. 'day_number' is 0 when not supplied — the
     * caller assigns a sequential fallback.
     *
     * @param array $row
     * @return array {day_number:int, title:?string, description:?string, meal_plan:?string, is_empty:bool}
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

        $out['meal_plan'] = costing_meal_plan_normalize(isset($row['meal_plan']) ? $row['meal_plan'] : null);
        if ($out['meal_plan'] !== null) {
            $all_blank = false;
        }

        $out['is_empty'] = $all_blank;
        return $out;
    }
}

if (!function_exists('costing_itinerary_prepare_level')) {
    /**
     * Normalise the posted itinerary-level field into a package-update shape. The
     * Include / Exclude / Important Notes / Terms & Conditions are now kept in one
     * merged rich-text Notes field. Trimmed and nulled when visually blank. Post
     * key is the bare name (notes); the returned key is the costing_packages column
     * (itinerary_notes).
     *
     * @param array $post
     * @return array {itinerary_notes:?string}
     */
    function costing_itinerary_prepare_level($post)
    {
        $post = (array) $post;
        // column => posted field name
        $map = array(
            'itinerary_notes' => 'notes',
        );

        $out = array();
        foreach ($map as $column => $key) {
            $val = trim((string) (isset($post[$key]) ? $post[$key] : ''));
            if ($val !== '' && costing_itinerary_html_is_blank($val)) {
                $val = '';
            }
            $out[$column] = $val !== '' ? $val : null;
        }

        return $out;
    }
}
