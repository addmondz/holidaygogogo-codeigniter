<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong><?php if(current_url() == base_url('Supplier/Create')) { echo 'New Supplier Record'; } else { echo 'Supplier Record : ' . $Name; } ?></strong>
                    </h3>
                </div>
            </div>
            <div class="card-body">
                <form id="form">
                    <strong>Supplier Information :</strong>
                    <br><br>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Name
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="text" id="Name" <?php if(current_url() == base_url('Supplier/Update')) { ?> value="<?php echo $Name; ?>" <?php } ?> autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-user-alt"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Phone</label>
                                <div class="input-icon">
                                    <input type="text" id="Phone" <?php if(current_url() == base_url('Supplier/Update')) { ?> value="<?php echo $Phone; ?>" <?php } ?> autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-phone"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Primary Email</label>
                                <div class="input-icon">
                                    <input type="email" id="PrimaryEmail" <?php if(current_url() == base_url('Supplier/Update')) { ?> value="<?php echo $PrimaryEmail; ?>" <?php } ?> autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-envelope"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Secondary Email</label>
                                <div class="input-icon">
                                    <input type="email" id="SecondaryEmail" <?php if(current_url() == base_url('Supplier/Update')) { ?> value="<?php echo $SecondaryEmail; ?>" <?php } ?> autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-envelope"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Address</label>
                                <div class="input-icon">
                                    <input type="text" id="Address" <?php if(current_url() == base_url('Supplier/Update')) { ?> value="<?php echo $Address; ?>" <?php } ?> autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-map-marker"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Currency Code
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="text" id="CurrencyCode" <?php if(current_url() == base_url('Supplier/Update')) { ?> value="<?php echo $CurrencyCode; ?>" <?php } ?> autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-clipboard-list"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-5"></div>
                    <strong>Supplier Bank Information :</strong>
                    <br><br>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Bank</label>
                                <div class="input-icon">
                                    <input type="text" id="Bank" <?php if(current_url() == base_url('Supplier/Update')) { ?> value="<?php echo $Bank; ?>" <?php } ?> autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-landmark"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Bank Account</label>
                                <div class="input-icon">
                                    <input type="text" id="BankAccount" <?php if(current_url() == base_url('Supplier/Update')) { ?> value="<?php echo $BankAccount; ?>" <?php } ?> autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-clipboard-list"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Bank Holder</label>
                                <div class="input-icon">
                                    <input type="text" id="BankHolder" <?php if(current_url() == base_url('Supplier/Update')) { ?> value="<?php echo $BankHolder; ?>" <?php } ?> autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-user"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Swift Code</label>
                                <div class="input-icon">
                                    <input type="text" id="SwiftCode" <?php if(current_url() == base_url('Supplier/Update')) { ?> value="<?php echo $SwiftCode; ?>" <?php } ?> autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-clipboard-list"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-5">
                        <input type="button" value="<?php if(current_url() == base_url('Supplier/Create')) { echo 'Create Supplier'; } else { echo 'Update Supplier'; } ?>" class="btn btn-success font-weight-bold px-9 py-4" style="width:180px; margin-left:auto;">
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    $('#Name').change(function() {
        var name = ($('#Name').val()).toUpperCase();
        $.ajax({
            url: '<?php echo base_url('Supplier/Detect') ?>',
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
            title: <?php if(current_url() == base_url('Supplier/Create')) { ?> 'Create New Supplier Record ?' <?php } else { ?> '<?php echo 'Update Supplier Record : ' . str_replace('\'', '', $Name) . ' ?'; ?>' <?php } ?>,
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel',
            showCancelButton: true
        }).then((action) => {
            if(action.isConfirmed) {
                var name = ($('#Name').val()).toUpperCase();
                var currency_code = ($('#CurrencyCode').val()).toUpperCase();
                if(name == '' || currency_code == '') {
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert All Required Supplier Information', null);
                } else {
                    if(window.location.href == '<?php echo base_url('Supplier/Create'); ?>') {
                        var supplier = [];
                        supplier.push({Name:name, CurrencyCode:currency_code, InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});
                        var phone = $('#Phone').val();
                        if(phone != '') {
                            supplier[0]['Phone'] = phone;
                        }
                        var primary_email = ($('#PrimaryEmail').val()).toUpperCase();
                        if(primary_email != '') {
                            supplier[0]['PrimaryEmail'] = primary_email;
                        }
                        var secondary_email = ($('#SecondaryEmail').val()).toUpperCase();
                        if(secondary_email != '') {
                            supplier[0]['SecondaryEmail'] = secondary_email;
                        }
                        var address = ($('#Address').val()).toUpperCase();
                        if(address != '') {
                            supplier[0]['Address'] = address;
                        }
                        var bank = ($('#Bank').val()).toUpperCase();
                        if(bank != '') {
                            supplier[0]['Bank'] = bank;
                        }
                        var bank_account = ($('#BankAccount').val()).toUpperCase();
                        if(bank_account != '') {
                            supplier[0]['BankAccount'] = bank_account;
                        }
                        var bank_holder = ($('#BankHolder').val()).toUpperCase();
                        if(bank_holder != '') {
                            supplier[0]['BankHolder'] = bank_holder;
                        }
                        var swift_code = ($('#SwiftCode').val()).toUpperCase();
                        if(swift_code != '') {
                            supplier[0]['SwiftCode'] = swift_code;
                        }
                        Submit_Supplier('<?php echo base_url('Supplier/Create') ?>', null, supplier);
                    } else {
                        var supplier = [{SupplierID:<?php echo $SupplierID ?>, UpdateBy:<?php echo $this->session->userdata('admin_id') ?>, UpdateDate:'<?php echo date('Y-m-d H:i:s') ?>'}];
                        var dirty_fields = $('#form').dirty('showDirtyFields');
                        if(dirty_fields.length > 0) {
                            for(var i = 0; i < dirty_fields.length; i++) {
                                var key = dirty_fields[i].id;
                                var value = (dirty_fields[i].value).toUpperCase();
                                supplier[0][key] = value;
                            }
                        }
                        count = 0;
                        $.each(supplier[0], function() {
                            count++;
                        });
                        if(count == 3) {
                            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php echo 'No Changes Detected In Supplier Record : ' . str_replace('\'', '', $Name); ?>', '<?php echo base_url('Supplier') ?>');
                        } else {
                            Submit_Supplier('<?php echo base_url('Supplier/Update') ?>', supplier[0].SupplierID, supplier);
                        }
                    }
                }
            }
        });
    });

    function Submit_Supplier(url, supplier_id, supplier)
    {
        $.ajax({
            url: url,
            type: 'post',
            data: {
                supplier_id: supplier_id,
                supplier: supplier
            },
            success: function() {
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php if(current_url() == base_url('Supplier/Create')) { echo 'New'; } ?> Supplier Record <?php if(current_url() == base_url('Supplier/Update')) { echo ': ' . str_replace('\'', '', $Name); } ?> Successfully <?php if(current_url() == base_url('Supplier/Create')) { echo 'Created'; } else { echo 'Updated'; } ?>', '<?php echo base_url('Supplier') ?>');
            },
            error: function() {
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php if(current_url() == base_url('Supplier/Create')) { echo 'New'; } ?> Supplier Record <?php if(current_url() == base_url('Supplier/Update')) { echo ': ' . str_replace('\'', '', $Name); } ?> Could Not Be <?php if(current_url() == base_url('Supplier/Create')) { echo 'Created'; } else { echo 'Updated'; } ?>', null);
            }
        });
    }
    
    $('#form').dirty('isClean');
</script>