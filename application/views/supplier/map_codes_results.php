<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Supplier Code Mapping Results</strong>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('Supplier/DownloadResults'); ?>" class="btn btn-light-success font-weight-bold mr-1 mb-2">
                        <i class="la la-download"></i>Download Results (Excel)
                    </a>
                    <a href="<?php echo base_url('Supplier'); ?>" class="btn btn-light-primary font-weight-bold mb-2">
                        <i class="la la-arrow-left"></i>Back to Suppliers
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Summary Card -->
                <div class="row mb-5">
                    <div class="col-md-3">
                        <div class="card card-custom bg-primary">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <span class="text-white font-weight-bold font-size-h6">Total Rows</span>
                                        <span class="text-white font-weight-bolder font-size-h2 d-block"><?php echo $summary['total']; ?></span>
                                    </div>
                                    <span class="svg-icon svg-icon-3x svg-icon-white ml-2">
                                        <i class="la la-list" style="font-size: 3rem; color: white;"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-custom bg-success">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <span class="text-white font-weight-bold font-size-h6">Mapped</span>
                                        <span class="text-white font-weight-bolder font-size-h2 d-block"><?php echo $summary['mapped_count']; ?></span>
                                    </div>
                                    <span class="svg-icon svg-icon-3x svg-icon-white ml-2">
                                        <i class="la la-check-circle" style="font-size: 3rem; color: white;"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-custom bg-warning">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <span class="text-white font-weight-bold font-size-h6">Unmapped</span>
                                        <span class="text-white font-weight-bolder font-size-h2 d-block"><?php echo $summary['unmapped_count']; ?></span>
                                    </div>
                                    <span class="svg-icon svg-icon-3x svg-icon-white ml-2">
                                        <i class="la la-exclamation-circle" style="font-size: 3rem; color: white;"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-custom bg-info">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <span class="text-white font-weight-bold font-size-h6">Updated</span>
                                        <span class="text-white font-weight-bolder font-size-h2 d-block"><?php echo isset($summary['updated_count']) ? $summary['updated_count'] : 0; ?></span>
                                    </div>
                                    <span class="svg-icon svg-icon-3x svg-icon-white ml-2">
                                        <i class="la la-save" style="font-size: 3rem; color: white;"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($duplicate_warnings)) { ?>
                <div class="alert alert-warning" role="alert">
                    <h4 class="alert-heading"><i class="la la-exclamation-triangle"></i> Warning: Duplicate Company Names with Different Codes</h4>
                    <p>The following company name(s) appear multiple times in the CSV with different supplier codes:</p>
                    <ul class="mb-0">
                        <?php foreach ($duplicate_warnings as $warning) { ?>
                        <li>
                            <strong>"<?php echo htmlspecialchars($warning['name']); ?>"</strong> has different codes: 
                            <?php 
                            $codes_display = array();
                            foreach ($warning['codes'] as $code) {
                                $codes_display[] = '"' . htmlspecialchars($code ?: 'EMPTY') . '"';
                            }
                            echo implode(', ', $codes_display);
                            ?>
                            at rows: <?php echo implode(', ', $warning['rows']); ?>
                        </li>
                        <?php } ?>
                    </ul>
                </div>
                <?php } ?>

                <?php if (isset($summary['updated_count']) && $summary['updated_count'] > 0) { ?>
                <div class="alert alert-success" role="alert">
                    <strong>Success!</strong> <?php echo $summary['updated_count']; ?> supplier code(s) have been updated in the database.
                </div>
                <?php } ?>

                <p>Please check the log file for more details.</p>

                <!-- Mapped Suppliers -->
                <?php if (!empty($mapped)) { ?>
                <div class="card card-custom mb-5">
                    <div class="card-header">
                        <div class="card-title">
                            <h3 class="card-label">Mapped Suppliers (<?php echo count($mapped); ?>)</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover table-checkable" id="mapped_table">
                                <thead>
                                    <tr>
                                        <th>Row #</th>
                                        <th>Supplier ID</th>
                                        <th>Supplier Name</th>
                                        <th>CSV Supplier Code</th>
                                        <th>DB Supplier Code</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mapped as $item) { 
                                        $status_class = '';
                                        $status_text = '';
                                        if (isset($item['update_status'])) {
                                            switch ($item['update_status']) {
                                                case 'updated':
                                                    $status_class = 'badge badge-success';
                                                    $status_text = 'Updated';
                                                    if (isset($item['original_db_code'])) {
                                                        $status_text .= ' (was: ' . ($item['original_db_code'] ?: 'NULL') . ')';
                                                    }
                                                    break;
                                                case 'already_exists':
                                                    $status_class = 'badge badge-info';
                                                    $status_text = 'Already Exists';
                                                    break;
                                                case 'csv_empty':
                                                    $status_class = 'badge badge-warning';
                                                    $status_text = 'CSV Empty';
                                                    break;
                                                case 'update_failed':
                                                    $status_class = 'badge badge-danger';
                                                    $status_text = 'Update Failed';
                                                    break;
                                                default:
                                                    $status_class = 'badge badge-secondary';
                                                    $status_text = 'Not Updated';
                                            }
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo $item['row']; ?></td>
                                        <td><strong><?php echo $item['supplier_id']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($item['csv_supplier_code'] ?: 'EMPTY'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($item['db_supplier_code'] ?: 'NULL'); ?></td>
                                        <td><span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php } ?>

                <!-- Unmapped Suppliers -->
                <?php if (!empty($unmapped)) { ?>
                <div class="card card-custom mb-5">
                    <div class="card-header">
                        <div class="card-title">
                            <h3 class="card-label">Unmapped Suppliers (<?php echo count($unmapped); ?>)</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning" role="alert">
                            <strong>Note:</strong> These suppliers could not be found in the database. Please verify the supplier names.
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover table-checkable" id="unmapped_table">
                                <thead>
                                    <tr>
                                        <th>Row #</th>
                                        <th>Supplier Name</th>
                                        <th>CSV Supplier Code</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($unmapped as $item) { ?>
                                    <tr>
                                        <td><?php echo $item['row']; ?></td>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo isset($item['csv_supplier_code']) ? htmlspecialchars($item['csv_supplier_code'] ?: 'EMPTY') : '-'; ?></td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php } else { ?>
                <div class="alert alert-success" role="alert">
                    <strong>Success!</strong> All suppliers were successfully mapped.
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize DataTables for mapped and unmapped tables - show full list, no pagination, no search
    $(document).ready(function() {
        <?php if (!empty($mapped)) { ?>
        $('#mapped_table').DataTable({
            "paging": false,
            "searching": false,
            "info": false,
            "order": [[0, "asc"]],
            "responsive": true,
            "scrollCollapse": false
        });
        <?php } ?>
        
        <?php if (!empty($unmapped)) { ?>
        $('#unmapped_table').DataTable({
            "paging": false,
            "searching": false,
            "info": false,
            "order": [[0, "asc"]],
            "responsive": true,
            "scrollCollapse": false
        });
        <?php } ?>
    });
</script>

