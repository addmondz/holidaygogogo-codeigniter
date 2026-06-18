<?php
    $is_sales_agent = $this->session->userdata('level') == 20;
    // Autocount status is only relevant to Owner (10) and Finance (30)
    $show_autocount_status = in_array((int)$this->session->userdata('level'), [10, 30]);
    ini_set("memory_limit","512M");
?>
<style>
/* Hide pseudo-elements on first child, but allow responsive control icons */
#kt_datatable tbody tr td:first-child:not(.dtr-control)::before,
#kt_datatable tbody tr td:first-child:not(.dtr-control)::after {
    display: none !important;
}
/* Ensure DataTable wrapper uses full width without extra padding */
.dataTables_wrapper {
    overflow-x: auto;
}
/* Center the No. column by resetting DataTable's padding-left */
#kt_datatable tbody tr td:first-child {
    padding-left: 0.75rem !important;
    text-align: center !important;
}

/* Checklist Modal Styles */
#checklistModal .checklist-container {
    padding: 0;
}
#checklistModal .checklist-item {
    padding: 10px 12px;
    margin-bottom: 8px;
    background-color: #ffffff;
    border: 1px solid #e4e6eb;
    border-radius: 6px;
    transition: all 0.2s ease;
}
#checklistModal .checklist-item:hover {
    border-color: #c1c7d0;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}
