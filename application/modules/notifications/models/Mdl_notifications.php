<?php

defined('BASEPATH') || exit('No direct script access allowed');

require_once APPPATH . 'modules/notifications/libraries/Whatsapp_policy.php';
require_once APPPATH . 'modules/notifications/libraries/Fonnte_adapter.php';

class Mdl_notifications extends CI_Model
{
    public function settings(): array
    {
        $settings = [];
        foreach (Whatsapp_policy::DEFAULTS as $key => $default) {
            $settings[$key] = (string) get_setting($key, $default);
        }

        return $settings;
    }

    public function invoice_issued(int $invoice_id, bool $send = true): void
    {
        // Mark the first publication even while disabled, preventing later backfill.
        $this->db->where('invoice_id', $invoice_id)->where_in('invoice_status_id', [2, 3, 4])
            ->where('invoice_whatsapp_issued_at IS NULL', null, false)
            ->update('ip_invoices', ['invoice_whatsapp_issued_at' => gmdate('Y-m-d H:i:s')]);
        if ($this->db->affected_rows() !== 1 || ! $send || get_setting('whatsapp_issued_enabled', '0') !== '1') {
            return;
        }
        $this->enqueue('issued', $invoice_id, 'issued:' . $invoice_id, new DateTimeImmutable('now', new DateTimeZone('UTC')));
    }

    public function payment_received(int $payment_id, array $snapshot): void
    {
        if (get_setting('whatsapp_payment_enabled', '0') !== '1') {
            return;
        }
        if ((float) $snapshot['amount'] <= 0) {
            return;
        }
        $this->enqueue('payment', (int) $snapshot['invoice_id'], 'payment:' . $payment_id, new DateTimeImmutable('now', new DateTimeZone('UTC')), [
            'payment_id'      => $payment_id,
            'payment_amount'  => $snapshot['amount'],
            'payment_balance' => $snapshot['balance'],
        ]);
    }

    public function schedule_reminders(?DateTimeImmutable $now = null): void
    {
        $settings = $this->settings();
        if ($settings['whatsapp_reminder_enabled'] !== '1') {
            return;
        }
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        Whatsapp_policy::validate_settings($settings);
        $local = $now->setTimezone(new DateTimeZone($settings['whatsapp_timezone']));
        if ($local->format('H:i') < $settings['whatsapp_send_time']) {
            return;
        }
        $due_dates = [];
        foreach (Whatsapp_policy::offsets($settings['whatsapp_reminder_days']) as $offset) {
            $due_dates[] = $local->modify(sprintf('%+d days', -$offset))->format('Y-m-d');
        }
        $invoices = $this->db->select('i.invoice_id')->from('ip_invoices i')
            ->join('ip_invoice_amounts a', 'a.invoice_id = i.invoice_id')
            ->where_in('i.invoice_status_id', [2, 3])->where('a.invoice_balance >', 0)
            ->where_in('i.invoice_date_due', $due_dates)->get()->result();
        foreach ($invoices as $invoice) {
            $date = $local->format('Y-m-d');
            $this->enqueue('reminder', (int) $invoice->invoice_id, 'reminder:' . $invoice->invoice_id . ':' . $date, $now, ['reminder_date' => $date]);
        }
    }

    public function claim(int $id, DateTimeImmutable $now): bool
    {
        $stamp = $this->stamp($now);
        $this->db->set('attempts', 'attempts + 1', false)->set('total_attempts', 'total_attempts + 1', false)
            ->where('notification_id', $id)->where('status', 'queued')->where('scheduled_at <=', $stamp)
            ->update('ip_notifications', ['status' => 'processing', 'claimed_at' => $stamp, 'updated_at' => $stamp]);

        return $this->db->affected_rows() === 1;
    }

