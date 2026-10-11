<script>
    $(function () {
        const historySettings = <?php echo json_encode([
            'endpoint' => site_url('invoices/ajax/last_product_price'),
            'invoiceUrl' => site_url('invoices/view'),
            'document' => $price_history_document ?? ['invoice_id' => (int) $invoice_id],
            'currency' => (string) get_setting('currency_symbol'),
            'label' => trans('invoice_last_product_price'),
            'invoiceLabel' => trans('invoice'),
            'empty' => trans('invoice_last_product_price_empty'),
            'loading' => trans('invoice_last_product_price_loading'),
            'error' => trans('invoice_last_product_price_error'),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        function loadLastPrice(row) {
            const productId = row.find('input[name=item_product_id]').val();
            let hint = row.find('.last-product-price');
            if (!productId || !/^[1-9][0-9]*$/.test(productId)) {
                hint.remove();
                return;
            }
            if (!hint.length) {
                hint = $('<p>').addClass('help-block last-product-price').attr('role', 'status');
                row.find('input[name=item_price]').closest('.input-group').after(hint);
            }
            hint.text(historySettings.loading);
            $.getJSON(historySettings.endpoint, $.extend({product_id: productId}, historySettings.document)).done(function (data) {
                if (!document.contains(row[0]) || row.find('input[name=item_product_id]').val() !== productId) {
                    return;
                }
                if (data.success !== 1) {
                    hint.text(historySettings.error);
                } else if (!data.history) {
                    hint.text(historySettings.empty);
                } else {
                    hint.text(historySettings.label + ' ' + data.history.price + ' ' + historySettings.currency + ' — ');
                    hint.append($('<a>').attr('href', historySettings.invoiceUrl + '/' + data.history.invoice_id)
                        .text(historySettings.invoiceLabel + ' ' + data.history.invoice_number));
                    hint.append(document.createTextNode(' (' + data.history.invoice_date + ')'));
                }
            }).fail(function () {
                if (document.contains(row[0]) && row.find('input[name=item_product_id]').val() === productId) {
                    hint.text(historySettings.error);
                }
            });
        }

        $('#item_table').on('change', 'input[name=item_product_id]', function () {
            loadLastPrice($(this).closest('.item'));
        });
        $('#item_table .item').each(function () {
            loadLastPrice($(this));
        });
    });
</script>
