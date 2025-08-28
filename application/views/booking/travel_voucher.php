<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<title><?php echo $Title; ?></title>
</head>

<style type="text/css">
	body {
		display: block;
		font-family: Helvetica;
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

	.margin-top-25 {
		margin-top: 25px;
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
</style>

<body>
	<table>
		<tr>
			<td style="width: 15%"><img src="<?php echo base_url('assets/image/logo.png'); ?>" style="width:160px;"></td>
			<td style="width: 85%">
				<h1><?php echo $CompanyName; ?></h1>
				<p class="text-center small-font">
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
	<hr class="margin-top-25">
	<table style="width:100%;">
		<tr>
			<th style="width:65%;">
				<h3 class="text-right">TRAVEL VOUCHER</h3>
			</th>
			<th style="width:35%; font-weight:700; text-align:right;">No : <?php echo $BookingNumber; ?></th>
		</tr>
	</table>
	<table style="width:100%; font-size:13px;">
		<tr>
			<td style="width:14%;">Customer</td>
			<td style="width:2%;"> : </td>
			<td style="width:38%;">
				<b><?php echo $Customer; ?></b>
			</td>
			<td style="width:15%;">
				<b>Destination</b>
			</td>
			<td style="width:2%;"> : </td>
			<td style="width:24%;"><?php echo $DestinationName; ?></td>
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
	<hr style="margin-bottom:0px;">
	<table style="width:100%; font-size:13px;">
		<tr style="font-weight:700;">
			<td style="width:10%;">Item</td>
			<td style="width:80%;">Description</td>
			<td class="text-center" style="width:10%;">Quantity</td>
		</tr>
	</table>
	<hr style="margin-top:0px;">
	<table style="width:100%; font-size:13px; border-spacing:15px;">
		<?php $count = 1;
		foreach($booking_products as $booking_product) { ?>
			<tr style="vertical-align:baseline;">
				<td style="width:10%;"><?php echo $count . '.'; ?></td>
				<td style="width:80%;"><?php if(empty($booking_product->Description)) { echo $booking_product->Name; } else { echo '<u><strong>' . $booking_product->Description . '</strong></u><br>' . $booking_product->Name; } ?></td>
				<td class="text-center" style="width:10%;"><?php if(empty($booking_product->Description)) { echo $booking_product->Quantity; } else { echo '<br>' . $booking_product->Quantity; } ?></td>
			</tr>
		<?php $count++; } ?>
		<tr>
			<td>&nbsp;</td>
		</tr>
	</table>
	<br>
	<table style="page-break-inside:avoid;">
		<tr>
			<td><?php echo $TravelVoucherTitle; ?></td>
		</tr>
		<tr>
			<td><?php echo $TravelVoucherFooter; ?></td>
		</tr>
	</table>
</body>

</html>