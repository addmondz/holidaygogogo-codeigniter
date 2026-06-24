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

if (!function_exists('ghl_message_log_format_duration')) {
    /**
     * Render a whole-second duration as a compact, reader-friendly string:
     * '4s', '1m 5s', '1h 2m'. Stops at minute precision once past an hour so a
     * long gap reads cleanly rather than '1h 2m 5s'. Null or negative input (no
     * measurable gap) renders as '' so the cell shows a dash, not a fake zero.
     *
     * @param int|float|null $seconds
     * @return string
     */
    function ghl_message_log_format_duration($seconds)
    {
        if ($seconds === null || $seconds === '' || $seconds < 0) {
            return '';
        }

        $seconds = (int) round($seconds);

        if ($seconds < 60) {
            return $seconds . 's';
        }

        if ($seconds < 3600) {
            $minutes = intdiv($seconds, 60);
            $rest = $seconds % 60;
            return $rest > 0 ? $minutes . 'm ' . $rest . 's' : $minutes . 'm';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        return $minutes > 0 ? $hours . 'h ' . $minutes . 'm' : $hours . 'h';
    }
}

if (!function_exists('ghl_message_log_consecutive_gap_seconds')) {
    /**
     * Seconds between a message and the one immediately before it, but only when
     * both fall on the SAME calendar day -- an overnight jump is dead air, not a
     * reply, so it returns null and the column shows a blank instead of an
     * misleading multi-hour value. Unparseable timestamps also yield null.
     *
     * @param string $newerTs The later message's timestamp ('Y-m-d H:i:s').
     * @param string $olderTs The earlier (previous) message's timestamp.
     * @return int|null Seconds elapsed, or null when not comparable.
     */
    function ghl_message_log_consecutive_gap_seconds($newerTs, $olderTs)
    {
        $newer = strtotime((string) $newerTs);
        $older = strtotime((string) $olderTs);

        if ($newer === false || $older === false) {
            return null;
        }

        if (date('Y-m-d', $newer) !== date('Y-m-d', $older)) {
            return null;
        }

        $gap = $newer - $older;

        return $gap >= 0 ? $gap : null;
    }
}

if (!function_exists('ghl_message_log_attach_reply_gaps')) {
    /**
     * Annotate a newest-first page of messages with the time taken since the
     * previous (older) message. Each displayed row's gap is measured against the
     * row directly below it; callers should pass one extra trailing row beyond
     * $displayCount so even the bottom visible row gets its predecessor. The
     * returned array is sliced back to $displayCount.
     *
     * Adds two keys per row: 'reply_gap_seconds' (int|null) and
     * 'reply_gap_label' (formatted string, '' when not comparable).
     *
     * @param array  $rows         Newest-first rows, each with $timeKey set.
     * @param int    $displayCount Rows actually shown.
     * @param string $timeKey      Timestamp field name.
     * @return array Sliced, gap-annotated rows.
     */
    function ghl_message_log_attach_reply_gaps(array $rows, $displayCount, $timeKey = 'message_timestamp')
    {
        $count = count($rows);
        $shown = min((int) $displayCount, $count);

        for ($i = 0; $i < $shown; $i++) {
            $gap = null;
            if ($i + 1 < $count) {
                $gap = ghl_message_log_consecutive_gap_seconds(
                    isset($rows[$i][$timeKey]) ? $rows[$i][$timeKey] : '',
                    isset($rows[$i + 1][$timeKey]) ? $rows[$i + 1][$timeKey] : ''
                );
            }

            $rows[$i]['reply_gap_seconds'] = $gap;
            $rows[$i]['reply_gap_label'] = ghl_message_log_format_duration($gap);
        }

        return array_slice($rows, 0, $displayCount);
    }
}

if (!function_exists('ghl_message_log_average_gap_seconds')) {
    /**
     * Mean consecutive same-day gap across one or more days, from per-day
     * aggregates. Within a day the consecutive gaps telescope to (max - min) over
     * (count - 1) intervals, so the daily-aggregated average is simply
     * SUM(max - min) / SUM(count - 1) across every day with at least two
     * messages. Days with a single message contribute no interval and are
     * skipped. Returns null when there is no interval to average.
     *
     * @param array $dayRows Rows of array('count' => int, 'min_ts' => string,
     *                       'max_ts' => string).
     * @return float|null Average seconds, or null when nothing comparable.
     */
    function ghl_message_log_average_gap_seconds(array $dayRows)
    {
        $totalSpan = 0;
        $totalIntervals = 0;

        foreach ($dayRows as $row) {
            $count = isset($row['count']) ? (int) $row['count'] : 0;
            if ($count < 2) {
                continue;
            }

            $min = strtotime((string) (isset($row['min_ts']) ? $row['min_ts'] : ''));
            $max = strtotime((string) (isset($row['max_ts']) ? $row['max_ts'] : ''));
            if ($min === false || $max === false || $max < $min) {
                continue;
            }

            $totalSpan += $max - $min;
            $totalIntervals += $count - 1;
        }

        return $totalIntervals > 0 ? (float) $totalSpan / $totalIntervals : null;
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
