<form method="post">

    <?php _csrf_field(); ?>

    <div id="headerbar">
        <h1 class="headerbar-title"><?php _trans('products_form'); ?></h1>
        <?php $this->layout->load_view('layout/header_buttons'); ?>
    </div>

    <div id="content">

        <?php $this->layout->load_view('layout/alerts'); ?>

        <div class="row">
            <div class="col-xs-12 col-md-6">

                <div class="panel panel-default">
                    <div class="panel-heading">

<?php if ($this->mdl_products->form_value('product_id')) : ?>
                        #<?php echo $this->mdl_products->form_value('product_id'); ?>&nbsp;
                        <?php echo $this->mdl_products->form_value('product_name', true); ?>
<?php else : ?>
                        <?php _trans('new_product'); ?>
<?php endif; ?>

                    </div>
                    <div class="panel-body">

                        <div class="form-group">
                            <label for="family_id">
                                <?php _trans('family'); ?>
                            </label>

                            <select name="family_id" id="family_id" class="form-control simple-select">
                                <option value="0"><?php _trans('select_family'); ?></option>
<?php foreach ($families as $family) { ?>
                                <option value="<?php echo $family->family_id; ?>"
                                    <?php check_select($this->mdl_products->form_value('family_id'), $family->family_id) ?>
                                ><?php echo htmlsc($family->family_name); ?></option>
<?php } ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="product_sku_id">
                                <?php _trans('product_sku'); ?>
                            </label>

                            <select name="product_sku_id" id="product_sku_id" class="form-control simple-select" required>
                                <option value=""><?php _trans('sku_select'); ?></option>
<?php foreach ($skus as $sku) { ?>
                                <option value="<?php echo (int) $sku->sku_id; ?>"
                                        data-shipping-cost="<?php echo htmlsc(format_product_amount($sku->sku_shipping_cost)); ?>"
                                    <?php check_select($this->mdl_products->form_value('product_sku_id'), $sku->sku_id); ?>
                                ><?php _htmlsc($sku->sku_code); ?></option>
<?php } ?>
                            </select>
<?php if (is_scalar($this->mdl_products->form_value('product_sku')) && $this->mdl_products->form_value('product_sku') && ! $this->mdl_products->form_value('product_sku_id')) { ?>
                            <p class="help-block"><?php _trans('sku_legacy_unlinked'); ?>
                                (<?php echo htmlsc($this->mdl_products->form_value('product_sku')); ?>)
                            </p>
<?php } ?>
                        </div>

                        <div class="form-group">
                            <label for="product_name">
                                <?php _trans('product_name'); ?>
                            </label>

                            <input type="text" name="product_name" id="product_name" class="form-control" required
                                   value="<?php echo $this->mdl_products->form_value('product_name', true); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="product_description">
                                <?php _trans('product_description'); ?>
                            </label>

                            <textarea name="product_description" id="product_description" class="form-control"
                                      rows="3"><?php echo $this->mdl_products->form_value('product_description', true); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="unit_id">
                                <?php _trans('product_unit'); ?>
                            </label>

                            <select name="unit_id" id="unit_id" class="form-control simple-select">
                                <option value="0"><?php _trans('select_unit'); ?></option>
<?php foreach ($units as $unit) { ?>
                                <option value="<?php echo $unit->unit_id; ?>"
                                    <?php check_select($this->mdl_products->form_value('unit_id'), $unit->unit_id); ?>
                                ><?php echo htmlsc($unit->unit_name) . '/' . htmlsc($unit->unit_name_plrl); ?></option>
<?php } ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="tax_rate_id">
                                <?php _trans('tax_rate'); ?>
                            </label>

                            <select name="tax_rate_id" id="tax_rate_id" class="form-control simple-select">
                                <option value="0"><?php _trans('none'); ?></option>
