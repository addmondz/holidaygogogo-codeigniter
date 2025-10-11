<html><head>
	<meta charset="utf-8">
	<title><?php echo $Title; ?> - Footer</title>
</head><style type="text/css">
	@font-face {
	  font-family: "Segoe UI", Arial, sans-serif !important;
	  font-style: normal;
	  font-weight: normal;
	}
	body {
		font-family: "Segoe UI", Arial, sans-serif !important;
		font-size: 13px;
		padding-left: 10px;
		padding-right: 10px;
	}

	h3 {
		font-size: 16px;
		margin: 0px;
		margin-bottom: 5px;
	}

	p {
		margin-top: 0px;
		margin-bottom: 2px;
	}

	.margin-top-10 {
		margin-top: 10px;
	}

	.text-center {
		text-align: center;
	}

	.small-font {
		font-size: 12px;
	}

	.terms-section {
		margin-top: 20px;
	}

	.terms-section h4 {
		font-size: 14px;
		margin-bottom: 10px;
		margin-top: 15px;
	}

	.terms-section p {
		font-size: 12px;
		line-height: 1.4;
		margin-bottom: 8px;
	}

	.bank-details {
		margin-top: 20px;
		border: 1px solid #000;
		padding: 10px;
	}

	.bank-details h4 {
		margin-top: 0;
		margin-bottom: 10px;
	}

	.bank-details p {
		margin-bottom: 5px;
	}

	.computer-generated {
		margin-top: 30px;
		text-align: center;
		font-style: italic;
		font-size: 11px;
		color: #666;
	}

	@page { margin: 40px; }
</style><body>
	<div class="terms-section">
		<h3>PAYMENT TERMS & CONDITIONS</h3>
		
		<h4>Payment Schedule:</h4>
		<p><strong>Deposit Deadline:</strong> <?php echo $DepositDeadline; ?></p>
		<p><strong>Full Payment Deadline:</strong> <?php echo $FullPaymentDeadline; ?></p>
		
		<h4>Payment Methods:</h4>
		<p>• Bank Transfer</p>
		<p>• Credit Card</p>
		<p>• Cash Payment</p>
		<p>• Cheque Payment</p>
		
		<h4>Important Notes:</h4>
		<p>• All payments must be made before the respective deadlines</p>
		<p>• Late payments may incur additional charges</p>
		<p>• Receipt will be issued upon payment confirmation</p>
		<p>• Please keep this receipt for your records</p>
		<p>• For any payment inquiries, please contact our office</p>
	</div>

	<div class="bank-details">
		<h4>BANK DETAILS FOR PAYMENT</h4>
		<p><strong>Bank Name:</strong> [Bank Name]</p>
		<p><strong>Account Name:</strong> <?php echo $CompanyName; ?></p>
		<p><strong>Account Number:</strong> [Account Number]</p>
		<p><strong>Swift Code:</strong> [Swift Code]</p>
		<p><strong>Reference:</strong> <?php echo $BookingNumber; ?></p>
	</div>

	<div class="terms-section">
		<h4>CANCELLATION POLICY:</h4>
		<p>• Cancellation charges may apply based on supplier terms</p>
		<p>• Refunds will be processed within 14 working days</p>
		<p>• Administrative fees may be deducted from refunds</p>
		
		<h4>CONTACT INFORMATION:</h4>
		<p><strong>Office:</strong> <?php echo $CompanyAddress; ?></p>
		<p><strong>Website:</strong> <?php echo $CompanyWebsite; ?></p>
		<p><strong>Sales Agent:</strong> <?php echo $SalesAgentName; ?> (<?php echo $SalesAgentMobile; ?>)</p>
	</div>

	<div class="computer-generated">
		<p>This is a computer generated receipt. No signature required.</p>
		<p>Generated on: <?php echo $InsertDate; ?></p>
		<p>Receipt No: <?php echo $BookingNumber; ?>_RECEIPT</p>
	</div>
</body></html>
