<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Businesses of the platform: the list with its filters, a business's profile, subscription,
 * usage, activity, users, account manager (business_crm) and notes, and suspension.
 */
class Business_model extends CI_Model
{
    public function suspension_ready()
    {
        static $ready = null;
        if ($ready === null) {
            $ready = $this->db->field_exists('status', 'businesses');
        }
        return $ready;
    }

    /** The list's SQL (FROM ... WHERE) and its values. $f: q, status, package, stage, agent, scope (business ids or null) */
    protected function list_sql(array $f)
    {
        $now = $this->db->escape(date('Y-m-d H:i:s'));
        $sql = " FROM businesses b
                 LEFT JOIN users u ON u.id = b.admin_id
                 LEFT JOIN subscriptions s ON s.id = (SELECT MAX(s2.id) FROM subscriptions s2 WHERE s2.business_id = b.id)
                 LEFT JOIN subscription_packages sp ON sp.id = s.package_id
                 LEFT JOIN business_crm c ON c.business_id = b.id
                 LEFT JOIN admin_staff ag ON ag.id = c.agent_id";
        $where = array();
        $binds = array();
        if (!empty($f['q'])) {
            $where[] = '(b.name LIKE ? OR b.contact_number LIKE ? OR b.business_email LIKE ? OR u.username LIKE ? OR u.phone LIKE ? OR b.id = ?)';
            $like = '%' . $f['q'] . '%';
            array_push($binds, $like, $like, $like, $like, $like, (int) $f['q']);
        }
        $soon = $this->db->escape(date('Y-m-d H:i:s', strtotime('+14 days')));
        switch ($f['status'] ?? '') {
            case 'active':    $where[] = "s.expire_date >= $now AND s.free_trial = 0"; break;
            case 'trial':     $where[] = "s.expire_date >= $now AND s.free_trial = 1"; break;
            case 'expired':   $where[] = "(s.expire_date IS NULL OR s.expire_date < $now)"; break;
            case 'expiring':  $where[] = "s.expire_date >= $now AND s.expire_date <= $soon"; break;
            case 'suspended': if ($this->suspension_ready()) { $where[] = "b.status = 'suspended'"; } break;
        }
        if (!empty($f['package'])) {
            $where[] = 's.package_id = ' . (int) $f['package'];
        }
        if (!empty($f['stage'])) {
            $where[] = 'COALESCE(c.stage, \'onboarding\') = ' . $this->db->escape($f['stage']);
        }
        if (isset($f['agent']) && $f['agent'] !== '') {
            $where[] = $f['agent'] === 'none' ? 'c.agent_id IS NULL' : 'c.agent_id = ' . (int) $f['agent'];
        }
        if (!empty($f['follow_up'])) {
            $where[] = 'c.next_follow_up IS NOT NULL AND c.next_follow_up <= CURDATE()';
        }
        if (isset($f['scope']) && is_array($f['scope'])) {
            $where[] = $f['scope'] ? 'b.id IN (' . implode(',', array_map('intval', $f['scope'])) . ')' : '1 = 0';
        }
        return array($sql . ($where ? ' WHERE ' . implode(' AND ', $where) : ''), $binds);
    }

    public function count(array $f)
    {
        list($sql, $binds) = $this->list_sql($f);
        return (int) $this->db->query('SELECT COUNT(*) AS n' . $sql, $binds)->row()->n;
    }

