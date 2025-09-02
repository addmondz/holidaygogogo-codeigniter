<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Quotation extends CI_Controller
{
	public function index()
    {
		$this->load->helper('autocount'); // loads application/helpers/autocount_helper.php
		$this->config->load('autocount'); // loads application/config/autocount.php

		$config = $this->config->item('autocount'); // should now return array
		print_r($config);

        $result = autocount_call('quotation', 'list', ['page' => 1, 'pageSize' => 10]);
        print_r($result);
    }
	
    public function listing($accountBookId)
    {

        $params = array_merge(['accountBookId' => $accountBookId], $this->input->get());
        $result = autocount_call('quotation', 'listing', $params);
        echo json_encode($result);
    }

    public function get($accountBookId, $docNo)
    {
        $result = autocount_call('quotation', 'get', [
            'accountBookId' => $accountBookId,
            'docNo'         => $docNo
        ]);
        echo json_encode($result);
    }

    public function create($accountBookId)
    {
        $body = json_decode($this->input->raw_input_stream, true);
        $result = autocount_call('quotation', 'create', ['accountBookId' => $accountBookId], $body);
        echo json_encode($result);
    }

    public function delete($accountBookId, $docNo)
    {
        $result = autocount_call('quotation', 'delete', [
            'accountBookId' => $accountBookId,
            'docNo'         => $docNo
        ]);
        echo json_encode($result);
    }
}

