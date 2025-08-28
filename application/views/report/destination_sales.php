<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Destination Sales</strong>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <?php $current_url = base_url($_SERVER['REQUEST_URI']); ?>
                    <a href="<?php if(strpos($current_url, '?') == true) { echo base_url('Report/Download?') . (explode('?', $current_url))[1]; } else { echo base_url('Report/Download'); } ?>" class="btn btn-light-warning font-weight-bold" style="width:180px;">
                        <i class="las la-arrow-circle-down"></i>Booking Records
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="accordion accordion-solid accordion-toggle-plus">
                    <div class="card">
                        <div class="card-header">
                            <div id="destination_sale_header" data-toggle="collapse" data-target="#destination_sale_info" class="card-title collapsed" style="font-size:13px;">Filter By Booking Information</div>
                        </div>
                        <div id="destination_sale_info" class="collapse">
                            <div class="card-body">
                                <form id="form" action="<?php echo base_url('Report/Destination_Sales') ?>" method="get" class="form">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Destination</label>
                                                <select name="destination" data-live-search="true" class="form-control selectpicker">
                                                    <option selected data-icon="la la-map-pin font-size-lg bs-icon" value="">--SELECT DESTINATION--</option>
                                                    <?php foreach($categories as $category) { ?>
                                                        <option data-icon="la la-map-pin font-size-lg bs-icon" value="<?php echo $category->CategoryID; ?>" <?php if(!empty($this->input->get('destination')) && $this->input->get('destination') == $category->CategoryID) { echo 'selected'; } ?>><?php echo $category->Name; ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
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
                <div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if(empty($destination_sales)) { echo 'style="overflow-x:auto;"'; } ?>>
                    <table id="kt_datatable" class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline">
                        <thead>
                            <tr>
                                <th style="text-align:center;">No.</th>
                                <th class="month" style="text-align:center;">Month</th>
                                <th style="text-align:center;">Destination</th>
                                <th class="subtotal" style="text-align:center;">Net Sales (RM)</th>
                                <th class="profit" style="text-align:center;">Net Profit (RM)</th>
                                <th style="text-align:center;">Net Profit Margin (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($destination_sales)) { ?>
                                <td colspan="6" style="text-align:center; padding-top:10px; padding-bottom:10px;">Destination Sales Not Found</td>
                            <?php } else { ?>
                                <?php $count = 1; ?>
                                <?php foreach($destination_sales as $destination_sale) { ?>
                                    <tr>
                                        <td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
                                        <td style="text-align:center;"><?php echo $destination_sale->Month; ?></td>
                                        <td style="text-align:center;"><?php echo $destination_sale->Destination; ?></td>
                                        <td style="text-align:center;"><?php echo $destination_sale->NetTotal; ?></td>
                                        <td style="color:<?php if($destination_sale->Profit < 0) { echo '#FF2400;'; } else if($destination_sale->Profit == 0) { echo '#F4BB44;'; } else { echo '#00A36C;'; } ?> text-align:center;"><?php echo $destination_sale->Profit; ?></td>
                                        <td style="color:<?php if($destination_sale->Margin < 0) { echo '#FF2400;'; } else if($destination_sale->Margin == 0) { echo '#F4BB44;'; } else { echo '#00A36C;'; } ?> text-align:center;"><?php echo $destination_sale->Margin; ?></td>
                                    </tr>
                                    <?php $count++; ?>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <br>
                <div class="row">
                    <div class="col-md-12 pt-3 pb-3" style="background-color:white; border:3px solid #D7E2F2; border-radius:8px;">
                        <div class="row">
                            <div class="col-md-6 mb-7 mb-md-0">
                                <label style="color:#C4B454;">Total Net Sales (RM)</label>
                                <div class="input-icon">
                                    <input disabled type="text" value="<?php echo $total_sales; ?>" class="form-control" style="text-align:right;">
                                    <span>
                                        <i class="la la-dollar"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label style="color:#FFC000;">Total Net Profit (RM)</label>
                                <div class="input-icon">
                                    <input disabled type="text" value="<?php echo $total_net_profit; ?>" class="form-control" style="text-align:right;">
                                    <span>
                                        <i class="la la-dollar"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
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

    <?php if(!empty($this->input->get('destination')) || !empty($this->input->get('sales_agent')) || !empty($this->input->get('travel_date'))) { ?>
        $('#destination_sale_header').click();
    <?php } ?>

    $('#reset').click(function() {
        Reset('<?php echo base_url('Report/Destination_Sales'); ?>');
    });
</script>