<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Supplier Records</strong>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('Supplier/Create'); ?>" class="btn btn-primary font-weight-bold mr-1 mb-2" style="width:180px;">
                        <i class="la la-user-alt"></i>Create Supplier
                    </a>
                    <?php $current_url = base_url($_SERVER['REQUEST_URI']); ?>
                    <a href="<?php if(strpos($current_url, '?') == true) { echo base_url('Supplier/Download?') . (explode('?', $current_url))[1]; } else { echo base_url('Supplier/Download'); } ?>" class="btn btn-light-warning font-weight-bold mb-2" style="width:180px;">
                        <i class="las la-arrow-circle-down"></i>Supplier Records
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="accordion accordion-solid accordion-toggle-plus">
                    <div class="card">
                        <div class="card-header">
                            <div id="supplier_header" data-toggle="collapse" data-target="#supplier_info" class="card-title collapsed" style="font-size:13px;">Filter By Supplier Information</div>
                        </div>
                        <div id="supplier_info" class="collapse">
                            <div class="card-body">
                                <form action="<?php echo base_url('Supplier') ?>" method="get" class="form">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Name</label>
                                                <div class="input-icon">
                                                    <input type="text" name="name" value="<?php if(!empty($this->input->get('name'))) { echo strtoupper($this->input->get('name')); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-user-alt"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Phone</label>
                                                <div class="input-icon">
                                                    <input type="text" name="phone" value="<?php if(!empty($this->input->get('phone'))) { echo $this->input->get('phone'); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-phone"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Primary Email</label>
                                                <div class="input-icon">
                                                    <input type="email" name="primary_email" value="<?php if(!empty($this->input->get('primary_email'))) { echo strtoupper($this->input->get('primary_email')); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-envelope"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Secondary Email</label>
                                                <div class="input-icon">
                                                    <input type="email" name="secondary_email" value="<?php if(!empty($this->input->get('secondary_email'))) { echo strtoupper($this->input->get('secondary_email')); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-envelope"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Address</label>
                                                <div class="input-icon">
                                                    <input type="text" name="address" value="<?php if(!empty($this->input->get('address'))) { echo strtoupper($this->input->get('address')); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-map-marker"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Currency Code</label>
                                                <div class="input-icon">
                                                    <input type="text" name="currency_code" value="<?php if(!empty($this->input->get('currency_code'))) { echo strtoupper($this->input->get('currency_code')); } ?>" autocomplete="off" class="form-control">
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
                                                    <input type="text" name="bank_account" value="<?php if(!empty($this->input->get('bank_account'))) { echo $this->input->get('bank_account'); } ?>" autocomplete="off" class="form-control">
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
                                                <label>Swift Code</label>
                                                <div class="input-icon">
                                                    <input type="text" name="swift_code" value="<?php if(!empty($this->input->get('swift_code'))) { echo strtoupper($this->input->get('swift_code')); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-clipboard-list"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="submit" value="Filter" class="btn btn-light-success font-weight-bold" style="width:80px;">
                                    <input type="button" id="reset" value="Reset" class="btn btn-light-primary font-weight-bold" style="width:80px;">
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <br><br>
                <div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if(empty($suppliers)) { echo 'style="overflow-x:auto;"'; } ?>>
                    <table id="kt_datatable" class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline">
                        <thead>
                            <tr>
                                <th style="text-align:center;">No.</th>
                                <th style="text-align:center;">Name</th>
                                <th style="text-align:center;">Phone</th>
                                <th style="text-align:center;">Supplier Code (Autocount Creditor Code)</th>
                                <th style="text-align:center;">Autocount Status</th>
                                <th class="action" style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($suppliers)) { ?>
                                <td colspan="4" style="text-align:center; padding-top:10px; padding-bottom:10px;">Supplier Records Not Found</td>
                            <?php } else { ?>
                                <?php $count = 1; ?>
                                <?php foreach($suppliers as $supplier) { ?>
                                    <tr>
                                        <td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
                                        <td style="text-align:center;"><?php echo $supplier->Name; ?></td>
                                        <td style="text-align:center;"><?php echo $supplier->Phone; ?></td>
                                        <td style="text-align:center;"><?php echo $supplier->SupplierCode; ?></td>
                                        <td style="text-align:center;">
                                            <?php 
                                                // default
                                                $statusColor = '#000000';
                                                $statusText  = 'UNKNOWN';

                                                // only handle P, S, F
                                                switch ($supplier->AutocountSyncStatus) {
                                                    case 'P': $statusColor = '#808080'; $statusText = 'Pending'; break;
                                                    case 'S': $statusColor = '#50C878'; $statusText = 'Synced'; break;
                                                    case 'F': $statusColor = '#FF4500'; $statusText = 'Failed'; break;
                                                }

                                                // tooltip
                                                $tooltipAttr = '';
                                                if (!empty($supplier->AutocountSyncMessage)) {
                                                    $decoded = json_decode($supplier->AutocountSyncMessage, true);

                                                    if (json_last_error() === JSON_ERROR_NONE) {
                                                        if (isset($decoded['error']) && $decoded['error'] === null) {
                                                            $tooltipText = "SUCCESS";
                                                        } elseif (isset($decoded['error']) && $decoded['error'] !== null) {
                                                            $tooltipText = "ERROR: " . (is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error']));
                                                        } else {
                                                            $tooltipText = $supplier->AutocountSyncMessage; // raw JSON
                                                        }
                                                    } else {
                                                        $tooltipText = $supplier->AutocountSyncMessage;
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
                                                    <button onclick="Delete_Record('<?php echo base_url('assets/image/sweetalert.jpg'); ?>', '<?php echo 'Supplier Record : ' . str_replace('\'', '', $supplier->Name); ?>', '<?php echo base_url('Supplier/Delete'); ?>', 'supplier_id', <?php echo $supplier->SupplierID; ?>, '<?php echo $supplier->Status; ?>', '<?php if(strpos($current_url, '?') == true) { echo base_url('Supplier?') . (explode('?', $current_url))[1]; } else { echo base_url('Supplier'); } ?>')" class="dropdown-item" style="color:#E37383; font-size:11px;">Delete Supplier</button>
                                                    <a href="<?php echo base_url('Supplier/Update?supplier_id=') . $supplier->SupplierID; ?>" class="dropdown-item" style="font-size:11px;">Update Supplier</a>
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
            </div>
        </div>
    </div>
</div>

<script>
    <?php if(!empty($this->input->get('name')) || !empty($this->input->get('phone')) || !empty($this->input->get('primary_email')) || !empty($this->input->get('secondary_email')) || !empty($this->input->get('address')) || !empty($this->input->get('currency_code')) || !empty($this->input->get('bank')) || !empty($this->input->get('bank_account')) || !empty($this->input->get('bank_holder')) || !empty($this->input->get('swift_code'))) { ?>
        $('#supplier_header').click();
    <?php } ?>
    
    $('#reset').click(function() {
        Reset('<?php echo base_url('Supplier'); ?>');
    });
</script>