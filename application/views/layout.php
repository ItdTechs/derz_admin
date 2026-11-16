<?php
/**
 * The portal's page on the DerZ app's theme (theme/assets: the app's Bootstrap, custom-style, skin,
 * ramom and square-borders; the same header, sidebar and page-title strip as the app).
 * $nav: Admin_Controller::navigation(), $badges, $title, $content.
 */
$uri = uri_string();
$is_active = function ($link) use ($uri) {
    $link = trim($link, '/');
    if ($link === 'dashboard') {
        return $uri === '' || $uri === 'dashboard';
    }
    if (in_array($uri, array('billing/payments', 'staff/roles'), true)) {
        return $uri === $link;
    }
    return $uri === $link || strpos($uri . '/', $link . '/') === 0;
};
$theme = function ($path) { return base_url('theme/' . $path); };
$flash = $this->session->flashdata('flash');
// the app's sidebar: items with an icon chip at the top level, the rest in groups
$top = array('Overview', 'Customers');
?><!doctype html>
<html class="fixed sidebar-left-sm sidebar-light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#038F5C">
<meta name="csrf-name" content="<?= e($this->security->get_csrf_token_name()) ?>">
<meta name="csrf-hash" content="<?= e($this->security->get_csrf_hash()) ?>">
<title><?= e($title ?? 'DerZ Admin') ?> · DerZ Admin</title>
<link rel="shortcut icon" href="<?= $theme('assets/images/favicon.png') ?>">
<script>try { if (localStorage.getItem('derz-admin-night') === '1') { document.documentElement.className = document.documentElement.className.replace('sidebar-light', 'dark'); } } catch (e) {}</script>
<?php $this->load->view('_theme_css'); ?>
</head>
<body class="loading-overlay-showing" data-loading-overlay data-base="<?= e(base_url()) ?>">
<div class="loading-overlay dark"><div class="ring-loader">Loading<span></span></div></div>
<section class="body">
    <header class="header">
        <div class="logo-env">
            <a href="<?= site_url('') ?>" class="logo"><img src="<?= $theme('uploads/app_image/dash-logo.png') ?>" height="40" alt="DerZ"></a>
            <div class="visible-xs toggle-sidebar-left" data-toggle-class="sidebar-left-opened" data-target="html" data-fire-event="sidebar-left-opened"><i class="fa fa-bars" aria-label="Menu"></i></div>
        </div>
        <div class="header-left hidden-xs">
            <ul class="header-menu">
                <li><div class="header-menu-icon sidebar-toggle" data-toggle-class="sidebar-left-collapsed" data-target="html" data-fire-event="sidebar-left-toggle"><i class="fas fa-bars"></i></div></li>
                <li><div class="header-menu-icon s-expand"><i class="fas fa-expand"></i></div></li>
            </ul>
            <?php if (can('businesses.view')): ?>
            <span class="separator hidden-sm"></span>
            <form class="search nav-form" action="<?= site_url('businesses') ?>" method="get" role="search">
                <div class="input-group input-search">
                    <input type="text" class="form-control" name="q" placeholder="Find a business…" value="<?= e($uri === 'businesses' ? (string) $this->input->get('q') : '') ?>">
                    <span class="input-group-btn"><button class="btn btn-default" type="submit"><i class="fa fa-search"></i></button></span>
                </div>
            </form>
            <?php endif; ?>
            <span class="separator hidden-sm"></span>
            <div class="topbar-actions">
                <?php if (can('businesses.view')): ?><a href="<?= site_url('businesses') ?>" class="topbar-action topbar-action-primary"><i class="fas fa-building"></i><span>Businesses</span></a><?php endif; ?>
                <?php if (can('billing.view')): ?><a href="<?= site_url('billing') ?>" class="topbar-action" title="Payment requests"><i class="fas fa-file-invoice-dollar"></i><span>Requests</span></a><?php endif; ?>
                <?php if (can('online.view')): ?><a href="<?= site_url('online') ?>" class="topbar-action" title="Online now"><i class="fas fa-signal"></i><span>Online</span></a><?php endif; ?>
            </div>
        </div>
        <div class="header-right">
            <ul class="header-menu">
                <li class="topbar-hard-refresh"><a style="cursor:pointer" class="header-menu-icon" onclick="location.reload(true)" title="Refresh"><i class="fas fa-sync"></i></a></li>
                <li><a href="#" class="header-menu-icon js-night" title="Night mode"><i class="fas fa-moon"></i></a></li>
                <li><a href="<?= e(app_url()) ?>" class="header-menu-icon" target="_blank" rel="noopener" title="Open the DerZ app"><i class="fas fa-up-right-from-square"></i></a></li>
                <?php if (can('billing.view')): ?>
                <li class="topbar-alerts">
                    <a href="<?= site_url('billing') ?>" class="header-menu-icon topbar-alerts-toggle" title="Payment requests waiting"><i class="fas fa-bell"></i>
                        <?php if ($badges['pending']): ?><span class="badge topbar-alerts-badge"><?= $badges['pending'] > 9 ? '9+' : (int) $badges['pending'] ?></span><?php endif; ?></a>
                </li>
                <?php endif; ?>
            </ul>
            <span class="separator"></span>
            <div id="userbox" class="userbox">
                <a href="#" data-toggle="dropdown"><figure class="profile-picture"><img src="<?= $theme('uploads/app_image/defualt_profile.png') ?>" alt="" class="img-circle" height="35"></figure></a>
                <div class="dropdown-menu">
                    <ul class="dropdown-user list-unstyled">
                        <li class="user-p-box">
                            <div class="dw-user-box">
                                <div class="u-img"><img src="<?= $theme('uploads/app_image/defualt_profile.png') ?>" alt=""></div>
                                <div class="u-text">
                                    <h4><?= e($me['name']) ?></h4>
                                    <p class="text-muted"><?= e($me['role_name']) ?></p>
                                    <a href="<?= site_url('logout') ?>" class="btn btn-danger btn-xs"><i class="fas fa-sign-out-alt"></i> Sign out</a>
                                </div>
                            </div>
                        </li>
                        <li role="separator" class="divider"></li>
                        <li><a href="<?= site_url('account') ?>"><i class="fas fa-user-shield"></i> My account</a></li>
                        <li><a href="<?= site_url('account/password') ?>"><i class="fas fa-mars-stroke-h"></i> Change password</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <div class="inner-wrapper">
        <aside id="sidebar-left" class="sidebar-left">
            <div class="sidebar-header"><div class="sidebar-title"><p><i class="fas fa-user-shield" style="margin-right:6px;opacity:.8"></i>DerZ Admin</p></div></div>
            <div class="nano">
                <div class="nano-content">
                    <nav id="menu" class="nav-main" role="navigation">
                        <ul class="nav nav-main">
                        <?php foreach ($nav as $section => $links): ?>
                            <?php if (in_array($section, $top, true)): ?>
                                <?php foreach ($links as $l): $count = $l[4] !== '' ? (int) $badges[$l[4]] : 0; ?>
                                <li class="<?= $is_active($l[0]) ? 'nav-active' : '' ?>"><a href="<?= site_url($l[0]) ?>"><i class="fas <?= $l[1] ?>"></i><span><?= e($l[2]) ?></span>
                                    <?php if ($count): ?><span class="label label-<?= $l[4] === 'online' ? 'success' : 'danger' ?> nav-count"><?= $count ?></span><?php endif; ?></a></li>
                                <?php endforeach; ?>
                            <?php else: $open = (bool) array_filter($links, function ($l) use ($is_active) { return $is_active($l[0]); }); ?>
                                <li class="nav-parent<?= $open ? ' nav-expanded nav-active' : '' ?>">
                                    <a><i class="fas <?= $section === 'Team' ? 'fa-users-gear' : 'fa-sliders' ?>"></i><span><?= e($section) ?></span></a>
                                    <ul class="nav nav-children">
                                        <?php foreach ($links as $l): ?>
                                        <li class="<?= $is_active($l[0]) ? 'nav-active' : '' ?>"><a href="<?= site_url($l[0]) ?>"><span><i class="fas <?= $l[1] ?>" aria-hidden="true"></i><?= e($l[2]) ?></span></a></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        </aside>

        <section role="main" class="content-body">
            <header class="page-header">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-6">
                            <a class="page-title-icon" href="<?= site_url('') ?>"><i class="fas fa-home"></i></a>
                            <h2 class="page-title"><?= e($title ?? 'Dashboard') ?></h2>
                        </div>
                        <div class="col-md-6 text-right hidden-xs">
                            <div class="subscription-header">
                                <div class="status-badge active">
                                    <i class="fas fa-user-shield"></i>
                                    <span class="plan-label"><?= e($me['role_name']) ?>: <strong class="plan-name text-primary"><?= e(strtok($me['name'], ' ')) ?></strong></span>
                                    <?php if (can('online.view')): ?>
                                    <div class="status-badge-inner active"><a href="<?= site_url('online') ?>" class="plan-label-inner">&nbsp;&nbsp;<i class="fas fa-signal"></i> <b><?= (int) $badges['online'] ?></b> online</a></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            <div id="main-workspace">
                <?php if (platform_setting('maintenance_mode', '0') === '1'): ?>
                <div class="alert alert-danger"><i class="fas fa-tools"></i> <strong>Maintenance mode is on</strong>: businesses see the maintenance page.
                    <?php if (can('settings.manage')): ?><a class="btn btn-default btn-xs ml-sm" href="<?= site_url('settings') ?>">Switch it off</a><?php endif; ?></div>
                <?php endif; ?>
                <?php if ($flash): ?><span id="flash" hidden data-type="<?= e($flash['type']) ?>" data-message="<?= e($flash['message']) ?>"></span><?php endif; ?>
                <?= $content ?>
            </div>
        </section>
    </div>
</section>
<?php $this->load->view('_theme_js'); ?>
</body>
</html>
