<?php

defined('BASEPATH') || exit('No direct script access allowed');

#[AllowDynamicProperties]
class Mdl_Skus extends Response_Model
{
    public $table = 'ip_product_skus';

    public $primary_key = 'ip_product_skus.sku_id';

    public function __construct()
    {
        parent::__construct();
        $this->load->helper('product_amount');
    }

    public function default_select()
    {
        $this->db->select('SQL_CALC_FOUND_ROWS ip_product_skus.*', false);
    }

    public function default_order_by()
    {
        $this->db->order_by('sku_code');
    }

    public function validation_rules()
    {
        return [
            'sku_code' => [
                'field' => 'sku_code',
                'label' => trans('product_sku'),
                'rules' => 'trim|required|max_length[191]|callback_validate_sku_code',
            ],
            'sku_shipping_cost' => [
                'field' => 'sku_shipping_cost',
                'label' => trans('product_shipping_cost'),
                'rules' => 'trim|callback_validate_sku_shipping_cost',
            ],
        ];
    }

    public function validate_sku_code($value): bool
    {
        if (is_array($this->input->post('sku_code'))) {
            $this->form_validation->set_message('validate_sku_code', trans('sku_invalid'));

            return false;
        }

        $this->db->where('sku_code', trim((string) $value));
        if ($this->id) {
            $this->db->where('sku_id !=', $this->id);
        }
        if ($this->db->count_all_results($this->table)) {
            $this->form_validation->set_message('validate_sku_code', trans('sku_already_exists'));

            return false;
        }

        return true;
    }

    public function validate_sku_shipping_cost($value): bool
    {
        if (is_array($this->input->post('sku_shipping_cost')) || standardize_product_amount($value) === false) {
            $this->form_validation->set_message('validate_sku_shipping_cost', trans('product_amount_invalid'));

            return false;
        }

        return true;
    }

    public function db_array()
    {
        $db_array = parent::db_array();
        $db_array['sku_shipping_cost'] = standardize_product_amount($db_array['sku_shipping_cost'] ?? null) ?? '0';

        return $db_array;
    }

    public function save($id = null, $db_array = null)
    {
        $id = parent::save($id, $db_array);
        $sku = $this->get_by_id($id);
        if ($sku) {
            // Invoice item models also read the legacy SKU column directly.
            $this->db->where('product_sku_id', $id)->update('ip_products', ['product_sku' => $sku->sku_code]);
        }

        return $id;
    }

    public function form_shipping_cost(): string
    {
        $submitted = $this->input->post('sku_shipping_cost');
        if ($submitted !== null) {
            return is_scalar($submitted) ? (string) $submitted : '';
        }

        return format_product_amount($this->form_value('sku_shipping_cost') ?: '0');
    }
}
