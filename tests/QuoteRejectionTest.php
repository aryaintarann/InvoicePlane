<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QuoteRejectionTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/Support/IntegrationEnvironment.php';
        require_once APPPATH . 'modules/quotes/models/Mdl_quotes.php';
        $GLOBALS['test_application'] = new IntegrationApplication(null);
    }

    #[Test]
    public function it_normalizes_optional_multiline_reasons(): void
    {
        $this->assertSame('', Mdl_quotes::normalize_rejection_reason(null));
        $this->assertSame('', Mdl_quotes::normalize_rejection_reason('  '));
        $this->assertSame("Harga terlalu tinggi\nAnggaran terbatas", Mdl_quotes::normalize_rejection_reason("  Harga terlalu tinggi\r\nAnggaran terbatas  "));
        $this->assertSame(str_repeat('好', 2000), Mdl_quotes::normalize_rejection_reason(str_repeat('好', 2000)));
    }

    #[Test]
    public function it_rejects_invalid_reason_types_encoding_and_excessive_length(): void
    {
        foreach ([['reason'], 123, str_repeat('a', 2001), "invalid\xFF"] as $reason) {
            try {
                Mdl_quotes::normalize_rejection_reason($reason);
                $this->fail('Invalid reason was accepted');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame('quote_rejection_reason_invalid', $exception->getMessage());
            }
        }
    }

    #[Test]
    public function it_encodes_the_rejection_reason_and_preserves_line_breaks(): void
    {
        $path = APPPATH . 'modules/quotes/views/partial_rejection_reason.php';
        $this->assertFileExists($path);
        $quote = (object) ['quote_status_id' => 5, 'quote_rejection_reason' => "<script>alert('x')</script>\nAnggaran terbatas"];
        ob_start();
        include $path;
        $html = ob_get_clean();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('<br', $html);
        $this->assertStringContainsString('Anggaran terbatas', $html);

        $quote->quote_rejection_reason = '0';
        ob_start();
        include $path;
        $this->assertStringContainsString('>0</div>', ob_get_clean());
    }
}
