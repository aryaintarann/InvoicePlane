<?php

defined('BASEPATH') || exit('No direct script access allowed');

#[AllowDynamicProperties]
class Skus extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('products/mdl_skus');
    }

    public function index($page = 0)
    {
        $this->mdl_skus->paginate(site_url('products/skus/index'), $page);
        $this->layout->set('skus', $this->mdl_skus->result());
        $this->layout->buffer('content', 'products/sku_index');
        $this->layout->render();
    }

    public function form($id = null)
    {
        if ($this->input->post('btn_cancel')) {
            redirect('products/skus');
        }

        if ($id && ! $this->mdl_skus->get_by_id($id)) {
            show_404();
        }
        $this->mdl_skus->set_id($id);

        if ($this->mdl_skus->run_validation()) {
            $this->mdl_skus->save($id);
            redirect('products/skus');
        }

        if ($id && ! $this->input->post('btn_submit')) {
            $this->mdl_skus->prep_form($id);
        }

        $this->layout->buffer('content', 'products/sku_form');
        $this->layout->render();
    }
}
