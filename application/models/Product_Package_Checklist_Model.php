<?php
class Product_Package_Checklist_Model extends CI_Model
{
	function Read_Product_Package_Checklist()
	{
		$this->db->select('ppc.id, ppc.product_id, ppc.package_checklist_id, p.ProductCode, p.Name as ProductName, pc.name as ChecklistName');
		$this->db->from('product_package_checklist ppc');
		$this->db->join('product p', 'p.ProductID = ppc.product_id', 'left');
		$this->db->join('package_checklist pc', 'pc.ID = ppc.package_checklist_id', 'left');
		$this->db->where('ppc.id', $this->input->get('id'));
		return $this->db->get()->row_array();
	}

	function Read_Product_Package_Checklists()
	{
		$this->db->select('ppc.id, ppc.product_id, ppc.package_checklist_id, p.ProductCode, p.Name as ProductName, pc.name as ChecklistName, ppc.created_at, ppc.updated_at');
		$this->db->from('product_package_checklist ppc');
		$this->db->join('product p', 'p.ProductID = ppc.product_id', 'left');
		$this->db->join('package_checklist pc', 'pc.ID = ppc.package_checklist_id', 'left');
		
		if(!empty($this->input->get('product_id'))) {
			$this->db->where('ppc.product_id', $this->input->get('product_id'));
		}
		if(!empty($this->input->get('package_checklist_id'))) {
			$this->db->where('ppc.package_checklist_id', $this->input->get('package_checklist_id'));
		}
		
		$this->db->order_by('p.ProductCode', 'ASC');
		$this->db->order_by('pc.name', 'ASC');
		return $this->db->get()->result();
	}

	function Read_Products()
	{
		$this->db->select('ProductID, ProductCode, Name');
		$this->db->where('Status', 'Y');
		$this->db->order_by('ProductCode', 'ASC');
		return $this->db->get('product')->result();
	}

	function Read_Package_Checklists()
	{
		$this->db->select('ID, name, is_required');
		$this->db->order_by('ID', 'ASC');
		return $this->db->get('package_checklist')->result();
	}

	function Create()
	{
		$data = json_decode(json_encode($this->input->post('product_package_checklist')), true);
		$this->db->insert_batch('product_package_checklist', $data);
	}
	
	function Update()
	{
		$this->db->update_batch('product_package_checklist', json_decode(json_encode($this->input->post('product_package_checklist'))), 'id');
	}

	function Delete($id)
	{
		$this->db->where('id', $id);
		$this->db->delete('product_package_checklist');
	}

	function Validate_Id($id)
	{
		$this->db->where('id', $id);
		if($this->db->get('product_package_checklist')->row()) {
			return true;
		} else {
			return false;
		}
	}

	function Detect_Duplicate()
	{
		$this->db->where('product_id', $this->input->post('product_id'));
		$this->db->where('package_checklist_id', $this->input->post('package_checklist_id'));
		if($this->input->get('id')) {
			$this->db->where('id !=', $this->input->get('id'));
		}
		if($this->db->get('product_package_checklist')->row()) {
			return true;
		} else {
			return false;
		}
	}

	function Get_Checklists_For_Product($product_id)
	{
		$this->db->select('package_checklist_json');
		$this->db->where('product_id', $product_id);
		$result = $this->db->get('product_package_checklist')->row();
		
		if($result && !empty($result->package_checklist_json)) {
			$json = $result->package_checklist_json;
			// Handle both JSON string and already decoded array
			if(is_string($json)) {
				$checklist_ids = json_decode($json, true);
			} else {
				$checklist_ids = $json;
			}
			return is_array($checklist_ids) ? $checklist_ids : array();
		}
		return array();
	}

	function Bulk_Update_Product_Checklists($product_id, $checklist_ids)
	{
		// Ensure checklist_ids is an array
		if(!is_array($checklist_ids)) {
			$checklist_ids = array();
		}
		
		// Normalize all IDs to integers
		$checklist_ids = array_map('intval', $checklist_ids);
		$checklist_ids = array_values(array_filter($checklist_ids, function($id) { return $id > 0; }));
		
		// Get required checklist IDs and ensure they're included
		$this->db->select('ID');
		$this->db->where('is_required', 1);
		$required_checklists = $this->db->get('package_checklist')->result();
		foreach($required_checklists as $req) {
			$req_id = (int)$req->ID;
			if(!in_array($req_id, $checklist_ids)) {
				$checklist_ids[] = $req_id;
			}
		}

		// Auto-add deposit checklist if product has supplier deposit
		$this->db->select('has_supplier_deposit');
		$this->db->where('ProductID', $product_id);
		$product_row = $this->db->get('product')->row();

		if($product_row && $product_row->has_supplier_deposit == 1) {
			$this->db->select('ID');
			$this->db->like('name', 'Payment Out To Supplier (deposit)');
			$deposit_checklist = $this->db->get('package_checklist')->row();
			if($deposit_checklist) {
				$deposit_id = (int)$deposit_checklist->ID;
				if(!in_array($deposit_id, $checklist_ids)) {
					$checklist_ids[] = $deposit_id;
				}
			}
		}

		// Check if table exists
		if(!$this->db->table_exists('product_package_checklist')) {
			log_message('error', 'Table product_package_checklist does not exist. Please run SQL migration.');
			return false;
		}
		
		// Check if table has the new JSON column structure
		$fields = $this->db->list_fields('product_package_checklist');
		$has_json_column = in_array('package_checklist_json', $fields);
		$has_old_column = in_array('package_checklist_id', $fields);
		
		if(!$has_json_column && $has_old_column) {
			log_message('error', 'Table product_package_checklist has old structure. Please run SQL migration to update to JSON structure.');
			return false;
		}
		
		// Check if record exists
		$this->db->where('product_id', $product_id);
		$existing = $this->db->get('product_package_checklist')->row();
		
		$json_data = json_encode($checklist_ids);
		
		if($existing) {
			// Update existing record
			$this->db->where('product_id', $product_id);
			$result = $this->db->update('product_package_checklist', array(
				'package_checklist_json' => $json_data
			));
			
			if(!$result) {
				$error = $this->db->error();
				log_message('error', 'Failed to update product_package_checklist: ' . (isset($error['message']) ? $error['message'] : 'Unknown error'));
				return false;
			}
		} else {
			// Insert new record
			$result = $this->db->insert('product_package_checklist', array(
				'product_id' => $product_id,
				'package_checklist_json' => $json_data
			));
			
			if(!$result) {
				$error = $this->db->error();
				log_message('error', 'Failed to insert product_package_checklist: ' . (isset($error['message']) ? $error['message'] : 'Unknown error'));
				return false;
			}
		}
		
		return true;
	}
}

