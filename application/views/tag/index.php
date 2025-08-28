<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Tag Records</strong>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('Tag/Create'); ?>" class="btn btn-primary font-weight-bold" style="width:180px;">
                        <i class="la la-tags"></i>Create Tag
                    </a>
                    <?php $current_url = base_url($_SERVER['REQUEST_URI']); ?>
                </div>
            </div>
            <div class="card-body">
                <div class="accordion accordion-solid accordion-toggle-plus">
                    <div class="card">
                        <div class="card-header">
                            <div id="tag_header" data-toggle="collapse" data-target="#tag_info" class="card-title collapsed" style="font-size:13px;">Filter By Tag Information</div>
                        </div>
                        <div id="tag_info" class="collapse">
                            <div class="card-body">
                                <form action="<?php echo base_url('Tag') ?>" method="get" class="form">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Name</label>
                                                <div class="input-icon">
                                                    <input type="text" name="name" value="<?php if(!empty($this->input->get('name'))) { echo strtoupper($this->input->get('name')); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-tags"></i>
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
                <div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if(empty($tags)) { echo 'style="overflow-x:auto;"'; } ?>>
                    <table id="kt_datatable" class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline">
                        <thead>
                            <tr>
                                <th style="text-align:center;">No.</th>
                                <th style="text-align:center;">Name</th>
                                <th class="action" style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($tags)) { ?>
                                <td colspan="3" style="text-align:center; padding-top:10px; padding-bottom:10px;">Tag Records Not Found</td>
                            <?php } else { ?>
                                <?php $count = 1; ?>
                                <?php foreach($tags as $tag) { ?>
                                    <tr>
                                        <td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
                                        <td style="text-align:center;"><?php echo $tag->Name; ?></td>
                                        <td style="text-align:center;">
                                            <div class="btn-group">
                                                <button type="button" data-toggle="dropdown" class="btn btn-light-primary btn-sm dropdown-toggle" style="padding-left:3px;"></button>
                                                <div class="dropdown-menu">
                                                    <button onclick="Delete_Record('<?php echo base_url('assets/image/sweetalert.jpg'); ?>', '<?php echo 'Tag Record : ' . str_replace('\'', '', $tag->Name); ?>', '<?php echo base_url('Tag/Delete'); ?>', 'tag_id', <?php echo $tag->TagID; ?>, '<?php echo $tag->Status; ?>', '<?php if(strpos($current_url, '?') == true) { echo base_url('Tag?') . (explode('?', $current_url))[1]; } else { echo base_url('Tag'); } ?>')" class="dropdown-item" style="color:#E37383; font-size:11px;">Delete Tag</button>
                                                    <a href="<?php echo base_url('Tag/Update?tag_id=') . $tag->TagID; ?>" class="dropdown-item" style="font-size:11px;">Update Tag</a>
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
        $('#tag_header').click();
    <?php } ?>

    $('#reset').click(function() {
        Reset('<?php echo base_url('Tag'); ?>');
    });
</script>