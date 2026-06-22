<?php
class Faq_Model extends CI_Model
{
	// Admin list. Honours the Title (like) and Type (exact) filters posted
	// from the index page filter accordion.
	function Read_Faqs()
	{
		// Destinations are still whole-FAQ, so they stay aggregated in the query
		// (left join through the map so FAQs without one are still returned).
		// DISTINCT guards the one-to-many fan-out; "||" separates names for the
		// view. Tags now live per sub-Q&A inside Description, so they're resolved
		// in PHP below (the SQL can't reach into the JSON list).
		$this->db->select('f.FAQID, f.Title, f.Slug, f.Description, f.Type, f.Status, f.InsertDate, a.Name AS InsertByName, GROUP_CONCAT(DISTINCT c.Name ORDER BY c.Name ASC SEPARATOR "||") AS Destinations', false);
		$this->db->from('faq f');
		$this->db->join('admin a', 'a.AdminID = f.InsertBy', 'left');
		$this->db->join('faq_destination_map fdm', 'fdm.FAQID = f.FAQID', 'left');
		$this->db->join('category c', "c.CategoryID = fdm.CategoryID AND c.Status = 'Y'", 'left', false);
		$this->db->where('f.Status', 'Y');

		if(!empty($this->input->get('type')) && in_array($this->input->get('type'), array('internal', 'external'), true)) {
			$this->db->where('f.Type', $this->input->get('type'));
		}
		// Destination filter: match a FAQ that carries ANY of the chosen ids.
		// Done with a subquery against the map (not a where_in on the joined row)
		// so the GROUP_CONCAT above still lists every destination, not only the
		// matched ones. IDs are int-sanitised, so inlining them is safe.
		$destination_ids = self::Parse_Id_Csv($this->input->get('destination'));
		if(!empty($destination_ids)) {
			$this->db->where('f.FAQID IN (SELECT FAQID FROM faq_destination_map WHERE CategoryID IN (' . implode(',', $destination_ids) . '))', null, false);
		}

		$this->db->group_by('f.FAQID');
		$this->db->order_by('f.FAQID', 'ASC');
		$rows = $this->db->get()->result();

		// Resolve per-item tags: a FAQ's effective tag set is the union across its
		// sub-Q&As. The tag filter keeps a FAQ when ANY sub-item carries a chosen
		// tag. $row->Tags is filled with the "||"-joined names so the view (which
		// already splits on "||") renders the badge column unchanged.
		$tag_ids   = self::Parse_Id_Csv($this->input->get('tag'));
		$tag_names = $this->Tag_Name_Map();
		$out = array();
		foreach($rows as $row) {
			$items = self::Decode_Items($row->Description);
			$faq_tag_ids = self::Item_Tag_Ids($items);
			if(!empty($tag_ids) && count(array_intersect($tag_ids, $faq_tag_ids)) === 0) {
				continue; // no chosen tag on any sub-item
			}
			$names = array();
			foreach($faq_tag_ids as $id) {
				if(isset($tag_names[$id])) {
					$names[] = $tag_names[$id];
				}
			}
			sort($names);
			$row->Tags = implode('||', $names);
			// How many sub-questions live under this title, for the listing's
			// "Questions" count column.
			$row->QuestionCount = count($items);
			// Flatten the sub-Q&A text into a hidden, searchable cell so the
			// listing's client-side DataTable search (which only sees rendered
			// cells) can match on sub-question / sub-answer content too.
			$row->SearchText = self::Search_Blob($items);
			$out[] = $row;
		}
		return $out;
	}

