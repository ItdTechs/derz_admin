<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Subscriptions, payment requests, packages and payment methods. The same rules as the DerZ app
 * (Company_model, Addon_model there), so a request approved here or there ends the same way:
 *  - a plan request: the package runs from today (or from the current end date when renewing
 *    the same package) for 4 months or a year; add-ons in a renewal run for the new period;
 *  - an add-on request: added to what runs, until the subscription's end date.
 */
class Billing_model extends CI_Model
{
    public static $periods = array('4_months' => 4, '1_year' => 12);
    public static $addon_columns = array('store' => 'addon_stores', 'warehouse' => 'addon_warehouses', 'user' => 'addon_users', 'hr' => 'addon_hr');

    public function addons_ready()
    {
        static $ready = null;
        if ($ready === null) {
            $ready = $this->db->table_exists('subscription_addons') && $this->db->field_exists('addon_amount', 'subscriptions_transactions');
        }
        return $ready;
    }

    /** Status of a subscription row: active | trial | expired | none */
    public static function status_of($sub)
    {
        if (empty($sub) || empty($sub['expire_date'])) {
            return 'none';
        }
        if ($sub['expire_date'] < date('Y-m-d H:i:s')) {
            return 'expired';
        }
        return (int) $sub['free_trial'] === 1 ? 'trial' : 'active';
    }

    /** What a package costs for a period (as the app's Company_model::period_price) */
    public static function period_price($package, $period)
    {
        $yearly = (float) ($package['price'] ?? 0);
        $four = isset($package['price_4_months']) && (float) $package['price_4_months'] > 0 ? (float) $package['price_4_months'] : round($yearly / 3, 2);
        if ($period === '4_months') {
            return array('list' => $four, 'rate' => 0.0, 'total' => $four, 'months' => 4);
        }
        $list = $yearly > 0 ? round($yearly, 2) : round($four * 3, 2);
        $rate = (float) ($package['discount'] ?? 0);
        return array('list' => $list, 'rate' => $rate, 'total' => round($list - round($list * $rate / 100, 2), 2), 'months' => 12);
    }

    /** What a request / payment comes to: the plan amount less its discount %, plus the add-on part */
    public static function total($tx)
    {
        $amount = (float) ($tx['amount'] ?? 0);
        return round($amount - round($amount * (float) ($tx['discount'] ?? 0) / 100, 2) + (float) ($tx['addon_amount'] ?? 0), 2);
    }

    /** "+2 stores · +5 users · HR" of a request */
    public static function addon_summary($tx)
    {
        $parts = array();
        foreach (array('addon_stores' => 'store', 'addon_warehouses' => 'warehouse', 'addon_users' => 'user') as $col => $name) {
            $n = (int) ($tx[$col] ?? 0);
            if ($n > 0) {
                $parts[] = '+' . $n . ' ' . $name . ($n > 1 ? 's' : '');
            }
        }
        if ((int) ($tx['addon_hr'] ?? 0) > 0) {
            $parts[] = 'HR & payroll';
        }
        return implode(' · ', $parts);
    }

    public function latest_subscription($business_id)
    {
        return $this->db->where('business_id', (int) $business_id)->order_by('id', 'DESC')->limit(1)->get('subscriptions')->row_array() ?: null;
    }

    public function package($id)
    {
        return $this->db->where('id', (int) $id)->get('subscription_packages')->row_array() ?: null;
    }

    public function packages()
    {
        return $this->db->query('SELECT p.*, (SELECT COUNT(*) FROM subscriptions s WHERE s.package_id = p.id AND s.expire_date >= NOW()
                AND s.id = (SELECT MAX(s2.id) FROM subscriptions s2 WHERE s2.business_id = s.business_id)) AS businesses
            FROM subscription_packages p ORDER BY p.package_order, p.id')->result_array();
    }

    /** The add-ons a business has running: kind => quantity */
    public function addons($business_id)
    {
        $out = array('store' => 0, 'warehouse' => 0, 'user' => 0, 'hr' => 0);
        if (!$this->addons_ready()) {
            return $out;
        }
        foreach ($this->db->where('business_id', (int) $business_id)->where('expires_at >=', date('Y-m-d H:i:s'))->get('subscription_addons')->result_array() as $r) {
            $out[$r['kind']] = (int) $r['quantity'];
        }
        return $out;
    }

