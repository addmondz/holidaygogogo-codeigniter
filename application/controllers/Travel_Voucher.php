<?php
class Travel_Voucher extends CI_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Booking_Model');
        $this->load->model('Universal_Model');
		$this->load->model('Booking_Product_Model');
		$this->load->model('Company_Model');
		$this->load->model('Guest_List_Model');
		$this->load->model('Guest_List_Room_Model');
	}
    
	function index()
	{
        $array = $this->Booking_Model->Booking_Document();
		if(empty($array)) {
			$this->load->view('errors/access_denied');
		} else {
            if(($this->session->has_userdata('admin_id') && $this->session->has_userdata('level')) || ($array['Status'] != 'Y' && $array['AfterSalesService'] != 'COMPLETE' || $array['Status'] == 'Y' && $array['AfterSalesService'] == 'PENDING')) {
                $array['DepositDeadline'] = empty($array['DepositDeadline']) ? '-' : strtoupper(date('j M Y', strtotime($array['DepositDeadline'])));
                $array['FullPaymentDeadline'] = strtoupper(date('j M Y', strtotime($array['FullPaymentDeadline'])));
                $array['CustomerMobile'] = $array['CountryCode'] . $array['CustomerMobile'];
                if (!empty($array['CustomerMobile2'])) {
                    $array['CustomerMobile2'] = (!empty($array['CountryCode2']) ? $array['CountryCode2'] : '') . $array['CustomerMobile2'];
                }
                if(!empty($array['StartDate']) && !empty($array['EndDate'])) {
                    $array['TravelDate'] = strtoupper(date('j M', strtotime($array['StartDate'])) . ' - ' . date('j M Y', strtotime($array['EndDate'])));
                } else {
                    $array['TravelDate'] = '-';
                }
                $pax = $this->Booking_Model->Compute_Pax_Counts($array['BookingID']);
                $pax_adult = $pax['adult']; $pax_child = $pax['child']; $pax_infant = $pax['infant'];
                $adult_str  = $pax_adult  > 0 ? ($pax_adult  == 1 ? $pax_adult  . ' ADULT '  : $pax_adult  . ' ADULTS '  ) : '';
                $child_str  = $pax_child  > 0 ? ($pax_child  == 1 ? $pax_child  . ' CHILD '  : $pax_child  . ' CHILDREN ') : '';
                $infant_str = $pax_infant > 0 ? ($pax_infant == 1 ? $pax_infant . ' INFANT ' : $pax_infant . ' INFANTS ' ) : '';
                $pax_parts = array_filter(array($adult_str, $child_str, $infant_str));
                $array['PaxNumber'] = !empty($pax_parts) ? implode('& ', $pax_parts) : '0 Pax';
                $array['InsertDate'] = strtoupper(date('j M Y', strtotime($array['InsertDate'])));
                $country_code = $this->Universal_Model->Read_Country_Code($array['SalesAgentCountryCode']);
                $array['SalesAgentMobile'] = $country_code . $array['SalesAgentMobile'];
                $array['Title'] = str_replace(' ', '_', $array['BookingNumber'] . '_' . $array['Customer'] . '_' . $array['TravelDate']);
                if(empty($array['ProductSequence'])) {
                    $array['ProductSequence'] = explode(',', $array['ProductSequence']);
                    $array['booking_products'] = $this->Booking_Product_Model->Read();
                } else {
                    $array['ProductSequence'] = explode(',', $array['ProductSequence']);
                    $booking_products = $this->Booking_Product_Model->Read();
                    $array['booking_products'] = [];
                    for($i = 0; $i < count($array['ProductSequence']); $i++) {
                        foreach($booking_products as $booking_product) {
                            if($booking_product->BookingProductID == $array['ProductSequence'][$i]) {
                                array_push($array['booking_products'], $booking_product);
                            }
                        }
                    }
                }
                foreach($array['booking_products'] as $booking_product) {
                    $booking_product->Name = (explode(' (' . $booking_product->ProductCode . ')', $booking_product->Name))[0];
                }
                $company = $this->Company_Model->Read();
                $array['CompanyName'] = $company['Name'];
                $array['CompanyRegistrationNumber'] = $company['RegistrationNumber'];
                $array['CompanyLicenseNumber'] = $company['LicenseNumber'];
                $array['CompanyAddress'] = $company['Address'];
                $array['CompanyWebsite'] = $company['Website'];

                // Get guest list data
                $bookingID = $array['BookingID'];
                $this->load->model('Guest_List_Model');
                $this->Guest_List_Model->Auto_Assign_Rooms($bookingID);
                $this->db->select('guest_list.Name As GuestFirstName, guest_list.LastName As GuestLastName, guest_list.Gender, DateOfBirth, guest_list.IdentificationNumber, guest_list.PassportNumber, Type, guest_list_room.room_name As RoomName');
                $this->db->join('guest_list_room', 'guest_list_room.id = guest_list.guest_list_room_id', 'left');
                $this->db->where('guest_list.BookingID', $bookingID);
                $this->db->where('guest_list.Status', 'Y');
                $this->db->order_by('Type', 'ASC');
                $array['guest_lists'] = $this->db->get('guest_list')->result();
                
                $array['CustomerProfileURL'] = base_url('customer/' . generate_customer_portal_slug($array['CustomerID']));

                $this->load->library('pdf');
                $this->dompdf->loadHtml($this->load->view('booking/travel_voucher', $array, true));
                $this->dompdf->set_option('isRemoteEnabled', true);
                $this->dompdf->setPaper('A4', 'potrait');
                $this->dompdf->render();
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
                $this->dompdf->stream($array['Title'] . '.pdf', array('Attachment' => 0));
            } else {
                $array = array('type' => 'Travel Voucher');
                $this->load->view('errors/bc_complete', $array);
            }
		}
    }
}