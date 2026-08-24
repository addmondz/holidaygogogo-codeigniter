<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * PUBLIC, token-gated customer Quotation PDF for a costing scenario. Like
 * Booking_Confirmation / Travel_Voucher this extends CI_Controller (no staff
 * login) so the shareable link works for customers; access is guarded solely by
 * the unguessable costing_bookings.quotation_token. Shows the tour, per-day
 * itinerary, a per-item "Package Includes" list at customer selling prices, and the
 * total selling price (MYR) — raw cost/margin/profit/snapshot stay hidden.
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

        // Use the same dated address source as the Booking Confirmation PDF so
        // both documents always show the current office address. A quotation is a
        // present-dated document, so this resolves to the new (post-cutoff) address.
        $this->load->helper('company_address');
        $data['CompanyAddress'] = pdf_company_address_for_date(date('Y-m-d'));

        // Itinerary blocks are rich text (TinyMCE). Strip layout-breaking styles
        // and inline any local images so DomPDF renders them — same pipeline the
        // booking voucher footers use.
        $this->load->helper('voucher_image');
        $this->load->helper('costing_itinerary');
        if (!empty($data['itinerary']) && is_array($data['itinerary'])) {
            $html_fields = costing_itinerary_html_fields();
            foreach ($data['itinerary'] as &$day) {
                foreach ($html_fields as $field) {
                    $day[$field] = inline_voucher_images_html(
                        sanitize_voucher_content_html(isset($day[$field]) ? $day[$field] : '')
                    );
                }
            }
            unset($day);
        }

        // Same sanitise/inline pipeline for the itinerary-wide blocks.
        if (!empty($data['itinerary_meta']) && is_array($data['itinerary_meta'])) {
            foreach ($data['itinerary_meta'] as $key => $val) {
                $data['itinerary_meta'][$key] = inline_voucher_images_html(
                    sanitize_voucher_content_html((string) $val)
                );
            }
        }

        require_once APPPATH . 'libraries/dompdf/autoload.inc.php';
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($this->load->view('costing/quotation_pdf', $data, true), 'UTF-8');
        $dompdf->set_option('isRemoteEnabled', true);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="Quotation-' . $data['public_ref'] . '.pdf"');
        // Always serve the freshest render — the quotation is edited in place, so
        // stop the browser/proxy from showing a stale cached PDF after an update.
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo $dompdf->output();
        exit;
    }

    private function public_ref($booking)
    {
        $year = !empty($booking['travel_date']) ? date('Y', strtotime($booking['travel_date'])) : date('Y');
        return 'CT-' . $year . '-' . str_pad((string) $booking['id'], 4, '0', STR_PAD_LEFT);
    }
}
