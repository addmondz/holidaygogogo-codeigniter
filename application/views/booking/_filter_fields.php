<?php
    /**
     * Shared booking filter fields partial.
     *
     * Used by:
     *   - booking/index.php (hydrated from $this->input->get())
     *   - quick_filter/quick_filter.php (hydrated from a saved FilterData JSON blob)
     *
     * Expected variable:
     *   $filter_values — associative array of filter field => submitted value (csv for multi-selects).
     *                    Missing keys default to empty via null-coalescing.
     *
     * Dropdown data ($categories, $sources, $admins, $booking_op_admins, $tags,
     * $customer_types, $filter_checklists, $cancellation_reasons) must be passed from
     * the controller to the outer view before this partial is included.
     */
    if(!isset($filter_values) || !is_array($filter_values)) {
        $filter_values = [];
    }
?>
<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label>Customer</label>
            <div class="input-icon">
                <input type="text" name="customer" value="<?php if(!empty($filter_values['customer'] ?? '')) { echo strtoupper($filter_values['customer']); } ?>" autocomplete="off" class="form-control">
                <span>
                    <i class="la la-user"></i>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Booking Number</label>
            <div class="input-icon">
                <input type="text" name="booking_number" value="<?php if(!empty($filter_values['booking_number'] ?? '')) { echo strtoupper($filter_values['booking_number']); } ?>" autocomplete="off" class="form-control">
                <span>
                    <i class="la la-suitcase"></i>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Reservation Number</label>
            <div class="input-icon">
                <input type="text" name="reservation_number" value="<?php if(!empty($filter_values['reservation_number'] ?? '')) { echo strtoupper($filter_values['reservation_number']); } ?>" autocomplete="off" class="form-control">
                <span>
                    <i class="la la-suitcase"></i>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Mobile</label>
            <div class="input-icon">
                <input type="text" name="mobile" value="<?php if(!empty($filter_values['mobile'] ?? '')) { echo $filter_values['mobile']; } ?>" autocomplete="off" class="form-control">
                <span>
                    <i class="la la-mobile"></i>
                </span>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label>Destination</label>
            <?php $selected_destinations = !empty($filter_values['destination'] ?? '') ? explode(',', $filter_values['destination']) : []; ?>
            <select id="destination_select" data-live-search="true" class="form-control selectpicker" multiple data-actions-box="true" title="--SELECT DESTINATION--">
                <?php foreach($categories as $category) { ?>
                    <option data-icon="la la-map-pin font-size-lg bs-icon" value="<?php echo $category->CategoryID; ?>" <?php if(in_array($category->CategoryID, $selected_destinations)) { echo 'selected'; } ?>><?php echo $category->Name; ?></option>
                <?php } ?>
            </select>
            <input type="hidden" name="destination" id="destination_hidden" value="<?php echo $filter_values['destination'] ?? ''; ?>">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Travel Date
                <a onclick="Reset_Travel_Date()" class="btn btn-icon btn-light-warning btn-xs">
                    <i class="la la-undo"></i>
                </a>
            </label>
            <div id="kt_daterangepicker_4" class="input-icon">
                <input readonly type="text" name="travel_date" value="<?php if(!empty($filter_values['travel_date'] ?? '')) { echo $filter_values['travel_date']; } ?>" autocomplete="off" class="form-control">
                <span>
                    <i class="la la-calendar"></i>
                </span>
            </div>
            <div class="booking-quick-range mt-2" data-target="travel_date">
                <button type="button" class="btn btn-light-primary btn-sm font-weight-bold mr-1 mb-1" data-range="next7">Next 7 Days</button>
                <button type="button" class="btn btn-light-primary btn-sm font-weight-bold mr-1 mb-1" data-range="next14">Next 14 Days</button>
                <button type="button" class="btn btn-light-primary btn-sm font-weight-bold mr-1 mb-1" data-range="next30">Next 30 Days</button>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Deadline
                <a onclick="Reset_Deadline()" class="btn btn-icon btn-light-warning btn-xs">
                    <i class="la la-undo"></i>
                </a>
            </label>
            <div id="kt_daterangepicker_1" class="input-icon">
                <input readonly type="text" name="deadline" value="<?php if(!empty($filter_values['deadline'] ?? '')) { echo $filter_values['deadline']; } ?>" autocomplete="off" class="form-control">
                <span>
                    <i class="la la-calendar"></i>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Source</label>
            <?php $selected_sources = !empty($filter_values['source'] ?? '') ? explode(',', $filter_values['source']) : []; ?>
            <select id="source_select" data-live-search="true" class="form-control selectpicker" multiple data-actions-box="true" title="--SELECT SOURCE--">
                <?php foreach($sources as $source) { ?>
                    <option data-icon="<?php if($source->Name == 'WHATSAPP') { echo 'la la-whatsapp'; } else if($source->Name == 'WECHAT') { echo 'la la-wechat'; } else if($source->Name == 'EMAIL') { echo 'la la-envelope'; } else if($source->Name == 'CALL') { echo 'la la-phone-volume'; } else if($source->Name == 'TELEGRAM') { echo 'la la-telegram'; } else if($source->Name == 'FACEBOOK') { echo 'la la-facebook'; } else { echo 'la la-clipboard-list'; } ?> font-size-lg bs-icon" value="<?php echo $source->SourceID; ?>" <?php if(in_array($source->SourceID, $selected_sources)) { echo 'selected'; } ?>><?php echo $source->Name; ?></option>
                <?php } ?>
            </select>
            <input type="hidden" name="source" id="source_hidden" value="<?php echo $filter_values['source'] ?? ''; ?>">
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label>Chat Language</label>
            <?php $selected_chat_languages = !empty($filter_values['chat_language'] ?? '') ? explode(',', $filter_values['chat_language']) : []; ?>
            <select id="chat_language_select" class="form-control selectpicker" multiple data-actions-box="true" title="--SELECT CHAT LANGUAGE--">
                <?php foreach(unserialize(CHAT_LANGUAGE) as $key => $value) { ?>
                    <option data-icon="la la-language font-size-lg bs-icon" value="<?php echo $key; ?>" <?php if(in_array($key, $selected_chat_languages)) { echo 'selected'; } ?>><?php echo $value; ?></option>
                <?php } ?>
            </select>
            <input type="hidden" name="chat_language" id="chat_language_hidden" value="<?php echo $filter_values['chat_language'] ?? ''; ?>">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Booking Date
                <a onclick="Reset_Booking_Date()" class="btn btn-icon btn-light-warning btn-xs">
                    <i class="la la-undo"></i>
                </a>
            </label>
            <div id="kt_daterangepicker_5" class="input-icon">
                <input readonly type="text" name="booking_date" value="<?php if(!empty($filter_values['booking_date'] ?? '')) { echo $filter_values['booking_date']; } ?>" autocomplete="off" class="form-control">
                <span>
                    <i class="la la-calendar"></i>
                </span>
            </div>
            <div class="booking-quick-range mt-2" data-target="booking_date">
                <button type="button" class="btn btn-light-primary btn-sm font-weight-bold mr-1 mb-1" data-range="last7">Last 7 Days</button>
                <button type="button" class="btn btn-light-primary btn-sm font-weight-bold mr-1 mb-1" data-range="last14">Last 14 Days</button>
                <button type="button" class="btn btn-light-primary btn-sm font-weight-bold mr-1 mb-1" data-range="last30">Last 30 Days</button>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Status</a>
            </label>
            <?php $selected_statuses = !empty($filter_values['status'] ?? '') ? explode(',', $filter_values['status']) : []; ?>
            <select id="status_select" class="form-control selectpicker" multiple data-actions-box="true" title="--SELECT STATUS--">
                <?php foreach(unserialize(BOOKING_STATUS) as $key => $value) { ?>
                    <option data-icon="<?php if($key == 'A') { echo 'la la-font'; } else if($key == 'Y') { echo 'la la-check-circle'; } else if($key == 'PR') { echo 'la la-user-edit'; } else if($key == 'P') { echo 'la la-exclamation-circle'; } else if($key == 'PP') { echo 'la la-dollar'; } else if($key == 'PTV') { echo 'la la-file-alt'; } else if($key == 'PGL') { echo 'la la-user-friends'; } else if($key == 'PT') { echo 'la la-suitcase'; } else if($key == 'OG') { echo 'la la-luggage-cart'; } else if($key == 'PO') { echo 'la la-exclamation-triangle'; } else { echo 'la la-times-circle'; } ?> font-size-lg bs-icon" value="<?php echo $key; ?>" <?php if(in_array($key, $selected_statuses)) { echo 'selected'; } ?>><?php echo $value; ?></option>
                <?php } ?>
            </select>
            <input type="hidden" name="status" id="status_hidden" value="<?php echo $filter_values['status'] ?? ''; ?>">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>BC Title</label>
            <?php $selected_bc_titles = !empty($filter_values['booking_confirmation_title'] ?? '') ? explode(',', $filter_values['booking_confirmation_title']) : []; ?>
            <select id="booking_confirmation_title_select" class="form-control selectpicker" multiple data-actions-box="true" title="--SELECT BC TITLE--">
                <?php foreach(unserialize(BC_TITLE) as $key => $value) { ?>
                    <option data-icon="la la-heading font-size-lg bs-icon" value="<?php echo $key; ?>" <?php if(in_array($key, $selected_bc_titles)) { echo 'selected'; } ?>><?php echo $value; ?></option>
                <?php } ?>
            </select>
            <input type="hidden" name="booking_confirmation_title" id="booking_confirmation_title_hidden" value="<?php echo $filter_values['booking_confirmation_title'] ?? ''; ?>">
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label>BC Tag</label>
            <?php $selected_tags = !empty($filter_values['tag'] ?? '') ? explode(',', $filter_values['tag']) : []; ?>
            <select id="tag_select" data-live-search="true" class="form-control selectpicker" multiple data-actions-box="true" title="--SELECT BC TAG--">
                <?php foreach($tags as $tag) { ?>
                    <option data-icon="la la-tags font-size-lg bs-icon" value="<?php echo $tag->TagID; ?>" <?php if(in_array($tag->TagID, $selected_tags)) { echo 'selected'; } ?>><?php echo $tag->Name; ?></option>
                <?php } ?>
            </select>
            <input type="hidden" name="tag" id="tag_hidden" value="<?php echo $filter_values['tag'] ?? ''; ?>">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Customer Type</label>
            <?php $selected_customer_types_filter = !empty($filter_values['customer_type'] ?? '') ? explode(',', $filter_values['customer_type']) : []; ?>
            <select id="customer_type_select" data-live-search="true" class="form-control selectpicker" multiple data-actions-box="true" title="--SELECT CUSTOMER TYPE--">
                <?php if(!empty($customer_types)) { foreach($customer_types as $ct) { ?>
                    <option data-icon="la la-user-tag font-size-lg bs-icon" value="<?php echo $ct->CustomerTypeID; ?>" <?php if(in_array($ct->CustomerTypeID, $selected_customer_types_filter)) { echo 'selected'; } ?>><?php echo $ct->Name; ?></option>
                <?php } } ?>
            </select>
            <input type="hidden" name="customer_type" id="customer_type_hidden" value="<?php echo $filter_values['customer_type'] ?? ''; ?>">
        </div>
    </div>
    <?php if($this->session->userdata('level') != 20) { ?>
        <div class="col-md-3">
            <div class="form-group">
                <label>Sales Agent</label>
                <?php $selected_sales_agents = !empty($filter_values['sales_agent'] ?? '') ? explode(',', $filter_values['sales_agent']) : []; ?>
                <select id="sales_agent_select" data-live-search="true" class="form-control selectpicker" multiple data-actions-box="true" title="--SELECT SALES AGENT--">
                    <optgroup label="Active">
                    <?php foreach($admins as $admin) { if($admin->Status == 'Y') { ?>
                        <option data-icon="la la-user-alt font-size-lg bs-icon" value="<?php echo $admin->AdminID; ?>" <?php if(in_array($admin->AdminID, $selected_sales_agents)) { echo 'selected'; } ?>><?php echo $admin->Name; ?></option>
                    <?php } } ?>
                    </optgroup>
                    <optgroup label="Deactivated">
                    <?php foreach($admins as $admin) { if($admin->Status == 'D') { ?>
                        <option data-icon="la la-user-alt font-size-lg bs-icon" value="<?php echo $admin->AdminID; ?>" <?php if(in_array($admin->AdminID, $selected_sales_agents)) { echo 'selected'; } ?>><?php echo $admin->Name; ?></option>
                    <?php } } ?>
                    </optgroup>
                </select>
                <input type="hidden" name="sales_agent" id="sales_agent_hidden" value="<?php echo $filter_values['sales_agent'] ?? ''; ?>">
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>Sales Agent 2</label>
                <?php $selected_sales_agents_2 = !empty($filter_values['sales_agent_2'] ?? '') ? explode(',', $filter_values['sales_agent_2']) : []; ?>
                <select id="sales_agent_2_select" data-live-search="true" class="form-control selectpicker" multiple data-actions-box="true" title="--SELECT SALES AGENT 2--">
                    <optgroup label="Active">
                    <?php foreach($admins as $admin) { if($admin->Status == 'Y') { ?>
                        <option data-icon="la la-user-alt font-size-lg bs-icon" value="<?php echo $admin->AdminID; ?>" <?php if(in_array($admin->AdminID, $selected_sales_agents_2)) { echo 'selected'; } ?>><?php echo $admin->Name; ?></option>
                    <?php } } ?>
                    </optgroup>
                    <optgroup label="Deactivated">
                    <?php foreach($admins as $admin) { if($admin->Status == 'D') { ?>
                        <option data-icon="la la-user-alt font-size-lg bs-icon" value="<?php echo $admin->AdminID; ?>" <?php if(in_array($admin->AdminID, $selected_sales_agents_2)) { echo 'selected'; } ?>><?php echo $admin->Name; ?></option>
                    <?php } } ?>
                    </optgroup>
                </select>
                <input type="hidden" name="sales_agent_2" id="sales_agent_2_hidden" value="<?php echo $filter_values['sales_agent_2'] ?? ''; ?>">
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>OP</label>
                <?php $selected_booking_ops = !empty($filter_values['booking_op'] ?? '') ? explode(',', $filter_values['booking_op']) : []; ?>
                <select id="booking_op_select" data-live-search="true" class="form-control selectpicker" multiple data-actions-box="true" title="--SELECT OP--">
                    <?php foreach($booking_op_admins as $op_admin) { ?>
                        <option data-icon="la la-user-alt font-size-lg bs-icon" value="<?php echo $op_admin->AdminID; ?>" <?php if(in_array($op_admin->AdminID, $selected_booking_ops)) { echo 'selected'; } ?>><?php echo $op_admin->Name; ?></option>
                    <?php } ?>
                </select>
                <input type="hidden" name="booking_op" id="booking_op_hidden" value="<?php echo $filter_values['booking_op'] ?? ''; ?>">
            </div>
        </div>
    <?php } ?>
    <div class="col-md-3">
        <div class="form-group">
            <label>Autocount Status</label>
            <?php $selected_autocount_statuses = !empty($filter_values['autocount_status'] ?? '') ? explode(',', $filter_values['autocount_status']) : []; ?>
            <select id="autocount_status_select" class="form-control selectpicker" multiple data-actions-box="true" title="--SELECT AUTOCOUNT STATUS--">
                <option data-icon="la la-clock font-size-lg bs-icon" value="P" <?php if(in_array('P', $selected_autocount_statuses)) echo 'selected'; ?>>Pending</option>
                <option data-icon="la la-check-circle font-size-lg bs-icon" value="S" <?php if(in_array('S', $selected_autocount_statuses)) echo 'selected'; ?>>Synced</option>
                <option data-icon="la la-times-circle font-size-lg bs-icon" value="F" <?php if(in_array('F', $selected_autocount_statuses)) echo 'selected'; ?>>Failed</option>
            </select>
            <input type="hidden" name="autocount_status" id="autocount_status_hidden" value="<?php echo $filter_values['autocount_status'] ?? ''; ?>">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Guest List Status</label>
            <?php $selected_guest_list_statuses = !empty($filter_values['guest_list_status'] ?? '') ? explode(',', $filter_values['guest_list_status']) : []; ?>
            <select id="guest_list_status_select" class="form-control selectpicker" multiple data-actions-box="true" title="--SELECT GL STATUS--">
                <option data-icon="la la-spinner font-size-lg bs-icon" value="in_progress" <?php if(in_array('in_progress', $selected_guest_list_statuses)) echo 'selected'; ?>>In Progress</option>
                <option data-icon="la la-lock font-size-lg bs-icon" value="locked" <?php if(in_array('locked', $selected_guest_list_statuses)) echo 'selected'; ?>>Locked</option>
                <option data-icon="la la-check-circle font-size-lg bs-icon" value="submitted" <?php if(in_array('submitted', $selected_guest_list_statuses)) echo 'selected'; ?>>Submitted</option>
                <option data-icon="la la-times-circle font-size-lg bs-icon" value="not_submitted" <?php if(in_array('not_submitted', $selected_guest_list_statuses)) echo 'selected'; ?>>Not Submitted</option>
            </select>
            <input type="hidden" name="guest_list_status" id="guest_list_status_hidden" value="<?php echo $filter_values['guest_list_status'] ?? ''; ?>">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Checklist</label>
            <?php $selected_checklist_filters = !empty($filter_values['checklist_filter'] ?? '') ? explode(',', $filter_values['checklist_filter']) : []; ?>
            <select id="checklist_filter_select" class="form-control selectpicker" data-live-search="true" multiple data-actions-box="true" title="--SELECT CHECKLIST--">
                <?php if(isset($filter_checklists)) { foreach($filter_checklists as $checklist) { ?>
                    <option value="<?php echo $checklist->ID; ?>" <?php if(in_array($checklist->ID, $selected_checklist_filters)) echo 'selected'; ?>><?php echo htmlspecialchars($checklist->name); ?></option>
                <?php } } ?>
            </select>
            <input type="hidden" name="checklist_filter" id="checklist_filter_hidden" value="<?php echo $filter_values['checklist_filter'] ?? ''; ?>">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>Cancellation Reason</label>
            <?php $selected_cancellation_reasons = !empty($filter_values['cancellation_reason'] ?? '') ? explode(',', $filter_values['cancellation_reason']) : []; ?>
            <select id="cancellation_reason_select" data-live-search="true" class="form-control selectpicker" multiple data-actions-box="true" title="--SELECT CANCELLATION REASON--">
                <?php if(isset($cancellation_reasons)) { foreach($cancellation_reasons as $reason) { ?>
                    <option data-icon="la la-times-circle font-size-lg bs-icon" value="<?php echo $reason->CancellationReasonID; ?>" <?php if(in_array($reason->CancellationReasonID, $selected_cancellation_reasons)) { echo 'selected'; } ?>><?php echo $reason->Name; ?></option>
                <?php } } ?>
            </select>
            <input type="hidden" name="cancellation_reason" id="cancellation_reason_hidden" value="<?php echo $filter_values['cancellation_reason'] ?? ''; ?>">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label>E-Invoice Status</label>
            <?php $selected_einvoice_statuses = !empty($filter_values['einvoice_status'] ?? '') ? explode(',', $filter_values['einvoice_status']) : []; ?>
            <select id="einvoice_status_select" class="form-control selectpicker" multiple data-actions-box="true" title="--SELECT E-INVOICE STATUS--">
                <option value="yes" <?php if(in_array('yes', $selected_einvoice_statuses)) echo 'selected'; ?>>Has E-Invoice</option>
                <option value="no" <?php if(in_array('no', $selected_einvoice_statuses)) echo 'selected'; ?>>No E-Invoice</option>
            </select>
            <input type="hidden" name="einvoice_status" id="einvoice_status_hidden" value="<?php echo $filter_values['einvoice_status'] ?? ''; ?>">
        </div>
    </div>
</div>
