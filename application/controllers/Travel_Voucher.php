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
                if(!empty($array['StartDate']) && !empty($array['EndDate'])) {
                    $array['TravelDate'] = strtoupper(date('j M', strtotime($array['StartDate'])) . ' - ' . date('j M Y', strtotime($array['EndDate'])));
                } else {
                    $array['TravelDate'] = '-';
                }
                if(!empty($array['Adult'])) {
                    $array['Adult'] = $array['Adult'] == 1 ? $array['Adult'] . ' ADULT ' : $array['Adult'] . ' ADULTS ';
                }
                if(!empty($array['Children'])) {
                    $array['Children'] = $array['Children'] == 1 ? $array['Children'] . ' CHILD ' : $array['Children'] . ' CHILDREN ';
                }
                if(!empty($array['Infant'])) {
                    $array['Infant'] = $array['Infant'] == 1 ? $array['Infant'] . ' INFANT ' : $array['Infant'] . ' INFANTS ';
                }
                if(!empty($array['Adult']) && !empty($array['Children']) && !empty($array['Infant'])) {
                    $array['PaxNumber'] = $array['Adult'] . '& ' . $array['Children'] . '& ' . $array['Infant'];
                } else {
                    if(!empty($array['Adult']) && empty($array['Children']) && !empty($array['Infant'])) {
                        $array['PaxNumber'] = $array['Adult'] . '& ' . $array['Infant'];
                    } else {
                        if(!empty($array['Adult']) && !empty($array['Children']) && empty($array['Infant'])) {
                            $array['PaxNumber'] = $array['Adult'] . '& ' . $array['Children'];
                        } else {
                            if(!empty($array['Adult']) && empty($array['Children']) && empty($array['Infant'])) {
                                $array['PaxNumber'] = $array['Adult'];
                            } else {
                                if(empty($array['Adult']) && !empty($array['Children']) && !empty($array['Infant'])) {
                                    $array['PaxNumber'] = $array['Children'] . '& ' . $array['Infant'];
                                } else {
                                    if(empty($array['Adult']) && empty($array['Children']) && !empty($array['Infant'])) {
                                        $array['PaxNumber'] = $array['Infant'];
                                    } else {
                                        $array['PaxNumber'] = $array['Children'];
                                    }
                                }
                            }
                        }
                    }
                }
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
                $bookingNumber = $array['BookingNumber'];
                $this->db->select('guest_list.Name As GuestFirstName, guest_list.LastName As GuestLastName, guest_list.Gender, DateOfBirth, guest_list.IdentificationNumber, guest_list.PassportNumber, Type');
                $this->db->where('guest_list.BookingID', $bookingNumber);
                $this->db->where('guest_list.Status', 'Y');
                $this->db->order_by('Type', 'ASC');
                $array['guest_lists'] = $this->db->get('guest_list')->result();
                
                $this->load->library('pdf');
                $this->dompdf->loadHtml($this->load->view('booking/travel_voucher', $array, true));
                $this->dompdf->set_option('isRemoteEnabled', true);
                $this->dompdf->setPaper('A4', 'potrait');
                $this->dompdf->render();
                $this->dompdf->stream($array['Title'] . '.pdf', array('Attachment' => 0));
            } else {
                $array = array('type' => 'Travel Voucher');
                $this->load->view('errors/bc_complete', $array);
            }
		}
    }
}