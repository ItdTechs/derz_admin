<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Every page: the allowed addresses, the session (in admin_sessions once SQL step 45 is run),
 * the Auth library and the page layout.
 */
class MY_Controller extends CI_Controller
{
    public $data = array();

    public function __construct()
    {
        parent::__construct();
        date_default_timezone_set('Africa/Addis_Ababa'); // as the DerZ app: dates in the database are Ethiopian time
        $allowed = (array) config_item('allowed_ips');
        if ($allowed && !is_cli() && !in_array($this->input->ip_address(), $allowed, true)) {
            show_error('This portal is not open from your network.', 403);
        }
        if (!is_cli()) {
            if (!$this->db->table_exists('admin_sessions')) {
                $this->config->set_item('sess_driver', 'files');
                $this->config->set_item('sess_save_path', NULL);
            }
            $this->load->library('session');
        }
        $this->load->library('staff_auth', null, 'auth');
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
    }

    protected function json(array $data, $status = 200)
    {
        set_status_header($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** A message shown once on the next page */
    protected function flash($type, $message)
    {
        $this->session->set_flashdata('flash', array('type' => $type, 'message' => $message));
    }
}

/**
 * Pages of signed-in staff: the session must be valid, the password changed after a reset, and
 * each page asks for its permission (need()).
 */
class Admin_Controller extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->auth->ready()) {
            show_error('Run database/sql/2026-11-10_45_admin_portal.sql on the DerZ database first.', 503);
        }
        if (!$this->auth->staff() || !$this->auth->touch()) {
            if ($this->input->is_ajax_request()) {
                $this->json(array('status' => 'fail', 'message' => 'Your session has ended. Sign in again.', 'login' => true), 401);
            }
            $this->session->set_userdata('after_login', uri_string() . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
            redirect('login');
        }
        $staff = $this->auth->staff();
        if ((int) $staff['must_change_password'] === 1 && $this->router->fetch_class() !== 'account') {
            redirect('account/password');
        }
        $this->data['me'] = $staff;
    }

    /** Stop unless the staff member has this permission. */
    protected function need($permission)
    {
        if (!$this->auth->can($permission)) {
            if ($this->input->is_ajax_request() || $this->input->method() === 'post') {
                $this->json(array('status' => 'fail', 'message' => 'You do not have permission to do this.'), 403);
            }
            $this->data['title'] = 'No access';
            $this->render('errors/no_access');
            $this->output->_display();
            exit;
        }
    }

    protected function need_post()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
    }

    /** The page inside the layout (sidebar, top bar). */
    protected function render($view, array $data = array())
    {
        $data = array_merge($this->data, $data);
        $data['content'] = $this->load->view($view, $data, true);
        $data['nav'] = $this->navigation();
        $data['badges'] = $this->badges();
        $this->load->view('layout', $data);
    }

    /** The sidebar: [section => [[uri, icon, label, permission, badge key]]] */
    protected function navigation()
    {
        $items = array(
            'Overview' => array(
                array('dashboard', 'fa-gauge-high', 'Dashboard', 'dashboard.view', ''),
                array('analytics', 'fa-chart-line', 'Analytics', 'analytics.view', ''),
            ),
            'Customers' => array(
                array('businesses', 'fa-building', 'Businesses', 'businesses.view', ''),
                array('billing', 'fa-file-invoice-dollar', 'Payment requests', 'billing.view', 'pending'),
                array('billing/payments', 'fa-receipt', 'Payments', 'billing.view', ''),
                array('online', 'fa-signal', 'Online now', 'online.view', 'online'),
                array('messages', 'fa-bullhorn', 'Announcements', 'messages.manage', ''),
            ),
            'Platform' => array(
                array('packages', 'fa-layer-group', 'Packages', 'packages.manage', ''),
                array('translations', 'fa-language', 'Translations', 'translations.manage', ''),
                array('settings', 'fa-sliders', 'Settings', 'settings.manage', ''),
            ),
            'Team' => array(
                array('staff', 'fa-user-shield', 'Staff', 'staff.manage', ''),
                array('staff/roles', 'fa-key', 'Roles', 'roles.manage', ''),
                array('audit', 'fa-clipboard-list', 'Audit log', 'audit.view', ''),
            ),
        );
        $out = array();
        foreach ($items as $section => $links) {
            $links = array_values(array_filter($links, function ($l) { return can($l[3]); }));
            if ($links) {
                $out[$section] = $links;
            }
        }
        return $out;
    }

    /** Counts on the sidebar: pending requests, people online */
    protected function badges()
    {
        $out = array('pending' => 0, 'online' => 0);
        if (can('billing.view') && $this->db->field_exists('status', 'subscriptions_transactions')) {
            $out['pending'] = (int) $this->db->where('status', 'pending')->count_all_results('subscriptions_transactions');
        }
        if (can('online.view') && $this->db->table_exists('rm_sessions')) {
            $since = time() - (int) config_item('online_minutes') * 60;
            $out['online'] = (int) $this->db->query("SELECT COUNT(*) AS n FROM rm_sessions WHERE timestamp >= ? AND data LIKE '%loggedin_id%'", array($since))->row()->n;
        }
        return $out;
    }
}
