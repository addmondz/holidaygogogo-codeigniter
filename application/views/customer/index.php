<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Customer Records</strong>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('Customer/Create'); ?>" class="btn btn-primary font-weight-bold mr-1 mb-2" style="width:180px;">
                        <i class="la la-user-alt"></i>Create Customer
                    </a>
                    <?php $current_url = base_url($_SERVER['REQUEST_URI']); ?>
                    <a href="<?php if(strpos($current_url, '?') == true) { echo base_url('Customer/Download?') . (explode('?', $current_url))[1]; } else { echo base_url('Customer/Download'); } ?>" class="btn btn-light-warning font-weight-bold mb-2" style="width:180px;">
                        <i class="las la-arrow-circle-down"></i>Customer Records
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="accordion accordion-solid accordion-toggle-plus">
                    <div class="card">
                        <div class="card-header">
                            <div id="customer_header" data-toggle="collapse" data-target="#customer_info" class="card-title collapsed" style="font-size:13px;">Filter By Customer Information</div>
                        </div>
                        <div id="customer_info" class="collapse">
                            <div class="card-body">
                                <form action="<?php echo base_url('Customer') ?>" method="get" class="form">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Name</label>
                                                <div class="input-icon">
                                                    <input type="text" name="name" value="<?php if(!empty($this->input->get('name'))) { echo strtoupper($this->input->get('name')); } ?>" autocomplete="off" class="form-control">
                                                    <span><i class="la la-user-alt"></i></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Phone Number</label>
                                                <div class="input-icon">
                                                    <input type="text" name="phone_number" value="<?php if(!empty($this->input->get('phone_number'))) { echo $this->input->get('phone_number'); } ?>" autocomplete="off" class="form-control">
                                                    <span><i class="la la-phone"></i></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Customer Code</label>
                                                <div class="input-icon">
                                                    <input type="text" name="CustomerCode" value="<?php if(!empty($this->input->get('CustomerCode'))) { echo strtoupper($this->input->get('CustomerCode')); } ?>" autocomplete="off" class="form-control">
                                                    <span><i class="la la-clipboard-list"></i></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Chat Language</label>
                                                <div class="input-icon">
                                                    <input type="text" name="ChatLanguage" value="<?php if(!empty($this->input->get('ChatLanguage'))) { echo strtoupper($this->input->get('ChatLanguage')); } ?>" autocomplete="off" class="form-control">
                                                    <span><i class="la la-language"></i></span>
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
                <div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if(empty($customers)) { echo 'style="overflow-x:auto;"'; } ?>>
                    <table id="kt_datatable" class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline">
                        <thead>
                            <tr>
                                <th style="text-align:center;">No.</th>
                                <th style="text-align:center;">Name</th>
                                <th style="text-align:center;">Phone Number</th>
                                <th style="text-align:center;">Customer Code</th>
                                <th style="text-align:center;">Chat Language</th>
                                <th style="text-align:center;">Autocount Sync Status</th>
                                <th class="action" style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($customers)) { ?>
                                <td colspan="7" style="text-align:center; padding-top:10px; padding-bottom:10px;">Customer Records Not Found</td>
                            <?php } else { ?>
                                <?php $count = 1; ?>
                                <?php foreach($customers as $customer) { ?>
                                    <tr>
                                        <td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
                                        <td style="text-align:center;"><?php echo $customer->name; ?></td>
                                        <td style="text-align:center;"><?php echo $customer->phone_number; ?></td>
                                        <td style="text-align:center;"><?php echo $customer->CustomerCode; ?></td>
                                        <td style="text-align:center;"><?php echo $customer->ChatLanguage; ?></td>
                                        <td style="text-align:center;">
                                            <?php 
                                                $statusColor = '#000000';
                                                $statusText  = 'UNKNOWN';

                                                switch ($customer->AutocountSyncStatus) {
                                                    case 'P': $statusColor = '#808080'; $statusText = 'Pending'; break;
                                                    case 'S': $statusColor = '#50C878'; $statusText = 'Synced'; break;
                                                    case 'F': $statusColor = '#FF4500'; $statusText = 'Failed'; break;
                                                }

                                                $tooltipAttr = '';
                                                if (!empty($customer->AutocountSyncMessage)) {
                                                    $decoded = json_decode($customer->AutocountSyncMessage, true);
                                                    if (json_last_error() === JSON_ERROR_NONE) {
                                                        if (isset($decoded['error']) && $decoded['error'] === null) {
                                                            $tooltipText = "SUCCESS";
                                                        } elseif (isset($decoded['error']) && $decoded['error'] !== null) {
                                                            $tooltipText = "ERROR: " . (is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error']));
                                                        } else {
                                                            $tooltipText = $customer->AutocountSyncMessage;
                                                        }
                                                    } else {
                                                        $tooltipText = $customer->AutocountSyncMessage;
                                                    }
                                                    $tooltipAttr = ' data-toggle="tooltip" data-placement="top" title="' . htmlspecialchars($tooltipText) . '"';
                                                }
                                            ?>
                                            <span class="font-weight-bold" style="color:<?= $statusColor ?>;" <?= $tooltipAttr ?>><?= $statusText ?></span>
                                        </td>
                                        <td style="text-align:center;">
                                            <div class="btn-group">
                                                <button type="button" data-toggle="dropdown" class="btn btn-light-primary btn-sm dropdown-toggle" style="padding-left:3px;"></button>
                                                <div class="dropdown-menu">
                                                    <button onclick="Delete_Record('<?php echo base_url('assets/image/sweetalert.jpg'); ?>', '<?php echo 'Customer Record : ' . str_replace('\'', '', $customer->name); ?>', '<?php echo base_url('Customer/Delete'); ?>', 'customer_id', <?php echo $customer->CustomerID; ?>, '', '<?php if(strpos($current_url, '?') == true) { echo base_url('Customer?') . (explode('?', $current_url))[1]; } else { echo base_url('Customer'); } ?>')" class="dropdown-item" style="color:#E37383; font-size:11px;">Delete Customer</button>
                                                    <a href="<?php echo base_url('Customer/Update?customer_id=') . $customer->CustomerID; ?>" class="dropdown-item" style="font-size:11px;">Update Customer</a>
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
    <?php if(!empty($this->input->get('name')) || !empty($this->input->get('phone_number')) || !empty($this->input->get('CustomerCode')) || !empty($this->input->get('ChatLanguage'))) { ?>
        $('#customer_header').click();
    <?php } ?>

    $('#reset').click(function() {
        Reset('<?php echo base_url('Customer'); ?>');
    });
</script>
