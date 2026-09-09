<?php
$p = $log_pagination;

$log_contact = isset($log_filters['contact']) ? $log_filters['contact'] : '';
// Agent is now a MULTI-select: the active filter is a list of names. Kept
// tolerant of a legacy single string so old drill-down links still light up.
$log_selected_agents = isset($log_filters['agent']) ? (array) $log_filters['agent'] : array();
$log_agents = isset($log_agents) ? $log_agents : array();

// Serialise the selected agents as repeated agent[] params so every filter-
// carrying link (sort headers, pagination, clear-hour, export) keeps the whole
// multi-agent selection. '%5B%5D' is the url-encoded '[]'.
$log_agent_param = '';
foreach ($log_selected_agents as $log_agent_name) {
    $log_agent_param .= '&agent%5B%5D=' . urlencode($log_agent_name);
}

// Direction filter: '', 'inbound', or 'outbound'. Narrows the log to one side of
// the conversation; travels with pagination, the sort headers, and the export.
$log_direction = isset($log_filters['direction']) && ($log_filters['direction'] === 'inbound' || $log_filters['direction'] === 'outbound')
    ? $log_filters['direction'] : '';
$log_direction_param = $log_direction !== '' ? '&direction=' . urlencode($log_direction) : '';
$log_show_reply_time = !empty($log_show_reply_time);
$log_avg_reply = isset($log_avg_reply) ? $log_avg_reply : '';
$log_table_colspan = $log_show_reply_time ? 7 : 6;

// Optional hour-of-day filter (0-23), carried from the Lead Reply Hourly
// "Leads Handled" drill-down. When set, the log shows just that hour of the day.
$log_hour = isset($log_filters['hour']) && $log_filters['hour'] !== null ? (int) $log_filters['hour'] : null;
$log_hour_label = isset($log_filters['hour_label']) ? (string) $log_filters['hour_label'] : '';
$log_hour_param = $log_hour === null ? '' : '&hour=' . $log_hour;

// Optional time-of-day range (clock filter). Both ends are canonical 'HH:MM'
// (or '' when unset); they narrow the log to that daily time window on top of
// the date range, and travel with pagination and the CSV export.
$log_time_from = isset($log_filters['time_from']) ? (string) $log_filters['time_from'] : '';
$log_time_to = isset($log_filters['time_to']) ? (string) $log_filters['time_to'] : '';
$log_time_param = ($log_time_from !== '' ? '&time_from=' . urlencode($log_time_from) : '')
    . ($log_time_to !== '' ? '&time_to=' . urlencode($log_time_to) : '');

// "All dates" mode: set when the reader clicks a contact in the log so the whole
// history of that contact is threaded regardless of the date window. Travels with
// pagination, the sort headers and the export; a fresh date pick drops it.
$log_all_dates = !empty($log_filters['all_dates']);
$log_all_dates_param = $log_all_dates ? '&all_dates=1' : '';

// Feed the picker unambiguous Y-m-d bounds so it opens on the correct month
// with today directly selectable (parsing the DD/MM/YYYY text field alone made
// the widget misread the month and refuse today until another date was picked).
// In all-dates mode there is no window, so open the picker on today.
$log_start_date = !empty($log_filters['start_date']) && !$log_all_dates ? $log_filters['start_date'] : date('Y-m-d');
$log_end_date = !empty($log_filters['end_date']) && !$log_all_dates ? $log_filters['end_date'] : date('Y-m-d');

// Sort state: which column ('date' default, or 'direction') and which way
// ('desc' default / 'asc'). Both carry through pagination and the header links.
$log_sort_col = isset($log_filters['sort']) && $log_filters['sort'] === 'direction' ? 'direction' : 'date';
$log_sort_dir = isset($log_filters['dir']) && $log_filters['dir'] === 'asc' ? 'asc' : 'desc';
$log_sort_param = '&sort=' . $log_sort_col . '&dir=' . $log_sort_dir;

/** Base query string shared by the sort-header links (every filter except sort). */
$log_filter_qs = 'log_date=' . urlencode($log_filters['log_date'])
    . '&contact=' . urlencode($log_contact)
    . $log_agent_param
    . $log_hour_param
    . $log_time_param
    . $log_direction_param
    . $log_all_dates_param;

