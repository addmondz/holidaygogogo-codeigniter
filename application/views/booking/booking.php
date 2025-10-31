
<div class="d-flex flex-column-fluid">

    <div class="container-fluid">

        <div class="card card-custom mb-5">

            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">

                <div class="card-title">

                    <h3 class="card-label" style="color:#6082B6;">

                        <strong><?php if(current_url() == base_url('Booking/Create') || current_url() == base_url('Booking/Duplicate')) { echo 'New Booking Record'; } else { echo 'Booking Record : ' . $BookingNumber; } ?></strong>

                    </h3>

                </div>

            </div>

            <div class="card-body">

                <form id="form">

                    <strong>Booking Information :</strong>

                    <br><br>

                    <div class="row">

                        <div class="col-md-6">

                            <div class="form-group">

                                <label>Booking Number</label>

                                <div class="input-icon">

                                    <input type="text" id="booking_number" <?php if(current_url() == base_url('Booking/Update')) { ?> disabled value="<?php echo $BookingNumber; ?>" <?php } ?> autocomplete="off" class="form-control">

                                    <span>

                                        <i class="la la-suitcase"></i>

                                    </span>

                                </div>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="form-group">

                                <label>Reservation Number

                                    <span style="color:red;">*</span>

                                </label>

                                <div class="input-icon">

                                    <input type="text" id="ReservationNumber" <?php if(current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) { ?> value="<?php echo $ReservationNumber; ?>" <?php } ?> autocomplete="off" class="form-control">

                                    <span>

                                        <i class="la la-suitcase"></i>

                                    </span>

                                </div>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="form-group">

                                <label>Sales Agent

                                    <?php if($this->session->userdata('level') != 20 && current_url() == base_url('Booking/Create')) { ?><span style="color:red;">*</span><?php } ?>

                                </label>

                                <?php if($this->session->userdata('level') != 20) { ?>

                                    <select id="SalesAgent" data-live-search="true" class="form-control selectpicker">

                                        <option selected disabled data-icon="la la-user-alt font-size-lg bs-icon" value="">--SELECT SALES AGENT--</option>

                                        <?php foreach($admins as $admin) { ?>

                                            <option <?php if((current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) && $admin->AdminID == $SalesAgent) { echo 'selected'; } ?> data-icon="la la-user-alt font-size-lg bs-icon" value="<?php echo $admin->AdminID; ?>"><?php echo $admin->Name; ?></option>

                                        <?php } ?>

                                    </select>

                                <?php } else { ?>

                                    <div class="input-icon">

                                        <input disabled type="text" value="<?php if(current_url() == base_url('Booking/Create')) { echo $this->session->userdata('name'); } else { echo $SalesAgentName; } ?>" class="form-control">

                                        <span>

                                            <i class="la la-user-alt"></i>

                                        </span>

                                    </div>

                                <?php } ?>

                            </div>

                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Customer <span style="color:red;">*</span></label>

                                <div class="input-icon position-relative">
                                    <input type="text" 
                                        id="Customer" 
                                        name="Customer"
                                        <?php if (current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) { ?> 
                                            value="<?php echo $Customer; ?>" 
                                        <?php } ?> 
                                        autocomplete="off" 
                                        class="form-control" 
                                        placeholder="Search or select customer">
                                    <small id="customerInfo" class="form-text text-muted">&laquo; New Customer &raquo;</small>

                                    <!-- Hidden field to detect existing customer -->
                                    <?php 
                                    $is_edit_page = in_array(current_url(), [base_url('Booking/Update'), base_url('Booking/Duplicate')]);
                                    $customer_id_value = ($is_edit_page && !empty($CustomerID)) ? $CustomerID : '';
                                    ?>
                                    <input type="hidden" id="CustomerID" name="CustomerID" value="<?= htmlspecialchars($customer_id_value, ENT_QUOTES) ?>">


                                    <span><i class="la la-user"></i></span>

                                    <div id="customerResults" 
                                        class="list-group position-absolute w-100 shadow-sm" 
                                        style="z-index:1000; display:none; top:100%; left:0; max-height:200px; overflow-y:auto;">
                                    </div>
                                </div>
                            </div>
                        </div>



                        <div class="col-md-6">

                            <div class="form-group">

                                <label>Country Code

                                    <?php if(current_url() == base_url('Booking/Create')) { ?><span style="color:red;">*</span><?php } ?>

                                </label>

                                <select id="CountryCodeID" data-live-search="true" class="form-control selectpicker">

                                    <option selected disabled data-icon="la la-phone font-size-lg bs-icon" value="">--SELECT COUNTRY CODE--</option>

                                    <?php foreach($country_codes as $country_code) { ?>

                                        <option <?php if((current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) && $country_code->CountryCodeID == $CustomerCountryCode) { echo 'selected'; } ?> data-icon="la la-phone font-size-lg bs-icon" value="<?php echo $country_code->CountryCodeID; ?>"><?php echo $country_code->Country . ' ' . $country_code->CountryCode; ?></option>

                                    <?php } ?>

                                </select>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="form-group">

                                <label>Mobile

                                    <span style="color:red;">*</span>

                                </label>

                                <div class="input-icon">

                                    <input type="text" id="Mobile" <?php if(current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) { ?> value="<?php echo $CustomerMobile; ?>" <?php } ?> autocomplete="off" class="form-control">

                                    <span>

                                        <i class="la la-mobile"></i>

                                    </span>

                                </div>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="form-group">

                                <label>Destination

                                    <?php if(current_url() == base_url('Booking/Create')) { ?><span style="color:red;">*</span><?php } ?>

                                </label>

                                <select id="Destination" data-live-search="true" class="form-control selectpicker">

                                    <option selected disabled data-icon="la la-map-pin font-size-lg bs-icon" value="">--SELECT DESTINATION--</option>

                                    <?php foreach($categories as $category) { ?>

                                        <option <?php if((current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) && $category->CategoryID == $Destination) { echo 'selected'; } ?> data-icon="la la-map-pin font-size-lg bs-icon" value="<?php echo $category->CategoryID; ?>"><?php echo $category->Name; ?></option>

                                    <?php } ?>

                                </select>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="form-group">

                                <label>Travel Date

                                    <span style="color:red;">*</span>

                                </label>

                                <div id="kt_daterangepicker_4" class="input-icon">

                                    <input readonly type="text" id="TravelDate" <?php if(current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) { ?> value="<?php echo $TravelDate; ?>" <?php } ?> autocomplete="off" class="form-control">

                                    <span>

                                        <i class="la la-calendar"></i>

                                    </span>

                                </div>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="form-group">

                                <label>Adult</label>

                                <div class="input-icon">

                                    <input <?php if(current_url() == base_url('Booking/Update')) { echo 'disabled'; } ?> type="text" id="Adult" <?php if(current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) { ?> value="<?php echo $Adult; ?>" <?php } ?> autocomplete="off" class="form-control">

                                    <span>

                                        <i class="la la-male"></i>

                                    </span>

                                </div>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="form-group">

                                <label>Children</label>

                                <div class="input-icon">

                                    <input <?php if(current_url() == base_url('Booking/Update')) { echo 'disabled'; } ?> type="text" id="Children" <?php if(current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) { ?> value="<?php echo $Children; ?>" <?php } ?> autocomplete="off" class="form-control">

                                    <span>

                                        <i class="la la-child"></i>

                                    </span>

                                </div>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="form-group">

                                <label>Infant</label>

                                <div class="input-icon">

                                    <input <?php if(current_url() == base_url('Booking/Update')) { echo 'disabled'; } ?> type="text" id="Infant" <?php if(current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) { ?> value="<?php echo $Infant; ?>" <?php } ?> autocomplete="off" class="form-control">

                                    <span>

                                        <i class="la la-baby-carriage"></i>

                                    </span>

                                </div>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="form-group">

                                <label>Remark</label>

                                <div class="input-icon">

                                    <input type="text" id="BookingRemark" <?php if(current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) { ?> value="<?php echo $BookingRemark; ?>" <?php } ?> autocomplete="off" class="form-control">

                                    <span>

                                        <i class="la la-pencil-alt"></i>

                                    </span>

                                </div>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="form-group">

                                <label>Source

                                    <span style="color:red;">*</span>

                                </label>

                                <select id="Source" data-live-search="true" class="form-control selectpicker">

                                    <option selected disabled data-icon="la la-clipboard-list font-size-lg bs-icon" value="">--SELECT SOURCE--</option>

                                    <?php foreach($sources as $source) { ?>

                                        <option <?php if((current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) && $source->SourceID == $Source) { echo 'selected'; } ?> data-icon="<?php if($source->Name == 'WHATSAPP') { echo 'la la-whatsapp'; } else if($source->Name == 'WECHAT') { echo 'la la-wechat'; } else if($source->Name == 'EMAIL') { echo 'la la-envelope'; } else if($source->Name == 'CALL') { echo 'la la-phone-volume'; } else if($source->Name == 'TELEGRAM') { echo 'la la-telegram'; } else if($source->Name == 'FACEBOOK') { echo 'la la-facebook'; } else { echo 'la la-clipboard-list'; } ?> font-size-lg bs-icon" value="<?php echo $source->SourceID; ?>"><?php echo $source->Name; ?></option>

                                    <?php } ?>

                                </select>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="form-group">

                                <label>Chat Language

                                    <span style="color:red;">*</span>

                                </label>

                                <select id="ChatLanguage" class="form-control selectpicker">

                                    <option selected disabled data-icon="la la-language font-size-lg bs-icon" value="">--SELECT CHAT LANGUAGE--</option>

                                    <?php foreach(unserialize(CHAT_LANGUAGE) as $key => $value) { ?>

                                        <option <?php if((current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) && $key == $ChatLanguage) { echo 'selected'; } ?> data-icon="la la-language font-size-lg bs-icon" value="<?php echo $key; ?>"><?php echo $value; ?></option>

                                    <?php } ?>

                                </select>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="form-group">

                                <label>Deposit Deadline

                                    <a onclick="Reset_Deposit_Deadline()" class="btn btn-icon btn-light-warning btn-xs">

                                        <i class="la la-undo"></i>

                                    </a>

                                </label>

                                <div class="input-icon">

                                    <input readonly type="text" name="DepositDeadline" id="kt_datepicker_4_3" <?php if(current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) { ?> value="<?php echo $DepositDeadline; ?>" <?php } ?> autocomplete="off" class="form-control">

                                    <span>

                                        <i class="la la-calendar"></i>

                                    </span>

                                </div>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="form-group">

                                <label>Full Payment Deadline

                                    <span style="color:red;">*</span>

                                </label>

                                <div class="input-icon">

                                    <input readonly type="text" name="FullPaymentDeadline" id="kt_datepicker_4_4" <?php if(current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) { ?> value="<?php echo $FullPaymentDeadline; ?>" <?php } ?> autocomplete="off" class="form-control">

                                    <span>

                                        <i class="la la-calendar"></i>

                                    </span>

                                </div>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="form-group">

                                <label>Additional Payment Deadline

                                    <?php if(current_url() == base_url('Booking/Update')) { ?>

                                        <a onclick="Reset_Additional_Payment_Deadline()" class="btn btn-icon btn-light-warning btn-xs">

                                            <i class="la la-undo"></i>

                                        </a>

                                    <?php } ?>

                                </label>

                                <div class="input-icon">

                                    <input <?php if(current_url() == base_url('Booking/Create') || current_url() == base_url('Booking/Duplicate')) { echo 'disabled'; } else { echo 'readonly'; } ?> type="text" name="AdditionalPaymentDeadline" id="kt_datepicker_5" <?php if(current_url() == base_url('Booking/Update')) { ?> value="<?php echo $AdditionalPaymentDeadline; ?>" <?php } ?> autocomplete="off" class="form-control">

                                    <span>

                                        <i class="la la-calendar"></i>

                                    </span>

                                </div>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="form-group">

                                <label>BC Title

                                    <span style="color:red;">*</span>

                                </label>

                                <select id="BookingConfirmationTitle" class="form-control selectpicker">

                                    <option selected disabled data-icon="la la-heading font-size-lg bs-icon" value="">--SELECT BC TITLE--</option>

                                    <?php foreach(unserialize(BC_TITLE) as $key => $value) { ?>

                                        <option <?php if((current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) && $key == $BookingConfirmationTitle) { echo 'selected'; } ?> data-icon="la la-heading font-size-lg bs-icon" value="<?php echo $key; ?>"><?php echo $value; ?></option>

                                    <?php } ?>

                                </select>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="form-group">

                                <label>BC Tag

                                    <a onclick="Reset_Tag()" class="btn btn-icon btn-light-warning btn-xs">

                                        <i class="la la-undo"></i>

                                    </a>

                                </label>

                                <select title="--SELECT BC TAG--" id="Tag" multiple data-live-search="true" class="form-control selectpicker">

                                    <?php foreach($tags as $tag) { ?>

                                        <option <?php if((current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) && in_array($tag->TagID, $Tag)) { echo 'selected'; } ?> data-icon="la la-tags font-size-lg bs-icon" value="<?php echo $tag->TagID; ?>"><?php echo $tag->Name; ?></option>

                                    <?php } ?>

                                </select>

                            </div>

                        </div>

                    </div>

                    <div class="d-flex justify-content-between border-top pt-5"></div>

                    <strong>Product Information :</strong>

                    <?php if(current_url() == base_url('Booking/Update')) { ?>

                        <div class="row">

                            <div class="col-md-6">

                                <select id="product_action" class="form-control selectpicker mt-3">

                                    <option selected data-icon="la la-product-hunt font-size-lg bs-icon" value="INSERT PRODUCT">INSERT PRODUCT</option>

                                    <option data-icon="la la-product-hunt font-size-lg bs-icon" value="ORGANIZE PRODUCT">ORGANIZE PRODUCT</option>

                                </select>

                            </div>

                        </div>

                    <?php } ?>

                    <br><br>

                    <div id="benchmark" class="row draggable-zone"></div>

                    <a id="create_booking_product" class="btn btn-light-success btn-sm mt-2" style="width:180px;">

                        <i class="la la-plus-circle"></i>Insert Product

                    </a>

                    <br><br>

                    <div class="row">

                        <div class="col-md-12 pt-3 pb-3" style="background-color:white; border:3px solid #D7E2F2; border-radius:8px;">

                            <div class="row">

                                <div class="col-md-4 mb-7 mb-md-0">

                                    <label style="color:#088F8F;">Subtotal (RM)</label>

                                    <div class="input-icon">

                                        <input disabled type="text" id="Subtotal" <?php if(current_url() == base_url('Booking/Create')) { ?> value="0.00" <?php } else { ?> value="<?php echo $Subtotal; ?>" <?php } ?> class="form-control" style="text-align:right;">

                                        <span>

                                            <i class="la la-dollar"></i>

                                        </span>

                                    </div>

                                </div>

                                <div class="col-md-4 mb-7 mb-md-0">

                                    <label style="color:#2AAA8A;">- Discount (RM)</label>

                                    <div class="input-icon">

                                        <input type="text" id="Discount" <?php if(current_url() == base_url('Booking/Create')) { ?> value="" <?php } else { ?> value="<?php echo $Discount; ?>" <?php } ?> autocomplete="off" class="form-control" style="text-align:right;">

                                        <span>

                                            <i class="la la-dollar"></i>

                                        </span>

                                    </div>

                                </div>

                                <div class="col-md-4">

                                    <label style="color:#50C878;">= Net Total (RM)</label>

                                    <div class="input-icon">

                                        <input disabled type="text" id="NetTotal" <?php if(current_url() == base_url('Booking/Create')) { ?> value="0.00" <?php } else { ?> value="<?php echo $NetTotal; ?>" <?php } ?> class="form-control" style="text-align:right;">

                                        <span>

                                            <i class="la la-dollar"></i>

                                        </span>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    <br>

                    <div class="d-flex justify-content-between border-top pt-5"></div>

                    <strong>Footer Information :</strong>

                    <br><br>

                    <div class="row">

                        <div class="col-md-6">

                            <div class="form-group">

                                <label>Booking Confirmation</label>

                                <select id="booking_confirmation_footer" data-live-search="true" class="form-control selectpicker">

                                    <option selected data-icon="la la-clipboard-list font-size-lg bs-icon" value="">--SELECT BC FOOTER--</option>

                                    <?php foreach($footers as $footer) { ?>

                                        <option <?php if((current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) && $footer->FooterID == $BookingConfirmationFooterID) { echo 'selected'; } ?> data-icon="la la-clipboard-list font-size-lg bs-icon" value="<?php echo $footer->FooterID; ?>"><?php echo $footer->BookingConfirmationTitle; ?></option>

                                    <?php } ?>

                                </select>

                            </div>

                            <div id="booking_confirmation_content" class="alert alert-custom alert-light-info fade show mb-5" style="overflow-x:auto;">

                                <div class="alert-text">

                                    <label style="color:#3F4254;">Content :</label>

                                    <textarea id="kt-tinymce-4" autocomplete="off" class="tox-target"><?php if(current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) { echo $BookingConfirmationFooter; } ?></textarea>

                                </div>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="form-group">

                                <label>Travel Voucher</label>

                                <select id="travel_voucher_footer" data-live-search="true" class="form-control selectpicker">

                                    <option selected data-icon="la la-clipboard-list font-size-lg bs-icon" value="">--SELECT TV FOOTER--</option>

                                    <?php foreach($footers as $footer) { ?>

                                        <option <?php if((current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) && $footer->FooterID == $TravelVoucherFooterID) { echo 'selected'; } ?> data-icon="la la-clipboard-list font-size-lg bs-icon" value="<?php echo $footer->FooterID; ?>"><?php echo $footer->TravelVoucherTitle; ?></option>

                                    <?php } ?>

                                </select>

                            </div>

                            <div id="travel_voucher_content" class="alert alert-custom alert-light-info fade show mb-5" style="overflow-x:auto;">

                                <div class="alert-text">

                                    <label style="color:#3F4254;">Content :</label>

                                    <textarea id="kt-tinymce-5" autocomplete="off" class="tox-target"><?php if(current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) { echo $TravelVoucherFooter; } ?></textarea>

                                </div>

                            </div>

                        </div>

                    </div>

                    <div class="d-flex justify-content-between border-top pt-5" style="overflow-x:auto;">

                        <input type="button" value="<?php if(current_url() == base_url('Booking/Create') || current_url() == base_url('Booking/Duplicate')) { echo 'Create Booking'; } else { echo 'Update Booking'; } ?>" class="btn btn-success font-weight-bold px-9 py-4" style="width:180px; margin-left:auto;">

                        <?php if(current_url() == base_url('Booking/Update')) { ?>

                            <a href="<?php echo base_url('Guest_List?gl=') . $Token; ?>" class="btn btn-light-primary font-weight-bold px-9 py-4 ml-1" style="width:180px;">GL</a>

                            <?php if(in_array('VP', $this->session->access_control)) { ?>

                                <a href="<?php echo base_url('Payment?booking_number=') . $BookingNumber . '&customer=' . urlencode($Customer); ?>" class="btn btn-light-warning font-weight-bold px-9 py-4 ml-1" style="width:180px;">Payments</a>

                            <?php } ?>

                        <?php } ?>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>