    /** Requests / payments, newest first. $f: status, business_id, business_ids (agent), q, from, to, kind */
    public function transactions(array $f = array(), $limit = 50, $offset = 0, $count = false)
    {
        $this->db->from('subscriptions_transactions t')
            ->join('businesses b', 'b.id = t.business_id', 'left')
            ->join('subscription_packages sp', 'sp.id = t.package_id', 'left')
            ->join('subscription_payment_types spt', 'spt.id = t.payment_id', 'left');
        if (!empty($f['status'])) {
            $this->db->where('t.status', $f['status']);
        }
        if (!empty($f['business_id'])) {
            $this->db->where('t.business_id', (int) $f['business_id']);
        }
        if (isset($f['business_ids'])) {
            $this->db->where_in('t.business_id', $f['business_ids'] ?: array(0));
        }
        if (!empty($f['q'])) {
            $this->db->group_start()->like('b.name', $f['q'])->or_like('t.reference', $f['q'])->group_end();
        }
        if (!empty($f['from'])) {
            $this->db->where('COALESCE(t.approved_at, t.created_at) >=', $f['from'] . ' 00:00:00');
        }
        if (!empty($f['to'])) {
            $this->db->where('COALESCE(t.approved_at, t.created_at) <=', $f['to'] . ' 23:59:59');
        }
        if (!empty($f['kind'])) {
            $this->db->where('t.kind', $f['kind']);
        }
        if ($count) {
            return (int) $this->db->count_all_results();
        }
        return $this->db->select("t.*, b.name AS business_name, sp.name AS package_name, spt.name AS payment_method_name", false)
            ->order_by('t.id', 'DESC')->limit($limit, $offset)->get()->result_array();
    }

    public function transaction($id)
    {
        return $this->db->select('t.*, b.name AS business_name, sp.name AS package_name, spt.name AS payment_method_name')
            ->from('subscriptions_transactions t')->join('businesses b', 'b.id = t.business_id', 'left')
            ->join('subscription_packages sp', 'sp.id = t.package_id', 'left')->join('subscription_payment_types spt', 'spt.id = t.payment_id', 'left')
            ->where('t.id', (int) $id)->get()->row_array() ?: null;
    }

