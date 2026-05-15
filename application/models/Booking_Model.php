<?php
class Booking_Model extends CI_Model
{
	private function apply_checklist_filter()
	{
		$raw = $this->input->get('checklist_filter');
		if(empty($raw)) {
			return false;
		}
		$ids = array_filter(array_map('intval', explode(',', $raw)), function($v){ return $v > 0; });
		if(empty($ids)) {
			return false;
		}
		$ids_list = implode(',', $ids);
		$json_contains_or = implode(' OR ', array_map(function($id) {
			return "JSON_CONTAINS(ppc.package_checklist_json, '{$id}')";
		}, $ids));

		// Find bookings that have a non-child/infant product with at least one of the selected
		// checklists assigned, but no completion record for any of those checklists on such a product
		$subquery = "booking.BookingID IN (
			SELECT DISTINCT bp.BookingID
			FROM booking_product bp
			JOIN product p ON p.ProductID = bp.ProductID AND p.is_child_or_infant = 0
			JOIN product_package_checklist ppc ON ppc.product_id = bp.ProductID
				AND ({$json_contains_or})
			WHERE bp.Status = 'Y'
			AND bp.disable_checklist_payment_out = 0
			AND NOT EXISTS (
				SELECT 1 FROM booking_checklist_completion bcc
				WHERE bcc.booking_id = bp.BookingID
				AND bcc.product_id = bp.ProductID
				AND bcc.package_checklist_id IN ({$ids_list})
			)
		)";
		$this->db->where($subquery, null, false);
		return true;
	}

	private function apply_guest_list_status_filter()
	{
		$raw = $this->input->get('guest_list_status');
		if(empty($raw)) {
			return false;
		}
		$valid = ['locked','in_progress','submitted','not_submitted'];
		$statuses = array_values(array_intersect($valid, array_map('trim', explode(',', $raw))));
		if(empty($statuses)) {
			return false;
		}

		$this->config->load('guest_list');
		$timeout = (int) $this->config->item('guest_list_lock_timeout');
		if($timeout <= 0) {
			$timeout = 90;
		}

		$active_soft_lock_sql = "(booking.Token IS NOT NULL AND EXISTS (SELECT 1 FROM guest_list_locks gll WHERE gll.guest_list_hash = booking.Token AND gll.lock_expires_at > NOW() AND gll.last_heartbeat_at IS NOT NULL AND gll.last_heartbeat_at >= DATE_SUB(NOW(), INTERVAL " . $timeout . " SECOND)))";

		$this->db->group_start();
		foreach($statuses as $i => $status) {
			if($i == 0) {
				$this->db->group_start();
			} else {
				$this->db->or_group_start();
			}

			if($status == 'locked') {
				$this->db->where('booking.LockStatus', 'Y');
			} else if($status == 'in_progress') {
				$this->db->where('booking.LockStatus', 'N');
				$this->db->where($active_soft_lock_sql, null, false);
			} else if($status == 'submitted') {
				$this->db->where('booking.LockStatus', 'N');
				$this->db->where('booking.is_submitted', 1);
			} else if($status == 'not_submitted') {
				$this->db->where('booking.LockStatus', 'N');
				$this->db->group_start();
				$this->db->where('booking.is_submitted', 0);
				$this->db->or_where('booking.is_submitted IS NULL', null, false);
				$this->db->group_end();
			}

			$this->db->group_end();
		}
		$this->db->group_end();
		return true;
	}

	function Read_Booking()
	{
		$this->db->select('booking.BookingID, booking.AllowReview, booking.CustomerReview, booking.CustomerReviewTimestamp, BookingConfirmationFooterID, TravelVoucherFooterID, booking.CountryCodeID AS CustomerCountryCode, booking.CountryCodeID2 AS CustomerCountryCode2, BookingNumber, ReservationNumber, DepositDeadline, FullPaymentDeadline, AdditionalPaymentDeadline, Customer, booking.Customer2, booking.Mobile AS CustomerMobile, booking.Mobile2 AS CustomerMobile2, StartDate, EndDate, Adult, Children, Infant, Destination, SalesAgent, Tag, BookingRemark, Subtotal, Discount, NetTotal, DepositPercentage, DepositMode, DepositFixedAmount, booking.ChatLanguage, Source, Token, booking.BookingConfirmationTitle, BookingConfirmationFooter, TravelVoucherFooter, booking.KeyContacts, booking.SpecialRemarks, ProductSequence, booking.Status, booking.CancelStatus, booking.PartialRefund, booking.LockStatus, booking.AfterSalesService, booking.bc_approved, booking.bc_approval_admin_id, booking.bc_approval_date, admin.Name AS SalesAgentName, booking.AutocountSyncStatus, booking.AutocountSyncMessage, booking.AutocountSyncAction, booking.CustomerAutocountSyncStatus, booking.CustomerAutocountSyncMessage, booking.CustomerAutocountSyncAction, customer.CustomerCode AS CustomerCode, customer.ic_passport_no AS ic_passport_no, customer.tin_no AS tin_no, customer.customer_type AS customer_type, booking.CustomerID, booking.CustomerID2, booking.BookingOP, booking.SalesAgent2, booking.InsertDate');
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('customer', 'customer.CustomerID = booking.CustomerID', 'left');
		$this->db->where('booking.BookingID', $this->input->get('booking_id'));

		$row = $this->db->get('booking')->row_array();
		if ($row && !empty($row['BookingID'])) {
			$this->load->model('Booking_Customer_Type_Model');
			$row['customer_types_selected'] = $this->Booking_Customer_Type_Model->Read_Names_By_Booking($row['BookingID']);
		}
		return $row;
	}


	function Read_All_Bookings()
	{

		$this->db->select('booking.BookingID, BookingNumber, DepositDeadline, FullPaymentDeadline, Customer, booking.Mobile As CustomerMobile, StartDate, EndDate, NetTotal, booking.ChatLanguage, Token, booking.BookingConfirmationTitle, CancelStatus, booking.PartialRefund, LockStatus, booking.is_submitted, AfterSalesService, booking.Status, booking.bc_approved, booking.bc_approval_admin_id, booking.bc_approval_date, booking.InsertDate, admin.Name As SalesAgentName, category.Name As DestinationName, CountryCode, booking.AutocountSyncStatus, booking.AutocountSyncMessage, booking.AutocountSyncAction, booking.CustomerAutocountSyncStatus, booking.CustomerAutocountSyncMessage, booking.CustomerAutocountSyncAction, customer.CustomerCode, booking.CustomerID');
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
		$this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
		$this->db->join('customer', 'customer.CustomerID = booking.CustomerID', 'left');
		if(in_array($this->session->userdata('level'), [20, 50])) {
			$this->db->where('SalesAgent', $this->session->userdata('admin_id'));
		}

		$this->db->where('booking.Status !=', 'N');
		$this->db->order_by('booking.BookingID', 'DESC');

		return $this->db->get('booking')->result();
	}

	function Read_Actionable_Bookings()
	{
		$this->db->select('booking.BookingID, BookingNumber, DepositDeadline, FullPaymentDeadline, Customer, booking.Mobile As CustomerMobile, StartDate, EndDate, NetTotal, booking.ChatLanguage, Token, booking.BookingConfirmationTitle, CancelStatus, booking.PartialRefund, LockStatus, booking.is_submitted, AfterSalesService, booking.Status, booking.bc_approved, booking.bc_approval_admin_id, booking.bc_approval_date, booking.InsertDate, admin.Name As SalesAgentName, category.Name As DestinationName, CountryCode, booking.AutocountSyncStatus, booking.AutocountSyncMessage, booking.AutocountSyncAction, booking.CustomerAutocountSyncStatus, booking.CustomerAutocountSyncMessage, booking.CustomerAutocountSyncAction, customer.CustomerCode, booking.CustomerID');
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
		$this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
		$this->db->join('customer', 'customer.CustomerID = booking.CustomerID', 'left');
		if(in_array($this->session->userdata('level'), [20, 50])) {
			$this->db->where('SalesAgent', $this->session->userdata('admin_id'));
		}

		$this->db->where_in('booking.Status', array('P', 'PP', 'PBO', 'PTV', 'PT', 'OG', 'PBC'));
		$this->db->order_by('booking.BookingID', 'DESC');

		return $this->db->get('booking')->result();
	}

	function Read_Bookings()
	{
		$this->db->select('booking.BookingID, BookingNumber, DepositDeadline, FullPaymentDeadline, Customer, booking.Mobile As CustomerMobile, StartDate, EndDate, NetTotal, booking.ChatLanguage, Token, booking.BookingConfirmationTitle, CancelStatus, booking.PartialRefund, LockStatus, booking.is_submitted, AfterSalesService, booking.Status, booking.bc_approved, booking.bc_approval_admin_id, booking.bc_approval_date, booking.InsertDate, admin.Name As SalesAgentName, category.Name As DestinationName, CountryCode, booking.AutocountSyncStatus, booking.AutocountSyncMessage, booking.AutocountSyncAction, booking.CustomerAutocountSyncStatus, booking.CustomerAutocountSyncMessage, booking.CustomerAutocountSyncAction, customer.CustomerCode, booking.CustomerID');
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
		$this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
		$this->db->join('customer', 'customer.CustomerID = booking.CustomerID', 'left');
		if(in_array($this->session->userdata('level'), [20, 50])) {
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
			$this->db->like('ReservationNumber', $this->input->get('reservation_number'));
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
				$this->db->where_in('Destination', explode(',', $this->input->get('destination')));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('sales_agent'))) {
				$this->db->where_in('SalesAgent', explode(',', $this->input->get('sales_agent')));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('booking_op'))) {
				$this->db->where_in('BookingOP', explode(',', $this->input->get('booking_op')));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('sales_agent_2'))) {
				$this->db->where_in('SalesAgent2', explode(',', $this->input->get('sales_agent_2')));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('tag'))) {
				$tags = explode(',', $this->input->get('tag'));
				$this->db->group_start();
				foreach($tags as $i => $t) {
					$t = (int)$t;
					if($i == 0) {
						$this->db->where("FIND_IN_SET($t, Tag)", null, false);
					} else {
						$this->db->or_where("FIND_IN_SET($t, Tag)", null, false);
					}
				}
				$this->db->group_end();
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('chat_language'))) {
				$this->db->where_in('booking.ChatLanguage', explode(',', $this->input->get('chat_language')));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('source'))) {
				$this->db->where_in('Source', explode(',', $this->input->get('source')));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('customer_type'))) {
				$customer_type_ids = array_filter(array_map('intval', explode(',', $this->input->get('customer_type'))));
				if(!empty($customer_type_ids)) {
					$in = implode(',', $customer_type_ids);
					$this->db->where("EXISTS (SELECT 1 FROM booking_customer_type bct WHERE bct.BookingID = booking.BookingID AND bct.CustomerTypeID IN ($in))", null, false);
				}
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('booking_confirmation_title'))) {
				$this->db->where_in('booking.BookingConfirmationTitle', explode(',', $this->input->get('booking_confirmation_title')));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('autocount_status'))) {
				$this->db->where_in('booking.AutocountSyncStatus', explode(',', $this->input->get('autocount_status')));
				$level2Ignore = 1;
			}
			if($this->apply_guest_list_status_filter()) {
				$level2Ignore = 1;
			}
			if($this->apply_checklist_filter()) {
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('cancellation_reason'))) {
				$this->db->where_in('booking.CancellationReasonID', explode(',', $this->input->get('cancellation_reason')));
				$this->db->where('CancelStatus', 'Y');
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('einvoice_status'))) {
				$einvoice_values = array_map('trim', explode(',', $this->input->get('einvoice_status')));
				$has_yes = in_array('yes', $einvoice_values);
				$has_no = in_array('no', $einvoice_values);
				if($has_yes && !$has_no) {
					$this->db->where("(SELECT COUNT(*) FROM invoice_split_pax WHERE invoice_split_pax.BookingID = booking.BookingID AND invoice_split_pax.Status = 'Y') > 0");
				} else if($has_no && !$has_yes) {
					$this->db->where("(SELECT COUNT(*) FROM invoice_split_pax WHERE invoice_split_pax.BookingID = booking.BookingID AND invoice_split_pax.Status = 'Y') = 0");
				}
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
					$today = date('Y-m-d');
					// Match display_booking_status(): a P/PP row only renders as PO when
					// there is still an outstanding balance (NetTotal > approved credits).
					$approved_credit_sql = "COALESCE((SELECT SUM(p.Credit) FROM payment p"
						. " WHERE p.BookingID = booking.BookingID"
						. " AND p.Status = 'Y' AND p.Credit > 0"
						. " AND (p.Type IS NULL OR p.Type != 'AGENT COMMISSION FROM SUPPLIER')), 0)";
					$this->db->where('CancelStatus', 'N');
					$this->db->where(
						"(((`booking`.`FullPaymentDeadline` < '".$today."' AND `booking`.`Status` IN ('P','PP'))"
						. " OR (`booking`.`DepositDeadline` < '".$today."' AND `booking`.`Status` = 'P'))"
						. " AND (`booking`.`NetTotal` - ".$approved_credit_sql.") > 0)"
					);
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
				$this->db->where('CAST(booking.InsertDate AS DATE) >=', date('Y-m-d', strtotime('-14 days')));
				$this->db->where('CAST(booking.InsertDate AS DATE) <=', date('Y-m-d'));
			}
		}

		$this->db->where('booking.Status !=', 'N');
		$this->db->order_by('booking.BookingID', 'DESC');

		return $this->db->get('booking')->result();
	}

	function Read_Bookings_With_Guest_Lists($group_by_booking_id)
	{
		if($group_by_booking_id == 'Y') {
			$this->db->select('booking.BookingID, BookingNumber, ReservationNumber, DepositDeadline, FullPaymentDeadline, Customer, booking.Mobile As CustomerMobile, StartDate, EndDate, Adult, Children, Infant, BookingRemark, Subtotal, Discount, NetTotal, ChatLanguage, CancelStatus, booking.PartialRefund, LockStatus, booking.is_submitted, AfterSalesService, booking.Status, booking.InsertDate, ANY_VALUE(guest_list.CountryCodeID) As GuestCountryCode, ANY_VALUE(Type) As Type, ANY_VALUE(guest_list.Name) As GuestName, ANY_VALUE(guest_list.Gender) As Gender, ANY_VALUE(DateOfBirth) As DateOfBirth, ANY_VALUE(Nationality) As Nationality, ANY_VALUE(guest_list.IdentificationNumber) As IdentificationNumber, ANY_VALUE(guest_list.PassportNumber) As PassportNumber, ANY_VALUE(guest_list.Mobile) As GuestMobile, ANY_VALUE(guest_list.Email) As Email, ANY_VALUE(MaritalStatus) As MaritalStatus, ANY_VALUE(Employment) As Employment, ANY_VALUE(Address) As Address, ANY_VALUE(Postcode) As Postcode, ANY_VALUE(guest_list.City) As City, ANY_VALUE(guest_list.State) As State, ANY_VALUE(guest_list.Country) As Country, ANY_VALUE(Nominee) As Nominee, ANY_VALUE(NomineeIdentificationNumber) As NomineeIdentificationNumber, ANY_VALUE(Relationship) As Relationship, admin.Name As SalesAgentName, category.Name As DestinationName, CountryCode, source.Name As SourceName', FALSE);
		} else {
			$this->db->select('booking.BookingID, BookingNumber, ReservationNumber, DepositDeadline, FullPaymentDeadline, Customer, booking.Mobile As CustomerMobile, StartDate, EndDate, Adult, Children, Infant, BookingRemark, Subtotal, Discount, NetTotal, ChatLanguage, CancelStatus, booking.PartialRefund, LockStatus, booking.is_submitted, AfterSalesService, booking.Status, booking.InsertDate, guest_list.CountryCodeID As GuestCountryCode, Type, guest_list.Name As GuestName, guest_list.Gender, DateOfBirth, Nationality, guest_list.IdentificationNumber, guest_list.PassportNumber, guest_list.Mobile As GuestMobile, guest_list.Email, MaritalStatus, Employment, Address, Postcode, guest_list.City, guest_list.State, guest_list.Country, Nominee, NomineeIdentificationNumber, Relationship, admin.Name As SalesAgentName, category.Name As DestinationName, CountryCode, source.Name As SourceName');
		}
		$this->db->join('guest_list', 'guest_list.BookingID = booking.BookingID', 'left');
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
		$this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
		$this->db->join('source', 'source.SourceID = booking.Source', 'left');
		if(in_array($this->session->userdata('level'), [20, 50])) {
			$this->db->where('SalesAgent', $this->session->userdata('admin_id'));
		}
		if(!empty($this->input->get('booking_number'))) {
			$this->db->where('BookingNumber', $this->input->get('booking_number'));
		}
		if(!empty($this->input->get('reservation_number'))) {
			$this->db->like('ReservationNumber', $this->input->get('reservation_number'));
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
			$this->db->where_in('SalesAgent', explode(',', $this->input->get('sales_agent')));
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
		$this->apply_guest_list_status_filter();
		$this->apply_checklist_filter();
		if(!empty($this->input->get('cancellation_reason'))) {
			$this->db->where('booking.CancellationReasonID', $this->input->get('cancellation_reason'));
			$this->db->where('CancelStatus', 'Y');
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
			if($this->input->get('status') == 'PBO') {
				$this->db->where('CancelStatus', 'N');
				$this->db->where('booking.Status', 'PBO');
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
		if($group_by_booking_id == 'Y') {
			$this->db->group_by('booking.BookingID');
		} else {
			$this->db->where('guest_list.Status', 'Y');
		}
		$this->db->order_by('booking.BookingID', 'DESC');
		$this->db->order_by('Type', 'ASC');
		return $this->db->get('booking')->result();
	}

	function Read_Payments($booking_id)
	{
		$this->db->select('payment.PaymentID, Type, Credit, Debit, payment.Status');
		$this->db->where('payment.BookingID', $booking_id);
		$this->db->where('payment.Status !=', 'N');
		return $this->db->get('payment')->result();
	}
	
	function Read_Admins()
	{
		$this->db->select('AdminID, Name, Level, Status');
		$this->db->where('AdminID !=', 8);
		$this->db->where('Level !=', '30');
		$this->db->where_in('Status', array('Y', 'D'));
		$this->db->order_by('Status', 'ASC');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('admin')->result();
	}

	function Read_Notify_Admins()
	{
		$this->db->select('AdminID, Name, Level, Status');
		$this->db->where('AdminID !=', 8);
		$this->db->where_in('Status', array('Y', 'D'));
		$this->db->order_by('Status', 'ASC');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('admin')->result();
	}

	function Read_Booking_OP_Admins()
	{
		$this->db->select('AdminID, Name');
		$this->db->where('AdminID !=', 8);
		$this->db->where('Level', '40');
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

	function Read_Sources_With_Inactive($source_id)
	{
		$this->db->select('SourceID, Name');
		if(!empty($source_id)) {
			$this->db->where("(Status = 'Y' OR SourceID = " . intval($source_id) . ")", NULL, FALSE);
		} else {
			$this->db->where('Status', 'Y');
		}
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
				
				// Set bc_approved to 0 for new bookings
				if (!isset($booking_item['bc_approved'])) {
					$booking_data[$key]['bc_approved'] = 0;
					$booking_data[$key]['bc_approval_date'] = null;
				}
			}
		}
		
		$this->db->insert_batch('booking', json_decode(json_encode($booking_data)));
		$booking_id = $this->db->insert_id();

		if ($booking_id) {
			$posted_customer_types = $this->input->post('customer_type');
			$this->load->model('Booking_Customer_Type_Model');
			$this->Booking_Customer_Type_Model->Sync($booking_id, is_array($posted_customer_types) ? $posted_customer_types : []);
		}

		// Log booking creation with initial status
		if ($booking_id) {
			$this->load->helper('booking_status_log');
			$initial_status = !empty($booking_data[0]['Status']) ? $booking_data[0]['Status'] : 'PBC';
			$creator_id = $this->session->userdata('admin_id');
			log_booking_creation($booking_id, $initial_status, "Booking Created", $creator_id);
		}

		$data = [
			'name'          => $this->input->post('booking')[0]['Customer'] ? $this->input->post('booking')[0]['Customer'] : null,
			'phone_number'  => $this->input->post('booking')[0]['Mobile'] ? $this->input->post('booking')[0]['Mobile'] : null,
			'ChatLanguage'  => $this->input->post('booking')[0]['ChatLanguage'] ? $this->input->post('booking')[0]['ChatLanguage'] : null,
			'updated_at'    => date('Y-m-d H:i:s'),
		];
		if (!empty($this->input->post('ic_passport_no'))) {
			$data['ic_passport_no'] = strtoupper($this->input->post('ic_passport_no'));
		}
		if ($this->input->post('tin_no') !== null) {
			$data['tin_no'] = strtoupper(trim($this->input->post('tin_no')));
		}
		$customerId = $this->input->post('CustomerID');
		if (!empty($customerId) && $customerId != 'undefined' && $customerId != 'null' && is_numeric($customerId)) {
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
			if (!empty($data['name']) || !empty($data['phone_number'])) {
				$this->load->model('Customer_Model');
				$customer_code = $this->Customer_Model->generate_customer_code($data['name']);

				$data['created_at'] = date('Y-m-d H:i:s');
				$data['CustomerCode'] = $customer_code;
				$data['AutocountSyncAction'] = 'C';
				$data['AutocountSyncStatus'] = 'P';

				$this->db->insert('customer', $data);
				$customer_id = $this->db->insert_id();
 
				// remove this no need sync directly, cron will sync customer at first 
				// // Immediately sync to Autocount
				// $this->load->library('CustomerSync');
				// $this->load->helper('autocount');
				// $config = get_autocount_config();
				// $data['CustomerID'] = $customer_id;
				// $result = $this->customersync->autocount_create($data, $config);

				// if (isset($result['status']) && ($result['status'] == 201 || $result['status'] == 204) && $result['error'] === null) {
				// 	$this->Customer_Model->update_by_id($customer_id, [
				// 		'AutocountSyncStatus'  => 'S',
				// 		'AutocountSyncMessage' => json_encode($result)
				// 	]);
				// } else {
				// 	$this->Customer_Model->update_by_id($customer_id, [
				// 		'AutocountSyncStatus'  => 'F',
				// 		'AutocountSyncMessage' => json_encode($result)
				// 	]);
				// }
			} else {
				$customer_id = null;
			}
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
			$telegram_ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
			@file_get_contents('https://api.telegram.org/bot7521016286:AAEMDyjd789UEHBH5LK4xfBzIzY9TZ80tCg/sendMessage?chat_id=-1002546036574&text=' . $text, false, $telegram_ctx);
		}

		return $booking_id;
	}

	function Create_Booking_Log()
	{
		if(current_url() == base_url('Booking/Update')) {
			$log_data = json_decode(json_encode($this->input->post('booking_log')));
			// Filter out Subtotal entries - Subtotal is a calculated field and should not be in audit log
			if (!empty($log_data)) {
				$log_data = array_values(array_filter($log_data, function($entry) {
					return !isset($entry->Column) || $entry->Column !== 'Subtotal';
				}));
			}
			if (!empty($this->_snapshot_pdf_path) && !empty($log_data)) {
				$snapshot_columns = array('NetTotal', 'StartDate', 'EndDate');
				foreach ($log_data as &$entry) {
					if (isset($entry->Column) && in_array($entry->Column, $snapshot_columns)) {
						$entry->SnapshotPDF = $this->_snapshot_pdf_path;
					} else {
						$entry->SnapshotPDF = null;
					}
				}
				unset($entry);
			}
			$this->db->insert_batch('booking_log', $log_data);
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

	protected $_snapshot_pdf_path = null;

	function generate_booking_snapshot($token, $booking_id)
	{
		$CI =& get_instance();
		$CI->load->library('Booking_PDF_Generator');

		$snapshot_dir = FCPATH . 'assets/upload/booking_snapshots/';
		if (!is_dir($snapshot_dir)) {
			mkdir($snapshot_dir, 0755, true);
		}

		$filename = $booking_id . '_' . date('Ymd_His') . '.pdf';
		$filepath = $snapshot_dir . $filename;

		$success = $CI->booking_pdf_generator->generate_to_file($token, $filepath);

		if ($success) {
			$this->_snapshot_pdf_path = 'booking_snapshots/' . $filename;
			return $this->_snapshot_pdf_path;
		}
		return null;
	}

	function Update()
	{
		// Get current booking data before update to compare changes
		$booking_id = $this->input->post('booking_id');
		$current_booking = $this->getBookingById($booking_id);
		
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
		
		// Check for critical changes that require status revert to PBC
		$needs_revert = false;
		$revert_reason = '';
		$price_increased = false;

		if ($current_booking) {
			// Check if NetTotal changed
			$new_net_total = isset($booking_data[0]['NetTotal']) ? floatval($booking_data[0]['NetTotal']) : null;
			$old_net_total = isset($current_booking->NetTotal) ? floatval($current_booking->NetTotal) : null;

			if ($new_net_total !== null && $old_net_total !== null && abs($new_net_total - $old_net_total) > 0.01) {
				$needs_revert = true;
				$price_increased = ($new_net_total > $old_net_total);
				$revert_reason = 'Booking total price changed from RM ' . number_format($old_net_total, 2) . ' to RM ' . number_format($new_net_total, 2);
			}
			
			// Check if travel dates changed
			$new_start_date = isset($booking_data[0]['StartDate']) ? $booking_data[0]['StartDate'] : null;
			$new_end_date = isset($booking_data[0]['EndDate']) ? $booking_data[0]['EndDate'] : null;
			$old_start_date = $current_booking->StartDate;
			$old_end_date = $current_booking->EndDate;
			
			// Normalize dates for comparison (handle different formats)
			$normalize_date = function($date) {
				if (empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') return null;
				// Try to parse date
				$parsed = strtotime($date);
				return $parsed ? date('Y-m-d', $parsed) : null;
			};
			
			$new_start_normalized = $normalize_date($new_start_date);
			$new_end_normalized = $normalize_date($new_end_date);
			$old_start_normalized = $normalize_date($old_start_date);
			$old_end_normalized = $normalize_date($old_end_date);
			
			if (($new_start_normalized && $new_start_normalized != $old_start_normalized) ||
			    ($new_end_normalized && $new_end_normalized != $old_end_normalized)) {
				$needs_revert = true;
				$date_change_desc = '';
				if ($new_start_normalized != $old_start_normalized) {
					$date_change_desc .= 'Start date changed from ' . ($old_start_normalized ? date('d/m/Y', strtotime($old_start_normalized)) : 'N/A') . 
					                     ' to ' . ($new_start_normalized ? date('d/m/Y', strtotime($new_start_normalized)) : 'N/A');
				}
				if ($new_end_normalized != $old_end_normalized) {
					if ($date_change_desc) $date_change_desc .= '; ';
					$date_change_desc .= 'End date changed from ' . ($old_end_normalized ? date('d/m/Y', strtotime($old_end_normalized)) : 'N/A') . 
					                     ' to ' . ($new_end_normalized ? date('d/m/Y', strtotime($new_end_normalized)) : 'N/A');
				}
				$revert_reason = $revert_reason ? $revert_reason . '; ' . $date_change_desc : $date_change_desc;
			}
			
			// Only revert when current status is PT — TC must re-approve the Travel Voucher.
			// For every other status, price/date edits are persisted without status change.
			$did_revert = false;
			if ($needs_revert && $current_booking->Status === 'PT') {
				$this->load->model('Booking_Status_Log_Model');
				$this->load->helper('booking_status_log');
				$admin_id = $this->session->userdata('admin_id') ?: 0;

				$booking_data[0]['Status'] = 'PTV';

				log_booking_status_change(
					$booking_id,
					'PTV',
					$current_booking->Status,
					$admin_id,
					'Status reverted to PENDING TRAVEL VOUCHER - TC must re-approve voucher: ' . $revert_reason,
					true
				);
				$did_revert = true;
			}

			// Generate PDF snapshot only when we actually revert
			if ($did_revert && !empty($current_booking->Token)) {
				try {
					$this->generate_booking_snapshot($current_booking->Token, $booking_id);
				} catch (\Throwable $e) {
					log_message('error', 'Failed to generate booking snapshot for BookingID ' . $booking_id . ': ' . $e->getMessage());
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
			if (!empty($this->input->post('ic_passport_no'))) { $data['ic_passport_no'] = strtoupper($this->input->post('ic_passport_no')); }
			if ($this->input->post('tin_no') !== null) { $data['tin_no'] = strtoupper(trim($this->input->post('tin_no'))); }
			if ($data) { $data['updated_at'] = date('Y-m-d H:i:s'); }
		}

		$customerId = $this->input->post('CustomerID');
		if (!empty($customerId) && $customerId != 'undefined' && $customerId != 'null' && is_numeric($customerId)) {			
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
			if (!empty($data['name']) || !empty($data['phone_number'])) {
				$this->load->model('Customer_Model');
				$customer_code = $this->Customer_Model->generate_customer_code($data['name']);

				$data['created_at'] = date('Y-m-d H:i:s');
				$data['CustomerCode'] = $customer_code;
				$data['AutocountSyncAction'] = 'C';
				$data['AutocountSyncStatus'] = 'P';

				$this->db->insert('customer', $data);
				$customer_id = $this->db->insert_id();

				// no need sync directly, cron will sync customer at first
				// // Immediately sync to Autocount
				// $this->load->library('CustomerSync');
				// $this->load->helper('autocount');
				// $config = get_autocount_config();
				// $data['CustomerID'] = $customer_id;
				// $result = $this->customersync->autocount_create($data, $config);

				// if (isset($result['status']) && ($result['status'] == 201 || $result['status'] == 204) && $result['error'] === null) {
				// 	$this->Customer_Model->update_by_id($customer_id, [
				// 		'AutocountSyncStatus'  => 'S',
				// 		'AutocountSyncMessage' => json_encode($result)
				// 	]);
				// } else {
				// 	$this->Customer_Model->update_by_id($customer_id, [
				// 		'AutocountSyncStatus'  => 'F',
				// 		'AutocountSyncMessage' => json_encode($result)
				// 	]);
				// }
			} else {
				$customer_id = null;
			}
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
		$this->db->where("CAST(`DepositDeadline` AS CHAR) = '0000-00-00'", null, false);
		$this->db->update('booking');

		$this->db->set('AdditionalPaymentDeadline', null);
		$this->db->where('BookingID', $this->input->post('booking_id'));
		$this->db->where("CAST(`AdditionalPaymentDeadline` AS CHAR) = '0000-00-00'", null, false);
		$this->db->update('booking');

		$this->db->set('StartDate', null);
		$this->db->where('BookingID', $this->input->post('booking_id'));
		$this->db->where("CAST(`StartDate` AS CHAR) = '0000-00-00'", null, false);
		$this->db->update('booking');

		$this->db->set('EndDate', null);
		$this->db->where('BookingID', $this->input->post('booking_id'));
		$this->db->where("CAST(`EndDate` AS CHAR) = '0000-00-00'", null, false);
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

		$this->Sync_Customer_From_Booking($this->input->post('booking_id'));
	}

	/**
	 * Sync the linked customer's name / phone_number from the booking row.
	 *
	 * Called on every booking update so customer contact info stays aligned with the
	 * booking even on partial AJAX saves that don't round-trip Customer/Mobile via POST.
	 * generate_customer_portal_slug() needs both fields to build the portal URL.
	 */
	public function Sync_Customer_From_Booking($booking_id)
	{
		if (empty($booking_id)) {
			return;
		}
		$this->db->query(
			"UPDATE customer c
			 JOIN booking b ON b.CustomerID = c.CustomerID
			 SET c.name = COALESCE(NULLIF(TRIM(b.Customer), ''), c.name),
			     c.phone_number = COALESCE(NULLIF(TRIM(b.Mobile), ''), c.phone_number),
			     c.updated_at = NOW()
			 WHERE b.BookingID = ?",
			[$booking_id]
		);
	}

	function Update_Cancel_Status()
	{
		$array = array(
			'CancelStatus' => $this->input->get('new_cancel_status'),
			'CancellationReasonID' => NULL,
			'UpdateBy' => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s')
		);
		$this->db->where('BookingID', $this->input->get('booking_id'));
		$this->db->update('booking', $array);
	}

	function Update_Cancel_Status_With_Reason()
	{
		$array = array(
			'CancelStatus' => 'Y',
			'CancellationReasonID' => $this->input->post('cancellation_reason_id'),
			'UpdateBy' => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s')
		);
		$this->db->where('BookingID', $this->input->post('booking_id'));
		$this->db->update('booking', $array);
	}

	function Create_Booking_Log_Cancel()
	{
		$array = array(
			'BookingID' => $this->input->post('booking_id'),
			'Column' => 'CancelStatus',
			'CurrentData' => 'N',
			'NewData' => 'Y',
			'InsertBy' => $this->session->admin_id,
			'InsertDate' => date('Y-m-d H:i:s')
		);
		$this->db->insert('booking_log', $array);
	}

	function Update_Partial_Refund_Status()
	{
		$array = array(
			'PartialRefund' => $this->input->get('new_partial_refund_status'),
			'CancellationReasonID' => NULL,
			'UpdateBy' => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s')
		);
		$this->db->where('BookingID', $this->input->get('booking_id'));
		$this->db->update('booking', $array);
	}

	function Update_Partial_Refund_Status_With_Reason()
	{
		$array = array(
			'PartialRefund' => 'Y',
			'CancellationReasonID' => $this->input->post('cancellation_reason_id'),
			'UpdateBy' => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s')
		);
		$this->db->where('BookingID', $this->input->post('booking_id'));
		$this->db->update('booking', $array);
	}

	function Create_Booking_Log_Partial_Refund()
	{
		$array = array(
			'BookingID' => $this->input->post('booking_id'),
			'Column' => 'PartialRefund',
			'CurrentData' => 'N',
			'NewData' => 'Y',
			'InsertBy' => $this->session->admin_id,
			'InsertDate' => date('Y-m-d H:i:s')
		);
		$this->db->insert('booking_log', $array);
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
		if ($this->input->get('new_lock_status') == 'Y') {
			$array['is_submitted'] = 1;
		} else {
			$this->load->model('Guest_List_Model');
			$array['is_submitted'] = $this->Guest_List_Model->Are_All_Guests_Complete($this->input->get('booking_id')) ? 1 : 0;
		}
		$this->db->where('BookingID', $this->input->get('booking_id'));
		$this->db->update('booking', $array);
	}

	function Mark_Guest_List_Submitted($booking_id)
	{
		$array = array(
			'is_submitted' => 1
		);
		$this->db->where('BookingID', $booking_id);
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
		
		// If reverting to PBC, also reset BC approval
		if ($status == 'PBC') {
			$array['bc_approved'] = 0;
			$array['bc_approval_admin_id'] = null;
			$array['bc_approval_date'] = null;
		}
		
		$this->db->where('BookingID', $booking_id);
		$this->db->update('booking', $array);
		
		// Log status change if status actually changed
		if ($current_status && $current_status != $status) {
			$this->load->helper('booking_status_log');
			$created_by = !empty($this->session->userdata('admin_id')) ? $this->session->userdata('admin_id') : 0;
			log_booking_status_update($booking_id, $current_status, $status, $created_by, null, true);
		}
	}

	function Update_BC_Approval($booking_id, $bc_approved, $admin_id = null)
	{
		$array = array(
			'bc_approved' => $bc_approved ? 1 : 0,
			'UpdateBy' => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s')
		);
		
		if ($bc_approved && $admin_id !== null) {
			$array['bc_approval_admin_id'] = $admin_id;
			$array['bc_approval_date'] = date('Y-m-d H:i:s');
			// Keep booking visible in customer portal permanently after first BC approval
			$array['customer_portal_visible'] = 1;
		} else {
			$array['bc_approval_admin_id'] = null;
			$array['bc_approval_date'] = null;
		}
		
		$this->db->where('BookingID', $booking_id);
		$this->db->update('booking', $array);
	}

	function Booking_Document()
	{
		$this->db->select('BookingID, BookingNumber, ReservationNumber, DepositDeadline, FullPaymentDeadline, Customer, booking.Customer2, booking.CustomerID, booking.CustomerID2, booking.Mobile As CustomerMobile, booking.Mobile2 As CustomerMobile2, StartDate, EndDate, Adult, Children, Infant, Subtotal, Discount, NetTotal, DepositPercentage, DepositMode, DepositFixedAmount, booking.BookingConfirmationTitle, BookingConfirmationFooter, TravelVoucherFooter, AfterSalesService, ProductSequence, booking.Status, booking.InsertDate, admin.CountryCodeID As SalesAgentCountryCode, admin.Name As SalesAgentName, admin.Mobile As SalesAgentMobile, category.Name As DestinationName, TravelVoucherTitle, booking.KeyContacts As TravelVoucherKeyContacts, booking.SpecialRemarks As TravelVoucherSpecialRemarks, country_code.CountryCode, country_code_2.CountryCode As CountryCode2');
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
		$this->db->join('footer', 'footer.FooterID = booking.TravelVoucherFooterID', 'left');
		$this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
		$this->db->join('country_code country_code_2', 'country_code_2.CountryCodeID = booking.CountryCodeID2', 'left');
		$this->db->where('Token', $this->input->get('token'));
		return $this->db->get('booking')->row_array();
	}

	function Booking_Document_By_Token($token)
	{
		$this->db->select('BookingID, BookingNumber, ReservationNumber, DepositDeadline, FullPaymentDeadline, Customer, booking.Customer2, booking.CustomerID, booking.CustomerID2, booking.Mobile As CustomerMobile, booking.Mobile2 As CustomerMobile2, StartDate, EndDate, Adult, Children, Infant, Subtotal, Discount, NetTotal, DepositPercentage, DepositMode, DepositFixedAmount, booking.BookingConfirmationTitle, BookingConfirmationFooter, TravelVoucherFooter, AfterSalesService, ProductSequence, booking.Status, booking.InsertDate, admin.CountryCodeID As SalesAgentCountryCode, admin.Name As SalesAgentName, admin.Mobile As SalesAgentMobile, category.Name As DestinationName, TravelVoucherTitle, booking.KeyContacts As TravelVoucherKeyContacts, booking.SpecialRemarks As TravelVoucherSpecialRemarks, country_code.CountryCode, country_code_2.CountryCode As CountryCode2');
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
		$this->db->join('footer', 'footer.FooterID = booking.TravelVoucherFooterID', 'left');
		$this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
		$this->db->join('country_code country_code_2', 'country_code_2.CountryCodeID = booking.CountryCodeID2', 'left');
		$this->db->where('Token', $token);
		return $this->db->get('booking')->row_array();
	}

	function Booking_Document_for_receipt()
	{
		$this->db->select('booking.BookingID, BookingNumber, ReservationNumber, DepositDeadline, DepositMode, DepositPercentage, DepositFixedAmount, FullPaymentDeadline, Customer, booking.Mobile As CustomerMobile, StartDate, EndDate, Adult, Children, Infant, Subtotal, Discount, NetTotal, booking.BookingConfirmationTitle, BookingConfirmationFooter, TravelVoucherFooter, AfterSalesService, ProductSequence, booking.Status, booking.InsertDate, admin.CountryCodeID As SalesAgentCountryCode, admin.Name As SalesAgentName, admin.Mobile As SalesAgentMobile, category.Name As DestinationName, TravelVoucherTitle, CountryCode, customer.CustomerCode');
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
		$this->db->join('footer', 'footer.FooterID = booking.TravelVoucherFooterID', 'left');
		$this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
		$this->db->join('customer', 'customer.CustomerID = booking.CustomerID', 'left');
		$this->db->where('Token', $this->input->get('token'));
		return $this->db->get('booking')->row_array();
	}

	// Rooms are the primary source of truth for displayed pax on customer-facing
	// surfaces (BC, Travel Voucher, Customer Portal). When no rooms have been set up
	// yet we fall back to booking.Adult/Children/Infant so the customer still sees a
	// meaningful figure. Room management changes never write back to those fields.
	function Compute_Pax_Counts($booking_id)
	{
		$this->load->model('Guest_List_Room_Model');

		$adult = 0; $child = 0; $infant = 0;
		$rooms = $this->Guest_List_Room_Model->Read_Rooms_By_Booking_ID($booking_id);
		if(!empty($rooms)) {
			foreach($rooms as $r) {
				$adult  += (int)$r->adult_count;
				$child  += (int)$r->child_count;
				$infant += (int)$r->infant_count;
			}
		} else {
			$this->db->select('Adult, Children, Infant');
			$this->db->where('BookingID', $booking_id);
			$row = $this->db->get('booking')->row();
			if(!empty($row)) {
				$adult  = (int)$row->Adult;
				$child  = (int)$row->Children;
				$infant = (int)$row->Infant;
			}
		}
		return array('adult' => $adult, 'child' => $child, 'infant' => $infant);
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
			->order_by('booking.BookingID', 'ASC')
			->limit($booking_qty_cront);

			// new condition remove have payment only can sync : ->where("EXISTS (SELECT 1 FROM payment WHERE payment.BookingID = booking.BookingID AND payment.Status = 'Y')")

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
		if(in_array($this->session->userdata('level'), [20, 50])) {
			$admin_id = $this->session->userdata('admin_id');
			$this->db->group_start();
			$this->db->where('SalesAgent', $admin_id);
			$this->db->or_where('SalesAgent2', $admin_id);
			$this->db->group_end();
		}

		// Hide completed bookings from TC only (SA can view in listing; detail page still blocks via Booking::View / Payment guards)
		if($this->session->userdata('level') == 50) {
			$this->db->where("NOT (booking.Status = 'Y' AND booking.AfterSalesService = 'COMPLETE')");
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
			$q = $this->input->get('customer');
			$like = $this->db->escape_like_str($q);
			$this->db->group_start();
				$this->db->like('booking.Customer', $q);
				$this->db->or_like('customer.name', $q);
				$this->db->or_where(
					"EXISTS (SELECT 1 FROM guest_list gl
						WHERE gl.BookingID = booking.BookingID
						  AND gl.Status = 'Y'
						  AND (gl.Name LIKE '%{$like}%'
							OR gl.LastName LIKE '%{$like}%'
							OR CONCAT_WS(' ', gl.Name, gl.LastName) LIKE '%{$like}%'))",
					null, false
				);
			$this->db->group_end();
			$ignore = 1;
		}

		if(!empty($this->input->get('booking_number'))) {
			$this->db->where('BookingNumber', $this->input->get('booking_number'));
			$ignore = 1;
		}

		if(!empty($this->input->get('reservation_number'))) {
			$this->db->like('ReservationNumber', $this->input->get('reservation_number'));
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
				$this->db->where_in('Destination', explode(',', $this->input->get('destination')));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('sales_agent'))) {
				$this->db->where_in('SalesAgent', explode(',', $this->input->get('sales_agent')));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('booking_op'))) {
				$this->db->where_in('BookingOP', explode(',', $this->input->get('booking_op')));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('sales_agent_2'))) {
				$this->db->where_in('SalesAgent2', explode(',', $this->input->get('sales_agent_2')));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('tag'))) {
				$tags = explode(',', $this->input->get('tag'));
				$this->db->group_start();
				foreach($tags as $i => $t) {
					$t = (int)$t;
					if($i == 0) {
						$this->db->where("FIND_IN_SET($t, Tag)", null, false);
					} else {
						$this->db->or_where("FIND_IN_SET($t, Tag)", null, false);
					}
				}
				$this->db->group_end();
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('chat_language'))) {
				$this->db->where_in('booking.ChatLanguage', explode(',', $this->input->get('chat_language')));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('source'))) {
				$this->db->where_in('Source', explode(',', $this->input->get('source')));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('customer_type'))) {
				$customer_type_ids = array_filter(array_map('intval', explode(',', $this->input->get('customer_type'))));
				if(!empty($customer_type_ids)) {
					$in = implode(',', $customer_type_ids);
					$this->db->where("EXISTS (SELECT 1 FROM booking_customer_type bct WHERE bct.BookingID = booking.BookingID AND bct.CustomerTypeID IN ($in))", null, false);
				}
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('booking_confirmation_title'))) {
				$this->db->where_in('booking.BookingConfirmationTitle', explode(',', $this->input->get('booking_confirmation_title')));
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('autocount_status'))) {
				$this->db->where_in('booking.AutocountSyncStatus', explode(',', $this->input->get('autocount_status')));
				$level2Ignore = 1;
			}
			if($this->apply_guest_list_status_filter()) {
				$level2Ignore = 1;
			}
			if($this->apply_checklist_filter()) {
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('cancellation_reason'))) {
				$this->db->where_in('booking.CancellationReasonID', explode(',', $this->input->get('cancellation_reason')));
				$this->db->where('CancelStatus', 'Y');
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('einvoice_status'))) {
				$einvoice_values = array_map('trim', explode(',', $this->input->get('einvoice_status')));
				$has_yes = in_array('yes', $einvoice_values);
				$has_no = in_array('no', $einvoice_values);
				if($has_yes && !$has_no) {
					$this->db->where("(SELECT COUNT(*) FROM invoice_split_pax WHERE invoice_split_pax.BookingID = booking.BookingID AND invoice_split_pax.Status = 'Y') > 0");
				} else if($has_no && !$has_yes) {
					$this->db->where("(SELECT COUNT(*) FROM invoice_split_pax WHERE invoice_split_pax.BookingID = booking.BookingID AND invoice_split_pax.Status = 'Y') = 0");
				}
				$level2Ignore = 1;
			}
			if(!empty($this->input->get('status'))) {
				$statuses = explode(',', $this->input->get('status'));
				$this->db->group_start();
				foreach($statuses as $i => $status) {
					$status = trim($status);
					if($i == 0) {
						$this->db->group_start();
					} else {
						$this->db->or_group_start();
					}
					if($status == 'A') {
						$this->db->where('CancelStatus', 'N');
						$this->db->where('booking.Status !=', 'N');
					}
					if($status == 'C') {
						$this->db->where('CancelStatus', 'Y');
						$this->db->where('booking.Status !=', 'N');
					}
					if($status == 'Y') {
						$this->db->where('CancelStatus', 'N');
						$this->db->where('AfterSalesService', 'COMPLETE');
						$this->db->where('booking.Status', 'Y');
					}
					if($status == 'OG') {
						$this->db->where('CancelStatus', 'N');
						$this->db->where('booking.Status', 'OG');
					}
					if($status == 'PP') {
						$this->db->where('CancelStatus', 'N');
						$this->db->where('FullPaymentDeadline >=', date('Y-m-d'));
						$this->db->where('booking.Status', 'PP');
					}
					if($status == 'PO') {
						$today = date('Y-m-d');
						// Match display_booking_status(): a P/PP row only renders as PO when
						// there is still an outstanding balance (NetTotal > approved credits).
						// Without this, fully-paid PP rows past their deadline display as
						// "PARTIAL PAYMENT" but still appear under the PO filter.
						$approved_credit_sql = "COALESCE((SELECT SUM(p.Credit) FROM payment p"
							. " WHERE p.BookingID = booking.BookingID"
							. " AND p.Status = 'Y' AND p.Credit > 0"
							. " AND (p.Type IS NULL OR p.Type != 'AGENT COMMISSION FROM SUPPLIER')), 0)";
						$this->db->where('CancelStatus', 'N');
						$this->db->where(
							"(((`booking`.`FullPaymentDeadline` < '".$today."' AND `booking`.`Status` IN ('P','PP'))"
							. " OR (`booking`.`DepositDeadline` < '".$today."' AND `booking`.`Status` = 'P'))"
							. " AND (`booking`.`NetTotal` - ".$approved_credit_sql.") > 0)"
						);
					}
					if($status == 'PGL') {
						$this->db->where('CancelStatus', 'N');
						$this->db->where('LockStatus', 'N');
						$this->db->where_in('booking.Status', array('PGL', 'PTV'));
					}
					if($status == 'PBC') {
						$this->db->where('CancelStatus', 'N');
						$this->db->where('booking.Status', 'PBC');
					}
					if($status == 'P') {
						$this->db->where('CancelStatus', 'N');
						$this->db->where("(`DepositDeadline` >= '".date('Y-m-d')."' OR (`DepositDeadline` IS NULL AND `FullPaymentDeadline` >= '".date('Y-m-d')."'))");
						$this->db->where('booking.Status', 'P');
					}
					if($status == 'PR') {
						$this->db->where('CancelStatus', 'N');
						$this->db->where('AfterSalesService', 'PENDING');
						$this->db->where('booking.Status', 'Y');
					}
					if($status == 'PT') {
						$this->db->where('CancelStatus', 'N');
						$this->db->where('booking.Status', 'PT');
					}
					if($status == 'PTV') {
						$this->db->where('CancelStatus', 'N');
						$this->db->where('LockStatus', 'Y');
						$this->db->where_in('booking.Status', array('PGL', 'PTV'));
					}
					if($status == 'PBO') {
						$this->db->where('CancelStatus', 'N');
						$this->db->where('booking.Status', 'PBO');
					}
					$this->db->group_end();
				}
				$this->db->group_end();

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
		}

		$this->db->where('booking.Status !=', 'N');
	}

	/**
	 * Read bookings with pagination for DataTables server-side processing
	 */
	function Read_Bookings_Paginated($start, $length, $order_column, $order_dir)
	{
		$this->db->select('booking.BookingID, BookingNumber, DepositDeadline, FullPaymentDeadline, Customer, booking.Mobile As CustomerMobile, StartDate, EndDate, NetTotal, booking.ChatLanguage, Token, booking.BookingConfirmationTitle, CancelStatus, booking.PartialRefund, LockStatus, booking.is_submitted, AfterSalesService, booking.Status, booking.bc_approved, booking.bc_approval_admin_id, booking.bc_approval_date, booking.InsertDate, admin.Name As SalesAgentName, admin.AdminID AS SalesAgentID, booking.BookingOP, op_admin.Name As BookingOPName, category.Name As DestinationName, CountryCode, booking.AutocountSyncStatus, booking.AutocountSyncMessage, booking.AutocountSyncAction, booking.CustomerAutocountSyncStatus, booking.CustomerAutocountSyncMessage, booking.CustomerAutocountSyncAction, customer.CustomerCode, booking.CustomerID, source.Name AS SourceName, cancellation_reason.Name AS CancellationReasonName, booking.SalesAgent2, sa2_admin.Name As SalesAgent2Name');
		$this->db->select("(SELECT COUNT(*) FROM invoice_split_pax WHERE invoice_split_pax.BookingID = booking.BookingID AND invoice_split_pax.Status = 'Y') AS has_einvoice", FALSE);
		$this->db->select("(CASE WHEN booking.CancelStatus = 'Y' THEN 10 WHEN booking.DepositDeadline IS NOT NULL AND ((booking.DepositDeadline < CURDATE() AND booking.Status = 'P') OR (booking.FullPaymentDeadline < CURDATE() AND booking.Status IN ('P','PP'))) THEN 1 WHEN booking.DepositDeadline IS NULL AND booking.FullPaymentDeadline < CURDATE() AND booking.Status IN ('P','PP') THEN 1 WHEN booking.Status = 'P' THEN 2 WHEN booking.Status = 'PP' THEN 3 WHEN booking.Status = 'PBC' THEN 4 WHEN booking.Status = 'PBO' THEN 5 WHEN booking.LockStatus = 'N' AND booking.Status = 'PTV' THEN 6 WHEN booking.LockStatus = 'Y' AND booking.Status = 'PTV' THEN 7 WHEN booking.Status = 'PT' THEN 8 WHEN booking.Status = 'OG' THEN 9 WHEN booking.AfterSalesService = 'PENDING' AND booking.Status = 'Y' THEN 11 WHEN booking.Status = 'Y' THEN 12 ELSE 99 END) AS status_sort_priority", FALSE);
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('admin AS sa2_admin', 'sa2_admin.AdminID = booking.SalesAgent2', 'left');
		$this->db->join('admin AS op_admin', 'op_admin.AdminID = booking.BookingOP', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
		$this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
		$this->db->join('customer', 'customer.CustomerID = booking.CustomerID', 'left');
		$this->db->join('source', 'source.SourceID = booking.Source', 'left');
		$this->db->join('cancellation_reason', 'cancellation_reason.CancellationReasonID = booking.CancellationReasonID', 'left');

		$this->apply_booking_filters();

		// Order by
		$this->db->order_by($order_column, $order_dir, FALSE);

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
		if(in_array($this->session->userdata('level'), [20, 50])) {
			$admin_id = $this->session->userdata('admin_id');
			$this->db->group_start();
			$this->db->where('SalesAgent', $admin_id);
			$this->db->or_where('SalesAgent2', $admin_id);
			$this->db->group_end();
		}
		// Hide completed bookings from TC only (SA can view in listing; detail page still blocks via Booking::View / Payment guards)
		if($this->session->userdata('level') == 50) {
			$this->db->where("NOT (booking.Status = 'Y' AND booking.AfterSalesService = 'COMPLETE')");
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
		$this->db->join('source', 'source.SourceID = booking.Source', 'left');
		$this->db->join('cancellation_reason', 'cancellation_reason.CancellationReasonID = booking.CancellationReasonID', 'left');

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
		$this->db->join('source', 'source.SourceID = booking.Source', 'left');
		$this->db->join('cancellation_reason', 'cancellation_reason.CancellationReasonID = booking.CancellationReasonID', 'left');

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

	/**
	 * Bulk update AutocountSyncStatus for multiple bookings
	 * @param array $booking_ids Array of booking IDs to update
	 * @param string $status The new status to set (e.g., 'P' for Pending)
	 * @return bool True if at least one row was affected
	 */
	function Update_Autocount_Status_Bulk($booking_ids, $status)
	{
		$this->db->where_in('BookingID', $booking_ids);
		$this->db->update('booking', [
			'AutocountSyncStatus' => $status,
			'AutocountSyncMessage' => null
		]);
		return $this->db->affected_rows() > 0;
	}

	/**
	 * Update AutocountSyncStatus to Pending with reset logic
	 * If status is F: update directly to P
	 * If status is P: update to F first, then to P (to trigger re-sync)
	 * @param array $booking_ids Array of booking IDs to update
	 * @return bool True on success
	 */
	function Update_Autocount_Status_To_Pending_With_Reset($booking_ids)
	{
		// Get current status for all selected bookings
		$this->db->select('BookingID, AutocountSyncStatus');
		$this->db->where_in('BookingID', $booking_ids);
		$bookings = $this->db->get('booking')->result_array();

		foreach ($bookings as $booking) {
			if ($booking['AutocountSyncStatus'] == 'P') {
				// P -> F -> P (intermediate F to reset)
				$this->db->where('BookingID', $booking['BookingID']);
				$this->db->update('booking', [
					'AutocountSyncStatus' => 'F',
					'AutocountSyncMessage' => 'Reset from P status'
				]);
			}
			// Now update to P
			$this->db->where('BookingID', $booking['BookingID']);
			$this->db->update('booking', [
				'AutocountSyncStatus' => 'P',
				'AutocountSyncMessage' => null
			]);
		}

		return true;
	}

	function Read_Booking_Logs($booking_id)
	{
		$this->db->select('booking_log.Column, booking_log.CurrentData, booking_log.NewData, booking_log.InsertDate, booking_log.SnapshotPDF, admin.Name As AdminName');
		$this->db->from('booking_log');
		$this->db->join('admin', 'admin.AdminID = booking_log.InsertBy', 'left');
		$this->db->where('booking_log.BookingID', $booking_id);
		$this->db->order_by('booking_log.InsertDate', 'DESC');
		return $this->db->get()->result_array();
	}

	function Create_GL_From_Rooms($booking_id)
	{
		$this->load->model('Guest_List_Room_Model');
		$this->load->model('Guest_List_Model');
		$rooms = $this->Guest_List_Room_Model->Read_Rooms_By_Booking_ID($booking_id);

		$adult_total = 0;
		$child_total = 0;
		$infant_total = 0;

		if (!empty($rooms)) {
			foreach ($rooms as $room) {
				$adult_total += (int)$room->adult_count;
				$child_total += (int)$room->child_count;
				$infant_total += (int)$room->infant_count;
			}
		}

		for ($i = 0; $i < $adult_total; $i++) {
			$this->Guest_List_Model->Create($booking_id, 'ADULT');
		}
		for ($i = 0; $i < $child_total; $i++) {
			$this->Guest_List_Model->Create($booking_id, 'CHILD');
		}
		for ($i = 0; $i < $infant_total; $i++) {
			$this->Guest_List_Model->Create($booking_id, 'INFANT');
		}

		$this->Guest_List_Model->Auto_Assign_Rooms($booking_id);
	}

	function Sync_GL_From_Rooms($booking_id)
	{
		$this->load->model('Guest_List_Room_Model');
		$this->load->model('Guest_List_Model');
		$rooms = $this->Guest_List_Room_Model->Read_Rooms_By_Booking_ID($booking_id);

		// Skip sync if booking has no rooms set up yet — avoid wiping existing GL entries
		if (empty($rooms)) {
			return;
		}

		$target = array('ADULT' => 0, 'CHILD' => 0, 'INFANT' => 0);
		foreach ($rooms as $room) {
			$target['ADULT'] += (int)$room->adult_count;
			$target['CHILD'] += (int)$room->child_count;
			$target['INFANT'] += (int)$room->infant_count;
		}

		foreach ($target as $type => $needed) {
			// Count current active GL entries of this type
			$this->db->where('BookingID', $booking_id);
			$this->db->where('Type', $type);
			$this->db->where('Status', 'Y');
			$this->db->order_by('GuestListID', 'ASC');
			$existing = $this->db->get('guest_list')->result();
			$current_count = count($existing);

			if ($current_count < $needed) {
				// Create missing GL entries
				for ($i = 0; $i < ($needed - $current_count); $i++) {
					$this->Guest_List_Model->Create($booking_id, $type);
				}
			} elseif ($current_count > $needed) {
				// Soft-delete excess GL entries (from the end, preferring blank ones)
				$to_remove = $current_count - $needed;
				// Sort: prefer removing blank entries (no Name) first
				$blank = array();
				$filled = array();
				foreach ($existing as $gl) {
					if (empty($gl->Name)) {
						$blank[] = $gl;
					} else {
						$filled[] = $gl;
					}
				}
				// Remove from blank first (reversed so we remove latest), then filled
				$removable = array_merge(array_reverse($blank), array_reverse($filled));
				for ($i = 0; $i < $to_remove && $i < count($removable); $i++) {
					$this->Guest_List_Model->Delete($removable[$i]->GuestListID);
				}
			}
		}

		$this->Guest_List_Model->Auto_Assign_Rooms($booking_id);

		// Guest rows were just added/removed — refresh is_submitted so the
		// booking list GL icon stays in sync with actual guest completeness.
		$is_submitted = $this->Guest_List_Model->Are_All_Guests_Complete($booking_id) ? 1 : 0;
		$this->db->where('BookingID', $booking_id);
		$this->db->update('booking', array('is_submitted' => $is_submitted));
	}

}
