<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>
                            <?php if(current_url() == base_url('Customer/Create')) { echo 'New Customer Record'; } else { echo 'Customer Record : ' . $name; } ?>
                        </strong>
                    </h3>
                </div>
            </div>
            <div class="card-body">
                <form id="form">
                    <strong>Customer Information :</strong>
                    <br><br>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Name <span style="color:red;">*</span></label>
                                <div class="input-icon">
                                    <input type="text" id="name" <?php if(current_url() == base_url('Customer/Update')) { ?> value="<?php echo $name; ?>" <?php } ?> autocomplete="off" class="form-control">
                                    <span><i class="la la-user-alt"></i></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Phone</label>
                                <div class="input-icon">
                                    <input type="text" id="phone_number" <?php if(current_url() == base_url('Customer/Update')) { ?> value="<?php echo $phone_number; ?>" <?php } ?> autocomplete="off" class="form-control">
                                    <span><i class="la la-phone"></i></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Chat Language</label>
                                <select id="ChatLanguage" class="form-control selectpicker">
                                    <option selected disabled data-icon="la la-language font-size-lg bs-icon" value="">--SELECT CHAT LANGUAGE--</option>
                                    <?php foreach(unserialize(CHAT_LANGUAGE) as $key => $value) { ?>
                                        <option <?php if((current_url() == base_url('Customer/Update') || current_url() == base_url('Customer/Duplicate')) && $key == $ChatLanguage) { echo 'selected'; } ?> data-icon="la la-language font-size-lg bs-icon" value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                    <?php } ?>
                                </select>  
                            </div>                   
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>IC / Passport No</label>
                                <div class="input-icon">
                                    <input type="text" id="ic_passport_no" <?php if(current_url() == base_url('Customer/Update')) { ?> value="<?php echo htmlspecialchars(isset($ic_passport_no) ? $ic_passport_no : '', ENT_QUOTES); ?>" <?php } ?> autocomplete="off" class="form-control">
                                    <span><i class="la la-id-card"></i></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>TIN</label>
                                <div class="input-icon">
                                    <input type="text" id="tin_no" <?php if(current_url() == base_url('Customer/Update')) { ?> value="<?php echo htmlspecialchars(isset($tin_no) ? $tin_no : '', ENT_QUOTES); ?>" <?php } ?> autocomplete="off" class="form-control">
                                    <span><i class="la la-file-invoice"></i></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Customer Code</label>
                                <?php if (current_url() == base_url('Customer/Update') && !empty($CustomerCode)) { ?> 
                                    <div class="input-icon">
                                        <div class="form-control bg-light" style="cursor:not-allowed;">
                                            <?php echo !empty($CustomerCode) ? htmlspecialchars($CustomerCode, ENT_QUOTES) : ''; ?>
                                        </div>
                                        <span><i class="la la-clipboard-list"></i></span>
                                    </div>
                                <?php } else { ?> 
                                    <div class="input-icon">
                                        <input type="text" 
                                            id="CustomerCode" 
                                            name="CustomerCode"
                                            class="form-control"
                                            autocomplete="off"
                                            placeholder="Enter customer code">
                                        <span><i class="la la-clipboard-list"></i></span>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email</label>
                                <div class="input-icon">
                                    <input type="email" id="PrimaryEmail" <?php if(current_url() == base_url('Customer/Update')) { ?> value="<?php echo htmlspecialchars(isset($PrimaryEmail) ? $PrimaryEmail : '', ENT_QUOTES); ?>" <?php } ?> autocomplete="off" class="form-control" placeholder="Enter email">
                                    <span><i class="la la-envelope"></i></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Billing Address</label>
                                <div class="input-icon">
                                    <textarea id="Address" rows="2" autocomplete="off" class="form-control" placeholder="Enter billing address"><?php if(current_url() == base_url('Customer/Update')) { echo htmlspecialchars(isset($Address) ? $Address : '', ENT_QUOTES); } ?></textarea>
                                    <span><i class="la la-map-marker"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-5">
                        <input type="button" value="<?php if(current_url() == base_url('Customer/Create')) { echo 'Create Customer'; } else { echo 'Update Customer'; } ?>" class="btn btn-success font-weight-bold px-9 py-4" style="width:180px; margin-left:auto;">
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<script>
    $('#name').change(function() {
        var name = ($('#name').val()).toUpperCase();
        $.ajax({
            url: '<?php echo base_url('Customer/Detect') ?>',
            type: 'post',
            data: { name: name },
            dataType: 'json',
            success: function(redundant_name) {
                if(redundant_name) {
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Redundant Name Detected', null);
                    $('#name').val('');
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
            title: <?php if(current_url() == base_url('Customer/Create')) { ?> 'Create New Customer Record ?' <?php } else { ?> '<?php echo 'Update Customer Record : ' . str_replace('\'', '', $name) . ' ?'; ?>' <?php } ?>,
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel',
            showCancelButton: true
        }).then((action) => {
            if(action.isConfirmed) {
                var name = ($('#name').val()).toUpperCase();
                var phone_number = $('#phone_number').val();
                var ChatLanguage = $('#ChatLanguage').val();
                var CustomerCode = $('#CustomerCode').val();
                var ic_passport_no = ($('#ic_passport_no').val()).toUpperCase();
                var tin_no = ($('#tin_no').val()).toUpperCase();
                var Address = $('#Address').val();
                var PrimaryEmail = $('#PrimaryEmail').val();

                if(name == '') {
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert All Required Customer Information', null);
                } else {
                    if(window.location.href == '<?php echo base_url('Customer/Create'); ?>') {
                        var customer = [{
                            name: name,
                            phone_number: phone_number,
                            ChatLanguage: ChatLanguage,
                            CustomerCode: CustomerCode,
                            ic_passport_no: ic_passport_no,
                            tin_no: tin_no,
                            Address: Address,
                            PrimaryEmail: PrimaryEmail
                            // InsertBy: <?php echo $this->session->userdata('admin_id') ?>,
                            // InsertDate: '<?php echo date('Y-m-d H:i:s') ?>'
                        }];
                        Submit_Customer('<?php echo base_url('Customer/Create') ?>', null, customer);
                    } else {
                        var customer = [{CustomerID: <?php echo $CustomerID ?>}];
                        var dirty_fields = $('#form').dirty('showDirtyFields');
                        if(dirty_fields.length > 0){
                            for(var i = 0; i < dirty_fields.length; i++){
                                var key = dirty_fields[i].id;
                                // Email and billing address are case-sensitive; keep as typed.
                                var preserveCase = (key === 'PrimaryEmail' || key === 'Address');
                                var value = preserveCase ? dirty_fields[i].value : (dirty_fields[i].value).toUpperCase();
                                customer[0][key] = value;
                            }
                        }
                        count = 0;
                        $.each(customer[0], function() {
                            count++;
                        });     
                        if(count == 1) {
                            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php echo 'No Changes Detected In Customer Record : ' . str_replace('\'', '', $name); ?>', '<?php echo base_url('Customer') ?>');
                        } else {
                            console.log('123');
                            Submit_Customer('<?php echo base_url('Customer/Update') ?>', customer[0].CustomerID, customer);
                        }
                    }
                }
            }
        });
    });

    function Submit_Customer(url, customer_id, customer) {
        $.ajax({
            url: url,
            type: 'post',
            data: {
                customer_id: customer_id,
                customer: customer
            },
            success: function() {
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php if(current_url() == base_url('Customer/Create')) { echo 'New'; } ?> Customer Record <?php if(current_url() == base_url('Customer/Update')) { echo ': ' . str_replace('\'', '', $name); } ?> Successfully <?php if(current_url() == base_url('Customer/Create')) { echo 'Created'; } else { echo 'Updated'; } ?>', '<?php echo base_url('Customer') ?>');
            },
            error: function() {
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php if(current_url() == base_url('Customer/Create')) { echo 'New'; } ?> Customer Record <?php if(current_url() == base_url('Customer/Update')) { echo ': ' . str_replace('\'', '', $name); } ?> Could Not Be <?php if(current_url() == base_url('Customer/Create')) { echo 'Created'; } else { echo 'Updated'; } ?>', null);
            }
        });
    }

    $('#form').dirty('isClean');
</script>