<?php foreach ($tax_rates as $tax_rate) { ?>
                                <option value="<?php echo $tax_rate->tax_rate_id; ?>"
                                    <?php check_select($this->mdl_products->form_value('tax_rate_id'), $tax_rate->tax_rate_id); ?>
                                ><?php echo htmlsc($tax_rate->tax_rate_name) . ' (' . format_amount($tax_rate->tax_rate_percent) . '%)'; ?></option>
<?php } ?>
                            </select>
                        </div>

                    </div>
                </div>
            </div>

            <div class="col-xs-12 col-md-6">

                <div class="panel panel-default">
                    <div class="panel-heading">
                        <?php _trans('product_prices_per_unit'); ?>
                    </div>
                    <div class="panel-body">

                        <div class="form-group">
                            <label for="purchase_price">
                                <?php _trans('product_cost_price'); ?>
                            </label>

                            <div class="input-group has-feedback">
                                <input type="text" name="purchase_price" id="purchase_price" class="form-control"
                                       value="<?php echo htmlsc($this->mdl_products->form_amount('purchase_price')); ?>">
                                <span class="input-group-addon"><?php echo htmlsc(get_setting('currency_symbol')); ?></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="product_shipping_cost">
                                <?php _trans('product_shipping_cost'); ?>
                            </label>

                            <div class="input-group has-feedback">
                                <input type="text" id="product_shipping_cost" class="form-control" readonly
                                       value="<?php echo htmlsc($this->mdl_products->form_amount('product_shipping_cost')); ?>">
                                <span class="input-group-addon"><?php echo htmlsc(get_setting('currency_symbol')); ?></span>
                            </div>
                            <p class="help-block"><?php _trans('sku_shipping_readonly'); ?></p>
                        </div>

                        <div class="form-group">
                            <label for="product_total_cost">
                                <?php _trans('product_total_cost'); ?>
                            </label>

                            <div class="input-group has-feedback">
                                <input type="text" id="product_total_cost" class="form-control" readonly>
                                <span class="input-group-addon"><?php echo htmlsc(get_setting('currency_symbol')); ?></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="product_price">
                                <?php _trans('product_sales_price'); ?>
                            </label>

                            <div class="input-group has-feedback">
                                <input type="text" name="product_price" id="product_price" class="form-control" required
                                       value="<?php echo htmlsc($this->mdl_products->form_amount('product_price')); ?>">
                                <span class="input-group-addon"><?php echo htmlsc(get_setting('currency_symbol')); ?></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="product_margin">
                                <?php _trans('product_margin'); ?>
                            </label>

                            <div class="input-group has-feedback">
                                <input type="text" id="product_margin" class="form-control" readonly>
                                <span class="input-group-addon"><?php echo htmlsc(get_setting('currency_symbol')); ?></span>
                            </div>
                            <p class="help-block"><?php _trans('product_margin_formula'); ?></p>
                        </div>

                    </div>
                </div>

                <div class="panel panel-default">
                    <div class="panel-heading">
                        <?php _trans('extra_information'); ?>
                    </div>
                    <div class="panel-body">

                        <div class="form-group">
                            <label for="provider_name">
                                <?php _trans('provider_name'); ?>
                            </label>

                            <input type="text" name="provider_name" id="provider_name" class="form-control"
                                   value="<?php echo $this->mdl_products->form_value('provider_name', true); ?>">
                        </div>

                    </div>
                </div>
<?php if (get_setting('sumex') == '1') { ?>

                <div class="panel panel-default">
                    <div class="panel-heading">
                        <?php _trans('invoice_sumex'); ?>
                    </div>
                    <div class="panel-body">

                        <div class="form-group">
                            <label for="product_tariff">
                                <?php _trans('product_tariff'); ?>
                            </label>

                            <input type="text" name="product_tariff" id="product_tariff" class="form-control"
                                   value="<?php echo $this->mdl_products->form_value('product_tariff', true); ?>">
                        </div>

                    </div>
                </div>
<?php } ?>
            </div>
        </div>

    </div>

</form>

<script>
    $(function () {
        const amountFormat = <?php echo json_encode([
            'thousands' => (string) get_setting('thousands_separator'),
            'decimal' => product_decimal_separator(),
            'decimals' => max(2, (int) get_setting('tax_rate_decimal_places')),
            'hideWholeDecimals' => get_setting('decimal_point') === '',
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        function escapePattern(value) {
            return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        function amountInCents(value) {
            value = value.trim();
            if (value === '') {
                return BigInt(0);
            }
            let integerPattern = '[0-9]+';
            if (amountFormat.thousands !== '') {
                integerPattern = '(?:[0-9]+|[0-9]{1,3}(?:' + escapePattern(amountFormat.thousands) + '[0-9]{3})+)';
            }
            const pattern = new RegExp('^' + integerPattern + '(?:' + escapePattern(amountFormat.decimal) + '[0-9]+)?$');
            if (amountFormat.thousands === amountFormat.decimal || !pattern.test(value)) {
                return null;
            }
            if (amountFormat.thousands !== '') {
                value = value.split(amountFormat.thousands).join('');
            }
            const parts = value.split(amountFormat.decimal);
            const integer = parts[0].replace(/^0+/, '') || '0';
            const fraction = parts[1] || '';
            if (integer.length > 18 || fraction.replace(/0+$/, '').length > 2) {
                return null;
            }
            // Integer cents preserve the precision of the database's DECIMAL(20,2) amounts.
            return BigInt(integer + fraction.slice(0, 2).padEnd(2, '0'));
        }

        function formatCents(amount) {
            const negative = amount < BigInt(0);
            const digits = (negative ? -amount : amount).toString().padStart(3, '0');
            const integer = digits.slice(0, -2).replace(/[0-9](?=(?:[0-9]{3})+$)/g, function (digit) {
                return digit + amountFormat.thousands;
            });
            const fraction = digits.slice(-2);
            const formatted = amountFormat.hideWholeDecimals && fraction === '00'
                ? integer
                : integer + amountFormat.decimal + fraction.padEnd(amountFormat.decimals, '0');
            return (negative ? '-' : '') + formatted;
        }

        function updateProductAmounts() {
            const purchase = amountInCents($('#purchase_price').val());
            const shipping = amountInCents($('#product_shipping_cost').val());
            if (purchase === null || shipping === null) {
                $('#product_total_cost, #product_margin').val('');
                return;
            }
            const total = purchase + shipping;
            $('#product_total_cost').val(formatCents(total));
            const salesValue = $('#product_price').val().trim();
            const sales = salesValue === '' ? null : amountInCents(salesValue);
            $('#product_margin').val(sales === null ? '' : formatCents(sales - total));
        }

        $('#purchase_price, #product_price').on('input change', updateProductAmounts);
        $('#product_sku_id').on('change', function () {
            $('#product_shipping_cost').val($(this).find(':selected').attr('data-shipping-cost') || '0');
            updateProductAmounts();
        });
        updateProductAmounts();
    });
</script>
