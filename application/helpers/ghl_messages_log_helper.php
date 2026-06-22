<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pure pagination math for the GHL Message Log. The log streams raw messages
 * straight from `ghl_messages` (newest first) with a hard LIMIT/OFFSET so the
 * page never loads more than one screen of rows -- this helper resolves the
 * requested page into a safe window. Unit-tested in
 * tests/helpers/GhlMessagesLogPaginationTest.php.
 */

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
