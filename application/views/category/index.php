<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Category Records</strong>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('Category/Create'); ?>" class="btn btn-primary font-weight-bold" style="width:180px;">
                        <i class="la la-clipboard-list"></i>Create Category
                    </a>
                    <?php $current_url = base_url($_SERVER['REQUEST_URI']); ?>
                </div>
            </div>
            <div class="card-body">
                <div class="accordion accordion-solid accordion-toggle-plus">
                    <div class="card">
                        <div class="card-header">
                            <div id="category_header" data-toggle="collapse" data-target="#category_info" class="card-title collapsed" style="font-size:13px;">Filter By Category Information</div>
                        </div>
                        <div id="category_info" class="collapse">
                            <div class="card-body">
                                <form action="<?php echo base_url('Category') ?>" method="get" class="form">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Category Code</label>
                                                <select name="category_code" data-live-search="true" class="form-control selectpicker">
                                                    <option selected data-icon="la la-clipboard-list font-size-lg bs-icon" value="">--SELECT CATEGORY CODE--</option>
                                                    <?php foreach($category_codes as $category_code) { ?>
                                                        <option data-icon="la la-clipboard-list font-size-lg bs-icon" value="<?php echo $category_code->CategoryCodeID; ?>" <?php if(!empty($this->input->get('category_code')) && $this->input->get('category_code') == $category_code->CategoryCodeID) { echo 'selected'; } ?>><?php echo $category_code->Name; ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Name</label>
                                                <div class="input-icon">
                                                    <input type="text" name="name" value="<?php if(!empty($this->input->get('name'))) { echo strtoupper($this->input->get('name')); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-clipboard-list"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>City</label>
                                                <div class="input-icon">
                                                    <input type="text" name="city" value="<?php if(!empty($this->input->get('city'))) { echo strtoupper($this->input->get('city')); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-city"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>State</label>
                                                <div class="input-icon">
                                                    <input type="text" name="state" value="<?php if(!empty($this->input->get('state'))) { echo strtoupper($this->input->get('state')); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-city"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Country</label>
                                                <select name="country" data-live-search="true" class="form-control selectpicker">
                                                    <option selected data-icon="la la-globe font-size-lg bs-icon" value="">--SELECT COUNTRY--</option>
                                                    <?php foreach($countries as $country) { ?>
                                                        <option data-icon="la la-globe font-size-lg bs-icon" value="<?php echo $country->CountryCodeID; ?>" <?php if(!empty($this->input->get('country')) && $this->input->get('country') == $country->CountryCodeID) { echo 'selected'; } ?>><?php echo $country->Country; ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Is Destination ?</label>
                                                <select name="is_destination" class="form-control selectpicker">
                                                    <option selected data-icon="la la-question-circle font-size-lg bs-icon" value="">--IS DESTINATION ?--</option>
                                                    <?php foreach(unserialize(IS_DESTINATION) as $key => $value) { ?>
                                                        <option data-icon="<?php if($key == 'YES') { echo 'la la-check-circle'; } else { echo 'la la-times-circle'; } ?> font-size-lg bs-icon" value="<?php echo $key; ?>" <?php if(!empty($this->input->get('is_destination')) && $this->input->get('is_destination') == $key) { echo 'selected'; } ?>><?php echo $value; ?></option>
                                                    <?php } ?>
                                                </select>
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
                <div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if(empty($categories)) { echo 'style="overflow-x:auto;"'; } ?>>
                    <table id="kt_datatable" class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline">
                        <thead>
                            <tr>
                                <th style="text-align:center;">No.</th>
                                <th style="text-align:center;">Name</th>
                                <th style="text-align:center;">City</th>
                                <th style="text-align:center;">State</th>
                                <th class="action" style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($categories)) { ?>
                                <td colspan="5" style="text-align:center; padding-top:10px; padding-bottom:10px;">Category Records Not Found</td>
                            <?php } else { ?>
                                <?php $count = 1; ?>
                                <?php foreach($categories as $category) { ?>
                                    <tr>
                                        <td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
                                        <td style="text-align:center;"><?php echo $category->Name; ?></td>
                                        <td style="text-align:center;"><?php echo $category->City; ?></td>
                                        <td style="text-align:center;"><?php echo $category->State; ?></td>
                                        <td style="text-align:center;">
                                            <div class="btn-group">
                                                <button type="button" data-toggle="dropdown" class="btn btn-light-primary btn-sm dropdown-toggle" style="padding-left:3px;"></button>
                                                <div class="dropdown-menu">
                                                    <button onclick="Delete_Record('<?php echo base_url('assets/image/sweetalert.jpg'); ?>', '<?php echo 'Category Record : ' . str_replace('\'', '', $category->Name); ?>', '<?php echo base_url('Category/Delete'); ?>', 'category_id', <?php echo $category->CategoryID; ?>, '<?php echo $category->Status; ?>', '<?php if(strpos($current_url, '?') == true) { echo base_url('Category?') . (explode('?', $current_url))[1]; } else { echo base_url('Category'); } ?>')" class="dropdown-item" style="color:#E37383; font-size:11px;">Delete Category</button>
                                                    <a href="<?php echo base_url('Category/Update?category_id=') . $category->CategoryID; ?>" class="dropdown-item" style="font-size:11px;">Update Category</a>
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

<script>
    <?php if(!empty($this->input->get('category_code')) || !empty($this->input->get('name')) || !empty($this->input->get('city')) || !empty($this->input->get('state')) || !empty($this->input->get('country')) || !empty($this->input->get('is_destination'))) { ?>
        $('#category_header').click();
    <?php } ?>
    
    $('#reset').click(function() {
        Reset('<?php echo base_url('Category'); ?>');
    });
</script>