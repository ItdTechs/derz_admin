<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Translations (copied from the DerZ app): the `languages` table (one row per word: word = key, english, and one
 * column per language in language_list). Shared by every business and by the landing page.
 */
class Translation_model extends CI_Model
{
    /** The language columns that exist in `languages`, other than english: [lang_field => language row]. */
    public function language_columns()
    {
        $columns = array();
        foreach ($this->db->order_by('id', 'ASC')->get('language_list')->result_array() as $l) {
            if ($l['lang_field'] !== 'english' && preg_match('/^[a-zA-Z0-9_]+$/', $l['lang_field']) && $this->db->field_exists($l['lang_field'], 'languages')) {
                $columns[$l['lang_field']] = $l;
            }
        }
        return $columns;
    }

    /** Search conditions: $q in the key, the English or any translation; $missing = some translation is empty. */
    private function filter($q, $missing, array $columns)
    {
        if ($q !== '') {
            $this->db->group_start()->like('word', $q)->or_like('english', $q);
            foreach (array_keys($columns) as $c) {
                $this->db->or_like($c, $q);
            }
            $this->db->group_end();
        }
        if ($missing && $columns) {
            $this->db->group_start();
            foreach (array_keys($columns) as $c) {
                $this->db->or_where("(`" . $c . "` IS NULL OR `" . $c . "` = '')", null, false);
            }
            $this->db->group_end();
        }
    }

    public function count_words($q, $missing, array $columns)
    {
        $this->filter($q, $missing, $columns);
        return (int) $this->db->count_all_results('languages');
    }

    public function words($q, $missing, array $columns, $limit, $offset)
    {
        $this->db->select(array_merge(array('id', 'word', 'english'), array_keys($columns)));
        $this->filter($q, $missing, $columns);
        return $this->db->order_by('word', 'ASC')->limit($limit, $offset)->get('languages')->result_array();
    }

    /** How many words each language still misses: [lang_field => count]. */
    public function missing_counts(array $columns)
    {
        $counts = array();
        foreach (array_keys($columns) as $c) {
            $counts[$c] = (int) $this->db->where("(`" . $c . "` IS NULL OR `" . $c . "` = '')", null, false)->count_all_results('languages');
        }
        return $counts;
    }

    /** Change the English or one translation of a word. Returns '' or an error message. */
    public function save_text($word, $field, $value)
    {
        $columns = $this->language_columns();
        if ($field !== 'english' && !isset($columns[$field])) {
            return 'This word is not there.';
        }
        if ($this->db->where('word', $word)->count_all_results('languages') === 0) {
            return 'This word is not there.';
        }
        if ($field === 'english' && trim($value) === '') {
            return 'The English text is required.';
        }
        $this->db->where('word', $word)->update('languages', array($field => $value));
        return '';
    }

    /** The key DerZ gives a text: lower case, underscores (as translate() keys are written). */
    public static function key_of($text)
    {
        return rtrim(substr(trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($text)), '_'), 0, 100), '_');
    }

    /** Add a word. Returns '' or an error message. */
    public function add_word($word, $english, array $texts)
    {
        if ($word === '' || !preg_match('/^[a-z0-9_]{1,100}$/', $word)) {
            return 'Use lower case letters, numbers and underscores.';
        }
        if (trim($english) === '') {
            return 'The English text is required.';
        }
        if ($this->db->where('word', $word)->count_all_results('languages') > 0) {
            return 'This word already exists.';
        }
        $row = array('word' => $word, 'english' => $english);
        foreach (array_keys($this->language_columns()) as $c) {
            $row[$c] = isset($texts[$c]) ? (string) $texts[$c] : '';
        }
        $this->db->insert('languages', $row);
        return '';
    }
}
