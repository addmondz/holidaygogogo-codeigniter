<?php
$buildOwnershipDataUrl = function($ownerId, $ownershipType = null) use ($lead_ownership_filters) {
    if ($ownershipType === null) {
        $ownershipType = isset($lead_ownership_filters['ownership_type']) ? $lead_ownership_filters['ownership_type'] : '';
    }

    $params = array(
        'lead_date' => isset($lead_ownership_filters['lead_date']) ? $lead_ownership_filters['lead_date'] : '',
        'owner' => array($ownerId),
        'ownership_type' => $ownershipType,
    );

    foreach ($params as $key => $value) {
        if ($value === '' || $value === null || $value === array()) {
            unset($params[$key]);
        }
    }

    $queryString = http_build_query($params);
    return base_url('Report/Lead_Ownership_Data') . ($queryString !== '' ? '?' . $queryString : '');
};
?>

<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-4" style="background:linear-gradient(135deg, #dfeadf 0%, #f3f8f1 100%);">
                <div class="card-title">
                    <div>
                        <h3 class="card-label mb-1" style="color:#36613a;">
                            <strong>Lead Ownership Dashboard</strong>
                        </h3>
                        <div class="text-muted font-size-sm">
                            Lead credit separated by current assigned owner and non-assigned users who replied more than 3 times inside the conversation window.
                        </div>
                    </div>
                </div>
                <div class="card-toolbar">
                    <div class="text-right">
                        <span class="label label-light-success label-inline font-weight-bold" id="lead-ownership-last-updated">
                            Calculated <?php echo !empty($lead_ownership_updated_at) ? html_escape($lead_ownership_updated_at) : 'Not available'; ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="accordion accordion-solid accordion-toggle-plus">
                    <div class="card">
                        <div class="card-header">
                            <div id="lead_ownership_header" data-toggle="collapse" data-target="#lead_ownership_filters" class="card-title" style="font-size:13px;">Filter Lead Ownership</div>
                        </div>
                        <div id="lead_ownership_filters" class="collapse show">
                            <div class="card-body">
                                <form id="lead-ownership-form" action="<?php echo base_url('Report/Lead_Ownership_Dashboard'); ?>" method="get" class="form">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Lead Date Range
                                                    <a onclick="resetLeadOwnershipDate()" class="btn btn-icon btn-light-warning btn-xs">
                                                        <i class="la la-undo"></i>
                                                    </a>
                                                </label>
                                                <div id="lead_ownership_daterangepicker" class="input-icon">
                                                    <input readonly type="text" name="lead_date" value="<?php echo html_escape($lead_ownership_filters['lead_date']); ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-calendar"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Owner</label>
                                                <select name="owner[]" data-live-search="true" class="form-control selectpicker" multiple data-actions-box="true" title="--ALL OWNERS--">
                                                    <?php foreach($lead_ownership_agents as $agent) { ?>
                                                        <option data-icon="la la-user font-size-lg bs-icon" value="<?php echo html_escape($agent->agent_id); ?>" <?php if(in_array((string) $agent->agent_id, $lead_ownership_filters['owner'], true)) { echo 'selected'; } ?>>
                                                            <?php echo html_escape($agent->agent_name); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Team Leader</label>
                                                <select name="team_lead[]" data-live-search="true" class="form-control selectpicker" multiple data-actions-box="true" title="--ALL TEAM LEADERS--">
                                                    <?php foreach($lead_ownership_team_leads as $tl) { ?>
                                                        <option data-icon="la la-user-tie font-size-lg bs-icon" value="<?php echo html_escape($tl->AdminID); ?>" <?php if(in_array((string) $tl->AdminID, $lead_ownership_filters['team_lead'], true)) { echo 'selected'; } ?>>
                                                            <?php echo html_escape($tl->Name); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Ownership Reason</label>
                                                <select name="ownership_type" class="form-control selectpicker">
                                                    <option value="">--ALL REASONS--</option>
                                                    <option value="assigned" <?php if($lead_ownership_filters['ownership_type'] === 'assigned') { echo 'selected'; } ?>>Assigned Owner</option>
                                                    <option value="reply" <?php if($lead_ownership_filters['ownership_type'] === 'reply') { echo 'selected'; } ?>>Reply Owner (&gt; 2 replies)</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="submit" value="Filter" class="btn btn-light-success font-weight-bold" style="width:80px;">
                                    <input type="button" id="lead-ownership-reset" value="Reset" class="btn btn-light-primary font-weight-bold" style="width:80px;">
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-8" id="lead-ownership-summary">
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-custom gutter-b shadow-sm">
                            <div class="card-body">
                                <div class="text-muted text-uppercase font-size-sm font-weight-bold mb-2">Total Leads</div>
                                <div class="font-weight-bolder font-size-h2 text-dark" data-summary="owned_leads"><?php echo number_format($ownership_summary['owned_leads']); ?></div>
                                <div class="d-flex flex-wrap mt-2" style="gap:6px;">
                                    <span class="label label-light-primary label-inline font-weight-bold">Assigned Owned:&nbsp <span data-summary="assigned_owned_leads"><?php echo number_format($ownership_summary['assigned_owned_leads']); ?></span></span>
                                    <span class="label label-light-info label-inline font-weight-bold">Reply Owned:&nbsp <span data-summary="reply_owned_leads"><?php echo number_format($ownership_summary['reply_owned_leads']); ?></span></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-custom gutter-b shadow-sm">
                            <div class="card-body">
                                <div class="text-muted text-uppercase font-size-sm font-weight-bold mb-2">Average Response</div>
                                <div class="font-weight-bolder font-size-h2 text-info" data-summary="avg_response_time_label"><?php echo html_escape($ownership_summary['avg_response_time_label']); ?></div>
                                <div class="d-flex flex-wrap mt-2" style="gap:6px;">
                                    <span class="label label-light-info label-inline font-weight-bold">First 5:&nbsp <span data-summary="avg_first_response_time_label"><?php echo html_escape($ownership_summary['avg_first_response_time_label']); ?></span></span>
                                    <span class="label label-light-primary label-inline font-weight-bold">Last 5:&nbsp <span data-summary="avg_recent_response_time_label"><?php echo html_escape($ownership_summary['avg_recent_response_time_label']); ?></span></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-custom gutter-b shadow-sm">
                            <div class="card-body">
                                <div class="text-muted text-uppercase font-size-sm font-weight-bold mb-2">Conversion Rate</div>
                                <div class="font-weight-bolder font-size-h2 text-success"><span data-summary="conversion_rate"><?php echo html_escape($ownership_summary['conversion_rate']); ?></span>%</div>
                                <div class="text-muted mt-2">Converted: <span data-summary="converted_leads"><?php echo number_format($ownership_summary['converted_leads']); ?></span></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-custom gutter-b shadow-sm">
                            <div class="card-body">
                                <div class="text-muted text-uppercase font-size-sm font-weight-bold mb-2">Follow Up Rate</div>
                                <div class="font-weight-bolder font-size-h2 text-warning"><span data-summary="follow_up_rate"><?php echo html_escape($ownership_summary['follow_up_rate']); ?></span>%</div>
                                <div class="text-muted mt-2">Follow ups: <span data-summary="follow_up_leads"><?php echo number_format($ownership_summary['follow_up_leads']); ?></span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-head-custom table-checkable" id="lead-ownership-table">
                        <thead>
                            <tr>
                                <th style="text-align:center;">No.</th>
                                <th>Owner</th>
                                <th style="text-align:center;">Ownership</th>
                                <th style="text-align:center;">Responded</th>
                                <th style="text-align:center;">Avg Response</th>
                                <th style="text-align:center;">Follow Up</th>
                                <th style="text-align:center;">Converted</th>
                                <th style="text-align:center;">Last Calculated</th>
                            </tr>
                        </thead>
                        <tbody id="lead-ownership-table-body">
                            <?php if(empty($lead_ownership_rows)) { ?>
                                <tr>
                                    <td colspan="8" class="text-center py-10">Lead ownership activity not found for the selected filters.</td>
                                </tr>
                            <?php } else { ?>
                                <?php $count = 1; ?>
                                <?php foreach($lead_ownership_rows as $row) { ?>
                                    <tr>
                                        <td class="text-center"><?php echo $count; ?></td>
                                        <td>
                                            <a href="<?php echo html_escape($buildOwnershipDataUrl($row['owner_user_id'])); ?>" class="font-weight-bold text-dark">
                                                <?php echo html_escape($row['owner_name']); ?>
                                            </a>
                                        </td>
                                        <td class="text-center" style="white-space:nowrap;">
                                            <a href="<?php echo html_escape($buildOwnershipDataUrl($row['owner_user_id'], 'assigned')); ?>" class="font-weight-bold text-primary"><?php echo number_format($row['assigned_owned_leads']); ?></a> <span class="text-muted font-size-sm">asgn</span>
                                        </td>
                                        <td class="text-center">
                                            <div class="font-weight-bold"><?php echo number_format($row['responded_leads']); ?></div>
                                            <div class="text-muted font-size-sm"><?php echo html_escape($row['response_rate']); ?>%</div>
                                        </td>
                                        <td class="text-center"><?php echo html_escape($row['avg_displayed_response_time_label']); ?></td>
                                        <td class="text-center">
                                            <div class="font-weight-bold"><?php echo number_format($row['follow_up_leads']); ?></div>
                                            <div class="text-muted font-size-sm"><?php echo html_escape($row['follow_up_rate']); ?>%</div>
                                        </td>
                                        <td class="text-center">
                                            <div class="font-weight-bold"><?php echo number_format($row['converted_leads']); ?></div>
                                            <div class="text-muted font-size-sm"><?php echo html_escape($row['conversion_rate']); ?>%</div>
                                        </td>
                                        <td class="text-center"><?php echo !empty($row['last_calculated_at']) ? html_escape($row['last_calculated_at']) : '-'; ?></td>
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
    $('<style>.lead-refresh-link:hover{text-decoration:underline;}</style>').appendTo('head');

    var leadOwnershipEndpoint = '<?php echo base_url('Report/Lead_Ownership_Dashboard_Data'); ?>';
    var leadOwnershipDataBaseUrl = '<?php echo base_url('Report/Lead_Ownership_Data'); ?>';
    var leadOwnershipCurrentDate = (new Date()).toLocaleDateString();

    $('#lead_ownership_daterangepicker').daterangepicker({
        buttonClasses: ' btn',
        applyClass: 'btn-primary',
        cancelClass: 'btn-secondary',
        autoApply: true
    }, function(start, end) {
        $('#lead_ownership_daterangepicker .form-control').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
    });

    $('#lead_ownership_daterangepicker').on('apply.daterangepicker', function(event, daterange) {
        var startDate = (new Date(daterange.startDate._d)).toLocaleDateString();
        var endDate = (new Date(daterange.endDate._d)).toLocaleDateString();

        if (startDate == leadOwnershipCurrentDate && endDate == leadOwnershipCurrentDate) {
            $('input[name="lead_date"]').val(moment().format('DD/MM/YYYY') + ' - ' + moment().format('DD/MM/YYYY'));
        }
    });

    function resetLeadOwnershipDate() {
        $('input[name="lead_date"]').val('');
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }

    function renderLeadOwnershipRows(rows) {
        var html = '';

        if (!rows || rows.length === 0) {
            $('#lead-ownership-table-body').html('<tr><td colspan="8" class="text-center py-10">Lead ownership activity not found for the selected filters.</td></tr>');
            return;
        }

        $.each(rows, function(index, row) {
            var allUrl = buildLeadOwnershipDataUrl(row.owner_user_id, $('#lead-ownership-form select[name="ownership_type"]').val());
            var assignedUrl = buildLeadOwnershipDataUrl(row.owner_user_id, 'assigned');

            html += '<tr>';
            html += '<td class="text-center">' + (index + 1) + '</td>';
            html += '<td><a href="' + allUrl + '" class="font-weight-bold text-dark">' + escapeHtml(row.owner_name) + '</a></td>';
            html += '<td class="text-center" style="white-space:nowrap;">';
            html += '<a href="' + assignedUrl + '" class="font-weight-bold text-primary">' + row.assigned_owned_leads + '</a> <span class="text-muted font-size-sm">asgn</span>';
            html += '</td>';
            html += '<td class="text-center"><div class="font-weight-bold">' + row.responded_leads + '</div><div class="text-muted font-size-sm">' + row.response_rate + '%</div></td>';
            html += '<td class="text-center">' + escapeHtml(row.avg_displayed_response_time_label) + '</td>';
            html += '<td class="text-center"><div class="font-weight-bold">' + row.follow_up_leads + '</div><div class="text-muted font-size-sm">' + row.follow_up_rate + '%</div></td>';
            html += '<td class="text-center"><div class="font-weight-bold">' + row.converted_leads + '</div><div class="text-muted font-size-sm">' + row.conversion_rate + '%</div></td>';
            html += '<td class="text-center">' + escapeHtml(row.last_calculated_at || '-') + '</td>';
            html += '</tr>';
        });

        $('#lead-ownership-table-body').html(html);
    }

    function buildLeadOwnershipDataUrl(ownerId, ownershipType) {
        var params = {};
        var leadDate = $.trim($('input[name="lead_date"]').val());

        if (leadDate !== '') {
            params.lead_date = leadDate;
        }

        if (ownerId) {
            params.owner = [ownerId];
        }

        if (ownershipType) {
            params.ownership_type = ownershipType;
        }

        var queryString = $.param(params);
        return leadOwnershipDataBaseUrl + (queryString ? '?' + queryString : '');
    }

    function renderLeadOwnershipSummary(summary) {
        $('[data-summary="owned_leads"]').text(summary.owned_leads);
        $('[data-summary="unique_leads"]').text(summary.unique_leads);
        $('[data-summary="assigned_owned_leads"]').text(summary.assigned_owned_leads);
        $('[data-summary="reply_owned_leads"]').text(summary.reply_owned_leads);
        $('[data-summary="avg_response_time_label"]').text(summary.avg_response_time_label);
        $('[data-summary="avg_first_response_time_label"]').text(summary.avg_first_response_time_label);
        $('[data-summary="avg_recent_response_time_label"]').text(summary.avg_recent_response_time_label);
        $('[data-summary="follow_up_leads"]').text(summary.follow_up_leads);
        $('[data-summary="follow_up_rate"]').text(summary.follow_up_rate);
        $('[data-summary="converted_leads"]').text(summary.converted_leads);
        $('[data-summary="conversion_rate"]').text(summary.conversion_rate);
    }

    function refreshLeadOwnershipDashboard() {
        $.getJSON(leadOwnershipEndpoint, $('#lead-ownership-form').serialize(), function(response) {
            renderLeadOwnershipSummary(response.summary);
            renderLeadOwnershipRows(response.rows);
            $('#lead-ownership-last-updated').text('Calculated ' + (response.updated_at || 'Not available'));
        });
    }

    $('#lead-ownership-reset').click(function() {
        Reset('<?php echo base_url('Report/Lead_Ownership_Dashboard'); ?>');
    });

</script>
