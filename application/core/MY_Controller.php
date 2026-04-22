<?php
class MY_Controller extends CI_Controller
{
	function __construct()
	{
		parent::__construct();
		if($this->session->has_userdata('admin_id') && $this->session->has_userdata('level')) {
			if(in_array($this->session->level, [20, 25])) {
				switch($this->router->class) {
					case 'Company':
					case 'Admin':
					case 'Category_Code':
					case 'Category':
					case 'Supplier':
					case 'Customer':
					case 'Product':
					case 'Country_Code':
					case 'Tag':
					case 'Source':
					case 'Customer_Type':
						redirect('Dashboard');
						break;
					default:
				}
			}
		} else {
			redirect('Login');
		}
	}
}