<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong><?php if(current_url() == base_url('Team/Create')) { echo 'New Team Record'; } else { echo 'Team Record : ' . $Name; } ?></strong>
                    </h3>
                </div>
            </div>
            <div class="card-body">
                <form id="form">
                    <strong>Team Information :</strong>
                    <br><br>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Name
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="text" id="Name" <?php if(current_url() == base_url('Team/Update')) { ?> value="<?php echo $Name; ?>" <?php } ?> autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-users"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-5">
                        <input type="button" value="<?php if(current_url() == base_url('Team/Create')) { echo 'Create Team'; } else { echo 'Update Team'; } ?>" class="btn btn-success font-weight-bold px-9 py-4" style="width:230px; margin-left:auto;">
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
            url: '<?php echo base_url('Team/Detect') ?>',
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
            title: <?php if(current_url() == base_url('Team/Create')) { ?> 'Create New Team Record ?' <?php } else { ?> '<?php echo 'Update Team Record : ' . str_replace('\'', '', $Name) . ' ?'; ?>' <?php } ?>,
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel',
            showCancelButton: true
        }).then((action) => {
            if(action.isConfirmed) {
                var name = ($('#Name').val()).toUpperCase();
                if(name == '') {
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert All Required Team Information', null);
                } else {
                    if(window.location.href == '<?php echo base_url('Team/Create'); ?>') {
                        var team = [];
                        team.push({Name:name, InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});
                        Submit_Team('<?php echo base_url('Team/Create') ?>', team);
                    } else {
                        var team = [{TeamID:<?php echo $TeamID ?>, UpdateBy:<?php echo $this->session->userdata('admin_id') ?>, UpdateDate:'<?php echo date('Y-m-d H:i:s') ?>'}];
                        var dirty_fields = $('#form').dirty('showDirtyFields');
                        if(dirty_fields.length > 0) {
                            for(var i = 0; i < dirty_fields.length; i++) {
                                var key = dirty_fields[i].id;
                                var value = (dirty_fields[i].value).toUpperCase();
                                team[0][key] = value;
                            }
                        }
                        count = 0;
                        $.each(team[0], function() {
                            count++;
                        });
                        if(count == 3) {
                            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php echo 'No Changes Detected In Team Record : ' . str_replace('\'', '', $Name); ?>', '<?php echo base_url('Team') ?>');
                        } else {
                            Submit_Team('<?php echo base_url('Team/Update') ?>', team);
                        }
                    }
                }
            }
        });
    });

    function Submit_Team(url, team)
    {
        $.ajax({
            url: url,
            type: 'post',
            data: {
                team: team
            },
            success: function() {
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php if(current_url() == base_url('Team/Create')) { echo 'New'; } ?> Team Record <?php if(current_url() == base_url('Team/Update')) { echo ': ' . str_replace('\'', '', $Name); } ?> Successfully <?php if(current_url() == base_url('Team/Create')) { echo 'Created'; } else { echo 'Updated'; } ?>', '<?php echo base_url('Team') ?>');
            },
            error: function() {
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php if(current_url() == base_url('Team/Create')) { echo 'New'; } ?> Team Record <?php if(current_url() == base_url('Team/Update')) { echo ': ' . str_replace('\'', '', $Name); } ?> Could Not Be <?php if(current_url() == base_url('Team/Create')) { echo 'Created'; } else { echo 'Updated'; } ?>', null);
            }
        });
    }

    $('#form').dirty('isClean');
</script>
