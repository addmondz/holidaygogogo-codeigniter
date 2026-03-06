<?php 
$is_view_mode = true;
?>

<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        
        <div class="alert alert-warning">
            <strong>Note:</strong> Once the booking is started, you can no longer update the booking.
        </div>
        
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Booking Record : <?php echo $BookingNumber; ?></strong>
                    </h3>
                </div>
            </div>
            <div class="card-body">
                <form id="form">
                    <strong>Booking Information :</strong>
                    <br><br>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Booking Number</label>
                                <div class="input-icon">
                                    <input type="text" id="booking_number" disabled value="<?php echo $BookingNumber; ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-suitcase"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Reservation Number</label>
                                <div class="input-icon">
                                    <input type="text" id="ReservationNumber" disabled value="<?php echo $ReservationNumber; ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-suitcase"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Sales Agent</label>
                                <div class="input-icon">
                                    <input disabled type="text" value="<?php echo $SalesAgentName; ?>" class="form-control">
                                    <span>
                                        <i class="la la-user-alt"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Customer</label>
                                <div class="input-icon">
                                    <input type="text" id="Customer" disabled value="<?php echo $Customer; ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-user"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Mobile</label>
                                <div class="input-icon">
                                    <input type="text" id="Mobile" disabled value="<?php echo $CustomerMobile; ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-mobile"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Destination</label>
                                <div class="input-icon">
                                    <input type="text" disabled value="<?php echo $DestinationName; ?>" class="form-control">
                                    <span>
                                        <i class="la la-map-pin"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Travel Date</label>
                                <div class="input-icon">
                                    <input readonly type="text" id="TravelDate" disabled value="<?php echo $TravelDate; ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-calendar"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Adult</label>
                                <div class="input-icon">
                                    <input disabled type="text" id="Adult" value="<?php echo $Adult; ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-male"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Children</label>
                                <div class="input-icon">
                                    <input disabled type="text" id="Children" value="<?php echo $Children; ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-child"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Infant</label>
                                <div class="input-icon">
                                    <input disabled type="text" id="Infant" value="<?php echo $Infant; ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-baby-carriage"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Remark</label>
                                <div class="input-icon">
                                    <input type="text" id="BookingRemark" disabled value="<?php echo $BookingRemark; ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-pencil-alt"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Source</label>
                                <div class="input-icon">
                                    <input type="text" disabled value="<?php echo $SourceName; ?>" class="form-control">
                                    <span>
                                        <i class="la la-clipboard-list"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Chat Language</label>
                                <div class="input-icon">
                                    <input type="text" disabled value="<?php echo $ChatLanguage; ?>" class="form-control">
                                    <span>
                                        <i class="la la-language"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between border-top pt-5"></div>

                    <strong>Payment Deadline :</strong>
                    <br><br>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Deposit Deadline</label>
                                <div class="input-icon">
                                    <input readonly type="text" name="DepositDeadline" id="kt_datepicker_4_3" disabled value="<?php echo $DepositDeadline; ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-calendar"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Full Payment Deadline</label>
                                <div class="input-icon">
                                    <input readonly type="text" name="FullPaymentDeadline" id="kt_datepicker_4_4" disabled value="<?php echo $FullPaymentDeadline; ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-calendar"></i>
                                    </span>
                                </div>
                            </div>
                            <?php if(!empty($AdditionalPaymentDeadline)): ?>
                            <div class="form-group">
                                <label>Additional Payment Deadline</label>
                                <div class="input-icon">
                                    <input readonly type="text" name="AdditionalPaymentDeadline" disabled value="<?php echo $AdditionalPaymentDeadline; ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-calendar"></i>
                                    </span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between border-top pt-5"></div>
                    <strong>Product Information :</strong>
                    <br><br>
                    <div id="benchmark" class="row">
                        <?php if(!empty($booking_products)): ?>
                            <?php foreach($booking_products as $booking_product): ?>
                                <div class="col-md-12 mb-3" style="border: 1px solid #D7E2F2; border-radius: 8px; padding: 15px;">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong><?php echo htmlspecialchars($booking_product->Name ?? $booking_product->Description ?? 'N/A'); ?></strong>
                                        </div>
                                        <div class="col-md-3 text-right">
                                            <span>Price: RM <?php 
                                                $price = $booking_product->Price ?? 0;
                                                echo is_numeric($price) ? number_format((float)$price, 2) : $price;
                                            ?></span>
                                        </div>
                                        <div class="col-md-3 text-right">
                                            <span>Total: RM <?php 
                                                $total = $booking_product->Total ?? 0;
                                                echo is_numeric($total) ? number_format((float)$total, 2) : $total;
                                            ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-md-12">
                                <p class="text-muted">No products added</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <br><br>
                    <div class="row">
                        <div class="col-md-12 pt-3 pb-3" style="background-color:white; border:3px solid #D7E2F2; border-radius:8px;">
                            <div class="row">
                                <div class="col-md-4 mb-7 mb-md-0">
                                    <label style="color:#088F8F;">Subtotal (RM)</label>
                                    <div class="input-icon">
                                        <input disabled type="text" id="Subtotal" value="<?php echo $Subtotal; ?>" class="form-control" style="text-align:right;">
                                        <span>
                                            <i class="la la-dollar"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-7 mb-md-0">
                                    <label style="color:#2AAA8A;">- Discount (RM)</label>
                                    <div class="input-icon">
                                        <input disabled type="text" id="Discount" value="<?php echo $Discount; ?>" class="form-control" style="text-align:right;">
                                        <span>
                                            <i class="la la-dollar"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label style="color:#50C878;">= Net Total (RM)</label>
                                    <div class="input-icon">
                                        <input disabled type="text" id="NetTotal" value="<?php echo $NetTotal; ?>" class="form-control" style="text-align:right;">
                                        <span>
                                            <i class="la la-dollar"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- Customer Remarks Section -->
        <div class="row">
            <div class="col-lg-6 col-md-12">
                <div class="card card-custom">
                    <div class="card-header flex-wrap py-2" style="background-color:#E8F5E9;">
                        <div class="card-title">
                            <h4 class="card-label mb-0" style="color:#4CAF50; font-size: 1.1rem;">
                                <strong>Customer Remarks</strong>
                            </h4>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Customer Remarks List -->
                        <div id="customer-remarks-list" class="mb-2">
                            <div class="text-center text-muted py-2" style="font-size: 0.8125rem;">
                                <i class="la la-spinner la-spin"></i> Loading customer remarks...
                            </div>
                        </div>
                        <!-- Add Admin Remark Form (for responding to customer) -->
                        <div class="border-top pt-2">
                            <div class="form-group mb-2">
                                <textarea id="new-admin-remark-content" class="form-control" rows="2" placeholder="Add your response or remark here..." style="font-size: 0.8125rem;"></textarea>
                            </div>
                            <button type="button" id="add-admin-remark-btn" class="btn btn-primary btn-sm font-weight-bold mt-2 mb-2">
                                <i class="la la-comment"></i> Add Remark
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-md-12">
                <!-- Status History Section -->
                <?php if(!empty($status_logs)) { ?>
                    <div class="card card-custom">
                        <div class="card-header flex-wrap py-2" style="background-color:#D7E2F2;">
                            <div class="card-title">
                                <h4 class="card-label mb-0" style="color:#6082B6; font-size: 1.1rem;">
                                    <strong><i class="la la-history"></i> Status History</strong>
                                </h4>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="timeline-container" style="max-height: 500px; overflow-y: auto;">
                                <?php foreach($status_logs as $log): ?>
                                    <div class="timeline-item status-log-item <?php echo $log['status']; ?>" style="padding: 12px 0; border-left: 2px solid #e0e0e0; padding-left: 20px; margin-left: 15px; position: relative;">
                                        <div class="timeline-marker" style="position: absolute; left: -7px; top: 15px; width: 12px; height: 12px; border-radius: 50%; background-color: <?php echo $log['status'] == 'completed' ? '#50C878' : ($log['status'] == 'cancelled' ? '#FF69B4' : '#FFBF00'); ?>; border: 2px solid white; box-shadow: 0 0 0 2px <?php echo $log['status'] == 'completed' ? '#50C878' : ($log['status'] == 'cancelled' ? '#FF69B4' : '#FFBF00'); ?>;"></div>
                                        <div class="timeline-content">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <div class="timeline-title" style="font-weight: 600; color: #212529; font-size: 0.95rem;">
                                                    <i class="<?php echo $log['icon']; ?>" style="margin-right: 6px; color: #6082B6;"></i>
                                                    <?php echo htmlspecialchars($log['title']); ?>
                                                </div>
                                                <div class="timeline-date" style="font-size: 0.8125rem; color: #6c757d;">
                                                    <?php echo $log['date']; ?>
                                                </div>
                                            </div>
                                            <div class="timeline-description" style="font-size: 0.875rem; color: #495057; margin-top: 4px;">
                                                <?php echo htmlspecialchars($log['description']); ?>
                                            </div>
                                            <?php if(!empty($log['created_by']) && $log['created_by'] != 'System'): ?>
                                            <div class="timeline-meta" style="font-size: 0.75rem; color: #868e96; margin-top: 6px;">
                                                <i class="la la-user"></i> Changed by: <?php echo htmlspecialchars($log['created_by']); ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Helper function to escape HTML
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Customer Remarks functionality
    var bookingId = <?php echo $BookingID; ?>;

    // Load customer remarks on page load
    function loadCustomerRemarks() {
        $.ajax({
            url: '<?php echo base_url('Booking/Get_Customer_Remarks'); ?>',
            type: 'get',
            data: { booking_id: bookingId },
            dataType: 'json',
            success: function(response) {
                var remarksList = $('#customer-remarks-list');
                remarksList.empty();

                if (response.success && response.remarks && response.remarks.length > 0) {
                    response.remarks.forEach(function(remark) {
                        // Get first letter for avatar color
                        var avatarColor = ['primary', 'success', 'info', 'warning', 'danger'][remark.commenter_name.charCodeAt(0) % 5];
                        
                        var remarkHtml = '<div class="comment-item d-flex mb-2 mx-2 pb-2 pl-1" style="border-bottom: 1px solid #e4e6eb;">' +
                            // Avatar
                            '<div class="flex-shrink-0 mr-2">' +
                            '<div class="symbol symbol-32 symbol-circle symbol-light-' + avatarColor + '">' +
                            '<span class="symbol-label font-weight-bold" style="font-size: 0.75rem;">' + (remark.commenter_initials || remark.commenter_name.substring(0, 2).toUpperCase()) + '</span>' +
                            '</div>' +
                            '</div>' +
                            // Comment content
                            '<div class="flex-grow-1" style="min-width: 0;">' +
                            '<div class="d-flex align-items-baseline mb-1">' +
                            '<strong class="mr-2" style="font-size: 0.8125rem; color: #050505;">' + escapeHtml(remark.commenter_name) + '</strong>' +
                            '<span class="text-muted" style="font-size: 0.75rem; color: #65676b;">' + remark.created_at + (remark.created_at_relative ? ' <span style="margin: 0 4px;">•</span> ' + remark.created_at_relative : '') + '</span>' +
                            '</div>' +
                            '<div class="comment-text" style="font-size: 0.8125rem; color: #050505; line-height: 1.3; white-space: pre-wrap; word-wrap: break-word;">' + escapeHtml(remark.content) + '</div>' +
                            '</div>' +
                            '</div>';
                        remarksList.append(remarkHtml);
                    });
                } else {
                    remarksList.html('<div class="text-center text-muted py-3" style="font-size: 0.8125rem; color: #65676b;">No customer remarks yet.</div>');
                }
            },
            error: function() {
                $('#customer-remarks-list').html('<div class="text-center text-danger py-3" style="font-size: 0.8125rem;">Error loading customer remarks. Please refresh the page.</div>');
            }
        });
    }

    // Add admin remark (internal remark in response to customer)
    $('#add-admin-remark-btn').on('click', function() {
        var content = $('#new-admin-remark-content').val().trim();
        
        if (!content) {
            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please enter a remark', null);
            return;
        }

        var $btn = $(this);
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<i class="la la-spinner la-spin"></i> Adding...');

        $.ajax({
            url: '<?php echo base_url('Booking/Add_Remark'); ?>',
            type: 'post',
            data: {
                booking_id: bookingId,
                content: content,
                remark_type: '2', // CUSTOMER type when adding from Customer Remarks section
                skip_notifications: '1' // Skip notifications when adding from Customer Remarks section
            },
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).html(originalText);
                
                if (response.success) {
                    $('#new-admin-remark-content').val('');
                    loadCustomerRemarks(); // Reload customer remarks to show the new one
                    Swal.fire({
                        width: 550,
                        background: 'url(<?php echo base_url('assets/image/sweetalert.jpg') ?>)',
                        icon: 'success',
                        title: 'Remark added Successfully',
                        showConfirmButton: false,
                        timer: 2200
                    });
                } else {
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', response.message || 'Failed to add remark', null);
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(originalText);
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Error adding remark. Please try again.', null);
            }
        });
    });

    // Allow Enter key to submit (Ctrl+Enter or Shift+Enter) for admin remark
    $('#new-admin-remark-content').on('keydown', function(e) {
        if ((e.ctrlKey || e.shiftKey) && e.keyCode === 13) {
            e.preventDefault();
            $('#add-admin-remark-btn').click();
        }
    });

    // Load customer remarks on page load
    loadCustomerRemarks();
</script>