	function Read_Faq($id)
	{
		$this->db->select('FAQID, Title, Description, Type, Status');
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
					$item = array(
						'q' => (string)(isset($row['q']) ? $row['q'] : ''),
						'a' => (string)(isset($row['a']) ? $row['a'] : ''),
					);
					// Per-item tag ids (FAQTagID list). Normalised and only kept
					// when non-empty so untagged/legacy rows decode unchanged.
					if(isset($row['tags'])) {
						$tags = self::Normalize_Ids($row['tags']);
						if(!empty($tags)) {
							$item['tags'] = $tags;
						}
					}
					// Audit fields (created/updated by-name + date) are passed
					// through only when present, so legacy {q,a} rows decode
					// unchanged and consumers can isset()-guard the meta.
					foreach(array('cb', 'cd', 'ub', 'ud') as $k) {
						if(isset($row[$k]) && (string)$row[$k] !== '') {
							$item[$k] = (string)$row[$k];
						}
					}
					$items[] = $item;
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
	//
	// Audit mode: when $meta is provided (not null), each kept row is also
	// stamped with created-by/date (cb/cd) and updated-by/date (ub/ud). $meta
	// carries the prior values posted back as hidden fields - parallel arrays
	// keyed 'cb','cd','ub','ud' plus the original text 'oq','oa' for change
	// detection. A row with a blank stored cd is new (everything = actor/now);
	// an existing row keeps cb/cd and only bumps ub/ud when its text changed.
	// $actor is the acting admin's name and $now a 'Y-m-d H:i:s' timestamp;
	// both are passed in so the helper stays pure + unit-testable. Calling with
	// only ($questions, $answers) preserves the original bare {q,a} contract.
	//
	// $tags (optional) is a parallel array aligned to the posted rows - each
	// entry a list of FAQTagID ids. A kept row gets a normalised, non-empty
	// 'tags' key (placed right after 'a'); empty/absent tag sets omit the key.
	public static function Build_Items($questions, $answers, $meta = null, $actor = '', $now = '', $tags = null)
	{
		$questions = is_array($questions) ? array_values($questions) : array();
		$answers   = is_array($answers)   ? array_values($answers)   : array();
		$count     = max(count($questions), count($answers));

		$audited = ($meta !== null);
		$actor   = (string)$actor;
		$now     = (string)$now;
		$meta_at = function($key, $i) use ($meta) {
			if(!is_array($meta) || !isset($meta[$key]) || !is_array($meta[$key]) || !array_key_exists($i, $meta[$key])) {
				return '';
			}
			return trim((string)$meta[$key][$i]);
		};

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

			// Base row, plus this row's tags (kept only when non-empty so the
			// no-$tags call path returns the original bare {q,a} shape).
			$item = array('q' => $q, 'a' => $a);
			if($tags !== null) {
				$row_tags = self::Normalize_Ids(isset($tags[$i]) ? $tags[$i] : array());
				if(!empty($row_tags)) {
					$item['tags'] = $row_tags;
				}
			}

			if(!$audited) {
				$items[] = $item;
				continue;
			}

			$cd = $meta_at('cd', $i);
			if($cd === '') {
				// New row: created + first update are the same event.
				$item['cb'] = $actor; $item['cd'] = $now; $item['ub'] = $actor; $item['ud'] = $now;
				$items[] = $item;
				continue;
			}

			// Existing row: preserve creation; bump "updated" only on edit.
			$cb = $meta_at('cb', $i);
			if($q !== $meta_at('oq', $i) || $a !== $meta_at('oa', $i)) {
				$ub = $actor;
				$ud = $now;
			} else {
				$ub = $meta_at('ub', $i);
				$ud = $meta_at('ud', $i);
				if($ub === '') { $ub = $cb; }
				if($ud === '') { $ud = $cd; }
			}
			$item['cb'] = $cb; $item['cd'] = $cd; $item['ub'] = $ub; $item['ud'] = $ud;
			$items[] = $item;
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

	// Deduped, sorted union of every item's tag ids (a FAQ's effective tag set,
	// since tags now live per sub-Q&A). Pure + static for unit testing; used for
	// the listing badge column and the "match any sub-item" tag filter.
	public static function Item_Tag_Ids($items)
	{
		$ids = array();
		if(is_array($items)) {
			foreach($items as $item) {
				if(is_array($item) && isset($item['tags']) && is_array($item['tags'])) {
					foreach($item['tags'] as $id) {
						$id = (int)$id;
						if($id > 0) {
							$ids[$id] = $id; // key dedupes
						}
					}
				}
			}
		}
		$ids = array_values($ids);
		sort($ids);
		return $ids;
	}

	// Flatten a decoded item list into one space-joined string of every
	// sub-question + sub-answer (q before a, in item order). Blank pieces are
	// dropped and inner whitespace is collapsed so the result is clean search
	// text. Per-item tag ids and audit metadata are excluded - only q/a text.
	// Pure + static for unit testing; the listing emits this as a hidden cell so
	// the client-side DataTable search reaches sub-Q&A content.
	public static function Search_Blob($items)
	{
		if(!is_array($items)) {
			return '';
		}
		$parts = array();
		foreach($items as $item) {
			if(!is_array($item)) {
				continue;
			}
			foreach(array('q', 'a') as $k) {
				if(isset($item[$k]) && trim((string)$item[$k]) !== '') {
					$parts[] = trim((string)$item[$k]);
				}
			}
		}
		// Collapse any inner runs of whitespace (newlines, tabs) to single spaces.
		return preg_replace('/\s+/', ' ', implode(' ', $parts));
	}

	// Flatten a list of FAQ rows (each carrying Title, the Description JSON of
	// sub-Q&As, and a "||"-joined Destinations string) into a flat list of
	// export rows: one row per sub-Q&A, plus a single title-only row for a FAQ
	// that has no sub-Q&As yet. $tag_names maps FAQTagID => Name so the per-item
	// tag ids resolve to display names (unknown ids are dropped). Pure + static
	// so it can be unit tested without a DB; both the Excel and PDF "download all
	// FAQs" exports build on it, so the two formats never drift.
	public static function Export_Rows($faqs, $tag_names = array())
	{
		if(!is_array($faqs)) {
			return array();
		}
		$tag_names = is_array($tag_names) ? $tag_names : array();

		$rows = array();
		foreach($faqs as $faq) {
			$title    = isset($faq->Title) ? (string)$faq->Title : '';
			$dest_raw = isset($faq->Destinations) ? (string)$faq->Destinations : '';
			$destinations = ($dest_raw === '') ? '' : implode(', ', explode('||', $dest_raw));
			$items = self::Decode_Items(isset($faq->Description) ? $faq->Description : '');

			if(empty($items)) {
				// A FAQ with no sub-Q&As still appears once, by title, so the
				// export is a faithful 1:1 of the library.
				$rows[] = array(
					'title' => $title, 'destinations' => $destinations,
					'question' => '', 'answer' => '', 'tags' => '',
					'created' => '', 'updated' => '',
				);
				continue;
			}

			foreach($items as $item) {
				$names = array();
				if(isset($item['tags']) && is_array($item['tags'])) {
					foreach($item['tags'] as $tid) {
						$tid = (int)$tid;
						if(isset($tag_names[$tid])) {
							$names[] = $tag_names[$tid];
						}
					}
				}
				$rows[] = array(
					'title'        => $title,
					'destinations' => $destinations,
					'question'     => isset($item['q']) ? (string)$item['q'] : '',
					'answer'       => isset($item['a']) ? (string)$item['a'] : '',
					'tags'         => implode(', ', $names),
					'created'      => isset($item['cd']) ? (string)$item['cd'] : '',
					'updated'      => isset($item['ud']) ? (string)$item['ud'] : '',
				);
			}
		}
		return $rows;
	}

	function Create($data)
	{
		$admin_id = $this->session->userdata('admin_id');
		$now      = date('Y-m-d H:i:s');

		$row = array(
			'Title'        => $data['Title'],
			'Slug'         => $data['Slug'],
			'Description'  => $data['Description'],
			'Type'         => $data['Type'],
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
			'Slug'         => $data['Slug'],
			'Description'  => $data['Description'],
			'Type'         => $data['Type'],
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

	// Turn a free-text Title into a URL-safe slug for the per-FAQ page
	// (/faq/<slug>). Lowercase; every run of non-[a-z0-9] collapses to a single
	// hyphen; leading/trailing hyphens trimmed; capped at 80 chars (trimmed back
	// to a hyphen boundary so it never ends mid-word or on a hyphen). A title
	// that reduces to nothing (blank / symbols / non-latin) falls back to 'faq'
	// so the Slug column is never empty. Pure + static for unit testing.
	public static function Slugify($title)
	{
		$slug = strtolower((string)$title);
		$slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
		$slug = trim($slug, '-');
		if(strlen($slug) > 80) {
			$slug = substr($slug, 0, 80);
			// Don't leave a dangling partial word fragment after a hyphen.
			$cut = strrpos($slug, '-');
			if($cut !== false) {
				$slug = substr($slug, 0, $cut);
			}
			$slug = trim($slug, '-');
		}
		return ($slug === '') ? 'faq' : $slug;
	}

	// Disambiguate a base slug against the slugs already in use. Returns $base
	// when free, otherwise the lowest 'base-N' (N>=2) not present in $taken
	// (gaps are filled, so base + base-3 taken yields base-2). The DB query that
	// collects $taken lives in Generate_Slug(); this stays pure + unit-testable.
	public static function Unique_Slug($base, $taken)
	{
		$taken = is_array($taken) ? $taken : array();
		if(!in_array($base, $taken, true)) {
			return $base;
		}
		$n = 2;
		while(in_array($base . '-' . $n, $taken, true)) {
			$n++;
		}
		return $base . '-' . $n;
	}

	// Build a unique slug for a FAQ from its Title. Slugifies, then disambiguates
	// against existing slugs (only the base and its 'base-%' family are fetched,
	// excluding the row being updated) via the pure Unique_Slug() helper.
	function Generate_Slug($title, $ignore_id = 0)
	{
		$base = self::Slugify($title);
		$this->db->select('Slug');
		$this->db->where('Slug IS NOT NULL', null, false);
		if((int)$ignore_id > 0) {
			$this->db->where('FAQID !=', (int)$ignore_id);
		}
		$this->db->group_start();
		$this->db->where('Slug', $base);
		$this->db->or_like('Slug', $base . '-', 'after');
		$this->db->group_end();
		$rows = $this->db->get('faq')->result();

		$taken = array();
		foreach($rows as $row) {
			$taken[] = $row->Slug;
		}
		return self::Unique_Slug($base, $taken);
	}

	// Single active FAQ by its slug, for the per-FAQ page (/faq/<slug>). Carries
	// the whole-FAQ destinations (same "||"-joined aggregation the public read
	// uses) and UpdateDate so the page can show when it was last touched. Returns
	// null for a blank slug or a miss (caller 404s).
	function Read_By_Slug($slug)
	{
		$slug = trim((string)$slug);
		if($slug === '') {
			return null;
		}
		$this->db->select('f.FAQID, f.Title, f.Slug, f.Description, f.Type, f.UpdateDate, GROUP_CONCAT(DISTINCT c.Name ORDER BY c.Name ASC SEPARATOR "||") AS Destinations', false);
		$this->db->from('faq f');
		$this->db->join('faq_destination_map fdm', 'fdm.FAQID = f.FAQID', 'left');
		$this->db->join('category c', "c.CategoryID = fdm.CategoryID AND c.Status = 'Y'", 'left', false);
		$this->db->where('f.Slug', $slug);
		$this->db->where('f.Status', 'Y');
		$this->db->group_by('f.FAQID');
		return $this->db->get()->row();
	}

	// One-time, idempotent backfill: stamp a unique slug onto any active FAQ that
	// doesn't have one yet (rows created before the Slug column existed). Cheap on
	// the happy path - a single SELECT that returns nothing once every row has a
	// slug. Called from the FAQ listing so it self-heals the moment slugs matter.
	function Backfill_Slugs()
	{
		$this->db->select('FAQID, Title');
		$this->db->group_start();
		$this->db->where('Slug IS NULL', null, false);
		$this->db->or_where('Slug', '');
		$this->db->group_end();
		$rows = $this->db->get('faq')->result();

		foreach($rows as $row) {
			$slug = $this->Generate_Slug($row->Title, $row->FAQID);
			$this->db->where('FAQID', (int)$row->FAQID);
			$this->db->update('faq', array('Slug' => $slug));
		}
	}

	// Turn a comma-separated id string (the tag/destination filter values posted
	// by the listing page, e.g. "3,7,9") into a clean list of positive ints.
	// Pure so it can be unit tested without a DB; reuses Normalize_Ids' rules.
	public static function Parse_Id_Csv($raw)
	{
		if(!is_string($raw) || trim($raw) === '') {
			return array();
		}
		return self::Normalize_Ids(explode(',', $raw));
	}

	// Map of active FAQTagID => Name. Resolves the per-item tag ids stored in
	// Description into display names for the listing column and the public page.
	function Tag_Name_Map()
	{
		$this->db->select('FAQTagID, Name');
		$this->db->where('Status', 'Y');
		$rows = $this->db->get('faq_tag')->result();

		$map = array();
		foreach($rows as $row) {
			$map[(int)$row->FAQTagID] = $row->Name;
		}
		return $map;
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
