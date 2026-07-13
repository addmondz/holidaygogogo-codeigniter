<?php
class Guests extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		if (!$this->config->item('show_guest_list')) {
			redirect(base_url('Booking'));
		}
		$this->load->model('Guests_Model');
		$this->load->model('Booking_Model');
		$this->load->model('Customer_Type_Model');
		$this->load->helper('guest_contact');
	}

	function index()
	{
		$titles = array('tab_title' => 'HolidayGoGoGo | Guest List', 'breadcrumb_title' => 'Guest List');
		$page   = max(1, (int) $this->input->get('page'));
		$limit  = 30;
		$offset = ($page - 1) * $limit;

		$this->Guests_Model->Set_Mode('guest');
		$data['guests']         = $this->Guests_Model->Read_Guests($limit, $offset);
		$this->load->model('Ghl_Messages_Model');
		$data['msg_log_phones'] = $this->Ghl_Messages_Model->Phones_With_Messages_For_Guests($data['guests']);
		$data['remark_counts']  = $this->Remark_Counts_For_Guests($data['guests']);
		$data['total']          = null;
		$data['page']           = $page;
		$data['limit']          = $limit;
		$data['list_base']      = 'Guests';
		$data['page_title']     = 'Guest List Records';
		$data['admins']         = $this->Booking_Model->Read_Admins();
		$data['sources']        = $this->Booking_Model->Read_Sources();
		$data['destinations']   = $this->Booking_Model->Read_Categories();
		$data['customer_types'] = $this->Customer_Type_Model->Read_Customer_Types();
		$data['nationalities']  = $this->Guests_Model->Read_Distinct('Nationality');
		$data['languages']      = $this->Guests_Model->Read_Distinct('ChatLanguage');
		$data['edit_languages'] = $this->Guest_Languages();
		$this->load->view('layout/header', $titles);
		$this->load->view('guests/index', $data);
		$this->load->view('layout/footer');
	}

	function Count()
	{
		$page  = max(1, (int) $this->input->get('page'));
		$limit = 30;
		$this->Guests_Model->Set_Mode('guest');
		$total = (int) $this->Guests_Model->Count_Guests();

		$pagination_html = $this->load->view('guests/_pagination', array(
			'total' => $total,
			'page'  => $page,
			'limit' => $limit,
			'query' => $this->input->get(),
		), true);

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode(array(
				'total'           => $total,
				'pagination_html' => $pagination_html,
			)));
	}

	function Update_Contact()
	{
		$out = function ($data) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode($data));
		};

		$dedup_key = (string) $this->input->post('dedup_key');
		$mobile    = trim((string) $this->input->post('mobile'));

		if ($dedup_key === '') {
			return $out(array('ok' => false, 'message' => 'Missing guest reference.'));
		}

		$valid = guest_contact_validate_mobile($mobile);
		if (!$valid['ok']) {
			return $out(array('ok' => false, 'message' => $valid['error']));
		}

		// Block when the new number already belongs to a different person.
		$new_key = guest_contact_normalize_key($mobile);
		if ($new_key !== '' && $new_key !== $dedup_key
			&& $this->Guests_Model->Contact_Key_Belongs_To_Other($new_key, $dedup_key)) {
			return $out(array('ok' => false, 'message' => 'This contact number already belongs to another guest.'));
		}

		// Levels 20/50 may only edit guests on their own bookings (mirrors the
		// read-side scoping in Guests_Model::Read_Guests).
		$scope_admin_id = in_array($this->session->userdata('level'), array(20, 50))
			? $this->session->userdata('admin_id')
			: null;

		$affected = $this->Guests_Model->Update_Guest_Contact(
			$dedup_key,
			$mobile,
			$this->session->userdata('admin_id'),
			$scope_admin_id
		);

		if ($affected < 1) {
			return $out(array('ok' => false, 'message' => 'Guest not found or you are not allowed to edit it.'));
		}

		return $out(array(
			'ok'        => true,
			'mobile'    => $mobile,
			'dedup_key' => $new_key !== '' ? $new_key : $dedup_key,
		));
	}

	/**
	 * Inline edit for the Guest List dashboard's First Name / Email / Language
	 * columns (Contact number has its own endpoint, Update_Contact). Each field
	 * validates via the pure guest_field_validate_* helpers, then writes back —
	 * guest_list for Name/Email, booking + customer ChatLanguage for Language.
	 * Levels 20/50 are confined to their own bookings, mirroring the read side.
	 */
	function Update_Field()
	{
		$out = function ($data) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode($data));
		};

		$dedup_key = (string) $this->input->post('dedup_key');
		$field     = (string) $this->input->post('field');
		$value     = (string) $this->input->post('value');

		if ($dedup_key === '') {
			return $out(array('ok' => false, 'message' => 'Missing guest reference.'));
		}

		$scope_admin_id = in_array($this->session->userdata('level'), array(20, 50))
			? $this->session->userdata('admin_id')
			: null;
		$admin_id = $this->session->userdata('admin_id');

		if ($field === 'name') {
			$valid = guest_field_validate_name($value);
			if (!$valid['ok']) {
				return $out(array('ok' => false, 'message' => $valid['error']));
			}
			$affected = $this->Guests_Model->Update_Guest_Name($dedup_key, $valid['value'], $admin_id, $scope_admin_id);
			if ($affected < 1) {
				return $out(array('ok' => false, 'message' => 'Guest not found or you are not allowed to edit it.'));
			}
			return $out(array('ok' => true, 'value' => $valid['value']));
		}

		if ($field === 'email') {
			$valid = guest_field_validate_email($value);
			if (!$valid['ok']) {
				return $out(array('ok' => false, 'message' => $valid['error']));
			}
			$affected = $this->Guests_Model->Update_Guest_Email($dedup_key, $valid['value'], $admin_id, $scope_admin_id);
			if ($affected < 1) {
				return $out(array('ok' => false, 'message' => 'Guest not found or you are not allowed to edit it.'));
			}
			return $out(array('ok' => true, 'value' => $valid['value']));
		}

		if ($field === 'language') {
			$valid = guest_field_validate_language($value, $this->Guest_Languages());
			if (!$valid['ok']) {
				return $out(array('ok' => false, 'message' => $valid['error']));
			}
			$affected = $this->Guests_Model->Update_Guest_Language($dedup_key, $valid['value'], $admin_id, $scope_admin_id);
			if ($affected < 1) {
				// Language lives on the booking/customer, so it only lands on a
				// guest who leads a booking — a pure team member has nowhere to store it.
				return $out(array('ok' => false, 'message' => 'Language can only be set on the guest who leads a booking.'));
			}
			return $out(array('ok' => true, 'value' => $valid['value']));
		}

		return $out(array('ok' => false, 'message' => 'Unknown field.'));
	}

	/**
	 * Build the dedup_key => active-remark-count map for the rows on this page,
	 * so the listing can badge each Action menu with "Remarks (n)". GHL rows have
	 * no Action menu, so only the booking-guest rows are looked up.
	 */
	private function Remark_Counts_For_Guests($guests)
	{
		$keys = array();
		foreach ((array) $guests as $g) {
			$is_ghl = isset($g->Type) && $g->Type === 'GHL';
			if (!$is_ghl && !empty($g->dedup_key)) {
				$keys[] = $g->dedup_key;
			}
		}
		return $this->Guests_Model->Read_Remark_Counts($keys);
	}

	/**
	 * List a guest's remarks as JSON (for the Remarks modal on the listing).
	 * Keyed by dedup_key so it spans all of that person's bookings.
	 */
	function Remarks()
	{
		$dedup_key = (string) $this->input->get('dedup_key');
		if ($dedup_key === '') {
			$this->output->set_content_type('application/json')
				->set_output(json_encode(array('ok' => false, 'message' => 'Missing guest reference.')));
			return;
		}

		$admin_id = $this->session->userdata('admin_id');
		$rows     = $this->Guests_Model->Read_Guest_Remarks($dedup_key, $admin_id);

		$out = array();
		foreach ($rows as $r) {
			$out[] = array(
				'id'         => (int) $r->RemarkID,
				'remark_at'  => $r->RemarkAt,
				'remark'     => $r->Remark,
				'created_by' => $r->CreatedByName !== null ? $r->CreatedByName : '',
				'can_delete' => (bool) $r->CanDelete,
			);
		}

		$this->output->set_content_type('application/json')
			->set_output(json_encode(array('ok' => true, 'remarks' => $out)));
	}

	/**
	 * Add a dated remark to a guest (datetime + text). Both fields validate via
	 * the pure guest_remark_validate_* helpers before the model stores them.
	 */
	function Add_Remark()
	{
		$out = function ($data) {
			$this->output->set_content_type('application/json')->set_output(json_encode($data));
		};

		$dedup_key = (string) $this->input->post('dedup_key');
		if ($dedup_key === '') {
			return $out(array('ok' => false, 'message' => 'Missing guest reference.'));
		}

		$dt = guest_remark_validate_datetime((string) $this->input->post('remark_at'));
		if (!$dt['ok']) {
			return $out(array('ok' => false, 'message' => $dt['error']));
		}
		$rm = guest_remark_validate_remark((string) $this->input->post('remark'));
		if (!$rm['ok']) {
			return $out(array('ok' => false, 'message' => $rm['error']));
		}

		$admin_id = $this->session->userdata('admin_id');
		$id = $this->Guests_Model->Add_Guest_Remark($dedup_key, $dt['value'], $rm['value'], $admin_id);

		return $out(array(
			'ok'     => true,
			'remark' => array(
				'id'         => (int) $id,
				'remark_at'  => $dt['value'],
				'remark'     => $rm['value'],
				'created_by' => (string) $this->session->userdata('name'),
				'can_delete' => true,
			),
		));
	}

	/**
	 * Soft-delete a remark. The model confines this to the remark's author.
	 */
	function Delete_Remark()
	{
		$out = function ($data) {
			$this->output->set_content_type('application/json')->set_output(json_encode($data));
		};

		$remark_id = (int) $this->input->post('id');
		if ($remark_id < 1) {
			return $out(array('ok' => false, 'message' => 'Missing remark reference.'));
		}

		$affected = $this->Guests_Model->Delete_Guest_Remark($remark_id, $this->session->userdata('admin_id'));
		if ($affected < 1) {
			return $out(array('ok' => false, 'message' => 'You can only delete your own remark.'));
		}
		return $out(array('ok' => true));
	}

	/**
	 * Allowed ChatLanguage codes (the booking/customer ENUM set). Single source
	 * for both the inline-edit dropdown and the server-side validation gate.
	 * Private so it is not exposed as a routable action.
	 */
	private function Guest_Languages()
	{
		return array('CN', 'EN', 'ML');
	}

	function View()
	{
		$key = $this->input->get('key');
		if(empty($key)) { redirect('Guests'); return; }

		$detail = $this->Guests_Model->Read_Guest_Detail($key);
		if(empty($detail) || empty($detail['bookings'])) {
			redirect('Guests');
			return;
		}

		$titles = array('tab_title' => 'HolidayGoGoGo | Guest List >> View', 'breadcrumb_title' => 'Guest List >> View');
		$this->load->view('layout/header', $titles);
		$this->load->view('guests/view', $detail);
		$this->load->view('layout/footer');
	}
}
