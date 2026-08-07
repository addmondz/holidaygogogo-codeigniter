<?php
/**
 * Owner-only Merge History. Lists past customer merges (customer_merge_log),
 * newest first, each with a GUARDED Undo — see Customer_Model::Revert_Merge().
 * Data comes from Customer_Model::Recent_Merges().
 */
?>
<div class="d-flex flex-column-fluid">
    <div class="container-fluid">

        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;"><strong>Merge History</strong></h3>
                </div>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('Merge_Duplicate_Customers'); ?>" class="btn btn-light-primary font-weight-bold">
                        <i class="la la-arrow-left"></i> Back to Merge
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted" style="font-size:13px;margin-bottom:0;">
                    <strong>Undo</strong> re-points each moved booking back to its original customer and reactivates
                    the merged records — but only bookings that <em>still</em> point to the keeper (anything edited
                    after the merge is left as-is and reported as skipped). Local only — AutoCount is not changed.
                </p>
            </div>
        </div>

        <div class="card card-custom">
            <div class="card-body">
                <?php if (empty($recent_merges)) { ?>
                    <div class="text-center text-muted" style="padding:30px;">No merges yet.</div>
                <?php } else { ?>
                    <table class="table table-bordered table-head-custom mb-0">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Kept</th>
                                <th style="text-align:center;">Records merged</th>
                                <th>By</th>
                                <th style="text-align:center;">Status</th>
                                <th style="text-align:center;width:120px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_merges as $m) { ?>
                                <tr id="merge_row_<?php echo (int) $m->MergeID; ?>">
                                    <td><?php echo $m->created_at ? date('d M Y H:i', strtotime($m->created_at)) : '—'; ?></td>
                                    <td>
                                        <span class="font-weight-bold"><?php echo htmlspecialchars($m->keeper_name, ENT_QUOTES); ?></span>
                                        <span class="text-muted">(<?php echo $m->keeper_code ? htmlspecialchars($m->keeper_code, ENT_QUOTES) : '—'; ?>)</span>
                                    </td>
                                    <td style="text-align:center;"><span class="label label-light-info label-inline"><?php echo (int) $m->loser_count; ?></span></td>
                                    <td><?php echo $m->admin_name ? htmlspecialchars($m->admin_name, ENT_QUOTES) : '—'; ?></td>
                                    <td style="text-align:center;">
                                        <?php if ($m->status === 'REVERTED') { ?>
                                            <span class="label label-light-warning label-inline">Reverted</span>
                                        <?php } else { ?>
                                            <span class="label label-light-success label-inline">Merged</span>
                                        <?php } ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if ($m->status === 'MERGED') { ?>
                                            <button type="button" class="btn btn-light-warning btn-sm font-weight-bold btn-revert"
                                                    data-merge="<?php echo (int) $m->MergeID; ?>">
                                                <i class="la la-undo"></i> Undo
                                            </button>
                                        <?php } else { ?>
                                            <span class="text-muted" style="font-size:12px;">
                                                <?php echo $m->reverted_at ? date('d M Y H:i', strtotime($m->reverted_at)) : ''; ?>
                                            </span>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } ?>
            </div>
        </div>

    </div>
</div>

<script>
    var REVERT_URL = '<?php echo base_url('Merge_Duplicate_Customers/ajax_revert'); ?>';
    var SWAL_IMG   = '<?php echo base_url('assets/image/sweetalert.jpg') ?>';

    $(document).on('click', '.btn-revert', function () {
        var mergeId = $(this).data('merge');
        var $row = $('#merge_row_' + mergeId);

        Swal.fire({
            icon: 'warning',
            title: 'Undo this merge?',
            html: 'Bookings that still point to the keeper will be re-pointed back and the merged records reactivated.<br><br>'
                + 'Anything edited since the merge is left unchanged.',
            showCancelButton: true,
            confirmButtonText: 'Yes, undo',
            cancelButtonText: 'Cancel',
            customClass: {
                confirmButton: 'btn btn-warning m-2',
                cancelButton: 'btn btn-secondary m-2'
            },
            buttonsStyling: true
        }).then(function (res) {
            if (!res.isConfirmed) { return; }
            $.ajax({
                url: REVERT_URL,
                type: 'post',
                data: { merge_id: mergeId },
                dataType: 'json'
            }).done(function (r) {
                if (r && r.success) {
                    $row.find('.btn-revert').remove();
                    $row.find('td').eq(4).html('<span class="label label-light-warning label-inline">Reverted</span>');
                    Swal.fire({
                        icon: 'success',
                        title: 'Reverted',
                        text: r.reverted + ' booking(s) restored, ' + r.reactivated + ' record(s) reactivated'
                            + (r.skipped ? ', ' + r.skipped + ' skipped (changed since merge)' : '') + '.',
                        timer: 2600, showConfirmButton: false
                    });
                } else {
                    Display_Message(SWAL_IMG, (r && r.message) ? r.message : 'Revert failed.', null);
                }
            }).fail(function () {
                Display_Message(SWAL_IMG, 'Revert failed (server error).', null);
            });
        });
    });
</script>
