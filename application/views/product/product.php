<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong><?php if(current_url() == base_url('Product/Create')) { echo 'New Product Record'; } else { echo 'Product Record : ' . $ProductCode; } ?></strong>
                    </h3>
                </div>
            </div>
            <div class="card-body">
                <form id="form">
                    <strong>Product Information :</strong>
                    <br><br>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Name
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="text" id="Name" <?php if(current_url() == base_url('Product/Update')) { ?> value="<?php echo $Product; ?>" <?php } ?> autocomplete="off" maxlength="99" class="form-control">
                                    <span>
                                        <i class="la la-product-hunt"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Category
                                    <?php if(current_url() == base_url('Product/Create')) { ?><span style="color:red;">*</span><?php } ?>
                                </label>
                                <?php if(current_url() == base_url('Product/Create')) { ?>
                                    <select id="CategoryID" data-live-search="true" class="form-control selectpicker">
                                        <option selected disabled data-icon="la la-clipboard-list font-size-lg bs-icon" value="">--SELECT CATEGORY--</option>
                                        <?php foreach($categories as $category) { ?>
                                            <option data-icon="la la-clipboard-list font-size-lg bs-icon" value="<?php echo $category->CategoryID; ?>"><?php echo $category->Name; ?></option>
                                        <?php } ?>
                                    </select>
                                <?php } else { ?>
                                    <div class="input-icon">
                                        <input disabled type="text" value="<?php echo $Category; ?>" class="form-control">
                                        <span>
                                            <i class="la la-clipboard-list"></i>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Supplier
                                    <?php if(current_url() == base_url('Product/Create')) { ?><span style="color:red;">*</span><?php } ?>
                                </label>
                                <select id="SupplierID" data-live-search="true" class="form-control selectpicker">
                                    <option selected disabled data-icon="la la-user-alt font-size-lg bs-icon" value="">--SELECT SUPPLIER--</option>
                                    <?php foreach($suppliers as $supplier) { ?>
                                        <option <?php if(current_url() == base_url('Product/Update') && $supplier->SupplierID == $SupplierID) { echo 'selected'; } ?> data-icon="la la-user-alt font-size-lg bs-icon" value="<?php echo $supplier->SupplierID; ?>"><?php echo $supplier->Name; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Retail Price (RM)</label>
                                <div class="input-icon">
                                    <input type="text" id="RetailPrice" <?php if(current_url() == base_url('Product/Create')) { ?> value="" <?php } else { ?> value="<?php echo $RetailPrice; ?>" <?php } ?> autocomplete="off" onchange="Validate_Price('RetailPrice')" class="form-control">
                                    <span>
                                        <i class="la la-dollar"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Supplier Price (RM)</label>
                                <div class="input-icon">
                                    <input type="text" id="SupplierPrice" <?php if(current_url() == base_url('Product/Create')) { ?> value="" <?php } else { ?> value="<?php echo $SupplierPrice; ?>" <?php } ?> autocomplete="off" onchange="Validate_Price('SupplierPrice')" class="form-control">
                                    <span>
                                        <i class="la la-dollar"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-5">
                        <input type="button" value="<?php if(current_url() == base_url('Product/Create')) { echo 'Create Product'; } else { echo 'Update Product'; } ?>" class="btn btn-success font-weight-bold px-9 py-4" style="width:180px; margin-left:auto;">
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function Validate_Price(value) {
        var price = value == 'RetailPrice' ? ($('#RetailPrice').val()).replace(/,/g, '') : ($('#SupplierPrice').val()).replace(/,/g, '');
        if(price.match(/^[1-9][\d]{0,9}([\.][\d]{0,2})?$/)) {
            if(value == 'RetailPrice') {
                $('#RetailPrice').val(parseFloat(price).toLocaleString('en-US', {minimumFractionDigits: 2}));
            } else {
                $('#SupplierPrice').val(parseFloat(price).toLocaleString('en-US', {minimumFractionDigits: 2}));
            }
        } else {
            if(value == 'RetailPrice') {
                $('#RetailPrice').val('');
            } else {
                $('#SupplierPrice').val('');
            }
        }
    }

    $('#Name').change(function() {
        var name = ($('#Name').val()).toUpperCase();
        $.ajax({
            url: '<?php echo base_url('Product/Detect') ?>',
            type: 'post',
            data: {
                name: name
            },
            dataType: 'json',
            success: function(redundant_name) {
                if(redundant_name) {
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Redundant Name Detected', null);
                    $('#Name').val('');
                }
            }
        });
    });

    $('input[type="button"]').click(function() {
        const swalWithBootstrapButtons = Swal.mixin({
            customClass: {
                confirmButton: 'btn btn-light-success m-2',
                cancelButton: 'btn btn-danger m-2'
            },
            buttonsStyling: true
        });
        swalWithBootstrapButtons.fire({
            width: 550,
            background: 'url(<?php echo base_url('assets/image/sweetalert.jpg') ?>)',
            icon: 'warning',
            title: <?php if(current_url() == base_url('Product/Create')) { ?> 'Create New Product Record ?' <?php } else { ?> '<?php echo 'Update Product Record : ' . str_replace('\'', '', $ProductCode) . ' ?'; ?>' <?php } ?>,
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel',
            showCancelButton: true
        }).then((action) => {
            if(action.isConfirmed) {
                var category = $('#CategoryID').val();
                var supplier = $('#SupplierID').val();
                var name = ($('#Name').val()).toUpperCase();
                var maxNameLength = '<?php echo $maxNameLength ?? 95 ?>'
                if(window.location.href == '<?php echo base_url('Product/Create'); ?>' && category == null || supplier == null || name == '') {
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert All Required Product Information', null);
                } else if(name.length > maxNameLength) {
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Product Name Must Not Exceed '+maxNameLength+' Characters', null);
                } else {
                    if(window.location.href == '<?php echo base_url('Product/Create'); ?>') {
                        var product = [];
                        product.push({CategoryID:category, SupplierID:supplier, Name:name, InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});
                        var retail_price = $('#RetailPrice').val();
                        if(retail_price != '') {
                            product[0]['RetailPrice'] = retail_price.replace(/,/g, '');;
                        }
                        var supplier_price = $('#SupplierPrice').val();
                        if(supplier_price != '') {
                            product[0]['SupplierPrice'] = supplier_price.replace(/,/g, '');;
                        }

                        Submit_Product('<?php echo base_url('Product/Create') ?>', category, product);
                    } else {
                        var product = [{ProductID:<?php echo $ProductID ?>, UpdateBy:<?php echo $this->session->userdata('admin_id') ?>, UpdateDate:'<?php echo date('Y-m-d H:i:s') ?>'}];
                        var dirty_fields = $('#form').dirty('showDirtyFields');
                        if(dirty_fields.length > 0) {
                            for(var i = 0; i < dirty_fields.length; i++) {
                                var key = dirty_fields[i].id;
                                var value = (dirty_fields[i].value).toUpperCase();
                                if(key == 'RetailPrice' || key == 'SupplierPrice') {
                                    value = value.replace(/,/g, '');
                                }
                                product[0][key] = value;
                            }
                        }
                        count = 0;
                        $.each(product[0], function() {
                            count++;
                        });
                        if(count == 3) {
                            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php echo 'No Changes Detected In Product Record : ' . str_replace('\'', '', $ProductCode); ?>', '<?php echo base_url('Product') ?>');
                        } else {
                            Submit_Product('<?php echo base_url('Product/Update') ?>', null, product);
                        }
                    }
                }
            }
        });
    });

    function Submit_Product(url, category_id, product)
    {
        $.ajax({
            url: url,
            type: 'post',
            data: {
                category_id: category_id,
                product: product
            },
            success: function() {
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php if(current_url() == base_url('Product/Create')) { echo 'New'; } ?> Product Record <?php if(current_url() == base_url('Product/Update')) { echo ': ' . str_replace('\'', '', $ProductCode); } ?> Successfully <?php if(current_url() == base_url('Product/Create')) { echo 'Created'; } else { echo 'Updated'; } ?>', '<?php echo base_url('Product') ?>');
            },
            error: function() {
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php if(current_url() == base_url('Product/Create')) { echo 'New'; } ?> Product Record <?php if(current_url() == base_url('Product/Update')) { echo ': ' . str_replace('\'', '', $ProductCode); } ?> Successfully <?php if(current_url() == base_url('Product/Create')) { echo 'Created'; } else { echo 'Updated'; } ?>', null);
            }
        });
    }
    
    $('#form').dirty('isClean');
</script>