<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Businesses: the list (filters, sort, export), a business's profile with its subscription,
 * usage, users, payments, notes and history, and what the team can do with it.
 * Agents without "all businesses" only see the businesses they manage.
 */
class Businesses extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model(array('business_model', 'billing_model', 'online_model', 'staff_model'));
    }

    /** null (all) or the business ids the staff member may see */
    protected function scope()
    {
        return can('businesses.view_all') ? null : $this->business_model->agent_business_ids($this->auth->id());
    }

    protected function filters()
    {
        $f = array();
        foreach (array('q', 'status', 'package', 'stage', 'agent') as $k) {
            $f[$k] = trim((string) $this->input->get($k, true));
        }
        $f['follow_up'] = $this->input->get('follow_up') === '1';
        $f['scope'] = $this->scope();
        if ($f['scope'] === null) {
            unset($f['scope']);
        }
        return $f;
    }

    public function index()
    {
        $this->need('businesses.view');
        $f = $this->filters();
        $per = 25;
        $page = max(1, (int) $this->input->get('page'));
        $sort = (string) $this->input->get('sort');
        $total = $this->business_model->count($f);
        $rows = $this->business_model->search($f, $per, ($page - 1) * $per, $sort ?: 'newest');
        $online = can('online.view') ? $this->online_model->businesses_online() : array();
        $this->render('businesses/index', array(
            'title' => 'Businesses', 'rows' => $rows, 'total' => $total, 'page' => $page, 'per' => $per, 'f' => $f, 'sort' => $sort, 'online' => $online,
            'packages' => $this->billing_model->packages(), 'agents' => $this->staff_model->agents(),
        ));
    }

    public function export()
    {
        $this->need('businesses.export');
        $f = $this->filters();
        $rows = $this->business_model->search($f, 100000, 0, 'newest');
        audit('export', 'businesses', 0, 'Exported ' . count($rows) . ' businesses');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="derz-businesses-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // Excel reads UTF-8 (Amharic names)
        fputcsv($out, array('ID', 'Business', 'Phone', 'Email', 'City', 'Admin', 'Admin phone', 'Package', 'Status', 'Ends', 'Users', 'Sales 30 days', 'Last active', 'Stage', 'Account manager', 'Tags', 'Created'));
        foreach ($rows as $r) {
            fputcsv($out, array($r['id'], $r['name'], $r['contact_number'], $r['business_email'], $r['city'], $r['admin_name'], $r['admin_phone'], $r['package_name'],
                $r['business_status'] === 'suspended' ? 'suspended' : $r['status'], $r['expire_date'], $r['users'], $r['sales_30d'], $r['last_active'], $r['stage'], $r['agent_name'], $r['tags'], $r['created_at']));
        }
        fclose($out);
        exit;
    }

    /** The business, or stop (not found / not theirs) */
    protected function business($id)
    {
        $this->need('businesses.view');
        if (!$this->auth->can_see_business($id)) {
            show_404();
        }
        $b = $this->business_model->get($id);
        if (!$b) {
            show_404();
        }
        return $b;
    }

    public function view($id = 0)
    {
        $b = $this->business((int) $id);
        $this->render('businesses/view', array(
            'title'     => $b['name'],
            'b'         => $b,
            'usage'     => $this->business_model->usage($id),
            'daily'     => $this->business_model->daily_sales($id, 30),
            'users'     => $this->business_model->users($id),
            'payments'  => can('billing.view') ? $this->billing_model->transactions(array('business_id' => $id), 50) : array(),
            'crm'       => $this->business_model->crm($id),
            'notes'     => $this->business_model->notes($id),
            'app_audit' => $this->business_model->app_audit($id, 40),
            'team_audit'=> $this->business_model->team_audit($id, 40),
            'packages'  => $this->billing_model->packages(),
            'methods'   => $this->billing_model->payment_methods(true),
            'agents'    => $this->staff_model->agents(),
            'suspension_ready' => $this->business_model->suspension_ready(),
        ));
    }

    public function update($id = 0)
    {
        $this->need_post();
        $this->need('businesses.edit');
        $b = $this->business((int) $id);
        $data = array();
        foreach (array('name', 'contact_number', 'business_email', 'address', 'city', 'tin', 'vat_number', 'website') as $k) {
            $data[$k] = mb_substr(trim((string) $this->input->post($k)), 0, $k === 'address' ? 500 : 255);
        }
        if ($data['name'] === '') {
            $this->json(array('status' => 'fail', 'errors' => array('name' => 'Enter the business name.')));
        }
        if ($data['business_email'] !== '' && !filter_var($data['business_email'], FILTER_VALIDATE_EMAIL)) {
            $this->json(array('status' => 'fail', 'errors' => array('business_email' => 'Enter a valid email.')));
        }
        $changes = array();
        foreach ($data as $k => $v) {
            if ((string) ($b[$k] ?? '') !== $v) {
                $changes[$k] = array($b[$k] ?? '', $v);
            }
        }
        $this->business_model->update($id, $data);
        audit('update', 'business', $id, 'Edited business details', $changes, $id);
        $this->flash('success', 'Business details saved.');
        $this->json(array('status' => 'success', 'url' => site_url('businesses/view/' . (int) $id)));
    }

    public function crm($id = 0)
    {
        $this->need_post();
        $this->need('businesses.assign');
        $this->business((int) $id);
        $agent = (int) $this->input->post('agent_id');
        $stage = (string) $this->input->post('stage');
        $follow = trim((string) $this->input->post('next_follow_up'));
        $data = array(
            'agent_id' => $agent && $this->staff_model->get($agent) ? $agent : null,
            'stage' => array_key_exists($stage, crm_stages()) ? $stage : 'onboarding',
            'tags' => mb_substr(implode(', ', array_filter(array_map('trim', explode(',', (string) $this->input->post('tags'))))), 0, 255),
            'next_follow_up' => $follow !== '' && strtotime($follow) ? date('Y-m-d', strtotime($follow)) : null,
        );
        $this->business_model->save_crm($id, $data);
        audit('update', 'business_crm', $id, 'Account: ' . crm_stages()[$data['stage']][0] . ($data['agent_id'] ? ' · manager #' . $data['agent_id'] : ''), $data, $id);
        $this->flash('success', 'Account details saved.');
        $this->json(array('status' => 'success', 'url' => site_url('businesses/view/' . (int) $id) . '#notes'));
    }

    public function note($id = 0)
    {
        $this->need_post();
        $this->need('businesses.notes');
        $this->business((int) $id);
        $note = trim((string) $this->input->post('note'));
        if ($note === '') {
            $this->json(array('status' => 'fail', 'errors' => array('note' => 'Write the note.')));
        }
        $this->business_model->add_note($id, $this->auth->id(), (string) $this->input->post('kind'), $note);
        // "my agent follows up": a follow-up date with the note
        $follow = trim((string) $this->input->post('next_follow_up'));
        if ($follow !== '' && strtotime($follow) && can('businesses.assign')) {
            $this->business_model->save_crm($id, array('next_follow_up' => date('Y-m-d', strtotime($follow))));
        }
        audit('note', 'business', $id, 'Note: ' . mb_substr($note, 0, 120), array(), $id);
        $this->json(array('status' => 'success', 'url' => site_url('businesses/view/' . (int) $id) . '#notes'));
    }

    public function suspend($id = 0)
    {
        $this->need_post();
        $this->need('businesses.suspend');
        $b = $this->business((int) $id);
        if (!$this->business_model->suspension_ready()) {
            $this->json(array('status' => 'fail', 'message' => 'Run SQL step 45 first.'));
        }
        $reason = trim((string) $this->input->post('reason'));
        if ($reason === '') {
            $this->json(array('status' => 'fail', 'errors' => array('reason' => 'Say why: the business sees it.')));
        }
        $this->business_model->suspend($id, $reason);
        audit('suspend', 'business', $id, 'Suspended: ' . $reason, array(), $id);
        $this->flash('success', $b['name'] . ' is suspended and its people were signed out.');
        $this->json(array('status' => 'success', 'url' => site_url('businesses/view/' . (int) $id)));
    }

    public function reactivate($id = 0)
    {
        $this->need_post();
        $this->need('businesses.suspend');
        $b = $this->business((int) $id);
        $this->business_model->reactivate($id);
        audit('reactivate', 'business', $id, 'Reactivated', array(), $id);
        $this->flash('success', $b['name'] . ' can use DerZ again.');
        $this->json(array('status' => 'success', 'url' => site_url('businesses/view/' . (int) $id)));
    }

    /* ---------- subscription ---------- */

    public function record_payment($id = 0)
    {
        $this->need_post();
        $this->need('billing.record');
        $b = $this->business((int) $id);
        $reference = trim((string) $this->input->post('reference'));
        if ($reference === '') {
            $this->json(array('status' => 'fail', 'errors' => array('reference' => 'Enter the bank reference or receipt number.')));
        }
        $package = (int) $this->input->post('package_id');
        $period = (string) $this->input->post('billing_period');
        $result = $this->billing_model->record_payment($id, $package, $period, (int) $this->input->post('payment_method'), $reference, (string) $this->input->post('note'));
        if ($result !== true) {
            $this->json(array('status' => 'fail', 'message' => $result));
        }
        $p = $this->billing_model->package($package);
        $price = Billing_model::period_price($p, $period);
        audit('payment', 'subscription', $id, 'Payment recorded: ' . $p['name'] . ' ' . str_replace('_', ' ', $period) . ' · ' . money($price['total']) . ' · ' . $reference, array(), $id);
        $this->flash('success', 'Payment recorded: ' . $b['name'] . ' is on ' . $p['name'] . '.');
        $this->json(array('status' => 'success', 'url' => site_url('businesses/view/' . (int) $id)));
    }

    public function set_expiry($id = 0)
    {
        $this->need_post();
        $this->need('billing.record');
        $b = $this->business((int) $id);
        $date = (string) $this->input->post('expire_date');
        $d = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            $this->json(array('status' => 'fail', 'errors' => array('expire_date' => 'Choose a date.')));
        }
        $trial = $this->input->post('as_trial');
        $this->billing_model->set_expiry($id, $date, $trial === null ? null : $trial === '1');
        audit('update', 'subscription', $id, 'End date set to ' . $date . (($note = trim((string) $this->input->post('note'))) !== '' ? ' — ' . $note : ''),
            array('from' => $b['expire_date'], 'to' => $date), $id);
        $this->flash('success', 'The end date is now ' . fmt_date($date) . '.');
        $this->json(array('status' => 'success', 'url' => site_url('businesses/view/' . (int) $id)));
    }

    public function change_package($id = 0)
    {
        $this->need_post();
        $this->need('billing.record');
        $b = $this->business((int) $id);
        $pid = (int) $this->input->post('package_id');
        $result = $this->billing_model->change_package($id, $pid);
        if ($result !== true) {
            $this->json(array('status' => 'fail', 'message' => $result));
        }
        $p = $this->billing_model->package($pid);
        audit('update', 'subscription', $id, 'Package changed from ' . ($b['package_name'] ?: '—') . ' to ' . $p['name'] . ' (same end date)', array(), $id);
        $this->flash('success', 'Package changed to ' . $p['name'] . '.');
        $this->json(array('status' => 'success', 'url' => site_url('businesses/view/' . (int) $id)));
    }

    /* ---------- the business's app users ---------- */

    protected function user_action($id, $uid)
    {
        $this->need_post();
        $this->need('users.manage');
        $this->business((int) $id);
        $user = $this->db->where('id', (int) $uid)->where('business_id', (int) $id)->get('users')->row_array();
        if (!$user) {
            $this->json(array('status' => 'fail', 'message' => 'This user is not in this business.'));
        }
        return $user;
    }

    public function user_password($id = 0, $uid = 0)
    {
        $user = $this->user_action($id, $uid);
        $r = $this->business_model->reset_user_password($id, $uid);
        audit('password', 'app_user', $uid, 'Reset the app password of ' . $user['username'], array(), $id);
        $this->json(array('status' => 'success', 'password' => $r['password'], 'message' => 'New password for ' . $user['username'] . ': give it to them; they were signed out.'));
    }

    public function user_active($id = 0, $uid = 0)
    {
        $user = $this->user_action($id, $uid);
        $active = $this->input->post('active') === '1';
        $b = $this->db->select('admin_id')->where('id', (int) $id)->get('businesses')->row();
        if (!$active && $b && (int) $b->admin_id === (int) $uid) {
            $this->json(array('status' => 'fail', 'message' => 'The business Admin cannot be switched off (suspend the business instead).'));
        }
        $this->business_model->set_user_active($id, $uid, $active);
        audit($active ? 'activate' : 'deactivate', 'app_user', $uid, ($active ? 'Switched on ' : 'Switched off ') . $user['username'], array(), $id);
        $this->flash('success', $user['username'] . ($active ? ' can sign in again.' : ' is switched off and signed out.'));
        $this->json(array('status' => 'success', 'url' => site_url('businesses/view/' . (int) $id) . '#users'));
    }

    public function user_signout($id = 0, $uid = 0)
    {
        $user = $this->user_action($id, $uid);
        $n = $this->online_model->end_user_sessions($uid);
        audit('signout', 'app_user', $uid, 'Signed out ' . $user['username'] . ' (' . $n . ' session' . ($n === 1 ? '' : 's') . ')', array(), $id);
        $this->flash('success', $user['username'] . ' was signed out of ' . $n . ' session' . ($n === 1 ? '' : 's') . '.');
        $this->json(array('status' => 'success', 'url' => site_url('businesses/view/' . (int) $id) . '#users'));
    }
}
