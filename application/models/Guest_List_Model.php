<?php
class Guest_List_Model extends CI_Model
{
	function Read_Guest_Lists1()
	{
		$this->db->select('GuestListID, guest_list.BookingID, guest_list.CountryCodeID As GuestCountryCode, Type, guest_list.Name As Guest, guest_list.LastName As GuestLastName, guest_list.Gender, DateOfBirth, Nationality, guest_list.IdentificationNumber, guest_list.PassportNumber, guest_list.Mobile As GuestMobile, guest_list.Email, MaritalStatus, Employment, Address, Postcode, guest_list.City, guest_list.State, guest_list.Country, Nominee, NomineeIdentificationNumber, Relationship, booking.BookingID, BookingNumber, ReservationNumber, Customer, booking.Mobile As CustomerMobile, StartDate, EndDate, Adult, Children, Infant, ChatLanguage, LockStatus, TravelInsuranceStatus, AfterSalesService, GLSessionLock, GLSessionExpiration, booking.Status, admin.CountryCodeID As SalesAgentCountryCode, admin.Name As SalesAgent, admin.Mobile As SalesAgentMobile, category.Name As Destination, CountryCode');
		$this->db->join('guest_list', 'guest_list.BookingID = booking.BookingID', 'left');
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
		$this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
		if(current_url() == base_url('Guest_List/Download')) {
			$this->db->where('booking.BookingID', $this->input->get('booking_id'));
		} else {
        	$this->db->where('Token', $this->input->get('gl'));
		}
		$this->db->where('guest_list.Status', 'Y');
		$this->db->order_by('Type', 'ASC');
		return $this->db->get('booking')->result();
	}

    function Read_Guest_Lists2()
	{
		$this->db->select('GuestListID, guest_list.CountryCodeID As GuestCountryCode, guest_list.Name As Guest, guest_list.LastName As GuestLastName, guest_list.Gender, DateOfBirth, Nationality, guest_list.IdentificationNumber, guest_list.PassportNumber, guest_list.Mobile As GuestMobile, guest_list.Email, MaritalStatus, Employment, Address, Postcode, guest_list.City, guest_list.State, guest_list.Country, Nominee, NomineeIdentificationNumber, Relationship, Adult, Children, Infant, LockStatus, TravelInsuranceStatus');
		$this->db->join('guest_list', 'guest_list.BookingID = booking.BookingID', 'left');
        $this->db->where('booking.BookingID', $this->input->post('booking_id'));
		$this->db->where('guest_list.Status', 'Y');
		$this->db->order_by('Type', 'ASC');
		return $this->db->get('booking')->result();
	}
	
	function Read_Country_Codes()
	{
		$this->db->select('CountryCodeID, Country, CountryCode');
		$this->db->where('Status', 'Y');
		$this->db->order_by('Country', 'ASC');
		return $this->db->get('country_code')->result();
	}

	function Read_Booking_ID() {
		$this->db->select('BookingID');
		$this->db->where('Token', $this->input->get('gl'));
		return $this->db->get('booking')->row()->BookingID;
	}

	function Read_GL_Session_Expiration() {
		$this->db->select('GLSessionExpiration');
		$this->db->where('Token', $this->input->get('gl'));
		return $this->db->get('booking')->row_array();
	}
	
	function Create($booking_id, $type)
	{
		$array = array(
			'BookingID' => $booking_id,
			'Type' => $type,
			'InsertBy' => $this->session->userdata('admin_id'),
			'InsertDate' => date('Y-m-d H:i:s')
		);
		$this->db->insert('guest_list', $array);
	}

	function Create_Guest($booking_id)
	{
		for($i = 0; $i < count(explode(',', $this->input->post('new_guests'))); $i++) {
			$array = array(
				'BookingID' => $booking_id,
				'CountryCodeID' => empty($this->input->post('new_country_codes')[$i]) ? null : $this->input->post('new_country_codes')[$i],
				'Type' => $this->input->post('new_types')[$i],
				'Name' => empty($this->input->post('new_names')[$i]) ? null : strtoupper($this->input->post('new_names')[$i]),
				'LastName' => empty($this->input->post('new_last_names')[$i]) ? null : strtoupper($this->input->post('new_last_names')[$i]),
				'Gender' => empty($this->input->post('new_genders')[$i]) ? null : $this->input->post('new_genders')[$i],
				'DateOfBirth' => empty($this->input->post('new_date_of_births')[$i]) ? null : date('Y-m-d', strtotime(str_replace('/', '-', $this->input->post('new_date_of_births')[$i]))),
				'Nationality' => empty($this->input->post('new_nationalities')[$i]) ? null : $this->input->post('new_nationalities')[$i],
				'IdentificationNumber' => empty($this->input->post('new_identification_numbers')[$i]) ? null : $this->input->post('new_identification_numbers')[$i],
				'PassportNumber' => empty($this->input->post('new_passport_numbers')[$i]) ? null : strtoupper($this->input->post('new_passport_numbers')[$i]),
				'Mobile' => empty($this->input->post('new_mobiles')[$i]) ? null : $this->input->post('new_mobiles')[$i],
				'Email' => empty($this->input->post('new_emails')[$i]) ? null : strtoupper($this->input->post('new_emails')[$i]),
				'MaritalStatus' => empty($this->input->post('new_marital_statuses')[$i]) ? null : $this->input->post('new_marital_statuses')[$i],
				'Employment' => empty($this->input->post('new_employments')[$i]) ? null : strtoupper($this->input->post('new_employments')[$i]),
				'Address' => empty($this->input->post('new_addresses')[$i]) ? null : strtoupper($this->input->post('new_addresses')[$i]),
				'Postcode' => empty($this->input->post('new_postcodes')[$i]) ? null : $this->input->post('new_postcodes')[$i],
				'City' => empty($this->input->post('new_cities')[$i]) ? null : strtoupper($this->input->post('new_cities')[$i]),
				'State' => empty($this->input->post('new_states')[$i]) ? null : strtoupper($this->input->post('new_states')[$i]),
				'Country' => empty($this->input->post('new_countries')[$i]) ? null : $this->input->post('new_countries')[$i],
				'Nominee' => empty($this->input->post('new_nominee_names')[$i]) ? null : strtoupper($this->input->post('new_nominee_names')[$i]),
				'NomineeIdentificationNumber' => empty($this->input->post('new_nominee_identification_numbers')[$i]) ? null : $this->input->post('new_nominee_identification_numbers')[$i],
				'Relationship' => empty($this->input->post('new_relationships')[$i]) ? null : strtoupper($this->input->post('new_relationships')[$i]),
				'InsertBy' => $this->session->userdata('admin_id'),
				'InsertDate' => date('Y-m-d H:i:s')
			);
			$this->db->insert('guest_list', $array);
		}
	}

