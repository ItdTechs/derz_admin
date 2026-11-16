<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** Revenue, growth, retention and usage of the platform. */
class Analytics extends Admin_Controller
{
    public function index()
    {
        $this->need('analytics.view');
        $this->load->model('analytics_model');
        $months = (int) $this->input->get('months');
        $months = in_array($months, array(6, 12, 24), true) ? $months : 12;
        $m = $this->analytics_model;
        $this->render('analytics/index', array(
            'title'     => 'Analytics',
            'months'    => $months,
            'kpi'       => $m->kpis(),
            'revenue'   => $m->revenue_by_month($months),
            'signups'   => $m->signups_by_month($months),
            'gmv'       => $m->platform_sales_by_month($months),
            'daily'     => $m->active_businesses_by_day(30),
            'plan_mix'  => $m->plan_mix(),
            'funnel'    => $m->funnel(),
            'cohorts'   => $m->cohorts(6),
            'top'       => $m->top_businesses(10),
            'at_risk'   => $m->at_risk(10),
            'cities'    => $m->cities(8),
            'methods'   => $m->payment_methods(),
        ));
    }

    /** The monthly figures as CSV */
    public function export()
    {
        $this->need('analytics.view');
        $this->load->model('analytics_model');
        $rev = $this->analytics_model->revenue_by_month(24);
        $sign = $this->analytics_model->signups_by_month(24);
        $gmv = $this->analytics_model->platform_sales_by_month(24);
        audit('export', 'analytics', 0, 'Exported monthly analytics');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="derz-analytics-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, array('Month', 'Plan revenue', 'Add-on revenue', 'Payments', 'New businesses', 'Of them paying', 'Sales recorded', 'Sales amount', 'Businesses selling'));
        foreach ($rev as $k => $r) {
            fputcsv($out, array($k, $r['plan'], $r['addon'], $r['payments'], $sign[$k]['signups'], $sign[$k]['paying'], $gmv[$k]['sales'], $gmv[$k]['amount'], $gmv[$k]['businesses']));
        }
        fclose($out);
        exit;
    }
}