<script>

    $('.alert-light-info').hide();

    <?php if((current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) && !empty($BookingConfirmationFooterID)) { ?>

        $('#booking_confirmation_content').show();

    <?php } ?>

    <?php if((current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) && !empty($TravelVoucherFooterID)) { ?>

        $('#travel_voucher_content').show();

    <?php } ?>

    var products = <?php echo json_encode($products) ?>;

    var label = null;

    var array = [];

    for(var i = 0; i < products.length; i++) {

        if(products[i].Category == label) {

            array.push('<option data-icon="la la-product-hunt font-size-lg bs-icon" value="'+ products[i].ProductID +'">'+ products[i].Product + ' (' + products[i].ProductCode +')</option>');

        } else {

            array.push('<optgroup label="'+ products[i].Category +'"><option data-icon="la la-product-hunt font-size-lg bs-icon" value="'+ products[i].ProductID +'">'+ products[i].Product + ' (' + products[i].ProductCode +')</option>');

            label = products[i].Category;

        }

    }

    var count = 0;

    var booking_products = <?php echo json_encode($booking_products) ?>;

    var booking_product_id = window.location.href.split('?')[0] == '<?php echo base_url('Booking/Update'); ?>' ? parseInt(booking_products[0].BookingProductID) : parseInt(<?php echo $BookingProductID; ?>);

    var booking_product_ids = [];

    var initial_booking_confirmation_footer = $('#kt-tinymce-4').val();

    var initial_travel_voucher_footer = $('#kt-tinymce-5').val();

    var product_sequence = [];



    function Reset_Deposit_Deadline() {

        $('input[name="DepositDeadline"]').val('');

    }



    function Reset_Tag() {

        $('#Tag').val('').change();

    }



    function Reset_Additional_Payment_Deadline() {

        $('input[name="AdditionalPaymentDeadline"]').val('');

    }

    

    var current_date = (new Date()).toLocaleDateString();

    $('#kt_daterangepicker_4').on('apply.daterangepicker', function(event, daterange) {

        var start_date = (new Date(daterange.startDate._d)).toLocaleDateString();

        var end_date = (new Date(daterange.endDate._d)).toLocaleDateString();

        if(start_date == current_date && end_date == current_date) {

            $('#TravelDate').val(moment().format('DD/MM/YYYY') + ' - ' + moment().format('DD/MM/YYYY'));

        }

    });



    $('#booking_confirmation_footer').change(function() {

        var footer_id = $('#booking_confirmation_footer').val();

        if(footer_id == '') {

            $('#booking_confirmation_content').hide();

            tinyMCE.editors[0].setContent('');

        } else {

            $('#booking_confirmation_content').show();

            $.ajax({

                url: '<?php echo base_url('Footer/Read') ?>',

                type: 'get',

                data: {

                    footer_id: footer_id

                },

                dataType: 'json',

                success: function(array) {

                    tinyMCE.editors[0].setContent(array.BookingConfirmationContent);

                }

            });

        }

    });



    $('#travel_voucher_footer').change(function() {

        var footer_id = $('#travel_voucher_footer').val();

        if(footer_id == '') {

            $('#travel_voucher_content').hide();

            tinyMCE.editors[1].setContent('');

        } else {

            $('#travel_voucher_content').show();

            $.ajax({

                url: '<?php echo base_url('Footer/Read') ?>',

                type: 'get',

                data: {

                    footer_id: footer_id

                },

                dataType: 'json',

                success: function(array) {

                    tinyMCE.editors[1].setContent(array.TravelVoucherContent);

                }

            });

        }

    });



    $('#booking_number').change(function() {

        var booking_number = $('#booking_number').val();

        $.ajax({

            url: '<?php echo base_url('Booking/Detect') ?>',

            type: 'post',

            data: {

                booking_number: booking_number

            },

            dataType: 'json',

            success: function(redundant_booking_number) {

                if(redundant_booking_number) {

                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Redundant Booking Number Detected', null);

                    $('#booking_number').val('');

                }

            }

        });

    });



    $('#create_booking_product').click(function() {

        count++;

        $('#benchmark').append('<div id="'+ booking_product_id +'" class="col-md-12 pt-3 pb-3 mb-5 draggable" style="background-color:white; border:3px solid #D7E2F2; border-radius:8px;">' +

            '<a onclick="Delete_Booking_Product('+ booking_product_id +')" class="btn btn-icon btn-light-danger">' +

                '<i class="la la-times"></i>' +

            '</a>' +

            '<a class="btn btn-icon btn-sm btn-hover-light-primary draggable-handle" style="float:right;">' +

                '<i class="ki ki-menu"></i>' +

			'</a>' +

            '<br><br>' +

            '<div class="form-group">' +

                '<div class="row">' +

                    '<div class="col-md-12">' +

                        '<label>Product <span style="color:red;">*</span></label>' +

                        '<select id="ProductID-'+ booking_product_id +'" data-live-search="true" onchange="Update_Booking_Product('+ '/Product/' + ',' + booking_product_id +')" class="form-control selectpicker">' +

                            '<option selected disabled data-icon="la la-product-hunt font-size-lg bs-icon" value="">--SELECT PRODUCT--</option>' + array +

                        '</select>' +

                    '</div>' +

                '</div>' +

                '<br>' +

                '<div class="row">' +

                    '<div class="col-md-6 mb-7 mb-md-0">' +

                        '<label>Product Code</label>' +

                        '<div class="input-icon">' +

                            '<input disabled type="text" id="ProductCode-'+ booking_product_id +'" class="form-control">' +

                            '<span>' +

                                '<i class="la la-product-hunt"></i>' +

                            '</span>' +

                        '</div>' +

                    '</div>' +

                    '<div class="col-md-6">' +

                        '<label>Description</label>' +

                        '<div class="input-icon">' +

                            '<input disabled type="text" id="Description-'+ booking_product_id +'" autocomplete="off" class="form-control">' +

                            '<span>' +

                                '<i class="la la-pencil-alt"></i>' +

                            '</span>' +

                        '</div>' +

                    '</div>' +

                '</div>' +

                '<br>' +

                '<div class="row">' +

                    '<div class="col-md-6 mb-7 mb-md-0">' +

                        '<label>Quantity <span style="color:red;">*</span></label>' +

                        '<div class="input-icon">' +

                            '<input disabled type="text" id="Quantity-'+ booking_product_id +'" oninput="Update_Booking_Product('+ '/Quantity/' + ',' + booking_product_id +')" autocomplete="off" class="form-control">' +

                            '<span>' +

                                '<i class="la la-calculator"></i>' +

                            '</span>' +

                        '</div>' +

                    '</div>' +

                    '<div class="col-md-6">' +

                        '<label>Price <span style="color:red;">*</span></label>' +

                        '<div class="input-icon">' +

                            '<input disabled type="text" id="Price-'+ booking_product_id +'" onchange="Update_Booking_Product('+ '/Price/' + ',' + booking_product_id +')" autocomplete="off" class="form-control" style="text-align:right;">' +

                            '<span>' +

                                '<i class="la la-dollar"></i>' +

                            '</span>' +

                        '</div>' +

                    '</div>' +

                '</div>' +

                '<br>' +

                '<div class="row">' +

                    '<div class="col-md-6">' +

                        '<label>Total</label>' +

                        '<div class="input-icon">' +

                            '<input disabled type="text" id="Total-'+ booking_product_id +'" value="0.00" class="form-control" style="text-align:right;">' +

                            '<span>' +

                                '<i class="la la-dollar"></i>' +

                            '</span>' +

                        '</div>' +

                    '</div>' +

                '</div>' +

            '</div>' +

        '</div>');

        if(window.location.href == '<?php echo base_url('Booking/Create'); ?>' || window.location.href.split('?')[0] == '<?php echo base_url('Booking/Create'); ?>' || (window.location.href.split('?')[0] == '<?php echo base_url('Booking/Update'); ?>' || window.location.href.split('?')[0] == '<?php echo base_url('Booking/Duplicate'); ?>') && count > booking_products.length) {

            $(`#ProductID-${booking_product_id}`).selectpicker();

        }

        booking_product_ids.push(booking_product_id);

        booking_product_id++;

        if(window.location.href.split('?')[0] == '<?php echo base_url('Booking/Update'); ?>' && $('#product_action').val() == 'INSERT PRODUCT') {

            $('.draggable-handle').hide();

        }

        if(window.location.href.split('?')[0] == '<?php echo base_url('Booking/Update'); ?>' && count > booking_products.length) {

            $('#product_action').prop('disabled', true);

        }

    });

    

    function Create_Booking_Products()

    {

        var booking_products = [];

        for(var i = 0; i < product_sequence.length; i++) {

            var product_id = $(`#ProductID-${product_sequence[i]}`).val();

            var index = products.findIndex(value => value.ProductID == product_id);

            var product_code = products[index].ProductCode;

            var name = $(`#ProductID-${product_sequence[i]} option:selected`).text();

            var description = ($(`#Description-${product_sequence[i]}`).val()).toUpperCase();

            var quantity = parseInt($(`#Quantity-${product_sequence[i]}`).val());

            var price = parseFloat(($(`#Price-${product_sequence[i]}`).val()).replace(/,/g, ''));

            var total = quantity * price;

            if(window.location.href == '<?php echo base_url('Booking/Create'); ?>' || window.location.href.split('?')[0] == '<?php echo base_url('Booking/Create'); ?>' || window.location.href.split('?')[0] == '<?php echo base_url('Booking/Duplicate'); ?>' || (window.location.href.split('?')[0] == '<?php echo base_url('Booking/Update'); ?>' && jQuery.inArray(product_sequence[i], array) == -1)) {

                booking_products.push({ProductID:product_id, ProductCode:product_code, Name:name, Description:description, Quantity:quantity, Price:price, Total:total, InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});

            }

        }

        return booking_products;

    }



    function Update_Booking_Product(value, booking_product_id)

    {

        if(value == '/Product/') {

            $(`#Description-${booking_product_id}`).removeAttr('disabled');

            $(`#Quantity-${booking_product_id}`).removeAttr('disabled');

            $(`#Price-${booking_product_id}`).removeAttr('disabled');

        }

        var product_id = $(`#ProductID-${booking_product_id}`).val();

        var index = products.findIndex(value => value.ProductID == product_id);

        var product_code = products[index].ProductCode;

        $(`#ProductCode-${booking_product_id}`).val(product_code);

        var quantity = $(`#Quantity-${booking_product_id}`).val();

        if(value == '/Product/') {

            $(`#Price-${booking_product_id}`).val('');

        }

        var price = ($(`#Price-${booking_product_id}`).val()).replace(/,/g, '');

        if(quantity.match(/^[1-9][\d]{0,4}$/) && price.match(/^[0-9][\d]{0,9}([\.][\d]{0,2})?$/)) {

            var total = parseInt(quantity) * parseFloat(price);

            $(`#Price-${booking_product_id}`).val(parseFloat(price).toLocaleString('en-US', {minimumFractionDigits: 2}));

            $(`#Total-${booking_product_id}`).val(total.toLocaleString('en-US', {minimumFractionDigits: 2}));

        } else {

            if(quantity.match(/^[1-9][\d]{0,4}$/) == null) {

                $(`#Quantity-${booking_product_id}`).val('');

            }

            if(price.match(/^[0-9][\d]{0,9}([\.][\d]{0,2})?$/) == null) {

                $(`#Price-${booking_product_id}`).val('');

            }

            quantity = $(`#Quantity-${booking_product_id}`).val() == '' ? 0 : parseInt($(`#Quantity-${booking_product_id}`).val());

            price = $(`#Price-${booking_product_id}`).val() == '' ? 0 : parseFloat(($(`#Price-${booking_product_id}`).val()).replace(/,/g, ''));

            var total = quantity * price;

            $(`#Total-${booking_product_id}`).val(total.toLocaleString('en-US', {minimumFractionDigits: 2}));

        }

        Calculate_Subtotal();

    }



    function Delete_Booking_Product(booking_product_id)

    {

        booking_product_ids = booking_product_ids.filter(function(value) {

            return value != booking_product_id;

        });

        $(`#${booking_product_id}`).remove();

        Calculate_Subtotal();

    }



    function Calculate_Subtotal()

    {

        var total = 0;

        var subtotal = 0;

        if(booking_product_ids.length > 0) {

            for(var i = 0; i < booking_product_ids.length; i++) {

                total += parseFloat(($(`#Total-${booking_product_ids[i]}`).val()).replace(/,/g, ''));

            }

        }

        subtotal += total;

        $('#Subtotal').val(subtotal.toLocaleString('en-US', {minimumFractionDigits: 2}));

        var discount = $('#Discount').val() == '' ? 0 : parseFloat(($('#Discount').val()).replace(/,/g, ''));

        $('#NetTotal').val((subtotal - discount).toLocaleString('en-US', {minimumFractionDigits: 2}));

    }



    $('#product_action').change(function() {

        var action = $('#product_action').val();

        var sequence = [];

        $('#benchmark').children().each((index, element) => {

            sequence.push(parseInt(element.id));

        });

        if(action == 'INSERT PRODUCT') {

            if(sequence.toString() != '<?php echo implode(',', $ProductSequence); ?>') {

                $('#product_action').val('ORGANIZE PRODUCT').change();

            } else {

                $('.draggable-handle').hide();

                $('#create_booking_product').show();

            }

        } else {

            $('.draggable-handle').show();

            $('#create_booking_product').hide();

        }

    });

    

    $('#Discount').change(function() {

        var subtotal = $('#Subtotal').val();

        var discount = $('#Discount').val();

        if(subtotal != '0.00') {

            if(discount.match(/^[0-9][\d]{0,9}([\.][\d]{0,2})?$/)) {

                $('#Discount').val(parseFloat(discount).toLocaleString('en-US', {minimumFractionDigits: 2}));

                var net_total = parseFloat(subtotal.replace(/,/g, '')) - parseFloat(discount.replace(/,/g, ''));

                $('#NetTotal').val(net_total.toLocaleString('en-US', {minimumFractionDigits: 2}));

            } else {

                $('#Discount').val('');

                $('#NetTotal').val(parseFloat(subtotal.replace(/,/g, '')).toLocaleString('en-US', {minimumFractionDigits: 2}));

            }

        } else {

            $('#Discount').val('');

        }

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

            title: <?php if(current_url() == base_url('Booking/Create') || current_url() == base_url('Booking/Duplicate')) { ?> 'Create New Booking Record ?' <?php } else { ?> '<?php echo 'Update Booking Record : ' . $BookingNumber . ' ?'; ?>' <?php } ?>,

            confirmButtonText: 'Confirm',

            cancelButtonText: 'Cancel',

            showCancelButton: true

        }).then((action) => {

            if(action.isConfirmed) {

                var booking_confirmation_footer = $('#booking_confirmation_footer').val();

                var travel_voucher_footer = $('#travel_voucher_footer').val();

                var country_code = $('#CountryCodeID').val();

                var reservation_number = ($('#ReservationNumber').val()).toUpperCase();

                var full_payment_deadline = $('input[name="FullPaymentDeadline"]').val();

                var customer = ($('#Customer').val()).toUpperCase();

                var CustomerID = $('input[name="CustomerID"]').val();

                var mobile = $('#Mobile').val();

                var travel_date = $('#TravelDate').val();

                var adult = $('#Adult').val();

                var children = $('#Children').val();

                var infant = $('#Infant').val();

                var destination = $('#Destination').val();

                var sales_agent = $('#SalesAgent').hasOwnProperty('length') ? $('#SalesAgent').val() : <?php echo $this->session->userdata('admin_id') ?>;

                var chat_language = $('#ChatLanguage').val();

                var source = $('#Source').val();

                var bc_title = $('#BookingConfirmationTitle').val();

                if(country_code == null || reservation_number == '' || full_payment_deadline == '' || customer == '' || mobile == '' || travel_date == '' || destination == null || sales_agent == null || chat_language == null || source == null || bc_title == null) {

                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert All Required Booking Information', null);

                } else {

                    if(adult == '' && children == '' && infant == '') {

                        Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert Pax Number', null);

                    } else {

                        if((booking_confirmation_footer != '' && tinyMCE.editors[0].getContent() == '') || (travel_voucher_footer != '' && tinyMCE.editors[1].getContent() == '')) {

                            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert Footer Content Upon Selection', null);

                        } else {

                            if(booking_product_ids.length == 0) {

                                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert Product', null);

                            } else {

                                for(var i = 0; i < booking_product_ids.length; i++) {

                                    if($(`#ProductID-${booking_product_ids[i]}`).val() == null || $(`#Quantity-${booking_product_ids[i]}`).val() == '' || $(`#Price-${booking_product_ids[i]}`).val() == '' || $(`#Price-${booking_product_ids[i]}`).val() == '0.00') {

                                        Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert All Required Product Information', null);

                                        return;

                                    }

                                }

                                $('#benchmark').children().each((index, element) => {

                                    product_sequence.push(parseInt(element.id));

                                });

                                if(window.location.href == '<?php echo base_url('Booking/Create'); ?>' || window.location.href.split('?')[0] == '<?php echo base_url('Booking/Create'); ?>' || window.location.href.split('?')[0] == '<?php echo base_url('Booking/Duplicate'); ?>') {

                                    var booking = [];

                                    full_payment_deadline = ($('input[name="FullPaymentDeadline"]').val()).split('/');

                                    var subtotal = ($('#Subtotal').val()).replace(/,/g, '');

                                    var net_total = ($('#NetTotal').val()).replace(/,/g, '');

                                    booking.push({CountryCodeID:country_code, ReservationNumber:reservation_number, FullPaymentDeadline:`${full_payment_deadline[2]}-${full_payment_deadline[1]}-${full_payment_deadline[0]}`, Customer:customer, Mobile:mobile, Destination:destination, SalesAgent:sales_agent, Source:source, Subtotal:subtotal, NetTotal:net_total, ChatLanguage:chat_language, BookingConfirmationTitle:bc_title, InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>', UpdateBy:<?php echo $this->session->userdata('admin_id') ?>, UpdateDate:'<?php echo date('Y-m-d H:i:s') ?>'});

                                    if(booking_confirmation_footer != '') {

                                        booking[0]['BookingConfirmationFooterID'] = booking_confirmation_footer;

                                    }

                                    if(travel_voucher_footer != '') {

                                        booking[0]['TravelVoucherFooterID'] = travel_voucher_footer;

                                    }

                                    var booking_number = ($('#booking_number').val()).toUpperCase();

                                    if(booking_number != '') {

                                        booking[0]['BookingNumber'] = booking_number;

                                    }

                                    var CustomerID = $('input[name="CustomerID"]').val();

                                    var deposit_deadline = $('input[name="DepositDeadline"]').val();

                                    if(deposit_deadline != '') {

                                        deposit_deadline = deposit_deadline.split('/');

                                        booking[0]['DepositDeadline'] = `${deposit_deadline[2]}-${deposit_deadline[1]}-${deposit_deadline[0]}`;

                                    }

                                    if(travel_date != '') {

                                        travel_date = travel_date.split(' - ');

                                        var start_date = travel_date[0].split('/');

                                        var end_date = travel_date[1].split('/');

                                        booking[0]['StartDate'] = `${start_date[2]}-${start_date[1]}-${start_date[0]}`;

                                        booking[0]['EndDate'] = `${end_date[2]}-${end_date[1]}-${end_date[0]}`;

                                    }

                                    if(adult != '') {

                                        booking[0]['Adult'] = adult;

                                    }

                                    if(children != '') {

                                        booking[0]['Children'] = children;

                                    }

                                    if(infant != '') {

                                        booking[0]['Infant'] = infant;

                                    }

                                    var tag = ($('#Tag').val()).toString();

                                    if(tag != '') {

                                        booking[0]['Tag'] = tag;

                                    }

                                    var booking_remark = ($('#BookingRemark').val()).toUpperCase();

                                    if(booking_remark != '') {

                                        booking[0]['BookingRemark'] = booking_remark;

                                    }

                                    var discount = $('#Discount').val();

                                    if(discount != '') {

                                        booking[0]['Discount'] = discount.replace(/,/g, '');;

                                    }

                                    if(tinyMCE.editors[0].getContent() != '') {

                                        booking[0]['BookingConfirmationFooter'] = tinyMCE.editors[0].getContent();

                                    }

                                    if(tinyMCE.editors[1].getContent() != '') {

                                        booking[0]['TravelVoucherFooter'] = tinyMCE.editors[1].getContent();

                                    }



                                    booking_products = Create_Booking_Products();



                                    Submit_Booking('<?php echo base_url('Booking/Create') ?>', null, booking_number, booking, null, booking_products, CustomerID);

                                } else {

                                    array = [];

                                    for(var i = 0; i < booking_products.length; i++) {

                                        array.push(parseInt(booking_products[i].BookingProductID));

                                    }



                                    var booking = [{BookingID:<?php echo $BookingID ?>, UpdateBy:<?php echo $this->session->userdata('admin_id') ?>, UpdateDate:'<?php echo date('Y-m-d H:i:s') ?>'}];

                                    var booking_log = [];



                                    booking_products = [];

                                    booking_products[0] = [];

                                    booking_products[1] = [];

                                    booking_products[2] = [];



                                    // Booking Product

                                    // Action : Create

                                    booking_products[0] = Create_Booking_Products();



                                    var dirty_fields = $('#form').dirty('showDirtyFields');

                                    var admins = <?php echo json_encode($admins) ?>;

                                    var sources = <?php echo json_encode($sources) ?>;

                                    var bc_titles = <?php echo json_encode(unserialize(BC_TITLE)) ?>;

                                    var categories = <?php echo json_encode($categories) ?>;

                                    var country_codes = <?php echo json_encode($country_codes) ?>;

                                    if(dirty_fields.length > 0) {

                                        for(var i = 0; i < dirty_fields.length; i++) {

                                            if(dirty_fields[i].localName == 'select' || Object.values(dirty_fields[i])[0].hasOwnProperty('dirtyInitialValue')) {

                                                var key = dirty_fields[i].id == 'kt_datepicker_4_3' || dirty_fields[i].id == 'kt_datepicker_4_4' || dirty_fields[i].id == 'kt_datepicker_5' ? dirty_fields[i].name : dirty_fields[i].id;

                                                var value = dirty_fields[i].id == 'kt_datepicker_4_3' || dirty_fields[i].id == 'kt_datepicker_4_4' || dirty_fields[i].id == 'kt_datepicker_5' ? `${((dirty_fields[i].value).split('/'))[2]}-${((dirty_fields[i].value).split('/'))[1]}-${((dirty_fields[i].value).split('/'))[0]}` : (dirty_fields[i].value).toUpperCase();

                                                // Booking

                                                // Action : Update

                                                if(key == 'CountryCodeID' && Object.values(dirty_fields[i])[country_codes.length + 1].hasOwnProperty('dirtyInitialValue') || key == 'ReservationNumber' || key == 'DepositDeadline' || key == 'FullPaymentDeadline' || key == 'AdditionalPaymentDeadline' || key == 'Customer' || key == 'Mobile' || key == 'Destination' && Object.values(dirty_fields[i])[categories.length + 1].hasOwnProperty('dirtyInitialValue') || key == 'SalesAgent' && Object.values(dirty_fields[i])[admins.length + 1].hasOwnProperty('dirtyInitialValue') || key == 'BookingRemark' || key == 'Subtotal' || key == 'ChatLanguage' && Object.values(dirty_fields[i])[4].hasOwnProperty('dirtyInitialValue') || key == 'Source' && Object.values(dirty_fields[i])[sources.length + 1].hasOwnProperty('dirtyInitialValue') || key == 'BookingConfirmationTitle' && Object.values(dirty_fields[i])[4].hasOwnProperty('dirtyInitialValue')) {

                                                    if(key == 'Subtotal') {

                                                        value = value.replace(/,/g, '');

                                                    }

                                                    booking[0][key] = value;



                                                    //Insert Booking Log

                                                    var default_value = null;

                                                    switch(key) {

                                                        case 'CountryCodeID':

                                                            default_value = (Object.values(dirty_fields[i])[country_codes.length + 1]).dirtyInitialValue;

                                                            break;

                                                        case 'Destination':

                                                            default_value = (Object.values(dirty_fields[i])[categories.length + 1]).dirtyInitialValue;

                                                            break;

                                                        case 'SalesAgent':

                                                            default_value = (Object.values(dirty_fields[i])[admins.length + 1]).dirtyInitialValue;

                                                            break;

                                                        case 'ChatLanguage':

                                                            default_value = (Object.values(dirty_fields[i])[4]).dirtyInitialValue;

                                                            break;

                                                        case 'Source':

                                                            default_value = (Object.values(dirty_fields[i])[sources.length + 1]).dirtyInitialValue;

                                                            break;

                                                        case 'BookingConfirmationTitle':

                                                            default_value = (Object.values(dirty_fields[i])[4]).dirtyInitialValue;

                                                            break;

                                                        default:

                                                            if(key == 'DepositDeadline' || key == 'FullPaymentDeadline' || key == 'AdditionalPaymentDeadline') {

                                                                var date = ((Object.values(dirty_fields[i])[0]).dirtyInitialValue).split('/');

                                                                default_value = `${date[2]}-${date[1]}-${date[0]}`;

                                                            } else {

                                                                default_value = (Object.values(dirty_fields[i])[0]).dirtyInitialValue;

                                                            }

                                                    }

                                                    booking_log.push({BookingID:<?php echo $BookingID ?>, Column:key, CurrentData:default_value, NewData:value, InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});

                                                } else {

                                                    if(key == 'TravelDate') {

                                                        var initial_travel_date = (Object.values(dirty_fields[i])[0].dirtyInitialValue).split(' - ');

                                                        var initial_start_date = initial_travel_date[0];

                                                        var initial_end_date = initial_travel_date[1];

                                                        if(value != '') {

                                                            var updated_travel_date = value.split(' - ');

                                                            var updated_start_date = updated_travel_date[0];

                                                            var updated_end_date = updated_travel_date[1];

                                                            if(updated_start_date != initial_start_date) {

                                                                initial_start_date = initial_travel_date[0].split('/');

                                                                updated_start_date = updated_travel_date[0].split('/');

                                                                booking[0]['StartDate'] = `${updated_start_date[2]}-${updated_start_date[1]}-${updated_start_date[0]}`;

                                                                booking_log.push({BookingID:<?php echo $BookingID ?>, Column:'StartDate', CurrentData:`${initial_start_date[2]}-${initial_start_date[1]}-${initial_start_date[0]}`, NewData:`${updated_start_date[2]}-${updated_start_date[1]}-${updated_start_date[0]}`, InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});

                                                            }

                                                            if(updated_end_date != initial_end_date) {

                                                                initial_end_date = initial_travel_date[1].split('/');

                                                                updated_end_date = updated_travel_date[1].split('/');

                                                                booking[0]['EndDate'] = `${updated_end_date[2]}-${updated_end_date[1]}-${updated_end_date[0]}`;

                                                                booking_log.push({BookingID:<?php echo $BookingID ?>, Column:'EndDate', CurrentData:`${initial_end_date[2]}-${initial_end_date[1]}-${initial_end_date[0]}`, NewData:`${updated_end_date[2]}-${updated_end_date[1]}-${updated_end_date[0]}`, InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});

                                                            }

                                                        } else {

                                                            booking[0]['StartDate'] = '';

                                                            booking[0]['EndDate'] = '';

                                                            booking_log.push({BookingID:<?php echo $BookingID ?>, Column:'StartDate', CurrentData:`${initial_start_date[2]}-${initial_start_date[1]}-${initial_start_date[0]}`, NewData:'', InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});

                                                            booking_log.push({BookingID:<?php echo $BookingID ?>, Column:'EndDate', CurrentData:`${initial_end_date[2]}-${initial_end_date[1]}-${initial_end_date[0]}`, NewData:'', InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});

                                                        }

                                                    } else {

                                                        // Booking Product

                                                        // Action : Update

                                                        key = key.split('-');

                                                        if(key[0] == 'ProductID' && Object.values(dirty_fields[i])[products.length + 1].hasOwnProperty('dirtyInitialValue') || key[0] == 'ProductCode' || key[0] == 'Description' || key[0] == 'Quantity' || key[0] == 'Price' || key[0] == 'Total') {

                                                            if(key[0] == 'Price' || key[0] == 'Total') {

                                                                value = value.replace(/,/g, '');

                                                            }

                                                            booking_products[1].push({BookingProductID:key[1], [key[0]]:value, UpdateBy:<?php echo $this->session->userdata('admin_id') ?>, UpdateDate:'<?php echo date('Y-m-d H:i:s') ?>'});

                                                            if(key[0] == 'ProductID' && Object.values(dirty_fields[i])[products.length + 1].hasOwnProperty('dirtyInitialValue')) {

                                                                var name = $(`#ProductID-${key[1]} option:selected`).text();

                                                                booking_products[1].push({BookingProductID:key[1], Name:name, UpdateBy:<?php echo $this->session->userdata('admin_id') ?>, UpdateDate:'<?php echo date('Y-m-d H:i:s') ?>'});

                                                            }

                                                        }

                                                    }

                                                }

                                            }

                                        }

                                    }


                                    var CustomerID = $('input[name="CustomerID"]').val();

                                    // Booking

                                    // Action : Update Tag

                                    var tag = ($('#Tag').val()).toString();

                                    if(tag != '<?php echo implode(',', $Tag); ?>') {

                                        booking[0]['Tag'] = tag;

                                        booking_log.push({BookingID:<?php echo $BookingID ?>, Column:'Tag', CurrentData:'<?php echo implode(',', $Tag); ?>', NewData:tag, InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});

                                    }



                                    // Booking

                                    // Action : Update Discount

                                    var discount = $('#Discount').val();

                                    if(discount != '<?php echo $Discount; ?>') {

                                        booking[0]['Discount'] = discount.replace(/,/g, '');

                                        booking_log.push({BookingID:<?php echo $BookingID ?>, Column:'Discount', CurrentData:'<?php echo $Discount; ?>', NewData:discount.replace(/,/g, ''), InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});

                                    }



                                    // Booking

                                    // Action : Update NetTotal

                                    var net_total = $('#NetTotal').val();

                                    if(net_total != '<?php echo $NetTotal; ?>') {

                                        booking[0]['NetTotal'] = net_total.replace(/,/g, '');

                                        booking_log.push({BookingID:<?php echo $BookingID ?>, Column:'NetTotal', CurrentData:'<?php echo $NetTotal; ?>', NewData:net_total.replace(/,/g, ''), InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});

                                    }



                                    // Booking

                                    // Action : Update Booking Confirmation Footer ID

                                    var booking_confirmation_footer_id = $('#booking_confirmation_footer').val();

                                    if(booking_confirmation_footer_id != '<?php echo $BookingConfirmationFooterID; ?>') {

                                        booking[0]['BookingConfirmationFooterID'] = booking_confirmation_footer_id;

                                        booking_log.push({BookingID:<?php echo $BookingID ?>, Column:'BookingConfirmationFooterID', CurrentData:'<?php echo $BookingConfirmationFooterID; ?>', NewData:booking_confirmation_footer_id, InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});

                                    }



                                    // Booking

                                    // Action : Update Travel Voucher Footer ID

                                    var travel_voucher_footer_id = $('#travel_voucher_footer').val();

                                    if(travel_voucher_footer_id != '<?php echo $TravelVoucherFooterID; ?>') {

                                        booking[0]['TravelVoucherFooterID'] = travel_voucher_footer_id;

                                        booking_log.push({BookingID:<?php echo $BookingID ?>, Column:'TravelVoucherFooterID', CurrentData:'<?php echo $TravelVoucherFooterID; ?>', NewData:travel_voucher_footer_id, InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});

                                    }



                                    // Booking

                                    // Action : Update Booking Confirmation Footer

                                    var updated_booking_confirmation_footer = Decode_HTML(tinyMCE.editors[0].getContent());

                                    if(updated_booking_confirmation_footer != initial_booking_confirmation_footer) {

                                        booking[0]['BookingConfirmationFooter'] = tinyMCE.editors[0].getContent();

                                        booking_log.push({BookingID:<?php echo $BookingID ?>, Column:'BookingConfirmationFooter', CurrentData:initial_booking_confirmation_footer, NewData:tinyMCE.editors[0].getContent(), InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});

                                    }



                                    // Booking

                                    // Action : Update Travel Voucher Footer

                                    var updated_travel_voucher_footer = Decode_HTML(tinyMCE.editors[1].getContent());

                                    if(updated_travel_voucher_footer != initial_travel_voucher_footer) {

                                        booking[0]['TravelVoucherFooter'] = tinyMCE.editors[1].getContent();

                                        booking_log.push({BookingID:<?php echo $BookingID ?>, Column:'TravelVoucherFooter', CurrentData:initial_travel_voucher_footer, NewData:tinyMCE.editors[1].getContent(), InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});

                                    }



                                    // Booking

                                    // Action : Update Product Sequence

                                    if(window.location.href.split('?')[0] == '<?php echo base_url('Booking/Update'); ?>' && $('#product_action').val() == 'ORGANIZE PRODUCT') {

                                        if(product_sequence.toString() != '<?php echo implode(',', $ProductSequence); ?>') {

                                            booking[0]['ProductSequence'] = product_sequence.toString();

                                            booking_log.push({BookingID:<?php echo $BookingID ?>, Column:'ProductSequence', CurrentData:'<?php echo implode(',', $ProductSequence); ?>', NewData:product_sequence.toString(), InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});

                                        }

                                    }

                                    

                                    function Decode_HTML(html) {

                                        var plain_text = document.createElement('textarea');

                                        plain_text.innerHTML = html;

                                        return plain_text.value;

                                    }

                                    

                                    // Booking Product

                                    // Action : Delete

                                    for(var i = 0; i < array.length; i++) {

                                        if(!$(`#${array[i]}`).hasOwnProperty('length')) {

                                            booking_products[2].push({BookingProductID:array[i], Status:'N', UpdateBy:<?php echo $this->session->userdata('admin_id') ?>, UpdateDate:'<?php echo date('Y-m-d H:i:s') ?>'});

                                        }

                                    }



                                    count = 0;

                                    $.each(booking[0], function() {

                                        count++;

                                    });

                                    if(count == 3 && booking_products[0].length == 0 && booking_products[1].length == 0 && booking_products[2].length == 0) {

                                        <?php if(current_url() == base_url('Booking/Update')) { ?>

                                            <?php 

                                                $current_url = base_url($_SERVER['REQUEST_URI']);

                                                if(!empty($this->input->get('booking_number')) || !empty($this->input->get('reservation_number')) || !empty($this->input->get('deadline')) || !empty($this->input->get('customer')) || !empty($this->input->get('mobile')) || !empty($this->input->get('travel_date')) || !empty($this->input->get('destination')) || !empty($this->input->get('sales_agent')) || !empty($this->input->get('tag')) || !empty($this->input->get('chat_language')) || !empty($this->input->get('source')) || !empty($this->input->get('booking_confirmation_title')) || !empty($this->input->get('status')) || !empty($this->input->get('booking_date'))) {

                                                    $url = base_url('Booking?') . (explode('?booking_id=' . $this->input->get('booking_id') . '&', $current_url))[1];

                                                } else {

                                                    $url = base_url('Booking');

                                                }

                                            ?>

                                            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php echo 'No Changes Detected In Booking Record : ' . $BookingNumber; ?>', '<?php echo $url ?>');

                                        <?php } ?>

                                    } else {

                                        Submit_Booking('<?php echo base_url('Booking/Update') ?>', booking[0].BookingID, null, booking, booking_log, booking_products, CustomerID);

                                    }

                                }

                            }

                        }

                    }

                }

            }

        });

    });



    function Submit_Booking(url, booking_id, booking_number, booking, booking_log, booking_products, CustomerID)

    {

        $.ajax({

            url: url,

            type: 'post',

            data: {

                booking_id: booking_id,

                booking_number : booking_number,

                booking: booking,

                booking_log: booking_log,

                booking_products: booking_products,
            
                CustomerID: CustomerID,

            },

            success: function() {

                var base_url = '<?php echo base_url('Booking') ?>';

                if(window.location.href.split('?')[0] == '<?php echo base_url('Booking/Update'); ?>' || window.location.href.split('?')[0] == '<?php echo base_url('Booking/Duplicate'); ?>') {

                    <?php if(!empty($this->input->get('booking_number')) || !empty($this->input->get('reservation_number')) || !empty($this->input->get('deadline')) || !empty($this->input->get('customer')) || !empty($this->input->get('mobile')) || !empty($this->input->get('travel_date')) || !empty($this->input->get('destination')) || !empty($this->input->get('sales_agent')) || !empty($this->input->get('tag')) || !empty($this->input->get('chat_language')) || !empty($this->input->get('source')) || !empty($this->input->get('booking_confirmation_title')) || !empty($this->input->get('status')) || !empty($this->input->get('booking_date'))) { ?>

                        var url = base_url + '?' + window.location.href.split('?booking_id=' + <?php echo $this->input->get('booking_id') ?> + '&')[1];

                    <?php } else { ?>

                        var url = base_url;

                    <?php } ?>

                } else {

                    <?php if(!empty($this->input->get('booking_number')) || !empty($this->input->get('reservation_number')) || !empty($this->input->get('deadline')) || !empty($this->input->get('customer')) || !empty($this->input->get('mobile')) || !empty($this->input->get('travel_date')) || !empty($this->input->get('destination')) || !empty($this->input->get('sales_agent')) || !empty($this->input->get('tag')) || !empty($this->input->get('chat_language')) || !empty($this->input->get('source')) || !empty($this->input->get('booking_confirmation_title')) || !empty($this->input->get('status')) || !empty($this->input->get('booking_date'))) { ?>

                        var url = base_url + '?' + window.location.href.split('?')[1];

                    <?php } else { ?>

                        var url = base_url;

                    <?php } ?>

                }

                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php if(current_url() == base_url('Booking/Create') || current_url() == base_url('Booking/Duplicate')) { echo 'New'; } ?> Booking Record <?php if(current_url() == base_url('Booking/Update')) { echo ': ' . $BookingNumber; } ?> Successfully <?php if(current_url() == base_url('Booking/Create') || current_url() == base_url('Booking/Duplicate')) { echo 'Created'; } else { echo 'Updated'; } ?>', url);

            },

            error: function() {

                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', '<?php if(current_url() == base_url('Booking/Create') || current_url() == base_url('Booking/Duplicate')) { echo 'New'; } ?> Booking Record <?php if(current_url() == base_url('Booking/Update')) { echo ': ' . $BookingNumber; } ?> Could Not Be <?php if(current_url() == base_url('Booking/Create') || current_url() == base_url('Booking/Duplicate')) { echo 'Created'; } else { echo 'Updated'; } ?>', null);

            }

        });

    }



    if(window.location.href.split('?')[0] == '<?php echo base_url('Booking/Update'); ?>' || window.location.href.split('?')[0] == '<?php echo base_url('Booking/Duplicate'); ?>') {

        for(var i = 0; i < booking_products.length; i++) {

            var product_id = booking_products[i].ProductID;

            var product_code = booking_products[i].ProductCode;

            var description = booking_products[i].Description;

            var quantity = booking_products[i].Quantity;

            var price = booking_products[i].Price;

            var total = booking_products[i].Total;

            $('#create_booking_product').click();

            if(window.location.href.split('?')[0] == '<?php echo base_url('Booking/Update'); ?>') {

                $(`#ProductID-${booking_products[i].BookingProductID}`).val(product_id);

                $(`#ProductCode-${booking_products[i].BookingProductID}`).val(product_code);

                $(`#Description-${booking_products[i].BookingProductID}`).val(description);

                $(`#Description-${booking_products[i].BookingProductID}`).removeAttr('disabled');

                $(`#Quantity-${booking_products[i].BookingProductID}`).val(quantity);

                $(`#Quantity-${booking_products[i].BookingProductID}`).removeAttr('disabled');

                $(`#Price-${booking_products[i].BookingProductID}`).val(price);

                $(`#Price-${booking_products[i].BookingProductID}`).removeAttr('disabled');

                $(`#Total-${booking_products[i].BookingProductID}`).val(total);

                if((i + 1) < booking_products.length) {

                    booking_product_id = parseInt(booking_products[i + 1].BookingProductID);

                } else {

                    booking_product_id = parseInt(<?php echo $BookingProductID; ?>);

                }

            } else {

                $(`#ProductID-${booking_product_id - 1}`).val(product_id);

                $(`#ProductCode-${booking_product_id - 1}`).val(product_code);

                $(`#Description-${booking_product_id - 1}`).val(description);

                $(`#Description-${booking_product_id - 1}`).removeAttr('disabled');

                $(`#Quantity-${booking_product_id - 1}`).val(quantity);

                $(`#Quantity-${booking_product_id - 1}`).removeAttr('disabled');

                $(`#Price-${booking_product_id - 1}`).val(price);

                $(`#Price-${booking_product_id - 1}`).removeAttr('disabled');

                $(`#Total-${booking_product_id - 1}`).val(total);

            }

        }

    }



    <?php if(current_url() == base_url('Booking/Update')) { ?>

        $('.draggable-handle').hide();

    <?php } ?>



    $('#form').dirty('isClean');



    var KTCardDraggable = function() {

	return {

		init: function() {

			var containers = document.querySelectorAll('.draggable-zone');

			if(containers.length === 0) {

				return false;

			}

			var swappable = new Sortable.default(containers, {

				draggable: '.draggable',

				handle: '.draggable .draggable-handle',

				mirror: {

					appendTo: 'body',

					constrainDimensions: true

				}

			});

		}

	};

}();

