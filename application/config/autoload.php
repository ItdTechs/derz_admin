<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/* The landing page: one database connection (the DerZ database, config/database.php) for its texts,
   languages and plans; no sessions. */
$autoload['packages'] = array();
$autoload['libraries'] = array('database'); // session and auth: loaded by MY_Controller (sessions stay in files until SQL step 45 is run)
$autoload['drivers'] = array();
$autoload['helper'] = array('url', 'form', 'admin');
$autoload['config'] = array('admin');
$autoload['language'] = array();
$autoload['model'] = array();
