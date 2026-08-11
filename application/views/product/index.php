<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Product Records</strong>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('Product/Create'); ?>" class="btn btn-primary font-weight-bold mr-1 mb-2" style="width:180px;">
                        <i class="la la-product-hunt"></i>Create Product
                    </a>
                    <?php $current_url = base_url($_SERVER['REQUEST_URI']); ?>
                    <a href="<?php if(strpos($current_url, '?') == true) { echo base_url('Product/Download?') . (explode('?', $current_url))[1]; } else { echo base_url('Product/Download'); } ?>" class="btn btn-light-warning font-weight-bold mr-1 mb-2" style="width:180px;">
                        <i class="las la-arrow-circle-down"></i>Product Records
                    </a>
                    <a href="<?php echo base_url('Product/Import_Template'); ?>" class="btn btn-light-info font-weight-bold mr-1 mb-2" style="width:180px;" data-toggle="tooltip" title="Download the blank Excel template to bulk create/update products">
                        <i class="la la-file-download"></i>Import Template
                    </a>
                    <button type="button" class="btn btn-light-success font-weight-bold mr-1 mb-2" style="width:180px;" data-toggle="modal" data-target="#product_import_modal" title="Upload a filled template to bulk create/update products">
                        <i class="la la-file-import"></i>Bulk Import
                    </button>
                    <?php if(!empty($products)) { ?>
                    <button type="button" id="btn_bulk_add_checklist" class="btn btn-light-success font-weight-bold mb-2" style="width:200px;">
                        <i class="las la-check-square"></i>Add Checklist to All
                    </button>
                    <?php } ?>
                </div>
            </div>
            <div class="card-body">
                <?php if($this->session->flashdata('product_import_success')) { ?>
                    <div class="alert alert-light-success font-weight-bold" role="alert" style="border-left:4px solid #1bc5bd;">
                        <?php echo htmlspecialchars($this->session->flashdata('product_import_success')); ?>
                    </div>
                <?php } ?>
                <?php if($this->session->flashdata('product_import_error')) { ?>
                    <div class="alert alert-light-danger font-weight-bold" role="alert" style="border-left:4px solid #f64e60;">
                        <?php echo htmlspecialchars($this->session->flashdata('product_import_error')); ?>
                    </div>
                <?php } ?>
                <div class="accordion accordion-solid accordion-toggle-plus">
                    <div class="card">
                        <div class="card-header">
                            <div id="product_header" data-toggle="collapse" data-target="#product_info" class="card-title collapsed" style="font-size:13px;">Filter By Product Information</div>
                        </div>
                        <div id="product_info" class="collapse">
                            <div class="card-body">
                                <form action="<?php echo base_url('Product') ?>" method="get" class="form">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Category</label>
                                                <select name="category" data-live-search="true" class="form-control selectpicker">
                                                    <option selected data-icon="la la-clipboard-list font-size-lg bs-icon" value="">--SELECT CATEGORY--</option>
                                                    <?php foreach($categories as $category) { ?>
                                                        <option data-icon="la la-clipboard-list font-size-lg bs-icon" value="<?php echo $category->CategoryID; ?>" <?php if(!empty($this->input->get('category')) && $this->input->get('category') == $category->CategoryID) { echo 'selected'; } ?>><?php echo $category->Name; ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Supplier</label>
                                                <select name="supplier" data-live-search="true" class="form-control selectpicker">
                                                    <option selected data-icon="la la-user-alt font-size-lg bs-icon" value="">--SELECT SUPPLIER--</option>
                                                    <?php foreach($suppliers as $supplier) { ?>
                                                        <option data-icon="la la-user-alt font-size-lg bs-icon" value="<?php echo $supplier->SupplierID; ?>" <?php if(!empty($this->input->get('supplier')) && $this->input->get('supplier') == $supplier->SupplierID) { echo 'selected'; } ?>><?php echo $supplier->Name; ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Product Code</label>
                                                <div class="input-icon">
                                                    <input type="text" name="product_code" value="<?php if(!empty($this->input->get('product_code'))) { echo strtoupper($this->input->get('product_code')); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-product-hunt"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Name</label>
                                                <div class="input-icon">
                                                    <input type="text" name="name" value="<?php if(!empty($this->input->get('name'))) { echo strtoupper($this->input->get('name')); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-product-hunt"></i>
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
                <div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if(empty($products)) { echo 'style="overflow-x:auto;"'; } ?>>
                    <table id="kt_datatable" class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline">
                        <thead>
                            <tr>
                                <th style="text-align:center;">No.</th>
                                <th style="text-align:center;">Product Code</th>
                                <th style="text-align:center;">Name</th>
                                <th class="retail_price" style="text-align:center;">Retail Price (RM)</th>
                                <th class="supplier_price" style="text-align:center;">Supplier Price (RM)</th>
                                <th class="action" style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($products)) { ?>
                                <td colspan="6" style="text-align:center; padding-top:10px; padding-bottom:10px;">Product Records Not Found</td>
                            <?php } else { ?>
                                <?php $count = 1; ?>
                                <?php foreach($products as $product) { ?>
                                    <tr data-product-id="<?php echo $product->ProductID; ?>">
                                        <td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
                                        <td style="text-align:center;"><?php echo $product->ProductCode; ?></td>
                                        <td style="text-align:center;"><?php echo $product->Product; ?></td>
                                        <td style="text-align:center;"><?php echo $product->RetailPrice; ?></td>
                                        <td style="text-align:center;"><?php echo $product->SupplierPrice; ?></td>
                                        <td style="text-align:center;">
                                            <div class="btn-group">
                                                <button type="button" data-toggle="dropdown" class="btn btn-light-primary btn-sm dropdown-toggle" style="padding-left:3px;"></button>
                                                <div class="dropdown-menu">
                                                    <button onclick="Delete_Record('<?php echo base_url('assets/image/sweetalert.jpg'); ?>', '<?php echo 'Product Record : ' . str_replace('\'', '', $product->ProductCode); ?>', '<?php echo base_url('Product/Delete'); ?>', 'product_id', <?php echo $product->ProductID; ?>, '<?php echo $product->Status; ?>', '<?php if(strpos($current_url, '?') == true) { echo base_url('Product?') . (explode('?', $current_url))[1]; } else { echo base_url('Product'); } ?>')" class="dropdown-item" style="color:#E37383; font-size:11px;">Delete Product</button>
                                                    <a href="<?php echo base_url('Product/Update?product_id=') . $product->ProductID; ?>" class="dropdown-item" style="font-size:11px;">Update Product</a>
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

<div class="modal fade" id="product_import_modal" tabindex="-1" role="dialog" aria-labelledby="product_import_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form action="<?php echo base_url('Product/Import'); ?>" method="post" enctype="multipart/form-data" id="product_import_form">
            <div class="modal-content">
                <div class="modal-header" style="background-color:#D7E2F2;">
                    <h5 class="modal-title" id="product_import_label" style="color:#6082B6;"><strong>Bulk Import Products from Excel</strong></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light-primary" role="alert" style="border-left:4px solid #6082B6;">
                        Download <strong>Import Template</strong> first, fill one product per row, then upload it here. A row whose <strong>Product Code</strong> matches an existing product <strong>updates</strong> it; a blank Product Code <strong>creates</strong> a new product.
                    </div>
                    <div class="form-group">
                        <label>Excel File <span class="text-danger">*</span></label>
                        <div class="custom-file">
                            <input type="file" name="import_file" class="custom-file-input" id="product_import_file" accept=".xlsx,.xls" required>
                            <label class="custom-file-label" for="product_import_file" id="product_import_file_label">Choose .xlsx / .xls file</label>
                        </div>
                        <span class="form-text text-muted"><strong>Category</strong>, <strong>Supplier</strong> and <strong>Name</strong> are required per row. Category/Supplier must match existing names. The last 3 uploads are kept as backups.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light font-weight-bold" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success font-weight-bold" id="product_import_submit">
                        <i class="la la-file-import"></i>Import Products
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    $('#product_import_file').on('change', function() {
        var name = (this.files && this.files.length) ? this.files[0].name : 'Choose .xlsx / .xls file';
        $('#product_import_file_label').text(name);
    });
    $('#product_import_form').on('submit', function() {
        $('#product_import_submit').prop('disabled', true).html('<i class="la la-spinner la-spin"></i>Importing...');
    });
</script>

<script>
    <?php if(!empty($this->input->get('category')) || !empty($this->input->get('supplier')) || !empty($this->input->get('product_code')) || !empty($this->input->get('name'))) { ?>
        $('#product_header').click();
    <?php } ?>
    
    $('#reset').click(function() {
        Reset('<?php echo base_url('Product'); ?>');
    });

    $('#btn_bulk_add_checklist').click(function() {
        var product_ids = [];
        $('#kt_datatable tbody tr[data-product-id]').each(function() {
            product_ids.push($(this).data('product-id'));
        });

        if(product_ids.length === 0) {
            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg'); ?>', 'No products found', null);
            return;
        }

        var checklist_options = '';
        <?php if(!empty($checklists)) { ?>
            <?php foreach($checklists as $checklist) { ?>
                checklist_options += '<option value="<?php echo $checklist->ID; ?>"><?php echo addslashes($checklist->name); ?><?php echo $checklist->is_required == 1 ? ' (Required)' : ''; ?></option>';
            <?php } ?>
        <?php } ?>

        var checklist_checkboxes = '';
        <?php if(!empty($checklists)) { ?>
            <?php foreach($checklists as $checklist) { ?>
                checklist_checkboxes += '<div class="checkbox-inline" style="display:block; text-align:left; margin-bottom:8px;">' +
                    '<label class="checkbox checkbox-success">' +
                    '<input type="checkbox" class="swal-checklist-cb" value="<?php echo $checklist->ID; ?>">' +
                    '<span></span>&nbsp;<?php echo addslashes($checklist->name); ?>' +
                    '</label></div>';
            <?php } ?>
        <?php } ?>

        Swal.fire({
            title: 'Add Checklist to All Products',
            html: '<p>This will add the selected checklist(s) to <strong>' + product_ids.length + '</strong> product(s).</p>' +
                  '<div style="max-height:300px; overflow-y:auto; padding:10px;">' + checklist_checkboxes + '</div>',
            background: 'url(<?php echo base_url('assets/image/sweetalert.jpg'); ?>)',
            showCancelButton: true,
            confirmButtonText: 'Add Checklist',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#1BC5BD',
            preConfirm: function() {
                var selected = [];
                document.querySelectorAll('.swal-checklist-cb:checked').forEach(function(cb) {
                    selected.push(cb.value);
                });
                if(selected.length === 0) {
                    Swal.showValidationMessage('Please select at least one checklist');
                    return false;
                }
                return selected;
            }
        }).then(function(result) {
            if(result.isConfirmed) {
                var checklist_ids = result.value;

                Swal.fire({
                    title: 'Processing...',
                    text: 'Adding checklist to ' + product_ids.length + ' product(s)',
                    allowOutsideClick: false,
                    didOpen: function() {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: '<?php echo base_url('Product/BulkAddChecklist'); ?>',
                    type: 'POST',
                    data: {
                        checklist_ids: checklist_ids,
                        product_ids: product_ids
                    },
                    dataType: 'json',
                    success: function(response) {
                        if(response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: response.message,
                                background: 'url(<?php echo base_url('assets/image/sweetalert.jpg'); ?>)'
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message,
                                background: 'url(<?php echo base_url('assets/image/sweetalert.jpg'); ?>)'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred. Please try again.',
                            background: 'url(<?php echo base_url('assets/image/sweetalert.jpg'); ?>)'
                        });
                    }
                });
            }
        });
    });
</script>