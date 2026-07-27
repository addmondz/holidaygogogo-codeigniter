<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong><?php if(current_url() == base_url('Quick_Filter/Create')) { echo 'New Quick Filter Record'; } else { echo 'Quick Filter Record : ' . htmlspecialchars($Name); } ?></strong>
                    </h3>
                </div>
            </div>
            <div class="card-body">
                <form id="form">
                    <strong>Quick Filter Information :</strong>
                    <br><br>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Name
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="text" id="Name" <?php if(current_url() == base_url('Quick_Filter/Update')) { ?> value="<?php echo htmlspecialchars($Name); ?>" <?php } ?> autocomplete="off" class="form-control" maxlength="100">
                                    <span>
                                        <i class="la la-filter"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <br>
                    <strong>Booking Filter Values :</strong>
                    <p class="text-muted" style="font-size:12px;">Leave any field empty to skip that filter. The combination below will be applied when this quick filter is selected on the Booking listing.</p>
                    <br>
                    <?php $this->load->view('booking/_filter_fields', ['filter_values' => $filter_values]); ?>
                    <div class="d-flex justify-content-between border-top pt-5">
                        <input type="button" id="submit_btn" value="<?php if(current_url() == base_url('Quick_Filter/Create')) { echo 'Create Quick Filter'; } else { echo 'Update Quick Filter'; } ?>" class="btn btn-success font-weight-bold px-9 py-4" style="width:220px; margin-left:auto;">
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Keep these functions available for the daterangepicker reset buttons in the shared partial
    function Reset_Travel_Date() { $('input[name="travel_date"]').val(''); }
    function Reset_Deadline()    { $('input[name="deadline"]').val(''); }
    function Reset_Booking_Date(){ $('input[name="booking_date"]').val(''); }

    // Same multi-select sync/reorder logic as on the booking listing
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
    multi_filters.forEach(function(name) {
        $('#' + name + '_select').on('changed.bs.select', function() { syncMultiSelect(name); });
    });

    // Duplicate-name detection
    $('#Name').change(function() {
        var name = ($('#Name').val()).toUpperCase();
        $.ajax({
            url: '<?php echo base_url('Quick_Filter/Detect') ?>',
            type: 'post',
            data: {
                name: name,
                quick_filter_id: '<?php echo (current_url() == base_url('Quick_Filter/Update')) ? $QuickFilterID : ''; ?>'
            },
            dataType: 'json',
            success: function(redundant_name) {
                if(redundant_name) {
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Redundant Name Detected', null);
                    $('#Name').val('');
                }
            }
        });
    });

    // Build FilterData JSON from all booking filter field values
    var filter_keys = ['customer','booking_number','reservation_number','mobile',
        'destination','travel_date','deadline','source',
        'chat_language','booking_date','status','booking_confirmation_title',
        'tag','customer_type','sales_agent','sales_agent_2','booking_op',
        'autocount_status','guest_list_status','checklist_filter',
        'cancellation_reason','einvoice_status'];

    function Collect_Filter_Data() {
        // Ensure multi-select hidden inputs reflect current selections
        multi_filters.forEach(syncMultiSelect);
        var data = {};
        filter_keys.forEach(function(k) {
            // Multi-select fields have a hidden <input name="k"> AND a <select> without a name.
            // Text/date fields have just one <input name="k">.
            var $inputs = $('[name="' + k + '"]');
            if(!$inputs.length) return;
            var val = $inputs.last().val();
            if(val !== null && val !== undefined && val !== '') {
                data[k] = val;
            }
        });
        return data;
    }

    $('#submit_btn').click(function() {
        Swal.mixin({
            customClass: {
                confirmButton: 'btn btn-light-success m-2',
                cancelButton: 'btn btn-danger m-2'
            },
            buttonsStyling: true
        }).fire({
            width: 550,
            background: 'url(<?php echo base_url('assets/image/sweetalert.jpg') ?>)',
            icon: 'warning',
            title: <?php if(current_url() == base_url('Quick_Filter/Create')) { ?> 'Create New Quick Filter Record ?' <?php } else { ?> '<?php echo 'Update Quick Filter Record : ' . str_replace('\'', '', $Name) . ' ?'; ?>' <?php } ?>,
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel',
            showCancelButton: true
        }).then((action) => {
            if(action.isConfirmed) {
                var name = ($('#Name').val()).toUpperCase();
                if(name == '') {
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert A Quick Filter Name', null);
                    return;
                }
                var filter_data = JSON.stringify(Collect_Filter_Data());
                if(window.location.href == '<?php echo base_url('Quick_Filter/Create'); ?>') {
                    var payload = [{
                        Name: name,
                        FilterData: filter_data,
                        InsertBy: <?php echo $this->session->userdata('admin_id') ?>,
                        InsertDate: '<?php echo date('Y-m-d H:i:s') ?>'
                    }];
                    Submit_Quick_Filter('<?php echo base_url('Quick_Filter/Create') ?>', payload);
                } else {
                    var payload = [{
                        QuickFilterID: <?php echo $QuickFilterID ?>,
                        Name: name,
                        FilterData: filter_data,
                        UpdateBy: <?php echo $this->session->userdata('admin_id') ?>,
                        UpdateDate: '<?php echo date('Y-m-d H:i:s') ?>'
                    }];
                    Submit_Quick_Filter('<?php echo base_url('Quick_Filter/Update') ?>', payload);
                }
            }
        });
    });

    function Submit_Quick_Filter(url, quick_filter) {
        $.ajax({
            url: url,
            type: 'post',
            data: { quick_filter: quick_filter },
            success: function() {
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php if(current_url() == base_url('Quick_Filter/Create')) { echo 'New'; } ?> Quick Filter Record <?php if(current_url() == base_url('Quick_Filter/Update')) { echo ': ' . str_replace('\'', '', $Name); } ?> Successfully <?php if(current_url() == base_url('Quick_Filter/Create')) { echo 'Created'; } else { echo 'Updated'; } ?>', '<?php echo base_url('Quick_Filter') ?>');
            },
            error: function() {
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php if(current_url() == base_url('Quick_Filter/Create')) { echo 'New'; } ?> Quick Filter Record <?php if(current_url() == base_url('Quick_Filter/Update')) { echo ': ' . str_replace('\'', '', $Name); } ?> Could Not Be <?php if(current_url() == base_url('Quick_Filter/Create')) { echo 'Created'; } else { echo 'Updated'; } ?>', null);
            }
        });
    }
</script>