jQuery(document).ready(function() {

	KTCardDraggable.init();

});

</script>

<script>
// $(document).ready(function() {
//     // Hardcoded customer list (simulate DB)
//     const customers = [
//         "Alice Travel",
//         "Bob Adventures",
//         "Charlie Holiday",
//         "Delta Tours",
//         "Echo Getaway",
//         "Foxtrot Trips",
//         "Golf Tours",
//         "Hotel Booking Co",
//         "India Travels",
//         "Juliet Vacations",
//         "Kilo Holidays",
//         "Lima Adventures",
//         "Mike Travel",
//         "November Trips",
//         "Oscar Getaway",
//         "Papa Tours",
//         "Quebec Holidays",
//         "Romeo Travels",
//         "Sierra Adventures",
//         "Tango Tours",
//         "Uniform Getaway",
//         "Victor Holidays",
//         "Whiskey Travels",
//         "Xray Adventures",
//         "Yankee Tours",
//         "Zulu Getaway",
//         "Extra Travel 1",
//         "Extra Travel 2",
//         "Extra Travel 3",
//         "Extra Travel 4",
//         "Extra Travel 5"
//     ];

//     const MAX_DISPLAY = 5; // show first 30 items
//     const container = $('#customerResults');

//     // Function to render results using DocumentFragment
//     function showResults(filtered) {
//         container.empty();
//         if (filtered.length === 0) {
//             container.hide();
//             return;
//         }

