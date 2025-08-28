<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong><?php if(current_url() == base_url('Country_Code/Create')) { echo 'New Country Code Record'; } else { echo 'Country Code Record : ' . $Country; } ?></strong>
                    </h3>
                </div>
            </div>
            <div class="card-body">
                <form id="form">
                    <strong>Country Code Information :</strong>
                    <br><br>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Country
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="text" id="Country" <?php if(current_url() == base_url('Country_Code/Update')) { ?> value="<?php echo $Country; ?>" <?php } ?> autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-globe"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Country Code
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="text" id="CountryCode" <?php if(current_url() == base_url('Country_Code/Update')) { ?> value="<?php echo $CountryCode; ?>" <?php } ?> autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-phone"></i>
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
                                    <input type="text" id="CurrencyCode" <?php if(current_url() == base_url('Country_Code/Update')) { ?> value="<?php echo $CurrencyCode; ?>" <?php } ?> autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-dollar"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-5">
                        <input type="button" value="<?php if(current_url() == base_url('Country_Code/Create')) { echo 'Create Country Code'; } else { echo 'Update Country Code'; } ?>" class="btn btn-success font-weight-bold px-9 py-4" style="width:220px; margin-left:auto;">
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    $('#Country').change(function() {
        var country = ($('#Country').val()).toUpperCase();
        $.ajax({
            url: '<?php echo base_url('Country_Code/Detect') ?>',
            type: 'post',
            data: {
                country: country
            },
            dataType: 'json',
            success: function(redundant_country) {
                if(redundant_country) {
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Redundant Country Detected', null);
                    $('#Country').val('');
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
            title: <?php if(current_url() == base_url('Country_Code/Create')) { ?> 'Create New Country Code Record ?' <?php } else { ?> '<?php echo 'Update Country Code Record : ' . str_replace('\'', '', $Country) . ' ?'; ?>' <?php } ?>,
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel',
            showCancelButton: true
        }).then((action) => {
            if(action.isConfirmed) {
                var country = ($('#Country').val()).toUpperCase();
                var currency_code = ($('#CurrencyCode').val()).toUpperCase();
                if(country == '' || $('#CountryCode').val() == '' || currency_code == '') {
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert All Required Country Code Information', null);
                } else {
                    if(window.location.href == '<?php echo base_url('Country_Code/Create'); ?>') {
                        var country_code = [];
                        country_code.push({Country:country, CountryCode:$('#CountryCode').val(), CurrencyCode:currency_code, InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});
                        Submit_Country_Code('<?php echo base_url('Country_Code/Create') ?>', country_code);
                    } else {
                        var country_code = [{CountryCodeID:<?php echo $CountryCodeID ?>, UpdateBy:<?php echo $this->session->userdata('admin_id') ?>, UpdateDate:'<?php echo date('Y-m-d H:i:s') ?>'}];
                        var dirty_fields = $('#form').dirty('showDirtyFields');
                        if(dirty_fields.length > 0) {
                            for(var i = 0; i < dirty_fields.length; i++) {
                                var key = dirty_fields[i].id;
                                var value = (dirty_fields[i].value).toUpperCase();
                                country_code[0][key] = value;
                            }
                        }
                        count = 0;
                        $.each(country_code[0], function() {
                            count++;
                        });
                        if(count == 3) {
                            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php echo 'No Changes Detected In Country Code Record : ' . str_replace('\'', '', $Country); ?>', '<?php echo base_url('Country_Code') ?>');
                        } else {
                            Submit_Country_Code('<?php echo base_url('Country_Code/Update') ?>', country_code);
                        }
                    }
                }
            }
        });
    });

    function Submit_Country_Code(url, country_code)
    {
        $.ajax({
            url: url,
            type: 'post',
            data: {
                country_code: country_code
            },
            success: function() {
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php if(current_url() == base_url('Country_Code/Create')) { echo 'New'; } ?> Country Code Record <?php if(current_url() == base_url('Country_Code/Update')) { echo ': ' . str_replace('\'', '', $Country); } ?> Successfully <?php if(current_url() == base_url('Country_Code/Create')) { echo 'Created'; } else { echo 'Updated'; } ?>', '<?php echo base_url('Country_Code') ?>');
            },
            error: function() {
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php if(current_url() == base_url('Country_Code/Create')) { echo 'New'; } ?> Country Code Record <?php if(current_url() == base_url('Country_Code/Update')) { echo ': ' . str_replace('\'', '', $Country); } ?> Could Not Be <?php if(current_url() == base_url('Country_Code/Create')) { echo 'Created'; } else { echo 'Updated'; } ?>', null);
            }
        });
    }
    
    $('#form').dirty('isClean');
</script>