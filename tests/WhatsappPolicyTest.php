<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WhatsappPolicyTest extends TestCase
{
    #[Test]
    public function it_normalizes_one_indonesian_mobile_number(): void
    {
        $this->assertSame('6281234567890', Whatsapp_policy::normalize_phone('0812-3456 7890'));
        $this->assertSame('6281234567890', Whatsapp_policy::normalize_phone('+62 (812) 34567890'));
        foreach (['', '0812,0813', '6281234567890|admin', '0211234567', 'abc081234567890', '081', '628123456789012345'] as $invalid) {
            $this->assertNull(Whatsapp_policy::normalize_phone($invalid));
        }
    }

    #[Test]
    public function it_limits_templates_to_known_placeholders_without_recursive_substitution(): void
    {
        $this->assertSame('Hi {link}: 123', Whatsapp_policy::render('Hi {customer}: {number}', ['customer' => '{link}', 'number' => '123']));
        $this->expectException(InvalidArgumentException::class);
        Whatsapp_policy::render('{token}', []);
    }

    #[Test]
    public function it_schedules_only_today_after_local_send_time(): void
    {
        $settings = ['whatsapp_reminder_days' => '-3, 0, 3', 'whatsapp_send_time' => '09:00', 'whatsapp_timezone' => 'Asia/Jakarta'];
        $this->assertSame([-3, 0, 3], Whatsapp_policy::offsets($settings['whatsapp_reminder_days']));
        $this->assertSame('2026-10-11', Whatsapp_policy::reminder_date('2026-10-14', $settings, new DateTimeImmutable('2026-10-11 02:05:00Z')));
        $this->assertNull(Whatsapp_policy::reminder_date('2026-10-14', $settings, new DateTimeImmutable('2026-10-11 01:59:00Z')));
        $this->assertNull(Whatsapp_policy::reminder_date('2026-10-14', $settings, new DateTimeImmutable('2026-10-12 02:05:00Z')));
        $this->assertNull(Whatsapp_policy::reminder_date('2026-10-15', $settings, new DateTimeImmutable('2026-10-11 02:05:00Z')));
    }

    #[Test]
    public function it_rejects_invalid_scheduler_settings(): void
    {
        foreach (['-3,x,3', '1000', '0,,3'] as $invalid) {
            try {
                Whatsapp_policy::offsets($invalid);
                $this->fail('Invalid offsets accepted');
            } catch (InvalidArgumentException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->expectException(InvalidArgumentException::class);
        Whatsapp_policy::validate_settings(['whatsapp_send_time' => '25:00']);
    }

    #[Test]
    public function it_allows_exactly_three_automatic_retries(): void
    {
        $this->assertSame(60, Whatsapp_policy::retry_delay(1));
        $this->assertSame(300, Whatsapp_policy::retry_delay(2));
        $this->assertSame(900, Whatsapp_policy::retry_delay(3));
        $this->assertNull(Whatsapp_policy::retry_delay(4));
    }
}
