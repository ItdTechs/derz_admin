<?php $t = function ($p) { return base_url('theme/assets/' . $p) . '?v=' . @filemtime(FCPATH . 'theme/assets/' . $p); }; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($page_title) ?> · DerZ Admin</title>
<link rel="shortcut icon" href="<?= base_url('theme/assets/images/favicon.png') ?>">
<!-- the DerZ app's sign-in page style (copied in theme/assets/login_page) -->
<link href="https://fonts.googleapis.com/css?family=Signika:300,400,600,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $t('vendor/bootstrap/css/bootstrap.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/font-awesome/css/all.min.css') ?>">
<link rel="stylesheet" href="<?= $t('login_page/css/style.css') ?>">
<script src="<?= $t('vendor/jquery/jquery.min.js') ?>"></script>
<style>
    .sign-area .admin-badge { display: inline-block; margin-top: 6px; padding: 3px 12px; border-radius: 20px; background: #038F5C; color: #fff; font-size: 12px; letter-spacing: .5px; }
    .sign-area .auth-note { color: #888; font-size: 12px; margin-top: 14px; }
</style>
</head>
<body>
