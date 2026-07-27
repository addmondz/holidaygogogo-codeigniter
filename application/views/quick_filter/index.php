<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Quick Filter Records</strong>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('Quick_Filter/Create'); ?>" class="btn btn-primary font-weight-bold" style="width:200px;">
                        <i class="la la-filter"></i>Create Quick Filter
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="accordion accordion-solid accordion-toggle-plus">
                    <div class="card">
                        <div class="card-header">
                            <div id="quick_filter_header" data-toggle="collapse" data-target="#quick_filter_info" class="card-title collapsed" style="font-size:13px;">Filter By Quick Filter Information</div>
                        </div>
                        <div id="quick_filter_info" class="collapse">
                            <div class="card-body">
                                <form action="<?php echo base_url('Quick_Filter') ?>" method="get" class="form">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Name</label>
                                                <div class="input-icon">
                                                    <input type="text" name="name" value="<?php if(!empty($this->input->get('name'))) { echo $this->input->get('name'); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-filter"></i>
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
                <div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if(empty($quick_filters)) { echo 'style="overflow-x:auto;"'; } ?>>
                    <table id="kt_datatable" class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline">
                        <thead>
                            <tr>
                                <th style="text-align:center;">No.</th>
                                <th style="text-align:center;">Name</th>
                                <th style="text-align:center;">Active Filters</th>
                                <th class="action" style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($quick_filters)) { ?>
                                <td colspan="4" style="text-align:center; padding-top:10px; padding-bottom:10px;">Quick Filter Records Not Found</td>
                            <?php } else { ?>
                                <?php $count = 1; ?>
                                <?php foreach($quick_filters as $qf) {
                                    $decoded = json_decode($qf->FilterData, true);
                                    $active = is_array($decoded) ? count(array_filter($decoded, function($v) { return $v !== '' && $v !== null; })) : 0;
                                ?>
                                    <tr>
                                        <td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
                                        <td style="text-align:center;"><?php echo htmlspecialchars($qf->Name); ?></td>
                                        <td style="text-align:center;"><?php echo $active; ?></td>
                                        <td style="text-align:center;">
                                            <div class="btn-group">
                                                <button type="button" data-toggle="dropdown" class="btn btn-light-primary btn-sm dropdown-toggle" style="padding-left:3px;"></button>
                                                <div class="dropdown-menu">
                                                    <a href="<?php echo base_url('Quick_Filter/Update?quick_filter_id=') . $qf->QuickFilterID; ?>" class="dropdown-item" style="font-size:11px;">Update Quick Filter</a>
                                                    <a href="javascript:;" class="dropdown-item deactivate-qf" data-id="<?php echo $qf->QuickFilterID; ?>" data-name="<?php echo htmlspecialchars($qf->Name, ENT_QUOTES); ?>" style="font-size:11px;">Delete Quick Filter</a>
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
        $('#quick_filter_header').click();
    <?php } ?>

    $('#reset').click(function() {
        Reset('<?php echo base_url('Quick_Filter'); ?>');
    });

    $('.deactivate-qf').click(function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
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
            title: 'Delete Quick Filter : ' + name + ' ?',
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel',
            showCancelButton: true
        }).then((action) => {
            if(action.isConfirmed) {
                var payload = [{
                    QuickFilterID: id,
                    Status: 'N',
                    UpdateBy: <?php echo $this->session->userdata('admin_id') ?>,
                    UpdateDate: '<?php echo date('Y-m-d H:i:s') ?>'
                }];
                $.ajax({
                    url: '<?php echo base_url('Quick_Filter/Update') ?>',
                    type: 'post',
                    data: { quick_filter: payload },
                    success: function() {
                        Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Quick Filter : ' + name + ' Successfully Deleted', '<?php echo base_url('Quick_Filter') ?>');
                    },
                    error: function() {
                        Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Quick Filter : ' + name + ' Could Not Be Deleted', null);
                    }
                });
            }
        });
    });
</script>
