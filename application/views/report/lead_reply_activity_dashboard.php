<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-4" style="background:linear-gradient(135deg, #e4edf5 0%, #f5f8fb 100%);">
                <div class="card-title">
                    <div>
                        <h3 class="card-label mb-1" style="color:#2f506f;">
                            <strong>Lead Reply Activity Dashboard</strong>
                        </h3>
                    </div>
                </div>
                <div class="card-toolbar">
                    <div class="text-right">
                        <span class="label label-light-primary label-inline font-weight-bold" id="lead-reply-activity-last-updated">
                            Updated <?php echo !empty($lead_reply_activity_updated_at) ? html_escape($lead_reply_activity_updated_at) : 'Not available'; ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="accordion accordion-solid accordion-toggle-plus">
                    <div class="card">
                        <div class="card-header">
                            <div id="lead_reply_activity_header" data-toggle="collapse" data-target="#lead_reply_activity_filters" class="card-title" style="font-size:13px;">Filter Reply Activity</div>
                        </div>
                        <div class="alert alert-custom alert-light-primary py-3 px-4 mt-3 mb-0" role="alert">
                            <div class="alert-icon">
                                <i class="la la-info-circle"></i>
                            </div>
                            <div class="alert-text font-weight-bold" style="line-height:1.45;">
                                Counts only replies made on a weekday between 9AM and 7PM. Lead Responded is the distinct leads the owner replied to in that window (replying many times to one lead still counts once). Transfer Out Lead uses the date the owner crossed the reply-created threshold. Today Handling Lead = Lead Responded &minus; Transfer Out Lead.
                            </div>
                        </div>
                        <div id="lead_reply_activity_filters" class="collapse show">
                            <div class="card-body">
                                <form id="lead-reply-activity-form" action="<?php echo base_url('Report/Lead_Reply_Activity_Dashboard'); ?>" method="get" class="form">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Activity Date
                                                    <a onclick="resetLeadReplyActivityDate()" class="btn btn-icon btn-light-warning btn-xs">
                                                        <i class="la la-undo"></i>
                                                    </a>
                                                </label>
                                                <div id="lead_reply_activity_daterangepicker" class="input-icon">
                                                    <input readonly type="text" name="reply_date" value="<?php echo html_escape($lead_reply_activity_filters['reply_date']); ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-calendar"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Owner</label>
                                                <select name="owner[]" data-live-search="true" class="form-control selectpicker" multiple data-actions-box="true" title="--ALL OWNERS--">
                                                    <?php foreach($lead_reply_activity_agents as $agent) { ?>
                                                        <option data-icon="la la-user font-size-lg bs-icon" value="<?php echo html_escape($agent->agent_id); ?>" <?php if(in_array((string) $agent->agent_id, $lead_reply_activity_filters['owner'], true)) { echo 'selected'; } ?>>
                                                            <?php echo html_escape($agent->agent_name); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Team Leader</label>
                                                <select name="team_lead[]" data-live-search="true" class="form-control selectpicker" multiple data-actions-box="true" title="--ALL TEAM LEADERS--">
                                                    <?php foreach($lead_reply_activity_team_leads as $tl) { ?>
                                                        <option data-icon="la la-user-tie font-size-lg bs-icon" value="<?php echo html_escape($tl->AdminID); ?>" <?php if(in_array((string) $tl->AdminID, $lead_reply_activity_filters['team_lead'], true)) { echo 'selected'; } ?>>
                                                            <?php echo html_escape($tl->Name); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="submit" value="Filter" class="btn btn-light-primary font-weight-bold" style="width:80px;">
                                    <input type="button" id="lead-reply-activity-reset" value="Reset" class="btn btn-light-success font-weight-bold" style="width:80px;">
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive mt-8">
                    <table class="table table-bordered table-head-custom table-checkable" id="lead-reply-activity-table">
                        <thead>
                            <tr>
                                <th style="text-align:center;">No.</th>
                                <th>Owner</th>
                                <th style="text-align:center;">
                                    Lead Responded
                                    <i class="la la-info-circle ml-1" style="cursor:help; color:#2f506f;" data-toggle="tooltip"
                                       title="Distinct leads this owner replied to, counted on a weekday between 9AM and 7PM only. Replying many times (even more than 3) to the same lead still counts as one."></i>
                                </th>
                                <th style="text-align:center;">
                                    Transfer Out Lead
                                    <i class="la la-info-circle ml-1" style="cursor:help; color:#2f506f;" data-toggle="tooltip"
                                       title="Leads the owner crossed the reply-created threshold on (their 3rd outbound reply lands in the selected window). These are handed off / transferred out of the owner's active queue."></i>
                                </th>
                                <th style="text-align:center;">
                                    Today Handling Lead
                                    <i class="la la-info-circle ml-1" style="cursor:help; color:#2f506f;" data-toggle="tooltip"
                                       title="Leads still being handled today: Lead Responded minus Transfer Out Lead (never below zero)."></i>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="lead-reply-activity-table-body">
                            <?php if(empty($lead_reply_activity_rows)) { ?>
                                <tr>
                                    <td colspan="5" class="text-center py-10">Lead reply activity not found for the selected filters.</td>
                                </tr>
                            <?php } else { ?>
                                <?php $count = 1; ?>
                                <?php foreach($lead_reply_activity_rows as $row) { ?>
                                    <tr>
                                        <td class="text-center"><?php echo $count; ?></td>
                                        <td class="font-weight-bold text-dark"><?php echo html_escape($row['owner_name']); ?></td>
                                        <td class="text-center"><?php echo number_format($row['lead_responded']); ?></td>
                                        <td class="text-center"><?php echo number_format($row['transfer_out_leads']); ?></td>
                                        <td class="text-center font-weight-bold text-dark"><?php echo number_format($row['today_handling_leads']); ?></td>
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
    $('<style>.lead-refresh-link:hover{text-decoration:underline;}#lead-reply-activity-table tbody tr{transition:background-color .15s ease;}#lead-reply-activity-table tbody tr:hover{background-color:#e4edf5;cursor:pointer;}</style>').appendTo('head');

    var leadReplyActivityEndpoint = '<?php echo base_url('Report/Lead_Reply_Activity_Dashboard_Data'); ?>';
    $('#lead_reply_activity_daterangepicker').daterangepicker({
        buttonClasses: ' btn',
        applyClass: 'btn-primary',
        cancelClass: 'btn-secondary',
        autoApply: true,
        singleDatePicker: true,
        locale: {
            format: 'DD/MM/YYYY'
        }
    }, function(start, end) {
        $('#lead_reply_activity_daterangepicker .form-control').val(start.format('DD/MM/YYYY'));
    });

    $('#lead_reply_activity_daterangepicker').on('apply.daterangepicker', function(event, daterange) {
        $('input[name="reply_date"]').val(daterange.startDate.format('DD/MM/YYYY'));
    });

    function resetLeadReplyActivityDate() {
        $('input[name="reply_date"]').val('');
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }

    function renderLeadReplyActivityRows(rows) {
        var html = '';

        if (!rows || rows.length === 0) {
            $('#lead-reply-activity-table-body').html('<tr><td colspan="5" class="text-center py-10">Lead reply activity not found for the selected filters.</td></tr>');
            return;
        }

        $.each(rows, function(index, row) {
            var leadResponded = Number(row.lead_responded) || 0;
            var transferOutLeads = Number(row.transfer_out_leads) || 0;
            var todayHandlingLeads = Number(row.today_handling_leads) || 0;
            html += '<tr>';
            html += '<td class="text-center">' + (index + 1) + '</td>';
            html += '<td class="font-weight-bold text-dark">' + escapeHtml(row.owner_name) + '</td>';
            html += '<td class="text-center">' + leadResponded + '</td>';
            html += '<td class="text-center">' + transferOutLeads + '</td>';
            html += '<td class="text-center font-weight-bold text-dark">' + todayHandlingLeads + '</td>';
            html += '</tr>';
        });

        $('#lead-reply-activity-table-body').html(html);
    }

    function refreshLeadReplyActivityDashboard() {
        $.getJSON(leadReplyActivityEndpoint, $('#lead-reply-activity-form').serialize(), function(response) {
            renderLeadReplyActivityRows(response.rows);
            $('#lead-reply-activity-last-updated').text('Updated ' + (response.updated_at || 'Not available'));
        });
    }

    $('#lead-reply-activity-reset').click(function() {
        Reset('<?php echo base_url('Report/Lead_Reply_Activity_Dashboard'); ?>');
    });

    $('[data-toggle="tooltip"]').tooltip({ container: 'body', boundary: 'viewport', trigger: 'hover' });

</script>
