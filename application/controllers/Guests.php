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
		$this->load->helper('chat_history');
	}

	function index()
	{
		if(lc_block_view('guests')) { return; }
		$titles = array('tab_title' => 'HolidayGoGoGo | Guest List', 'breadcrumb_title' => 'Guest List');
		$page   = max(1, (int) $this->input->get('page'));
		$limit  = 30;
		$offset = ($page - 1) * $limit;

		$this->Guests_Model->Set_Mode('guest');
		$data['guests']         = $this->Guests_Model->Read_Guests($limit, $offset);
		$this->load->model('Ghl_Messages_Model');
		$data['msg_log_phones'] = $this->Ghl_Messages_Model->Phones_With_Messages_For_Guests($data['guests']);
		$data['msg_log_contacts'] = $this->Ghl_Messages_Model->Contacts_With_Messages_For_Guests($data['guests']);
		$data['remark_counts']  = $this->Remark_Counts_For_Guests($data['guests']);
		$data['chat_counts']    = $this->Chat_Counts_For_Guests($data['guests']);
		$data['campaign_counts'] = $this->Campaign_Counts_For_Guests($data['guests']);
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
		$data['lc_can_edit']    = lc_can_edit('guests');
		$this->load->view('layout/header', $titles);
		$this->load->view('guests/index', $data);
		$this->load->view('layout/footer');
	}

	/**
	 * Export the filtered Guest List to Excel (all pages at once). Same filters
	 * and merge as the listing, streamed as an .xlsx.
	 */
	function Download()
	{
		if(lc_block_view('guests')) { return; }
		$this->load->helper('guest_list_export');
		$this->Guests_Model->Set_Mode('guest');
		$rows = $this->Guests_Model->Read_Guests_For_Export();
		guest_list_export_stream($rows, 'guest', 'GUEST_LIST_' . date('Ymd') . '.xlsx');
	}

	function Count()
	{
		if(lc_block_view('guests')) { return; }
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
		if(lc_block_edit(lc_request_module('guests'))) { return; }
		$out = function ($data) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode($data));
		};

		$dedup_key   = (string) $this->input->post('dedup_key');
		$customer_id = (int) $this->input->post('customer_id');
		$mobile      = trim((string) $this->input->post('mobile'));

		// A guest row anchors on its dedup_key; a Customer List row can also anchor
		// on its CustomerID, so a name-only customer (no guest_list rows, leads no
		// booking) can still have a number set. At least one anchor is required.
		if ($dedup_key === '' && $customer_id < 1) {
			return $out(array('ok' => false, 'message' => 'Missing guest reference.'));
		}

		$valid = guest_contact_validate_mobile($mobile);
		if (!$valid['ok']) {
			return $out(array('ok' => false, 'message' => $valid['error']));
		}

		// Block when the new number already belongs to a different person. With no
		// old key (a customer getting their first number) this still blocks a number
		// already used by anyone else — current_dedup_key '' excludes no one.
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

		// GHL-first: push the new number to the matching GHL contact, resolved by
		// the OLD key (ghl_contacts still holds the pre-edit number). The local
		// save only proceeds when GHL succeeds — the sole exception is a guest
		// who isn't a GHL contact (nothing to sync), so local can't get ahead of GHL.
		// With no old key there is no GHL contact to resolve, so the push is skipped.
		$ghl = array('action' => 'skipped', 'reason' => 'no_contact');
		if ($dedup_key !== '') {
			$ghl = $this->Push_Guest_Edit_To_Ghl($dedup_key, array('ContactNum' => $mobile));
			if ($this->Ghl_Sync_Blocks_Save($ghl)) {
				return $out(array('ok' => false, 'message' => 'Could not sync to GHL — contact number not saved. Please try again.'));
			}
		}

		// Write the guest-side records (guest_list + led bookings) when we have a
		// dedup_key, and the customer's own phone_number when a Customer List row
		// supplied its CustomerID. Either write counts toward "affected".
		$affected = 0;
		if ($dedup_key !== '') {
			$affected += $this->Guests_Model->Update_Guest_Contact(
				$dedup_key,
				$mobile,
				$this->session->userdata('admin_id'),
				$scope_admin_id
			);
		}
		if ($customer_id > 0) {
			$affected += $this->Guests_Model->Update_Customer_Contact($customer_id, $mobile);
		}

		if ($affected < 1) {
			return $out(array('ok' => false, 'message' => 'Guest not found or you are not allowed to edit it.'));
		}

		return $out(array(
			'ok'        => true,
			'mobile'    => $mobile,
			'dedup_key' => $new_key !== '' ? $new_key : $dedup_key,
			'ghl_sync'  => $ghl['action'],
		));
	}

	/**
	 * GHL-first push of an inline edit to the matching GHL contact, called
	 * BEFORE the local write so the caller can abort and keep local in step
	 * with GHL. $row carries only the changed field(s) in the keys the sync
	 * service expects (ContactNum / GuestName / Email); $lookup_key is the
	 * guest's pre-edit dedup_key, which still matches ghl_contacts.phone.
	 * Returns array(action, reason, ...) — pass it to Ghl_Sync_Blocks_Save().
	 * Any thrown error is normalized to 'failed' so a caught exception blocks
	 * the save.
	 */
	private function Push_Guest_Edit_To_Ghl($lookup_key, $row)
	{
		try {
			$this->load->library('GhlCampaignSyncService');
			return $this->ghlcampaignsyncservice->push_guest_edit(
				array('phone_key' => (string) $lookup_key),
				$row
			);
		} catch (Exception $e) {
			return array('action' => 'failed', 'reason' => 'error', 'contact_id' => null, 'http_status' => 0, 'message' => $e->getMessage());
		}
	}

	/**
	 * Whether a GHL push result must block the local save. The local DB may
	 * only move once GHL has (per user directive 2026-07-23): the save proceeds
	 * only on a real 'updated', or when there was genuinely nothing to sync —
	 * the guest isn't a GHL contact ('no_contact') or the change maps to no GHL
	 * field ('nothing_to_sync', e.g. clearing an email, which we never push as a
	 * blank). Everything else — a push 'failed' or GHL not configured — blocks,
	 * so a value can't sit locally that GHL never accepted.
	 */
	private function Ghl_Sync_Blocks_Save($ghl)
	{
		if ($ghl['action'] === 'updated') {
			return false;
		}
		if ($ghl['action'] === 'skipped'
			&& isset($ghl['reason'])
			&& in_array($ghl['reason'], array('no_contact', 'nothing_to_sync'), true)) {
			return false;
		}
		return true;
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
		if(lc_block_edit(lc_request_module('guests'))) { return; }
		$out = function ($data) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode($data));
		};

		$dedup_key = (string) $this->input->post('dedup_key');
		$field     = (string) $this->input->post('field');
		$value     = (string) $this->input->post('value');

		// Alt Name is a customer-level attribute (customer.AltName), keyed by the
		// row's CustomerID rather than the guest dedup_key, so it is handled before
		// the guest-reference guard below. Kept in sync with the BC / Customer form.
		if ($field === 'altname') {
			$customer_id = (int) $this->input->post('customer_id');
			if ($customer_id < 1) {
				return $out(array('ok' => false, 'message' => 'Missing customer reference.'));
			}
			$valid = guest_field_validate_altname($value);
			if (!$valid['ok']) {
				return $out(array('ok' => false, 'message' => $valid['error']));
			}
			$affected = $this->Guests_Model->Update_Customer_AltName($customer_id, $valid['value']);
			if ($affected < 1) {
				return $out(array('ok' => false, 'message' => 'Customer not found.'));
			}
			return $out(array('ok' => true, 'value' => $valid['value']));
		}

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
			$ghl = $this->Push_Guest_Edit_To_Ghl($dedup_key, array('GuestName' => $valid['value']));
			if ($this->Ghl_Sync_Blocks_Save($ghl)) {
				return $out(array('ok' => false, 'message' => 'Could not sync to GHL — name not saved. Please try again.'));
			}
			$affected = $this->Guests_Model->Update_Guest_Name($dedup_key, $valid['value'], $admin_id, $scope_admin_id);
			if ($affected < 1) {
				return $out(array('ok' => false, 'message' => 'Guest not found or you are not allowed to edit it.'));
			}
			return $out(array('ok' => true, 'value' => $valid['value'], 'ghl_sync' => $ghl['action']));
		}

		if ($field === 'email') {
			$valid = guest_field_validate_email($value);
			if (!$valid['ok']) {
				return $out(array('ok' => false, 'message' => $valid['error']));
			}
			$ghl = $this->Push_Guest_Edit_To_Ghl($dedup_key, array('Email' => $valid['value']));
			if ($this->Ghl_Sync_Blocks_Save($ghl)) {
				return $out(array('ok' => false, 'message' => 'Could not sync to GHL — email not saved. Please try again.'));
			}
			$affected = $this->Guests_Model->Update_Guest_Email($dedup_key, $valid['value'], $admin_id, $scope_admin_id);
			if ($affected < 1) {
				return $out(array('ok' => false, 'message' => 'Guest not found or you are not allowed to edit it.'));
			}
			return $out(array('ok' => true, 'value' => $valid['value'], 'ghl_sync' => $ghl['action']));
		}

		if ($field === 'language') {
			$valid = guest_field_validate_language($value, $this->Guest_Languages());
			if (!$valid['ok']) {
				return $out(array('ok' => false, 'message' => $valid['error']));
			}
			$affected = $this->Guests_Model->Update_Guest_Language($dedup_key, $valid['value'], $admin_id, $scope_admin_id);
			if ($affected < 1) {
				return $out(array('ok' => false, 'message' => 'Guest not found or you are not allowed to edit it.'));
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
		if(lc_block_view(lc_request_module('guests'))) { return; }
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
				'id'               => (int) $r->RemarkID,
				'campaign_date'    => $r->CampaignDate,
				'destination_id'   => $r->DestinationID !== null ? (int) $r->DestinationID : 0,
				'destination_name' => $r->DestinationName !== null ? $r->DestinationName : '',
				'follow_date'      => $r->FollowDate !== null ? $r->FollowDate : '',
				'remark'           => $r->Remark,
				'created_by'       => $r->CreatedByName !== null ? $r->CreatedByName : '',
				'can_delete'       => (bool) $r->CanDelete,
			);
		}

		$this->output->set_content_type('application/json')
			->set_output(json_encode(array('ok' => true, 'remarks' => $out)));
	}

	/**
	 * Add a campaign remark to a guest (campaign date + destination + follow date
	 * + text). Every field validates via the pure guest_remark_validate_* helpers
	 * before the model stores it. Campaign date and remark are required;
	 * destination and follow date are optional.
	 */
	function Add_Remark()
	{
		if(lc_block_edit(lc_request_module('guests'))) { return; }
		$out = function ($data) {
			$this->output->set_content_type('application/json')->set_output(json_encode($data));
		};

		$dedup_key = (string) $this->input->post('dedup_key');
		if ($dedup_key === '') {
			return $out(array('ok' => false, 'message' => 'Missing guest reference.'));
		}

		$cd = guest_remark_validate_date((string) $this->input->post('campaign_date'), true, 'Campaign date');
		if (!$cd['ok']) {
			return $out(array('ok' => false, 'message' => $cd['error']));
		}
		$de = guest_remark_validate_destination((string) $this->input->post('destination_id'));
		if (!$de['ok']) {
			return $out(array('ok' => false, 'message' => $de['error']));
		}
		$rm = guest_remark_validate_remark((string) $this->input->post('remark'));
		if (!$rm['ok']) {
			return $out(array('ok' => false, 'message' => $rm['error']));
		}
		$fd = guest_remark_validate_date((string) $this->input->post('follow_date'), false, 'Follow date');
		if (!$fd['ok']) {
			return $out(array('ok' => false, 'message' => $fd['error']));
		}

		$admin_id = $this->session->userdata('admin_id');
		$id = $this->Guests_Model->Add_Guest_Remark(
			$dedup_key, $cd['value'], $de['value'], $rm['value'], $fd['value'], $admin_id
		);

		// The modal reloads the list after a successful add (grLoad), which
		// re-reads the destination name via the category join — so the response
		// only needs to confirm the insert, not resolve the name here.
		return $out(array(
			'ok'     => true,
			'remark' => array('id' => (int) $id),
		));
	}

	/**
	 * Soft-delete a remark. The model confines this to the remark's author.
	 */
	function Delete_Remark()
	{
		if(lc_block_edit(lc_request_module('guests'))) { return; }
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
	 * dedup_key => active chat-file count for the rows on this page, so the shared
	 * view can badge each Action menu with "Chat History (n)". Every row (incl.
	 * GHL leads) carries a dedup_key, so all of them get a count here.
	 */
	private function Chat_Counts_For_Guests($guests)
	{
		$keys = array();
		foreach ((array) $guests as $g) {
			if (!empty($g->dedup_key)) {
				$keys[] = $g->dedup_key;
			}
		}
		return $this->Guests_Model->Read_Chat_History_Counts($keys);
	}

	/**
	 * dedup_key => count of campaigns the person joined, for the rows on this
	 * page — badges the Action ▸ Campaigns item without a per-row query.
	 */
	private function Campaign_Counts_For_Guests($guests)
	{
		$keys = array();
		foreach ((array) $guests as $g) {
			if (!empty($g->dedup_key)) {
				$keys[] = $g->dedup_key;
			}
		}
		return $this->Guests_Model->Read_Campaign_Counts($keys);
	}

	/**
	 * ----- Chat history (uploaded WhatsApp .txt exports) ----------------------
	 * Same JSON contract + dedup_key keying as the remarks above. These live on
	 * the Guests controller and the shared view calls them by absolute path
	 * (base_url('Guests/...')) from every page (Guest List / Customer / GHL).
	 */
	function Chat_History()
	{
		if(lc_block_view(lc_request_module('guests'))) { return; }
		$dedup_key = (string) $this->input->get('dedup_key');
		if ($dedup_key === '') {
			$this->output->set_content_type('application/json')
				->set_output(json_encode(array('ok' => false, 'message' => 'Missing guest reference.')));
			return;
		}

		$admin_id = $this->session->userdata('admin_id');
		$rows     = $this->Guests_Model->Read_Chat_History($dedup_key, $admin_id);

		$out = array();
		foreach ($rows as $r) {
			$out[] = array(
				'id'            => (int) $r->FileID,
				'title'         => ($r->Title !== null && $r->Title !== '') ? $r->Title : $r->OriginalName,
				'original_name' => $r->OriginalName,
				'created_by'    => $r->CreatedByName !== null ? $r->CreatedByName : '',
				'created_at'    => $r->CreatedAt,
				'can_delete'    => (bool) $r->CanDelete,
			);
		}

		$this->output->set_content_type('application/json')
			->set_output(json_encode(array('ok' => true, 'files' => $out)));
	}

	/**
	 * ----- Campaigns (Action ▸ Campaigns) -------------------------------------
	 * The active campaigns this person has joined (on the roster) — campaigns
	 * they never joined are excluded. Same JSON contract + dedup_key keying as
	 * remarks/chat history; called by absolute path from the shared listing view.
	 */
	function Campaigns()
	{
		if(lc_block_view(lc_request_module('guests'))) { return; }
		$dedup_key = (string) $this->input->get('dedup_key');
		if ($dedup_key === '') {
			$this->output->set_content_type('application/json')
				->set_output(json_encode(array('ok' => false, 'message' => 'Missing guest reference.')));
			return;
		}

		$rows = $this->Guests_Model->Read_Member_Campaigns($dedup_key);

		$out = array();
		foreach ($rows as $r) {
			$out[] = array(
				'id'            => (int) $r->CampaignID,
				'name'          => $r->Name,
				'campaign_date' => $r->CampaignDate !== null ? $r->CampaignDate : '',
			);
		}

		$this->output->set_content_type('application/json')
			->set_output(json_encode(array('ok' => true, 'campaigns' => $out)));
	}

	/**
	 * Opt a person in/out of a SINGLE campaign's audience picker (Action ▸
	 * Campaigns per-campaign toggle). Editing permission required; keyed by
	 * (campaign, dedup_key) so it follows the person across every source they
	 * surface in for that campaign. Echoes back the new state.
	 */
	function Set_Campaign_Visibility()
	{
		if(lc_block_edit(lc_request_module('guests'))) { return; }
		$out = function ($data) {
			$this->output->set_content_type('application/json')->set_output(json_encode($data));
		};

		$campaign_id = (int) $this->input->post('campaign_id');
		$dedup_key   = (string) $this->input->post('dedup_key');
		if ($campaign_id <= 0 || $dedup_key === '') {
			return $out(array('ok' => false, 'message' => 'Missing campaign or guest reference.'));
		}

		$hidden = (int) $this->input->post('hidden') === 1;
		$state  = $this->Guests_Model->Set_Campaign_Hidden($campaign_id, $dedup_key, $hidden, $this->session->userdata('admin_id'));
		return $out(array('ok' => true, 'hidden' => (bool) $state));
	}

	/**
	 * Store an uploaded chat .txt (validated by the pure helper) under
	 * assets/upload/chat_history/ and record it against the guest's dedup_key.
	 */
	function Upload_Chat_History()
	{
		if(lc_block_edit(lc_request_module('guests'))) { return; }
		$out = function ($data) {
			$this->output->set_content_type('application/json')->set_output(json_encode($data));
		};

		$dedup_key = (string) $this->input->post('dedup_key');
		if ($dedup_key === '') {
			return $out(array('ok' => false, 'message' => 'Missing guest reference.'));
		}
		if (!isset($_FILES['chat_file']) || $_FILES['chat_file']['error'] !== UPLOAD_ERR_OK) {
			return $out(array('ok' => false, 'message' => 'No file was uploaded.'));
		}

		$original = (string) $_FILES['chat_file']['name'];
		$size     = (int) $_FILES['chat_file']['size'];
		$v        = chat_history_validate_upload($original, $size);
		if (!$v['ok']) {
			return $out(array('ok' => false, 'message' => $v['error']));
		}

		$dir = FCPATH . 'assets/upload/chat_history/';
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}
		$stored = chat_history_stored_name($original);
		if (!move_uploaded_file($_FILES['chat_file']['tmp_name'], $dir . $stored)) {
			return $out(array('ok' => false, 'message' => 'Could not save the file. Please try again.'));
		}

		$title    = trim((string) $this->input->post('title'));
		$admin_id = $this->session->userdata('admin_id');
		$id = $this->Guests_Model->Add_Chat_History($dedup_key, $original, $stored, $title, $admin_id);

		return $out(array('ok' => true, 'file' => array('id' => (int) $id)));
	}

	/**
	 * Return one chat file parsed into messages for the in-app bubble viewer.
	 * View permission gated (same as the list/download endpoints).
	 */
	function View_Chat_History()
	{
		if(lc_block_view(lc_request_module('guests'))) { return; }
		$id  = (int) $this->input->get('id');
		$row = $this->Guests_Model->Get_Chat_History_File($id);
		if (!$row) {
			$this->output->set_content_type('application/json')
				->set_output(json_encode(array('ok' => false, 'message' => 'File not found.')));
			return;
		}

		$path = FCPATH . 'assets/upload/chat_history/' . basename($row->StoredName);
		$text = is_file($path) ? file_get_contents($path) : '';

		$this->output->set_content_type('application/json')->set_output(json_encode(array(
			'ok'       => true,
			'title'    => ($row->Title !== null && $row->Title !== '') ? $row->Title : $row->OriginalName,
			'messages' => chat_history_parse($text),
		)));
	}

	/**
	 * Stream the raw .txt back to the browser under its original filename.
	 */
	function Download_Chat_History()
	{
		if(lc_block_view(lc_request_module('guests'))) { return; }
		$id  = (int) $this->input->get('id');
		$row = $this->Guests_Model->Get_Chat_History_File($id);
		if (!$row) {
			show_404();
			return;
		}
		$path = FCPATH . 'assets/upload/chat_history/' . basename($row->StoredName);
		if (!is_file($path)) {
			show_404();
			return;
		}

		$name = str_replace(array('"', "\r", "\n"), '', $row->OriginalName);
		header('Content-Type: text/plain; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $name . '"');
		header('Content-Length: ' . filesize($path));
		readfile($path);
		exit;
	}

	/**
	 * Soft-delete a chat file. The model confines this to the uploader.
	 */
	function Delete_Chat_History()
	{
		if(lc_block_edit(lc_request_module('guests'))) { return; }
		$out = function ($data) {
			$this->output->set_content_type('application/json')->set_output(json_encode($data));
		};

		$file_id = (int) $this->input->post('id');
		if ($file_id < 1) {
			return $out(array('ok' => false, 'message' => 'Missing file reference.'));
		}

		$affected = $this->Guests_Model->Delete_Chat_History($file_id, $this->session->userdata('admin_id'));
		if ($affected < 1) {
			return $out(array('ok' => false, 'message' => 'You can only delete your own upload.'));
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
		// Customer Profile is reachable from any of the four lists, so allow anyone
		// who can view at least one of them (owner always).
		if( ! lc_any_view()) { redirect(base_url('Booking')); return; }
		$key = $this->input->get('key');
		if(empty($key)) { redirect('Guests'); return; }

		$detail = $this->Guests_Model->Read_Guest_Detail($key);
		if(empty($detail) || empty($detail['bookings'])) {
			redirect('Guests');
			return;
		}

		$titles = array('tab_title' => 'HolidayGoGoGo | Customer Profile', 'breadcrumb_title' => 'Guest List >> Customer Profile');
		$this->load->view('layout/header', $titles);
		$this->load->view('guests/view', $detail);
		$this->load->view('layout/footer');
	}
}
