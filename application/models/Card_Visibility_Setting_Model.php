<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Card Visibility Settings — the owner's per-(user x card) hidden list (table
 * `card_visibility_hidden_cards`). Cards are VISIBLE BY DEFAULT; presence of a
 * row => that user does NOT see that card.
 *
 * Reads here drive the owner settings page; writes happen only from the
 * owner-only Card_Visibility_Setting controller. The runtime hiding (CSS + AJAX
 * stripping) is read separately via card_visibility_helper.
 */
class Card_Visibility_Setting_Model extends CI_Model
{
	/**
	 * Every non-owner active admin — the population the owner toggles on the
	 * settings page (owners are never card-gated, so they are excluded).
	 *
	 * @return array list of {AdminID, Name, Level}
	 */
	function Configurable_Users()
	{
		$this->db->select('AdminID, Name, Level');
		$this->db->where('Level !=', '10');
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('admin')->result();
	}

	/**
	 * Currently hidden pairs as a lookup map "slug|adminid" => true, so the
	 * settings view can pre-set each switch.
	 *
	 * @return array
	 */
	function Hidden_Pair_Keys()
	{
		$this->load->helper('card_visibility');
		$out = array();
		foreach ($this->db->select('AdminID, CardSlug')->get('card_visibility_hidden_cards')->result() as $r) {
			$out[card_visibility_pair_key($r->CardSlug, $r->AdminID)] = true;
		}
		return $out;
	}

	/**
	 * Replace the whole hidden list with $pairs (full-sync). Each pair is
	 * ['admin' => int, 'slug' => string]; invalid entries are dropped and the set
	 * is de-duplicated on (AdminID, CardSlug).
	 *
	 * @param array $pairs list of ['admin' => int, 'slug' => string]
	 * @param int   $by    the saving admin's AdminID (audit)
	 */
	function Set_Hidden(array $pairs, $by = null)
	{
		$this->db->empty_table('card_visibility_hidden_cards');

		$clean = array();
		foreach ($pairs as $p) {
			$aid  = (int) (isset($p['admin']) ? $p['admin'] : 0);
			$slug = isset($p['slug']) ? (string) $p['slug'] : '';
			if ($aid > 0 && $slug !== '') {
				$clean[$aid . '|' . $slug] = array('AdminID' => $aid, 'CardSlug' => $slug);
			}
		}
		if (empty($clean)) { return; }

		$now = date('Y-m-d H:i:s');
		$by  = (int) $by ?: null;
		$rows = array();
		foreach ($clean as $row) {
			$row['InsertBy']   = $by;
			$row['InsertDate'] = $now;
			$rows[] = $row;
		}
		$this->db->insert_batch('card_visibility_hidden_cards', $rows);
	}
}
