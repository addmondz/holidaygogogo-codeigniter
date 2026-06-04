<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-4" style="background:linear-gradient(135deg, #dfeadf 0%, #f3f8f1 100%);">
                <div class="card-title">
                    <div>
                        <h3 class="card-label mb-1" style="color:#36613a;">
                            <strong>Lead Ownership Data</strong>
                        </h3>
                        <div class="text-muted font-size-sm">
                            Owned lead details by current assigned owner or non-assigned reply owner.
                        </div>
                    </div>
                </div>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('Report/Lead_Ownership_Dashboard'); ?>" class="btn btn-light-primary font-weight-bold mr-2">Dashboard</a>
                    <span class="label label-light-success label-inline font-weight-bold">
                        Calculated <?php echo !empty($lead_ownership_data_updated_at) ? html_escape($lead_ownership_data_updated_at) : 'Not available'; ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="accordion accordion-solid accordion-toggle-plus">
                    <div class="card">
                        <div class="card-header">
                            <div id="lead_ownership_data_header" data-toggle="collapse" data-target="#lead_ownership_data_filters" class="card-title" style="font-size:13px;">Filter Ownership Data</div>
                        </div>
                        <div id="lead_ownership_data_filters" class="collapse show">
                            <div class="card-body">
                                <form id="lead-ownership-data-form" action="<?php echo base_url('Report/Lead_Ownership_Data'); ?>" method="get" class="form">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Lead Date Range
                                                    <a onclick="resetLeadOwnershipDataDate()" class="btn btn-icon btn-light-warning btn-xs">
                                                        <i class="la la-undo"></i>
                                                    </a>
                                                </label>
                                                <div id="lead_ownership_data_daterangepicker" class="input-icon">
                                                    <input readonly type="text" name="lead_date" value="<?php echo html_escape($lead_ownership_data_filters['lead_date']); ?>" autocomplete="off" class="form-control">
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
                                                    <?php foreach($lead_ownership_data_agents as $agent) { ?>
                                                        <option data-icon="la la-user font-size-lg bs-icon" value="<?php echo html_escape($agent->agent_id); ?>" <?php if(in_array((string) $agent->agent_id, $lead_ownership_data_filters['owner'], true)) { echo 'selected'; } ?>>
                                                            <?php echo html_escape($agent->agent_name); ?>
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
                                                    <option value="assigned" <?php if($lead_ownership_data_filters['ownership_type'] === 'assigned') { echo 'selected'; } ?>>Assigned Owned</option>
                                                    <option value="reply" <?php if($lead_ownership_data_filters['ownership_type'] === 'reply') { echo 'selected'; } ?>>Reply Owned</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Rows Per Page</label>
                                                <select name="per_page" id="lead-ownership-data-per-page" class="form-control selectpicker">
                                                    <option value="25" <?php if((int) $lead_ownership_data_filters['per_page'] === 25) { echo 'selected'; } ?>>25</option>
                                                    <option value="50" <?php if((int) $lead_ownership_data_filters['per_page'] === 50) { echo 'selected'; } ?>>50</option>
                                                    <option value="100" <?php if((int) $lead_ownership_data_filters['per_page'] === 100) { echo 'selected'; } ?>>100</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="submit" value="Filter" class="btn btn-light-success font-weight-bold" style="width:80px;">
                                    <input type="button" id="lead-ownership-data-reset" value="Reset" class="btn btn-light-primary font-weight-bold" style="width:80px;">
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive mt-8">
                    <table class="table table-bordered table-head-custom table-checkable">
                        <thead>
                            <tr>
                                <th style="text-align:center;">No.</th>
                                <th>Contact</th>
                                <th>Owner</th>
                                <th style="text-align:center;">Reason</th>
                                <th>Current Assigned</th>
                                <th>Lead Started</th>
                                <th style="text-align:center;">Reply Count</th>
                                <th style="text-align:center;">Response</th>
                                <th style="text-align:center;">Conversion</th>
                                <th style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($lead_ownership_data_rows)) { ?>
                                <tr>
                                    <td colspan="10" class="text-center py-10">No owned leads found for the selected filters.</td>
                                </tr>
                            <?php } else { ?>
                                <?php $count = $lead_ownership_data_pagination['start_row']; ?>
                                <?php foreach($lead_ownership_data_rows as $row) { ?>
                                    <tr>
                                        <td class="text-center align-middle"><?php echo $count; ?></td>
                                        <td class="align-middle">
                                            <div class="font-weight-bold text-dark"><?php echo html_escape($row['contact_name']); ?></div>
                                            <div class="text-muted font-size-sm"><?php echo !empty($row['phone']) ? html_escape($row['phone']) : 'No phone'; ?></div>
                                            <div class="text-muted font-size-sm"><?php echo html_escape($row['conversation_id']); ?></div>
                                        </td>
                                        <td class="align-middle">
                                            <div class="font-weight-bold"><?php echo html_escape($row['owner_name']); ?></div>
                                        </td>
                                        <td class="text-center align-middle">
                                            <span class="label <?php echo html_escape($row['ownership_class']); ?> label-inline font-weight-bold"><?php echo html_escape($row['ownership_label']); ?></span>
                                        </td>
                                        <td class="align-middle"><?php echo html_escape($row['assigned_name']); ?></td>
                                        <td class="align-middle">
                                            <div class="font-weight-bold"><?php echo html_escape($row['lead_started_at_label']); ?></div>
                                            <?php if(!empty($row['lead_ended_at'])) { ?>
                                                <div class="text-muted font-size-sm">Ended: <?php echo html_escape($row['lead_ended_at_label']); ?></div>
                                            <?php } ?>
                                        </td>
                                        <td class="text-center align-middle"><?php echo number_format($row['outbound_reply_count']); ?></td>
                                        <td class="text-center align-middle">
                                            <div class="font-weight-bold"><?php echo html_escape($row['avg_first_5_response_label']); ?></div>
                                            <div class="text-muted font-size-sm"><?php echo html_escape($row['response_progress_label']); ?> replied</div>
                                            <div class="text-muted font-size-sm">Last 5: <?php echo html_escape($row['avg_recent_5_response_label']); ?></div>
                                        </td>
                                        <td class="text-center align-middle">
                                            <?php if((int) $row['is_converted'] === 1) { ?>
                                                <span class="label label-light-success label-inline font-weight-bold">Converted</span>
                                                <?php if(!empty($row['booking_id'])) { ?>
                                                    <div class="mt-2">
                                                        <a href="<?php echo html_escape($row['booking_url']); ?>" class="font-size-sm" target="_blank">
                                                            <?php echo !empty($row['booking_number']) ? html_escape($row['booking_number']) : 'Booking #' . html_escape($row['booking_id']); ?>
                                                        </a>
                                                    </div>
                                                <?php } ?>
                                            <?php } else { ?>
                                                <span class="label label-light-warning label-inline font-weight-bold">Open</span>
                                            <?php } ?>
                                        </td>
                                        <td class="text-center align-middle">
                                            <a href="<?php echo html_escape($row['lead_data_url']); ?>" class="btn btn-light-primary btn-sm font-weight-bold" target="_blank">Lead Data</a>
                                        </td>
                                    </tr>
                                    <?php $count++; ?>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mt-6">
                    <div class="text-muted mb-3 mb-md-0">
                        <?php if($lead_ownership_data_pagination['total_rows'] > 0) { ?>
                            Showing <?php echo number_format($lead_ownership_data_pagination['start_row']); ?> to <?php echo number_format($lead_ownership_data_pagination['end_row']); ?> of <?php echo number_format($lead_ownership_data_pagination['total_rows']); ?> owned leads
                        <?php } else { ?>
                            Showing 0 owned leads
                        <?php } ?>
                    </div>

                    <?php if($lead_ownership_data_pagination['total_pages'] > 1) { ?>
                        <div class="d-flex flex-wrap align-items-center">
                            <a href="<?php echo html_escape($lead_ownership_data_pagination['first_url']); ?>" class="btn btn-sm btn-light mr-2 mb-2 <?php if(!$lead_ownership_data_pagination['has_previous']) { echo 'disabled'; } ?>">First</a>
                            <a href="<?php echo $lead_ownership_data_pagination['has_previous'] ? html_escape($lead_ownership_data_pagination['previous_url']) : '#'; ?>" class="btn btn-sm btn-light mr-2 mb-2 <?php if(!$lead_ownership_data_pagination['has_previous']) { echo 'disabled'; } ?>">Previous</a>

                            <?php foreach($lead_ownership_data_pagination['pages'] as $pageItem) { ?>
                                <a href="<?php echo html_escape($pageItem['url']); ?>" class="btn btn-sm mr-2 mb-2 <?php echo $pageItem['is_current'] ? 'btn-primary' : 'btn-light'; ?>">
                                    <?php echo $pageItem['page']; ?>
                                </a>
                            <?php } ?>

                            <a href="<?php echo $lead_ownership_data_pagination['has_next'] ? html_escape($lead_ownership_data_pagination['next_url']) : '#'; ?>" class="btn btn-sm btn-light mr-2 mb-2 <?php if(!$lead_ownership_data_pagination['has_next']) { echo 'disabled'; } ?>">Next</a>
                            <a href="<?php echo html_escape($lead_ownership_data_pagination['last_url']); ?>" class="btn btn-sm btn-light mb-2 <?php if(!$lead_ownership_data_pagination['has_next']) { echo 'disabled'; } ?>">Last</a>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var leadOwnershipDataCurrentDate = (new Date()).toLocaleDateString();

    $('#lead_ownership_data_daterangepicker').daterangepicker({
        buttonClasses: ' btn',
        applyClass: 'btn-primary',
        cancelClass: 'btn-secondary',
        autoApply: true
    }, function(start, end) {
        $('#lead_ownership_data_daterangepicker .form-control').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
    });

    $('#lead_ownership_data_daterangepicker').on('apply.daterangepicker', function(event, daterange) {
        var startDate = (new Date(daterange.startDate._d)).toLocaleDateString();
        var endDate = (new Date(daterange.endDate._d)).toLocaleDateString();

        if (startDate == leadOwnershipDataCurrentDate && endDate == leadOwnershipDataCurrentDate) {
            $('input[name="lead_date"]').val(moment().format('DD/MM/YYYY') + ' - ' + moment().format('DD/MM/YYYY'));
        }
    });

    function resetLeadOwnershipDataDate() {
        $('input[name="lead_date"]').val('');
    }

    $('#lead-ownership-data-reset').click(function() {
        Reset('<?php echo base_url('Report/Lead_Ownership_Data'); ?>');
    });

    $('#lead-ownership-data-per-page').on('changed.bs.select', function() {
        $('#lead-ownership-data-form').submit();
    });
</script>
