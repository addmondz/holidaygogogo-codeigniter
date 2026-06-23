<?php
$p = $log_pagination;

$log_contact = isset($log_filters['contact']) ? $log_filters['contact'] : '';

/** Build a page URL keeping the current date and contact filters. */
$page_url = function ($page) use ($log_filters, $log_contact) {
    return base_url('Report/Ghl_Message_Log?log_date=' . urlencode($log_filters['log_date'])
        . '&contact=' . urlencode($log_contact)
        . '&page=' . (int) $page);
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
                    <span class="label label-light-primary label-inline font-weight-bold">
                        <?php echo number_format($p['total_rows']); ?> messages
                    </span>
                </div>
            </div>
            <div class="card-body">
                <form id="ghl-message-log-form" action="<?php echo base_url('Report/Ghl_Message_Log'); ?>" method="get" class="form mb-6">
                    <div class="row align-items-end">
                        <div class="col-md-4">
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
                                <label>Contact Number</label>
                                <input type="text" name="contact" value="<?php echo html_escape($log_contact); ?>" autocomplete="off" placeholder="e.g. 0123456789" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <input type="submit" value="Filter" class="btn btn-light-success font-weight-bold" style="width:80px;">
                            <input type="button" id="ghl-message-log-reset" value="Reset" class="btn btn-light-primary font-weight-bold" style="width:80px;">
                            <a href="<?php echo base_url('Report/Ghl_Message_Log_Export?log_date=') . urlencode($log_filters['log_date']) . '&contact=' . urlencode($log_contact); ?>" class="btn btn-light-info font-weight-bold float-right">
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
                                <th style="width:150px;">Agent</th>
                                <th style="width:150px;">From</th>
                                <th style="width:150px;">To</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($log_messages)) { ?>
                                <tr>
                                    <td colspan="5" class="text-center py-10">No messages found for the selected range.</td>
                                </tr>
                            <?php } else { ?>
                                <?php foreach ($log_messages as $m) { ?>
                                    <tr>
                                        <td class="text-nowrap"><?php echo html_escape($m['message_timestamp']); ?></td>
                                        <td><?php echo !empty($m['agent']) ? html_escape($m['agent']) : '<span class="text-muted">&mdash;</span>'; ?></td>
                                        <td class="text-nowrap"><?php echo html_escape($m['from_number']); ?></td>
                                        <td class="text-nowrap"><?php echo html_escape($m['to_number']); ?></td>
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
</script>
