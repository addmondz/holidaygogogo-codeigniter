<?php
$p = $log_pagination;

$log_contact = isset($log_filters['contact']) ? $log_filters['contact'] : '';
$log_agent = isset($log_filters['agent']) ? $log_filters['agent'] : '';
$log_agents = isset($log_agents) ? $log_agents : array();
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

// Feed the picker unambiguous Y-m-d bounds so it opens on the correct month
// with today directly selectable (parsing the DD/MM/YYYY text field alone made
// the widget misread the month and refuse today until another date was picked).
$log_start_date = isset($log_filters['start_date']) ? $log_filters['start_date'] : date('Y-m-d');
$log_end_date = isset($log_filters['end_date']) ? $log_filters['end_date'] : date('Y-m-d');

/** Build a page URL keeping the current date, contact, agent and hour filters. */
$page_url = function ($page) use ($log_filters, $log_contact, $log_agent, $log_hour_param, $log_time_param) {
    return base_url('Report/Ghl_Message_Log?log_date=' . urlencode($log_filters['log_date'])
        . '&contact=' . urlencode($log_contact)
        . '&agent=' . urlencode($log_agent)
        . $log_hour_param
        . $log_time_param
        . '&page=' . (int) $page);
};

/** URL that filters the log to one contact number, keeping the current date range. */
$contact_url = function ($number) use ($log_filters) {
    return base_url('Report/Ghl_Message_Log?log_date=' . urlencode($log_filters['log_date'])
        . '&contact=' . urlencode($number));
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
                    <?php if ($log_show_reply_time && $log_avg_reply !== '') { ?>
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
                                . '&agent=' . urlencode($log_agent));
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
                    <div class="row align-items-end">
                        <div class="col-md-3">
                            <div class="form-group mb-0">
                                <label>Date Range</label>
                                <div id="ghl_message_log_daterangepicker" class="input-icon">
                                    <input readonly type="text" name="log_date" value="<?php echo html_escape($log_filters['log_date']); ?>" autocomplete="off" class="form-control">
                                    <span><i class="la la-calendar"></i></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mb-0">
                                <label>Time of Day <span class="text-muted font-size-sm">(each day)</span></label>
                                <div class="d-flex align-items-center" style="gap:6px;">
                                    <input type="time" name="time_from" value="<?php echo html_escape($log_time_from); ?>" class="form-control" aria-label="From time">
                                    <span class="text-muted">&ndash;</span>
                                    <input type="time" name="time_to" value="<?php echo html_escape($log_time_to); ?>" class="form-control" aria-label="To time">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group mb-0">
                                <label>Contact Number</label>
                                <input type="text" name="contact" value="<?php echo html_escape($log_contact); ?>" autocomplete="off" placeholder="e.g. 0123456789" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group mb-0">
                                <label>Agent</label>
                                <select name="agent" data-live-search="true" class="form-control selectpicker" title="All Agents">
                                    <option value="" <?php if ($log_agent === '') { echo 'selected'; } ?>>All Agents</option>
                                    <?php foreach ($log_agents as $agent_name) { ?>
                                        <option data-icon="la la-user-alt font-size-lg bs-icon" value="<?php echo html_escape($agent_name); ?>" <?php if ($log_agent !== '' && $log_agent === $agent_name) { echo 'selected'; } ?>><?php echo html_escape($agent_name); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <input type="submit" value="Filter" class="btn btn-light-success font-weight-bold btn-block mb-2">
                            <input type="button" id="ghl-message-log-reset" value="Reset" class="btn btn-light-primary font-weight-bold btn-block mb-2">
                            <a href="<?php echo base_url('Report/Ghl_Message_Log_Export?log_date=') . urlencode($log_filters['log_date']) . '&contact=' . urlencode($log_contact) . '&agent=' . urlencode($log_agent) . $log_hour_param . $log_time_param; ?>" class="btn btn-light-info font-weight-bold btn-block">
                                <i class="la la-download"></i> Export CSV
                            </a>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-head-custom">
                        <thead>
                            <tr>
                                <th style="width:170px;">Date / Time</th>
                                <?php if ($log_show_reply_time) { ?>
                                    <th style="width:110px;">Time Taken</th>
                                <?php } ?>
                                <th style="width:110px;">Direction</th>
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
                                                <a href="<?php echo $contact_url($m['from_number']); ?>" class="font-weight-bold text-primary" data-toggle="tooltip" title="Show only this contact's messages"><?php echo html_escape($m['from_number']); ?></a>
                                            <?php } else { ?>
                                                <span class="text-muted">&mdash;</span>
                                            <?php } ?>
                                        </td>
                                        <td class="text-nowrap">
                                            <?php if (!empty($m['to_number'])) { ?>
                                                <a href="<?php echo $contact_url($m['to_number']); ?>" class="font-weight-bold text-primary" data-toggle="tooltip" title="Show only this contact's messages"><?php echo html_escape($m['to_number']); ?></a>
                                            <?php } else { ?>
                                                <span class="text-muted">&mdash;</span>
                                            <?php } ?>
                                        </td>
                                        <td style="white-space:pre-wrap; word-break:break-word;"><?php echo html_escape($m['body']); ?></td>
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
    });

    $('#ghl-message-log-reset').on('click', function() {
        window.location.href = '<?php echo base_url('Report/Ghl_Message_Log'); ?>';
    });

    $('[data-toggle="tooltip"]').tooltip({ container: 'body', boundary: 'viewport', trigger: 'hover' });
</script>
