<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Map Supplier Codes from CSV</strong>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('Supplier'); ?>" class="btn btn-light-primary font-weight-bold mr-1 mb-2">
                        <i class="la la-arrow-left"></i>Back to Suppliers
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($error)) { ?>
                    <div class="alert alert-danger" role="alert">
                        <strong>Error:</strong> <?php echo $error; ?>
                    </div>
                <?php } ?>

                <div class="alert alert-info" role="alert">
                    <h4 class="alert-heading">Instructions:</h4>
                    <ul class="mb-0">
                        <li>Upload a CSV file containing supplier names</li>
                        <li>The CSV should have a header row with a column containing supplier names (e.g., "name", "Name", "supplier_name")</li>
                        <li>The tool will automatically map supplier names to supplier codes from the database</li>
                        <li>A log file will be generated showing mapped and unmapped suppliers</li>
                    </ul>
                </div>

                <form action="<?php echo base_url('Supplier/MapSupplierCodes'); ?>" method="post" enctype="multipart/form-data" class="form" id="csv_upload_form">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>CSV File <span class="text-danger">*</span></label>
                                <div class="custom-file">
                                    <input type="file" name="csv_file" class="custom-file-input" id="csv_file" accept=".csv" required>
                                    <label class="custom-file-label" for="csv_file" id="csv_file_label" style="font-size:13px;">Choose CSV file</label>
                                </div>
                                <span class="form-text text-muted">Maximum file size: 5MB. Only CSV files are allowed.</span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary font-weight-bold" id="upload_btn">
                                    <i class="la la-upload"></i>Upload and Map Supplier Codes
                                </button>
                                <span id="upload_loading" style="display:none; margin-left: 10px;">
                                    <i class="la la-spinner la-spin"></i> Processing...
                                </span>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="separator separator-dashed my-5"></div>

                <div class="card card-custom">
                    <div class="card-header">
                        <div class="card-title">
                            <h3 class="card-label">CSV File Format Example</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <p><strong>Required CSV Columns:</strong></p>
                        <ul>
                            <li><code>Supplier Code</code> (required)</li>
                            <li><code>Company Name</code> (required)</li>
                        </ul>
                        <p><strong>Note:</strong> The CSV file must contain both "Supplier Code" and "Company Name" columns. The tool will map "Company Name" to find the corresponding Supplier Code from the database.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Update file input label when file is selected
    document.getElementById('csv_file').addEventListener('change', function(e) {
        if (e.target.files && e.target.files[0]) {
            var fileName = e.target.files[0].name;
            // Remove the fakepath prefix if present
            fileName = fileName.replace(/^.*[\\\/]/, '');
            document.getElementById('csv_file_label').innerText = fileName;
        }
    });

    // Show loading indicator on form submit
    document.getElementById('csv_upload_form').addEventListener('submit', function(e) {
        var btn = document.getElementById('upload_btn');
        var loading = document.getElementById('upload_loading');
        var fileInput = document.getElementById('csv_file');
        
        if (!fileInput.files || !fileInput.files[0]) {
            e.preventDefault();
            alert('Please select a CSV file to upload.');
            return false;
        }
        
        btn.disabled = true;
        loading.style.display = 'inline-block';
    });
</script>

