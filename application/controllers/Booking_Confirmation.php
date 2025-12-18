<?php

require_once APPPATH.'libraries/dompdf/autoload.inc.php';
require FCPATH.'vendor/autoload.php';  
use Clegginabox\PDFMerger\PDFMerger;
use Dompdf\Dompdf;

class Booking_Confirmation extends CI_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Booking_Model');
        $this->load->model('Universal_Model');
		$this->load->model('Booking_Product_Model');
		$this->load->model('Company_Model');
	}

    function index()
	{  
        $identifier = floor(microtime(true) * 1000);

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
                $subtotal = explode('.', $array['NetTotal']);
                $ringgit = $this->Convert_Subtotal($subtotal[0]);
                if(isset($subtotal[1]) && $subtotal[1] != 0) {
                    $sen = 'AND CENTS ' . $this->Convert_Subtotal($subtotal[1]);
                } else {
                    $sen = '';
                }
                $negative = $subtotal[0] < 0 ? 'NEGATIVE ' : '';
                $array['Text'] = 'RINGGIT MALAYSIA ' . $negative . $ringgit . '' . $sen . ' ONLY';
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

                    if(isset($_GET['nick'])) {
                        echo "<pre>";
                        print_r($array['ProductSequence']);exit;
                    }
                }
                
                foreach($array['booking_products'] as $booking_product) {
                    $booking_product->Name = (explode(' (' . $booking_product->ProductCode . ')', $booking_product->Name))[0];
                }
                $array['num'] = count($array['booking_products']);
                $company = $this->Company_Model->Read();
                $array['CompanyName'] = $company['Name'];
                $array['CompanyRegistrationNumber'] = $company['RegistrationNumber'];
                $array['CompanyLicenseNumber'] = $company['LicenseNumber'];
                $array['CompanyAddress'] = $company['Address'];
                $array['CompanyWebsite'] = $company['Website'];

                if(isset($_GET['nick'])) {
                    $this->load->view('booking/booking_confirmation', $array); exit;
                }
                $pdf1 = new Dompdf();
                $pdf1->loadHtml($this->load->view('booking/booking_confirmation', $array, true), 'UTF-8');
                $pdf1->set_option('isRemoteEnabled', true);
                $pdf1->set_option('enable_html5_parser', true);
                $pdf1->setPaper('A4', 'potrait');
                $pdf1->render();
                $output = $pdf1->output();
                file_put_contents('assets/upload/1_'.$identifier.'.pdf', $output);
                // -- footer -- //
                $pdf2 = new Dompdf();
                $pdf2->loadHtml($this->load->view('booking/booking_footer', $array, true), 'UTF-8');
                $pdf2->set_option('isRemoteEnabled', true);
                $pdf2->set_option('enable_html5_parser', true);
                $pdf2->setPaper('A4', 'potrait');
                $pdf2->render();
                $output2 = $pdf2->output();
                file_put_contents('assets/upload/2_'.$identifier.'.pdf', $output2);

                $pdf = new \Clegginabox\PDFMerger\PDFMerger;

                $pdf->addPDF('assets/upload/1_'.$identifier.'.pdf', 'all'); 
                $pdf->addPDF('assets/upload/2_'.$identifier.'.pdf', 'all');
                $pdf->merge('browser', $array['Title'].'3.pdf', 'P');
                unlink('assets/upload/1_'.$identifier.'.pdf');
                unlink('assets/upload/2_'.$identifier.'.pdf');
                
                // $this->dompdf->stream('assets/upload/booking/'.$array['Title'] . '.pdf', array('Attachment' => 0));
            } else {
                $array = array('type' => 'Booking Confirmation');
                $this->load->view('errors/bc_complete', $array);
            }
		}
    }
    
	function Convert_Subtotal($subtotal) 
    {
        if($subtotal < 0) {
            $negative = true;
            $subtotal = abs($subtotal);
        } else {
            $negative = false;
        }

        if(($subtotal < 0) || ($subtotal > 999999999)) {
            throw new Exception('Subtotal Is Out Of Range');
        }
        $giga = floor($subtotal / 1000000);
        $subtotal -= $giga * 1000000;
        $kilo = floor($subtotal / 1000);
        $subtotal -= $kilo * 1000;
        $hecto = floor($subtotal / 100);
        $subtotal -= $hecto * 100;
        $deca = floor($subtotal / 10);
        $number = $subtotal % 10;
        $value = '';
        if($giga) {
            $value .= $this->Convert_Subtotal($giga) . ' Million';
        }
        if($kilo) {
            $value .= (empty($value) ? '' : ' ') . $this->Convert_Subtotal($kilo) . ' Thousand';
        }
        if($hecto) {
            $value .= (empty($value) ? '' : ' ') . $this->Convert_Subtotal($hecto) . ' Hundred';
        }
        $ones = array('', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen');
        $tens = array('', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety');
        if($deca || $number) {
            if(!empty($value)) {
                $value .= ' And ';
            }
            if($deca < 2) {
                $value .= $ones[$deca * 10 + $number];
            } else {
                $value .= $tens[$deca];
                if($number) {
                    $value .= '-' . $ones[$number];
                }
            }
        }
        if(empty($value)) {
            $value = 'Zero';
        }
        return $value;
    }
}