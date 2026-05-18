<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Campaign_Ghl_Sync_Model extends CI_Model
{
	private $columns = null;

	public function log_row($data)
	{
		$now = date('Y-m-d H:i:s');
		$row = array(
			'CampaignID'    => isset($data['CampaignID'])    ? (int) $data['CampaignID']    : 0,
			'RunID'         => isset($data['RunID'])         ? (string) $data['RunID']      : '',
			'DedupKey'      => isset($data['DedupKey'])      ? (string) $data['DedupKey']   : null,
			'GhlContactID'  => isset($data['GhlContactID'])  ? (string) $data['GhlContactID'] : null,
			'Action'        => isset($data['Action'])        ? (string) $data['Action']     : 'failed',
			'Message'       => isset($data['Message'])       ? (string) $data['Message']    : null,
			'HttpStatus'    => isset($data['HttpStatus'])    ? (int) $data['HttpStatus']    : null,
			'Status'        => isset($data['Status'])        ? (string) $data['Status']     : 'completed',
			'MatchedCount'  => isset($data['MatchedCount'])  ? (int) $data['MatchedCount']  : 0,
			'CreatedCount'  => isset($data['CreatedCount'])  ? (int) $data['CreatedCount']  : 0,
			'TaggedCount'   => isset($data['TaggedCount'])   ? (int) $data['TaggedCount']   : 0,
			'EnrolledCount' => isset($data['EnrolledCount']) ? (int) $data['EnrolledCount'] : 0,
			'FailedCount'   => isset($data['FailedCount'])   ? (int) $data['FailedCount']   : 0,
			'InsertBy'      => isset($data['InsertBy'])      ? (int) $data['InsertBy']      : null,
			'InsertDate'    => $now,
			'CompletedAt'   => isset($data['CompletedAt'])   ? (string) $data['CompletedAt'] : null,
		);
		$this->db->insert('campaign_ghl_sync_log', $row);
		return (int) $this->db->insert_id();
	}

	// Inserts the initial run_summary row used as both the audit anchor and
	// the queue entry. Returns the inserted ID. Status='pending' so the cron
	// worker can claim it.
	public function create_run_summary($campaign_id, $run_id, $workflow_id, $total_guests, $admin_id)
	{
		return $this->log_row(array(
			'CampaignID'    => $campaign_id,
			'RunID'         => $run_id,
			'Action'        => 'run_summary',
			'Status'        => 'pending',
			'Message'       => $workflow_id,
			'TotalGuests'   => $total_guests,
			'CurrentOffset' => 0,
			'InsertBy'      => $admin_id,
		)) ? $this->insert_run_progress_columns($run_id, $total_guests, 0) : 0;
	}

	// The CI insert in log_row() doesn't know about CurrentOffset/TotalGuests
	// (they're not in the canonical $row above so older callers don't need to
	// pass them). Backfill them in a second UPDATE to keep log_row stable.
	private function insert_run_progress_columns($run_id, $total_guests, $current_offset)
	{
		$this->db->where('RunID', (string) $run_id);
		$this->db->where('Action', 'run_summary');
		$this->db->update('campaign_ghl_sync_log', array(
			'TotalGuests'   => (int) $total_guests,
			'CurrentOffset' => (int) $current_offset,
		));
		return 1;
	}

	// Atomically picks the next pending run, or reclaims a stale-running run
	// whose worker died (ClaimedAt older than 5 minutes). Sets ClaimedAt=NOW()
	// so concurrent workers don't both grab the same job. Returns the claimed
	// run row or null.
	public function claim_next_pending_run()
	{
		$now    = date('Y-m-d H:i:s');
		$stale  = date('Y-m-d H:i:s', time() - 5 * 60);

		// Find the candidate ID first (LIMIT in UPDATE...JOIN is portable).
		$sql = "SELECT ID FROM campaign_ghl_sync_log
				WHERE Action = 'run_summary'
				  AND (Status = 'pending'
				       OR (Status = 'running' AND (ClaimedAt IS NULL OR ClaimedAt < ?)))
				ORDER BY InsertDate ASC, ID ASC
				LIMIT 1";
		$row = $this->db->query($sql, array($stale))->row();
		if (!$row) { return null; }

		// Claim it. UPDATE...WHERE Status IN (...) makes the claim itself the
		// race winner: if another worker beat us, affected_rows=0 and we abort.
		$this->db->query(
			"UPDATE campaign_ghl_sync_log
			 SET Status = 'running', ClaimedAt = ?
			 WHERE ID = ?
			   AND Action = 'run_summary'
			   AND (Status = 'pending'
			        OR (Status = 'running' AND (ClaimedAt IS NULL OR ClaimedAt < ?)))",
			array($now, (int) $row->ID, $stale)
		);
		if ($this->db->affected_rows() < 1) { return null; }

		return $this->db
			->select('ID, CampaignID, RunID, Message AS WorkflowID, Status, CurrentOffset, TotalGuests, MatchedCount, CreatedCount, EnrolledCount, FailedCount, InsertBy, InsertDate, ClaimedAt')
			->from('campaign_ghl_sync_log')
			->where('ID', (int) $row->ID)
			->get()
			->row();
	}

	// Adds the supplied deltas to the per-run counters and updates the
	// CurrentOffset cursor in a single statement. Counter increments are
	// SQL-side so two workers (or the worker + a concurrent log_row) can't
	// stomp each other.
	public function increment_run_counters($run_id, $deltas, $current_offset)
	{
		$matched  = isset($deltas['matched'])  ? (int) $deltas['matched']  : 0;
		$created  = isset($deltas['created'])  ? (int) $deltas['created']  : 0;
		$enrolled = isset($deltas['enrolled']) ? (int) $deltas['enrolled'] : 0;
		$failed   = isset($deltas['failed'])   ? (int) $deltas['failed']   : 0;

		$sql = "UPDATE campaign_ghl_sync_log
				SET MatchedCount  = MatchedCount  + ?,
				    CreatedCount  = CreatedCount  + ?,
				    EnrolledCount = EnrolledCount + ?,
				    FailedCount   = FailedCount   + ?,
				    CurrentOffset = ?,
				    ClaimedAt     = ?
				WHERE RunID = ? AND Action = 'run_summary'";
		$this->db->query($sql, array(
			$matched, $created, $enrolled, $failed,
			(int) $current_offset,
			date('Y-m-d H:i:s'),
			(string) $run_id,
		));
		return $this->db->affected_rows();
	}

	// Terminal transition for a run. Idempotent: re-calling with the same
	// status is a no-op except for refreshing CompletedAt.
	public function finalize_run($run_id, $status, $completed_at = null)
	{
		$completed_at = $completed_at ?: date('Y-m-d H:i:s');
		$this->db->where('RunID', (string) $run_id);
		$this->db->where('Action', 'run_summary');
		$this->db->update('campaign_ghl_sync_log', array(
			'Status'      => (string) $status,
			'CompletedAt' => $completed_at,
			'ClaimedAt'   => null,
		));
		return $this->db->affected_rows();
	}

	public function get_run_state($run_id)
	{
		return $this->db
			->select('ID, CampaignID, RunID, Message AS WorkflowID, Status, CurrentOffset, TotalGuests, MatchedCount, CreatedCount, EnrolledCount, FailedCount, CompletedAt, InsertDate, ClaimedAt')
			->from('campaign_ghl_sync_log')
			->where('RunID', (string) $run_id)
			->where('Action', 'run_summary')
			->limit(1)
			->get()
			->row();
	}

	public function get_latest_run_summary($campaign_id)
	{
		$row = $this->db
			->select('RunID, Status, MatchedCount, CreatedCount, TaggedCount, EnrolledCount, FailedCount, CurrentOffset, TotalGuests, CompletedAt, InsertDate')
			->from('campaign_ghl_sync_log')
			->where('CampaignID', (int) $campaign_id)
			->where('Action', 'run_summary')
			->order_by('ID', 'DESC')
			->limit(1)
			->get()
			->row();
		return $row ? $row : null;
	}

	public function get_latest_run_summaries_for_campaigns($campaign_ids)
	{
		if (empty($campaign_ids) || !is_array($campaign_ids)) {
			return array();
		}
		$ids = array();
		foreach ($campaign_ids as $id) {
			$id = (int) $id;
			if ($id > 0) { $ids[] = $id; }
		}
		if (empty($ids)) { return array(); }

		$placeholders = implode(',', array_fill(0, count($ids), '?'));
		$sql = "
SELECT t1.CampaignID, t1.RunID, t1.Status, t1.MatchedCount, t1.CreatedCount,
       t1.TaggedCount, t1.EnrolledCount, t1.FailedCount,
       t1.CurrentOffset, t1.TotalGuests,
       t1.CompletedAt, t1.InsertDate
FROM campaign_ghl_sync_log t1
INNER JOIN (
  SELECT CampaignID, MAX(ID) AS MaxID
  FROM campaign_ghl_sync_log
  WHERE Action = 'run_summary' AND CampaignID IN ({$placeholders})
  GROUP BY CampaignID
) t2 ON t2.CampaignID = t1.CampaignID AND t2.MaxID = t1.ID
		";
		$rows = $this->db->query($sql, $ids)->result();
		$out = array();
		foreach ($rows as $r) {
			$out[(int) $r->CampaignID] = $r;
		}
		return $out;
	}
}
