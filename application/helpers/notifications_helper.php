<?php

defined('BASEPATH') || exit('No direct script access allowed');

function notify_whatsapp(string $event, int $id = 0, array $context = []): void
{
    $ci               = &get_instance();
    $debug            = $ci->db->db_debug;
    $ci->db->db_debug = false;
    try {
        if ( ! $ci->db->table_exists('ip_notifications')) {
            return;
        }
        $ci->load->model('notifications/mdl_notifications');
        if ($event === 'issued') {
            $ci->mdl_notifications->invoice_issued($id, $context['send'] ?? true);
        } elseif ($event === 'payment') {
            $ci->mdl_notifications->payment_received($id, $context);
        } elseif ($event === 'cron') {
            $ci->mdl_notifications->schedule_reminders();
            $ci->mdl_notifications->process();
        }
    } catch (Throwable $e) {
        // Notification failure must not undo a committed invoice or payment.
        log_message('error', 'WhatsApp notification processing failed; inspect notification history.');
    } finally {
        $ci->db->db_debug = $debug;
    }
}
