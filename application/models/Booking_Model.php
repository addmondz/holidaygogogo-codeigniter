<?php
class Booking_Model extends CI_Model
{
	function Read_Booking()
	{
		$this->db->select('booking.BookingID, booking.AllowReview, booking.CustomerReview, booking.CustomerReviewTimestamp, BookingConfirmationFooterID, TravelVoucherFooterID, booking.CountryCodeID AS CustomerCountryCode, BookingNumber, ReservationNumber, DepositDeadline, FullPaymentDeadline, AdditionalPaymentDeadline, Customer, booking.Mobile AS CustomerMobile, StartDate, EndDate, Adult, Children, Infant, Destination, SalesAgent, Tag, BookingRemark, Subtotal, Discount, NetTotal, booking.ChatLanguage, Source, Token, booking.BookingConfirmationTitle, BookingConfirmationFooter, TravelVoucherFooter, ProductSequence, admin.Name AS SalesAgentName, booking.AutocountSyncStatus, booking.AutocountSyncMessage, booking.AutocountSyncAction, booking.CustomerAutocountSyncStatus, booking.CustomerAutocountSyncMessage, booking.CustomerAutocountSyncAction, customer.CustomerCode AS CustomerCode, booking.CustomerID');
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('customer', 'customer.CustomerID = booking.CustomerID', 'left');
		$this->db->where('booking.BookingID', $this->input->get('booking_id'));

		return $this->db->get('booking')->row_array();
	}


	function Read_All_Bookings() 
	{

		$this->db->select('booking.BookingID, BookingNumber, DepositDeadline, FullPaymentDeadline, Customer, booking.Mobile As CustomerMobile, StartDate, EndDate, NetTotal, booking.ChatLanguage, Token, booking.BookingConfirmationTitle, CancelStatus, LockStatus, AfterSalesService, booking.Status, booking.InsertDate, admin.Name As SalesAgentName, category.Name As DestinationName, CountryCode, booking.AutocountSyncStatus, booking.AutocountSyncMessage, booking.AutocountSyncAction, booking.CustomerAutocountSyncStatus, booking.CustomerAutocountSyncMessage, booking.CustomerAutocountSyncAction, customer.CustomerCode, booking.CustomerID');
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
		$this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
		$this->db->join('customer', 'customer.CustomerID = booking.CustomerID', 'left');
		if($this->session->userdata('level') == 20) {
			$this->db->where('SalesAgent', $this->session->userdata('admin_id'));
		}
		
		$this->db->where('booking.Status !=', 'N');
		$this->db->order_by('booking.BookingID', 'DESC');

		return $this->db->get('booking')->result();
	}
	
