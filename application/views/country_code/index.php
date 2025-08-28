<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Country Code Records</strong>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('Country_Code/Create'); ?>" class="btn btn-primary font-weight-bold" style="width:220px;">
                        <i class="la la-phone"></i>Create Country Code
                    </a>
                    <?php $current_url = base_url($_SERVER['REQUEST_URI']); ?>
                </div>
            </div>
            <div class="card-body">
                <div class="accordion accordion-solid accordion-toggle-plus">
                    <div class="card">
                        <div class="card-header">
                            <div id="country_code_header" data-toggle="collapse" data-target="#country_code_info" class="card-title collapsed" style="font-size:13px;">Filter By Country Code Information</div>
                        </div>
                        <div id="country_code_info" class="collapse">
                            <div class="card-body">
                                <form action="<?php echo base_url('Country_Code') ?>" method="get" class="form">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Country</label>
                                                <div class="input-icon">
                                                    <input type="text" name="country" value="<?php if(!empty($this->input->get('country'))) { echo strtoupper($this->input->get('country')); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-globe"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Country Code</label>
                                                <div class="input-icon">
                                                    <input type="text" name="country_code" value="<?php if(!empty($this->input->get('country_code'))) { echo $this->input->get('country_code'); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-phone"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Currency Code</label>
                                                <div class="input-icon">
                                                    <input type="text" name="currency_code" value="<?php if(!empty($this->input->get('currency_code'))) { echo strtoupper($this->input->get('currency_code')); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-dollar"></i>
                                                    </span>
                                                </div>
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
                <div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if(empty($country_codes)) { echo 'style="overflow-x:auto;"'; } ?>>
                    <table id="kt_datatable" class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline">
                        <thead>
                            <tr>
                                <th style="text-align:center;">No.</th>
                                <th style="text-align:center;">Country</th>
                                <th style="text-align:center;">Country Code</th>
                                <th style="text-align:center;">Currency Code</th>
                                <th class="action" style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($country_codes)) { ?>
                                <td colspan="5" style="text-align:center; padding-top:10px; padding-bottom:10px;">Country Code Records Not Found</td>
                            <?php } else { ?>
                                <?php $count = 1; ?>
                                <?php foreach($country_codes as $country_code) { ?>
                                    <tr>
                                        <td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
                                        <td style="text-align:center;"><?php echo $country_code->Country; ?></td>
                                        <td style="text-align:center;"><?php echo $country_code->CountryCode; ?></td>
                                        <td style="text-align:center;"><?php echo $country_code->CurrencyCode; ?></td>
                                        <td style="text-align:center;">
                                            <div class="btn-group">
                                                <button type="button" data-toggle="dropdown" class="btn btn-light-primary btn-sm dropdown-toggle" style="padding-left:3px;"></button>
                                                <div class="dropdown-menu">
                                                    <button onclick="Delete_Record('<?php echo base_url('assets/image/sweetalert.jpg'); ?>', '<?php echo 'Country Code Record : ' . str_replace('\'', '', $country_code->Country); ?>', '<?php echo base_url('Country_Code/Delete'); ?>', 'country_code_id', <?php echo $country_code->CountryCodeID; ?>, '<?php echo $country_code->Status; ?>', '<?php if(strpos($current_url, '?') == true) { echo base_url('Country_Code?') . (explode('?', $current_url))[1]; } else { echo base_url('Country_Code'); } ?>')" class="dropdown-item" style="color:#E37383; font-size:11px;">Delete Country Code</button>
                                                    <a href="<?php echo base_url('Country_Code/Update?country_code_id=') . $country_code->CountryCodeID; ?>" class="dropdown-item" style="font-size:11px;">Update Country Code</a>
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
    <?php if(!empty($this->input->get('country')) || !empty($this->input->get('country_code')) || !empty($this->input->get('currency_code'))) { ?>
        $('#country_code_header').click();
    <?php } ?>
    
    $('#reset').click(function() {
        Reset('<?php echo base_url('Country_Code'); ?>');
    });
</script>