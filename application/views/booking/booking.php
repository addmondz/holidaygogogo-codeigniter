
<div class="d-flex flex-column-fluid">

    <div class="container-fluid">

        <div class="card card-custom mb-5">

            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">

                <div class="card-title d-flex justify-content-between align-items-center w-100">

                    <h3 class="card-label" style="color:#6082B6; margin: 0;">

                        <strong><?php if(current_url() == base_url('Booking/Create') || current_url() == base_url('Booking/Duplicate')) { echo 'New Booking Record'; } else { echo 'Booking Record : ' . $BookingNumber; } ?></strong>

                    </h3>

                    <?php if(current_url() == base_url('Booking/Update') && !empty($display_status)): ?>
                        <?php 
                        // Load helper if not already loaded
                        $this->load->helper('booking_flow');
                        
                        $status = $display_status;
                        $status_code = $status['status_code'];
                        $status_text = $status['status_text'];
                        $status_color = $status['status_color'];
                        $all_statuses = !empty($status['all_statuses']) ? $status['all_statuses'] : [$status_code];
                        $status_info = get_booking_status_info();
                        ?>
                        <div class="booking-status-display">
                            <!-- Primary Status Badge -->
                            <span class="badge badge-lg" style="background-color: <?php echo $status_color; ?>; color: #fff; font-size: 0.9rem; font-weight: 600; padding: 8px 16px; border-radius: 4px;">
                                <?php echo htmlspecialchars($status_text); ?>
                            </span>
                            
                            <?php if(count($all_statuses) > 1): ?>
                                <!-- Multiple Statuses Indicator -->
                                <?php
                                $status_labels = array();
                                foreach($all_statuses as $s) {
                                    $status_labels[] = isset($status_info['texts'][$s]) ? $status_info['texts'][$s] : $s;
                                }
                                ?>
                                <span class="badge badge-secondary ml-2" style="font-size: 0.75rem; padding: 4px 8px; cursor: help;" 
                                      data-toggle="tooltip" 
                                      data-placement="left" 
                                      title="Multiple statuses: <?php echo htmlspecialchars(implode(', ', $status_labels)); ?>">
                                    <i class="la la-info-circle"></i> <?php echo count($all_statuses); ?> status<?php echo count($all_statuses) > 1 ? 'es' : ''; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

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
                                <small id="ReservationNumberError" class="text-danger"></small>

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

                                <label>Sales Agent 2</label>

                                <select id="SalesAgent2" data-live-search="true" class="form-control selectpicker">

                                    <option data-icon="la la-user-alt font-size-lg bs-icon" value="">--SELECT SALES AGENT 2--</option>

                                    <?php foreach($admins as $admin) { ?>

                                        <option <?php if((current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) && $admin->AdminID == $SalesAgent2) { echo 'selected'; } ?> data-icon="la la-user-alt font-size-lg bs-icon" value="<?php echo $admin->AdminID; ?>"><?php echo $admin->Name; ?></option>

                                    <?php } ?>

                                </select>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="form-group">

                                <label>Booking OP</label>

                                <select id="BookingOP" data-live-search="true" class="form-control selectpicker">

                                    <option data-icon="la la-user-cog font-size-lg bs-icon" value="">--SELECT BOOKING OP--</option>

                                    <?php foreach($booking_op_admins as $admin) { ?>

                                        <option <?php if((current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) && $admin->AdminID == $BookingOP) { echo 'selected'; } ?> data-icon="la la-user-cog font-size-lg bs-icon" value="<?php echo $admin->AdminID; ?>"><?php echo $admin->Name; ?></option>

                                    <?php } ?>

                                </select>

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
                                <small id="MobileError" class="text-danger"></small>


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

                        <?php if(current_url() == base_url('Booking/Create')) { ?>
                            <div class="col-lg-6 col-md-12">
                                <?php print_allow_review($AllowReview); ?>
                            </div>
                        <?php } ?>

                    </div>

                    <div class="d-flex justify-content-between border-top pt-5"></div>

                    <strong>Payment Deadline :</strong>

                    <br><br>

                    <div class="row">

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

                                <div class="col-12">
                                    <div class="row" id="deposit_container" <?php 
                                        // Hide deposit container if DepositDeadline is null or empty
                                        $deposit_deadline_value = '';
                                        if(current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) {
                                            $deposit_deadline_value = isset($DepositDeadline) ? $DepositDeadline : '';
                                        }
                                        if(empty($deposit_deadline_value)) {
                                            echo 'style="display: none;"';
                                        }
                                    ?>>
                                        <?php
                                            $deposit_mode_value = 'percentage';
                                            if(current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) {
                                                $deposit_mode_value = isset($DepositMode) ? $DepositMode : 'percentage';
                                            }
                                            $deposit_fixed_amount_value = 0;
                                            if(current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) {
                                                $deposit_fixed_amount_value = isset($DepositFixedAmount) ? $DepositFixedAmount : 0;
                                            }
                                        ?>
                                        <div class="col-md-3 mt-5">
                                            <label style="color:#50C878;">Deposit Mode</label>
                                            <select id="DepositMode" class="form-control">
                                                <option value="percentage" <?php echo $deposit_mode_value == 'percentage' ? 'selected' : ''; ?>>Percentage</option>
                                                <option value="fixed" <?php echo $deposit_mode_value == 'fixed' ? 'selected' : ''; ?>>Fixed Amount</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mt-5">
                                            <label id="DepositValueLabel" style="color:#50C878;"><?php echo $deposit_mode_value == 'fixed' ? 'Deposit Amount (RM)' : 'Deposit Percentage'; ?></label>
                                            <div class="input-icon">
                                                <?php if($deposit_mode_value == 'fixed') { ?>
                                                    <input type="number" id="DepositPercentage" class="form-control" style="text-align:right; display:none;" min="0" max="100" <?php if(current_url() == base_url('Booking/Create')) { ?> value="50" <?php } else { ?> value="<?php echo isset($DepositPercentage) ? $DepositPercentage : 0; ?>" <?php } ?>>
                                                    <input type="number" id="DepositFixedAmount" class="form-control" style="text-align:right;" min="0" step="0.01" value="<?php echo $deposit_fixed_amount_value; ?>">
                                                <?php } else { ?>
                                                    <input type="number" id="DepositPercentage" class="form-control" style="text-align:right;" min="0" max="100" <?php if(current_url() == base_url('Booking/Create')) { ?> value="50" <?php } else { ?> value="<?php echo isset($DepositPercentage) ? $DepositPercentage : 0; ?>" <?php } ?>>
                                                    <input type="number" id="DepositFixedAmount" class="form-control" style="text-align:right; display:none;" min="0" step="0.01" value="<?php echo $deposit_fixed_amount_value; ?>">
                                                <?php } ?>
                                                <span id="DepositValueIcon">
                                                    <i class="la <?php echo $deposit_mode_value == 'fixed' ? 'la-dollar' : 'la-percentage'; ?>"></i>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-md-3 mt-5">
                                            <label style="color:#50C878;">Deposit Total (RM)</label>
                                            <div class="input-icon">
                                                <input disabled type="text" id="DepositTotal" value="0.00" class="form-control" style="text-align:right;">
                                                <span>
                                                    <i class="la la-dollar"></i>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-md-3 mt-5">
                                            <label style="color:#50C878;">Deposit Paid (RM)</label>
                                            <div class="input-icon">
                                                <input disabled type="text" id="DepositPaid" value="<?php echo isset($DepositPaidDisplay) ? $DepositPaidDisplay : '0.00'; ?>" class="form-control" style="text-align:right; <?php echo (isset($DepositPaidColor) && ($DepositPaidColor == '#FF6B6B' || $DepositPaidColor == '#FFA500')) ? 'color: ' . $DepositPaidColor . '; font-weight: bold;' : ''; ?>">
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

                    <br>

                    <?php if(current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Create') || current_url() == base_url('Booking/Duplicate')) { ?>
                    <div class="d-flex justify-content-between border-top pt-5"></div>

                    <strong>Room Management :</strong>

                    <br><br>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="card card-custom mb-5" style="border: 1px solid #D7E2F2;">
                                <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                                    <div class="card-title">
                                        <h3 class="card-label" style="color:#6082B6;">
                                            <strong>Room Management</strong>
                                        </h3>
                                    </div>
                                    <div class="card-toolbar">
                                        <?php if(isset($LockStatus) && $LockStatus == 'Y') { ?>
                                            <span class="badge badge-danger font-weight-bold"><i class="la la-lock"></i> GL Locked</span>
                                        <?php } else { ?>
                                            <button type="button" id="create_room_btn" class="btn btn-sm btn-light-success font-weight-bold">
                                                <i class="la la-plus"></i> Add Room
                                            </button>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <small class="text-muted">
                                            Total Room Pax — Adult: <strong id="allocated_adult">0</strong>,
                                            Children: <strong id="allocated_child">0</strong>,
                                            Infant: <strong id="allocated_infant">0</strong>
                                        </small>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-head-solid" id="rooms_table">
                                            <thead>
                                                <tr>
                                                    <th>Room Name</th>
                                                    <th class="text-center" style="width:130px;">Adult</th>
                                                    <th class="text-center" style="width:130px;">Child</th>
                                                    <th class="text-center" style="width:130px;">Infant</th>
                                                    <th class="text-center" style="width:120px;">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="rooms_list">
                                                <tr id="no_rooms_row"><td colspan="5" class="text-muted text-center">No rooms created yet. Click "Add Room" to create one.</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <br>
                    <?php } ?>

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

                            <div id="travel_voucher_key_contacts" class="alert alert-custom alert-light-info fade show mb-5" style="overflow-x:auto;">

                                <div class="alert-text">

                                    <label style="color:#3F4254;">Key Contacts :</label>

                                    <textarea id="kt-tinymce-6" autocomplete="off" class="tox-target"><?php if(current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) { echo isset($KeyContacts) ? $KeyContacts : ''; } ?></textarea>

                                </div>

                            </div>

                            <div id="travel_voucher_special_remarks" class="alert alert-custom alert-light-info fade show mb-5" style="overflow-x:auto;">

                                <div class="alert-text">

                                    <label style="color:#3F4254;">Special Remarks :</label>

                                    <textarea id="kt-tinymce-7" autocomplete="off" class="tox-target"><?php if(current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) { echo isset($SpecialRemarks) ? $SpecialRemarks : ''; } ?></textarea>

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

        <?php if(current_url() == base_url('Booking/Update')) { ?>
            <div class="row">
                <!-- Internal Comments Section -->
                <div class="col-lg-6 col-md-12">
                    <div class="row">
                        <div class="col">
                            <div class="card card-custom" id="internal-comments">
                                <div class="card-header flex-wrap py-2" style="background-color:#D7E2F2;">
                                    <div class="card-title">
                                        <h4 class="card-label mb-0" style="color:#6082B6; font-size: 1.1rem;">
                                            <strong>Internal Comments</strong>
                                        </h4>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <!-- Comments List -->
                                    <div id="internal-comments-list" class="mb-2">
                                        <div class="text-center text-muted py-2" style="font-size: 0.8125rem;">
                                            <i class="la la-spinner la-spin"></i> Loading comments...
                                        </div>
                                    </div>

                                    <!-- Add New Comment Form -->
                                    <div class="border-top pt-2">
                                        <div class="form-group mb-2">
                                            <textarea id="new-comment-content" class="form-control" rows="2" placeholder="Enter your comment here..." style="font-size: 0.8125rem;"></textarea>
                                        </div>
                                        <div class="form-group mb-2">
                                            <label class="font-weight-bold" style="font-size: 0.8125rem;">Also Notify</label>
                                            <select id="comment-notify-users" multiple="multiple" data-live-search="true" data-actions-box="true" class="form-control selectpicker" title="--Select additional users to notify--">
                                                <?php foreach($admins as $admin) { ?>
                                                    <?php if($admin->Status == 'Y') { ?>
                                                        <option data-icon="la la-user-alt font-size-lg bs-icon" value="<?php echo $admin->AdminID; ?>"><?php echo $admin->Name; ?></option>
                                                    <?php } ?>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <button type="button" id="add-comment-btn" class="btn btn-primary btn-sm font-weight-bold mt-2 mb-2">
                                            <i class="la la-comment"></i> Add Comment
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-5">
                        <div class="col">
                            <div class="card card-custom" id="customer-remarks">
                                <div class="card-header flex-wrap py-2" style="background-color:#E8F5E9;">
                                    <div class="card-title">
                                        <h4 class="card-label mb-0" style="color:#4CAF50; font-size: 1.1rem;">
                                            <strong>Customer Remarks</strong>
                                        </h4>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <!-- Customer Remarks List -->
                                    <div id="customer-remarks-list" class="mb-2">
                                        <div class="text-center text-muted py-2" style="font-size: 0.8125rem;">
                                            <i class="la la-spinner la-spin"></i> Loading customer remarks...
                                        </div>
                                    </div>

                                    <!-- Add Admin Remark Form (for responding to customer) -->
                                    <div class="border-top pt-2">
                                        <div class="form-group mb-2">
                                            <textarea id="new-admin-remark-content" class="form-control" rows="2" placeholder="Add your response or remark here..." style="font-size: 0.8125rem;"></textarea>
                                        </div>
                                        <button type="button" id="add-admin-remark-btn" class="btn btn-primary btn-sm font-weight-bold mt-2 mb-2">
                                            <i class="la la-comment"></i> Add Remark
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-5">
                        <div class="col">
                            <div class="card card-custom">
                                <div class="card-header flex-wrap py-2" style="background-color:#D7E2F2;">
                                    <div class="card-title">
                                        <h4 class="card-label mb-0" style="color:#6082B6; font-size: 1.1rem;">
                                            <strong>Custom Uploads</strong>
                                        </h4>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <form id="custom_upload_form" enctype="multipart/form-data" style="border-bottom:1px solid #D7E2F2; padding-bottom:15px;">
                                                <input type="hidden" id="upload_booking_id" value="<?php echo $BookingID; ?>">
                                                
                                                <div class="row">
                                                    <div class="col">
                                                        <div class="form-group">
                                                            <label>Document Name <span style="color:red;">*</span></label>
                                                            <input type="text" id="upload_name" class="form-control" placeholder="Enter document name" required>
                                                        </div>
                                                    </div>
                                                    <div class="col">
                                                        <div class="form-group">
                                                            <label>Select File <span style="color:red;">*</span></label>
                                                            <div class="custom-file">
                                                                <input type="file" class="custom-file-input" id="upload_file" accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx,.xls,.xlsx,.txt" required>
                                                                <label class="custom-file-label" for="upload_file">Choose file</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-auto">
                                                        <button type="submit" class="btn btn-primary btn-block btn-sm" id="upload_btn">
                                                            <i class="la la-upload"></i> Upload
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>

                                             <div id="custom_uploads_list" class="mt-4">
                                                 <strong class="mb-3 d-block">Uploaded Documents</strong>
                                                 <div id="uploads_container">
                                                     <?php if (!empty($custom_uploads)): ?>
                                                         <?php foreach ($custom_uploads as $upload): ?>
                                                             <div class="upload-item mb-3 border rounded" data-upload-id="<?php echo $upload['id']; ?>" style="background: #fff; transition: all 0.2s;">
                                                                 <div class="p-3 d-flex justify-content-between align-items-end">
                                                                     <div class="flex-grow-1 min-w-0 mr-3">
                                                                         <div class="mb-1">
                                                                             <strong class="d-block text-truncate" style="font-size: 0.95rem; color: #212529;"><?php echo htmlspecialchars($upload['upload_name']); ?></strong>
                                                                         </div>
                                                                         <small class="text-muted d-block" style="font-size: 0.75rem; line-height: 1.4;">
                                                                             <i class="la la-user-circle"></i> <?php echo htmlspecialchars($upload['CreatedByName']); ?><br>
                                                                             <i class="la la-clock"></i> <?php echo return_timestamp_output($upload['created_at']); ?>
                                                                         </small>
                                                                     </div>
                                                                     <div class="d-flex align-items-center flex-shrink-0">
                                                                         <a href="<?php echo base_url($upload['upload_content']); ?>" target="_blank" class="btn btn-sm btn-light-primary mr-2" title="View Document" style="min-width: 50px; display: flex; align-items: center; justify-content: center;">
                                                                             <i class="la la-eye"></i>
                                                                         </a>
                                                                         <a href="<?php echo base_url($upload['upload_content']); ?>" download class="btn btn-sm btn-light-success mr-2" title="Download Document" style="min-width: 50px; display: flex; align-items: center; justify-content: center;">
                                                                             <i class="la la-download"></i>
                                                                         </a>
                                                                         <?php if ($upload['created_by'] == $this->session->userdata('admin_id')) { ?>
                                                                             <button type="button" class="btn btn-sm btn-light-danger delete-upload" data-upload-id="<?php echo $upload['id']; ?>" title="Delete Document" style="min-width: 50px; display: flex; align-items: center; justify-content: center;">
                                                                                 <i class="la la-trash"></i>
                                                                             </button>
                                                                         <?php } ?>
                                                                     </div>
                                                                 </div>
                                                             </div>
                                                         <?php endforeach; ?>
                                                     <?php else: ?>
                                                         <div class="text-center text-muted py-5">
                                                             <i class="la la-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                                                             <p class="mt-3 mb-0" style="font-size: 0.9rem;">No documents uploaded yet</p>
                                                         </div>
                                                     <?php endif; ?>
                                                 </div>
                                             </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 col-md-12  mt-md-6 mt-lg-0">
                    <div class="row">
                        <div class="col">
                            <div class="card card-custom">
                                <div class="card-header flex-wrap py-2" style="background-color:#D7E2F2;">
                                    <div class="card-title">
                                        <h4 class="card-label mb-0" style="color:#6082B6; font-size: 1.1rem;">
                                            <strong>Customer Review</strong>
                                        </h4>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label>Customer Review</label>
                                                <textarea id="CustomerReview" class="form-control auto-resize-textarea" disabled style="overflow: scroll;"><?php echo $CustomerReview; ?></textarea>
                                                <small class="font-italic" style="display: <?php echo isset($CustomerReviewTimestamp) && !empty($CustomerReviewTimestamp) ? 'block' : 'none'; ?>;"> Review provided on <?php echo date('d/m/Y h:i:s A', strtotime($CustomerReviewTimestamp)); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col">
                                            <?php print_allow_review($AllowReview); ?>
                                        </div>
                                    </div>

                                    <button type="button" id="update_allow_review_btn" class="btn btn-primary font-weight-bold btn-sm">
                                        <i class="la la-save"></i> Update
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <!-- Booking Checklist Section -->
                            <?php if(isset($booking_checklists) && !empty($booking_checklists['groups'])) {
                                $is_multi = $booking_checklists['is_multi_product'];
                                $groups = $booking_checklists['groups'];
                                $total_count = $booking_checklists['total_count'];
                                $completion_map = isset($completion_map) ? $completion_map : array();
                                $checked_count = 0;
                            ?>
                                <div class="row mt-5">
                                    <div class="col-12">
                                        <div class="card card-custom">
                                            <div class="card-header flex-wrap py-2" style="background-color:#D7E2F2;">
                                                <div class="card-title">
                                                    <h4 class="card-label mb-0" style="color:#6082B6; font-size: 1.1rem;">
                                                        <strong>Booking Checklist</strong>
                                                    </h4>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <?php foreach($groups as $group_index => $group) { ?>
                                                    <?php if($is_multi && $group_index > 0) { ?>
                                                        <div style="margin: 1rem 0;"></div>
                                                    <?php } ?>
                                                    <h6 class="font-weight-bold mb-3" style="color:#6082B6;">
                                                        <?php echo htmlspecialchars($group['product_name']); ?>
                                                    </h6>
                                                    <?php if(!empty($group['PaymentOutSupplierDeposit']) || !empty($group['PaymentOutSupplierFull'])) { ?>
                                                        <div class="mb-3" style="margin-top:-0.5rem;">
                                                            <?php if(!empty($group['PaymentOutSupplierDeposit'])) { ?>
                                                                <span class="label label-inline label-light-warning font-weight-bold mr-2">
                                                                    <i class="la la-calendar-check-o mr-1" style="font-size:14px;"></i>Supplier Deposit: <?php echo $group['PaymentOutSupplierDeposit']; ?>
                                                                </span>
                                                            <?php } ?>
                                                            <?php if(!empty($group['PaymentOutSupplierFull'])) { ?>
                                                                <span class="label label-inline label-light-primary font-weight-bold">
                                                                    <i class="la la-calendar-check-o mr-1" style="font-size:14px;"></i>Supplier Full: <?php echo $group['PaymentOutSupplierFull']; ?>
                                                                </span>
                                                            <?php } ?>
                                                        </div>
                                                    <?php } ?>
                                                    <div class="checklist-container">
                                                        <?php foreach($group['checklists'] as $checklist) {
                                                            $is_checked = isset($completion_map[$group['product_id']][$checklist->ID]);
                                                            $completion_info = $is_checked ? $completion_map[$group['product_id']][$checklist->ID] : null;
                                                            if($is_checked) {
                                                                $checked_count++;
                                                            }
                                                        ?>
                                                            <div class="checklist-item <?php echo $is_checked ? 'checked' : ''; ?>" data-checklist-id="<?php echo $checklist->ID; ?>">
                                                                <div class="checklist-item-content">
                                                                    <div class="checklist-checkbox-wrapper">
                                                                        <input class="form-check-input checklist-checkbox" type="checkbox"
                                                                            id="checklist_<?php echo $group['product_id']; ?>_<?php echo $checklist->ID; ?>"
                                                                            value="<?php echo $group['product_id'] . '_' . $checklist->ID; ?>"
                                                                            <?php echo $is_checked ? 'checked' : ''; ?>>
                                                                        <label class="checklist-checkbox-label" for="checklist_<?php echo $group['product_id']; ?>_<?php echo $checklist->ID; ?>"></label>
                                                                    </div>
                                                                    <div class="checklist-details">
                                                                        <div class="checklist-name-wrapper">
                                                                            <label class="checklist-name" for="checklist_<?php echo $group['product_id']; ?>_<?php echo $checklist->ID; ?>">
                                                                                <?php echo htmlspecialchars($checklist->name); ?>
                                                                            </label>
                                                                        </div>
                                                                        <?php if($is_checked && $completion_info) { ?>
                                                                            <div class="checklist-completion-info">
                                                                                <i class="la la-user-circle text-primary"></i>
                                                                                <span class="completion-text">
                                                                                    Completed by <strong><?php echo htmlspecialchars($completion_info['created_by_name']); ?></strong>
                                                                                    <span class="completion-separator">•</span>
                                                                                    <span class="completion-date"><?php echo return_timestamp_output($completion_info['created_at']); ?></span>
                                                                                </span>
                                                                            </div>
                                                                        <?php } ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php } ?>
                                                    </div>
                                                <?php } ?>

                                                <div class="checklist-footer">
                                                    <div class="checklist-progress">
                                                        <div class="progress-info">
                                                            <span class="progress-text">
                                                                <strong><?php echo $checked_count; ?></strong> of <strong><?php echo $total_count; ?></strong> items completed
                                                            </span>
                                                            <span class="progress-percentage"><?php echo $total_count > 0 ? round(($checked_count / $total_count) * 100) : 0; ?>%</span>
                                                        </div>
                                                        <div class="progress-bar-wrapper">
                                                            <div class="progress" style="height: 8px; background-color: #e9ecef; border-radius: 4px;">
                                                                <div class="progress-bar bg-success" role="progressbar"
                                                                     style="width: <?php echo $total_count > 0 ? ($checked_count / $total_count) * 100 : 0; ?>%;"
                                                                     aria-valuenow="<?php echo $checked_count; ?>"
                                                                     aria-valuemin="0"
                                                                     aria-valuemax="<?php echo $total_count; ?>">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <button type="button" id="update_checklist_btn" class="btn btn-primary btn-sm font-weight-bold">
                                                        <i class="la la-save"></i> Save Changes
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>

                            <!-- E-Invoice Request by Pax (Read-Only) -->
                            <?php if(!empty($invoice_split)) { ?>
                                <div class="row mt-5">
                                    <div class="col-12">
                                        <div class="card card-custom">
                                            <div class="card-header flex-wrap py-2" style="background-color:#D7E2F2;">
                                                <div class="card-title">
                                                    <h4 class="card-label mb-0" style="color:#6082B6; font-size: 1.1rem;">
                                                        <strong>E-Invoice Request by Pax</strong>
                                                    </h4>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <?php
                                                $grand_subtotal = 0;
                                                $grand_discount = 0;
                                                $grand_net = 0;
                                                foreach($invoice_split as $idx => $pax) {
                                                    $grand_subtotal += $pax['SubtotalAmount'];
                                                    $grand_discount += $pax['DiscountAmount'];
                                                    $grand_net += $pax['NetAmount'];
                                                ?>
                                                <div style="background: #f8f9fa; border-radius: 6px; padding: 12px; margin-bottom: 10px; border: 1px solid #e8e8e8;">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <strong>Pax <?php echo $idx + 1; ?>: <?php echo htmlspecialchars($pax['PaxName']); ?></strong>
                                                        <?php if(!empty($pax['TIN'])) { ?>
                                                            <span class="text-muted" style="font-size: 0.85rem;">TIN: <?php echo htmlspecialchars($pax['TIN']); ?></span>
                                                        <?php } ?>
                                                    </div>
                                                    <div class="text-muted mb-2" style="font-size: 0.82rem;">
                                                        <?php if(!empty($pax['Email'])) { ?>
                                                            <span class="mr-3">Email: <?php echo htmlspecialchars($pax['Email']); ?></span>
                                                        <?php } ?>
                                                        <?php if(!empty($pax['PhoneNumber'])) { ?>
                                                            <span>Phone: <?php echo htmlspecialchars($pax['PhoneNumber']); ?></span>
                                                        <?php } ?>
                                                        <?php if(!empty($pax['Address'])) { ?>
                                                            <div>Address: <?php echo htmlspecialchars($pax['Address']); ?></div>
                                                        <?php } ?>
                                                    </div>
                                                    <table class="table table-sm table-bordered mb-2" style="font-size: 0.85rem;">
                                                        <thead style="background: #e9ecef;">
                                                            <tr>
                                                                <th>Product</th>
                                                                <th class="text-center" style="width:80px;">Qty</th>
                                                                <th class="text-right" style="width:120px;">Unit Price</th>
                                                                <th class="text-right" style="width:120px;">Amount</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach($pax['products'] as $prod) { ?>
                                                                <tr>
                                                                    <td><?php echo htmlspecialchars($prod['ProductName']); ?></td>
                                                                    <td class="text-center"><?php echo rtrim(rtrim(number_format($prod['Quantity'], 2), '0'), '.'); ?></td>
                                                                    <td class="text-right">RM <?php echo number_format($prod['UnitPrice'], 2); ?></td>
                                                                    <td class="text-right">RM <?php echo number_format($prod['Amount'], 2); ?></td>
                                                                </tr>
                                                            <?php } ?>
                                                        </tbody>
                                                    </table>
                                                    <div class="text-right" style="font-size: 0.85rem;">
                                                        <span>Subtotal: <strong>RM <?php echo number_format($pax['SubtotalAmount'], 2); ?></strong></span>
                                                        <?php if(floatval($pax['DiscountAmount']) > 0) { ?>
                                                            <span class="ml-3 text-danger">Discount: <strong>- RM <?php echo number_format($pax['DiscountAmount'], 2); ?></strong></span>
                                                        <?php } ?>
                                                        <span class="ml-3" style="color:#6082B6;">Net: <strong>RM <?php echo number_format($pax['NetAmount'], 2); ?></strong></span>
                                                    </div>
                                                </div>
                                                <?php } ?>
                                                <div class="text-right mt-3 p-3" style="background: #D7E2F2; border-radius: 6px; font-size: 0.9rem;">
                                                    <span>Grand Subtotal: <strong>RM <?php echo number_format($grand_subtotal, 2); ?></strong></span>
                                                    <?php if($grand_discount > 0) { ?>
                                                        <span class="ml-3">Discount: <strong>- RM <?php echo number_format($grand_discount, 2); ?></strong></span>
                                                    <?php } ?>
                                                    <span class="ml-3"><strong>Net Total: RM <?php echo number_format($grand_net, 2); ?></strong></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>

                             <!-- Booking Status Log Timeline -->
                            <?php if(!empty($status_logs)) { ?>
                                <div class="row mt-5">
                                    <div class="col-12">
                                        <div class="card card-custom">
                                            <div class="card-header flex-wrap py-2" style="background-color:#D7E2F2;">
                                                <div class="card-title">
                                                    <h4 class="card-label mb-0" style="color:#6082B6; font-size: 1.1rem;">
                                                        <strong><i class="la la-history"></i> Status History</strong>
                                                    </h4>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <!-- <div class="timeline-container" style="max-height: 500px; overflow-y: auto;"> -->
                                                <div class="timeline-container">
                                                    <?php foreach($status_logs as $log): ?>
                                                        <div class="timeline-item status-log-item <?php echo $log['status']; ?>" style="padding: 12px 0; border-left: 2px solid #e0e0e0; padding-left: 20px; margin-left: 15px; position: relative;">
                                                            <div class="timeline-marker" style="position: absolute; left: -7px; top: 15px; width: 12px; height: 12px; border-radius: 50%; background-color: <?php echo $log['status'] == 'completed' ? '#50C878' : ($log['status'] == 'cancelled' ? '#FF69B4' : '#FFBF00'); ?>; border: 2px solid white; box-shadow: 0 0 0 2px <?php echo $log['status'] == 'completed' ? '#50C878' : ($log['status'] == 'cancelled' ? '#FF69B4' : '#FFBF00'); ?>;"></div>
                                                            <div class="timeline-content">
                                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                                    <div class="timeline-title" style="font-weight: 600; color: #212529; font-size: 0.95rem;">
                                                                        <i class="<?php echo $log['icon']; ?>" style="margin-right: 6px; color: #6082B6;"></i>
                                                                        <?php echo htmlspecialchars($log['title']); ?>
                                                                    </div>
                                                                    <div class="timeline-date" style="font-size: 0.8125rem; color: #6c757d;">
                                                                        <?php echo return_timestamp_output($log['date_raw']); ?>
                                                                    </div>
                                                                </div>
                                                                <div class="timeline-description" style="font-size: 0.875rem; color: #495057; margin-top: 4px;">
                                                                    <?php echo htmlspecialchars($log['description']); ?>
                                                                </div>
                                                                <?php if(!empty($log['created_by']) && $log['created_by'] != 'System'): ?>
                                                                <div class="timeline-meta" style="font-size: 0.75rem; color: #868e96; margin-top: 6px;">
                                                                    <i class="la la-user"></i> Changed by: <?php echo htmlspecialchars($log['created_by']); ?>
                                                                </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>

                            <?php if(!empty($booking_logs)) { ?>
                                <div class="row mt-5">
                                    <div class="col-12">
                                        <div class="card card-custom">
                                        <div class="card-header flex-wrap py-2" style="background-color:#D7E2F2;">
                                            <div class="card-title">
                                                <h4 class="card-label mb-0" style="color:#6082B6; font-size: 1.1rem;">
                                                    <i class="la la-file-alt"></i> <strong>Audit Log</strong>
                                                </h4>
                                            </div>
                                        </div>
                                        <div class="card-body p-0">
                                            <div style="max-height: 500px; overflow-y: auto;">
                                                <table class="table table-striped table-bordered mb-0" style="font-size: 0.85rem;">
                                                    <thead style="position: sticky; top: 0; background: #f8f9fa; z-index: 1;">
                                                        <tr>
                                                            <th style="min-width: 140px;">Date/Time</th>
                                                            <th style="min-width: 120px;">Changed By</th>
                                                            <th style="min-width: 120px;">Field</th>
                                                            <th>Old Value</th>
                                                            <th>New Value</th>
                                                            <th style="min-width: 90px;">Snapshot</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach($booking_logs as $log): ?>
                                                        <tr>
                                                            <td><?php echo date('d M Y H:i', strtotime($log['InsertDate'])); ?></td>
                                                            <td><?php echo htmlspecialchars($log['AdminName'] ?? '-'); ?></td>
                                                            <td><?php echo audit_log_field_label($log['Column']); ?></td>
                                                            <td><?php echo audit_log_value($log['Column'], $log['CurrentData']); ?></td>
                                                            <td><?php echo audit_log_value($log['Column'], $log['NewData']); ?></td>
                                                            <td>
                                                                <?php if (!empty($log['SnapshotPDF'])): ?>
                                                                    <a href="<?php echo base_url('Booking/View_Snapshot?file=' . basename($log['SnapshotPDF'])); ?>" target="_blank" title="View previous booking confirmation">
                                                                        <i class="la la-file-pdf" style="font-size: 1.2rem;"></i> View PDF
                                                                    </a>
                                                                <?php else: ?>
                                                                    -
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>

                            <?php if(!empty($payment_logs)) { ?>
                                <div class="row mt-5">
                                    <div class="col-12">
                                        <div class="card card-custom">
                                        <div class="card-header flex-wrap py-2" style="background-color:#D7E2F2;">
                                            <div class="card-title">
                                                <h4 class="card-label mb-0" style="color:#6082B6; font-size: 1.1rem;">
                                                    <i class="la la-money-bill"></i> <strong>Payment Changes Log</strong>
                                                </h4>
                                            </div>
                                        </div>
                                        <div class="card-body p-0">
                                            <div style="max-height: 500px; overflow-y: auto;">
                                                <table class="table table-striped table-bordered mb-0" style="font-size: 0.85rem;">
                                                    <thead style="position: sticky; top: 0; background: #f8f9fa; z-index: 1;">
                                                        <tr>
                                                            <th style="min-width: 140px;">Date/Time</th>
                                                            <th style="min-width: 120px;">Changed By</th>
                                                            <th style="min-width: 140px;">Payment (Type)</th>
                                                            <th style="min-width: 120px;">Field</th>
                                                            <th>Old Value</th>
                                                            <th>New Value</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach($payment_logs as $log): ?>
                                                        <tr>
                                                            <td><?php echo date('d M Y H:i', strtotime($log['InsertDate'])); ?></td>
                                                            <td><?php echo htmlspecialchars($log['AdminName'] ?? '-'); ?></td>
                                                            <td><?php echo '#' . $log['PaymentID'] . ' (' . htmlspecialchars($log['PaymentType'] ?? '-') . ')'; ?></td>
                                                            <td><?php echo payment_log_field_label($log['Column']); ?></td>
                                                            <td><?php echo audit_log_value($log['Column'], $log['CurrentData']); ?></td>
                                                            <td><?php echo audit_log_value($log['Column'], $log['NewData']); ?></td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>

    </div>
</div>

<?php function print_allow_review($AllowReview) { ?>
    <div class="form-group">
        <label>Allow Customer Review</label>
        <select id="AllowReview" class="form-control selectpicker">
            <option value="1" <?php if((isset($AllowReview) && $AllowReview == 1) || !isset($AllowReview)) { echo 'selected'; } ?>>Yes</option>
            <option value="0" <?php if(isset($AllowReview) && $AllowReview == 0) { echo 'selected'; } ?>>No</option>
        </select>
    </div>
<?php }?>

<?php function audit_log_field_label($column) {
    $labels = array(
        'Status' => 'Status',
        'NetTotal' => 'Net Total',
        'GrossProfit' => 'Gross Profit',
        'StartDate' => 'Start Date',
        'EndDate' => 'End Date',
        'BookingConfirmationFooter' => 'Booking Confirmation Footer',
        'TravelVoucherFooter' => 'Travel Voucher Footer',
        'KeyContacts' => 'Key Contacts',
        'SpecialRemarks' => 'Special Remarks',
        'PaymentDueDate' => 'Payment Due Date',
        'CustomerID' => 'Customer',
        'SalesInCharge' => 'Sales In Charge',
        'OverallStatus' => 'Overall Status',
        'AllowReview' => 'Allow Customer Review',
        'CancellationReasonID' => 'Cancellation Reason',
    );
    return isset($labels[$column]) ? $labels[$column] : ucwords(str_replace('_', ' ', preg_replace('/([a-z])([A-Z])/', '$1 $2', $column)));
} ?>

<?php function audit_log_value($column, $value) {
    if ($value === null || $value === '') return '-';

    $html_fields = array('BookingConfirmationFooter', 'TravelVoucherFooter', 'KeyContacts', 'SpecialRemarks');

    if (in_array($column, $html_fields)) {
        $plain = trim(strip_tags($value));
        if ($plain === '') return '-';
        if (strlen($plain) > 80) {
            $id = 'audit_' . uniqid();
            return '<span id="' . $id . '_short">' . htmlspecialchars(substr($plain, 0, 80)) . '... <a href="javascript:void(0)" onclick="document.getElementById(\'' . $id . '_short\').style.display=\'none\';document.getElementById(\'' . $id . '_full\').style.display=\'inline\';">show more</a></span>'
                 . '<span id="' . $id . '_full" style="display:none;">' . htmlspecialchars($plain) . ' <a href="javascript:void(0)" onclick="document.getElementById(\'' . $id . '_full\').style.display=\'none\';document.getElementById(\'' . $id . '_short\').style.display=\'inline\';">show less</a></span>';
        }
        return htmlspecialchars($plain);
    }

    $escaped = htmlspecialchars($value);
    if (strlen($value) > 100) {
        return htmlspecialchars(substr($value, 0, 100)) . '...';
    }
    return $escaped;
} ?>

<?php function payment_log_field_label($column) {
    $labels = array(
        'Status' => 'Status',
        'Credit' => 'Credit (RM)',
        'Debit' => 'Debit (RM)',
        'Date' => 'Date',
        'Type' => 'Type',
        'ReferenceNumber' => 'Reference Number',
        'Deadline' => 'Deadline',
        'Bank' => 'Bank',
        'BankAccount' => 'Bank Account',
        'BankHolder' => 'Bank Holder',
        'BankSlip' => 'Bank Slip',
        'Quotation' => 'Quotation',
        'Invoice' => 'Invoice',
        'QuotationNumber' => 'Quotation Number',
        'InvoiceNumber' => 'Invoice Number',
        'Currency' => 'Currency',
        'ForeignCurrency' => 'Foreign Currency',
        'SupplierID' => 'Supplier',
        'BookingProductID' => 'Booking Product',
        'DebitRemark' => 'Debit Remark',
        'PaymentRemark' => 'Payment Remark',
    );
    return isset($labels[$column]) ? $labels[$column] : ucwords(str_replace('_', ' ', preg_replace('/([a-z])([A-Z])/', '$1 $2', $column)));
} ?>

<script>

    $('.alert-light-info').hide();

    <?php if((current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) && isset($BookingConfirmationFooterID) && !empty($BookingConfirmationFooterID)) { ?>

        $('#booking_confirmation_content').show();

    <?php } ?>

    <?php if((current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Duplicate')) && isset($TravelVoucherFooterID) && !empty($TravelVoucherFooterID)) { ?>

        $('#travel_voucher_content').show();

        $('#travel_voucher_key_contacts').show();

        $('#travel_voucher_special_remarks').show();

        <?php if(empty($KeyContacts) || empty($SpecialRemarks)) { ?>
            $(document).ready(function() {
                setTimeout(function() {
                    $.ajax({
                        url: '<?php echo base_url('Footer/Read') ?>',
                        type: 'get',
                        data: { footer_id: '<?php echo $TravelVoucherFooterID; ?>' },
                        dataType: 'json',
                        success: function(array) {
                            <?php if(empty($KeyContacts)) { ?>
                                if(array.KeyContacts && array.KeyContacts.trim() != '') {
                                    tinyMCE.editors[2].setContent(array.KeyContacts);
                                }
                            <?php } ?>
                            <?php if(empty($SpecialRemarks)) { ?>
                                if(array.SpecialRemarks && array.SpecialRemarks.trim() != '') {
                                    tinyMCE.editors[3].setContent(array.SpecialRemarks);
                                }
                            <?php } ?>
                        }
                    });
                }, 500);
            });
        <?php } ?>

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

    var initial_key_contacts = $('#kt-tinymce-6').val();

    var initial_special_remarks = $('#kt-tinymce-7').val();

    var product_sequence = [];



    function Reset_Deposit_Deadline() {
        $('input[name="DepositDeadline"]').val('');
        // Hide deposit container when Deposit Deadline is reset
        $('#deposit_container').hide();
    }
    
    // Function to toggle deposit container based on Deposit Deadline value
    function toggleDepositContainer() {
        var depositDeadline = $('input[name="DepositDeadline"]').val();
        if (depositDeadline && depositDeadline.trim() !== '') {
            $('#deposit_container').show();
        } else {
            $('#deposit_container').hide();
        }
    }
    
    // Check deposit deadline on page load
    $(document).ready(function() {
        toggleDepositContainer();
        
        // Listen for changes on Deposit Deadline field
        $('input[name="DepositDeadline"]').on('change', function() {
            toggleDepositContainer();
        });
        
        // Also listen for datepicker change events (if using datepicker)
        $('input[name="DepositDeadline"]').on('changeDate', function() {
            toggleDepositContainer();
        });
        
        // Listen for input events (when manually typing or clearing)
        $('input[name="DepositDeadline"]').on('input', function() {
            toggleDepositContainer();
        });
    });



    function Reset_Tag() {

        $('#Tag').val('').change();

    }



    function Reset_Additional_Payment_Deadline() {

        $('input[name="AdditionalPaymentDeadline"]').val('');

    }

    function Reset_ProductSupplierDeposit(booking_product_id) {

        $(`#PaymentOutSupplierDeposit-${booking_product_id}`).val('');

    }

    function Toggle_PaymentOutFields(booking_product_id) {

        if($(`#DisableChecklistPaymentOut-${booking_product_id}`).is(':checked')) {

            $(`#PaymentOutSection-${booking_product_id}`).hide();

            $(`#PaymentOutSupplierFull-${booking_product_id}`).val('');

            $(`#PaymentOutSupplierDeposit-${booking_product_id}`).val('');

        } else {

            $(`#PaymentOutSection-${booking_product_id}`).show();

        }

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

            $('#travel_voucher_key_contacts').hide();

            $('#travel_voucher_special_remarks').hide();

            tinyMCE.editors[1].setContent('');

            tinyMCE.editors[2].setContent('');

            tinyMCE.editors[3].setContent('');

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

                    // Populate Key Contacts and Special Remarks editors with footer values
                    tinyMCE.editors[2].setContent(array.KeyContacts || '');

                    $('#travel_voucher_key_contacts').show();

                    tinyMCE.editors[3].setContent(array.SpecialRemarks || '');

                    $('#travel_voucher_special_remarks').show();

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

                '<br>' +

                '<div class="row">' +

                    '<div class="col-md-12">' +

                        '<label class="checkbox checkbox-outline checkbox-primary">' +

                            '<input disabled type="checkbox" id="DisableChecklistPaymentOut-'+ booking_product_id +'" onchange="Toggle_PaymentOutFields('+ booking_product_id +')">' +

                            '<span></span>' +

                            '&nbsp;Disable Checklist & Payment Out' +

                        '</label>' +

                    '</div>' +

                '</div>' +

                '<div id="PaymentOutSection-'+ booking_product_id +'">' +

                '<br>' +

                '<div class="row">' +

                    '<div class="col-md-6 mb-7 mb-md-0">' +

                        '<label>Payment Out to Supplier (Deposit)' +

                            '<a onclick="Reset_ProductSupplierDeposit('+ booking_product_id +')" class="btn btn-icon btn-light-warning btn-xs">' +

                                '<i class="la la-undo"></i>' +

                            '</a>' +

                        '</label>' +

                        '<div class="input-icon">' +

                            '<input disabled readonly type="text" id="PaymentOutSupplierDeposit-'+ booking_product_id +'" autocomplete="off" class="form-control">' +

                            '<span>' +

                                '<i class="la la-calendar"></i>' +

                            '</span>' +

                        '</div>' +

                    '</div>' +

                    '<div class="col-md-6">' +

                        '<label>Payment Out to Supplier (Full) <span style="color:red;">*</span></label>' +

                        '<div class="input-icon">' +

                            '<input disabled readonly type="text" id="PaymentOutSupplierFull-'+ booking_product_id +'" autocomplete="off" class="form-control">' +

                            '<span>' +

                                '<i class="la la-calendar"></i>' +

                            '</span>' +

                        '</div>' +

                    '</div>' +

                '</div>' +

                '</div>' +

            '</div>' +

        '</div>');

        if(window.location.href == '<?php echo base_url('Booking/Create'); ?>' || window.location.href.split('?')[0] == '<?php echo base_url('Booking/Create'); ?>' || (window.location.href.split('?')[0] == '<?php echo base_url('Booking/Update'); ?>' || window.location.href.split('?')[0] == '<?php echo base_url('Booking/Duplicate'); ?>') && count > booking_products.length) {

            $(`#ProductID-${booking_product_id}`).selectpicker();

            $(`#PaymentOutSupplierFull-${booking_product_id}`).datepicker({format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true});

            $(`#PaymentOutSupplierDeposit-${booking_product_id}`).datepicker({format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true});

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

            var disable_checklist = $(`#DisableChecklistPaymentOut-${product_sequence[i]}`).is(':checked') ? 1 : 0;
            var supplier_full = null;
            var supplier_deposit = null;
            if(!disable_checklist) {
                supplier_full = $(`#PaymentOutSupplierFull-${product_sequence[i]}`).val();
                supplier_deposit = $(`#PaymentOutSupplierDeposit-${product_sequence[i]}`).val();
                if(supplier_full && supplier_full != '') {
                    var parts = supplier_full.split('/');
                    supplier_full = `${parts[2]}-${parts[1]}-${parts[0]}`;
                } else {
                    supplier_full = null;
                }
                if(supplier_deposit && supplier_deposit != '') {
                    var parts = supplier_deposit.split('/');
                    supplier_deposit = `${parts[2]}-${parts[1]}-${parts[0]}`;
                } else {
                    supplier_deposit = null;
                }
            }

            if(window.location.href == '<?php echo base_url('Booking/Create'); ?>' || window.location.href.split('?')[0] == '<?php echo base_url('Booking/Create'); ?>' || window.location.href.split('?')[0] == '<?php echo base_url('Booking/Duplicate'); ?>' || (window.location.href.split('?')[0] == '<?php echo base_url('Booking/Update'); ?>' && jQuery.inArray(product_sequence[i], array) == -1)) {

                booking_products.push({ProductID:product_id, ProductCode:product_code, Name:name, Description:description, Quantity:quantity, Price:price, Total:total, PaymentOutSupplierFull:supplier_full, PaymentOutSupplierDeposit:supplier_deposit, disable_checklist_payment_out:disable_checklist, InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});

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

            $(`#PaymentOutSupplierFull-${booking_product_id}`).removeAttr('disabled');

            $(`#PaymentOutSupplierDeposit-${booking_product_id}`).removeAttr('disabled');

            $(`#DisableChecklistPaymentOut-${booking_product_id}`).removeAttr('disabled');

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

        // Recalculate deposit amount when NetTotal changes
        Calculate_Deposit_Amount();

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

                // Recalculate deposit amount when NetTotal changes
                Calculate_Deposit_Amount();

            } else {

                $('#Discount').val('');

                $('#NetTotal').val(parseFloat(subtotal.replace(/,/g, '')).toLocaleString('en-US', {minimumFractionDigits: 2}));

                // Recalculate deposit amount when NetTotal changes
                Calculate_Deposit_Amount();

            }

        } else {

            $('#Discount').val('');

        }

    });

    // Store deposit paid value from PHP (loaded on page load)
    var deposit_paid_value = 0;
    $(document).ready(function() {
        // Get deposit paid value from the input field (set by PHP)
        // Extract numeric value (may include status text in parentheses)
        var deposit_paid_text = $('#DepositPaid').val();
        if(deposit_paid_text) {
            // Remove any text in parentheses and extract just the number
            var numeric_part = deposit_paid_text.split(' (')[0];
            deposit_paid_value = parseFloat(numeric_part.replace(/,/g, '')) || 0;
        }
        // Calculate deposit amount on page load
        Calculate_Deposit_Amount();
    });

    // Function to calculate deposit amount and status based on mode (percentage or fixed)
    function Calculate_Deposit_Amount() {
        var deposit_mode = $('#DepositMode').val();
        var net_total = $('#NetTotal').val();
        var deposit_total = 0;

        if(deposit_mode == 'fixed') {
            var fixed_amount = parseFloat($('#DepositFixedAmount').val());
            if(!isNaN(fixed_amount) && fixed_amount >= 0) {
                deposit_total = fixed_amount;
            }
        } else {
            var deposit_percentage = $('#DepositPercentage').val();
            if(net_total && deposit_percentage) {
                var net_total_value = parseFloat(net_total.replace(/,/g, ''));
                var percentage_value = parseFloat(deposit_percentage);
                if(!isNaN(net_total_value) && !isNaN(percentage_value) && percentage_value >= 0 && percentage_value <= 100) {
                    deposit_total = (net_total_value * percentage_value / 100);
                    deposit_total = Math.ceil(deposit_total);
                }
            }
        }

        $('#DepositTotal').val(deposit_total.toLocaleString('en-US', {minimumFractionDigits: 2}));

        // Calculate deposit status and update Deposit Paid field
        var deposit_difference = deposit_paid_value - deposit_total;
        var deposit_paid_display = '';
        var deposit_paid_color = '';

        if (deposit_paid_value > 0) {
            deposit_paid_display = deposit_paid_value.toLocaleString('en-US', {minimumFractionDigits: 2});

            if (deposit_difference > 0.01) {
                deposit_paid_display += ' (Overpaid: RM ' + Math.abs(deposit_difference).toLocaleString('en-US', {minimumFractionDigits: 2}) + ')';
                deposit_paid_color = '#FF6B6B';
            } else if (deposit_difference < -0.01) {
                deposit_paid_display += ' (Underpaid: RM ' + Math.abs(deposit_difference).toLocaleString('en-US', {minimumFractionDigits: 2}) + ')';
                deposit_paid_color = '#FFA500';
            } else {
                deposit_paid_color = '';
            }
        } else {
            deposit_paid_display = '0.00';
            deposit_paid_color = '';
        }

        $('#DepositPaid').val(deposit_paid_display);
        if (deposit_paid_color) {
            $('#DepositPaid').css('color', deposit_paid_color);
            $('#DepositPaid').css('font-weight', 'bold');
        } else {
            $('#DepositPaid').css('color', '');
            $('#DepositPaid').css('font-weight', '');
        }
    }

    // Toggle deposit mode: show/hide percentage vs fixed amount input
    $('#DepositMode').on('change', function() {
        var mode = $(this).val();
        if(mode == 'fixed') {
            $('#DepositPercentage').hide();
            $('#DepositFixedAmount').show();
            $('#DepositValueLabel').text('Deposit Amount (RM)');
            $('#DepositValueIcon').html('<i class="la la-dollar"></i>');
        } else {
            $('#DepositPercentage').show();
            $('#DepositFixedAmount').hide();
            $('#DepositValueLabel').text('Deposit Percentage');
            $('#DepositValueIcon').html('<i class="la la-percentage"></i>');
        }
        Calculate_Deposit_Amount();
    });

    // Calculate deposit amount when percentage changes
    $('#DepositPercentage').on('input change', function() {
        var percentage = $(this).val();

        // Validate percentage is between 0 and 100
        if(percentage < 0) {
            $(this).val(0);
        } else if(percentage > 100) {
            $(this).val(100);
        }

        Calculate_Deposit_Amount();
    });

    // Calculate deposit amount when fixed amount changes
    $('#DepositFixedAmount').on('input change', function() {
        var amount = parseFloat($(this).val());
        if(amount < 0) {
            $(this).val(0);
        }
        Calculate_Deposit_Amount();
    });

    // Calculate deposit amount when NetTotal changes (also triggered by Discount change)
    $('#NetTotal').on('change', function() {
        Calculate_Deposit_Amount();
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

                var booking_op = $('#BookingOP').val();

                var sales_agent_2 = $('#SalesAgent2').val();

                var chat_language = $('#ChatLanguage').val();

                var source = $('#Source').val();

                var bc_title = $('#BookingConfirmationTitle').val();

                 // Apply validation
                validateLength('ReservationNumber', 'ReservationNumberError', 20);
                validateLength('Mobile', 'MobileError', 25, true);

                const fields = {
                    'Country code': country_code,
                    'Reservation number': reservation_number,
                    'Full payment deadline': full_payment_deadline,
                    'Customer': customer,
                    'Mobile': mobile,
                    'Travel date': travel_date,
                    'Destination': destination,
                    'Sales agent': sales_agent,
                    'Chat language': chat_language,
                    'Source': source,
                    'BC title': bc_title,
                };

                const missing = Object.keys(fields).filter(
                    key => fields[key] === null || fields[key] === ''
                );

                if (missing.length) {
                    Display_Message(
                        '<?= base_url("assets/image/sweetalert.jpg") ?>',
                        `Please insert: ${missing.join(', ')}`,
                        null,
                        true
                    );
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

                                    if(!$(`#DisableChecklistPaymentOut-${booking_product_ids[i]}`).is(':checked')) {

                                        if($(`#PaymentOutSupplierFull-${booking_product_ids[i]}`).val() == null || $(`#PaymentOutSupplierFull-${booking_product_ids[i]}`).val() == '') {

                                            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert Payment Out to Supplier (Full) For All Products', null);

                                            return;

                                        }

                                    }

                                }

                                // Validate room management - at least 1 room required
                                var hasRooms = false;
                                if(window.location.href == '<?php echo base_url('Booking/Create'); ?>' || window.location.href.split('?')[0] == '<?php echo base_url('Booking/Create'); ?>' || window.location.href.split('?')[0] == '<?php echo base_url('Booking/Duplicate'); ?>') {
                                    hasRooms = (typeof roomsList !== 'undefined') && roomsList.length > 0;
                                } else {
                                    hasRooms = $('#rooms_table tbody tr').length > 0 && $('#no_rooms_row').length === 0;
                                }
                                if(!hasRooms) {
                                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please set up at least 1 room in Room Management', null);
                                    return;
                                }

                                $('#benchmark').children().each((index, element) => {

                                    product_sequence.push(parseInt(element.id));

                                });

                                if(window.location.href == '<?php echo base_url('Booking/Create'); ?>' || window.location.href.split('?')[0] == '<?php echo base_url('Booking/Create'); ?>' || window.location.href.split('?')[0] == '<?php echo base_url('Booking/Duplicate'); ?>') {

                                    var booking = [];

                                    full_payment_deadline = ($('input[name="FullPaymentDeadline"]').val()).split('/');

                                    var subtotal = ($('#Subtotal').val()).replace(/,/g, '');

                                    var net_total = ($('#NetTotal').val()).replace(/,/g, '');

                                    var deposit_percentage = $('#DepositPercentage').val() != '' ? parseInt($('#DepositPercentage').val()) : 50;
                                    var deposit_mode = $('#DepositMode').val();
                                    var deposit_fixed_amount = $('#DepositFixedAmount').val() != '' ? parseFloat($('#DepositFixedAmount').val()) : 0;

                                    booking.push({CountryCodeID:country_code, ReservationNumber:reservation_number, FullPaymentDeadline:`${full_payment_deadline[2]}-${full_payment_deadline[1]}-${full_payment_deadline[0]}`, Customer:customer, Mobile:mobile, Destination:destination, SalesAgent:sales_agent, Source:source, Subtotal:subtotal, NetTotal:net_total, DepositPercentage:deposit_percentage, DepositMode:deposit_mode, DepositFixedAmount:deposit_fixed_amount, ChatLanguage:chat_language, BookingConfirmationTitle:bc_title, InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>', UpdateBy:<?php echo $this->session->userdata('admin_id') ?>, UpdateDate:'<?php echo date('Y-m-d H:i:s') ?>'});

                                    if(booking_op != '') {

                                        booking[0]['BookingOP'] = booking_op;

                                    }

                                    if(sales_agent_2 != '') {

                                        booking[0]['SalesAgent2'] = sales_agent_2;

                                    }

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

                                    booking[0]['KeyContacts'] = tinyMCE.editors[2].getContent();

                                    booking[0]['SpecialRemarks'] = tinyMCE.editors[3].getContent();



                                    booking_products = Create_Booking_Products();



                                    var createRooms = (typeof roomsList !== 'undefined') ? roomsList : [];
                                    Submit_Booking('<?php echo base_url('Booking/Create') ?>', null, booking_number, booking, null, booking_products, CustomerID, createRooms);

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
                                    var booking_op_admins = <?php echo json_encode($booking_op_admins) ?>;

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

                                                if(key == 'CountryCodeID' && Object.values(dirty_fields[i])[country_codes.length + 1].hasOwnProperty('dirtyInitialValue') || key == 'ReservationNumber' || key == 'DepositDeadline' || key == 'FullPaymentDeadline' || key == 'AdditionalPaymentDeadline' || key == 'Customer' || key == 'Mobile' || key == 'Destination' && Object.values(dirty_fields[i])[categories.length + 1].hasOwnProperty('dirtyInitialValue') || key == 'SalesAgent' && Object.values(dirty_fields[i])[admins.length + 1].hasOwnProperty('dirtyInitialValue') || key == 'SalesAgent2' && Object.values(dirty_fields[i])[admins.length + 1].hasOwnProperty('dirtyInitialValue') || key == 'BookingRemark' || key == 'ChatLanguage' && Object.values(dirty_fields[i])[4].hasOwnProperty('dirtyInitialValue') || key == 'Source' && Object.values(dirty_fields[i])[sources.length + 1].hasOwnProperty('dirtyInitialValue') || key == 'BookingConfirmationTitle' && Object.values(dirty_fields[i])[4].hasOwnProperty('dirtyInitialValue') || key == 'BookingOP' && Object.values(dirty_fields[i])[booking_op_admins.length + 1].hasOwnProperty('dirtyInitialValue')) {

                                                    if(key == 'BookingOP' && value == '') {

                                                        value = null;

                                                    }

                                                    if(key == 'SalesAgent2' && value == '') {

                                                        value = null;

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

                                                        case 'SalesAgent2':

                                                            default_value = (Object.values(dirty_fields[i])[admins.length + 1]).dirtyInitialValue;

                                                            break;

                                                        case 'BookingOP':

                                                            default_value = (Object.values(dirty_fields[i])[booking_op_admins.length + 1]).dirtyInitialValue;

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

                                                        if(key[0] == 'ProductID' && Object.values(dirty_fields[i])[products.length + 1].hasOwnProperty('dirtyInitialValue') || key[0] == 'ProductCode' || key[0] == 'Description' || key[0] == 'Quantity' || key[0] == 'Price' || key[0] == 'Total' || key[0] == 'PaymentOutSupplierFull' || key[0] == 'PaymentOutSupplierDeposit') {

                                                            if(key[0] == 'Price' || key[0] == 'Total') {

                                                                value = value.replace(/,/g, '');

                                                            }

                                                            if(key[0] == 'PaymentOutSupplierFull' || key[0] == 'PaymentOutSupplierDeposit') {
                                                                if(value && value != '') {
                                                                    var date_parts = value.split('/');
                                                                    value = `${date_parts[2]}-${date_parts[1]}-${date_parts[0]}`;
                                                                } else {
                                                                    value = null;
                                                                }
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


                                    // Collect disable_checklist_payment_out checkbox state for all existing booking products
                                    var existing_bp_ids = <?php echo json_encode(array_map(function($bp) { return $bp->BookingProductID; }, $booking_products ?? [])); ?>;
                                    for(var bp = 0; bp < existing_bp_ids.length; bp++) {
                                        var bp_id = existing_bp_ids[bp];
                                        var is_disabled = $(`#DisableChecklistPaymentOut-${bp_id}`).is(':checked') ? 1 : 0;
                                        booking_products[1].push({BookingProductID:bp_id, disable_checklist_payment_out:is_disabled, UpdateBy:<?php echo $this->session->userdata('admin_id') ?>, UpdateDate:'<?php echo date('Y-m-d H:i:s') ?>'});
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

                                    // Action : Update DepositPercentage

                                    var deposit_percentage = $('#DepositPercentage').val() != '' ? parseInt($('#DepositPercentage').val()) : 50;

                                    // Use original DB values for comparison (not the displayed values)
                                    var current_deposit_percentage = '<?php echo isset($DepositPercentageOriginal) ? $DepositPercentageOriginal : 0; ?>';

                                    if(deposit_percentage != current_deposit_percentage) {

                                        booking[0]['DepositPercentage'] = deposit_percentage;

                                        booking_log.push({BookingID:<?php echo $BookingID ?>, Column:'DepositPercentage', CurrentData:current_deposit_percentage, NewData:deposit_percentage.toString(), InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});

                                    }

                                    // Action : Update DepositMode

                                    var deposit_mode = $('#DepositMode').val();

                                    var current_deposit_mode = '<?php echo isset($DepositModeOriginal) ? $DepositModeOriginal : "percentage"; ?>';

                                    if(deposit_mode != current_deposit_mode) {

                                        booking[0]['DepositMode'] = deposit_mode;

                                        booking_log.push({BookingID:<?php echo $BookingID ?>, Column:'DepositMode', CurrentData:current_deposit_mode, NewData:deposit_mode, InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});

                                    }

                                    // Action : Update DepositFixedAmount

                                    var deposit_fixed_amount = $('#DepositFixedAmount').val() != '' ? parseFloat($('#DepositFixedAmount').val()) : 0;

                                    var current_deposit_fixed_amount = '<?php echo isset($DepositFixedAmountOriginal) ? $DepositFixedAmountOriginal : 0; ?>';

                                    if(deposit_fixed_amount != parseFloat(current_deposit_fixed_amount)) {

                                        booking[0]['DepositFixedAmount'] = deposit_fixed_amount;

                                        booking_log.push({BookingID:<?php echo $BookingID ?>, Column:'DepositFixedAmount', CurrentData:current_deposit_fixed_amount, NewData:deposit_fixed_amount.toString(), InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});

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

                                    if(travel_voucher_footer_id != '<?php echo isset($TravelVoucherFooterID) ? $TravelVoucherFooterID : ''; ?>') {

                                        booking[0]['TravelVoucherFooterID'] = travel_voucher_footer_id;

                                        booking_log.push({BookingID:<?php echo $BookingID ?>, Column:'TravelVoucherFooterID', CurrentData:'<?php echo isset($TravelVoucherFooterID) ? $TravelVoucherFooterID : ''; ?>', NewData:travel_voucher_footer_id, InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});

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

                                    // Action : Update Key Contacts

                                    var updated_key_contacts = Decode_HTML(tinyMCE.editors[2].getContent());

                                    if(updated_key_contacts != initial_key_contacts) {

                                        booking[0]['KeyContacts'] = tinyMCE.editors[2].getContent();

                                        booking_log.push({BookingID:<?php echo $BookingID ?>, Column:'KeyContacts', CurrentData:initial_key_contacts, NewData:tinyMCE.editors[2].getContent(), InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});

                                    }



                                    // Booking

                                    // Action : Update Special Remarks

                                    var updated_special_remarks = Decode_HTML(tinyMCE.editors[3].getContent());

                                    if(updated_special_remarks != initial_special_remarks) {

                                        booking[0]['SpecialRemarks'] = tinyMCE.editors[3].getContent();

                                        booking_log.push({BookingID:<?php echo $BookingID ?>, Column:'SpecialRemarks', CurrentData:initial_special_remarks, NewData:tinyMCE.editors[3].getContent(), InsertBy:<?php echo $this->session->userdata('admin_id') ?>, InsertDate:'<?php echo date('Y-m-d H:i:s') ?>'});

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

                                                if(!empty($this->input->get('booking_number')) || !empty($this->input->get('reservation_number')) || !empty($this->input->get('deadline')) || !empty($this->input->get('customer')) || !empty($this->input->get('mobile')) || !empty($this->input->get('travel_date')) || !empty($this->input->get('destination')) || !empty($this->input->get('sales_agent')) || !empty($this->input->get('sales_agent_2')) || !empty($this->input->get('tag')) || !empty($this->input->get('chat_language')) || !empty($this->input->get('source')) || !empty($this->input->get('booking_confirmation_title')) || !empty($this->input->get('status')) || !empty($this->input->get('booking_date'))) {

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



    function Submit_Booking(url, booking_id, booking_number, booking, booking_log, booking_products, CustomerID, booking_rooms)

    {

        var postData = {

                booking_id: booking_id,

                booking_number : booking_number,

                booking: booking,

                booking_log: booking_log,

                booking_products: booking_products,

                CustomerID: CustomerID,

            };

        if (booking_rooms && booking_rooms.length > 0) {
            postData.booking_rooms = booking_rooms;
        }

        $.ajax({

            url: url,

            type: 'post',

            data: postData,

            success: function() {

                var base_url = '<?php echo base_url('Booking') ?>';

                if(window.location.href.split('?')[0] == '<?php echo base_url('Booking/Update'); ?>' || window.location.href.split('?')[0] == '<?php echo base_url('Booking/Duplicate'); ?>') {

                    <?php if(!empty($this->input->get('booking_number')) || !empty($this->input->get('reservation_number')) || !empty($this->input->get('deadline')) || !empty($this->input->get('customer')) || !empty($this->input->get('mobile')) || !empty($this->input->get('travel_date')) || !empty($this->input->get('destination')) || !empty($this->input->get('sales_agent')) || !empty($this->input->get('sales_agent_2')) || !empty($this->input->get('tag')) || !empty($this->input->get('chat_language')) || !empty($this->input->get('source')) || !empty($this->input->get('booking_confirmation_title')) || !empty($this->input->get('status')) || !empty($this->input->get('booking_date'))) { ?>

                        var url = base_url + '?' + window.location.href.split('?booking_id=' + <?php echo $this->input->get('booking_id') ?> + '&')[1];

                    <?php } else { ?>

                        var url = base_url;

                    <?php } ?>

                } else {

                    <?php if(!empty($this->input->get('booking_number')) || !empty($this->input->get('reservation_number')) || !empty($this->input->get('deadline')) || !empty($this->input->get('customer')) || !empty($this->input->get('mobile')) || !empty($this->input->get('travel_date')) || !empty($this->input->get('destination')) || !empty($this->input->get('sales_agent')) || !empty($this->input->get('sales_agent_2')) || !empty($this->input->get('tag')) || !empty($this->input->get('chat_language')) || !empty($this->input->get('source')) || !empty($this->input->get('booking_confirmation_title')) || !empty($this->input->get('status')) || !empty($this->input->get('booking_date'))) { ?>

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

                // Set disable checklist checkbox state
                $(`#DisableChecklistPaymentOut-${booking_products[i].BookingProductID}`).removeAttr('disabled');
                if(booking_products[i].disable_checklist_payment_out == 1) {
                    $(`#DisableChecklistPaymentOut-${booking_products[i].BookingProductID}`).prop('checked', true);
                    $(`#PaymentOutSection-${booking_products[i].BookingProductID}`).hide();
                }

                if(booking_products[i].PaymentOutSupplierFull) {
                    $(`#PaymentOutSupplierFull-${booking_products[i].BookingProductID}`).val(booking_products[i].PaymentOutSupplierFull);
                }
                if(booking_products[i].PaymentOutSupplierDeposit) {
                    $(`#PaymentOutSupplierDeposit-${booking_products[i].BookingProductID}`).val(booking_products[i].PaymentOutSupplierDeposit);
                }
                $(`#PaymentOutSupplierFull-${booking_products[i].BookingProductID}`).removeAttr('disabled');
                $(`#PaymentOutSupplierDeposit-${booking_products[i].BookingProductID}`).removeAttr('disabled');
                $(`#PaymentOutSupplierFull-${booking_products[i].BookingProductID}`).datepicker({format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true});
                $(`#PaymentOutSupplierDeposit-${booking_products[i].BookingProductID}`).datepicker({format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true});

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

                // Set disable checklist checkbox state for Duplicate
                $(`#DisableChecklistPaymentOut-${booking_product_id - 1}`).removeAttr('disabled');
                if(booking_products[i].disable_checklist_payment_out == 1) {
                    $(`#DisableChecklistPaymentOut-${booking_product_id - 1}`).prop('checked', true);
                    $(`#PaymentOutSection-${booking_product_id - 1}`).hide();
                }

                if(booking_products[i].PaymentOutSupplierFull) {
                    $(`#PaymentOutSupplierFull-${booking_product_id - 1}`).val(booking_products[i].PaymentOutSupplierFull);
                }
                if(booking_products[i].PaymentOutSupplierDeposit) {
                    $(`#PaymentOutSupplierDeposit-${booking_product_id - 1}`).val(booking_products[i].PaymentOutSupplierDeposit);
                }
                $(`#PaymentOutSupplierFull-${booking_product_id - 1}`).removeAttr('disabled');
                $(`#PaymentOutSupplierDeposit-${booking_product_id - 1}`).removeAttr('disabled');
                $(`#PaymentOutSupplierFull-${booking_product_id - 1}`).datepicker({format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true});
                $(`#PaymentOutSupplierDeposit-${booking_product_id - 1}`).datepicker({format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true});

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
    function validateLength(inputId, errorId, maxLength, onlyNumber = false) {
        const input = document.getElementById(inputId);
        const error = document.getElementById(errorId);

        if (!input) return; // safety check

        input.addEventListener('input', function () {
            let value = this.value;

            // Numbers only
            if (onlyNumber) {
                value = value.replace(/\D/g, '');
            }

            // Length validation
            if (value.length > maxLength) {
                error.textContent = `Length can't be more than ${maxLength} characters`;
                value = value.substring(0, maxLength);
            } else {
                error.textContent = '';
            }

            this.value = value;
        });
    }

    // Run when page is fully loaded
    document.addEventListener('DOMContentLoaded', function () {
        validateLength('ReservationNumber', 'ReservationNumberError', 20);
        validateLength('Mobile', 'MobileError', 25, true);
    });

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

    // Quick Update Allow Review Button
    <?php if(current_url() == base_url('Booking/Update')) { ?>
    $('#update_allow_review_btn').on('click', function() {
        var allowReview = $('#AllowReview').val();
        var bookingId = <?php echo $BookingID; ?>;
        var currentAllowReview = '<?php echo isset($AllowReview) && $AllowReview !== null ? $AllowReview : 1; ?>';

        console.log('allowReview: ', allowReview);
        console.log('currentAllowReview: ', currentAllowReview);
        
        // Check if value changed
        if(allowReview == currentAllowReview) {
            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'No changes detected for Allow Review', null);
            return;
        }

        // Disable button during update
        var $btn = $(this);
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<i class="la la-spinner la-spin"></i> Updating...');

        // Prepare booking data with only AllowReview
        var booking = [{
            BookingID: bookingId,
            AllowReview: parseInt(allowReview),
            UpdateBy: <?php echo $this->session->userdata('admin_id'); ?>,
            UpdateDate: '<?php echo date('Y-m-d H:i:s'); ?>'
        }];

        // Prepare booking log
        var booking_log = [{
            BookingID: bookingId,
            Column: 'AllowReview',
            CurrentData: currentAllowReview,
            NewData: allowReview,
            InsertBy: <?php echo $this->session->userdata('admin_id'); ?>,
            InsertDate: '<?php echo date('Y-m-d H:i:s'); ?>'
        }];

        $.ajax({
            url: '<?php echo base_url('Booking/UpdateAllowReview'); ?>',
            type: 'post',
            data: {
                booking_id: bookingId,
                booking: booking,
                booking_log: booking_log,
                allow_review: allowReview,
                CustomerID: $('input[name="CustomerID"]').val() || ''
            },
            success: function() {
                $btn.prop('disabled', false).html(originalText);
                // Get current URL to reload after success
                var currentUrl = window.location.href;
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Allow Review Successfully Updated', currentUrl);
            },
            error: function(xhr, status, error) {
                $btn.prop('disabled', false).html(originalText);
                console.error('Update Error:', error);
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Allow Review Could Not Be Updated', null);
            }
        });
    });
    <?php } ?>

    // Auto-resize Customer Review textarea
    function autoResizeTextarea() {
        var textarea = $('#CustomerReview');
        if (textarea.length) {
            // Reset height to auto to get the correct scrollHeight
            textarea.css('height', 'auto');
            // Set height to scrollHeight to fit all content
            textarea.css('height', textarea[0].scrollHeight + 'px');
        }
    }

    // Run on page load
    autoResizeTextarea();

    // Also run after a short delay to ensure content is fully loaded
    setTimeout(autoResizeTextarea, 100);

    // Internal Comments functionality
    <?php if(current_url() == base_url('Booking/Update')) { ?>
    var bookingId = <?php echo $BookingID; ?>;

    // Load customer remarks on page load
    function loadCustomerRemarks() {
        $.ajax({
            url: '<?php echo base_url('Booking/Get_Customer_Remarks'); ?>',
            type: 'get',
            data: { booking_id: bookingId },
            dataType: 'json',
            success: function(response) {
                var remarksList = $('#customer-remarks-list');
                remarksList.empty();

                if (response.success && response.remarks && response.remarks.length > 0) {
                    response.remarks.forEach(function(remark) {
                        // Get first letter for avatar color
                        var avatarColor = ['primary', 'success', 'info', 'warning', 'danger'][remark.commenter_name.charCodeAt(0) % 5];
                        
                        var remarkHtml = '<div class="comment-item d-flex mb-2 mx-2 pb-2 pl-1" style="border-bottom: 1px solid #e4e6eb;">' +
                            // Avatar
                            '<div class="flex-shrink-0 mr-2">' +
                            '<div class="symbol symbol-32 symbol-circle symbol-light-' + avatarColor + '">' +
                            '<span class="symbol-label font-weight-bold" style="font-size: 0.75rem;">' + (remark.commenter_initials || remark.commenter_name.substring(0, 2).toUpperCase()) + '</span>' +
                            '</div>' +
                            '</div>' +
                            // Comment content
                            '<div class="flex-grow-1" style="min-width: 0;">' +
                            '<div class="d-flex align-items-baseline mb-1">' +
                            '<strong class="mr-2" style="font-size: 0.8125rem; color: #050505;">' + escapeHtml(remark.commenter_name) + '</strong>' +
                            '<span class="text-muted" style="font-size: 0.75rem; color: #65676b;">' + remark.created_at + (remark.created_at_relative ? ' <span style="margin: 0 4px;">•</span> ' + remark.created_at_relative : '') + '</span>' +
                            '</div>' +
                            '<div class="comment-text" style="font-size: 0.8125rem; color: #050505; line-height: 1.3; white-space: pre-wrap; word-wrap: break-word;">' + escapeHtml(remark.content) + '</div>' +
                            '</div>' +
                            '</div>';
                        remarksList.append(remarkHtml);
                    });
                } else {
                    remarksList.html('<div class="text-center text-muted py-3" style="font-size: 0.8125rem; color: #65676b;">No customer remarks yet.</div>');
                }
            },
            error: function() {
                $('#customer-remarks-list').html('<div class="text-center text-danger py-3" style="font-size: 0.8125rem;">Error loading customer remarks. Please refresh the page.</div>');
            }
        });
    }

    // Load comments on page load
    function loadComments() {
        $.ajax({
            url: '<?php echo base_url('Booking/Get_Remarks'); ?>',
            type: 'get',
            data: { booking_id: bookingId },
            dataType: 'json',
            success: function(response) {
                var commentsList = $('#internal-comments-list');
                commentsList.empty();

                if (response.success && response.remarks && response.remarks.length > 0) {
                    response.remarks.forEach(function(remark) {
                        // Get first letter for avatar color
                        var avatarColor = ['primary', 'success', 'info', 'warning', 'danger'][remark.commenter_name.charCodeAt(0) % 5];
                        
                        var commentHtml = '<div class="comment-item d-flex mb-2 mx-2 pb-2 pl-1" style="border-bottom: 1px solid #e4e6eb; position: relative;">' +
                            // Avatar
                            '<div class="flex-shrink-0 mr-2">' +
                            '<div class="symbol symbol-32 symbol-circle symbol-light-' + avatarColor + '">' +
                            '<span class="symbol-label font-weight-bold" style="font-size: 0.75rem;">' + (remark.commenter_initials || remark.commenter_name.substring(0, 2).toUpperCase()) + '</span>' +
                            '</div>' +
                            '</div>' +
                            // Comment content
                            '<div class="flex-grow-1" style="min-width: 0;">' +
                            '<div class="d-flex align-items-baseline mb-1">' +
                            '<strong class="mr-2" style="font-size: 0.8125rem; color: #050505; cursor: pointer;">' + escapeHtml(remark.commenter_name) + '</strong>' +
                            '<span class="text-muted" style="font-size: 0.75rem; color: #65676b;">' + remark.created_at + (remark.created_at_relative ? ' <span style="margin: 0 4px;">•</span> ' + remark.created_at_relative : '') + '</span>' +
                            '</div>' +
                            '<div class="comment-text" style="font-size: 0.8125rem; color: #050505; line-height: 1.3; white-space: pre-wrap; word-wrap: break-word;">' + escapeHtml(remark.content) + '</div>' +
                            '</div>' +
                            // Delete button (only show if user is the owner)
                            (remark.is_owner ? '<button type="button" class="btn btn-sm btn-link text-muted delete-comment-btn comment-delete-btn" data-remark-id="' + remark.RemarkID + '" style="position: absolute; top: 0; right: 0; opacity: 1; padding: 2px 6px; font-size: 0.75rem; background: transparent !important;" title="Delete comment">' +
                            '<i class="la la-trash" style="color: #65676b; font-size: 0.875rem;"></i>' +
                            '</button>' : '') +
                            '</div>';
                        commentsList.append(commentHtml);
                    });
                } else {
                    commentsList.html('<div class="text-center text-muted py-3" style="font-size: 0.8125rem; color: #65676b;">No comments yet. Be the first to add a comment!</div>');
                }
            },
            error: function() {
                $('#internal-comments-list').html('<div class="text-center text-danger py-3" style="font-size: 0.8125rem;">Error loading comments. Please refresh the page.</div>');
            }
        });
    }

    // Add new comment
    $('#add-comment-btn').on('click', function() {
        var content = $('#new-comment-content').val().trim();
        
        if (!content) {
            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please enter a comment', null);
            return;
        }

        var $btn = $(this);
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<i class="la la-spinner la-spin"></i> Adding...');

        $.ajax({
            url: '<?php echo base_url('Booking/Add_Remark'); ?>',
            type: 'post',
            data: {
                booking_id: bookingId,
                content: content,
                notify_user_ids: $('#comment-notify-users').val()
            },
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).html(originalText);

                if (response.success) {
                    $('#new-comment-content').val('');
                    $('#comment-notify-users').selectpicker('deselectAll');
                    loadComments(); // Reload comments to show the new one
                    // Don't redirect, just show success message
                    Swal.fire({
                        width: 550,
                        background: 'url(<?php echo base_url('assets/image/sweetalert.jpg') ?>)',
                        icon: 'success',
                        title: 'Comment added Successfully',
                        showConfirmButton: false,
                        timer: 2200
                    });
                } else {
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', response.message || 'Failed to add comment', null);
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(originalText);
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Error adding comment. Please try again.', null);
            }
        });
    });

    // Allow Enter key to submit (Ctrl+Enter or Shift+Enter)
    $('#new-comment-content').on('keydown', function(e) {
        if ((e.ctrlKey || e.shiftKey) && e.keyCode === 13) {
            e.preventDefault();
            $('#add-comment-btn').click();
        }
    });

    // Delete comment handler (using event delegation for dynamically added buttons)
    $(document).on('click', '.delete-comment-btn', function() {
        var remarkId = $(this).data('remark-id');
        var $commentDiv = $(this).closest('.comment-item');
        
        if (!remarkId) {
            return;
        }

        // Confirm deletion
        Swal.fire({
            width: 550,
            background: 'url(<?php echo base_url('assets/image/sweetalert.jpg') ?>)',
            icon: 'warning',
            title: 'Delete Comment?',
            text: 'Are you sure you want to delete this comment?',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading state
                $commentDiv.css('opacity', '0.5');
                
                $.ajax({
                    url: '<?php echo base_url('Booking/Delete_Remark'); ?>',
                    type: 'post',
                    data: {
                        remark_id: remarkId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Remove the comment from DOM
                            $commentDiv.fadeOut(300, function() {
                                $(this).remove();
                                // Reload comments to ensure sync
                                loadComments();
                            });
                            
                            Swal.fire({
                                width: 550,
                                background: 'url(<?php echo base_url('assets/image/sweetalert.jpg') ?>)',
                                icon: 'success',
                                title: 'Comment deleted Successfully',
                                showConfirmButton: false,
                                timer: 1500
                            });
                        } else {
                            $commentDiv.css('opacity', '1');
                            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', response.message || 'Failed to delete comment', null);
                        }
                    },
                    error: function() {
                        $commentDiv.css('opacity', '1');
                        Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Error deleting comment. Please try again.', null);
                    }
                });
            }
        });
    });

    // Helper function to escape HTML
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Add admin remark (internal remark in response to customer)
    $('#add-admin-remark-btn').on('click', function() {
        var content = $('#new-admin-remark-content').val().trim();
        
        if (!content) {
            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please enter a remark', null);
            return;
        }

        var $btn = $(this);
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<i class="la la-spinner la-spin"></i> Adding...');

        $.ajax({
            url: '<?php echo base_url('Booking/Add_Remark'); ?>',
            type: 'post',
            data: {
                booking_id: bookingId,
                content: content,
                remark_type: '2', // CUSTOMER type when adding from Customer Remarks section
                skip_notifications: '1' // Skip notifications when adding from Customer Remarks section
            },
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).html(originalText);
                
                if (response.success) {
                    $('#new-admin-remark-content').val('');
                    loadCustomerRemarks(); // Reload customer remarks to show the new one
                    // Don't redirect, just show success message
                    Swal.fire({
                        width: 550,
                        background: 'url(<?php echo base_url('assets/image/sweetalert.jpg') ?>)',
                        icon: 'success',
                        title: 'Remark added Successfully',
                        showConfirmButton: false,
                        timer: 2200
                    });
                } else {
                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', response.message || 'Failed to add remark', null);
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(originalText);
                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Error adding remark. Please try again.', null);
            }
        });
    });

    // Allow Enter key to submit (Ctrl+Enter or Shift+Enter) for admin remark
    $('#new-admin-remark-content').on('keydown', function(e) {
        if ((e.ctrlKey || e.shiftKey) && e.keyCode === 13) {
            e.preventDefault();
            $('#add-admin-remark-btn').click();
        }
    });

    // Load comments when page is ready
    loadComments();
    loadCustomerRemarks();
    <?php } ?>
    
    // Booking Checklist functionality
    <?php if(isset($booking_checklists) && !empty($booking_checklists['groups']) && current_url() == base_url('Booking/Update')) { ?>
    var bookingId = <?php echo $BookingID; ?>;
    var checklistCompletions = [];
    var totalChecklistItems = <?php echo $booking_checklists['total_count']; ?>;
    
    // Update checklist completions array and UI when checkbox changes
    $('.checklist-checkbox').on('change', function() {
        var $checkbox = $(this);
        var $item = $checkbox.closest('.checklist-item');
        var checklistId = $checkbox.val();
        
        // Update item visual state
        if($checkbox.is(':checked')) {
            $item.addClass('checked');
        } else {
            $item.removeClass('checked');
            // Remove completion info if exists
            $item.find('.checklist-completion-info').remove();
        }
        
        updateChecklistCompletions();
        updateProgressBar();
    });
    
    function updateChecklistCompletions() {
        checklistCompletions = [];
        $('.checklist-checkbox:checked').each(function() {
            checklistCompletions.push($(this).val());
        });
    }
    
    function updateProgressBar() {
        var checkedCount = checklistCompletions.length;
        var percentage = totalChecklistItems > 0 ? Math.round((checkedCount / totalChecklistItems) * 100) : 0;
        
        // Update progress text
        $('.progress-text').html('<strong>' + checkedCount + '</strong> of <strong>' + totalChecklistItems + '</strong> items completed');
        $('.progress-percentage').text(percentage + '%');
        
        // Update progress bar
        $('.progress-bar').css('width', percentage + '%').attr('aria-valuenow', checkedCount);
    }
    
    // Update Checklist Button Click Handler
    $('#update_checklist_btn').on('click', function() {
        updateChecklistCompletions();
        
        // Disable button during update
        var $btn = $(this);
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<i class="la la-spinner la-spin"></i> Updating...');
        
        // Prepare data - CodeIgniter expects checklist_completions[] format
        var postData = 'booking_id=' + bookingId;
        
        // Add each checklist ID as checklist_completions[]
        if(checklistCompletions.length === 0) {
            postData += '&checklist_completions=';
        }
        $.each(checklistCompletions, function(index, value) {
            postData += '&checklist_completions[]=' + encodeURIComponent(value);
        });
        
        console.log('Sending checklist completions:', checklistCompletions);
        console.log('Post data string:', postData);
        
        $.ajax({
            url: '<?php echo base_url('Booking/Update'); ?>',
            type: 'POST',
            data: postData,
            contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
            success: function(response) {
                $btn.prop('disabled', false).html(originalText);
                console.log('Response received');
                Swal.fire({
                    width: 550,
                    background: 'url(<?php echo base_url('assets/image/sweetalert.jpg') ?>)',
                    icon: 'success',
                    title: 'Booking Checklist Successfully Updated',
                    showConfirmButton: false,
                    timer: 1500
                }).then(function() {
                    // Reload page to show updated "checked by" information
                    window.location.reload();
                });
            },
            error: function(xhr, status, error) {
                $btn.prop('disabled', false).html(originalText);
                console.error('Update Error:', error);
                Swal.fire({
                    width: 550,
                    background: 'url(<?php echo base_url('assets/image/sweetalert.jpg') ?>)',
                    icon: 'error',
                    title: 'Booking Checklist Could Not Be Updated',
                    showConfirmButton: false,
                    timer: 3000
                });
            }
        });
    });
    
    // Save checklist completions when form is submitted
    $('form').on('submit', function(e) {
        updateChecklistCompletions();
        // Remove any existing hidden inputs
        $('input[name="checklist_completions[]"]').remove();
        // Add checklist completions to form data
        $.each(checklistCompletions, function(index, value) {
            $('<input>').attr({
                type: 'hidden',
                name: 'checklist_completions[]',
                value: value
            }).appendTo($('form'));
        });
    });
    
    // Initialize
    updateChecklistCompletions();
    updateProgressBar();
    <?php } ?>
});

// Custom Upload Functionality
<?php if(current_url() == base_url('Booking/Update')) { ?>
$(document).ready(function() {
    // Update file input label when file is selected
    $('#upload_file').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName);
    });

    // Handle upload form submission
    $('#custom_upload_form').on('submit', function(e) {
        e.preventDefault();
        
        var bookingId = $('#upload_booking_id').val();
        var uploadName = $('#upload_name').val();
        var uploadFile = $('#upload_file')[0].files[0];

        if (!uploadName || !uploadFile) {
            Swal.fire({
                width: 550,
                background: 'url(<?php echo base_url('assets/image/sweetalert.jpg') ?>)',
                icon: 'warning',
                title: 'Please provide both document name and file',
                showConfirmButton: true
            });
            return;
        }

        var formData = new FormData();
        formData.append('booking_id', bookingId);
        formData.append('upload_name', uploadName);
        formData.append('upload_file', uploadFile);

        $('#upload_btn').prop('disabled', true).html('<i class="la la-spinner la-spin"></i> Uploading...');

        $.ajax({
            url: '<?php echo base_url('Booking/Upload_Custom_File'); ?>',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                var result = typeof response === 'string' ? JSON.parse(response) : response;
                if (result.success) {
                    // Add new upload to list
                    var uploadHtml = '<div class="upload-item mb-3 border rounded" data-upload-id="' + result.upload.id + '" style="background: #fff; transition: all 0.2s;">' +
                        '<div class="p-3">' +
                        '<div class="d-flex align-items-start">' +
                        '<div class="flex-shrink-0 mr-3">' +
                        '<div class="d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: #f0f4ff; border-radius: 8px;">' +
                        '<i class="la la-file-alt text-primary" style="font-size: 1.5rem;"></i>' +
                        '</div>' +
                        '</div>' +
                        '<div class="flex-grow-1 min-w-0">' +
                        '<div class="mb-1">' +
                        '<strong class="d-block text-truncate" style="font-size: 0.95rem; color: #212529;">' + escapeHtml(result.upload.upload_name) + '</strong>' +
                        '</div>' +
                        '<small class="text-muted d-block" style="font-size: 0.75rem; line-height: 1.4;">' +
                        '<i class="la la-user-circle"></i> ' + escapeHtml(result.upload.created_by) + '<br>' +
                        '<i class="la la-clock"></i> ' + result.upload.created_at + (result.upload.created_at_relative ? ' <span style="margin: 0 4px;">•</span> ' + result.upload.created_at_relative : '') +
                        '</small>' +
                        '</div>' +
                        '</div>' +
                        '<div class="mt-3 pt-3 border-top">' +
                        '<div class="d-flex flex-wrap align-items-center">' +
                        '<a href="' + result.upload.upload_content + '" target="_blank" class="btn btn-sm btn-light-primary mr-2 mb-2" title="View Document" style="flex: 1; min-width: 80px;">' +
                        '<i class="la la-eye mr-1"></i> <span class="d-none d-md-inline">View</span></a>' +
                        '<a href="' + result.upload.upload_content + '" download class="btn btn-sm btn-light-success mr-2 mb-2" title="Download Document" style="flex: 1; min-width: 80px;">' +
                        '<i class="la la-download mr-1"></i> <span class="d-none d-md-inline">Download</span></a>' +
                        '<button type="button" class="btn btn-sm btn-light-danger delete-upload mb-2" data-upload-id="' + result.upload.id + '" title="Delete Document" style="min-width: 50px;">' +
                        '<i class="la la-trash"></i></button>' +
                        '</div>' +
                        '</div>' +
                        '</div>' +
                        '</div>';

                    if ($('#uploads_container .text-center').length > 0) {
                        $('#uploads_container').html(uploadHtml);
                    } else {
                        $('#uploads_container').prepend(uploadHtml);
                    }

                    // Reset form
                    $('#custom_upload_form')[0].reset();
                    $('#upload_file').next('.custom-file-label').html('Choose file');
                    $('#upload_btn').prop('disabled', false).html('<i class="la la-upload"></i> Upload');

                    // Show success message
                    Swal.fire({
                        width: 550,
                        background: 'url(<?php echo base_url('assets/image/sweetalert.jpg') ?>)',
                        icon: 'success',
                        title: 'File uploaded successfully!',
                        showConfirmButton: false,
                        timer: 2000
                    });
                } else {
                    Swal.fire({
                        width: 550,
                        background: 'url(<?php echo base_url('assets/image/sweetalert.jpg') ?>)',
                        icon: 'error',
                        title: 'Error: ' + result.message,
                        showConfirmButton: true
                    });
                    $('#upload_btn').prop('disabled', false).html('<i class="la la-upload"></i> Upload');
                }
            },
            error: function(xhr, status, error) {
                Swal.fire({
                    width: 550,
                    background: 'url(<?php echo base_url('assets/image/sweetalert.jpg') ?>)',
                    icon: 'error',
                    title: 'Upload failed: ' + error,
                    showConfirmButton: true
                });
                $('#upload_btn').prop('disabled', false).html('<i class="la la-upload"></i> Upload');
            }
        });
    });

    // Handle delete upload
    $(document).on('click', '.delete-upload', function() {
        var uploadId = $(this).data('upload-id');
        var $uploadItem = $(this).closest('.upload-item');

        Swal.fire({
            width: 550,
            background: 'url(<?php echo base_url('assets/image/sweetalert.jpg') ?>)',
            icon: 'warning',
            title: 'Are you sure you want to delete this upload?',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '<?php echo base_url('Booking/Delete_Custom_Upload'); ?>',
                    type: 'POST',
                    data: { upload_id: uploadId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $uploadItem.fadeOut(300, function() {
                                $(this).remove();
                                if ($('#uploads_container .upload-item').length === 0) {
                                    $('#uploads_container').html(
                                        '<div class="text-center text-muted py-4">' +
                                        '<i class="la la-inbox" style="font-size: 3rem;"></i>' +
                                        '<p class="mt-2">No documents uploaded yet</p>' +
                                        '</div>'
                                    );
                                }
                            });
                            Swal.fire({
                                width: 550,
                                background: 'url(<?php echo base_url('assets/image/sweetalert.jpg') ?>)',
                                icon: 'success',
                                title: 'Upload deleted successfully!',
                                showConfirmButton: false,
                                timer: 2000
                            });
                        } else {
                            Swal.fire({
                                width: 550,
                                background: 'url(<?php echo base_url('assets/image/sweetalert.jpg') ?>)',
                                icon: 'error',
                                title: 'Error: ' + response.message,
                                showConfirmButton: true
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            width: 550,
                            background: 'url(<?php echo base_url('assets/image/sweetalert.jpg') ?>)',
                            icon: 'error',
                            title: 'Failed to delete upload',
                            showConfirmButton: true
                        });
                    }
                });
            }
        });
    });

    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
<?php } ?>

</script>

<style>
.comment-item {
    transition: background-color 0.2s;
    padding: 6px 0;
    border-radius: 4px;
}

.comment-item:hover {
    background-color: #f2f3f5;
    transition: background-color 0.2s;
}

.comment-item:last-child {
    border-bottom: none !important;
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}

.comment-text {
    margin-top: 1px;
}

.comment-delete-btn {
    color: #65676b !important;
    transition: all 0.2s ease;
}

.comment-delete-btn:hover {
    color: #e41e3f !important;
    transform: scale(1.15);
}

.comment-delete-btn:hover i {
    color: #e41e3f !important;
}

.symbol-circle {
    border-radius: 50% !important;
}

#internal-comments-list {
    padding: 2px 0;
}

#internal-comments-list::-webkit-scrollbar {
    width: 6px;
}

#internal-comments-list::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

#internal-comments-list::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 10px;
}

#internal-comments-list::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>

<style>
    .auto-resize-textarea {
        resize: none;
        overflow: hidden;
        min-height: 60px;
    }

    /* Booking Checklist Professional Styling */
    .checklist-container {
        padding: 0;
    }

    .checklist-item {
        padding: 10px 12px;
        margin-bottom: 8px;
        background-color: #ffffff;
        border: 1px solid #e4e6eb;
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    .checklist-item:hover {
        border-color: #c1c7d0;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .checklist-item.checked {
        background-color: #f8f9fa;
        border-color: #d4edda;
    }

    .checklist-item.checked:hover {
        border-color: #c3e6cb;
    }

    .checklist-item-content {
        display: flex;
        align-items: flex-start;
        gap: 28px;
    }

    .checklist-checkbox-wrapper {
        position: relative;
        flex-shrink: 0;
        margin-top: 2px;
    }

    .checklist-checkbox {
        width: 18px;
        height: 18px;
        cursor: pointer;
        margin: 0;
        accent-color: #6082B6;
    }

    .checklist-checkbox-label {
        position: absolute;
        top: 0;
        left: 0;
        width: 18px;
        height: 18px;
        cursor: pointer;
        margin: 0;
    }

    .checklist-details {
        flex: 1;
        min-width: 0;
    }

    .checklist-name-wrapper {
        margin-bottom: 4px;
    }

    .checklist-name {
        font-size: 0.875rem;
        font-weight: 500;
        color: #212529;
        cursor: pointer;
        margin: 0;
        line-height: 1.3;
    }

    .checklist-completion-info {
        display: flex;
        align-items: center;
        gap: 4px;
        margin-top: 6px;
        padding-top: 6px;
        border-top: 1px solid #e9ecef;
        font-size: 0.75rem;
        color: #6c757d;
    }

    .checklist-completion-info i {
        font-size: 0.875rem;
        color: #6082B6;
    }

    .completion-text {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }

    .completion-separator {
        color: #adb5bd;
        margin: 0 4px;
    }

    .completion-date {
        color: #868e96;
    }

    .checklist-footer {
        margin-top: 16px;
        padding-top: 14px;
        border-top: 2px solid #e4e6eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }

    .checklist-progress {
        flex: 1;
        min-width: 200px;
    }

    .progress-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 6px;
    }

    .progress-text {
        font-size: 0.8125rem;
        color: #495057;
    }

    .progress-text strong {
        color: #212529;
    }

    .progress-percentage {
        font-size: 0.8125rem;
        font-weight: 600;
        color: #6082B6;
    }

    .progress-bar-wrapper {
        width: 100%;
    }

    .progress-bar-wrapper .progress {
        height: 6px;
        border-radius: 3px;
        overflow: hidden;
    }

    .progress-bar-wrapper .progress-bar {
        transition: width 0.3s ease;
    }

    @media (max-width: 768px) {
        .checklist-footer {
            flex-direction: column;
            align-items: stretch;
        }

        .checklist-footer .btn {
            width: 100%;
        }
    }

    /* Mobile-friendly upload items */
    .upload-item {
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .upload-item:hover {
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
    }

    @media (max-width: 576px) {
        .upload-item .p-3 {
            flex-direction: column !important;
            align-items: flex-start !important;
        }

        .upload-item .flex-shrink-0 {
            width: 100%;
            margin-top: 12px;
            justify-content: flex-start;
        }

        .upload-item .flex-shrink-0 .btn {
            flex: 1;
        }
    }
</style>
<script>
    // Scroll to hash anchor on page load (for remarks panel redirect)
    $(document).ready(function() {
        if (window.location.hash) {
            var target = $(window.location.hash);
            if (target.length) {
                setTimeout(function() {
                    $('html, body').animate({ scrollTop: target.offset().top - 100 }, 400);
                }, 500);
            }
        }
    });

    <?php if(current_url() == base_url('Booking/Update') || current_url() == base_url('Booking/Create') || current_url() == base_url('Booking/Duplicate')) { ?>
    // Room Management Functions
    var roomsList = [];
    var guestsList = [];
    var roomBookingId = <?php echo (isset($BookingID) && $BookingID !== 'NA') ? $BookingID : 'null'; ?>;
    var glLocked = <?php echo (isset($LockStatus) && $LockStatus == 'Y') ? 'true' : 'false'; ?>;
    var tempRoomCounter = 0;

    function getAllocatedPax(excludeRoomId) {
        var totals = { adult: 0, child: 0, infant: 0 };
        roomsList.forEach(function(room) {
            if (excludeRoomId && room.id == excludeRoomId) return;
            totals.adult += parseInt(room.adult_count) || 0;
            totals.child += parseInt(room.child_count) || 0;
            totals.infant += parseInt(room.infant_count) || 0;
        });
        return totals;
    }

    function updateAllocatedDisplay() {
        var totals = getAllocatedPax();
        $('#allocated_adult').text(totals.adult);
        $('#allocated_child').text(totals.child);
        $('#allocated_infant').text(totals.infant);
    }

    function getGuestTypeBadge(type) {
        var badges = { 'ADULT': 'primary', 'CHILD': 'info', 'INFANT': 'warning' };
        return '<span class="label label-inline label-light-' + (badges[type] || 'secondary') + ' font-weight-bold">' + type + '</span>';
    }

    function renderGuestRows(roomId) {
        var roomGuests = guestsList.filter(function(g) {
            return roomId === null ? !g.guest_list_room_id : g.guest_list_room_id == roomId;
        });
        if (roomGuests.length === 0) return '';

        var html = '<tr class="guest-sub-row" data-parent-room="' + (roomId || 'unassigned') + '">' +
            '<td colspan="5" style="padding:0; border-top:0;">' +
            '<table class="table table-sm mb-0" style="background:#f9fbfd;">';

        roomGuests.forEach(function(guest) {
            var guestName = $('<span>').text((guest.Name || '') + ' ' + (guest.LastName || '')).html().trim() || '<em class="text-muted">No Name</em>';
            var deleteBtn = glLocked ? '' :
                '<button type="button" class="btn btn-xs btn-icon btn-light-danger delete-guest-btn ml-2" data-guest-id="' + guest.GuestListID + '" title="Delete Guest"><i class="la la-trash"></i></button>';
            html += '<tr>' +
                '<td style="padding:4px 12px;">' +
                '<i class="la la-user text-muted mr-1"></i>' + guestName +
                ' ' + getGuestTypeBadge(guest.Type) +
                deleteBtn +
                '</td>' +
                '</tr>';
        });

        html += '</table></td></tr>';
        return html;
    }

    function renderRoomsTable() {
        var tbody = $('#rooms_list');
        tbody.empty();
        if (roomsList.length === 0) {
            tbody.html('<tr id="no_rooms_row"><td colspan="5" class="text-muted text-center">No rooms created yet. Click "Add Room" to create one.</td></tr>');
        } else {
            roomsList.forEach(function(room) {
                var actionsTd = glLocked
                    ? '<td class="text-center text-muted">—</td>'
                    : '<td class="text-center" style="white-space:nowrap">' +
                      '<button type="button" class="btn btn-sm btn-icon btn-light-primary edit-room-btn mr-1" data-room-id="' + room.id + '"><i class="la la-edit"></i></button>' +
                      '<button type="button" class="btn btn-sm btn-icon btn-light-warning duplicate-room-btn mr-1" data-room-id="' + room.id + '" title="Duplicate"><i class="la la-copy"></i></button>' +
                      '<button type="button" class="btn btn-sm btn-icon btn-light-danger delete-room-btn" data-room-id="' + room.id + '"><i class="la la-trash"></i></button>' +
                      '</td>';
                var addBtn = function(roomId, type) {
                    return glLocked ? '' : ' &nbsp;<button type="button" class="btn btn-xs btn-icon btn-light-success add-count-btn ml-2" data-room-id="' + roomId + '" data-type="' + type + '" title="Add 1"><i class="la la-plus"></i></button>';
                };
                tbody.append(
                    '<tr data-room-id="' + room.id + '">' +
                    '<td class="font-weight-bold">' + $('<span>').text(room.room_name).html() + '</td>' +
                    '<td class="text-center" style="padding:8px 12px;">' + (room.adult_count || 0) + addBtn(room.id, 'adult_count') + '</td>' +
                    '<td class="text-center" style="padding:8px 12px;">' + (room.child_count || 0) + addBtn(room.id, 'child_count') + '</td>' +
                    '<td class="text-center" style="padding:8px 12px;">' + (room.infant_count || 0) + addBtn(room.id, 'infant_count') + '</td>' +
                    actionsTd +
                    '</tr>' +
                    renderGuestRows(room.id)
                );
            });

            // Show unassigned guests
            var unassignedHtml = renderGuestRows(null);
            if (unassignedHtml) {
                tbody.append(
                    '<tr class="bg-light-warning">' +
                    '<td class="font-weight-bold text-muted" colspan="4"><i class="la la-exclamation-circle text-warning mr-1"></i>Unassigned Guests</td>' +
                    '<td></td>' +
                    '</tr>' +
                    unassignedHtml
                );
            }
        }
        updateAllocatedDisplay();
        attachRoomEventHandlers();
    }

    function loadRooms() {
        if (roomBookingId) {
            $.ajax({
                url: '<?php echo base_url("Guest_List_Room/Read"); ?>',
                type: 'get',
                data: { booking_id: roomBookingId },
                dataType: 'json',
                success: function(data) {
                    roomsList = data || [];
                    // Also fetch guests
                    $.ajax({
                        url: '<?php echo base_url("Guest_List_Room/Read_Guests"); ?>',
                        type: 'get',
                        data: { booking_id: roomBookingId },
                        dataType: 'json',
                        success: function(guests) {
                            guestsList = guests || [];
                            renderRoomsTable();
                        }
                    });
                }
            });
        } else {
            // Add default room for new bookings
            tempRoomCounter++;
            roomsList.push({
                id: 'temp_' + tempRoomCounter,
                room_name: 'ROOM 1',
                adult_count: 0,
                child_count: 0,
                infant_count: 0
            });
            renderRoomsTable();
        }
    }

    function getRoomFormHtml() {
        return '<div class="row">' +
            '<div class="col-12 mb-3">' +
            '<label class="font-weight-bold">Room Name <span style="color:red;">*</span></label>' +
            '<input type="text" id="swal_room_name" class="form-control" placeholder="e.g. Room 101">' +
            '</div>' +
            '<div class="col-4">' +
            '<label class="font-weight-bold">Adult</label>' +
            '<input type="number" id="swal_adult_count" class="form-control" min="0" value="0">' +
            '</div>' +
            '<div class="col-4">' +
            '<label class="font-weight-bold">Child</label>' +
            '<input type="number" id="swal_child_count" class="form-control" min="0" value="0">' +
            '</div>' +
            '<div class="col-4">' +
            '<label class="font-weight-bold">Infant</label>' +
            '<input type="number" id="swal_infant_count" class="form-control" min="0" value="0">' +
            '</div>' +
            '</div>';
    }

    function attachRoomEventHandlers() {
        $('.add-count-btn').off('click').on('click', function() {
            var roomId = $(this).data('room-id');
            var type = $(this).data('type');
            if (roomBookingId) {
                $.ajax({
                    url: '<?php echo base_url("Guest_List_Room/Add_Count"); ?>',
                    type: 'post',
                    data: { room_id: roomId, type: type },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            loadRooms();
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error!', 'Failed to add count', 'error');
                    }
                });
            } else {
                var room = roomsList.find(function(r) { return r.id == roomId; });
                if (room) {
                    room[type] = (parseInt(room[type]) || 0) + 1;
                    renderRoomsTable();
                }
            }
        });

        $('.edit-room-btn').off('click').on('click', function() {
            var roomId = $(this).data('room-id');
            var room = roomsList.find(function(r) { return r.id == roomId; });
            if (!room) return;

            Swal.fire({
                title: 'Edit Room',
                html: getRoomFormHtml(),
                showCancelButton: true,
                confirmButtonText: 'Update',
                cancelButtonText: 'Cancel',
                didOpen: function() {
                    $('#swal_room_name').val(room.room_name);
                    $('#swal_adult_count').val(room.adult_count || 0);
                    $('#swal_child_count').val(room.child_count || 0);
                    $('#swal_infant_count').val(room.infant_count || 0);
                },
                preConfirm: function() {
                    var roomName = $('#swal_room_name').val().trim();
                    var adultCount = parseInt($('#swal_adult_count').val()) || 0;
                    var childCount = parseInt($('#swal_child_count').val()) || 0;
                    var infantCount = parseInt($('#swal_infant_count').val()) || 0;
                    if (!roomName) {
                        Swal.showValidationMessage('Room name is required!');
                        return false;
                    }
                    return { room_name: roomName, adult_count: adultCount, child_count: childCount, infant_count: infantCount };
                }
            }).then(function(result) {
                if (result.isConfirmed) {
                    if (roomBookingId) {
                        $.ajax({
                            url: '<?php echo base_url("Guest_List_Room/Update"); ?>',
                            type: 'post',
                            data: {
                                room_id: roomId,
                                room_name: result.value.room_name,
                                adult_count: result.value.adult_count,
                                child_count: result.value.child_count,
                                infant_count: result.value.infant_count
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire('Success!', response.message, 'success');
                                    loadRooms();
                                } else {
                                    Swal.fire('Error!', response.message, 'error');
                                }
                            },
                            error: function() {
                                Swal.fire('Error!', 'Failed to update room', 'error');
                            }
                        });
                    } else {
                        var idx = roomsList.findIndex(function(r) { return r.id == roomId; });
                        if (idx !== -1) {
                            roomsList[idx].room_name = result.value.room_name;
                            roomsList[idx].adult_count = result.value.adult_count;
                            roomsList[idx].child_count = result.value.child_count;
                            roomsList[idx].infant_count = result.value.infant_count;
                            renderRoomsTable();
                            Swal.fire('Success!', 'Room updated', 'success');
                        }
                    }
                }
            });
        });

        $('.delete-room-btn').off('click').on('click', function() {
            var roomId = $(this).data('room-id');
            Swal.fire({
                title: 'Are you sure?',
                text: 'This will delete the room.' + (roomBookingId ? ' Guests assigned to this room will be unassigned.' : ''),
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                if (result.isConfirmed) {
                    if (roomBookingId) {
                        $.ajax({
                            url: '<?php echo base_url("Guest_List_Room/Delete"); ?>',
                            type: 'get',
                            data: { room_id: roomId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire('Deleted!', response.message, 'success');
                                    loadRooms();
                                } else {
                                    Swal.fire('Error!', response.message, 'error');
                                }
                            },
                            error: function() {
                                Swal.fire('Error!', 'Failed to delete room', 'error');
                            }
                        });
                    } else {
                        roomsList = roomsList.filter(function(r) { return r.id != roomId; });
                        renderRoomsTable();
                        Swal.fire('Deleted!', 'Room removed', 'success');
                    }
                }
            });
        });

        $('.duplicate-room-btn').off('click').on('click', function() {
            var roomId = $(this).data('room-id');
            var room = roomsList.find(function(r) { return r.id == roomId; });
            if (!room) return;

            var newName = room.room_name + ' (COPY)';
            var dupData = {
                room_name: newName,
                adult_count: room.adult_count || 0,
                child_count: room.child_count || 0,
                infant_count: room.infant_count || 0
            };

            if (roomBookingId) {
                $.ajax({
                    url: '<?php echo base_url("Guest_List_Room/Create"); ?>',
                    type: 'post',
                    data: $.extend({ booking_id: roomBookingId }, dupData),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Success!', 'Room duplicated', 'success');
                            loadRooms();
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error!', 'Failed to duplicate room', 'error');
                    }
                });
            } else {
                tempRoomCounter++;
                roomsList.push({
                    id: 'temp_' + tempRoomCounter,
                    room_name: dupData.room_name,
                    adult_count: dupData.adult_count,
                    child_count: dupData.child_count,
                    infant_count: dupData.infant_count
                });
                renderRoomsTable();
                Swal.fire('Success!', 'Room duplicated', 'success');
            }
        });

        $('.delete-guest-btn').off('click').on('click', function() {
            var guestId = $(this).data('guest-id');
            Swal.fire({
                title: 'Are you sure?',
                text: 'This will delete the guest entry.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '<?php echo base_url("Guest_List_Room/Delete_Guest"); ?>',
                        type: 'get',
                        data: { guest_list_id: guestId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('Deleted!', response.message, 'success');
                                loadRooms();
                            } else {
                                Swal.fire('Error!', response.message, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error!', 'Failed to delete guest', 'error');
                        }
                    });
                }
            });
        });
    }

    $('#create_room_btn').click(function() {
        Swal.fire({
            title: 'Create New Room',
            html: getRoomFormHtml(),
            showCancelButton: true,
            confirmButtonText: 'Create',
            cancelButtonText: 'Cancel',
            preConfirm: function() {
                var roomName = $('#swal_room_name').val().trim();
                var adultCount = parseInt($('#swal_adult_count').val()) || 0;
                var childCount = parseInt($('#swal_child_count').val()) || 0;
                var infantCount = parseInt($('#swal_infant_count').val()) || 0;
                if (!roomName) {
                    Swal.showValidationMessage('Room name is required!');
                    return false;
                }
                return { room_name: roomName, adult_count: adultCount, child_count: childCount, infant_count: infantCount };
            }
        }).then(function(result) {
            if (result.isConfirmed) {
                if (roomBookingId) {
                    $.ajax({
                        url: '<?php echo base_url("Guest_List_Room/Create"); ?>',
                        type: 'post',
                        data: {
                            booking_id: roomBookingId,
                            room_name: result.value.room_name,
                            adult_count: result.value.adult_count,
                            child_count: result.value.child_count,
                            infant_count: result.value.infant_count
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('Success!', response.message, 'success');
                                loadRooms();
                            } else {
                                Swal.fire('Error!', response.message, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error!', 'Failed to create room', 'error');
                        }
                    });
                } else {
                    tempRoomCounter++;
                    roomsList.push({
                        id: 'temp_' + tempRoomCounter,
                        room_name: result.value.room_name,
                        adult_count: result.value.adult_count,
                        child_count: result.value.child_count,
                        infant_count: result.value.infant_count
                    });
                    renderRoomsTable();
                    Swal.fire('Success!', 'Room added', 'success');
                }
            }
        });
    });

    // Load rooms on page load
    $(document).ready(function() {
        loadRooms();
    });
    <?php } ?>
</script>
