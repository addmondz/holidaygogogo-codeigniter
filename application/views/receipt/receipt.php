<html><head>
	<meta charset="utf-8">
	<title><?php echo $Title; ?></title>
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

	h1 {
		font-size: 24px;
		text-align: center;
		margin-top: 0px;
		margin-bottom: 5px;
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

	.text-right {
		text-align: right;
	}

	.small-font {
		font-size: 13px;
	}

	a {
		color: black;
		text-decoration: none;
	}

	@page { margin: 80px 40px 20px 40px; }
    header { position: fixed; top: -60px; left: 0px; right: 0px; height: 50px; }
    .marginontop { margin-top: 250px; }
    .items { position: fixed; bottom: 280px; left: 0px; right: 0px; height: 50px; }
    .item0 { margin-top: 550px; }
    .item1 { margin-top: 200px; }
    .item2 { margin-top: 130px; }
    .item3 { margin-top: 60px; }
    .item4 { margin-top: 0px; }
    .split { page-break-after: always; }
	.no-split { page-break-after: avoid; }
	
	.payment-table {
		width: 100%;
		border-collapse: collapse;
		margin-top: 20px;
	}
	
	.payment-table th,
	.payment-table td {
		border: 1px solid #000;
		padding: 8px;
		text-align: left;
	}
	
	.payment-table th {
		background-color: #f0f0f0;
		font-weight: bold;
	}
	
	.total-section {
		margin-top: 20px;
		border-top: 2px solid #000;
		padding-top: 10px;
	}
	
	.total-table {
		width: 100%;
		border-collapse: collapse;
	}
	
	.total-table td {
		padding: 5px 10px;
		border: none;
	}
	
	.total-table .label {
		width: 70%;
	}
	
	.total-table .amount {
		width: 30%;
		text-align: right;
	}
	
	.total-table .final-row td {
		font-weight: bold;
		font-size: 16px;
		border-top: 1px solid #000;
		padding-top: 10px;
	}
</style><body>
	<header>
		<table>
			<tr>
				<?php
					$logoPath = FCPATH.'assets/image/pdflogo.png';
					$imgData  = base64_encode(file_get_contents($logoPath));
					$imgSrc   = 'data:image/png;base64,'.$imgData;
				?>
				<td style="width:15%">
					<img src="<?= $imgSrc ?>" style="width:160px;">
				</td>
				<td style="width:85%; text-align: center;">
					<h1><?php echo $CompanyName; ?></h1>
					<small>(Co. Reg. No. - <?php echo $CompanyRegistrationNumber; ?> | Travel Agent License No. - <?php echo $CompanyLicenseNumber; ?>)</small>
					</p>
					<p class="text-center small-font">
						<small>Address : <?php echo $CompanyAddress; ?></small>
					</p>
					<p class="text-center small-font">
						<small>Website : <?php echo $CompanyWebsite; ?></small>
					</p>
				</td>
			</tr>
		</table>
		<hr class="margin-top-10">
		<table style="width:100%;">
			<tr>
				<th style="width:65%;">
					<h3 class="text-right"><?php echo $BookingConfirmationTitle; ?></h3>
				</th>
				<th style="width:35%; font-weight:700; text-align:right;">No : <?php echo $BookingNumber; ?></th>
			</tr>
		</table>
		<table style="width:100%; font-size:12px;">
			<tr>
				<td style="width:12%;">Customer</td>
				<td style="width:1%;"> : </td>
				<td style="width:36%;">
					<b><?php echo $Customer; ?></b>
				</td>
				<td style="width:15%;">
					<b>Destination</b>
				</td>
				<td style="width:1%;"> : </td>
				<td style="width:26%;"><?php echo $DestinationName; ?></td>
			</tr>
			<tr>
				<td>Contact No.</td>
				<td> : </td>
				<td><?php echo $CustomerMobile; ?></td>
				<td>
					<b>Reservation No.</b>
				</td>
				<td> : </td>
				<td><?php echo $ReservationNumber; ?></td>
			</tr>
			<tr>
				<td>Travel Date</td>
				<td> : </td>
				<td><?php echo $TravelDate; ?></td>
				<td>Date</td>
				<td> : </td>
				<td><?php echo $InsertDate; ?></td>
			</tr>
			<tr>
				<td>No. Of Guests</td>
				<td> : </td>
				<td><?php echo $PaxNumber; ?></td>
				<td>Sales Agent</td>
				<td> : </td>
				<td><?php echo $SalesAgentName . ' (' . $SalesAgentMobile . ')'; ?></td>
			</tr>
		</table>
	</header>

	<div class="marginontop">
		<h3>PAYMENT DETAILS</h3>
		
		<table class="payment-table">
			<thead>
				<tr>
					<th>Date</th>
					<th>Payment Type</th>
					<th>Amount Received</th>
					<th>Reference Number</th>
				</tr>
			</thead>
			<tbody>
				<?php if(!empty($approved_payments)) { ?>
					<?php foreach($approved_payments as $payment) { ?>
						<tr>
							<td><?php echo strtoupper(date('j M Y', strtotime($payment->Date))); ?></td>
							<td><?php echo $payment->Type; ?></td>
							<td style="text-align: right;">RM <?php echo number_format($payment->Credit, 2, '.', ','); ?></td>
							<td><?php echo $payment->ReferenceNumber; ?></td>
						</tr>
					<?php } ?>
				<?php } else { ?>
					<tr>
						<td colspan="4" style="text-align: center;">No payments received</td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
		
		<div class="total-section">
			<table class="total-table">
				<tr>
					<td class="label"><strong>Total Package Price:</strong></td>
					<td class="amount"><strong><?php echo $NetTotal; ?></strong></td>
				</tr>
				<tr>
					<td class="label">Total Amount Received:</td>
					<td class="amount"><?php echo $total_received; ?></td>
				</tr>
				<tr class="final-row">
					<td class="label">Balance Due:</td>
					<td class="amount"><?php echo $balance_due; ?></td>
				</tr>
			</table>
		</div>
		
		<h3>PACKAGE DETAILS</h3>
		
		<table class="payment-table">
			<thead>
				<tr>
					<th>Product</th>
					<th>Description</th>
					<th>Price</th>
				</tr>
			</thead>
			<tbody>
				<?php if(!empty($booking_products)) { ?>
					<?php foreach($booking_products as $product) { ?>
						<tr>
							<td><?php echo $product->Name; ?></td>
							<td><?php echo $product->Description; ?></td>
							<td style="text-align: right;">RM <?php echo number_format($product->Price, 2, '.', ','); ?></td>
						</tr>
					<?php } ?>
				<?php } else { ?>
					<tr>
						<td colspan="3" style="text-align: center;">No products found</td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
		
		<div class="total-section">
			<table class="total-table">
				<tr>
					<td class="label">Subtotal:</td>
					<td class="amount"><?php echo $Subtotal; ?></td>
				</tr>
				<tr>
					<td class="label">Discount:</td>
					<td class="amount"><?php echo $Discount; ?></td>
				</tr>
				<tr class="final-row">
					<td class="label">Net Total:</td>
					<td class="amount"><?php echo $NetTotal; ?></td>
				</tr>
			</table>
		</div>
	</div>
</body></html>
