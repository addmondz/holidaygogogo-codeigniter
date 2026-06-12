<?php
class MY_Controller extends CI_Controller
{
	function __construct()
	{
		parent::__construct();
		if($this->session->has_userdata('admin_id') && $this->session->has_userdata('level')) {
			if(in_array($this->session->level, [20, 25, 45])) {
				switch($this->router->class) {
					case 'Category':
					case 'Product':
						// OP TEAM LEAD (45) may access Product & Category; SALES AGENT (20) and TEAM LEAD (25) stay blocked
						if(in_array($this->session->level, [20, 25])) {
							redirect('Dashboard');
						}
						break;
					case 'Company':
					case 'Admin':
					case 'Category_Code':
					case 'Supplier':
					case 'Customer':
					case 'Country_Code':
					case 'Tag':
					case 'Source':
					case 'Customer_Type':
					case 'Guests':
					case 'Quick_Filter':
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