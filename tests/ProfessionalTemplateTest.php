<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProfessionalTemplateTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/Support/IntegrationEnvironment.php';
        require_once APPPATH . 'helpers/invoice_helper.php';
        require_once APPPATH . 'helpers/client_helper.php';
        $GLOBALS['test_application'] = new IntegrationApplication(null);
        $GLOBALS['test_settings']    = [
            'currency_symbol'       => 'Rp', 'currency_symbol_placement' => 'before',
            'decimal_point'         => ',', 'thousands_separator' => '.', 'tax_rate_decimal_places' => '2',
            'default_item_decimals' => '2', 'date_format' => 'Y-m-d',
        ];
    }

    #[Test]
    public function it_preserves_invoice_and_quote_amounts_from_the_model(): void
    {
        foreach (['invoice', 'quote'] as $type) {
            $html = $this->render($type, $this->document($type));
            foreach (['Rp1.000,00', 'Rp110,00', 'Rp22,00', 'Rp100,00', 'Rp1.032,00'] as $amount) {
                $this->assertStringContainsString($amount, $html);
            }
            if ($type === 'invoice') {
                $this->assertStringContainsString('Rp300,00', $html);
                $this->assertStringContainsString('Rp732,00', $html);
            } else {
                $this->assertStringNotContainsString('Rp732,00', $html);
            }
        }
    }

    #[Test]
    public function it_encodes_company_client_items_notes_and_currency_symbols(): void
    {
        $GLOBALS['test_settings']['currency_symbol'] = '<img src="https://external.example/symbol">';
        foreach (['invoice', 'quote'] as $type) {
            $document                                                    = $this->document($type);
            $document->user_company                                      = '<script>company</script>';
            $document->client_name                                       = '<img src="https://external.example/client">';
            $document->{$type === 'invoice' ? 'invoice_terms' : 'notes'} = '<iframe>notes</iframe>';
            $items                                                       = [(object) ['item_name' => '<script>item</script>', 'item_description' => '<img src="https://external.example/item">', 'item_quantity' => 1, 'item_price' => 1000, 'item_total' => 1000, 'item_discount' => 0]];
            $html                                                        = str_replace("\u{200B}", '', $this->render($type, $document, $items));
            foreach (['<script>', '<img', '<iframe>'] as $unsafe) {
                $this->assertStringNotContainsString($unsafe, $html);
            }
            $this->assertStringContainsString('&lt;script&gt;company&lt;/script&gt;', $html);
            $this->assertStringContainsString('&lt;script&gt;item&lt;/script&gt;', $html);
            $this->assertStringContainsString('&lt;iframe&gt;notes&lt;/iframe&gt;', $html);
        }
    }

    #[Test]
    public function it_keeps_internal_purchase_and_shipping_costs_out_of_customer_documents(): void
    {
        $items = [(object) ['item_name' => 'Service', 'item_description' => '', 'item_quantity' => 1, 'item_price' => 1000, 'item_total' => 1000, 'item_discount' => 0, 'purchase_price' => 987654.32, 'product_shipping_cost' => 765432.10]];
        foreach (['invoice', 'quote'] as $type) {
            $html = $this->render($type, $this->document($type), $items);
            $this->assertStringNotContainsString('987.654,32', $html);
            $this->assertStringNotContainsString('765.432,10', $html);
        }
    }

    #[Test]
    public function it_handles_empty_optional_fields_and_zero_quantity(): void
    {
        $items = [(object) ['item_name' => 'Zero quantity', 'item_quantity' => 0, 'item_price' => 0, 'item_total' => 0, 'item_discount' => 0]];
        foreach (['invoice', 'quote'] as $type) {
            $html = $this->render($type, $this->document($type), $items);
            $this->assertStringContainsString('Zero quantity', $html);
            $this->assertStringContainsString('Rp0,00', $html);
            $this->assertMatchesRegularExpression('/class="quantity"[^>]*>\s*0\s*</', $html);
        }
    }

    #[Test]
    public function it_distinguishes_paid_and_overdue_invoices_with_the_same_template(): void
    {
        $invoice             = $this->document('invoice');
        $invoice->is_overdue = true;
        $this->assertMatchesRegularExpression('/class="document-status"[^>]*>\s*overdue\s*</', $this->render('invoice', $invoice));
        $invoice->invoice_status_id = 4;
        $invoice->invoice_balance   = 0;
        $this->assertMatchesRegularExpression('/class="document-status"[^>]*>\s*paid\s*</', $this->render('invoice', $invoice));
    }

    #[Test]
    public function it_skips_unsafe_missing_and_unsupported_logo_files(): void
    {
        foreach (['../ipconfig.php', 'https://external.example/logo.png', 'missing-logo.png', 'logo.svg'] as $filename) {
            $GLOBALS['test_settings']['invoice_logo'] = $filename;
            foreach (['invoice', 'quote'] as $type) {
                $this->assertStringNotContainsString('<img', $this->render($type, $this->document($type)));
            }
        }
    }

    #[Test]
    public function it_renders_item_discounts_and_negative_tax_amounts(): void
    {
        $items = [(object) ['item_name' => 'Discounted item', 'item_quantity' => 2, 'item_price' => 1000, 'item_discount' => 125, 'item_total' => 1750]];
        foreach (['invoice', 'quote'] as $type) {
            $document                              = $this->document($type);
            $document->{$type . '_item_tax_total'} = -110;
            $html                                  = $this->render($type, $document, $items, true);
            foreach (['Rp125,00', 'Rp1.750,00', 'Rp-110,00'] as $amount) {
                $this->assertStringContainsString($amount, $html);
            }
        }
    }

    #[Test]
    public function it_keeps_footer_text_without_embedding_external_html(): void
    {
        foreach (['invoice', 'quote'] as $type) {
            $GLOBALS['test_settings']['pdf_' . $type . '_footer'] = '<b>Thank you &amp; welcome</b><br><img src="https://external.example/footer"><iframe>Text only</iframe>';
            $html                                                 = $this->render($type, $this->document($type));
            $this->assertStringContainsString('Thank you &amp; welcome', $html);
            $this->assertStringContainsString('Text only', $html);
            $this->assertStringNotContainsString('<img', $html);
            $this->assertStringNotContainsString('<iframe', $html);
        }
    }

    #[Test]
    public function it_preserves_footer_paragraph_boundaries(): void
    {
        foreach (['invoice', 'quote'] as $type) {
            $GLOBALS['test_settings']['pdf_' . $type . '_footer'] = '<p>First paragraph</p><div>Second paragraph</div>';
            $this->assertMatchesRegularExpression('/First paragraph<br\s*\/?>\s*Second paragraph/', $this->render($type, $this->document($type)));
        }
    }

    #[Test]
    public function it_uses_bank_details_from_qr_settings_when_the_company_fields_are_empty(): void
    {
        $GLOBALS['test_settings']['qr_code_iban'] = 'DE02120300000000202051';
        $GLOBALS['test_settings']['qr_code_bic']  = 'BYLADEM1001';
        $html                                     = $this->render('invoice', $this->document('invoice'));
        $this->assertStringContainsString('DE02120300000000202051', $html);
        $this->assertStringContainsString('BYLADEM1001', $html);
        $this->assertStringNotContainsString('DE02120300000000202051', $this->render('quote', $this->document('quote')));
    }

    private function document(string $type): object
    {
        return (object) [
            $type . '_number'          => 'DOC-001', $type . '_status_id' => 2,
            $type . '_date_created'    => '2026-10-11', $type . '_date_due' => '2026-10-25', $type . '_date_expires' => '2026-10-25',
            $type . '_item_subtotal'   => 1000, $type . '_item_tax_total' => 110, $type . '_total' => 1032,
            $type . '_discount_amount' => 100, $type . '_discount_percent' => 0,
            'invoice_paid'             => 300, 'invoice_balance' => 732,
            'client_name'              => 'Client', 'user_name' => 'User', 'user_company' => 'Company & Co',
        ];
    }

    private function render(string $type, object $document, ?array $rows = null, bool $show_item_discounts = false): string
    {
        $path = dirname(__DIR__) . '/templates/' . $type . '_templates/pdf/Professional.php';
        $this->assertFileExists($path);
        $invoice            = $quote = $document;
        $items              = $rows ?? [(object) ['item_name' => 'Service', 'item_description' => 'Description', 'item_quantity' => 1, 'item_price' => 1000, 'item_total' => 1000, 'item_discount' => 0]];
        $legacy_calculation = false;
        $payment_method     = false;
        $invoice_tax_rates  = [(object) ['invoice_tax_rate_name' => 'Tax', 'invoice_tax_rate_percent' => 2, 'invoice_tax_rate_amount' => 22]];
        $quote_tax_rates    = [(object) ['quote_tax_rate_name' => 'Tax', 'quote_tax_rate_percent' => 2, 'quote_tax_rate_amount' => 22]];
        ob_start();
        try {
            include $path;

            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
