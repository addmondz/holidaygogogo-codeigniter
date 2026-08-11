<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Global costing item master (table: costing_items). Reusable cost items grouped
 * into the five fixed costing categories (see costing_calc_helper::costing_categories).
 * Soft-deleted via Status='N'. Priced in a default currency from costing_currencies.
 */
class Costing_Item_Model extends CI_Model
{
    /**
     * All active items, newest first, with their currency code joined in.
     *
     * @param array $filters optional name/category filters
     * @return array
     */
    public function Read_Items($filters = array())
    {
        $this->db->select('ci.*, cc.code AS currency_code, cc.name AS currency_name');
        $this->db->from('costing_items ci');
        $this->db->join('costing_currencies cc', 'cc.id = ci.default_currency_id', 'left');
        $this->db->where('ci.Status', 'Y');

        if (!empty($filters['name'])) {
            $this->db->like('ci.name', $filters['name']);
        }
        if (!empty($filters['category'])) {
            $this->db->where('ci.category', $filters['category']);
        }

        $this->db->order_by('ci.category', 'ASC');
        $this->db->order_by('ci.name', 'ASC');
        return $this->db->get()->result_array();
    }

    /**
     * One active item by id, or null.
     */
    public function Read_Item($id)
    {
        $this->db->where('id', (int) $id);
        $this->db->where('Status', 'Y');
        return $this->db->get('costing_items')->row_array();
    }

    /**
     * Currency options for the item form (id + code + name), MYR first.
     */
    public function Read_Currency_Options()
    {
        $this->db->select('id, code, name');
        $this->db->order_by("CASE WHEN code = 'MYR' THEN 0 ELSE 1 END", 'ASC', false);
        $this->db->order_by('code', 'ASC');
        return $this->db->get('costing_currencies')->result_array();
    }

    /**
     * Create or update an item. Returns true on success.
     *
     * @param array $item id, name, category, default_currency_id, default_unit_cost
     */
    public function Save_Item($item)
    {
        $this->load->helper('costing_calc');
        $valid_categories = array_keys(costing_categories());

        $name = trim((string) $item['name']);
        $category = strtolower(trim((string) $item['category']));
        $currency_id = (int) $item['default_currency_id'];
        $unit_cost = (float) $item['default_unit_cost'];

        if ($name === '' || !in_array($category, $valid_categories, true) || $currency_id <= 0) {
            return false;
        }

        $data = array(
            'name'                => $name,
            'category'            => $category,
            'default_currency_id' => $currency_id,
            'default_unit_cost'   => $unit_cost,
        );

        if ((int) $item['id'] > 0) {
            $this->db->where('id', (int) $item['id']);
            $this->db->update('costing_items', $data);
            return true;
        }

        $this->db->insert('costing_items', $data);
        return $this->db->insert_id() > 0;
    }

    /**
     * Soft-delete an item.
     */
    public function Delete_Item($id)
    {
        $this->db->where('id', (int) $id);
        $this->db->update('costing_items', array('Status' => 'N'));
        return $this->db->affected_rows() >= 0;
    }
}
