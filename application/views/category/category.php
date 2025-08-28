<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong><?php if(current_url() == base_url('Category/Create')) { echo 'New Category Record'; } else { echo 'Category Record : ' . $Name; } ?></strong>
                    </h3>
                </div>
            </div>
            <div class="card-body">
                <form id="form">
                    <strong>Category Information :</strong>
                    <br><br>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Name
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="text" id="Name" <?php if(current_url() == base_url('Category/Update')) { ?> value="<?php echo $Name; ?>" <?php } ?> autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-clipboard-list"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Category Code
                                    <?php if(current_url() == base_url('Category/Create')) { ?><span style="color:red;">*</span><?php } ?>
                                </label>
                                <select id="CategoryCodeID" data-live-search="true" class="form-control selectpicker">
                                    <option selected disabled data-icon="la la-clipboard-list font-size-lg bs-icon" value="">--SELECT CATEGORY CODE--</option>
                                    <?php foreach($category_codes as $category_code) { ?>
                                        <option <?php if(current_url() == base_url('Category/Update') && $category_code->CategoryCodeID == $CategoryCodeID) { echo 'selected'; } ?> data-icon="la la-clipboard-list font-size-lg bs-icon" value="<?php echo $category_code->CategoryCodeID; ?>"><?php echo $category_code->Name; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>City
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="text" id="City" <?php if(current_url() == base_url('Category/Update')) { ?> value="<?php echo $City; ?>" <?php } ?> autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-city"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>State
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="text" id="State" <?php if(current_url() == base_url('Category/Update')) { ?> value="<?php echo $State; ?>" <?php } ?> autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-city"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Country
                                    <?php if(current_url() == base_url('Category/Create')) { ?><span style="color:red;">*</span><?php } ?>
                                </label>
                                <select id="Country" data-live-search="true" class="form-control selectpicker">
                                    <option selected disabled data-icon="la la-globe font-size-lg bs-icon" value="">--SELECT COUNTRY--</option>
                                    <?php foreach($countries as $country) { ?>
                                        <option <?php if(current_url() == base_url('Category/Update') && $country->CountryCodeID == $Country) { echo 'selected'; } ?> data-icon="la la-globe font-size-lg bs-icon" value="<?php echo $country->CountryCodeID; ?>"><?php echo $country->Country; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Is Destination ?
                                    <?php if(current_url() == base_url('Category/Create')) { ?><span style="color:red;">*</span><?php } ?>
                                </label>
                                <select id="IsDestination" class="form-control selectpicker">
                                    <option selected disabled data-icon="la la-question-circle font-size-lg bs-icon" value="">--IS DESTINATION ?--</option>
                                    <?php foreach(unserialize(IS_DESTINATION) as $key => $value) { ?>
                                        <option <?php if(current_url() == base_url('Category/Update') && $key == $IsDestination) { echo 'selected'; } ?> data-icon="<?php if($key == 'YES') { echo 'la la-check-circle'; } else { echo 'la la-times-circle'; } ?> font-size-lg bs-icon" value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-5">
                        <input type="button" value="<?php if(current_url() == base_url('Category/Create')) { echo 'Create Category'; } else { echo 'Update Category'; } ?>" class="btn btn-success font-weight-bold px-9 py-4" style="width:180px; margin-left:auto;">
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
            url: '<?php echo base_url('Category/Detect') ?>',
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
            title: <?php if(current_url() == base_url('Category/Create')) { ?> 'Create New Category Record ?' <?php } else { ?> '<?php echo 'Update Category Record : ' . str_replace('\'', '', $Name) . ' ?'; ?>' <?php } ?>,
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel',
            showCancelButton: true
        }).then((action) => {
            if(action.isConfirmed) {
                var category_code = $('#CategoryCodeID').val();
                var name = ($('#Name').val()).toUpperCase();
                var city = ($('#City').val()).toUpperCase();
                var state = ($('#State').val()).toUpperCase();
                var country = $('#Country').val();
                var is_destination = $('#IsDestination').val();
                if(category_code == null || name == '' || city == '' || state == '' || country == null || is_destination == null) {
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert All Required Category Information', null);
                } else {
                    if(window.location.href == '<?php echo base_url('Category/Create'); ?>') {
                        var category = [];
                        category.push({CategoryCodeID:category_code, Name:name, City:city, State:state, Country:country, IsDestination:is_destination, InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});
                        Submit_Category('<?php echo base_url('Category/Create') ?>', category);
                    } else {
                        var category = [{CategoryID:<?php echo $CategoryID ?>, UpdateBy:<?php echo $this->session->userdata('admin_id') ?>, UpdateDate:'<?php echo date('Y-m-d H:i:s') ?>'}];
                        var dirty_fields = $('#form').dirty('showDirtyFields');
                        if(dirty_fields.length > 0) {
                            for(var i = 0; i < dirty_fields.length; i++) {
                                var key = dirty_fields[i].id;
                                var value = (dirty_fields[i].value).toUpperCase();
                                category[0][key] = value;
                            }
                        }
                        count = 0;
                        $.each(category[0], function() {
                            count++;
                        });
                        if(count == 3) {
                            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php echo 'No Changes Detected In Category Record : ' . str_replace('\'', '', $Name); ?>', '<?php echo base_url('Category') ?>');
                        } else {
                            Submit_Category('<?php echo base_url('Category/Update') ?>', category);
                        }
                    }
                }
            }
        });
    });

    function Submit_Category(url, category)
    {
        $.ajax({
            url: url,
            type: 'post',
            data: {
                category: category
            },
            success: function() {
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php if(current_url() == base_url('Category/Create')) { echo 'New'; } ?> Category Record <?php if(current_url() == base_url('Category/Update')) { echo ': ' . str_replace('\'', '', $Name); } ?> Successfully <?php if(current_url() == base_url('Category/Create')) { echo 'Created'; } else { echo 'Updated'; } ?>', '<?php echo base_url('Category') ?>');
            },
            error: function() {
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php if(current_url() == base_url('Category/Create')) { echo 'New'; } ?> Category Record <?php if(current_url() == base_url('Category/Update')) { echo ': ' . str_replace('\'', '', $Name); } ?> Could Not Be <?php if(current_url() == base_url('Category/Create')) { echo 'Created'; } else { echo 'Updated'; } ?>', null);
            }
        });
    }
    
    $('#form').dirty('isClean');
</script>