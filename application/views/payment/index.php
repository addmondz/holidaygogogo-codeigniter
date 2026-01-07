<?php
    ini_set("memory_limit","512M");
?>

<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <?php if($this->session->flashdata('message_success')) { ?>
            <div class="alert alert-custom alert-light-success fade show mb-5">
                <div class="alert-icon">
                    <i class="la la-check-circle"></i>
                </div>
                <div class="alert-text"><?php echo $this->session->flashdata('message_success'); ?></div>
                <div class="alert-close">
                    <button type="button" data-dismiss="alert" class="close">
                        <span>
                            <i class="ki ki-close"></i>
                        </span>
                    </button>
                </div>
            </div>
        <?php } ?>
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Payment Records</strong>
                    </h3>
                </div> 
                <div class="card-toolbar">
                    <?php $current_url = base_url($_SERVER['REQUEST_URI']); ?>
                    <?php if(in_array('GP', $this->session->access_control)) { ?>
                        <a href="<?php if(strpos($current_url, '?') == true) { echo base_url('Payment/Create?') . (explode('?', $current_url))[1]; } else { echo base_url('Payment/Create'); } ?>" class="btn btn-primary font-weight-bold mr-1 mb-2" style="width:180px;">
                            <i class="la la-dollar"></i>Create Payment
                        </a>
                    <?php } ?>
                    <a href="<?php if(strpos($current_url, '?') == true) { echo base_url('Payment/Download?') . (explode('?', $current_url))[1]; } else { echo base_url('Payment/Download'); } ?>" class="btn btn-light-warning font-weight-bold mr-1 mb-2" style="width:180px;">
                        <i class="las la-arrow-circle-down"></i>Payment Records
                    </a>
                    <?php if(!empty($this->input->get('booking_number'))) { ?>
                        <a href="<?php if(!empty($payments)) { echo base_url('Booking_Confirmation?token=') . $payments[0]->Token; } else { echo base_url('Booking_Confirmation?token=') . $token; } ?>" target="_blank" class="btn btn-light-info font-weight-bold mr-1 mb-2" style="width:180px;">
                            <i class="la la-suitcase"></i>BC
                        </a>
                        <a href="<?php if(!empty($payments)) { echo base_url('Guest_List?gl=') . $payments[0]->Token; } else { echo base_url('Guest_List?gl=') . $token; } ?>" class="btn btn-light-primary font-weight-bold mr-1 mb-2" style="width:180px;">
                            <i class="la la-user-friends"></i>GL
                        </a>
                        <?php if(in_array('AB', $this->session->access_control)) { ?>
                            <a href="<?php if(!empty($payments)) { echo base_url('Booking/Update?booking_id=') . $payments[0]->BookingID; } else { echo base_url('Booking/Update?booking_id=') . $booking_id; } ?>" class="btn btn-light-success font-weight-bold mb-2" style="width:180px;">
                                <i class="la la-suitcase"></i>Booking
                            </a>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>
            <div class="card-body">
                <div class="accordion accordion-solid accordion-toggle-plus">
                    <div class="card">
                        <div class="card-header">
                            <div id="payment_header" data-toggle="collapse" data-target="#payment_info" class="card-title collapsed" style="font-size:13px;">Filter By Booking / Payment Information</div>
                        </div>
                        <div id="payment_info" class="collapse">
                            <div class="card-body">
                                <form id="form1" action="<?php echo base_url('Payment') ?>" method="get" class="form">
                                    <strong>Booking :</strong>
                                    <br><br>
                                    <div class="row">
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
                                    <strong>Payment :</strong>
                                    <br><br>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Transaction Date
                                                    <a onclick="Reset_Transaction_Date()" class="btn btn-icon btn-light-warning btn-xs">
                                                        <i class="la la-undo"></i>
                                                    </a>
                                                </label>
                                                <div id="kt_daterangepicker_5" class="input-icon">
                                                    <input readonly type="text" name="transaction_date" value="<?php if(!empty($this->input->get('transaction_date'))) { echo $this->input->get('transaction_date'); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-calendar"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Payment Deadline
                                                    <a onclick="Reset_Payment_Deadline()" class="btn btn-icon btn-light-warning btn-xs">
                                                        <i class="la la-undo"></i>
                                                    </a>
                                                </label>
                                                <div id="kt_daterangepicker_3" class="input-icon">
                                                    <input readonly type="text" name="payment_deadline" value="<?php if(!empty($this->input->get('payment_deadline'))) { echo $this->input->get('payment_deadline'); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-calendar"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Status</label>
                                                <select name="status" class="form-control selectpicker">
                                                    <option data-icon="la la-dollar font-size-lg bs-icon" value="">--SELECT STATUS--</option>
                                                    <?php foreach(unserialize(PAYMENT_STATUS) as $key => $value) { ?>
                                                        <option data-icon="<?php if($key == 'Y') { echo 'la la-check-circle'; } else if($key == 'P') { echo 'la la-exclamation-circle'; } else { echo 'la la-times-circle'; } ?> font-size-lg bs-icon" value="<?php echo $key; ?>" <?php if((!empty($this->input->get('status')) && $this->input->get('status') == $key) || (empty($this->input->get('status')) && $key == 'P' && strpos($_SERVER['REQUEST_URI'], '?') == false)) { echo 'selected'; } ?>><?php echo $value; ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Payment Type</label>
                                                <select name="payment_type" class="form-control selectpicker">
                                                    <option selected data-icon="la la-dollar font-size-lg bs-icon" value="">--SELECT PAYMENT TYPE--</option>
                                                    <?php foreach(unserialize(PAYMENT_TYPE) as $key => $value) { ?>
                                                        <option data-icon="la la-dollar font-size-lg bs-icon" value="<?php echo $key; ?>" <?php if(!empty($this->input->get('payment_type')) && $this->input->get('payment_type') == $key) { echo 'selected'; } ?>><?php echo $value; ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Transaction Type</label>
                                                <select name="transaction_type" class="form-control selectpicker">
                                                    <option selected data-icon="la la-dollar font-size-lg bs-icon" value="">--SELECT TRANSACTION TYPE--</option>
                                                    <?php foreach(unserialize(TRANSACTION_TYPE) as $key => $value) { ?>
                                                        <option data-icon="<?php if($key == 'PAYMENT IN') { echo 'la la-receipt'; } else { echo 'la la-file-invoice-dollar'; } ?> font-size-lg bs-icon" value="<?php echo $key; ?>" <?php if(!empty($this->input->get('transaction_type')) && $this->input->get('transaction_type') == $key) { echo 'selected'; } ?>><?php echo $value; ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Reference Number</label>
                                                <div class="input-icon">
                                                    <input type="text" name="reference_number" value="<?php if(!empty($this->input->get('reference_number'))) { echo strtoupper($this->input->get('reference_number')); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-clipboard-list"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Supplier</label>
                                                <select name="supplier" data-live-search="true" class="form-control selectpicker">
                                                    <option selected data-icon="la la-user-alt font-size-lg bs-icon" value="">--SELECT SUPPLIER--</option>
                                                    <?php foreach($suppliers as $supplier) { ?>
                                                        <option data-icon="la la-user-alt font-size-lg bs-icon" value="<?php echo $supplier->SupplierID; ?>" <?php if(!empty($this->input->get('supplier')) && $this->input->get('supplier') == $supplier->SupplierID) { echo 'selected'; } ?>><?php echo $supplier->Name; ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Quotation Number</label>
                                                <div class="input-icon">
                                                    <input type="text" name="quotation_number" value="<?php if(!empty($this->input->get('quotation_number'))) { echo strtoupper($this->input->get('quotation_number')); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-clipboard-list"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Invoice Number</label>
                                                <div class="input-icon">
                                                    <input type="text" name="invoice_number" value="<?php if(!empty($this->input->get('invoice_number'))) { echo strtoupper($this->input->get('invoice_number')); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-clipboard-list"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Bank</label>
                                                <div class="input-icon">
                                                    <input type="text" name="bank" value="<?php if(!empty($this->input->get('bank'))) { echo strtoupper($this->input->get('bank')); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-landmark"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Bank Account</label>
                                                <div class="input-icon">
                                                    <input type="text" name="bank_account" value="<?php if(!empty($this->input->get('bank_account'))) { echo strtoupper($this->input->get('bank_account')); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-clipboard-list"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Bank Holder</label>
                                                <div class="input-icon">
                                                    <input type="text" name="bank_holder" value="<?php if(!empty($this->input->get('bank_holder'))) { echo strtoupper($this->input->get('bank_holder')); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-user"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Autocount Reference</label>
                                                <div class="input-icon">
                                                    <input type="text" name="autocount_reference" value="<?php if(!empty($this->input->get('autocount_reference'))) { echo strtoupper($this->input->get('autocount_reference')); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-hashtag"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Autocount Status</label>
                                                <select name="autocount_status" class="form-control selectpicker">
                                                    <option value="">--SELECT AUTOCOUNT STATUS--</option>
                                                    <option data-icon="la la-clock font-size-lg bs-icon" value="P" <?php if($this->input->get('autocount_status') == 'P') echo 'selected'; ?>>Pending</option>
                                                    <option data-icon="la la-check-circle font-size-lg bs-icon" value="S" <?php if($this->input->get('autocount_status') == 'S') echo 'selected'; ?>>Synced</option>
                                                    <option data-icon="la la-times-circle font-size-lg bs-icon" value="F" <?php if($this->input->get('autocount_status') == 'F') echo 'selected'; ?>>Failed</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="button" id="filter" value="Filter" class="btn btn-light-success font-weight-bold" style="width:80px;">
                                    <input type="button" id="reset" value="Reset" class="btn btn-light-primary font-weight-bold" style="width:80px;">
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if($this->session->userdata('level') != 20 && in_array('AP', $this->session->access_control)) { ?>
                    <br>
                    <div class="accordion accordion-solid accordion-toggle-plus">
                        <div class="card">
                            <div class="card-header">
                                <div data-toggle="collapse" data-target="#bulk_update" class="card-title collapsed" style="font-size:13px;">Update Payment Status</div>
                            </div>
                            <div id="bulk_update" class="collapse">
                                <div class="card-body">
                                    <form id="form" action="<?php echo base_url('Payment/Bulk_Update?url=' . urlencode(base_url($_SERVER['REQUEST_URI']))) ?>" method="post" class="form">
                                        <input type="hidden" name="payment_ids">
                                        <input type="hidden" name="payment_ids_exempted_from_updating_transaction_date">
                                        <input type="hidden" name="payment_ids_exempted_from_updating_payment_deadline">
                                        <input type="hidden" name="payment_ids_exempted_from_updating_reference_number">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Transaction Date
                                                        <a onclick="Reset_Date()" class="btn btn-icon btn-light-warning btn-xs">
                                                            <i class="la la-undo"></i>
                                                        </a>
                                                    </label>
                                                    <div class="input-icon">
                                                        <input readonly type="text" name="date" id="kt_datepicker_6" autocomplete="off" class="form-control">
                                                        <span>
                                                            <i class="la la-calendar"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Payment Deadline
                                                        <a onclick="Reset_Deadline()" class="btn btn-icon btn-light-warning btn-xs">
                                                            <i class="la la-undo"></i>
                                                        </a>
                                                    </label>
                                                    <div class="input-icon">
                                                        <input readonly type="text" name="deadline" id="kt_datepicker_5" autocomplete="off" class="form-control">
                                                        <span>
                                                            <i class="la la-calendar"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Reference Number</label>
                                                    <div class="input-icon">
                                                        <input type="text" name="reference" autocomplete="off" class="form-control">
                                                        <span>
                                                            <i class="la la-clipboard-list"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Status</label>
                                                    <select name="action" class="form-control selectpicker">
                                                        <option selected data-icon="la la-dollar font-size-lg bs-icon" value="">--SELECT STATUS--</option>
                                                        <?php foreach(unserialize(PAYMENT_STATUS) as $key => $value) { ?>
                                                            <option data-icon="<?php if($key == 'Y') { echo 'la la-check-circle'; } else if($key == 'P') { echo 'la la-exclamation-circle'; } else { echo 'la la-times-circle'; } ?> font-size-lg bs-icon" value="<?php echo $key; ?>"><?php if($key == 'Y') { echo 'APPROVE ALL'; } else if($key == 'P') { echo 'PENDING ALL'; } else { echo 'REJECT ALL'; } ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <input type="submit" value="Submit" class="btn btn-success font-weight-bold" style="width:80px;">
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php } ?>
                <br><br>
                <?php if ($bulkPaymentSyncToAutocount) { ?>
                    <button type="button" 
                            class="btn btn-primary font-weight-bold mb-2" 
                            id="sync-autocount-payment" 
                            style="width:180px; display:none;">
                        Sync Autocount
                    </button>
                <?php } ?>
                <div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if(empty($payments)) { echo 'style="overflow-x:auto;"'; } ?>>
                    <table id="kt_datatable" class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline">
                        <thead>
                            <tr>
                                <th style="text-align:center;">No.</th>
                                <?php if($this->session->userdata('level') != 20 && in_array('AP', $this->session->access_control)) { ?>
                                    <th class="action" style="text-align:center;">
                                        <label class="checkbox checkbox-outline checkbox-success" style="color:#7393B3; font-size:10px;">
                                            <input type="checkbox" id="all">
                                            <span></span>
                                        </label>
                                    </th>
                                <?php } ?>
                                <th class="transaction_date" style="text-align:center;">Transaction Date</th>
                                <?php if($this->session->userdata('level') != 20) { ?>
                                    <th style="text-align:center;">SA</th>
                                <?php } ?>
                                <th style="text-align:center;">BC</th>
                                <th style="text-align:center;">Customer</th>
                                <th style="text-align:center;">Reservation</th>
                                <th class="start_date" style="text-align:center;">Start</th>
                                <th class="end_date" style="text-align:center;">End</th>
                                <th class="amount_received" style="text-align:center;">Received (RM)</th>
                                <th style="text-align:center;">Type</th>
                                <?php if(empty($this->input->get('payment_deadline'))) { ?>
                                    <th class="in" style="text-align:center;">In (RM)</th>
                                <?php } ?>
                                <th class="out" style="text-align:center;">Out (RM)</th>
                                <th style="text-align:center;">Supplier</th>
                                <th class="deadline" style="text-align:center;">Deadline</th>
                                <th style="text-align:center;">Reference</th>
                                <th style="text-align:center;">Autocount Reference</th>
                                <th class="status" style="text-align:center;">Status</th>
                                <th class="autocount_sync_status" style="text-align:center;">Autocount Status</th>
                                <th class="action" style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data loaded via AJAX server-side processing -->
                        </tbody>
                    </table>
                    <?php if(!empty($this->input->get('payment_deadline'))) { ?>
                        <br>
                        <div class="row">
                            <div class="col-md-6 accordion accordion-solid accordion-toggle-plus mb-7 mb-md-0">
                                <div class="card">
                                    <div class="card-header">
                                        <div data-toggle="collapse" data-target="#supplier_payments_info" class="card-title collapsed" style="font-size:13px;">Supplier Payments</div>
                                    </div>
                                    <div id="supplier_payments_info" class="collapse">
                                        <div class="card-body">
                                            <div class="pt-1 pb-1">
                                                <div class="row">
                                                    <?php if(!empty($supplier_payments)) {
                                                        $count = 0;
                                                        foreach($supplier_payments as $key => $value) {
                                                        $count++; ?>
                                                            <div class="col-md-9">
                                                                <a href="<?php echo base_url('Payment?supplier=') . $supplier_ids[$count - 1] . '&payment_deadline=' . $this->input->get('payment_deadline'); ?>" target="_blank"><?php echo $count . '. ' . $key; ?></a>
                                                            </div>
                                                            <div class="col-md-3"><?php echo 'RM ' . number_format($value, 2, '.', ','); ?></div>
                                                    <?php }
                                                        echo '<br><br><div class="col-md-9"><strong>Total</strong></div>' . 
                                                            '<div class="col-md-3"><strong>' . $total_supplier_payment . '</strong></div>';
                                                    } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 accordion accordion-solid accordion-toggle-plus">
                                <div class="card">
                                    <div class="card-header">
                                        <div data-toggle="collapse" data-target="#customer_refunds_info" class="card-title collapsed" style="font-size:13px;">Customer Refunds</div>
                                    </div>
                                    <div id="customer_refunds_info" class="collapse">
                                        <div class="card-body">
                                            <div class="pt-1 pb-1">
                                                <div class="row">
                                                    <?php if(!empty($customer_refunds)) {
                                                        $count = 0;
                                                        foreach($customer_refunds as $key => $value) {
                                                        $count++; ?>
                                                            <div class="col-md-9">
                                                                <a href="<?php echo base_url('Payment?payment_deadline=') . $this->input->get('payment_deadline') . '&bank_holder=' . $key; ?>" target="_blank"><?php echo $count . '. ' . $key; ?></a>
                                                            </div>
                                                            <div class="col-md-3"><?php echo 'RM ' . number_format($value, 2, '.', ','); ?></div>
                                                    <?php }
                                                        echo '<br><br><div class="col-md-9"><strong>Total</strong></div>' . 
                                                            '<div class="col-md-3"><strong>' . $total_customer_refund . '</strong></div>';
                                                    } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    <?php if(!empty($this->input->get('booking_number'))) { ?>
                        <br>
                        <div class="row">
                            <div class="col-md-6 pt-3 pb-3 ml-auto" style="background-color:white; border:3px solid #D7E2F2; border-radius:8px;">
                                <label style="color:#9FE2BF;">Booking Sales (RM)</label>
                                <div class="input-icon">
                                    <input disabled type="text" value="<?php echo $booking_subtotal; ?>" class="form-control" style="text-align:right;">
                                    <span>
                                        <i class="la la-dollar"></i>
                                    </span>
                                </div>
                                <br>
                                <label style="color:#FFAA33;">Customer Outstanding (RM)</label>
                                <div class="input-icon">
                                    <input disabled type="text" value="<?php echo $outstanding_balance_by_customer; ?>" class="form-control" style="text-align:right;">
                                    <span>
                                        <i class="la la-dollar"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    <?php if($this->session->userdata('level') != 20) { ?>
                        <div class="row">
                            <br>
                            <div class="col-md-12 pt-3 pb-3" style="background-color:white; border:3px solid #D7E2F2; border-radius:8px;">
                                <div class="row">
                                    <div class="col-md-4 mb-7 mb-md-0">
                                        <label style="color:#2AAA8A;">Total Payment In (RM)</label>
                                        <div class="input-icon">
                                            <input disabled type="text" id="total_credit_display" value="Loading..." class="form-control" style="text-align:right;">
                                            <span>
                                                <i class="la la-dollar"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-7 mb-md-0">
                                        <label style="color:#F88379;">- Total Payment Out (RM)</label>
                                        <div class="input-icon">
                                            <input disabled type="text" id="total_debit_display" value="Loading..." class="form-control" style="text-align:right;">
                                            <span>
                                                <i class="la la-dollar"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label style="color:#FFC000;">= Total Net Profit (RM)</label>
                                        <div class="input-icon">
                                            <input disabled type="text" id="total_net_profit_display" value="Loading..." class="form-control" style="text-align:right;">
                                            <span>
                                                <i class="la la-dollar"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    <br>
                    <?php if(in_array('GP', $this->session->access_control)) { ?>
                        <a href="<?php if(strpos($current_url, '?') == true) { echo base_url('Payment/Create?') . (explode('?', $current_url))[1]; } else { echo base_url('Payment/Create'); } ?>" class="btn btn-primary font-weight-bold" style="width:180px; float:right;">
                            <i class="la la-dollar"></i>Create Payment
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var current_date = (new Date()).toLocaleDateString();
    var payment_ids = [];

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
            $('input[name="transaction_date"]').val(moment().format('DD/MM/YYYY') + ' - ' + moment().format('DD/MM/YYYY'));
        }
    });

    $('#kt_daterangepicker_3').on('apply.daterangepicker', function(event, daterange) {
        var start_date = (new Date(daterange.startDate._d)).toLocaleDateString();
        var end_date = (new Date(daterange.endDate._d)).toLocaleDateString();
        if(start_date == current_date && end_date == current_date) {
            $('input[name="payment_deadline"]').val(moment().format('DD/MM/YYYY') + ' - ' + moment().format('DD/MM/YYYY'));
        }
    });

    function Reset_Travel_Date() {
        $('input[name="travel_date"]').val('');
    }

    function Reset_Transaction_Date() {
        $('input[name="transaction_date"]').val('');
    }

    function Reset_Payment_Deadline() {
        $('input[name="payment_deadline"]').val('');
    }

    function Reset_Date() {
        $('input[name="date"]').val('');
    }

    function Reset_Deadline() {
        $('input[name="deadline"]').val('');
    }
    
    <?php if(!empty($this->input->get('supplier')) || !empty($this->input->get('transaction_date')) || !empty($this->input->get('payment_type')) || !empty($this->input->get('transaction_type')) || !empty($this->input->get('reference_number')) || !empty($this->input->get('payment_deadline')) || !empty($this->input->get('quotation_number')) || !empty($this->input->get('invoice_number')) || !empty($this->input->get('bank')) || !empty($this->input->get('bank_account')) || !empty($this->input->get('bank_holder')) || !empty($this->input->get('status')) || !empty($this->input->get('booking_number')) || !empty($this->input->get('customer')) || !empty($this->input->get('travel_date')) || !empty($this->input->get('sales_agent')) || !empty($this->input->get('autocount_reference'))) { ?>
        $('#payment_header').click();
    <?php } ?>

    $('#filter').click(function() {
        $('#form1').submit();
    });

    $('#reset').click(function() {
        Reset('<?php echo base_url('Payment'); ?>');
    });
    
    $('#all').click(function() {
        payment_ids = [];
        var isChecked = $('#all').is(':checked');

        // Get all visible checkboxes from the DataTable
        $('.check_item').each(function() {
            var paymentId = $(this).attr('id');
            if(isChecked) {
                $(this).prop('checked', true);
                payment_ids.push(parseInt(paymentId));
            } else {
                $(this).prop('checked', false);
            }
        });

        var bulkPaymentSyncToAutocount = "<?php echo $bulkPaymentSyncToAutocount; ?>";
        if (bulkPaymentSyncToAutocount) {
            if (isChecked) {
                $('#sync-autocount-payment').show();
            } else {
                $('#sync-autocount-payment').hide();
            }
        }
    });

    function Select_Payment(payment_id) {
        if ($(`#${payment_id}`).is(':checked')) {
            // Add payment_id to the selected list
            payment_ids.push(payment_id);
        } else {
            // Remove payment_id from the selected list
            payment_ids = payment_ids.filter(function(value) {
                return value != payment_id;
            });
        }

        // If at least one payment is selected, show the "Sync Autocount" button
        var bulkPaymentSyncToAutocount = "<?php echo $bulkPaymentSyncToAutocount; ?>";
        if (bulkPaymentSyncToAutocount) {
            if (payment_ids.length > 0) {
                $('#sync-autocount-payment').show();
            } else {
                $('#sync-autocount-payment').hide();
            }
        }

        // Update the "Select All" checkbox based on visible checkboxes
        var totalVisible = $('.check_item').length;
        var totalChecked = $('.check_item:checked').length;
        if (totalChecked == totalVisible && totalVisible > 0) {
            $('#all').prop('checked', true);
        } else {
            $('#all').prop('checked', false);
        }
    }

    
    $('input[type="submit"]').click(function(event) {
        event.preventDefault();
        var date = $('input[name="date"]').val();
        var deadline = $('input[name="deadline"]').val();
        var reference = $('input[name="reference"]').val();
        var action = $('select[name="action"]').val();
        var credit_records = [];
        var credit_ids = [];
        var debit_records = [];
        var debit_ids = [];
        var payment_ids_exempted_from_updating_transaction_date = [];
        var payment_ids_exempted_from_updating_payment_deadline = [];
        var payment_ids_exempted_from_updating_reference_number = [];
        if(payment_ids.length == 0) {
            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Select Payment(s)', null);
        } else {
            if(date == '' && deadline == '' && reference == '' && action == '') {
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert Transaction Date Or Payment Deadline Or Reference Number Or Select Status', null);
            } else {
                for(var i = 0; i < payment_ids.length; i++) {
                    if($(`#credit-${payment_ids[i]}`).html() != '') {
                        credit_records.push($(`#count-${payment_ids[i]}`).html());
                        credit_ids.push(payment_ids[i]);
                    } else {
                        debit_records.push($(`#count-${payment_ids[i]}`).html());
                        debit_ids.push(payment_ids[i]);
                    }
                }
                if(date != '') {
                    if(credit_records.length > 0) {
                        for(var i = 0; i < credit_records.length; i++) {
                            if(!confirm(`Replace Payment Record ${credit_records[i]}'s Transaction Date ?`)) {
                                $('#all').prop('checked', false);
                                if(deadline == '' && reference == '' && action == '') {
                                    $(`#${credit_ids[i]}`).prop('checked', false);
                                    payment_ids = payment_ids.filter(function(value) {
                                        return value != credit_ids[i];
                                    });
                                } else {
                                    payment_ids_exempted_from_updating_transaction_date.push(credit_ids[i]);
                                }
                            }
                        }
                    }
                    if(debit_records.length > 0) {
                        for(var i = 0; i < debit_records.length; i++) {
                            if($(`#date-${debit_ids[i]}`).html() != '' && !confirm(`Replace Payment Record ${debit_records[i]}'s Transaction Date ?`)) {
                                $('#all').prop('checked', false);
                                if(deadline == '' && reference == '' && action == '') {
                                    $(`#${debit_ids[i]}`).prop('checked', false);
                                    payment_ids = payment_ids.filter(function(value) {
                                        return value != debit_ids[i];
                                    });
                                } else {
                                    payment_ids_exempted_from_updating_transaction_date.push(debit_ids[i]);
                                }
                            }
                        }
                    }
                }
                if(deadline != '') {
                    if(credit_records.length > 0) {
                        for(var i = 0; i < credit_records.length; i++) {
                            if($(`#deadline-${credit_ids[i]}`).html() != '' && !confirm(`Replace Payment Record ${credit_records[i]}'s Payment Deadline ?`)) {
                                $('#all').prop('checked', false);
                                if(date == '' && reference == '' && action == '') {
                                    $(`#${credit_ids[i]}`).prop('checked', false);
                                    payment_ids = payment_ids.filter(function(value) {
                                        return value != credit_ids[i];
                                    });
                                } else {
                                    payment_ids_exempted_from_updating_payment_deadline.push(credit_ids[i]);
                                }
                            }
                        }
                    }
                    if(debit_records.length > 0) {
                        for(var i = 0; i < debit_records.length; i++) {
                            if(!confirm(`Replace Payment Record ${debit_records[i]}'s Payment Deadline ?`)) {
                                $('#all').prop('checked', false);
                                if(date == '' && reference == '' && action == '') {
                                    $(`#${debit_ids[i]}`).prop('checked', false);
                                    payment_ids = payment_ids.filter(function(value) {
                                        return value != debit_ids[i];
                                    });
                                } else {
                                    payment_ids_exempted_from_updating_payment_deadline.push(debit_ids[i]);
                                }
                            }
                        }
                    }
                }
                if(reference != '') {
                    if(credit_records.length > 0) {
                        for(var i = 0; i < credit_records.length; i++) {
                            if($(`#reference_number-${credit_ids[i]}`).html() != '' && !confirm(`Replace Payment Record ${credit_records[i]}'s Reference Number ?`)) {
                                $('#all').prop('checked', false);
                                if(date == '' && deadline == '' && action == '') {
                                    $(`#${credit_ids[i]}`).prop('checked', false);
                                    payment_ids = payment_ids.filter(function(value) {
                                        return value != credit_ids[i];
                                    });
                                } else {
                                    payment_ids_exempted_from_updating_reference_number.push(credit_ids[i]);
                                }
                            }
                        }
                    }
                    if(debit_records.length > 0) {
                        for(var i = 0; i < debit_records.length; i++) {
                            if($(`#reference_number-${debit_ids[i]}`).html() != '' && !confirm(`Replace Payment Record ${debit_records[i]}'s Reference Number ?`)) {
                                $('#all').prop('checked', false);
                                if(date == '' && deadline == '' && action == '') {
                                    $(`#${debit_ids[i]}`).prop('checked', false);
                                    payment_ids = payment_ids.filter(function(value) {
                                        return value != debit_ids[i];
                                    });
                                } else {
                                    payment_ids_exempted_from_updating_reference_number.push(debit_ids[i]);
                                }
                            }
                        }
                    }
                }
                if(action == 'Y') {
                    debit_records = [];
                    for(var i = 0; i < payment_ids.length; i++) {
                        if($(`#date-${payment_ids[i]}`).html() == '' && $(`#reference_number-${payment_ids[i]}`).html() == '' && $(`#credit-${payment_ids[i]}`).html() == '') {
                            debit_records.push($(`#count-${payment_ids[i]}`).html());
                        }
                    }
                    if(debit_records.length > 0) {
                        if(date == '' || reference == '') {
                            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Could Not Approve Debit Payment(s) Without Transaction Date And Reference Number', null);
                            return;
                        }
                    }
                }
                if(payment_ids.length > 0) {
                    $('input[name="payment_ids"]').val(payment_ids);
                    $('input[name="payment_ids_exempted_from_updating_transaction_date"]').val(payment_ids_exempted_from_updating_transaction_date);
                    $('input[name="payment_ids_exempted_from_updating_payment_deadline"]').val(payment_ids_exempted_from_updating_payment_deadline);
                    $('input[name="payment_ids_exempted_from_updating_reference_number"]').val(payment_ids_exempted_from_updating_reference_number);
                    $('#form').submit();
                }
            }
        }
    });
</script>
<script>
document.getElementById('sync-autocount-payment').addEventListener('click', function() {
    let selected = Array.from(document.querySelectorAll('.check_item:checked'))
                        .map(cb => cb.value);

    if (selected.length === 0) {
        alert("Please select at least one payment.");
        return;
    }

   fetch("<?php echo base_url('Payment/bulkSyncToAutocount'); ?>", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ payment_ids: selected })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
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

<!-- Server-Side DataTables Initialization -->
<script>
var paymentTable;
var is_sales_agent = <?php echo $this->session->userdata('level') == 20 ? 'true' : 'false'; ?>;
var has_ap_permission = <?php echo in_array('AP', $this->session->access_control) ? 'true' : 'false'; ?>;
var has_payment_deadline_filter = <?php echo !empty($this->input->get('payment_deadline')) ? 'true' : 'false'; ?>;

$(document).ready(function() {
    setTimeout(function() {
        // Destroy existing DataTable if it exists
        if ($.fn.DataTable.isDataTable('#kt_datatable')) {
            $('#kt_datatable').DataTable().destroy();
        }

        // Build columns array based on user role and filters
        var columns = [
            { data: 'row_number', orderable: false, searchable: false, className: 'text-center' },
        ];

        // Checkbox column (only for non-SA users with AP permission)
        if (!is_sales_agent && has_ap_permission) {
            columns.push({ data: 'checkbox', orderable: false, searchable: false, className: 'text-center' });
        }

        columns.push({ data: 'transaction_date', className: 'text-center' });

        // Sales agent column (hidden for SA users)
        if (!is_sales_agent) {
            columns.push({ data: 'sales_agent', className: 'text-center' });
        }

        columns = columns.concat([
            { data: 'booking_number', className: 'text-center' },
            { data: 'customer', className: 'text-center' },
            { data: 'reservation', className: 'text-center' },
            { data: 'start_date', className: 'text-center' },
            { data: 'end_date', className: 'text-center' },
            { data: 'total_credit', className: 'text-center' },
            { data: 'type', className: 'text-center' },
        ]);

        // Credit/In column (hidden when payment_deadline filter is set)
        if (!has_payment_deadline_filter) {
            columns.push({ data: 'credit', className: 'text-center' });
        }

        columns = columns.concat([
            { data: 'debit', className: 'text-center' },
            { data: 'supplier', className: 'text-center' },
            { data: 'deadline', className: 'text-center' },
            { data: 'reference', className: 'text-center' },
            { data: 'autocount_ref', className: 'text-center' },
            { data: 'status', orderable: false, className: 'text-center' },
            { data: 'autocount_status', orderable: false, className: 'text-center' },
            { data: 'action', orderable: false, searchable: false, className: 'text-center' }
        ]);

        // Get current filter params from URL
        var urlParams = new URLSearchParams(window.location.search);
        var filterParams = {};
        var filterKeys = ['booking_number', 'customer', 'travel_date', 'transaction_date', 'payment_deadline',
            'status', 'payment_type', 'transaction_type', 'reference_number', 'supplier',
            'quotation_number', 'invoice_number', 'bank', 'bank_account', 'bank_holder', 'sales_agent', 'autocount_reference', 'autocount_status'];

        filterKeys.forEach(function(param) {
            if (urlParams.has(param)) {
                filterParams[param] = urlParams.get(param);
            }
        });

        // Initialize DataTable with server-side processing
        paymentTable = $('#kt_datatable').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: {
                url: '<?php echo base_url("Payment/ajax_list"); ?>',
                type: 'GET',
                data: function(d) {
                    for (var key in filterParams) {
                        d[key] = filterParams[key];
                    }
                    return d;
                }
            },
            columns: columns,
            order: [[is_sales_agent && has_ap_permission ? 1 : (is_sales_agent ? 1 : 2), 'desc']],
            pageLength: 100,
            lengthMenu: [[50, 100, 200, 500], [50, 100, 200, 500]],
            searchDelay: 300,
            language: {
                processing: '<div class="spinner spinner-primary spinner-lg mr-15"></div> Loading...',
                emptyTable: 'Payment Records Not Found',
                zeroRecords: 'No matching records found'
            },
            drawCallback: function(settings) {
                // Re-init tooltips and checkbox listeners after each draw
                $('[data-toggle="tooltip"]').tooltip();
                // Reset selection state
                payment_ids = [];
                $('#all').prop('checked', false);
                $('#sync-autocount-payment').hide();
            }
        });

        // Load summary totals
        loadPaymentSummary();
    }, 100);
});

function loadPaymentSummary() {
    var urlParams = new URLSearchParams(window.location.search);
    var params = [];
    var filterKeys = ['booking_number', 'customer', 'travel_date', 'transaction_date', 'payment_deadline',
        'status', 'payment_type', 'transaction_type', 'reference_number', 'supplier',
        'quotation_number', 'invoice_number', 'bank', 'bank_account', 'bank_holder', 'sales_agent', 'autocount_reference'];

    filterKeys.forEach(function(param) {
        if (urlParams.has(param)) {
            params.push(param + '=' + encodeURIComponent(urlParams.get(param)));
        }
    });

    var queryString = params.length > 0 ? '?' + params.join('&') : '';

    $.ajax({
        url: '<?php echo base_url("Payment/ajax_summary"); ?>' + queryString,
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            $('#total_credit_display').val(data.total_credit);
            $('#total_debit_display').val(data.total_debit);
            $('#total_net_profit_display').val(data.total_net_profit);
        },
        error: function() {
            console.error('Failed to load payment summary');
            $('#total_credit_display').val('Error');
            $('#total_debit_display').val('Error');
            $('#total_net_profit_display').val('Error');
        }
    });
}
</script>