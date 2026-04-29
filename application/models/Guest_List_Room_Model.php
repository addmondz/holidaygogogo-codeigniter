<?php
class Guest_List_Room_Model extends CI_Model
{
	function Read_Rooms()
	{
		$this->db->select('id, booking_id, room_name, adult_count, child_count, infant_count, Status');
		$this->db->where('booking_id', $this->input->get('booking_id'));
		$this->db->where('Status', 'Y');
		$this->db->order_by('LENGTH(room_name)', 'ASC', FALSE);
		$this->db->order_by('room_name', 'ASC');
		return $this->db->get('guest_list_room')->result();
	}

	function Read_Rooms_By_Booking_ID($booking_id)
	{
		$this->db->select('id, booking_id, room_name, adult_count, child_count, infant_count, Status');
		$this->db->where('booking_id', $booking_id);
		$this->db->where('Status', 'Y');
		$this->db->order_by('LENGTH(room_name)', 'ASC', FALSE);
		$this->db->order_by('room_name', 'ASC');
		return $this->db->get('guest_list_room')->result();
	}

	function Read_Room()
	{
		$this->db->select('id, booking_id, room_name, adult_count, child_count, infant_count, Status');
		$this->db->where('id', $this->input->get('room_id'));
		return $this->db->get('guest_list_room')->row_array();
	}

	function Create()
	{
		$array = array(
			'booking_id' => $this->input->post('booking_id'),
			'room_name' => strtoupper($this->input->post('room_name')),
			'adult_count' => (int)$this->input->post('adult_count'),
			'child_count' => (int)$this->input->post('child_count'),
			'infant_count' => (int)$this->input->post('infant_count'),
			'InsertBy' => $this->session->userdata('admin_id'),
			'InsertDate' => date('Y-m-d H:i:s')
		);
		$this->db->insert('guest_list_room', $array);
		return $this->db->insert_id();
	}

	function Update()
	{
		$array = array(
			'room_name' => strtoupper($this->input->post('room_name')),
			'adult_count' => (int)$this->input->post('adult_count'),
			'child_count' => (int)$this->input->post('child_count'),
			'infant_count' => (int)$this->input->post('infant_count'),
			'UpdateBy' => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s')
		);
		$this->db->where('id', $this->input->post('room_id'));
		$this->db->update('guest_list_room', $array);
	}

	function Delete()
	{
		$array = array(
			'Status' => 'N',
			'UpdateBy' => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s')
		);
		$this->db->where('id', $this->input->get('room_id'));
		$this->db->update('guest_list_room', $array);
	}
}
