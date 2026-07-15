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

if (!function_exists('ghl_message_log_within_business_hours')) {
    /**
     * Whether a timestamp falls inside working hours: any day (everyday) within
     * 07:00:00-22:00:00 inclusive. Reply time is only counted while the office is
     * meant to be answering, so anything outside 7AM-10PM is "out of hours".
     * Unparseable input is treated as out of hours.
     *
     * @param int|string $timestamp Unix seconds or a parseable 'Y-m-d H:i:s'.
     * @return bool
     */
    function ghl_message_log_within_business_hours($timestamp)
    {
        $ts = is_int($timestamp) ? $timestamp : strtotime((string) $timestamp);
        if ($ts === false) {
            return false;
        }

        $secondsOfDay = (int) date('G', $ts) * 3600 + (int) date('i', $ts) * 60 + (int) date('s', $ts);

        return $secondsOfDay >= 25200 && $secondsOfDay <= 79200; // 07:00:00 .. 22:00:00
    }
}

if (!function_exists('lead_reply_activity_today_handling')) {
    /**
     * "Today Handling Lead" for the Lead Reply Activity dashboard: the leads an
     * owner is still working today, derived as Lead Responded minus Transfer Out
     * Lead. Clamped at zero so a stray rounding/edge case (transfer-out counts a
     * lead the responded metric's business-hours gate excluded) can never render
     * a negative, misleading count.
     *
     * @param int $responded   Lead Responded count (distinct leads replied to).
     * @param int $transferOut Transfer Out Lead count (reply-created leads).
     * @return int Non-negative leads still being handled.
     */
    function lead_reply_activity_today_handling($responded, $transferOut)
    {
        $handling = (int) $responded - (int) $transferOut;

        return $handling > 0 ? $handling : 0;
    }
}

