<html><head>
	<meta charset="utf-8">
	<title><?php echo $Title; ?></title>
</head><style type="text/css">
	@font-face {
	  font-family: Tahoma, Arial, sans-serif !important;
	  font-style: normal;
	  font-weight: normal;
	}
	body {
		font-family: Tahoma, Arial, sans-serif !important;
		font-size: 7.8pt;
		padding-left: 10px;
		padding-right: 10px;
	}

	h1 {
		font-size: 24px;
		text-align: center;
		margin-top: 0px;
		margin-bottom: 5px;
	}

	h2 {
		font-size: 20px;
		text-align: center;
		margin-top: 20px;
		margin-bottom: 20px;
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

	.margin-top-20 {
		margin-top: 20px;
	}

	.text-center {
		text-align: center;
	}

	.text-right {
		text-align: right;
	}

	.small-font {
		font-size: 13px;
	}

	.underline {
		text-decoration: underline;
	}

	.italic {
		font-style: italic;
	}

	.bold {
		font-weight: bold;
	}

	a {
		color: black;
		text-decoration: none;
	}

	@page { margin: 40px 40px 20px 40px; }

	.info-table td {
		vertical-align: top;
		padding: 2px 0;
	}

	.payment-table {
		width: 450px;
		border-collapse: collapse;
		margin-top: 10px;
		margin-bottom: 20px;
	}

	.payment-table th,
	.payment-table td {
		border-top: 1px solid black;
		border-bottom: 1px solid black;
		padding: 8px 12px;
		text-align: left;
		font-size: 13px;
		font-weight: normal;
	}

	.paid-table {
		width: 100%;
		border-collapse: collapse;
		margin-top: 10px;
	}

	.paid-table th,
	.paid-table td {
		border-top: 1px solid black;
		padding: 8px 12px;
		font-size: 13px;
		font-weight: normal;
	}

	.paid-table th {
		text-align: left;
	}

	.total-row {
		border-top: 2px solid black;
		padding-top: 10px;
		margin-top: 0px;
	}

	.signature-line {
		width: 300px;
		border-top: 1px solid black;
		margin: 30px auto 0 auto;
		padding-top: 5px;
		text-align: center;
		font-size: 13px;
	}
</style><body>
	<!-- Header -->
	<table style="width:100%;">
		<tr>
			<?php
				$logoPath = FCPATH.'assets/image/pdflogo-new.jpeg';
				$imgData  = base64_encode(file_get_contents($logoPath));
				$imgSrc   = 'data:image/jpeg;base64,'.$imgData;
			?>
			<td style="width:15%">
				<img src="<?= $imgSrc ?>" style="width:160px;">
			</td>
			<td style="width:85%; text-align: center;">
				<h1><?php echo $CompanyName; ?></h1>
				<small>(Co. Reg. no.<?php echo $CompanyRegistrationNumber; ?> | Travel Agent License no. - <?php echo $CompanyLicenseNumber; ?>)</small>
				<p class="text-center small-font">
					<small>Address: <?php echo $CompanyAddress; ?></small>
				</p>
				<p class="text-center small-font">
					<small>Website: <?php echo $CompanyWebsite; ?></small>
				</p>
			</td>
		</tr>
	</table>

	<!-- Receipt Title -->
	<h2 class="bold">OFFICIAL RECEIPT</h2>

	<!-- Receipt Info -->
	<table class="info-table" style="width:100%; font-size:13px;">
		<tr>
			<td style="width:15%;">Received From</td>
			<td style="width:45%;"><?php echo $ReceivedFrom; ?></td>
			<td style="width:40%; text-align:right;">Voucher No. : <?php echo $VoucherNo; ?></td>
		</tr>
		<tr>
			<td style="vertical-align:top;">Receive The Sum Of</td>
			<td class="underline"><?php echo $ReceiveSumOf; ?></td>
			<td style="text-align:right;">Date : <?php echo $ReceiptDate; ?></td>
		</tr>
		<tr>
			<td></td>
			<td></td>
			<td style="text-align:right;">Ref No : <?php echo $RefNo; ?></td>
		</tr>
	</table>

	<!-- Payment Issued -->
	<p class="italic margin-top-20">Payment Issued</p>
	<table class="payment-table">
		<tr>
			<th>Payment By</th>
			<th>Cheque No.</th>
			<th style="text-align:right;">Payment Amount</th>
		</tr>
		<?php foreach($payments as $payment) { ?>
		<tr>
			<td><?php echo $payment->PaymentBy; ?></td>
			<td><?php echo $payment->ChequeNo; ?></td>
			<td style="text-align:right;"><?php echo number_format($payment->Amount, 2, '.', ','); ?></td>
		</tr>
		<?php } ?>
	</table>

	<!-- Paid For -->
	<p class="italic">Paid For</p>
	<table class="paid-table">
		<tr>
			<th style="width:15%;">Acc. No.</th>
			<th style="width:45%;">Description</th>
			<th style="width:20%; text-align:right;">Tax Amount</th>
			<th style="width:20%; text-align:right;">Amount</th>
		</tr>
		<?php foreach($paid_items as $item) { ?>
		<tr>
			<td><?php echo $item->AccNo; ?></td>
			<td><?php echo $item->Description; ?></td>
			<td style="text-align:right;"><?php echo number_format($item->TaxAmount, 2, '.', ','); ?></td>
			<td style="text-align:right;"><?php echo number_format($item->Amount, 2, '.', ','); ?></td>
		</tr>
		<?php } ?>
	</table>

	<!-- Total -->
	<table style="width:100%;" class="total-row">
		<tr>
			<td style="width:80%; text-align:right; font-weight:bold;">Total:</td>
			<td style="width:20%; text-align:right; font-weight:bold;"><?php echo number_format($Total, 2, '.', ','); ?></td>
		</tr>
	</table>

	<!-- Footer -->
	<div style="margin-top:30px;">
		<p><strong>N.B.</strong></p>
		<p>Validity of This Receipt</p>
		<p>Subject to Clearing of Cheque</p>
	</div>

	<!-- Signature -->
	<div class="signature-line">
		For <?php echo $CompanyName; ?>
	</div>

</body></html>