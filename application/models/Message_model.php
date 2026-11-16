<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Talking to the businesses: announcements shown at the top of the app (platform_announcements,
 * read by the app) and Telegram broadcasts to the chats the businesses linked to the DerZ bot.
 */
class Message_model extends CI_Model
{
    public function ready()
    {
        return $this->db->table_exists('platform_announcements');
    }

    /** Audience choices: key => label */
    public function audiences()
    {
        $out = array('all' => 'All businesses', 'paid' => 'Paying businesses', 'trial' => 'Businesses on trial', 'expiring' => 'Ending within 14 days', 'expired' => 'Expired businesses');
        foreach ($this->db->select('id, name')->order_by('package_order')->get('subscription_packages')->result_array() as $p) {
            $out['package:' . $p['id']] = 'Package: ' . $p['name'];
        }
        return $out;
    }

    /** Business ids of an audience */
    public function audience_ids($audience)
    {
        $now = date('Y-m-d H:i:s');
        $sql = 'SELECT b.id FROM businesses b LEFT JOIN subscriptions s ON s.id = (SELECT MAX(s2.id) FROM subscriptions s2 WHERE s2.business_id = b.id)';
        $binds = array();
        if ($audience === 'paid') {
            $sql .= ' WHERE s.expire_date >= ? AND s.free_trial = 0';
            $binds[] = $now;
        } elseif ($audience === 'trial') {
            $sql .= ' WHERE s.expire_date >= ? AND s.free_trial = 1';
            $binds[] = $now;
        } elseif ($audience === 'expiring') {
            $sql .= ' WHERE s.expire_date >= ? AND s.expire_date <= ?';
            array_push($binds, $now, date('Y-m-d H:i:s', strtotime('+14 days')));
        } elseif ($audience === 'expired') {
            $sql .= ' WHERE s.expire_date IS NULL OR s.expire_date < ?';
            $binds[] = $now;
        } elseif (strpos($audience, 'package:') === 0) {
            $sql .= ' WHERE s.expire_date >= ? AND s.package_id = ?';
            array_push($binds, $now, (int) substr($audience, 8));
        }
        return array_map('intval', array_column($this->db->query($sql, $binds)->result_array(), 'id'));
    }

    public function announcements()
    {
        return $this->db->select('a.*, s.name AS staff_name')->from('platform_announcements a')->join('admin_staff s', 's.id = a.created_by', 'left')
            ->order_by('a.id', 'DESC')->get()->result_array();
    }

    public function announcement($id)
    {
        return $this->db->where('id', (int) $id)->get('platform_announcements')->row_array() ?: null;
    }

    public function save($id, array $d)
    {
        $row = array(
            'title' => mb_substr(trim((string) $d['title']), 0, 150), 'body' => mb_substr(trim((string) $d['body']), 0, 2000),
            'level' => in_array($d['level'] ?? '', array('info', 'success', 'warning', 'danger'), true) ? $d['level'] : 'info',
            'audience' => array_key_exists($d['audience'] ?? '', $this->audiences()) ? $d['audience'] : 'all',
            'starts_at' => !empty($d['starts_at']) ? date('Y-m-d H:i:s', strtotime($d['starts_at'])) : date('Y-m-d H:i:s'),
            'ends_at' => !empty($d['ends_at']) ? date('Y-m-d 23:59:59', strtotime($d['ends_at'])) : null,
            'active' => !empty($d['active']) ? 1 : 0,
        );
        if ($id) {
            $this->db->where('id', (int) $id)->update('platform_announcements', $row);
            return (int) $id;
        }
        $row['created_by'] = (int) $this->auth->id();
        $row['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('platform_announcements', $row);
        return (int) $this->db->insert_id();
    }

    public function delete($id)
    {
        $this->db->where('id', (int) $id)->delete('platform_announcements');
    }

    /** The Telegram chats of an audience's businesses */
    public function chats($audience)
    {
        if (!$this->db->table_exists('telegram_recipients')) {
            return array();
        }
        $ids = $this->audience_ids($audience);
        return $ids ? $this->db->where_in('business_id', $ids)->get('telegram_recipients')->result_array() : array();
    }
}
