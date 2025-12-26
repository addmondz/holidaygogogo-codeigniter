<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Package Checklist Records</strong>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('Package_Checklist/Create'); ?>" class="btn btn-primary font-weight-bold" style="width:180px;">
                        <i class="la la-check-square"></i>Create
                    </a>
                    <?php $current_url = base_url($_SERVER['REQUEST_URI']); ?>
                </div>
            </div>
            <div class="card-body">
                <div class="accordion accordion-solid accordion-toggle-plus">
                    <div class="card">
                        <div class="card-header">
                            <div id="package_checklist_header" data-toggle="collapse" data-target="#package_checklist_info" class="card-title collapsed" style="font-size:13px;">Filter By Package Checklist Information</div>
                        </div>
                        <div id="package_checklist_info" class="collapse">
                            <div class="card-body">
                                <form action="<?php echo base_url('Package_Checklist') ?>" method="get" class="form">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Name</label>
                                                <div class="input-icon">
                                                    <input type="text" name="name" value="<?php if(!empty($this->input->get('name'))) { echo htmlspecialchars($this->input->get('name')); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-check-square"></i>
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
                <div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if(empty($package_checklists)) { echo 'style="overflow-x:auto;"'; } ?>>
                    <table id="kt_datatable" class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline">
                        <thead>
                            <tr>
                                <th style="text-align:center;">No.</th>
                                <th style="text-align:center;">Name</th>
                                <th style="text-align:center;">Is Required</th>
                                <th style="text-align:center;">Created At</th>
                                <th style="text-align:center;">Updated At</th>
                                <th class="action" style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($package_checklists)) { ?>
                                <td colspan="6" style="text-align:center; padding-top:10px; padding-bottom:10px;">Package Checklist Records Not Found</td>
                            <?php } else { ?>
                                <?php $count = 1; ?>
                                <?php foreach($package_checklists as $package_checklist) { ?>
                                    <tr>
                                        <td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
                                        <td style="text-align:center;"><?php echo htmlspecialchars($package_checklist->name); ?></td>
                                        <td style="text-align:center;">
                                            <?php if($package_checklist->is_required == 1) { ?>
                                                <span class="label label-lg label-light-success label-inline">Yes</span>
                                            <?php } else { ?>
                                                <span class="label label-lg label-light-secondary label-inline" style="color:#6c757d;">No</span>
                                            <?php } ?>
                                        </td>
                                        <td style="text-align:center;"><?php echo !empty($package_checklist->created_at) ? date('d/m/Y H:i', strtotime($package_checklist->created_at)) : 'N/A'; ?></td>
                                        <td style="text-align:center;"><?php echo !empty($package_checklist->updated_at) ? date('d/m/Y H:i', strtotime($package_checklist->updated_at)) : 'N/A'; ?></td>
                                        <td style="text-align:center;">
                                            <?php if($package_checklist->ID == 1) { ?>
                                                <!-- <span class="label label-lg label-light-info label-inline">Protected</span> -->
                                            <?php } else { ?>
                                                <div class="btn-group">
                                                    <button type="button" data-toggle="dropdown" class="btn btn-light-primary btn-sm dropdown-toggle" style="padding-left:3px;"></button>
                                                    <div class="dropdown-menu">
                                                        <a href="<?php echo base_url('Package_Checklist/Update?package_checklist_id=') . $package_checklist->ID; ?>" class="dropdown-item" style="font-size:11px;">Update</a>
                                                        <button onclick="Delete_Record('<?php echo base_url('assets/image/sweetalert.jpg'); ?>', '<?php echo 'Package Checklist Record : ' . str_replace('\'', '', htmlspecialchars($package_checklist->name)); ?>', '<?php echo base_url('Package_Checklist/Delete'); ?>', 'package_checklist_id', <?php echo $package_checklist->ID; ?>, 'Y', '<?php if(strpos($current_url, '?') == true) { echo base_url('Package_Checklist?') . (explode('?', $current_url))[1]; } else { echo base_url('Package_Checklist'); } ?>')" class="dropdown-item" style="color:#E37383; font-size:11px;">Delete</button>
                                                    </div>
                                                </div>
                                            <?php } ?>
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
        $('#package_checklist_header').click();
    <?php } ?>

    $('#reset').click(function() {
        Reset('<?php echo base_url('Package_Checklist'); ?>');
    });
</script>

