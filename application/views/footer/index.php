<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <?php if($this->session->flashdata('message_success')) { ?>
            <div class="alert alert-custom alert-light-success fade show mb-5">
                <div class="alert-icon">
                    <i class="la la-check-circle"></i>
                </div>
                <div class="alert-text"><?php echo $this->session->flashdata('message_success'); ?></div>
                <div class="alert-close">
                    <button type="button" data-dismiss="alert" class="close">
                        <span>
                            <i class="ki ki-close"></i>
                        </span>
                    </button>
                </div>
            </div>
        <?php } ?>
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Footer Records</strong>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <a href="<?php echo base_url('Footer/Create'); ?>" class="btn btn-primary font-weight-bold mr-1 mb-2" style="width:180px;">
                        <i class="la la-clipboard-list"></i>Create Footer
                    </a>
                    <?php $current_url = base_url($_SERVER['REQUEST_URI']); ?>
                    <a href="<?php if(strpos($current_url, '?') == true) { echo base_url('Footer/Download?') . (explode('?', $current_url))[1]; } else { echo base_url('Footer/Download'); } ?>" class="btn btn-light-warning font-weight-bold mb-2" style="width:180px;">
                        <i class="las la-arrow-circle-down"></i>Footer Records
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="accordion accordion-solid accordion-toggle-plus">
                    <div class="card">
                        <div class="card-header">
                            <div id="footer_header" data-toggle="collapse" data-target="#footer_info" class="card-title collapsed" style="font-size:13px;">Filter By Footer Information</div>
                        </div>
                        <div id="footer_info" class="collapse">
                            <div class="card-body">
                                <form action="<?php echo base_url('Footer') ?>" method="get" class="form">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>BC Title</label>
                                                <div class="input-icon">
                                                    <input type="text" name="booking_confirmation_title" value="<?php if(!empty($this->input->get('booking_confirmation_title'))) { echo strtoupper($this->input->get('booking_confirmation_title')); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-clipboard-list"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>TV Title</label>
                                                <div class="input-icon">
                                                    <input type="text" name="travel_voucher_title" value="<?php if(!empty($this->input->get('travel_voucher_title'))) { echo strtoupper($this->input->get('travel_voucher_title')); } ?>" autocomplete="off" class="form-control">
                                                    <span>
                                                        <i class="la la-clipboard-list"></i>
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
                <div class="dataTables_wrapper dt-bootstrap4 no-footer" <?php if(empty($footers)) { echo 'style="overflow-x:auto;"'; } ?>>
                    <table id="kt_datatable" class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline">
                        <thead>
                            <tr>
                                <th style="text-align:center;">No.</th>
                                <th style="text-align:center;">BC Title</th>
                                <th style="text-align:center;">TV Title</th>
                                <th class="action" style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($footers)) { ?>
                                <td colspan="4" style="text-align:center; padding-top:10px; padding-bottom:10px;">Footer Records Not Found</td>
                            <?php } else { ?>
                                <?php $count = 1; ?>
                                <?php foreach($footers as $footer) { ?>
                                    <tr>
                                        <td style="text-align:center; padding-top:15px; padding-bottom:15px;"><?php echo $count; ?></td>
                                        <td style="text-align:center;"><?php echo $footer->BookingConfirmationTitle; ?></td>
                                        <td style="text-align:center;"><?php echo $footer->TravelVoucherTitle; ?></td>
                                        <td style="text-align:center;">
                                            <div class="btn-group">
                                                <button type="button" data-toggle="dropdown" class="btn btn-light-primary btn-sm dropdown-toggle" style="padding-left:3px;"></button>
                                                <div class="dropdown-menu">
                                                    <button onclick="Delete_Record('<?php echo base_url('assets/image/sweetalert.jpg'); ?>', '<?php echo 'Footer Record : ' . str_replace('\'', '', $footer->BookingConfirmationTitle); ?>', '<?php echo base_url('Footer/Delete'); ?>', 'footer_id', <?php echo $footer->FooterID; ?>, '<?php echo $footer->Status; ?>', '<?php if(strpos($current_url, '?') == true) { echo base_url('Footer?') . (explode('?', $current_url))[1]; } else { echo base_url('Footer'); } ?>')" class="dropdown-item" style="color:#E37383; font-size:11px;">Delete Footer</button>
                                                    <a href="<?php echo base_url('Footer/Update?footer_id=') . $footer->FooterID; ?>" class="dropdown-item" style="font-size:11px;">Update Footer</a>
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
    <?php if(!empty($this->input->get('booking_confirmation_title')) || !empty($this->input->get('travel_voucher_title'))) { ?>
        $('#footer_header').click();
    <?php } ?>
    
    $('#reset').click(function() {
        Reset('<?php echo base_url('Footer'); ?>');
    });
</script>