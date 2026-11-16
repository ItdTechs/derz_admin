<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** Platform settings (trial, support contacts, payment instructions, maintenance mode, Telegram). */
class Settings extends Admin_Controller
{
    protected $keys = array('trial_days', 'payment_instructions', 'support_phone', 'support_email', 'support_telegram', 'telegram_login_client_id', 'maintenance_message', 'maintenance_until');

    public function __construct()
    {
        parent::__construct();
        $this->need('settings.manage');
        $this->load->library('telegram');
    }

    public function index()
    {
        if ($this->input->method() === 'post') {
            $v = array();
            foreach ($this->keys as $k) {
                $v[$k] = trim((string) $this->input->post($k));
            }
            $errors = array();
            if (!ctype_digit($v['trial_days']) || (int) $v['trial_days'] < 1 || (int) $v['trial_days'] > 365) {
                $errors['trial_days'] = 'Enter 1 to 365 days.';
            }
            if ($v['support_email'] !== '' && !filter_var($v['support_email'], FILTER_VALIDATE_EMAIL)) {
                $errors['support_email'] = 'Enter a valid email.';
            }
            foreach (array('payment_instructions' => 1000, 'maintenance_message' => 500, 'support_phone' => 30, 'support_telegram' => 100, 'maintenance_until' => 60, 'telegram_login_client_id' => 30) as $k => $max) {
                if (mb_strlen($v[$k]) > $max) {
                    $errors[$k] = 'At most ' . $max . ' characters.';
                }
            }
            $token = trim((string) $this->input->post('telegram_bot_token'));
            if (!$errors && $token !== '') {
                $r = preg_match('/^\d{5,15}:[A-Za-z0-9_-]{20,60}$/', $token) ? $this->telegram->setup($token) : 'This is not a bot token.';
                if ($r !== true) {
                    $errors['telegram_bot_token'] = $r;
                }
            }
            if ($errors) {
                $this->json(array('status' => 'fail', 'errors' => $errors));
            }
            $was_on = platform_setting('maintenance_mode', '0') === '1';
            $v['maintenance_mode'] = $this->input->post('maintenance_mode') ? '1' : '0';
            save_platform_settings($v);
            $secret = trim((string) $this->input->post('telegram_login_client_secret'));
            if ($secret !== '') {
                save_platform_settings(array('telegram_login_client_secret' => $secret));
            }
            audit('settings', 'platform', 0, 'Platform settings saved' . ($token !== '' ? ' (new Telegram bot token)' : ''), array_diff_key($v, array('payment_instructions' => 1)));
            if ($was_on !== ($v['maintenance_mode'] === '1')) {
                audit('settings', 'maintenance', 0, $v['maintenance_mode'] === '1' ? 'Maintenance mode switched ON' : 'Maintenance mode switched off');
            }
            $this->flash('success', 'Settings saved.');
            $this->json(array('status' => 'success', 'url' => site_url('settings')));
        }
        $s = array();
        foreach ($this->keys as $k) {
            $s[$k] = platform_setting($k, $k === 'trial_days' ? '15' : '');
        }
        $s['maintenance_mode'] = platform_setting('maintenance_mode', '0') === '1';
        $this->render('settings/index', array('title' => 'Settings', 's' => $s, 'bot' => $this->telegram->username(), 'webhook' => platform_setting('telegram_webhook', '0') === '1',
            'login_secret_saved' => trim((string) platform_setting('telegram_login_client_secret', '')) !== '', 'team_chats' => $this->telegram->team_chats()));
    }

    /** A one-time link to add a chat to the team's alerts (JSON) */
    public function telegram_connect()
    {
        $this->need_post();
        if (!$this->telegram->ready()) {
            $this->json(array('status' => 'fail', 'message' => 'Save the bot token first.'));
        }
        $this->json(array('status' => 'success') + $this->telegram->team_connect_links());
    }

    public function telegram_remove($id = 0)
    {
        $this->need_post();
        $this->telegram->remove_team_chat((int) $id);
        audit('delete', 'telegram_chat', (int) $id, 'Removed a team Telegram chat');
        $this->json(array('status' => 'success', 'url' => site_url('settings') . '#telegram'));
    }

    public function telegram_test()
    {
        $this->need_post();
        $n = $this->telegram->notify_team('✅ DerZ Admin: test message from ' . $this->data['me']['name'] . '.');
        $this->json($n ? array('status' => 'success', 'message' => 'Sent to ' . $n . ' chat' . ($n > 1 ? 's' : '') . '.') : array('status' => 'fail', 'message' => 'No team chat got it.'));
    }
}
