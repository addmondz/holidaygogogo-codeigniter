<style>
    .reply-detail-summary {
        display: grid;
        grid-template-columns: repeat(2, minmax(160px, 1fr));
        gap: 12px;
    }

    .reply-detail-metric {
        border: 1px solid #e5e9f2;
        border-radius: 6px;
        padding: 14px 16px;
        background: #fff;
    }

    .reply-detail-metric .label-text {
        color: #7e8299;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .reply-detail-metric .value-text {
        color: #263238;
        font-size: 26px;
        font-weight: 700;
        line-height: 1.2;
        margin-top: 4px;
    }

    @media (max-width: 991px) {
        .reply-detail-summary {
            grid-template-columns: repeat(2, minmax(140px, 1fr));
        }
    }

    @media (max-width: 575px) {
        .reply-detail-summary {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-4" style="background:linear-gradient(135deg, #e4edf5 0%, #f5f8fb 100%);">
                <div class="card-title">
                    <div>
                        <h3 class="card-label mb-1" style="color:#2f506f;">
                            <strong>Lead Reply Activity Details</strong>
                        </h3>
                        <div class="text-muted font-size-sm">
                            Details for assigned leads and reply-created leads on the selected date.
                        </div>
                    </div>
                </div>
                <div class="card-toolbar">
                    <span class="label label-light-primary label-inline font-weight-bold">
                        Updated <?php echo !empty($reply_activity_updated_at) ? html_escape($reply_activity_updated_at) : 'Not available'; ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="accordion accordion-solid accordion-toggle-plus">
                    <div class="card">
                        <div class="card-header">
                            <div id="reply_activity_detail_header" data-toggle="collapse" data-target="#reply_activity_detail_filters" class="card-title" style="font-size:13px;">Filter Reply Activity Details</div>
                        </div>
                        <div id="reply_activity_detail_filters" class="collapse show">
                            <div class="card-body">
                                <form id="reply-activity-detail-form" action="<?php echo base_url('Report/Lead_Reply_Activity_Dashboard_Details'); ?>" method="get" class="form">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Reply Date
                                                    <a onclick="resetReplyActivityDetailDate()" class="btn btn-icon btn-light-warning btn-xs">
                                                        <i class="la la-undo"></i>
                                                    </a>
                                                </label>
                                                <div id="reply_activity_detail_daterangepicker" class="input-icon">
                                                    <input readonly type="text" name="reply_date" value="<?php echo html_escape($reply_activity_detail_filters['reply_date']); ?>" autocomplete="off" class="form-control">
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
                                                    <?php foreach($reply_activity_detail_agents as $agent) { ?>
                                                        <option data-icon="la la-user font-size-lg bs-icon" value="<?php echo html_escape($agent->agent_id); ?>" <?php if(in_array((string) $agent->agent_id, $reply_activity_detail_filters['owner'], true)) { echo 'selected'; } ?>>
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
                                                    <?php foreach($reply_activity_detail_team_leads as $tl) { ?>
                                                        <option data-icon="la la-user-tie font-size-lg bs-icon" value="<?php echo html_escape($tl->AdminID); ?>" <?php if(in_array((string) $tl->AdminID, $reply_activity_detail_filters['team_lead'], true)) { echo 'selected'; } ?>>
                                                            <?php echo html_escape($tl->Name); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Activity Type</label>
                                                <select name="lead_type" class="form-control selectpicker">
                                                    <option value="" <?php if($reply_activity_detail_filters['lead_type'] === '') { echo 'selected'; } ?>>--ALL ACTIVITY--</option>
                                                    <option value="assigned" <?php if($reply_activity_detail_filters['lead_type'] === 'assigned') { echo 'selected'; } ?>>Assigned Lead</option>
                                                    <option value="reply_created" <?php if($reply_activity_detail_filters['lead_type'] === 'reply_created') { echo 'selected'; } ?>>Reply-Created Lead</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="submit" value="Filter" class="btn btn-light-primary font-weight-bold" style="width:80px;">
                                    <input type="button" id="reply-activity-detail-reset" value="Reset" class="btn btn-light-success font-weight-bold" style="width:80px;">
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="reply-detail-summary mt-8">
                    <div class="reply-detail-metric">
                        <div class="label-text">Assigned Leads</div>
                        <div class="value-text"><?php echo number_format($reply_activity_detail_summary['assigned_leads']); ?></div>
                    </div>
                    <div class="reply-detail-metric">
                        <div class="label-text">Reply-Created Leads</div>
                        <div class="value-text"><?php echo number_format($reply_activity_detail_summary['reply_created_leads']); ?></div>
                    </div>
                </div>

                <div class="table-responsive mt-8">
                    <table class="table table-bordered table-head-custom table-checkable">
                        <thead>
                            <tr>
                                <th style="text-align:center;">No.</th>
                                <th>Contact</th>
                                <th>Owner</th>
                                <th>Assigned</th>
                                <th style="text-align:center;">Activity Type</th>
                                <th>Activity Date</th>
                                <th>Lead Started</th>
                                <th>Reply Created</th>
                                <th style="text-align:center;">Replies</th>
                                <th style="text-align:center;">Messages</th>
                                <th style="text-align:center;">Status</th>
                                <th style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($reply_activity_detail_rows)) { ?>
                                <tr>
                                    <td colspan="12" class="text-center py-10">No lead activity found for the selected filters.</td>
                                </tr>
                            <?php } else { ?>
                                <?php $count = 1; ?>
                                <?php foreach($reply_activity_detail_rows as $row) { ?>
                                    <tr>
                                        <td class="text-center align-middle"><?php echo $count; ?></td>
                                        <td class="align-middle">
                                            <div class="font-weight-bold text-dark"><?php echo html_escape($row['contact_name']); ?></div>
                                            <div class="text-muted font-size-sm"><?php echo !empty($row['phone']) ? html_escape($row['phone']) : 'No phone'; ?></div>
                                            <div class="text-muted font-size-sm"><?php echo html_escape($row['conversation_id']); ?></div>
                                        </td>
                                        <td class="align-middle font-weight-bold"><?php echo html_escape($row['owner_name']); ?></td>
                                        <td class="align-middle"><?php echo html_escape($row['assigned_name']); ?></td>
                                        <td class="text-center align-middle">
                                            <span class="label <?php echo html_escape($row['activity_type_class']); ?> label-inline font-weight-bold"><?php echo html_escape($row['activity_type']); ?></span>
                                        </td>
                                        <td class="align-middle"><?php echo html_escape($row['activity_at_label']); ?></td>
                                        <td class="align-middle"><?php echo html_escape($row['lead_started_at_label']); ?></td>
                                        <td class="align-middle"><?php echo html_escape($row['reply_created_at_label']); ?></td>
                                        <td class="text-center align-middle"><?php echo number_format($row['outbound_replies']); ?></td>
                                        <td class="text-center align-middle"><?php echo number_format($row['message_count']); ?></td>
                                        <td class="text-center align-middle">
                                            <span class="label <?php echo html_escape($row['follow_up_status_class']); ?> label-inline font-weight-bold"><?php echo html_escape($row['follow_up_status_label']); ?></span>
                                            <div class="mt-2">
                                                <span class="label <?php echo $row['conversion_status_label'] === 'Converted' ? 'label-light-success' : 'label-light-secondary'; ?> label-inline font-weight-bold"><?php echo html_escape($row['conversion_status_label']); ?></span>
                                            </div>
                                            <?php if(!empty($row['booking_id'])) { ?>
                                                <div class="mt-2">
                                                    <a href="<?php echo html_escape($row['booking_url']); ?>" target="_blank" class="font-size-sm">
                                                        <?php echo !empty($row['booking_number']) ? html_escape($row['booking_number']) : 'Booking #' . html_escape($row['booking_id']); ?>
                                                    </a>
                                                </div>
                                            <?php } ?>
                                        </td>
                                        <td class="text-center align-middle">
                                            <a href="<?php echo html_escape($row['lead_data_url']); ?>" target="_blank" class="btn btn-sm btn-light-primary font-weight-bold">Lead Data</a>
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
    $('#reply_activity_detail_daterangepicker').daterangepicker({
        buttonClasses: ' btn',
        applyClass: 'btn-primary',
        cancelClass: 'btn-secondary',
        autoApply: true,
        singleDatePicker: true,
        locale: {
            format: 'DD/MM/YYYY'
        }
    }, function(start) {
        $('#reply_activity_detail_daterangepicker .form-control').val(start.format('DD/MM/YYYY'));
    });

    $('#reply_activity_detail_daterangepicker').on('apply.daterangepicker', function(event, daterange) {
        $('input[name="reply_date"]').val(daterange.startDate.format('DD/MM/YYYY'));
    });

    function resetReplyActivityDetailDate() {
        $('input[name="reply_date"]').val('');
    }

    $('#reply-activity-detail-reset').click(function() {
        Reset('<?php echo base_url('Report/Lead_Reply_Activity_Dashboard_Details'); ?>');
    });
</script>
