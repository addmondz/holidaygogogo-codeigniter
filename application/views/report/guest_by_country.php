<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Guest By Country</strong>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <?php $current_url = base_url($_SERVER['REQUEST_URI']); ?>
                    <a href="<?php if(strpos($current_url, '?') == true) { echo base_url('Report/Download_Guest_By_Country?') . (explode('?', $current_url))[1]; } else { echo base_url('Report/Download_Guest_By_Country'); } ?>" class="btn btn-light-warning font-weight-bold" style="width:180px;">
                        <i class="las la-arrow-circle-down"></i>Guest By Country
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="accordion accordion-solid accordion-toggle-plus">
                    <div class="card">
                        <div class="card-header">
                            <div id="guest_by_country_header" data-toggle="collapse" data-target="#guest_by_country_info" class="card-title collapsed" style="font-size:13px;">Filter By Category / Booking Information</div>
                        </div>
                        <div id="guest_by_country_info" class="collapse">
                            <div class="card-body">
                                <form id="form" action="<?php echo base_url('Report/Guest_By_Country') ?>" method="get" class="form">
                                    <strong>Category :</strong>
                                    <br><br>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Country</label>
                                                <select name="country" data-live-search="true" class="form-control selectpicker">
                                                    <option selected data-icon="la la-globe font-size-lg bs-icon" value="">--SELECT COUNTRY--</option>
                                                    <?php foreach($country_codes as $country_code) { ?>
                                                        <option data-icon="la la-globe font-size-lg bs-icon" value="<?php echo $country_code->CountryCodeID; ?>" <?php if(!empty($this->input->get('country')) && $this->input->get('country') == $country_code->CountryCodeID) { echo 'selected'; } ?>><?php echo $country_code->Country; ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <strong>Booking :</strong>
                                    <br><br>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Sales Agent</label>
                                                <select name="sales_agent" data-live-search="true" class="form-control selectpicker">
                                                    <option selected data-icon="la la-user-alt font-size-lg bs-icon" value="">--SELECT SALES AGENT--</option>
                                                    <?php foreach($admins as $admin) { ?>
                                                        <option data-icon="la la-user-alt font-size-lg bs-icon" value="<?php echo $admin->AdminID; ?>" <?php if(!empty($this->input->get('sales_agent')) && $this->input->get('sales_agent') == $admin->AdminID) { echo 'selected'; } ?>><?php echo $admin->Name; ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Travel Date
                                                    <a onclick="Reset_Travel_Date()" class="btn btn-icon btn-light-warning btn-xs">
                                                        <i class="la la-undo"></i>
                                                    </a>
                                                </label>
                                                <div id="kt_daterangepicker_4" class="input-icon">
                                                    <input readonly type="text" name="travel_date" value="<?php if(!empty($this->input->get('travel_date'))) { echo $this->input->get('travel_date'); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-calendar"></i>
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
                <div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if(empty($guest_by_country)) { echo 'style="overflow-x:auto;"'; } ?>>
                    <table id="kt_datatable" class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline">
                        <thead>
                            <tr>
                                <th style="text-align:center;">No.</th>
                                <th class="month" style="text-align:center;">Month</th>
                                <th style="text-align:center;">Country</th>
                                <th style="text-align:center;">Total Adult</th>
                                <th style="text-align:center;">Total Children</th>
                                <th style="text-align:center;">Total Infant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($guest_by_country)) { ?>
                                <td colspan="6" style="text-align:center; padding-top:10px; padding-bottom:10px;">Guest By Country Not Found</td>
                            <?php } else { ?>
                                <?php $count = 1; ?>
                                <?php foreach($guest_by_country as $guest) { ?>
                                    <tr>
                                        <td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
                                        <td style="text-align:center;"><?php echo $guest->Month; ?></td>
                                        <td style="text-align:center;"><?php echo $guest->Country; ?></td>
                                        <td style="text-align:center;"><?php echo $guest->TotalAdult; ?></td>
                                        <td style="text-align:center;"><?php echo $guest->TotalChildren; ?></td>
                                        <td style="text-align:center;"><?php echo $guest->TotalInfant; ?></td>
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
    var current_date = (new Date()).toLocaleDateString();

    $('#kt_daterangepicker_4').on('apply.daterangepicker', function(event, daterange) {
        var start_date = (new Date(daterange.startDate._d)).toLocaleDateString();
        var end_date = (new Date(daterange.endDate._d)).toLocaleDateString();
        if(start_date == current_date && end_date == current_date) {
            $('input[name="travel_date"]').val(moment().format('DD/MM/YYYY') + ' - ' + moment().format('DD/MM/YYYY'));
        }
    });

    function Reset_Travel_Date() {
        $('input[name="travel_date"]').val('');
    }

    <?php if(!empty($this->input->get('country')) || !empty($this->input->get('sales_agent')) || !empty($this->input->get('travel_date'))) { ?>
        $('#guest_by_country_header').click();
    <?php } ?>

    $('#reset').click(function() {
        Reset('<?php echo base_url('Report/Guest_By_Country'); ?>');
    });
</script>