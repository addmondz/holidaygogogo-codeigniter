<div class="d-flex flex-column-fluid">
    <div class="container-fluid">
        <div class="card card-custom mb-5">
            <div class="card-header flex-wrap py-3" style="background-color:#D7E2F2;">
                <div class="card-title">
                    <h3 class="card-label" style="color:#6082B6;">
                        <strong><?php if(current_url() == base_url('Payment/Create')) { echo 'New Payment Record'; } else { if($Credit != 0.00) { echo 'Payment Record : Credit RM ' . $Credit; } else { echo 'Payment Record : Debit RM ' . $Debit; } } ?></strong>
                    </h3>
                </div>
                <?php $current_url = base_url($_SERVER['REQUEST_URI']); ?>
            </div>
            <div class="card-body">
                <script>
                    function isSupplierPaymentType(type) {
                        return type == 'SUPPLIER PAYMENT (DEPOSIT)' || type == 'SUPPLIER PAYMENT (FULL)' || type == 'SUPPLIER PAYMENT (ADDITIONAL)';
                    }
                    // Payment-out types addressed to a supplier (show a Supplier selector,
                    // sync to AutoCount against that supplier). Mirrors payment_type_uses_supplier() in PHP.
                    function usesSupplier(type) {
                        return isSupplierPaymentType(type) || type == 'AGENT COMMISSION FROM SUPPLIER';
                    }
                </script>
                <form id="form" action="<?php if(current_url() == base_url('Payment/Create')) { echo base_url('Payment/Create'); } else { echo base_url('Payment/Update?payment_id=') . $this->input->get('payment_id'); } ?>" method="post" enctype="multipart/form-data">
                    <?php if(current_url() == base_url('Payment/Create')) { ?>
                        <div id="booking"></div>
                        <div id="payment" class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Booking
                                        <span style="color:red;">*</span>
                                    </label>
                                    <select name="booking" data-live-search="true" onchange="Select_Booking()" class="form-control selectpicker">
                                        <option selected disabled data-icon="la la-suitcase font-size-lg bs-icon" value="">--SELECT BOOKING--</option>
                                        <?php foreach($bookings as $booking) { ?>
                                            <option data-icon="la la-suitcase font-size-lg bs-icon" value="<?php echo $booking->BookingID; ?>"><?php echo $booking->BookingNumber . ' - ' . $booking->Customer; ?></option> 
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between border-top pt-5"></div>
                        <input type="hidden" name="booking_number">
                        <input type="hidden" name="count">
                        <input type="hidden" name="url">
                        <strong>Payment Information :</strong>
                        <br><br>
                        <div id="benchmark" class="row"></div>
                        <a id="create_payment" class="btn btn-light-success btn-sm mt-2" style="width:180px;">
                            <i class="la la-plus-circle"></i>Insert Payment
                        </a>
                        <br><br id="summary">
                        <div class="row">
                            <div class="col-md-12 pt-3 pb-3" style="background-color:white; border:3px solid #D7E2F2; border-radius:8px;">
                                <div class="row">
                                    <div class="col-md-6 mb-7 mb-md-0">
                                        <label style="color:#2AAA8A;">Total Payment In (RM)</label>
                                        <div class="input-icon">
                                            <input disabled type="text" id="total_credit" value="0.00" class="form-control" style="text-align:right;">
                                            <span>
                                                <i class="la la-dollar"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label style="color:#F88379;">Total Payment Out (RM)</label>
                                        <div class="input-icon">
                                            <input disabled type="text" id="total_debit" value="0.00" class="form-control" style="text-align:right;">
                                            <span>
                                                <i class="la la-dollar"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <br>
                        <div class="d-flex justify-content-between border-top pt-5">
                            <input type="submit" value="Create Payment" class="btn btn-success font-weight-bold px-9 py-4" style="width:180px; margin-left:auto;">
                        </div>

                        <script>
                            var reservation_number = null;
                            var suppliers = <?php echo json_encode($suppliers) ?>;
                            var country_codes = <?php echo json_encode($country_codes) ?>;
                            var array1 = [];
                            for(var i = 0; i < suppliers.length; i++) {
                                array1.push('<option data-icon="la la-user-alt font-size-lg bs-icon" value="'+ suppliers[i].SupplierID +'">'+ suppliers[i].Name +'</option>');
                            }
                            var array2 = [];
                            for(var i = 0; i < country_codes.length; i++) {
                                array2.push('<option data-icon="la la-dollar font-size-lg bs-icon" value="'+ country_codes[i].CountryCodeID +'">'+ country_codes[i].Country + ' - ' + country_codes[i].CurrencyCode +'</option>');
                            }
                            var payment_id = 0;
                            var payment_ids = [];
                            var booking_auto_selected = false;
                            var outstanding_balance = 0;
                            var booking_products = [];
                            var booking_net_total = 0;
                            var existing_total_credit = 0;
                            var existing_total_debit = 0;
                            window.productOptionsHtml = '';

                            <?php if(!empty($this->input->get('booking_number'))) { ?>
                                Select_Booking();
                            <?php } ?>

                            function Select_Booking()
                            {
                                <?php if(!empty($this->input->get('booking_number'))) { ?>
                                    if(booking_auto_selected == false) {
                                        var bookings = <?php echo json_encode($bookings) ?>;
                                        var booking_number = '<?php echo $this->input->get('booking_number') ?>';
                                        var index = bookings.findIndex(value => value.BookingNumber == booking_number);
                                        if(index == -1) {
                                            window.location.href = window.location.href.split('?')[0];
                                        } else {
                                            $('select[name="booking"]').val(bookings[index].BookingID);
                                        }
                                        booking_auto_selected = true;
                                    }
                                <?php } ?>
                                var booking_id = $('select[name="booking"]').val();
                                $.ajax({
                                    url: '<?php echo base_url('Payment/Read') ?>',
                                    type: 'get',
                                    data: {
                                        booking_id: booking_id
                                    },
                                    dataType: 'json',
                                    success: function(array) {
                                        booking_products = array.booking_products || [];
                                        var productOptions = '<option selected disabled value="">--SELECT PRODUCT--</option>';
                                        $.each(booking_products, function(key, bp) {
                                            productOptions += '<option data-icon="la la-box font-size-lg bs-icon" value="' + bp.BookingProductID + '" data-product-id="' + bp.ProductID + '">' + bp.ProductCode + ' - ' + bp.Name + '</option>';
                                        });
                                        window.productOptionsHtml = productOptions;
                                        reservation_number = array.ReservationNumber;
                                        var count = 0;
                                        var credit_payments = [];
                                        var total_credit = 0;
                                        outstanding_balance = parseFloat((array.NetTotal).replace(/[RM,]/g, ''));
                                        var debit_payments = [];
                                        var total_debit = 0;
                                        var customerInTypes = ['DEPOSIT', 'FULL', 'ADDITIONAL PAYMENT'];
                                        if(array.credit_payments.length > 0) {
                                            $.each(array.credit_payments, function(key, value) {
                                                count++;
                                                credit_payments.push('<tr><td>' + count + '</td><td>' + value.Date + '</td><td>' + value.Type + '</td><td style="color:#F64E60;">' + value.Status + '</td><td>Reference Number : ' + value.ReferenceNumber + '<br>Remark : ' + value.PaymentRemark + '</td><td style="color:#2AAA8A; text-align:right;">' + value.Credit + '</td><td>' + value.Debit + '</td></tr>');
                                                total_credit = total_credit + parseFloat((value.Credit).replace(/[RM,]/g, ''));
                                                if(value.Status == '<i class="la la-check-circle text-success"></i>' && customerInTypes.indexOf(value.Type) !== -1) {
                                                    outstanding_balance = outstanding_balance - parseFloat((value.Credit).replace(/[RM,]/g, ''));
                                                }
                                            });
                                        }
                                        if(array.debit_payments.length > 0) {
                                            $.each(array.debit_payments, function(key, value) {
                                                count++;
                                                if(value.Type == 'AGENT COMMISSION FROM SUPPLIER') {
                                                    debit_payments.push('<tr><td>' + count + '</td><td>' + value.Date + '</td><td>' + value.Type + '</td><td style="color:#F64E60;">' + value.Status + '</td><td>Payment Deadline : ' + value.Deadline + '<br>Supplier : ' + value.Supplier + '<br>Reference Number : ' + value.ReferenceNumber + '<br>Remark : ' + value.PaymentRemark + '</td><td>' + value.Credit + '</td><td style="color:#F88379; text-align:right;">' + value.Debit + '</td></tr>');
                                                } else if(isSupplierPaymentType(value.Type)) {
                                                    debit_payments.push('<tr><td>' + count + '</td><td>' + value.Date + '</td><td>' + value.Type + '</td><td style="color:#F64E60;">' + value.Status + '</td><td>Payment Deadline : ' + value.Deadline + '<br>Supplier : ' + value.Supplier + '<br>Quotation Number : ' + value.QuotationNumber + '<br>Invoice Number : ' + value.InvoiceNumber + '<br>Reference Number : ' + value.ReferenceNumber + '<br>Remark : ' + value.PaymentRemark + '</td><td>' + value.Credit + '</td><td style="color:#F88379; text-align:right;">' + value.Debit + '</td></tr>');
                                                } else {
                                                    debit_payments.push('<tr><td>' + count + '</td><td>' + value.Date + '</td><td>' + value.Type + '</td><td style="color:#F64E60;">' + value.Status + '</td><td>Payment Deadline : ' + value.Deadline + '<br>Bank : ' + value.Bank + '<br>Bank Account : ' + value.BankAccount + '<br>Bank Holder : ' + value.BankHolder + '<br>Reference Number : ' + value.ReferenceNumber + '<br>Remark : ' + value.DebitRemark + '</td><td>' + value.Credit + '</td><td style="color:#F88379; text-align:right;">' + value.Debit + '</td></tr>');
                                                }
                                                total_debit = total_debit + parseFloat((value.Debit).replace(/[RM,]/g, ''));
                                                if(value.Type == 'CUSTOMER REFUND' && value.Status == '<i class="la la-check-circle text-success"></i>') {
                                                    outstanding_balance = outstanding_balance + parseFloat((value.Debit).replace(/[RM,]/g, ''));
                                                }
                                            });
                                        }
                                        $('.card-toolbar').remove();
                                        var url = '<?php echo base_url('Booking_Confirmation?token=') ?>';
                                        $('<div class="card-toolbar">' +
                                            '<a href="'+ url + array.Token +'" target="_blank" class="btn btn-light-info font-weight-bold" style="width:180px;">' +
                                                '<i class="la la-suitcase"></i>BC' +
                                            '</a>' +
                                        '</div>').insertAfter('.card-title');
                                        $('.accordion').remove();
                                        $('<div class="accordion accordion-solid accordion-toggle-plus">' +
                                            '<div class="card">' +
                                                '<div class="card-header">' +
                                                    '<div data-toggle="collapse" data-target="#booking_info" class="card-title collapsed" style="font-size:13px;">Booking Information</div>' +
                                                '</div>' +
                                                '<div id="booking_info" class="collapse">' +
                                                    '<div class="card-body">' +
                                                        '<div class="alert alert-custom alert-light-primary fade show">' +
                                                            '<div class="alert-text pt-5 pl-5 pr-5" style="background-color:white;">' +
                                                                '<div class="form-group">' +
                                                                    '<div class="row">' +
                                                                        '<div class="col-md-4 mb-7 mb-md-0">' +
                                                                            '<strong>Reservation Number : </strong>' + array.ReservationNumber +
                                                                        '</div>' +
                                                                        '<div class="col-md-4 mb-7 mb-md-0">' +
                                                                            '<strong>Deposit Deadline : </strong>' + array.DepositDeadline +
                                                                        '</div>' +
                                                                        '<div class="col-md-4 mb-7 mb-md-0">' +
                                                                            '<strong>Full Payment Deadline : </strong>' + array.FullPaymentDeadline +
                                                                        '</div>' +
                                                                    '</div>' +
                                                                    '<div class="row pt-2">' +
                                                                        '<div class="col-md-4 mb-7 mb-md-0">' +
                                                                            '<strong>Additional Payment Deadline : </strong>' + array.AdditionalPaymentDeadline +
                                                                        '</div>' +
                                                                        '<div class="col-md-4 mb-7 mb-md-0">' +
                                                                            '<strong>Travel Date : </strong>' + array.TravelDate +
                                                                        '</div>' +
                                                                        '<div class="col-md-4 mb-7 mb-md-0">' +
                                                                            '<strong>Destination : </strong>' + array.DestinationName +
                                                                        '</div>' +
                                                                    '</div>' +
                                                                    '<div class="row pt-2">' +
                                                                        '<div class="col-md-4 mb-7 mb-md-0">' +
                                                                            '<strong>Sales Agent : </strong>' + array.SalesAgentName +
                                                                        '</div>' +
                                                                        '<div class="col-md-4 mb-7 mb-md-0">' +
                                                                            '<strong>Remark : </strong>' + array.BookingRemark +
                                                                        '</div>' +
                                                                        '<div class="col-md-4">' +
                                                                            '<strong>Status : </strong>' + array.Status +
                                                                        '</div>' +
                                                                    '</div>' +
                                                                '</div>' +
                                                            '</div>' +
                                                        '</div>' +
                                                    '</div>' +
                                                '</div>' +
                                            '</div>' +
                                        '</div><br class="accordion">').insertAfter('#booking');
                                        $('.alert-light-success').remove();
                                        if(credit_payments.length > 0 || debit_payments.length > 0) {
                                            $('<div class="alert alert-custom alert-light-success fade show" style="overflow-x:auto;">' +
                                                '<div class="alert-text p-5" style="background-color:white;">' +
                                                    '<div class="dataTables_wrapper dt-bootstrap4 no-footer">' +
                                                        '<table class="table table-bordered table-head-custom table-checkable dataTable no-footer dtr-inline">' +
                                                            '<thead>' +
                                                                '<tr>' +
                                                                    '<th>No.</th>' +
                                                                    '<th>Transaction Date</th>' +
                                                                    '<th>Payment Type</th>' +
                                                                    '<th>Status</th>' +
                                                                    '<th>Details</th>' +
                                                                    '<th>In (RM)</th>' +
                                                                    '<th>Out (RM)</th>' +
                                                                '</tr>' +
                                                            '</thead>' +
                                                            '<tbody>' + credit_payments.join('') + debit_payments.join('') +
                                                                '<tr>' +
                                                                    '<td colspan="5" style="color:#B5B5C3"><strong>TOTAL</strong></td>' +
                                                                    '<td style="color:#2AAA8A; text-align:right;">' + total_credit.toLocaleString('en-US', {minimumFractionDigits: 2}) + '</td>' +
                                                                    '<td style="color:#F88379; text-align:right;">' + total_debit.toLocaleString('en-US', {minimumFractionDigits: 2}) + '</td>' +
                                                                '</tr>' +
                                                            '</tbody>' +
                                                        '</table>' +
                                                    '</div>' +
                                                '</div>' +
                                            '</div>').insertAfter('#payment');
                                        }
                                        $('.alert-light-warning').remove();
                                        $('<div class="col-md-6 alert alert-custom alert-light-warning fade show ml-auto">' +
                                            '<div class="alert-text pt-5 pl-5 pr-5" style="background-color:white;">' +
                                                '<strong>Booking Sales (RM) : </strong>' + array.NetTotal +
                                                '<br><br>' +
                                                '<strong>Customer Outstanding (RM) : </strong>' + outstanding_balance.toLocaleString('en-US', {minimumFractionDigits: 2}) +
                                                '<br><br>' +
                                            '</div>' +
                                        '</div>').insertAfter('#summary');
                                        $('#total_credit').val(total_credit.toLocaleString('en-US', {minimumFractionDigits: 2}));
                                        $('#total_debit').val(total_debit.toLocaleString('en-US', {minimumFractionDigits: 2}));
                                        booking_net_total = parseFloat((array.NetTotal).replace(/[RM,]/g, '')) || 0;
                                        existing_total_credit = total_credit;
                                        existing_total_debit = total_debit;
                                    }
                                });
                                for(var i = 0; i < payment_ids.length; i++) {
                                    $(`#reservation_number-${payment_ids[i]}`).html('Reservation Number : ' + reservation_number);
                                }
                            }
                            
                            <?php if(!empty($this->input->get('booking_id'))) { ?>
                                $('select[name="booking"]').val('<?php echo $this->input->get('booking_id'); ?>').change();
                            <?php } ?>

                            $('#create_payment').click(function() {
                                if($('select[name="booking"]').val() == null) {
                                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Select Booking', null);
                                } else {
                                    payment_id++;
                                    $('#benchmark').append('<div id="'+ payment_id +'" class="col-md-12 pt-3 pb-3 mb-5" style="background-color:white; border:3px solid #D7E2F2; border-radius:8px;">' +
                                        '<div class="row">' +
                                            '<div class="col-md-6">' +
                                                '<strong id="reservation_number-'+ payment_id +'" style="color:#6F8FAF;">Reservation Number : ' + reservation_number + '</strong>' +
                                            '</div>' +
                                            '<div class="col-md-6">' +
                                               '<a onclick="Delete_Payment('+ payment_id +')" class="btn btn-icon btn-light-danger" style="float:right;">' +
                                                    '<i class="la la-times"></i>' +
                                                '</a>' +
                                            '</div>' +
                                        '</div>' +
                                        '<br>' +
                                        '<div class="form-group">' +
                                            '<div class="row">' +
                                                '<div class="col-md-6">' +
                                                    '<label>Transaction Type <span style="color:red;">*</span></label>' +
                                                    '<select id="transaction_type-'+ payment_id +'" onchange="Transaction_Type('+ payment_id +')" class="form-control selectpicker">' +
                                                        '<option selected disabled data-icon="la la-dollar font-size-lg bs-icon" value="">--SELECT TRANSACTION TYPE--</option>' +
                                                        '<?php foreach(unserialize(TRANSACTION_TYPE) as $key => $value) { ?>' +
                                                            '<option data-icon="<?php if($key == 'PAYMENT IN') { echo 'la la-receipt'; } else { echo 'la la-file-invoice-dollar'; } ?> font-size-lg bs-icon" value="<?php echo $key; ?>"><?php echo $value; ?></option>' +
                                                        '<?php } ?>' +
                                                    '</select>' +
                                                '</div>' +
                                            '</div>' +
                                            '<br><div id="benchmark-'+ payment_id +'"></div>' +
                                        '</div>' +
                                    '</div>');
                                    $(`#transaction_type-${payment_id}`).selectpicker();
                                    payment_ids.push(payment_id);
                                }
                            });

                            function Transaction_Type(payment_id) {
                                $(`#payment_in-${payment_id}`).remove();
                                $(`#payment_out-${payment_id}`).remove();
                                $(`#debit-${payment_id}`).remove();
                                var transaction_type = $(`#transaction_type-${payment_id}`).val();
                                var arrows;
                                arrows = {
                                    leftArrow: '<i class="la la-angle-left"></i>',
                                    rightArrow: '<i class="la la-angle-right"></i>'
                                }
                                if(transaction_type == 'PAYMENT IN') {
                                    $('<div id="payment_in-'+ payment_id +'" class="p-3" style="background-color:#9FE2BF30;">' +
                                        '<div class="row">' +
                                            '<div class="col-md-6 mb-7 mb-md-0">' +
                                                '<label>Transaction Date <span style="color:red;">*</span></label>' +
                                                '<div class="input-icon">' +
                                                    '<input readonly type="text" name="transaction_date-'+ payment_id +'" id="kt_datepicker_4_3" autocomplete="off" class="form-control">' +
                                                    '<span>' +
                                                        '<i class="la la-calendar"></i>' +
                                                    '</span>' +
                                                '</div>' +
                                            '</div>' +
                                            '<div class="col-md-6">' +
                                                '<label>Payment Type <span style="color:red;">*</span></label>' +
                                                '<select name="credit_type-'+ payment_id +'" class="form-control selectpicker">' +
                                                    '<option selected disabled data-icon="la la-dollar font-size-lg bs-icon" value="">--SELECT PAYMENT TYPE--</option>' +
                                                    '<?php foreach(unserialize(PAYMENT_TYPE) as $key => $value) { ?>' +
                                                        '<?php if($key == 'SUPPLIER PAYMENT (DEPOSIT)' || $key == 'SUPPLIER PAYMENT (FULL)' || $key == 'SUPPLIER PAYMENT (ADDITIONAL)' || $key == 'CUSTOMER REFUND' || $key == 'ONE-TIME PAYMENT' || $key == 'AGENT COMMISSION' || $key == 'AGENT COMMISSION FROM SUPPLIER' || $key == 'BANK CHARGES' || $key == 'CREDIT CARD CHARGES') { continue; } ?>' +
                                                        '<option data-icon="la la-dollar font-size-lg bs-icon" value="<?php echo $key; ?>"><?php echo $value; ?></option>' +
                                                    '<?php } ?>' +
                                                '</select>' +
                                            '</div>' +
                                        '</div>' +
                                        '<br>' +
                                        '<div class="row">' +
                                            '<div class="col-md-6 mb-7 mb-md-0">' +
                                                '<label>Amount (RM) <span style="color:red;">*</span></label>' +
                                                '<div class="input-icon">' +
                                                    // '<input type="text" name="credit-'+ payment_id +'" autocomplete="off" onchange="Validate_Amount('+ '/Credit/' + ',' + payment_id +')" class="form-control" style="text-align:right;">' +
                                                    '<input type="text" name="credit-'+ payment_id +'" autocomplete="off" onchange="Validate_Amount(`credit`, ' + payment_id + ')" class="form-control" style="text-align:right;">' +
                                                    '<span>' +
                                                        '<i class="la la-dollar"></i>' +
                                                    '</span>' +
                                                '</div>' +
                                            '</div>' +
                                            '<div class="col-md-6">' +
                                                '<label>Payment Remark</label>' +
                                                '<div class="input-icon">' +
                                                    '<input type="text" name="payment_remark-'+ payment_id +'" autocomplete="off" class="form-control">' +
                                                    '<span>' +
                                                        '<i class="la la-pencil-alt"></i>' +
                                                    '</span>' +
                                                '</div>' +
                                            '</div>' +
                                        '</div>' +
                                        '<br>' +
                                        '<div class="row">' +
                                            '<div class="col-md-6 mb-7 mb-md-0">' +
                                                '<label>Reference Number</label>' +
                                                '<div class="input-icon">' +
                                                    '<input type="text" name="reference_number-'+ payment_id +'" autocomplete="off" class="form-control">' +
                                                    '<span>' +
                                                        '<i class="la la-clipboard-list"></i>' +
                                                    '</span>' +
                                                '</div>' +
                                            '</div>' +
                                            '<div class="col-md-6">' +
                                                '<label>Bank Slip</label>' +
                                                '<div class="custom-file">' +
                                                    // '<input type="file" name="bank_slip-'+ payment_id +'" onchange="Update_File_Label('+ '/BankSlip/' + ',' + payment_id +')" class="custom-file-input">' +
                                                    '<input type="file" name="bank_slip-'+ payment_id +'" onchange="Update_File_Label(`bank_slip`, ' + payment_id + ')" class="custom-file-input">' +
                                                    '<label id="bank_slip-'+ payment_id +'" class="custom-file-label" style="font-size:13px;"></label>' +
                                                '</div>' +
                                            '</div>' +
                                        '</div>' +
                                    '</div>').insertBefore('#benchmark-' + payment_id);
                                    $(`input[name="transaction_date-${payment_id}"]`).datepicker({
                                        orientation: 'bottom left',
                                        todayHighlight: true,
                                        templates: arrows,
                                        format: 'dd/mm/yyyy',
                                        autoclose: true
                                    });
                                    $(`select[name="credit_type-${payment_id}"]`).selectpicker().on('changed.bs.select', function() {
                                        Calculate_Subtotal();
                                    });
                                } else {
                                    $('<div id="payment_out-'+ payment_id +'" class="p-3" style="background-color:#FAA0A030;">' +
                                        '<div class="row">' +
                                            '<div class="col-md-6 mb-7 mb-md-0">' +
                                                '<label>Payment Type <span style="color:red;">*</span></label>' +
                                                '<select name="debit_type-'+ payment_id +'" onchange="Debit('+ payment_id +')" class="form-control selectpicker">' +
                                                    '<option selected disabled data-icon="la la-dollar font-size-lg bs-icon" value="">--SELECT PAYMENT TYPE--</option>' +
                                                    '<?php foreach(unserialize(PAYMENT_TYPE) as $key => $value) { ?>' +
                                                        '<?php if($key == 'SUPPLIER REFUND' || $key == 'DEPOSIT' || $key == 'FULL' || $key == 'ADDITIONAL PAYMENT') { continue; } ?>' +
                                                        '<option data-icon="la la-dollar font-size-lg bs-icon" value="<?php echo $key; ?>"><?php echo $value; ?></option>' +
                                                    '<?php } ?>' +
                                                '</select>' +
                                            '</div>' +
                                            '<div class="col-md-6">' +
                                                '<label>Amount (RM)</label>' +
                                                '<div class="input-icon">' +
                                                    // '<input type="text" name="debit-'+ payment_id +'" autocomplete="off" onchange="Validate_Amount('+ '/Debit/' + ',' + payment_id +')" class="form-control" style="text-align:right;">' +
                                                    '<input type="text" name="debit-'+ payment_id +'" autocomplete="off" onchange="Validate_Amount(`debit`, ' + payment_id + ')" class="form-control" style="text-align:right;">' +
                                                    '<span>' +
                                                        '<i class="la la-dollar"></i>' +
                                                    '</span>' +
                                                '</div>' +
                                            '</div>' +
                                        '</div>' +
                                        '<br>' +
                                        '<div class="row">' +
                                            '<div class="col-md-6 mb-7 mb-md-0">' +
                                                '<label>Currency Code</label>' +
                                                '<select name="currency_code-'+ payment_id +'" data-live-search="true" class="form-control selectpicker">' + 
                                                    '<option selected data-icon="la la-dollar font-size-lg bs-icon" value="">--SELECT CURRENCY CODE--</option>' + array2 + 
                                                '</select>' +
                                            '</div>' +
                                            '<div class="col-md-6">' +
                                                '<label>Foreign Currency</label>' +
                                                '<div class="input-icon">' +
                                                    '<input type="text" name="foreign_currency-'+ payment_id +'" autocomplete="off" onchange="Validate_Foreign_Currency('+ payment_id +')" class="form-control" style="text-align:right;">' +
                                                    '<span>' +
                                                        '<i class="la la-dollar"></i>' +
                                                    '</span>' +
                                                '</div>' +
                                            '</div>' +
                                        '</div>' +
                                        '<br>' +
                                        '<div class="row">' +
                                            '<div class="col-md-6 mb-7 mb-md-0">' +
                                                '<label>Payment Deadline <span style="color:red;">*</span></label>' +
                                                '<div class="input-icon">' +
                                                    '<input readonly type="text" name="payment_deadline-'+ payment_id +'" id="kt_datepicker_4_4" autocomplete="off" class="form-control">' +
                                                    '<span>' +
                                                        '<i class="la la-calendar"></i>' +
                                                    '</span>' +
                                                '</div>' +
                                            '</div>' +
                                            '<div class="col-md-6">' +
                                                '<label>Reference Number</label>' +
                                                '<div class="input-icon">' +
                                                    '<input type="text" name="reference_number-'+ payment_id +'" autocomplete="off" class="form-control">' +
                                                    '<span>' +
                                                        '<i class="la la-clipboard-list"></i>' +
                                                    '</span>' +
                                                '</div>' +
                                            '</div>' +
                                        '</div>' +
                                        '<br>' +
                                        '<div class="row">' +
                                            '<div class="col-md-6">' +
                                                '<label>Payment Remark</label>' +
                                                '<div class="input-icon">' +
                                                    '<input type="text" name="payment_remark-'+ payment_id +'" autocomplete="off" class="form-control">' +
                                                    '<span>' +
                                                        '<i class="la la-pencil-alt"></i>' +
                                                    '</span>' +
                                                '</div>' +
                                            '</div>' +
                                        '</div>' +
                                    '</div>').insertBefore('#benchmark-' + payment_id);
                                    $(`select[name="debit_type-${payment_id}"]`).selectpicker();
                                    $(`select[name="currency_code-${payment_id}"]`).selectpicker();
                                    $(`input[name="payment_deadline-${payment_id}"]`).datepicker({
                                        orientation: 'bottom left',
                                        todayHighlight: true,
                                        templates: arrows,
                                        format: 'dd/mm/yyyy',
                                        autoclose: true,
                                        startDate: new Date()
                                    });
                                }
                                Calculate_Subtotal();
                            }

                            function Debit(payment_id) {
                                $(`#debit-${payment_id}`).remove();
                                var payment_type = $(`select[name="debit_type-${payment_id}"]`).val();
                                if(isSupplierPaymentType(payment_type)) {
                                    $('<div id="debit-'+ payment_id +'" class="p-3" style="background-color:#DE316310;">' +
                                        '<div class="row">' +
                                            '<div class="col-md-12">' +
                                                '<label>Supplier <span style="color:red;">*</span></label>' +
                                                '<select name="supplier-'+ payment_id +'" data-live-search="true" class="form-control selectpicker">' + 
                                                    '<option selected disabled data-icon="la la-user-alt font-size-lg bs-icon" value="">--SELECT SUPPLIER--</option>' + array1 + 
                                                '</select>' +
                                            '</div>' +
                                        '</div>' +
                                        '<br>' +
                                        '<div class="row">' +
                                            '<div class="col-md-6 mb-7 mb-md-0">' +
                                                '<label>Quotation Number</label>' +
                                                '<div class="input-icon">' +
                                                    '<input type="text" name="quotation_number-'+ payment_id +'" autocomplete="off" class="form-control">' +
                                                    '<span>' +
                                                        '<i class="la la-clipboard-list"></i>' +
                                                    '</span>' +
                                                '</div>' +
                                            '</div>' +
                                            '<div class="col-md-6">' +
                                                '<label>Quotation</label>' +
                                                '<div class="custom-file">' +
                                                    // '<input type="file" name="quotation-'+ payment_id +'" onchange="Update_File_Label('+ '/Quotation/' + ',' + payment_id +')" class="custom-file-input">' +
                                                    '<input type="file" name="quotation-'+ payment_id +'" onchange="Update_File_Label(`Quotation`, ' + payment_id + ')" class="custom-file-input">' +
                                                    '<label id="quotation-'+ payment_id +'" class="custom-file-label" style="font-size:13px;"></label>' +
                                                '</div>' +
                                            '</div>' +
                                        '</div>' +
                                        '<br>' +
                                        '<div class="row">' +
                                            '<div class="col-md-6 mb-7 mb-md-0">' +
                                                '<label>Invoice Number</label>' +
                                                '<div class="input-icon">' +
                                                    '<input type="text" name="invoice_number-'+ payment_id +'" autocomplete="off" class="form-control">' +
                                                    '<span>' +
                                                        '<i class="la la-clipboard-list"></i>' +
                                                    '</span>' +
                                                '</div>' +
                                            '</div>' +
                                            '<div class="col-md-6">' +
                                                '<label>Invoice</label>' +
                                                '<div class="custom-file">' +
                                                    // '<input type="file" name="invoice-'+ payment_id +'" onchange="Update_File_Label('+ '/Invoice/' + ',' + payment_id +')" class="custom-file-input">' +
                                                    '<input type="file" name="invoice-'+ payment_id +'" onchange="Update_File_Label(`Invoice`, ' + payment_id + ')" class="custom-file-input">' +
                                                    '<label id="invoice-'+ payment_id +'" class="custom-file-label" style="font-size:13px;"></label>' +
                                                '</div>' +
                                            '</div>' +
                                        '</div>' +
                                        '<br>' +
                                        '<div class="row">' +
                                            '<div class="col-md-12">' +
                                                '<label>Product</label>' +
                                                '<select name="booking_product-'+ payment_id +'" data-live-search="true" onchange="Select_Product('+ payment_id +')" class="form-control selectpicker">' +
                                                    window.productOptionsHtml +
                                                '</select>' +
                                            '</div>' +
                                        '</div>' +
                                        '<div id="checklist-container-'+ payment_id +'" class="mt-3"></div>' +
                                    '</div>').insertBefore('#benchmark-' + payment_id);
                                    $(`select[name="supplier-${payment_id}"]`).selectpicker();
                                    $(`select[name="booking_product-${payment_id}"]`).selectpicker();
                                } else if(payment_type == 'AGENT COMMISSION FROM SUPPLIER') {
                                    $('<div id="debit-'+ payment_id +'" class="p-3" style="background-color:#DE316310;">' +
                                        '<div class="row">' +
                                            '<div class="col-md-12">' +
                                                '<label>Supplier <span style="color:red;">*</span></label>' +
                                                '<select name="supplier-'+ payment_id +'" data-live-search="true" class="form-control selectpicker">' +
                                                    '<option selected disabled data-icon="la la-user-alt font-size-lg bs-icon" value="">--SELECT SUPPLIER--</option>' + array1 +
                                                '</select>' +
                                            '</div>' +
                                        '</div>' +
                                    '</div>').insertBefore('#benchmark-' + payment_id);
                                    $(`select[name="supplier-${payment_id}"]`).selectpicker();
                                } else {
                                    $('<div id="debit-'+ payment_id +'" class="p-3" style="background-color:#D7004010;">' +
                                        '<div class="row">' +
                                            '<div class="col-md-6 mb-7 mb-md-0">' +
                                                '<label>Bank <span style="color:red;">*</span></label>' +
                                                '<div class="input-icon">' +
                                                    '<input type="text" name="bank-'+ payment_id +'" autocomplete="off" class="form-control">' +
                                                    '<span>' +
                                                        '<i class="la la-landmark"></i>' +
                                                    '</span>' +
                                                '</div>' +
                                            '</div>' +
                                            '<div class="col-md-6">' +
                                                '<label>Bank Account <span style="color:red;">*</span></label>' +
                                                '<div class="input-icon">' +
                                                    '<input type="text" name="bank_account-'+ payment_id +'" autocomplete="off" class="form-control">' +
                                                    '<span>' +
                                                        '<i class="la la-clipboard-list"></i>' +
                                                    '</span>' +
                                                '</div>' +
                                            '</div>' +
                                        '</div>' +
                                        '<br>' +
                                        '<div class="row">' +
                                            '<div class="col-md-6 mb-7 mb-md-0">' +
                                                '<label>Bank Holder <span style="color:red;">*</span></label>' +
                                                '<div class="input-icon">' +
                                                    '<input type="text" name="bank_holder-'+ payment_id +'" autocomplete="off" class="form-control">' +
                                                    '<span>' +
                                                        '<i class="la la-user"></i>' +
                                                    '</span>' +
                                                '</div>' +
                                            '</div>' +
                                            '<div class="col-md-6">' +
                                                '<label>Remark</label>' +
                                                '<div class="input-icon">' +
                                                    '<input type="text" name="remark-'+ payment_id +'" autocomplete="off" class="form-control">' +
                                                    '<span>' +
                                                        '<i class="la la-pencil-alt"></i>' +
                                                    '</span>' +
                                                '</div>' +
                                            '</div>' +
                                        '</div>' +
                                    '</div>').insertBefore('#benchmark-' + payment_id);
                                }
                            }

                            function Select_Product(payment_id) {
                                var booking_id = $('select[name="booking"]').val();
                                var $select = $(`select[name="booking_product-${payment_id}"]`);
                                var product_id = $select.find(':selected').data('product-id');
                                var container = $(`#checklist-container-${payment_id}`);
                                if(!product_id) {
                                    container.html('');
                                    return;
                                }
                                $.ajax({
                                    url: '<?php echo base_url("Payment/Get_Product_Checklist") ?>',
                                    type: 'get',
                                    data: { booking_id: booking_id, product_id: product_id },
                                    dataType: 'json',
                                    success: function(res) {
                                        if(!res.success) return;
                                        var html = '<div class="p-3" style="background-color:#f0f0f0; border-radius:5px;">';
                                        html += '<h6 class="font-weight-bold mb-3">Package Checklist</h6>';
                                        $.each(res.checklists, function(i, cl) {
                                            var checked = res.completions[cl.ID] ? 'checked' : '';
                                            var info = '';
                                            if(res.completions[cl.ID]) {
                                                info = '<small class="text-muted ml-2">' + res.completions[cl.ID].created_by_name + ' - ' + res.completions[cl.ID].created_at + '</small>';
                                            }
                                            html += '<div class="checkbox-inline mb-2"><label class="checkbox"><input type="checkbox" name="checklist_item" value="' + cl.ID + '" ' + checked + '><span></span> ' + cl.name + '</label>' + info + '</div>';
                                        });
                                        html += '<button type="button" onclick="Save_Payment_Checklist(' + payment_id + ')" class="btn btn-sm btn-primary mt-3">Save Checklist</button>';
                                        html += '</div>';
                                        container.html(html);
                                    }
                                });
                            }

                            function Save_Payment_Checklist(payment_id) {
                                var booking_id = $('select[name="booking"]').val();
                                var $select = $(`select[name="booking_product-${payment_id}"]`);
                                var product_id = $select.find(':selected').data('product-id');
                                var completions = [];
                                $(`#checklist-container-${payment_id} input[name="checklist_item"]:checked`).each(function() {
                                    completions.push($(this).val());
                                });
                                $.ajax({
                                    url: '<?php echo base_url("Payment/Save_Checklist") ?>',
                                    type: 'post',
                                    data: { booking_id: booking_id, product_id: product_id, completions: completions },
                                    dataType: 'json',
                                    success: function(res) {
                                        if(res.success) {
                                            toastr.success('Checklist saved successfully');
                                            Select_Product(payment_id);
                                        } else {
                                            toastr.error(res.message || 'Failed to save checklist');
                                        }
                                    }
                                });
                            }

                            function Delete_Payment(payment_id)
                            {
                                payment_ids = payment_ids.filter(function(value) {
                                    return value != payment_id;
                                });
                                $(`#${payment_id}`).remove();
                                Calculate_Subtotal();
                            }

                            function Calculate_Subtotal()
                            {
                                var total_credit = 0;
                                var total_debit = 0;
                                if(payment_ids.length > 0) {
                                    for(var i = 0; i < payment_ids.length; i++) {
                                        if($(`#transaction_type-${payment_ids[i]}`).val() == 'PAYMENT IN') {
                                            if($(`input[name="credit-${payment_ids[i]}"]`).val() != '') {
                                                total_credit += parseFloat(($(`input[name="credit-${payment_ids[i]}"]`).val()).replace(/,/g, ''));
                                            } else {
                                                total_credit += 0.00;
                                            }
                                        } else {
                                            if($(`input[name="debit-${payment_ids[i]}"]`).val() != '') {
                                                total_debit += parseFloat(($(`input[name="debit-${payment_ids[i]}"]`).val()).replace(/,/g, ''));
                                            } else {
                                                total_debit += 0.00;
                                            }
                                        }
                                    }
                                }
                                $('#total_credit').val(total_credit.toLocaleString('en-US', {minimumFractionDigits: 2}));
                                $('#total_debit').val(total_debit.toLocaleString('en-US', {minimumFractionDigits: 2}));
                            }

                            function Update_File_Label(value, payment_id)
                            {
                                if(value == '/BankSlip/') {
                                    var file = $(`input[name="bank_slip-${payment_id}"]`).val();
                                } else {
                                    if(value == '/Quotation/') {
                                        var file = $(`input[name="quotation-${payment_id}"]`).val();
                                    } else {
                                        var file = $(`input[name="invoice-${payment_id}"]`).val();
                                    }
                                }
                                if(file != '') {
                                    if(value == '/BankSlip/') {
                                        $(`#bank_slip-${payment_id}`).html(file);
                                    } else {
                                        if(value == '/Quotation/') {
                                            $(`#quotation-${payment_id}`).html(file);
                                        } else {
                                            $(`#invoice-${payment_id}`).html(file);
                                        }
                                    }
                                } else {
                                    if(value == '/BankSlip/') {
                                        $(`#bank_slip-${payment_id}`).html('');
                                    } else {
                                        if(value == '/Quotation/') {
                                            $(`#quotation-${payment_id}`).html('');
                                        } else {
                                            $(`#invoice-${payment_id}`).html('');
                                        }
                                    }
                                }
                            }

                            function Has_New_Payment_Out() {
                                for(var i = 0; i < payment_ids.length; i++) {
                                    if($(`#transaction_type-${payment_ids[i]}`).val() == 'PAYMENT OUT') {
                                        return true;
                                    }
                                }
                                return false;
                            }

                            function Projected_Margin_Pct() {
                                if(!booking_net_total || booking_net_total <= 0) return null;
                                var new_credit = 0, new_debit = 0;
                                for(var i = 0; i < payment_ids.length; i++) {
                                    var type = $(`#transaction_type-${payment_ids[i]}`).val();
                                    if(type == 'PAYMENT IN') {
                                        var v = $(`input[name="credit-${payment_ids[i]}"]`).val();
                                        if(v) new_credit += parseFloat(v.replace(/,/g, '')) || 0;
                                    } else if(type == 'PAYMENT OUT') {
                                        var v = $(`input[name="debit-${payment_ids[i]}"]`).val();
                                        if(v) new_debit += parseFloat(v.replace(/,/g, '')) || 0;
                                    }
                                }
                                var projected_profit = (existing_total_credit + new_credit) - (existing_total_debit + new_debit);
                                return (projected_profit / booking_net_total) * 100;
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
                                    title: 'Create New Payment Record ?',
                                    confirmButtonText: 'Confirm',
                                    cancelButtonText: 'Cancel',
                                    showCancelButton: true
                                }).then((action) => {
                                    if(action.isConfirmed) {
                                        var full_payment = 0;
                                        if($('select[name="booking"]').val() == null) {
                                            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Select Booking', null);
                                        } else {
                                            if(payment_ids.length == 0) {
                                                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert Payment', null);
                                            } else {
                                                for(var i = 0; i < payment_ids.length; i++) {
                                                    if($(`select[name="credit_type-${payment_ids[i]}"]`).val() == 'FULL') {
                                                        full_payment++;
                                                    }
                                                    if($(`#transaction_type-${payment_ids[i]}`).val() == 'PAYMENT IN') {
                                                        if($(`input[name="transaction_date-${payment_ids[i]}"]`).val() == '' || $(`select[name="credit_type-${payment_ids[i]}"]`).val() == null || $(`input[name="credit-${payment_ids[i]}"]`).val() == '') {
                                                            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert All Required Payment In Details', null);
                                                            return;
                                                        }
                                                    } else {
                                                        if($(`#transaction_type-${payment_ids[i]}`).val() == 'PAYMENT OUT') {
                                                            if((usesSupplier($(`select[name="debit_type-${payment_ids[i]}"]`).val()) && $(`select[name="supplier-${payment_ids[i]}"]`).val() == null) || $(`select[name="debit_type-${payment_ids[i]}"]`).val() == null || $(`input[name="payment_deadline-${payment_ids[i]}"]`).val() == '' || (!usesSupplier($(`select[name="debit_type-${payment_ids[i]}"]`).val()) && ($(`select[name="bank-${payment_ids[i]}"]`).val() == '' || $(`input[name="bank_account-${payment_ids[i]}"]`).val() == '' || $(`input[name="bank_holder-${payment_ids[i]}"]`).val() == ''))) {
                                                                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert All Required Payment Out Details', null);
                                                                return;
                                                            } else {
                                                                if(($(`input[name="debit-${payment_ids[i]}"]`).val() == '' && ($(`select[name="currency_code-${payment_ids[i]}"]`).val() == '' && $(`input[name="foreign_currency-${payment_ids[i]}"]`).val() == ''))) {
                                                                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert Foreign Currency Or Payment Out Amount', null);
                                                                    return;
                                                                } else {
                                                                    if($(`select[name="currency_code-${payment_ids[i]}"]`).val() == '' && $(`input[name="foreign_currency-${payment_ids[i]}"]`).val() != '') {
                                                                        Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Select Currency Code', null);
                                                                        return;
                                                                    } else {
                                                                        if($(`select[name="currency_code-${payment_ids[i]}"]`).val() != '' && $(`input[name="foreign_currency-${payment_ids[i]}"]`).val() == '') {
                                                                            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert Foreign Currency Amount', null);
                                                                            return;
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        } else {
                                                            if($(`#transaction_type-${payment_ids[i]}`).val() == null) {
                                                                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Select Transaction Type', null);
                                                                return;
                                                            }
                                                        }
                                                    }
                                                }
                                                var full_payment_existed = false;
                                                $.ajax({
                                                    url: '<?php echo base_url('Payment/Detect') ?>',
                                                    type: 'post',
                                                    data: {
                                                        payment_id: null,
                                                        booking_id: $('select[name="booking"]').val()
                                                    },
                                                    dataType: 'json',
                                                    async: false,
                                                    success: function(redundant_full_payment) {
                                                        if(redundant_full_payment) {
                                                            full_payment_existed = true;
                                                        }
                                                    }
                                                });
                                                if(full_payment > 1) {
                                                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Redundant Full Payment Detected', null);
                                                } else {
                                                    if(full_payment == 1 && full_payment_existed) {
                                                        Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Full Payment Existed', null);
                                                    } else {
                                                        $('input[name="booking_number"]').val((($('select[name="booking"] option:selected').text()).split(' - '))[0]);
                                                        $('input[name="count"]').val(payment_id);
                                                        <?php if(!empty($this->input->get('supplier')) || !empty($this->input->get('transaction_date')) || !empty($this->input->get('payment_type')) || !empty($this->input->get('transaction_type')) || !empty($this->input->get('reference_number')) || !empty($this->input->get('payment_deadline')) || !empty($this->input->get('quotation_number')) || !empty($this->input->get('invoice_number')) || !empty($this->input->get('bank')) || !empty($this->input->get('bank_account')) || !empty($this->input->get('bank_holder')) || !empty($this->input->get('status')) || !empty($this->input->get('booking_number')) || !empty($this->input->get('customer')) || !empty($this->input->get('travel_date')) || !empty($this->input->get('sales_agent'))) { ?>
                                                            $('input[name="url"]').val('<?php echo base_url('Payment?') . (explode('?', $current_url))[1]; ?>');
                                                        <?php } else { ?>
                                                            var base_url = '<?php echo base_url('Payment') ?>';
                                                            $('input[name="url"]').val(base_url + '?booking_number=' + $('input[name="booking_number"]').val());
                                                        <?php } ?>
                                                        var doSubmit = function() { $('#form').submit(); };
                                                        if(Has_New_Payment_Out()) {
                                                            var margin = Projected_Margin_Pct();
                                                            if(margin !== null && margin < 5) {
                                                                swalWithBootstrapButtons.fire({
                                                                    width: 550,
                                                                    background: 'url(<?php echo base_url('assets/image/sweetalert.jpg') ?>)',
                                                                    icon: 'warning',
                                                                    title: 'The net profit is less than 5%, continue?',
                                                                    html: 'Projected margin: <strong>' + margin.toFixed(2) + '%</strong>',
                                                                    confirmButtonText: 'Continue',
                                                                    cancelButtonText: 'Cancel',
                                                                    showCancelButton: true
                                                                }).then(function(action) {
                                                                    if(action.isConfirmed) doSubmit();
                                                                });
                                                            } else {
                                                                doSubmit();
                                                            }
                                                        } else {
                                                            doSubmit();
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                });
                            });
                        </script>
                    <?php } else { ?>
                        <input type="hidden" name="url">
                        <?php if($Status == 'R') { ?>
                            <div class="alert alert-custom alert-light-danger fade show">
                                <div class="alert-icon">
                                    <i class="la la-times-circle"></i>
                                </div>
                                <div class="alert-text">Payment Rejected <?php if(!empty($Remark)) { echo ': ' . $Remark; } ?></div>
                            </div>
                            <br>
                        <?php } ?>
                        <strong>Payment Information :</strong>
                        <br><br>
                        <div class="alert alert-custom alert-light-primary fade show">
                            <div class="alert-text">
                                <div class="row">
                                    <div class="col-md-4 mb-7 mb-md-0"><?php echo '<strong>Customer : </strong>' . $Customer; ?></div>
                                    <div class="col-md-4 mb-7 mb-md-0"><?php echo '<strong>Booking Number : </strong>' . $BookingNumber; ?></div>
                                    <div class="col-md-4"><?php echo '<strong>Reservation Number : </strong>' . $ReservationNumber; ?></div>
                                </div>
                            </div>
                        </div>
                        <br>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Transaction Date
                                        <?php if($Credit != 0.00) { ?><span style="color:red;">*</span><?php } ?>
                                    </label>
                                    <div class="input-icon">
                                        <input <?php if(current_url() == base_url('Payment/View')) { echo 'disabled'; } else { echo 'readonly'; } ?> type="text" name="transaction_date" id="kt_datepicker_6" value="<?php echo $Date; ?>" autocomplete="off" class="form-control">
                                        <span>
                                            <i class="la la-calendar"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Payment Type</label>
                                    <select <?php if(current_url() == base_url('Payment/View')) { echo 'disabled'; } ?> name="payment_type" class="form-control selectpicker">
                                        <?php foreach(unserialize(PAYMENT_TYPE) as $key => $value) {
                                            if($Credit != 0.00 && ($key == 'SUPPLIER PAYMENT (DEPOSIT)' || $key == 'SUPPLIER PAYMENT (FULL)' || $key == 'SUPPLIER PAYMENT (ADDITIONAL)' || $key == 'CUSTOMER REFUND' || $key == 'ONE-TIME PAYMENT' || $key == 'AGENT COMMISSION' || $key == 'AGENT COMMISSION FROM SUPPLIER' || $key == 'BANK CHARGES' || $key == 'CREDIT CARD CHARGES')) { continue; }
                                            if($Credit == 0.00 && ($key == 'SUPPLIER REFUND' || $key == 'DEPOSIT' || $key == 'FULL' || $key == 'ADDITIONAL PAYMENT')) { continue; } ?>
                                            <option <?php if($key == $Type) { echo 'selected'; } ?> data-icon="la la-dollar font-size-lg bs-icon" value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <?php if($Credit != 0.00) { ?>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Credit (RM)
                                            <span style="color:red;">*</span>
                                        </label>
                                        <div class="input-icon">
                                            <input <?php if(current_url() == base_url('Payment/View')) { echo 'disabled'; } ?> type="text" name="<?php echo 'credit-' . $this->input->get('payment_id'); ?>" value="<?php echo $Credit; ?>" autocomplete="off" onchange="Validate_Amount('/Credit/', <?php echo $this->input->get('payment_id'); ?>)" class="form-control">
                                            <span>
                                                <i class="la la-dollar"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Reference Number</label>
                                    <div class="input-icon">
                                        <input <?php if(current_url() == base_url('Payment/View')) { echo 'disabled'; } ?> type="text" name="reference_number" value="<?php echo $ReferenceNumber; ?>" autocomplete="off" class="form-control">
                                        <span>
                                            <i class="la la-clipboard-list"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Bank Slip</label>
                                    <div class="custom-file mb-2">
                                        <input <?php if(current_url() == base_url('Payment/View')) { echo 'disabled'; } ?> type="file" name="bank_slip" class="custom-file-input">
                                        <label class="custom-file-label" style="font-size:13px;"></label>
                                    </div>
                                    <?php if(!empty($BankSlip)) { ?>
                                        <a href="<?php echo $BankSlip; ?>" target="_blank" class="btn btn-primary font-weight-bold" style="width:110px;">
                                            <i class="la la-file-alt"></i>Bank Slip
                                        </a>
                                    <?php } ?>
                                </div>
                            </div>
                            <?php if($Credit == 0.00) { ?>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Payment Deadline
                                            <span style="color:red;">*</span>
                                        </label>
                                        <div class="input-icon">
                                            <input <?php if(current_url() == base_url('Payment/View')) { echo 'disabled'; } else { echo 'readonly'; } ?> type="text" name="payment_deadline" id="kt_datepicker_4_4" value="<?php echo $Deadline; ?>" autocomplete="off" class="form-control">
                                            <span>
                                                <i class="la la-calendar"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Debit (RM)</label>
                                        <div class="input-icon">
                                            <input <?php if(current_url() == base_url('Payment/View')) { echo 'disabled'; } ?> type="text" name="<?php echo 'debit-' . $this->input->get('payment_id'); ?>" value="<?php echo $Debit; ?>" autocomplete="off" onchange="Validate_Amount('/Debit/', <?php echo $this->input->get('payment_id'); ?>)" class="form-control">
                                            <span>
                                                <i class="la la-dollar"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Currency Code</label>
                                        <select <?php if(current_url() == base_url('Payment/View')) { echo 'disabled'; } ?> name="currency_code" data-live-search="true" class="form-control selectpicker">
                                            <option selected data-icon="la la-dollar font-size-lg bs-icon" value="">--SELECT CURRENCY CODE--</option>
                                            <?php foreach($country_codes as $country_code) { ?>
                                                <option <?php if($country_code->CountryCodeID == $Currency) { echo 'selected'; } ?> data-icon="la la-dollar font-size-lg bs-icon" value="<?php echo $country_code->CountryCodeID; ?>"><?php echo $country_code->Country . ' - ' . $country_code->CurrencyCode; ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Foreign Currency</label>
                                        <div class="input-icon">
                                            <input <?php if(current_url() == base_url('Payment/View')) { echo 'disabled'; } ?> type="text" name="<?php echo 'foreign_currency-' . $this->input->get('payment_id'); ?>" value="<?php echo $ForeignCurrency; ?>" autocomplete="off" onchange="Validate_Foreign_Currency(<?php echo $this->input->get('payment_id'); ?>)" class="form-control">
                                            <span>
                                                <i class="la la-dollar"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="supplier_field col-md-6" <?php if($Type != 'SUPPLIER PAYMENT (DEPOSIT)' && $Type != 'SUPPLIER PAYMENT (FULL)' && $Type != 'SUPPLIER PAYMENT (ADDITIONAL)' && $Type != 'AGENT COMMISSION FROM SUPPLIER') { echo 'style="display:none;"'; } ?>>
                                    <div class="form-group">
                                        <label>Supplier
                                            <span style="color:red;">*</span>
                                        </label>
                                        <select <?php if(current_url() == base_url('Payment/View')) { echo 'disabled'; } ?> name="supplier" data-live-search="true" class="form-control selectpicker">
                                            <option selected data-icon="la la-user-alt font-size-lg bs-icon" value="">--SELECT SUPPLIER--</option>
                                            <?php foreach($suppliers as $supplier) { ?>
                                                <option <?php if($supplier->SupplierID == $SupplierID) { echo 'selected'; } ?> data-icon="la la-user-alt font-size-lg bs-icon" value="<?php echo $supplier->SupplierID; ?>"><?php echo $supplier->Name; ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="supplier_payment col-md-6" <?php if($Type != 'SUPPLIER PAYMENT (DEPOSIT)' && $Type != 'SUPPLIER PAYMENT (FULL)' && $Type != 'SUPPLIER PAYMENT (ADDITIONAL)') { echo 'style="display:none;"'; } ?>>
                                    <div class="form-group">
                                        <label>Quotation Number</label>
                                        <div class="input-icon">
                                            <input <?php if(current_url() == base_url('Payment/View')) { echo 'disabled'; } ?> type="text" name="quotation_number" value="<?php echo $QuotationNumber; ?>" autocomplete="off" class="form-control">
                                            <span>
                                                <i class="la la-clipboard-list"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="supplier_payment col-md-6" <?php if($Type != 'SUPPLIER PAYMENT (DEPOSIT)' && $Type != 'SUPPLIER PAYMENT (FULL)' && $Type != 'SUPPLIER PAYMENT (ADDITIONAL)') { echo 'style="display:none;"'; } ?>>
                                    <div class="form-group">
                                        <label>Quotation</label>
                                        <div class="custom-file mb-2">
                                            <input <?php if(current_url() == base_url('Payment/View')) { echo 'disabled'; } ?> type="file" name="quotation" class="custom-file-input">
                                            <label class="custom-file-label" style="font-size:13px;"></label>
                                        </div>
                                        <?php if(!empty($Quotation)) { ?>
                                            <a href="<?php echo $Quotation; ?>" target="_blank" class="btn btn-primary font-weight-bold" style="width:110px;">
                                                <i class="la la-file-invoice"></i>Quotation
                                            </a>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="supplier_payment col-md-6" <?php if($Type != 'SUPPLIER PAYMENT (DEPOSIT)' && $Type != 'SUPPLIER PAYMENT (FULL)' && $Type != 'SUPPLIER PAYMENT (ADDITIONAL)') { echo 'style="display:none;"'; } ?>>
                                    <div class="form-group">
                                        <label>Invoice Number</label>
                                        <div class="input-icon">
                                            <input <?php if(current_url() == base_url('Payment/View')) { echo 'disabled'; } ?> type="text" name="invoice_number" value="<?php echo $InvoiceNumber; ?>" autocomplete="off" class="form-control">
                                            <span>
                                                <i class="la la-clipboard-list"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="supplier_payment col-md-6" <?php if($Type != 'SUPPLIER PAYMENT (DEPOSIT)' && $Type != 'SUPPLIER PAYMENT (FULL)' && $Type != 'SUPPLIER PAYMENT (ADDITIONAL)') { echo 'style="display:none;"'; } ?>>
                                    <div class="form-group">
                                        <label>Invoice</label>
                                        <div class="custom-file mb-2">
                                            <input <?php if(current_url() == base_url('Payment/View')) { echo 'disabled'; } ?> type="file" name="invoice" class="custom-file-input">
                                            <label class="custom-file-label" style="font-size:13px;"></label>
                                        </div>
                                        <?php if(!empty($Invoice)) { ?>
                                            <a href="<?php echo $Invoice; ?>" target="_blank" class="btn btn-primary font-weight-bold" style="width:110px;">
                                                <i class="la la-file-invoice-dollar"></i>Invoice
                                            </a>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="supplier_payment col-md-6" <?php if($Type != 'SUPPLIER PAYMENT (DEPOSIT)' && $Type != 'SUPPLIER PAYMENT (FULL)' && $Type != 'SUPPLIER PAYMENT (ADDITIONAL)') { echo 'style="display:none;"'; } ?>>
                                    <div class="form-group">
                                        <label>Product</label>
                                        <select <?php if(current_url() == base_url('Payment/View')) { echo 'disabled'; } ?> name="booking_product" data-live-search="true" onchange="Select_Product_Update()" class="form-control selectpicker">
                                            <option selected data-icon="la la-box font-size-lg bs-icon" value="">--SELECT PRODUCT--</option>
                                            <?php if(isset($booking_products)) { foreach($booking_products as $bp) { ?>
                                                <option <?php if(isset($BookingProductID) && $bp->BookingProductID == $BookingProductID) { echo 'selected'; } ?> data-icon="la la-box font-size-lg bs-icon" value="<?php echo $bp->BookingProductID; ?>" data-product-id="<?php echo $bp->ProductID; ?>"><?php echo $bp->ProductCode . ' - ' . $bp->Name; ?></option>
                                            <?php } } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="supplier_payment col-md-12" <?php if($Type != 'SUPPLIER PAYMENT (DEPOSIT)' && $Type != 'SUPPLIER PAYMENT (FULL)' && $Type != 'SUPPLIER PAYMENT (ADDITIONAL)') { echo 'style="display:none;"'; } ?>>
                                    <div id="checklist-container-update"></div>
                                </div>
                                <div class="customer_refund_one_time_payment col-md-6" <?php if($Type != 'CUSTOMER REFUND' && $Type != 'ONE-TIME PAYMENT' && $Type != 'AGENT COMMISSION' && $Type != 'BANK CHARGES') { echo 'style="display:none;"'; } ?>>
                                    <div class="form-group">
                                        <label>Bank
                                            <span style="color:red;">*</span>
                                        </label>
                                        <div class="input-icon">
                                            <input <?php if(current_url() == base_url('Payment/View')) { echo 'disabled'; } ?> type="text" name="bank" value="<?php echo $Bank; ?>" autocomplete="off" class="form-control">
                                            <span>
                                                <i class="la la-landmark"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="customer_refund_one_time_payment col-md-6" <?php if($Type != 'CUSTOMER REFUND' && $Type != 'ONE-TIME PAYMENT' && $Type != 'AGENT COMMISSION' && $Type != 'BANK CHARGES') { echo 'style="display:none;"'; } ?>>
                                    <div class="form-group">
                                        <label>Bank Account
                                            <span style="color:red;">*</span>
                                        </label>
                                        <div class="input-icon">
                                            <input <?php if(current_url() == base_url('Payment/View')) { echo 'disabled'; } ?> type="text" name="bank_account" value="<?php echo $BankAccount; ?>" autocomplete="off" class="form-control">
                                            <span>
                                                <i class="la la-clipboard-list"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="customer_refund_one_time_payment col-md-6" <?php if($Type != 'CUSTOMER REFUND' && $Type != 'ONE-TIME PAYMENT' && $Type != 'AGENT COMMISSION' && $Type != 'BANK CHARGES') { echo 'style="display:none;"'; } ?>>
                                    <div class="form-group">
                                        <label>Bank Holder
                                            <span style="color:red;">*</span>
                                        </label>
                                        <div class="input-icon">
                                            <input <?php if(current_url() == base_url('Payment/View')) { echo 'disabled'; } ?> type="text" name="bank_holder" value="<?php echo $BankHolder; ?>" autocomplete="off" class="form-control">
                                            <span>
                                                <i class="la la-user"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="customer_refund_one_time_payment col-md-6" <?php if($Type != 'CUSTOMER REFUND' && $Type != 'ONE-TIME PAYMENT' && $Type != 'AGENT COMMISSION' && $Type != 'BANK CHARGES') { echo 'style="display:none;"'; } ?>>
                                    <div class="form-group">
                                        <label>Remark</label>
                                        <div class="input-icon">
                                            <input <?php if(current_url() == base_url('Payment/View')) { echo 'disabled'; } ?> type="text" name="remark" value="<?php echo $DebitRemark; ?>" autocomplete="off" class="form-control">
                                            <span>
                                                <i class="la la-pencil-alt"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Payment Remark</label>
                                    <div class="input-icon">
                                        <input <?php if(current_url() == base_url('Payment/View')) { echo 'disabled'; } ?> type="text" name="payment_remark" value="<?php echo $PaymentRemark; ?>" autocomplete="off" class="form-control">
                                        <span>
                                            <i class="la la-pencil-alt"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select <?php if(current_url() == base_url('Payment/View')) { echo 'disabled'; } ?> name="status" class="form-control selectpicker">
                                        <?php foreach(unserialize(PAYMENT_STATUS) as $key => $value) { ?>
                                            <option <?php if($key == $Status) { echo 'selected'; } ?> data-icon="<?php if($key == 'Y') { echo 'la la-check-circle'; } else if($key == 'P') { echo 'la la-exclamation-circle'; } else { echo 'la la-times-circle'; } ?> font-size-lg bs-icon" value="<?php echo $key; ?>"><?php if($key == 'Y') { echo 'APPROVE'; } else if($key == 'P') { echo 'PENDING'; } else { echo 'REJECT'; } ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div id="rejection_reason" class="form-group">
                                    <label>Rejection Reason</label>
                                    <div class="input-icon">
                                        <input <?php if(current_url() == base_url('Payment/View')) { echo 'disabled'; } ?> type="text" name="rejection_reason" value="<?php echo $Remark; ?>" autocomplete="off" class="form-control">
                                        <span>
                                            <i class="la la-pencil-alt"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php if(current_url() == base_url('Payment/Update')) { ?> 
                            <div class="d-flex justify-content-between border-top pt-5">
                                <div class="card-toolbar ml-auto">
                                    <input type="submit" value="Update Payment" class="btn btn-success font-weight-bold px-9 py-4" style="width:180px;">
                                </div>
                            </div>
                        <?php } ?>

                        <script>
                            <?php if($Status != 'R') { ?>
                                $('#rejection_reason').hide();
                            <?php } ?>

                            $('input[name="transaction_date"]').change(function() {
                                var transaction_date = $('input[name="transaction_date"]').val();
                                if(transaction_date == '') {
                                    $('select[name="status"]').val('<?php echo $Status; ?>').change();
                                }
                            });

                            <?php if($Credit == 0.00) { ?>
                                $('input[name="reference_number"]').change(function() {
                                    var reference_number = $('input[name="reference_number"]').val();
                                    if(reference_number == '') {
                                        $('select[name="status"]').val('<?php echo $Status; ?>').change();
                                    }
                                });
                            <?php } ?>

                            $('select[name="payment_type"]').change(function() {
                                var payment_type = $('select[name="payment_type"]').val();
                                if(payment_type == 'FULL') {
                                    var payment_id = <?php echo $this->input->get('payment_id') ?>;
                                    var booking_id = <?php echo $BookingID ?>;
                                    $.ajax({
                                        url: '<?php echo base_url('Payment/Detect') ?>',
                                        type: 'post',
                                        data: {
                                            payment_id: payment_id,
                                            booking_id: booking_id
                                        },
                                        dataType: 'json',
                                        success: function(redundant_full_payment) {
                                            if(redundant_full_payment) {
                                                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Redundant Full Payment Detected', null);
                                                $('select[name="payment_type"]').val('DEPOSIT').change();
                                            }
                                        }
                                    });
                                } else {
                                    $('select[name="supplier"]').val('').change();
                                    $('input[name="quotation_number"]').val('');
                                    $('input[name="quotation"]').val('');
                                    $('input[name="invoice_number"]').val('');
                                    $('input[name="invoice"]').val('');
                                    $('.custom-file-label').html('');
                                    $('input[name="bank"]').val('');
                                    $('input[name="bank_account"]').val('');
                                    $('input[name="bank_holder"]').val('');
                                    $('input[name="remark"]').val('');
                                    if(isSupplierPaymentType(payment_type)) {
                                        $('.supplier_field').show();
                                        $('.supplier_payment').show();
                                        $('.customer_refund_one_time_payment').hide();
                                    } else if(payment_type == 'AGENT COMMISSION FROM SUPPLIER') {
                                        $('.supplier_field').show();
                                        $('.supplier_payment').hide();
                                        $('.customer_refund_one_time_payment').hide();
                                    } else {
                                        $('.supplier_field').hide();
                                        $('.supplier_payment').hide();
                                        if(payment_type == 'CUSTOMER REFUND' || payment_type == 'ONE-TIME PAYMENT' || payment_type == 'AGENT COMMISSION' || payment_type == 'BANK CHARGES') {
                                            $('.customer_refund_one_time_payment').show();
                                        }
                                    }
                                }
                            });

                            $('select[name="status"]').change(function() {
                                var credit = <?php echo str_replace(',', '', $Credit) ?>;
                                var transaction_date = $('input[name="transaction_date"]').val();
                                var reference_number = $('input[name="reference_number"]').val();
                                var status = $('select[name="status"]').val();
                                if((transaction_date == '' || (credit == 0.00 && reference_number == '')) && status == 'Y') {
                                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Could Not Approve Payment Without Transaction Date And Reference Number', null);
                                    $('select[name="status"]').val('<?php echo $Status; ?>').change();
                                }
                                $('input[name="rejection_reason"]').val('');
                                if(status == 'R') {
                                    $('#rejection_reason').show();
                                } else {
                                    $('#rejection_reason').hide();
                                }
                            });

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
                                    title: <?php if($Credit != 0.00) { ?> '<?php echo 'Update Payment Record : Credit RM ' . $Credit . ' ?'; ?>' <?php } else { ?> '<?php echo 'Update Payment Record : Debit RM ' . $Debit . ' ?'; ?>' <?php } ?>,
                                    confirmButtonText: 'Confirm',
                                    cancelButtonText: 'Cancel',
                                    showCancelButton: true
                                }).then((action) => {
                                    if(action.isConfirmed) {
                                        var credit = <?php echo str_replace(',', '', $Credit) ?>;
                                        if((credit != 0.00 && $('input[name="transaction_date"]').val() == '') || $('input[name="credit-'+ <?php echo $this->input->get('payment_id'); ?> +'"]').val() == '' || $('input[name="payment_deadline"]').val() == '' || (usesSupplier($('select[name="payment_type"]').val()) && $('select[name="supplier"]').val() == '') || (($('select[name="payment_type"]').val() == 'CUSTOMER REFUND' || $('select[name="payment_type"]').val() == 'ONE-TIME PAYMENT' || $('select[name="payment_type"]').val() == 'AGENT COMMISSION' || $('select[name="payment_type"]').val() == 'BANK CHARGES') && $('input[name="bank"]').val() == '') || (($('select[name="payment_type"]').val() == 'CUSTOMER REFUND' || $('select[name="payment_type"]').val() == 'ONE-TIME PAYMENT' || $('select[name="payment_type"]').val() == 'AGENT COMMISSION' || $('select[name="payment_type"]').val() == 'BANK CHARGES') && $('input[name="bank_account"]').val() == '') || (($('select[name="payment_type"]').val() == 'CUSTOMER REFUND' || $('select[name="payment_type"]').val() == 'ONE-TIME PAYMENT' || $('select[name="payment_type"]').val() == 'AGENT COMMISSION' || $('select[name="payment_type"]').val() == 'BANK CHARGES') && $('input[name="bank_holder"]').val() == '')) {
                                            Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert All Required Payment Information', null);
                                        } else {
                                            if(($('input[name="debit-'+ <?php echo $this->input->get('payment_id'); ?> +'"]').val() == '' && ($('select[name="currency_code"]').val() == '' && $('input[name="foreign_currency-'+ <?php echo $this->input->get('payment_id'); ?> +'"]').val() == ''))) {
                                                Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert Foreign Currency Or Payment Out Amount', null);
                                            } else {
                                                if($('select[name="currency_code"]').val() == '' && $('input[name="foreign_currency-'+ <?php echo $this->input->get('payment_id'); ?> +'"]').val() != '') {
                                                    Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Select Currency Code', null);
                                                } else {
                                                    if($('select[name="currency_code"]').val() != '' && $('input[name="foreign_currency-'+ <?php echo $this->input->get('payment_id'); ?> +'"]').val() == '') {
                                                        Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Please Insert Foreign Currency Amount', null);
                                                    } else {
                                                        <?php if(!empty($this->input->get('supplier')) || !empty($this->input->get('transaction_date')) || !empty($this->input->get('payment_type')) || !empty($this->input->get('transaction_type')) || !empty($this->input->get('reference_number')) || !empty($this->input->get('payment_deadline')) || !empty($this->input->get('quotation_number')) || !empty($this->input->get('invoice_number')) || !empty($this->input->get('bank')) || !empty($this->input->get('bank_account')) || !empty($this->input->get('bank_holder')) || !empty($this->input->get('status')) || !empty($this->input->get('booking_number')) || !empty($this->input->get('customer')) || !empty($this->input->get('travel_date')) || !empty($this->input->get('sales_agent'))) { ?>
                                                            $('input[name="url"]').val('<?php echo base_url('Payment?') . (explode('?payment_id=' . $this->input->get('payment_id') . '&', $current_url))[1]; ?>');
                                                        <?php } else { ?>
                                                            $('input[name="url"]').val('<?php echo base_url('Payment?booking_number=' . $BookingNumber) ?>');
                                                        <?php } ?>
                                                        $('#form').submit();
                                                    }
                                                }
                                            }
                                        }
                                    }
                                });
                            });
                        </script>
                        <script>
                            function Select_Product_Update() {
                                var booking_id = <?php echo $BookingID ?>;
                                var $select = $('select[name="booking_product"]');
                                var product_id = $select.find(':selected').data('product-id');
                                var container = $('#checklist-container-update');
                                if(!product_id) {
                                    container.html('');
                                    return;
                                }
                                $.ajax({
                                    url: '<?php echo base_url("Payment/Get_Product_Checklist") ?>',
                                    type: 'get',
                                    data: { booking_id: booking_id, product_id: product_id },
                                    dataType: 'json',
                                    success: function(res) {
                                        if(!res.success) return;
                                        var isView = <?php echo current_url() == base_url('Payment/View') ? 'true' : 'false'; ?>;
                                        var html = '<div class="p-3" style="background-color:#f0f0f0; border-radius:5px;">';
                                        html += '<h6 class="font-weight-bold mb-3">Package Checklist</h6>';
                                        $.each(res.checklists, function(i, cl) {
                                            var checked = res.completions[cl.ID] ? 'checked' : '';
                                            var disabled = isView ? 'disabled' : '';
                                            var info = '';
                                            if(res.completions[cl.ID]) {
                                                info = '<small class="text-muted ml-2">' + res.completions[cl.ID].created_by_name + ' - ' + res.completions[cl.ID].created_at + '</small>';
                                            }
                                            html += '<div class="checkbox-inline mb-2"><label class="checkbox"><input type="checkbox" name="checklist_item_update" value="' + cl.ID + '" ' + checked + ' ' + disabled + '><span></span> ' + cl.name + '</label>' + info + '</div>';
                                        });
                                        if(!isView) {
                                            html += '<button type="button" onclick="Save_Payment_Checklist_Update()" class="btn btn-sm btn-primary mt-3">Save Checklist</button>';
                                        }
                                        html += '</div>';
                                        container.html(html);
                                    }
                                });
                            }

                            function Save_Payment_Checklist_Update() {
                                var booking_id = <?php echo $BookingID ?>;
                                var $select = $('select[name="booking_product"]');
                                var product_id = $select.find(':selected').data('product-id');
                                var completions = [];
                                $('#checklist-container-update input[name="checklist_item_update"]:checked').each(function() {
                                    completions.push($(this).val());
                                });
                                $.ajax({
                                    url: '<?php echo base_url("Payment/Save_Checklist") ?>',
                                    type: 'post',
                                    data: { booking_id: booking_id, product_id: product_id, completions: completions },
                                    dataType: 'json',
                                    success: function(res) {
                                        if(res.success) {
                                            toastr.success('Checklist saved successfully');
                                            Select_Product_Update();
                                        } else {
                                            toastr.error(res.message || 'Failed to save checklist');
                                        }
                                    }
                                });
                            }

                            // Auto-load checklist if product is already selected
                            $(document).ready(function() {
                                var selectedProduct = $('select[name="booking_product"]').val();
                                if(selectedProduct) {
                                    Select_Product_Update();
                                }
                            });
                        </script>
                    <?php } ?>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function Validate_Amount(kind, payment_id, el) {
        const isCredit = kind === 'credit';
        const $input = el ? $(el) : $(`input[name="${isCredit ? `credit-${payment_id}` : `debit-${payment_id}` }"]`);
        const raw = String($input.val() || '').replace(/,/g, '').trim();
        const re = /^(?:0|[1-9]\d{0,9})(?:\.\d{1,2})?$/;

        if (re.test(raw)) {
            const num = parseFloat(raw);
            $input.val(num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        } else {
            $input.val('');
        }

        <?php if(current_url() == base_url('Payment/Create')) { ?>
            Calculate_Subtotal();
        <?php } ?>
    }

    function Validate_Foreign_Currency(payment_id) {
        var foreign_currency = ($(`input[name="foreign_currency-${payment_id}"]`).val()).replace(/,/g, '');
        if(foreign_currency.match(/^[1-9][\d]{0,9}([\.][\d]{0,2})?$/)) {
            $(`input[name="foreign_currency-${payment_id}"]`).val(parseFloat(foreign_currency).toLocaleString('en-US', {minimumFractionDigits: 2}));
        } else {
            $(`input[name="foreign_currency-${payment_id}"]`).val('');
        }
    }
</script>