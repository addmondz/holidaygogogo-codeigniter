<?php
class Faq_Model extends CI_Model
{
	// Admin list. Honours the Title (like) and Type (exact) filters posted
	// from the index page filter accordion.
	function Read_Faqs()
	{
		// Tags and destinations are aggregated in the same query (left joins
		// through their maps so FAQs without either are still returned). DISTINCT
		// guards against the row fan-out of the two independent one-to-many joins.
		// "||" separates names; the view splits on it to render one badge each.
		$this->db->select('f.FAQID, f.Title, f.Description, f.Type, f.DisplayOrder, f.Status, f.InsertDate, a.Name AS InsertByName, GROUP_CONCAT(DISTINCT ft.Name ORDER BY ft.Name ASC SEPARATOR "||") AS Tags, GROUP_CONCAT(DISTINCT c.Name ORDER BY c.Name ASC SEPARATOR "||") AS Destinations', false);
		$this->db->from('faq f');
		$this->db->join('admin a', 'a.AdminID = f.InsertBy', 'left');
		$this->db->join('faq_tag_map ftm', 'ftm.FAQID = f.FAQID', 'left');
		$this->db->join('faq_tag ft', "ft.FAQTagID = ftm.FAQTagID AND ft.Status = 'Y'", 'left', false);
		$this->db->join('faq_destination_map fdm', 'fdm.FAQID = f.FAQID', 'left');
		$this->db->join('category c', "c.CategoryID = fdm.CategoryID AND c.Status = 'Y'", 'left', false);
		$this->db->where('f.Status', 'Y');

		if(!empty($this->input->get('title'))) {
			$this->db->like('f.Title', $this->input->get('title'));
		}
		if(!empty($this->input->get('type')) && in_array($this->input->get('type'), array('internal', 'external'), true)) {
			$this->db->where('f.Type', $this->input->get('type'));
		}

		$this->db->group_by('f.FAQID');
		$this->db->order_by('f.DisplayOrder', 'ASC');
		$this->db->order_by('f.FAQID', 'ASC');
		return $this->db->get()->result();
	}

	function Read_Faq($id)
	{
		$this->db->select('FAQID, Title, Description, Type, DisplayOrder, Status');
		$this->db->where('FAQID', (int)$id);
		return $this->db->get('faq')->row();
	}

	// The Description column holds a JSON list of sub-question/sub-answer pairs:
	// [{"q":"...","a":"..."}, ...]. These two helpers are the single source of
	// truth for that encoding. Both are pure + static so they can be unit tested
	// without a DB.

	// Parse a stored Description into a list of array('q'=>..., 'a'=>...).
	// Legacy rows (plain text saved before this refactor) decode to a single
	// answer-only pair so old FAQs keep rendering.
	public static function Decode_Items($description)
	{
		$description = (string)$description;
		if(trim($description) === '') {
			return array();
		}

		$decoded = json_decode($description, true);

		// A single {"q":..,"a":..} object (not wrapped in a list).
		if(is_array($decoded) && (isset($decoded['q']) || isset($decoded['a']))) {
			$decoded = array($decoded);
		}

		if(is_array($decoded)) {
			$items = array();
			foreach($decoded as $row) {
				if(is_array($row) && (isset($row['q']) || isset($row['a']))) {
					$items[] = array(
						'q' => (string)(isset($row['q']) ? $row['q'] : ''),
						'a' => (string)(isset($row['a']) ? $row['a'] : ''),
					);
				}
			}
			return $items;
		}

		// Not JSON -> treat the whole string as one legacy answer.
		return array(array('q' => '', 'a' => $description));
	}

	// Zip the posted sub_questions[]/sub_answers[] arrays into a validated list.
	// Returns array('items' => array, 'error' => null|string). Fully-empty rows
	// are dropped; a half-filled row (one side blank) is an error because every
	// pair must carry both a sub-question and a sub-answer.
	public static function Build_Items($questions, $answers)
	{
		$questions = is_array($questions) ? array_values($questions) : array();
		$answers   = is_array($answers)   ? array_values($answers)   : array();
		$count     = max(count($questions), count($answers));

		$items = array();
		for($i = 0; $i < $count; $i++) {
			$q = trim((string)(isset($questions[$i]) ? $questions[$i] : ''));
			$a = trim((string)(isset($answers[$i]) ? $answers[$i] : ''));

			if($q === '' && $a === '') {
				continue; // blank row, ignore
			}
			if($q === '' || $a === '') {
				return array('items' => array(), 'error' => 'Each sub-question must have a matching sub-answer.');
			}
			$items[] = array('q' => $q, 'a' => $a);
		}

		return array('items' => $items, 'error' => null);
	}

