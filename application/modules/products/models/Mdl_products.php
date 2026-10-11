<?php

if ( ! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/*
 * InvoicePlane
 *
 * @author		InvoicePlane Developers & Contributors
 * @copyright	Copyright (c) 2012 - 2018 InvoicePlane.com
 * @license		https://invoiceplane.com/license.txt
 * @link		https://invoiceplane.com
 */

#[AllowDynamicProperties]
class Mdl_Products extends Response_Model
{
    public $table = 'ip_products';

    public $primary_key = 'ip_products.product_id';

    public function __construct()
    {
        parent::__construct();
        $this->load->helper('product_amount');
    }

    public function default_select()
    {
        $this->db->select('SQL_CALC_FOUND_ROWS ip_products.*, ip_families.*, ip_units.*, ip_tax_rates.*,
            COALESCE(ip_product_skus.sku_code, ip_products.product_sku) AS product_sku,
            COALESCE(ip_product_skus.sku_shipping_cost, ip_products.product_shipping_cost) AS product_shipping_cost', false);
    }

    public function default_order_by()
    {
        $this->db->order_by('ip_families.family_name, ip_products.product_name');
    }

    public function default_join()
    {
        $this->db->join('ip_families', 'ip_families.family_id = ip_products.family_id', 'left');
        $this->db->join('ip_units', 'ip_units.unit_id = ip_products.unit_id', 'left');
        $this->db->join('ip_tax_rates', 'ip_tax_rates.tax_rate_id = ip_products.tax_rate_id', 'left');
        $this->db->join('ip_product_skus', 'ip_product_skus.sku_id = ip_products.product_sku_id', 'left');
    }

    public function by_product($match)
    {
        $this->db->group_start();
        $this->db->like('LOWER(COALESCE(ip_product_skus.sku_code, ip_products.product_sku))', mb_strtolower($match));
        $this->db->or_like('ip_products.product_name', $match);
        $this->db->or_like('ip_products.product_description', $match);
        $this->db->group_end();
    }

    public function by_family($match)
    {
        $this->db->where('ip_products.family_id', $match);
    }

    /**
     * @return array
     */
    public function validation_rules()
    {
        return [
            'product_sku_id' => [
                'field' => 'product_sku_id',
                'label' => trans('product_sku'),
                'rules' => 'trim|required|callback_validate_product_sku',
            ],
            'product_name' => [
                'field' => 'product_name',
                'label' => trans('product_name'),
                'rules' => 'required',
            ],
            'product_description' => [
                'field' => 'product_description',
                'label' => trans('product_description'),
            ],
            'product_price' => [
                'field' => 'product_price',
                'label' => trans('product_sales_price'),
                'rules' => 'trim|required|callback_validate_product_amount[product_price]',
            ],
            'purchase_price' => [
                'field' => 'purchase_price',
                'label' => trans('product_cost_price'),
                'rules' => 'trim|callback_validate_product_amount[purchase_price]',
            ],
            'provider_name' => [
                'field' => 'provider_name',
                'label' => trans('provider_name'),
            ],
            'family_id' => [
                'field' => 'family_id',
                'label' => trans('family'),
                'rules' => 'numeric',
            ],
            'unit_id' => [
                'field' => 'unit_id',
                'label' => trans('unit'),
                'rules' => 'numeric',
            ],
            'tax_rate_id' => [
                'field' => 'tax_rate_id',
                'label' => trans('tax_rate'),
                'rules' => 'numeric',
            ],
            // Sumex
            'product_tariff' => [
                'field' => 'product_tariff',
                'label' => trans('product_tariff'),
            ],
        ];
    }

    /**
     * @return array
     */
    public function db_array()
    {
        $db_array = parent::db_array();

        $db_array['product_price'] = standardize_product_amount($db_array['product_price'] ?? null);
        $db_array['purchase_price'] = standardize_product_amount($db_array['purchase_price'] ?? null);
        $sku = $this->db->where('sku_id', $db_array['product_sku_id'])->get('ip_product_skus')->row();
        if ( ! $sku) {
            throw new InvalidArgumentException('A valid product SKU is required.');
        }
        $db_array['product_sku'] = $sku->sku_code;
        $db_array['family_id'] = (empty($db_array['family_id']) ? null : $db_array['family_id']);
        $db_array['unit_id'] = (empty($db_array['unit_id']) ? null : $db_array['unit_id']);
        $db_array['tax_rate_id'] = (empty($db_array['tax_rate_id']) ? null : $db_array['tax_rate_id']);

        return $db_array;
    }

    public function validate_product_amount($value, $field): bool
    {
        if (is_array($this->input->post($field)) || standardize_product_amount($value) === false) {
            $this->form_validation->set_message('validate_product_amount', trans('product_amount_invalid'));

            return false;
        }

        return true;
    }

    public function validate_product_sku($value): bool
    {
        if (is_array($this->input->post('product_sku_id')) || ! preg_match('/\A[1-9][0-9]*\z/', (string) $value)
            || ! $this->db->where('sku_id', $value)->count_all_results('ip_product_skus')) {
            $this->form_validation->set_message('validate_product_sku', trans('sku_invalid'));

            return false;
        }

        return true;
    }

    public function form_amount($field): string
    {
        $submitted = $this->input->post($field);
        if ($field !== 'product_shipping_cost' && $submitted !== null) {
            return is_scalar($submitted) ? (string) $submitted : '';
        }

        $value = $this->form_value($field);
        if ($value === null || $value === '') {
            return $field === 'product_shipping_cost' ? '0' : '';
        }

        return format_product_amount($value);
    }
}
