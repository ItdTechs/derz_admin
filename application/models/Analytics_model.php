<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Figures of the platform: businesses, subscriptions, revenue (approved payments), recurring
 * revenue, conversion and churn, and how much the businesses use DerZ (sales they record).
 * Revenue = approved subscription transactions (plan amount less its yearly discount, plus add-ons).
 */
class Analytics_model extends CI_Model
{
    protected $now;

    public function __construct()
    {
        parent::__construct();
        $this->now = date('Y-m-d H:i:s');
    }

    protected function billing_ready()
    {
        return $this->db->field_exists('status', 'subscriptions_transactions');
    }

    /** SQL of a transaction's total */
    protected function total_sql($alias = 't')
    {
        $addon = $this->db->field_exists('addon_amount', 'subscriptions_transactions') ? " + COALESCE($alias.addon_amount, 0)" : '';
        return "($alias.amount - ROUND($alias.amount * $alias.discount / 100, 2)$addon)";
    }

    protected function approved_where($alias = 't')
    {
        return $this->billing_ready() ? "$alias.status = 'approved'" : '1 = 1';
    }

    /** The latest subscription of every business (joined as s) */
    protected function latest_join()
    {
        return 'LEFT JOIN subscriptions s ON s.id = (SELECT MAX(s2.id) FROM subscriptions s2 WHERE s2.business_id = b.id)';
    }

    /** Monthly recurring revenue: every running paid subscription, its latest approved payment per month */
    public function mrr()
    {
        $total = $this->total_sql('t');
        $row = $this->db->query("SELECT COALESCE(SUM(m.monthly), 0) AS mrr, COUNT(*) AS paying FROM (
                SELECT b.id,
                    COALESCE((SELECT $total / IF(t.billing_period = '4_months', 4, 12) FROM subscriptions_transactions t
                        WHERE t.business_id = b.id AND " . $this->approved_where('t') . " AND COALESCE(t.kind, 'plan') <> 'addon'
                        ORDER BY t.id DESC LIMIT 1), sp.price / 12) AS monthly
                FROM businesses b " . $this->latest_join() . " LEFT JOIN subscription_packages sp ON sp.id = s.package_id
                WHERE s.expire_date >= ? AND s.free_trial = 0) m", array($this->now))->row_array();
        return array('mrr' => round((float) $row['mrr'], 2), 'paying' => (int) $row['paying']);
    }

