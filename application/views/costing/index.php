<?php
$packages = isset($packages) ? $packages : array();
?>

<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Costing Package Records</strong>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('Costing/Create'); ?>" class="btn btn-primary font-weight-bold mr-2">
                        <i class="la la-plus"></i>Create Package
                    </a>
                    <a href="<?php echo base_url('Costing/Currency'); ?>" class="btn btn-light-primary font-weight-bold">
                        <i class="la la-coins"></i>Currency Setup
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if ($this->session->flashdata('message_success')) { ?>
                    <div class="alert alert-success"><?php echo html_escape($this->session->flashdata('message_success')); ?></div>
                <?php } ?>
                <?php if ($this->session->flashdata('message_error')) { ?>
                    <div class="alert alert-danger"><?php echo html_escape($this->session->flashdata('message_error')); ?></div>
                <?php } ?>

                <div class="mb-6">
                    <div class="font-size-h5 font-weight-bold text-dark mb-2">Packages</div>
                    <div class="text-muted">Show package records only. Open a package to manage costing breakdown, booking snapshots, and calculations.</div>
                </div>

                <div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if (empty($packages)) { echo 'style="overflow-x:auto;"'; } ?>>
                    <table class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline">
                        <thead>
                            <tr>
                                <th style="text-align:center;">No.</th>
                                <th style="text-align:center;">Package Name</th>
                                <th style="text-align:center;">Duration</th>
                                <th style="text-align:center;">Status</th>
                                <th style="text-align:center;">Updated At</th>
                                <th class="action" style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($packages)) { ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding-top:10px; padding-bottom:10px;">Costing Package Records Not Found</td>
                                </tr>
                            <?php } else { ?>
                                <?php $count = 1; ?>
                                <?php foreach ($packages as $package) { ?>
                                    <tr>
                                        <td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
                                        <td>
                                            <div class="font-weight-bold"><?php echo html_escape($package['name']); ?></div>
                                            <div class="text-muted"><?php echo html_escape($package['description']); ?></div>
                                        </td>
                                        <td style="text-align:center;"><?php echo (int) $package['duration_days']; ?>D / <?php echo (int) $package['duration_nights']; ?>N</td>
                                        <td style="text-align:center;">
                                            <span class="label label-lg label-light-primary label-inline"><?php echo html_escape($package['status_label']); ?></span>
                                        </td>
                                        <td style="text-align:center;"><?php echo !empty($package['updated_at']) ? date('d/m/Y H:i', strtotime($package['updated_at'])) : 'N/A'; ?></td>
                                        <td style="text-align:center;">
                                            <a href="<?php echo base_url('Costing/Package/' . (int) $package['id']); ?>" class="btn btn-icon btn-light-primary btn-sm mr-2" title="View Package">
                                                <i class="la la-eye"></i>
                                            </a>
                                            <?php if (!empty($package['booking_count'])) { ?>
                                                <a href="<?php echo base_url('Costing/Quotation/' . (int) $package['id']); ?>" target="_blank" class="btn btn-icon btn-light-success btn-sm mr-2" title="Open Quotation PDF">
                                                    <i class="la la-file-pdf"></i>
                                                </a>
                                            <?php } ?>
                                            <form method="post" action="<?php echo base_url('Costing/Delete_Package'); ?>" class="d-inline delete-package-form">
                                                <input type="hidden" name="package_id" value="<?php echo (int) $package['id']; ?>">
                                                <button type="button" class="btn btn-icon btn-light-danger btn-sm delete-package-button" data-name="<?php echo html_escape($package['name']); ?>" title="Delete Package">
                                                    <i class="la la-trash"></i>
                                                </button>
                                            </form>
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
    (function () {
        document.querySelectorAll('.delete-package-button').forEach(function (button) {
            button.addEventListener('click', function () {
                var form = button.closest('form');
                var packageName = button.getAttribute('data-name') || 'this package';

                if (typeof Swal === 'undefined') {
                    if (confirm('Delete package: ' + packageName + '?')) {
                        form.submit();
                    }
                    return;
                }

                var swalWithBootstrapButtons = Swal.mixin({
                    customClass: {
                        confirmButton: 'btn btn-danger',
                        cancelButton: 'btn btn-light-primary'
                    },
                    buttonsStyling: false
                });

                swalWithBootstrapButtons.fire({
                    title: 'Delete Package: ' + packageName + '?',
                    text: 'This will delete the package and all related costing records.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Delete',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true
                }).then(function (result) {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    })();
</script>
