<?php
class Message extends CI_Controller
{
	function __construct()
	{
		parent::__construct();
	}
	
	function index()
	{
		$array = array('url' => $this->input->get('url'));
		$this->load->view('booking/message', $array);
	}
}