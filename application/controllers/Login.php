<?php
class Login extends CI_Controller
{
	function __construct()
	{
		parent::__construct();
        $this->load->model('Login_Model');
	}

	function index()
	{
		$array = [];
		if($this->input->post('login')) {
			$login = $this->Login_Model->Validate_Login();
			if(!empty($login)) {
				switch($login->Level) {
					case 10:
						$priviledge = 'OWNER';
						break;
					case 20:
						$priviledge = 'SALES AGENT';
						break;
					case 30:
						$priviledge = 'FINANCE';
						break;
					default:
				}
				$session = array(
					'admin_id' => $login->AdminID,
					'profile_picture' => $login->Gender == 'F' ? base_url('assets/image/female.svg') : base_url('assets/image/male.svg'),
					'name' => $login->Name,
					'level' => $login->Level,
					'access_control' => explode(',', $login->AccessControl),
					'priviledge' => $priviledge
				);
				$this->activity_log('Login Success - Username:'.$_POST["username"].', Password:'.$_POST["password"], 'Y', $login->AdminID);
				$this->session->set_userdata($session);
				redirect('Dashboard');
			} else {
				$this->activity_log('Login Fail - Username:'.$_POST["username"].', Password:'.$_POST["password"], 'N', null);
				$array = array('error_message' => 'Invalid Login');
			}
		}
		$this->load->view('login', $array);
	}
	
	function Logout()
	{
		$this->session->sess_destroy();
		redirect('Login');
	}

	public function activity_log($action, $status, $user_id){
    	if($_SERVER['QUERY_STRING'] != ''){
            $get = '?'.$_SERVER['QUERY_STRING'];
        }else{
            $get = $_SERVER['QUERY_STRING'];
        }

        $data = array(
            'UserID'    => $user_id,
            'Action'    => $action,
            'IP'        => $_SERVER['REMOTE_ADDR'],
            'Url'       => current_url().$get,
            'Status'    => $status,
            'InsertBy'  => 'SYSTEM',
            'InsertDate'=> date('Y-m-d H:i:s')
        );

        return $this->db->insert('activity_log', $data);
    }
}