<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QuoteRejectionIntegrationTest extends TestCase
{
    private $db;

    private $quotes;

    public static function setUpBeforeClass(): void
    {
        if ( ! getenv('IP_TEST_DB')) {
            return;
        }
        require_once __DIR__ . '/Support/IntegrationEnvironment.php';
        $pdo = new PDO('mysql:host=' . (getenv('IP_TEST_DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('IP_TEST_DB_PORT') ?: '33317'), getenv('IP_TEST_DB_USER') ?: 'root', getenv('IP_TEST_DB_PASSWORD') ?: 'invoiceplane_test');
        $pdo->exec('CREATE DATABASE IF NOT EXISTS invoiceplane_whatsapp_test');
        $GLOBALS['test_application'] = new IntegrationApplication(integration_database());
        $GLOBALS['test_application']->load->helper('directory');
        require_once APPPATH . 'modules/setup/models/Mdl_setup.php';
        $setup = new Mdl_setup();
        if ( ! $GLOBALS['test_application']->db->table_exists('ip_versions')) {
            $setup->install_tables();
        }
        if ( ! $setup->upgrade_tables()) {
            throw new RuntimeException(implode('; ', $setup->errors));
        }
        require_once APPPATH . 'modules/quotes/models/Mdl_quotes.php';
    }

    protected function setUp(): void
    {
        if ( ! getenv('IP_TEST_DB')) {
            $this->markTestSkipped('Requires the disposable MariaDB test database.');
        }
        $this->db                    = integration_database();
        $GLOBALS['test_application'] = new IntegrationApplication($this->db);
        $this->quotes                = new Mdl_quotes();
        $this->db->where_in('quote_id', [3001, 3002])->delete('ip_quotes');
        foreach ([3001 => 2, 3002 => 3] as $id => $status) {
            $this->db->insert('ip_quotes', ['quote_id' => $id, 'client_id' => 1, 'user_id' => 1, 'invoice_group_id' => 1, 'quote_status_id' => $status, 'quote_url_key' => 'test-quote-' . $id, 'notes' => 'Existing quote notes']);
        }
    }

    #[Test]
    public function it_saves_status_and_reason_together_for_both_guest_entry_points(): void
    {
        $this->assertTrue($this->quotes->reject_quote_by_id(3001, 'Harga di luar anggaran'));
        $this->assertTrue($this->quotes->reject_quote_by_key('test-quote-3002', "Waktu tidak sesuai\nTerima kasih"));
        $one = $this->row(3001);
        $two = $this->row(3002);
        $this->assertSame('5', (string) $one->quote_status_id);
        $this->assertSame('Harga di luar anggaran', $one->quote_rejection_reason);
        $this->assertSame("Waktu tidak sesuai\nTerima kasih", $two->quote_rejection_reason);
        $this->assertSame('Existing quote notes', $one->notes);
    }

    #[Test]
    public function it_preserves_four_byte_unicode_in_rejection_reasons(): void
    {
        $reason = "Anggaran belum disetujui \u{1F641}";
        $this->assertTrue($this->quotes->reject_quote_by_id(3001, $reason));
        $this->assertSame($reason, $this->row(3001)->quote_rejection_reason);
    }

    #[Test]
    public function it_leaves_a_previous_decision_unchanged_on_duplicate_rejection(): void
    {
        $this->assertTrue($this->quotes->reject_quote_by_id(3001, 'Original reason'));
        $this->assertFalse($this->quotes->reject_quote_by_id(3001, 'Replacement'));
        $this->assertSame('Original reason', $this->row(3001)->quote_rejection_reason);
        $this->db->where('quote_id', 3002)->update('ip_quotes', ['quote_status_id' => 4]);
        $this->assertFalse($this->quotes->reject_quote_by_key('test-quote-3002', 'Rejected too late'));
        $this->assertSame('4', (string) $this->row(3002)->quote_status_id);
    }

    #[Test]
    public function it_saves_edits_and_clears_the_reason_when_admin_reopens_a_quote(): void
    {
        $this->quotes->save(3001, ['quote_status_id' => 5, 'quote_rejection_reason' => '  Anggaran terbatas  ']);
        $this->assertSame('Anggaran terbatas', $this->row(3001)->quote_rejection_reason);
        $this->quotes->save(3001, ['notes' => 'Edited notes']);
        $this->assertSame('Anggaran terbatas', $this->row(3001)->quote_rejection_reason);
        $this->quotes->save(3001, ['quote_status_id' => 2]);
        $this->assertNull($this->row(3001)->quote_rejection_reason);
    }

    #[Test]
    public function it_does_not_change_a_quote_for_an_invalid_reason(): void
    {
        try {
            $this->quotes->reject_quote_by_id(3001, str_repeat('a', 2001));
            $this->fail('Oversized reason accepted');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('2', (string) $this->row(3001)->quote_status_id);
            $this->assertNull($this->row(3001)->quote_rejection_reason);
        }
    }

    #[Test]
    public function it_allows_rejecting_without_a_reason_and_applies_the_upgrade_once(): void
    {
        $this->assertTrue($this->quotes->reject_quote_by_id(3001));
        $this->assertSame('', $this->row(3001)->quote_rejection_reason);
        $setup = new Mdl_setup();
        $this->assertTrue($setup->upgrade_tables());
        $this->assertSame(1, $this->db->where('version_file', '048_1.7.7.sql')->count_all_results('ip_versions'));
    }

    private function row(int $id): object
    {
        return $this->db->where('quote_id', $id)->get('ip_quotes')->row();
    }
}
