<?php
class Login_Model extends CI_Model
{
	function Validate_Login()
	{
		$this->db->select('AdminID, Name, Gender, Level, AccessControl');
		$this->db->where('Username', $this->input->post('username'));
        $this->db->where('Password', $this->input->post('password'));
		$this->db->where('Status', 'Y');
		$this->db->limit(1);
		$login = $this->db->get('admin');
		if($login->num_rows() == 1) {
			return $login->row();
		} else {
			return false;
		}
	}
}