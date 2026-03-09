<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Source Records</strong>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('Source/Create'); ?>" class="btn btn-primary font-weight-bold" style="width:180px;">
                        <i class="la la-clipboard-list"></i>Create Source
                    </a>
                    <?php $current_url = base_url($_SERVER['REQUEST_URI']); ?>
                </div>
            </div>
            <div class="card-body">
                <div class="accordion accordion-solid accordion-toggle-plus">
                    <div class="card">
                        <div class="card-header">
                            <div id="source_header" data-toggle="collapse" data-target="#source_info" class="card-title collapsed" style="font-size:13px;">Filter By Source Information</div>
                        </div>
                        <div id="source_info" class="collapse">
                            <div class="card-body">
                                <form action="<?php echo base_url('Source') ?>" method="get" class="form">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Name</label>
                                                <div class="input-icon">
                                                    <input type="text" name="name" value="<?php if(!empty($this->input->get('name'))) { echo strtoupper($this->input->get('name')); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-clipboard-list"></i>
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
                <div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if(empty($sources)) { echo 'style="overflow-x:auto;"'; } ?>>
                    <table id="kt_datatable" class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline">
                        <thead>
                            <tr>
                                <th style="text-align:center;">No.</th>
                                <th style="text-align:center;">Name</th>
                                <th style="text-align:center;">Status</th>
                                <th class="action" style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($sources)) { ?>
                                <td colspan="4" style="text-align:center; padding-top:10px; padding-bottom:10px;">Source Records Not Found</td>
                            <?php } else { ?>
                                <?php $count = 1; ?>
                                <?php foreach($sources as $source) { ?>
                                    <tr>
                                        <td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
                                        <td style="text-align:center;"><?php echo $source->Name; ?></td>
                                        <td style="text-align:center;">
                                            <?php if($source->Status == 'Y') { ?>
                                                <span class="label label-lg label-light-success label-inline font-weight-bold">Active</span>
                                            <?php } else { ?>
                                                <span class="label label-lg label-light-danger label-inline font-weight-bold">Inactive</span>
                                            <?php } ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <div class="btn-group">
                                                <button type="button" data-toggle="dropdown" class="btn btn-light-primary btn-sm dropdown-toggle" style="padding-left:3px;"></button>
                                                <div class="dropdown-menu">
                                                    <button onclick="Toggle_Status(<?php echo $source->SourceID; ?>, '<?php echo str_replace('\'', '', $source->Name); ?>', '<?php echo $source->Status; ?>')" class="dropdown-item" style="color:<?php echo ($source->Status == 'Y') ? '#E37383' : '#50CD89'; ?>; font-size:11px;"><?php echo ($source->Status == 'Y') ? 'Deactivate Source' : 'Activate Source'; ?></button>
                                                    <a href="<?php echo base_url('Source/Update?source_id=') . $source->SourceID; ?>" class="dropdown-item" style="font-size:11px;">Update Source</a>
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
    <?php if(!empty($this->input->get('name'))) { ?>
        $('#source_header').click();
    <?php } ?>

    $('#reset').click(function() {
        Reset('<?php echo base_url('Source'); ?>');
    });

    function Toggle_Status(source_id, name, current_status) {
        var action = (current_status == 'Y') ? 'Deactivate' : 'Activate';
        var swalWithBootstrapButtons = Swal.mixin({
            customClass: {
                confirmButton: 'btn btn-light-success m-2',
                cancelButton: 'btn btn-danger m-2'
            },
            buttonsStyling: true
        });
        swalWithBootstrapButtons.fire({
            width: 550,
            background: "url(<?php echo base_url('assets/image/sweetalert.jpg'); ?>)",
            icon: 'warning',
            title: action + ' Source Record : ' + name + ' ?',
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel',
            showCancelButton: true
        }).then(function(action_result) {
            if(action_result.isConfirmed) {
                $.ajax({
                    url: '<?php echo base_url('Source/Toggle_Status'); ?>',
                    type: 'get',
                    data: { source_id: source_id },
                    timeout: 2000,
                    success: function() {
                        Display_Message("<?php echo base_url('assets/image/sweetalert.jpg'); ?>", 'Source Record : ' + name + ' Successfully ' + action + 'd', '<?php if(strpos($current_url, '?') == true) { echo base_url('Source?') . (explode('?', $current_url))[1]; } else { echo base_url('Source'); } ?>');
                    },
                    error: function() {
                        Display_Message("<?php echo base_url('assets/image/sweetalert.jpg'); ?>", 'Error. Please Try Again', '<?php echo base_url('Source'); ?>');
                    }
                });
            }
        });
    }
</script>