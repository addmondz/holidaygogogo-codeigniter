<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong><?php if(current_url() == base_url('Source/Create')) { echo 'New Source Record'; } else { echo 'Source Record : ' . $Name; } ?></strong>
                    </h3>
                </div>
            </div>
            <div class="card-body">
                <form id="form">
                    <strong>Source Information :</strong>
                    <br><br>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Name
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="text" id="Name" <?php if(current_url() == base_url('Source/Update')) { ?> value="<?php echo $Name; ?>" <?php } ?> autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-clipboard-list"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-5">
                        <input type="button" value="<?php if(current_url() == base_url('Source/Create')) { echo 'Create Source'; } else { echo 'Update Source'; } ?>" class="btn btn-success font-weight-bold px-9 py-4" style="width:180px; margin-left:auto;">
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
            url: '<?php echo base_url('Source/Detect') ?>',
            type: 'post',
            data: {
                name: name
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
            title: <?php if(current_url() == base_url('Source/Create')) { ?> 'Create New Source Record ?' <?php } else { ?> '<?php echo 'Update Source Record : ' . str_replace('\'', '', $Name) . ' ?'; ?>' <?php } ?>,
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel',
            showCancelButton: true
        }).then((action) => {
            if(action.isConfirmed) {
                var name = ($('#Name').val()).toUpperCase();
                if(name == '') {
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert All Required Source Information', null);
                } else {
                    if(window.location.href == '<?php echo base_url('Source/Create'); ?>') {
                        var source = [];
                        source.push({Name:name, InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});
                        Submit_Source('<?php echo base_url('Source/Create') ?>', source);
                    } else {
                        var source = [{SourceID:<?php echo $SourceID ?>, UpdateBy:<?php echo $this->session->userdata('admin_id') ?>, UpdateDate:'<?php echo date('Y-m-d H:i:s') ?>'}];
                        var dirty_field = $('#form').dirty('showDirtyFields');
                        if(dirty_field.length == 1) {
                            var key = dirty_field[0].id;
                            var value = (dirty_field[0].value).toUpperCase();
                            source[0][key] = value;
                        }
                        count = 0;
                        $.each(source[0], function() {
                            count++;
                        });
                        if(count == 3) {
                            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php echo 'No Changes Detected In Source Record : ' . str_replace('\'', '', $Name); ?>', '<?php echo base_url('Source') ?>');
                        } else {
                            Submit_Source('<?php echo base_url('Source/Update') ?>', source);
                        }
                    }
                }
            }
        });
    });

    function Submit_Source(url, source)
    {
        $.ajax({
            url: url,
            type: 'post',
            data: {
                source: source
            },
            success: function() {
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php if(current_url() == base_url('Source/Create')) { echo 'New'; } ?> Source Record <?php if(current_url() == base_url('Source/Update')) { echo ': ' . str_replace('\'', '', $Name); } ?> Successfully <?php if(current_url() == base_url('Source/Create')) { echo 'Created'; } else { echo 'Updated'; } ?>', '<?php echo base_url('Source') ?>');
            },
            error: function() {
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php if(current_url() == base_url('Source/Create')) { echo 'New'; } ?> Source Record <?php if(current_url() == base_url('Source/Update')) { echo ': ' . str_replace('\'', '', $Name); } ?> Could Not Be <?php if(current_url() == base_url('Source/Create')) { echo 'Created'; } else { echo 'Updated'; } ?>', null);
            }
        });
    }
    
    $('#form').dirty('isClean');
</script>