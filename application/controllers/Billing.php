<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Payment requests (plans and add-ons the businesses asked for, with their receipt) and every
 * payment. Approving runs the plan / add-ons (Billing_model, same rules as the app).
 */
class Billing extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model(array('billing_model', 'business_model'));
    }

    /** Agents only see the requests of their businesses */
    protected function scope_filter(array $f)
    {
        if (!can('businesses.view_all')) {
            $f['business_ids'] = $this->business_model->agent_business_ids($this->auth->id());
        }
        return $f;
    }

    public function index()
    {
        $this->need('billing.view');
        $status = (string) $this->input->get('status');
        $status = in_array($status, array('pending', 'approved', 'rejected'), true) ? $status : 'pending';
        $f = $this->scope_filter(array('status' => $status, 'q' => trim((string) $this->input->get('q', true))));
        $per = 30;
        $page = max(1, (int) $this->input->get('page'));
        $counts = array();
        foreach (array('pending', 'approved', 'rejected') as $s) {
            $counts[$s] = $this->billing_model->transactions($this->scope_filter(array('status' => $s)), 0, 0, true);
        }
        $this->render('billing/requests', array(
            'title' => 'Payment requests', 'status' => $status, 'q' => $f['q'], 'counts' => $counts, 'page' => $page, 'per' => $per,
            'total' => $this->billing_model->transactions($f, 0, 0, true), 'rows' => $this->billing_model->transactions($f, $per, ($page - 1) * $per),
        ));
    }

    public function payments()
    {
        $this->need('billing.view');
        $from = (string) $this->input->get('from');
        $to = (string) $this->input->get('to');
        $from = strtotime($from) ? date('Y-m-d', strtotime($from)) : date('Y-m-01', strtotime('-11 months'));
        $to = strtotime($to) ? date('Y-m-d', strtotime($to)) : date('Y-m-d');
        $kind = (string) $this->input->get('kind');
        $f = $this->scope_filter(array('status' => 'approved', 'from' => $from, 'to' => $to, 'q' => trim((string) $this->input->get('q', true)),
            'kind' => in_array($kind, array('plan', 'addon'), true) ? $kind : ''));
        $per = 50;
        $page = max(1, (int) $this->input->get('page'));
        $all = $this->billing_model->transactions($f, 100000);
        $sum = 0.0;
        foreach ($all as $t) {
            $sum += Billing_model::total($t);
        }
        if ($this->input->get('export') === 'csv') {
            audit('export', 'payments', 0, 'Exported payments ' . $from . ' – ' . $to);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="derz-payments-' . $from . '-' . $to . '.csv"');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array('ID', 'Date', 'Business', 'What', 'Period', 'Method', 'Reference', 'Amount', 'Starts', 'Ends'));
            foreach ($all as $t) {
                fputcsv($out, array($t['id'], $t['approved_at'] ?: $t['created_at'], $t['business_name'], ($t['kind'] ?? 'plan') === 'addon' ? 'Add-ons: ' . Billing_model::addon_summary($t) : $t['package_name'],
                    $t['billing_period'], $t['payment_method_name'], $t['reference'], Billing_model::total($t), $t['purchase_date'], $t['expire_date']));
            }
            fclose($out);
            exit;
        }
        $this->render('billing/payments', array('title' => 'Payments', 'rows' => array_slice($all, ($page - 1) * $per, $per), 'total' => count($all), 'sum' => $sum,
            'page' => $page, 'per' => $per, 'from' => $from, 'to' => $to, 'q' => $f['q'], 'kind' => $f['kind']));
    }

    protected function request($id)
    {
        $tx = $this->billing_model->transaction($id);
        if (!$tx || !$this->auth->can_see_business($tx['business_id'])) {
            $this->json(array('status' => 'fail', 'message' => 'This request is not there.'));
        }
        return $tx;
    }

    public function approve($id = 0)
    {
        $this->need_post();
        $this->need('billing.approve');
        $tx = $this->request((int) $id);
        $result = $this->billing_model->approve($id);
        if ($result !== true) {
            $this->json(array('status' => 'fail', 'message' => $result));
        }
        $what = ($tx['kind'] ?? 'plan') === 'addon' ? 'add-ons ' . Billing_model::addon_summary($tx) : $tx['package_name'] . ' (' . str_replace('_', ' ', $tx['billing_period']) . ')';
        audit('approve', 'payment_request', $id, 'Approved ' . $what . ' · ' . money(Billing_model::total($tx)) . ' · ' . $tx['reference'], array(), $tx['business_id']);
        $this->tell_business($tx['business_id'], '✅ DerZ: your payment of ' . money(Billing_model::total($tx)) . ' is confirmed. ' . ucfirst($what) . ' is active'
            . (($sub = $this->billing_model->latest_subscription($tx['business_id'])) ? ' until ' . fmt_date($sub['expire_date']) : '') . '. Thank you!');
        $this->flash('success', 'Approved: ' . $tx['business_name'] . ' — ' . $what . '.');
        $this->json(array('status' => 'success', 'url' => site_url('billing')));
    }

    public function reject($id = 0)
    {
        $this->need_post();
        $this->need('billing.approve');
        $tx = $this->request((int) $id);
        $note = trim((string) $this->input->post('note'));
        if ($note === '') {
            $this->json(array('status' => 'fail', 'errors' => array('note' => 'Say why: the business sees it.')));
        }
        if (!$this->billing_model->reject($id, $note)) {
            $this->json(array('status' => 'fail', 'message' => 'This request is no longer waiting.'));
        }
        audit('reject', 'payment_request', $id, 'Rejected: ' . $note, array(), $tx['business_id']);
        $this->tell_business($tx['business_id'], 'DerZ: your payment request (' . $tx['reference'] . ') could not be confirmed: ' . $note . '. Please contact us or send it again.');
        $this->flash('success', 'Rejected: ' . $tx['business_name'] . '.');
        $this->json(array('status' => 'success', 'url' => site_url('billing')));
    }

    /** The receipt a business attached (from the app's uploads), shown in the browser */
    public function receipt($id = 0)
    {
        $this->need('billing.view');
        $tx = $this->billing_model->transaction((int) $id);
        if (!$tx || empty($tx['receipt']) || !$this->auth->can_see_business($tx['business_id'])) {
            show_404();
        }
        $path = $this->billing_model->receipt_path($tx['receipt']);
        if (!$path) {
            show_error('The receipt file is not reachable from the portal: set app_path in application/config/admin.php.', 404);
        }
        $types = array('jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf');
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        header('Content-Type: ' . $types[$ext]);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="receipt-' . (int) $id . '.' . $ext . '"');
        readfile($path);
        exit;
    }

    /** A Telegram message to the business's linked chats (when the bot is set up) */
    protected function tell_business($business_id, $text)
    {
        $this->load->library('telegram');
        if (!$this->telegram->ready()) {
            return;
        }
        foreach ($this->db->where('business_id', (int) $business_id)->get('telegram_recipients')->result_array() as $c) {
            $this->telegram->send($c['chat_id'], $text);
        }
    }
}
