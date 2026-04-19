<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-4" style="background:linear-gradient(135deg, #d7e2f2 0%, #eef4fb 100%);">
                <div class="card-title">
                    <div>
                        <h3 class="card-label mb-1" style="color:#355c7d;">
                            <strong>Real-Time Lead Dashboard</strong>
                        </h3>
                        <div class="text-muted font-size-sm">
                            Live lead volume, average first-5 response time, and conversion rate by sales agent.
                        </div>
                    </div>
                </div>
                <div class="card-toolbar">
                    <span class="label label-light-primary label-inline font-weight-bold" id="lead-dashboard-last-updated">
                        Updated <?php echo html_escape($lead_dashboard_updated_at); ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="accordion accordion-solid accordion-toggle-plus">
                    <div class="card">
                        <div class="card-header">
                            <div id="lead_dashboard_header" data-toggle="collapse" data-target="#lead_dashboard_filters" class="card-title collapsed" style="font-size:13px;">Filter Lead Activity</div>
                        </div>
                        <div id="lead_dashboard_filters" class="collapse">
                            <div class="card-body">
                                <form id="lead-dashboard-form" action="<?php echo base_url('Report/Lead_Dashboard'); ?>" method="get" class="form">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Lead Date Range
                                                    <a onclick="resetLeadDate()" class="btn btn-icon btn-light-warning btn-xs">
                                                        <i class="la la-undo"></i>
                                                    </a>
                                                </label>
                                                <div id="lead_dashboard_daterangepicker" class="input-icon">
                                                    <input readonly type="text" name="lead_date" value="<?php echo html_escape($lead_dashboard_filters['lead_date']); ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-calendar"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Sales Agent</label>
                                                <select name="sales_agent" data-live-search="true" class="form-control selectpicker">
                                                    <option selected data-icon="la la-user font-size-lg bs-icon" value="">--ALL SALES AGENTS--</option>
                                                    <?php foreach($lead_dashboard_agents as $agent) { ?>
                                                        <option data-icon="la la-user font-size-lg bs-icon" value="<?php echo html_escape($agent->agent_id); ?>" <?php if($lead_dashboard_filters['sales_agent'] === $agent->agent_id) { echo 'selected'; } ?>>
                                                            <?php echo html_escape($agent->agent_name); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Auto Refresh</label>
                                                <select id="lead-dashboard-refresh-interval" class="form-control selectpicker">
                                                    <option value="30000">Every 30 seconds</option>
                                                    <option value="60000" selected>Every 60 seconds</option>
                                                    <option value="300000">Every 5 minutes</option>
                                                    <option value="0">Off</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="submit" value="Filter" class="btn btn-light-success font-weight-bold" style="width:80px;">
                                    <input type="button" id="lead-dashboard-reset" value="Reset" class="btn btn-light-primary font-weight-bold" style="width:80px;">
                                    <button type="button" id="lead-dashboard-refresh-now" class="btn btn-light-warning font-weight-bold">Refresh Now</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-8" id="lead-dashboard-summary">
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-custom gutter-b shadow-sm">
                            <div class="card-body">
                                <div class="text-muted text-uppercase font-size-sm font-weight-bold mb-2">Total Leads</div>
                                <div class="font-weight-bolder font-size-h2 text-dark" data-summary="total_leads"><?php echo number_format($dashboard_summary['total_leads']); ?></div>
                                <div class="text-muted mt-2">Responded: <span data-summary="responded_leads"><?php echo number_format($dashboard_summary['responded_leads']); ?></span></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-custom gutter-b shadow-sm">
                            <div class="card-body">
                                <div class="text-muted text-uppercase font-size-sm font-weight-bold mb-2">Average Response</div>
                                <div class="font-weight-bolder font-size-h2 text-info" data-summary="avg_response_time_label"><?php echo html_escape($dashboard_summary['avg_response_time_label']); ?></div>
                                <div class="text-muted mt-2">Avg replied msgs: <span data-summary="avg_responded_messages"><?php echo html_escape($dashboard_summary['avg_responded_messages']); ?></span> / 5</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-custom gutter-b shadow-sm">
                            <div class="card-body">
                                <div class="text-muted text-uppercase font-size-sm font-weight-bold mb-2">Conversion Rate</div>
                                <div class="font-weight-bolder font-size-h2 text-success"><span data-summary="conversion_rate"><?php echo html_escape($dashboard_summary['conversion_rate']); ?></span>%</div>
                                <div class="text-muted mt-2">Converted: <span data-summary="converted_leads"><?php echo number_format($dashboard_summary['converted_leads']); ?></span></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-custom gutter-b shadow-sm">
                            <div class="card-body">
                                <div class="text-muted text-uppercase font-size-sm font-weight-bold mb-2">Active Agents</div>
                                <div class="font-weight-bolder font-size-h2 text-primary" data-summary="active_agents"><?php echo number_format($dashboard_summary['active_agents']); ?></div>
                                <div class="text-muted mt-2">Agents with leads in range</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-head-custom table-checkable" id="lead-dashboard-table">
                        <thead>
                            <tr>
                                <th style="text-align:center;">No.</th>
                                <th>Sales Agent</th>
                                <th style="text-align:center;">Total Leads</th>
                                <th style="text-align:center;">Responded Leads</th>
                                <th style="text-align:center;">Response Rate</th>
                                <th style="text-align:center;">Avg First 5 Response</th>
                                <th style="text-align:center;">Converted Leads</th>
                                <th style="text-align:center;">Conversion Rate</th>
                                <th style="text-align:center;">Last Lead Update</th>
                                <th style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="lead-dashboard-table-body">
                            <?php if(empty($lead_dashboard_rows)) { ?>
                                <tr>
                                    <td colspan="10" class="text-center py-10">Lead activity not found for the selected filters.</td>
                                </tr>
                            <?php } else { ?>
                                <?php $count = 1; ?>
                                <?php foreach($lead_dashboard_rows as $row) { ?>
                                    <tr>
                                        <td class="text-center"><?php echo $count; ?></td>
                                        <td><?php echo html_escape($row['agent_name']); ?></td>
                                        <td class="text-center"><?php echo number_format($row['total_leads']); ?></td>
                                        <td class="text-center"><?php echo number_format($row['responded_leads']); ?></td>
                                        <td class="text-center"><?php echo html_escape($row['response_rate']); ?>%</td>
                                        <td class="text-center">
                                            <div class="font-weight-bold"><?php echo html_escape($row['avg_response_time_label']); ?></div>
                                            <div class="text-muted font-size-sm"><?php echo html_escape($row['avg_responded_messages']); ?> / 5 replied</div>
                                        </td>
                                        <td class="text-center"><?php echo number_format($row['converted_leads']); ?></td>
                                        <td class="text-center"><?php echo html_escape($row['conversion_rate']); ?>%</td>
                                        <td class="text-center"><?php echo !empty($row['last_updated_at']) ? html_escape($row['last_updated_at']) : '-'; ?></td>
                                        <td class="text-center">
                                            <a href="<?php echo base_url('Report/Lead_Data?sales_agent=') . urlencode($row['agent_id']) . (!empty($lead_dashboard_filters['lead_date']) ? '&lead_date=' . urlencode($lead_dashboard_filters['lead_date']) : ''); ?>" class="btn btn-light-primary btn-sm font-weight-bold">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php $count++; ?>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var leadDashboardTimer = null;
    var leadDashboardEndpoint = '<?php echo base_url('Report/Lead_Dashboard_Data'); ?>';
    var leadDataBaseUrl = '<?php echo base_url('Report/Lead_Data'); ?>';
    var leadDashboardCurrentDate = (new Date()).toLocaleDateString();

    $('#lead_dashboard_daterangepicker').daterangepicker({
        buttonClasses: ' btn',
        applyClass: 'btn-primary',
        cancelClass: 'btn-secondary',
        autoApply: true
    }, function(start, end) {
        $('#lead_dashboard_daterangepicker .form-control').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
    });

    $('#lead_dashboard_daterangepicker').on('apply.daterangepicker', function(event, daterange) {
        var startDate = (new Date(daterange.startDate._d)).toLocaleDateString();
        var endDate = (new Date(daterange.endDate._d)).toLocaleDateString();

        if (startDate == leadDashboardCurrentDate && endDate == leadDashboardCurrentDate) {
            $('input[name="lead_date"]').val(moment().format('DD/MM/YYYY') + ' - ' + moment().format('DD/MM/YYYY'));
        }
    });

    function resetLeadDate() {
        $('input[name="lead_date"]').val('');
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }

    function renderLeadDashboardRows(rows) {
        var html = '';

        if (!rows || rows.length === 0) {
            html = '<tr><td colspan="10" class="text-center py-10">Lead activity not found for the selected filters.</td></tr>';
            $('#lead-dashboard-table-body').html(html);
            return;
        }

        $.each(rows, function(index, row) {
            html += '<tr>';
            html += '<td class="text-center">' + (index + 1) + '</td>';
            html += '<td>' + escapeHtml(row.agent_name) + '</td>';
            html += '<td class="text-center">' + row.total_leads + '</td>';
            html += '<td class="text-center">' + row.responded_leads + '</td>';
            html += '<td class="text-center">' + row.response_rate + '%</td>';
            html += '<td class="text-center"><div class="font-weight-bold">' + escapeHtml(row.avg_response_time_label) + '</div><div class="text-muted font-size-sm">' + escapeHtml(row.avg_responded_messages) + ' / 5 replied</div></td>';
            html += '<td class="text-center">' + row.converted_leads + '</td>';
            html += '<td class="text-center">' + row.conversion_rate + '%</td>';
            html += '<td class="text-center">' + escapeHtml(row.last_updated_at || '-') + '</td>';
            html += '<td class="text-center"><a href="' + buildLeadDataUrl(row.agent_id) + '" class="btn btn-light-primary btn-sm font-weight-bold">View</a></td>';
            html += '</tr>';
        });

        $('#lead-dashboard-table-body').html(html);
    }

    function buildLeadDataUrl(agentId) {
        var params = {};
        var leadDate = $.trim($('input[name="lead_date"]').val());

        if (agentId) {
            params.sales_agent = agentId;
        }

        if (leadDate !== '') {
            params.lead_date = leadDate;
        }

        var queryString = $.param(params);
        return leadDataBaseUrl + (queryString ? '?' + queryString : '');
    }

    function renderLeadDashboardSummary(summary) {
        $('[data-summary="total_leads"]').text(summary.total_leads);
        $('[data-summary="responded_leads"]').text(summary.responded_leads);
        $('[data-summary="avg_response_time_label"]').text(summary.avg_response_time_label);
        $('[data-summary="avg_responded_messages"]').text(summary.avg_responded_messages);
        $('[data-summary="converted_leads"]').text(summary.converted_leads);
        $('[data-summary="conversion_rate"]').text(summary.conversion_rate);
        $('[data-summary="active_agents"]').text(summary.active_agents);
    }

    function refreshLeadDashboard() {
        $.getJSON(leadDashboardEndpoint, $('#lead-dashboard-form').serialize(), function(response) {
            renderLeadDashboardSummary(response.summary);
            renderLeadDashboardRows(response.rows);
            $('#lead-dashboard-last-updated').text('Updated ' + response.updated_at);
        });
    }

    function restartLeadDashboardTimer() {
        var refreshMs = parseInt($('#lead-dashboard-refresh-interval').val(), 10);

        if (leadDashboardTimer) {
            clearInterval(leadDashboardTimer);
            leadDashboardTimer = null;
        }

        if (refreshMs > 0) {
            leadDashboardTimer = setInterval(refreshLeadDashboard, refreshMs);
        }
    }

    $('#lead-dashboard-reset').click(function() {
        Reset('<?php echo base_url('Report/Lead_Dashboard'); ?>');
    });

    $('#lead-dashboard-refresh-now').click(function() {
        refreshLeadDashboard();
    });

    $('#lead-dashboard-refresh-interval').on('changed.bs.select', function() {
        restartLeadDashboardTimer();
    });

    <?php if(!empty($lead_dashboard_filters['sales_agent'])) { ?>
        $('#lead_dashboard_header').click();
    <?php } ?>

    restartLeadDashboardTimer();
</script>
