<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * PUBLIC, token-gated customer Quotation PDF for a costing scenario. Like
 * Booking_Confirmation / Travel_Voucher this extends CI_Controller (no staff
 * login) so the shareable link works for customers; access is guarded solely by
 * the unguessable costing_bookings.quotation_token. Shows the tour, per-day
 * itinerary, and total selling price (MYR) only — no cost/margin/profit/snapshot.
 *
 *   /Costing_Quotation?token=XXXX
 */
class Costing_Quotation extends CI_Controller
{
    public function index($package_id = 0)
    {
        $this->load->model('Costing_Model');

        // Preferred: per-package (/Costing/Quotation/<package_id>). Falls back to a
        // scenario token (?token=) so previously shared links still resolve.
        $package_id = (int) $package_id;
        if ($package_id <= 0) {
            $package_id = (int) $this->input->get('package', true);
        }

        if ($package_id > 0) {
            $data = $this->Costing_Model->Read_Quotation_By_Package($package_id);
        } else {
            $data = $this->Costing_Model->Read_Quotation_By_Token(trim((string) $this->input->get('token', true)));
        }

        if (empty($data)) {
            show_error('Quotation not found.', 404);
            return;
        }

        $this->load->model('Company_Model');
        $data['company'] = $this->Company_Model->Read();
        $data['public_ref'] = $this->public_ref($data['booking']);

        require_once APPPATH . 'libraries/dompdf/autoload.inc.php';
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($this->load->view('costing/quotation_pdf', $data, true), 'UTF-8');
        $dompdf->set_option('isRemoteEnabled', true);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="Quotation-' . $data['public_ref'] . '.pdf"');
        echo $dompdf->output();
        exit;
    }

    private function public_ref($booking)
    {
        $year = !empty($booking['travel_date']) ? date('Y', strtotime($booking['travel_date'])) : date('Y');
        return 'CT-' . $year . '-' . str_pad((string) $booking['id'], 4, '0', STR_PAD_LEFT);
    }
}
