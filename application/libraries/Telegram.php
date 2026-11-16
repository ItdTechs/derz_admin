<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * The DerZ Telegram bot (the same bot as the app; its token is a platform setting). The bot's
 * webhook stays on the app (telegram/webhook there), which links chats with /start codes.
 */
class Telegram
{
    protected $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
    }

    public function token()
    {
        return trim((string) platform_setting('telegram_bot_token', ''));
    }

    public function username()
    {
        return trim((string) platform_setting('telegram_bot_username', ''));
    }

    public function ready()
    {
        return $this->token() !== '' && $this->CI->db->table_exists('telegram_recipients');
    }

    public function api($method, array $params = array(), $token = null)
    {
        $token = $token === null ? $this->token() : $token;
        if ($token === '' || !function_exists('curl_init')) {
            return array('ok' => false, 'description' => $token === '' ? 'No bot token saved.' : 'cURL is not available.');
        }
        $ch = curl_init('https://api.telegram.org/bot' . $token . '/' . $method);
        curl_setopt_array($ch, array(CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($params), CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10));
        $body = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        $json = $body === false ? null : json_decode($body, true);
        return is_array($json) ? $json : array('ok' => false, 'description' => $error ?: 'No answer from Telegram.');
    }

    public function send($chat_id, $text)
    {
        $r = $this->api('sendMessage', array('chat_id' => $chat_id, 'text' => $text, 'disable_web_page_preview' => 'true'));
        return !empty($r['ok']) ? true : (string) ($r['description'] ?? 'error');
    }

    /** Save a new token: checked with Telegram, webhook pointed at the app (https). true or the error. */
    public function setup($token)
    {
        $me = $this->api('getMe', array(), $token);
        if (empty($me['ok']) || empty($me['result']['username'])) {
            return (string) ($me['description'] ?? 'Telegram did not accept the token.');
        }
        $values = array('telegram_bot_token' => $token, 'telegram_bot_username' => $me['result']['username'], 'telegram_webhook' => '0');
        $hook_url = app_url('telegram/webhook');
        if (strpos($hook_url, 'https://') === 0) {
            $secret = bin2hex(random_bytes(16));
            $hook = $this->api('setWebhook', array('url' => $hook_url, 'secret_token' => $secret, 'allowed_updates' => '["message"]'), $token);
            if (!empty($hook['ok'])) {
                $values['telegram_webhook'] = '1';
                $values['telegram_webhook_secret'] = $secret;
            }
        } else {
            $this->api('deleteWebhook', array(), $token);
        }
        save_platform_settings($values);
        return true;
    }

    /** The team's chats (business 0): new sign-ups and payment requests arrive there */
    public function team_chats()
    {
        return $this->CI->db->table_exists('telegram_recipients') ? $this->CI->db->where('business_id', 0)->order_by('id')->get('telegram_recipients')->result_array() : array();
    }

    /** A one-time link to add a chat to the team's alerts (the app's webhook links it). */
    public function team_connect_links()
    {
        $db = $this->CI->db;
        $db->where('expires_at <', date('Y-m-d H:i:s'))->delete('telegram_link_codes');
        $code = bin2hex(random_bytes(12));
        $db->insert('telegram_link_codes', array('code' => $code, 'business_id' => 0, 'user_id' => 0, 'expires_at' => date('Y-m-d H:i:s', strtotime('+30 minutes'))));
        $bot = $this->username();
        return array('private' => 'https://t.me/' . $bot . '?start=' . $code, 'group' => 'https://t.me/' . $bot . '?startgroup=' . $code);
    }

    public function remove_team_chat($id)
    {
        $this->CI->db->where('business_id', 0)->where('id', (int) $id)->delete('telegram_recipients');
    }

    /** A message to the team's chats: how many got it */
    public function notify_team($text)
    {
        $sent = 0;
        foreach ($this->team_chats() as $c) {
            if ($this->send($c['chat_id'], $text) === true) {
                $sent++;
            }
        }
        return $sent;
    }
}
