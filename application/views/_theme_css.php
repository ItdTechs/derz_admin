<?php $t = function ($p) { return base_url('theme/assets/' . $p) . '?v=' . @filemtime(FCPATH . 'theme/assets/' . $p); }; ?>
<!-- the DerZ app's theme (copied in theme/assets: keep it in step with the app) -->
<link href="https://fonts.googleapis.com/css?family=Signika:300,400,600,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $t('vendor/bootstrap/css/bootstrap.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/font-awesome/css/all.min.css') ?>">
<link rel="stylesheet" href="<?= $t('vendor/datatables/media/css/dataTables.bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= $t('vendor/nanoscroller/nanoscroller.css') ?>">
<link rel="stylesheet" href="<?= $t('css/custom-style.css') ?>">
<link rel="stylesheet" href="<?= $t('css/skins/default.css') ?>">
<link rel="stylesheet" href="<?= $t('vendor/sweetalert/sweetalert-custom.css') ?>">
<link rel="stylesheet" href="<?= $t('css/mobile-nav.css') ?>">
<link rel="stylesheet" href="<?= $t('css/ramom.css') ?>">
<link rel="stylesheet" href="<?= $t('css/skins/square-borders.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/admin.css') . '?v=' . @filemtime(FCPATH . 'assets/css/admin.css') ?>">
<script src="<?= $t('vendor/jquery/jquery.min.js') ?>"></script>
<script src="<?= $t('vendor/modernizr/modernizr.js') ?>"></script>
