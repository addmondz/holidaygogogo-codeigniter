<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong><?php if(current_url() == base_url('Footer/Create')) { echo 'New Footer Record'; } else { echo 'Footer Record : ' . $BookingConfirmationTitle; } ?></strong>
                    </h3>
                </div>
            </div>
            <div class="card-body">
                <form id="form" action="<?php if(current_url() == base_url('Footer/Create')) { echo base_url('Footer/Create'); } else { echo base_url('Footer/Update?footer_id=') . $this->input->get('footer_id'); } ?>" method="post">
                    <strong>Footer Information :</strong>
                    <br><br>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Booking Confirmation Title
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="text" name="booking_confirmation_title" <?php if(current_url() == base_url('Footer/Update')) { ?> value="<?php echo $BookingConfirmationTitle; ?>" <?php } ?> onchange="Change_BC_And_TV_Title('BC')" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-clipboard-list"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Booking Confirmation Content
                                    <span style="color:red;">*</span>
                                </label>
                                <textarea name="booking_confirmation_content" id="kt-tinymce-4" autocomplete="off" class="tox-target"><?php if(current_url() == base_url('Footer/Update')) { echo $BookingConfirmationContent; } ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Travel Voucher Title
                                    <span style="color:red;">*</span>
                                </label>
                                <div class="input-icon">
                                    <input type="text" name="travel_voucher_title" <?php if(current_url() == base_url('Footer/Update')) { ?> value="<?php echo $TravelVoucherTitle; ?>" <?php } ?> onchange="Change_BC_And_TV_Title('TV')" autocomplete="off" class="form-control">
                                    <span>
                                        <i class="la la-clipboard-list"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Travel Voucher Content
                                    <span style="color:red;">*</span>
                                </label>
                                <textarea name="travel_voucher_content" id="kt-tinymce-5" autocomplete="off" class="tox-target"><?php if(current_url() == base_url('Footer/Update')) { echo $TravelVoucherContent; } ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Key Contacts</label>
                                <textarea name="key_contacts" id="kt-tinymce-6" autocomplete="off" class="tox-target"><?php echo isset($KeyContacts) ? $KeyContacts : ''; ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Special Remarks</label>
                                <textarea name="special_remarks" id="kt-tinymce-7" autocomplete="off" class="tox-target"><?php echo isset($SpecialRemarks) ? $SpecialRemarks : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-5">
                        <input type="submit" value="<?php if(current_url() == base_url('Footer/Create')) { echo 'Create Footer'; } else { echo 'Update Footer'; } ?>" class="btn btn-success font-weight-bold px-9 py-4" style="width:180px; margin-left:auto;">
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function Change_BC_And_TV_Title(value)
    {
        if(value == 'BC') {
            var booking_confirmation_title = ($('input[name="booking_confirmation_title"]').val()).toUpperCase();
            var travel_voucher_title = null;
        } else {
            var booking_confirmation_title = null;
            var travel_voucher_title = ($('input[name="travel_voucher_title"]').val()).toUpperCase();
        }
        $.ajax({
            url: '<?php echo base_url('Footer/Detect') ?>',
            type: 'post',
            data: {
                booking_confirmation_title: booking_confirmation_title,
                travel_voucher_title: travel_voucher_title
            },
            dataType: 'json',
            success: function(redundant_title) {
                if(redundant_title) {
                    if(value == 'BC') {
                        Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Redundant Booking Confirmation Title Detected', null);
                        $('input[name="booking_confirmation_title"]').val('');
                    } else {
                        Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Redundant Travel Voucher Title Detected', null);
                        $('input[name="travel_voucher_title"]').val('');
                    }
                }
            }
        });
    }
    
    $('input[type="submit"]').click(function(event) {
        event.preventDefault();
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
            title: <?php if(current_url() == base_url('Footer/Create')) { ?> 'Create New Footer Record ?' <?php } else { ?> '<?php echo 'Update Footer Record : ' . str_replace('\'', '', $BookingConfirmationTitle) . ' ?'; ?>' <?php } ?>,
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel',
            showCancelButton: true
        }).then((action) => {
            if(action.isConfirmed) {
                var booking_confirmation_title = $('input[name="booking_confirmation_title"]').val();
                var travel_voucher_title = $('input[name="travel_voucher_title"]').val();
                var booking_confirmation_content = '';
                var travel_voucher_content = '';
                // Get content from specific TinyMCE editors (kt-tinymce-4 and kt-tinymce-5 are required)
                for(i = 0; i < tinyMCE.editors.length; i++) {
                    if(tinyMCE.editors[i].id == 'kt-tinymce-4') {
                        booking_confirmation_content = tinyMCE.editors[i].getContent();
                    }
                    if(tinyMCE.editors[i].id == 'kt-tinymce-5') {
                        travel_voucher_content = tinyMCE.editors[i].getContent();
                    }
                }
                if(booking_confirmation_title == '' || travel_voucher_title == '' || booking_confirmation_content == '' || travel_voucher_content == '') {
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert All Required Footer Information', null);
                } else {
                    $('#form').submit();
                }
            }
        });
    });
</script>