	function Create_Guest_List_Log($booking_id)
	{
		$array = array(
			'BookingID' => $booking_id,
			'IP' => $this->input->ip_address(),
			'UserAgent' => $this->agent->agent_string(),
			'InsertDate' => date('Y-m-d H:i:s')
		);
		$this->db->insert('guest_list_log', $array);
	}

	function Update()
	{
		$value = false;
		if(!empty($this->input->post('names'))) {
			for($i = 0; $i < count($this->input->post('names')); $i++) {
				$array = array(
					'CountryCodeID' => empty($this->input->post('country_codes')[$i]) ? null : $this->input->post('country_codes')[$i],
					'Name' => empty($this->input->post('names')[$i]) ? null : strtoupper($this->input->post('names')[$i]),
					'LastName' => empty($this->input->post('last_names')[$i]) ? null : strtoupper($this->input->post('last_names')[$i]),
					'Gender' => empty($this->input->post('genders')[$i]) ? null : $this->input->post('genders')[$i],
					'DateOfBirth' => empty($this->input->post('date_of_births')[$i]) ? null : date('Y-m-d', strtotime(str_replace('/', '-', $this->input->post('date_of_births')[$i]))),
					'Nationality' => empty($this->input->post('nationalities')[$i]) ? null : $this->input->post('nationalities')[$i],
					'IdentificationNumber' => empty($this->input->post('identification_numbers')[$i]) ? null : $this->input->post('identification_numbers')[$i],
					'PassportNumber' => empty($this->input->post('passport_numbers')[$i]) ? null : strtoupper($this->input->post('passport_numbers')[$i]),
					'Mobile' => empty($this->input->post('mobiles')[$i]) ? null : $this->input->post('mobiles')[$i],
					'Email' => empty($this->input->post('emails')[$i]) ? null : strtoupper($this->input->post('emails')[$i]),
					'MaritalStatus' => empty($this->input->post('marital_statuses')[$i]) ? null : $this->input->post('marital_statuses')[$i],
					'Employment' => empty($this->input->post('employments')[$i]) ? null : strtoupper($this->input->post('employments')[$i]),
					'Address' => empty($this->input->post('addresses')[$i]) ? null : strtoupper($this->input->post('addresses')[$i]),
					'Postcode' => empty($this->input->post('postcodes')[$i]) ? null : $this->input->post('postcodes')[$i],
					'City' => empty($this->input->post('cities')[$i]) ? null : strtoupper($this->input->post('cities')[$i]),
					'State' => empty($this->input->post('states')[$i]) ? null : strtoupper($this->input->post('states')[$i]),
					'Country' => empty($this->input->post('countries')[$i]) ? null : $this->input->post('countries')[$i],
					'Nominee' => empty($this->input->post('nominee_names')[$i]) ? null : strtoupper($this->input->post('nominee_names')[$i]),
					'NomineeIdentificationNumber' => empty($this->input->post('nominee_identification_numbers')[$i]) ? null : $this->input->post('nominee_identification_numbers')[$i],
					'Relationship' => empty($this->input->post('relationships')[$i]) ? null : strtoupper($this->input->post('relationships')[$i])
				);
				$this->db->where('GuestListID', $this->input->post('guests')[$i]);
				$this->db->update('guest_list', $array);
				if($this->db->affected_rows() > 0) {
					$this->db->set('UpdateDate', date('Y-m-d H:i:s'));
					$this->db->where('GuestListID', $this->input->post('guests')[$i]);
					$this->db->update('guest_list');
					$value = true;
				}
			}
		}
		return $value;
	}

	function Update_GL_Session($column, $value, $gl_session_lock, $gl_session_expiration)
	{
		$array = array(
			'GLSessionLock' => $gl_session_lock,
			'GLSessionExpiration' => $gl_session_expiration
		);
		$this->db->where($column, $value);
		$this->db->update('booking', $array);
	}
	
	function Delete($guest_list_id)
	{
		$array = array(
			'Status' => 'N',
			'UpdateBy' => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s')
		);
		$this->db->where('GuestListID', $guest_list_id);
		$this->db->update('guest_list', $array);
	}
}