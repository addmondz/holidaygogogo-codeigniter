<style>
/* Hide pagination buttons container */
.kt-datatable__pager {
    display: none !important;
}

/* Hide "show entries" dropdown (page size selector) */
.kt-datatable__pager .kt-datatable__pager-size {
    display: none !important;
}

/* Hide info text (optional if you want to build your own info display) */
.kt-datatable__info {
    display: none !important;
}
.kt-datatable__pager-size {
    display: none !important;
}
/* Hide pagination controls */
.kt-datatable__pager,
.kt-datatable__pager-info,
.kt-datatable__pager-size,
.kt-datatable__pager-nav,
.kt-datatable__info,
.dataTables_paginate,
.dataTables_length,
.dataTables_info {
    display: none !important;
}
div.kt-datatable__pager-container {
    display: none !important;
}


</style>

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
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Autocount Status</label>
                                                <select name="autocount_status" class="form-control selectpicker">
                                                    <option selected data-icon="la la-sync font-size-lg bs-icon" value="">--SELECT AUTOCOUNT STATUS--</option>
                                                    <option data-icon="la la-clock font-size-lg bs-icon" value="P" <?php if($this->input->get('autocount_status') == 'P') echo 'selected'; ?>>Pending</option>
                                                    <option data-icon="la la-check-circle font-size-lg bs-icon" value="S" <?php if($this->input->get('autocount_status') == 'S') echo 'selected'; ?>>Synced</option>
                                                    <option data-icon="la la-times-circle font-size-lg bs-icon" value="F" <?php if($this->input->get('autocount_status') == 'F') echo 'selected'; ?>>Failed</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Customer Type</label>
                                                <select name="customer_type" class="form-control selectpicker">
                                                    <option selected data-icon="la la-users font-size-lg bs-icon" value="">--SELECT CUSTOMER TYPE--</option>
                                                    <?php if(!empty($customer_types)) { foreach($customer_types as $ct) { ?>
                                                        <option data-icon="la la-user-tag font-size-lg bs-icon" value="<?php echo $ct->Name; ?>" <?php if($this->input->get('customer_type') == $ct->Name) echo 'selected'; ?>><?php echo $ct->Name; ?></option>
                                                    <?php } } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Created Date
                                                    <a onclick="Reset_Created_Date()" class="btn btn-icon btn-light-warning btn-xs">
                                                        <i class="la la-undo"></i>
                                                    </a>
                                                </label>
                                                <div id="kt_daterangepicker_customer" class="input-icon">
                                                    <input readonly type="text" name="created_date" value="<?php if(!empty($this->input->get('created_date'))) { echo $this->input->get('created_date'); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-calendar"></i>
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
                <div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if(empty($customers)) { echo 'style="overflow-x:auto;"'; } ?>>
                    <table id="kt_datatable" class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline">
                        <thead>
                            <tr>
                                <th style="text-align:center;">No.</th>
                                <th style="text-align:center;">Name</th>
                                <th style="text-align:center;">Phone Number</th>
                                <th style="text-align:center;">Customer Code</th>
                                <th style="text-align:center;">Chat Language</th>
                                <th style="text-align:center;">Created Date</th>
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
                                        <td style="text-align:center;"><?php echo date('Y-m-d', strtotime($customer->created_at)); ?></td>
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
                                                    <a href="<?php echo base_url('Customer/Update?customer_id=') . $customer->CustomerID; ?>" class="dropdown-item" style="font-size:11px;">Update Customer</a>
                                                    <a href="#" class="dropdown-item copy-customer-link" data-customer-id="<?php echo $customer->CustomerID; ?>" style="font-size:11px;">
                                                        Copy Customer Link
                                                    </a>
                                                    <?php
                                                        // Generate portal hash for direct link
                                                        $this->load->helper('utils');
                                                        $portal_hash = generate_customer_portal_slug($customer->CustomerID);
                                                        // Only show link if hash was generated successfully
                                                        if (!empty($portal_hash) && $portal_hash !== false):
                                                    ?>
                                                    <a href="<?php echo base_url('customer/' . urlencode($portal_hash)); ?>" target="_blank" class="dropdown-item" style="font-size:11px;">
                                                        Customer Portal
                                                    </a>
                                                    <?php endif; ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a href="#" class="dropdown-item delete-customer text-danger"
                                                       data-customer-id="<?php echo $customer->CustomerID; ?>"
                                                       data-customer-name="<?php echo htmlspecialchars($customer->name, ENT_QUOTES); ?>"
                                                       style="font-size:11px;">
                                                        Delete Customer
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php $count++; ?>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <?php if (!empty($customers)) {
                            $start = ($page - 1) * $limit + 1;
                            $end = min($page * $limit, $total);
                        ?>
                            <div class="text-left font-weight-bold" style="padding-left:15px;">
                                Showing <?= $start ?> to <?= $end ?> of <?= $total ?> entries
                            </div>
                        <?php } ?>

                        <div class="text-center">
                            <?php
                            $totalPages = ceil($total / $limit);
                            $query = $_GET;
                            unset($query['page']);

                            // Only show pagination if more than 1 page
                            if ($totalPages > 1):
                                $maxPagesToShow = 7; // max page buttons to show
                                $half = floor($maxPagesToShow / 2);
                                $startPage = max(1, $page - $half);
                                $endPage = min($totalPages, $page + $half);

                                // Adjust start or end if near beginning or end
                                if ($page <= $half) {
                                    $endPage = min($totalPages, $maxPagesToShow);
                                }
                                if ($page + $half > $totalPages) {
                                    $startPage = max(1, $totalPages - $maxPagesToShow + 1);
                                }
                            ?>

                            <!-- First Page -->
                            <?php
                                $query['page'] = 1;
                            ?>
                            <a href="?<?= http_build_query($query) ?>" class="btn btn-sm <?= ($page == 1 ? 'btn-primary disabled' : 'btn-light') ?>">« First</a>

                            <!-- Previous Page -->
                            <?php
                                $prevPage = max(1, $page - 1);
                                $query['page'] = $prevPage;
                            ?>
                            <a href="?<?= http_build_query($query) ?>" class="btn btn-sm <?= ($page == 1 ? 'btn-primary disabled' : 'btn-light') ?>">‹ Prev</a>

                            <!-- Ellipsis before -->
                            <?php if ($startPage > 1): ?>
                                <span class="btn btn-sm btn-light disabled">...</span>
                            <?php endif; ?>

                            <!-- Page Numbers -->
                            <?php for ($i = $startPage; $i <= $endPage; $i++):
                                $query['page'] = $i;
                            ?>
                                <a href="?<?= http_build_query($query) ?>" class="btn btn-sm <?= ($page == $i ? 'btn-primary' : 'btn-light') ?>"><?= $i ?></a>
                            <?php endfor; ?>

                            <!-- Ellipsis after -->
                            <?php if ($endPage < $totalPages): ?>
                                <span class="btn btn-sm btn-light disabled">...</span>
                            <?php endif; ?>

                            <!-- Next Page -->
                            <?php
                                $nextPage = min($totalPages, $page + 1);
                                $query['page'] = $nextPage;
                            ?>
                            <a href="?<?= http_build_query($query) ?>" class="btn btn-sm <?= ($page == $totalPages ? 'btn-primary disabled' : 'btn-light') ?>">Next ›</a>

                            <!-- Last Page -->
                            <?php
                                $query['page'] = $totalPages;
                            ?>
                            <a href="?<?= http_build_query($query) ?>" class="btn btn-sm <?= ($page == $totalPages ? 'btn-primary disabled' : 'btn-light') ?>">Last »</a>

                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    <?php if(!empty($this->input->get('name')) || !empty($this->input->get('phone_number')) || !empty($this->input->get('CustomerCode')) || !empty($this->input->get('ChatLanguage')) || !empty($this->input->get('autocount_status')) || !empty($this->input->get('customer_type')) || !empty($this->input->get('created_date'))) { ?>
        $('#customer_header').click();
    <?php } ?>

    $('#reset').click(function() {
        Reset('<?php echo base_url('Customer'); ?>');
    });

    // Copy Customer Portal Link functionality
    $(document).on('click', '.copy-customer-link', function(e) {
        e.preventDefault();
        var $button = $(this);
        var customerId = $button.data('customer-id');
        var originalText = $button.html();
        
        // Disable button and show loading
        $button.prop('disabled', true).html('<i class="la la-spinner la-spin"></i> Generating...');
        
        // Fetch portal URL
        $.ajax({
            url: '<?php echo base_url('Customer/GeneratePortalUrl'); ?>',
            method: 'GET',
            data: { customer_id: customerId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.portal_url) {
                    // Copy to clipboard using modern API with fallback
                    var urlToCopy = response.portal_url;
                    
                    if (navigator.clipboard && window.isSecureContext) {
                        // Use modern Clipboard API
                        navigator.clipboard.writeText(urlToCopy).then(function() {
                            showCopySuccess($button, originalText);
                        }).catch(function(err) {
                            console.error('Failed to copy:', err);
                            fallbackCopy(urlToCopy, $button, originalText);
                        });
                    } else {
                        // Fallback for older browsers
                        fallbackCopy(urlToCopy, $button, originalText);
                    }
                } else {
                    alert('Failed to generate portal link: ' + (response.message || 'Unknown error'));
                    $button.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error generating portal URL:', error);
                alert('Error generating portal link. Please try again.');
                $button.prop('disabled', false).html(originalText);
            }
        });
    });

    // Soft-delete customer (Status='N') via existing Customer/Delete endpoint
    $(document).on('click', '.delete-customer', function(e) {
        e.preventDefault();
        var $link = $(this);
        var customerId = $link.data('customer-id');
        var customerName = $link.data('customer-name');

        Swal.fire({
            title: 'Delete this customer?',
            html: 'You are about to delete <strong>' + $('<div>').text(customerName).html() + '</strong>.<br>The record will be hidden from all lists.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6'
        }).then(function(result) {
            if (!result.isConfirmed) return;

            $.ajax({
                url: '<?php echo base_url('Customer/Delete'); ?>',
                method: 'GET',
                data: { customer_id: customerId },
                success: function() {
                    if (typeof toastr !== 'undefined') {
                        toastr.success('Customer deleted.');
                    }
                    window.location.reload();
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Delete failed',
                        text: 'Could not delete the customer. Please try again.'
                    });
                }
            });
        });
    });

    // Fallback copy function for older browsers
    function fallbackCopy(text, $button, originalText) {
        var tempInput = $('<input>');
        $('body').append(tempInput);
        tempInput.val(text).select();
        try {
            document.execCommand('copy');
            showCopySuccess($button, originalText);
        } catch (err) {
            console.error('Fallback copy failed:', err);
            alert('Failed to copy. Please copy manually: ' + text);
            $button.prop('disabled', false).html(originalText);
        }
        tempInput.remove();
    }
    
    // Show success feedback
    function showCopySuccess($button, originalText) {
        $button.html('<i class="la la-check"></i> Copied!');
        setTimeout(function() {
            $button.prop('disabled', false).html(originalText);
        }, 2000);
        
        // Show toast notification if available
        if (typeof toastr !== 'undefined') {
            toastr.success('Customer portal link copied to clipboard!');
        } else if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Copied!',
                text: 'Customer portal link copied to clipboard',
                timer: 2000,
                showConfirmButton: false
            });
        }
    }
    
    function Reset_Created_Date() {
        $('#kt_daterangepicker_customer input').val('');
    }

    $('#kt_daterangepicker_customer').daterangepicker({
        buttonClasses: ' btn',
        applyClass: 'btn-primary',
        cancelClass: 'btn-secondary',
        autoApply: true
    }, function(start, end, label) {
        $('#kt_daterangepicker_customer .form-control').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
    });
</script>