/** Build a page URL keeping the current date, contact, agent, hour and sort filters. */
$page_url = function ($page) use ($log_filter_qs, $log_sort_param) {
    return base_url('Report/Ghl_Message_Log?' . $log_filter_qs . $log_sort_param . '&page=' . (int) $page);
};

// Each sortable header links to itself: clicking the active column flips its
// direction; clicking an inactive one opens it at its natural default (date =
// newest-first/desc, direction = Inbound-first/asc). Resets to page 1.
$sort_defaults = array('date' => 'desc', 'direction' => 'asc');
$sort_header_url = function ($col) use ($log_filter_qs, $log_sort_col, $log_sort_dir, $sort_defaults) {
    $next_dir = $col === $log_sort_col
        ? ($log_sort_dir === 'asc' ? 'desc' : 'asc')
        : $sort_defaults[$col];
    return base_url('Report/Ghl_Message_Log?' . $log_filter_qs . '&sort=' . $col . '&dir=' . $next_dir);
};
/** Caret shown only on the active sort column. */
$sort_caret = function ($col) use ($log_sort_col, $log_sort_dir) {
    if ($col !== $log_sort_col) {
        return '';
    }
    return $log_sort_dir === 'asc'
        ? ' <i class="la la-arrow-up ml-1"></i>'
        : ' <i class="la la-arrow-down ml-1"></i>';
};

/**
 * URL that threads the log to one contact -- across ALL dates. Clicking a contact
 * drops the date window (all_dates=1) so the reader sees that person's whole
 * conversation history in one go, not just the currently selected days.
 */