#checklistModal .checklist-item.checked {
    background-color: #f8f9fa;
    border-color: #d4edda;
}
#checklistModal .checklist-item.checked:hover {
    border-color: #c3e6cb;
}
#checklistModal .checklist-item-content {
    display: flex;
    align-items: flex-start;
    gap: 28px;
}
#checklistModal .checklist-checkbox-wrapper {
    position: relative;
    flex-shrink: 0;
    margin-top: 2px;
}
#checklistModal .checklist-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
    margin: 0;
    accent-color: #6082B6;
}
#checklistModal .checklist-checkbox-label {
    position: absolute;
    top: 0;
    left: 0;
    width: 18px;
    height: 18px;
    cursor: pointer;
    margin: 0;
}
#checklistModal .checklist-details {
    flex: 1;
    min-width: 0;
}
#checklistModal .checklist-name-wrapper {
    margin-bottom: 4px;
}
#checklistModal .checklist-name {
    font-size: 0.875rem;
    font-weight: 500;
    color: #212529;
    cursor: pointer;
    margin: 0;
    line-height: 1.3;
}
#checklistModal .checklist-completion-info {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 6px;
    padding-top: 6px;
    border-top: 1px solid #e9ecef;
    font-size: 0.75rem;
    color: #6c757d;
}
#checklistModal .checklist-completion-info i {
    font-size: 0.875rem;
    color: #6082B6;
}
#checklistModal .completion-text {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
#checklistModal .completion-separator {
    color: #adb5bd;
    margin: 0 4px;
}
#checklistModal .completion-date {
    color: #868e96;
}
#checklistModal .checklist-footer {
    margin-top: 16px;
    padding-top: 14px;
    border-top: 2px solid #e4e6eb;
}
#checklistModal .checklist-progress {
    min-width: 200px;
}
#checklistModal .progress-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}
#checklistModal .progress-text {
    font-size: 0.8125rem;
    color: #495057;
}
#checklistModal .progress-text strong {
    color: #212529;
}
#checklistModal .progress-percentage {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #6082B6;
}
#checklistModal .progress-bar-wrapper {
    width: 100%;
}
#checklistModal .progress-bar-wrapper .progress {
    height: 6px;
    border-radius: 3px;
    overflow: hidden;
}
#checklistModal .progress-bar-wrapper .progress-bar {
    transition: width 0.3s ease;
}
.booking-quick-range .btn {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}
</style>
<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <?php if((int)$this->session->userdata('level') !== 10) { $this->load->view('booking/_summary_cards'); } ?>
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
                                <?php if(!empty($quick_filters)) { ?>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Apply Quick Filter</label>
                                                <div class="d-flex align-items-center">
                                                    <select id="quick_filter_select" class="form-control selectpicker" data-live-search="true" data-actions-box="false" title="--SELECT QUICK FILTER--" style="flex:1;">
                                                        <?php foreach($quick_filters as $qf) { ?>
                                                            <option value="<?php echo $qf->QuickFilterID; ?>"><?php echo htmlspecialchars($qf->Name); ?></option>
                                                        <?php } ?>
                                                    </select>
                                                    <a href="<?php echo base_url('Quick_Filter'); ?>" class="btn btn-light-primary font-weight-bold ml-2" style="white-space:nowrap;">Manage</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php } else { ?>
                                    <div class="mb-3">
                                        <a href="<?php echo base_url('Quick_Filter'); ?>" class="btn btn-light-primary font-weight-bold">Manage Quick Filters</a>
                                    </div>
                                <?php } ?>
                                <form id="form" action="<?php echo base_url('Booking') ?>" method="get" class="form">
                                    <?php $this->load->view('booking/_filter_fields', ['filter_values' => $filter_values]); ?>
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
                <button type="button"
                        class="btn btn-warning font-weight-bold mb-2"
                        id="change-autocount-to-pending"
                        style="width:180px; display:none;">
                    Change to P Status
                </button>

                <div class="dataTables_wrapper dt-bootstrap4 no-footer" style="overflow-x:auto;">
                    <table id="kt_datatable" class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline" style="width:100%;">
                        <thead>
                            <tr>
                                <th style="text-align:center;">No.</th>
                                <th class="booking_checkbox" style="text-align:center;">
                                    <input class="booking_checkbox" type="checkbox" id="check_all">
                                </th>
                                <th style="text-align:center;">TC</th>
                                <th style="text-align:center;">TC 2</th>
                                <th style="text-align:center;">OP</th>
                                <th class="bc_date" style="text-align:center;">Creation Date</th>
                                <th style="text-align:center;">BC Number</th>
                                <th style="text-align:center;">BC</th>
                                <th style="text-align:center;">Customer</th>
                                <th style="text-align:center;">Source</th>
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
                                <?php if($show_autocount_status) { ?>
                                <th class="autocount_sync_status" style="text-align:center;">Booking Autocount Status</th>
                                <?php } ?>
                                <th class="action" style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
                <br>
                <div class="row" id="summary_section">
                    <div class="col-md-12 pt-3 pb-3" style="background-color:white; border:3px solid #D7E2F2; border-radius:8px;">
                        <div class="row">
                            <div class="<?php echo $is_sales_agent ? 'col-md-12' : 'col-md-6 mb-7 mb-md-0'; ?>">
                                <label style="color:#C4B454;">Total Net Sales (RM)</label>
                                <div class="input-icon">
                                    <input disabled type="text" id="total_sales_display" value="Loading..." class="form-control" style="text-align:right;">
                                    <span>
                                        <i class="la la-dollar"></i>
                                    </span>
                                </div>
                            </div>
                            <?php if(!$is_sales_agent) { ?>
                            <div class="col-md-6">
                                <label style="color:#FFC000;">Total Net Profit (RM)</label>
                                <div class="input-icon">
                                    <input disabled type="text" id="total_net_profit_display" value="Loading..." class="form-control" style="text-align:right;">
                                    <span>
                                        <i class="la la-dollar"></i>
                                    </span>
                                </div>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
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

    $(window).on('load', function() {
        $('#kt_daterangepicker_4').daterangepicker({
            buttonClasses: ' btn',
            applyClass: 'btn-primary',
            cancelClass: 'btn-secondary',
            autoApply: true
        }, function(start, end, label) {
            $('#kt_daterangepicker_4 .form-control').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
        });

        $('#kt_daterangepicker_5').daterangepicker({
            buttonClasses: ' btn',
            applyClass: 'btn-primary',
            cancelClass: 'btn-secondary',
            autoApply: true
        }, function(start, end, label) {
            $('#kt_daterangepicker_5 .form-control').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
        });
    });

    $(document).on('click', '.booking-quick-range button', function() {
        var $row     = $(this).closest('.booking-quick-range');
        var target   = $row.data('target');
        var range    = $(this).data('range');
        var pickerId = target === 'travel_date' ? '#kt_daterangepicker_4' : '#kt_daterangepicker_5';

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

        var picker = $(pickerId).data('daterangepicker');
        if(picker) {
            picker.setStartDate(start);
            picker.setEndDate(end);
        }
        $('input[name="' + target + '"]').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
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

    <?php if(!empty($this->input->get('booking_number')) || !empty($this->input->get('reservation_number')) || !empty($this->input->get('deadline')) || !empty($this->input->get('customer')) || !empty($this->input->get('mobile')) || !empty($this->input->get('travel_date')) || !empty($this->input->get('destination')) || !empty($this->input->get('sales_agent')) || !empty($this->input->get('tag')) || !empty($this->input->get('chat_language')) || !empty($this->input->get('source')) || !empty($this->input->get('booking_confirmation_title')) || !empty($this->input->get('status')) || !empty($this->input->get('booking_date')) || !empty($this->input->get('autocount_status')) || !empty($this->input->get('guest_list_status')) || !empty($this->input->get('cancellation_reason')) || !empty($this->input->get('customer_type'))) { ?>
        $('#booking_header').click();
    <?php } ?>
    
    // Sync multi-select filters to their hidden inputs on change and form submit
    var multi_filters = ['status','destination','source','chat_language',
        'booking_confirmation_title','tag','sales_agent','sales_agent_2',
        'booking_op','autocount_status','guest_list_status','checklist_filter',
        'cancellation_reason','einvoice_status','customer_type'];

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
    $('#form').on('submit', function() {
        multi_filters.forEach(syncMultiSelect);
    });

    $('#reset').click(function() {
        Reset('<?php echo base_url('Booking'); ?>');
    });

    // Apply a saved Quick Filter by redirecting to its preset query string
    $('#quick_filter_select').on('changed.bs.select', function() {
        var id = $(this).val();
        if(id) {
            window.location = '<?php echo base_url('Quick_Filter/Apply'); ?>/' + id;
        }
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
        } else if(value == 'CUSTOMER PORTAL LINK') {
            elementId = 'portal_url-' + booking_id;
        } else if(value == 'BOOKING PAGE LINK') {
            elementId = 'booking_page_url-' + booking_id;
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

    function Cancel_Booking(background, bookingNumber, bookingId, param)
    {
        var reasonOptions = '';
        <?php if(isset($cancellation_reasons)) { foreach($cancellation_reasons as $reason) { ?>
            reasonOptions += '<option value="<?php echo $reason->CancellationReasonID; ?>"><?php echo str_replace("'", "\\'", $reason->Name); ?></option>';
        <?php } } ?>

        const swalWithBootstrapButtons = Swal.mixin({
            customClass: {
                confirmButton: 'btn btn-light-success m-2',
                cancelButton: 'btn btn-danger m-2'
            },
            buttonsStyling: true
        });
        swalWithBootstrapButtons.fire({
            width: 550,
            background: 'url(' + background + ')',
            icon: 'warning',
            title: 'Cancel Booking ?',
            html: '<b>' + bookingNumber + '</b><br><br>' +
                  '<select id="swal_cancellation_reason" class="form-control" style="text-align:center;">' +
                  '<option value="">-- SELECT CANCELLATION REASON --</option>' +
                  reasonOptions +
                  '</select>',
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel',
            showCancelButton: true,
            preConfirm: () => {
                var reason = document.getElementById('swal_cancellation_reason').value;
                if(!reason) {
                    Swal.showValidationMessage('Please select a cancellation reason');
                    return false;
                }
                return reason;
            }
        }).then((action) => {
            if(action.isConfirmed) {
                $.ajax({
                    url: '<?php echo base_url('Booking/Update_Cancel_Status'); ?>',
                    type: 'post',
                    data: {
                        booking_id: bookingId,
                        cancellation_reason_id: action.value
                    },
                    success: function() {
                        Display_Message(background, 'Booking ' + bookingNumber + ' Successfully Cancelled', window.location.href);
                    },
                    error: function() {
                        Display_Message(background, 'Booking ' + bookingNumber + ' Could Not Be Cancelled', null);
                    }
                });
            }
        });
    }

    function Cancel_With_Partial_Refund(background, bookingNumber, bookingId, param)
    {
        var reasonOptions = '';
        <?php if(isset($cancellation_reasons)) { foreach($cancellation_reasons as $reason) { ?>
            reasonOptions += '<option value="<?php echo $reason->CancellationReasonID; ?>"><?php echo str_replace("'", "\\'", $reason->Name); ?></option>';
        <?php } } ?>

        const swalWithBootstrapButtons = Swal.mixin({
            customClass: {
                confirmButton: 'btn btn-light-success m-2',
                cancelButton: 'btn btn-danger m-2'
            },
            buttonsStyling: true
        });
        swalWithBootstrapButtons.fire({
            width: 550,
            background: 'url(' + background + ')',
            icon: 'warning',
            title: 'Cancel With Partial Refund ?',
            html: '<b>' + bookingNumber + '</b><br>' +
                  '<small style="color:#555;">The BC will remain active, but this booking will appear in the customer\'s Cancelled tab.</small><br><br>' +
                  '<select id="swal_partial_refund_reason" class="form-control" style="text-align:center;">' +
                  '<option value="">-- SELECT CANCELLATION REASON --</option>' +
                  reasonOptions +
                  '</select>',
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel',
            showCancelButton: true,
            preConfirm: () => {
                var reason = document.getElementById('swal_partial_refund_reason').value;
                if(!reason) {
                    Swal.showValidationMessage('Please select a cancellation reason');
                    return false;
                }
                return reason;
            }
        }).then((action) => {
            if(action.isConfirmed) {
                $.ajax({
                    url: '<?php echo base_url('Booking/Update_Partial_Refund_Status'); ?>',
                    type: 'post',
                    data: {
                        booking_id: bookingId,
                        cancellation_reason_id: action.value
                    },
                    success: function() {
                        Display_Message(background, 'Booking ' + bookingNumber + ' Successfully Marked As Partial Refund', window.location.href);
                    },
                    error: function() {
                        Display_Message(background, 'Booking ' + bookingNumber + ' Could Not Be Marked As Partial Refund', null);
                    }
                });
            }
        });
    }
</script>

<script>
// DataTables Server-Side Initialization
var is_sales_agent = <?php echo $is_sales_agent ? 'true' : 'false'; ?>;
var bookingTable;

$(document).ready(function() {
    // Use setTimeout to run AFTER column-rendering.js initialization
    setTimeout(function() {
        // Destroy existing DataTable if it exists (from column-rendering.js)
        if ($.fn.DataTable.isDataTable('#kt_datatable')) {
            $('#kt_datatable').DataTable().destroy();
        }

        // Build columns array based on user role
        // responsivePriority: LOWER number = HIGHER priority (stays visible longer)
        // HIGHER number = LOWER priority (hidden first on smaller screens)
        var columns = [
            {
                data: 'row_number',
                orderable: false,
                searchable: false,
                className: 'text-center',
                responsivePriority: 1,
                createdCell: function(td) {
                    $(td).css('text-align', 'center');
                }
            }
        ];

        // Checkbox column - always show (only populated for Failed status)
        columns.push({ data: 'checkbox', orderable: false, searchable: false, className: 'text-center', responsivePriority: 2 });

        columns.push({ data: 'sales_agent', className: 'text-center', responsivePriority: 10000 });
        columns.push({ data: 'sales_agent_2', className: 'text-center', responsivePriority: 10000 });
        columns.push({ data: 'booking_op', className: 'text-center', responsivePriority: 10000 });

        columns = columns.concat([
            { data: 'insert_date', className: 'text-center', responsivePriority: 10001 },
            { data: 'booking_number', className: 'text-center', responsivePriority: 3 },
            { data: 'bc_title', className: 'text-center', responsivePriority: 10002 },
            { data: 'customer', className: 'text-center', responsivePriority: 4, createdCell: function(td, cellData, rowData) { if (rowData.has_einvoice) { $(td).css('background-color', '#c8e6c9'); } } },
            { data: 'source', className: 'text-center', responsivePriority: 10011 },
            { data: 'chat_language', className: 'text-center', responsivePriority: 10003 },
            { data: 'mobile', orderable: false, searchable: false, className: 'text-center', responsivePriority: 5 },
            { data: 'start_date', className: 'text-center', responsivePriority: 10004 },
            { data: 'end_date', className: 'text-center', responsivePriority: 10005 },
            { data: 'destination', className: 'text-center', responsivePriority: 10006 },
            { data: 'net_total', className: 'text-center', responsivePriority: 6 }
        ]);

        if (!is_sales_agent) {
            columns.push({ data: 'profit', className: 'text-center', responsivePriority: 10007 });
            columns.push({ data: 'profit_margin', className: 'text-center', responsivePriority: 10008 });
        }

        columns = columns.concat([
            { data: 'status', className: 'text-center', responsivePriority: 7 },
            { data: 'gl_status', className: 'text-center', responsivePriority: 10009 },
            <?php if($show_autocount_status) { ?>
            { data: 'autocount_status', className: 'text-center', responsivePriority: 10010 },
            <?php } ?>
            { data: 'action', orderable: false, searchable: false, className: 'text-center', responsivePriority: 1 }
        ]);

        // Get current filter params from URL
        var urlParams = new URLSearchParams(window.location.search);
        var filterParams = {};
        ['customer', 'booking_number', 'reservation_number', 'mobile', 'destination', 'travel_date',
         'deadline', 'source', 'chat_language', 'booking_date', 'status', 'booking_confirmation_title',
         'tag', 'sales_agent', 'sales_agent_2', 'booking_op', 'autocount_status', 'guest_list_status', 'checklist_filter', 'cancellation_reason', 'einvoice_status', 'customer_type', 'upcoming_not_ready'].forEach(function(param) {
            if (urlParams.has(param)) {
                filterParams[param] = urlParams.get(param);
            }
        });

        // Initialize DataTable with server-side processing
        bookingTable = $('#kt_datatable').DataTable({
            processing: true,
            serverSide: true,
            scrollX: true,
            responsive: false,
            ajax: {
                url: '<?php echo base_url("Booking/ajax_list"); ?>',
                type: 'GET',
                data: function(d) {
                    // Add filter params to request
                    for (var key in filterParams) {
                        d[key] = filterParams[key];
                    }
                    return d;
                }
            },
            columns: columns,
            order: [[5, 'desc']], // Order by Insert Date (creation date) descending
            pageLength: 100,
            lengthMenu: [[50, 100, 200, 500], [50, 100, 200, 500]],
            searchDelay: 300, // 300ms debounce on search
            language: {
                processing: '<div class="spinner spinner-primary spinner-lg mr-15"></div> Loading...',
                emptyTable: 'Booking Records Not Found',
                zeroRecords: 'No matching records found'
            },
            drawCallback: function(settings) {
                // Re-initialize tooltips after each draw
                $('[data-toggle="tooltip"]').tooltip({
                    html: true,
                    container: 'body',
                    boundary: 'viewport',
                    trigger: 'hover',
                    delay: { "show": 300, "hide": 100 }
                });
                
                // Allow tooltip to stay open when hovering over it
                $('[data-toggle="tooltip"]').on('shown.bs.tooltip', function() {
                    var $tooltip = $(this);
                    var $tip = $tooltip.next('.tooltip');
                    $tip.on('mouseenter', function() {
                        $tooltip.tooltip('show');
                    });
                    $tip.on('mouseleave', function() {
                        $tooltip.tooltip('hide');
                    });
                });
                
                // Re-attach checkbox event listeners
                attachCheckboxListeners();
            }
        });

        // Load summary totals
        loadSummaryTotals();
    }, 100); // Small delay to ensure column-rendering.js runs first
});

// Function to load summary totals via AJAX
function loadSummaryTotals() {
    var urlParams = new URLSearchParams(window.location.search);
    var params = [];
    ['customer', 'booking_number', 'reservation_number', 'mobile', 'destination', 'travel_date',
     'deadline', 'source', 'chat_language', 'booking_date', 'status', 'booking_confirmation_title',
     'tag', 'sales_agent', 'sales_agent_2', 'booking_op', 'autocount_status', 'guest_list_status', 'checklist_filter', 'cancellation_reason', 'einvoice_status', 'customer_type', 'upcoming_not_ready'].forEach(function(param) {
        if (urlParams.has(param)) {
            params.push(param + '=' + encodeURIComponent(urlParams.get(param)));
        }
    });

    var queryString = params.length > 0 ? '?' + params.join('&') : '';

    $.ajax({
        url: '<?php echo base_url("Booking/ajax_summary"); ?>' + queryString,
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            $('#total_sales_display').val(data.total_sales);
            if (!is_sales_agent) {
                $('#total_net_profit_display').val(data.total_net_profit);
            }
        },
        error: function() {
            $('#total_sales_display').val('Error loading');
            if (!is_sales_agent) {
                $('#total_net_profit_display').val('Error loading');
            }
        }
    });
}

// Function to attach checkbox listeners (called after each DataTable draw)
function attachCheckboxListeners() {
    // Check All toggle
    $('#check_all').off('change').on('change', function() {
        var checked = this.checked;
        $('.check_item').prop('checked', checked);
        toggleButton();
    });

    // Individual checkbox toggle
    $('.check_item').off('change').on('change', function() {
        // If one unchecked → uncheck "check_all"
        if (!this.checked) {
            $('#check_all').prop('checked', false);
        }
        // If all checked → check "check_all"
        else if ($('.check_item:checked').length === $('.check_item').length) {
            $('#check_all').prop('checked', true);
        }
        toggleButton();
    });
}
</script>

<script>
function toggleButton() {
    let anyChecked = document.querySelectorAll('.check_item:checked').length > 0;
    var syncBtn = document.getElementById('sync-autocount-booking');
    var changeToPendingBtn = document.getElementById('change-autocount-to-pending');

    if (syncBtn) {
        syncBtn.style.display = anyChecked ? 'inline-block' : 'none';
    }
    if (changeToPendingBtn) {
        changeToPendingBtn.style.display = anyChecked ? 'inline-block' : 'none';
    }
}
</script>
<script>
var syncAutocountBtn = document.getElementById('sync-autocount-booking');
if (syncAutocountBtn) {
    syncAutocountBtn.addEventListener('click', function() {
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
                alert(data.message);
            } else {
                alert("Error: " + data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert("Error occurred during sync.");
        });
    });
}

// Handler for "Chg to P Status" button
var changeAutocountBtn = document.getElementById('change-autocount-to-pending');
if (changeAutocountBtn) {
    changeAutocountBtn.addEventListener('click', function() {
        let selected = Array.from(document.querySelectorAll('.check_item:checked'))
                            .map(cb => cb.value);

        if (selected.length === 0) {
            alert("Please select at least one booking.");
            return;
        }

        // Confirmation dialog
        if (!confirm("Are you sure you want to change " + selected.length + " booking(s) to Pending (P) status?")) {
            return;
        }

        fetch("<?php echo base_url('Booking/bulkChangeAutocountStatusToPending'); ?>", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ booking_ids: selected })
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                bookingTable.ajax.reload(); // Refresh table
            }
        })
        .catch(err => {
            console.error(err);
            alert("Error occurred during status change.");
        });
    });
}
</script>

