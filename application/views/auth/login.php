<?php $this->load->view('auth/_head', array('page_title' => 'Sign in')); ?>
<div class="auth-main">
    <div class="container">
        <div class="slideIn">
            <div class="col-lg-4 col-lg-offset-1 col-md-4 col-md-offset-1 col-sm-12 col-xs-12 no-padding fitxt-center">
                <div class="image-area">
                    <div class="content">
                        <div class="image-hader">
                            <h2 style="margin-bottom: 15px">DerZ Admin</h2>
                            <h4 style="color: white;">The DerZ team's portal: businesses, billing, analytics</h4>
                        </div>
                        <div class="center img-hol-p"></div>
                        <div class="sign-footer"><p style="color: white"><i class="fas fa-shield-halved"></i> For the DerZ team only</p></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-lg-offset-right-1 col-md-6 col-md-offset-right-1 col-sm-12 col-xs-12 no-padding">
                <div class="sign-area">
                    <div class="sign-hader text-center">
                        <img src="<?= base_url('theme/uploads/app_image/dash-logo.png') ?>" alt="DerZ" style="max-height:60px">
                        <div><span class="admin-badge">ADMIN</span></div>
                    </div><br>
                    <?php if (!empty($flash)): ?><div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?>"><?= e($flash['message']) ?></div><?php endif; ?>
                    <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-circle-exclamation"></i> <?= e($error) ?></div><?php endif; ?>
                    <?= form_open('login') ?>
                        <div class="form-group">
                            <div class="input-group input-group-icon">
                                <span class="input-group-addon"><span class="icon"><i class="fas fa-phone-alt"></i></span></span>
                                <input type="text" class="form-control" id="email" name="email" value="<?= e($email) ?>" placeholder="Phone or email" autocomplete="username" required autofocus>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="input-group input-group-icon">
                                <span class="input-group-addon"><span class="icon"><i class="fas fa-lock"></i></span></span>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Password" autocomplete="current-password" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <button type="submit" id="btn_submit" class="btn btn-block btn-round"><i class="fas fa-sign-in-alt"></i> Sign in</button>
                        </div>
                        <p class="auth-note text-center"><i class="fas fa-shield-halved"></i> Too many wrong passwords pause signing in for <?= (int) config_item('login_lock_minutes') ?> minutes.</p>
                    <?= form_close() ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="<?= base_url('theme/assets/vendor/bootstrap/js/bootstrap.js') ?>"></script>
<script src="<?= base_url('theme/assets/login_page/js/jquery.backstretch.min.js') ?>"></script>
<script>jQuery(function ($) { $.backstretch(['<?= base_url('theme/uploads/login_image/slider_1.jpg') ?>']); });</script>
</body></html>
