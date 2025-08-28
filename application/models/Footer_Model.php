<?php
class Footer_Model extends CI_Model
{
	function Read_Footer()
	{
		$this->db->select('BookingConfirmationTitle, BookingConfirmationContent, TravelVoucherTitle, TravelVoucherContent');
		$this->db->where('FooterID', $this->input->get('footer_id'));
		return $this->db->get('footer')->row_array();
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
		$this->db->select('BookingConfirmationTitle, BookingConfirmationContent, TravelVoucherTitle, TravelVoucherContent');
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