<!-- Checklist Modal -->
<div class="modal fade" id="checklistModal" tabindex="-1" role="dialog" aria-labelledby="checklistModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background-color:#D7E2F2;">
                <h5 class="modal-title" id="checklistModalLabel" style="color:#6082B6;">
                    <strong>Booking Checklist</strong>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="checklistModalBody">
                <div class="text-center py-5">
                    <div class="spinner spinner-primary spinner-lg"></div>
                    <p class="mt-3 text-muted">Loading checklist...</p>
                </div>
            </div>
            <div class="modal-footer" id="checklistModalFooter" style="display:none;">
                <div style="width:100%;">
                    <div class="checklist-progress">
                        <div class="progress-info">
                            <span class="progress-text">
                                <strong id="modal_checked_count">0</strong> of <strong id="modal_total_count">0</strong> items completed
                            </span>
                            <span class="progress-percentage" id="modal_percentage">0%</span>
                        </div>
                        <div class="progress-bar-wrapper">
                            <div class="progress" style="height: 8px; background-color: #e9ecef; border-radius: 4px;">
                                <div class="progress-bar bg-success" id="modal_progress_bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="0"></div>
                            </div>
                        </div>
                    </div>
                    <button type="button" id="modal_save_checklist_btn" class="btn btn-primary btn-sm font-weight-bold mt-3">
                        <i class="la la-save"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var modalBookingId = null;