$contact_url = function ($number) {
    return base_url('Report/Ghl_Message_Log?all_dates=1&contact=' . urlencode($number));
};
?>
<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-4" style="background:linear-gradient(135deg, #d7e2f2 0%, #eef4fb 100%);">
                <div class="card-title">
                    <div>
                        <h3 class="card-label mb-1" style="color:#355c7d;">
                            <strong>Message Log</strong>
                        </h3>
                        <div class="text-muted font-size-sm">
                            Raw GHL messages &mdash; from, to, and date/time.
                        </div>
                    </div>
                </div>
                <div class="card-toolbar">
                    <?php if ($log_avg_reply !== '') { ?>
                        <span class="label label-light-info label-inline font-weight-bold mr-2">
                            Avg time taken: <?php echo html_escape($log_avg_reply); ?>
                            <i class="la la-info-circle ml-1" style="cursor:help;" data-toggle="tooltip"
                               title="Average time this agent takes to reply to a customer: from each incoming message to the agent's next reply in the same chat. Only replies where both the customer's message and the reply land on the same day between 7AM and 10PM are counted &mdash; overnight and after-hours gaps are skipped."></i>
                        </span>
                    <?php } ?>
                    <?php if ($log_hour !== null && $log_hour_label !== '') { ?>
                        <?php
                            // Clear-hour link drops just the hour filter, keeping the
                            // current day, agent and contact so the reader can widen
                            // back to the whole day in one click.
                            $clear_hour_url = base_url('Report/Ghl_Message_Log?log_date=' . urlencode($log_filters['log_date'])
                                . '&contact=' . urlencode($log_contact)
                                . $log_agent_param
                                . $log_all_dates_param);
                        ?>
                        <span class="label label-light-warning label-inline font-weight-bold mr-2">
                            Hour: <?php echo html_escape($log_hour_label); ?>
                            <a href="<?php echo html_escape($clear_hour_url); ?>" class="text-dark ml-2" data-toggle="tooltip" title="Show the whole day">&times;</a>
                        </span>
                    <?php } ?>
                    <span class="label label-light-primary label-inline font-weight-bold">
                        <?php echo number_format($p['total_rows']); ?> messages
                    </span>
                </div>
            </div>
            <div class="card-body">
                <form id="ghl-message-log-form" action="<?php echo base_url('Report/Ghl_Message_Log'); ?>" method="get" class="form mb-6">
                    <?php if ($log_hour !== null) { ?>
                        <input type="hidden" name="hour" value="<?php echo (int) $log_hour; ?>">
                    <?php } ?>
                    <?php if ($log_sort_col !== 'date') { ?>
                        <input type="hidden" name="sort" value="<?php echo html_escape($log_sort_col); ?>">
                    <?php } ?>
                    <?php if ($log_sort_dir !== 'desc') { ?>
                        <input type="hidden" name="dir" value="<?php echo html_escape($log_sort_dir); ?>">
                    <?php } ?>
                    <!-- Filters laid out 3 per row: Date/Time/Direction, then Contact/Agent/Actions. -->
                    <div class="row align-items-end">
                        <div class="col-md-4">
                            <div class="form-group mb-4">
                                <label>Date Range
                                    <?php if ($log_all_dates) { ?>
                                        <span class="label label-light-warning label-inline font-weight-bold ml-1">All dates</span>
                                    <?php } ?>
                                </label>
                                <div id="ghl_message_log_daterangepicker" class="input-icon">
                                    <input readonly type="text" name="log_date" value="<?php echo html_escape($log_filters['log_date']); ?>" autocomplete="off" placeholder="<?php echo $log_all_dates ? 'All dates — pick to narrow' : ''; ?>" class="form-control">
                                    <span><i class="la la-calendar"></i></span>
                                    <?php if ($log_all_dates) { ?>
                                        <!-- Keeps the log in all-dates mode while other filters (agent, direction, contact) are re-applied; the daterangepicker removes it as soon as a range is picked. -->
                                        <input type="hidden" name="all_dates" id="ghl_message_log_all_dates" value="1">
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-4">
                                <label>Time of Day <span class="text-muted font-size-sm">(each day)</span></label>
                                <div class="d-flex align-items-center" style="gap:6px;">
                                    <input type="time" name="time_from" value="<?php echo html_escape($log_time_from); ?>" class="form-control" aria-label="From time">
                                    <span class="text-muted">&ndash;</span>
                                    <input type="time" name="time_to" value="<?php echo html_escape($log_time_to); ?>" class="form-control" aria-label="To time">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-4">
                                <label>Direction</label>
                                <select name="direction" class="form-control">
                                    <option value="" <?php if ($log_direction === '') { echo 'selected'; } ?>>All</option>
                                    <option value="inbound" <?php if ($log_direction === 'inbound') { echo 'selected'; } ?>>Inbound</option>
                                    <option value="outbound" <?php if ($log_direction === 'outbound') { echo 'selected'; } ?>>Outbound</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row align-items-end">
                        <div class="col-md-4">
                            <div class="form-group mb-2">
                                <label>Contact
                                    <i class="la la-info-circle" style="cursor:help;" data-toggle="tooltip" title="Filter by phone number OR contact name (GHL shows a name when it has no number). Separate multiple entries with a comma, e.g. 0123456789, Siew Chin Yap"></i>
                                </label>
                                <input type="text" name="contact" value="<?php echo html_escape($log_contact); ?>" autocomplete="off" placeholder="e.g. 0123456789, Siew Chin Yap" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-2">
                                <label>Agent</label>
                                <select name="agent[]" multiple data-live-search="true" data-actions-box="true" data-selected-text-format="count > 1" class="form-control selectpicker" title="All Agents">
                                    <?php foreach ($log_agents as $agent_name) { ?>
                                        <option data-icon="la la-user-alt font-size-lg bs-icon" value="<?php echo html_escape($agent_name); ?>" <?php if (in_array($agent_name, $log_selected_agents, true)) { echo 'selected'; } ?>><?php echo html_escape($agent_name); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-2">
                                <div class="d-flex" style="gap:8px;">
                                    <input type="submit" value="Filter" class="btn btn-light-success font-weight-bold flex-fill">
                                    <input type="button" id="ghl-message-log-reset" value="Reset" class="btn btn-light-primary font-weight-bold flex-fill">
                                    <a href="<?php echo base_url('Report/Ghl_Message_Log_Export?log_date=') . urlencode($log_filters['log_date']) . '&contact=' . urlencode($log_contact) . $log_agent_param . $log_hour_param . $log_time_param . $log_direction_param . $log_all_dates_param; ?>" class="btn btn-light-info font-weight-bold flex-fill text-nowrap">
                                        <i class="la la-download"></i> Export CSV
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-head-custom">
                        <thead>
                            <tr>
                                <?php
                                    $date_active = $log_sort_col === 'date';
                                    $date_tip = $date_active
                                        ? 'Sorted ' . ($log_sort_dir === 'asc' ? 'oldest first' : 'newest first') . ' &mdash; click to show ' . ($log_sort_dir === 'asc' ? 'newest first' : 'oldest first')
                                        : 'Click to sort by date (newest first)';
                                    $dir_active = $log_sort_col === 'direction';
                                    $dir_tip = $dir_active
                                        ? 'Grouped ' . ($log_sort_dir === 'asc' ? 'Inbound first' : 'Outbound first') . ' &mdash; click to show ' . ($log_sort_dir === 'asc' ? 'Outbound first' : 'Inbound first')
                                        : 'Click to group by direction (Inbound first)';
                                ?>
                                <th style="width:170px;">
                                    <a href="<?php echo html_escape($sort_header_url('date')); ?>" class="<?php echo $date_active ? 'text-primary' : 'text-dark'; ?> font-weight-bolder text-hover-primary" data-toggle="tooltip" title="<?php echo $date_tip; ?>">
                                        Date / Time<?php echo $sort_caret('date'); ?>
                                    </a>
                                </th>
                                <?php if ($log_show_reply_time) { ?>
                                    <th style="width:110px;">Time Taken</th>
                                <?php } ?>
                                <th style="width:110px;">
                                    <a href="<?php echo html_escape($sort_header_url('direction')); ?>" class="<?php echo $dir_active ? 'text-primary' : 'text-dark'; ?> font-weight-bolder text-hover-primary" data-toggle="tooltip" title="<?php echo $dir_tip; ?>">
                                        Direction<?php echo $sort_caret('direction'); ?>
                                    </a>
                                </th>
                                <th style="width:150px;">Agent</th>
                                <th style="width:150px;">From</th>
                                <th style="width:150px;">To</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($log_messages)) { ?>
                                <tr>
                                    <td colspan="<?php echo $log_table_colspan; ?>" class="text-center py-10">No messages found for the selected range.</td>
                                </tr>
                            <?php } else { ?>
                                <?php foreach ($log_messages as $m) {
                                    $direction = isset($m['direction']) ? strtolower(trim((string) $m['direction'])) : '';
                                    $direction_label = ghl_message_log_direction_label($direction);
                                    if ($direction === 'inbound') {
                                        $direction_badge = 'label-light-info';
                                        $direction_icon = 'la-arrow-down';
                                    } elseif ($direction === 'outbound') {
                                        $direction_badge = 'label-light-success';
                                        $direction_icon = 'la-arrow-up';
                                    } else {
                                        $direction_badge = 'label-light-secondary';
                                        $direction_icon = '';
                                    }
                                ?>
                                    <tr>
                                        <td class="text-nowrap"><?php echo html_escape($m['message_timestamp']); ?></td>
                                        <?php if ($log_show_reply_time) { ?>
                                            <td class="text-nowrap">
                                                <?php if (!empty($m['reply_gap_label'])) { ?>
                                                    <span class="font-weight-bold text-dark-75"><?php echo html_escape($m['reply_gap_label']); ?></span>
                                                <?php } else { ?>
                                                    <span class="text-muted">&mdash;</span>
                                                <?php } ?>
                                            </td>
                                        <?php } ?>
                                        <td>
                                            <?php if ($direction_label !== '') { ?>
                                                <span class="label <?php echo $direction_badge; ?> label-inline font-weight-bold">
                                                    <?php if ($direction_icon !== '') { ?><i class="la <?php echo $direction_icon; ?> mr-1"></i><?php } ?><?php echo html_escape($direction_label); ?>
                                                </span>
                                            <?php } else { ?>
                                                <span class="text-muted">&mdash;</span>
                                            <?php } ?>
                                        </td>
                                        <td><?php echo !empty($m['agent']) ? html_escape($m['agent']) : '<span class="text-muted">&mdash;</span>'; ?></td>
                                        <td class="text-nowrap">
                                            <?php if (!empty($m['from_number'])) { ?>
                                                <a href="<?php echo $contact_url($m['from_number']); ?>" class="font-weight-bold text-primary" data-toggle="tooltip" title="Show this contact's full conversation history (all dates)"><?php echo html_escape($m['from_number']); ?></a>
                                            <?php } else { ?>
                                                <span class="text-muted">&mdash;</span>
                                            <?php } ?>
                                        </td>
                                        <td class="text-nowrap">
                                            <?php if (!empty($m['to_number'])) { ?>
                                                <a href="<?php echo $contact_url($m['to_number']); ?>" class="font-weight-bold text-primary" data-toggle="tooltip" title="Show this contact's full conversation history (all dates)"><?php echo html_escape($m['to_number']); ?></a>
                                            <?php } else { ?>
                                                <span class="text-muted">&mdash;</span>
                                            <?php } ?>
                                        </td>
                                        <td style="word-break:break-word;">
                                            <?php
                                                $body_text  = trim((string) (isset($m['body']) ? $m['body'] : ''));
                                                $attachments = ghl_message_log_attachments(isset($m['attachments_json']) ? $m['attachments_json'] : '');
                                            ?>
                                            <?php if ($body_text !== '') { ?><div style="white-space:pre-wrap;"><?php echo html_escape($body_text); ?></div><?php } ?>
                                            <?php foreach ($attachments as $att) {
                                                $url = html_escape($att['url']);
                                            ?>
                                                <div class="mt-2">
                                                    <?php if ($att['kind'] === 'image') { ?>
                                                        <a href="<?php echo $url; ?>" target="_blank" rel="noopener">
                                                            <img src="<?php echo $url; ?>" alt="image" style="max-width:180px; max-height:180px; border-radius:6px; border:1px solid #ebedf3;" loading="lazy" />
                                                        </a>
                                                    <?php } elseif ($att['kind'] === 'audio') { ?>
                                                        <audio controls preload="none" style="max-width:260px; vertical-align:middle;">
                                                            <source src="<?php echo $url; ?>">
                                                        </audio>
                                                    <?php } elseif ($att['kind'] === 'video') { ?>
                                                        <video controls preload="none" style="max-width:260px; max-height:200px; border-radius:6px;">
                                                            <source src="<?php echo $url; ?>">
                                                        </video>
                                                    <?php } else { ?>
                                                        <a href="<?php echo $url; ?>" target="_blank" rel="noopener" class="font-weight-bold text-primary">
                                                            <i class="la la-paperclip mr-1"></i><?php echo html_escape($att['name']); ?>
                                                        </a>
                                                    <?php } ?>
                                                </div>
                                            <?php } ?>
                                            <?php if ($body_text === '' && empty($attachments)) { ?><span class="text-muted">&mdash;</span><?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($log_messages)) { ?>
                    <div class="d-flex justify-content-between align-items-center flex-wrap mt-4">
                        <div class="text-muted">
                            Showing <?php echo number_format($p['from_row']); ?>&ndash;<?php echo number_format($p['to_row']); ?>
                            of <?php echo number_format($p['total_rows']); ?>
                        </div>
                        <div class="d-flex align-items-center" style="gap:8px;">
                            <?php if ($p['has_prev']) { ?>
                                <a href="<?php echo $page_url($p['page'] - 1); ?>" class="btn btn-light-primary btn-sm font-weight-bold">&laquo; Prev</a>
                            <?php } else { ?>
                                <span class="btn btn-light btn-sm font-weight-bold disabled">&laquo; Prev</span>
                            <?php } ?>
                            <span class="font-weight-bold">Page <?php echo number_format($p['page']); ?> of <?php echo number_format($p['total_pages']); ?></span>
                            <?php if ($p['has_next']) { ?>
                                <a href="<?php echo $page_url($p['page'] + 1); ?>" class="btn btn-light-primary btn-sm font-weight-bold">Next &raquo;</a>
                            <?php } else { ?>
                                <span class="btn btn-light btn-sm font-weight-bold disabled">Next &raquo;</span>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<script>
    $('#ghl_message_log_daterangepicker').daterangepicker({
        locale: { format: 'DD/MM/YYYY', separator: ' - ' },
        startDate: moment('<?php echo $log_start_date; ?>', 'YYYY-MM-DD'),
        endDate: moment('<?php echo $log_end_date; ?>', 'YYYY-MM-DD'),
        maxDate: moment(),
        buttonClasses: ' btn',
        applyClass: 'btn-primary',
        cancelClass: 'btn-secondary',
        autoApply: true
    }, function(start, end) {
        $('#ghl_message_log_daterangepicker .form-control').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
        // Picking a concrete range leaves "all dates" mode: drop the hidden flag
        // so this window is what gets applied on submit.
        $('#ghl_message_log_all_dates').remove();
    });

    $('#ghl-message-log-reset').on('click', function() {
        window.location.href = '<?php echo base_url('Report/Ghl_Message_Log'); ?>';
    });

    $('[data-toggle="tooltip"]').tooltip({ container: 'body', boundary: 'viewport', trigger: 'hover' });
</script>
