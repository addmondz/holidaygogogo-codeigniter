<style>
    .lead-thread-row {
        background: #f7efe3;
    }

    .lead-thread-panel {
        background: #fffdf9;
        border-top: 1px solid #e7d8c5;
        border-left: 4px solid #c58a3d;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
        padding-top: 1rem !important;
        padding-bottom: 1rem !important;
    }

    .lead-sort-link {
        color: inherit;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .lead-sort-link:hover {
        color: #7a4f2a;
        text-decoration: none;
    }

    .lead-sort-icon {
        font-size: 11px;
        color: #8c8c8c;
    }

    .lead-tag-list {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        min-width: 160px;
    }

    .lead-tag {
        display: inline-flex;
        align-items: center;
        max-width: 180px;
        border-radius: 4px;
        padding: 3px 7px;
        background: #edf4ff;
        color: #27527a;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.2;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .lead-chat-thread {
        max-width: 760px;
        margin: 0 auto;
    }

    .lead-chat-row {
        display: flex;
        align-items: flex-end;
        gap: 8px;
        margin-bottom: 10px;
    }

    .lead-chat-row.outbound {
        justify-content: flex-end;
    }

    .lead-chat-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 28px;
        font-size: 14px;
    }

    .lead-chat-row.inbound .lead-chat-avatar {
        background: #dff3e6;
        color: #2e7d4f;
    }

    .lead-chat-row.outbound .lead-chat-avatar {
        background: #dfeafc;
        color: #355caa;
        order: 2;
    }

    .lead-chat-bubble-wrap {
        max-width: min(78%, 520px);
        display: flex;
        flex-direction: column;
    }

    .lead-chat-row.outbound .lead-chat-bubble-wrap {
        align-items: flex-end;
    }

    .lead-chat-meta {
        font-size: 11px;
        color: #7b7b7b;
        margin-bottom: 3px;
        line-height: 1.3;
    }

    .lead-chat-row.outbound .lead-chat-meta {
        text-align: right;
    }

    .lead-chat-bubble {
        border-radius: 14px;
        padding: 8px 11px;
        font-size: 12px;
        line-height: 1.45;
        word-break: break-word;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
    }

    .lead-chat-row.inbound .lead-chat-bubble {
        background: #ffffff;
        border: 1px solid #dbe6d8;
        color: #2f2f2f;
    }

    .lead-chat-row.outbound .lead-chat-bubble {
        background: #e8f0ff;
        border: 1px solid #c9dafc;
        color: #1f3359;
    }

    .lead-chat-empty {
        max-width: 520px;
        margin: 0 auto;
        font-size: 12px;
        color: #7b7b7b;
        text-align: center;
        padding: 12px 10px;
        background: #fff;
        border: 1px dashed #d7c7b3;
        border-radius: 12px;
    }

    .lead-chat-modal .modal-dialog {
        max-width: 900px;
    }

    .lead-chat-modal .modal-content {
        border-radius: 14px;
        overflow: hidden;
    }

    .lead-chat-modal .modal-header {
        background: linear-gradient(135deg, #f3e7d3 0%, #fbf7ef 100%);
        border-bottom: 1px solid #e7d8c5;
    }

    .lead-chat-modal-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        margin-top: 6px;
    }

    .lead-chat-modal .modal-body {
        background: #f8f4ed;
        max-height: 70vh;
        overflow-y: auto;
    }

    body.lead-modal-scroll-lock,
    body.modal-open {
        overflow: hidden !important;
    }
</style>

<?php
$leadCurrentSortBy = isset($lead_data_sorting['current_sort_by']) ? $lead_data_sorting['current_sort_by'] : 'lead_started_at';
$leadCurrentSortDir = isset($lead_data_sorting['current_sort_dir']) ? $lead_data_sorting['current_sort_dir'] : 'desc';
$leadSortIcon = function($column) use ($leadCurrentSortBy, $leadCurrentSortDir) {
    if ($leadCurrentSortBy !== $column) {
        return 'la la-sort lead-sort-icon';
    }

    return $leadCurrentSortDir === 'asc'
        ? 'la la-sort-amount-up lead-sort-icon'
        : 'la la-sort-amount-down lead-sort-icon';
};
?>

