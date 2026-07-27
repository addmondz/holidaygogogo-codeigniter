<style>
    .lra-count-link {
        color: #2f506f;
        text-decoration: underline;
        text-decoration-style: dotted;
        cursor: pointer;
    }
    .lra-count-link:hover { color: #1b3550; }

    .lra-leads-table tbody tr { transition: background-color .15s ease; }
    .lra-leads-table tbody tr:hover { background-color: #e4edf5; }

    .lra-chat-modal .modal-dialog { max-width: 820px; }
    .lra-chat-modal .modal-content { border-radius: 14px; overflow: hidden; }
    .lra-chat-modal .modal-header {
        background: linear-gradient(135deg, #e4edf5 0%, #f5f8fb 100%);
        border-bottom: 1px solid #d6e0ec;
    }
    .lra-chat-modal .modal-body { background: #f6f8fb; max-height: 72vh; overflow-y: auto; }

    .lra-chat-empty {
        max-width: 520px; margin: 0 auto; font-size: 12px; color: #7b7b7b;
        text-align: center; padding: 12px 10px; background: #fff;
        border: 1px dashed #cdd8e4; border-radius: 12px;
    }

    body.lra-modal-scroll-lock, body.modal-open { overflow: hidden !important; }
</style>

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
                    <a href="<?php echo base_url('Report/Lead_Reply_Activity_Hourly_All') . '?reply_date=' . urlencode($lead_reply_activity_filters['reply_date']); ?>" class="btn btn-light-primary font-weight-bold mr-3" data-toggle="tooltip" title="See every agent's inbound/outbound per hour on one page">
                        <i class="la la-clock-o"></i> All Agents Hourly
                    </a>
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
                                Counts only replies made on any day between 7AM and 10PM. Lead Responded is the distinct leads the owner replied to in that window (replying many times to one lead still counts once). Transfer Out Lead counts leads that were once assigned to the owner and have since been reassigned to another agent; leads still under the owner, and leads the owner only helped reply on but was never assigned, never count. Today Handling Lead = Lead Responded &minus; Transfer Out Lead.
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
                                        <?php if($this->session->level != 20) { ?>
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
                                                <label>Team</label>
                                                <select name="team_lead[]" data-live-search="true" class="form-control selectpicker" multiple data-actions-box="true" title="--ALL TEAMS--">
                                                    <?php foreach($lead_reply_activity_team_leads as $tl) { ?>
                                                        <option data-icon="la la-user-tie font-size-lg bs-icon" value="<?php echo html_escape($tl->TeamID); ?>" <?php if(in_array((string) $tl->TeamID, $lead_reply_activity_filters['team_lead'], true)) { echo 'selected'; } ?>>
                                                            <?php echo html_escape($tl->Name); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <?php } ?>
                                    </div>
                                    <input type="submit" value="Filter" class="btn btn-light-primary font-weight-bold" style="width:80px;">
                                    <input type="button" id="lead-reply-activity-reset" value="Reset" class="btn btn-light-success font-weight-bold" style="width:80px;">
                                </form>

                                <div class="separator separator-dashed my-5"></div>

                                <div class="row align-items-end">
                                    <div class="col-md-4">
                                        <div class="form-group mb-0">
                                            <label>Export Date Range
                                                <i class="la la-info-circle ml-1" style="cursor:help; color:#2f506f;" data-toggle="tooltip"
                                                   title="The on-screen table is for one day. The Excel download instead covers this whole range, with each date as a row and one column group per owner (max 92 days). Owner and Team filters above are applied."></i>
                                            </label>
                                            <div id="lead_reply_export_daterangepicker" class="input-icon">
                                                <input readonly type="text" id="lead-reply-export-range" autocomplete="off" class="form-control" placeholder="Select date range">
                                                <span>
                                                    <i class="la la-calendar"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-0">
                                            <button type="button" id="lead-reply-export-btn" class="btn btn-light-success font-weight-bold">
                                                <i class="la la-file-excel-o"></i> Download Excel
                                            </button>
                                        </div>
                                    </div>
                                </div>
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
                                    New Lead Picked Up
                                    <i class="la la-info-circle ml-1" style="cursor:help; color:#2f506f;" data-toggle="tooltip"
                                       title="Distinct leads assigned to this owner whose conversation landed within the selected date range (counted by the landing date, same as the Total New Leads GHL card). Re-engaged customers count too. Only leads actually picked up (assigned) appear here, so the per-owner total sits below the company-wide card by however many landed leads nobody has picked up yet."></i>
                                </th>
                                <th style="text-align:center;">
                                    Lead Responded
                                    <i class="la la-info-circle ml-1" style="cursor:help; color:#2f506f;" data-toggle="tooltip"
                                       title="Distinct leads this owner replied to, counted on any day between 7AM and 10PM only. Replying many times to the same lead still counts as one."></i>
                                </th>
                                <th style="text-align:center;">
                                    Transfer Out Lead
                                    <i class="la la-info-circle ml-1" style="cursor:help; color:#2f506f;" data-toggle="tooltip"
                                       title="Leads that were once assigned to this owner and have since been reassigned to another agent/inbox (their first outbound reply lands in the selected window). Leads still assigned to this owner, and leads this owner only helped reply on but was never assigned, never count."></i>
                                </th>
                                <th style="text-align:center;">
                                    Helped Reply Lead
                                    <i class="la la-info-circle ml-1" style="cursor:help; color:#2f506f;" data-toggle="tooltip"
                                       title="Leads this owner replied to but was never assigned -- they helped on another agent's lead (their first outbound reply lands in the selected window). These are NOT counted as Transfer Out, since the lead was never this owner's."></i>
                                </th>
                                <th style="text-align:center;">
                                    Today Handling Lead
                                    <i class="la la-info-circle ml-1" style="cursor:help; color:#2f506f;" data-toggle="tooltip"
                                       title="Leads still being handled today: Lead Responded minus Transfer Out Lead (never below zero)."></i>
                                </th>
                                <th style="text-align:center;">
                                    Avg Response Time
                                    <i class="la la-info-circle ml-1" style="cursor:help; color:#2f506f;" data-toggle="tooltip"
                                       title="Average first-response time across the leads this owner replied to in the window (same measure as the Lead Dashboard). Leads with no measured response time are ignored; a dash means none qualified."></i>
                                </th>
                                <th style="text-align:center;">Hourly</th>
                            </tr>
                        </thead>
                        <tbody id="lead-reply-activity-table-body">
                            <?php if(empty($lead_reply_activity_rows)) { ?>
                                <tr>
                                    <td colspan="9" class="text-center py-10">Lead reply activity not found for the selected filters.</td>
                                </tr>
                            <?php } else { ?>
                                <?php $count = 1; ?>
                                <?php $replyDateParam = isset($lead_reply_activity_filters['reply_date']) ? $lead_reply_activity_filters['reply_date'] : ''; ?>
                                <?php foreach($lead_reply_activity_rows as $row) { ?>
                                    <?php $hourlyUrl = base_url('Report/Lead_Reply_Activity_Hourly') . '?owner=' . urlencode($row['owner_user_id']) . '&reply_date=' . urlencode($replyDateParam); ?>
                                    <tr>
                                        <td class="text-center"><?php echo $count; ?></td>
                                        <td class="font-weight-bold text-dark"><?php echo html_escape($row['owner_name']); ?></td>
                                        <td class="text-center">
                                            <?php if((int) $row['new_leads_picked_up'] > 0) { ?>
                                                <a class="lra-count-link font-weight-bold" data-owner="<?php echo html_escape($row['owner_user_id']); ?>" data-owner-name="<?php echo html_escape($row['owner_name']); ?>" data-metric="picked_up" data-toggle="tooltip" title="Show the leads behind this number"><?php echo number_format($row['new_leads_picked_up']); ?></a>
                                            <?php } else { echo number_format($row['new_leads_picked_up']); } ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if((int) $row['lead_responded'] > 0) { ?>
                                                <a class="lra-count-link font-weight-bold" data-owner="<?php echo html_escape($row['owner_user_id']); ?>" data-owner-name="<?php echo html_escape($row['owner_name']); ?>" data-metric="responded" data-toggle="tooltip" title="Show the leads behind this number"><?php echo number_format($row['lead_responded']); ?></a>
                                            <?php } else { echo number_format($row['lead_responded']); } ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if((int) $row['transfer_out_leads'] > 0) { ?>
                                                <a class="lra-count-link font-weight-bold" data-owner="<?php echo html_escape($row['owner_user_id']); ?>" data-owner-name="<?php echo html_escape($row['owner_name']); ?>" data-metric="transfer_out" data-toggle="tooltip" title="Show the leads behind this number"><?php echo number_format($row['transfer_out_leads']); ?></a>
                                            <?php } else { echo number_format($row['transfer_out_leads']); } ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if((int) $row['helped_reply_leads'] > 0) { ?>
                                                <a class="lra-count-link font-weight-bold" data-owner="<?php echo html_escape($row['owner_user_id']); ?>" data-owner-name="<?php echo html_escape($row['owner_name']); ?>" data-metric="helped_reply" data-toggle="tooltip" title="Show the leads behind this number"><?php echo number_format($row['helped_reply_leads']); ?></a>
                                            <?php } else { echo number_format($row['helped_reply_leads']); } ?>
                                        </td>
                                        <td class="text-center font-weight-bold text-dark">
                                            <?php if((int) $row['today_handling_leads'] > 0) { ?>
                                                <a class="lra-count-link" data-owner="<?php echo html_escape($row['owner_user_id']); ?>" data-owner-name="<?php echo html_escape($row['owner_name']); ?>" data-metric="today_handling" data-toggle="tooltip" title="Show the leads behind this number"><?php echo number_format($row['today_handling_leads']); ?></a>
                                            <?php } else { echo number_format($row['today_handling_leads']); } ?>
                                        </td>
                                        <td class="text-center"><?php echo html_escape($row['avg_response_time_label']); ?></td>
                                        <td class="text-center">
                                            <a href="<?php echo html_escape($hourlyUrl); ?>" class="btn btn-sm btn-light-primary font-weight-bold" data-toggle="tooltip" title="View inbound/outbound per hour for this owner">
                                                <i class="la la-clock-o"></i> Hourly
                                            </a>
                                        </td>
                                    </tr>
                                    <?php $count++; ?>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                        <?php if(!empty($lead_reply_activity_rows)) { ?>
                            <?php
                                $totalNewLeads = 0; $totalResponded = 0; $totalTransfer = 0; $totalHelped = 0; $totalHandling = 0;
                                $weightedSeconds = 0; $weightedLeads = 0;
                                foreach($lead_reply_activity_rows as $totalRow) {
                                    $totalNewLeads += (int) $totalRow['new_leads_picked_up'];
                                    $totalResponded += (int) $totalRow['lead_responded'];
                                    $totalTransfer += (int) $totalRow['transfer_out_leads'];
                                    $totalHelped += (int) $totalRow['helped_reply_leads'];
                                    $totalHandling += (int) $totalRow['today_handling_leads'];
                                    if($totalRow['avg_response_time_seconds'] !== null && (int) $totalRow['lead_responded'] > 0) {
                                        $weightedSeconds += (int) $totalRow['avg_response_time_seconds'] * (int) $totalRow['lead_responded'];
                                        $weightedLeads += (int) $totalRow['lead_responded'];
                                    }
                                }
                                $avgTotalSeconds = $weightedLeads > 0 ? (int) round($weightedSeconds / $weightedLeads) : null;
                                // Match the per-row Avg Response Time label formatting.
                                if($avgTotalSeconds === null) {
                                    $avgTotalLabel = '-';
                                } elseif($avgTotalSeconds < 60) {
                                    $avgTotalLabel = $avgTotalSeconds . ' sec';
                                } elseif($avgTotalSeconds < 3600) {
                                    $avgTotalLabel = floor($avgTotalSeconds / 60) . ' min';
                                } elseif($avgTotalSeconds < 86400) {
                                    $h = floor($avgTotalSeconds / 3600); $m = floor(($avgTotalSeconds % 3600) / 60);
                                    $avgTotalLabel = $m === 0.0 ? $h . ' hr' : $h . ' hr ' . $m . ' min';
                                } else {
                                    $d = floor($avgTotalSeconds / 86400); $h = floor(($avgTotalSeconds % 86400) / 3600);
                                    $avgTotalLabel = $h === 0.0 ? $d . ' day' : $d . ' day ' . $h . ' hr';
                                }
                            ?>
                            <tfoot id="lead-reply-activity-table-foot">
                                <tr style="background:#e4edf5; font-weight:bold; color:#2f506f;">
                                    <td class="text-right" colspan="2">Total</td>
                                    <td class="text-center"><?php echo number_format($totalNewLeads); ?></td>
                                    <td class="text-center"><?php echo number_format($totalResponded); ?></td>
                                    <td class="text-center"><?php echo number_format($totalTransfer); ?></td>
                                    <td class="text-center"><?php echo number_format($totalHelped); ?></td>
                                    <td class="text-center"><?php echo number_format($totalHandling); ?></td>
                                    <td class="text-center">
                                        <?php echo html_escape($avgTotalLabel); ?>
                                        <i class="la la-info-circle ml-1" style="cursor:help; color:#2f506f;" data-toggle="tooltip"
                                           title="Average response time across all owners, weighted by each owner's Lead Responded count (owners with no measured response time are ignored)."></i>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        <?php } ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade lra-chat-modal" id="lraLeadsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="lra-modal-title">Leads</h5>
                    <div class="text-muted font-size-sm" id="lra-modal-subtitle"></div>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="lra-leads-content">
                    <div class="lra-chat-empty">Loading leads...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $('<style>.lead-refresh-link:hover{text-decoration:underline;}#lead-reply-activity-table tbody tr{transition:background-color .15s ease;}#lead-reply-activity-table tbody tr:hover{background-color:#e4edf5;cursor:pointer;}</style>').appendTo('head');

    var lraLeadsEndpoint = '<?php echo base_url('Report/Lead_Reply_Activity_Leads'); ?>';

    var lraMetricLabels = {
        picked_up: 'New Leads Picked Up',
        responded: 'Leads Responded',
        transfer_out: 'Transfer Out Leads',
        helped_reply: 'Helped Reply Leads',
        today_handling: 'Today Handling Leads'
    };
    var lraActivityHeaders = {
        picked_up: 'Customer\'s First Inbound Message',
        responded: 'First Reply',
        transfer_out: 'Transferred Out',
        helped_reply: 'First Reply',
        today_handling: 'First Reply'
    };

    // Build a clickable count cell (link only when > 0 -- nothing to drill on 0).
    function lraCountCell(value, ownerId, ownerName, metric) {
        var n = Number(value) || 0;
        if (n <= 0) { return String(n); }
        return '<a class="lra-count-link font-weight-bold" data-owner="' + escapeHtml(ownerId) +
            '" data-owner-name="' + escapeHtml(ownerName) + '" data-metric="' + metric +
            '" data-toggle="tooltip" title="Show the leads behind this number">' + n + '</a>';
    }

    function lraRenderLeads(metric, leads) {
        if (!leads || leads.length === 0) {
            $('#lra-leads-content').html('<div class="lra-chat-empty">No leads found behind this number.</div>');
            return;
        }
        var activityHeader = lraActivityHeaders[metric] || 'Activity';
        var html = '<div class="table-responsive"><table class="table table-bordered table-head-custom lra-leads-table">';
        html += '<thead><tr>' +
            '<th style="text-align:center;">No.</th>' +
            '<th>Contact</th>' +
            '<th>' + escapeHtml(activityHeader) + '</th>' +
            '</tr></thead><tbody>';
        $.each(leads, function(index, lead) {
            html += '<tr>';
            html += '<td class="text-center align-middle">' + (index + 1) + '</td>';
            html += '<td class="align-middle">' +
                '<div class="font-weight-bold text-dark">' + escapeHtml(lead.contact_name) + '</div>' +
                '<div class="text-muted font-size-sm">' + escapeHtml(lead.phone) + '</div>' +
                '<div class="text-muted font-size-sm">' + escapeHtml(lead.conversation_id) + '</div>' +
                '</td>';
            html += '<td class="align-middle">' + escapeHtml(lead.activity_label) + '</td>';
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        $('#lra-leads-content').html(html);
    }

    function lraOpenLeads(link) {
        var ownerId = link.data('owner');
        var ownerName = link.data('owner-name') || ownerId;
        var metric = link.data('metric');
        var replyDate = $('input[name="reply_date"]').val() || '';

        $('#lra-modal-title').text(lraMetricLabels[metric] || 'Leads');
        $('#lra-modal-subtitle').text((ownerName || '') + (replyDate ? ' | ' + replyDate : ''));
        $('#lra-leads-content').html('<div class="lra-chat-empty">Loading leads...</div>');
        $('#lraLeadsModal').modal('show');

        $.getJSON(lraLeadsEndpoint, { owner: ownerId, reply_date: replyDate, metric: metric })
            .done(function(response) {
                if (!response || !response.success) {
                    $('#lra-leads-content').html('<div class="lra-chat-empty text-danger">Failed to load leads.</div>');
                    return;
                }
                lraRenderLeads(metric, response.leads);
            })
            .fail(function() {
                $('#lra-leads-content').html('<div class="lra-chat-empty text-danger">Failed to load leads.</div>');
            });
    }

    $(document).on('click', '.lra-count-link', function() {
        lraOpenLeads($(this));
    });

    $('#lraLeadsModal').on('shown.bs.modal', function() {
        $('body').addClass('lra-modal-scroll-lock');
    });

    $('#lraLeadsModal').on('hidden.bs.modal', function() {
        $('body').removeClass('lra-modal-scroll-lock');
    });

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

    // Export-only range picker: defaults to the last 30 days, independent of the
    // daily Activity Date above. Stored as "DD/MM/YYYY - DD/MM/YYYY".
    var leadReplyExportEndpoint = '<?php echo base_url('Report/Lead_Reply_Activity_Export'); ?>';
    var leadReplyExportStart = moment().subtract(29, 'days');
    var leadReplyExportEnd = moment();

    function applyLeadReplyExportRange(start, end) {
        leadReplyExportStart = start;
        leadReplyExportEnd = end;
        $('#lead-reply-export-range').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
    }

    $('#lead_reply_export_daterangepicker').daterangepicker({
        buttonClasses: ' btn',
        applyClass: 'btn-primary',
        cancelClass: 'btn-secondary',
        startDate: leadReplyExportStart,
        endDate: leadReplyExportEnd,
        locale: {
            format: 'DD/MM/YYYY'
        }
    }, applyLeadReplyExportRange);
    applyLeadReplyExportRange(leadReplyExportStart, leadReplyExportEnd);

    $('#lead-reply-export-btn').click(function() {
        // Carry the same Owner / Team filters shown above; only the date
        // window differs (range here vs. single day in the form).
        var params = $('#lead-reply-activity-form')
            .serializeArray()
            .filter(function(item) { return item.name !== 'reply_date'; });
        params.push({
            name: 'export_range',
            value: leadReplyExportStart.format('DD/MM/YYYY') + ' - ' + leadReplyExportEnd.format('DD/MM/YYYY')
        });
        window.location.href = leadReplyExportEndpoint + '?' + $.param(params);
    });

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }

    var leadReplyHourlyBase = '<?php echo base_url('Report/Lead_Reply_Activity_Hourly'); ?>';

    // Mirror of the PHP format_duration_label used for the per-row Avg Response Time.
    function formatLeadReplyDuration(seconds) {
        if (seconds === null || seconds === undefined || seconds === '') { return '-'; }
        seconds = parseInt(seconds, 10);
        if (isNaN(seconds)) { return '-'; }
        if (seconds < 60) { return seconds + ' sec'; }
        if (seconds < 3600) { return Math.floor(seconds / 60) + ' min'; }
        if (seconds < 86400) {
            var h = Math.floor(seconds / 3600), m = Math.floor((seconds % 3600) / 60);
            return m === 0 ? h + ' hr' : h + ' hr ' + m + ' min';
        }
        var d = Math.floor(seconds / 86400), rh = Math.floor((seconds % 86400) / 3600);
        return rh === 0 ? d + ' day' : d + ' day ' + rh + ' hr';
    }

    function renderLeadReplyActivityRows(rows) {
        var html = '';

        $('#lead-reply-activity-table-foot').remove();

        if (!rows || rows.length === 0) {
            $('#lead-reply-activity-table-body').html('<tr><td colspan="9" class="text-center py-10">Lead reply activity not found for the selected filters.</td></tr>');
            return;
        }

        var replyDate = $('input[name="reply_date"]').val() || '';

        var totalNewLeads = 0, totalResponded = 0, totalTransfer = 0, totalHelped = 0, totalHandling = 0;
        var weightedSeconds = 0, weightedLeads = 0;

        $.each(rows, function(index, row) {
            var newLeadsPickedUp = Number(row.new_leads_picked_up) || 0;
            var leadResponded = Number(row.lead_responded) || 0;
            var transferOutLeads = Number(row.transfer_out_leads) || 0;
            var helpedReplyLeads = Number(row.helped_reply_leads) || 0;
            var todayHandlingLeads = Number(row.today_handling_leads) || 0;
            totalNewLeads += newLeadsPickedUp;
            totalResponded += leadResponded;
            totalTransfer += transferOutLeads;
            totalHelped += helpedReplyLeads;
            totalHandling += todayHandlingLeads;
            if (row.avg_response_time_seconds !== null && row.avg_response_time_seconds !== undefined && leadResponded > 0) {
                weightedSeconds += Number(row.avg_response_time_seconds) * leadResponded;
                weightedLeads += leadResponded;
            }
            var hourlyUrl = leadReplyHourlyBase + '?owner=' + encodeURIComponent(row.owner_user_id) + '&reply_date=' + encodeURIComponent(replyDate);
            html += '<tr>';
            html += '<td class="text-center">' + (index + 1) + '</td>';
            html += '<td class="font-weight-bold text-dark">' + escapeHtml(row.owner_name) + '</td>';
            html += '<td class="text-center">' + lraCountCell(newLeadsPickedUp, row.owner_user_id, row.owner_name, 'picked_up') + '</td>';
            html += '<td class="text-center">' + lraCountCell(leadResponded, row.owner_user_id, row.owner_name, 'responded') + '</td>';
            html += '<td class="text-center">' + lraCountCell(transferOutLeads, row.owner_user_id, row.owner_name, 'transfer_out') + '</td>';
            html += '<td class="text-center">' + lraCountCell(helpedReplyLeads, row.owner_user_id, row.owner_name, 'helped_reply') + '</td>';
            html += '<td class="text-center font-weight-bold text-dark">' + lraCountCell(todayHandlingLeads, row.owner_user_id, row.owner_name, 'today_handling') + '</td>';
            html += '<td class="text-center">' + escapeHtml(row.avg_response_time_label || '-') + '</td>';
            html += '<td class="text-center"><a href="' + hourlyUrl + '" class="btn btn-sm btn-light-primary font-weight-bold" data-toggle="tooltip" title="View inbound/outbound per hour for this owner"><i class="la la-clock-o"></i> Hourly</a></td>';
            html += '</tr>';
        });

        $('#lead-reply-activity-table-body').html(html);

        var avgTotalSeconds = weightedLeads > 0 ? Math.round(weightedSeconds / weightedLeads) : null;
        var footHtml = '<tr style="background:#e4edf5; font-weight:bold; color:#2f506f;">';
        footHtml += '<td class="text-right" colspan="2">Total</td>';
        footHtml += '<td class="text-center">' + totalNewLeads.toLocaleString() + '</td>';
        footHtml += '<td class="text-center">' + totalResponded.toLocaleString() + '</td>';
        footHtml += '<td class="text-center">' + totalTransfer.toLocaleString() + '</td>';
        footHtml += '<td class="text-center">' + totalHelped.toLocaleString() + '</td>';
        footHtml += '<td class="text-center">' + totalHandling.toLocaleString() + '</td>';
        footHtml += '<td class="text-center">' + escapeHtml(formatLeadReplyDuration(avgTotalSeconds)) + ' <i class="la la-info-circle ml-1" style="cursor:help; color:#2f506f;" data-toggle="tooltip" title="Average response time across all owners, weighted by each owner\'s Lead Responded count (owners with no measured response time are ignored)."></i></td>';
        footHtml += '<td></td>';
        footHtml += '</tr>';
        $('#lead-reply-activity-table').append('<tfoot id="lead-reply-activity-table-foot">' + footHtml + '</tfoot>');

        $('#lead-reply-activity-table [data-toggle="tooltip"]').tooltip({ container: 'body', boundary: 'viewport', trigger: 'hover' });
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
