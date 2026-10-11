<?php

defined('BASEPATH') || exit('No direct script access allowed');

class Whatsapp_policy
{
    public const DEFAULTS = [
        'whatsapp_issued_enabled'     => '0',
        'whatsapp_payment_enabled'    => '0',
        'whatsapp_reminder_enabled'   => '0',
        'whatsapp_automatic_on_recur' => '0',
        'whatsapp_reminder_days'      => '-3,0,3',
        'whatsapp_send_time'          => '09:00',
        'whatsapp_timezone'           => 'Asia/Jakarta',
        'whatsapp_issued_template'    => 'Halo {customer}, invoice {number} sebesar {amount}, jatuh tempo {due_date}. Sisa saldo {balance}. {link}',
        'whatsapp_payment_template'   => 'Halo {customer}, pembayaran {amount} untuk invoice {number} telah dicatat. Sisa saldo {balance}. {link}',
        'whatsapp_reminder_template'  => 'Halo {customer}, pengingat invoice {number}, jatuh tempo {due_date}. Sisa saldo {balance}. {link}',
    ];

    public static function normalize_phone(?string $phone): ?string
    {
        $phone = trim($phone ?? '');
        if ( ! preg_match('/^\+?[0-9 ()-]+$/D', $phone)) {
            return null;
        }
        $phone = preg_replace('/[ ()+-]/', '', $phone);
        if (str_starts_with($phone, '08')) {
            $phone = '62' . substr($phone, 1);
        }

        return preg_match('/^628[1-9][0-9]{7,10}$/D', $phone) ? $phone : null;
    }

    public static function render(string $template, array $values): string
    {
        $allowed = ['customer', 'number', 'amount', 'balance', 'due_date', 'link'];
        preg_match_all('/\{([^{}]*)\}/', $template, $matches);
        foreach ($matches[1] as $placeholder) {
            if ( ! in_array($placeholder, $allowed, true)) {
                throw new InvalidArgumentException('Unknown WhatsApp placeholder.');
            }
        }
        $replacements = [];
        foreach ($allowed as $key) {
            $replacements['{' . $key . '}'] = (string) ($values[$key] ?? '');
        }

        return strtr($template, $replacements);
    }

    public static function offsets(string $input): array
    {
        $offsets = [];
        foreach (explode(',', $input) as $part) {
            $part = trim($part);
            if ( ! preg_match('/^-?\d{1,3}$/D', $part) || abs((int) $part) > 365) {
                throw new InvalidArgumentException('Reminder days must be comma-separated integers between -365 and 365.');
            }
            $offsets[] = (int) $part;
        }

        return array_values(array_unique($offsets));
    }

    public static function validate_settings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            if ( ! str_starts_with((string) $key, 'whatsapp_')) {
                continue;
            }
            if ( ! array_key_exists($key, self::DEFAULTS) || ! is_string($value)) {
                throw new InvalidArgumentException('Invalid WhatsApp setting.');
            }
            if (str_ends_with($key, '_enabled') || $key === 'whatsapp_automatic_on_recur') {
                if ( ! in_array($value, ['0', '1'], true)) {
                    throw new InvalidArgumentException('Invalid WhatsApp switch.');
                }
            } elseif (str_ends_with($key, '_template')) {
                if (trim($value) === '' || strlen($value) > 4000) {
                    throw new InvalidArgumentException('WhatsApp templates must contain 1 to 4000 bytes.');
                }
                self::render($value, []);
            } elseif ($key === 'whatsapp_reminder_days') {
                self::offsets($value);
            } elseif ($key === 'whatsapp_send_time' && ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $value)) {
                throw new InvalidArgumentException('WhatsApp send time must use HH:MM (24 hours).');
            } elseif ($key === 'whatsapp_timezone' && ! in_array($value, DateTimeZone::listIdentifiers(), true)) {
                throw new InvalidArgumentException('Choose a valid IANA time zone.');
            }
        }
    }

    public static function reminder_date(string $due_date, array $settings, DateTimeImmutable $now): ?string
    {
        $settings += self::DEFAULTS;
        self::validate_settings($settings);
        $local = $now->setTimezone(new DateTimeZone($settings['whatsapp_timezone']));
        if ($local->format('H:i') < $settings['whatsapp_send_time']) {
            return null;
        }
        $due = DateTimeImmutable::createFromFormat('!Y-m-d', $due_date, $local->getTimezone());
        if ( ! $due || $due->format('Y-m-d') !== $due_date) {
            return null;
        }
        $days = (int) $due->diff($local->setTime(0, 0))->format('%r%a');

        return in_array($days, self::offsets($settings['whatsapp_reminder_days']), true) ? $local->format('Y-m-d') : null;
    }

    public static function retry_delay(int $attempts): ?int
    {
        return [1 => 60, 2 => 300, 3 => 900][$attempts] ?? null;
    }
}
