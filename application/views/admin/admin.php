<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong><?php if($Action == 'C') { echo 'New Admin Record'; } else { echo 'Admin Record : ' . $Name; } ?></strong>
                    </h3>
                </div>
            </div>
            <div class="card-body">
                <form id="form">
                    <strong>Admin Information :</strong>
                    <br><br>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Name
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="text" id="Name" value="<?php if($Action == 'U') { echo $Name; } else { echo ''; } ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-user"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Gender
                                    <?php if($Action == 'C') { ?><span style="color:red;">*</span><?php } ?>
                                </label>
                                <select id="Gender" class="form-control selectpicker">
                                    <option selected disabled data-icon="la la-mercury font-size-lg bs-icon" value="">--SELECT GENDER--</option>
                                    <?php foreach(unserialize(GENDER) as $key => $value) { ?>
                                        <option <?php if($Action == 'U' && $key == $Gender) { echo 'selected'; } ?> data-icon="<?php if($key == 'F') { echo 'la la-female'; } else { echo 'la la-male'; } ?> font-size-lg bs-icon" value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Identification Number</label>
                                <div class="input-icon">
                                    <input type="text" id="IdentificationNumber" value="<?php if($Action == 'U') { echo $IdentificationNumber; } else { echo ''; } ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-id-card"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Passport Number</label>
                                <div class="input-icon">
                                    <input type="text" id="PassportNumber" value="<?php if($Action == 'U') { echo $PassportNumber; } else { echo ''; } ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-passport"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Country Code
                                    <span style="color:red;">*</span>
                                </label>
                                <select id="CountryCodeID" <?php if(!empty($country_codes)) { echo 'data-live-search="true"'; } ?> class="form-control selectpicker">
                                    <option selected disabled data-icon="la la-phone font-size-lg bs-icon" value="">--SELECT COUNTRY CODE--</option>
                                    <?php if(!empty($country_codes)) {
                                        foreach($country_codes as $country_code) { ?>
                                            <option <?php if($Action == 'U' && $country_code->CountryCodeID == $CountryCodeID) { echo 'selected'; } ?> data-icon="la la-phone font-size-lg bs-icon" value="<?php echo $country_code->CountryCodeID; ?>"><?php echo $country_code->Country . ' ' . $country_code->CountryCode; ?></option>
                                        <?php }
                                    } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Mobile
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="text" id="Mobile" value="<?php if($Action == 'U') { echo $Mobile; } else { echo ''; } ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-mobile"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="email" id="Email" value="<?php if($Action == 'U') { echo $Email; } else { echo ''; } ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-envelope"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-5"></div>
                    <strong>Account Setting :</strong>
                    <br><br>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Username
                                    <?php if($Action == 'C') { ?><span style="color:red;">*</span><?php } ?>
                                </label>
                                <div class="input-icon">
                                    <input <?php if($Action == 'U') { echo 'disabled'; } ?> type="text" id="Username" value="<?php if($Action == 'U') { echo $Username; } else { echo ''; } ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-user"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Password
                                    <?php if($Action == 'C') { ?><span style="color:red;">*</span><?php } ?>
                                </label>
                                <div class="input-icon">
                                    <input type="password" id="Password" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-key"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Level
                                    <?php if($Action == 'C') { ?><span style="color:red;">*</span><?php } ?>
                                </label>
                                <select id="Level" class="form-control selectpicker">
                                    <option selected disabled data-icon="la la-key font-size-lg bs-icon" value="">--SELECT LEVEL--</option>
                                    <?php foreach(unserialize(LEVEL) as $key => $value) { ?>
                                        <?php if($this->session->level == 30 && $key == 10) { continue; } ?>
                                        <option <?php if($Action == 'U' && $key == $Level) { echo 'selected'; } ?> data-icon="<?php if($key == 20) { echo 'la la-user-alt'; } else if($key == 30) { echo 'la la-hand-holding-usd'; } else { echo 'la la-user-tie'; } ?> font-size-lg bs-icon" value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Access Control
                                    <span style="color:red;">*</span>
                                    <a onclick="Reset_Access_Control()" class="btn btn-icon btn-light-warning btn-xs">
                                        <i class="la la-undo"></i>
                                    </a>
                                </label>
                                <select title="--SELECT ACCESS CONTROL--" id="AccessControl" multiple class="form-control selectpicker">
                                    <?php foreach(unserialize(ACCESS_CONTROL) as $key => $value) { ?>
                                        <?php if($key == 'GB') { ?><optgroup label="BOOKING MODULE"><?php } ?>
                                        <?php if($key == 'GP') { ?><optgroup label="PAYMENT MODULE"><?php } ?>
                                        <?php if($key == 'VR') { ?><optgroup label="REPORT MODULE"><?php } ?>
                                        <option <?php if($Action == 'U' && in_array($key, $AccessControl)) { echo 'selected'; } ?> data-icon="la la-check-circle font-size-lg bs-icon" value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-5">
                        <input type="button" value="<?php if($Action == 'C') { echo 'Create Admin'; } else { echo 'Update Admin'; } ?>" class="btn btn-success font-weight-bold px-9 py-4" style="width:180px; margin-left:auto;">
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    var background = '<?php echo base_url('assets/image/sweetalert.jpg') ?>';
    var action = '<?php echo $Action ?>';
    var session_id = <?php echo $this->session->admin_id ?>;
    var current_datetime = '<?php echo date('Y-m-d H:i:s') ?>';
    var success_message = action == 'C' ? 'New Admin Record Successfully Created' : '<?php echo 'Admin Record : ' . $Name . ' Successfully Updated'; ?>';
    var error_message = action == 'C' ? 'New Admin Record Could Not Be Created' : '<?php echo 'Update Admin Record : ' . $Name . ' Could Not Be Updated'; ?>';

    function Reset_Access_Control() {
        $('#AccessControl').val('').change();
    }

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
            background: `url(${background})`,
            icon: 'warning',
            title: action == 'C' ? 'Create New Admin Record ?' : '<?php echo 'Update Admin Record : ' . $Name . ' ?'; ?>',
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel',
            showCancelButton: true
        }).then((response) => {
            if(response.isConfirmed) {
                var country_code = $('#CountryCodeID').val();
                var name = ($('#Name').val()).toUpperCase();
                var gender = $('#Gender').val();
                var identification_number = $('#IdentificationNumber').val();
                var passport_number = ($('#PassportNumber').val()).toUpperCase();
                var mobile = $('#Mobile').val();
                var email = ($('#Email').val()).toUpperCase();
                var username = ($('#Username').val()).toUpperCase();
                var password = ($('#Password').val()).toUpperCase();
                var level = $('#Level').val();
                var access_control = ($('#AccessControl').val()).toString();
                if(country_code == null || name == '' || (action == 'C' && gender == null) || mobile == '' || email == '' || (action == 'C' && username == '') || (action == 'C' && password == '') || (action == 'C' && level == null) || access_control == '') {
                    Display_Message(background, 'Please Insert All Required Admin Information', null);
                } else {
                    if(action == 'C') {
                        var admin = [];
                        var url = '<?php echo base_url('Admin/Create') ?>';
                        admin.push({CountryCodeID:country_code, Name:name, Gender:gender, IdentificationNumber:identification_number, PassportNumber:passport_number, Mobile:mobile, Email:email, Username:username, Password:password, Level:level, AccessControl:access_control, InsertBy:session_id, InsertDate:current_datetime});
                        Submit_Admin(url, admin, null);
                    } else {
                        var dirty_fields = $('#form').dirty('showDirtyFields');
                        var admin_id = <?php echo $AdminID ?>;
                        var admin = [{AdminID:admin_id, UpdateBy:session_id, UpdateDate:current_datetime}];
                        var admin_log = [];
                        var url = '<?php echo base_url('Admin/Update') ?>';
                        for(var i = 0; i < dirty_fields.length; i++) {
                            var key = dirty_fields[i].id;
                            if(key != 'AccessControl') {
                                var value = key == 'Name' || key == 'PassportNumber' || key == 'Email' || key == 'Password' ? (dirty_fields[i].value).toUpperCase() : dirty_fields[i].value;

                                //Update Admin
                                admin[0][key] = value;

                                //Insert Admin Log
                                var default_value = null;
                                var country_codes = <?php echo count($country_codes) ?>;
                                switch(key) {
                                    case 'CountryCodeID':
                                        default_value = (Object.values(dirty_fields[i])[country_codes + 1]).dirtyInitialValue;
                                        break;
                                    case 'Gender':
                                        default_value = (Object.values(dirty_fields[i])[3]).dirtyInitialValue;
                                        break;
                                    case 'Level':
                                        default_value = (Object.values(dirty_fields[i])[4]).dirtyInitialValue;
                                        break;
                                    default:
                                        default_value = (Object.values(dirty_fields[i])[0]).dirtyInitialValue;
                                }
                                admin_log.push({AdminID:admin_id, Column:key, CurrentData:default_value, NewData:value, InsertBy:session_id, InsertDate:current_datetime});
                            }
                        }

                        // Action : Update Access Control
                        if(access_control != '<?php echo implode(',', $AccessControl); ?>') {
                            admin[0]['AccessControl'] = access_control;
                            admin_log.push({AdminID:admin_id, Column:'AccessControl', CurrentData:'<?php echo implode(',', $AccessControl); ?>', NewData:access_control, InsertBy:session_id, InsertDate:current_datetime});
                        }

                        var count = 0;
                        $.each(admin[0], function() {
                            count++;
                        });
                        if(count == 3) {
                            var url = '<?php echo base_url('Admin') ?>';
                            Display_Message(background, '<?php echo 'No Changes Detected In Admin Record : ' . $Name; ?>', url);
                        } else {
                            Submit_Admin(url, admin, admin_log);
                        }
                    }
                }
            }
        });
    });

    function Submit_Admin(url, admin, admin_log)
    {
        $.ajax({
            url: url,
            type: 'post',
            data: {
                admin: admin,
                admin_log: admin_log
            },
            dataType: 'json',
            success: function(status) {
                if(status == true) {
                    var url = '<?php echo base_url('Admin') ?>';
                    Display_Message(background, success_message, url);
                } else {
                    Display_Message(background, error_message, null);
                }
            },
            error: function() {
                Display_Message(background, error_message, null);
            }
        });
    }
    
    $('#form').dirty('isClean');
</script>