if (!function_exists('ghl_message_log_reply_pair_seconds')) {
    /**
     * Seconds an agent took to answer a customer: the gap from an INBOUND message
     * to the OUTBOUND reply that follows it. Counted only when the pair stays
     * inside working hours -- both endpoints must be on the same day within
     * 07:00:00-22:00:00. A pair that leaves the window (overnight, before
     * 7AM or after 10PM) is EXCLUDED entirely, returning null, rather than clamped
     * to the in-window slice. A reply timestamped before the inbound, or an
     * unparseable timestamp, also yields null.
     *
     * @param string $inboundTs  Customer message timestamp ('Y-m-d H:i:s').
     * @param string $outboundTs Agent reply timestamp.
     * @return int|null Seconds taken, or null when the pair does not qualify.
     */
    function ghl_message_log_reply_pair_seconds($inboundTs, $outboundTs)
    {
        $inbound = strtotime((string) $inboundTs);
        $outbound = strtotime((string) $outboundTs);

        if ($inbound === false || $outbound === false) {
            return null;
        }

        if ($outbound < $inbound) {
            return null;
        }

        // Same calendar day keeps the span from crossing an overnight close; the
        // window check then guarantees both ends sit inside 7AM-10PM.
        if (date('Y-m-d', $inbound) !== date('Y-m-d', $outbound)) {
            return null;
        }

        if (!ghl_message_log_within_business_hours($inbound)
            || !ghl_message_log_within_business_hours($outbound)) {
            return null;
        }

        return $outbound - $inbound;
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
     * previous (older) message -- shown for EVERY row, regardless of direction.
     * Each displayed row's gap is measured against the row directly below it;
     * callers should pass one extra trailing row beyond $displayCount so even the
     * bottom visible row gets its predecessor. The returned array is sliced back
     * to $displayCount.
     *
     * This per-row column is a raw cadence read, intentionally simpler than the
     * "Avg time taken" summary (which counts only in-hours inbound->outbound
     * replies via ghl_message_log_average_reply_seconds()).
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

if (!function_exists('ghl_message_log_average_reply_seconds')) {
    /**
     * Mean agent reply time across a conversation-ordered message stream. Rows
     * must arrive grouped by conversation and ascending in time (the order the
     * model's query emits), so an inbound followed immediately by an outbound in
     * the same thread is a reply. Each such pair contributes its in-hours gap
     * (ghl_message_log_reply_pair_seconds(); pairs that leave working hours add
     * nothing); the result is SUM(gap) / COUNT(pairs). Returns null when no pair
     * qualifies.
     *
     * @param array  $rows    Rows of array('conversation_id' => string,
     *                        'direction' => string, $timeKey => string),
     *                        ordered by conversation then time ascending.
     * @param string $timeKey Timestamp field name.
     * @return float|null Average seconds, or null when nothing qualifies.
     */
    function ghl_message_log_average_reply_seconds(array $rows, $timeKey = 'ts')
    {
        $totalSeconds = 0;
        $pairs = 0;
        $prev = null;

        foreach ($rows as $row) {
            if ($prev !== null) {
                $prevDir = strtolower(trim((string) (isset($prev['direction']) ? $prev['direction'] : '')));
                $curDir = strtolower(trim((string) (isset($row['direction']) ? $row['direction'] : '')));
                $sameConversation = (string) (isset($prev['conversation_id']) ? $prev['conversation_id'] : '')
                    === (string) (isset($row['conversation_id']) ? $row['conversation_id'] : '');

                if ($sameConversation && $prevDir === 'inbound' && $curDir === 'outbound') {
                    $seconds = ghl_message_log_reply_pair_seconds(
                        isset($prev[$timeKey]) ? $prev[$timeKey] : '',
                        isset($row[$timeKey]) ? $row[$timeKey] : ''
                    );
                    if ($seconds !== null) {
                        $totalSeconds += $seconds;
                        $pairs++;
                    }
                }
            }

            $prev = $row;
        }

        return $pairs > 0 ? (float) $totalSeconds / $pairs : null;
    }
}

if (!function_exists('ghl_message_log_average_reply_seconds_by_group')) {
    /**
     * Per-agent variant of ghl_message_log_average_reply_seconds(): the same
     * inbound->outbound reply pairing and in-hours rule, but tallied separately
     * per group (e.g. the resolved agent). Rows must arrive ordered by group,
     * then conversation, then time ascending; a pair only counts when both rows
     * share the same group AND conversation, so an agent boundary or a
     * conversation boundary never produces a phantom pair. Powers the team-wide
     * "Best:" reply-time leaderboard on the dashboard, which must compute every
     * agent's figure with identical logic to the per-agent card.
     *
     * @param array  $rows     Rows of array($groupKey => string,
     *                         'conversation_id' => string, 'direction' => string,
     *                         $timeKey => string).
     * @param string $groupKey Field naming the group (default 'agent_id').
     * @param string $timeKey  Timestamp field name (default 'ts').
     * @return array Map of group => array('avg_seconds' => float|null,
     *               'lead_count' => int) where lead_count is the number of
     *               distinct conversations that contributed at least one pair.
     */
    function ghl_message_log_average_reply_seconds_by_group(array $rows, $groupKey = 'agent_id', $timeKey = 'ts')
    {
        $acc = array();
        $prev = null;

        foreach ($rows as $row) {
            if ($prev !== null) {
                $prevGroup = (string) (isset($prev[$groupKey]) ? $prev[$groupKey] : '');
                $curGroup = (string) (isset($row[$groupKey]) ? $row[$groupKey] : '');
                $sameGroup = $prevGroup === $curGroup;
                $sameConversation = (string) (isset($prev['conversation_id']) ? $prev['conversation_id'] : '')
                    === (string) (isset($row['conversation_id']) ? $row['conversation_id'] : '');
                $prevDir = strtolower(trim((string) (isset($prev['direction']) ? $prev['direction'] : '')));
                $curDir = strtolower(trim((string) (isset($row['direction']) ? $row['direction'] : '')));

                if ($sameGroup && $sameConversation && $prevDir === 'inbound' && $curDir === 'outbound') {
                    $seconds = ghl_message_log_reply_pair_seconds(
                        isset($prev[$timeKey]) ? $prev[$timeKey] : '',
                        isset($row[$timeKey]) ? $row[$timeKey] : ''
                    );
                    if ($seconds !== null) {
                        if (!isset($acc[$curGroup])) {
                            $acc[$curGroup] = array('total' => 0, 'pairs' => 0, 'convs' => array());
                        }
                        $acc[$curGroup]['total'] += $seconds;
                        $acc[$curGroup]['pairs']++;
                        $acc[$curGroup]['convs'][(string) (isset($row['conversation_id']) ? $row['conversation_id'] : '')] = true;
                    }
                }
            }

            $prev = $row;
        }

        $out = array();
        foreach ($acc as $group => $stat) {
            $out[$group] = array(
                'avg_seconds' => $stat['pairs'] > 0 ? (float) $stat['total'] / $stat['pairs'] : null,
                'lead_count'  => count($stat['convs']),
            );
        }

        return $out;
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

if (!function_exists('ghl_message_log_hourly_breakdown')) {
    /**
     * Normalise sparse "messages per hour" rows into a full 24-hour series so the
     * Lead Reply Hourly page always renders every hour 00:00..23:00, even the ones
     * with no traffic. Each input row is one hour bucket the SQL actually returned
     * (e.g. {hour_of_day: 9, inbound_count: 3, outbound_count: 5}); hours the query
     * skipped are filled with zeros. Returns the ordered series plus day totals.
     *
     * @param array  $rows        Raw rows, one per hour that had any message.
     * @param string $hourKey     Key holding the 0-23 hour value.
     * @param string $inboundKey  Key holding the inbound count for that hour.
     * @param string $outboundKey Key holding the outbound count for that hour.
     * @param string $leadsKey    Key holding the distinct-leads count for that hour.
     * @return array{hours: array, total_inbound: int, total_outbound: int, total: int, total_leads: int}
     */
    function ghl_message_log_hourly_breakdown(array $rows, $hourKey = 'hour_of_day', $inboundKey = 'inbound_count', $outboundKey = 'outbound_count', $leadsKey = 'leads_count')
    {
        $byHour = array();
        foreach ($rows as $row) {
            if (!isset($row[$hourKey])) {
                continue;
            }
            $hour = (int) $row[$hourKey];
            if ($hour < 0 || $hour > 23) {
                continue;
            }
            $inbound = isset($row[$inboundKey]) ? (int) $row[$inboundKey] : 0;
            $outbound = isset($row[$outboundKey]) ? (int) $row[$outboundKey] : 0;
            $leads = isset($row[$leadsKey]) ? (int) $row[$leadsKey] : 0;
            if (!isset($byHour[$hour])) {
                $byHour[$hour] = array('inbound' => 0, 'outbound' => 0, 'leads' => 0);
            }
            $byHour[$hour]['inbound'] += $inbound;
            $byHour[$hour]['outbound'] += $outbound;
            $byHour[$hour]['leads'] += $leads;
        }

        $hours = array();
        $totalInbound = 0;
        $totalOutbound = 0;
        $totalLeads = 0;
        for ($hour = 0; $hour <= 23; $hour++) {
            $inbound = isset($byHour[$hour]) ? $byHour[$hour]['inbound'] : 0;
            $outbound = isset($byHour[$hour]) ? $byHour[$hour]['outbound'] : 0;
            $leads = isset($byHour[$hour]) ? $byHour[$hour]['leads'] : 0;
            $totalInbound += $inbound;
            $totalOutbound += $outbound;
            $totalLeads += $leads;

            $suffix = $hour < 12 ? 'AM' : 'PM';
            $display = $hour % 12;
            if ($display === 0) {
                $display = 12;
            }

            $hours[] = array(
                'hour' => $hour,
                'label' => $display . ' ' . $suffix,
                'range_label' => sprintf('%02d:00 - %02d:59', $hour, $hour),
                'inbound' => $inbound,
                'outbound' => $outbound,
                'leads' => $leads,
                'total' => $inbound + $outbound,
            );
        }

        return array(
            'hours' => $hours,
            'total_inbound' => $totalInbound,
            'total_outbound' => $totalOutbound,
            'total_leads' => $totalLeads,
            'total' => $totalInbound + $totalOutbound,
        );
    }
}

if (!function_exists('ghl_message_log_normalize_hour')) {
    /**
     * Sanitise the `hour` filter carried by the Lead Reply Hourly drill-down link
     * into the Message Log. A valid value is a whole hour-of-day 0-23; anything
     * else -- blank, out of range, decimal, or non-numeric -- means "no hour
     * filter" and returns null so the log falls back to the whole day. Kept
     * strict (digits only) so nothing but an integer can ever reach the HOUR()
     * comparison.
     *
     * @param mixed $value Raw `hour` input.
     * @return int|null 0-23, or null when absent/invalid.
     */
    function ghl_message_log_normalize_hour($value)
    {
        if (is_int($value)) {
            return ($value >= 0 && $value <= 23) ? $value : null;
        }

        $value = trim((string) $value);
        if ($value === '' || !ctype_digit($value)) {
            return null;
        }

        $hour = (int) $value;

        return ($hour >= 0 && $hour <= 23) ? $hour : null;
    }
}

if (!function_exists('ghl_message_log_hour_label')) {
    /**
     * Render an hour-of-day as the same 12-hour label the Lead Reply Hourly table
     * uses ("12 AM", "1 PM", ...), for the Message Log's active-hour filter chip.
     * Returns '' for anything that is not a valid 0-23 hour.
     *
     * @param mixed $hour Hour value (int or numeric string).
     * @return string Label, or '' when the hour is invalid.
     */
    function ghl_message_log_hour_label($hour)
    {
        $hour = ghl_message_log_normalize_hour($hour);
        if ($hour === null) {
            return '';
        }

        $suffix = $hour < 12 ? 'AM' : 'PM';
        $display = $hour % 12;
        if ($display === 0) {
            $display = 12;
        }

        return $display . ' ' . $suffix;
    }
}
