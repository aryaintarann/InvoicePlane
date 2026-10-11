<?php

/**
 * CUSTOM LANGUAGE STRINGS.
 *
 * This file allows you to override or add custom language strings without
 * modifying the main ip_lang.php file. Language strings defined here will
 * extend (not replace) the translations from ip_lang.php.
 *
 * Add your custom Language Strings here with the following scheme:
 *
 * $lang = [
 *     'language_string_key' => 'Actual content of the Language String',
 *     'another_key'         => 'Another custom translation',
 * ];
 *
 * Please notice that 'language_string_key' should only contain alphanumeric
 * characters and underscores.
 *
 * Example - Override an existing translation:
 * $lang = [
 *     'client_name' => 'Customer Name',  // Changes "Client Name" to "Customer Name"
 * ];
 *
 * Example - Add a new custom translation:
 * $lang = [
 *     'my_custom_string' => 'My Custom Translation',
 * ];
 *
 * For more information, see the ip_lang.php file or visit the
 * community forums at https://community.invoiceplane.com/
 */
$lang = [
    'quote_rejection_reason' => 'Alasan penolakan',
    'quote_rejection_reason_help' => 'Opsional. Maksimal 2.000 karakter.',
    'quote_rejection_reason_invalid' => 'Masukkan alasan yang valid, maksimal 2.000 karakter.',
    'add_family' => 'Add Product Category',
    'any_family' => 'Any Product Category',
    'families' => 'Product Categories',
    'family' => 'Product Category',
    'family_already_exists' => 'Product category already exists!',
    'family_name' => 'Category Name',
    'filter_families' => 'Filter Product Categories',
    'product_families' => 'Product Categories',
    'select_family' => 'Select Product Category',
    'view_product_families' => 'View Product Categories',
    'product_cost_price' => 'HPP (Harga Modal)',
    'product_shipping_cost' => 'Ongkir',
    'product_total_cost' => 'Total HPP',
    'product_sales_price' => 'Harga Jual',
    'product_margin' => 'Margin Produk',
    'product_margin_formula' => 'Margin per unit = Harga Jual − Total HPP.',
    'invoice_last_product_price' => 'Harga terakhir:',
    'invoice_last_product_price_empty' => 'Belum ada harga barang ini pada invoice terkirim.',
    'invoice_last_product_price_loading' => 'Memuat harga terakhir…',
    'invoice_last_product_price_error' => 'Harga terakhir belum dapat dimuat.',
    'product_prices_per_unit' => 'Harga Produk per Unit',
    'product_amount_invalid' => '{field} harus berupa nominal tidak negatif dengan format angka sesuai pengaturan aplikasi dan ketelitian maksimal 2 angka desimal.',
    'sku_create' => 'Create SKU',
    'sku_list' => 'SKU',
    'sku_edit' => 'Edit SKU',
    'sku_select' => 'Pilih SKU',
    'sku_invalid' => 'Pilih SKU yang tersedia.',
    'sku_already_exists' => 'SKU sudah terdaftar.',
    'sku_shipping_per_unit' => 'Ongkir per Unit',
    'sku_shipping_shared' => 'Perubahan ongkir berlaku untuk semua produk yang memakai SKU ini.',
    'sku_shipping_readonly' => 'Ongkir mengikuti SKU. Ubah ongkir melalui menu SKU.',
    'sku_legacy_unlinked' => 'SKU lama belum terhubung. Pilih SKU untuk menentukan ongkir produk ini.',
    'sku_empty' => 'Belum ada SKU. Gunakan Create SKU untuk menambahkan SKU barang.',
];
