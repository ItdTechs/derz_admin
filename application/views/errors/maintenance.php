<?php
// Maintenance mode (Platform > Settings; maintenance_gate()): $message, $until (optional), $contacts (support_contacts())
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= html_escape(translate('under_maintenance')) ?> - DerZ</title>
    <link rel="shortcut icon" href="<?= base_url('assets/images/favicon.png') ?>">
    <link href="https://fonts.googleapis.com/css?family=Signika:300,400,600,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand: #038F5C;
            --brand-soft: #e6f4ee;
            --bg: #f4f7f6;
            --card: #ffffff;
            --text: #2f3a35;
            --muted: #6c7a73;
            --line: #dfe7e3;
            --metal: #b3c2bb;
            --metal-dark: #7f938a;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --brand: #2fbf86;
                --brand-soft: rgba(47, 191, 134, .14);
                --bg: #151a18;
                --card: #1d2421;
                --text: #e4ebe7;
                --muted: #9aaba3;
                --line: #2c3632;
                --metal: #4a5a53;
                --metal-dark: #6f8279;
            }
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Signika', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 24px 16px;
        }
        .maintenance-container { text-align: center; max-width: 620px; width: 100%; }
        .brand { display: inline-flex; align-items: center; gap: 10px; margin-bottom: 18px; font-size: 22px; font-weight: 700; color: var(--brand); letter-spacing: .5px; }
        .brand img { width: 34px; height: 34px; border-radius: 8px; }

        /* the illustration of the sample, in the DerZ colours */
        .illustration { width: 100%; max-width: 400px; height: 270px; margin: 0 auto 26px; position: relative; display: flex; justify-content: center; align-items: center; }
        .monitor { width: 180px; height: 120px; background: var(--card); border-radius: 10px; position: relative; border: 3px solid var(--line); z-index: 2; animation: float 3s ease-in-out infinite; box-shadow: 0 10px 30px rgba(3, 143, 92, .08); }
        .monitor::before { content: ''; position: absolute; bottom: -15px; left: 50%; transform: translateX(-50%); width: 40px; height: 15px; background: var(--line); border-radius: 0 0 8px 8px; }
        .monitor::after { content: ''; position: absolute; bottom: -25px; left: 50%; transform: translateX(-50%); width: 80px; height: 10px; background: var(--line); border-radius: 20px; }
        .screen { width: calc(100% - 20px); height: calc(100% - 20px); background: var(--brand-soft); margin: 10px; border-radius: 5px; position: relative; overflow: hidden; }
        .screen-line { position: absolute; background: var(--brand); opacity: .35; border-radius: 2px; height: 4px; left: 16px; }
        .screen-line:nth-child(1) { width: 60px; top: 18px; }
        .screen-line:nth-child(2) { width: 40px; top: 30px; }
        .screen-line:nth-child(3) { width: 90px; top: 50px; opacity: .2; }
        .screen-bar { position: absolute; left: 16px; right: 16px; bottom: 14px; height: 6px; border-radius: 3px; background: var(--line); overflow: hidden; }
        .screen-bar::after { content: ''; position: absolute; top: 0; left: -40%; width: 40%; height: 100%; background: var(--brand); border-radius: 3px; animation: progress 2.2s ease-in-out infinite; }
        .crane { position: absolute; right: 28px; top: 0; z-index: 3; }
        .crane-tower { width: 8px; height: 200px; background: var(--metal); position: relative; border-radius: 2px; }
        .crane-arm { position: absolute; top: 20px; left: -60px; width: 120px; height: 6px; background: var(--metal); border-radius: 2px; }
        .crane-arm::before { content: ''; position: absolute; right: -12px; top: -5px; border-left: 15px solid var(--metal); border-top: 8px solid transparent; border-bottom: 8px solid transparent; }
        .crane-hook { position: absolute; left: 14px; top: 6px; width: 2px; height: 34px; background: var(--metal-dark); animation: swing 3s ease-in-out infinite; transform-origin: top center; }
        .crane-hook::after { content: ''; position: absolute; bottom: -6px; left: -3px; width: 8px; height: 6px; background: var(--brand); border-radius: 0 0 4px 4px; }
        .tools { position: absolute; left: 46px; bottom: 34px; z-index: 1; }
        .tool { width: 22px; height: 4px; background: var(--metal); margin: 4px 0; border-radius: 2px; }
        .plant { position: absolute; left: 16px; bottom: 50px; z-index: 1; }
        .pot { width: 20px; height: 15px; background: #8d6e63; border-radius: 0 0 10px 10px; position: relative; }
        .leaves { position: absolute; top: -10px; left: 50%; transform: translateX(-50%); }
        .leaf { width: 8px; height: 12px; background: var(--brand); border-radius: 50% 0; position: absolute; }
        .leaf:nth-child(1) { transform: rotate(-20deg); left: -8px; }
        .leaf:nth-child(2) { transform: rotate(20deg); left: 0; }
        .gears { position: absolute; left: 70px; top: 30px; z-index: 1; }
        .gear { width: 28px; height: 28px; border: 4px dashed var(--brand); border-radius: 50%; position: relative; opacity: .55; animation: rotate 8s linear infinite; }
        .gear.small { width: 18px; height: 18px; border-width: 3px; margin: -4px 0 0 24px; animation-direction: reverse; animation-duration: 5s; }
        .search-icon { position: absolute; right: 78px; top: 58px; z-index: 1; }
        .search-circle { width: 20px; height: 20px; border: 3px solid var(--line); border-radius: 50%; position: relative; }
        .search-handle { position: absolute; bottom: -7px; right: -8px; width: 9px; height: 3px; background: var(--line); transform: rotate(45deg); border-radius: 2px; }

        .card { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 26px 24px; box-shadow: 0 10px 30px rgba(0, 0, 0, .04); }
        .card h1 { font-size: 26px; font-weight: 600; margin-bottom: 12px; line-height: 1.3; }
        .card p { font-size: 16px; color: var(--muted); line-height: 1.55; max-width: 500px; margin: 0 auto; }
        .until { display: inline-flex; align-items: center; gap: 8px; margin-top: 16px; padding: 6px 14px; border-radius: 20px; background: var(--brand-soft); color: var(--brand); font-weight: 600; font-size: 14px; }
        .actions { margin-top: 20px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 9px 18px; border-radius: 6px; font: inherit; font-size: 15px; text-decoration: none; cursor: pointer; border: 1px solid var(--line); background: var(--card); color: var(--text); }
        .btn-primary { background: var(--brand); border-color: var(--brand); color: #fff; }
        .btn:hover { filter: brightness(.96); }
        .help { margin-top: 18px; font-size: 13px; color: var(--muted); }
        .help a { color: var(--brand); text-decoration: none; font-weight: 600; }
        .checking { margin-top: 12px; font-size: 12px; color: var(--muted); }

        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
        @keyframes rotate { from { transform: rotate(0); } to { transform: rotate(360deg); } }
        @keyframes swing { 0%, 100% { transform: rotate(-6deg); } 50% { transform: rotate(6deg); } }
        @keyframes progress { 0% { left: -40%; } 100% { left: 100%; } }
        @media (max-width: 600px) {
            .card h1 { font-size: 21px; }
            .card p { font-size: 14px; }
            .illustration { max-width: 300px; height: 230px; }
            .monitor { width: 150px; height: 104px; }
            .crane { right: 6px; }
            .crane-tower { height: 165px; }
            .gears { left: 40px; }
            .tools { left: 20px; }
            .plant { left: 0; }
        }
        @media (prefers-reduced-motion: reduce) { * { animation: none !important; } }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="illustration" aria-hidden="true">
            <div class="monitor">
                <div class="screen">
                    <div class="screen-line"></div>
                    <div class="screen-line"></div>
                    <div class="screen-line"></div>
                    <div class="screen-bar"></div>
                </div>
            </div>
            <div class="crane"><div class="crane-tower"><div class="crane-arm"><div class="crane-hook"></div></div></div></div>
            <div class="tools"><div class="tool"></div><div class="tool"></div><div class="tool"></div></div>
            <div class="plant"><div class="pot"><div class="leaves"><div class="leaf"></div><div class="leaf"></div></div></div></div>
            <div class="gears"><div class="gear"></div><div class="gear small"></div></div>
            <div class="search-icon"><div class="search-circle"><div class="search-handle"></div></div></div>
        </div>

        <div class="panel">
            <h1><?= nl2br(html_escape($message)) ?></h1>            
            <?php if ($until !== ''): ?>
            <div class="until">&#9719; <?= html_escape(translate('expected_back')) ?>: <?= html_escape($until) ?>, <?= html_escape(translate('thank_you_for_your_patience')) ?></div>
            <?php endif; ?>
            <div class="actions">
                <button type="button" class="btn btn-primary" onclick="window.location.reload()">&#8635; <?= html_escape(translate('try_again')) ?></button>
            </div>
            <?php if (!empty($contacts['phone_display']) || !empty($contacts['telegram_url'])): ?>
            <div class="help">
                <?= html_escape(translate('need_help_contact_us')) ?>
                <?php if (!empty($contacts['tel'])): ?> <a href="tel:<?= html_escape($contacts['tel']) ?>"><?= html_escape($contacts['phone_display']) ?></a><?php endif; ?>
                <?php if (!empty($contacts['telegram_url'])): ?> &middot; <a href="<?= html_escape($contacts['telegram_url']) ?>" target="_blank" rel="noopener">Telegram</a><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        setInterval(function () {
            fetch(window.location.href, { method: 'HEAD', cache: 'no-store' }).then(function (r) {
                if (r.status !== 503) { window.location.reload(); }
            }).catch(function () {});
        }, 60000);
    </script>
</body>
</html>
