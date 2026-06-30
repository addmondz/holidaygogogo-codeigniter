<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Agent Score Settings — the owner's "exclude these agents from the Agent Score"
 * list (table `agent_score_excluded_agents`). Presence of a row => excluded.
 *
 * Reads here feed both Agent Score paths in Booking.php (the TC Top-5 leaderboard
 * card and the owner per-agent matrix); writes happen only from the owner-only
 * Agent_Score_Setting controller.
 */
class Agent_Score_Setting_Model extends CI_Model
{
	/**
	 * Every TC sales agent (Level 20/50, active) — the population the owner picks
	 * from on the settings page. Ordered by name.
	 *
	 * @return array list of {AdminID, Name, Level}
	 */
	function Sales_Agents()
	{
		$this->db->select('AdminID, Name, Level');
		$this->db->where_in('Level', array('20', '50'));
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('admin')->result();
	}

	/**
	 * The set of excluded AdminIDs.
	 *
	 * @return int[] list of AdminID
	 */
	function Excluded_Admin_Ids()
	{
		$out = array();
		foreach ($this->db->select('AdminID')->get('agent_score_excluded_agents')->result() as $r) {
			$out[] = (int) $r->AdminID;
		}
		return $out;
	}

	/**
	 * Replace the whole exclusion list with $admin_ids (full-sync, mirroring the
	 * admin Lead-Dashboard-Agents save). Non-numeric / non-positive ids are
	 * dropped; the set is de-duplicated.
	 *
	 * @param array $admin_ids AdminIDs to exclude
	 * @param int   $by        the saving admin's AdminID (audit)
	 */
	function Set_Excluded(array $admin_ids, $by = null)
	{
		$this->db->empty_table('agent_score_excluded_agents');

		$clean = array();
		foreach ($admin_ids as $id) {
			$id = (int) $id;
			if ($id > 0) { $clean[$id] = true; }
		}
		if (empty($clean)) { return; }

		$now = date('Y-m-d H:i:s');
		$by  = (int) $by ?: null;
		$rows = array();
		foreach (array_keys($clean) as $id) {
			$rows[] = array('AdminID' => $id, 'InsertBy' => $by, 'InsertDate' => $now);
		}
		$this->db->insert_batch('agent_score_excluded_agents', $rows);
	}
}