var modalTotalItems = 0;
var modalCanModify = false;

function openChecklistModal(bookingId) {
    modalBookingId = bookingId;

    // Reset modal
    $('#checklistModalLabel').html('<strong>Booking Checklist</strong>');
    $('#checklistModalBody').html('<div class="text-center py-5"><div class="spinner spinner-primary spinner-lg"></div><p class="mt-3 text-muted">Loading checklist...</p></div>');
    $('#checklistModalFooter').hide();
    $('#checklistModal').modal('show');

    // Fetch checklist data
    $.ajax({
        url: '<?php echo base_url("Booking/Get_Checklist/"); ?>' + bookingId,
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            if(!data.success) {
                $('#checklistModalBody').html('<div class="text-center py-5"><p class="text-danger">' + (data.message || 'Failed to load checklist') + '</p></div>');
                return;
            }

            $('#checklistModalLabel').html('<strong>Booking Checklist &mdash; ' + escapeHtml(data.booking_number) + '</strong>');
            modalTotalItems = data.total_count;
            modalCanModify = !!data.can_modify;
            var disabledAttr = modalCanModify ? '' : ' disabled';

            // Build checklist HTML
            var html = '';
            for(var gi = 0; gi < data.groups.length; gi++) {
                var group = data.groups[gi];
                if(data.is_multi_product && gi > 0) {
                    html += '<div style="margin: 1rem 0;"></div>';
                }
                html += '<h6 class="font-weight-bold mb-3" style="color:#6082B6;">' + escapeHtml(group.product_name) + '</h6>';
                if(group.PaymentOutSupplierDeposit || group.PaymentOutSupplierFull) {
                    html += '<div class="mb-3" style="margin-top:-0.5rem;">';
                    if(group.PaymentOutSupplierDeposit) {
                        html += '<span class="label label-inline label-light-warning font-weight-bold mr-2">';
                        html += '<i class="la la-calendar-check-o mr-1" style="font-size:14px;"></i>Supplier Deposit: ' + escapeHtml(group.PaymentOutSupplierDeposit);
                        html += '</span>';
                    }
                    if(group.PaymentOutSupplierFull) {
                        html += '<span class="label label-inline label-light-primary font-weight-bold">';
                        html += '<i class="la la-calendar-check-o mr-1" style="font-size:14px;"></i>Supplier Full: ' + escapeHtml(group.PaymentOutSupplierFull);
                        html += '</span>';
                    }
                    html += '</div>';
                }
                html += '<div class="checklist-container">';

                for(var ci = 0; ci < group.checklists.length; ci++) {
                    var cl = group.checklists[ci];
                    var pid = group.product_id;
                    var isChecked = data.completion_map[pid] && data.completion_map[pid][cl.ID];
                    var completionInfo = isChecked ? data.completion_map[pid][cl.ID] : null;
                    var value = pid + '_' + cl.ID;
                    var inputId = 'modal_cl_' + pid + '_' + cl.ID;

                    html += '<div class="checklist-item ' + (isChecked ? 'checked' : '') + '">';
                    html += '<div class="checklist-item-content">';
                    html += '<div class="checklist-checkbox-wrapper">';
                    html += '<input class="form-check-input checklist-checkbox modal-checklist-cb" type="checkbox" id="' + inputId + '" value="' + value + '"' + (isChecked ? ' checked' : '') + disabledAttr + '>';
                    html += '<label class="checklist-checkbox-label" for="' + inputId + '"></label>';
                    html += '</div>';
                    html += '<div class="checklist-details">';
                    html += '<div class="checklist-name-wrapper">';
                    html += '<label class="checklist-name" for="' + inputId + '">' + escapeHtml(cl.name) + '</label>';
                    html += '</div>';
                    if(isChecked && completionInfo) {
                        html += '<div class="checklist-completion-info">';
                        html += '<i class="la la-user-circle text-primary"></i>';
                        html += '<span class="completion-text">Completed by <strong>' + escapeHtml(completionInfo.created_by_name) + '</strong>';
                        html += '<span class="completion-separator">&bull;</span>';
                        html += '<span class="completion-date">' + escapeHtml(completionInfo.created_at) + '</span></span>';
                        html += '</div>';
                    }
                    html += '</div></div></div>';
                }
                html += '</div>';
            }

            if(!modalCanModify) {
                html += '<div class="text-muted small mt-3"><i class="la la-lock"></i> Only this booking\'s TC1 and OP can update the checklist.</div>';
            }

            $('#checklistModalBody').html(html);
            $('#checklistModalFooter').toggle(modalCanModify);
            updateModalProgress();
        },
        error: function() {
            $('#checklistModalBody').html('<div class="text-center py-5"><p class="text-danger">Failed to load checklist. Please try again.</p></div>');
        }
    });
}