    public function kpis()
    {
        $now = $this->now;
        $db = $this->db;
        $suspended = $db->field_exists('status', 'businesses') ? "SUM(b.status = 'suspended')" : '0';
        $c = $db->query("SELECT COUNT(*) AS businesses,
                SUM(s.expire_date >= ? AND s.free_trial = 0) AS active,
                SUM(s.expire_date >= ? AND s.free_trial = 1) AS trial,
                SUM(s.expire_date IS NULL OR s.expire_date < ?) AS expired,
                SUM(s.expire_date >= ? AND s.expire_date <= ?) AS expiring,
                $suspended AS suspended,
                SUM(b.created_at >= ?) AS new_month,
                SUM(b.created_at >= ? AND b.created_at < ?) AS new_last_month
            FROM businesses b " . $this->latest_join(),
            array($now, $now, $now, $now, date('Y-m-d H:i:s', strtotime('+14 days')), date('Y-m-01'), date('Y-m-01', strtotime('first day of last month')), date('Y-m-01')))->row_array();
        $out = array_map('intval', $c);

        $total = $this->total_sql('t');
        $rev = function ($from, $to) use ($total) {
            return round((float) $this->db->query("SELECT COALESCE(SUM($total), 0) AS r FROM subscriptions_transactions t WHERE " . $this->approved_where('t')
                . ' AND COALESCE(t.approved_at, t.created_at) >= ? AND COALESCE(t.approved_at, t.created_at) < ?', array($from, $to))->row()->r, 2);
        };
        $out['revenue_month'] = $rev(date('Y-m-01'), date('Y-m-d', strtotime('+1 day')));
        $out['revenue_last_month'] = $rev(date('Y-m-01', strtotime('first day of last month')), date('Y-m-01'));
        $out['revenue_year'] = $rev(date('Y-01-01'), date('Y-m-d', strtotime('+1 day')));
        $out['revenue_all'] = $rev('2000-01-01', date('Y-m-d', strtotime('+1 day')));
        $m = $this->mrr();
        $out['mrr'] = $m['mrr'];
        $out['arr'] = round($m['mrr'] * 12, 2);
        $out['arpu'] = $m['paying'] ? round($m['mrr'] / $m['paying'], 2) : 0;

        // conversion: businesses older than the trial that ever paid
        $trial_days = (int) platform_setting('trial_days', '15');
        $cut = date('Y-m-d H:i:s', strtotime('-' . $trial_days . ' days'));
        $conv = $db->query('SELECT COUNT(*) AS n, SUM(EXISTS(SELECT 1 FROM subscriptions_transactions t WHERE t.business_id = b.id AND ' . $this->approved_where('t') . ')) AS paid
            FROM businesses b WHERE b.created_at < ?', array($cut))->row_array();
        $out['conversion'] = (int) $conv['n'] ? round(100 * (int) $conv['paid'] / (int) $conv['n'], 1) : 0;

        // churn: paid subscriptions that ended in the last 30 days and were not renewed, of those running 30 days ago
        $ago = date('Y-m-d H:i:s', strtotime('-30 days'));
        $churn = $db->query('SELECT SUM(s.expire_date >= ? AND s.expire_date < ?) AS lost, SUM(s.expire_date >= ?) AS base
            FROM businesses b ' . $this->latest_join() . ' WHERE s.free_trial = 0', array($ago, $now, $ago))->row_array();
        $out['churned_30d'] = (int) $churn['lost'];
        $out['churn_rate'] = (int) $churn['base'] ? round(100 * (int) $churn['lost'] / (int) $churn['base'], 1) : 0;

        $out['pending'] = $this->billing_ready() ? (int) $db->where('status', 'pending')->count_all_results('subscriptions_transactions') : 0;
        $pending_amount = $this->billing_ready() ? $db->query("SELECT COALESCE(SUM($total), 0) AS a FROM subscriptions_transactions t WHERE t.status = 'pending'")->row()->a : 0;
        $out['pending_amount'] = round((float) $pending_amount, 2);
        $out['users'] = (int) $db->count_all('users');
        $out['users_active_7d'] = (int) $db->where('last_login >=', date('Y-m-d H:i:s', strtotime('-7 days')))->count_all_results('users');
        $gmv = $db->query('SELECT COUNT(*) AS n, COALESCE(SUM(net_amount), 0) AS t FROM sales WHERE created_at >= ?', array(date('Y-m-d H:i:s', strtotime('-30 days'))))->row_array();
        $out['sales_30d'] = (int) $gmv['n'];
        $out['gmv_30d'] = round((float) $gmv['t'], 2);
        return $out;
    }

    /** The last $n months: ['Y-m' => label] */
    public static function months($n = 12)
    {
        $out = array();
        for ($i = $n - 1; $i >= 0; $i--) {
            $t = strtotime(date('Y-m-01') . " -$i months");
            $out[date('Y-m', $t)] = date('M y', $t);
        }
        return $out;
    }

    /** Revenue per month (plan and add-ons) */
    public function revenue_by_month($n = 12)
    {
        $months = self::months($n);
        $out = array();
        foreach ($months as $k => $label) {
            $out[$k] = array('label' => $label, 'plan' => 0.0, 'addon' => 0.0, 'payments' => 0);
        }
        $kind = $this->db->field_exists('kind', 'subscriptions_transactions') ? "COALESCE(t.kind, 'plan')" : "'plan'";
        $rows = $this->db->query("SELECT DATE_FORMAT(COALESCE(t.approved_at, t.created_at), '%Y-%m') AS m, $kind AS kind, SUM(" . $this->total_sql('t') . ") AS total, COUNT(*) AS n
            FROM subscriptions_transactions t WHERE " . $this->approved_where('t') . " AND COALESCE(t.approved_at, t.created_at) >= ? GROUP BY m, kind",
            array(key($months) . '-01'))->result_array();
        foreach ($rows as $r) {
            if (isset($out[$r['m']])) {
                $out[$r['m']][$r['kind'] === 'addon' ? 'addon' : 'plan'] += (float) $r['total'];
                $out[$r['m']]['payments'] += (int) $r['n'];
            }
        }
        return $out;
    }

    /** New businesses per month, and how many of them pay today */
    public function signups_by_month($n = 12)
    {
        $months = self::months($n);
        $out = array();
        foreach ($months as $k => $label) {
            $out[$k] = array('label' => $label, 'signups' => 0, 'paying' => 0);
        }
        $rows = $this->db->query("SELECT DATE_FORMAT(b.created_at, '%Y-%m') AS m, COUNT(*) AS n, SUM(s.expire_date >= ? AND s.free_trial = 0) AS paying
            FROM businesses b " . $this->latest_join() . ' WHERE b.created_at >= ? GROUP BY m', array($this->now, key($months) . '-01'))->result_array();
        foreach ($rows as $r) {
            if (isset($out[$r['m']])) {
                $out[$r['m']]['signups'] = (int) $r['n'];
                $out[$r['m']]['paying'] = (int) $r['paying'];
            }
        }
        return $out;
    }

    /** Running subscriptions per package */
    public function plan_mix()
    {
        return $this->db->query('SELECT COALESCE(sp.name, \'—\') AS name, SUM(s.free_trial = 1) AS trial, SUM(s.free_trial = 0) AS paid, COUNT(*) AS n
            FROM businesses b ' . $this->latest_join() . ' LEFT JOIN subscription_packages sp ON sp.id = s.package_id
            WHERE s.expire_date >= ? GROUP BY sp.id, sp.name ORDER BY n DESC', array($this->now))->result_array();
    }

    /** What the businesses sell through DerZ per month (sales recorded) and how many businesses sell */
    public function platform_sales_by_month($n = 12)
    {
        $months = self::months($n);
        $out = array();
        foreach ($months as $k => $label) {
            $out[$k] = array('label' => $label, 'sales' => 0, 'amount' => 0.0, 'businesses' => 0);
        }
        foreach ($this->db->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS n, SUM(net_amount) AS t, COUNT(DISTINCT business_id) AS b
            FROM sales WHERE created_at >= ? GROUP BY m", array(key($months) . '-01'))->result_array() as $r) {
            if (isset($out[$r['m']])) {
                $out[$r['m']] = array('label' => $months[$r['m']], 'sales' => (int) $r['n'], 'amount' => (float) $r['t'], 'businesses' => (int) $r['b']);
            }
        }
        return $out;
    }

    /** Businesses that recorded a sale, per day of the last $days days */
    public function active_businesses_by_day($days = 30)
    {
        $out = array();
        for ($i = $days - 1; $i >= 0; $i--) {
            $out[date('Y-m-d', strtotime("-$i days"))] = 0;
        }
        foreach ($this->db->query('SELECT DATE(created_at) AS d, COUNT(DISTINCT business_id) AS n FROM sales WHERE created_at >= ? GROUP BY DATE(created_at)',
            array(date('Y-m-d', strtotime('-' . ($days - 1) . ' days'))))->result_array() as $r) {
            $out[$r['d']] = (int) $r['n'];
        }
        return $out;
    }

    /** The businesses selling most through DerZ in the last 30 days */
    public function top_businesses($limit = 10)
    {
        return $this->db->query('SELECT b.id, b.name, COUNT(sa.id) AS sales, SUM(sa.net_amount) AS amount, sp.name AS package_name
            FROM sales sa JOIN businesses b ON b.id = sa.business_id ' . $this->latest_join() . ' LEFT JOIN subscription_packages sp ON sp.id = s.package_id
            WHERE sa.created_at >= ? GROUP BY b.id, b.name, sp.name ORDER BY amount DESC LIMIT ' . (int) $limit, array(date('Y-m-d H:i:s', strtotime('-30 days'))))->result_array();
    }

    /** Paying / trial businesses nobody used for 14 days (no sign-in and no sale) */
    public function at_risk($limit = 10)
    {
        $cut = date('Y-m-d H:i:s', strtotime('-14 days'));
        return $this->db->query('SELECT b.id, b.name, s.expire_date, s.free_trial, sp.name AS package_name,
                (SELECT MAX(u.last_login) FROM users u WHERE u.business_id = b.id) AS last_login,
                (SELECT MAX(sa.created_at) FROM sales sa WHERE sa.business_id = b.id) AS last_sale
            FROM businesses b ' . $this->latest_join() . ' LEFT JOIN subscription_packages sp ON sp.id = s.package_id
            WHERE s.expire_date >= ?
            HAVING (last_login IS NULL OR last_login < ?) AND (last_sale IS NULL OR last_sale < ?)
            ORDER BY s.free_trial ASC, s.expire_date ASC LIMIT ' . (int) $limit, array($this->now, $cut, $cut))->result_array();
    }

    /** Businesses ending within $days days */
    public function expiring($days = 14, $limit = 10)
    {
        return $this->db->query('SELECT b.id, b.name, b.contact_number, s.expire_date, s.free_trial, sp.name AS package_name
            FROM businesses b ' . $this->latest_join() . ' LEFT JOIN subscription_packages sp ON sp.id = s.package_id
            WHERE s.expire_date >= ? AND s.expire_date <= ? ORDER BY s.expire_date LIMIT ' . (int) $limit,
            array($this->now, date('Y-m-d H:i:s', strtotime("+$days days"))))->result_array();
    }

    /** How far the businesses got: signed up, added products, made a sale, paid */
    public function funnel()
    {
        $r = $this->db->query('SELECT COUNT(*) AS signed_up,
                SUM(EXISTS(SELECT 1 FROM products p WHERE p.business_id = b.id)) AS products,
                SUM(EXISTS(SELECT 1 FROM sales sa WHERE sa.business_id = b.id)) AS sold,
                SUM(EXISTS(SELECT 1 FROM subscriptions_transactions t WHERE t.business_id = b.id AND ' . $this->approved_where('t') . ')) AS paid
            FROM businesses b')->row_array();
        return array_map('intval', $r);
    }

    /** Signup cohorts of the last $n months: how many still have a running plan, and how many pay */
    public function cohorts($n = 6)
    {
        $out = array();
        foreach (self::months($n) as $k => $label) {
            $out[$k] = array('label' => $label, 'signups' => 0, 'running' => 0, 'paying' => 0);
        }
        foreach ($this->db->query("SELECT DATE_FORMAT(b.created_at, '%Y-%m') AS m, COUNT(*) AS n, SUM(s.expire_date >= ?) AS running, SUM(s.expire_date >= ? AND s.free_trial = 0) AS paying
            FROM businesses b " . $this->latest_join() . ' WHERE b.created_at >= ? GROUP BY m', array($this->now, $this->now, key($out) . '-01'))->result_array() as $r) {
            if (isset($out[$r['m']])) {
                $out[$r['m']] = array('label' => $out[$r['m']]['label'], 'signups' => (int) $r['n'], 'running' => (int) $r['running'], 'paying' => (int) $r['paying']);
            }
        }
        return $out;
    }

    /** Where the businesses are (city) */
    public function cities($limit = 8)
    {
        return $this->db->query("SELECT COALESCE(NULLIF(TRIM(city), ''), 'Not given') AS city, COUNT(*) AS n FROM businesses GROUP BY 1 ORDER BY n DESC LIMIT " . (int) $limit)->result_array();
    }

    /** How the businesses pay (approved payments of the last 12 months) */
    public function payment_methods()
    {
        return $this->db->query('SELECT COALESCE(spt.name, \'Other\') AS name, COUNT(*) AS n, SUM(' . $this->total_sql('t') . ') AS amount
            FROM subscriptions_transactions t LEFT JOIN subscription_payment_types spt ON spt.id = t.payment_id
            WHERE ' . $this->approved_where('t') . ' AND COALESCE(t.approved_at, t.created_at) >= ? GROUP BY spt.id, spt.name ORDER BY amount DESC',
            array(date('Y-m-d', strtotime('-12 months'))))->result_array();
    }

    /** New sign-ins of app users per day (users.last_login only keeps the latest: people seen per day) */
    public function recent_signups($limit = 8)
    {
        return $this->db->query('SELECT b.id, b.name, b.created_at, b.city, s.free_trial, s.expire_date, CONCAT_WS(\' \', u.firstname, u.lastname) AS admin_name, u.phone
            FROM businesses b LEFT JOIN users u ON u.id = b.admin_id ' . $this->latest_join() . ' ORDER BY b.id DESC LIMIT ' . (int) $limit)->result_array();
    }
}
