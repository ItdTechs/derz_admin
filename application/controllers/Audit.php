<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** What the team did in the portal (admin_audit): filters, export. */
class Audit extends Admin_Controller
{
    public function index()
    {
        $this->need('audit.view');
        $this->load->model('staff_model');
        $f = array();
        foreach (array('staff_id', 'business_id', 'action', 'q', 'from', 'to') as $k) {
            $f[$k] = trim((string) $this->input->get($k, true));
        }
        $per = 50;
        $page = max(1, (int) $this->input->get('page'));
        if ($this->input->get('export') === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="derz-admin-audit-' . date('Y-m-d') . '.csv"');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array('When', 'Staff', 'Action', 'What', 'Business', 'Summary', 'IP'));
            foreach ($this->staff_model->audit($f, 100000) as $a) {
                fputcsv($out, array($a['created_at'], $a['staff_name'], $a['action'], $a['entity'] . ' #' . $a['entity_id'], $a['business_name'], $a['summary'], $a['ip']));
            }
            fclose($out);
            exit;
        }
        $this->render('audit/index', array('title' => 'Audit log', 'f' => $f, 'page' => $page, 'per' => $per, 'total' => $this->staff_model->audit($f, 0, 0, true),
            'rows' => $this->staff_model->audit($f, $per, ($page - 1) * $per), 'staff' => $this->staff_model->all(), 'actions' => $this->staff_model->audit_actions()));
    }
}
