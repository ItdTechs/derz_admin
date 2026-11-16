<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** The first page: today's figures, what needs doing (requests, follow-ups, ending plans), recent sign-ups. */
class Dashboard extends Admin_Controller
{
    public function index()
    {
        $this->need('dashboard.view');
        $this->load->model(array('analytics_model', 'billing_model', 'online_model', 'business_model'));
        $data = array('title' => 'Dashboard');
        $data['kpi'] = $this->analytics_model->kpis();
        $data['revenue'] = $this->analytics_model->revenue_by_month(6);
        $data['signups'] = $this->analytics_model->signups_by_month(6);
        $data['expiring'] = $this->analytics_model->expiring(14, 8);
        $data['recent'] = $this->analytics_model->recent_signups(8);
        $data['pending'] = can('billing.view') ? $this->billing_model->transactions(array('status' => 'pending'), 6) : array();
        $data['online'] = can('online.view') ? count($this->online_model->app_online()) : null;
        $data['businesses_online'] = can('online.view') ? count($this->online_model->businesses_online()) : null;
        // the staff member's own follow-ups due (agents)
        $data['follow_ups'] = $this->db->table_exists('business_crm') ? $this->db->select('c.*, b.name')->from('business_crm c')->join('businesses b', 'b.id = c.business_id')
            ->where('c.agent_id', $this->auth->id())->where('c.next_follow_up IS NOT NULL', null, false)->where('c.next_follow_up <=', date('Y-m-d', strtotime('+3 days')))
            ->order_by('c.next_follow_up')->limit(8)->get()->result_array() : array();
        $data['maintenance'] = platform_setting('maintenance_mode', '0') === '1';
        $this->render('dashboard/index', $data);
    }
}
