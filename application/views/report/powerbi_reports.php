<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Power BI Reports</strong>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('Report/PowerBI'); ?>" class="btn btn-sm btn-primary font-weight-bold">
                        Create New Report
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($error)) { ?>
                    <div class="alert alert-custom alert-light-danger fade show" role="alert">
                        <div class="alert-text"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <p class="text-muted mb-2">Configure these values in your <code>.env</code> file:</p>
                    <ul class="text-muted mb-0">
                        <li><code>POWERBI_TENANT_ID</code></li>
                        <li><code>POWERBI_CLIENT_ID</code></li>
                        <li><code>POWERBI_CLIENT_SECRET</code></li>
                        <li><code>POWERBI_WORKSPACE_ID</code></li>
                        <li><code>POWERBI_DATASET_ID</code> (needed for Builder)</li>
                    </ul>
                <?php } elseif (empty($reports)) { ?>
                    <div class="alert alert-custom alert-light-warning fade show mb-0" role="alert">
                        <div class="alert-text">
                            No reports found in this Power BI workspace yet.
                            Use <a href="<?php echo base_url('Report/PowerBI'); ?>">Power BI Builder</a> to create and save a report, then return here to open it in this system.
                        </div>
                    </div>
                <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th style="width:60px;">No.</th>
                                    <th>Report Name</th>
                                    <th style="width:220px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports as $index => $report) {
                                    $report_id = !empty($report['id']) ? $report['id'] : '';
                                    $report_name = !empty($report['name']) ? $report['name'] : 'Untitled report';
                                    if ($report_id === '') {
                                        continue;
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo (int) $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($report_name, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <a href="<?php echo base_url('Report/PowerBI_View/' . rawurlencode($report_id)); ?>" class="btn btn-sm btn-light-primary font-weight-bold mr-2">
                                                View
                                            </a>
                                            <a href="<?php echo base_url('Report/PowerBI_View/' . rawurlencode($report_id) . '?mode=edit'); ?>" class="btn btn-sm btn-light-success font-weight-bold">
                                                Edit
                                            </a>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
