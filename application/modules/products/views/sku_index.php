<div id="headerbar">
    <h1 class="headerbar-title"><?php _trans('sku_list'); ?></h1>
    <div class="headerbar-item pull-right">
        <a class="btn btn-sm btn-primary" href="<?php echo site_url('products/skus/form'); ?>">
            <i class="fa fa-plus"></i> <?php _trans('sku_create'); ?>
        </a>
    </div>
    <div class="headerbar-item pull-right">
        <?php echo pager(site_url('products/skus/index'), 'mdl_skus'); ?>
    </div>
</div>

<div id="content" class="table-content">
    <?php $this->layout->load_view('layout/alerts'); ?>
    <div class="table-responsive">
        <table class="table table-hover table-striped">
            <thead>
            <tr>
                <th><?php _trans('product_sku'); ?></th>
                <th class="amount"><?php _trans('sku_shipping_per_unit'); ?></th>
                <th><?php _trans('options'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($skus as $sku) { ?>
                <tr>
                    <td><a href="<?php echo site_url('products/skus/form/' . (int) $sku->sku_id); ?>"><?php _htmlsc($sku->sku_code); ?></a></td>
                    <td class="amount"><?php echo format_currency($sku->sku_shipping_cost); ?></td>
                    <td><a class="btn btn-default btn-sm" href="<?php echo site_url('products/skus/form/' . (int) $sku->sku_id); ?>">
                        <i class="fa fa-edit"></i> <?php _trans('edit'); ?>
                    </a></td>
                </tr>
            <?php } ?>
            <?php if ( ! $skus) { ?>
                <tr><td colspan="3"><?php _trans('sku_empty'); ?></td></tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
