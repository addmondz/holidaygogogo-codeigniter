<?php
class Guest_List_Room extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Guest_List_Room_Model');
		$this->load->model('Booking_Model');
		$this->load->model('Universal_Model');
	}

	function Read()
	{
		$rooms = $this->Guest_List_Room_Model->Read_Rooms();
		echo json_encode($rooms);
	}

	function Create()
	{
		if($this->input->post()) {
			$booking_id = $this->input->post('booking_id');
			if($this->_is_gl_locked($booking_id)) {
				echo json_encode(array('success' => false, 'message' => 'Room management is locked because Guest List has been locked'));
				return;
			}
			$room_id = $this->Guest_List_Room_Model->Create();
			$this->Booking_Model->Sync_GL_From_Rooms($booking_id);
			echo json_encode(array('success' => true, 'room_id' => $room_id, 'message' => 'Room successfully created'));
		} else {
			echo json_encode(array('success' => false, 'message' => 'Invalid request'));
		}
	}

	function Update()
	{
		if($this->input->post()) {
			$room = $this->_get_room_by_id($this->input->post('room_id'));
			if($room && $this->_is_gl_locked($room['booking_id'])) {
				echo json_encode(array('success' => false, 'message' => 'Room management is locked because Guest List has been locked'));
				return;
			}
			$this->Guest_List_Room_Model->Update();
			$this->Booking_Model->Sync_GL_From_Rooms($room['booking_id']);
			echo json_encode(array('success' => true, 'message' => 'Room successfully updated'));
		} else {
			echo json_encode(array('success' => false, 'message' => 'Invalid request'));
		}
	}

	function Delete()
	{
		$room = $this->_get_room_by_id($this->input->get('room_id'));
		if($room && $this->_is_gl_locked($room['booking_id'])) {
			echo json_encode(array('success' => false, 'message' => 'Room management is locked because Guest List has been locked'));
			return;
		}
		$this->Guest_List_Room_Model->Delete();
		$this->Booking_Model->Sync_GL_From_Rooms($room['booking_id']);
		echo json_encode(array('success' => true, 'message' => 'Room successfully deleted'));
	}

	private function _get_room_by_id($room_id)
	{
		$this->db->select('id, booking_id');
		$this->db->where('id', $room_id);
		return $this->db->get('guest_list_room')->row_array();
	}

	private function _is_gl_locked($booking_id)
	{
		$this->db->select('LockStatus');
		$this->db->where('BookingID', $booking_id);
		$booking = $this->db->get('booking')->row();
		return $booking && $booking->LockStatus == 'Y';
	}
}