//         // Create a fragment
//         const fragment = $(document.createDocumentFragment());
//         filtered.slice(0, MAX_DISPLAY).forEach(r => {
//             fragment.append(`<button type="button" class="list-group-item list-group-item-action">${r}</button>`);
//         });

//         container.append(fragment);
//         container.show();
//     }

//     // Click/focus shows first 30 items
//     $('#Customer').on('focus click', function() {
//         showResults(customers);
//     });

//     // Filter while typing
//     $('#Customer').on('input', function() {
//         const query = $(this).val().toLowerCase();
//         if (query === "") {
//             showResults(customers);
//             return;
//         }
//         const filtered = customers.filter(c => c.toLowerCase().includes(query));
//         showResults(filtered);
//     });

//     // Select an item
//     $(document).on('click', '#customerResults button', function() {
//         $('#Customer').val($(this).text());
//         container.hide();
//     });

//     // Hide dropdown when clicking outside
//     $(document).click(function(e) {
//         if (!$(e.target).closest('#Customer, #customerResults').length) {
//             container.hide();
//         }
//     });
// });

$(document).ready(function() {
    const MAX_DISPLAY = 5; // visible items in dropdown
    const MAX_CUSTOMER = 'INFINITE';
    const container = $('#customerResults');
    const hiddenCustomerId = $('#CustomerID');
    const infoSpan = $('#customerInfo'); // new info span

    // Function to render results
    function showResults(customers) {
        container.empty();
        if (customers.length === 0) {
            container.hide();
            return;
        }

        const fragment = $(document.createDocumentFragment());
        customers.forEach(c => {
            fragment.append(`
                <button type="button" 
                    class="list-group-item list-group-item-action"
                    data-id="${c.CustomerID}"
                    data-name="${c.name}"
                    data-phone="${c.phone_number}"
                    data-code="${c.CustomerCode ?? ''}">
                    ${c.name} (${c.phone_number})
                </button>
            `);
        });
        container.append(fragment);
        container.show();

        // Fix height for MAX_DISPLAY
        const itemHeight = container.find('button').first().outerHeight() || 40;
        container.css({
            'max-height': itemHeight * MAX_DISPLAY + 'px',
            'overflow-y': 'auto'
        });
    }

    // Fetch from backend
    function fetchCustomers(query = '') {
        $.ajax({
            url: "<?= base_url('customer/search'); ?>",
            type: "GET",
            data: { q: query, limit: MAX_CUSTOMER },
            dataType: "json",
            success: function(data) {
                showResults(data);
            }
        });
    }

    // Show top items on focus/click
    $('#Customer').on('focus click', function() {
        fetchCustomers('');
    });

    // Filter while typing
    $('#Customer').on('input', function() {
        const query = $(this).val();
        hiddenCustomerId.val(''); // clear selection if typing
        fetchCustomers(query);
        updateInfo(false); // reset to new customer message
    });

    // Click to select existing customer
    $(document).on('click', '#customerResults button', function() {
        const name = $(this).data('name');
        const id = $(this).data('id');
        const code = $(this).data('code');
        const phone = $(this).data('phone');

        $('#Customer').val(name);
        hiddenCustomerId.val(id);
        container.hide();

        updateInfo(true, { code, phone });
    });

    // Hide dropdown when clicking outside
    $(document).click(function(e) {
        if (!$(e.target).closest('#Customer, #customerResults').length) {
            container.hide();
        }
    });

    // Update info span
    function updateInfo(isExisting, data = {}) {
        if (isExisting) {
            infoSpan
                .removeClass('text-muted text-primary')
                .addClass('text-success')
                .html(`<i class="la la-check-circle"></i> Existing Customer — <strong>${data.code || 'Empty Customer Code '}</strong> (${data.phone || 'No phone number'})`);
        } else {
            infoSpan
                .removeClass('text-success')
                .addClass('text-muted')
                .html('&laquo; New Customer &raquo;');
        }
    }

    // Initial setup (for update page)
    if (hiddenCustomerId.val()) {
        updateInfo(true, {
            code: "<?= isset($CustomerCode) ? $CustomerCode : 'N/A'; ?>",
            phone: "<?= isset($CustomerMobile) ? $CustomerMobile : 'N/A'; ?>"
        });
    } else {
        updateInfo(false);
    }
});



</script>
