<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Recalculate {

    function __construct()
    {
        // Get the CodeIgniter super object
        $this->CI =& get_instance();

        $this->CI->load->model('Booking_Model');
        $this->CI->load->model('Booking_Product_Model');
        $this->CI->load->model('Payment_Model');
        $this->CI->load->model('Universal_Model');
    }

    public function recalculate_all_bookings() {

        // 1. Get all the bookings
        $bookings = $this->CI->Booking_Model->Read_All_Bookings();

        // 2. Run the logic
        foreach($bookings as $booking) {
            if((date('Y-m-d') >= $booking->StartDate && date('Y-m-d') <= $booking->EndDate) && ($booking->Status == 'PT')) {
                $this->CI->Booking_Model->Update_After_Sales_Service2($booking->BookingID);
                $this->CI->Booking_Model->Update_Status('OG', $booking->BookingID);
                $this->CI->Booking_Model->Create_Booking_Log2($booking->Status, 'OG', $booking->BookingID);
            } else {
                if((date('Y-m-d') < $booking->StartDate && ($booking->Status == 'OG'))) {
                    $this->CI->Booking_Model->Update_After_Sales_Service2($booking->BookingID);
                    $this->CI->Booking_Model->Update_Status('PT', $booking->BookingID);
                    $this->CI->Booking_Model->Create_Booking_Log2($booking->Status, 'PT', $booking->BookingID);
                } else {
                    if((date('Y-m-d') > $booking->EndDate && ($booking->Status == 'PT' || $booking->Status == 'OG'))) {
                        $this->CI->Booking_Model->Update_After_Sales_Service2($booking->BookingID);
                        $this->CI->Booking_Model->Update_Status('Y', $booking->BookingID);
                        $this->CI->Booking_Model->Create_Booking_Log2($booking->Status, 'Y', $booking->BookingID);
                    }
                }
            }

            // Skip payment-based status transitions for completed bookings
            if($booking->Status == 'Y') {
                continue;
            }

            $payments = $this->CI->Booking_Model->Read_Payments($booking->BookingID);
            $total_approved_credit = 0;
            if(!empty($payments)) {
                foreach($payments as $payment) {
                    if($payment->Type != 'SUPPLIER REFUND' && $payment->Credit != 0.00 && $payment->Status == 'Y') {
                        $total_approved_credit += $payment->Credit;
                    }
                }
                if($total_approved_credit != 0) {
                    if(strval($total_approved_credit) >= $booking->NetTotal) {
                        $full_payment_existed = $this->CI->Payment_Model->Read_Type($booking->BookingID);
                        if($full_payment_existed) {
                            if($booking->Status == 'P' || $booking->Status == 'PP') {
                                $this->CI->Booking_Model->Update_Status('PTV', $booking->BookingID);
                                $this->CI->Booking_Model->Create_Booking_Log2($booking->Status, 'PTV', $booking->BookingID);
                            }
                        } else {
                            if($booking->Status == 'P') {
                                $this->CI->Booking_Model->Update_Status('PP', $booking->BookingID);
                                $this->CI->Booking_Model->Create_Booking_Log2($booking->Status, 'PP', $booking->BookingID);
                            }
                        }
                    } else {
                        if($booking->Status != 'PP') {
                            $this->CI->Booking_Model->Update_Status('PP', $booking->BookingID);
                            $this->CI->Booking_Model->Create_Booking_Log2($booking->Status, 'PP', $booking->BookingID);
                        }
                    }
                } else {
                    if($booking->Status != 'P') {
                        $this->CI->Booking_Model->Update_Status('P', $booking->BookingID);
                        $this->CI->Booking_Model->Create_Booking_Log2($booking->Status, 'P', $booking->BookingID);
                    }
                }
            } else {
                if($booking->Status != 'P') {
                    $this->CI->Booking_Model->Update_Status('P', $booking->BookingID);
                    $this->CI->Booking_Model->Create_Booking_Log2($booking->Status, 'P', $booking->BookingID);
                }
            }
        }
    }
}