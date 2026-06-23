<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pure pagination math for the GHL Message Log. The log streams raw messages
 * straight from `ghl_messages` (newest first) with a hard LIMIT/OFFSET so the
 * page never loads more than one screen of rows -- this helper resolves the
 * requested page into a safe window. Unit-tested in
 * tests/helpers/GhlMessagesLogPaginationTest.php.
 */

if (!function_exists('ghl_message_log_normalize_contact')) {
    /**
     * Reduce a contact-number filter to digits only, so format differences
     * (spaces, dashes, '+', country-code prefixes) never block a match. The
     * Message Log query compares this against a digits-only version of both
     * from_number and to_number, surfacing the full two-way thread for a lead.
     *
     * @param string $input Raw user input.
     * @return string Digits only ('' when nothing usable was typed).
     */
    function ghl_message_log_normalize_contact($input)
    {
        return preg_replace('/\D+/', '', (string) $input);
    }
}

if (!function_exists('ghl_message_log_direction_label')) {
    /**
     * Normalize a raw `ghl_messages.direction` value into a reader-friendly word
     * ('Inbound' / 'Outbound'). Empty/null becomes '' so it renders as a blank
     * cell; any other value is title-cased rather than dropped, so unexpected
     * directions stay visible instead of silently vanishing.
     *
     * @param string|null $direction Raw direction value.
     * @return string
     */
    function ghl_message_log_direction_label($direction)
    {
        $normalized = strtolower(trim((string) $direction));

        if ($normalized === '') {
            return '';
        }

        return ucfirst($normalized);
    }
}

if (!function_exists('ghl_message_log_export_columns')) {
    /**
     * CSV header for the Message Log export. Leads with Contact -- the
     * chatroom/lead the row belongs to -- because the export groups rows by
     * contact so each thread can be analysed together; the remaining columns
     * follow the on-screen table order.
     *
     * @return array
     */
    function ghl_message_log_export_columns()
    {
        return array('Contact', 'Date / Time', 'Direction', 'Agent', 'From', 'To', 'Message');
    }
}

if (!function_exists('ghl_message_log_export_row')) {
    /**
     * Map a Ghl_Messages_Log() row to ordered CSV cells, coercing missing/null
     * values to '' so Excel shows blanks rather than "null".
     *
     * @param array $row
     * @return array
     */
    function ghl_message_log_export_row($row)
    {
        $cell = function ($key) use ($row) {
            return isset($row[$key]) && $row[$key] !== null ? (string) $row[$key] : '';
        };

        return array(
            $cell('contact_name'),
            $cell('message_timestamp'),
            ghl_message_log_direction_label($cell('direction')),
            $cell('agent'),
            $cell('from_number'),
            $cell('to_number'),
            $cell('body'),
        );
    }
}

if (!function_exists('ghl_message_log_export_filename')) {
    /**
     * Download filename encoding the exported date window.
     *
     * @param string $startDate 'Y-m-d'
     * @param string $endDate   'Y-m-d'
     * @return string
     */
    function ghl_message_log_export_filename($startDate, $endDate)
    {
        return 'ghl_message_log_' . $startDate . '_to_' . $endDate . '.csv';
    }
}

if (!function_exists('ghl_messages_log_pagination')) {
    /**
     * Resolve a requested page against the row count into a clamped, render-ready
     * window.
     *
     * @param int $totalRows Total matching rows (>= 0).
     * @param int $page      Requested 1-based page (anything out of range is clamped).
     * @param int $perPage   Rows per page (non-positive falls back to 50).
     * @return array array(
     *     'per_page'    => int,
     *     'total_rows'  => int,
     *     'total_pages' => int (>= 1),
     *     'page'        => int (clamped to 1..total_pages),
     *     'offset'      => int (>= 0),
     *     'from_row'    => int (1-based, 0 when no rows),
     *     'to_row'      => int (0 when no rows),
     *     'has_prev'    => bool,
     *     'has_next'    => bool,
     * )
     */
    function ghl_messages_log_pagination($totalRows, $page, $perPage)
    {
        $totalRows = max(0, (int) $totalRows);
        $perPage = (int) $perPage;
        if ($perPage <= 0) {
            $perPage = 50;
        }

        $totalPages = $totalRows > 0 ? (int) ceil($totalRows / $perPage) : 1;

        $page = (int) $page;
        if ($page < 1) {
            $page = 1;
        }
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $perPage;

        $fromRow = $totalRows > 0 ? $offset + 1 : 0;
        $toRow = $totalRows > 0 ? min($offset + $perPage, $totalRows) : 0;

        return array(
            'per_page'    => $perPage,
            'total_rows'  => $totalRows,
            'total_pages' => $totalPages,
            'page'        => $page,
            'offset'      => $offset,
            'from_row'    => $fromRow,
            'to_row'      => $toRow,
            'has_prev'    => $page > 1,
            'has_next'    => $page < $totalPages,
        );
    }
}
