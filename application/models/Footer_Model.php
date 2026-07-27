<?php
class Footer_Model extends CI_Model
{
	function Read_Footer()
	{
		$this->db->where('FooterID', $this->input->get('footer_id'));
		$footer = $this->db->get('footer')->row_array();
		// Handle case where footer is not found or columns don't exist
		if(empty($footer)) {
			$footer = array();
		}
		// Select specific columns we need, handling missing columns gracefully
		$result = array(
			'BookingConfirmationTitle' => isset($footer['BookingConfirmationTitle']) ? $footer['BookingConfirmationTitle'] : '',
			'BookingConfirmationContent' => isset($footer['BookingConfirmationContent']) ? $footer['BookingConfirmationContent'] : '',
			'TravelVoucherTitle' => isset($footer['TravelVoucherTitle']) ? $footer['TravelVoucherTitle'] : '',
			'TravelVoucherContent' => isset($footer['TravelVoucherContent']) ? $footer['TravelVoucherContent'] : '',
			'KeyContacts' => isset($footer['KeyContacts']) ? $footer['KeyContacts'] : '',
			'SpecialRemarks' => isset($footer['SpecialRemarks']) ? $footer['SpecialRemarks'] : ''
		);
		return $result;
	}

	function Read_Footers1()
	{
		$this->db->select('FooterID, BookingConfirmationTitle, TravelVoucherTitle, Status');
		if(!empty($this->input->get('booking_confirmation_title'))) {
			$this->db->where('BookingConfirmationTitle', $this->input->get('booking_confirmation_title'));
		}
		if(!empty($this->input->get('travel_voucher_title'))) {
			$this->db->where('TravelVoucherTitle', $this->input->get('travel_voucher_title'));
		}
		$this->db->where('Status', 'Y');
		$this->db->order_by('BookingConfirmationTitle', 'ASC');
		$this->db->order_by('TravelVoucherTitle', 'ASC');
		return $this->db->get('footer')->result();
	}

	function Read_Footers2()
	{
		// Use * to get all columns, then we'll handle missing ones in the controller
		// This prevents SQL errors if columns don't exist yet
		if(!empty($this->input->get('booking_confirmation_title'))) {
			$this->db->where('BookingConfirmationTitle', $this->input->get('booking_confirmation_title'));
		}
		if(!empty($this->input->get('travel_voucher_title'))) {
			$this->db->where('TravelVoucherTitle', $this->input->get('travel_voucher_title'));
		}
		$this->db->where('Status', 'Y');
		$this->db->order_by('BookingConfirmationTitle', 'ASC');
		$this->db->order_by('TravelVoucherTitle', 'ASC');
		return $this->db->get('footer')->result();
	}
	
	function Create()
	{
		$array = array(
			'BookingConfirmationTitle' => strtoupper($this->input->post('booking_confirmation_title')),
			'BookingConfirmationContent' => $this->input->post('booking_confirmation_content'),
			'TravelVoucherTitle' => strtoupper($this->input->post('travel_voucher_title')),
			'TravelVoucherContent' => $this->input->post('travel_voucher_content'),
			'KeyContacts' => $this->input->post('key_contacts'),
			'SpecialRemarks' => $this->input->post('special_remarks'),
			'InsertBy' => $this->session->userdata('admin_id'),
			'InsertDate' => date('Y-m-d H:i:s')
		);
		$this->db->insert('footer', $array);
	}
	
	function Update($footer)
	{
		$this->db->update_batch('footer', $footer, 'FooterID');
	}

	function Detect($column, $value)
	{
		$this->db->where($column, $value);
		if($this->db->get('footer')->row()) {
			return true;
		} else {
			return false;
		}
	}
}