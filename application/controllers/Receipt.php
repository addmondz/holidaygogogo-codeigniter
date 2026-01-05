<?php

require FCPATH.'vendor/autoload.php';  
use Clegginabox\PDFMerger\PDFMerger;

class Receipt extends CI_Controller
{
    public $Booking_Model;
    public $Universal_Model;
    public $Booking_Product_Model;
    public $Company_Model;
    public $Payment_Model;
    public $session;
    public $input;
    public $db;
    public $dompdf;
    
    function __construct()
    {
        parent::__construct();
        $this->load->model('Booking_Model');
        $this->load->model('Universal_Model');
        $this->load->model('Booking_Product_Model');
        $this->load->model('Company_Model');
        $this->load->model('Payment_Model');
    }

    function index()
    {  
        $token = $this->input->get('token');
        
        if(empty($token)) {
            $this->load->view('errors/access_denied');
            return;
        }
        
        // Get booking data by token
        $this->db->where('Token', $token);
        $booking = $this->db->get('booking')->row();
        
        if(empty($booking)) {
            $this->load->view('errors/access_denied');
            return;
        }
        
        // Generate receipt on demand
        $this->generate_receipt($booking);
    }
    
    private function generate_receipt($booking)
    {
        // Get booking data
        $array = $this->Booking_Model->Booking_Document_for_receipt();
        
        if(empty($array)) {
            $this->load->view('errors/access_denied');
            return;
        }
        
        // Set receipt title and data
        $array['BookingConfirmationTitle'] = 'PAYMENT RECEIPT';
        $array['Title'] = 'Receipt_' . $array['BookingNumber'];
        $array['InsertDate'] = strtoupper(date('j M Y'));
        
        // Get type filter from URL
        $payment_type = $this->input->get('type');

        // Get approved payments for this booking
        $this->db->select('Date, Type, Credit, ReferenceNumber, AutocountReferenceNumber, Status');
        $this->db->where('BookingID', $array['BookingID']);
        $this->db->where('Status', 'Y');
        $this->db->where('Credit >', 0);
        if(!empty($payment_type)) {
            $this->db->where('Type', $payment_type);
        }
        $this->db->order_by('Date', 'ASC');
        $approved_payments = $this->db->get('payment')->result();
        
        $total_received = 0;
        foreach($approved_payments as $payment) {
            $total_received += $payment->Credit;
        }
        
        $array['approved_payments'] = $approved_payments;
        $array['total_received'] = $total_received;
        $array['balance_due'] = $array['NetTotal'] - $total_received;
        
        // Get booking products
        $_GET['booking_id'] = $array['BookingID'];
        $array['booking_products'] = $this->Booking_Product_Model->Read();
        
        // Format dates
        $array['DepositDeadline'] = empty($array['DepositDeadline']) ? '-' : strtoupper(date('j M Y', strtotime($array['DepositDeadline'])));
        $array['FullPaymentDeadline'] = strtoupper(date('j M Y', strtotime($array['FullPaymentDeadline'])));
        $array['CustomerMobile'] = $array['CountryCode'] . $array['CustomerMobile'];
        
        if(!empty($array['StartDate']) && !empty($array['EndDate'])) {
            $array['TravelDate'] = strtoupper(date('j M', strtotime($array['StartDate'])) . ' - ' . date('j M Y', strtotime($array['EndDate'])));
        } else {
            $array['TravelDate'] = '-';
        }
        
        // Format guest numbers
        if(!empty($array['Adult'])) {
            $array['Adult'] = $array['Adult'] == 1 ? $array['Adult'] . ' ADULT ' : $array['Adult'] . ' ADULTS ';
        }
        if(!empty($array['Children'])) {
            $array['Children'] = $array['Children'] == 1 ? $array['Children'] . ' CHILD ' : $array['Children'] . ' CHILDREN ';
        }
        if(!empty($array['Infant'])) {
            $array['Infant'] = $array['Infant'] == 1 ? $array['Infant'] . ' INFANT ' : $array['Infant'] . ' INFANTS ';
        }
        
        // Combine guest numbers
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
        
        // Keep numeric values - view will format them
        // No formatting needed here as the view template handles it
        
        // Format product names
        foreach($array['booking_products'] as $booking_product) {
            $booking_product->Name = (explode(' (' . $booking_product->ProductCode . ')', $booking_product->Name))[0];
        }
        
        // Get company information
        $company = $this->Company_Model->Read();
        $array['CompanyName'] = $company['Name'];
        $array['CompanyRegistrationNumber'] = $company['RegistrationNumber'];
        $array['CompanyLicenseNumber'] = $company['LicenseNumber'];
        $array['CompanyAddress'] = $company['Address'];
        $array['CompanyWebsite'] = $company['Website'];
        
        // Set Text variable for footer
        $array['Text'] = !empty($array['BookingConfirmationFooter']) ? $array['BookingConfirmationFooter'] : 'Thank you for your payment. Please keep this receipt for your records.';

        // Variables for receipt_simple2 view
        $array['ReceivedFrom'] = $array['Customer'];
        // Use AutoCount reference number from first payment if available
        $array['VoucherNo'] = !empty($approved_payments) && !empty($approved_payments[0]->AutocountReferenceNumber)
            ? $approved_payments[0]->AutocountReferenceNumber
            : 'OR-' . date('ym') . '-' . str_pad($array['BookingID'], 4, '0', STR_PAD_LEFT);
        $array['ReceiptDate'] = !empty($approved_payments)
            ? date('d/m/Y', strtotime($approved_payments[0]->Date))
            : date('d/m/Y');
        $array['RefNo'] = $array['BookingNumber'];

        // Generate Amount In Words
        $amount_parts = explode('.', $total_received);
        $ringgit = $this->Convert_Subtotal($amount_parts[0]);
        if(isset($amount_parts[1]) && $amount_parts[1] != 0) {
            $sen = 'AND CENTS ' . $this->Convert_Subtotal($amount_parts[1]);
        } else {
            $sen = '';
        }
        $array['ReceiveSumOf'] = 'RINGGIT MALAYSIA ' . strtoupper($ringgit) . ' ' . strtoupper($sen) . ' ONLY.';

        // Format payments for view
        $payments = [];
        foreach($approved_payments as $payment) {
            $pay = new stdClass();
            $pay->PaymentBy = $payment->Type;
            $pay->ChequeNo = $payment->ReferenceNumber;
            $pay->Amount = $payment->Credit;
            $payments[] = $pay;
        }
        $array['payments'] = $payments;

        // Format paid items for view
        $paid_items = [];
        $item = new stdClass();
        $item->AccNo = $array['CustomerCode'];
        $item->Description = $array['Customer'] . '     ' . $array['TravelDate'];
        $item->TaxAmount = $total_received;
        $item->Amount = $total_received;
        $paid_items[] = $item;
        $array['paid_items'] = $paid_items;

        // Total
        $array['Total'] = $total_received;

        // Generate PDF
        $this->load->library('pdf');

        // Generate single-page receipt
        $this->dompdf->loadHtml($this->load->view('receipt/receipt_simple2', $array, true));
        $this->dompdf->set_option('isRemoteEnabled', true);
        $this->dompdf->setPaper(array(0, 0, 850, 550));
        $this->dompdf->render();
        
        $pdf_output = $this->dompdf->output();

        // Output the PDF directly to the browser
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $array['BookingNumber'] . '_receipt.pdf"');
        echo $pdf_output;
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
