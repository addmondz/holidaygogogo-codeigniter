<div class="d-flex flex-column-fluid">
	<div class="container-fluid">
		<?php if((int)$this->session->userdata('level') === 10) { ?>
			<?php $this->load->view('booking/_summary_cards'); ?>
		<?php } ?>
		<div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong>Dashboard</strong>
                    </h3>
                </div>
                <?php if($this->session->level != 20 && (int)$this->session->userdata('level') !== 10) { ?>
                    <div class="card-toolbar" style="width:350px;">
                        <label>Sales Agent</label>
                        <select title="--Select Sales Agent--" id="sales_agent" data-live-search="true" class="form-control selectpicker" multiple="multiple">
                            <?php foreach($sales_agents as $sales_agent) { ?>
                                <option data-icon="la la-user-alt font-size-lg bs-icon" value="<?php echo $sales_agent->AdminID; ?>"><?php echo $sales_agent->Name; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } ?>
            </div>
            <div class="card-body">
                <?php // Owner (Level 10) skips the whole travel/payment "reminder" row. ?>
                <?php if((int)$this->session->userdata('level') !== 10) { ?>
                <div class="row pt-7 pl-3 pr-3 mb-5" style="background-color:#CCCCFF30;">
                    <?php if($this->session->level == 20) { ?>
                        <div class="col-md-12">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#F0FFFF;">
                                    <h3 class="card-title font-weight-bold text-dark" style="font-size:14px;">Travel Reminder</h3>
                                    <!-- <div class="card-toolbar">
                                        <div id="kt_daterangepicker_4" class="input-icon sa_picker">
                                            <input readonly type="text" id="travel_reminder_date" autocomplete="off" class="form-control">
                                            <span>
                                                <i class="la la-calendar"></i>
                                            </span>
                                        </div>
                                    </div> -->
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#A7C7E730;">
                                    <!-- <h3 class="card-title font-weight-bold text-dark" style="font-size:14px;"> -->
                                        <?php 
                                            // $start_date = $this->input->get('start_date');
                                            // $end_date = $this->input->get('end_date');
                                            // if ($start_date && $end_date) {
                                            //     echo 'Travels: ' . date('j M', strtotime($start_date)) . ' - ' . date('j M Y', strtotime($end_date));
                                            // } elseif ($start_date) {
                                            //     echo 'Travels From: ' . date('j M Y', strtotime($start_date));
                                            // } elseif ($end_date) {
                                            //     echo 'Travels Until: ' . date('j M Y', strtotime($end_date));
                                            // } else {
                                            //     echo 'Travels Tomorrow';
                                            // }
                                        ?>
                                    <!-- </h3> -->
                                </div>
                                <div class="card-body">
                                    <div class="accordion accordion-solid accordion-toggle-plus">
                                        <div class="card">
                                            <div class="card-header">
                                                <div data-toggle="collapse" data-target="#sales_agent_upcoming_travels" class="card-title collapsed" style="font-size:13px;"><?php echo '<strong>' . count($sales_agent_upcoming_travels) . '</strong>&nbsp;BC(s)'; ?></div>
                                            </div>
                                            <div id="sales_agent_upcoming_travels" class="collapse">
                                                <div class="card-body">
                                                    <?php if(!empty($sales_agent_upcoming_travels)) {
                                                        foreach($sales_agent_upcoming_travels as $sales_agent_upcoming_travel) { ?>
                                                            <div class="d-flex align-items-center">
                                                                <span class="bullet bullet-bar bg-primary align-self-stretch"></span>
                                                                <label class="checkbox checkbox-lg checkbox-light-primary checkbox-inline flex-shrink-0 m-0 mx-4">
                                                                    <input disabled type="checkbox">
                                                                    <span></span>
                                                                </label>
                                                                <div class="d-flex flex-column flex-grow-1">
                                                                    <a href="<?php echo base_url('Booking?booking_number=') . $sales_agent_upcoming_travel->BookingNumber; ?>" target="_blank" class="text-dark-75 text-hover-primary font-weight-bold font-size-xs"><?php echo $sales_agent_upcoming_travel->BookingNumber; ?></a>
                                                                    <span class="text-muted font-weight-bold" style="color:#A7C7E7 !important; font-size:10px;"><?php echo strtoupper(date('j M Y', strtotime($sales_agent_upcoming_travel->StartDate))); ?></span>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $sales_agent_upcoming_travel->Name; ?></span>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $sales_agent_upcoming_travel->Customer; ?></span>
                                                                </div>
                                                            </div>
                                                            <br>
                                                        <?php }
                                                    } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#FFFAA030;">
                                    <h3 class="card-title font-weight-bold text-dark" style="font-size:14px;">
                                        <?php
                                            $start_date = $this->input->get('start_date');
                                            $end_date = $this->input->get('end_date');
                                            if ($start_date && $end_date) {
                                                echo 'Travels (Upcoming / Ongoing): ' . date('j M', strtotime($start_date)) . ' - ' . date('j M Y', strtotime($end_date));
                                            } elseif ($start_date) {
                                                echo 'Travels (Upcoming / Ongoing) From: ' . date('j M Y', strtotime($start_date));
                                            } elseif ($end_date) {
                                                echo 'Travels (Upcoming / Ongoing) Until: ' . date('j M Y', strtotime($end_date));
                                            } else {
                                                echo 'Travels In Next 7 Days (Upcoming / Ongoing)';
                                            }
                                        ?>
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="accordion accordion-solid accordion-toggle-plus">
                                        <div class="card">
                                            <div class="card-header">
                                                <div data-toggle="collapse" data-target="#sales_agent_pending_travel_vouchers" class="card-title collapsed" style="font-size:13px;"><?php echo '<strong>' . count($sales_agent_pending_travel_vouchers) . '</strong>&nbsp;BC(s)'; ?></div>
                                            </div>
                                            <div id="sales_agent_pending_travel_vouchers" class="collapse">
                                                <div class="card-body">
                                                    <?php if(!empty($sales_agent_pending_travel_vouchers)) {
                                                        foreach($sales_agent_pending_travel_vouchers as $sales_agent_pending_travel_voucher) { ?>
                                                            <div class="d-flex align-items-center">
                                                                <span class="bullet bullet-bar bg-warning align-self-stretch"></span>
                                                                <label class="checkbox checkbox-lg checkbox-light-warning checkbox-inline flex-shrink-0 m-0 mx-4">
                                                                    <input disabled type="checkbox">
                                                                    <span></span>
                                                                </label>
                                                                <div class="d-flex flex-column flex-grow-1">
                                                                    <div class="d-flex align-items-center">
                                                                        <a href="<?php echo base_url('Booking?booking_number=') . $sales_agent_pending_travel_voucher->BookingNumber; ?>" target="_blank" class="text-dark-75 text-hover-primary font-weight-bold font-size-xs"><?php echo $sales_agent_pending_travel_voucher->BookingNumber; ?></a>
                                                                        <span class="label label-inline label-light-warning font-weight-bold ml-2" style="font-size:9px;"><?php echo $sales_agent_pending_travel_voucher->Status; ?></span>
                                                                    </div>
                                                                    <span class="text-muted font-weight-bold" style="color:#FAC898 !important; font-size:10px;"><?php echo strtoupper(date('j M', strtotime($sales_agent_pending_travel_voucher->StartDate)) . ' - ' . date('j M Y', strtotime($sales_agent_pending_travel_voucher->EndDate))); ?></span>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $sales_agent_pending_travel_voucher->Name; ?></span>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $sales_agent_pending_travel_voucher->Customer; ?></span>
                                                                </div>
                                                            </div>
                                                            <br>
                                                        <?php }
                                                    } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#FFFFF0;">
                                    <h3 class="card-title font-weight-bold text-dark" style="font-size:14px;">Payment Reminder</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#FAA0A030;">
                                    <h3 class="card-title font-weight-bold text-dark" style="font-size:14px;">Payment Overdue</h3>
                                </div>
                                <div class="card-body">
                                    <div class="accordion accordion-solid accordion-toggle-plus">
                                        <div class="card">
                                            <div class="card-header">
                                                <div data-toggle="collapse" data-target="#sales_agent_overdue_payments" class="card-title collapsed" style="font-size:13px;"><?php echo '<strong>' . count($sales_agent_overdue_payments) . '</strong>&nbsp;BC(s)'; ?></div>
                                            </div>
                                            <div id="sales_agent_overdue_payments" class="collapse">
                                                <div class="card-body">
                                                    <?php if(!empty($sales_agent_overdue_payments)) {
                                                        foreach($sales_agent_overdue_payments as $sales_agent_overdue_payment) { ?>
                                                            <div class="d-flex align-items-center">
                                                                <span class="bullet bullet-bar bg-danger align-self-stretch"></span>
                                                                <label class="checkbox checkbox-lg checkbox-light-danger checkbox-inline flex-shrink-0 m-0 mx-4">
                                                                    <input disabled type="checkbox">
                                                                    <span></span>
                                                                </label>
                                                                <div class="d-flex flex-column flex-grow-1">
                                                                    <a href="<?php echo base_url('Booking/Update?booking_id=') . $sales_agent_overdue_payment->BookingID; ?>" target="_blank" class="text-dark-75 text-hover-primary font-weight-bold font-size-xs"><?php echo $sales_agent_overdue_payment->BookingNumber; ?></a>
                                                                    <a href="<?php echo base_url('Payment?booking_number=') . $sales_agent_overdue_payment->BookingNumber; ?>" target="_blank" class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $sales_agent_overdue_payment->Name; ?></a>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $sales_agent_overdue_payment->Customer; ?></span>
                                                                </div>
                                                            </div>
                                                            <br>
                                                        <?php }
                                                    } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#FAA0A030;">
                                    <h3 class="card-title font-weight-bold text-dark" style="font-size:14px;">Negative Profit Margin</h3>
                                </div>
                                <div class="card-body">
                                    <div class="accordion accordion-solid accordion-toggle-plus">
                                        <div class="card">
                                            <div class="card-header">
                                                <div data-toggle="collapse" data-target="#sales_agent_negative_profit_margins" class="card-title collapsed" style="font-size:13px;"><?php echo '<strong>' . count($sales_agent_negative_profit_margins) . '</strong>&nbsp;BC(s)'; ?></div>
                                            </div>
                                            <div id="sales_agent_negative_profit_margins" class="collapse">
                                                <div class="card-body">
                                                    <?php if(!empty($sales_agent_negative_profit_margins)) {
                                                        foreach($sales_agent_negative_profit_margins as $sales_agent_negative_profit_margin) { ?>
                                                            <div class="d-flex align-items-center">
                                                                <span class="bullet bullet-bar bg-danger align-self-stretch"></span>
                                                                <label class="checkbox checkbox-lg checkbox-light-danger checkbox-inline flex-shrink-0 m-0 mx-4">
                                                                    <input disabled type="checkbox">
                                                                    <span></span>
                                                                </label>
                                                                <div class="d-flex flex-column flex-grow-1">
                                                                    <a href="<?php echo base_url('Payment?booking_number=') . $sales_agent_negative_profit_margin->BookingNumber; ?>" target="_blank" class="text-dark-75 text-hover-primary font-weight-bold font-size-xs"><?php echo $sales_agent_negative_profit_margin->BookingNumber; ?></a>
                                                                    <span class="text-muted font-weight-bold" style="color:#F88379 !important; font-size:10px;"><?php echo 'RM ' . number_format($sales_agent_negative_profit_margin->NetProfit, 2, '.', ','); ?></span>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $sales_agent_negative_profit_margin->Name; ?></span>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $sales_agent_negative_profit_margin->Customer; ?></span>
                                                                </div>
                                                            </div>
                                                            <br>
                                                        <?php }
                                                    } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#C1E1C130;">
                                    <h3 class="card-title font-weight-bold text-dark" style="font-size:14px;">Profit Margin Less Than 10% - Last 30 Days</h3>
                                </div>
                                <div class="card-body">
                                    <div class="accordion accordion-solid accordion-toggle-plus">
                                        <div class="card">
                                            <div class="card-header">
                                                <div data-toggle="collapse" data-target="#sales_agent_profit_margins_less_than_10_percent" class="card-title collapsed" style="font-size:13px;"><?php echo '<strong>' . count($sales_agent_profit_margins_less_than_10_percent) . '</strong>&nbsp;BC(s)'; ?></div>
                                            </div>
                                            <div id="sales_agent_profit_margins_less_than_10_percent" class="collapse">
                                                <div class="card-body">
                                                    <?php if(!empty($sales_agent_profit_margins_less_than_10_percent)) {
                                                        foreach($sales_agent_profit_margins_less_than_10_percent as $sales_agent_profit_margin_less_than_10_percent) { ?>
                                                            <div class="d-flex align-items-center">
                                                                <span class="bullet bullet-bar bg-success align-self-stretch"></span>
                                                                <label class="checkbox checkbox-lg checkbox-light-success checkbox-inline flex-shrink-0 m-0 mx-4">
                                                                    <input disabled type="checkbox">
                                                                    <span></span>
                                                                </label>
                                                                <div class="d-flex flex-column flex-grow-1">
                                                                    <a href="<?php echo base_url('Payment?booking_number=') . $sales_agent_profit_margin_less_than_10_percent->BookingNumber; ?>" target="_blank" class="text-dark-75 text-hover-primary font-weight-bold font-size-xs"><?php echo $sales_agent_profit_margin_less_than_10_percent->BookingNumber; ?></a>
                                                                    <span class="text-muted font-weight-bold" style="color:#50C878 !important; font-size:10px;"><?php echo $sales_agent_profit_margin_less_than_10_percent->Percentage; ?></span>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $sales_agent_profit_margin_less_than_10_percent->Name; ?></span>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $sales_agent_profit_margin_less_than_10_percent->Customer; ?></span>
                                                                </div>
                                                            </div>
                                                            <br>
                                                        <?php }
                                                    } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#FFFFF0;">
                                    <h3 class="card-title font-weight-bold text-dark" style="font-size:14px;">Payment Reminder</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#C1E1C130;">
                                    <h3 class="card-title font-weight-bold text-dark" style="font-size:14px;">Pending Credit Payments</h3>
                                </div>
                                <div class="card-body">
                                    <div class="accordion accordion-solid accordion-toggle-plus">
                                        <div class="card">
                                            <div class="card-header">
                                                <div data-toggle="collapse" data-target="#sales_agent_pending_credit_payments" class="card-title collapsed" style="font-size:13px;"><?php echo '<strong>' . count($sales_agent_pending_credit_payments) . '</strong>&nbsp;Payment(s)'; ?></div>
                                            </div>
                                            <div id="sales_agent_pending_credit_payments" class="collapse">
                                                <div class="card-body">
                                                    <?php if(!empty($sales_agent_pending_credit_payments)) {
                                                        foreach($sales_agent_pending_credit_payments as $sales_agent_pending_credit_payment) { ?>
                                                            <div class="d-flex align-items-center">
                                                                <span class="bullet bullet-bar bg-success align-self-stretch"></span>
                                                                <label class="checkbox checkbox-lg checkbox-light-success checkbox-inline flex-shrink-0 m-0 mx-4">
                                                                    <input disabled type="checkbox">
                                                                    <span></span>
                                                                </label>
                                                                <div class="d-flex flex-column flex-grow-1">
                                                                    <a href="<?php echo base_url('Payment/View?payment_id=') . $sales_agent_pending_credit_payment->PaymentID; ?>" target="_blank" class="text-dark-75 text-hover-primary font-weight-bold font-size-xs"><?php echo $sales_agent_pending_credit_payment->BookingNumber; ?></a>
                                                                    <span class="text-muted font-weight-bold" style="color:#A7C7E7 !important; font-size:10px;"><?php echo $sales_agent_pending_credit_payment->Customer; ?></span>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo strtoupper(date('j M Y', strtotime($sales_agent_pending_credit_payment->Date))); ?></span>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $sales_agent_pending_credit_payment->Type; ?></span>
                                                                    <span class="text-muted font-weight-bold" style="color:#50C878 !important; font-size:10px;"><?php echo 'RM ' . number_format($sales_agent_pending_credit_payment->Credit, 2, '.', ','); ?></span>
                                                                </div>
                                                            </div>
                                                            <br>
                                                        <?php }
                                                    } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#FAA0A030;">
                                    <h3 class="card-title font-weight-bold text-dark" style="font-size:14px;">Pending Debit Payments</h3>
                                </div>
                                <div class="card-body">
                                    <div class="accordion accordion-solid accordion-toggle-plus">
                                        <div class="card">
                                            <div class="card-header">
                                                <div data-toggle="collapse" data-target="#sales_agent_pending_debit_payments" class="card-title collapsed" style="font-size:13px;"><?php echo '<strong>' . count($sales_agent_pending_debit_payments) . '</strong>&nbsp;Payment(s)'; ?></div>
                                            </div>
                                            <div id="sales_agent_pending_debit_payments" class="collapse">
                                                <div class="card-body">
                                                    <?php if(!empty($sales_agent_pending_debit_payments)) {
                                                        foreach($sales_agent_pending_debit_payments as $sales_agent_pending_debit_payment) { ?>
                                                            <div class="d-flex align-items-center">
                                                                <span class="bullet bullet-bar bg-danger align-self-stretch"></span>
                                                                <label class="checkbox checkbox-lg checkbox-light-danger checkbox-inline flex-shrink-0 m-0 mx-4">
                                                                    <input disabled type="checkbox">
                                                                    <span></span>
                                                                </label>
                                                                <div class="d-flex flex-column flex-grow-1">
                                                                    <a href="<?php echo base_url('Payment/View?payment_id=') . $sales_agent_pending_debit_payment->PaymentID; ?>" target="_blank" class="text-dark-75 text-hover-primary font-weight-bold font-size-xs"><?php echo $sales_agent_pending_debit_payment->BookingNumber; ?></a>
                                                                    <?php if(in_array($sales_agent_pending_debit_payment->Type, array('SUPPLIER PAYMENT (DEPOSIT)', 'SUPPLIER PAYMENT (FULL)', 'SUPPLIER PAYMENT (ADDITIONAL)'))) { ?>
                                                                        <span class="text-muted font-weight-bold" style="color:#A7C7E7 !important; font-size:10px;"><?php echo $sales_agent_pending_debit_payment->Name; ?></span>
                                                                    <?php } else { ?>
                                                                        <span class="text-muted font-weight-bold" style="color:#A7C7E7 !important; font-size:10px;"><?php echo $sales_agent_pending_debit_payment->BankHolder; ?></span>
                                                                    <?php } ?>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo strtoupper(date('j M Y', strtotime($sales_agent_pending_debit_payment->Deadline))); ?></span>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $sales_agent_pending_debit_payment->Type; ?></span>
                                                                    <span class="text-muted font-weight-bold" style="color:#F88379 !important; font-size:10px;"><?php echo 'RM ' . number_format($sales_agent_pending_debit_payment->Debit, 2, '.', ','); ?></span>
                                                                </div>
                                                            </div>
                                                            <br>
                                                        <?php }
                                                    } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } else { ?>
                        <div class="col-md-12">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#F0FFFF;">
                                    <h3 class="card-title font-weight-bold text-dark" style="font-size:14px;">Travel Reminder</h3>
                                    <div class="card-toolbar">
                                        <div id="kt_daterangepicker_4" class="input-icon travel_reminder_picker">
                                            <?php
                                                $start_date_travel_reminder =isset( $_GET['start_date']) ? date('d/m/Y', strtotime($_GET['start_date'])) : '';
                                                $end_date_travel_reminder =isset( $_GET['end_date']) ? date('d/m/Y', strtotime($_GET['end_date'])) : '';

                                                $display_travel_reminder_date = '';
                                                if ($start_date_travel_reminder && $end_date_travel_reminder) {
                                                    $display_travel_reminder_date = $start_date_travel_reminder . ' - ' . $end_date_travel_reminder;
                                                }
                                            ?>
                                            <input readonly type="text" id="travel_reminder_date" autocomplete="off" class="form-control" value="<?php echo $display_travel_reminder_date; ?>">
                                            <span>
                                                <i class="la la-calendar"></i>
                                            </span>
                                        </div>
                                        <span id="reset_travel_reminder_date" class="btn btn-icon btn-warning btn-sm ml-1">
                                            <i class="la la-refresh"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#A7C7E730;">
                                    <h3 class="card-title font-weight-bold text-dark" style="font-size:14px;">
                                        <?php 
                                            $start_date = $this->input->get('start_date');
                                            $end_date = $this->input->get('end_date');
                                            if ($start_date && $end_date) {
                                                echo 'Travels: ' . date('j M', strtotime($start_date)) . ' - ' . date('j M Y', strtotime($end_date));
                                            } elseif ($start_date) {
                                                echo 'Travels From: ' . date('j M Y', strtotime($start_date));
                                            } elseif ($end_date) {
                                                echo 'Travels Until: ' . date('j M Y', strtotime($end_date));
                                            } else {
                                                echo 'Travels Tomorrow';
                                            }
                                        ?>
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="accordion accordion-solid accordion-toggle-plus">
                                        <div class="card">
                                            <div class="card-header">
                                                <div id="upcoming_travels_header" data-toggle="collapse" data-target="#upcoming_travels" class="card-title collapsed" style="font-size:13px;"><?php echo '<strong>' . count($upcoming_travels) . '</strong>&nbsp;BC(s)'; ?></div>
                                            </div>
                                            <div id="upcoming_travels" class="collapse">
                                                <div class="card-body">
                                                    <?php if(!empty($upcoming_travels)) {
                                                        foreach($upcoming_travels as $upcoming_travel) { ?>
                                                            <div class="d-flex align-items-center <?php echo $upcoming_travel->AdminID; ?>">
                                                                <span class="bullet bullet-bar bg-primary align-self-stretch"></span>
                                                                <label class="checkbox checkbox-lg checkbox-light-primary checkbox-inline flex-shrink-0 m-0 mx-4">
                                                                    <input disabled type="checkbox">
                                                                    <span></span>
                                                                </label>
                                                                <div class="d-flex flex-column flex-grow-1">
                                                                    <span class="font-weight-bold" style="color:#C3B1E1; font-size:11px;"><?php echo $upcoming_travel->SalesAgent; ?></span>
                                                                    <a href="<?php echo base_url('Booking?booking_number=') . $upcoming_travel->BookingNumber; ?>" target="_blank" class="text-dark-75 text-hover-primary font-weight-bold font-size-xs"><?php echo $upcoming_travel->BookingNumber; ?></a>
                                                                    <span class="text-muted font-weight-bold" style="color:#A7C7E7 !important; font-size:10px;"><?php echo strtoupper(date('j M Y', strtotime($upcoming_travel->StartDate))); ?></span>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $upcoming_travel->Destination; ?></span>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $upcoming_travel->Customer; ?></span>
                                                                </div>
                                                            </div>
                                                            <br class="<?php echo $upcoming_travel->AdminID; ?>">
                                                        <?php }
                                                    } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#FFFAA030;">
                                    <h3 class="card-title font-weight-bold text-dark" style="font-size:14px;">
                                        <?php
                                            $start_date = $this->input->get('start_date');
                                            $end_date = $this->input->get('end_date');
                                            if ($start_date && $end_date) {
                                                echo 'Travels (Upcoming / Ongoing): ' . date('j M', strtotime($start_date)) . ' - ' . date('j M Y', strtotime($end_date));
                                            } elseif ($start_date) {
                                                echo 'Travels (Upcoming / Ongoing) From: ' . date('j M Y', strtotime($start_date));
                                            } elseif ($end_date) {
                                                echo 'Travels (Upcoming / Ongoing) Until: ' . date('j M Y', strtotime($end_date));
                                            } else {
                                                echo 'Travels In Next 7 Days (Upcoming / Ongoing)';
                                            }
                                        ?>
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="accordion accordion-solid accordion-toggle-plus">
                                        <div class="card">
                                            <div class="card-header">
                                                <div id="pending_travel_vouchers_header" data-toggle="collapse" data-target="#pending_travel_vouchers" class="card-title collapsed" style="font-size:13px;"><?php echo '<strong>' . count($pending_travel_vouchers) . '</strong>&nbsp;BC(s)'; ?></div>
                                            </div>
                                            <div id="pending_travel_vouchers" class="collapse">
                                                <div class="card-body">
                                                    <?php if(!empty($pending_travel_vouchers)) {
                                                        foreach($pending_travel_vouchers as $pending_travel_voucher) { ?>
                                                            <div class="d-flex align-items-center <?php echo $pending_travel_voucher->AdminID; ?>">
                                                                <span class="bullet bullet-bar bg-warning align-self-stretch"></span>
                                                                <label class="checkbox checkbox-lg checkbox-light-warning checkbox-inline flex-shrink-0 m-0 mx-4">
                                                                    <input disabled type="checkbox">
                                                                    <span></span>
                                                                </label>
                                                                <div class="d-flex flex-column flex-grow-1">
                                                                    <span class="font-weight-bold" style="color:#C3B1E1; font-size:11px;"><?php echo $pending_travel_voucher->SalesAgent; ?></span>
                                                                    <div class="d-flex align-items-center">
                                                                        <a href="<?php echo base_url('Booking?booking_number=') . $pending_travel_voucher->BookingNumber; ?>" target="_blank" class="text-dark-75 text-hover-primary font-weight-bold font-size-xs"><?php echo $pending_travel_voucher->BookingNumber; ?></a>
                                                                        <span class="label label-inline label-light-warning font-weight-bold ml-2" style="font-size:9px;"><?php echo $pending_travel_voucher->Status; ?></span>
                                                                    </div>
                                                                    <span class="text-muted font-weight-bold" style="color:#FAC898 !important; font-size:10px;"><?php echo strtoupper(date('j M', strtotime($pending_travel_voucher->StartDate)) . ' - ' . date('j M Y', strtotime($pending_travel_voucher->EndDate))); ?></span>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $pending_travel_voucher->Destination; ?></span>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $pending_travel_voucher->Customer; ?></span>
                                                                </div>
                                                            </div>
                                                            <br class="<?php echo $pending_travel_voucher->AdminID; ?>">
                                                        <?php }
                                                    } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#FFFAA030;">
                                    <h3 class="card-title font-weight-bold text-dark" style="font-size:14px;">Travel Completed - Pending Review</h3>
                                </div>
                                <div class="card-body">
                                    <div class="accordion accordion-solid accordion-toggle-plus">
                                        <div class="card">
                                            <div class="card-header">
                                                <div id="pending_reviews_header" data-toggle="collapse" data-target="#pending_reviews" class="card-title collapsed" style="font-size:13px;"><?php echo '<strong>' . count($pending_reviews) . '</strong>&nbsp;BC(s)'; ?></div>
                                            </div>
                                            <div id="pending_reviews" class="collapse">
                                                <div class="card-body">
                                                    <?php if(!empty($pending_reviews)) {
                                                        foreach($pending_reviews as $pending_review) { ?>
                                                            <div class="d-flex align-items-center <?php echo $pending_review->AdminID; ?>">
                                                                <span class="bullet bullet-bar bg-warning align-self-stretch"></span>
                                                                <label class="checkbox checkbox-lg checkbox-light-warning checkbox-inline flex-shrink-0 m-0 mx-4">
                                                                    <input disabled type="checkbox">
                                                                    <span></span>
                                                                </label>
                                                                <div class="d-flex flex-column flex-grow-1">
                                                                    <span class="font-weight-bold" style="color:#C3B1E1; font-size:11px;"><?php echo $pending_review->SalesAgent; ?></span>
                                                                    <a href="<?php echo base_url('Booking?booking_number=') . $pending_review->BookingNumber; ?>" target="_blank" class="text-dark-75 text-hover-primary font-weight-bold font-size-xs"><?php echo $pending_review->BookingNumber; ?></a>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $pending_review->Destination; ?></span>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $pending_review->Customer; ?></span>
                                                                </div>
                                                            </div>
                                                            <br class="<?php echo $pending_review->AdminID; ?>">
                                                        <?php }
                                                    } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#FFFFF0;">
                                    <h3 class="card-title font-weight-bold text-dark" style="font-size:14px;">Payment Reminder</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#FAA0A030;">
                                    <h3 class="card-title font-weight-bold text-dark" style="font-size:14px;">Payment Overdue</h3>
                                </div>
                                <div class="card-body">
                                    <div class="accordion accordion-solid accordion-toggle-plus">
                                        <div class="card">
                                            <div class="card-header">
                                                <div id="overdue_payments_header" data-toggle="collapse" data-target="#overdue_payments" class="card-title collapsed" style="font-size:13px;"><?php echo '<strong>' . count($overdue_payments) . '</strong>&nbsp;BC(s)'; ?></div>
                                            </div>
                                            <div id="overdue_payments" class="collapse">
                                                <div class="card-body">
                                                    <?php if(!empty($overdue_payments)) {
                                                        foreach($overdue_payments as $overdue_payment) { ?>
                                                            <div class="d-flex align-items-center <?php echo $overdue_payment->AdminID; ?>">
                                                                <span class="bullet bullet-bar bg-danger align-self-stretch"></span>
                                                                <label class="checkbox checkbox-lg checkbox-light-danger checkbox-inline flex-shrink-0 m-0 mx-4">
                                                                    <input disabled type="checkbox">
                                                                    <span></span>
                                                                </label>
                                                                <div class="d-flex flex-column flex-grow-1">
                                                                    <span class="font-weight-bold" style="color:#C3B1E1; font-size:11px;"><?php echo $overdue_payment->SalesAgent; ?></span>
                                                                    <a href="<?php echo base_url('Booking/Update?booking_id=') . $overdue_payment->BookingID; ?>" target="_blank" class="text-dark-75 text-hover-primary font-weight-bold font-size-xs"><?php echo $overdue_payment->BookingNumber; ?></a>
                                                                    <a href="<?php echo base_url('Payment?booking_number=') . $overdue_payment->BookingNumber; ?>" target="_blank" class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $overdue_payment->Destination; ?></a>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $overdue_payment->Customer; ?></span>
                                                                </div>
                                                            </div>
                                                            <br class="<?php echo $overdue_payment->AdminID; ?>">
                                                        <?php }
                                                    } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#FAA0A030;">
                                    <h3 class="card-title font-weight-bold text-dark" style="font-size:14px;">Negative Profit Margin</h3>
                                </div>
                                <div class="card-body">
                                    <div class="accordion accordion-solid accordion-toggle-plus">
                                        <div class="card">
                                            <div class="card-header">
                                                <div id="negative_profit_margins_header" data-toggle="collapse" data-target="#negative_profit_margins" class="card-title collapsed" style="font-size:13px;"><?php echo '<strong>' . count($negative_profit_margins) . '</strong>&nbsp;BC(s)'; ?></div>
                                            </div>
                                            <div id="negative_profit_margins" class="collapse">
                                                <div class="card-body">
                                                    <?php if(!empty($negative_profit_margins)) {
                                                        foreach($negative_profit_margins as $negative_profit_margin) { ?>
                                                            <div class="d-flex align-items-center <?php echo $negative_profit_margin->AdminID; ?>">
                                                                <span class="bullet bullet-bar bg-danger align-self-stretch"></span>
                                                                <label class="checkbox checkbox-lg checkbox-light-danger checkbox-inline flex-shrink-0 m-0 mx-4">
                                                                    <input disabled type="checkbox">
                                                                    <span></span>
                                                                </label>
                                                                <div class="d-flex flex-column flex-grow-1">
                                                                    <span class="font-weight-bold" style="color:#C3B1E1; font-size:11px;"><?php echo $negative_profit_margin->SalesAgent; ?></span>
                                                                    <a href="<?php echo base_url('Payment?booking_number=') . $negative_profit_margin->BookingNumber; ?>" target="_blank" class="text-dark-75 text-hover-primary font-weight-bold font-size-xs"><?php echo $negative_profit_margin->BookingNumber; ?></a>
                                                                    <span class="text-muted font-weight-bold" style="color:#F88379 !important; font-size:10px;"><?php echo 'RM ' . number_format($negative_profit_margin->NetProfit, 2, '.', ','); ?></span>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $negative_profit_margin->Destination; ?></span>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $negative_profit_margin->Customer; ?></span>
                                                                </div>
                                                            </div>
                                                            <br class="<?php echo $negative_profit_margin->AdminID; ?>">
                                                        <?php }
                                                    } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#C1E1C130;">
                                    <h3 class="card-title font-weight-bold text-dark" style="font-size:14px;">Profit Margin Less Than 10% - Last 30 Days</h3>
                                </div>
                                <div class="card-body">
                                    <div class="accordion accordion-solid accordion-toggle-plus">
                                        <div class="card">
                                            <div class="card-header">
                                                <div id="profit_margins_less_than_10_percent_header" data-toggle="collapse" data-target="#profit_margins_less_than_10_percent" class="card-title collapsed" style="font-size:13px;"><?php echo '<strong>' . count($profit_margins_less_than_10_percent) . '</strong>&nbsp;BC(s)'; ?></div>
                                            </div>
                                            <div id="profit_margins_less_than_10_percent" class="collapse">
                                                <div class="card-body">
                                                    <?php if(!empty($profit_margins_less_than_10_percent)) {
                                                        foreach($profit_margins_less_than_10_percent as $profit_margin_less_than_10_percent) { ?>
                                                            <div class="d-flex align-items-center <?php echo $profit_margin_less_than_10_percent->AdminID; ?>">
                                                                <span class="bullet bullet-bar bg-success align-self-stretch"></span>
                                                                <label class="checkbox checkbox-lg checkbox-light-success checkbox-inline flex-shrink-0 m-0 mx-4">
                                                                    <input disabled type="checkbox">
                                                                    <span></span>
                                                                </label>
                                                                <div class="d-flex flex-column flex-grow-1">
                                                                    <span class="font-weight-bold" style="color:#C3B1E1; font-size:11px;"><?php echo $profit_margin_less_than_10_percent->SalesAgent; ?></span>
                                                                    <a href="<?php echo base_url('Payment?booking_number=') . $profit_margin_less_than_10_percent->BookingNumber; ?>" target="_blank" class="text-dark-75 text-hover-primary font-weight-bold font-size-xs"><?php echo $profit_margin_less_than_10_percent->BookingNumber; ?></a>
                                                                    <span class="text-muted font-weight-bold" style="color:#50C878 !important; font-size:10px;"><?php echo $profit_margin_less_than_10_percent->Percentage; ?></span>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $profit_margin_less_than_10_percent->Destination; ?></span>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $profit_margin_less_than_10_percent->Customer; ?></span>
                                                                </div>
                                                            </div>
                                                            <br class="<?php echo $profit_margin_less_than_10_percent->AdminID; ?>">
                                                        <?php }
                                                    } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#FFFFF0;">
                                    <h3 class="card-title font-weight-bold text-dark" style="font-size:14px;">Payment Reminder</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#C1E1C130;">
                                    <h3 class="card-title font-weight-bold text-dark" style="font-size:14px;">Pending Credit Payments</h3>
                                </div>
                                <div class="card-body">
                                    <div class="accordion accordion-solid accordion-toggle-plus">
                                        <div class="card">
                                            <div class="card-header">
                                                <div id="pending_credit_payments_header" data-toggle="collapse" data-target="#pending_credit_payments" class="card-title collapsed" style="font-size:13px;"><?php echo '<strong>' . count($pending_credit_payments) . '</strong>&nbsp;Payment(s)'; ?></div>
                                            </div>
                                            <div id="pending_credit_payments" class="collapse">
                                                <div class="card-body">
                                                    <?php if(!empty($pending_credit_payments)) {
                                                        foreach($pending_credit_payments as $pending_credit_payment) { ?>
                                                            <div class="d-flex align-items-center <?php echo $pending_credit_payment->AdminID; ?>">
                                                                <span class="bullet bullet-bar bg-success align-self-stretch"></span>
                                                                <label class="checkbox checkbox-lg checkbox-light-success checkbox-inline flex-shrink-0 m-0 mx-4">
                                                                    <input disabled type="checkbox">
                                                                    <span></span>
                                                                </label>
                                                                <div class="d-flex flex-column flex-grow-1">
                                                                    <span class="font-weight-bold" style="color:#C3B1E1; font-size:11px;"><?php echo $pending_credit_payment->Name; ?></span>
                                                                    <a href="<?php echo base_url('Payment/Update?payment_id=') . $pending_credit_payment->PaymentID; ?>" target="_blank" class="text-dark-75 text-hover-primary font-weight-bold font-size-xs"><?php echo $pending_credit_payment->BookingNumber; ?></a>
                                                                    <span class="text-muted font-weight-bold" style="color:#A7C7E7 !important; font-size:10px;"><?php echo $pending_credit_payment->Customer; ?></span>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo strtoupper(date('j M Y', strtotime($pending_credit_payment->Date))); ?></span>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $pending_credit_payment->Type; ?></span>
                                                                    <span class="text-muted font-weight-bold" style="color:#50C878 !important; font-size:10px;"><?php echo 'RM ' . number_format($pending_credit_payment->Credit, 2, '.', ','); ?></span>
                                                                </div>
                                                            </div>
                                                            <br class="<?php echo $pending_credit_payment->AdminID; ?>">
                                                        <?php }
                                                    } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#FAA0A030;">
                                    <h3 class="card-title font-weight-bold text-dark" style="font-size:14px;">Pending Debit Payments</h3>
                                </div>
                                <div class="card-body">
                                    <div class="accordion accordion-solid accordion-toggle-plus">
                                        <div class="card">
                                            <div class="card-header">
                                                <div id="pending_debit_payments_header" data-toggle="collapse" data-target="#pending_debit_payments" class="card-title collapsed" style="font-size:13px;"><?php echo '<strong>' . count($pending_debit_payments) . '</strong>&nbsp;Payment(s)'; ?></div>
                                            </div>
                                            <div id="pending_debit_payments" class="collapse">
                                                <div class="card-body">
                                                    <?php if(!empty($pending_debit_payments)) {
                                                        foreach($pending_debit_payments as $pending_debit_payment) { ?>
                                                            <div class="d-flex align-items-center <?php echo $pending_debit_payment->AdminID; ?>">
                                                                <span class="bullet bullet-bar bg-danger align-self-stretch"></span>
                                                                <label class="checkbox checkbox-lg checkbox-light-danger checkbox-inline flex-shrink-0 m-0 mx-4">
                                                                    <input disabled type="checkbox">
                                                                    <span></span>
                                                                </label>
                                                                <div class="d-flex flex-column flex-grow-1">
                                                                    <span class="font-weight-bold" style="color:#C3B1E1; font-size:11px;"><?php echo $pending_debit_payment->SalesAgent; ?></span>
                                                                    <a href="<?php echo base_url('Payment/Update?payment_id=') . $pending_debit_payment->PaymentID; ?>" target="_blank" class="text-dark-75 text-hover-primary font-weight-bold font-size-xs"><?php echo $pending_debit_payment->BookingNumber; ?></a>
                                                                    <?php if(in_array($pending_debit_payment->Type, array('SUPPLIER PAYMENT (DEPOSIT)', 'SUPPLIER PAYMENT (FULL)', 'SUPPLIER PAYMENT (ADDITIONAL)'))) { ?>
                                                                        <span class="text-muted font-weight-bold" style="color:#A7C7E7 !important; font-size:10px;"><?php echo $pending_debit_payment->Supplier; ?></span>
                                                                    <?php } else { ?>
                                                                        <span class="text-muted font-weight-bold" style="color:#A7C7E7 !important; font-size:10px;"><?php echo $pending_debit_payment->BankHolder; ?></span>
                                                                    <?php } ?>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo strtoupper(date('j M Y', strtotime($pending_debit_payment->Deadline))); ?></span>
                                                                    <span class="text-muted font-weight-bold" style="font-size:10px;"><?php echo $pending_debit_payment->Type; ?></span>
                                                                    <span class="text-muted font-weight-bold" style="color:#F88379 !important; font-size:10px;"><?php echo 'RM ' . number_format($pending_debit_payment->Debit, 2, '.', ','); ?></span>
                                                                </div>
                                                            </div>
                                                            <br class="<?php echo $pending_debit_payment->AdminID; ?>">
                                                        <?php }
                                                    } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <?php } // end reminder row (non-Owner) ?>
                <?php if($this->session->level == 20) { ?>
                    <div class="row pt-7 pl-3 pr-3 mb-5" style="background-color:#B6D0E230;">
                        <div class="col-md-12">
                            <div class="card card-custom gutter-b">
                                <div class="card-header" style="background-color:#FFFAA030;">
                                    <div class="card-title">
                                        <h3 class="card-label" style="font-size:14px;"><?php echo 'Year ' . date('Y') . ' Daily Sales'; ?></h3>
                                    </div>
                                </div>
                                <div class="card-body" style="overflow-x:auto; position:relative;">
                                    <div id="sales_agent_daily_sales" style="min-width:900px;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="card card-custom gutter-b">
                                <div class="card-header" style="background-color:#FFFAA030;">
                                    <div class="card-title">
                                        <h3 class="card-label" style="font-size:14px;"><?php echo 'Year ' . date('Y') . ' Monthly Sales'; ?></h3>
                                    </div>
                                </div>
                                <div class="card-body" style="overflow-x:auto; position:relative;">
                                    <div id="sales_agent_monthly_sales" style="min-width:900px;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="card card-custom gutter-b">
                                <div class="card-header" style="background-color:#FAA0A030;">
                                    <div class="card-title">
                                        <h3 class="card-label" style="font-size:14px;"><?php echo 'Year ' . date('Y') . ' Monthly Cancellation Rates'; ?></h3>
                                    </div>
                                </div>
                                <div class="card-body" style="overflow-x:auto; position:relative;">
                                    <div id="sales_agent_cancellation_rates" style="min-width:900px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php } elseif((int)$this->session->userdata('level') === 10) { ?>
                    <?php
                        // ---------- Owner (Level 10) lean KPI cards ----------
                        $rm   = function($v) { return 'RM ' . number_format((float)$v, 2, '.', ','); };
                        $rm0  = function($v) { return 'RM ' . number_format((float)$v, 0, '.', ','); };
                        // Same-period-last-year comparison chip for a current vs LY pair.
                        $yoy  = function($cur, $ly) use ($rm0) {
                            $cur = (float)$cur; $ly = (float)$ly;
                            if($ly > 0) {
                                $d   = round((($cur - $ly) / $ly) * 100);
                                $cls = $d > 0 ? 'is-up' : ($d < 0 ? 'is-down' : 'is-flat');
                                $sym = $d > 0 ? '&#9650;' : ($d < 0 ? '&#9660;' : '&#8226;');
                                return '<span class="okpi-ly">LY ' . $rm0($ly) . ' <span class="okpi-delta ' . $cls . '">' . $sym . ' ' . abs($d) . '%</span></span>';
                            }
                            if($cur > 0) { return '<span class="okpi-ly">LY ' . $rm0(0) . ' <span class="okpi-delta is-up">new</span></span>'; }
                            return '<span class="okpi-ly">LY &mdash;</span>';
                        };
                        $period_labels = array('yesterday' => 'Yesterday', 'today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year');
                        // Actual-vs-target cell: amount / target + % and a bar.
                        // The bar caps at 100% visually but the % label keeps the
                        // true figure (over-achievement allowed). No target => plain
                        // amount with a "no target" note.
                        $tgt_cell = function($actual, $target) use ($rm) {
                            $actual = (float)$actual; $target = (float)$target;
                            if($target <= 0) {
                                return '<div class="tgt-line"><span class="team-amt">' . $rm($actual) . '</span></div>'
                                     . '<div class="kpi-sub">No target set</div>';
                            }
                            $pct = round(($actual / $target) * 100);
                            $w   = max(0, min(100, ($actual / $target) * 100));
                            $hit = $actual >= $target;
                            return '<div class="tgt-line"><span class="team-amt">' . $rm($actual) . '</span>'
                                 . ' <span class="text-muted">/ ' . $rm($target) . '</span>'
                                 . ' <span class="tgt-pct ' . ($hit ? 'is-hit' : 'is-miss') . '">' . $pct . '%</span></div>'
                                 . '<div class="tgt-track"><div class="tgt-fill' . ($hit ? ' is-hit' : '') . '" style="width:' . $w . '%;"></div></div>';
                        };
                    ?>
                    <style>
                        .owner-kpi .card.card-custom > .card-header { min-height:56px; padding-top:8px; padding-bottom:8px; display:flex; align-items:center; justify-content:flex-start; }
                        .owner-kpi h3 { font-size:14px; margin:0; color:#3F4254; font-weight:700; text-align:left; align-self:center; }
                        .owner-kpi .kpi-sub { font-size:11px; color:#7E8299; }
                        .owner-kpi .kpi-strip { display:flex; gap:10px; flex-wrap:wrap; }
                        .owner-kpi .kpi-cell { flex:1 1 0; min-width:90px; padding:8px 10px; background:#F7F8FA; border-radius:6px; }
                        .owner-kpi .kpi-cell .lbl { font-size:10px; text-transform:uppercase; letter-spacing:.5px; color:#7E8299; font-weight:600; }
                        .owner-kpi .kpi-cell .val { font-size:18px; font-weight:700; color:#3F4254; line-height:1.2; }
                        .owner-kpi table.owner-team { font-size:13px; margin-bottom:0; white-space:nowrap; }
                        .owner-kpi table.owner-team th { font-size:11px; text-transform:uppercase; color:#7E8299; border-top:none; border-bottom:1px solid #EBEDF3; padding:8px; font-weight:600; }
                        .owner-kpi table.owner-team td { padding:8px; border-top:1px solid #F3F6F9; vertical-align:top; }
                        .owner-kpi table.owner-team tbody tr.team-unassigned td { background:#FBFBFD; color:#5C6473; font-style:italic; }
                        .owner-kpi table.owner-team tfoot td { border-top:2px solid #EBEDF3; font-weight:700; }
                        .owner-kpi .team-amt { font-weight:700; color:#3F4254; }
                        .owner-kpi .okpi-ly { display:block; font-size:10px; color:#7E8299; margin-top:2px; }
                        .owner-kpi .okpi-delta { font-weight:700; padding:0 5px; border-radius:4px; margin-left:2px; }
                        .owner-kpi .okpi-delta.is-up { color:#2F6F4F; background:#E5F3EC; }
                        .owner-kpi .okpi-delta.is-down { color:#C0392B; background:#FBEAEA; }
                        .owner-kpi .okpi-delta.is-flat { color:#5C6473; background:#EDEFF3; }
                        .owner-kpi .reason-bar { height:8px; border-radius:4px; background:#6082B6; }
                        .owner-kpi .tgt-line { font-size:12px; color:#3F4254; white-space:nowrap; }
                        .owner-kpi .tgt-track { height:8px; border-radius:4px; background:#E4E9F2; overflow:hidden; margin-top:4px; }
                        .owner-kpi .tgt-fill { height:100%; border-radius:4px; background:#3699FF; }
                        .owner-kpi .tgt-fill.is-hit { background:#2F6F4F; }
                        .owner-kpi .tgt-pct { font-weight:700; margin-left:4px; }
                        .owner-kpi .tgt-pct.is-hit { color:#2F6F4F; }
                        .owner-kpi .tgt-pct.is-miss { color:#C0392B; }
                    </style>
                    <div class="row pt-7 pl-3 pr-3 mb-5 owner-kpi" style="background-color:#EEF3FB;">

                        <?php // 1. Total sales by team — per period, with same-period-last-year. ?>
                        <div class="col-md-12">
                            <div class="card card-custom gutter-b">
                                <div class="card-header border-0" style="background-color:#B6D0E2;">
                                    <h3>Total Sales by Team</h3>
                                </div>
                                <div class="card-body" style="overflow-x:auto;">
                                    <table class="table table-sm owner-team">
                                        <thead>
                                            <tr>
                                                <th>Team</th>
                                                <?php foreach($period_labels as $lbl) { ?><th class="text-right"><?php echo $lbl; ?></th><?php } ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                                $team_totals = array('yesterday' => 0, 'today' => 0, 'week' => 0, 'month' => 0, 'year' => 0);
                                                $team_totals_ly = $team_totals;
                                                if(empty($owner_team_sales)) { ?>
                                                    <tr><td colspan="6" class="text-center text-muted">No teams found.</td></tr>
                                                <?php } else {
                                                    foreach($owner_team_sales as $tid => $team) { ?>
                                                        <tr<?php echo $tid === 'unassigned' ? ' class="team-unassigned"' : ''; ?>>
                                                            <td class="font-weight-bold"><?php echo htmlspecialchars($team['name']); ?></td>
                                                            <?php foreach(array_keys($period_labels) as $pk) {
                                                                $team_totals[$pk]    += $team['cur'][$pk];
                                                                $team_totals_ly[$pk] += $team['ly'][$pk];
                                                            ?>
                                                                <td class="text-right">
                                                                    <span class="team-amt"><?php echo $rm($team['cur'][$pk]); ?></span>
                                                                    <?php echo $yoy($team['cur'][$pk], $team['ly'][$pk]); ?>
                                                                </td>
                                                            <?php } ?>
                                                        </tr>
                                                    <?php }
                                                } ?>
                                        </tbody>
                                        <?php if(!empty($owner_team_sales)) { ?>
                                        <tfoot>
                                            <tr>
                                                <td>All Teams</td>
                                                <?php foreach(array_keys($period_labels) as $pk) { ?>
                                                    <td class="text-right">
                                                        <span class="team-amt"><?php echo $rm($team_totals[$pk]); ?></span>
                                                        <?php echo $yoy($team_totals[$pk], $team_totals_ly[$pk]); ?>
                                                    </td>
                                                <?php } ?>
                                            </tr>
                                        </tfoot>
                                        <?php } ?>
                                    </table>
                                    <div class="kpi-sub mt-2">Booking-confirmation value (SUM of NetTotal) by the sales agent's team, by booking date, regardless of payment received. &ldquo;Unassigned&rdquo; = agents outside any active team (e.g. owner's own bookings), so the rows reconcile to the company total. Excludes quotation, cancelled and drafts. LY = same period last year.</div>
                                </div>
                            </div>
                        </div>

                        <?php // 1b. Total sales vs target by team — this month & this year. ?>
                        <div class="col-md-12">
                            <div class="card card-custom gutter-b">
                                <div class="card-header border-0" style="background-color:#C3B1E1;">
                                    <h3>Total Sales vs Target by Team</h3>
                                </div>
                                <div class="card-body" style="overflow-x:auto;">
                                    <table class="table table-sm owner-team">
                                        <thead>
                                            <tr>
                                                <th>Team</th>
                                                <th>This Month (<?php echo date('M Y'); ?>)</th>
                                                <th>This Year (<?php echo date('Y'); ?>)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                                $tgt_sum = array('m_a' => 0, 'm_t' => 0, 'y_a' => 0, 'y_t' => 0);
                                                if(empty($owner_team_sales)) { ?>
                                                    <tr><td colspan="3" class="text-center text-muted">No teams found.</td></tr>
                                                <?php } else {
                                                    foreach($owner_team_sales as $tid => $team) {
                                                        $mt = isset($owner_team_targets[$tid]['month']) ? $owner_team_targets[$tid]['month'] : 0;
                                                        $yt = isset($owner_team_targets[$tid]['year'])  ? $owner_team_targets[$tid]['year']  : 0;
                                                        $tgt_sum['m_a'] += $team['cur']['month']; $tgt_sum['m_t'] += $mt;
                                                        $tgt_sum['y_a'] += $team['cur']['year'];  $tgt_sum['y_t'] += $yt;
                                                    ?>
                                                        <tr<?php echo $tid === 'unassigned' ? ' class="team-unassigned"' : ''; ?>>
                                                            <td class="font-weight-bold"><?php echo htmlspecialchars($team['name']); ?></td>
                                                            <td><?php echo $tgt_cell($team['cur']['month'], $mt); ?></td>
                                                            <td><?php echo $tgt_cell($team['cur']['year'], $yt); ?></td>
                                                        </tr>
                                                    <?php }
                                                } ?>
                                        </tbody>
                                        <?php if(!empty($owner_team_sales)) { ?>
                                        <tfoot>
                                            <tr>
                                                <td>All Teams</td>
                                                <td><?php echo $tgt_cell($tgt_sum['m_a'], $tgt_sum['m_t']); ?></td>
                                                <td><?php echo $tgt_cell($tgt_sum['y_a'], $tgt_sum['y_t']); ?></td>
                                            </tr>
                                        </tfoot>
                                        <?php } ?>
                                    </table>
                                    <div class="kpi-sub mt-2">Actual booking-confirmation sales vs target, for the current month and year. A team's target is the sum of its members' sales targets (set in Admin). Green = target met. &ldquo;Unassigned&rdquo; agents have no team target.</div>
                                </div>
                            </div>
                        </div>

                        <?php // 2. Total new leads (GHL). ?>
                        <div class="col-md-6">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#B7E4C7;">
                                    <h3>Total New Leads (GHL)</h3>
                                </div>
                                <div class="card-body">
                                    <div class="kpi-strip">
                                        <?php foreach($period_labels as $pk => $lbl) { ?>
                                            <div class="kpi-cell">
                                                <div class="lbl"><?php echo $lbl; ?></div>
                                                <div class="val"><?php echo number_format((int)$owner_new_leads[$pk]); ?></div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                    <div class="kpi-sub mt-2">New GHL leads company-wide, by the date the conversation started.</div>
                                </div>
                            </div>
                        </div>

                        <?php // 3. Top 5 cancellation reasons (this year). ?>
                        <div class="col-md-6">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#FAC898;">
                                    <h3>Top 5 Cancellation Reasons (<?php echo date('Y'); ?>)</h3>
                                </div>
                                <div class="card-body">
                                    <?php if(empty($owner_cancellation_reasons)) { ?>
                                        <div class="text-muted" style="font-size:13px;">No cancellations this year.</div>
                                    <?php } else {
                                        $reason_total = isset($owner_cancellation_total) ? (int)$owner_cancellation_total : 0;
                                        $booking_total = isset($owner_booking_total) ? (int)$owner_booking_total : 0;
                                        $reason_max = 0;
                                        foreach($owner_cancellation_reasons as $r) { $reason_max = max($reason_max, (int)$r->Total); }
                                        foreach($owner_cancellation_reasons as $r) {
                                            $w = $reason_max > 0 ? round(((int)$r->Total / $reason_max) * 100) : 0;
                                            $pct = $booking_total > 0 ? round(((int)$r->Total / $booking_total) * 100) : 0; ?>
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="flex-grow-1 mr-3" style="min-width:0;">
                                                    <div style="font-size:13px; color:#3F4254; font-weight:600;"><?php echo htmlspecialchars($r->Name); ?></div>
                                                    <div class="reason-bar" style="width:<?php echo $w; ?>%;"></div>
                                                </div>
                                                <div class="text-right" style="min-width:64px;">
                                                    <div style="font-size:18px; font-weight:700; color:#3F4254;"><?php echo $pct; ?>%</div>
                                                    <div style="font-size:12px; color:#7E8299; font-weight:600;"><?php echo (int)$r->Total; ?> bookings</div>
                                                </div>
                                            </div>
                                        <?php }
                                    } ?>
                                    <div class="kpi-sub mt-2">Cancelled booking confirmations grouped by reason, by booking date, this year. % is cancelled bookings for that reason out of all <?php echo isset($owner_booking_total) ? (int)$owner_booking_total : 0; ?> bookings this year (<?php echo isset($owner_cancellation_total) ? (int)$owner_cancellation_total : 0; ?> cancelled total).</div>
                                </div>
                            </div>
                        </div>

                        <?php // 4. Approved payment OUT. ?>
                        <div class="col-md-6">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#FAA0A0;">
                                    <h3>Payment Out &mdash; Approved</h3>
                                </div>
                                <div class="card-body">
                                    <div class="kpi-strip">
                                        <div class="kpi-cell"><div class="lbl">Today</div><div class="val"><?php echo $rm($owner_payment_out['today']); ?></div></div>
                                        <div class="kpi-cell"><div class="lbl">This Week</div><div class="val"><?php echo $rm($owner_payment_out['week']); ?></div></div>
                                        <div class="kpi-cell"><div class="lbl">This Month</div><div class="val"><?php echo $rm($owner_payment_out['month']); ?></div></div>
                                        <div class="kpi-cell"><div class="lbl">This Year</div><div class="val"><?php echo $rm($owner_payment_out['year']); ?></div></div>
                                    </div>
                                    <div class="kpi-sub mt-2">Approved supplier pay-outs, by payment date.</div>
                                </div>
                            </div>
                        </div>

                        <?php // 4b. Unapproved (pending) payment OUT. ?>
                        <div class="col-md-6">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#FFD27F;">
                                    <h3>Payment Out &mdash; Unapproved</h3>
                                </div>
                                <div class="card-body">
                                    <div class="kpi-strip">
                                        <div class="kpi-cell"><div class="lbl">Today</div><div class="val"><?php echo $rm($owner_payment_out_pending['today']); ?></div></div>
                                        <div class="kpi-cell"><div class="lbl">This Week</div><div class="val"><?php echo $rm($owner_payment_out_pending['week']); ?></div></div>
                                        <div class="kpi-cell"><div class="lbl">This Month</div><div class="val"><?php echo $rm($owner_payment_out_pending['month']); ?></div></div>
                                        <div class="kpi-cell"><div class="lbl">This Year</div><div class="val"><?php echo $rm($owner_payment_out_pending['year']); ?></div></div>
                                    </div>
                                    <div class="kpi-sub mt-2">All pending supplier pay-outs due by each date (overdue included), awaiting approval.</div>
                                </div>
                            </div>
                        </div>

                        <?php // 5. Approved payment IN. ?>
                        <div class="col-md-6">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#98FB98;">
                                    <h3>Payment In &mdash; Approved</h3>
                                </div>
                                <div class="card-body">
                                    <div class="kpi-strip">
                                        <div class="kpi-cell"><div class="lbl">Today</div><div class="val"><?php echo $rm($owner_payment_in['today']); ?></div></div>
                                        <div class="kpi-cell"><div class="lbl">This Week</div><div class="val"><?php echo $rm($owner_payment_in['week']); ?></div></div>
                                        <div class="kpi-cell"><div class="lbl">This Month</div><div class="val"><?php echo $rm($owner_payment_in['month']); ?></div></div>
                                        <div class="kpi-cell"><div class="lbl">This Year</div><div class="val"><?php echo $rm($owner_payment_in['year']); ?></div></div>
                                    </div>
                                    <div class="kpi-sub mt-2">Approved customer payments received, by payment date.</div>
                                </div>
                            </div>
                        </div>

                    </div>
                <?php } else { ?>
                    <div class="row pt-7 pl-3 pr-3 mb-5" style="background-color:#98FB9830;">
                        <div class="col-md-4">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#7FFFD430;">
                                    <h3 class="card-title font-weight-bold text-dark" style="font-size:14px;"><?php echo date('j F', strtotime('This Week Monday')) . ' To ' . date('j F', strtotime('This Week Sunday')) . ' Leading SA'; ?></h3>
                                </div>
                                <br>
                                <div class="card-body pt-2">
                                    <?php foreach($weekly_top_sa as $sa) { ?>
                                        <div class="d-flex align-items-center mb-10">
                                            <div class="symbol symbol-40 symbol-light-success mr-5">
                                                <span class="symbol-label">
                                                    <img src="<?php echo $sa->ProfilePicture; ?>" class="h-75 align-self-end">
                                                </span>
                                            </div>
                                            <div class="d-flex flex-column flex-grow-1 font-weight-bold">
                                                <a class="text-dark text-hover-primary mb-1 font-size-xs"><?php echo $sa->Name; ?></a>
                                                <span class="text-muted" style="font-size:11px;"><?php echo $sa->Sales; ?></span>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#7FFFD430;">
                                    <h3 class="card-title font-weight-bold text-dark" style="font-size:14px;"><?php echo date('F') . ' Leading SA'; ?></h3>
                                </div>
                                <br>
                                <div class="card-body pt-2">
                                    <?php foreach($monthly_top_sa as $sa) { ?>
                                        <div class="d-flex align-items-center mb-10">
                                            <div class="symbol symbol-40 symbol-light-success mr-5">
                                                <span class="symbol-label">
                                                    <img src="<?php echo $sa->ProfilePicture; ?>" class="h-75 align-self-end">
                                                </span>
                                            </div>
                                            <div class="d-flex flex-column flex-grow-1 font-weight-bold">
                                                <a class="text-dark text-hover-primary mb-1 font-size-xs"><?php echo $sa->Name; ?></a>
                                                <span class="text-muted" style="font-size:11px;"><?php echo $sa->Sales; ?></span>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-custom card-stretch gutter-b">
                                <div class="card-header border-0" style="background-color:#7FFFD430;">
                                    <h3 class="card-title font-weight-bold text-dark" style="font-size:14px;"><?php echo 'Year ' . date('Y') . ' Leading SA'; ?></h3>
                                </div>
                                <br>
                                <div class="card-body pt-2">
                                    <?php foreach($annual_top_sa as $sa) { ?>
                                        <div class="d-flex align-items-center mb-10">
                                            <div class="symbol symbol-40 symbol-light-success mr-5">
                                                <span class="symbol-label">
                                                    <img src="<?php echo $sa->ProfilePicture; ?>" class="h-75 align-self-end">
                                                </span>
                                            </div>
                                            <div class="d-flex flex-column flex-grow-1 font-weight-bold">
                                                <a class="text-dark text-hover-primary mb-1 font-size-xs"><?php echo $sa->Name; ?></a>
                                                <span class="text-muted" style="font-size:11px;"><?php echo $sa->Sales; ?></span>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row pt-7 pl-3 pr-3 mb-5" style="background-color:#B6D0E230;">
                        <div class="col-md-12">
                            <div class="card card-custom gutter-b">
                                <div class="card-header" style="background-color:#FFFAA030;">
                                    <div class="card-title">
                                        <h3 class="card-label" style="font-size:14px;"><?php echo 'Year ' . date('Y') . ' SA Daily Sales'; ?></h3>
                                    </div>
                                    <div class="card-toolbar">
                                        <select class="form-control form-control-sm" id="sa_daily_filter" multiple="multiple" style="width: 200px;">
                                            <?php foreach($sales_agents as $agent) { ?>
                                                <option value="<?php echo $agent->AdminID; ?>" 
                                                    <?php 
                                                        if(isset($_GET['sa_daily_agents'])) {
                                                            $selected_agents = explode(',', $_GET['sa_daily_agents']);
                                                            if(in_array($agent->AdminID, $selected_agents)) {
                                                                echo 'selected';
                                                            }
                                                        }
                                                    ?>
                                                ><?php echo $agent->Name; ?></option>
                                            <?php } ?>
                                        </select>
                                        <div id="kt_daterangepicker_sa_daily" class="input-icon sa_daily_picker ml-2">
                                            <?php
                                                $start_date_sa_daily = isset($_GET['start_date_sa_daily']) ? date('d/m/Y', strtotime($_GET['start_date_sa_daily'])) : '';
                                                $end_date_sa_daily = isset($_GET['end_date_sa_daily']) ? date('d/m/Y', strtotime($_GET['end_date_sa_daily'])) : '';

                                                $display_sa_daily_date = '';
                                                if ($start_date_sa_daily && $end_date_sa_daily) {
                                                    $display_sa_daily_date = $start_date_sa_daily . ' - ' . $end_date_sa_daily;
                                                }
                                            ?>
                                            <input readonly type="text" id="sa_daily_date" autocomplete="off" class="form-control" value="<?php echo $display_sa_daily_date; ?>">
                                            <span>
                                                <i class="la la-calendar"></i>
                                            </span>
                                        </div>
                                        <span id="reset_sa_daily_filter" class="btn btn-icon btn-warning btn-sm ml-1">
                                            <i class="la la-refresh"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body" style="overflow-x:auto; position:relative;">
                                    <div id="sales_agents_daily_sales" style="min-width:900px;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="card card-custom gutter-b">
                                <div class="card-header" style="background-color:#FFFAA030;">
                                    <div class="card-title">
                                        <h3 class="card-label" style="font-size:14px;"><?php echo 'Year ' . date('Y') . ' Daily Total Sales'; ?></h3>
                                    </div>
                                    <div class="card-toolbar">
                                        <div id="kt_daterangepicker_daily_total" class="input-icon daily_total_picker">
                                            <?php
                                                $start_date_daily_total = isset($_GET['start_date_daily_total']) ? date('d/m/Y', strtotime($_GET['start_date_daily_total'])) : '';
                                                $end_date_daily_total = isset($_GET['end_date_daily_total']) ? date('d/m/Y', strtotime($_GET['end_date_daily_total'])) : '';

                                                $display_daily_total_date = '';
                                                if ($start_date_daily_total && $end_date_daily_total) {
                                                    $display_daily_total_date = $start_date_daily_total . ' - ' . $end_date_daily_total;
                                                }
                                            ?>
                                            <input readonly type="text" id="daily_total_date" autocomplete="off" class="form-control" value="<?php echo $display_daily_total_date; ?>">
                                            <span>
                                                <i class="la la-calendar"></i>
                                            </span>
                                        </div>
                                        <span id="reset_daily_total_date" class="btn btn-icon btn-warning btn-sm ml-1">
                                            <i class="la la-refresh"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body" style="overflow-x:auto; position:relative;">
                                    <div id="daily_sales" style="min-width:900px;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="card card-custom gutter-b">
                                <div class="card-header" style="background-color:#FFFAA030;">
                                    <div class="card-title">
                                        <h3 class="card-label" style="font-size:14px;"><?php echo 'Year ' . date('Y') . ' SA Monthly Sales'; ?></h3>
                                    </div>
                                </div>
                                <div class="card-body" style="overflow-x:auto; position:relative;">
                                    <div id="sales_agents_monthly_sales" style="min-width:900px;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="card card-custom gutter-b">
                                <div class="card-header" style="background-color:#FFFAA030;">
                                    <div class="card-title">
                                        <h3 class="card-label" style="font-size:14px;"><?php echo 'Year ' . date('Y') . ' Monthly Total Sales'; ?></h3>
                                    </div>
                                </div>
                                <div class="card-body" style="overflow-x:auto; position:relative;">
                                    <div id="monthly_sales" style="min-width:900px;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="card card-custom gutter-b">
                                <div class="card-header" style="background-color:#FAA0A030;">
                                    <div class="card-title">
                                        <h3 class="card-label" style="font-size:14px;"><?php echo 'Year ' . date('Y') . ' Monthly Total Cancellation Rates'; ?></h3>
                                    </div>
                                </div>
                                <div class="card-body" style="overflow-x:auto; position:relative;">
                                    <div id="cancellation_rates" style="min-width:900px;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="card card-custom gutter-b">
                                <div class="card-header" style="background-color:#FAC89830;">
                                    <div class="card-title">
                                        <h3 class="card-label" style="font-size:14px;"><?php echo 'Year ' . date('Y') . ' Monthly Total Approved Credit And Debit Payments'; ?></h3>
                                    </div>
                                </div>
                                <div class="card-body" style="overflow-x:auto; position:relative;">
                                    <div id="approved_payments" style="min-width:900px;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="card card-custom gutter-b">
                                <div class="card-header" style="background-color:#FAC89830;">
                                    <div class="card-title">
                                        <h3 class="card-label" style="font-size:14px;"><?php echo 'Monthly Total Pending Credit And Debit Payments - All Generated Years'; ?></h3>
                                    </div>
                                </div>
                                <div class="card-body" style="overflow-x:auto; position:relative;">
                                    <div id="pending_payments" style="min-width:900px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
	</div>
</div>
<script src="<?php echo base_url('assets/js/pages/widgets.js'); ?>"></script>
<script src="<?php echo base_url('assets/js/pages/features/charts/apexcharts.js'); ?>"></script>
<?php // Owner (Level 10) has no ApexCharts/date-pickers on this page — skip the whole chart script so it fires no needless AJAX. ?>
<?php if((int)$this->session->userdata('level') !== 10) { ?>
<script type="text/javascript">
    $(document).ready(function() {
        //SA
        $.ajax({
            url: '<?php echo base_url('Dashboard/Sales_Agent_Daily_Sales'); ?>',
            type: 'post',
            dataType: 'json',
            success: function(array) {
                var options = {
                    series: [{
                        name: 'Sales',
                        data: array.daily_sales
                    }],
                    chart: {
                        type: 'bar',
                        height: 350,
                        toolbar: {
                            show: false
		                }
                    },
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            columnWidth: '15%'
                        },
                    },
                    dataLabels: {
                        enabled: false,
                    },
                    stroke: {
                        show: true,
                        width: 2,
                        colors: ['transparent']
                    },
                    xaxis: {
                        categories: array.current_week,
                    },
                    yaxis: {
                        title: {
                            text: 'Sales (RM)'
                        },
                        labels: {
                            formatter: (value) => { return value.toLocaleString('en-US', {minimumFractionDigits: 2}) },
                        }
                    },
                    fill: {
                        opacity: 1
                    },
                    tooltip: {
                        y: {
                            formatter: function(sales) {
                                return "RM " + sales.toLocaleString('en-US', {minimumFractionDigits: 2})
                            }
                        }
                    },
                    colors: ['#FFDB58']
                };
                var chart = new ApexCharts(document.querySelector("#sales_agent_daily_sales"), options);
                chart.render();
            }
        });

        $.ajax({
            url: '<?php echo base_url('Dashboard/Sales_Agent_Monthly_Sales'); ?>',
            type: 'post',
            dataType: 'json',
            success: function(array) {
                var options = {
                    series: [{
                        name: 'Sales',
                        data: array
                    }],
                    chart: {
                        type: 'bar',
                        height: 350,
                        toolbar: {
                            show: false
		                }
                    },
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            columnWidth: '15%'
                        },
                    },
                    dataLabels: {
                        enabled: false,
                    },
                    stroke: {
                        show: true,
                        width: 2,
                        colors: ['transparent']
                    },
                    xaxis: {
                        categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Total'],
                        labels: {
                            style: {
                                colors: ['#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#FFC000']
                            }
                        }
                    },
                    yaxis: {
                        title: {
                            text: 'Sales (RM)'
                        },
                        labels: {
                            formatter: (value) => { return value.toLocaleString('en-US', {minimumFractionDigits: 2}) },
                        }
                    },
                    fill: {
                        opacity: 1
                    },
                    tooltip: {
                        y: {
                            formatter: function(sales) {
                                return "RM " + sales.toLocaleString('en-US', {minimumFractionDigits: 2})
                            }
                        }
                    },
                    colors: ['#FFDB58']
                };
                var chart = new ApexCharts(document.querySelector("#sales_agent_monthly_sales"), options);
                chart.render();
            }
        });

        $.ajax({
            url: '<?php echo base_url('Dashboard/Sales_Agent_Cancellation_Rates'); ?>',
            type: 'post',
            dataType: 'json',
            success: function(array) {
                var options = {
                    series: [{
                        name: 'Rate',
                        data: array
                    }],
                    chart: {
                        type: 'bar',
                        height: 350,
                        toolbar: {
                            show: false
		                }
                    },
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            columnWidth: '15%'
                        },
                    },
                    dataLabels: {
                        enabled: false,
                    },
                    stroke: {
                        show: true,
                        width: 2,
                        colors: ['transparent']
                    },
                    xaxis: {
                        categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    },
                    yaxis: {
                        title: {
                            text: 'Rate (%)'
                        },
                        labels: {
                            formatter: (value) => { return value + '%' },
                        }
                    },
                    fill: {
                        opacity: 1
                    },
                    tooltip: {
                        y: {
                            formatter: function(rate) {
                                return rate + "%"
                            }
                        }
                    },
                    colors: ['#FAA0A0']
                };
                var chart = new ApexCharts(document.querySelector("#sales_agent_cancellation_rates"), options);
                chart.render();
            }
        });

        //Owner / Finance
        var sales_agents = <?php echo json_encode($sales_agents) ?>;
        var upcoming_travels = <?php echo json_encode($upcoming_travels) ?>;
        var profit_margins_less_than_10_percent = <?php echo json_encode($profit_margins_less_than_10_percent) ?>;
        var overdue_payments = <?php echo json_encode($overdue_payments) ?>;
        var negative_profit_margins = <?php echo json_encode($negative_profit_margins) ?>;
        var pending_travel_vouchers = <?php echo json_encode($pending_travel_vouchers) ?>;
        var pending_reviews = <?php echo json_encode($pending_reviews) ?>;
        var pending_credit_payments = <?php echo json_encode($pending_credit_payments) ?>;
        var pending_debit_payments = <?php echo json_encode($pending_debit_payments) ?>;

        $('#sales_agent').change(function() {
            var selectedAgents = $('#sales_agent').val(); // Returns array when multiple is enabled
            // Convert to strings to ensure type consistency between environments
            if(selectedAgents && selectedAgents.length > 0) {
                selectedAgents = selectedAgents.map(String);
            }
            var count = 0;

            // Collect every AdminID that owns a rendered card, including deactivated
            // agents who are absent from the dropdown but still present in the data.
            var allAgentIds = new Set();
            [upcoming_travels, profit_margins_less_than_10_percent, overdue_payments,
             negative_profit_margins, pending_travel_vouchers, pending_reviews,
             pending_credit_payments, pending_debit_payments].forEach(function(arr) {
                arr.forEach(function(row) { allAgentIds.add(String(row.AdminID)); });
            });

            // Show/hide elements based on selected agents
            allAgentIds.forEach(function(adminId) {
                if(selectedAgents && selectedAgents.length > 0) {
                    if(selectedAgents.includes(adminId)) {
                        $('.' + adminId).removeAttr('style');
                    } else {
                        $('.' + adminId).attr('style', 'display: none !important');
                    }
                } else {
                    $('.' + adminId).removeAttr('style');
                }
            });

            // Update counts based on selected agents
            if(selectedAgents && selectedAgents.length > 0) {
                for(var i = 0; i < upcoming_travels.length; i++) {
                    if(selectedAgents.includes(String(upcoming_travels[i].AdminID))) {
                        count++;
                    }
                }
                $('#upcoming_travels_header').html('<strong>' + count + '</strong>&nbsp;BC(s)');
                count = 0;
                for(var i = 0; i < profit_margins_less_than_10_percent.length; i++) {
                    if(selectedAgents.includes(String(profit_margins_less_than_10_percent[i].AdminID))) {
                        count++;
                    }
                }
                $('#profit_margins_less_than_10_percent_header').html('<strong>' + count + '</strong>&nbsp;BC(s)');
                count = 0;
                for(var i = 0; i < overdue_payments.length; i++) {
                    if(selectedAgents.includes(String(overdue_payments[i].AdminID))) {
                        count++;
                    }
                }
                $('#overdue_payments_header').html('<strong>' + count + '</strong>&nbsp;BC(s)');
                count = 0;
                for(var i = 0; i < negative_profit_margins.length; i++) {
                    if(selectedAgents.includes(String(negative_profit_margins[i].AdminID))) {
                        count++;
                    }
                }
                $('#negative_profit_margins_header').html('<strong>' + count + '</strong>&nbsp;BC(s)');
                count = 0;
                for(var i = 0; i < pending_travel_vouchers.length; i++) {
                    if(selectedAgents.includes(String(pending_travel_vouchers[i].AdminID))) {
                        count++;
                    }
                }
                $('#pending_travel_vouchers_header').html('<strong>' + count + '</strong>&nbsp;BC(s)');
                count = 0;
                for(var i = 0; i < pending_reviews.length; i++) {
                    if(selectedAgents.includes(String(pending_reviews[i].AdminID))) {
                        count++;
                    }
                }
                $('#pending_reviews_header').html('<strong>' + count + '</strong>&nbsp;BC(s)');
                count = 0;
                for(var i = 0; i < pending_credit_payments.length; i++) {
                    if(selectedAgents.includes(String(pending_credit_payments[i].AdminID))) {
                        count++;
                    }
                }
                $('#pending_credit_payments_header').html('<strong>' + count + '</strong>&nbsp;Payment(s)');
                count = 0;
                for(var i = 0; i < pending_debit_payments.length; i++) {
                    if(selectedAgents.includes(String(pending_debit_payments[i].AdminID))) {
                        count++;
                    }
                }
                $('#pending_debit_payments_header').html('<strong>' + count + '</strong>&nbsp;Payment(s)');
            } else {
                $('#upcoming_travels_header').html('<?php echo '<strong>' . count($upcoming_travels) . '</strong>&nbsp;BC(s)'; ?>');
                $('#profit_margins_less_than_10_percent_header').html('<?php echo '<strong>' . count($profit_margins_less_than_10_percent) . '</strong>&nbsp;BC(s)'; ?>');
                $('#overdue_payments_header').html('<?php echo '<strong>' . count($overdue_payments) . '</strong>&nbsp;BC(s)'; ?>');
                $('#negative_profit_margins_header').html('<?php echo '<strong>' . count($negative_profit_margins) . '</strong>&nbsp;BC(s)'; ?>');
                $('#pending_travel_vouchers_header').html('<?php echo '<strong>' . count($pending_travel_vouchers) . '</strong>&nbsp;BC(s)'; ?>');
                $('#pending_reviews_header').html('<?php echo '<strong>' . count($pending_reviews) . '</strong>&nbsp;BC(s)'; ?>');
                $('#pending_credit_payments_header').html('<?php echo '<strong>' . count($pending_credit_payments) . '</strong>&nbsp;Payment(s)'; ?>');
                $('#pending_debit_payments_header').html('<?php echo '<strong>' . count($pending_debit_payments) . '</strong>&nbsp;Payment(s)'; ?>');
            }
        });

        function loadSADailySales() {
            var sa_daily_params = {};
            var start_date_sa_daily = '<?php echo isset($_GET['start_date_sa_daily']) ? $_GET['start_date_sa_daily'] : ''; ?>';
            var end_date_sa_daily = '<?php echo isset($_GET['end_date_sa_daily']) ? $_GET['end_date_sa_daily'] : ''; ?>';
            var sa_daily_agents = '<?php echo isset($_GET['sa_daily_agents']) ? $_GET['sa_daily_agents'] : ''; ?>';
            
            if (start_date_sa_daily) {
                sa_daily_params.start_date = start_date_sa_daily;
            }
            if (end_date_sa_daily) {
                sa_daily_params.end_date = end_date_sa_daily;
            }
            if (sa_daily_agents) {
                sa_daily_params.sales_agents = sa_daily_agents;
            }

            $.ajax({
                url: '<?php echo base_url('Dashboard/Sales_Agents_Daily_Sales'); ?>',
                type: 'post',
                dataType: 'json',
                data: sa_daily_params,
                success: function(array) {
                var series = [];
                for(var i = 0; i < (array.sales_agents).length; i++) {
                    series.push({name: array.sales_agents[i], data: array.daily_sales[array.sales_agents[i]]});
                }
                var options = {
                    series: series,
                    chart: {
                        type: 'bar',
                        height: 350,
                        toolbar: {
                            show: false
		                }
                    },
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            columnWidth: '65%'
                        },
                    },
                    dataLabels: {
                        enabled: false,
                    },
                    stroke: {
                        show: true,
                        width: 2,
                        colors: ['transparent']
                    },
                    xaxis: {
                        categories: array.current_week,
                    },
                    yaxis: {
                        title: {
                            text: 'Sales (RM)'
                        },
                        labels: {
                            formatter: (value) => { return value.toLocaleString('en-US', {minimumFractionDigits: 2}) },
                        }
                    },
                    fill: {
                        opacity: 1
                    },
                    tooltip: {
                        y: {
                            formatter: function(sales) {
                                return "RM " + sales.toLocaleString('en-US', {minimumFractionDigits: 2})
                            }
                        }
                    },
                    colors: ['#F8C8DC', '#C3B1E1', '#A7C7E7', '#FAA0A0', '#FAC898', '#C1E1C1']
                };
                var chart = new ApexCharts(document.querySelector("#sales_agents_daily_sales"), options);
                chart.render();
            }
        });
        }

        loadSADailySales();

        $.ajax({
            url: '<?php echo base_url('Dashboard/Sales_Agents_Monthly_Sales'); ?>',
            type: 'post',
            dataType: 'json',
            success: function(array) {
                var series = [];
                for(var i = 0; i < (array.sales_agents).length; i++) {
                    series.push({name: array.sales_agents[i], data: array.monthly_sales[array.sales_agents[i]]});
                }
                var options = {
                    series: series,
                    chart: {
                        type: 'bar',
                        height: 350,
                        toolbar: {
                            show: false
		                }
                    },
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            columnWidth: '65%'
                        },
                    },
                    dataLabels: {
                        enabled: false,
                    },
                    stroke: {
                        show: true,
                        width: 2,
                        colors: ['transparent']
                    },
                    xaxis: {
                        categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    },
                    yaxis: {
                        title: {
                            text: 'Sales (RM)'
                        },
                        labels: {
                            formatter: (value) => { return value.toLocaleString('en-US', {minimumFractionDigits: 2}) },
                        }
                    },
                    fill: {
                        opacity: 1
                    },
                    tooltip: {
                        y: {
                            formatter: function(sales) {
                                return "RM " + sales.toLocaleString('en-US', {minimumFractionDigits: 2})
                            }
                        }
                    },
                    colors: ['#F8C8DC', '#C3B1E1', '#A7C7E7', '#FAA0A0', '#FAC898', '#C1E1C1']
                };
                var chart = new ApexCharts(document.querySelector("#sales_agents_monthly_sales"), options);
                chart.render();
            }
        });

        function loadDailyTotalSales() {
            var daily_total_params = {};
            var start_date_daily_total = '<?php echo isset($_GET['start_date_daily_total']) ? $_GET['start_date_daily_total'] : ''; ?>';
            var end_date_daily_total = '<?php echo isset($_GET['end_date_daily_total']) ? $_GET['end_date_daily_total'] : ''; ?>';
            
            if (start_date_daily_total) {
                daily_total_params.start_date = start_date_daily_total;
            }
            if (end_date_daily_total) {
                daily_total_params.end_date = end_date_daily_total;
            }

            $.ajax({
                url: '<?php echo base_url('Dashboard/Daily_Sales'); ?>',
                type: 'post',
                dataType: 'json',
                data: daily_total_params,
                success: function(array) {
                var options = {
                    series: [{
                        name: 'Sales',
                        data: array.daily_sales
                    }],
                    chart: {
                        type: 'bar',
                        height: 350,
                        toolbar: {
                            show: false
		                }
                    },
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            columnWidth: '15%'
                        },
                    },
                    dataLabels: {
                        enabled: false,
                    },
                    stroke: {
                        show: true,
                        width: 2,
                        colors: ['transparent']
                    },
                    xaxis: {
                        categories: array.current_week,
                    },
                    yaxis: {
                        title: {
                            text: 'Sales (RM)'
                        },
                        labels: {
                            formatter: (value) => { return value.toLocaleString('en-US', {minimumFractionDigits: 2}) },
                        }
                    },
                    fill: {
                        opacity: 1
                    },
                    tooltip: {
                        y: {
                            formatter: function(sales) {
                                return "RM " + sales.toLocaleString('en-US', {minimumFractionDigits: 2})
                            }
                        }
                    },
                    colors: ['#FFDB58']
                };
                var chart = new ApexCharts(document.querySelector("#daily_sales"), options);
                chart.render();
            }
        });
        }

        loadDailyTotalSales();

        $.ajax({
            url: '<?php echo base_url('Dashboard/Monthly_Sales'); ?>',
            type: 'post',
            dataType: 'json',
            success: function(array) {
                var options = {
                    series: [{
                        name: 'Sales',
                        data: array
                    }],
                    chart: {
                        type: 'bar',
                        height: 350,
                        toolbar: {
                            show: false
		                }
                    },
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            columnWidth: '15%'
                        },
                    },
                    dataLabels: {
                        enabled: false,
                    },
                    stroke: {
                        show: true,
                        width: 2,
                        colors: ['transparent']
                    },
                    xaxis: {
                        categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Total'],
                        labels: {
                            style: {
                                colors: ['#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#FFC000']
                            }
                        }
                    },
                    yaxis: {
                        title: {
                            text: 'Sales (RM)'
                        },
                        labels: {
                            formatter: (value) => { return value.toLocaleString('en-US', {minimumFractionDigits: 2}) },
                        }
                    },
                    fill: {
                        opacity: 1
                    },
                    tooltip: {
                        y: {
                            formatter: function(sales) {
                                return "RM " + sales.toLocaleString('en-US', {minimumFractionDigits: 2})
                            }
                        }
                    },
                    colors: ['#FFDB58']
                };
                var chart = new ApexCharts(document.querySelector("#monthly_sales"), options);
                chart.render();
            }
        });

        $.ajax({
            url: '<?php echo base_url('Dashboard/Approved_Payments'); ?>',
            type: 'post',
            dataType: 'json',
            success: function(array) {
                var series = [];
                for(var i = 1; i <= 2; i++) {
                    if(i == 1) {
                        series.push({name: 'Credit', data: array.credits});
                    } else {
                        series.push({name: 'Debit', data: array.debits});
                    }
                }
                var options = {
                    series: series,
                    chart: {
                        type: 'bar',
                        height: 350,
                        toolbar: {
                            show: false
		                }
                    },
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            columnWidth: '25%'
                        },
                    },
                    dataLabels: {
                        enabled: false,
                    },
                    stroke: {
                        show: true,
                        width: 2,
                        colors: ['transparent']
                    },
                    xaxis: {
                        categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Total'],
                        labels: {
                            style: {
                                colors: ['#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#FFC000']
                            }
                        }
                    },
                    yaxis: {
                        title: {
                            text: 'Payment (RM)'
                        },
                        labels: {
                            formatter: (value) => { return value.toLocaleString('en-US', {minimumFractionDigits: 2}) },
                        }
                    },
                    fill: {
                        opacity: 1
                    },
                    tooltip: {
                        y: {
                            formatter: function(payment) {
                                return "RM " + payment.toLocaleString('en-US', {minimumFractionDigits: 2})
                            }
                        }
                    },
                    colors: ['#C1E1C1', '#FAA0A0']
                };
                var chart = new ApexCharts(document.querySelector("#approved_payments"), options);
                chart.render();
            }
        });

        $.ajax({
            url: '<?php echo base_url('Dashboard/Pending_Payments'); ?>',
            type: 'post',
            dataType: 'json',
            success: function(array) {
                var series = [];
                for(var i = 1; i <= 2; i++) {
                    if(i == 1) {
                        series.push({name: 'Credit', data: array.credits});
                    } else {
                        series.push({name: 'Debit', data: array.debits});
                    }
                }
                var options = {
                    series: series,
                    chart: {
                        type: 'bar',
                        height: 350,
                        toolbar: {
                            show: false
		                }
                    },
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            columnWidth: '25%'
                        },
                    },
                    dataLabels: {
                        enabled: false,
                    },
                    stroke: {
                        show: true,
                        width: 2,
                        colors: ['transparent']
                    },
                    xaxis: {
                        categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Total'],
                        labels: {
                            style: {
                                colors: ['#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#36454F', '#FFC000']
                            }
                        }
                    },
                    yaxis: {
                        title: {
                            text: 'Payment (RM)'
                        },
                        labels: {
                            formatter: (value) => { return value.toLocaleString('en-US', {minimumFractionDigits: 2}) },
                        }
                    },
                    fill: {
                        opacity: 1
                    },
                    tooltip: {
                        y: {
                            formatter: function(payment) {
                                return "RM " + payment.toLocaleString('en-US', {minimumFractionDigits: 2})
                            }
                        }
                    },
                    colors: ['#C1E1C1', '#FAA0A0']
                };
                var chart = new ApexCharts(document.querySelector("#pending_payments"), options);
                chart.render();
            }
        });
        
        $.ajax({
            url: '<?php echo base_url('Dashboard/Cancellation_Rates'); ?>',
            type: 'post',
            dataType: 'json',
            success: function(array) {
                var options = {
                    series: [{
                        name: 'Rate',
                        data: array
                    }],
                    chart: {
                        type: 'bar',
                        height: 350,
                        toolbar: {
                            show: false
		                }
                    },
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            columnWidth: '15%'
                        },
                    },
                    dataLabels: {
                        enabled: false,
                    },
                    stroke: {
                        show: true,
                        width: 2,
                        colors: ['transparent']
                    },
                    xaxis: {
                        categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    },
                    yaxis: {
                        title: {
                            text: 'Rate (%)'
                        },
                        labels: {
                            formatter: (value) => { return value + '%' },
                        }
                    },
                    fill: {
                        opacity: 1
                    },
                    tooltip: {
                        y: {
                            formatter: function(rate) {
                                return rate + "%"
                            }
                        }
                    },
                    colors: ['#FAA0A0']
                };
                var chart = new ApexCharts(document.querySelector("#cancellation_rates"), options);
                chart.render();
            }
        });

        // travel reminder picker
        var start_date_travel_reminder = '';
        var end_date_travel_reminder = '';
        var current_url = window.location.href;

        // get start and end date from current url
        if (current_url.includes('start_date=') && current_url.includes('end_date=')) {
            start_date_travel_reminder = current_url.split('start_date=')[1].split('&')[0];
            end_date_travel_reminder = current_url.split('end_date=')[1].split('&')[0];
        }

        $('#kt_daterangepicker_4.travel_reminder_picker').on('apply.daterangepicker', function(ev, picker) {
            console.log('travel reminder picker change', picker.startDate.format('YYYY-MM-DD'), picker.endDate.format('YYYY-MM-DD'));
            start_date_travel_reminder = picker.startDate.format('YYYY-MM-DD');
            end_date_travel_reminder = picker.endDate.format('YYYY-MM-DD');
            filterTravelReminders();
        });

        // sa picker
        var start_date_sa = '';
        var end_date_sa = '';
        // $('#kt_daterangepicker_4.sa_picker').on('apply.daterangepicker', function(ev, picker) {
        //     console.log('sa change', picker.startDate.format('YYYY-MM-DD'), picker.endDate.format('YYYY-MM-DD'));
        //     start_date_sa = picker.startDate.format('YYYY-MM-DD');
        //     end_date_sa = picker.endDate.format('YYYY-MM-DD');
        // });

        
        $('#reset_travel_reminder_date').click(function() {
            window.location.href = '<?php echo base_url('Dashboard'); ?>';
        });

        // SA Daily Sales picker and filter
        var start_date_sa_daily = '';
        var end_date_sa_daily = '';
        var selected_sa_daily_agents = [];

        // Initialize Select2 for sales agent filter
        $('#sa_daily_filter').select2({
            placeholder: "Select Sales Agents",
            allowClear: true
        });

        // Handle sales agent selection change
        $('#sa_daily_filter').on('change', function() {
            selected_sa_daily_agents = $(this).val() || [];
            filterSADailySales();
        });

        $('#kt_daterangepicker_sa_daily.sa_daily_picker').on('apply.daterangepicker', function(ev, picker) {
            console.log('sa daily picker change', picker.startDate.format('YYYY-MM-DD'), picker.endDate.format('YYYY-MM-DD'));
            start_date_sa_daily = picker.startDate.format('YYYY-MM-DD');
            end_date_sa_daily = picker.endDate.format('YYYY-MM-DD');
            filterSADailySales();
        });

        $('#reset_sa_daily_filter').click(function() {
            var current_url = window.location.href;
            var new_url = current_url.split('?')[0];
            var params = new URLSearchParams(window.location.search);
            params.delete('start_date_sa_daily');
            params.delete('end_date_sa_daily');
            params.delete('sa_daily_agents');
            
            var remaining_params = params.toString();
            if (remaining_params) {
                new_url += '?' + remaining_params;
            }
            window.location.href = new_url;
        });

        // Daily Total Sales picker
        var start_date_daily_total = '';
        var end_date_daily_total = '';

        $('#kt_daterangepicker_daily_total.daily_total_picker').on('apply.daterangepicker', function(ev, picker) {
            console.log('daily total picker change', picker.startDate.format('YYYY-MM-DD'), picker.endDate.format('YYYY-MM-DD'));
            start_date_daily_total = picker.startDate.format('YYYY-MM-DD');
            end_date_daily_total = picker.endDate.format('YYYY-MM-DD');
            filterDailyTotalSales();
        });

        $('#reset_daily_total_date').click(function() {
            var current_url = window.location.href;
            var new_url = current_url.split('?')[0];
            var params = new URLSearchParams(window.location.search);
            params.delete('start_date_daily_total');
            params.delete('end_date_daily_total');
            
            var remaining_params = params.toString();
            if (remaining_params) {
                new_url += '?' + remaining_params;
            }
            window.location.href = new_url;
        });

        // Function to filter SA Daily Sales based on date range and selected agents
        function filterSADailySales() {
            var current_url = window.location.href;
            var base_url = current_url.split('?')[0];
            var params = new URLSearchParams(window.location.search);

            if (start_date_sa_daily) {
                params.set('start_date_sa_daily', start_date_sa_daily);
            } else {
                params.delete('start_date_sa_daily');
            }
            if (end_date_sa_daily) {
                params.set('end_date_sa_daily', end_date_sa_daily);
            } else {
                params.delete('end_date_sa_daily');
            }

            // Get current selected agents if not already set
            if (selected_sa_daily_agents.length === 0) {
                selected_sa_daily_agents = $('#sa_daily_filter').val() || [];
            }

            if (selected_sa_daily_agents.length > 0) {
                params.set('sa_daily_agents', selected_sa_daily_agents.join(','));
            } else {
                params.delete('sa_daily_agents');
            }

            console.log('SA Daily params', params.toString());
            
            // Reload page with parameters
            window.location.href = base_url + (params.toString() ? '?' + params.toString() : '');
        }

        // Function to filter Daily Total Sales based on date range
        function filterDailyTotalSales() {
            var current_url = window.location.href;
            var base_url = current_url.split('?')[0];
            var params = new URLSearchParams(window.location.search);

            if (start_date_daily_total) {
                params.set('start_date_daily_total', start_date_daily_total);
            }
            if (end_date_daily_total) {
                params.set('end_date_daily_total', end_date_daily_total);
            }

            console.log('Daily Total params', params.toString());
            
            // Reload page with date parameters
            window.location.href = base_url + '?' + params.toString();
        }

        // Function to filter travel reminders based on date range
        function filterTravelReminders() {
            var params = '';

            // sa
            var start_date_sa = start_date_sa;
            var end_date_sa = end_date_sa;
            // if (start_date_sa && $('#sa_travel_start_date').length) {
            //     params += '?start_date=' + moment(start_date_sa, 'DD MMM YYYY').format('YYYY-MM-DD');
            // }
            // if (end_date_sa && $('#sa_travel_end_date').length) {
            //     params += (params ? '&' : '?') + 'end_date=' + moment(end_date_sa, 'DD MMM YYYY').format('YYYY-MM-DD');
            // }

            // travel reminder
            var start_date = start_date_travel_reminder;
            var end_date = end_date_travel_reminder;
            if (start_date != '') {
                params += (params ? '&' : '?') + 'start_date=' + start_date;
            }
            if (end_date != '') {
                params += (params ? '&' : '?') + 'end_date=' + end_date;
            }

            console.log('params', params);
            
            // Reload page with date parameters
            if (params) {
                window.location.href = '<?php echo base_url('Dashboard'); ?>' + params;
            } else {
                // If both dates are cleared, reload without parameters
                if (!start_date_sa && !end_date_sa && !start_date && !end_date) {
                    window.location.href = '<?php echo base_url('Dashboard'); ?>';
                }
            }
        }
    });
</script>
<?php } // end old-chart script (skipped for Owner) ?>