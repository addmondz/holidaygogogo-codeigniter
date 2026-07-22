<?php
class MY_Controller extends CI_Controller
{
	function __construct()
	{
		parent::__construct();
		if($this->session->has_userdata('admin_id') && $this->session->has_userdata('level')) {
			if(in_array($this->session->level, [20, 25, 45, 60])) {
				// Product / Customer / Guest List are gated per-admin: an Owner-granted
				// AccessControl flag (VPR/VC/VGL) overrides the level block. See
				// setting_module_access_helper. MARKETING (60) is restricted like a
				// Sales Agent: no Settings pages except Customer / Guest List when the
				// Owner grants the VC / VGL flag.
				$level = $this->session->level;
				$access_control = (array) $this->session->access_control;
				switch($this->router->class) {
					case 'Category':
						// OP TEAM LEAD (45) may access Category; SALES AGENT (20), TEAM LEAD (25) and MARKETING (60) stay blocked
						if(in_array($this->session->level, [20, 25, 60])) {
							redirect('Dashboard');
						}
						break;
					case 'Product':
						if( ! admin_can_access_setting_module('product', $level, $access_control)) {
							redirect('Dashboard');
						}
						break;
					case 'Customer':
						if( ! admin_can_access_setting_module('customer', $level, $access_control)) {
							redirect('Dashboard');
						}
						break;
					case 'Guests':
						if( ! admin_can_access_setting_module('guests', $level, $access_control, $this->config->item('show_guest_list'))) {
							redirect('Dashboard');
						}
						break;
					case 'Company':
					case 'Admin':
					case 'Category_Code':
					case 'Supplier':
					case 'Country_Code':
					case 'Tag':
					case 'Source':
					case 'Customer_Type':
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