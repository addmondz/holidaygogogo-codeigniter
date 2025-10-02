<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>HolidayGoGoGo | Guest List</title>
    <link href="<?php echo base_url('assets/image/favicon.png'); ?>" rel="icon">
    <link href="<?php echo base_url('assets/image/favicon.png'); ?>" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" rel="stylesheet">
    <link href="<?php echo base_url('assets/css/login.css'); ?>" type="text/css" rel="stylesheet">
    <link href="<?php echo base_url('assets/css/plugins-bundle.css'); ?>" type="text/css" rel="stylesheet">
    <link href="<?php echo base_url('assets/css/prismjs-bundle.css'); ?>" type="text/css" rel="stylesheet">
    <link href="<?php echo base_url('assets/css/style-bundle.css?param=kiriel'); ?>" type="text/css" rel="stylesheet">
    <script src="<?php echo base_url('assets/js/plugins-bundle.js'); ?>"></script>
</head>

<style>
	#timer {
		color: #CCCCFF;
		font-family: Verdana, sans-serif, Arial;
		font-size: 80px;
		font-weight: bold;
		text-align: center;
	}
</style>

<body>
    <div class="login login-2 login-signin-on d-flex flex-row-fluid">
        <div class="d-flex flex-center flex-row-fluid bgi-size-cover bgi-position-top bgi-no-repeat" style="background-image:url(<?php echo base_url('assets/image/login.jpg'); ?>);">
            <div class="container p-7 position-relative overflow-hidden">
                <div class="d-flex flex-center mb-10">
                    <a>
                        <img src="<?php echo base_url('assets/image/logo.png'); ?>" class="max-h-75px">
                    </a>
                </div>
               	<div class="row mb-10">
                	<div class="col-md-6">
                		<p>Booking Number :
							<a href="<?php echo base_url('Travel_Voucher?token=') . $this->input->get('gl'); ?>" target="_blank"><?php echo $guest_lists[0]->BookingNumber; ?></a>
						</p>
                		<p>Customer : <?php echo $guest_lists[0]->Customer; ?> (<?php echo $guest_lists[0]->CustomerMobile; ?>)</p>
						<p>Sales Agent : <?php echo $guest_lists[0]->SalesAgent; ?> (<?php echo $guest_lists[0]->SalesAgentMobile; ?>)</p>
                	</div>
                	<div class="col-md-6">
                		<p>Destination : 
							<b><?php echo $guest_lists[0]->Destination; ?></b>
						</p>
                		<p>Travel Date : 
							<b><?php echo $guest_lists[0]->TravelDate; ?></b>
						</p>
                		<p id="pax_number">Pax Number : <?php echo $guest_lists[0]->PaxNumber; ?></p>
                	</div>
                </div>
				<div class="row mb-10">
					<div class="col-md-12">
						<?php if(!empty($this->session->userdata('admin_id'))) {
							if($guest_lists[0]->LockStatus == 'N') { ?>
								<a href="<?php echo base_url('Booking/Update_Lock_Status?booking_id=') . $guest_lists[0]->BookingID . '&current_lock_status=' . $guest_lists[0]->LockStatus . '&new_lock_status=Y&gl=' . $this->input->get('gl'); ?>" class="btn btn-light-danger font-weight-bold mr-1 mb-2" style="width:180px;">
									<i class="la la-lock"></i>Lock Guest List
								</a>
								<?php if($guest_lists[0]->TravelInsuranceStatus == 'Y') { ?>
									<a href="<?php echo base_url('Booking/Update_Travel_Insurance_Status?booking_id=') . $guest_lists[0]->BookingID . '&current_travel_insurance_status=' . $guest_lists[0]->TravelInsuranceStatus . '&new_travel_insurance_status=N&gl=' . $this->input->get('gl'); ?>" class="btn btn-light-warning font-weight-bold mr-1 mb-2" style="width:180px;">
										<i class="la la-plane"></i>Without Insurance
									</a>
								<?php } else { ?>
									<a href="<?php echo base_url('Booking/Update_Travel_Insurance_Status?booking_id=') . $guest_lists[0]->BookingID . '&current_travel_insurance_status=' . $guest_lists[0]->TravelInsuranceStatus . '&new_travel_insurance_status=Y&gl=' . $this->input->get('gl'); ?>" class="btn btn-light-primary font-weight-bold mr-1 mb-2" style="width:180px;">
										<i class="la la-plane"></i>With Insurance
									</a>
								<?php } ?>
								<a id="create_guest" class="btn btn-light-success font-weight-bold mr-1 mb-2" style="width:180px;">
									<i class="la la-plus-circle"></i>Insert Guest
								</a>
						<?php } else { ?>
							<a href="<?php echo base_url('Booking/Update_Lock_Status?booking_id=') . $guest_lists[0]->BookingID. '&current_lock_status=' . $guest_lists[0]->LockStatus . '&new_lock_status=N&gl=' . $this->input->get('gl'); ?>" class="btn btn-light-success font-weight-bold mr-1 mb-2" style="width:180px;">
								<i class="la la-unlock"></i>Unlock Guest List
							</a>
						<?php } ?>
							<a onclick="beforeUnloadHandler('RTB', '<?php echo base_url('Booking?booking_number=') . $guest_lists[0]->BookingNumber; ?>')" class="btn btn-light-info font-weight-bold mb-2" style="width:180px;">
								<i class="la la-arrow-circle-left"></i>Return To Booking
							</a>
							<a onclick="beforeUnloadHandler('EGL1', null)" class="btn btn-info font-weight-bold mb-2" style="width:180px;">
								<i class="la la-sign-out-alt"></i>Exit Guest List
							</a>
						<?php } else { ?>
							<a onclick="beforeUnloadHandler('EGL2', '<?php echo base_url('Message?url=' . base_url($_SERVER['REQUEST_URI'])) ?>')" class="btn btn-info font-weight-bold mb-2" style="width:180px;">
								<i class="la la-sign-out-alt"></i>Exit Guest List
							</a>
						<?php } ?>
						<br><br>
						<?php if($guest_lists[0]->LockStatus == 'N') { ?>
							<div id="timer"></div>
							<div class="p-5" style="background-color:#F8C8DC; border-radius:6px;">
								<!-- <p style="color:white; font-size:13px; text-align:justify;"><strong>We Kindly Request That You Complete The Action Within The Specified Timeframe Provided. We Understand That Unforseen Circumstances May Arise, Leading To The Need For Additional Time. By The Last Minute Of The Allocated Timeframe, A Pop-Up Will Appear Allowing You To Request An Extension.</strong></p> -->
								<div style="padding:15px; border-radius:6px;">
									<p style="color:#333; font-size:14px; text-align:justify; margin:0 0 10px 0;">
										<strong>
										We kindly request that you complete the action within the specified timeframe provided. 
										We understand that unforeseen circumstances may arise, leading to the need for additional time. 
										By the last minute of the allocated timeframe, a pop-up will appear allowing you to request an extension.
										</strong>
									</p>

									<p style="color:#333; font-size:13px; text-align:justify; margin:0;">
										By proceeding, you acknowledge that you have read and agreed to our 
										<a href="https://www.holidaygogogo.com/terms-condition/" target="_blank" style="color:#0066cc; font-weight:bold; text-decoration:underline;">Terms & Conditions</a>, 
										<a href="https://www.holidaygogogo.com/privacy-policy/" target="_blank" style="color:#0066cc; font-weight:bold; text-decoration:underline;">Privacy Policy</a>, 
										and 
										<a href="https://www.holidaygogogo.com/pdpa-notice/" target="_blank" style="color:#0066cc; font-weight:bold; text-decoration:underline;">PDPA Notice</a>.
									</p>
								</div>
							</div>
						<?php } ?>
					</div>
				</div>
                <form id="form" action="<?php if($_SERVER['SERVER_NAME'] != 'gl.holidaygogogo.com') { echo base_url('Guest_List?gl=') . $this->input->get('gl'); } else { echo 'https://gl.holidaygogogo.com/?gl=' . $this->input->get('gl'); } ?>" method="post">
					<div id="benchmark" class="row">
						<?php $counter = 1;
							$adult = 0;
							$child = 0;
							$infant = 0;
						?>
						<input type="hidden" name="new_guests">
						<input type="hidden" name="deleted_guests">
						<input type="hidden" name="adult">
						<input type="hidden" name="child">
						<input type="hidden" name="infant">
						<?php foreach($guest_lists as $guest) {
							switch($guest->Type) {
								case 'ADULT':
									$adult++;
									break;
								case 'CHILD':
									$child++;
									break;
								case 'INFANT':
									$infant++;
							}
						?>
							<input type="hidden" name="guests[]" value="<?php echo $guest->GuestListID; ?>">
							<input type="hidden" id="<?php echo 'type-' . $guest->GuestListID; ?>" value="<?php echo $guest->Type; ?>">
							<div id="<?php echo 'guest-' . $guest->GuestListID; ?>" class="col-md-6">
								<div class="card card-custom gutter-b" style="border:1px solid lightgray;">
									<div class="card-header" style="background-color:#D7E2F2;">
										<div class="card-title w-100">
											<h3 class="card-label" style="color:#6082B6;">Guest <?php echo $counter . ' : ' . $guest->Type; ?></h3>
											<?php if(!empty($this->session->userdata('admin_id')) && $guest_lists[0]->LockStatus == 'N') { ?>
												<a onclick="Delete_Guest(<?php echo $guest->GuestListID; ?>)" class="btn btn-icon btn-light-danger" style="margin-left:auto;">
													<i class="la la-times"></i>
												</a>
											<?php } ?>
										</div>
									</div>
									<div class="card-body">
										<strong>Basic Details :</strong>
										<br><br>
										<div class="form-group">
											<div class="row">
												<div class="col-md-6 mb-7 mb-md-0">
													<label id="<?php echo 'name_label-' . $guest->GuestListID; ?>">Name (As per IC/Passport) <?php if(!empty($guest->Guest)) { echo '<span style="color:red;">*</span>'; } ?></label>
													<input <?php if(!empty($guest->Guest)) { echo 'required'; } ?> <?php if($guest_lists[0]->LockStatus == 'Y') { echo 'disabled'; } ?> type="text" name="names[]" id="<?php echo 'name-' . $guest->GuestListID; ?>" value="<?php echo $guest->Guest; ?>" onchange="Set_Required_Field(<?php echo $guest->GuestListID; ?>)" autocomplete="off" class="form-control">
												</div>
												<div class="col-md-6">
													<label id="<?php echo 'gender_label-' . $guest->GuestListID; ?>">Gender <?php if(!empty($guest->Guest)) { echo '<span style="color:red;">*</span>'; } ?></label>
													<select <?php if(!empty($guest->Guest)) { echo 'required'; } ?> <?php if($guest_lists[0]->LockStatus == 'Y') { echo 'disabled'; } ?> name="genders[]" id="<?php echo 'gender-' . $guest->GuestListID; ?>" onchange="Set_Required_Field(<?php echo $guest->GuestListID; ?>)" class="form-control">
														<option selected disabled value="">--SELECT GENDER--</option>
														<option value="F" <?php if($guest->Gender == 'F') { echo 'selected'; } ?>>FEMALE</option>
														<option value="M" <?php if($guest->Gender == 'M') { echo 'selected'; } ?>>MALE</option>
													</select>
												</div>
											</div>
											<br>
											<div class="row">
												<div class="col-md-6 mb-7 mb-md-0">
													<label id="<?php echo 'date_of_birth_label-' . $guest->GuestListID; ?>">Date Of Birth <?php if(!empty($guest->Guest)) { echo '<span style="color:red;">*</span>'; } ?></label>
													<input <?php if(!empty($guest->Guest)) { echo 'required'; } ?> <?php if($guest_lists[0]->LockStatus == 'Y') { echo 'disabled'; } ?> type="text" name="date_of_births[]" id="<?php echo 'date_of_birth-' . $guest->GuestListID; ?>" value="<?php echo $guest->DateOfBirth; ?>" onchange="Set_Required_Field(<?php echo $guest->GuestListID; ?>)" autocomplete="off" class="form-control kt_datepicker_4_3">
												</div>
												<div class="col-md-6">
													<label id="<?php echo 'nationality_label-' . $guest->GuestListID; ?>">Nationality <?php if(!empty($guest->Guest)) { echo '<span style="color:red;">*</span>'; } ?></label>
													<select <?php if(!empty($guest->Guest)) { echo 'required'; } ?> <?php if($guest_lists[0]->LockStatus == 'Y') { echo 'disabled'; } ?> name="nationalities[]" id="<?php echo 'nationality-' . $guest->GuestListID; ?>" onchange="Set_Required_Field(<?php echo $guest->GuestListID; ?>)" class="form-control">
														<option selected disabled value="">--SELECT NATIONALITY--</option>
														<?php foreach($country_codes as $nationality) { ?>
															<option <?php if(!empty($guest->Nationality) && $nationality->CountryCodeID == $guest->Nationality) { echo 'selected'; } ?> value="<?php echo $nationality->CountryCodeID; ?>"><?php echo $nationality->Country; ?></option>
														<?php } ?>
													</select>
												</div>
											</div>
											<br>
											<div class="row">
												<div class="col-md-6 mb-7 mb-md-0">
													<label id="<?php echo 'identification_number_label-' . $guest->GuestListID; ?>">Identification Number <?php if(!empty($guest->Guest) && $guest->NationalityName == 'MALAYSIA') { echo '<span style="color:red;">*</span>'; } ?></label>
													<input <?php if(!empty($guest->Guest && $guest->NationalityName == 'MALAYSIA')) { echo 'required'; } ?> <?php if($guest_lists[0]->LockStatus == 'Y') { echo 'disabled'; } ?> type="text" name="identification_numbers[]" id="<?php echo 'identification_number-' . $guest->GuestListID; ?>" value="<?php echo $guest->IdentificationNumber; ?>" onchange="Set_Required_Field(<?php echo $guest->GuestListID; ?>)" autocomplete="off" class="form-control">
												</div>
												<div class="col-md-6">
													<label id="<?php echo 'passport_number_label-' . $guest->GuestListID; ?>">Passport Number <?php if(!empty($guest->Guest) && !empty($guest->NationalityName) && $guest->NationalityName != 'MALAYSIA') { echo '<span style="color:red;">*</span>'; } ?></label>
													<input <?php if(!empty($guest->Guest && !empty($guest->NationalityName) && $guest->NationalityName != 'MALAYSIA')) { echo 'required'; } ?> <?php if($guest_lists[0]->LockStatus == 'Y') { echo 'disabled'; } ?> type="text" name="passport_numbers[]" id="<?php echo 'passport_number-' . $guest->GuestListID; ?>" value="<?php echo $guest->PassportNumber; ?>" onchange="Set_Required_Field(<?php echo $guest->GuestListID; ?>)" autocomplete="off" class="form-control">
												</div>
											</div>
											<br>
											<div class="row">
												<div class="col-md-6 mb-7 mb-md-0">
													<label id="<?php echo 'country_code_label-' . $guest->GuestListID; ?>">Country Code <?php if(!empty($guest->Guest)) { echo '<span style="color:red;">*</span>'; } ?></label>
													<select <?php if(!empty($guest->Guest)) { echo 'required'; } ?> <?php if($guest_lists[0]->LockStatus == 'Y') { echo 'disabled'; } ?> name="country_codes[]" id="<?php echo 'country_code-' . $guest->GuestListID; ?>" onchange="Set_Required_Field(<?php echo $guest->GuestListID; ?>)" class="form-control">
														<option selected disabled value="">--SELECT COUNTRY CODE--</option>
														<?php foreach($country_codes as $country_code) { ?>
															<option <?php if(!empty($guest->GuestCountryCode) && $country_code->CountryCodeID == $guest->GuestCountryCode) { echo 'selected'; } ?> value="<?php echo $country_code->CountryCodeID; ?>"><?php echo $country_code->Country . ' ' . $country_code->CountryCode; ?></option>
														<?php } ?>
													</select>
												</div>
												<div class="col-md-6">
													<label id="<?php echo 'mobile_label-' . $guest->GuestListID; ?>">Mobile <?php if(!empty($guest->Guest)) { echo '<span style="color:red;">*</span>'; } ?></label>
													<input <?php if(!empty($guest->Guest)) { echo 'required'; } ?> <?php if($guest_lists[0]->LockStatus == 'Y') { echo 'disabled'; } ?> type="text" name="mobiles[]" id="<?php echo 'mobile-' . $guest->GuestListID; ?>" value="<?php echo $guest->GuestMobile; ?>" onchange="Set_Required_Field(<?php echo $guest->GuestListID; ?>)" autocomplete="off" class="form-control">
												</div>
											</div>
											<br>
											<div class="row">
												<div class="col-md-6">
													<label id="<?php echo 'email_label-' . $guest->GuestListID; ?>">Email <?php if(!empty($guest->Guest)) { echo '<span style="color:red;">*</span>'; } ?></label>
													<input <?php if(!empty($guest->Guest)) { echo 'required'; } ?> <?php if($guest_lists[0]->LockStatus == 'Y') { echo 'disabled'; } ?> type="text" name="emails[]" id="<?php echo 'email-' . $guest->GuestListID; ?>" value="<?php echo $guest->Email; ?>" onchange="Set_Required_Field(<?php echo $guest->GuestListID; ?>)" autocomplete="off" class="form-control">
												</div>
											</div>
										</div>
										<?php if($guest_lists[0]->TravelInsuranceStatus == 'Y') { ?>
											<strong>For Guest Add-On Travel Insurance Only :</strong>
											<br><br>
											<div class="accordion accordion-solid accordion-toggle-plus">
												<div class="card">
													<div class="card-header">
														<div id="<?php echo 'travel_insurance_header-' . $guest->GuestListID; ?>" data-toggle="collapse" data-target="<?php echo '#travel_insurance_info-' . $guest->GuestListID; ?>" class="card-title collapsed" style="font-size:13px;">Travel Insurance Information</div>
													</div>
													<div id="<?php echo 'travel_insurance_info-' . $guest->GuestListID; ?>" class="collapse">
														<div class="card-body">
															<div class="form-group">
																<div class="row">
																	<div class="col-md-6 mb-7 mb-md-0">
																		<label id="<?php echo 'marital_status_label-' . $guest->GuestListID; ?>">Marital Status <?php if(!empty($guest->Guest)) { echo '<span style="color:red;">*</span>'; } ?></label>
																		<select <?php if(!empty($guest->Guest)) { echo 'required'; } ?> <?php if($guest_lists[0]->LockStatus == 'Y') { echo 'disabled'; } ?> name="marital_statuses[]" id="<?php echo 'marital_status-' . $guest->GuestListID; ?>" onchange="Set_Required_Field(<?php echo $guest->GuestListID; ?>)" class="form-control">
																			<option selected disabled value="">--SELECT MARITAL STATUS--</option>
																			<option value="DIVORCED" <?php if($guest->MaritalStatus == 'DIVORCED') { echo 'selected'; } ?>>DIVORCED</option>
																			<option value="MARRIED" <?php if($guest->MaritalStatus == 'MARRIED') { echo 'selected'; } ?>>MARRIED</option>
																			<option value="SINGLE" <?php if($guest->MaritalStatus == 'SINGLE') { echo 'selected'; } ?>>SINGLE</option>
																			<option value="WIDOW" <?php if($guest->MaritalStatus == 'WIDOW') { echo 'selected'; } ?>>WIDOW</option>
																		</select>
																	</div>
																	<div class="col-md-6">
																		<label id="<?php echo 'employment_label-' . $guest->GuestListID; ?>">Employment <?php if(!empty($guest->Guest)) { echo '<span style="color:red;">*</span>'; } ?></label>
																		<input <?php if(!empty($guest->Guest)) { echo 'required'; } ?> <?php if($guest_lists[0]->LockStatus == 'Y') { echo 'disabled'; } ?> type="text" name="employments[]" id="<?php echo 'employment-' . $guest->GuestListID; ?>" value="<?php echo $guest->Employment; ?>" onchange="Set_Required_Field(<?php echo $guest->GuestListID; ?>)" autocomplete="off" class="form-control">
																	</div>
																</div>
																<br>
																<div class="row">
																	<div class="col-md-6 mb-7 mb-md-0">
																		<label id="<?php echo 'address_label-' . $guest->GuestListID; ?>">Address <?php if(!empty($guest->Guest)) { echo '<span style="color:red;">*</span>'; } ?></label>
																		<input <?php if(!empty($guest->Guest)) { echo 'required'; } ?> <?php if($guest_lists[0]->LockStatus == 'Y') { echo 'disabled'; } ?> type="text" name="addresses[]" id="<?php echo 'address-' . $guest->GuestListID; ?>" value="<?php echo $guest->Address; ?>" onchange="Set_Required_Field(<?php echo $guest->GuestListID; ?>)" autocomplete="off" class="form-control">
																	</div>
																	<div class="col-md-6">
																		<label id="<?php echo 'postcode_label-' . $guest->GuestListID; ?>">Postcode <?php if(!empty($guest->Guest)) { echo '<span style="color:red;">*</span>'; } ?></label>
																		<input <?php if(!empty($guest->Guest)) { echo 'required'; } ?> <?php if($guest_lists[0]->LockStatus == 'Y') { echo 'disabled'; } ?> type="text" name="postcodes[]" id="<?php echo 'postcode-' . $guest->GuestListID; ?>" value="<?php echo $guest->Postcode; ?>" onchange="Set_Required_Field(<?php echo $guest->GuestListID; ?>)" autocomplete="off" class="form-control">
																	</div>
																</div>
																<br>
																<div class="row">
																	<div class="col-md-6 mb-7 mb-md-0">
																		<label id="<?php echo 'city_label-' . $guest->GuestListID; ?>">City <?php if(!empty($guest->Guest)) { echo '<span style="color:red;">*</span>'; } ?></label>
																		<input <?php if(!empty($guest->Guest)) { echo 'required'; } ?> <?php if($guest_lists[0]->LockStatus == 'Y') { echo 'disabled'; } ?> type="text" name="cities[]" id="<?php echo 'city-' . $guest->GuestListID; ?>" value="<?php echo $guest->City; ?>" onchange="Set_Required_Field(<?php echo $guest->GuestListID; ?>)" autocomplete="off" class="form-control">
																	</div>
																	<div class="col-md-6">
																		<label id="<?php echo 'state_label-' . $guest->GuestListID; ?>">State <?php if(!empty($guest->Guest)) { echo '<span style="color:red;">*</span>'; } ?></label>
																		<input <?php if(!empty($guest->Guest)) { echo 'required'; } ?> <?php if($guest_lists[0]->LockStatus == 'Y') { echo 'disabled'; } ?> type="text" name="states[]" id="<?php echo 'state-' . $guest->GuestListID; ?>" value="<?php echo $guest->State; ?>" onchange="Set_Required_Field(<?php echo $guest->GuestListID; ?>)" autocomplete="off" class="form-control">
																	</div>
																</div>
																<br>
																<div class="row">
																	<div class="col-md-6 mb-7 mb-md-0">
																		<label id="<?php echo 'country_label-' . $guest->GuestListID; ?>">Country <?php if(!empty($guest->Guest)) { echo '<span style="color:red;">*</span>'; } ?></label>
																		<select <?php if(!empty($guest->Guest)) { echo 'required'; } ?> <?php if($guest_lists[0]->LockStatus == 'Y') { echo 'disabled'; } ?> name="countries[]" id="<?php echo 'country-' . $guest->GuestListID; ?>" onchange="Set_Required_Field(<?php echo $guest->GuestListID; ?>)" class="form-control">
																			<option selected disabled value="">--SELECT COUNTRY--</option>
																			<?php foreach($country_codes as $country) { ?>
																				<option <?php if(!empty($guest->Country) && $country->CountryCodeID == $guest->Country) { echo 'selected'; } ?> value="<?php echo $country->CountryCodeID; ?>"><?php echo $country->Country; ?></option>
																			<?php } ?>
																		</select>
																	</div>
																	<div class="col-md-6">
																		<label id="<?php echo 'nominee_name_label-' . $guest->GuestListID; ?>">Nominee Name <?php if(!empty($guest->Guest)) { echo '<span style="color:red;">*</span>'; } ?></label>
																		<input <?php if(!empty($guest->Guest)) { echo 'required'; } ?> <?php if($guest_lists[0]->LockStatus == 'Y') { echo 'disabled'; } ?> type="text" name="nominee_names[]" id="<?php echo 'nominee_name-' . $guest->GuestListID; ?>" value="<?php echo $guest->Nominee; ?>" onchange="Set_Required_Field(<?php echo $guest->GuestListID; ?>)" autocomplete="off" class="form-control">
																	</div>
																</div>
																<br>
																<div class="row">
																	<div class="col-md-6 mb-7 mb-md-0">
																		<label id="<?php echo 'nominee_identification_number_label-' . $guest->GuestListID; ?>">Nominee Identification Number <?php if(!empty($guest->Guest)) { echo '<span style="color:red;">*</span>'; } ?></label>
																		<input <?php if(!empty($guest->Guest)) { echo 'required'; } ?> <?php if($guest_lists[0]->LockStatus == 'Y') { echo 'disabled'; } ?> type="text" name="nominee_identification_numbers[]" id="<?php echo 'nominee_identification_number-' . $guest->GuestListID; ?>" value="<?php echo $guest->NomineeIdentificationNumber; ?>" onchange="Set_Required_Field(<?php echo $guest->GuestListID; ?>)" autocomplete="off" class="form-control">
																	</div>
																	<div class="col-md-6">
																		<label id="<?php echo 'relationship_label-' . $guest->GuestListID; ?>">Relationship <?php if(!empty($guest->Guest)) { echo '<span style="color:red;">*</span>'; } ?></label>
																		<input <?php if(!empty($guest->Guest)) { echo 'required'; } ?> <?php if($guest_lists[0]->LockStatus == 'Y') { echo 'disabled'; } ?> type="text" name="relationships[]" id="<?php echo 'relationship-' . $guest->GuestListID; ?>" value="<?php echo $guest->Relationship; ?>" onchange="Set_Required_Field(<?php echo $guest->GuestListID; ?>)" autocomplete="off" class="form-control">
																		<p style="color:#FAA0A0; font-size:10px; margin-top:5px;">(must be relative and not in the trip, eg cousin, uncle, sister, brother, father, mother & etc)</p>
																	</div>
																</div>
															</div>
														</div>
													</div>
												</div>
											</div>
										<?php } ?>
									</div>
								</div>
							</div>
							<?php $counter++; ?>
							<script>
								<?php if($guest_lists[0]->TravelInsuranceStatus == 'Y' && !empty($guest->Nominee)) { ?>
									$(`#travel_insurance_header-${<?php echo $guest->GuestListID; ?>}`).click();
								<?php } ?>
							</script>
						<?php } ?>
					</div>
					<?php if($guest_lists[0]->LockStatus == 'N') { ?>
						<div class="text-center">
							<input type="submit" value="Update Guest List" class="btn btn-primary w-50">
						</div>
					<?php } ?>
				</form>
            </div>
        </div>
    </div>

	<script src="<?php echo base_url('assets/js/scripts-bundle.js'); ?>"></script>
	<script src="<?php echo base_url('assets/js/bootstrap-datepicker.js?param=kiriel'); ?>"></script>
	<script src="<?php echo base_url('assets/js/universal-js.js?param=angiethay'); ?>"></script>

	<script>
		var country_codes = <?php echo json_encode($country_codes) ?>;
		var array1 = [];
		var array2 = [];
		for(var i = 0; i < country_codes.length; i++) {
			array1.push('<option value="'+ country_codes[i].CountryCodeID +'">'+ country_codes[i].Country +'</option>');
			array2.push('<option value="'+ country_codes[i].CountryCodeID +'">'+ country_codes[i].Country + ' ' + country_codes[i].CountryCode + '</option>');
		}
		var counter = <?php echo $counter ?>;
		var adult = <?php echo $adult ?>;
		var child = <?php echo $child ?>;
		var infant = <?php echo $infant ?>;
		var guest_list_id = -1;
		var new_guests = [];
		var deleted_guests = [];

		function Set_Required_Field(guest_list_id)
		{
			//Basic Details
			var name = $(`#name-${guest_list_id}`).val();
			var gender = $(`#gender-${guest_list_id}`).val();
			var date_of_birth = $(`#date_of_birth-${guest_list_id}`).val();
			var nationality = $(`#nationality-${guest_list_id}`).val();
			var identification_number = $(`#identification_number-${guest_list_id}`).val();
			var passport_number = $(`#passport_number-${guest_list_id}`).val();
			var country_code = $(`#country_code-${guest_list_id}`).val();
			var mobile = $(`#mobile-${guest_list_id}`).val();
			var email = $(`#email-${guest_list_id}`).val();

			//Travel Insurance
			var marital_status = $(`#marital_status-${guest_list_id}`).val();
			var employment = $(`#employment-${guest_list_id}`).val();
			var address = $(`#address-${guest_list_id}`).val();
			var postcode = $(`#postcode-${guest_list_id}`).val();
			var city = $(`#city-${guest_list_id}`).val();
			var state = $(`#state-${guest_list_id}`).val();
			var country = $(`#country-${guest_list_id}`).val();
			var nominee_name = $(`#nominee_name-${guest_list_id}`).val();
			var nominee_identification_number = $(`#nominee_identification_number-${guest_list_id}`).val();
			var relationship = $(`#relationship-${guest_list_id}`).val();

			if(name != '' || gender != null || date_of_birth != '' || nationality != null || identification_number != '' || passport_number != '' || country_code != null || mobile != '' || email != '' || marital_status != null || employment != '' || address != '' || postcode != '' || city != '' || state != '' || country != null || nominee_name != '' || nominee_identification_number != '' || relationship != '') {
				$(`#name_label-${guest_list_id}`).html('Name <span style="color:red;">*</span>');
				$(`#name-${guest_list_id}`).prop('required', 'true');
				$(`#gender_label-${guest_list_id}`).html('Gender <span style="color:red;">*</span>');
				$(`#gender-${guest_list_id}`).prop('required', 'true');
				$(`#date_of_birth_label-${guest_list_id}`).html('Date Of Birth <span style="color:red;">*</span>');
				$(`#date_of_birth-${guest_list_id}`).prop('required', 'true');
				$(`#nationality_label-${guest_list_id}`).html('Nationality <span style="color:red;">*</span>');
				$(`#nationality-${guest_list_id}`).prop('required', 'true');
				$(`#email_label-${guest_list_id}`).html('Email <span style="color:red;">*</span>');
				$(`#email-${guest_list_id}`).prop('required', 'true');
				$(`#country_code_label-${guest_list_id}`).html('Country Code <span style="color:red;">*</span>');
				$(`#country_code-${guest_list_id}`).prop('required', 'true');
				$(`#mobile_label-${guest_list_id}`).html('Mobile <span style="color:red;">*</span>');
				$(`#mobile-${guest_list_id}`).prop('required', 'true');

				//Nationality
				if(nationality != null) {
					if($(`#nationality-${guest_list_id} option:selected`).text() == 'MALAYSIA') {
						$(`#identification_number_label-${guest_list_id}`).html('Identification Number <span style="color:red;">*</span>');
						$(`#identification_number-${guest_list_id}`).prop('required', 'true');
						$(`#passport_number_label-${guest_list_id}`).html('Passport Number');
						$(`#passport_number-${guest_list_id}`).removeAttr('required');
					} else {
						$(`#passport_number_label-${guest_list_id}`).html('Passport Number <span style="color:red;">*</span>');
						$(`#passport_number-${guest_list_id}`).prop('required', 'true');
						$(`#identification_number_label-${guest_list_id}`).html('Identification Number');
						$(`#identification_number-${guest_list_id}`).removeAttr('required');
					}
				}

				//Travel Insurance
				<?php if($guest_lists[0]->TravelInsuranceStatus == 'Y') { ?>
					$(`#marital_status_label-${guest_list_id}`).html('Marital Status <span style="color:red;">*</span>');
					$(`#marital_status-${guest_list_id}`).prop('required', 'true');
					$(`#employment_label-${guest_list_id}`).html('Employment <span style="color:red;">*</span>');
					$(`#employment-${guest_list_id}`).prop('required', 'true');
					$(`#address_label-${guest_list_id}`).html('Address <span style="color:red;">*</span>');
					$(`#address-${guest_list_id}`).prop('required', 'true');
					$(`#postcode_label-${guest_list_id}`).html('Postcode <span style="color:red;">*</span>');
					$(`#postcode-${guest_list_id}`).prop('required', 'true');
					$(`#city_label-${guest_list_id}`).html('City <span style="color:red;">*</span>');
					$(`#city-${guest_list_id}`).prop('required', 'true');
					$(`#state_label-${guest_list_id}`).html('State <span style="color:red;">*</span>');
					$(`#state-${guest_list_id}`).prop('required', 'true');
					$(`#country_label-${guest_list_id}`).html('Country <span style="color:red;">*</span>');
					$(`#country-${guest_list_id}`).prop('required', 'true');
					$(`#nominee_name_label-${guest_list_id}`).html('Nominee Name <span style="color:red;">*</span>');
					$(`#nominee_name-${guest_list_id}`).prop('required', 'true');
					$(`#nominee_identification_number_label-${guest_list_id}`).html('Nominee Identification Number <span style="color:red;">*</span>');
					$(`#nominee_identification_number-${guest_list_id}`).prop('required', 'true');
					$(`#relationship_label-${guest_list_id}`).html('Relationship <span style="color:red;">*</span>');
					$(`#relationship-${guest_list_id}`).prop('required', 'true');
				<?php } ?>
			} else {
				$(`#name_label-${guest_list_id}`).html('Name');
				$(`#name-${guest_list_id}`).removeAttr('required');
				$(`#gender_label-${guest_list_id}`).html('Gender');
				$(`#gender-${guest_list_id}`).removeAttr('required');
				$(`#date_of_birth_label-${guest_list_id}`).html('Date Of Birth');
				$(`#date_of_birth-${guest_list_id}`).removeAttr('required');
				$(`#nationality_label-${guest_list_id}`).html('Nationality');
				$(`#nationality-${guest_list_id}`).removeAttr('required');
				$(`#email_label-${guest_list_id}`).html('Email');
				$(`#email-${guest_list_id}`).removeAttr('required');
				$(`#country_code_label-${guest_list_id}`).html('Country Code');
				$(`#country_code-${guest_list_id}`).removeAttr('required');
				$(`#mobile_label-${guest_list_id}`).html('Mobile');
				$(`#mobile-${guest_list_id}`).removeAttr('required');

				//Travel Insurance
				<?php if($guest_lists[0]->TravelInsuranceStatus == 'Y') { ?>
					$(`#marital_status_label-${guest_list_id}`).html('Marital Status');
					$(`#marital_status-${guest_list_id}`).removeAttr('required');
					$(`#employment_label-${guest_list_id}`).html('Employment');
					$(`#employment-${guest_list_id}`).removeAttr('required');
					$(`#address_label-${guest_list_id}`).html('Address');
					$(`#address-${guest_list_id}`).removeAttr('required');
					$(`#postcode_label-${guest_list_id}`).html('Postcode');
					$(`#postcode-${guest_list_id}`).removeAttr('required');
					$(`#city_label-${guest_list_id}`).html('City');
					$(`#city-${guest_list_id}`).removeAttr('required');
					$(`#state_label-${guest_list_id}`).html('State');
					$(`#state-${guest_list_id}`).removeAttr('required');
					$(`#country_label-${guest_list_id}`).html('Country');
					$(`#country-${guest_list_id}`).removeAttr('required');
					$(`#nominee_name_label-${guest_list_id}`).html('Nominee Name');
					$(`#nominee_name-${guest_list_id}`).removeAttr('required');
					$(`#nominee_identification_number_label-${guest_list_id}`).html('Nominee Identification Number');
					$(`#nominee_identification_number-${guest_list_id}`).removeAttr('required');
					$(`#relationship_label-${guest_list_id}`).html('Relationship');
					$(`#relationship-${guest_list_id}`).removeAttr('required');
				<?php } ?>
			}
		}
		
		$('#create_guest').click(function() {
			$('#benchmark').append('<div id="guest-'+ guest_list_id +'" class="col-md-6">' +
				'<div class="card card-custom gutter-b" style="border:1px solid lightgray;">' +
					'<div class="card-header" style="background-color:#D7E2F2;">' +
						'<div class="card-title w-100">' +
							'<h3 class="card-label" style="color:#6082B6;">Guest ' + counter + ' : </h3>' +
							'<select name="new_types[]" id="type-'+ guest_list_id +'" onchange="Update_Pax_Number('+ guest_list_id + ',' + null +')" class="form-control" style="width:25%;">' +
								'<option selected value="ADULT">ADULT</option>' +
								'<option value="CHILD">CHILD</option>' +
								'<option value="INFANT">INFANT</option>' +
							'</select>' +
							'<input type="hidden" id="default_type-'+ guest_list_id +'" value="ADULT">' +
							'<a onclick="Delete_Guest('+ guest_list_id +')" class="btn btn-icon btn-light-danger" style="margin-left:auto;">' +
								'<i class="la la-times"></i>' +
							'</a>' +
						'</div>' +
					'</div>' +
					'<div class="card-body">' +
						'<strong>Basic Details :</strong>' +
						'<br><br>' +
						'<div class="form-group">' +
							'<div class="row">' +
								'<div class="col-md-6 mb-7 mb-md-0">' +
									'<label id="name_label-'+ guest_list_id +'">Name (As per IC/Passport) </label>' +
									'<input type="text" name="new_names[]" id="name-'+ guest_list_id +'" onchange="Set_Required_Field('+ guest_list_id +')" autocomplete="off" class="form-control">' +
								'</div>' +
								'<div class="col-md-6">' +
									'<label id="gender_label-'+ guest_list_id +'">Gender</label>' +
									'<select name="new_genders[]" id="gender-'+ guest_list_id +'" onchange="Set_Required_Field('+ guest_list_id +')" class="form-control">' +
										'<option selected disabled value="">--SELECT GENDER--</option>' +
										'<option value="F">FEMALE</option>' +
										'<option value="M">MALE</option>' +
									'</select>' +
								'</div>' +
							'</div>' +
							'<br>' +
							'<div class="row">' +
								'<div class="col-md-6 mb-7 mb-md-0">' +
									'<label id="date_of_birth_label-'+ guest_list_id +'">Date Of Birth</label>' +
									'<input type="text" name="new_date_of_births[]" id="date_of_birth-'+ guest_list_id +'" onchange="Set_Required_Field('+ guest_list_id +')" autocomplete="off" class="form-control kt_datepicker_4_3">' +
								'</div>' +
								'<div class="col-md-6">' +
									'<label id="nationality_label-'+ guest_list_id +'">Nationality</label>' +
									'<select name="new_nationalities[]" id="nationality-'+ guest_list_id +'" onchange="Set_Required_Field('+ guest_list_id +')" class="form-control">' +
										'<option selected disabled value="">--SELECT NATIONALITY--</option>' + array1 +
									'</select>' +
								'</div>' +
							'</div>' +
							'<br>' +
							'<div class="row">' +
								'<div class="col-md-6 mb-7 mb-md-0">' +
									'<label id="identification_number_label-'+ guest_list_id +'">Identification Number</label>' +
									'<input type="text" name="new_identification_numbers[]" id="identification_number-'+ guest_list_id +'" onchange="Set_Required_Field('+ guest_list_id +')" autocomplete="off" class="form-control">' +
								'</div>' +
								'<div class="col-md-6">' +
									'<label id="passport_number_label-'+ guest_list_id +'">Passport Number</label>' +
									'<input type="text" name="new_passport_numbers[]" id="passport_number-'+ guest_list_id +'" onchange="Set_Required_Field('+ guest_list_id +')" autocomplete="off" class="form-control">' +
								'</div>' +
							'</div>' +
							'<br>' +
							'<div class="row">' +
								'<div class="col-md-6 mb-7 mb-md-0">' +
									'<label id="country_code_label-'+ guest_list_id +'">Country Code</label>' +
									'<select name="new_country_codes[]" id="country_code-'+ guest_list_id +'" onchange="Set_Required_Field('+ guest_list_id +')" class="form-control">' +
										'<option selected disabled value="">--SELECT COUNTRY CODE--</option>' + array2 +
									'</select>' +
								'</div>' +
								'<div class="col-md-6">' +
									'<label id="mobile_label-'+ guest_list_id +'">Mobile</label>' +
									'<input type="text" name="new_mobiles[]" id="mobile-'+ guest_list_id +'" onchange="Set_Required_Field('+ guest_list_id +')" autocomplete="off" class="form-control">' +
								'</div>' +
							'</div>' +
							'<br>' +
							'<div class="row">' +
								'<div class="col-md-6">' +
									'<label id="email_label-'+ guest_list_id +'">Email</label>' +
									'<input type="text" name="new_emails[]" id="email-'+ guest_list_id +'" onchange="Set_Required_Field('+ guest_list_id +')" autocomplete="off" class="form-control">' +
								'</div>' +
							'</div>' +
						'</div>' +
						'<?php if($guest_lists[0]->TravelInsuranceStatus == 'Y') { ?>' +
							'<strong>For Guest Add-On Travel Insurance Only :</strong>' +
							'<br><br>' +
							'<div class="accordion accordion-solid accordion-toggle-plus">' +
								'<div class="card">' +
									'<div class="card-header">' +
										'<div data-toggle="collapse" data-target="#travel_insurance_info-'+ guest_list_id +'" class="card-title collapsed" style="font-size:13px;">Travel Insurance Information</div>' +
									'</div>' +
									'<div id="travel_insurance_info-'+ guest_list_id +'" class="collapse">' +
										'<div class="card-body">' +
											'<div class="form-group">' +
												'<div class="row">' +
													'<div class="col-md-6 mb-7 mb-md-0">' +
														'<label id="marital_status_label-'+ guest_list_id +'">Marital Status</label>' +
														'<select name="new_marital_statuses[]" id="marital_status-'+ guest_list_id +'" onchange="Set_Required_Field('+ guest_list_id +')" class="form-control">' +
															'<option selected disabled value="">--SELECT MARITAL STATUS--</option>' +
															'<option value="SINGLE">SINGLE</option>' +
															'<option value="MARRIED">MARRIED</option>' +
															'<option value="DIVORCED">DIVORCED</option>' +
															'<option value="WIDOW">WIDOW</option>' +
														'</select>' +
													'</div>' +
													'<div class="col-md-6">' +
														'<label id="employment_label-'+ guest_list_id +'">Employment</label>' +
														'<input type="text" name="new_employments[]" id="employment-'+ guest_list_id +'" onchange="Set_Required_Field('+ guest_list_id +')" autocomplete="off" class="form-control">' +
													'</div>' +
												'</div>' +
												'<br>' +
												'<div class="row">' +
													'<div class="col-md-6 mb-7 mb-md-0">' +
														'<label id="address_label-'+ guest_list_id +'">Address</label>' +
														'<input type="text" name="new_addresses[]" id="address-'+ guest_list_id +'" onchange="Set_Required_Field('+ guest_list_id +')" autocomplete="off" class="form-control">' +
													'</div>' +
													'<div class="col-md-6">' +
														'<label id="postcode_label-'+ guest_list_id +'">Postcode</label>' +
														'<input type="text" name="new_postcodes[]" id="postcode-'+ guest_list_id +'" onchange="Set_Required_Field('+ guest_list_id +')" autocomplete="off" class="form-control">' +
													'</div>' +
												'</div>' +
												'<br>' +
												'<div class="row">' +
													'<div class="col-md-6 mb-7 mb-md-0">' +
														'<label id="city_label-'+ guest_list_id +'">City</label>' +
														'<input type="text" name="new_cities[]" id="city-'+ guest_list_id +'" onchange="Set_Required_Field('+ guest_list_id +')" autocomplete="off" class="form-control">' +
													'</div>' +
													'<div class="col-md-6">' +
														'<label id="state_label-'+ guest_list_id +'">State</label>' +
														'<input type="text" name="new_states[]" id="state-'+ guest_list_id +'" onchange="Set_Required_Field('+ guest_list_id +')" autocomplete="off" class="form-control">' +
													'</div>' +
												'</div>' +
												'<br>' +
												'<div class="row">' +
													'<div class="col-md-6 mb-7 mb-md-0">' +
														'<label id="country_label-'+ guest_list_id +'">Country</label>' +
														'<select name="new_countries[]" id="country-'+ guest_list_id +'" onchange="Set_Required_Field('+ guest_list_id +')" class="form-control">' +
															'<option selected disabled value="">--SELECT COUNTRY--</option>' + array1 +
														'</select>' +
													'</div>' +
													'<div class="col-md-6">' +
														'<label id="nominee_name_label-'+ guest_list_id +'">Nominee Name</label>' +
														'<input type="text" name="new_nominee_names[]" id="nominee_name-'+ guest_list_id +'" onchange="Set_Required_Field('+ guest_list_id +')" autocomplete="off" class="form-control">' +
													'</div>' +
												'</div>' +
												'<br>' +
												'<div class="row">' +
													'<div class="col-md-6 mb-7 mb-md-0">' +
														'<label id="nominee_identification_number_label-'+ guest_list_id +'">Nominee Identification Number</label>' +
														'<input type="text" name="new_nominee_identification_numbers[]" id="nominee_identification_number-'+ guest_list_id +'" onchange="Set_Required_Field('+ guest_list_id +')" autocomplete="off" class="form-control">' +
													'</div>' +
													'<div class="col-md-6">' +
														'<label id="relationship_label-'+ guest_list_id +'">Relationship</label>' +
														'<input type="text" name="new_relationships[]" id="relationship-'+ guest_list_id +'" onchange="Set_Required_Field('+ guest_list_id +')" autocomplete="off" class="form-control">' +
														'<p style="color:#FAA0A0; font-size:10px; margin-top:5px;">(must be relative and not in the trip, eg cousin, uncle, sister, brother, father, mother & etc)</p>' +
													'</div>' +
												'</div>' +
											'</div>' +
										'</div>' +
									'</div>' +
								'</div>' +
							'</div>' +
						'<?php } ?>' +
					'</div>' +
				'</div>' +
			'</div>');
			$(`#date_of_birth-${guest_list_id}`).datepicker({
				orientation: 'bottom left',
				todayHighlight: true,
				format: 'dd/mm/yyyy',
				autoclose: true,
				endDate: new Date()
			});
			Update_Pax_Number(guest_list_id, 'C');
			new_guests.push(guest_list_id);
			$('input[name="new_guests"]').val(new_guests);
			guest_list_id--;
			counter++;
		});

		function Delete_Guest(guest_list_id) {
			Update_Pax_Number(guest_list_id, 'D');
			if(guest_list_id > 0) {
				deleted_guests.push(guest_list_id);
				$('input[name="deleted_guests"]').val(deleted_guests);
			} else {
				new_guests = new_guests.filter(function(value) {
					return value != guest_list_id;
				});
				$('input[name="new_guests"]').val(new_guests);
			}
			$(`#guest-${guest_list_id}`).remove();
		}

		function Update_Pax_Number(guest_list_id, action) {
			if(action == null) {
				var default_type = $(`#default_type-${guest_list_id}`).val();
				switch(default_type) {
					case 'ADULT':
						adult--;
						break;
					case 'CHILD':
						child--;
						break;
					case 'INFANT':
						infant--;
				}
			}
			var type = $(`#type-${guest_list_id}`).val();
			var adult_pax = null;
			var child_pax = null;
			var infant_pax = null;
			var pax_number = null;
			switch(type) {
				case 'ADULT':
					if(action == 'C' || action == null) {
						adult++;
					} else {
						adult--;
					}
					break;
				case 'CHILD':
					if(action == 'C' || action == null) {
						child++;
					} else {
						child--;
					}
					break;
				case 'INFANT':
					if(action == 'C' || action == null) {
						infant++;
					} else {
						infant--;
					}
			}
			if(adult != 0) {
				adult_pax = adult == 1 ? adult + ' ADULT ' : adult + ' ADULTS ';
			}
			if(child != 0) {
				child_pax = child == 1 ? child + ' CHILD ' : child + ' CHILDREN ';
			}
			if(infant != 0) {
				infant_pax = infant == 1 ? infant + ' INFANT ' : infant + ' INFANTS ';
			}
			if(adult != 0 && child != 0 && infant != 0) {
				pax_number = adult_pax + '& ' + child_pax + '& ' + infant_pax;
			} else {
				if(adult != 0 && child == 0 && infant != 0) {
					pax_number = adult_pax + '& ' + infant_pax;
				} else {
					if(adult != 0 && child != 0 && infant == 0) {
						pax_number = adult_pax + '& ' + child_pax;
					} else {
						if(adult != 0 && child == 0 && infant == 0) {
							pax_number = adult_pax;
						} else {
							if(adult == 0 && child != 0 && infant != 0) {
								pax_number = child_pax + '& ' + infant_pax;
							} else {
								if(adult == 0 && child == 0 && infant != 0) {
									pax_number = infant_pax;
								} else {
									if(adult == 0 && child != 0 && infant == 0) {
										pax_number = child_pax;
									} else {
										pax_number = '0 Pax';
									}
								}
							}
						}
					}
				}
			}
			$('#pax_number').html('Pax Number : ' + pax_number);
			if(action == null) {
				$(`#default_type-${guest_list_id}`).val(type);
			}
			$('input[name="adult"]').val(adult);
			$('input[name="child"]').val(child);
			$('input[name="infant"]').val(infant);
		}

		$('input[type="submit"]').click(function() {
			$('#form').submit(function(event) {
				event.preventDefault();
				const swalWithBootstrapButtons = Swal.mixin({
					customClass: {
						confirmButton: 'btn btn-light-success m-2',
						cancelButton: 'btn btn-danger m-2'
					},
					buttonsStyling: true
				});
				$.ajax({
					url: '<?php echo base_url('Guest_List/Populate_Form_Data') ?>',
					type: 'post',
					data: { booking_id: <?php echo $guest_lists[0]->BookingID; ?> },
					dataType: 'json',
					success: function(array) { 
						if(array[0].LockStatus != '<?php echo $guest_lists[0]->LockStatus ?>' || array[0].TravelInsuranceStatus != '<?php echo $guest_lists[0]->TravelInsuranceStatus ?>' || array[0].Adult != '<?php echo $adult == "" ? "0" : $adult; ?>' || array[0].Children != '<?php echo $child == "" ? "0" : $child; ?>' || array[0].Infant != '<?php echo $infant == "" ? "0" : $infant; ?>') {
							Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Data Has Been Changed, Page Will Refresh', window.location.href);
							return;
						}
						$.each(array, function(key, value) {
							if(value.Guest != null && $(`#name-${value.GuestListID}`).val() == '') {
								$(`#name-${value.GuestListID}`).val(value.Guest);
							}
							if(value.Gender != null && $(`#gender-${value.GuestListID}`).val() == null) {
								$(`#gender-${value.GuestListID}`).val(value.Gender).change();
							}
							if(value.DateOfBirth != null && $(`#date_of_birth-${value.GuestListID}`).val() == '') {
								$(`#date_of_birth-${value.GuestListID}`).val(value.DateOfBirth);
							}
							if(value.Nationality != null && $(`#nationality-${value.GuestListID}`).val() == null) {
								$(`#nationality-${value.GuestListID}`).val(value.Nationality).change();
							}
							if(value.IdentificationNumber != null && $(`#identification_number-${value.GuestListID}`).val() == '') {
								$(`#identification_number-${value.GuestListID}`).val(value.IdentificationNumber);
							}
							if(value.PassportNumber != null && $(`#passport_number-${value.GuestListID}`).val() == '') {
								$(`#passport_number-${value.GuestListID}`).val(value.PassportNumber);
							}
							if(value.GuestCountryCode != null && $(`#country_code-${value.GuestListID}`).val() == null) {
								$(`#country_code-${value.GuestListID}`).val(value.GuestCountryCode).change();
							}
							if(value.GuestMobile != null && $(`#mobile-${value.GuestListID}`).val() == '') {
								$(`#mobile-${value.GuestListID}`).val(value.GuestMobile);
							}
							if(value.Email != null && $(`#email-${value.GuestListID}`).val() == '') {
								$(`#email-${value.GuestListID}`).val(value.Email);
							}
							if(value.MaritalStatus != null && $(`#marital_status-${value.GuestListID}`).val() == null) {
								$(`#marital_status-${value.GuestListID}`).val(value.MaritalStatus).change();
							}
							if(value.Employment != null && $(`#employment-${value.GuestListID}`).val() == '') {
								$(`#employment-${value.GuestListID}`).val(value.Employment);
							}
							if(value.Address != null && $(`#address-${value.GuestListID}`).val() == '') {
								$(`#address-${value.GuestListID}`).val(value.Address);
							}
							if(value.Postcode != null && $(`#postcode-${value.GuestListID}`).val() == '') {
								$(`#postcode-${value.GuestListID}`).val(value.Postcode);
							}
							if(value.City != null && $(`#city-${value.GuestListID}`).val() == '') {
								$(`#city-${value.GuestListID}`).val(value.City);
							}
							if(value.State != null && $(`#state-${value.GuestListID}`).val() == '') {
								$(`#state-${value.GuestListID}`).val(value.State);
							}
							if(value.Country != null && $(`#country-${value.GuestListID}`).val() == null) {
								$(`#country-${value.GuestListID}`).val(value.Country).change();
							}
							if(value.Nominee != null && $(`#nominee_name-${value.GuestListID}`).val() == '') {
								$(`#nominee_name-${value.GuestListID}`).val(value.Nominee);
							}
							if(value.NomineeIdentificationNumber != null && $(`#nominee_identification_number-${value.GuestListID}`).val() == '') {
								$(`#nominee_identification_number-${value.GuestListID}`).val(value.NomineeIdentificationNumber);
							}
							if(value.Relationship != null && $(`#relationship-${value.GuestListID}`).val() == '') {
								$(`#relationship-${value.GuestListID}`).val(value.Relationship);
							}
						});
						swalWithBootstrapButtons.fire({
							width: 550,
							background: 'url(<?php echo base_url('assets/image/sweetalert.jpg') ?>)',
							icon: 'warning',
							title: 'Update Guest List Information ?',
							confirmButtonText: 'Confirm',
							cancelButtonText: 'Cancel',
							showCancelButton: true
						}).then((action) => {
							if(action.isConfirmed) {
								$('#form').unbind('submit');
								$('#form').submit();
							}
						});
					},
					error: function() {
						Display_Message('<?php echo base_url('assets/image/sweetalert.jpg') ?>', 'Guest List Could Not Be Updated', null);
					}
				});
			});
		});
		
		<?php if($guest_lists[0]->LockStatus == 'N') { ?>
			function countdown(elementName, minutes, seconds)
			{
				var element, endTime, hours, mins, msLeft, time;

				function twoDigits(n)
				{
					return (n <= 9 ? "0" + n : n);
				}

				function updateTimer()
				{
					msLeft = endTime - (+ new Date);
					if(msLeft < 120000) {
						swal.fire({
							width: 550,
							background: 'url(<?php echo base_url('assets/image/sweetalert.jpg') ?>)',
							icon: 'question',
							title: 'Time Over Soon, Need More Time ?',
							text: 'By Clicking "No", All Changes Done To The Form WILL NOT Be Saved Automatically',
							confirmButtonText: 'Yes',
							cancelButtonText: 'No',
							showCancelButton: true,
							timer: 120000,
							allowOutsideClick: false
						}).then((action) => {
							if(action.isConfirmed) {
								countdown("timer", 12, 0);
								$.ajax({
									url: '<?php echo base_url('Guest_List/Update_GL_Session_Expiration') ?>',
									type: 'post',
									data: { booking_id: <?php echo $guest_lists[0]->BookingID; ?> },
									success: function() {
										console.log("Nick here loh");
									}
								});
							} else {
								$.ajax({
									url: '<?php echo base_url('Guest_List/Unlock') ?>',
									type: 'post',
									data: { booking_id: <?php echo $guest_lists[0]->BookingID; ?> },
									success: function() {
										window.location.href = '<?php echo base_url('Message?url=' . base_url($_SERVER['REQUEST_URI'])) ?>';
									}
								});
							}
						});
					} else {
						time = new Date(msLeft);
						hours = time.getUTCHours();
						mins = time.getUTCMinutes();
						element.innerHTML = (hours ? hours + ':' + twoDigits(mins) : mins) + ':' + twoDigits(time.getUTCSeconds());
						setTimeout(updateTimer, time.getUTCMilliseconds() + 500);
					}
				}

				element = document.getElementById(elementName);
				endTime = (+ new Date) + 1000 * (60 * minutes + seconds) + 500;
				updateTimer();
			}
			countdown("timer", 10, 0);
		<?php } ?>

		function beforeUnloadHandler(value, url) {
			$.ajax({
				url: '<?php echo base_url('Guest_List/Unlock') ?>',
				type: 'post',
				data: { booking_id: <?php echo $guest_lists[0]->BookingID; ?> },
				success: function(response) {
					console.log("API Response:", response);
					if(value == 'RTB' || value == 'EGL2') {
						window.location.href = url;
					} else {
						window.close();
					}
				}
			});
		}
	</script>

</body>

</html>