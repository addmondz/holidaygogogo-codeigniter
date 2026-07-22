<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong><?php if(current_url() == base_url('Customer_Type/Create')) { echo 'New Customer Type Record'; } else { echo 'Customer Type Record : ' . $Name; } ?></strong>
                    </h3>
                </div>
            </div>
            <div class="card-body">
                <form id="form">
                    <strong>Customer Type Information :</strong>
                    <br><br>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Name
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="text" id="Name" <?php if(current_url() == base_url('Customer_Type/Update')) { ?> value="<?php echo $Name; ?>" <?php } ?> autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-user-tag"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-5">
                        <input type="button" value="<?php if(current_url() == base_url('Customer_Type/Create')) { echo 'Create Customer Type'; } else { echo 'Update Customer Type'; } ?>" class="btn btn-success font-weight-bold px-9 py-4" style="width:220px; margin-left:auto;">
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    $('#Name').change(function() {
        var name = ($('#Name').val()).toUpperCase();
        $.ajax({
            url: '<?php echo base_url('Customer_Type/Detect') ?>',
            type: 'post',
            data: {
                name: name,
                customer_type_id: '<?php echo (current_url() == base_url('Customer_Type/Update')) ? $CustomerTypeID : ''; ?>'
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

    $('input[type="button"]').click(function() {
        const swalWithBootstrapButtons = Swal.mixin({
            customClass: {
                confirmButton: 'btn btn-light-success m-2',
                cancelButton: 'btn btn-danger m-2'
            },
            buttonsStyling: true
        });
        swalWithBootstrapButtons.fire({
            width: 550,
            background: 'url(<?php echo base_url('assets/image/sweetalert.jpg') ?>)',
            icon: 'warning',
            title: <?php if(current_url() == base_url('Customer_Type/Create')) { ?> 'Create New Customer Type Record ?' <?php } else { ?> '<?php echo 'Update Customer Type Record : ' . str_replace('\'', '', $Name) . ' ?'; ?>' <?php } ?>,
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel',
            showCancelButton: true
        }).then((action) => {
            if(action.isConfirmed) {
                var name = ($('#Name').val()).toUpperCase();
                if(name == '') {
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert All Required Customer Type Information', null);
                } else {
                    if(window.location.href == '<?php echo base_url('Customer_Type/Create'); ?>') {
                        var customer_type = [];
                        customer_type.push({Name:name, InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});
                        Submit_Customer_Type('<?php echo base_url('Customer_Type/Create') ?>', customer_type);
                    } else {
                        var customer_type = [{CustomerTypeID:<?php echo $CustomerTypeID ?>, UpdateBy:<?php echo $this->session->userdata('admin_id') ?>, UpdateDate:'<?php echo date('Y-m-d H:i:s') ?>'}];
                        var dirty_field = $('#form').dirty('showDirtyFields');
                        if(dirty_field.length == 1) {
                            var key = dirty_field[0].id;
                            var value = (dirty_field[0].value).toUpperCase();
                            customer_type[0][key] = value;
                        }
                        count = 0;
                        $.each(customer_type[0], function() {
                            count++;
                        });
                        if(count == 3) {
                            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php echo 'No Changes Detected In Customer Type Record : ' . str_replace('\'', '', $Name); ?>', '<?php echo base_url('Customer_Type') ?>');
                        } else {
                            Submit_Customer_Type('<?php echo base_url('Customer_Type/Update') ?>', customer_type);
                        }
                    }
                }
            }
        });
    });

    function Submit_Customer_Type(url, customer_type)
    {
        $.ajax({
            url: url,
            type: 'post',
            data: {
                customer_type: customer_type
            },
            success: function() {
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php if(current_url() == base_url('Customer_Type/Create')) { echo 'New'; } ?> Customer Type Record <?php if(current_url() == base_url('Customer_Type/Update')) { echo ': ' . str_replace('\'', '', $Name); } ?> Successfully <?php if(current_url() == base_url('Customer_Type/Create')) { echo 'Created'; } else { echo 'Updated'; } ?>', '<?php echo base_url('Customer_Type') ?>');
            },
            error: function() {
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php if(current_url() == base_url('Customer_Type/Create')) { echo 'New'; } ?> Customer Type Record <?php if(current_url() == base_url('Customer_Type/Update')) { echo ': ' . str_replace('\'', '', $Name); } ?> Could Not Be <?php if(current_url() == base_url('Customer_Type/Create')) { echo 'Created'; } else { echo 'Updated'; } ?>', null);
            }
        });
    }

    $('#form').dirty('isClean');
</script>