    /** Approve a pending request (the payment was checked). true or the error. approved_by = 0: the portal (staff in admin_audit). */
    public function approve($id)
    {
        $this->db->trans_begin();
        $tx = $this->db->query("SELECT * FROM subscriptions_transactions WHERE id = ? AND status = 'pending' FOR UPDATE", array((int) $id))->row_array();
        if (!$tx) {
            $this->db->trans_rollback();
            return 'This request is no longer waiting.';
        }
        $now = date('Y-m-d H:i:s');
        if (($tx['kind'] ?? 'plan') === 'addon') {
            $sub = $this->latest_subscription($tx['business_id']);
            if (!$sub || $sub['expire_date'] < $now) {
                $this->db->trans_rollback();
                return 'The plan has ended: renew it first, then approve the add-ons.';
            }
            $this->apply_addons($tx, $sub['expire_date']);
            $this->db->where('id', (int) $tx['id'])->update('subscriptions_transactions', array('status' => 'approved', 'approved_by' => 0, 'approved_at' => $now,
                'subscriptions_id' => (int) $sub['id'], 'purchase_date' => date('Y-m-d'), 'expire_date' => date('Y-m-d', strtotime($sub['expire_date']))));
        } else {
            $months = self::$periods[$tx['billing_period']] ?? 12;
            $current = $this->latest_subscription($tx['business_id']);
            $start = ($current && (int) $current['package_id'] === (int) $tx['package_id'] && $current['expire_date'] > $now) ? $current['expire_date'] : $now;
            $expire = date('Y-m-d H:i:s', strtotime($start . ' +' . $months . ' months'));
            if ($current) {
                $this->db->where('id', (int) $current['id'])->update('subscriptions', array('package_id' => (int) $tx['package_id'], 'expire_date' => $expire,
                    'upgrade_lasttime' => date('Y-m-d'), 'free_trial' => 0));
                $sub_id = (int) $current['id'];
            } else {
                $this->db->insert('subscriptions', array('package_id' => (int) $tx['package_id'], 'business_id' => (int) $tx['business_id'], 'expire_date' => $expire,
                    'upgrade_lasttime' => date('Y-m-d'), 'free_trial' => 0));
                $sub_id = (int) $this->db->insert_id();
            }
            $this->db->where('id', (int) $tx['id'])->update('subscriptions_transactions', array('status' => 'approved', 'approved_by' => 0, 'approved_at' => $now,
                'subscriptions_id' => $sub_id, 'purchase_date' => date('Y-m-d', strtotime($start)), 'expire_date' => date('Y-m-d', strtotime($expire))));
            if ($this->addons_ready()) {
                $this->apply_addons($tx, $expire);
            }
        }
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return 'Something went wrong. Nothing was changed.';
        }
        $this->db->trans_commit();
        return true;
    }

    public function reject($id, $note)
    {
        $this->db->where('id', (int) $id)->where('status', 'pending')->update('subscriptions_transactions', array(
            'status' => 'rejected', 'approved_by' => 0, 'approved_at' => date('Y-m-d H:i:s'), 'note' => mb_substr(trim((string) $note), 0, 255)));
        return $this->db->affected_rows() > 0;
    }

    /** Add-ons of an approved request: an add-on request adds to what runs, a renewal sets what runs */
    protected function apply_addons(array $tx, $expire)
    {
        $now = date('Y-m-d H:i:s');
        foreach (self::$addon_columns as $kind => $col) {
            $n = (int) ($tx[$col] ?? 0);
            if ($n <= 0) {
                continue;
            }
            $row = $this->db->where('business_id', (int) $tx['business_id'])->where('kind', $kind)->get('subscription_addons')->row_array();
            $running = $row && $row['expires_at'] >= $now ? (int) $row['quantity'] : 0;
            $qty = $kind === 'hr' ? 1 : (($tx['kind'] ?? 'plan') === 'addon' ? $running + $n : $n);
            $this->db->query('INSERT INTO subscription_addons (business_id, kind, quantity, expires_at) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), expires_at = VALUES(expires_at)', array((int) $tx['business_id'], $kind, $qty, $expire));
        }
    }

    /** A payment received outside the app: recorded as a request and approved at once. true or the error. */
    public function record_payment($business_id, $package_id, $period, $payment_id, $reference, $note)
    {
        $package = $this->package($package_id);
        if (!$package || !isset(self::$periods[$period])) {
            return 'Choose a package and a period.';
        }
        $price = self::period_price($package, $period);
        $current = $this->latest_subscription($business_id);
        $this->db->insert('subscriptions_transactions', array(
            'subscriptions_id' => $current ? (int) $current['id'] : 0, 'package_id' => (int) $package_id, 'business_id' => (int) $business_id,
            'payment_id' => (int) $payment_id, 'reference' => mb_substr(trim((string) $reference), 0, 200),
            'amount' => $price['list'], 'discount' => $price['rate'], 'billing_period' => $period, 'status' => 'pending',
            'note' => mb_substr(trim((string) $note), 0, 255), 'requested_by' => 0,
            'renew' => $current && (int) $current['package_id'] === (int) $package_id ? 1 : 0, 'purchase_date' => null, 'expire_date' => null,
        ));
        return $this->approve((int) $this->db->insert_id());
    }

    /** Set the end date (extend a trial, correct a date); keeps the package. */
    public function set_expiry($business_id, $date, $as_trial = null)
    {
        $current = $this->latest_subscription($business_id);
        $expire = date('Y-m-d 23:59:59', strtotime($date));
        if ($current) {
            $data = array('expire_date' => $expire);
            if ($as_trial !== null) {
                $data['free_trial'] = $as_trial ? 1 : 0;
            }
            $this->db->where('id', (int) $current['id'])->update('subscriptions', $data);
        } else {
            $this->db->insert('subscriptions', array('package_id' => 1, 'business_id' => (int) $business_id, 'expire_date' => $expire, 'free_trial' => 1));
        }
        return true;
    }

    /** Move a business to another package until the same end date (a correction, nothing billed). */
    public function change_package($business_id, $package_id)
    {
        $current = $this->latest_subscription($business_id);
        if (!$current || !$this->package($package_id)) {
            return 'This business has no subscription yet: record a payment instead.';
        }
        $this->db->where('id', (int) $current['id'])->update('subscriptions', array('package_id' => (int) $package_id));
        return true;
    }

    // ---------- packages ----------

    /** Every module key used by any package (the module switches) */
    public function module_keys()
    {
        $keys = array();
        foreach ($this->db->select('permission')->get('subscription_packages')->result_array() as $p) {
            foreach ((array) json_decode((string) $p['permission'], true) as $k => $v) {
                if ($k !== '*' && is_bool($v)) {
                    $keys[$k] = true;
                }
            }
        }
        $keys = array_keys($keys);
        sort($keys);
        return $keys;
    }

    public function package_features($id)
    {
        return array_column($this->db->select('title')->where('subscription_package_id', (int) $id)->order_by('id')->get('subscription_package_features')->result_array(), 'title');
    }

    public function save_package($id, array $data, array $modules, array $features)
    {
        $data['permission'] = json_encode($modules, JSON_PRETTY_PRINT);
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->trans_start();
        if ($id) {
            $this->db->where('id', (int) $id)->update('subscription_packages', $data);
        } else {
            $this->db->insert('subscription_packages', $data);
            $id = (int) $this->db->insert_id();
        }
        $this->db->where('subscription_package_id', (int) $id)->delete('subscription_package_features');
        foreach ($features as $title) {
            $this->db->insert('subscription_package_features', array('subscription_package_id' => (int) $id, 'title' => $title, 'description' => '', 'icon' => 'fa-check'));
        }
        $this->db->trans_complete();
        return $id;
    }

    // ---------- payment methods ----------

    public function payment_methods($active_only = false)
    {
        $has = $this->db->field_exists('sort_order', 'subscription_payment_types');
        if ($active_only && $has) {
            $this->db->where('active', 1);
        }
        return $this->db->select('pt.*, (SELECT COUNT(*) FROM subscriptions_transactions t WHERE t.payment_id = pt.id) AS used', false)
            ->from('subscription_payment_types pt')->order_by($has ? 'pt.sort_order, pt.name' : 'pt.name')->get()->result_array();
    }

    public function save_payment_method($id, $name, $details, $active, $sort)
    {
        $name = trim((string) $name);
        if ($name === '' || mb_strlen($name) > 60) {
            return array('error' => 'Enter a name of up to 60 characters.');
        }
        if ($this->db->where('name', $name)->where('id <>', (int) $id)->count_all_results('subscription_payment_types')) {
            return array('error' => 'This name is already used.');
        }
        $data = array('name' => $name);
        if ($this->db->field_exists('details', 'subscription_payment_types')) {
            $data += array('details' => mb_substr(trim((string) $details), 0, 1000), 'active' => $active ? 1 : 0, 'sort_order' => (int) $sort);
        }
        if ($id) {
            $this->db->where('id', (int) $id)->update('subscription_payment_types', $data);
            return (int) $id;
        }
        $this->db->insert('subscription_payment_types', $data);
        return (int) $this->db->insert_id();
    }

    public function delete_payment_method($id)
    {
        if ($this->db->where('payment_id', (int) $id)->count_all_results('subscriptions_transactions')) {
            return array('error' => 'Requests used this method: switch it off instead.');
        }
        $this->db->where('id', (int) $id)->delete('subscription_payment_types');
        return true;
    }

    // ---------- receipts (files of the app: uploads/subscription_receipts) ----------

    public function receipt_path($file)
    {
        $file = basename((string) $file);
        $root = (string) config_item('app_path');
        if ($root === '' || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|pdf)$/', $file)) {
            return null;
        }
        $path = rtrim($root, '/\\') . '/uploads/subscription_receipts/' . $file;
        return is_file($path) ? $path : null;
    }
}
