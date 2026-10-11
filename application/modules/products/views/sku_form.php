<form method="post">

    <?php _csrf_field(); ?>

    <div id="headerbar">
        <h1 class="headerbar-title"><?php _trans($this->mdl_skus->id ? 'sku_edit' : 'sku_create'); ?></h1>
        <?php $this->layout->load_view('layout/header_buttons'); ?>
    </div>

    <div id="content">
        <div class="row">
            <div class="col-xs-12 col-md-6 col-md-offset-3">
                <?php $this->layout->load_view('layout/alerts'); ?>

                <div class="form-group">
                    <label for="sku_code"><?php _trans('product_sku'); ?></label>
                    <input type="text" name="sku_code" id="sku_code" class="form-control" maxlength="191" required
                           value="<?php echo htmlsc(is_scalar($this->mdl_skus->form_value('sku_code')) ? $this->mdl_skus->form_value('sku_code') : ''); ?>">
                </div>

                <div class="form-group">
                    <label for="sku_shipping_cost"><?php _trans('sku_shipping_per_unit'); ?></label>
                    <div class="input-group">
                        <input type="text" name="sku_shipping_cost" id="sku_shipping_cost" class="form-control"
                               value="<?php echo htmlsc($this->mdl_skus->form_shipping_cost()); ?>">
                        <span class="input-group-addon"><?php echo htmlsc(get_setting('currency_symbol')); ?></span>
                    </div>
                    <p class="help-block"><?php _trans('sku_shipping_shared'); ?></p>
                </div>
            </div>
        </div>
    </div>

</form>
