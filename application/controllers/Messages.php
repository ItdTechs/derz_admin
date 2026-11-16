<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** Announcements shown in the app, and Telegram broadcasts to the businesses' linked chats. */
class Messages extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->need('messages.manage');
        $this->load->model('message_model');
        $this->load->library('telegram');
    }

    public function index()
    {
        $edit = (int) $this->input->get('edit');
        $audiences = $this->message_model->audiences();
        $counts = array();
        foreach (array_keys($audiences) as $a) {
            $counts[$a] = count($this->message_model->audience_ids($a));
        }
        $this->render('messages/index', array(
            'title' => 'Announcements', 'ready' => $this->message_model->ready(),
            'items' => $this->message_model->ready() ? $this->message_model->announcements() : array(),
            'edit' => $edit && $this->message_model->ready() ? $this->message_model->announcement($edit) : null,
            'audiences' => $audiences, 'counts' => $counts, 'telegram' => $this->telegram->ready(),
        ));
    }

    public function save()
    {
        $this->need_post();
        if (!$this->message_model->ready()) {
            $this->json(array('status' => 'fail', 'message' => 'Run SQL step 45 first.'));
        }
        $errors = array();
        if (trim((string) $this->input->post('title')) === '') {
            $errors['title'] = 'Enter a title.';
        }
        if (trim((string) $this->input->post('body')) === '') {
            $errors['body'] = 'Write the message.';
        }
        if ($errors) {
            $this->json(array('status' => 'fail', 'errors' => $errors));
        }
        $id = (int) $this->input->post('id');
        $new = $this->message_model->save($id, $this->input->post());
        audit($id ? 'update' : 'create', 'announcement', $new, 'Announcement: ' . trim((string) $this->input->post('title')) . ' → ' . $this->input->post('audience'));
        $this->flash('success', 'Announcement saved. It shows at the top of the app for its audience.');
        $this->json(array('status' => 'success', 'url' => site_url('messages')));
    }

    public function delete($id = 0)
    {
        $this->need_post();
        $this->message_model->delete((int) $id);
        audit('delete', 'announcement', (int) $id, 'Announcement deleted');
        $this->flash('success', 'Announcement deleted.');
        $this->json(array('status' => 'success', 'url' => site_url('messages')));
    }

    /** Send a Telegram message to the linked chats of an audience (at most 500 chats per send) */
    public function broadcast()
    {
        $this->need_post();
        if (!$this->telegram->ready()) {
            $this->json(array('status' => 'fail', 'message' => 'Set up the Telegram bot first (Settings).'));
        }
        $text = trim((string) $this->input->post('text'));
        if ($text === '' || mb_strlen($text) > 3500) {
            $this->json(array('status' => 'fail', 'errors' => array('text' => 'Write a message of up to 3,500 characters.')));
        }
        $audience = (string) $this->input->post('audience');
        $chats = array_slice($this->message_model->chats($audience), 0, 500);
        @set_time_limit(300);
        $sent = 0;
        $failed = 0;
        foreach ($chats as $c) {
            if ($this->telegram->send($c['chat_id'], $text) === true) {
                $sent++;
            } else {
                $failed++;
            }
            usleep(40000); // Telegram allows about 30 messages a second
        }
        audit('broadcast', 'telegram', 0, 'Telegram broadcast to ' . $audience . ': ' . $sent . ' sent, ' . $failed . ' failed', array('text' => $text));
        $this->flash($sent ? 'success' : 'error', $sent . ' chats got the message' . ($failed ? ', ' . $failed . ' could not be reached (chat removed or bot blocked)' : '') . '.');
        $this->json(array('status' => 'success', 'url' => site_url('messages')));
    }
}
