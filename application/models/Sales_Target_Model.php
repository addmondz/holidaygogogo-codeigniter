<?php
class Sales_Target_Model extends CI_Model
{
	public function get($admin_id, $year, $month)
	{
		return $this->db
			->where('AdminID', (int)$admin_id)
			->where('target_year', (int)$year)
			->where('target_month', (int)$month)
			->get('sales_target')
			->row_array();
	}

	public function get_amount($admin_id, $year, $month)
	{
		$row = $this->get($admin_id, $year, $month);
		return $row ? (float)$row['target_amount'] : 0.0;
	}

	public function list_for_period($year, $month)
	{
		return $this->db
			->select('a.AdminID, a.Name, a.Level, a.Status, COALESCE(st.target_amount, 0) AS target_amount, st.updated_at', false)
			->from('admin a')
			->join("sales_target st", "st.AdminID = a.AdminID AND st.target_year = " . (int)$year . " AND st.target_month = " . (int)$month, 'left')
			->where_in('a.Level', array('20', '50'))
			->where('a.Status', 'Y')
			->order_by('a.Name', 'asc')
			->get()
			->result_array();
	}

	public function upsert($admin_id, $year, $month, $amount)
	{
		$admin_id = (int)$admin_id;
		$year     = (int)$year;
		$month    = (int)$month;
		$amount   = (float)$amount;

		$sql = "INSERT INTO sales_target (AdminID, target_year, target_month, target_amount)
		        VALUES (?, ?, ?, ?)
		        ON DUPLICATE KEY UPDATE
		            target_amount = VALUES(target_amount),
		            updated_at    = CURRENT_TIMESTAMP";
		$this->db->query($sql, array($admin_id, $year, $month, $amount));
		return $this->get($admin_id, $year, $month);
	}

	// ---- Yearly target (sales_target_year) --------------------------------
	// Companion to the monthly methods above. One row per admin per year, keyed
	// by the unique (AdminID, target_year). Backs the Booking dashboard
	// "Year Sales vs Target" card.

	public function get_year($admin_id, $year)
	{
		return $this->db
			->where('AdminID', (int)$admin_id)
			->where('target_year', (int)$year)
			->get('sales_target_year')
			->row_array();
	}

	public function get_year_amount($admin_id, $year)
	{
		$row = $this->get_year($admin_id, $year);
		return $row ? (float)$row['target_amount'] : 0.0;
	}

	public function upsert_year($admin_id, $year, $amount)
	{
		$admin_id = (int)$admin_id;
		$year     = (int)$year;
		$amount   = max(0.0, (float)$amount);

		$sql = "INSERT INTO sales_target_year (AdminID, target_year, target_amount)
		        VALUES (?, ?, ?)
		        ON DUPLICATE KEY UPDATE
		            target_amount = VALUES(target_amount),
		            updated_at    = CURRENT_TIMESTAMP";
		$this->db->query($sql, array($admin_id, $year, $amount));
		return $this->get_year($admin_id, $year);
	}

	public function copy_from_previous_month($year, $month)
	{
		$prev_year  = (int)$year;
		$prev_month = (int)$month - 1;
		if($prev_month < 1) { $prev_month = 12; $prev_year--; }

		$sql = "INSERT INTO sales_target (AdminID, target_year, target_month, target_amount)
		        SELECT AdminID, ?, ?, target_amount
		        FROM sales_target
		        WHERE target_year = ? AND target_month = ?
		        ON DUPLICATE KEY UPDATE
		            target_amount = VALUES(target_amount),
		            updated_at    = CURRENT_TIMESTAMP";
		$this->db->query($sql, array((int)$year, (int)$month, $prev_year, $prev_month));
		return $this->list_for_period($year, $month);
	}
}
