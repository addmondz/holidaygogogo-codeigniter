<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Team Records</strong>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('Team/Create'); ?>" class="btn btn-primary font-weight-bold" style="width:230px;">
                        <i class="la la-users"></i>Create Team
                    </a>
                    <?php $current_url = base_url($_SERVER['REQUEST_URI']); ?>
                </div>
            </div>
            <div class="card-body">
                <div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if(empty($teams)) { echo 'style="overflow-x:auto;"'; } ?>>
                    <table id="kt_datatable" class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline">
                        <thead>
                            <tr>
                                <th style="text-align:center;">No.</th>
                                <th style="text-align:center;">Name</th>
                                <th class="action" style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($teams)) { ?>
                                <td colspan="3" style="text-align:center; padding-top:10px; padding-bottom:10px;">Team Records Not Found</td>
                            <?php } else { ?>
                                <?php $count = 1; ?>
                                <?php foreach($teams as $team) { ?>
                                    <tr>
                                        <td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
                                        <td style="text-align:center;"><?php echo $team->Name; ?></td>
                                        <td style="text-align:center;">
                                            <div class="btn-group">
                                                <button type="button" data-toggle="dropdown" class="btn btn-light-primary btn-sm dropdown-toggle" style="padding-left:3px;"></button>
                                                <div class="dropdown-menu">
                                                    <button onclick="Delete_Record('<?php echo base_url('assets/image/sweetalert.jpg'); ?>', '<?php echo 'Team Record : ' . str_replace('\'', '', $team->Name); ?>', '<?php echo base_url('Team/Delete'); ?>', 'team_id', <?php echo $team->TeamID; ?>, '<?php echo $team->Status; ?>', '<?php echo base_url('Team'); ?>')" class="dropdown-item" style="color:#E37383; font-size:11px;">Delete Team</button>
                                                    <a href="<?php echo base_url('Team/Update?team_id=') . $team->TeamID; ?>" class="dropdown-item" style="font-size:11px;">Update Team</a>
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
