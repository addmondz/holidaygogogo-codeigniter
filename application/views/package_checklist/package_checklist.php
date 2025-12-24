<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong><?php if(current_url() == base_url('Package_Checklist/Create')) { echo 'New Package Checklist Record'; } else { echo 'Package Checklist Record : ' . htmlspecialchars($name); } ?></strong>
                    </h3>
                </div>
            </div>
            <div class="card-body">
                <?php if(current_url() == base_url('Package_Checklist/Update') && isset($ID) && $ID == 1) { ?>
                    <div class="alert alert-warning" role="alert">
                        <strong>Protected Record:</strong> This package checklist (ID: 1) cannot be edited or deleted.
                    </div>
                    <a href="<?php echo base_url('Package_Checklist'); ?>" class="btn btn-primary font-weight-bold">Back to List</a>
                <?php } else { ?>
                    <form id="form">
                        <strong>Package Checklist Information :</strong>
                        <br><br>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Name
                                        <span style="color:red;">*</span>
                                    </label>
                                    <div class="input-icon">
                                        <input type="text" id="name" <?php if(current_url() == base_url('Package_Checklist/Update')) { ?> value="<?php echo htmlspecialchars($name); ?>" <?php } ?> autocomplete="off" class="form-control">
                                        <span>
                                            <i class="la la-check-square"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Can Be Disabled
                                        <span style="color:red;">*</span>
                                    </label>
                                    <select id="can_be_disabled" class="form-control">
                                        <option value="1" <?php if(current_url() == base_url('Package_Checklist/Update') && isset($can_be_disabled) && $can_be_disabled == 1) { echo 'selected'; } elseif(current_url() == base_url('Package_Checklist/Create')) { echo 'selected'; } ?>>Yes</option>
                                        <option value="0" <?php if(current_url() == base_url('Package_Checklist/Update') && isset($can_be_disabled) && $can_be_disabled == 0) { echo 'selected'; } ?>>No</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between border-top pt-5">
                            <a class="btn btn-light-primary font-weight-bold d-flex align-items-center justify-content-center px-9 py-4" href="<?php echo base_url('Package_Checklist'); ?>"><i class="la la-arrow-left"></i> Back to List</a>
                            <input type="button" value="<?php if(current_url() == base_url('Package_Checklist/Create')) { echo 'Create'; } else { echo 'Update'; } ?>" class="btn btn-success font-weight-bold px-9 py-4" style="width:180px; margin-left:auto;">
                        </div>
                    </form>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<script>
    $('#name').change(function() {
        var name = ($('#name').val()).trim();
        if(name != '') {
            $.ajax({
                url: '<?php echo base_url('Package_Checklist/Detect') ?>' + (window.location.href.includes('Update') ? '?package_checklist_id=<?php echo isset($ID) ? $ID : ""; ?>' : ''),
                type: 'post',
                data: {
                    name: name
                },
                dataType: 'json',
                success: function(redundant_name) {
                    if(redundant_name) {
                        Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Redundant Name Detected', null);
                        $('#name').val('');
                    }
                }
            });
        }
    });

    $('input[type="button"]').click(function() {
        <?php if(current_url() == base_url('Package_Checklist/Update') && isset($ID) && $ID == 1) { ?>
            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'This package checklist (ID: 1) cannot be edited.', null);
            return;
        <?php } ?>
        
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
            title: <?php if(current_url() == base_url('Package_Checklist/Create')) { ?> 'Create New Package Checklist Record ?' <?php } else { ?> '<?php echo 'Update Package Checklist Record : ' . str_replace('\'', '', htmlspecialchars($name)) . ' ?'; ?>' <?php } ?>,
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel',
            showCancelButton: true
        }).then((action) => {
            if(action.isConfirmed) {
                var name = ($('#name').val()).trim();
                var can_be_disabled = $('#can_be_disabled').val();
                if(name == '') {
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert All Required Package Checklist Information', null);
                } else {
                    if(window.location.href == '<?php echo base_url('Package_Checklist/Create'); ?>') {
                        var package_checklist = [];
                        package_checklist.push({
                            name: name,
                            can_be_disabled: can_be_disabled,
                            InsertBy: <?php echo $this->session->userdata('admin_id') ?>,
                            InsertDate: '<?php echo date('Y-m-d H:i:s') ?>'
                        });
                        Submit_Package_Checklist('<?php echo base_url('Package_Checklist/Create') ?>', package_checklist);
                    } else {
                        var package_checklist = [{
                            ID: <?php echo $ID ?>,
                            UpdateBy: <?php echo $this->session->userdata('admin_id') ?>,
                            UpdateDate: '<?php echo date('Y-m-d H:i:s') ?>'
                        }];
                        var dirty_field = $('#form').dirty('showDirtyFields');
                        if(dirty_field.length > 0) {
                            $.each(dirty_field, function(index, field) {
                                var key = field.id;
                                var value = field.value;
                                if(key == 'name') {
                                    value = value.trim();
                                }
                                package_checklist[0][key] = value;
                            });
                        }
                        count = 0;
                        $.each(package_checklist[0], function() {
                            count++;
                        });
                        if(count == 3) {
                            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php echo 'No Changes Detected In Package Checklist Record : ' . str_replace('\'', '', htmlspecialchars($name)); ?>', '<?php echo base_url('Package_Checklist') ?>');
                        } else {
                            Submit_Package_Checklist('<?php echo base_url('Package_Checklist/Update') ?>', package_checklist);
                        }
                    }
                }
            }
        });
    });

    function Submit_Package_Checklist(url, package_checklist)
    {
        $.ajax({
            url: url,
            type: 'post',
            data: {
                package_checklist: package_checklist
            },
            success: function() {
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php if(current_url() == base_url('Package_Checklist/Create')) { echo 'New'; } ?> Package Checklist Record <?php if(current_url() == base_url('Package_Checklist/Update')) { echo ': ' . str_replace('\'', '', htmlspecialchars($name)); } ?> Successfully <?php if(current_url() == base_url('Package_Checklist/Create')) { echo 'Created'; } else { echo 'Updated'; } ?>', '<?php echo base_url('Package_Checklist') ?>');
            },
            error: function() {
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php if(current_url() == base_url('Package_Checklist/Create')) { echo 'New'; } ?> Package Checklist Record <?php if(current_url() == base_url('Package_Checklist/Update')) { echo ': ' . str_replace('\'', '', htmlspecialchars($name)); } ?> Could Not Be <?php if(current_url() == base_url('Package_Checklist/Create')) { echo 'Created'; } else { echo 'Updated'; } ?>', null);
            }
        });
    }
    
    $('#form').dirty('isClean');
</script>

