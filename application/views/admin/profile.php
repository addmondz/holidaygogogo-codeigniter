<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong><?php echo ucwords(strtolower($Level)) . ' : ' . $Name; ?></strong>
                    </h3>
                </div>
            </div>
            <div class="card-body">
                <form id="form">
                    <strong>Profile Information :</strong>
                    <br><br>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Name
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="text" id="Name" value="<?php echo $Name; ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-user"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Gender</label>
                                <select id="Gender" class="form-control selectpicker">
                                    <?php foreach(unserialize(GENDER) as $key => $value) { ?>
                                        <option <?php if($key == $Gender) { echo 'selected'; } ?> data-icon="<?php if($key == 'F') { echo 'la la-female'; } else { echo 'la la-male'; } ?> font-size-lg bs-icon" value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Identification Number</label>
                                <div class="input-icon">
                                    <input type="text" id="IdentificationNumber" value="<?php echo $IdentificationNumber; ?>" autocomplete="off" class="form-control">
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
                                    <input type="text" id="PassportNumber" value="<?php echo $PassportNumber; ?>" autocomplete="off" class="form-control">
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
                                <select title="--Select Country Code--" id="CountryCodeID" <?php if(!empty($country_codes)) { echo 'data-live-search="true"'; } ?> class="form-control selectpicker">
                                    <?php if(!empty($country_codes)) {
                                        foreach($country_codes as $country_code) { ?>
                                            <option <?php if($country_code->CountryCodeID == $CountryCodeID) { echo 'selected'; } ?> data-icon="la la-phone font-size-lg bs-icon" value="<?php echo $country_code->CountryCodeID; ?>"><?php echo $country_code->Country . ' ' . $country_code->CountryCode; ?></option>
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
                                    <input type="text" id="Mobile" value="<?php echo $Mobile; ?>" autocomplete="off" class="form-control">
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
                                    <input type="email" id="Email" value="<?php echo $Email; ?>" autocomplete="off" class="form-control">
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
                                <label>Username</label>
                                <div class="input-icon">
                                    <input disabled type="text" value="<?php echo $Username; ?>" class="form-control">
                                    <span>
                                        <i class="la la-user"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Password</label>
                                <div class="input-icon">
                                    <input type="password" id="Password" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-key"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-5">
                        <input type="button" value="Update Profile" class="btn btn-success font-weight-bold px-9 py-4" style="width:180px; margin-left:auto;">
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    var background = '<?php echo base_url('assets/image/sweetalert.jpg') ?>';
    var session_id = <?php echo $this->session->admin_id ?>;
    var current_datetime = '<?php echo date('Y-m-d H:i:s') ?>';

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
            title: 'Update Profile ?',
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel',
            showCancelButton: true
        }).then((action) => {
            if(action.isConfirmed) {
                var country_code = $('#CountryCodeID').val();
                var name = $('#Name').val();
                var mobile = $('#Mobile').val();
                var email = $('#Email').val();
                if(country_code == '' || name == '' || mobile == '' || email == '') {
                    Display_Message(background, 'Please Insert All Required Profile Information', null);
                } else {
                    var dirty_fields = $('#form').dirty('showDirtyFields');
                    if(dirty_fields.length > 0) {
                        var admin_id = <?php echo $AdminID ?>;
                        var admin = [{AdminID:admin_id, UpdateBy:session_id, UpdateDate:current_datetime}];
                        var admin_log = [];
                        var url = '<?php echo base_url('Profile') ?>';
                        for(var i = 0; i < dirty_fields.length; i++) {
                            var key = dirty_fields[i].id;
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
                                    default_value = (Object.values(dirty_fields[i])[2]).dirtyInitialValue;
                                    break;
                                default:
                                    default_value = (Object.values(dirty_fields[i])[0]).dirtyInitialValue;
                            }
                            admin_log.push({AdminID:admin_id, Column:key, CurrentData:default_value, NewData:value, InsertBy:session_id, InsertDate:current_datetime});
                        }
                        Submit_Profile(url, admin, admin_log);
                    } else {
                        var url = '<?php echo base_url('Dashboard') ?>';
                        Display_Message(background, 'No Changes Detected In Profile', url);
                    }
                }
            }
        });
    });

    function Submit_Profile(url, admin, admin_log)
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
                    var url = '<?php echo base_url('Dashboard') ?>';
                    Display_Message(background, 'Profile Successfully Updated', url);
                } else {
                    Display_Message(background, 'Profile Could Not Be Updated', null);
                }
            },
            error: function() {
                Display_Message(background, 'Profile Could Not Be Updated', null);
            }
        });
    }
    
    $('#form').dirty('isClean');
</script>