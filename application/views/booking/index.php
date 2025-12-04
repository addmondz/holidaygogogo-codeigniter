<?php
    $is_sales_agent = $this->session->userdata('level') == 20;
    ini_set("memory_limit","512M");
?>
<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Booking Records</strong>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <?php $current_url = base_url($_SERVER['REQUEST_URI']); ?>
                    <div>
                        <?php if(in_array('GB', $this->session->access_control)) { ?>
                            <a href="<?php if(strpos($current_url, '?') == true) { echo base_url('Booking/Create?') . (explode('?', $current_url))[1]; } else { echo base_url('Booking/Create'); } ?>" class="btn btn-primary font-weight-bold mb-2" style="width:180px;">
                                <i class="la la-suitcase"></i>Create Booking
                            </a>
                        <?php } ?>
                        <div>
                            <a href="<?php if(strpos($current_url, '?') == true) { echo base_url('Booking/Download?') . (explode('?', $current_url))[1]; } else { echo base_url('Booking/Download'); } ?>" class="btn btn-light-warning font-weight-bold mb-2" style="width:180px;">
                                <i class="las la-arrow-circle-down"></i>Booking Records
                            </a>
                            <label class="checkbox checkbox-outline checkbox-success" style="color:#7393B3; font-size:12px;">
                                <input <?php if($this->input->get('checkbox') == 'ON') { echo 'checked'; } ?> type="checkbox" id="checkbox">
                                <span></span>&nbsp;Include GL
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="accordion accordion-solid accordion-toggle-plus">
                    <div class="card">
                        <div class="card-header">
                            <div id="booking_header" data-toggle="collapse" data-target="#booking_info" class="card-title collapsed" style="font-size:13px;">Filter By Booking Information</div>
                        </div>
                        <div id="booking_info" class="collapse">
                            <div class="card-body">
                                <form id="form" action="<?php echo base_url('Booking') ?>" method="get" class="form">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Customer</label>
                                                <div class="input-icon">
                                                    <input type="text" name="customer" value="<?php if(!empty($this->input->get('customer'))) { echo strtoupper($this->input->get('customer')); } ?>" autocomplete="off" class="form-control">
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
                                                    <input type="text" name="booking_number" value="<?php if(!empty($this->input->get('booking_number'))) { echo strtoupper($this->input->get('booking_number')); } ?>" autocomplete="off" class="form-control">
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
                                                    <input type="text" name="reservation_number" value="<?php if(!empty($this->input->get('reservation_number'))) { echo strtoupper($this->input->get('reservation_number')); } ?>" autocomplete="off" class="form-control">
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
                                                    <input type="text" name="mobile" value="<?php if(!empty($this->input->get('mobile'))) { echo $this->input->get('mobile'); } ?>" autocomplete="off" class="form-control">
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
                                                <select name="destination" data-live-search="true" class="form-control selectpicker">
                                                    <option selected data-icon="la la-map-pin font-size-lg bs-icon" value="">--SELECT DESTINATION--</option>
                                                    <?php foreach($categories as $category) { ?>
                                                        <option data-icon="la la-map-pin font-size-lg bs-icon" value="<?php echo $category->CategoryID; ?>" <?php if(!empty($this->input->get('destination')) && $this->input->get('destination') == $category->CategoryID) { echo 'selected'; } ?>><?php echo $category->Name; ?></option>
                                                    <?php } ?>
                                                </select>
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
                                                    <input readonly type="text" name="travel_date" value="<?php if(!empty($this->input->get('travel_date'))) { echo $this->input->get('travel_date'); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-calendar"></i>
                                                    </span>
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
                                                    <input readonly type="text" name="deadline" value="<?php if(!empty($this->input->get('deadline'))) { echo $this->input->get('deadline'); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-calendar"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Source</label>
                                                <select name="source" data-live-search="true" class="form-control selectpicker">
                                                    <option selected data-icon="la la-clipboard-list font-size-lg bs-icon" value="">--SELECT SOURCE--</option>
                                                    <?php foreach($sources as $source) { ?>
                                                        <option data-icon="<?php if($source->Name == 'WHATSAPP') { echo 'la la-whatsapp'; } else if($source->Name == 'WECHAT') { echo 'la la-wechat'; } else if($source->Name == 'EMAIL') { echo 'la la-envelope'; } else if($source->Name == 'CALL') { echo 'la la-phone-volume'; } else if($source->Name == 'TELEGRAM') { echo 'la la-telegram'; } else if($source->Name == 'FACEBOOK') { echo 'la la-facebook'; } else { echo 'la la-clipboard-list'; } ?> font-size-lg bs-icon" value="<?php echo $source->SourceID; ?>" <?php if(!empty($this->input->get('source')) && $this->input->get('source') == $source->SourceID) { echo 'selected'; } ?>><?php echo $source->Name; ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Chat Language</label>
                                                <select name="chat_language" class="form-control selectpicker">
                                                    <option selected data-icon="la la-language font-size-lg bs-icon" value="">--SELECT CHAT LANGUAGE--</option>
                                                    <?php foreach(unserialize(CHAT_LANGUAGE) as $key => $value) { ?>
                                                        <option data-icon="la la-language font-size-lg bs-icon" value="<?php echo $key; ?>" <?php if(!empty($this->input->get('chat_language')) && $this->input->get('chat_language') == $key) { echo 'selected'; } ?>><?php echo $value; ?></option>
                                                    <?php } ?>
                                                </select>
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
                                                    <input readonly type="text" name="booking_date" value="<?php if(!empty($this->input->get('booking_date'))) { echo $this->input->get('booking_date'); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-calendar"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Status</a>
                                                </label>
                                                <select name="status" class="form-control selectpicker">
                                                    <option selected data-icon="la la-suitcase font-size-lg bs-icon" value="">--SELECT STATUS--</option>
                                                    <?php foreach(unserialize(BOOKING_STATUS) as $key => $value) { ?>
                                                        <option data-icon="<?php if($key == 'A') { echo 'la la-font'; } else if($key == 'Y') { echo 'la la-check-circle'; } else if($key == 'PR') { echo 'la la-user-edit'; } else if($key == 'P') { echo 'la la-exclamation-circle'; } else if($key == 'PP') { echo 'la la-dollar'; } else if($key == 'PTV') { echo 'la la-file-alt'; } else if($key == 'PGL') { echo 'la la-user-friends'; } else if($key == 'PT') { echo 'la la-suitcase'; } else if($key == 'OG') { echo 'la la-luggage-cart'; } else if($key == 'PO') { echo 'la la-exclamation-triangle'; } else { echo 'la la-times-circle'; } ?> font-size-lg bs-icon" value="<?php echo $key; ?>" <?php if(!empty($this->input->get('status')) && $this->input->get('status') == $key) { echo 'selected'; } ?>><?php echo $value; ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>BC Title</label>
                                                <select name="booking_confirmation_title" class="form-control selectpicker">
                                                    <option selected data-icon="la la-heading font-size-lg bs-icon" value="">--SELECT BC TITLE--</option>
                                                    <?php foreach(unserialize(BC_TITLE) as $key => $value) { ?>
                                                        <option data-icon="la la-heading font-size-lg bs-icon" value="<?php echo $key; ?>" <?php if(!empty($this->input->get('booking_confirmation_title')) && $this->input->get('booking_confirmation_title') == $key) { echo 'selected'; } ?>><?php echo $value; ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>BC Tag</label>
                                                <select name="tag" data-live-search="true" class="form-control selectpicker">
                                                    <option selected data-icon="la la-tags font-size-lg bs-icon" value="">--SELECT BC TAG--</option>
                                                    <?php foreach($tags as $tag) { ?>
                                                        <option data-icon="la la-tags font-size-lg bs-icon" value="<?php echo $tag->TagID; ?>" <?php if(!empty($this->input->get('tag')) && $this->input->get('tag') == $tag->TagID) { echo 'selected'; } ?>><?php echo $tag->Name; ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <?php if($this->session->userdata('level') != 20) { ?>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Sales Agent</label>
                                                    <select name="sales_agent" data-live-search="true" class="form-control selectpicker">
                                                        <option selected data-icon="la la-user-alt font-size-lg bs-icon" value="">--SELECT SALES AGENT--</option>
                                                        <?php foreach($admins as $admin) { ?>
                                                            <option data-icon="la la-user-alt font-size-lg bs-icon" value="<?php echo $admin->AdminID; ?>" <?php if(!empty($this->input->get('sales_agent')) && $this->input->get('sales_agent') == $admin->AdminID) { echo 'selected'; } ?>><?php echo $admin->Name; ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                    <input type="submit" value="Filter" class="btn btn-light-success font-weight-bold" style="width:80px;">
                                    <input type="button" id="reset" value="Reset" class="btn btn-light-primary font-weight-bold" style="width:80px;">
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <br><br>
                <?php if ($bulkBookingSyncToAutocount) { ?>
                    <button type="button" 
                            class="btn btn-primary font-weight-bold mb-2" 
                            id="sync-autocount-booking" 
                            style="width:180px; display:none;">
                        Sync Autocount
                    </button>
                <?php } ?>

                <div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if(empty($bookings)) { echo 'style="overflow-x:auto;"'; } ?>>
                    <table id="kt_datatable" class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline">
                        <thead>
                            <tr>
                                <th class="booking_checkbox" style="text-align:center;">
                                    <input class="booking_checkbox" type="checkbox" id="check_all">
                                </th>
                                <th style="text-align:center;">No.</th>
                                <?php if($this->session->userdata('level') != 20) { ?>
                                    <th style="text-align:center;">SA</th>
                                <?php } ?>
                                <th class="bc_date" style="text-align:center;">Creation Date</th>
                                <th style="text-align:center;">BC Number</th>
                                <th style="text-align:center;">BC</th>
                                <th style="text-align:center;">Customer</th>
                                <th style="text-align:center;">Customer Code (Autocount Debtor Code)</th>
                                <th style="text-align:center;">Chat</th>
                                <th class="test" style="text-align:center;">Mobile</th>
                                <th class="start_date" style="text-align:center;">Start</th>
                                <th class="end_date" style="text-align:center;">End</th>
                                <th style="text-align:center;">Destination</th>
                                <th class="subtotal" style="text-align:center;">Net Sales (RM)</th>
                                <?php if(!$is_sales_agent) { ?>
                                    <th class="profit" style="text-align:center;">Net Profit (RM)</th>
                                    <th style="text-align:center;">Net Profit Margin (%)</th>
                                <?php } ?>
                                <th style="text-align:center;">BC Status</th>
                                <th class="gl_status" style="text-align:center;">GL Status</th>
                                <th class="autocount_sync_status" style="text-align:center;">Booking Autocount Status</th>
                                <th class="action" style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($bookings)) { ?>
                                <td colspan="<?php if($this->session->userdata('level') != 20) { echo 17; } else { echo 16; } ?>" style="text-align:center; padding-top:10px; padding-bottom:10px;">Booking Records Not Found</td>
                            <?php } else { ?>
                                <?php $count = 1; ?>
                                <?php foreach($bookings as $booking) { ?>
                                    <tr>
                                        <td style="text-align:center;">
                                            <input type="checkbox" id="check_item" class="check_item" value="<?php echo $booking->BookingID; ?>">
                                        </td>
                                        <td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
                                        <?php if($this->session->userdata('level') != 20) { ?>
                                            <td style="text-align:center;"><?php echo $booking->SalesAgentName; ?></td>
                                        <?php } ?>
                                        <td style="text-align:center;"><?php echo $booking->InsertDate; ?></td>
                                        <td style="text-align:center;">
                                            <a href="<?php echo base_url('Payment?booking_number=') . $booking->BookingNumber . '&customer=' . str_replace('&', '%26', $booking->Customer); ?>" target="_blank"><?php echo $booking->BookingNumber; ?></a>
                                        </td>
                                        <td style="text-align:center;"><?php echo $booking->BookingConfirmationTitle; ?></td>
                                        <td style="text-align:center;"><?php echo $booking->Customer; ?></td>
                                        <td style="text-align:center;"><?php echo $booking->CustomerCode; ?></td>
                                        <td style="text-align:center;"><?php echo $booking->ChatLanguage; ?></td>
                                        <td style="text-align:center;">
                                            <a href="<?php echo 'https://wa.me/' . $booking->CustomerMobile; ?>" target="_blank" class="btn btn-light-success d-inline-flex align-items-center btn-sm">
                                                <i class="la la-whatsapp"></i>
                                            </a>
                                        </td>
                                        <td style="text-align:center;"><?php echo $booking->StartDate; ?></td>
                                        <td style="text-align:center;"><?php echo $booking->EndDate; ?></td>
                                        <td style="text-align:center;"><?php echo $booking->DestinationName; ?></td>
                                        <td style="text-align:center;"><?php echo $booking->NetTotal; ?></td>
                                        <?php if(!$is_sales_agent) { ?>
                                            <td style="color:<?php if(isset($booking->Profit) && $booking->Profit < 0) { echo '#FF2400;'; } else if(isset($booking->Profit) && $booking->Profit == 0) { echo '#F4BB44;'; } else { echo '#00A36C;'; } ?> text-align:center;"><?php echo isset($booking->Profit) ? $booking->Profit : '-'; ?></td>
                                            <td style="color:<?php if(isset($booking->ProfitMargin) && $booking->ProfitMargin < 0) { echo '#FF2400;'; } else if(isset($booking->ProfitMargin) && $booking->ProfitMargin == 0) { echo '#F4BB44;'; } else { echo '#00A36C;'; } ?> text-align:center;"><?php echo isset($booking->ProfitMargin) ? $booking->ProfitMargin : '-'; ?></td>
                                        <?php } ?>
                                        <td style="text-align:center;">
                                            <span class="font-weight-bold" style="color:<?php if($booking->CancelStatus == 'Y') { echo '#FF69B4'; } else if($booking->Status == 'Y') { echo '#50C878'; } else if($booking->Status == 'PR') { echo '#C3B1E1'; } else if($booking->Status == 'P') { echo '#FFBF00'; } else if($booking->Status == 'PP') { echo '#A7C7E7'; } else if($booking->Status == 'PTV') { echo '#F89880'; } else if($booking->Status == 'PGL') { echo '#FAC898'; } else if($booking->Status == 'PT') { echo '#F8C8DC'; } else if($booking->Status == 'OG') { echo '#CCCCFF'; } else { echo '#DA70D6'; } ?>"><?php if($booking->CancelStatus == 'Y') { echo 'CANCELLED'; } else if($booking->Status == 'Y') { echo 'COMPLETED'; } else if($booking->Status == 'PR') { echo 'PENDING REVIEW'; } else if($booking->Status == 'P') { echo 'PENDING PAYMENT'; } else if($booking->Status == 'PP') { echo 'PARTIAL PAYMENT'; } else if($booking->Status == 'PTV') { echo 'PENDING TRAVEL VOUCHER'; } else if($booking->Status == 'PGL') { echo 'PENDING GUEST LIST'; } else if($booking->Status == 'PT') { echo 'PENDING TRAVEL'; } else if($booking->Status == 'OG') { echo 'ON-GOING'; } else { echo 'PAYMENT OVERDUE'; } ?></span>
                                        </td>
                                        <td style="text-align:center;"><?php if($booking->LockStatus == 'Y') { echo '<i class="la la-lock text-danger"></i>'; } else { echo '<i class="la la-unlock text-success"></i>'; } ?></td>
                                        <td style="text-align:center;">
                                            <?php 
                                                // status info (P, S, F only)
                                                $statusInfo  = mapAutocountSyncStatus(isset($booking->AutocountSyncStatus) ? $booking->AutocountSyncStatus : null);
                                                $statusText  = $statusInfo['text'];
                                                $statusColor = $statusInfo['color'];

                                                // tooltip logic
                                                $tooltipAttr = ''; 
                                                if (!empty($booking->AutocountSyncMessage)) {
                                                    $decoded = json_decode($booking->AutocountSyncMessage, true);

                                                    if (json_last_error() === JSON_ERROR_NONE) {
                                                        if (isset($decoded['error']) && $decoded['error'] == null) {
                                                            $tooltipText = "SUCCESS";
                                                        } elseif (isset($decoded['error']) && $decoded['error'] !== null) {
                                                            $tooltipText = "ERROR: " . (is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error']));
                                                        } else {
                                                            $tooltipText = $booking->AutocountSyncMessage;
                                                        }
                                                    } else {
                                                        $tooltipText = $booking->AutocountSyncMessage;
                                                    }

                                                    $tooltipAttr = ' data-toggle="tooltip" data-placement="top" title="' . htmlspecialchars($tooltipText) . '"';
                                                }
                                            ?>
                                            <span class="font-weight-bold" style="color:<?= $statusColor ?>;" <?= $tooltipAttr ?>>
                                                <?= $statusText ?>
                                            </span>
                                        </td>
                                        <td style="text-align:center;">
                                            <div class="btn-group">
                                                <button type="button" data-toggle="dropdown" class="btn btn-light-primary btn-sm dropdown-toggle" style="padding-left:3px;"></button>
                                                <div class="dropdown-menu">
                                                    <?php if($booking->Status != 'Y' || ($this->session->level != 20 && $booking->Status == 'Y')) { ?>
                                                        <?php if(in_array('RB', $this->session->access_control)) { ?>
                                                            <button onclick="Delete_Record('<?php echo base_url('assets/image/sweetalert.jpg'); ?>', '<?php echo 'Booking Record : ' . $booking->BookingNumber; ?>', '<?php echo base_url('Booking/Delete'); ?>', 'booking_id', <?php echo $booking->BookingID; ?>, '<?php echo $booking->Status; ?>', '<?php if(strpos($current_url, '?') == true) { echo base_url('Booking?') . (explode('?', $current_url))[1]; } else { echo base_url('Booking'); } ?>')" class="dropdown-item" style="color:#E37383; font-size:11px;">Delete Booking</button>
                                                        <?php } ?>
                                                        <?php if(in_array('AB', $this->session->access_control)) { ?>
                                                            <?php if($booking->CancelStatus == 'Y') { ?>
                                                                <a href="<?php echo base_url('Booking/Update_Cancel_Status?booking_id=') . $booking->BookingID . '&current_cancel_status=' . $booking->CancelStatus . '&new_cancel_status=N' . '&param=' . urlencode($current_url); ?>" class="dropdown-item" style="color:#93C572; font-size:11px;">Activate Booking</a>
                                                            <?php } else { ?>
                                                                <a href="<?php echo base_url('Booking/Update_Cancel_Status?booking_id=') . $booking->BookingID . '&current_cancel_status=' . $booking->CancelStatus . '&new_cancel_status=Y' . '&param=' . urlencode($current_url); ?>" class="dropdown-item" style="color:#E0115F; font-size:11px;">Cancel Booking</a>
                                                            <?php } ?>
                                                            <?php if($booking->Status == 'Y' || $booking->Status == 'PR') { ?>
                                                                <?php if($booking->AfterSalesService == 'PENDING') { ?>
                                                                    <a href="<?php echo base_url('Booking/Update_After_Sales_Service?booking_id=') . $booking->BookingID . '&current_after_sales_service=' . $booking->AfterSalesService . '&new_after_sales_service=COMPLETE' . '&param=' . urlencode($current_url); ?>" class="dropdown-item" style="color:#50C878; font-size:11px;">Complete Booking</a>
                                                                <?php } else { ?>
                                                                    <a href="<?php echo base_url('Booking/Update_After_Sales_Service?booking_id=') . $booking->BookingID . '&current_after_sales_service=' . $booking->AfterSalesService . '&new_after_sales_service=PENDING' . '&param=' . urlencode($current_url); ?>" class="dropdown-item" style="color:#702963; font-size:11px;">Revert Pending Review</a>
                                                                <?php } ?>
                                                            <?php } ?>
                                                            <?php if($booking->Status == 'PTV' || $booking->Status == 'PT') { ?>
                                                                <?php if($booking->Status == 'PTV') { ?>
                                                                    <a href="<?php echo base_url('Booking/Update_Status?booking_id=') . $booking->BookingID . '&current_status=' . $booking->Status . '&new_status=PT' . '&param=' . urlencode($current_url); ?>" class="dropdown-item" style="color:#6082B6; font-size:11px;">Sent Travel Voucher ?</a>
                                                                <?php } else { ?>
                                                                    <a href="<?php echo base_url('Booking/Update_Status?booking_id=') . $booking->BookingID . '&current_status=' . $booking->Status . '&new_status=PTV' . '&param=' . urlencode($current_url); ?>" class="dropdown-item" style="color:#F4BB44; font-size:11px;">Revert Pending Travel Voucher</a>
                                                                <?php } ?>
                                                            <?php } ?>
                                                            <a href="<?php if(strpos($current_url, '?') == true) { echo base_url('Booking/Update?booking_id=') . $booking->BookingID . '&' . (explode('?', $current_url))[1]; } else { echo base_url('Booking/Update?booking_id=') . $booking->BookingID; } ?>" class="dropdown-item" style="font-size:11px;">Update Booking</a>
                                                        <?php } ?>
                                                    <?php } ?>
                                                    <?php if(in_array('GB', $this->session->access_control)) { ?>
                                                        <a href="<?php if(strpos($current_url, '?') == true) { echo base_url('Booking/Duplicate?booking_id=') . $booking->BookingID . '&' . (explode('?', $current_url))[1]; } else { echo base_url('Booking/Duplicate?booking_id=') . $booking->BookingID; } ?>" class="dropdown-item" style="font-size:11px;">Duplicate Booking</a>
                                                    <?php } ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a href="<?php echo base_url('Booking_Confirmation?token=') . $booking->Token; ?>" target="_blank" class="dropdown-item" style="font-size:11px;">Booking Confirmation</a>
                                                    <button id="<?php echo 'bc_url-' . $booking->BookingID; ?>" value="<?php echo base_url('Booking_Confirmation?token=') . $booking->Token; ?>" onclick="Copy_URL('BC URL', <?php echo $booking->BookingID; ?>)" class="dropdown-item" style="font-size:11px;">Copy BC Link</button>
                                                    <div class="dropdown-divider"></div>
                                                    <?php if($booking->Status != 'Y' || ($this->session->level != 20 && $booking->Status == 'Y')) { ?>
                                                        <a href="<?php echo base_url('Guest_List?gl=') . $booking->Token; ?>"  target="_blank" class="dropdown-item" style="font-size:11px;">Guest List</a>
                                                    <?php } ?>
                                                    <a href="<?php echo base_url('Guest_List/Download?booking_id=') . $booking->BookingID; ?>" class="dropdown-item" style="font-size:11px;">Download Guest List</a>
                                                    <button id="<?php echo 'gl_url-' . $booking->BookingID; ?>" value="<?php echo 'https://gl.holidaygogogo.com?gl=' . $booking->Token; ?>" onclick="Copy_URL('GL URL', <?php echo $booking->BookingID; ?>)" class="dropdown-item" style="font-size:11px;">Copy GL Link</button>
                                                    <div class="dropdown-divider"></div>
                                                    <a href="<?php echo base_url('Travel_Voucher?token=') . $booking->Token; ?>" target="_blank" class="dropdown-item" style="font-size:11px;">Travel Voucher</a>
                                                    <button id="<?php echo 'tv_url-' . $booking->BookingID; ?>" value="<?php echo base_url('Travel_Voucher?token=') . $booking->Token; ?>" onclick="Copy_URL('TV URL', <?php echo $booking->BookingID; ?>)" class="dropdown-item" style="font-size:11px;">Copy TV Link</button>
                                                    <div class="dropdown-divider"></div>
                                                    <button id="<?php echo 'customer_name-' . $booking->BookingID; ?>" value="<?php echo $booking->Customer; ?>" onclick="Copy_URL('CUSTOMER NAME', <?php echo $booking->BookingID; ?>)" class="dropdown-item" style="font-size:11px;">Copy Customer Name</button>
                                                    <button id="<?php echo 'customer_mobile-' . $booking->BookingID; ?>" value="<?php echo $booking->CustomerMobile; ?>" onclick="Copy_URL('CUSTOMER MOBILE', <?php echo $booking->BookingID; ?>)" class="dropdown-item" style="font-size:11px;">Copy Customer Mobile</button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php $count++; ?>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php if(!empty($this->input->get('booking_number')) || !empty($this->input->get('reservation_number')) || !empty($this->input->get('deadline')) || !empty($this->input->get('customer')) || !empty($this->input->get('mobile')) || !empty($this->input->get('travel_date')) || !empty($this->input->get('destination')) || !empty($this->input->get('sales_agent')) || !empty($this->input->get('source')) || !empty($this->input->get('status')) || !empty($this->input->get('booking_date')) || !empty($this->input->get('chat_language')) || !empty($this->input->get('booking_confirmation_title')) || !empty($this->input->get('tag'))) { ?>
                    <br>
                    <div class="row">
                        <div class="col-md-12 pt-3 pb-3" style="background-color:white; border:3px solid #D7E2F2; border-radius:8px;">
                            <div class="row">
                                <div class="col-md-6 mb-7 mb-md-0">
                                    <label style="color:#C4B454;">Total Net Sales (RM)</label>
                                    <div class="input-icon">
                                        <input disabled type="text" value="<?php echo $total_sales; ?>" class="form-control" style="text-align:right;">
                                        <span>
                                            <i class="la la-dollar"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label style="color:#FFC000;">Total Net Profit (RM)</label>
                                    <div class="input-icon">
                                        <input disabled type="text" value="<?php echo $total_net_profit; ?>" class="form-control" style="text-align:right;">
                                        <span>
                                            <i class="la la-dollar"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<script>
    var current_date = (new Date()).toLocaleDateString();

    $('#kt_daterangepicker_1').on('apply.daterangepicker', function(event, daterange) {
        var start_date = (new Date(daterange.startDate._d)).toLocaleDateString();
        var end_date = (new Date(daterange.endDate._d)).toLocaleDateString();
        if(start_date == current_date && end_date == current_date) {
            $('input[name="deadline"]').val(moment().format('DD/MM/YYYY') + ' - ' + moment().format('DD/MM/YYYY'));
        }
    });

    $('#kt_daterangepicker_4').on('apply.daterangepicker', function(event, daterange) {
        var start_date = (new Date(daterange.startDate._d)).toLocaleDateString();
        var end_date = (new Date(daterange.endDate._d)).toLocaleDateString();
        if(start_date == current_date && end_date == current_date) {
            $('input[name="travel_date"]').val(moment().format('DD/MM/YYYY') + ' - ' + moment().format('DD/MM/YYYY'));
        }
    });

    $('#kt_daterangepicker_5').on('apply.daterangepicker', function(event, daterange) {
        var start_date = (new Date(daterange.startDate._d)).toLocaleDateString();
        var end_date = (new Date(daterange.endDate._d)).toLocaleDateString();
        if(start_date == current_date && end_date == current_date) {
            $('input[name="booking_date"]').val(moment().format('DD/MM/YYYY') + ' - ' + moment().format('DD/MM/YYYY'));
        }
    });

    function Reset_Deadline() {
        $('input[name="deadline"]').val('');
    }

    function Reset_Travel_Date() {
        $('input[name="travel_date"]').val('');
    }
    
    function Reset_Booking_Date() {
        $('input[name="booking_date"]').val('');
    }

    <?php if(!empty($this->input->get('booking_number')) || !empty($this->input->get('reservation_number')) || !empty($this->input->get('deadline')) || !empty($this->input->get('customer')) || !empty($this->input->get('mobile')) || !empty($this->input->get('travel_date')) || !empty($this->input->get('destination')) || !empty($this->input->get('sales_agent')) || !empty($this->input->get('tag')) || !empty($this->input->get('chat_language')) || !empty($this->input->get('source')) || !empty($this->input->get('booking_confirmation_title')) || !empty($this->input->get('status')) || !empty($this->input->get('booking_date'))) { ?>
        $('#booking_header').click();
    <?php } ?>
    
    $('#reset').click(function() {
        Reset('<?php echo base_url('Booking'); ?>');
    });

    $('#checkbox').change(function() {
        if($('#checkbox').is(':checked')) {
            var url = window.location.href.includes('?') ? window.location.href + '&checkbox=ON' : window.location.href + '?checkbox=ON';
        } else {
            var url = window.location.href.includes('?booking_number=') ? window.location.href.split('&checkbox=ON')[0] : window.location.href.split('?checkbox=ON')[0];
        }
        window.location.href = url;
    });
    
    function Copy_URL(value, booking_id)
    {
        var elementId = '';
        if(value == 'BC URL') {
            elementId = 'bc_url-' + booking_id;
        } else if(value == 'GL URL') {
            elementId = 'gl_url-' + booking_id;
        } else if(value == 'TV URL') {
            elementId = 'tv_url-' + booking_id;
        } else if(value == 'CUSTOMER NAME') {
            elementId = 'customer_name-' + booking_id;
        } else if(value == 'CUSTOMER MOBILE') {
            elementId = 'customer_mobile-' + booking_id;
        }
        
        // Try to get element using jQuery first, fallback to vanilla JS
        var element = null;
        if (typeof $ !== 'undefined' && $) {
            element = $('#' + elementId);
            if (element.length > 0) {
                var url = element.val();
            } else {
                alert("Element not found");
                return;
            }
        } else {
            element = document.getElementById(elementId);
            if (element) {
                var url = element.value;
            } else {
                alert("Element not found");
                return;
            }
        }
        
        // Try modern clipboard API first, fallback to older method
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function() {
                // alert("Successfully Copied");
            }).catch(function(err) {
                // Fallback to older method if clipboard API fails
                fallbackCopyToClipboard(url);
            });
        } else {
            // Use fallback method for older browsers
            fallbackCopyToClipboard(url);
        }
    }
    
    function fallbackCopyToClipboard(text) {
        var textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        textArea.style.top = "-999999px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            var successful = document.execCommand('copy');
            if (successful) {
                // alert("Successfully Copied");
            } else {
                alert("Failed to copy. Please copy manually.");
            }
        } catch (err) {
            alert("Failed to copy. Please copy manually.");
        }
        
        document.body.removeChild(textArea);
    }
</script>
<script>
function toggleButton() {
    let anyChecked = document.querySelectorAll('.check_item:checked').length > 0;
    document.getElementById('sync-autocount-booking').style.display = anyChecked ? 'inline-block' : 'none';
}

// Check All toggle
document.getElementById('check_all').addEventListener('change', function() {
    let checked = this.checked;
    document.querySelectorAll('.check_item').forEach(cb => cb.checked = checked);
    var bulkBookingSyncToAutocount = "<?php echo $bulkBookingSyncToAutocount; ?>";
    if (bulkBookingSyncToAutocount == true) {
        toggleButton();
    }
});

// Individual checkbox toggle
document.querySelectorAll('.check_item').forEach(cb => {
    cb.addEventListener('change', function() {
        // If one unchecked → uncheck "check_all"
        if (!this.checked) {
            document.getElementById('check_all').checked = false;
        }
        // If all checked → check "check_all"
        else if (document.querySelectorAll('.check_item:checked').length === document.querySelectorAll('.check_item').length) {
            document.getElementById('check_all').checked = true;
        }
        var bulkBookingSyncToAutocount = "<?php echo $bulkBookingSyncToAutocount; ?>";
        if (bulkBookingSyncToAutocount) {
            toggleButton();
        }
    });
});
</script>
<script>
document.getElementById('sync-autocount-booking').addEventListener('click', function() {
    let selected = Array.from(document.querySelectorAll('.check_item:checked'))
                        .map(cb => cb.value);

    if (selected.length === 0) {
        alert("Please select at least one booking.");
        return;
    }

   fetch("<?php echo base_url('Booking/bulkSyncToAutocount'); ?>", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ booking_ids: selected })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message); // ✅ all messages from PHP
        } else {
            alert("❌ " + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert("Error occurred during sync.");
    });
});
</script>