    public function process(?Fonnte_adapter $adapter = null, ?DateTimeImmutable $now = null): void
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $settings = $this->settings();
        $adapter ??= new Fonnte_adapter(defined('FONNTE_TOKEN') ? (string) FONNTE_TOKEN : '');
        // A crashed worker may already have submitted the message. Never reclaim it for sending.
        $this->db->where('status', 'processing')->where('claimed_at <', $this->stamp($now->modify('-5 minutes')))
            ->update('ip_notifications', ['status' => 'uncertain', 'failure_reason' => 'worker_interrupted', 'updated_at' => $this->stamp($now)]);
        $jobs = $this->db->where('status', 'queued')->where('scheduled_at <=', $this->stamp($now))
            ->order_by('notification_id')->limit(20)->get('ip_notifications')->result();
        $started = microtime(true);
        foreach ($jobs as $job) {
            if (microtime(true) - $started > 40) {
                break;
            }
            if ( ! $this->claim((int) $job->notification_id, $now)) {
                continue;
            }
            $job->attempts++;
            $job->total_attempts++;
            $invoice = $this->invoice((int) $job->invoice_id);
            $reason  = $this->skip_reason($job, $invoice, $settings, $now);
            if ($reason) {
                $this->finish($job, ['status' => 'skipped', 'failure_reason' => $reason], $now);
                continue;
            }
            $recipient = Whatsapp_policy::normalize_phone($invoice->client_mobile);
            $values    = [
                'customer' => trim($invoice->client_name . ' ' . $invoice->client_surname),
                'number'   => $invoice->invoice_number,
                'amount'   => html_entity_decode(format_currency($job->event_type === 'payment' ? $job->payment_amount : $invoice->invoice_total), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'balance'  => html_entity_decode(format_currency($job->event_type === 'payment' ? $job->payment_balance : $invoice->invoice_balance), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'due_date' => $invoice->invoice_date_due,
                'link'     => site_url('guest/view/invoice/' . $invoice->invoice_url_key),
            ];
            try {
                $message = Whatsapp_policy::render($settings['whatsapp_' . $job->event_type . '_template'], $values);
                $result  = $adapter->send($recipient, $message);
            } catch (InvalidArgumentException $e) {
                $this->finish($job, ['status' => 'failed', 'failure_reason' => 'template_invalid'], $now);
                continue;
            }
            $status = $result['status'];
            $update = ['recipient' => $recipient, 'provider_id' => $result['provider_id'], 'failure_reason' => $result['reason']];
            if ($status === 'rejected') {
                $delay  = Whatsapp_policy::retry_delay((int) $job->attempts);
                $status = $delay === null ? 'failed' : 'queued';
                if ($delay !== null) {
                    $update['scheduled_at'] = $this->stamp($now->modify('+' . $delay . ' seconds'));
                }
            }
            $this->finish($job, ['status' => $status] + $update, $now);
        }
    }

    public function retry(int $id, int $invoice_id, int $user_id, ?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->db->where('notification_id', $id)->where('invoice_id', $invoice_id)->where_in('status', ['failed', 'uncertain', 'skipped'])
            ->update('ip_notifications', [
                'status'       => 'queued', 'attempts' => 0, 'claimed_at' => null, 'failure_reason' => null,
                'scheduled_at' => $this->stamp($now), 'updated_at' => $this->stamp($now), 'retried_by' => $user_id,
            ]);

        return $this->db->affected_rows() === 1;
    }

    public function history(int $invoice_id): array
    {
        return $this->db->where('invoice_id', $invoice_id)->order_by('notification_id', 'DESC')->get('ip_notifications')->result();
    }

    private function enqueue(string $type, int $invoice_id, string $key, DateTimeImmutable $now, array $extra = []): void
    {
        $invoice = $this->invoice($invoice_id);
        if ( ! $invoice) {
            return;
        }
        $reason = $this->recipient_reason($invoice);
        $stamp  = $this->stamp($now);
        $row    = $extra + [
            'event_key'      => $key, 'event_type' => $type, 'invoice_id' => $invoice_id,
            'recipient'      => Whatsapp_policy::normalize_phone($invoice->client_mobile),
            'scheduled_at'   => $stamp, 'status' => $reason ? 'skipped' : 'queued',
            'failure_reason' => $reason, 'created_at' => $stamp, 'updated_at' => $stamp,
        ];
        $sql = $this->db->set($row)->get_compiled_insert('ip_notifications');
        $this->db->query(preg_replace('/^INSERT INTO/i', 'INSERT IGNORE INTO', $sql, 1));
    }

    private function skip_reason(object $job, ?object $invoice, array $settings, DateTimeImmutable $now): ?string
    {
        if ( ! $invoice) {
            return 'invoice_missing';
        }
        if ($settings['whatsapp_' . $job->event_type . '_enabled'] !== '1') {
            return 'type_disabled';
        }
        if ($reason = $this->recipient_reason($invoice)) {
            return $reason;
        }
        if ($job->event_type === 'reminder') {
            if ( ! in_array((int) $invoice->invoice_status_id, [2, 3], true) || (float) $invoice->invoice_balance <= 0) {
                return 'invoice_not_open';
            }
            $date = $now->setTimezone(new DateTimeZone($settings['whatsapp_timezone']))->format('Y-m-d');
            if ($date !== $job->reminder_date) {
                return 'reminder_expired';
            }
            if (Whatsapp_policy::reminder_date($invoice->invoice_date_due, $settings, $now) !== $date) {
                return 'reminder_schedule_changed';
            }
        } elseif ($job->event_type === 'issued' && ! in_array((int) $invoice->invoice_status_id, [2, 3, 4], true)) {
            return 'invoice_not_published';
        } elseif ($job->event_type === 'payment') {
            $payment = $this->db->get_where('ip_payments', ['payment_id' => $job->payment_id, 'invoice_id' => $job->invoice_id])->row();
            if ( ! $payment || (float) $payment->payment_amount <= 0) {
                return 'payment_missing';
            }
        }

        return null;
    }

    private function recipient_reason(object $invoice): ?string
    {
        if ((int) $invoice->client_whatsapp_consent !== 1) {
            return 'consent_missing';
        }
        if (trim((string) $invoice->client_mobile) === '') {
            return 'phone_missing';
        }

        return Whatsapp_policy::normalize_phone($invoice->client_mobile) === null ? 'phone_invalid' : null;
    }

    private function finish(object $job, array $update, DateTimeImmutable $now): void
    {
        $this->db->where('notification_id', $job->notification_id)->where('status', 'processing')
            ->where('total_attempts', $job->total_attempts)->where('claimed_at', $this->stamp($now))
            ->update('ip_notifications', $update + ['updated_at' => $this->stamp($now), 'claimed_at' => null]);
    }

    private function invoice(int $id): ?object
    {
        return $this->db->select('i.*, c.client_name, c.client_surname, c.client_mobile, c.client_whatsapp_consent, a.invoice_total, a.invoice_balance')
            ->from('ip_invoices i')->join('ip_clients c', 'c.client_id = i.client_id')
            ->join('ip_invoice_amounts a', 'a.invoice_id = i.invoice_id')->where('i.invoice_id', $id)->get()->row();
    }

    private function stamp(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
