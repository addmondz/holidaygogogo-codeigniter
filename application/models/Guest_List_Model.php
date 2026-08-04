<?php
class Guest_List_Model extends CI_Model
{
	function Read_Guest_Lists1()
	{
		$this->db->select('GuestListID, guest_list.BookingID, guest_list.guest_list_room_id, guest_list.CountryCodeID As GuestCountryCode, Type, guest_list.Name As Guest, guest_list.LastName As GuestLastName, guest_list.Gender, DateOfBirth, Nationality, guest_list.IdentificationNumber, guest_list.PassportNumber, guest_list.PassportIssueDate, guest_list.PassportExpiryDate, guest_list.PassportCopy, guest_list.DietaryRequirement, guest_list.Mobile As GuestMobile, guest_list.Email, MaritalStatus, Employment, Address, Postcode, guest_list.City, guest_list.State, guest_list.Country, Nominee, NomineeIdentificationNumber, NomineeContactNumber, Relationship, booking.BookingID, BookingNumber, ReservationNumber, Customer, booking.Mobile As CustomerMobile, StartDate, EndDate, Adult, Children, Infant, booking.ChatLanguage As ChatLanguage, LockStatus, TravelInsuranceStatus, AfterSalesService, GLSessionLock, GLSessionExpiration, booking.Status, admin.CountryCodeID As SalesAgentCountryCode, admin.Name As SalesAgent, admin.Mobile As SalesAgentMobile, admin2.CountryCodeID As SalesAgent2CountryCode, admin2.Name As SalesAgent2, admin2.Mobile As SalesAgent2Mobile, booking.SalesAgentIsPIC, booking.SalesAgent2IsPIC, booking.InsertDate, category.Name As Destination, CountryCode, guest_list_room.room_name As RoomName');
		$this->db->join('guest_list', 'guest_list.BookingID = booking.BookingID', 'left');
		$this->db->join('guest_list_room', 'guest_list_room.id = guest_list.guest_list_room_id', 'left');
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('admin admin2', 'admin2.AdminID = booking.SalesAgent2', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
		$this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
		if(current_url() == base_url('Guest_List/Download') || current_url() == base_url('Guest_List/Download_ZIP')) {
			$this->db->where('booking.BookingID', $this->input->get('booking_id'));
		} else {
        	$this->db->where('Token', $this->input->get('gl'));
		}
		$this->db->where('guest_list.Status', 'Y');
		// Arrange by room: assigned rooms first (natural sorted), unassigned last,
		// then by Type (ADULT first). Keep entry order (GuestListID) within each
		// room/type group so the form does not re-alphabetize guests on reload.
		$this->db->order_by('guest_list_room.room_name IS NULL', 'ASC', FALSE);
		$this->db->order_by('LENGTH(guest_list_room.room_name)', 'ASC', FALSE);
		$this->db->order_by('guest_list_room.room_name', 'ASC');
		$this->db->order_by("FIELD(Type, 'ADULT', 'CHILD', 'INFANT')", 'ASC', FALSE);
		$this->db->order_by('guest_list.GuestListID', 'ASC');
		return $this->db->get('booking')->result();
	}

    function Read_Guest_Lists2()
	{
		$this->db->select('GuestListID, guest_list.CountryCodeID As GuestCountryCode, guest_list.Name As Guest, guest_list.LastName As GuestLastName, guest_list.Gender, DateOfBirth, Nationality, guest_list.IdentificationNumber, guest_list.PassportNumber, guest_list.Mobile As GuestMobile, guest_list.Email, MaritalStatus, Employment, Address, Postcode, guest_list.City, guest_list.State, guest_list.Country, Nominee, NomineeIdentificationNumber, NomineeContactNumber, Relationship, Adult, Children, Infant, LockStatus, TravelInsuranceStatus');
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
		$row = $this->db->get('booking')->row();
		return $row ? $row->BookingID : null;
	}

	function Read_GL_Session_Expiration() {
		$this->db->select('GLSessionExpiration');
		$this->db->where('Token', $this->input->get('gl'));
		return $this->db->get('booking')->row_array();
	}
	
	// $seed lets a caller pre-fill guest_list columns (e.g. the group leader
	// seeded from the booking customer). Only whitelisted, non-reserved keys
	// are honoured so a caller can never overwrite BookingID/Type/audit fields.
	function Create($booking_id, $type, $seed = array())
	{
		$array = array(
			'BookingID' => $booking_id,
			'Type' => $type,
			'InsertBy' => $this->session->userdata('admin_id'),
			'InsertDate' => date('Y-m-d H:i:s')
		);
		if (!empty($seed) && is_array($seed)) {
			$allowed = array('Name', 'LastName', 'Mobile', 'Email', 'CountryCodeID');
			foreach ($allowed as $col) {
				if (isset($seed[$col]) && $seed[$col] !== '') {
					$array[$col] = $seed[$col];
				}
			}
		}
		$this->db->insert('guest_list', $array);
		$this->Sync_Customer_Snapshot($booking_id);
	}

	// Destination country = first product's category country (mirrors the
	// Guest List form's nationality/destination logic).
	private function Destination_Country_Name($booking_id)
	{
		$this->db->select('country_code.Country As CategoryCountryName');
		$this->db->from('booking_product');
		$this->db->join('product', 'product.ProductID = booking_product.ProductID', 'left');
		$this->db->join('category', 'category.CategoryID = product.CategoryID', 'left');
		$this->db->join('country_code', 'country_code.CountryCodeID = category.Country', 'left');
		$this->db->where('booking_product.BookingID', $booking_id);
		$this->db->where('booking_product.Status', 'Y');
		$row = $this->db->get()->row();
		return ($row && !empty($row->CategoryCountryName)) ? strtoupper($row->CategoryCountryName) : '';
	}

	// A passport only applies to non-Malaysians, or Malaysians travelling
	// overseas. Domestic Malaysian trips never carry a passport, so any value
	// posted for them is dropped here rather than persisted. $nationality_id is
	// empty for an unset nationality, which defaults to Malaysia (as the form does).
	private function Passport_Applicable($destination_country_name, $nationality_id)
	{
		$nationality = 'MALAYSIA';
		if(!empty($nationality_id)) {
			$row = $this->db->select('Country')->where('CountryCodeID', $nationality_id)->get('country_code')->row();
			if($row && !empty($row->Country)) {
				$nationality = strtoupper($row->Country);
			}
		}
		$is_malaysian = ($nationality == 'MALAYSIA');
		$going_to_malaysia = ($destination_country_name == 'MALAYSIA');
		return (!$is_malaysian) || ($is_malaysian && !empty($destination_country_name) && !$going_to_malaysia);
	}

	// Null out every passport column on a guest row when a passport doesn't apply.
	private function Clear_Inapplicable_Passport(&$array)
	{
		$array['PassportNumber'] = null;
		$array['PassportIssueDate'] = null;
		$array['PassportExpiryDate'] = null;
		$array['PassportCopy'] = null;
	}

	function Create_Guest($booking_id, $preserve_case = false)
	{
		$upper = function($value) use ($preserve_case) {
			return $preserve_case ? $value : strtoupper($value);
		};
		$destination_country_name = $this->Destination_Country_Name($booking_id);
		for($i = 0; $i < count(explode(',', $this->input->post('new_guests'))); $i++) {
			$array = array(
				'BookingID' => $booking_id,
				'CountryCodeID' => empty($this->input->post('new_country_codes')[$i]) ? null : $this->input->post('new_country_codes')[$i],
				'Type' => $this->input->post('new_types')[$i],
				'Name' => empty($this->input->post('new_names')[$i]) ? null : $upper($this->input->post('new_names')[$i]),
				'LastName' => empty($this->input->post('new_last_names')[$i]) ? null : $upper($this->input->post('new_last_names')[$i]),
				'Gender' => empty($this->input->post('new_genders')[$i]) ? null : $this->input->post('new_genders')[$i],
				'DateOfBirth' => empty($this->input->post('new_date_of_births')[$i]) ? null : date('Y-m-d', strtotime(str_replace('/', '-', $this->input->post('new_date_of_births')[$i]))),
				'Nationality' => empty($this->input->post('new_nationalities')[$i]) ? null : $this->input->post('new_nationalities')[$i],
				'IdentificationNumber' => empty($this->input->post('new_identification_numbers')[$i]) ? null : $this->input->post('new_identification_numbers')[$i],
				'PassportNumber' => empty($this->input->post('new_passport_numbers')[$i]) ? null : $upper($this->input->post('new_passport_numbers')[$i]),
				'PassportIssueDate' => empty($this->input->post('new_passport_issue_dates')[$i]) ? null : (strtotime(str_replace('/', '-', $this->input->post('new_passport_issue_dates')[$i])) !== false ? date('Y-m-d', strtotime(str_replace('/', '-', $this->input->post('new_passport_issue_dates')[$i]))) : null),
				'PassportExpiryDate' => empty($this->input->post('new_passport_expiry_dates')[$i]) ? null : (strtotime(str_replace('/', '-', $this->input->post('new_passport_expiry_dates')[$i])) !== false ? date('Y-m-d', strtotime(str_replace('/', '-', $this->input->post('new_passport_expiry_dates')[$i]))) : null),
				'PassportCopy' => (isset($this->input->post('new_passport_copies')[$i]) && !empty($this->input->post('new_passport_copies')[$i])) ? $this->input->post('new_passport_copies')[$i] : null,
				'DietaryRequirement' => empty($this->input->post('new_dietary_requirements')[$i]) ? null : $this->input->post('new_dietary_requirements')[$i],
				'Mobile' => empty($this->input->post('new_mobiles')[$i]) ? null : $this->input->post('new_mobiles')[$i],
				'Email' => empty($this->input->post('new_emails')[$i]) ? null : $upper($this->input->post('new_emails')[$i]),
				'Employment' => empty($this->input->post('new_employments')[$i]) ? null : $upper($this->input->post('new_employments')[$i]),
				'Address' => empty($this->input->post('new_addresses')[$i]) ? null : $upper($this->input->post('new_addresses')[$i]),
				'Postcode' => empty($this->input->post('new_postcodes')[$i]) ? null : $this->input->post('new_postcodes')[$i],
				'City' => empty($this->input->post('new_cities')[$i]) ? null : $upper($this->input->post('new_cities')[$i]),
				'State' => empty($this->input->post('new_states')[$i]) ? null : $upper($this->input->post('new_states')[$i]),
				'Country' => empty($this->input->post('new_countries')[$i]) ? null : $this->input->post('new_countries')[$i],
				'InsertBy' => $this->session->userdata('admin_id'),
				'InsertDate' => date('Y-m-d H:i:s')
			);
			if(!$this->Passport_Applicable($destination_country_name, $this->input->post('new_nationalities')[$i] ?? null)) {
				$this->Clear_Inapplicable_Passport($array);
			}
			$this->db->insert('guest_list', $array);
		}
		// New guests may be the customer's own record (Gender/DOB/Nationality/Type)
		// — refresh the denormalised customer.* snapshot for this booking's guests.
		$this->Sync_Customer_Snapshot($booking_id);
	}

	/**
	 * Keep the denormalised customer "self guest" snapshot (Gender / DateOfBirth
	 * / Nationality / GuestType) in step after a guest_list write on $booking_id.
	 * Central hook so every guest form save/update/delete refreshes the Customer
	 * List columns in real time. Best-effort: never blocks the guest_list write.
	 */
	private function Sync_Customer_Snapshot($booking_id)
	{
		if (empty($booking_id)) {
			return;
		}
		$this->load->model('Customer_Model');
		$this->Customer_Model->Refresh_Snapshot_By_Booking($booking_id);
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

	function Update($preserve_case = false, $booking_id = null)
	{
		$upper = function($value) use ($preserve_case) {
			return $preserve_case ? $value : strtoupper($value);
		};
		$value = false;
		if(!empty($this->input->post('names'))) {
			$destination_country_name = $this->Destination_Country_Name($booking_id);
			for($i = 0; $i < count($this->input->post('names')); $i++) {
				$array = array(
					'CountryCodeID' => empty($this->input->post('country_codes')[$i]) ? null : $this->input->post('country_codes')[$i],
					'Name' => empty($this->input->post('names')[$i]) ? null : $upper($this->input->post('names')[$i]),
					'LastName' => empty($this->input->post('last_names')[$i]) ? null : $upper($this->input->post('last_names')[$i]),
					'Gender' => empty($this->input->post('genders')[$i]) ? null : $this->input->post('genders')[$i],
					'DateOfBirth' => empty($this->input->post('date_of_births')[$i]) ? null : date('Y-m-d', strtotime(str_replace('/', '-', $this->input->post('date_of_births')[$i]))),
					'Nationality' => empty($this->input->post('nationalities')[$i]) ? null : $this->input->post('nationalities')[$i],
					'IdentificationNumber' => empty($this->input->post('identification_numbers')[$i]) ? null : $this->input->post('identification_numbers')[$i],
					'PassportNumber' => empty($this->input->post('passport_numbers')[$i]) ? null : $upper($this->input->post('passport_numbers')[$i]),
					'PassportIssueDate' => empty($this->input->post('passport_issue_dates')[$i]) ? null : (strtotime(str_replace('/', '-', $this->input->post('passport_issue_dates')[$i])) !== false ? date('Y-m-d', strtotime(str_replace('/', '-', $this->input->post('passport_issue_dates')[$i]))) : null),
					'PassportExpiryDate' => empty($this->input->post('passport_expiry_dates')[$i]) ? null : (strtotime(str_replace('/', '-', $this->input->post('passport_expiry_dates')[$i])) !== false ? date('Y-m-d', strtotime(str_replace('/', '-', $this->input->post('passport_expiry_dates')[$i]))) : null),
					'DietaryRequirement' => empty($this->input->post('dietary_requirements')[$i]) ? null : $this->input->post('dietary_requirements')[$i],
					'Mobile' => empty($this->input->post('mobiles')[$i]) ? null : $this->input->post('mobiles')[$i],
					'Email' => empty($this->input->post('emails')[$i]) ? null : $upper($this->input->post('emails')[$i]),
					'Employment' => empty($this->input->post('employments')[$i]) ? null : $upper($this->input->post('employments')[$i]),
					'Address' => empty($this->input->post('addresses')[$i]) ? null : $upper($this->input->post('addresses')[$i]),
					'Postcode' => empty($this->input->post('postcodes')[$i]) ? null : $this->input->post('postcodes')[$i],
					'City' => empty($this->input->post('cities')[$i]) ? null : $upper($this->input->post('cities')[$i]),
					'State' => empty($this->input->post('states')[$i]) ? null : $upper($this->input->post('states')[$i]),
					'Country' => empty($this->input->post('countries')[$i]) ? null : $this->input->post('countries')[$i]
				);
				// Update PassportCopy if provided in POST (either new upload or existing file)
				if (isset($this->input->post('passport_copies')[$i])) {
					$passport_copy_value = $this->input->post('passport_copies')[$i];
					$array['PassportCopy'] = !empty($passport_copy_value) ? $passport_copy_value : null;
				}
				if(!$this->Passport_Applicable($destination_country_name, $this->input->post('nationalities')[$i] ?? null)) {
					$this->Clear_Inapplicable_Passport($array);
				}
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
		// Refresh the customer snapshot for these guests. $booking_id can be null
		// from some callers, so fall back to resolving it from the first guest row.
		if (empty($booking_id)) {
			$booking_id = $this->Booking_Id_For_Guest($this->input->post('guests')[0] ?? null);
		}
		$this->Sync_Customer_Snapshot($booking_id);
		return $value;
	}

	/** BookingID for a guest_list row, so an update with no booking_id can still
	 *  target the right snapshot. Returns null when the guest id is unknown. */
	private function Booking_Id_For_Guest($guest_list_id)
	{
		$guest_list_id = (int) $guest_list_id;
		if ($guest_list_id < 1) {
			return null;
		}
		$row = $this->db->select('BookingID')
			->get_where('guest_list', array('GuestListID' => $guest_list_id))->row();
		return $row ? $row->BookingID : null;
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
		// Capture the booking BEFORE the soft-delete so we can refresh the
		// customer snapshot (the deleted row may have been the self record).
		$booking_id = $this->Booking_Id_For_Guest($guest_list_id);
		$array = array(
			'Status' => 'N',
			'UpdateBy' => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s')
		);
		$this->db->where('GuestListID', $guest_list_id);
		$this->db->update('guest_list', $array);
		$this->Sync_Customer_Snapshot($booking_id);
	}

	function Update_Passport_Copy($guest_list_id, $filename)
	{
		$this->db->where('GuestListID', $guest_list_id);
		$this->db->update('guest_list', array(
			'PassportCopy' => !empty($filename) ? $filename : null,
			'UpdateBy' => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s')
		));
	}

	function Are_All_Guests_Complete($booking_id)
	{
		$this->db->where('BookingID', $booking_id);
		$this->db->where('Status', 'Y');
		$guests = $this->db->get('guest_list')->result();

		if (empty($guests)) {
			return false;
		}

		// Expected pax: room management is primary; fall back to booking PDF pax
		// counts only when no rooms are configured.
		$this->load->model('Guest_List_Room_Model');
		$rooms = $this->Guest_List_Room_Model->Read_Rooms_By_Booking_ID($booking_id);
		$expected = 0;
		if (!empty($rooms)) {
			foreach ($rooms as $room) {
				$expected += (int)$room->adult_count + (int)$room->child_count + (int)$room->infant_count;
			}
		} else {
			$this->db->select('Adult, Children, Infant');
			$this->db->where('BookingID', $booking_id);
			$booking = $this->db->get('booking')->row();
			if (!empty($booking)) {
				$expected = (int)$booking->Adult + (int)$booking->Children + (int)$booking->Infant;
			}
		}

		if ($expected <= 0) {
			return false;
		}
		if (count($guests) < $expected) {
			return false;
		}

		$this->load->helper('guest_complete');
		foreach ($guests as $guest) {
			// Contact (Email/Mobile/CountryCode) is optional for child guests.
			if (!guest_row_is_complete($guest)) {
				return false;
			}
		}

		return true;
	}

	function Read_Guests_By_Booking_ID($booking_id)
	{
		$this->db->select('GuestListID, Name, LastName, Type, guest_list_room_id');
		$this->db->where('BookingID', $booking_id);
		$this->db->where('Status', 'Y');
		$this->db->order_by('Type', 'ASC');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('guest_list')->result();
	}

	function Auto_Assign_Rooms($booking_id)
	{
		$this->load->model('Guest_List_Room_Model');
		$rooms = $this->Guest_List_Room_Model->Read_Rooms_By_Booking_ID($booking_id);

		if (empty($rooms)) return;

		$types = array('ADULT' => 'adult_count', 'CHILD' => 'child_count', 'INFANT' => 'infant_count');

		foreach ($types as $type => $count_field) {
			// Per-room remaining capacity for this type, keyed by room id.
			$remaining = array();
			foreach ($rooms as $room) {
				$remaining[$room->id] = (int)$room->$count_field;
			}

			$this->db->select('GuestListID, guest_list_room_id');
			$this->db->where('BookingID', $booking_id);
			$this->db->where('Type', $type);
			$this->db->where('Status', 'Y');
			$this->db->order_by('GuestListID', 'ASC');
			$guests = $this->db->get('guest_list')->result();

			// Pass 1: honor existing assignments when the room still has capacity.
			// Leaves $pending holding guests that need a fresh room.
			$pending = array();
			$keep = array();
			foreach ($guests as $guest) {
				$current = $guest->guest_list_room_id;
				if ($current !== null && isset($remaining[$current]) && $remaining[$current] > 0) {
					$remaining[$current]--;
					$keep[$guest->GuestListID] = $current;
				} else {
					$pending[] = $guest;
				}
			}

			// Pass 2: fill pending guests into rooms in natural-sort order.
			$assignments = $keep;
			$room_index = 0;
			foreach ($pending as $guest) {
				$assigned_room_id = null;
				while ($room_index < count($rooms)) {
					$rid = $rooms[$room_index]->id;
					if ($remaining[$rid] > 0) {
						$assigned_room_id = $rid;
						$remaining[$rid]--;
						break;
					}
					$room_index++;
				}
				$assignments[$guest->GuestListID] = $assigned_room_id;
			}

			// Write only rows where assignment actually changed.
			foreach ($guests as $guest) {
				$new_room_id = isset($assignments[$guest->GuestListID]) ? $assignments[$guest->GuestListID] : null;
				if ($new_room_id != $guest->guest_list_room_id) {
					$this->db->where('GuestListID', $guest->GuestListID);
					$this->db->update('guest_list', array('guest_list_room_id' => $new_room_id));
				}
			}
		}
	}
}