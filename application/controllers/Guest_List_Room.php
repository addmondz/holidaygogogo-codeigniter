<?php
class Guest_List_Room extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Guest_List_Room_Model');
		$this->load->model('Universal_Model');
	}

	function Read()
	{
		$rooms = $this->Guest_List_Room_Model->Read_Rooms();
		echo json_encode($rooms);
	}

	function Create()
	{
		if($this->session->userdata('level') == 20) {
			redirect(base_url());
		} else {
			if($this->input->post()) {
				$room_id = $this->Guest_List_Room_Model->Create();
				echo json_encode(array('success' => true, 'room_id' => $room_id, 'message' => 'Room successfully created'));
			} else {
				echo json_encode(array('success' => false, 'message' => 'Invalid request'));
			}
		}
	}

	function Update()
	{
		if($this->session->userdata('level') == 20) {
			redirect(base_url());
		} else {
			if($this->input->post()) {
				$this->Guest_List_Room_Model->Update();
				echo json_encode(array('success' => true, 'message' => 'Room successfully updated'));
			} else {
				echo json_encode(array('success' => false, 'message' => 'Invalid request'));
			}
		}
	}

	function Delete()
	{
		if($this->session->userdata('level') == 20) {
			redirect(base_url());
		} else {
			$this->Guest_List_Room_Model->Delete();
			echo json_encode(array('success' => true, 'message' => 'Room successfully deleted'));
		}
	}
}