	function Read_Bookings()
	{
		$this->db->select('booking.BookingID, BookingNumber, DepositDeadline, FullPaymentDeadline, Customer, booking.Mobile As CustomerMobile, StartDate, EndDate, NetTotal, booking.ChatLanguage, Token, booking.BookingConfirmationTitle, CancelStatus, LockStatus, AfterSalesService, booking.Status, booking.InsertDate, admin.Name As SalesAgentName, category.Name As DestinationName, CountryCode, booking.AutocountSyncStatus, booking.AutocountSyncMessage, booking.AutocountSyncAction, booking.CustomerAutocountSyncStatus, booking.CustomerAutocountSyncMessage, booking.CustomerAutocountSyncAction, customer.CustomerCode, booking.CustomerID');
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
		$this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
		$this->db->join('customer', 'customer.CustomerID = booking.CustomerID', 'left');
		if($this->session->userdata('level') == 20) {
			$this->db->where('SalesAgent', $this->session->userdata('admin_id'));
		}

		// These filters can ignore others
		$ignore = 0;

		if(!empty($this->input->get('customer'))) {
			//$this->db->where('Customer', $this->input->get('customer'));
			$this->db->like('Customer', $this->input->get('customer'));
			$ignore = 1;
		}

		if(!empty($this->input->get('booking_number'))) {
			$this->db->where('BookingNumber', $this->input->get('booking_number'));
			$ignore = 1;
		}

		if(!empty($this->input->get('reservation_number'))) {
			$this->db->where('ReservationNumber', $this->input->get('reservation_number'));
			$ignore = 1;
		}

		if($ignore == 0) {

			// Second level ignore
			$level2Ignore = 0;

			if(!empty($this->input->get('deadline'))) {
				$deadline = explode(' - ', $this->input->get('deadline'));
				$start_date = date('Y-m-d', strtotime(str_replace('/', '-', $deadline[0])));
				$end_date = date('Y-m-d', strtotime(str_replace('/', '-', $deadline[1])));
				$this->db->where("((`DepositDeadline` >= '".$start_date."' AND `DepositDeadline` <= '".$end_date."') OR (`FullPaymentDeadline` >= '".$start_date."' AND `FullPaymentDeadline` <= '".$end_date."') OR (`AdditionalPaymentDeadline` >= '".$start_date."' AND `AdditionalPaymentDeadline` <= '".$end_date."')) AND `booking`.`Status` IN ('P','PP')");
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('mobile'))) {
				$this->db->where('booking.Mobile', $this->input->get('mobile'));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('travel_date'))) {
				$travel_date = explode(' - ', $this->input->get('travel_date'));
				$start_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[0])));
				$end_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[1])));
				$this->db->where("((`StartDate` <= '".$start_date."' AND `EndDate` >= '".$end_date."') OR (`StartDate` >= '".$start_date."' AND `StartDate` <= '".$end_date."') OR (`EndDate` >= '".$start_date."' AND `EndDate` <= '".$end_date."'))");
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('destination'))) {
				$this->db->where('Destination', $this->input->get('destination'));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('sales_agent'))) {
				$this->db->where('SalesAgent', $this->input->get('sales_agent'));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('tag'))) {
				$this->db->where("FIND_IN_SET('".$this->input->get('tag')."', Tag)");
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('chat_language'))) {
				$this->db->where('booking.ChatLanguage', $this->input->get('chat_language'));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('source'))) {
				$this->db->where('Source', $this->input->get('source'));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('booking_confirmation_title'))) {
				$this->db->where('booking.BookingConfirmationTitle', $this->input->get('booking_confirmation_title'));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('autocount_status'))) {
				$this->db->where('booking.AutocountSyncStatus', $this->input->get('autocount_status'));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('status'))) {
				if($this->input->get('status') == 'A') {
					$this->db->where('CancelStatus', 'N');
					$this->db->where('booking.Status !=', 'N');
				}
				if($this->input->get('status') == 'C') {
					$this->db->where('CancelStatus', 'Y');
					$this->db->where('booking.Status !=', 'N');
				}
				if($this->input->get('status') == 'Y') {
					$this->db->where('CancelStatus', 'N');
					$this->db->where('AfterSalesService', 'COMPLETE');
					$this->db->where('booking.Status', 'Y');
				}
				if($this->input->get('status') == 'OG') {
					$this->db->where('CancelStatus', 'N');
					$this->db->where('booking.Status', 'OG');
				}
				if($this->input->get('status') == 'PP') {
					$this->db->where('CancelStatus', 'N');
					$this->db->where('FullPaymentDeadline >=', date('Y-m-d'));
					$this->db->where('booking.Status', 'PP');
				}
				if($this->input->get('status') == 'PO') {
					$this->db->where('CancelStatus', 'N');
					$this->db->where("((`FullPaymentDeadline` < '".date('Y-m-d')."' AND `booking`.`Status` IN ('P','PP')) OR ((`DepositDeadline` < '".date('Y-m-d')."' AND `booking`.`Status` = 'P') OR (`FullPaymentDeadline` < '".date('Y-m-d')."' AND `booking`.`Status` IN ('P','PP'))))");
				}
				if($this->input->get('status') == 'PGL') {
					$this->db->where('CancelStatus', 'N');
					$this->db->where('LockStatus', 'N');
					$this->db->where('booking.Status', 'PTV');
				}
				if($this->input->get('status') == 'PBC') {
					$this->db->where('CancelStatus', 'N');
					$this->db->where('booking.Status', 'PBC');
				}
				if($this->input->get('status') == 'P') {
					$this->db->where('CancelStatus', 'N');
					$this->db->where("(`DepositDeadline` >= '".date('Y-m-d')."' OR (`DepositDeadline` IS NULL AND `FullPaymentDeadline` >= '".date('Y-m-d')."'))");
					$this->db->where('booking.Status', 'P');
				}
				if($this->input->get('status') == 'PR') {
					$this->db->where('CancelStatus', 'N');
					$this->db->where('AfterSalesService', 'PENDING');
					$this->db->where('booking.Status', 'Y');
				}
				if($this->input->get('status') == 'PT') {
					$this->db->where('CancelStatus', 'N');
					$this->db->where('booking.Status', 'PT');
				}
				if($this->input->get('status') == 'PTV') {
					$this->db->where('CancelStatus', 'N');
					$this->db->where('LockStatus', 'Y');
					$this->db->where('booking.Status', 'PTV');
				}

				$level2Ignore = 1;
			} else {
				$this->db->where('CancelStatus', 'N');
				$this->db->where('AfterSalesService', 'PENDING');
			}
		}

		if(!empty($this->input->get('booking_date'))) {
			$booking_date = explode(' - ', $this->input->get('booking_date'));
			$start_date = date('Y-m-d', strtotime(str_replace('/', '-', $booking_date[0])));
			$end_date = date('Y-m-d', strtotime(str_replace('/', '-', $booking_date[1])));
			$this->db->where('CAST(booking.InsertDate AS DATE) >=', $start_date);
			$this->db->where('CAST(booking.InsertDate AS DATE) <=', $end_date);
		} else {

			if($ignore == 0 && isset($level2Ignore) && $level2Ignore == 0) {
				$this->db->where('CAST(booking.InsertDate AS DATE) >=', date('Y-m-d', strtotime('-7 days')));
				$this->db->where('CAST(booking.InsertDate AS DATE) <=', date('Y-m-d'));
			}
		}
		
		$this->db->where('booking.Status !=', 'N');
		$this->db->order_by('booking.BookingID', 'DESC');

		return $this->db->get('booking')->result();
	}

	function Read_Bookings_With_Guest_Lists($group_by_booking_id)
	{
		$this->db->select('booking.BookingID, BookingNumber, ReservationNumber, DepositDeadline, FullPaymentDeadline, Customer, booking.Mobile As CustomerMobile, StartDate, EndDate, Adult, Children, Infant, BookingRemark, Subtotal, Discount, NetTotal, ChatLanguage, CancelStatus, LockStatus, AfterSalesService, booking.Status, booking.InsertDate, guest_list.CountryCodeID As GuestCountryCode, Type, guest_list.Name As GuestName, guest_list.Gender, DateOfBirth, Nationality, guest_list.IdentificationNumber, guest_list.PassportNumber, guest_list.Mobile As GuestMobile, guest_list.Email, MaritalStatus, Employment, Address, Postcode, guest_list.City, guest_list.State, guest_list.Country, Nominee, NomineeIdentificationNumber, Relationship, admin.Name As SalesAgentName, category.Name As DestinationName, CountryCode, source.Name As SourceName');
		$this->db->join('guest_list', 'guest_list.BookingID = booking.BookingID', 'left');
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
		$this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
		$this->db->join('source', 'source.SourceID = booking.Source', 'left');
		if($this->session->userdata('level') == 20) {
			$this->db->where('SalesAgent', $this->session->userdata('admin_id'));
		}
		if(!empty($this->input->get('booking_number'))) {
			$this->db->where('BookingNumber', $this->input->get('booking_number'));
		}
		if(!empty($this->input->get('reservation_number'))) {
			$this->db->where('ReservationNumber', $this->input->get('reservation_number'));
		}
		if(!empty($this->input->get('deadline'))) {
			$deadline = explode(' - ', $this->input->get('deadline'));
			$start_date = date('Y-m-d', strtotime(str_replace('/', '-', $deadline[0])));
			$end_date = date('Y-m-d', strtotime(str_replace('/', '-', $deadline[1])));
			$this->db->where("((`DepositDeadline` >= '".$start_date."' AND `DepositDeadline` <= '".$end_date."') OR (`FullPaymentDeadline` >= '".$start_date."' AND `FullPaymentDeadline` <= '".$end_date."') OR (`AdditionalPaymentDeadline` >= '".$start_date."' AND `AdditionalPaymentDeadline` <= '".$end_date."')) AND `booking`.`Status` IN ('P','PP')");
		}
		if(!empty($this->input->get('customer'))) {
			// $this->db->where('Customer', $this->input->get('customer'));
			$this->db->like('Customer', $this->input->get('customer'));
		}
		if(!empty($this->input->get('mobile'))) {
			$this->db->where('booking.Mobile', $this->input->get('mobile'));
		}
		if(!empty($this->input->get('travel_date'))) {
			$travel_date = explode(' - ', $this->input->get('travel_date'));
			$start_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[0])));
			$end_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[1])));
			$this->db->where("((`StartDate` <= '".$start_date."' AND `EndDate` >= '".$end_date."') OR (`StartDate` >= '".$start_date."' AND `StartDate` <= '".$end_date."') OR (`EndDate` >= '".$start_date."' AND `EndDate` <= '".$end_date."'))");
		}
		if(!empty($this->input->get('destination'))) {
			$this->db->where('Destination', $this->input->get('destination'));
		}
		if(!empty($this->input->get('sales_agent'))) {
			$this->db->where('SalesAgent', $this->input->get('sales_agent'));
		}
		if(!empty($this->input->get('tag'))) {
			$this->db->where("FIND_IN_SET('".$this->input->get('tag')."', Tag)");
		}
		if(!empty($this->input->get('chat_language'))) {
			$this->db->where('ChatLanguage', $this->input->get('chat_language'));
		}
		if(!empty($this->input->get('source'))) {
			$this->db->where('Source', $this->input->get('source'));
		}
		if(!empty($this->input->get('booking_confirmation_title'))) {
			$this->db->where('booking.BookingConfirmationTitle', $this->input->get('booking_confirmation_title'));
		}
		if(!empty($this->input->get('status'))) {
			if($this->input->get('status') == 'A') {
				$this->db->where('CancelStatus', 'N');
				$this->db->where('booking.Status !=', 'N');
			}
			if($this->input->get('status') == 'C') {
				$this->db->where('CancelStatus', 'Y');
				$this->db->where('booking.Status !=', 'N');
			}
			if($this->input->get('status') == 'Y') {
				$this->db->where('CancelStatus', 'N');
				$this->db->where('AfterSalesService', 'COMPLETE');
				$this->db->where('booking.Status', 'Y');
			}
			if($this->input->get('status') == 'OG') {
				$this->db->where('CancelStatus', 'N');
				$this->db->where('booking.Status', 'OG');
			}
			if($this->input->get('status') == 'PP') {
				$this->db->where('CancelStatus', 'N');
				$this->db->where('FullPaymentDeadline >=', date('Y-m-d'));
				$this->db->where('booking.Status', 'PP');
			}
			if($this->input->get('status') == 'PO') {
				$this->db->where('CancelStatus', 'N');
				$this->db->where("((`FullPaymentDeadline` < '".date('Y-m-d')."' AND `booking`.`Status` IN ('P','PP')) OR ((`DepositDeadline` < '".date('Y-m-d')."' AND `booking`.`Status` = 'P') OR (`FullPaymentDeadline` < '".date('Y-m-d')."' AND `booking`.`Status` IN ('P','PP'))))");
			}
			if($this->input->get('status') == 'PGL') {
				$this->db->where('CancelStatus', 'N');
				$this->db->where('LockStatus', 'N');
				$this->db->where('booking.Status', 'PTV');
			}
			if($this->input->get('status') == 'PBC') {
				$this->db->where('CancelStatus', 'N');
				$this->db->where('booking.Status', 'PBC');
			}
			if($this->input->get('status') == 'P') {
				$this->db->where('CancelStatus', 'N');
				$this->db->where("(`DepositDeadline` >= '".date('Y-m-d')."' OR (`DepositDeadline` IS NULL AND `FullPaymentDeadline` >= '".date('Y-m-d')."'))");
				$this->db->where('booking.Status', 'P');
			}
			if($this->input->get('status') == 'PR') {
				$this->db->where('CancelStatus', 'N');
				$this->db->where('AfterSalesService', 'PENDING');
				$this->db->where('booking.Status', 'Y');
			}
			if($this->input->get('status') == 'PT') {
				$this->db->where('CancelStatus', 'N');
				$this->db->where('booking.Status', 'PT');
			}
			if($this->input->get('status') == 'PTV') {
				$this->db->where('CancelStatus', 'N');
				$this->db->where('LockStatus', 'Y');
				$this->db->where('booking.Status', 'PTV');
			}
		} else {
			$this->db->where('CancelStatus', 'N');
			$this->db->where('AfterSalesService', 'PENDING');
		}
		if(!empty($this->input->get('booking_date'))) {
			$booking_date = explode(' - ', $this->input->get('booking_date'));
			$start_date = date('Y-m-d', strtotime(str_replace('/', '-', $booking_date[0])));
			$end_date = date('Y-m-d', strtotime(str_replace('/', '-', $booking_date[1])));
			$this->db->where('CAST(booking.InsertDate AS DATE) >=', $start_date);
			$this->db->where('CAST(booking.InsertDate AS DATE) <=', $end_date);
		}
		$this->db->where('booking.Status !=', 'N');
		$this->db->where('guest_list.Status', 'Y');
		if($group_by_booking_id == 'Y') {
			$this->db->group_by('booking.BookingID');
		}
		$this->db->order_by('booking.BookingID', 'DESC');
		$this->db->order_by('Type', 'ASC');
		return $this->db->get('booking')->result();
	}

	function Read_Payments($booking_id)
	{
		$this->db->select('Type, Credit, Debit, payment.Status');
		$this->db->where('payment.BookingID', $booking_id);
		$this->db->where('payment.Status !=', 'N');
		return $this->db->get('payment')->result();
	}
	
	function Read_Admins()
	{
		$this->db->select('AdminID, Name');
		$this->db->where('AdminID !=', 8);
		$this->db->where('Level !=', '30');
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('admin')->result();
	}

	function Read_Categories()
	{
		$this->db->select('CategoryID, Name');
		$this->db->where('IsDestination', 'YES');
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('category')->result();
	}

	function Read_Products()
	{
		$this->db->select('ProductID, ProductCode, product.Name As Product, category.Name As Category');
		$this->db->join('category', 'category.CategoryID = product.CategoryID', 'left');
		$this->db->where('product.Status', 'Y');
		$this->db->order_by('Category', 'ASC');
		$this->db->order_by('Product', 'ASC');
		return $this->db->get('product')->result();
	}

	function Read_Footers()
	{
		$this->db->select('FooterID, BookingConfirmationTitle, TravelVoucherTitle');
		$this->db->where('Status', 'Y');
		$this->db->order_by('BookingConfirmationTitle', 'ASC');
		$this->db->order_by('TravelVoucherTitle', 'ASC');
		return $this->db->get('footer')->result();
	}

	function Read_Country_Codes()
	{
		$this->db->select('CountryCodeID, Country, CountryCode');
		$this->db->where('Status', 'Y');
		$this->db->order_by('Country', 'ASC');
		return $this->db->get('country_code')->result();
	}

	function Read_Tags()
	{
		$this->db->select('TagID, Name');
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('tag')->result();
	}

	function Read_Sources()
	{
		$this->db->select('SourceID, Name');
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('source')->result();
	}

	function Read_Booking_ID() {
		$this->db->select('BookingID');
		$this->db->where('BookingNumber', $this->input->get('booking_number'));
		return $this->db->get('booking')->row_array();
	}

	function Read_Customer() {
		$this->db->select('Customer');
		$this->db->where('BookingNumber', $this->input->get('booking_number'));
		return $this->db->get('booking')->row_array();
	}

	function Read_Net_Total() {
		$this->db->select('NetTotal');
		$this->db->where('BookingNumber', $this->input->get('booking_number'));
		return $this->db->get('booking')->row_array();
	}

	function Read_Token() {
		$this->db->select('Token');
		$this->db->where('BookingNumber', $this->input->get('booking_number'));
		return $this->db->get('booking')->row_array();
	}

	function Read_Product_Sequence() {
		$this->db->select('ProductSequence');
		$this->db->where('BookingID', $this->input->post('booking_id'));
		return $this->db->get('booking')->row_array();
	}

	function Create()
	{
		// Load booking flow helper
		$this->load->helper('booking_flow');
		
		// Get booking data and ensure AllowReview is properly formatted as integer (0 or 1)
		$booking_data = $this->input->post('booking');
		if (!empty($booking_data) && is_array($booking_data)) {
			foreach ($booking_data as $key => $booking_item) {
				if (isset($booking_item['AllowReview'])) {
					// Convert AllowReview to integer (0 or 1) for BOOLEAN field
					$booking_data[$key]['AllowReview'] = (int)$booking_item['AllowReview'];
					// Ensure it's either 0 or 1 (validate)
					if ($booking_data[$key]['AllowReview'] != 0 && $booking_data[$key]['AllowReview'] != 1) {
						$booking_data[$key]['AllowReview'] = 1; // Default to 1 if invalid
					}
				} else {
					// If AllowReview is not set, default to 1 (TRUE)
					$booking_data[$key]['AllowReview'] = 1;
				}
				
				// Set initial status to PBC if not provided
				if (!isset($booking_item['Status']) || empty($booking_item['Status'])) {
					$booking_data[$key]['Status'] = get_initial_booking_status(); // 'PBC'
				}
			}
		}
		
		$this->db->insert_batch('booking', json_decode(json_encode($booking_data)));
		$booking_id = $this->db->insert_id();
		
		// Log booking creation with initial status
		if ($booking_id) {
			$this->load->helper('booking_status_log');
			$initial_status = !empty($booking_data[0]['Status']) ? $booking_data[0]['Status'] : 'PBC';
			$creator_id = $this->session->userdata('admin_id');
			log_booking_creation($booking_id, $initial_status, "Booking created", $creator_id);

			// Notify Sales Agent when a booking is created under them by someone else
			$sales_agent = isset($booking_data[0]['SalesAgent']) ? $booking_data[0]['SalesAgent'] : null;
			$creator_name = $this->session->userdata('name') ?: 'Someone';
			$this->load->model('Notification_Model');
			$this->Notification_Model->Create_Booking_Created_Notification($booking_id, $creator_id, $creator_name, $sales_agent);
		}

		$data = [
			'name'          => $this->input->post('booking')[0]['Customer'] ? $this->input->post('booking')[0]['Customer'] : null,
			'phone_number'  => $this->input->post('booking')[0]['Mobile'] ? $this->input->post('booking')[0]['Mobile'] : null,
			'ChatLanguage'  => $this->input->post('booking')[0]['ChatLanguage'] ? $this->input->post('booking')[0]['ChatLanguage'] : null,
			'updated_at'    => date('Y-m-d H:i:s'),
		];
		if ((!empty($this->input->post('CustomerID')) && $this->input->post('CustomerID') != 'undefiend')) {
			$customer_id = $this->input->post('CustomerID');
			$this->load->model('Customer_Model');
			$this->Customer_Model->update_by_id($this->input->post('CustomerID'), $data);
			$customerInfo = get_object_vars($this->Customer_Model->find($this->input->post('CustomerID')));
			if (!empty($customerInfo)) {
				if ($customerInfo['AutocountSyncAction'] == 'C' && $customerInfo['AutocountSyncStatus'] == 'S') {
					$this->Customer_Model->update_by_id($customerInfo['CustomerID'], [
						'AutocountSyncAction' => 'U',
						'AutocountSyncStatus' => 'P'
					]);
				} else if ($customerInfo['AutocountSyncAction'] == 'U' && $customerInfo['AutocountSyncStatus'] == 'S') {
					$this->Customer_Model->update_by_id($customerInfo['CustomerID'], [
						'AutocountSyncAction' => 'U',
						'AutocountSyncStatus' => 'P'
					]);
				} else {
					$this->Customer_Model->update_by_id($customerInfo['CustomerID'], [
						'AutocountSyncStatus'  => 'P',
					]);
				}
			}
		} else {
			$data['created_at'] = date('Y-m-d H:i:s');
			$this->db->insert('customer', $data);
			$customer_id = $this->db->insert_id();
		}

		if($this->input->post('booking_number') != '' || $this->input->post('booking_number') != null) {
			$booking_number = $this->input->post('booking_number');
		} else {
			$this->db->like('BookingNumber', date('ym'));
			if($this->db->get('booking')->row()) {
				$this->db->like('BookingNumber', date('ym'));
				$this->db->order_by('BookingNumber', 'DESC');
				$booking = $this->db->get('booking')->row_array();
				$digits = (explode('-', $booking['BookingNumber'])[2]) + 1;
				$booking_number = 'BC-' . date('ym') . '-' . sprintf('%04d', $digits);
			} else {
				$booking_number = 'BC-' . date('ym') . '-0001';
			}
		}

		$this->db->set('BookingNumber', $booking_number);
		$this->db->where('BookingID', $booking_id);
		$this->db->where('BookingNumber', null);
		$this->db->update('booking');

		$this->db->set('Token', sha1($booking_number));
		$this->db->where('BookingID', $booking_id);
		$this->db->where('Token', null);
		$this->db->update('booking');

		$this->db->set('CustomerID', $customer_id);
		$this->db->where('BookingID', $booking_id);
		$this->db->where('CustomerID', null);
		$this->db->update('booking');

		$this->db->select('Adult, Children, Infant');
		$this->db->where('BookingID', $booking_id);
		$booking = $this->db->get('booking')->row_array();
		$this->load->model('Guest_List_Model');
		if(!empty($booking['Adult'])) {
			for($i = 1; $i <= $booking['Adult']; $i++) {
				$this->Guest_List_Model->Create($booking_id, 'ADULT');
			}
		}
		if(!empty($booking['Children'])) {
			for($i = 1; $i <= $booking['Children']; $i++) {
				$this->Guest_List_Model->Create($booking_id, 'CHILD');
			}
		}
		if(!empty($booking['Infant'])) {
			for($i = 1; $i <= $booking['Infant']; $i++) {
				$this->Guest_List_Model->Create($booking_id, 'INFANT');
			}
		}

		$this->db->select('BookingNumber, ReservationNumber, DepositDeadline, FullPaymentDeadline, Customer, booking.Mobile As CustomerMobile, StartDate, EndDate, Adult, Children, Infant, BookingRemark, NetTotal, ChatLanguage, Source, admin.CountryCodeID, admin.Name As SalesAgent, admin.Mobile As SalesAgentMobile, category.Name As Destination, CountryCode, source.Name As SourceName');
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
		$this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
		$this->db->join('source', 'source.SourceID = booking.Source', 'left');
		$this->db->where('BookingID', $booking_id);
		$booking = $this->db->get('booking')->row_array();
		$booking['DepositDeadline'] = empty($booking['DepositDeadline']) ? '-' : strtoupper(date('j M Y', strtotime($booking['DepositDeadline'])));
		$booking['FullPaymentDeadline'] = strtoupper(date('j M Y', strtotime($booking['FullPaymentDeadline'])));
		$booking['CustomerMobile'] = $booking['CountryCode'] . $booking['CustomerMobile'];
		if(!empty($booking['StartDate']) && !empty($booking['EndDate'])) {
			$booking['TravelDate'] = strtoupper(date('j M', strtotime($booking['StartDate'])) . ' - ' . date('j M Y', strtotime($booking['EndDate'])));
		} else {
			$booking['TravelDate'] = '-';
		}
		if(!empty($booking['Adult'])) {
			$booking['Adult'] = $booking['Adult'] == 1 ? $booking['Adult'] . ' ADULT ' : $booking['Adult'] . ' ADULTS ';
		}
		if(!empty($booking['Children'])) {
			$booking['Children'] = $booking['Children'] == 1 ? $booking['Children'] . ' CHILD ' : $booking['Children'] . ' CHILDREN ';
		}
		if(!empty($booking['Infant'])) {
			$booking['Infant'] = $booking['Infant'] == 1 ? $booking['Infant'] . ' INFANT ' : $booking['Infant'] . ' INFANTS ';
		}
		if(!empty($booking['Adult']) && !empty($booking['Children']) && !empty($booking['Infant'])) {
			$booking['PaxNumber'] = $booking['Adult'] . '& ' . $booking['Children'] . '& ' . $booking['Infant'];
		} else {
			if(!empty($booking['Adult']) && empty($booking['Children']) && !empty($booking['Infant'])) {
				$booking['PaxNumber'] = $booking['Adult'] . '& ' . $booking['Infant'];
			} else {
				if(!empty($booking['Adult']) && !empty($booking['Children']) && empty($booking['Infant'])) {
					$booking['PaxNumber'] = $booking['Adult'] . '& ' . $booking['Children'];
				} else {
					if(!empty($booking['Adult']) && empty($booking['Children']) && empty($booking['Infant'])) {
						$booking['PaxNumber'] = $booking['Adult'];
					} else {
						if(empty($booking['Adult']) && !empty($booking['Children']) && !empty($booking['Infant'])) {
							$booking['PaxNumber'] = $booking['Children'] . '& ' . $booking['Infant'];
						} else {
							if(empty($booking['Adult']) && empty($booking['Children']) && !empty($booking['Infant'])) {
								$booking['PaxNumber'] = $booking['Infant'];
							} else {
								$booking['PaxNumber'] = $booking['Children'];
							}
						}
					}
				}
			}
		}
		$booking['BookingRemark'] = empty($booking['BookingRemark']) ? '-' : $booking['BookingRemark'];
		$booking['NetTotal'] = 'RM ' . number_format($booking['NetTotal'], 2, '.', ',');
		$booking['Source'] = empty($booking['Source']) ? '-' : $booking['SourceName'];
		$this->load->model('Universal_Model');
		$country_code = $this->Universal_Model->Read_Country_Code($booking['CountryCodeID']);
		$booking['SalesAgentMobile'] = $country_code . $booking['SalesAgentMobile'];
		$text = urlencode('New Booking Record Successfully Created' . "\n\n" . 'Booking Number : ' . "\n" . $booking['BookingNumber'] . "\n\n" . 'Reservation Number : ' . "\n" . $booking['ReservationNumber'] . "\n\n" . 'Deposit Deadline : ' . "\n" . $booking['DepositDeadline'] . "\n\n" . 'Full Payment Deadline : ' . "\n" . $booking['FullPaymentDeadline'] . "\n\n" . 'Customer : ' . "\n" . $booking['Customer'] . ' (' . $booking['CustomerMobile'] . ')' . "\n\n" . 'Travel Date : ' . "\n" . $booking['TravelDate'] . "\n\n" . 'Pax Number : ' . "\n" . $booking['PaxNumber'] . "\n\n" . 'Destination : ' . "\n" . $booking['Destination'] . "\n\n" . 'Sales Agent : ' . "\n" . $booking['SalesAgent'] . ' (' . $booking['SalesAgentMobile'] . ')' . "\n\n" . 'Remark : ' . "\n" . $booking['BookingRemark'] . "\n\n" . 'Subtotal : ' . "\n" . $booking['NetTotal'] . "\n\n" . 'Chat Language : ' . "\n" . $booking['ChatLanguage'] . "\n\n" . 'Source : ' . "\n" . $booking['Source']);
		
		// Send Telegram notification only if APP_ENV is 'prod'
		$this->load->helper('utils');
		$app_env = get_app_env();
		if ($app_env === 'prod') {
			file_get_contents('https://api.telegram.org/bot7521016286:AAEMDyjd789UEHBH5LK4xfBzIzY9TZ80tCg/sendMessage?chat_id=-1002546036574&text=' . $text);
		}

		return $booking_id;
	}

	function Create_Booking_Log()
	{
		if(current_url() == base_url('Booking/Update')) {
			$this->db->insert_batch('booking_log', json_decode(json_encode($this->input->post('booking_log'))));
		} else {
			if(current_url() == base_url('Booking/Update_Cancel_Status')) {
				$array = array(
					'BookingID' => $this->input->get('booking_id'),
					'Column' => 'CancelStatus',
					'CurrentData' => $this->input->get('current_cancel_status'),
					'NewData' => $this->input->get('new_cancel_status'),
					'InsertBy' => $this->session->admin_id,
					'InsertDate' => date('Y-m-d H:i:s')
				);
				$this->db->insert('booking_log', $array);
			} else {
				if(current_url() == base_url('Booking/Update_Lock_Status')) {
					$array = array(
						'BookingID' => $this->input->get('booking_id'),
						'Column' => 'LockStatus',
						'CurrentData' => $this->input->get('current_lock_status'),
						'NewData' => $this->input->get('new_lock_status'),
						'InsertBy' => $this->session->admin_id,
						'InsertDate' => date('Y-m-d H:i:s')
					);
					$this->db->insert('booking_log', $array);
				} else {
					if(current_url() == base_url('Booking/Update_Travel_Insurance_Status')) {
						$array = array(
							'BookingID' => $this->input->get('booking_id'),
							'Column' => 'TravelInsuranceStatus',
							'CurrentData' => $this->input->get('current_travel_insurance_status'),
							'NewData' => $this->input->get('new_travel_insurance_status'),
							'InsertBy' => $this->session->admin_id,
							'InsertDate' => date('Y-m-d H:i:s')
						);
						$this->db->insert('booking_log', $array);
					} else {
						if(current_url() == base_url('Booking/Update_After_Sales_Service')) {
							$array = array(
								'BookingID' => $this->input->get('booking_id'),
								'Column' => 'AfterSalesService',
								'CurrentData' => $this->input->get('current_after_sales_service'),
								'NewData' => $this->input->get('new_after_sales_service'),
								'InsertBy' => $this->session->admin_id,
								'InsertDate' => date('Y-m-d H:i:s')
							);
							$this->db->insert('booking_log', $array);
						} else {
							$array = array(
								'BookingID' => $this->input->get('booking_id'),
								'Column' => 'Status',
								'CurrentData' => $this->input->get('current_status'),
								'NewData' => $this->input->get('new_status'),
								'InsertBy' => $this->session->admin_id,
								'InsertDate' => date('Y-m-d H:i:s')
							);
							$this->db->insert('booking_log', $array);
						}
					}
				}
			}
		}
	}
	
	function Create_Booking_Log2($current_status, $new_status, $booking_id)
	{
		// Log to original booking_log table
		$array = array(
			'BookingID' => $booking_id,
			'Column' => 'Status',
			'CurrentData' => $current_status,
			'NewData' => $new_status,
			'InsertBy' => $this->session->admin_id,
			'InsertDate' => date('Y-m-d H:i:s')
		);
		$this->db->insert('booking_log', $array);
		
		// Also log to booking_status_log table
		$this->load->helper('booking_status_log');
		$created_by = !empty($this->session->admin_id) ? $this->session->admin_id : 0;
		log_booking_status_update($booking_id, $current_status, $new_status, $created_by, null, true);
	}

	function Update()
	{
		// Get booking data and ensure AllowReview is properly formatted as integer (0 or 1)
		$booking_data = $this->input->post('booking');
		if (!empty($booking_data) && is_array($booking_data)) {
			foreach ($booking_data as $key => $booking_item) {
				if (isset($booking_item['AllowReview'])) {
					// Convert AllowReview to integer (0 or 1) for BOOLEAN field
					$booking_data[$key]['AllowReview'] = (int)$booking_item['AllowReview'];
					// Ensure it's either 0 or 1 (validate)
					if ($booking_data[$key]['AllowReview'] != 0 && $booking_data[$key]['AllowReview'] != 1) {
						$booking_data[$key]['AllowReview'] = 1; // Default to 1 if invalid
					}
				}
			}
		}
		
		$this->db->update_batch('booking', json_decode(json_encode($booking_data)), 'BookingID');
		
		$data = [];
		$booking = $this->input->post('booking');
		if (!empty($booking) && isset($booking[0])) {
			$booking = $booking[0];
			if (!empty($booking['Customer'])) { $data['name'] = $booking['Customer']; }
			if (!empty($booking['Mobile'])) { $data['phone_number'] = $booking['Mobile']; }
			if (!empty($booking['ChatLanguage'])) { $data['ChatLanguage'] = $booking['ChatLanguage'];}
			if ($data) { $data['updated_at'] = date('Y-m-d H:i:s'); }
		}

		if ((!empty($this->input->post('CustomerID')) && $this->input->post('CustomerID') != 'undefiend')) {
			$this->load->model('Customer_Model');
			$customer_id = $this->input->post('CustomerID');

			if (!empty($data)) {
				$this->Customer_Model->update_by_id($this->input->post('CustomerID'), $data);
			}
			$customerInfo = get_object_vars($this->Customer_Model->find($this->input->post('CustomerID')));
			if (!empty($customerInfo)) {
				if ($customerInfo['AutocountSyncAction'] == 'C' && $customerInfo['AutocountSyncStatus'] == 'S') {
					$this->Customer_Model->update_by_id($customerInfo['CustomerID'], [
						'AutocountSyncAction' => 'U',
						'AutocountSyncStatus' => 'P'
					]);
				} else if ($customerInfo['AutocountSyncAction'] == 'U' && $customerInfo['AutocountSyncStatus'] == 'S') {
					$this->Customer_Model->update_by_id($customerInfo['CustomerID'], [
						'AutocountSyncAction' => 'U',
						'AutocountSyncStatus' => 'P'
					]);
				} else {
					$this->Customer_Model->update_by_id($customerInfo['CustomerID'], [
						'AutocountSyncStatus'  => 'P',
					]);
				}
			}
		} else {
			$data['created_at'] = date('Y-m-d H:i:s');
			$this->db->insert('customer', $data);
			$customer_id = $this->db->insert_id();
		}

		$this->db->set('BookingConfirmationFooterID', null);
		$this->db->where('BookingID', $this->input->post('booking_id'));
		$this->db->where('BookingConfirmationFooterID', '');
		$this->db->update('booking');

		$this->db->set('CustomerID', $customer_id);
		$this->db->where('BookingID', $this->input->post('booking_id'));
		$this->db->where("(CustomerID IS NULL OR CustomerID <> " . $this->db->escape($customer_id) . ")", null, false);
		$this->db->update('booking');

		$this->db->set('TravelVoucherFooterID', null);
		$this->db->where('BookingID', $this->input->post('booking_id'));
		$this->db->where('TravelVoucherFooterID', '');
		$this->db->update('booking');

		$this->db->set('DepositDeadline', null);
		$this->db->where('BookingID', $this->input->post('booking_id'));
		$this->db->where('DepositDeadline', '0000-00-00');
		$this->db->update('booking');

		$this->db->set('AdditionalPaymentDeadline', null);
		$this->db->where('BookingID', $this->input->post('booking_id'));
		$this->db->where('AdditionalPaymentDeadline', '0000-00-00');
		$this->db->update('booking');

		$this->db->set('StartDate', null);
		$this->db->where('BookingID', $this->input->post('booking_id'));
		$this->db->where('StartDate', '0000-00-00');
		$this->db->update('booking');

		$this->db->set('EndDate', null);
		$this->db->where('BookingID', $this->input->post('booking_id'));
		$this->db->where('EndDate', '0000-00-00');
		$this->db->update('booking');

		$this->db->set('Tag', null);
		$this->db->where('BookingID', $this->input->post('booking_id'));
		$this->db->where('Tag', '');
		$this->db->update('booking');
		
		$this->db->set('BookingRemark', null);
		$this->db->where('BookingID', $this->input->post('booking_id'));
		$this->db->where('BookingRemark', '');
		$this->db->update('booking');

		$this->db->set('Source', null);
		$this->db->where('BookingID', $this->input->post('booking_id'));
		$this->db->where('Source', '');
		$this->db->update('booking');
		
		$this->db->set('BookingConfirmationFooter', null);
		$this->db->where('BookingID', $this->input->post('booking_id'));
		$this->db->where('BookingConfirmationFooter', '');
		$this->db->update('booking');

		$this->db->set('TravelVoucherFooter', null);
		$this->db->where('BookingID', $this->input->post('booking_id'));
		$this->db->where('TravelVoucherFooter', '');
		$this->db->update('booking');
	}

	function Update_Pax_Number($booking_id)
	{
		$array = array(
			'Adult' => empty($this->input->post('adult')) ? null : $this->input->post('adult'),
			'Children' => empty($this->input->post('child')) ? null : $this->input->post('child'),
			'Infant' => empty($this->input->post('infant')) ? null : $this->input->post('infant'),
			'UpdateBy' => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s')
		);
		$this->db->where('BookingID', $booking_id);
		$this->db->update('booking', $array);
	}
	
	function Update_Cancel_Status()
	{
		$array = array(
			'CancelStatus' => $this->input->get('new_cancel_status'),
			'UpdateBy' => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s')
		);
		$this->db->where('BookingID', $this->input->get('booking_id'));
		$this->db->update('booking', $array);
	}

	function Update_Lock_Status()
	{
		$array = array(
			'LockStatus' => $this->input->get('new_lock_status'),
			'GLSessionLock' => 'N',
			'GLSessionExpiration' => null,
			'UpdateBy' => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s')
		);
		$this->db->where('BookingID', $this->input->get('booking_id'));
		$this->db->update('booking', $array);
	}

	function Update_Travel_Insurance_Status()
	{
		$array = array(
			'TravelInsuranceStatus' => $this->input->get('new_travel_insurance_status'),
			'GLSessionLock' => 'N',
			'GLSessionExpiration' => null,
			'UpdateBy' => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s')
		);
		$this->db->where('BookingID', $this->input->get('booking_id'));
		$this->db->update('booking', $array);
	}

	function Update_After_Sales_Service()
	{
		$array = array(
			'AfterSalesService' => $this->input->get('new_after_sales_service'),
			'UpdateBy' => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s')
		);
		$this->db->where('BookingID', $this->input->get('booking_id'));
		$this->db->update('booking', $array);
	}

	function Update_After_Sales_Service2($booking_id)
	{
		$array = array(
			'AfterSalesService' => 'PENDING',
			'UpdateBy' => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s')
		);
		$this->db->where('BookingID', $booking_id);
		$this->db->update('booking', $array);
	}

	function Update_Product_Sequence($product_sequence)
	{
		$array = array(
			'ProductSequence' => $product_sequence,
			'UpdateBy' => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s')
		);
		$this->db->where('BookingID', $this->input->post('booking_id'));
		$this->db->update('booking', $array);
	}

	function Update_Status($status, $booking_id)
	{
		// Get current status before update
		$this->db->select('Status');
		$this->db->where('BookingID', $booking_id);
		$current_booking = $this->db->get('booking')->row();
		$current_status = $current_booking ? $current_booking->Status : null;
		
		// Update status
		$array = array(
			'Status' => $status,
			'UpdateBy' => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s')
		);
		$this->db->where('BookingID', $booking_id);
		$this->db->update('booking', $array);
		
		// Log status change if status actually changed
		if ($current_status && $current_status != $status) {
			$this->load->helper('booking_status_log');
			$created_by = !empty($this->session->userdata('admin_id')) ? $this->session->userdata('admin_id') : 0;
			log_booking_status_update($booking_id, $current_status, $status, $created_by, null, true);
		}
	}

	function Booking_Document()
	{
		$this->db->select('BookingID, BookingNumber, ReservationNumber, DepositDeadline, FullPaymentDeadline, Customer, booking.Mobile As CustomerMobile, StartDate, EndDate, Adult, Children, Infant, Subtotal, Discount, NetTotal, booking.BookingConfirmationTitle, BookingConfirmationFooter, TravelVoucherFooter, AfterSalesService, ProductSequence, booking.Status, booking.InsertDate, admin.CountryCodeID As SalesAgentCountryCode, admin.Name As SalesAgentName, admin.Mobile As SalesAgentMobile, category.Name As DestinationName, TravelVoucherTitle, footer.KeyContacts As TravelVoucherKeyContacts, footer.SpecialRemarks As TravelVoucherSpecialRemarks, CountryCode');
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
		$this->db->join('footer', 'footer.FooterID = booking.TravelVoucherFooterID', 'left');
		$this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
		$this->db->where('Token', $this->input->get('token'));
		return $this->db->get('booking')->row_array();
	}

	function Booking_Document_for_receipt()
	{
		$this->db->select('booking.BookingID, BookingNumber, ReservationNumber, DepositDeadline, FullPaymentDeadline, Customer, booking.Mobile As CustomerMobile, StartDate, EndDate, Adult, Children, Infant, Subtotal, Discount, NetTotal, booking.BookingConfirmationTitle, BookingConfirmationFooter, TravelVoucherFooter, AfterSalesService, ProductSequence, booking.Status, booking.InsertDate, admin.CountryCodeID As SalesAgentCountryCode, admin.Name As SalesAgentName, admin.Mobile As SalesAgentMobile, category.Name As DestinationName, TravelVoucherTitle, CountryCode, customer.CustomerCode');
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
		$this->db->join('footer', 'footer.FooterID = booking.TravelVoucherFooterID', 'left');
		$this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
		$this->db->join('customer', 'customer.CustomerID = booking.CustomerID', 'left');
		$this->db->where('Token', $this->input->get('token'));
		return $this->db->get('booking')->row_array();
	}

	function Detect()
	{
		$this->db->where('BookingNumber', $this->input->post('booking_number'));
		if($this->db->get('booking')->row()) {
			return true;
		} else {
			return false;
		}
	}

	function getAllBookingsWithGuests($booking_id = null)
	{
		// Get all columns from booking table
		$bookingCols = $this->db->list_fields('booking');
		$bookingCols = array_map(function($col) {
			return "booking.`$col`";
		}, $bookingCols);

		// Get all columns from guest_list table, but alias with guest_ prefix
		$guestCols = $this->db->list_fields('guest_list');
		$guestCols = array_map(function($col) {
			return "guest_list.`$col` AS guest_$col";
		}, $guestCols);

		// Merge both columns into one select string
		$allCols = array_merge($bookingCols, $guestCols);
		$this->db->select(implode(', ', $allCols), false);

		// Main query
		$this->db->from('booking');
		$this->db->join('guest_list', 'guest_list.BookingID = booking.BookingID', 'left');

		// Optional filter by BookingID
		if (!is_null($booking_id) && $booking_id !== '') {
			$this->db->where('booking.BookingID', $booking_id);
		}

		$this->db->order_by('booking.BookingID', 'ASC');

		$query = $this->db->get();
		return $query->result();
	}

	function getAllBookingsWithProducts($booking_id = null)
	{
		// Get all columns from booking table
		$bookingCols = $this->db->list_fields('booking');
		$bookingCols = array_map(function($col) {
			return "booking.`$col`";
		}, $bookingCols);

		// Get all columns from booking_product table, alias with product_ prefix
		$productCols = $this->db->list_fields('booking_product');
		$productCols = array_map(function($col) {
			return "booking_product.`$col` AS product_$col";
		}, $productCols);

		// Merge both columns into one select string
		$allCols = array_merge($bookingCols, $productCols);
		$this->db->select(implode(', ', $allCols), false);

		// Main query
		$this->db->from('booking');
		$this->db->join('booking_product', 'booking_product.BookingID = booking.BookingID', 'left');

		// Optional filter by BookingID
		if (!is_null($booking_id) && $booking_id !== '') {
			$this->db->where('booking.BookingID', $booking_id);
		}

		$this->db->order_by('booking.BookingID', 'ASC');

		$query = $this->db->get();
		return $query->result();
	}

	function getBookingById($booking_id)
	{
		$this->db->select('*');
		$this->db->from('booking');
		$this->db->where('BookingID', $booking_id);
		$query = $this->db->get();

		return $query->num_rows() > 0 ? $query->row() : null;
	}


	public function find($booking_id)
    {
        return $this->db->get_where('booking', ['BookingID' => $booking_id])->row();
    }
	
    public function update_by_id($booking_id, $data = [])
    {
        if (empty($data)) return false;

        return $this->db
            ->where('BookingID', $booking_id)
            ->update('booking', $data);
    }

	function getPendingBookingsWithDetails($booking_ids = array())
	{
		$this->load->helper('autocount');
		$config = get_autocount_config();

		$booking_qty_cront = !empty($config['booking_qty_cront'])
			? $config['booking_qty_cront']
			: 10;
		
		// --- 1. Get all booking columns (no prefix) ---
		$bookingCols = $this->db->list_fields('booking');
		$bookingCols = array_map(function($col) {
			return "booking.`$col`";
		}, $bookingCols);
		$this->db->select(implode(', ', $bookingCols), false);

		$statuses = !empty($config['booking_sync_autocount_status']) 
			? $config['booking_sync_autocount_status'] 
			: ['P'];

		$titles   = !empty($config['booking_sync_status']) 
			? $config['booking_sync_status'] 
			: ['BOOKING CONFIRMATION'];

		// base query
		$this->db->from('booking')
			->join('customer', 'booking.CustomerID = customer.CustomerID', 'left')
			->where("(customer.CustomerCode IS NOT NULL AND customer.CustomerCode <> '')")
			->where_in('booking.AutocountSyncStatus', $statuses)
			->where_in('booking.BookingConfirmationTitle', $titles)
			->where('booking.AutocountSyncAction IS NOT NULL')
			->where("EXISTS (SELECT 1 FROM payment WHERE payment.BookingID = booking.BookingID AND payment.Status = 'Y')")
			->order_by('booking.BookingID', 'ASC')
			->limit($booking_qty_cront);

		if (!empty($config['booking_cutoff_date'])) {
			$date = date('Y-m-d', strtotime($config['booking_cutoff_date']));
			$this->db->where('booking.InsertDate >', $date);
		}

		// extra filter if booking_id is provided
		if (!empty($booking_ids)) {
			$this->db->where_in('booking.BookingID', $booking_ids);
		}

		$bookings = $this->db->get()->result_array();

		if (empty($bookings)) {
			return [];
		}

		$bookingIds = array_column($bookings, 'BookingID');

		// --- 2. Guest list with guest_ prefix (flattened, 1st guest only) ---
		$guestCols = $this->db->list_fields('guest_list');
		$guestCols = array_map(function($col) {
			return "guest_list.`$col` AS guest_$col";
		}, $guestCols);
		$this->db->select(implode(', ', $guestCols), false);
		$guests = $this->db
			->where_in('guest_list.BookingID', $bookingIds)
			->get('guest_list')
			->result_array();

		$guestByBooking = [];
		foreach ($guests as $g) {
			$bookingId = $g['guest_BookingID'];
			if (!isset($guestByBooking[$bookingId])) {
				$guestByBooking[$bookingId] = $g; // keep first guest only
			}
		}

		// --- 3. Products with product_ prefix (array under "details") ---
		$productCols = $this->db->list_fields('booking_product');
		$productCols = array_map(function($col) {
			return "booking_product.`$col` AS product_$col";
		}, $productCols);
		$this->db->select(implode(', ', $productCols), false);
		$products = $this->db
			->where_in('booking_product.BookingID', $bookingIds)
			->where('booking_product.Status', 'Y')
			->get('booking_product')
			->result_array();

		$productsByBooking = [];
		foreach ($products as $p) {
			$productsByBooking[$p['product_BookingID']][] = $p;
		}

		// --- 4. Attach guest (flat fields) & products (array as details) ---
		foreach ($bookings as &$b) {
			if (isset($guestByBooking[$b['BookingID']])) {
				$b = array_merge($b, $guestByBooking[$b['BookingID']]);
			}
			$b['booking_product'] = $productsByBooking[$b['BookingID']] ?? [];
		}

		return $bookings;
	}

	public function get_pending_sycn_booking_customer()
	{
		$this->load->helper('autocount');
		$config = get_autocount_config();

		$customer_qty_cront = !empty($config['customer_qty_cront'])
			? (int)$config['customer_qty_cront']
			: 10;

		$statuses = !empty($config['customer_sync_autocount_status'])
			? (array)$config['customer_sync_autocount_status']
			: ['P'];

		$this->db->from('booking');
		$this->db->where_in('CustomerAutocountSyncStatus', $statuses);
		$this->db->where('CustomerAutocountSyncAction IS NOT NULL', null, false);

		if (!empty($config['customer_cutoff_date'])) {
			$date = date('Y-m-d', strtotime($config['customer_cutoff_date']));
			$this->db->where('booking.InsertDate >', $date);
		}

		$this->db->limit($customer_qty_cront);

		return $this->db->get()->result_array();
	}

	/**
	 * Apply filters to the query builder (shared logic for pagination methods)
	 */
	private function apply_booking_filters()
	{
		if($this->session->userdata('level') == 20) {
			$this->db->where('SalesAgent', $this->session->userdata('admin_id'));
		}

		// DataTables search parameter
		$search_value = $this->input->get('search[value]');
		if(!empty($search_value)) {
			$this->db->group_start();
			$this->db->like('BookingNumber', $search_value);
			$this->db->or_like('Customer', $search_value);
			$this->db->or_like('customer.CustomerCode', $search_value);
			$this->db->or_like('category.Name', $search_value);
			$this->db->or_like('admin.Name', $search_value);
			$this->db->or_like('booking.Mobile', $search_value);
			$this->db->group_end();
		}

		// These filters can ignore others
		$ignore = 0;

		if(!empty($this->input->get('customer'))) {
			$this->db->like('Customer', $this->input->get('customer'));
			$ignore = 1;
		}

		if(!empty($this->input->get('booking_number'))) {
			$this->db->where('BookingNumber', $this->input->get('booking_number'));
			$ignore = 1;
		}

		if(!empty($this->input->get('reservation_number'))) {
			$this->db->where('ReservationNumber', $this->input->get('reservation_number'));
			$ignore = 1;
		}

		if($ignore == 0) {
			// Second level ignore
			$level2Ignore = 0;

			if(!empty($this->input->get('deadline'))) {
				$deadline = explode(' - ', $this->input->get('deadline'));
				$start_date = date('Y-m-d', strtotime(str_replace('/', '-', $deadline[0])));
				$end_date = date('Y-m-d', strtotime(str_replace('/', '-', $deadline[1])));
				$this->db->where("((`DepositDeadline` >= '".$start_date."' AND `DepositDeadline` <= '".$end_date."') OR (`FullPaymentDeadline` >= '".$start_date."' AND `FullPaymentDeadline` <= '".$end_date."') OR (`AdditionalPaymentDeadline` >= '".$start_date."' AND `AdditionalPaymentDeadline` <= '".$end_date."')) AND `booking`.`Status` IN ('P','PP')");
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('mobile'))) {
				$this->db->where('booking.Mobile', $this->input->get('mobile'));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('travel_date'))) {
				$travel_date = explode(' - ', $this->input->get('travel_date'));
				$start_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[0])));
				$end_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[1])));
				$this->db->where("((`StartDate` <= '".$start_date."' AND `EndDate` >= '".$end_date."') OR (`StartDate` >= '".$start_date."' AND `StartDate` <= '".$end_date."') OR (`EndDate` >= '".$start_date."' AND `EndDate` <= '".$end_date."'))");
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('destination'))) {
				$this->db->where('Destination', $this->input->get('destination'));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('sales_agent'))) {
				$this->db->where('SalesAgent', $this->input->get('sales_agent'));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('tag'))) {
				$this->db->where("FIND_IN_SET('".$this->input->get('tag')."', Tag)");
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('chat_language'))) {
				$this->db->where('booking.ChatLanguage', $this->input->get('chat_language'));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('source'))) {
				$this->db->where('Source', $this->input->get('source'));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('booking_confirmation_title'))) {
				$this->db->where('booking.BookingConfirmationTitle', $this->input->get('booking_confirmation_title'));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('autocount_status'))) {
				$this->db->where('booking.AutocountSyncStatus', $this->input->get('autocount_status'));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('status'))) {
				if($this->input->get('status') == 'A') {
					$this->db->where('CancelStatus', 'N');
					$this->db->where('booking.Status !=', 'N');
				}
				if($this->input->get('status') == 'C') {
					$this->db->where('CancelStatus', 'Y');
					$this->db->where('booking.Status !=', 'N');
				}
				if($this->input->get('status') == 'Y') {
					$this->db->where('CancelStatus', 'N');
					$this->db->where('AfterSalesService', 'COMPLETE');
					$this->db->where('booking.Status', 'Y');
				}
				if($this->input->get('status') == 'OG') {
					$this->db->where('CancelStatus', 'N');
					$this->db->where('booking.Status', 'OG');
				}
				if($this->input->get('status') == 'PP') {
					$this->db->where('CancelStatus', 'N');
					$this->db->where('FullPaymentDeadline >=', date('Y-m-d'));
					$this->db->where('booking.Status', 'PP');
				}
				if($this->input->get('status') == 'PO') {
					$this->db->where('CancelStatus', 'N');
					$this->db->where("((`FullPaymentDeadline` < '".date('Y-m-d')."' AND `booking`.`Status` IN ('P','PP')) OR ((`DepositDeadline` < '".date('Y-m-d')."' AND `booking`.`Status` = 'P') OR (`FullPaymentDeadline` < '".date('Y-m-d')."' AND `booking`.`Status` IN ('P','PP'))))");
				}
				if($this->input->get('status') == 'PGL') {
					$this->db->where('CancelStatus', 'N');
					$this->db->where('LockStatus', 'N');
					$this->db->where('booking.Status', 'PTV');
				}
				if($this->input->get('status') == 'PBC') {
					$this->db->where('CancelStatus', 'N');
					$this->db->where('booking.Status', 'PBC');
				}
				if($this->input->get('status') == 'P') {
					$this->db->where('CancelStatus', 'N');
					$this->db->where("(`DepositDeadline` >= '".date('Y-m-d')."' OR (`DepositDeadline` IS NULL AND `FullPaymentDeadline` >= '".date('Y-m-d')."'))");
					$this->db->where('booking.Status', 'P');
				}
				if($this->input->get('status') == 'PR') {
					$this->db->where('CancelStatus', 'N');
					$this->db->where('AfterSalesService', 'PENDING');
					$this->db->where('booking.Status', 'Y');
				}
				if($this->input->get('status') == 'PT') {
					$this->db->where('CancelStatus', 'N');
					$this->db->where('booking.Status', 'PT');
				}
				if($this->input->get('status') == 'PTV') {
					$this->db->where('CancelStatus', 'N');
					$this->db->where('LockStatus', 'Y');
					$this->db->where('booking.Status', 'PTV');
				}

				$level2Ignore = 1;
			} else {
				$this->db->where('CancelStatus', 'N');
				$this->db->where('AfterSalesService', 'PENDING');
			}
		}

		if(!empty($this->input->get('booking_date'))) {
			$booking_date = explode(' - ', $this->input->get('booking_date'));
			$start_date = date('Y-m-d', strtotime(str_replace('/', '-', $booking_date[0])));
			$end_date = date('Y-m-d', strtotime(str_replace('/', '-', $booking_date[1])));
			$this->db->where('CAST(booking.InsertDate AS DATE) >=', $start_date);
			$this->db->where('CAST(booking.InsertDate AS DATE) <=', $end_date);
		} else {
			if($ignore == 0 && isset($level2Ignore) && $level2Ignore == 0) {
				$this->db->where('CAST(booking.InsertDate AS DATE) >=', date('Y-m-d', strtotime('-7 days')));
				$this->db->where('CAST(booking.InsertDate AS DATE) <=', date('Y-m-d'));
			}
		}

		$this->db->where('booking.Status !=', 'N');
	}

	/**
	 * Read bookings with pagination for DataTables server-side processing
	 */
	function Read_Bookings_Paginated($start, $length, $order_column, $order_dir)
	{
		$this->db->select('booking.BookingID, BookingNumber, DepositDeadline, FullPaymentDeadline, Customer, booking.Mobile As CustomerMobile, StartDate, EndDate, NetTotal, booking.ChatLanguage, Token, booking.BookingConfirmationTitle, CancelStatus, LockStatus, AfterSalesService, booking.Status, booking.InsertDate, admin.Name As SalesAgentName, admin.AdminID AS SalesAgentID, category.Name As DestinationName, CountryCode, booking.AutocountSyncStatus, booking.AutocountSyncMessage, booking.AutocountSyncAction, booking.CustomerAutocountSyncStatus, booking.CustomerAutocountSyncMessage, booking.CustomerAutocountSyncAction, customer.CustomerCode, booking.CustomerID');
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
		$this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
		$this->db->join('customer', 'customer.CustomerID = booking.CustomerID', 'left');

		$this->apply_booking_filters();

		// Order by
		$this->db->order_by($order_column, $order_dir);

		// Pagination
		$this->db->limit($length, $start);

		return $this->db->get('booking')->result();
	}

	/**
	 * Count total bookings without filters (for DataTables recordsTotal)
	 */
	function Count_Bookings_Total()
	{
		$this->db->from('booking');
		if($this->session->userdata('level') == 20) {
			$this->db->where('SalesAgent', $this->session->userdata('admin_id'));
		}
		$this->db->where('booking.Status !=', 'N');
		return $this->db->count_all_results();
	}

	/**
	 * Count bookings with filters applied (for DataTables recordsFiltered)
	 */
	function Count_Bookings_Filtered()
	{
		$this->db->from('booking');
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
		$this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
		$this->db->join('customer', 'customer.CustomerID = booking.CustomerID', 'left');

		$this->apply_booking_filters();

		return $this->db->count_all_results();
	}

	/**
	 * Calculate summary totals for all filtered bookings
	 * Returns total sales and calculates net profit from payments
	 */
	function Calculate_Summary()
	{
		// First get all filtered booking IDs and their NetTotal
		$this->db->select('booking.BookingID, booking.NetTotal');
		$this->db->from('booking');
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
		$this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
		$this->db->join('customer', 'customer.CustomerID = booking.CustomerID', 'left');

		$this->apply_booking_filters();

		$bookings = $this->db->get()->result();

		$total_sales = 0;
		$total_net_profit = 0;

		foreach($bookings as $booking) {
			$total_sales += $booking->NetTotal;

			// Get payments for this booking
			$payments = $this->Read_Payments($booking->BookingID);
			$total_credit = 0;
			$total_debit = 0;

			if(!empty($payments)) {
				foreach($payments as $payment) {
					if($payment->Status == 'Y' || $payment->Status == 'P') {
						if($payment->Credit != 0.00) {
							$total_credit += $payment->Credit;
						} else {
							$total_debit += $payment->Debit;
						}
					}
				}
			}
			$total_net_profit += ($total_credit - $total_debit);
		}

		return array(
			'total_sales' => $total_sales,
			'total_net_profit' => $total_net_profit
		);
	}


}