<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-4" style="background:linear-gradient(135deg, #f3e7d3 0%, #fbf7ef 100%);">
                <div class="card-title">
                    <div>
                        <h3 class="card-label mb-1" style="color:#7a4f2a;">
                            <strong>Lead Data</strong>
                        </h3>
                        <div class="text-muted font-size-sm">
                            Review every lead, filter by conversation or agent, and inspect both the first five and last five response timings.
                        </div>
                    </div>
                </div>
                <div class="card-toolbar">
                    <span class="label label-light-primary label-inline font-weight-bold">
                        Last Synced <?php echo !empty($lead_data_updated_at) ? html_escape($lead_data_updated_at) : 'Not available'; ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="accordion accordion-solid accordion-toggle-plus">
                    <div class="card">
                        <div class="card-header">
                            <div id="lead_data_header" data-toggle="collapse" data-target="#lead_data_filters" class="card-title collapsed" style="font-size:13px;">Filter Lead Data</div>
                        </div>
                        <div id="lead_data_filters" class="collapse">
                            <div class="card-body">
                                <form id="lead-data-form" action="<?php echo base_url('Report/Lead_Data'); ?>" method="get" class="form">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Lead Date Range
                                                    <a onclick="resetLeadDataDate()" class="btn btn-icon btn-light-warning btn-xs">
                                                        <i class="la la-undo"></i>
                                                    </a>
                                                </label>
                                                <div id="lead_data_daterangepicker" class="input-icon">
                                                    <input readonly type="text" name="lead_date" value="<?php echo html_escape($lead_data_filters['lead_date']); ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-calendar"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Sales Agent</label>
                                                <select name="sales_agent" data-live-search="true" class="form-control selectpicker">
                                                    <option value="">--ALL SALES AGENTS--</option>
                                                    <?php foreach($lead_data_agents as $agent) { ?>
                                                        <option value="<?php echo html_escape($agent->agent_id); ?>" <?php if($lead_data_filters['sales_agent'] === $agent->agent_id) { echo 'selected'; } ?>>
                                                            <?php echo html_escape($agent->agent_name); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Conversation ID</label>
                                                <input type="text" name="conversation_id" value="<?php echo html_escape($lead_data_filters['conversation_id']); ?>" class="form-control" placeholder="Search exact conversation id">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Contact Name</label>
                                                <input type="text" name="contact_name" value="<?php echo html_escape($lead_data_filters['contact_name']); ?>" class="form-control" placeholder="Search contact name">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Response Status</label>
                                                <select name="response_status" class="form-control selectpicker">
                                                    <option value="">--ALL--</option>
                                                    <option value="responded" <?php if($lead_data_filters['response_status'] === 'responded') { echo 'selected'; } ?>>Responded</option>
                                                    <option value="pending" <?php if($lead_data_filters['response_status'] === 'pending') { echo 'selected'; } ?>>No Reply Yet</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Conversion Status</label>
                                                <select name="conversion_status" class="form-control selectpicker">
                                                    <option value="">--ALL--</option>
                                                    <option value="converted" <?php if($lead_data_filters['conversion_status'] === 'converted') { echo 'selected'; } ?>>Converted</option>
                                                    <option value="open" <?php if($lead_data_filters['conversion_status'] === 'open') { echo 'selected'; } ?>>Open</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Phone Number</label>
                                                <input type="text" name="phone" value="<?php echo html_escape($lead_data_filters['phone']); ?>" class="form-control" placeholder="Search phone number">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Tag</label>
                                                <select name="tag" data-live-search="true" class="form-control selectpicker">
                                                    <option value="">--ALL TAGS--</option>
                                                    <?php foreach($lead_data_tags as $tag) { ?>
                                                        <option value="<?php echo html_escape($tag); ?>" <?php if($lead_data_filters['tag'] === $tag) { echo 'selected'; } ?>>
                                                            <?php echo html_escape($tag); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Rows Per Page</label>
                                                <select name="per_page" id="lead-data-per-page" class="form-control selectpicker">
                                                    <option value="25" <?php if((int) $lead_data_filters['per_page'] === 25) { echo 'selected'; } ?>>25</option>
                                                    <option value="50" <?php if((int) $lead_data_filters['per_page'] === 50) { echo 'selected'; } ?>>50</option>
                                                    <option value="100" <?php if((int) $lead_data_filters['per_page'] === 100) { echo 'selected'; } ?>>100</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="sort_by" value="<?php echo html_escape($lead_data_filters['sort_by']); ?>">
                                    <input type="hidden" name="sort_dir" value="<?php echo html_escape($lead_data_filters['sort_dir']); ?>">
                                    <input type="submit" value="Filter" class="btn btn-light-success font-weight-bold" style="width:80px;">
                                    <input type="button" id="lead-data-reset" value="Reset" class="btn btn-light-primary font-weight-bold" style="width:80px;">
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-8 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-custom gutter-b shadow-sm">
                            <div class="card-body">
                                <div class="text-muted text-uppercase font-size-sm font-weight-bold mb-2">Total Leads</div>
                                <div class="font-weight-bolder font-size-h2 text-dark"><?php echo number_format($lead_data_summary['total_leads']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-custom gutter-b shadow-sm">
                            <div class="card-body">
                                <div class="text-muted text-uppercase font-size-sm font-weight-bold mb-2">Responded Leads</div>
                                <div class="font-weight-bolder font-size-h2 text-info"><?php echo number_format($lead_data_summary['responded_leads']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-custom gutter-b shadow-sm">
                            <div class="card-body">
                                <div class="text-muted text-uppercase font-size-sm font-weight-bold mb-2">Converted Leads</div>
                                <div class="font-weight-bolder font-size-h2 text-success"><?php echo number_format($lead_data_summary['converted_leads']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-custom gutter-b shadow-sm">
                            <div class="card-body">
                                <div class="text-muted text-uppercase font-size-sm font-weight-bold mb-2">Average Response</div>
                                <div class="font-weight-bolder font-size-h2 text-primary"><?php echo html_escape($lead_data_summary['avg_response_time_label']); ?></div>
                                <div class="text-muted mt-2">First 5 avg of tracked replied messages</div>
                                <div class="text-muted mt-1">Last 5 avg: <?php echo html_escape($lead_data_summary['avg_recent_response_time_label']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-head-custom table-checkable">
                        <thead>
                            <tr>
                                <th style="text-align:center;">No.</th>
                                <th>
                                    <a href="<?php echo html_escape($lead_data_sorting['links']['contact_name']); ?>" class="lead-sort-link">
                                        Contact
                                        <i class="<?php echo $leadSortIcon('contact_name'); ?>"></i>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?php echo html_escape($lead_data_sorting['links']['agent_name']); ?>" class="lead-sort-link">
                                        Sales Agent
                                        <i class="<?php echo $leadSortIcon('agent_name'); ?>"></i>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?php echo html_escape($lead_data_sorting['links']['conversation_id']); ?>" class="lead-sort-link">
                                        Conversation ID
                                        <i class="<?php echo $leadSortIcon('conversation_id'); ?>"></i>
                                    </a>
                                </th>
                                <th>Tags</th>
                                <th>
                                    <a href="<?php echo html_escape($lead_data_sorting['links']['lead_started_at']); ?>" class="lead-sort-link">
                                        Lead Started
                                        <i class="<?php echo $leadSortIcon('lead_started_at'); ?>"></i>
                                    </a>
                                </th>
                                <th style="text-align:center;">
                                    <a href="<?php echo html_escape($lead_data_sorting['links']['response_status']); ?>" class="lead-sort-link justify-content-center">
                                        Response Status
                                        <i class="<?php echo $leadSortIcon('response_status'); ?>"></i>
                                    </a>
                                </th>
                                <th style="text-align:center;">
                                    <a href="<?php echo html_escape($lead_data_sorting['links']['response_time']); ?>" class="lead-sort-link justify-content-center">
                                        Avg First 5 Response
                                        <i class="<?php echo $leadSortIcon('response_time'); ?>"></i>
                                    </a>
                                </th>
                                <th style="text-align:center;">Avg Last 5 Response</th>
                                <th style="text-align:center;">
                                    <a href="<?php echo html_escape($lead_data_sorting['links']['conversion_status']); ?>" class="lead-sort-link justify-content-center">
                                        Conversion
                                        <i class="<?php echo $leadSortIcon('conversion_status'); ?>"></i>
                                    </a>
                                </th>
                                <th style="text-align:center;">
                                    <a href="<?php echo html_escape($lead_data_sorting['links']['message_count']); ?>" class="lead-sort-link justify-content-center">
                                        Messages
                                        <i class="<?php echo $leadSortIcon('message_count'); ?>"></i>
                                    </a>
                                </th>
                                <th style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($lead_data_rows)) { ?>
                                <tr>
                                    <td colspan="12" class="text-center py-10">No lead records found for the selected filters.</td>
                                </tr>
                            <?php } else { ?>
                                <?php $count = $lead_data_pagination['start_row']; ?>
                                <?php foreach($lead_data_rows as $row) { ?>
                                    <?php $collapseId = 'lead-messages-' . $row['id']; ?>
                                    <tr>
                                        <td class="text-center align-middle"><?php echo $count; ?></td>
                                        <td class="align-middle">
                                            <div class="font-weight-bold text-dark"><?php echo html_escape($row['contact_name']); ?></div>
                                            <div class="text-muted font-size-sm"><?php echo !empty($row['phone']) ? html_escape($row['phone']) : 'No phone'; ?></div>
                                            <!-- <div class="text-muted font-size-sm">Contact ID: <?php echo !empty($row['contact_id']) ? html_escape($row['contact_id']) : '-'; ?></div> -->
                                        </td>
                                        <td class="align-middle">
                                            <div class="font-weight-bold"><?php echo html_escape($row['agent_name']); ?></div>
                                            <!-- <div class="text-muted font-size-sm"><?php echo $row['agent_id'] !== '__unassigned__' ? html_escape($row['agent_id']) : 'Unassigned'; ?></div> -->
                                        </td>
                                        <td class="align-middle">
                                            <div class="font-weight-bold"><?php echo html_escape($row['conversation_id']); ?></div>
                                        </td>
                                        <td class="align-middle">
                                            <?php if(!empty($row['tags'])) { ?>
                                                <div class="lead-tag-list">
                                                    <?php foreach($row['tags'] as $tag) { ?>
                                                        <span class="lead-tag" title="<?php echo html_escape($tag); ?>"><?php echo html_escape($tag); ?></span>
                                                    <?php } ?>
                                                </div>
                                            <?php } else { ?>
                                                <span class="text-muted font-size-sm">No tags</span>
                                            <?php } ?>
                                        </td>
                                        <td class="align-middle">
                                            <div class="font-weight-bold"><?php echo html_escape($row['lead_started_at_label']); ?></div>
                                            <?php if(!empty($row['lead_ended_at'])) { ?>
                                                <div class="text-muted font-size-sm">Ended: <?php echo html_escape($row['lead_ended_at_label']); ?></div>
                                            <?php } ?>
                                            <?php if(!empty($row['converted_at']) && (int) $row['is_converted'] === 1) { ?>
                                                <div class="text-muted font-size-sm">Converted: <?php echo html_escape($row['converted_at_label']); ?></div>
                                            <?php } ?>
                                        </td>
                                        <td class="text-center align-middle">
                                            <?php if((int) $row['responded_message_count'] > 0) { ?>
                                                <span class="label label-light-success label-inline font-weight-bold">Responded</span>
                                            <?php } else { ?>
                                                <span class="label label-light-warning label-inline font-weight-bold">Pending</span>
                                            <?php } ?>
                                            <div class="text-muted font-size-sm mt-2"><?php echo html_escape($row['response_progress_label']); ?> replied</div>
                                        </td>
                                        <td class="text-center align-middle">
                                            <?php if((int) $row['responded_message_count'] > 0) { ?>
                                                <div class="font-weight-bold text-dark"><?php echo html_escape($row['avg_first_5_response_label']); ?></div>
                                                <div class="text-muted font-size-sm mt-2">
                                                    R1 <?php echo html_escape($row['response_1_label']); ?> |
                                                    R2 <?php echo html_escape($row['response_2_label']); ?> |
                                                    R3 <?php echo html_escape($row['response_3_label']); ?> |
                                                    R4 <?php echo html_escape($row['response_4_label']); ?> |
                                                    R5 <?php echo html_escape($row['response_5_label']); ?>
                                                </div>
                                            <?php } else { ?>
                                                <div class="font-size-sm text-muted">No reply yet</div>
                                            <?php } ?>
                                        </td>
                                        <td class="text-center align-middle">
                                            <?php if((int) $row['recent_responded_message_count'] > 0) { ?>
                                                <div class="font-weight-bold text-dark"><?php echo html_escape($row['avg_recent_5_response_label']); ?></div>
                                                <div class="text-muted font-size-sm mt-2">
                                                    R1 <?php echo html_escape($row['recent_response_1_label']); ?> |
                                                    R2 <?php echo html_escape($row['recent_response_2_label']); ?> |
                                                    R3 <?php echo html_escape($row['recent_response_3_label']); ?> |
                                                    R4 <?php echo html_escape($row['recent_response_4_label']); ?> |
                                                    R5 <?php echo html_escape($row['recent_response_5_label']); ?>
                                                </div>
                                            <?php } else { ?>
                                                <div class="font-size-sm text-muted">No reply yet</div>
                                            <?php } ?>
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
                                            <span class="label label-light-info label-inline font-weight-bold"><?php echo number_format($row['message_count']); ?></span>
                                        </td>
                                        <td class="text-center align-middle">
                                            <button
                                                type="button"
                                                class="btn btn-light-primary btn-sm font-weight-bold lead-messages-toggle"
                                                data-toggle="modal"
                                                data-target="#leadMessagesModal"
                                                data-lead-id="<?php echo $row['id']; ?>"
                                                data-conversation-id="<?php echo html_escape($row['conversation_id']); ?>"
                                                data-lead-started-at="<?php echo html_escape($row['lead_started_at']); ?>"
                                                data-lead-ended-at="<?php echo !empty($row['lead_ended_at']) ? html_escape($row['lead_ended_at']) : ''; ?>"
                                                data-contact-name="<?php echo html_escape($row['contact_name']); ?>"
                                                data-agent-name="<?php echo html_escape($row['agent_name']); ?>"
                                                data-tags="<?php echo html_escape(json_encode($row['tags'])); ?>"
                                            >
                                                View Messages
                                            </button>
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
                        <?php if($lead_data_pagination['total_rows'] > 0) { ?>
                            Showing <?php echo number_format($lead_data_pagination['start_row']); ?> to <?php echo number_format($lead_data_pagination['end_row']); ?> of <?php echo number_format($lead_data_pagination['total_rows']); ?> leads
                        <?php } else { ?>
                            Showing 0 leads
                        <?php } ?>
                    </div>

                    <?php if($lead_data_pagination['total_pages'] > 1) { ?>
                        <div class="d-flex flex-wrap align-items-center">
                            <a href="<?php echo html_escape($lead_data_pagination['first_url']); ?>" class="btn btn-sm btn-light mr-2 mb-2 <?php if(!$lead_data_pagination['has_previous']) { echo 'disabled'; } ?>">First</a>
                            <a href="<?php echo $lead_data_pagination['has_previous'] ? html_escape($lead_data_pagination['previous_url']) : '#'; ?>" class="btn btn-sm btn-light mr-2 mb-2 <?php if(!$lead_data_pagination['has_previous']) { echo 'disabled'; } ?>">Previous</a>

                            <?php foreach($lead_data_pagination['pages'] as $pageItem) { ?>
                                <a href="<?php echo html_escape($pageItem['url']); ?>" class="btn btn-sm mr-2 mb-2 <?php echo $pageItem['is_current'] ? 'btn-primary' : 'btn-light'; ?>">
                                    <?php echo $pageItem['page']; ?>
                                </a>
                            <?php } ?>

                            <a href="<?php echo $lead_data_pagination['has_next'] ? html_escape($lead_data_pagination['next_url']) : '#'; ?>" class="btn btn-sm btn-light mr-2 mb-2 <?php if(!$lead_data_pagination['has_next']) { echo 'disabled'; } ?>">Next</a>
                            <a href="<?php echo html_escape($lead_data_pagination['last_url']); ?>" class="btn btn-sm btn-light mb-2 <?php if(!$lead_data_pagination['has_next']) { echo 'disabled'; } ?>">Last</a>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade lead-chat-modal" id="leadMessagesModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">Lead Messages</h5>
                    <div class="text-muted font-size-sm" id="lead-messages-modal-subtitle">Conversation</div>
                    <div class="lead-chat-modal-tags" id="lead-messages-modal-tags"></div>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="lead-thread-panel p-4">
                    <div id="lead-messages-modal-content" class="lead-messages-content">
                        <div class="lead-chat-empty">Open a lead to load messages.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var leadDataMessagesEndpoint = '<?php echo base_url('Report/Lead_Data_Messages'); ?>';
    var leadDataCurrentDate = (new Date()).toLocaleDateString();
    var leadMessagesModalContent = $('#lead-messages-modal-content');
    var leadMessagesModalSubtitle = $('#lead-messages-modal-subtitle');
    var leadMessagesModalTags = $('#lead-messages-modal-tags');

    $('#lead_data_daterangepicker').daterangepicker({
        buttonClasses: ' btn',
        applyClass: 'btn-primary',
        cancelClass: 'btn-secondary',
        autoUpdateInput: false,
        autoApply: true
    }, function(start, end) {
        $('#lead_data_daterangepicker .form-control').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
    });

    if ($('input[name="lead_date"]').val() !== '') {
        var currentLeadDate = $('input[name="lead_date"]').val().split(' - ');
        if (currentLeadDate.length === 2) {
            $('#lead_data_daterangepicker').data('daterangepicker').setStartDate(moment(currentLeadDate[0], 'DD/MM/YYYY'));
            $('#lead_data_daterangepicker').data('daterangepicker').setEndDate(moment(currentLeadDate[1], 'DD/MM/YYYY'));
            $('#lead_data_daterangepicker .form-control').val($('input[name="lead_date"]').val());
        }
    }

    $('#lead_data_daterangepicker').on('apply.daterangepicker', function(event, daterange) {
        var startDate = (new Date(daterange.startDate._d)).toLocaleDateString();
        var endDate = (new Date(daterange.endDate._d)).toLocaleDateString();

        if (startDate == leadDataCurrentDate && endDate == leadDataCurrentDate) {
            $('input[name="lead_date"]').val(moment().format('DD/MM/YYYY') + ' - ' + moment().format('DD/MM/YYYY'));
        }
    });

    function resetLeadDataDate() {
        $('input[name="lead_date"]').val('');
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }

    function nl2br(value) {
        return escapeHtml(value).replace(/\n/g, '<br>');
    }

    function renderMessageTimeline(messages) {
        if (!messages || messages.length === 0) {
            return '<div class="lead-chat-empty">No messages found inside this lead window.</div>';
        }

        var html = '<div class="lead-chat-thread">';

        $.each(messages, function(index, message) {
            var isInbound = message.direction === 'inbound';
            var rowClass = isInbound ? 'inbound' : 'outbound';
            var directionLabel = isInbound ? 'Customer' : 'Agent';
            var avatarIcon = isInbound ? 'la-user' : 'la-user-tie';
            var messageBody = $.trim(message.body || '') !== '' ? nl2br(message.body) : '<span class="text-muted">No text body</span>';
            var meta = [];

            if (message.user_name) {
                meta.push(escapeHtml(message.user_name));
            }

            if (message.message_type) {
                meta.push(escapeHtml(message.message_type));
            }

            if (message.attachments_json && message.attachments_json !== '[]') {
                meta.push('Attachment data available');
            }

            html += '<div class="lead-chat-row ' + rowClass + '">';
            html += '<div class="lead-chat-avatar"><i class="la ' + avatarIcon + '"></i></div>';
            html += '<div class="lead-chat-bubble-wrap">';
            html += '<div class="lead-chat-meta">';
            html += '<strong>' + directionLabel + '</strong> ';
            html += escapeHtml(message.message_timestamp_label);
            if (meta.length > 0) {
                html += ' | ' + meta.join(' | ');
            }
            html += '</div>';
            html += '<div class="lead-chat-bubble">' + messageBody + '</div>';
            html += '</div>';
            html += '</div>';
        });

        html += '</div>';
        return html;
    }

    function parseLeadTags(value) {
        if ($.isArray(value)) {
            return value;
        }

        if (!value) {
            return [];
        }

        try {
            var parsed = JSON.parse(value);
            return $.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function renderLeadModalTags(tags) {
        tags = parseLeadTags(tags);

        if (!tags.length) {
            leadMessagesModalTags.html('<span class="text-muted font-size-sm">No tags</span>');
            return;
        }

        var html = '';
        $.each(tags, function(index, tag) {
            html += '<span class="lead-tag" title="' + escapeHtml(tag) + '">' + escapeHtml(tag) + '</span>';
        });

        leadMessagesModalTags.html(html);
    }

    function loadLeadMessages(button) {
        var cacheKey = [
            button.data('conversation-id'),
            button.data('lead-started-at'),
            button.data('lead-ended-at') || ''
        ].join('|');

        leadMessagesModalSubtitle.text(
            (button.data('contact-name') || 'Unknown Contact') +
            ' | ' +
            (button.data('agent-name') || 'Unassigned') +
            ' | ' +
            (button.data('conversation-id') || '')
        );
        renderLeadModalTags(button.attr('data-tags'));

        if (leadMessagesModalContent.data('cache-key') === cacheKey && leadMessagesModalContent.data('loaded') === 1) {
            return;
        }

        leadMessagesModalContent.html('<div class="lead-chat-empty">Loading messages...</div>');
        leadMessagesModalContent.data('cache-key', cacheKey);
        leadMessagesModalContent.data('loaded', 0);

        $.getJSON(leadDataMessagesEndpoint, {
            conversation_id: button.data('conversation-id'),
            lead_started_at: button.data('lead-started-at'),
            next_lead_started_at: button.data('lead-ended-at')
        }).done(function(response) {
            if (!response.success) {
                leadMessagesModalContent.html('<div class="lead-chat-empty text-danger">Failed to load messages.</div>');
                return;
            }

            leadMessagesModalContent.html(renderMessageTimeline(response.messages));
            leadMessagesModalContent.data('loaded', 1);
        }).fail(function() {
            leadMessagesModalContent.html('<div class="lead-chat-empty text-danger">Failed to load messages.</div>');
        });
    }

    $('.lead-messages-toggle').on('click', function() {
        loadLeadMessages($(this));
    });

    $('#leadMessagesModal').on('shown.bs.modal', function() {
        $('body').addClass('lead-modal-scroll-lock');
    });

    $('#leadMessagesModal').on('hidden.bs.modal', function() {
        $('body').removeClass('lead-modal-scroll-lock');
    });

    $('#lead-data-reset').click(function() {
        Reset('<?php echo base_url('Report/Lead_Data'); ?>');
    });

    $('#lead-data-per-page').on('changed.bs.select', function() {
        $('#lead-data-form').submit();
    });

    <?php if(
        !empty($lead_data_filters['lead_date']) ||
        !empty($lead_data_filters['sales_agent']) ||
        !empty($lead_data_filters['conversation_id']) ||
        !empty($lead_data_filters['contact_name']) ||
        !empty($lead_data_filters['phone']) ||
        !empty($lead_data_filters['tag']) ||
        !empty($lead_data_filters['response_status']) ||
        !empty($lead_data_filters['conversion_status'])
    ) { ?>
        $('#lead_data_header').click();
    <?php } ?>
</script>