// Checkbox change handler (delegated)
$(document).on('change', '.modal-checklist-cb', function() {
    var $cb = $(this);
    var $item = $cb.closest('.checklist-item');
    if($cb.is(':checked')) {
        $item.addClass('checked');
    } else {
        $item.removeClass('checked');
        $item.find('.checklist-completion-info').remove();
    }
    updateModalProgress();
});

function updateModalProgress() {
    var checked = $('#checklistModal .modal-checklist-cb:checked').length;
    var pct = modalTotalItems > 0 ? Math.round((checked / modalTotalItems) * 100) : 0;
    $('#modal_checked_count').text(checked);
    $('#modal_total_count').text(modalTotalItems);
    $('#modal_percentage').text(pct + '%');
    $('#modal_progress_bar').css('width', pct + '%').attr('aria-valuenow', checked).attr('aria-valuemax', modalTotalItems);
}

// Save handler
$('#modal_save_checklist_btn').on('click', function() {
    if(!modalCanModify) {
        return;
    }
    var $btn = $(this);
    var originalText = $btn.html();
    $btn.prop('disabled', true).html('<i class="la la-spinner la-spin"></i> Saving...');

    // Collect checked values
    var completions = [];
    $('#checklistModal .modal-checklist-cb:checked').each(function() {
        completions.push($(this).val());
    });

    // Build POST data
    var postData = 'booking_id=' + modalBookingId;
    if(completions.length === 0) {
        postData += '&checklist_completions=';
    }
    $.each(completions, function(i, val) {
        postData += '&checklist_completions[]=' + encodeURIComponent(val);
    });

    $.ajax({
        url: '<?php echo base_url("Booking/Save_Checklist"); ?>',
        type: 'POST',
        data: postData,
        contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
        dataType: 'json',
        success: function(response) {
            $btn.prop('disabled', false).html(originalText);
            if(response && response.success) {
                $('#checklistModal').modal('hide');
                Swal.fire({
                    width: 550,
                    background: 'url(<?php echo base_url("assets/image/sweetalert.jpg"); ?>)',
                    icon: 'success',
                    title: 'Booking Checklist Updated',
                    showConfirmButton: false,
                    timer: 1500
                });
                // Refresh DataTable to reflect any status changes
                if(typeof bookingTable !== 'undefined') {
                    bookingTable.ajax.reload(null, false);
                }
            } else {
                Swal.fire({
                    width: 550,
                    background: 'url(<?php echo base_url("assets/image/sweetalert.jpg"); ?>)',
                    icon: 'error',
                    title: 'Failed to Update Checklist',
                    text: (response && response.message) ? response.message : '',
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
                title: 'Failed to Update Checklist',
                showConfirmButton: false,
                timer: 3000
            });
        }
    });
});

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
