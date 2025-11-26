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

                <div class="dataTables_wrapper dt-bootstrap4 no-footer" style="overflow-x:auto;">
                    <table id="kt_datatable" class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline" style="width:100%;">
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
                        </tbody>
                    </table>
                </div>
                <br>
                <div class="row" id="summary_section">
                    <div class="col-md-12 pt-3 pb-3" style="background-color:white; border:3px solid #D7E2F2; border-radius:8px;">
                        <div class="row">
                            <div class="col-md-6 mb-7 mb-md-0">
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
        if(value == 'BC URL') {
            var url = $(`#bc_url-${booking_id}`).val();
        } else {
            if(value == 'GL URL') {
                var url = $(`#gl_url-${booking_id}`).val();
            } else {
                if(value == 'TV URL') {
                    var url = $(`#tv_url-${booking_id}`).val();
                } else {
                    if(value == 'CUSTOMER NAME') {
                        var url = $(`#customer_name-${booking_id}`).val();
                    } else {
                        var url = $(`#customer_mobile-${booking_id}`).val();
                    }
                }
            }
        }
        navigator.clipboard.writeText(url).then(() => { alert("Successfully Copied"); }) .catch(() => { alert("Something Went Wrong"); });
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
        var columns = [
            { data: 'checkbox', orderable: false, searchable: false, className: 'text-center' },
            { data: 'row_number', orderable: false, searchable: false, className: 'text-center' }
        ];

        if (!is_sales_agent) {
            columns.push({ data: 'sales_agent', className: 'text-center' });
        }

        columns = columns.concat([
            { data: 'insert_date', className: 'text-center' },
            { data: 'booking_number', className: 'text-center' },
            { data: 'bc_title', className: 'text-center' },
            { data: 'customer', className: 'text-center' },
            { data: 'customer_code', className: 'text-center' },
            { data: 'chat_language', className: 'text-center' },
            { data: 'mobile', orderable: false, searchable: false, className: 'text-center' },
            { data: 'start_date', className: 'text-center' },
            { data: 'end_date', className: 'text-center' },
            { data: 'destination', className: 'text-center' },
            { data: 'net_total', className: 'text-center' }
        ]);

        if (!is_sales_agent) {
            columns.push({ data: 'profit', className: 'text-center' });
            columns.push({ data: 'profit_margin', className: 'text-center' });
        }

        columns = columns.concat([
            { data: 'status', className: 'text-center' },
            { data: 'gl_status', className: 'text-center' },
            { data: 'autocount_status', className: 'text-center' },
            { data: 'action', orderable: false, searchable: false, className: 'text-center' }
        ]);

        // Get current filter params from URL
        var urlParams = new URLSearchParams(window.location.search);
        var filterParams = {};
        ['customer', 'booking_number', 'reservation_number', 'mobile', 'destination', 'travel_date',
         'deadline', 'source', 'chat_language', 'booking_date', 'status', 'booking_confirmation_title',
         'tag', 'sales_agent'].forEach(function(param) {
            if (urlParams.has(param)) {
                filterParams[param] = urlParams.get(param);
            }
        });

        // Initialize DataTable with server-side processing
        bookingTable = $('#kt_datatable').DataTable({
            processing: true,
            serverSide: true,
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
            order: [[1, 'desc']], // Order by row number (BookingID) descending
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
                $('[data-toggle="tooltip"]').tooltip();
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
     'tag', 'sales_agent'].forEach(function(param) {
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
            if (!data.is_sales_agent) {
                $('#total_net_profit_display').val(data.total_net_profit);
            }
        },
        error: function() {
            $('#total_sales_display').val('Error loading');
            $('#total_net_profit_display').val('Error loading');
        }
    });
}

// Function to attach checkbox listeners (called after each DataTable draw)
function attachCheckboxListeners() {
    var bulkBookingSyncToAutocount = <?php echo $bulkBookingSyncToAutocount ? 'true' : 'false'; ?>;

    // Check All toggle
    $('#check_all').off('change').on('change', function() {
        var checked = this.checked;
        $('.check_item').prop('checked', checked);
        if (bulkBookingSyncToAutocount) {
            toggleButton();
        }
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
        if (bulkBookingSyncToAutocount) {
            toggleButton();
        }
    });
}
</script>

<script>
function toggleButton() {
    let anyChecked = document.querySelectorAll('.check_item:checked').length > 0;
    var syncBtn = document.getElementById('sync-autocount-booking');
    if (syncBtn) {
        syncBtn.style.display = anyChecked ? 'inline-block' : 'none';
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
</script>