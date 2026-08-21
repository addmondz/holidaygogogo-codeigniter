<?php $this->load->view('partials/phone_country_picker_assets'); ?>
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
                                <label>Alt Name</label>
                                <div class="input-icon">
                                    <input type="text" id="AltName" <?php if(current_url() == base_url('Customer/Update')) { ?> value="<?php echo htmlspecialchars(isset($AltName) ? $AltName : '', ENT_QUOTES); ?>" <?php } ?> autocomplete="off" class="form-control" placeholder="Enter alternate name">
                                    <span><i class="la la-user-friends"></i></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Phone</label>
                                <?php
                                    // Stored phone is "+60 123456789"; split it so the dial-code
                                    // picker and the local number field pre-fill on edit.
                                    $__stored_phone = (current_url() == base_url('Customer/Update')) ? (isset($phone_number) ? $phone_number : '') : '';
                                    $__phone = phone_country_split($__stored_phone);
                                ?>
                                <div class="phone-input-wrapper" data-picker id="cust_phone_wrapper">
                                    <div class="phone-country-selector">
                                        <span class="phone-country-flag">🌐</span>
                                        <span class="phone-country-code">--</span>
                                        <span class="phone-country-arrow">▼</span>
                                    </div>
                                    <input type="text" id="phone_local" value="<?php echo htmlspecialchars($__phone['local'], ENT_QUOTES); ?>" autocomplete="off" class="phone-input-field" placeholder="Enter phone number">
                                    <input type="hidden" id="phone_country_code" class="phone-code-value" value="<?php echo htmlspecialchars($__phone['code'] !== '' ? $__phone['code'] : '+60', ENT_QUOTES); ?>">
                                    <div class="phone-dropdown">
                                        <div class="phone-dropdown-search">
                                            <input type="text" class="phone-search" placeholder="Search country...">
                                        </div>
                                        <div class="phone-dropdown-list">
                                            <?php foreach((isset($country_codes) ? $country_codes : array()) as $cc) { ?>
                                            <div class="phone-dropdown-item" data-code="<?php echo htmlspecialchars($cc->CountryCode, ENT_QUOTES); ?>" data-country="<?php echo htmlspecialchars(strtolower($cc->Country), ENT_QUOTES); ?>" data-country-name="<?php echo htmlspecialchars($cc->Country, ENT_QUOTES); ?>">
                                                <span class="phone-dropdown-item-flag">🌐</span>
                                                <span class="phone-dropdown-item-name"><?php echo htmlspecialchars($cc->Country); ?></span>
                                                <span class="phone-dropdown-item-code"><?php echo htmlspecialchars($cc->CountryCode); ?></span>
                                            </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                                <span class="form-text text-muted">Pick the country code, then enter the number.</span>
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
                                <label>Destinations</label>
                                <?php $sel_dest = isset($selected_destinations) ? (array) $selected_destinations : array(); ?>
                                <select id="Destinations" class="form-control selectpicker" multiple data-live-search="true" title="-- SELECT DESTINATION(S) --" data-selected-text-format="count > 2">
                                    <?php foreach((isset($destinations) ? $destinations : array()) as $dest) { ?>
                                        <option value="<?php echo $dest->CategoryID; ?>" <?php echo in_array((int) $dest->CategoryID, array_map('intval', $sel_dest), true) ? 'selected' : ''; ?>><?php echo htmlspecialchars($dest->Name, ENT_QUOTES); ?></option>
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
                                <?php if (current_url() == base_url('Customer/Update') && !empty($CustomerCode) && !can_edit_customer_code($this->session->userdata('admin_id'))) { ?>
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
                                            value="<?php echo (current_url() == base_url('Customer/Update') && !empty($CustomerCode)) ? htmlspecialchars($CustomerCode, ENT_QUOTES) : ''; ?>"
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
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="checkbox checkbox-lg font-weight-bold">
                                    <input type="checkbox" id="CreatedFromEInvoice" <?php if(current_url() == base_url('Customer/Update') && !empty($CreatedFromEInvoice)) { echo 'checked'; } ?>>
                                    <span></span>
                                    &nbsp;Non-Booker (created from an e-invoice request)
                                </label>
                                <span class="form-text text-muted">Tick to mark this customer as someone who is not the booking customer.</span>
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
                var AltName = ($('#AltName').val()).toUpperCase();
                // Phone is entered as a dial-code picker + local number; combine into
                // the stored "+60 123456789" shape.
                var phone_code = $('#phone_country_code').val();
                var phone_local = $('#phone_local').val();
                var phone_number = combinePhone(phone_code, phone_local);
                var ChatLanguage = $('#ChatLanguage').val();
                var CustomerCode = $('#CustomerCode').val();
                var ic_passport_no = ($('#ic_passport_no').val()).toUpperCase();
                var tin_no = ($('#tin_no').val()).toUpperCase();
                var Address = $('#Address').val();
                var PrimaryEmail = $('#PrimaryEmail').val();
                // Multi-select destinations -> array of CategoryIDs (or [] when none).
                var destinations = $('#Destinations').val() || [];

                if(name == '') {
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert All Required Customer Information', null);
                } else if($.trim(phone_local) !== '' && $.trim(phone_code) === '') {
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please select the phone country code.', null);
                } else {
                    if(window.location.href == '<?php echo base_url('Customer/Create'); ?>') {
                        var customer = [{
                            name: name,
                            AltName: AltName,
                            phone_number: phone_number,
                            ChatLanguage: ChatLanguage,
                            CustomerCode: CustomerCode,
                            ic_passport_no: ic_passport_no,
                            tin_no: tin_no,
                            Address: Address,
                            PrimaryEmail: PrimaryEmail,
                            CreatedFromEInvoice: ($('#CreatedFromEInvoice').is(':checked') ? 1 : 0)
                            // InsertBy: <?php echo $this->session->userdata('admin_id') ?>,
                            // InsertDate: '<?php echo date('Y-m-d H:i:s') ?>'
                        }];
                        Submit_Customer('<?php echo base_url('Customer/Create') ?>', null, customer, destinations);
                    } else {
                        var customer = [{CustomerID: <?php echo $CustomerID ?>}];
                        var dirty_fields = $('#form').dirty('showDirtyFields');
                        if(dirty_fields.length > 0){
                            for(var i = 0; i < dirty_fields.length; i++){
                                var key = dirty_fields[i].id;
                                // Skip phantom dirty fields with no id (e.g. bootstrap-select's
                                // live-search box) — an empty key serializes as customer[0][]
                                // and PHP reads it as numeric column "0", corrupting the UPDATE.
                                if(!key) { continue; }
                                // Destinations is a separate top-level param, not a customer column.
                                if(key === 'Destinations') { continue; }
                                // Phone picker fields are not customer columns; the combined
                                // phone_number is handled separately below.
                                if(key === 'phone_country_code' || key === 'phone_local') { continue; }
                                // Email and billing address are case-sensitive; keep as typed.
                                var preserveCase = (key === 'PrimaryEmail' || key === 'Address');
                                var value = preserveCase ? dirty_fields[i].value : (dirty_fields[i].value).toUpperCase();
                                customer[0][key] = value;
                            }
                        }
                        // Phone is rebuilt from the picker; send it only when it changed.
                        var phoneChanged = (phone_number !== INITIAL_PHONE);
                        if(phoneChanged) { customer[0].phone_number = phone_number; }
                        // Non-booker checkbox isn't caught by jquery.dirty reliably;
                        // send it explicitly only when toggled from its loaded value.
                        var einvoiceFlag = $('#CreatedFromEInvoice').is(':checked') ? 1 : 0;
                        var flagChanged = (einvoiceFlag !== INITIAL_EINVOICE);
                        if(flagChanged) { customer[0].CreatedFromEInvoice = einvoiceFlag; }
                        count = 0;
                        $.each(customer[0], function() {
                            count++;
                        });
                        // Did the destination selection change vs what was loaded?
                        var destChanged = JSON.stringify(destinations.slice().sort())
                            !== JSON.stringify(INITIAL_DESTINATIONS.slice().sort());
                        if(count == 1 && !destChanged && !phoneChanged && !flagChanged) {
                            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php echo 'No Changes Detected In Customer Record : ' . str_replace('\'', '', $name); ?>', '<?php echo base_url('Customer') ?>');
                        } else {
                            Submit_Customer('<?php echo base_url('Customer/Update') ?>', customer[0].CustomerID, customer, destinations);
                        }
                    }
                }
            }
        });
    });

    var SWAL_IMG = '<?php echo base_url('assets/image/sweetalert.jpg') ?>';
    var BACK_URL = '<?php echo base_url('Customer') ?>';
    var OK_MSG   = '<?php if(current_url() == base_url('Customer/Create')) { echo 'New'; } ?> Customer Record <?php if(current_url() == base_url('Customer/Update')) { echo ': ' . str_replace('\'', '', $name); } ?> Successfully <?php if(current_url() == base_url('Customer/Create')) { echo 'Created'; } else { echo 'Updated'; } ?>';
    var FAIL_MSG = '<?php if(current_url() == base_url('Customer/Create')) { echo 'New'; } ?> Customer Record <?php if(current_url() == base_url('Customer/Update')) { echo ': ' . str_replace('\'', '', $name); } ?> Could Not Be <?php if(current_url() == base_url('Customer/Create')) { echo 'Created'; } else { echo 'Updated'; } ?>';
    var CUSTOMER_EDIT_URL = '<?php echo base_url('Customer/Update') ?>';

    // Destination CategoryIDs loaded for this record (Update pre-select), so we can
    // tell whether the selection changed even when no customer field is dirty.
    var INITIAL_DESTINATIONS = <?php echo json_encode(array_map('strval', isset($selected_destinations) ? (array) $selected_destinations : array())); ?>;

    // The phone as originally stored ("+60 123456789" or ''), so we can tell
    // whether the picker changed even when no other field is dirty.
    var INITIAL_PHONE = <?php echo json_encode(isset($__stored_phone) ? $__stored_phone : ''); ?>;

    // Non-booker flag as loaded, so a toggle is detected even when nothing else
    // is dirty (the checkbox isn't tracked by jquery.dirty).
    var INITIAL_EINVOICE = <?php echo (current_url() == base_url('Customer/Update') && !empty($CreatedFromEInvoice)) ? 1 : 0; ?>;

    // Combine the dial-code picker + local number into the stored shape. Mirrors
    // the server phone_country_combine(): drops a single leading trunk "0"; with
    // no code the local number passes through unchanged.
    function combinePhone(code, local) {
        local = $.trim(local == null ? '' : ('' + local));
        code  = $.trim(code == null ? '' : ('' + code));
        if(local === '') { return ''; }
        if(code === '') { return local; }
        if(local.charAt(0) === '0') { local = local.substring(1); }
        if(local === '') { return code; }
        return code + ' ' + local;
    }

    function Submit_Customer(url, customer_id, customer, destinations) {
        $.ajax({
            url: url,
            type: 'post',
            data: {
                customer_id: customer_id,
                customer: customer,
                // JSON string so an empty selection ([]) still posts the key —
                // jQuery would otherwise drop an empty array, blocking "clear all".
                destinations: JSON.stringify(destinations || [])
            },
            dataType: 'text',
            success: function(resp) {
                var data = null;
                try { data = JSON.parse(resp); } catch (e) {}

                // HARD duplicate block: a customer with this name AND phone
                // already exists, so creation is refused. Point the user to it.
                if (data && data.duplicate && data.matches && data.matches.length) {
                    var m = data.matches[0];
                    var esc = function(s) { return $('<div>').text(s == null ? '' : String(s)).html(); };
                    Swal.fire({
                        icon: 'error',
                        title: 'Duplicate Customer Blocked',
                        html: 'A customer with this name and phone number already exists, so a new record was not created:<br><br>'
                            + '<strong>' + esc(m.name) + '</strong><br>'
                            + 'Code: <strong>' + esc(m.CustomerCode || '&mdash;') + '</strong><br>'
                            + 'Phone: ' + esc(m.phone_number),
                        showCancelButton: true,
                        confirmButtonText: 'View existing customer',
                        cancelButtonText: 'Back to edit',
                        customClass: {
                            confirmButton: 'btn btn-light-success m-2',
                            cancelButton: 'btn btn-secondary m-2'
                        },
                        buttonsStyling: true
                    }).then(function(res) {
                        if (res.isConfirmed) {
                            window.location.href = CUSTOMER_EDIT_URL + '?customer_id=' + encodeURIComponent(m.CustomerID);
                        }
                    });
                    return;
                }

                if (data && data.success === false) {
                    Display_Message(SWAL_IMG, data.message || FAIL_MSG, null);
                    return;
                }

                Display_Message(SWAL_IMG, OK_MSG, BACK_URL);
            },
            error: function() {
                Display_Message(SWAL_IMG, FAIL_MSG, null);
            }
        });
    }

    $('#form').dirty('isClean');
</script>