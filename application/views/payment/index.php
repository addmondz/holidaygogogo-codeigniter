<?php
    ini_set("memory_limit","512M");
    // Autocount status is only relevant to Owner (10) and Finance (30)
    $show_autocount_status = in_array((int)$this->session->userdata('level'), [10, 30]);
?>
<style>
.booking-quick-range .btn {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}
</style>

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
        <?php
            $invoice_download_url = base_url('Payment/Download_Supplier_Invoices');
            if (!empty($this->input->get('supplier'))) {
                $invoice_download_url .= '?supplier=' . urlencode($this->input->get('supplier'));
            }
        ?>
        <div class="row mb-5">
            <div class="col-12">
                <div class="card" style="border:1px solid #FFCC80;">
                    <div class="card-header" style="background-color:#FFF3E0; padding: 0.75rem 1.25rem;">
                        <div class="d-flex align-items-center justify-content-between flex-wrap">
                            <div style="font-size: 0.95rem; color: #E65100;">
                                <strong>Supplier Invoices &mdash; Outstanding</strong>
                                <span class="ml-3" style="color:#6c757d; font-size: 0.875rem;">
                                    Total outstanding: <strong style="color:#C62828;"><?php echo $total_supplier_invoice_outstanding; ?></strong>
                                </span>
                            </div>
                            <div>
                                <a href="<?php echo $invoice_download_url; ?>" class="btn btn-success btn-sm font-weight-bold">
                                    <i class="la la-file-excel-o"></i> Export to Excel
                                </a>
                                <button type="button"
                                        class="btn btn-light btn-sm font-weight-bold ml-1"
                                        data-toggle="collapse"
                                        data-target="#supplier-invoice-summary-body">
                                    Toggle
                                </button>
                            </div>
                        </div>
                    </div>
                    <div id="supplier-invoice-summary-body" class="collapse show">
                        <div class="card-body p-3">
                            <div class="row">
                                <div class="col-md-5 mb-3 mb-md-0">
                                    <div style="font-size:0.875rem; font-weight:600; color:#424242; margin-bottom:6px;">Outstanding by Supplier</div>
                                    <?php if (!empty($supplier_invoice_summary)) { ?>
                                        <table class="table table-sm table-bordered mb-0" style="font-size:0.875rem;">
                                            <thead style="background-color:#F5F5F5;">
                                                <tr>
                                                    <th>Supplier</th>
                                                    <th style="text-align:right; width:80px;">Invoices</th>
                                                    <th style="text-align:right; width:130px;">Outstanding (RM)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($supplier_invoice_summary as $sup_row) {
                                                    $supplier_drill_url = base_url('Payment?supplier=' . (int)$sup_row->SupplierID);
                                                ?>
                                                    <tr>
                                                        <td><a href="<?php echo $supplier_drill_url; ?>"><?php echo htmlspecialchars($sup_row->SupplierName); ?></a></td>
                                                        <td style="text-align:right;"><?php echo (int) $sup_row->InvoiceCount; ?></td>
                                                        <td style="text-align:right; color:#C62828;"><strong><?php echo number_format((float)$sup_row->OutstandingTotal, 2, '.', ','); ?></strong></td>
                                                    </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    <?php } else { ?>
                                        <div class="text-muted" style="font-size:0.875rem;">No outstanding supplier invoices.</div>
                                    <?php } ?>
                                </div>
                                <div class="col-md-7">
                                    <div style="font-size:0.875rem; font-weight:600; color:#424242; margin-bottom:6px;">Invoice Detail</div>
                                    <?php if (!empty($supplier_invoice_line_items)) { ?>
                                        <div class="table-responsive" style="max-height: 340px; overflow-y: auto;">
                                            <table class="table table-sm table-bordered mb-0" style="font-size:0.8125rem;">
                                                <thead style="background-color:#F5F5F5; position: sticky; top: 0; z-index: 1;">
                                                    <tr>
                                                        <th>Booking</th>
                                                        <th>Supplier</th>
                                                        <th>Invoice #</th>
                                                        <th style="text-align:right;">Invoice (RM)</th>
                                                        <th style="text-align:right;">Paid (RM)</th>
                                                        <th style="text-align:right;">Balance (RM)</th>
                                                        <th>Deadline</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($supplier_invoice_line_items as $line) {
                                                        $booking_url = !empty($line->BookingID) ? base_url('Booking/Update?booking_id=' . (int)$line->BookingID) : '#';
                                                        $deadline_disp = !empty($line->PaymentDeadline) ? date('d M Y', strtotime($line->PaymentDeadline)) : '';
                                                    ?>
                                                        <tr>
                                                            <td><a href="<?php echo $booking_url; ?>" target="_blank"><?php echo htmlspecialchars((string)$line->BookingNumber); ?></a></td>
                                                            <td><?php echo htmlspecialchars((string)$line->SupplierName); ?></td>
                                                            <td><?php echo htmlspecialchars((string)$line->InvoiceNumber); ?></td>
                                                            <td style="text-align:right;"><?php echo number_format((float)$line->InvoiceAmount, 2, '.', ','); ?></td>
                                                            <td style="text-align:right; color:#388E3C;"><?php echo number_format((float)$line->PaidAmount, 2, '.', ','); ?></td>
                                                            <td style="text-align:right; color:#C62828;"><strong><?php echo number_format((float)$line->BalanceDue, 2, '.', ','); ?></strong></td>
                                                            <td><?php echo $deadline_disp; ?></td>
                                                        </tr>
                                                    <?php } ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php } else { ?>
                                        <div class="text-muted" style="font-size:0.875rem;">No outstanding invoices.</div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
                            <a href="<?php if(!empty($payments)) { echo base_url('Booking/Update?booking_id=') . $payments[0]->BookingID; } else { echo base_url('Booking/Update?booking_id=') . $booking_id; } ?>" class="btn btn-light-success font-weight-bold mr-1 mb-2" style="width:180px;">
                                <i class="la la-suitcase"></i>Booking
                            </a>
                        <?php } ?>
                        <?php if((in_array('AB', $this->session->access_control) || $this->session->userdata('level') == 20) && !empty($booking_id) && $booking_id != 'NA') { ?>
                            <button type="button" onclick="openRemarksModal(<?php echo $booking_id; ?>, '<?php echo addslashes(strtoupper($this->input->get('booking_number'))); ?>', '<?php echo $sales_agent_id; ?>', '<?php echo $booking_op_id; ?>')" class="btn btn-light-dark font-weight-bold mb-2" style="width:180px;">
                                <i class="la la-comment"></i>View Remark
                            </button>
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
                                                <div class="booking-quick-range mt-2" data-target="travel_date">
                                                    <button type="button" class="btn btn-light-primary btn-sm font-weight-bold mr-1 mb-1" data-range="next7">Next 7 Days</button>
                                                    <button type="button" class="btn btn-light-primary btn-sm font-weight-bold mr-1 mb-1" data-range="next14">Next 14 Days</button>
                                                    <button type="button" class="btn btn-light-primary btn-sm font-weight-bold mr-1 mb-1" data-range="next30">Next 30 Days</button>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if($this->session->userdata('level') != 20) { ?>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Sales Agent</label>
                                                    <?php $selected_sales_agents = !empty($this->input->get('sales_agent')) ? explode(',', $this->input->get('sales_agent')) : []; ?>
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
                                                    <input type="hidden" name="sales_agent" id="sales_agent_hidden" value="<?php echo $this->input->get('sales_agent'); ?>">
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
                                                <div class="booking-quick-range mt-2" data-target="transaction_date">
                                                    <button type="button" class="btn btn-light-primary btn-sm font-weight-bold mr-1 mb-1" data-range="last7">Last 7 Days</button>
                                                    <button type="button" class="btn btn-light-primary btn-sm font-weight-bold mr-1 mb-1" data-range="last14">Last 14 Days</button>
                                                    <button type="button" class="btn btn-light-primary btn-sm font-weight-bold mr-1 mb-1" data-range="last30">Last 30 Days</button>
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
                                                <div class="booking-quick-range mt-2" data-target="payment_deadline">
                                                    <button type="button" class="btn btn-light-primary btn-sm font-weight-bold mr-1 mb-1" data-range="next7">Next 7 Days</button>
                                                    <button type="button" class="btn btn-light-primary btn-sm font-weight-bold mr-1 mb-1" data-range="next14">Next 14 Days</button>
                                                    <button type="button" class="btn btn-light-primary btn-sm font-weight-bold mr-1 mb-1" data-range="next30">Next 30 Days</button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Status</label>
                                                <?php $selected_statuses = !empty($this->input->get('status')) ? explode(',', $this->input->get('status')) : []; ?>
                                                <select id="status_select" class="form-control selectpicker" multiple data-actions-box="true" title="--SELECT STATUS--">
                                                    <?php foreach(unserialize(PAYMENT_STATUS) as $key => $value) { ?>
                                                        <option data-icon="<?php if($key == 'Y') { echo 'la la-check-circle'; } else if($key == 'P') { echo 'la la-exclamation-circle'; } else { echo 'la la-times-circle'; } ?> font-size-lg bs-icon" value="<?php echo $key; ?>" <?php if(in_array($key, $selected_statuses)) { echo 'selected'; } ?>><?php echo $value; ?></option>
                                                    <?php } ?>
                                                </select>
                                                <input type="hidden" name="status" id="status_hidden" value="<?php echo $this->input->get('status'); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Payment Type</label>
                                                <?php $selected_payment_types = !empty($this->input->get('payment_type')) ? explode(',', $this->input->get('payment_type')) : []; ?>
                                                <select id="payment_type_select" class="form-control selectpicker" multiple data-actions-box="true" title="--SELECT PAYMENT TYPE--">
                                                    <?php foreach(unserialize(PAYMENT_TYPE) as $key => $value) { ?>
                                                        <option data-icon="la la-dollar font-size-lg bs-icon" value="<?php echo $key; ?>" <?php if(in_array($key, $selected_payment_types)) { echo 'selected'; } ?>><?php echo $value; ?></option>
                                                    <?php } ?>
                                                </select>
                                                <input type="hidden" name="payment_type" id="payment_type_hidden" value="<?php echo $this->input->get('payment_type'); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Transaction Type</label>
                                                <?php $selected_transaction_types = !empty($this->input->get('transaction_type')) ? explode(',', $this->input->get('transaction_type')) : []; ?>
                                                <select id="transaction_type_select" class="form-control selectpicker" multiple data-actions-box="true" title="--SELECT TRANSACTION TYPE--">
                                                    <?php foreach(unserialize(TRANSACTION_TYPE) as $key => $value) { ?>
                                                        <option data-icon="<?php if($key == 'PAYMENT IN') { echo 'la la-receipt'; } else { echo 'la la-file-invoice-dollar'; } ?> font-size-lg bs-icon" value="<?php echo $key; ?>" <?php if(in_array($key, $selected_transaction_types)) { echo 'selected'; } ?>><?php echo $value; ?></option>
                                                    <?php } ?>
                                                </select>
                                                <input type="hidden" name="transaction_type" id="transaction_type_hidden" value="<?php echo $this->input->get('transaction_type'); ?>">
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
                                                <?php $selected_suppliers = !empty($this->input->get('supplier')) ? explode(',', $this->input->get('supplier')) : []; ?>
                                                <select id="supplier_select" data-live-search="true" class="form-control selectpicker" multiple data-actions-box="true" title="--SELECT SUPPLIER--">
                                                    <?php foreach($suppliers as $supplier) { ?>
                                                        <option data-icon="la la-user-alt font-size-lg bs-icon" value="<?php echo $supplier->SupplierID; ?>" <?php if(in_array($supplier->SupplierID, $selected_suppliers)) { echo 'selected'; } ?>><?php echo $supplier->Name; ?></option>
                                                    <?php } ?>
                                                </select>
                                                <input type="hidden" name="supplier" id="supplier_hidden" value="<?php echo $this->input->get('supplier'); ?>">
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
                                        <?php if($show_autocount_status) { ?>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Autocount Status</label>
                                                <?php $selected_autocount_statuses = !empty($this->input->get('autocount_status')) ? explode(',', $this->input->get('autocount_status')) : []; ?>
                                                <select id="autocount_status_select" class="form-control selectpicker" multiple data-actions-box="true" title="--SELECT AUTOCOUNT STATUS--">
                                                    <option data-icon="la la-clock font-size-lg bs-icon" value="P" <?php if(in_array('P', $selected_autocount_statuses)) echo 'selected'; ?>>Pending</option>
                                                    <option data-icon="la la-check-circle font-size-lg bs-icon" value="S" <?php if(in_array('S', $selected_autocount_statuses)) echo 'selected'; ?>>Synced</option>
                                                    <option data-icon="la la-times-circle font-size-lg bs-icon" value="F" <?php if(in_array('F', $selected_autocount_statuses)) echo 'selected'; ?>>Failed</option>
                                                </select>
                                                <input type="hidden" name="autocount_status" id="autocount_status_hidden" value="<?php echo $this->input->get('autocount_status'); ?>">
                                            </div>
                                        </div>
                                        <?php } ?>
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
                <button type="button"
                        class="btn btn-warning font-weight-bold mb-2"
                        id="change-payment-autocount-to-pending"
                        style="width:180px; display:none;">
                    Change to P Status
                </button>
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
                                <?php if($show_autocount_status) { ?>
                                <th class="autocount_sync_status" style="text-align:center;">Autocount Status</th>
                                <?php } ?>
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

    $(window).on('load', function() {
        $('#kt_daterangepicker_4').daterangepicker({
            buttonClasses: ' btn',
            applyClass: 'btn-primary',
            cancelClass: 'btn-secondary',
            autoApply: true
        }, function(start, end, label) {
            $('#kt_daterangepicker_4 .form-control').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
        });
    });

    $(document).on('click', '.booking-quick-range button', function() {
        var $row     = $(this).closest('.booking-quick-range');
        var target   = $row.data('target');
        var range    = $(this).data('range');
        var pickerMap = {
            'travel_date':      '#kt_daterangepicker_4',
            'transaction_date': '#kt_daterangepicker_5',
            'payment_deadline': '#kt_daterangepicker_3'
        };
        var pickerId = pickerMap[target];

        var start, end;
        switch(range) {
            case 'next7':  start = moment();                       end = moment().add(6, 'days');  break;
            case 'next14': start = moment();                       end = moment().add(13, 'days'); break;
            case 'next30': start = moment();                       end = moment().add(29, 'days'); break;
            case 'last7':  start = moment().subtract(6, 'days');   end = moment();                 break;
            case 'last14': start = moment().subtract(13, 'days');  end = moment();                 break;
            case 'last30': start = moment().subtract(29, 'days');  end = moment();                 break;
            default: return;
        }

        if(pickerId) {
            var picker = $(pickerId).data('daterangepicker');
            if(picker) {
                picker.setStartDate(start);
                picker.setEndDate(end);
            }
        }
        $('input[name="' + target + '"]').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
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

    var multi_filters = ['status','sales_agent','payment_type','transaction_type','supplier','autocount_status'];

    function syncMultiSelect(name) {
        var $sel = $('#' + name + '_select');
        var $hid = $('#' + name + '_hidden');
        if(!$sel.length || !$hid.length) return;
        var v = $sel.val();
        $hid.val(v ? v.join(',') : '');
    }
    function moveSelectedToTop(name) {
        var $sel = $('#' + name + '_select');
        if(!$sel.length) return;
        var $groups = $sel.children('optgroup');
        var changed = false;
        if($groups.length) {
            $groups.each(function() {
                var $grp = $(this);
                var $opts = $grp.children('option');
                var $selected = $opts.filter(':selected');
                if(!$selected.length || $selected.length === $opts.length) return;
                var $unselected = $opts.not(':selected');
                $grp.empty().append($selected).append($unselected);
                changed = true;
            });
        } else {
            var $opts = $sel.children('option');
            var $selected = $opts.filter(':selected');
            if($selected.length && $selected.length < $opts.length) {
                var $unselected = $opts.not(':selected');
                $sel.empty().append($selected).append($unselected);
                changed = true;
            }
        }
        if(changed) $sel.selectpicker('refresh');
    }
    multi_filters.forEach(function(name) {
        var $s = $('#' + name + '_select');
        $s.on('changed.bs.select', function() { syncMultiSelect(name); });
        $s.on('hidden.bs.select', function() { moveSelectedToTop(name); });
        moveSelectedToTop(name);
    });
    $('#form1').on('submit', function() {
        multi_filters.forEach(syncMultiSelect);
    });

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

        if (isChecked) {
            $('#change-payment-autocount-to-pending').show();
        } else {
            $('#change-payment-autocount-to-pending').hide();
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

        if (payment_ids.length > 0) {
            $('#change-payment-autocount-to-pending').show();
        } else {
            $('#change-payment-autocount-to-pending').hide();
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
var syncAutocountBtn = document.getElementById('sync-autocount-payment');
if (syncAutocountBtn) {
    syncAutocountBtn.addEventListener('click', function() {
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
}

document.getElementById('change-payment-autocount-to-pending').addEventListener('click', function() {
    let selected = Array.from(document.querySelectorAll('.check_item:checked'))
                        .map(cb => cb.id);

    if (selected.length === 0) {
        alert("Please select at least one payment.");
        return;
    }

    if (!confirm("Are you sure you want to change " + selected.length + " payment(s) to Pending (P) status?")) {
        return;
    }

    fetch("<?php echo base_url('Payment/bulkChangePaymentAutocountStatusToPending'); ?>", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ payment_ids: selected })
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            paymentTable.ajax.reload();
        }
    })
    .catch(err => {
        console.error(err);
        alert("Error occurred during status change.");
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
            <?php if($show_autocount_status) { ?>
            { data: 'autocount_status', orderable: false, className: 'text-center' },
            <?php } ?>
            { data: 'action', orderable: false, searchable: false, className: 'text-center' }
        ]);

        // Get current filter params from URL
        var urlParams = new URLSearchParams(window.location.search);
        var filterParams = {};
        var filterKeys = ['booking_number', 'customer', 'travel_date', 'transaction_date', 'payment_deadline',
            'status', 'payment_type', 'transaction_type', 'reference_number', 'supplier',
            'quotation_number', 'invoice_number', 'bank', 'bank_account', 'bank_holder', 'sales_agent', 'autocount_reference', 'autocount_status', 'view_mode'];

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
                $('#change-payment-autocount-to-pending').hide();
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
        'quotation_number', 'invoice_number', 'bank', 'bank_account', 'bank_holder', 'sales_agent', 'autocount_reference', 'view_mode'];

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

function escapeHtml(text) {
    if(!text) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}
</script>

<!-- Remarks Modal -->
<div class="modal fade" id="remarksModal" tabindex="-1" role="dialog" aria-labelledby="remarksModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background-color:#D7E2F2;">
                <h5 class="modal-title" id="remarksModalLabel" style="color:#6082B6;">
                    <strong>Remarks</strong>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="remarksModalBody">
                <!-- Internal Comments Section -->
                <h6 class="font-weight-bold mb-2" style="color:#6082B6;">Internal Comments</h6>
                <div id="remarks-internal-list" style="max-height:250px; overflow-y:auto;">
                    <div class="text-center py-3"><div class="spinner spinner-primary spinner-lg"></div></div>
                </div>
                <div class="border-top pt-2 mt-2">
                    <div class="form-group mb-2 mention-wrapper" style="position: relative;">
                        <textarea id="modal-new-comment-content" class="form-control" rows="2" placeholder="Enter your comment here... Type @ to mention a user" style="font-size: 0.8125rem;"></textarea>
                    </div>
                    <button type="button" id="modal-add-comment-btn" class="btn btn-primary btn-sm font-weight-bold mt-2 mb-2">
                        <i class="la la-comment"></i> Add Comment
                    </button>
                </div>

                <hr>

                <!-- Customer Remarks Section -->
                <h6 class="font-weight-bold mb-2" style="color:#388E3C;">Customer Remarks</h6>
                <div id="remarks-customer-list" style="max-height:250px; overflow-y:auto;">
                    <div class="text-center py-3"><div class="spinner spinner-primary spinner-lg"></div></div>
                </div>
                <div class="border-top pt-2 mt-2">
                    <div class="form-group mb-2">
                        <textarea id="modal-new-customer-remark-content" class="form-control" rows="2" placeholder="Add your response or remark here..." style="font-size: 0.8125rem;"></textarea>
                    </div>
                    <button type="button" id="modal-add-customer-remark-btn" class="btn btn-primary btn-sm font-weight-bold mt-2 mb-2">
                        <i class="la la-comment"></i> Add Remark
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var remarksModalBookingId = null;
var remarksModalSalesAgentId = null;
var remarksModalBookingOpId = null;

window.ADMIN_HANDLE_MAP = <?php
    $__map = array();
    if (!empty($notify_admins)) {
        foreach ($notify_admins as $__a) {
            if ($__a->Status === 'Y' && !empty($__a->handle)) {
                $__map[] = array(
                    'AdminID' => (int)$__a->AdminID,
                    'Name' => $__a->Name,
                    'handle' => $__a->handle,
                );
            }
        }
    }
    echo json_encode($__map);
?>;

function renderMentionedContent(text) {
    if (text == null) return '';
    var safe = escapeHtml(String(text));
    var nameByHandle = {};
    (window.ADMIN_HANDLE_MAP || []).forEach(function(a) { nameByHandle[a.handle] = a.Name; });
    return safe.replace(/@([a-z0-9]+)/g, function(full, handle) {
        if (nameByHandle[handle]) {
            return '<span class="mention" style="color:#1877f2;font-weight:600;background:#e7f3ff;padding:1px 4px;border-radius:3px;">@' + escapeHtml(nameByHandle[handle]) + '</span>';
        }
        return full;
    });
}

function initMentionAutocomplete(textareaSelector) {
    var $ta = $(textareaSelector);
    if (!$ta.length || $ta.data('mention-init')) return;
    $ta.data('mention-init', true);

    var admins = (window.ADMIN_HANDLE_MAP || []).slice();
    var $dd = $('<ul class="mention-dropdown"></ul>').css({
        position: 'absolute', zIndex: 1070, background: '#fff',
        border: '1px solid #d0d7de', borderRadius: '4px', padding: '4px 0',
        margin: 0, listStyle: 'none', maxHeight: '180px', overflowY: 'auto',
        minWidth: '180px', boxShadow: '0 4px 12px rgba(0,0,0,0.12)',
        display: 'none', fontSize: '0.8125rem'
    });
    $ta.closest('.mention-wrapper').append($dd);

    var activeIdx = 0;

    function hide() { $dd.hide().empty(); }

    function filter(q) {
        q = q.toLowerCase();
        return admins.filter(function(a) {
            return a.handle.indexOf(q) === 0 || a.Name.toLowerCase().indexOf(q) !== -1;
        }).slice(0, 8);
    }

    function render(list) {
        $dd.empty();
        if (!list.length) { hide(); return; }
        list.forEach(function(a, i) {
            var $li = $('<li></li>').css({
                padding: '6px 10px', cursor: 'pointer',
                background: i === activeIdx ? '#e7f3ff' : 'transparent'
            }).attr('data-handle', a.handle);
            $li.html('<strong>' + escapeHtml(a.Name) + '</strong> <span style="color:#65676b;font-size:0.75rem;">@' + escapeHtml(a.handle) + '</span>');
            $li.on('mousedown', function(e) { e.preventDefault(); pick(a.handle); });
            $li.on('mouseenter', function() { activeIdx = i; render(list); });
            $dd.append($li);
        });
        $dd.show();
    }

    function currentMatch() {
        var val = $ta.val();
        var pos = $ta[0].selectionStart;
        var before = val.slice(0, pos);
        var m = before.match(/(?:^|\s)@([a-z0-9]*)$/i);
        if (!m) return null;
        return { query: m[1].toLowerCase(), start: pos - m[1].length - 1, end: pos };
    }

    function pick(handle) {
        var match = currentMatch();
        if (!match) { hide(); return; }
        var val = $ta.val();
        var newVal = val.slice(0, match.start) + '@' + handle + ' ' + val.slice(match.end);
        var newPos = match.start + handle.length + 2;
        $ta.val(newVal);
        $ta[0].setSelectionRange(newPos, newPos);
        $ta.trigger('focus');
        hide();
    }

    $ta.on('input click keyup', function(e) {
        if (e.type === 'keyup' && (e.keyCode === 38 || e.keyCode === 40 || e.keyCode === 13 || e.keyCode === 27 || e.keyCode === 9)) return;
        var match = currentMatch();
        if (!match) { hide(); return; }
        activeIdx = 0;
        var list = filter(match.query);
        if (!list.length) { hide(); return; }
        $dd.css({ top: ($ta.outerHeight() + 2) + 'px', left: '0px', right: 'auto' });
        render(list);
        $dd.data('current-list', list);
    });

    $ta.on('keydown', function(e) {
        if (!$dd.is(':visible')) return;
        var list = $dd.data('current-list') || [];
        if (e.keyCode === 38) {
            e.preventDefault();
            activeIdx = (activeIdx - 1 + list.length) % list.length;
            render(list);
        } else if (e.keyCode === 40) {
            e.preventDefault();
            activeIdx = (activeIdx + 1) % list.length;
            render(list);
        } else if (e.keyCode === 13 || e.keyCode === 9) {
            if (list[activeIdx]) {
                e.preventDefault();
                e.stopPropagation();
                pick(list[activeIdx].handle);
            }
        } else if (e.keyCode === 27) {
            e.preventDefault();
            hide();
        }
    });

    $ta.on('blur', function() { setTimeout(hide, 150); });
}

function renderRemarkItem(remark) {
    var avatarColor = ['primary', 'success', 'info', 'warning', 'danger'][remark.commenter_name.charCodeAt(0) % 5];
    return '<div class="comment-item d-flex mb-2 mx-2 pb-2 pl-1" style="border-bottom: 1px solid #e4e6eb;">' +
        '<div class="flex-shrink-0 mr-2">' +
        '<div class="symbol symbol-32 symbol-circle symbol-light-' + avatarColor + '">' +
        '<span class="symbol-label font-weight-bold" style="font-size: 0.75rem;">' + (remark.commenter_initials || remark.commenter_name.substring(0, 2).toUpperCase()) + '</span>' +
        '</div>' +
        '</div>' +
        '<div class="flex-grow-1" style="min-width: 0;">' +
        '<div class="d-flex align-items-baseline mb-1">' +
        '<strong class="mr-2" style="font-size: 0.8125rem; color: #050505;">' + escapeHtml(remark.commenter_name) + '</strong>' +
        '<span class="text-muted" style="font-size: 0.75rem; color: #65676b;">' + remark.created_at + '</span>' +
        '</div>' +
        '<div class="comment-text" style="font-size: 0.8125rem; color: #050505; line-height: 1.3; white-space: pre-wrap; word-wrap: break-word;">' + renderMentionedContent(remark.content) + '</div>' +
        '</div>' +
        '</div>';
}

function loadModalInternalComments() {
    $.ajax({
        url: '<?php echo base_url("Booking/Get_Remarks"); ?>',
        type: 'get',
        data: { booking_id: remarksModalBookingId },
        dataType: 'json',
        success: function(response) {
            var list = $('#remarks-internal-list');
            list.empty();
            if (response.success && response.remarks && response.remarks.length > 0) {
                response.remarks.forEach(function(remark) {
                    list.append(renderRemarkItem(remark));
                });
                // Scroll to bottom
                list.scrollTop(list[0].scrollHeight);
            } else {
                list.html('<div class="text-center text-muted py-3" style="font-size: 0.8125rem;">No internal comments yet.</div>');
            }
        },
        error: function() {
            $('#remarks-internal-list').html('<div class="text-center text-danger py-3" style="font-size: 0.8125rem;">Error loading comments.</div>');
        }
    });
}

function loadModalCustomerRemarks() {
    $.ajax({
        url: '<?php echo base_url("Booking/Get_Customer_Remarks"); ?>',
        type: 'get',
        data: { booking_id: remarksModalBookingId },
        dataType: 'json',
        success: function(response) {
            var list = $('#remarks-customer-list');
            list.empty();
            if (response.success && response.remarks && response.remarks.length > 0) {
                response.remarks.forEach(function(remark) {
                    list.append(renderRemarkItem(remark));
                });
                list.scrollTop(list[0].scrollHeight);
            } else {
                list.html('<div class="text-center text-muted py-3" style="font-size: 0.8125rem;">No customer remarks yet.</div>');
            }
        },
        error: function() {
            $('#remarks-customer-list').html('<div class="text-center text-danger py-3" style="font-size: 0.8125rem;">Error loading remarks.</div>');
        }
    });
}

function openRemarksModal(bookingId, bookingNumber, salesAgentId, bookingOpId) {
    remarksModalBookingId = bookingId;
    remarksModalSalesAgentId = salesAgentId;
    remarksModalBookingOpId = bookingOpId;

    $('#remarksModalLabel').html('<strong>Remarks &mdash; ' + escapeHtml(bookingNumber) + '</strong>');
    var spinnerHtml = '<div class="text-center py-3"><div class="spinner spinner-primary spinner-lg"></div></div>';
    $('#remarks-internal-list').html(spinnerHtml);
    $('#remarks-customer-list').html(spinnerHtml);

    // Clear form fields
    $('#modal-new-comment-content').val('');
    $('#modal-new-customer-remark-content').val('');

    $('#remarksModal').modal('show');

    loadModalInternalComments();
    loadModalCustomerRemarks();
}

// Add internal comment from modal
$('#modal-add-comment-btn').on('click', function() {
    var content = $('#modal-new-comment-content').val().trim();
    if (!content) {
        Swal.fire({
            width: 550,
            background: 'url(<?php echo base_url("assets/image/sweetalert.jpg"); ?>)',
            icon: 'warning',
            title: 'Please enter a comment',
            showConfirmButton: false,
            timer: 2000
        });
        return;
    }

    var $btn = $(this);
    var originalText = $btn.html();
    $btn.prop('disabled', true).html('<i class="la la-spinner la-spin"></i> Adding...');

    $.ajax({
        url: '<?php echo base_url("Booking/Add_Remark"); ?>',
        type: 'post',
        data: {
            booking_id: remarksModalBookingId,
            content: content
        },
        dataType: 'json',
        success: function(response) {
            $btn.prop('disabled', false).html(originalText);
            if (response.success) {
                $('#modal-new-comment-content').val('');
                loadModalInternalComments();
                Swal.fire({
                    width: 550,
                    background: 'url(<?php echo base_url("assets/image/sweetalert.jpg"); ?>)',
                    icon: 'success',
                    title: 'Comment added Successfully',
                    showConfirmButton: false,
                    timer: 2200
                });
            } else {
                Swal.fire({
                    width: 550,
                    background: 'url(<?php echo base_url("assets/image/sweetalert.jpg"); ?>)',
                    icon: 'error',
                    title: response.message || 'Failed to add comment',
                    showConfirmButton: false,
                    timer: 3000
                });
            }
        },
        error: function() {
            $btn.prop('disabled', false).html(originalText);
            Swal.fire({
                width: 550,
                background: 'url(<?php echo base_url("assets/image/sweetalert.jpg"); ?>)',
                icon: 'error',
                title: 'Error adding comment. Please try again.',
                showConfirmButton: false,
                timer: 3000
            });
        }
    });
});

// Add customer remark from modal
$('#modal-add-customer-remark-btn').on('click', function() {
    var content = $('#modal-new-customer-remark-content').val().trim();
    if (!content) {
        Swal.fire({
            width: 550,
            background: 'url(<?php echo base_url("assets/image/sweetalert.jpg"); ?>)',
            icon: 'warning',
            title: 'Please enter a remark',
            showConfirmButton: false,
            timer: 2000
        });
        return;
    }

    var $btn = $(this);
    var originalText = $btn.html();
    $btn.prop('disabled', true).html('<i class="la la-spinner la-spin"></i> Adding...');

    $.ajax({
        url: '<?php echo base_url("Booking/Add_Remark"); ?>',
        type: 'post',
        data: {
            booking_id: remarksModalBookingId,
            content: content,
            remark_type: '2'
        },
        dataType: 'json',
        success: function(response) {
            $btn.prop('disabled', false).html(originalText);
            if (response.success) {
                $('#modal-new-customer-remark-content').val('');
                loadModalCustomerRemarks();
                Swal.fire({
                    width: 550,
                    background: 'url(<?php echo base_url("assets/image/sweetalert.jpg"); ?>)',
                    icon: 'success',
                    title: 'Remark added Successfully',
                    showConfirmButton: false,
                    timer: 2200
                });
            } else {
                Swal.fire({
                    width: 550,
                    background: 'url(<?php echo base_url("assets/image/sweetalert.jpg"); ?>)',
                    icon: 'error',
                    title: response.message || 'Failed to add remark',
                    showConfirmButton: false,
                    timer: 3000
                });
            }
        },
        error: function() {
            $btn.prop('disabled', false).html(originalText);
            Swal.fire({
                width: 550,
                background: 'url(<?php echo base_url("assets/image/sweetalert.jpg"); ?>)',
                icon: 'error',
                title: 'Error adding remark. Please try again.',
                showConfirmButton: false,
                timer: 3000
            });
        }
    });
});

// Allow Ctrl+Enter / Shift+Enter to submit in modal textareas
$('#modal-new-comment-content').on('keydown', function(e) {
    if ((e.ctrlKey || e.shiftKey) && e.keyCode === 13) {
        e.preventDefault();
        $('#modal-add-comment-btn').click();
    }
});
$('#modal-new-customer-remark-content').on('keydown', function(e) {
    if ((e.ctrlKey || e.shiftKey) && e.keyCode === 13) {
        e.preventDefault();
        $('#modal-add-customer-remark-btn').click();
    }
});

// Initialize @mention autocomplete on the modal textarea when modal opens
$('#remarksModal').on('shown.bs.modal', function() {
    if (typeof initMentionAutocomplete === 'function') {
        initMentionAutocomplete('#modal-new-comment-content');
    }
});
</script>