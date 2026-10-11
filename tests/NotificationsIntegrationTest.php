<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NotificationsIntegrationTest extends TestCase
{
    private $db;

    private $queue;

    public static function setUpBeforeClass(): void
    {
        if ( ! getenv('IP_TEST_DB')) {
            return;
        }
        require_once __DIR__ . '/Support/IntegrationEnvironment.php';
        $pdo = new PDO('mysql:host=' . (getenv('IP_TEST_DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('IP_TEST_DB_PORT') ?: '33317'), getenv('IP_TEST_DB_USER') ?: 'root', getenv('IP_TEST_DB_PASSWORD') ?: 'invoiceplane_test');
        $pdo->exec('CREATE DATABASE IF NOT EXISTS invoiceplane_whatsapp_test');
        $pdo->exec('USE invoiceplane_whatsapp_test');
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $pdo->exec('DROP TABLE `' . str_replace('`', '``', $table) . '`');
        }
        $GLOBALS['test_application'] = new IntegrationApplication(integration_database());
        $GLOBALS['test_application']->load->helper('directory');
        require_once APPPATH . 'modules/setup/models/Mdl_setup.php';
        $setup = new Mdl_setup();
        if ( ! $setup->install_tables() || ! $setup->upgrade_tables()) {
            throw new RuntimeException(implode('; ', $setup->errors));
        }
        require_once APPPATH . 'modules/notifications/models/Mdl_notifications.php';
    }

    protected function setUp(): void
    {
        if ( ! getenv('IP_TEST_DB')) {
            $this->markTestSkipped('Set IP_TEST_DB=1 to run against a disposable MySQL/MariaDB test database.');
        }
        $this->db                    = integration_database();
        $GLOBALS['test_application'] = new IntegrationApplication($this->db);
        $GLOBALS['test_settings']    = array_replace(Whatsapp_policy::DEFAULTS, ['whatsapp_issued_enabled' => '1', 'whatsapp_payment_enabled' => '1', 'whatsapp_reminder_enabled' => '1', 'date_format' => 'Y-m-d', 'invoices_due_after' => '30']);
        $GLOBALS['test_settings'] += ['currency_symbol' => 'Rp', 'currency_symbol_placement' => 'before', 'decimal_point' => '.', 'thousands_separator' => ',', 'tax_rate_decimal_places' => '2'];
        $this->queue = new Mdl_notifications();
        foreach (['ip_notifications', 'ip_payments', 'ip_invoice_amounts', 'ip_invoice_item_amounts', 'ip_invoice_items', 'ip_invoice_groups', 'ip_users', 'ip_invoices_recurring', 'ip_invoices', 'ip_clients'] as $table) {
            $this->db->query('DELETE FROM ' . $table);
        }
        $this->db->insert('ip_clients', ['client_id' => 1, 'client_name' => 'Internal', 'client_mobile' => '081234567890', 'client_whatsapp_consent' => 1]);
        $this->db->insert('ip_invoices', ['invoice_id' => 1, 'client_id' => 1, 'user_id' => 1, 'invoice_group_id' => 1, 'invoice_status_id' => 2, 'invoice_date_due' => '2026-10-14', 'invoice_number' => 'INV-001', 'invoice_url_key' => 'private-link']);
        $this->db->insert('ip_invoice_amounts', ['invoice_id' => 1, 'invoice_total' => 100, 'invoice_balance' => 100]);
    }

    #[Test]
    public function it_deduplicates_issuance_and_claims_each_job_once_across_connections(): void
    {
        $this->queue->invoice_issued(1);
        $this->queue->invoice_issued(1);
        $jobs = $this->queue->history(1);
        $this->assertCount(1, $jobs);
        $now = new DateTimeImmutable('+1 minute', new DateTimeZone('UTC'));
        $this->assertTrue($this->queue->claim((int) $jobs[0]->notification_id, $now));
        $GLOBALS['test_application']->db = integration_database();
        $this->assertFalse((new Mdl_notifications())->claim((int) $jobs[0]->notification_id, $now));
    }

    #[Test]
    public function it_preserves_skipped_reasons_for_missing_phone_and_revoked_consent(): void
    {
        $this->db->where('client_id', 1)->update('ip_clients', ['client_mobile' => '']);
        $this->queue->invoice_issued(1);
        $this->assertSame('phone_missing', $this->queue->history(1)[0]->failure_reason);
        $this->db->where('client_id', 1)->update('ip_clients', ['client_mobile' => '081234567890']);
        $job = $this->queue->history(1)[0];
        $this->assertTrue($this->queue->retry((int) $job->notification_id, 1, 1));
        $this->db->where('client_id', 1)->update('ip_clients', ['client_whatsapp_consent' => 0]);
        $this->queue->process($this->adapter(), new DateTimeImmutable('+1 minute'));
        $this->assertSame('consent_missing', $this->queue->history(1)[0]->failure_reason);
    }

    #[Test]
    public function it_records_provider_acceptance_and_never_retries_it(): void
    {
        $this->queue->invoice_issued(1);
        $this->queue->process($this->adapter(new Response(200, [], '{"status":true,"id":["id-1"]}')), new DateTimeImmutable('+1 minute'));
        $job = $this->queue->history(1)[0];
        $this->assertSame('accepted', $job->status);
        $this->assertSame('id-1', $job->provider_id);
        $this->assertFalse($this->queue->retry((int) $job->notification_id, 1, 1));
    }

    #[Test]
    public function it_rechecks_reminders_and_skips_previous_local_dates(): void
    {
        $now = new DateTimeImmutable('2026-10-11 02:05:00Z');
        $this->queue->schedule_reminders($now);
        $this->queue->schedule_reminders($now);
        $this->assertCount(1, $this->queue->history(1));
        $this->db->where('invoice_id', 1)->update('ip_invoice_amounts', ['invoice_balance' => 0]);
        $this->queue->process($this->adapter(), $now);
        $this->assertSame('invoice_not_open', $this->queue->history(1)[0]->failure_reason);
        $this->db->where('invoice_id', 1)->update('ip_invoice_amounts', ['invoice_balance' => 100]);
        $this->queue->retry((int) $this->queue->history(1)[0]->notification_id, 1, 1, $now);
        $this->queue->process($this->adapter(), $now->modify('+1 day'));
        $this->assertSame('reminder_expired', $this->queue->history(1)[0]->failure_reason);
    }

    #[Test]
    public function it_requires_review_after_a_worker_lease_expires(): void
    {
        $this->queue->invoice_issued(1);
        $now = new DateTimeImmutable('+1 minute');
        $this->queue->claim((int) $this->queue->history(1)[0]->notification_id, $now);
        $this->queue->process($this->adapter(), $now->modify('+6 minutes'));
        $this->assertSame('uncertain', $this->queue->history(1)[0]->status);
    }

    #[Test]
    public function it_enqueues_only_new_successful_manual_and_gateway_payments(): void
    {
        $this->db->insert('ip_users', ['user_id' => 1, 'user_name' => 'Admin']);
        $this->db->insert('ip_invoice_items', ['item_id' => 100, 'invoice_id' => 1, 'item_name' => 'Service', 'item_quantity' => 1, 'item_price' => 100]);
        $this->db->insert('ip_invoice_item_amounts', ['item_id' => 100, 'item_subtotal' => 100, 'item_tax_total' => 0, 'item_discount' => 0]);
        $GLOBALS['test_application']->load->model('payments/mdl_payments');
        $model   = $GLOBALS['test_application']->mdl_payments;
        $payment = ['invoice_id' => 1, 'payment_date' => '2026-10-11', 'payment_amount' => 25, 'payment_method_id' => 0];
        $id      = $model->save(null, $payment);
        $this->assertGreaterThan(0, $id);
        $this->assertCount(1, $this->queue->history(1));
        $this->assertSame('75.00', $this->queue->history(1)[0]->payment_balance);
        $model->save($id, $payment);
        $this->assertCount(1, $this->queue->history(1));
        $this->assertTrue($model->record_external_payment($payment + ['payment_external_id' => 'gateway-1']));
        $this->assertCount(2, $this->queue->history(1));
        $this->assertSame('50.00', $this->queue->history(1)[0]->payment_balance);
        $this->assertFalse($model->record_external_payment($payment + ['payment_external_id' => 'gateway-1']));
        $this->assertCount(2, $this->queue->history(1));
        $this->assertFalse($model->record_external_payment(array_replace($payment, ['payment_amount' => 1000, 'payment_external_id' => 'gateway-oversize'])));
        $this->assertCount(2, $this->queue->history(1));
    }

    #[Test]
    public function it_uses_three_retry_delays_and_requires_review_for_uncertain_results(): void
    {
        $this->queue->invoice_issued(1);
        $now = new DateTimeImmutable('+1 minute', new DateTimeZone('UTC'));
        foreach ([60, 300, 900] as $delay) {
            $this->queue->process($this->adapter(new Response(200, [], '{"status":false}')), $now);
            $job = $this->queue->history(1)[0];
            $this->assertSame('queued', $job->status);
            $this->assertSame($now->modify('+' . $delay . ' seconds')->format('Y-m-d H:i:s'), $job->scheduled_at);
            $now = $now->modify('+' . $delay . ' seconds');
        }
        $this->queue->process($this->adapter(new Response(200, [], '{"status":false}')), $now);
        $this->assertSame('failed', $this->queue->history(1)[0]->status);
        $this->assertSame('4', (string) $this->queue->history(1)[0]->total_attempts);
        $this->queue->retry((int) $job->notification_id, 1, 1, $now);
        $this->queue->process($this->adapter(new Response(500)), $now);
        $this->assertSame('uncertain', $this->queue->history(1)[0]->status);
        $this->queue->process($this->adapter(), $now->modify('+1 hour'));
        $this->assertSame('5', (string) $this->queue->history(1)[0]->total_attempts);
    }

    #[Test]
    public function it_skips_changed_due_dates_and_never_backfills_disabled_issuance(): void
    {
        $now = new DateTimeImmutable('2026-10-11 02:05:00Z');
        $this->queue->schedule_reminders($now);
        $this->db->where('invoice_id', 1)->update('ip_invoices', ['invoice_date_due' => '2026-10-15']);
        $this->queue->process($this->adapter(), $now);
        $this->assertSame('reminder_schedule_changed', $this->queue->history(1)[0]->failure_reason);
        $GLOBALS['test_settings']['whatsapp_issued_enabled'] = '0';
        $this->queue->invoice_issued(1);
        $GLOBALS['test_settings']['whatsapp_issued_enabled'] = '1';
        $this->queue->invoice_issued(1);
        $this->assertCount(1, $this->queue->history(1));
        $this->assertNotNull($this->db->get_where('ip_invoices', ['invoice_id' => 1])->row()->invoice_whatsapp_issued_at);
    }

    #[Test]
    public function it_rolls_back_failed_payment_writes_without_notifications(): void
    {
        $this->payment_fixture();
        $this->db->query("CREATE TRIGGER wa_test_refuse BEFORE INSERT ON ip_payments FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Test refusal'");
        try {
            $model   = $GLOBALS['test_application']->mdl_payments;
            $payment = ['invoice_id' => 1, 'payment_date' => '2026-10-11', 'payment_amount' => 25, 'payment_method_id' => 0];
            $this->assertFalse($model->save(null, $payment));
            $this->assertCount(0, $this->queue->history(1));
            $this->assertCount(0, $this->db->get('ip_payments')->result());
            $this->assertSame('100.00', $this->db->get_where('ip_invoice_amounts', ['invoice_id' => 1])->row()->invoice_balance);
        } finally {
            $this->db->query('DROP TRIGGER wa_test_refuse');
        }
    }

    #[Test]
    public function it_processes_the_authenticated_cron_without_recurring_invoices(): void
    {
        require_once APPPATH . 'modules/invoices/controllers/Cron.php';
        $GLOBALS['test_settings']['cron_key'] = 'test-key';
        $this->queue->invoice_issued(1);
        (new Cron())->recur('test-key');
        $this->assertSame('token_missing', $this->queue->history(1)[0]->failure_reason);
        $this->assertSame('1', (string) $this->queue->history(1)[0]->total_attempts);
    }

    #[Test]
    public function it_publishes_recurring_whatsapp_invoices_without_smtp_and_only_once(): void
    {
        require_once APPPATH . 'modules/invoices/controllers/Cron.php';
        $this->payment_fixture();
        require_once APPPATH . 'helpers/date_helper.php';
        $GLOBALS['test_settings'] += ['cron_key' => 'test-key', 'automatic_email_on_recur' => '1', 'email_send_method' => 'smtp'];
        $GLOBALS['test_settings']['whatsapp_automatic_on_recur'] = '1';
        $this->db->insert('ip_invoices_recurring', ['invoice_id' => 1, 'recur_start_date' => date('Y-m-d'), 'recur_next_date' => date('Y-m-d'), 'recur_frequency' => '1M']);
        (new Cron())->recur('test-key');
        $invoices = $this->db->order_by('invoice_id')->get('ip_invoices')->result();
        $this->assertCount(2, $invoices);
        $target = $invoices[1];
        $this->assertSame('2', (string) $target->invoice_status_id);
        $this->assertCount(1, $this->queue->history((int) $target->invoice_id));
        $this->assertSame('100.00', $this->db->get_where('ip_invoice_amounts', ['invoice_id' => $target->invoice_id])->row()->invoice_total);
        (new Cron())->recur('test-key');
        $this->assertCount(2, $this->db->get('ip_invoices')->result());
    }

    #[Test]
    public function it_marks_existing_publications_during_upgrade_without_queuing_old_events(): void
    {
        $this->db->insert('ip_invoices', ['invoice_id' => 2, 'client_id' => 1, 'user_id' => 1, 'invoice_group_id' => 1, 'invoice_status_id' => 1]);
        $this->db->query('DROP TABLE ip_notifications');
        $this->db->query('ALTER TABLE ip_clients DROP COLUMN client_whatsapp_consent');
        $this->db->query('ALTER TABLE ip_invoices DROP COLUMN invoice_whatsapp_issued_at');
        foreach (split_sql_statements(file_get_contents(APPPATH . 'modules/setup/sql/047_1.7.6.sql')) as $sql) {
            $this->assertNotFalse($this->db->query($sql));
        }
        $this->assertSame('0', (string) $this->db->get_where('ip_clients', ['client_id' => 1])->row()->client_whatsapp_consent);
        $this->assertNotNull($this->db->get_where('ip_invoices', ['invoice_id' => 1])->row()->invoice_whatsapp_issued_at);
        $this->assertNull($this->db->get_where('ip_invoices', ['invoice_id' => 2])->row()->invoice_whatsapp_issued_at);
        $this->queue->invoice_issued(1);
        $this->assertCount(0, $this->queue->history(1));
        $this->assertTrue((new Mdl_setup())->upgrade_tables());
    }

    #[Test]
    public function it_claims_once_when_workers_start_simultaneously(): void
    {
        $this->queue->invoice_issued(1);
        $id      = $this->queue->history(1)[0]->notification_id;
        $start   = microtime(true) + 1;
        $command = [PHP_BINARY];
        if (DIRECTORY_SEPARATOR === '\\') {
            array_push($command, '-d', 'extension=mysqli');
        }
        array_push($command, __DIR__ . '/Support/claim_worker.php', (string) $id, (string) $start);
        $processes = [];
        for ($i = 0; $i < 2; $i++) {
            $process     = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $processes[] = [$process, $pipes];
        }
        $claims = 0;
        foreach ($processes as [$process, $pipes]) {
            fclose($pipes[0]);
            $claims += (int) stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->assertSame(0, proc_close($process), $error);
        }
        $this->assertSame(1, $claims);
        $this->assertSame('1', (string) $this->queue->history(1)[0]->total_attempts);
    }

    #[Test]
    public function it_does_not_let_an_expired_worker_overwrite_a_manual_retry(): void
    {
        $this->queue->invoice_issued(1);
        $now           = new DateTimeImmutable('+1 minute');
        $queue         = $this->queue;
        $retry_adapter = $this->adapter(new Response(200, [], '{"status":true,"id":["new-worker"]}'));
        $old_adapter   = new class ($queue, $now, $retry_adapter) extends Fonnte_adapter {
            public function __construct(private Mdl_notifications $queue, private DateTimeImmutable $now, private Fonnte_adapter $retry_adapter)
            {
                parent::__construct('test');
            }

            public function send(string $recipient, string $message): array
            {
                $later = $this->now->modify('+6 minutes');
                $this->queue->process($this->retry_adapter, $later);
                $job = $this->queue->history(1)[0];
                $this->queue->retry((int) $job->notification_id, 1, 1, $later);
                // The retry owns the row while the old request returns.
                $this->queue->claim((int) $job->notification_id, $later);

                return ['status' => 'accepted', 'provider_id' => 'old-worker', 'reason' => ''];
            }
        };
        $this->queue->process($old_adapter, $now);
        $this->assertSame('processing', $this->queue->history(1)[0]->status);
        $this->assertNull($this->queue->history(1)[0]->provider_id);
    }

    #[Test]
    public function it_preserves_the_payment_snapshot_if_another_payment_commits_before_enqueue(): void
    {
        $this->db->insert('ip_payments', ['payment_id' => 100, 'invoice_id' => 1, 'payment_amount' => 25, 'payment_date' => '2026-10-11']);
        $this->db->where('invoice_id', 1)->update('ip_invoice_amounts', ['invoice_balance' => 50]);
        $this->queue->payment_received(100, ['invoice_id' => 1, 'amount' => '25.00', 'balance' => '75.00']);
        $this->assertSame('75.00', $this->queue->history(1)[0]->payment_balance);
        $this->assertSame('25.00', $this->queue->history(1)[0]->payment_amount);
    }

    #[Test]
    public function it_sends_currency_values_as_plain_text(): void
    {
        $GLOBALS['test_settings']['currency_symbol']           = '€ &';
        $GLOBALS['test_settings']['currency_symbol_placement'] = 'afterspace';
        $history                                               = [];
        $stack                                                 = HandlerStack::create(new MockHandler([new Response(200, [], '{"status":true,"id":["id-1"]}')]));
        $stack->push(\GuzzleHttp\Middleware::history($history));
        $this->queue->invoice_issued(1);
        $this->queue->process(new Fonnte_adapter('test', new Client(['handler' => $stack])), new DateTimeImmutable('+1 minute'));
        parse_str((string) $history[0]['request']->getBody(), $body);
        $this->assertStringContainsString("100.00\u{00a0}€ &", $body['message']);
        $this->assertStringNotContainsString('&nbsp;', $body['message']);
        $this->assertStringNotContainsString('&amp;', $body['message']);
    }

    #[Test]
    public function it_records_publication_without_queuing_when_recurring_whatsapp_is_disabled(): void
    {
        $this->queue->invoice_issued(1, false);
        $this->assertCount(0, $this->queue->history(1));
        $this->assertNotNull($this->db->get_where('ip_invoices', ['invoice_id' => 1])->row()->invoice_whatsapp_issued_at);
        $this->queue->invoice_issued(1);
        $this->assertCount(0, $this->queue->history(1));
    }

    private function adapter(...$responses): Fonnte_adapter
    {
        return new Fonnte_adapter('internal-test', new Client(['handler' => HandlerStack::create(new MockHandler($responses))]));
    }

    private function payment_fixture(): void
    {
        $this->db->insert('ip_users', ['user_id' => 1, 'user_name' => 'Admin']);
        $this->db->insert('ip_invoice_groups', ['invoice_group_id' => 1, 'invoice_group_name' => 'Default', 'invoice_group_identifier_format' => '{{{id}}}', 'invoice_group_next_id' => 1]);
        $this->db->insert('ip_invoice_items', ['item_id' => 100, 'invoice_id' => 1, 'item_name' => 'Service', 'item_quantity' => 1, 'item_price' => 100]);
        $this->db->insert('ip_invoice_item_amounts', ['item_id' => 100, 'item_subtotal' => 100, 'item_total' => 100, 'item_tax_total' => 0, 'item_discount' => 0]);
        $GLOBALS['test_application']->load->model('payments/mdl_payments');
    }
}