    /** One page of the list. $sort: newest | name | expiry | last_active | sales */
    public function search(array $f, $limit = 25, $offset = 0, $sort = 'newest')
    {
        list($sql, $binds) = $this->list_sql($f);
        $orders = array('newest' => 'b.id DESC', 'name' => 'b.name ASC', 'expiry' => 's.expire_date IS NULL, s.expire_date ASC',
            'last_active' => 'last_active IS NULL, last_active DESC', 'sales' => 'sales_30d DESC');
        $order = isset($orders[$sort]) ? $orders[$sort] : $orders['newest'];
        $since = $this->db->escape(date('Y-m-d H:i:s', strtotime('-30 days')));
        $rows = $this->db->query("SELECT b.id, b.name, b.contact_number, b.business_email, b.city, b.created_at, b.logo,"
            . ($this->suspension_ready() ? ' b.status AS business_status,' : " 'active' AS business_status,") . "
                u.username AS admin_username, CONCAT_WS(' ', u.firstname, u.lastname) AS admin_name, u.phone AS admin_phone,
                s.package_id, s.expire_date, s.free_trial, sp.name AS package_name,
                c.stage, c.tags, c.next_follow_up, c.agent_id, ag.name AS agent_name,
                (SELECT MAX(us.last_login) FROM users us WHERE us.business_id = b.id) AS last_active,
                (SELECT COUNT(*) FROM users us WHERE us.business_id = b.id) AS users,
                (SELECT COALESCE(SUM(sa.net_amount), 0) FROM sales sa WHERE sa.business_id = b.id AND sa.created_at >= $since) AS sales_30d
            $sql ORDER BY $order LIMIT " . (int) $offset . ', ' . (int) $limit, $binds)->result_array();
        foreach ($rows as &$r) {
            $r['status'] = Billing_model::status_of($r);
        }
        return $rows;
    }

    /** A business with everything the profile shows, or null */
    public function get($id)
    {
        $rows = $this->search(array('q' => '', 'scope' => array((int) $id)), 1);
        if (!$rows) {
            return null;
        }
        $b = array_merge($this->db->where('id', (int) $id)->get('businesses')->row_array(), $rows[0]);
        $b['subscription'] = $this->billing_model->latest_subscription($id);
        $b['addons'] = $this->billing_model->addons($id);
        return $b;
    }

    /** Counts of what the business has and does */
    public function usage($id)
    {
        $q = function ($sql) use ($id) { return $this->db->query($sql, array((int) $id))->row_array(); };
        $since30 = date('Y-m-d H:i:s', strtotime('-30 days'));
        $out = array(
            'stores'     => (int) $q('SELECT COUNT(*) AS n FROM stores WHERE business_id = ?')['n'],
            'warehouses' => (int) $q('SELECT COUNT(*) AS n FROM warehouses WHERE business_id = ?')['n'],
            'users'      => (int) $q('SELECT COUNT(*) AS n FROM users WHERE business_id = ?')['n'],
            'products'   => (int) $q('SELECT COUNT(*) AS n FROM products WHERE business_id = ?')['n'],
            'customers'  => (int) $q('SELECT COUNT(*) AS n FROM customers WHERE business_id = ?')['n'],
            'suppliers'  => (int) $q('SELECT COUNT(*) AS n FROM suppliers WHERE business_id = ?')['n'],
        );
        $s = $this->db->query('SELECT COUNT(*) AS n, COALESCE(SUM(net_amount), 0) AS total, MAX(created_at) AS last FROM sales WHERE business_id = ?', array((int) $id))->row_array();
        $s30 = $this->db->query('SELECT COUNT(*) AS n, COALESCE(SUM(net_amount), 0) AS total FROM sales WHERE business_id = ? AND created_at >= ?', array((int) $id, $since30))->row_array();
        $p = $this->db->query('SELECT COUNT(*) AS n, COALESCE(SUM(net_amount), 0) AS total FROM purchases WHERE business_id = ?', array((int) $id))->row_array();
        $out += array('sales' => (int) $s['n'], 'sales_total' => (float) $s['total'], 'last_sale' => $s['last'],
            'sales_30d' => (int) $s30['n'], 'sales_30d_total' => (float) $s30['total'], 'purchases' => (int) $p['n'], 'purchases_total' => (float) $p['total']);
        return $out;
    }

    /** Sales per day for the last $days days: [date => [count, total]] */
    public function daily_sales($id, $days = 30)
    {
        $out = array();
        for ($i = $days - 1; $i >= 0; $i--) {
            $out[date('Y-m-d', strtotime("-$i days"))] = array(0, 0.0);
        }
        foreach ($this->db->query('SELECT DATE(created_at) AS d, COUNT(*) AS n, SUM(net_amount) AS t FROM sales WHERE business_id = ? AND created_at >= ? GROUP BY DATE(created_at)',
            array((int) $id, date('Y-m-d', strtotime('-' . ($days - 1) . ' days')))) ->result_array() as $r) {
            $out[$r['d']] = array((int) $r['n'], (float) $r['t']);
        }
        return $out;
    }

    /** The app users of a business, with their role and when they were last online */
    public function users($id)
    {
        $users = $this->db->query("SELECT u.id, u.username, u.firstname, u.lastname, u.email, u.phone, u.active, u.last_login, u.created_at, r.name AS role_name, up.role_id
            FROM users u LEFT JOIN user_permission up ON up.user_id = u.id LEFT JOIN roles r ON r.id = up.role_id
            WHERE u.business_id = ? ORDER BY u.id", array((int) $id))->result_array();
        $online = $this->online_model->user_ids();
        foreach ($users as &$u) {
            $u['online'] = isset($online[(int) $u['id']]) ? $online[(int) $u['id']] : null;
            $u['is_admin'] = (int) $u['role_id'] === 2;
        }
        return $users;
    }

    public function update($id, array $data)
    {
        $allowed = array('name', 'contact_number', 'business_email', 'address', 'city', 'tin', 'vat_number', 'website');
        $data = array_intersect_key($data, array_flip($allowed));
        $this->db->where('id', (int) $id)->update('businesses', $data);
        return true;
    }

    public function suspend($id, $reason)
    {
        $this->db->where('id', (int) $id)->update('businesses', array('status' => 'suspended', 'suspended_reason' => mb_substr(trim((string) $reason), 0, 255),
            'suspended_at' => date('Y-m-d H:i:s')));
        // the business's people are signed out of the app now
        $this->online_model->end_business_sessions($id);
        return true;
    }

    public function reactivate($id)
    {
        $this->db->where('id', (int) $id)->update('businesses', array('status' => 'active', 'suspended_reason' => '', 'suspended_at' => null));
        return true;
    }

    public function crm($id)
    {
        return $this->db->where('business_id', (int) $id)->get('business_crm')->row_array() ?: array('business_id' => (int) $id, 'agent_id' => null, 'stage' => 'onboarding', 'tags' => '', 'next_follow_up' => null);
    }

    public function save_crm($id, array $data)
    {
        $row = array_intersect_key($data, array_flip(array('agent_id', 'stage', 'tags', 'next_follow_up')));
        $row['business_id'] = (int) $id;
        $sets = array();
        foreach ($row as $k => $v) {
            if ($k !== 'business_id') {
                $sets[] = "`$k` = VALUES(`$k`)";
            }
        }
        $this->db->query('INSERT INTO business_crm (' . implode(',', array_map(function ($k) { return "`$k`"; }, array_keys($row))) . ') VALUES ('
            . implode(',', array_fill(0, count($row), '?')) . ') ON DUPLICATE KEY UPDATE ' . implode(', ', $sets), array_values($row));
        return true;
    }

    /** The businesses an agent manages */
    public function agent_business_ids($staff_id)
    {
        return array_map('intval', array_column($this->db->select('business_id')->where('agent_id', (int) $staff_id)->get('business_crm')->result_array(), 'business_id'));
    }

    public function notes($id)
    {
        return $this->db->select('n.*, s.name AS staff_name')->from('business_notes n')->join('admin_staff s', 's.id = n.staff_id', 'left')
            ->where('n.business_id', (int) $id)->order_by('n.id', 'DESC')->limit(100)->get()->result_array();
    }

    public function add_note($id, $staff_id, $kind, $note)
    {
        $this->db->insert('business_notes', array('business_id' => (int) $id, 'staff_id' => (int) $staff_id,
            'kind' => in_array($kind, array('note', 'call', 'visit', 'email'), true) ? $kind : 'note', 'note' => mb_substr(trim((string) $note), 0, 5000),
            'created_at' => date('Y-m-d H:i:s')));
        return (int) $this->db->insert_id();
    }

    /** What happened in the business (its own audit log in the app) */
    public function app_audit($id, $limit = 50)
    {
        if (!$this->db->table_exists('audit_log')) {
            return array();
        }
        return $this->db->select("a.*, CONCAT_WS(' ', u.firstname, u.lastname) AS user_name", false)->from('audit_log a')->join('users u', 'u.id = a.user_id', 'left')
            ->where('a.business_id', (int) $id)->order_by('a.id', 'DESC')->limit($limit)->get()->result_array();
    }

    /** The team's actions on the business */
    public function team_audit($id, $limit = 50)
    {
        return $this->db->select('a.*, s.name AS staff_name')->from('admin_audit a')->join('admin_staff s', 's.id = a.staff_id', 'left')
            ->where('a.business_id', (int) $id)->order_by('a.id', 'DESC')->limit($limit)->get()->result_array();
    }

    /** App user actions: reset the password (a new random one, returned once) */
    public function reset_user_password($business_id, $user_id)
    {
        $user = $this->db->where('id', (int) $user_id)->where('business_id', (int) $business_id)->get('users')->row_array();
        if (!$user) {
            return null;
        }
        $password = substr(str_replace(array('+', '/', '='), '', base64_encode(random_bytes(12))), 0, 10);
        $this->db->where('id', (int) $user_id)->update('users', array('password' => password_hash($password, PASSWORD_BCRYPT)));
        $this->online_model->end_user_sessions($user_id);
        return array('user' => $user, 'password' => $password);
    }

    public function set_user_active($business_id, $user_id, $active)
    {
        $this->db->where('id', (int) $user_id)->where('business_id', (int) $business_id)->update('users', array('active' => $active ? 1 : 0));
        if (!$active) {
            $this->online_model->end_user_sessions($user_id);
        }
        return $this->db->affected_rows() >= 0;
    }
}
