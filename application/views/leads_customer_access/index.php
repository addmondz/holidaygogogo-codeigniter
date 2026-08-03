<?php
    $level_names = unserialize(LEVEL);
    $module_keys = array_keys($modules);
?>
<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Leads/Customer Access</strong>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <button type="button" id="save_access" class="btn btn-primary font-weight-bold" style="width:160px;">
                        <i class="la la-save"></i>Save Changes
                    </button>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted" style="font-size:13px;">
                    Choose who can <strong>view</strong> (open the page) and <strong>edit</strong>
                    (add/change/delete records) each page. The Owner always has full access, so is not
                    listed here. Ticking <strong>Edit</strong> automatically grants <strong>View</strong>.
                </p>
                <div class="dataTables_wrapper dt-bootstrap4 no-footer" style="overflow-x:auto;">
                    <table class="table table-bordered table-head-custom table-checkable" style="min-width:900px;">
                        <thead>
                            <tr>
                                <th rowspan="2" style="text-align:center; vertical-align:middle;">Staff</th>
                                <?php foreach($modules as $m) { ?>
                                    <th colspan="2" style="text-align:center;"><?php echo $m['label']; ?></th>
                                <?php } ?>
                            </tr>
                            <tr>
                                <?php foreach($modules as $m) { ?>
                                    <th style="text-align:center; font-weight:500;">View</th>
                                    <th style="text-align:center; font-weight:500;">Edit</th>
                                <?php } ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($admins)) { ?>
                                <tr><td colspan="<?php echo 1 + count($modules) * 2; ?>" style="text-align:center; padding:15px;">No staff found.</td></tr>
                            <?php } else { ?>
                                <?php foreach($admins as $a) { ?>
                                    <?php $aid = (int) $a->AdminID; ?>
                                    <tr>
                                        <td style="padding-top:14px; padding-bottom:14px;">
                                            <span class="font-weight-bold"><?php echo htmlspecialchars($a->Name, ENT_QUOTES); ?></span>
                                            <span class="label label-light-primary label-inline ml-2" style="font-size:11px;"><?php echo isset($level_names[$a->Level]) ? $level_names[$a->Level] : $a->Level; ?></span>
                                        </td>
                                        <?php foreach($module_keys as $mk) {
                                            $can_view = isset($a->access[$mk]) ? (int) $a->access[$mk]->CanView : 0;
                                            $can_edit = isset($a->access[$mk]) ? (int) $a->access[$mk]->CanEdit : 0;
                                        ?>
                                            <td style="text-align:center; vertical-align:middle;">
                                                <label class="checkbox checkbox-lg justify-content-center mb-0">
                                                    <input type="checkbox" class="lc-view" data-aid="<?php echo $aid; ?>" data-mod="<?php echo $mk; ?>" <?php if($can_view || $can_edit) echo 'checked'; ?>>
                                                    <span></span>
                                                </label>
                                            </td>
                                            <td style="text-align:center; vertical-align:middle;">
                                                <label class="checkbox checkbox-lg justify-content-center mb-0">
                                                    <input type="checkbox" class="lc-edit" data-aid="<?php echo $aid; ?>" data-mod="<?php echo $mk; ?>" <?php if($can_edit) echo 'checked'; ?>>
                                                    <span></span>
                                                </label>
                                            </td>
                                        <?php } ?>
                                    </tr>
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
    // Edit implies View: ticking Edit auto-ticks its row/module View; unticking
    // View auto-unticks the matching Edit.
    $(document).on('change', '.lc-edit', function() {
        if($(this).is(':checked')) {
            var aid = $(this).data('aid'), mod = $(this).data('mod');
            $('.lc-view[data-aid="' + aid + '"][data-mod="' + mod + '"]').prop('checked', true);
        }
    });
    $(document).on('change', '.lc-view', function() {
        if(!$(this).is(':checked')) {
            var aid = $(this).data('aid'), mod = $(this).data('mod');
            $('.lc-edit[data-aid="' + aid + '"][data-mod="' + mod + '"]').prop('checked', false);
        }
    });

    $('#save_access').click(function() {
        var access = {};
        $('.lc-view:checked').each(function() {
            var aid = $(this).data('aid'), mod = $(this).data('mod');
            access[aid] = access[aid] || {};
            access[aid][mod] = access[aid][mod] || {};
            access[aid][mod]['view'] = 1;
        });
        $('.lc-edit:checked').each(function() {
            var aid = $(this).data('aid'), mod = $(this).data('mod');
            access[aid] = access[aid] || {};
            access[aid][mod] = access[aid][mod] || {};
            access[aid][mod]['edit'] = 1;
        });

        $.ajax({
            url: '<?php echo base_url('Leads_Customer_Access/Save'); ?>',
            type: 'post',
            data: { access: access },
            success: function() {
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Access Updated Successfully', '<?php echo base_url('Leads_Customer_Access') ?>');
            },
            error: function() {
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Access Could Not Be Updated', null);
            }
        });
    });
</script>
