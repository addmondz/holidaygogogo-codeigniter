<?php
class Cronjob extends CI_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Cronjob_Model');
	}

	function UpdateOngoing(){
		$this->Cronjob_Model->update_ongoing();
		exit('Done');
	}

	function UpdateCompleted(){
		$this->Cronjob_Model->update_completed();
		exit('Done');
	}

}