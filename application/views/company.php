<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong><?php echo 'Company : ' . $Name; ?></strong>
                    </h3>
                </div>
            </div>
            <div class="card-body">
                <form id="form">
                    <strong>Company Information :</strong>
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
                                        <i class="la la-building"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Registration Number
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="text" id="RegistrationNumber" value="<?php echo $RegistrationNumber; ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-registered"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>License Number
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="text" id="LicenseNumber" value="<?php echo $LicenseNumber; ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-registered"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Address
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="text" id="Address" value="<?php echo $Address; ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-map-marker"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Website
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="text" id="Website" value="<?php echo $Website; ?>" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-globe"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-5">
                        <input type="button" value="Update Company Profile" class="btn btn-success font-weight-bold px-9 py-4" style="width:220px; margin-left:auto;">
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
            title: 'Update Company Profile ?',
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel',
            showCancelButton: true
        }).then((action) => {
            if(action.isConfirmed) {
                var name = $('#Name').val();
                var registration_number = $('#RegistrationNumber').val();
                var license_number = $('#LicenseNumber').val();
                var address = $('#Address').val();
                var website = $('#Website').val();
                if(name == '' || registration_number == '' || license_number == '' || address == '' || website == '') {
                    Display_Message(background, 'Please Insert All Required Company Information', null);
                } else {
                    var dirty_fields = $('#form').dirty('showDirtyFields');
                    if(dirty_fields.length > 0) {
                        var company_id = <?php echo $CompanyID ?>;
                        var company = [{CompanyID:company_id, UpdateBy:session_id, UpdateDate:current_datetime}];
                        var company_log = [];
                        var url = '<?php echo base_url('Company') ?>';
                        for(var i = 0; i < dirty_fields.length; i++) {
                            var key = dirty_fields[i].id;
                            var value = (dirty_fields[i].value).toUpperCase();

                            //Update Company
                            company[0][key] = value;

                            //Insert Company Log
                            var default_value = (Object.values(dirty_fields[i])[0]).dirtyInitialValue;
                            company_log.push({CompanyID:company_id, Column:key, CurrentData:default_value, NewData:value, InsertBy:session_id, InsertDate:current_datetime});
                        }
                        Submit_Company(url, company, company_log);
                    } else {
                        var url = '<?php echo base_url('Dashboard') ?>';
                        Display_Message(background, 'No Changes Detected In Company Profile', url);
                    }
                }
            }
        });
    });

    function Submit_Company(url, company, company_log) 
    {
        $.ajax({
            url: url,
            type: 'post',
            data: {
                company: company,
                company_log: company_log
            },
            dataType: 'json',
            success: function(status) {
                if(status == true) {
                    var url = '<?php echo base_url('Dashboard') ?>';
                    Display_Message(background, 'Company Profile Successfully Updated', url);
                } else {
                    Display_Message(background, 'Company Profile Could Not Be Updated', null);
                }
            },
            error: function() {
                Display_Message(background, 'Company Profile Could Not Be Updated', null);
            }
        });
    }
    
    $('#form').dirty('isClean');
</script>