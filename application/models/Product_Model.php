<?php
class Product_Model extends CI_Model
{
	function Read_Product()
	{
		$this->db->select('ProductID, product.SupplierID, ProductCode, product.Name As Product, RetailPrice, SupplierPrice, is_child_or_infant, category.Name As Category');
		$this->db->join('category', 'category.CategoryID = product.CategoryID', 'left');
		$this->db->where('ProductID', $this->input->get('product_id'));
		return $this->db->get('product')->row_array();
	}

	function Read_Products1()
	{
		$this->db->select('ProductID, ProductCode, product.Name As Product, RetailPrice, SupplierPrice, product.Status');
		if(!empty($this->input->get('category'))) {
			$this->db->where('product.CategoryID', $this->input->get('category'));
		}
		if(!empty($this->input->get('supplier'))) {
			$this->db->where('product.SupplierID', $this->input->get('supplier'));
		}
		if(!empty($this->input->get('product_code'))) {
			$this->db->where('ProductCode', $this->input->get('product_code'));
		}
		if(!empty($this->input->get('name'))) {
			$this->db->where('product.Name', $this->input->get('name'));
		}
		$this->db->where('product.Status', 'Y');
		$this->db->order_by('ProductCode', 'ASC');
		$this->db->order_by('product.Name', 'ASC');
		return $this->db->get('product')->result();
	}

	function Read_Products2()
	{
		$this->db->select('ProductCode, product.Name As Product, RetailPrice, SupplierPrice, category.Name As Category, supplier.Name As Supplier');
		$this->db->join('category', 'category.CategoryID = product.CategoryID', 'left');
		$this->db->join('supplier', 'supplier.SupplierID = product.SupplierID', 'left');
		if(!empty($this->input->get('category'))) {
			$this->db->where('product.CategoryID', $this->input->get('category'));
		}
		if(!empty($this->input->get('supplier'))) {
			$this->db->where('product.SupplierID', $this->input->get('supplier'));
		}
		if(!empty($this->input->get('product_code'))) {
			$this->db->where('ProductCode', $this->input->get('product_code'));
		}
		if(!empty($this->input->get('name'))) {
			$this->db->where('product.Name', $this->input->get('name'));
		}
		$this->db->where('product.Status', 'Y');
		$this->db->order_by('ProductCode', 'ASC');
		$this->db->order_by('product.Name', 'ASC');
		return $this->db->get('product')->result();
	}

	function Read_Categories()
	{
		$this->db->select('CategoryID, Name');
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('category')->result();
	}

	function Read_Suppliers()
	{
		$this->db->select('SupplierID, Name');
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('supplier')->result();
	}
	
	function Create()
	{
		$product_data = json_decode(json_encode($this->input->post('product')), true);
		
		// Use insert instead of insert_batch for single record to get proper insert_id
		if(!empty($product_data) && isset($product_data[0])) {
			$this->db->insert('product', $product_data[0]);
			$product_id = $this->db->insert_id();
		} else {
			$product_id = false;
		}

		if($product_id && $product_id > 0) {
			$this->db->select('category_code.Name');
			$this->db->join('category_code', 'category_code.CategoryCodeID = category.CategoryCodeID', 'left');
			$this->db->where('CategoryID', $this->input->post('category_id'));
			$result = $this->db->get('category')->row();
			
			if($result && isset($result->Name)) {
				$category_code = $result->Name;
				$this->db->set('ProductCode', $category_code . '-' . $product_id);
				$this->db->where('ProductID', $product_id);
				$this->db->update('product');
			}
		}
		
		return $product_id;
	}
	
	function Update()
	{
		$this->db->update_batch('product', json_decode(json_encode($this->input->post('product'))), 'ProductID');
	}

	function Detect()
	{
		$this->db->where('Name', $this->input->post('name'));
		if($this->db->get('product')->row()) {
			return true;
		} else {
			return false;
		}
	}

	function Get_Max_Name_Length($category_id)
	{
		$this->db->select('category_code.Name');
		$this->db->join('category_code', 'category_code.CategoryCodeID = category.CategoryCodeID', 'left');
		$this->db->where('CategoryID', $category_id);
		$category_code = $this->db->get('category')->row()->Name;

		$next_id = $this->db->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product'")->row()->AUTO_INCREMENT;

		return 99 - strlen($category_code) - 1 - strlen($next_id);
	}
}