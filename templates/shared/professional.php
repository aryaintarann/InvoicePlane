<?php

defined('BASEPATH') || exit('No direct script access allowed');

$is_invoice    = $document_type === 'invoice';
$document      = $is_invoice ? $invoice : $quote;
$value         = static fn (string $name) => $document->{$document_type . '_' . $name} ?? '';
$money         = static fn ($amount): string => htmlsc(html_entity_decode(format_currency($amount), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
$breakable     = static fn ($text): string => htmlsc(preg_replace('/(\S{20})(?=\S)/u', '$1' . "\u{200B}", (string) $text) ?? (string) $text);
$company       = trim((string) ($document->user_company ?? '')) ?: (string) ($document->user_name ?? '');
$status_labels = $is_invoice
    ? [1 => 'draft', 2 => 'sent', 3 => 'viewed', 4 => 'paid']
    : [1 => 'draft', 2 => 'sent', 3 => 'viewed', 4 => 'approved', 5 => 'rejected', 6 => 'canceled'];
$status = $status_labels[(int) $value('status_id')] ?? '';
if ($is_invoice && ! empty($document->is_overdue) && (float) $value('balance') > 0) {
    $status = 'overdue';
}
$addresses = static function (string $prefix) use ($document): array {
    $lines   = [$document->{$prefix . '_address_1'} ?? '', $document->{$prefix . '_address_2'} ?? ''];
    $lines[] = trim(implode(' ', array_filter([
        $document->{$prefix . '_city'} ?? '', $document->{$prefix . '_state'} ?? '', $document->{$prefix . '_zip'} ?? '',
    ], static fn ($part) => (string) $part !== '')));
    if ( ! empty($document->{$prefix . '_country'})) {
        $lines[] = get_country_name(trans('cldr'), $document->{$prefix . '_country'});
    }

    return array_filter($lines, static fn ($line) => (string) $line !== '');
};
$logo          = '';
$logo_filename = (string) get_setting('invoice_logo');
if ($logo_filename !== '') {
    $ci = &get_instance();
    $ci->load->helper('file_security');
    $uploads = dirname(rtrim(APPPATH, '/\\')) . '/uploads';
    if (validate_safe_filename($logo_filename)['valid']
        && validate_file_in_directory($uploads . '/' . $logo_filename, $uploads)
        && in_array(strtolower(pathinfo($logo_filename, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'gif', 'bmp', 'webp'], true)) {
        $logo = invoice_logo_pdf();
    }
}
$discount = '';
if ((float) $value('discount_percent') != 0) {
    $discount = htmlsc((string) format_amount($value('discount_percent'))) . '%';
} elseif ((float) $value('discount_amount') != 0) {
    $discount = $money($value('discount_amount'));
}
$summary = [];
if ( ! $legacy_calculation && $discount !== '') {
    $summary[] = ['label' => trans('discount'), 'amount' => $discount];
}
$summary[] = ['label' => trans('subtotal'), 'amount' => $money($value('item_subtotal'))];
if ((float) $value('item_tax_total') != 0) {
    $summary[] = ['label' => trans('item_tax'), 'amount' => $money($value('item_tax_total'))];
}
foreach (($is_invoice ? $invoice_tax_rates : $quote_tax_rates) as $tax_rate) {
    $summary[] = [
        'label'  => $tax_rate->{$document_type . '_tax_rate_name'} . ' (' . format_amount($tax_rate->{$document_type . '_tax_rate_percent'}) . '%)',
        'amount' => $money($tax_rate->{$document_type . '_tax_rate_amount'}),
    ];
}
if ($legacy_calculation && $discount !== '') {
    $summary[] = ['label' => trans('discount'), 'amount' => $discount];
}
$summary[] = ['label' => trans('total'), 'amount' => $money($value('total')), 'class' => $is_invoice ? 'total' : 'balance'];
if ($is_invoice) {
    $summary[] = ['label' => trans('paid'), 'amount' => $money($value('paid'))];
    $summary[] = ['label' => trans('amount_due'), 'amount' => $money($value('balance')), 'class' => 'balance'];
}
$footer      = (string) get_setting($is_invoice ? 'pdf_invoice_footer' : 'pdf_quote_footer');
$footer      = preg_replace('/<br\s*\/?\s*>|<\/(?:p|div)\s*>/i', "\n", $footer);
$footer      = trim(html_entity_decode(strip_tags($footer), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
$notes       = (string) ($is_invoice ? ($document->invoice_terms ?? '') : ($document->notes ?? ''));
$show_qrcode = $is_invoice && ! empty($document->invoice_id) && (float) $value('balance') > 0 && (float) $value('balance') < 10e9 && get_setting('qr_code');
$bank_iban   = (string) (($document->user_iban ?? '') ?: get_setting('qr_code_iban'));
$bank_bic    = (string) (($document->user_bic ?? '') ?: get_setting('qr_code_bic'));
?><!DOCTYPE html>
<html lang="<?php echo htmlsc(trans('cldr')); ?>">
<head>
    <meta charset="utf-8">
    <title><?php echo htmlsc(trans($document_type) . ' ' . $value('number')); ?></title>
    <style>
        @page { sheet-size: A4; size: auto; margin-top: 18mm; margin-right: 18mm; margin-bottom: 25mm; margin-left: 18mm; margin-footer: 8mm; footer: html_professionalFooter; }
        body { font-family: dejavusans, sans-serif; font-size: 9pt; color: #161616; line-height: 1.45; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        .header { border-bottom: 2px solid #161616; margin-bottom: 8mm; }
        .header td { padding: 0 0 6mm; }
        .issuer { width: 61%; padding-right: 8mm; }
        .logo { margin-bottom: 3mm; }
        #invoice-logo { max-width: 42mm; max-height: 18mm; }
        .company-name { font-size: 13pt; font-weight: bold; margin-bottom: 2mm; }
        .contact { color: #555555; font-size: 8pt; margin-top: 2mm; }
        .document-heading { width: 39%; text-align: right; }
        .document-title { font-size: 25pt; font-weight: bold; text-transform: uppercase; }
        .document-number { font-size: 10pt; margin-top: 2mm; }
        .document-status { font-size: 8pt; font-weight: bold; margin-top: 3mm; text-transform: uppercase; }
        .parties { margin-bottom: 8mm; }
        .recipient { width: 54%; padding-right: 8mm; }
        .section-label { font-size: 8pt; font-weight: bold; margin-bottom: 2mm; }
        .client-name { font-size: 10pt; font-weight: bold; margin-bottom: 1mm; }
        .metadata { width: 46%; }
        .metadata td { padding: 0 0 2mm; }
        .metadata .label { color: #555555; width: 51%; }
        .metadata .value { text-align: right; }
        .items { margin-bottom: 4mm; }
        .items th { font-size: 8pt; font-weight: bold; text-align: left; padding: 2.5mm 1.5mm; border-top: 1px solid #161616; border-bottom: 1px solid #161616; }
        .items td { padding: 3mm 1.5mm; border-bottom: 1px solid #d6d6d6; }
        .items .numeric { text-align: right; }
        .item-name { font-weight: bold; }
        .description { font-size: 8pt; color: #555555; margin-top: 1mm; }
        .unit { font-size: 7.5pt; color: #555555; }
        .summary { page-break-inside: avoid; margin-bottom: 6mm; }
        .summary .spacer { width: 49%; }
        .summary .totals { width: 51%; }
        .totals td { padding: 1.6mm 1.5mm; }
        .totals .amount { text-align: right; width: 45%; }
        .totals .total td { font-weight: bold; border-top: 1px solid #d6d6d6; }
        .totals .balance td { font-size: 12pt; font-weight: bold; border-top: 2px solid #161616; padding-top: 3mm; }
        .notes { margin-top: 5mm; }
        .notes-content { font-size: 8.5pt; line-height: 1.6; }
        .payment-details { margin-top: 5mm; page-break-inside: avoid; }
        .payment-details td { font-size: 8pt; }
        .qr { width: 25%; text-align: right; }
        .footer { font-size: 7.5pt; color: #555555; border-top: 1px solid #d6d6d6; padding-top: 2mm; }
        .footer td { padding-top: 1mm; }
        .footer .page-number { width: 25%; text-align: right; }
    </style>
</head>
<body>
<htmlpagefooter name="professionalFooter">
    <div class="footer">
        <?php if (trim($footer) !== '') { ?><div><?php echo nl2br(htmlsc($footer)); ?></div><?php } ?>
        <table>
            <tr>
                <td><?php echo htmlsc($company . ' | ' . trans($document_type) . ' ' . $value('number')); ?></td>
                <td class="page-number"><?php echo htmlsc(trans('page')); ?> {PAGENO} / {nbpg}</td>
            </tr>
        </table>
    </div>
</htmlpagefooter>
<sethtmlpagefooter name="professionalFooter" value="on" page="ALL" />

<table class="header">
    <tr>
        <td class="issuer">
            <?php if ($logo !== '') { ?><div class="logo"><?php echo $logo; ?></div><?php } ?>
            <div class="company-name"><?php echo $breakable($company); ?></div>
            <?php foreach ($addresses('user') as $line) { ?><div><?php echo $breakable($line); ?></div><?php } ?>
            <div class="contact">
                <?php echo htmlsc(implode(' | ', array_filter([$document->user_phone ?? '', $document->user_email ?? '', $document->user_web ?? '']))); ?>
            </div>
            <?php foreach (['vat_id' => 'vat_id_short', 'tax_code' => 'tax_code_short'] as $field => $label) {
                if ( ! empty($document->{'user_' . $field})) { ?>
                    <div class="contact"><?php echo htmlsc(trans($label) . ': ' . $document->{'user_' . $field}); ?></div>
                <?php }
                } ?>
        </td>
        <td class="document-heading">
            <div class="document-title"><?php echo htmlsc(trans($document_type)); ?></div>
            <div class="document-number"><?php echo $breakable($value('number')); ?></div>
            <?php if ($status !== '') { ?><div class="document-status"><?php echo htmlsc(trans($status)); ?></div><?php } ?>
        </td>
    </tr>
</table>

<table class="parties">
    <tr>
        <td class="recipient">
            <div class="section-label"><?php echo htmlsc(trans($is_invoice ? 'bill_to' : 'client')); ?></div>
            <div class="client-name"><?php echo $breakable(format_client($document)); ?></div>
            <?php foreach ($addresses('client') as $line) { ?><div><?php echo $breakable($line); ?></div><?php } ?>
            <?php foreach (['vat_id' => 'vat_id_short', 'tax_code' => 'tax_code_short'] as $field => $label) {
                if ( ! empty($document->{'client_' . $field})) { ?>
                    <div class="contact"><?php echo htmlsc(trans($label) . ': ' . $document->{'client_' . $field}); ?></div>
                <?php }
                } ?>
            <?php if ( ! empty($document->client_email)) { ?><div class="contact"><?php echo htmlsc($document->client_email); ?></div><?php } ?>
        </td>
        <td class="metadata">
            <table>
                <tr>
                    <td class="label"><?php echo htmlsc(trans($is_invoice ? 'invoice_date' : 'quote_date')); ?></td>
                    <td class="value"><?php echo htmlsc(date_from_mysql($value('date_created'), true)); ?></td>
                </tr>
                <tr>
                    <td class="label"><?php echo htmlsc(trans($is_invoice ? 'due_date' : 'expires')); ?></td>
                    <td class="value"><?php echo htmlsc(date_from_mysql($value($is_invoice ? 'date_due' : 'date_expires'), true)); ?></td>
                </tr>
                <?php if ($is_invoice && ! empty($payment_method)) { ?>
                    <tr>
                        <td class="label"><?php echo htmlsc(trans('payment_method')); ?></td>
                        <td class="value"><?php echo htmlsc($payment_method->payment_method_name); ?></td>
                    </tr>
                <?php } ?>
            </table>
        </td>
    </tr>
</table>

<table class="items">
    <thead>
    <tr>
        <th style="width: <?php echo $show_item_discounts ? '34%' : '45%'; ?>"><?php echo htmlsc(trans('description')); ?></th>
        <th class="numeric" style="width: <?php echo $show_item_discounts ? '8%' : '9%'; ?>"><?php echo htmlsc(trans('qty')); ?></th>
        <th class="numeric" style="width: <?php echo $show_item_discounts ? '20%' : '23%'; ?>"><?php echo htmlsc(trans('price')); ?></th>
        <?php if ($show_item_discounts) { ?><th class="numeric" style="width: 18%"><?php echo htmlsc(trans('discount')); ?></th><?php } ?>
        <th class="numeric" style="width: <?php echo $show_item_discounts ? '20%' : '23%'; ?>"><?php echo htmlsc(trans('total')); ?></th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $item) { ?>
        <tr>
            <td>
                <div class="item-name"><?php echo $breakable($item->item_name); ?></div>
                <?php if ( ! empty($item->item_description)) { ?><div class="description"><?php echo nl2br($breakable($item->item_description)); ?></div><?php } ?>
            </td>
            <td class="numeric">
                <div class="quantity"><?php echo htmlsc((string) (format_quantity($item->item_quantity) ?? '0')); ?></div>
                <?php if ( ! empty($item->item_product_unit)) { ?><div class="unit"><?php echo htmlsc($item->item_product_unit); ?></div><?php } ?>
            </td>
            <td class="numeric"><?php echo $money($item->item_price); ?></td>
            <?php if ($show_item_discounts) { ?><td class="numeric"><?php echo $money($item->item_discount); ?></td><?php } ?>
            <td class="numeric"><?php echo $money($item->item_total); ?></td>
        </tr>
    <?php } ?>
    </tbody>
</table>

<table class="summary">
    <tr>
        <td class="spacer"></td>
        <td class="totals">
            <table>
                <?php foreach ($summary as $row) { ?>
                    <tr class="<?php echo $row['class'] ?? ''; ?>">
                        <td><?php echo htmlsc($row['label']); ?></td>
                        <td class="amount"><?php echo $row['amount']; ?></td>
                    </tr>
                <?php } ?>
            </table>
        </td>
    </tr>
</table>

<?php if ($notes !== '') { ?>
    <div class="notes">
        <div class="section-label"><?php echo htmlsc(trans($is_invoice ? 'terms' : 'notes')); ?></div>
        <div class="notes-content"><?php echo nl2br(htmlsc($notes)); ?></div>
    </div>
<?php } ?>

<?php if ($is_invoice && ( ! empty($document->user_bank) || $bank_iban !== '' || $bank_bic !== '' || $show_qrcode)) { ?>
    <table class="payment-details">
        <tr>
            <td>
                <div class="section-label"><?php echo htmlsc(trans('bank_information')); ?></div>
                <?php if ( ! empty($document->user_bank)) { ?><div><?php echo htmlsc($document->user_bank); ?></div><?php } ?>
                <?php if ($bank_iban !== '') { ?><div><?php echo htmlsc(trans('qr_code_settings_iban') . ': ' . $bank_iban); ?></div><?php } ?>
                <?php if ($bank_bic !== '') { ?><div><?php echo htmlsc(trans('qr_code_settings_bic') . ': ' . $bank_bic); ?></div><?php } ?>
            </td>
            <?php if ($show_qrcode) { ?><td class="qr"><?php echo invoice_qrcode((int) $document->invoice_id, 80); ?></td><?php } ?>
        </tr>
    </table>
<?php } ?>

</body>
</html>
