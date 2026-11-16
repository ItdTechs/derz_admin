<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** Every word of the app and the landing page, in every language (the shared `languages` table). */
class Translations extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->need('translations.manage');
        $this->load->model('translation_model');
    }

    public function index()
    {
        $columns = $this->translation_model->language_columns();
        $q = trim((string) $this->input->get('q', true));
        $missing = $this->input->get('missing') === '1';
        $per = 50;
        $total = $this->translation_model->count_words($q, $missing, $columns);
        $page = max(1, (int) $this->input->get('page'));
        $this->render('translations/index', array('title' => 'Translations', 'columns' => $columns, 'q' => $q, 'missing' => $missing, 'total' => $total, 'page' => $page, 'per' => $per,
            'words' => $this->translation_model->words($q, $missing, $columns, $per, ($page - 1) * $per), 'missing_counts' => $this->translation_model->missing_counts($columns)));
    }

    public function save()
    {
        $this->need_post();
        $word = (string) $this->input->post('word');
        $field = (string) $this->input->post('field');
        $error = $this->translation_model->save_text($word, $field, (string) $this->input->post('value'));
        if ($error !== '') {
            $this->json(array('status' => 'fail', 'message' => $error));
        }
        audit('update', 'translation', 0, 'Translation: ' . $word . ' (' . $field . ')');
        $this->json(array('status' => 'success'));
    }

    public function add()
    {
        $this->need_post();
        $english = trim((string) $this->input->post('english'));
        $word = trim((string) $this->input->post('word'));
        $word = $word !== '' ? strtolower($word) : Translation_model::key_of($english);
        $error = $this->translation_model->add_word($word, $english, (array) $this->input->post('text'));
        if ($error !== '') {
            $this->json(array('status' => 'fail', 'errors' => array('word' => $error)));
        }
        audit('create', 'translation', 0, 'Word added: ' . $word);
        $this->flash('success', 'Word added: ' . $word);
        $this->json(array('status' => 'success', 'url' => site_url('translations?q=' . rawurlencode($word))));
    }
}
