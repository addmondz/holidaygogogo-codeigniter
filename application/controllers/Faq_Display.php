<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Public FAQ display controller (no authentication).
 *
 * Serves ONLY the external/customer-facing FAQ page at /faq/external
 * (green theme). It deliberately cannot render the internal type: since this
 * controller is public, exposing internal FAQs here would let anyone read
 * them by guessing a URL. The internal page is served by the login-protected
 * Faq::Internal() instead. Both render the same accordion view (faq/display.php).
 */
class Faq_Display extends CI_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Faq_Model');
	}

	function external()
	{
		$data['type'] = 'external';
		$data['faqs'] = $this->Faq_Model->Read_Public('external');
		$this->load->view('faq/display', $data);
	}
}
