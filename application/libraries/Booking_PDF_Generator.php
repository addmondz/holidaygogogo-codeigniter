<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/dompdf/autoload.inc.php';

use Dompdf\Dompdf;

class Booking_PDF_Generator {

	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->model('Booking_Model');
		$this->CI->load->model('Universal_Model');
		$this->CI->load->model('Booking_Product_Model');
		$this->CI->load->model('Company_Model');
		$this->CI->load->model('Payment_Model');
	}

	/**
	 * Prepare booking data array for PDF rendering
	 */
	protected function prepare_data($token)
	{
		$array = $this->CI->Booking_Model->Booking_Document_By_Token($token);

		if (empty($array)) {
			return false;
		}

		$array['DepositDeadline'] = empty($array['DepositDeadline']) ? '-' : strtoupper(date('j M Y', strtotime($array['DepositDeadline'])));

		$deposit_mode = isset($array['DepositMode']) ? $array['DepositMode'] : 'percentage';
		if ($deposit_mode == 'fixed') {
			$array['DepositAmount'] = isset($array['DepositFixedAmount']) ? floatval($array['DepositFixedAmount']) : 0;
		} else {
			$deposit_percentage = isset($array['DepositPercentage']) ? $array['DepositPercentage'] : 0;
			$array['DepositAmount'] = ceil($array['NetTotal'] * $deposit_percentage / 100);
		}

		$array['FullPaymentDeadline'] = strtoupper(date('j M Y', strtotime($array['FullPaymentDeadline'])));

		$array['CustomerMobile'] = $array['CountryCode'] . $array['CustomerMobile'];

		if (!empty($array['StartDate']) && !empty($array['EndDate'])) {
			$array['TravelDate'] = strtoupper(date('j M', strtotime($array['StartDate'])) . ' - ' . date('j M Y', strtotime($array['EndDate'])));
		} else {
			$array['TravelDate'] = '-';
		}

		if (!empty($array['Adult'])) {
			$array['Adult'] = $array['Adult'] == 1 ? $array['Adult'] . ' ADULT ' : $array['Adult'] . ' ADULTS ';
		}

		if (!empty($array['Children'])) {
			$array['Children'] = $array['Children'] == 1 ? $array['Children'] . ' CHILD ' : $array['Children'] . ' CHILDREN ';
		}

		if (!empty($array['Infant'])) {
			$array['Infant'] = $array['Infant'] == 1 ? $array['Infant'] . ' INFANT ' : $array['Infant'] . ' INFANTS ';
		}

		if (!empty($array['Adult']) && !empty($array['Children']) && !empty($array['Infant'])) {
			$array['PaxNumber'] = $array['Adult'] . '& ' . $array['Children'] . '& ' . $array['Infant'];
		} else if (!empty($array['Adult']) && empty($array['Children']) && !empty($array['Infant'])) {
			$array['PaxNumber'] = $array['Adult'] . '& ' . $array['Infant'];
		} else if (!empty($array['Adult']) && !empty($array['Children']) && empty($array['Infant'])) {
			$array['PaxNumber'] = $array['Adult'] . '& ' . $array['Children'];
		} else if (!empty($array['Adult']) && empty($array['Children']) && empty($array['Infant'])) {
			$array['PaxNumber'] = $array['Adult'];
		} else if (empty($array['Adult']) && !empty($array['Children']) && !empty($array['Infant'])) {
			$array['PaxNumber'] = $array['Children'] . '& ' . $array['Infant'];
		} else if (empty($array['Adult']) && empty($array['Children']) && !empty($array['Infant'])) {
			$array['PaxNumber'] = $array['Infant'];
		} else {
			$array['PaxNumber'] = $array['Children'];
		}

		$array['InsertDate'] = strtoupper(date('j M Y', strtotime($array['InsertDate'])));

		$country_code = $this->CI->Universal_Model->Read_Country_Code($array['SalesAgentCountryCode']);
		$array['SalesAgentMobile'] = $country_code . $array['SalesAgentMobile'];

		$array['Title'] = str_replace(' ', '_', $array['BookingNumber'] . '_' . $array['Customer'] . '_' . $array['TravelDate']);

		$subtotal = explode('.', $array['NetTotal']);
		$ringgit = $this->convert_subtotal($subtotal[0]);

		if (isset($subtotal[1]) && $subtotal[1] != 0) {
			$sen = 'AND CENTS ' . $this->convert_subtotal($subtotal[1]);
		} else {
			$sen = '';
		}

		$negative = $subtotal[0] < 0 ? 'NEGATIVE ' : '';
		$array['Text'] = 'RINGGIT MALAYSIA ' . $negative . $ringgit . '' . $sen . ' ONLY';

		if (empty($array['ProductSequence'])) {
			$array['ProductSequence'] = explode(',', $array['ProductSequence']);
			$array['booking_products'] = $this->CI->Booking_Product_Model->Read();
		} else {
			$array['ProductSequence'] = explode(',', $array['ProductSequence']);
			$booking_products = $this->CI->Booking_Product_Model->Read();
			$array['booking_products'] = [];

			for ($i = 0; $i < count($array['ProductSequence']); $i++) {
				foreach ($booking_products as $booking_product) {
					if ($booking_product->BookingProductID == $array['ProductSequence'][$i]) {
						array_push($array['booking_products'], $booking_product);
					}
				}
			}
		}

		foreach ($array['booking_products'] as $booking_product) {
			$booking_product->Name = (explode(' (' . $booking_product->ProductCode . ')', $booking_product->Name))[0];
		}

		$array['num'] = count($array['booking_products']);

		$company = $this->CI->Company_Model->Read();
		$array['CompanyName'] = $company['Name'];
		$array['CompanyRegistrationNumber'] = $company['RegistrationNumber'];
		$array['CompanyLicenseNumber'] = $company['LicenseNumber'];
		$array['CompanyAddress'] = $company['Address'];
		$array['CompanyWebsite'] = $company['Website'];

		// Generate customer portal profile URL
		$array['CustomerProfileURL'] = base_url('customer/' . generate_customer_portal_hash($array['CustomerID']));

		// Calculate total paid and outstanding balance
		$total_paid = 0;
		$payments = $this->CI->Payment_Model->Read_Approved_Payments($array['BookingID']);
		if (!empty($payments)) {
			foreach ($payments as $payment) {
				if ($payment->Type != 'SUPPLIER REFUND' && $payment->Type != 'AGENT COMMISSION FROM SUPPLIER' && $payment->Credit > 0) {
					$total_paid += $payment->Credit;
				}
			}
		}
		$array['TotalPaid'] = $total_paid;
		$array['OutstandingBalance'] = $array['NetTotal'] - $total_paid;

		return $array;
	}

	/**
	 * Combine confirmation and footer HTML with page break
	 */
	protected function build_combined_html($array)
	{
		$confirmation_html = $this->CI->load->view('booking/booking_confirmation', $array, true);
		$footer_html = $this->CI->load->view('booking/booking_footer', $array, true);

		// Extract body content from footer page and append with page break
		if (preg_match('/<body[^>]*>(.*)<\/body>/is', $footer_html, $matches)) {
			$footer_body = $matches[1];
			$separator = '<div style="page-break-before: always;"></div>';
			$combined = str_replace('</body></html>', $separator . $footer_body . '</body></html>', $confirmation_html);
		} else {
			$combined = $confirmation_html;
		}

		return $combined;
	}

	/**
	 * Create a DOMPDF instance with the combined HTML
	 */
	protected function create_pdf($array)
	{
		$html = $this->build_combined_html($array);

		$pdf = new Dompdf();
		$pdf->loadHtml($html, 'UTF-8');
		$pdf->set_option('isRemoteEnabled', true);
		$pdf->set_option('enable_html5_parser', true);
		$pdf->setPaper('A4', 'potrait');
		$pdf->render();

		return $pdf;
	}

	/**
	 * Generate PDF and save to file
	 */
	public function generate_to_file($token, $output_path)
	{
		$array = $this->prepare_data($token);
		if (!$array) {
			return false;
		}

		$pdf = $this->create_pdf($array);
		file_put_contents($output_path, $pdf->output());

		return file_exists($output_path);
	}

	/**
	 * Convert number to words
	 */
	protected function convert_subtotal($subtotal)
	{
		if ($subtotal < 0) {
			$subtotal = abs($subtotal);
		}

		if (($subtotal < 0) || ($subtotal > 999999999)) {
			throw new \Exception('Subtotal Is Out Of Range');
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

		if ($giga) {
			$value .= $this->convert_subtotal($giga) . ' Million';
		}

		if ($kilo) {
			$value .= (empty($value) ? '' : ' ') . $this->convert_subtotal($kilo) . ' Thousand';
		}

		if ($hecto) {
			$value .= (empty($value) ? '' : ' ') . $this->convert_subtotal($hecto) . ' Hundred';
		}

		$ones = array('', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen');
		$tens = array('', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety');

		if ($deca || $number) {
			if (!empty($value)) {
				$value .= ' And ';
			}

			if ($deca < 2) {
				$value .= $ones[$deca * 10 + $number];
			} else {
				$value .= $tens[$deca];
				if ($number) {
					$value .= '-' . $ones[$number];
				}
			}
		}

		if (empty($value)) {
			$value = 'Zero';
		}

		return $value;
	}
}
