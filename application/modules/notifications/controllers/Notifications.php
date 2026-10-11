<?php

defined('BASEPATH') || exit('No direct script access allowed');

class Notifications extends Admin_Controller
{
    public function retry($id = null): void
    {
        $invoice_id = (int) $this->input->post('invoice_id');
        $redirect   = 'invoices/view/' . $invoice_id;
        if ( ! $this->ensure_valid_post_request($redirect)) {
            return;
        }
        $this->load->model('invoices/mdl_invoices');
        if ( ! $this->mdl_invoices->get_by_id($invoice_id) || ! $this->mdl_invoices->can_user_access($invoice_id)) {
            show_404();

            return;
        }
        $this->load->model('notifications/mdl_notifications');
        $success = $this->mdl_notifications->retry((int) $id, $invoice_id, (int) $this->session->userdata('user_id'));
        $this->session->set_flashdata($success ? 'alert_success' : 'alert_error', trans($success ? 'whatsapp_retry_queued' : 'whatsapp_retry_refused'));
        redirect($redirect);
    }
}