	// Serialise a Build_Items() list back to the stored Description string.
	public static function Encode_Items($items)
	{
		if(empty($items)) {
			return '';
		}
		return json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	// Public read used by both /faq/internal and /faq/external. Only active
	// FAQs of the requested type, in display order. Tags and destinations are
	// aggregated in the same query (see Read_Faqs for the join/DISTINCT notes);
	// "||" separates names so the display view can split them into badges.
	function Read_Public($type)
	{
		$type = ($type === 'external') ? 'external' : 'internal';
		$this->db->select('f.FAQID, f.Title, f.Description, GROUP_CONCAT(DISTINCT ft.Name ORDER BY ft.Name ASC SEPARATOR "||") AS Tags, GROUP_CONCAT(DISTINCT c.Name ORDER BY c.Name ASC SEPARATOR "||") AS Destinations', false);
		$this->db->from('faq f');
		$this->db->join('faq_tag_map ftm', 'ftm.FAQID = f.FAQID', 'left');
		$this->db->join('faq_tag ft', "ft.FAQTagID = ftm.FAQTagID AND ft.Status = 'Y'", 'left', false);
		$this->db->join('faq_destination_map fdm', 'fdm.FAQID = f.FAQID', 'left');
		$this->db->join('category c', "c.CategoryID = fdm.CategoryID AND c.Status = 'Y'", 'left', false);
		$this->db->where('f.Status', 'Y');
		$this->db->where('f.Type', $type);
		$this->db->group_by('f.FAQID');
		$this->db->order_by('f.DisplayOrder', 'ASC');
		$this->db->order_by('f.FAQID', 'ASC');
		return $this->db->get()->result();
	}

	function Create($data)
	{
		$admin_id = $this->session->userdata('admin_id');
		$now      = date('Y-m-d H:i:s');

		$row = array(
			'Title'        => $data['Title'],
			'Description'  => $data['Description'],
			'Type'         => $data['Type'],
			'DisplayOrder' => (int)$data['DisplayOrder'],
			'Status'       => 'Y',
			'InsertBy'     => $admin_id,
			'InsertDate'   => $now,
			'UpdateBy'     => $admin_id,
			'UpdateDate'   => $now,
		);
		$this->db->insert('faq', $row);
		return (int)$this->db->insert_id();
	}

	function Update($id, $data)
	{
		$row = array(
			'Title'        => $data['Title'],
			'Description'  => $data['Description'],
			'Type'         => $data['Type'],
			'DisplayOrder' => (int)$data['DisplayOrder'],
			'UpdateBy'     => $this->session->userdata('admin_id'),
			'UpdateDate'   => date('Y-m-d H:i:s'),
		);
		$this->db->where('FAQID', (int)$id);
		return $this->db->update('faq', $row);
	}

	// Coerce a posted id[] payload (Tags[] / Destinations[]) into a clean,
	// de-duplicated list of positive ints. Kept static + side-effect free so it
	// can be unit tested without a DB. Non-array / empty / non-numeric input
	// yields an empty list.
	public static function Normalize_Ids($raw)
	{
		if(!is_array($raw)) {
			return array();
		}
		$ids = array();
		foreach($raw as $value) {
			$id = (int)$value;
			if($id > 0) {
				$ids[$id] = $id; // key dedupes
			}
		}
		return array_values($ids);
	}

	// IDs of the tags currently mapped to a FAQ (for pre-selecting the form).
	function Read_Tag_Ids($faq_id)
	{
		$this->db->select('FAQTagID');
		$this->db->where('FAQID', (int)$faq_id);
		$rows = $this->db->get('faq_tag_map')->result();

		$ids = array();
		foreach($rows as $row) {
			$ids[] = (int)$row->FAQTagID;
		}
		return $ids;
	}

	// Replace a FAQ's tag links with the given set (full-sync, idempotent).
	function Sync_Tags($faq_id, $tag_ids)
	{
		$faq_id = (int)$faq_id;
		$this->db->where('FAQID', $faq_id);
		$this->db->delete('faq_tag_map');

		$ids = self::Normalize_Ids($tag_ids);
		if(empty($ids)) {
			return;
		}

		$rows = array();
		foreach($ids as $id) {
			$rows[] = array('FAQID' => $faq_id, 'FAQTagID' => $id);
		}
		$this->db->insert_batch('faq_tag_map', $rows);
	}

	// Active destination categories for the FAQ form's multi-select. Same source
	// the booking form uses: category rows flagged IsDestination = 'YES'.
	function Read_Destinations()
	{
		$this->db->select('CategoryID, Name');
		$this->db->where('IsDestination', 'YES');
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('category')->result();
	}

	// CategoryIDs currently mapped to a FAQ (for pre-selecting the form).
	function Read_Destination_Ids($faq_id)
	{
		$this->db->select('CategoryID');
		$this->db->where('FAQID', (int)$faq_id);
		$rows = $this->db->get('faq_destination_map')->result();

		$ids = array();
		foreach($rows as $row) {
			$ids[] = (int)$row->CategoryID;
		}
		return $ids;
	}

	// Replace a FAQ's destination links with the given set (full-sync, idempotent).
	function Sync_Destinations($faq_id, $category_ids)
	{
		$faq_id = (int)$faq_id;
		$this->db->where('FAQID', $faq_id);
		$this->db->delete('faq_destination_map');

		$ids = self::Normalize_Ids($category_ids);
		if(empty($ids)) {
			return;
		}

		$rows = array();
		foreach($ids as $id) {
			$rows[] = array('FAQID' => $faq_id, 'CategoryID' => $id);
		}
		$this->db->insert_batch('faq_destination_map', $rows);
	}
}
