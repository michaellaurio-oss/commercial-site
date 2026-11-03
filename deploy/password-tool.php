<?php
// Local tool to set the admin password in a browser (same way you'll type it at login).
// Run:   php -S 127.0.0.1:8765 deploy/password-tool.php                 (Crosshairs site)
//        SITE=glendale php -S 127.0.0.1:8765 deploy/password-tool.php   (another site)
//        then open http://127.0.0.1:8765
// Writes sites/<site>/config.local.php (only a hash of the password). Then upload it with:
//        powershell -ExecutionPolicy Bypass -File deploy/deploy.ps1 -Site <site> -Config

$origin = 'http://127.0.0.1:8765';
$site = getenv('SITE') ?: 'crosshairs';
if (!preg_match('~^[a-z0-9-]+$~', $site) || !is_file(__DIR__ . "/../sites/$site/site.php")) {
    exit("Unknown site '" . htmlspecialchars($site) . "' (no sites/$site/site.php)");
}
$siteCfg = require __DIR__ . "/../sites/$site/site.php";
$shopName = (string)((require __DIR__ . "/../sites/$site/shop.php")['name'] ?? $site);
$adminUrl = rtrim($siteCfg['site_url'], '/') . $siteCfg['base_url'] . 'admin/';
$file = __DIR__ . "/../sites/$site/config.local.php";
$existing = is_file($file) ? (require $file) : [];
$msg = '';
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only accept posts from this page itself (not from other websites open in the browser).
    if (($_SERVER['HTTP_ORIGIN'] ?? '') !== $origin) {
        $msg = 'Please open this page at ' . $origin . ' and try again.';
    } else {
        $pw1 = (string)($_POST['pw1'] ?? '');
        $pw2 = (string)($_POST['pw2'] ?? '');
        $contact = trim((string)($_POST['contact'] ?? ''));
        if (mb_strlen($pw1) < 12) {
            $msg = 'Please use at least 12 characters.';
        } elseif ($pw1 !== $pw2) {
            $msg = 'The two passwords did not match.';
        } elseif ($contact !== '' && !filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            $msg = 'That email address doesn\'t look right.';
        } else {
            $cfg = [
                'admin_password_hash' => password_hash($pw1, PASSWORD_DEFAULT),
                'contact_to'          => $contact,
                'contact_from'        => (string)($existing['contact_from'] ?? ''),
                'ip_salt'             => (string)($existing['ip_salt'] ?? bin2hex(random_bytes(16))),
                'debug'               => false,
            ];
            $php = "<?php\n// Live-server settings. Not committed to git. Recreate with deploy/password-tool.php.\nreturn " . var_export($cfg, true) . ";\n";
            $ok = file_put_contents($file, $php) !== false && password_verify($pw1, (require $file)['admin_password_hash']);
            $msg = $ok ? 'Saved and checked.' : 'Could not save the file.';
        }
    }
}
$contact = (string)($_POST['contact'] ?? ($existing['contact_to'] ?? ''));
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Set admin password – <?= htmlspecialchars($shopName) ?></title>
<style>
body{font-family:system-ui,sans-serif;background:#F7F6F3;color:#16181A;margin:0;padding:40px 16px}
main{max-width:460px;margin:0 auto;background:#fff;border:1px solid #E3E1DC;border-radius:12px;padding:28px}
h1{margin:0 0 6px;font-size:26px} p{color:#43474C;line-height:1.5}
label{display:block;font-weight:600;margin:18px 0 6px} input[type=password],input[type=text],input[type=email]{width:100%;box-sizing:border-box;height:46px;padding:0 12px;border:1px solid #D6D3CC;border-radius:8px;font-size:17px}
.row{display:flex;align-items:center;gap:8px;margin-top:10px;font-weight:400} button{margin-top:22px;height:50px;padding:0 24px;border:0;border-radius:8px;background:#B4541A;color:#fff;font-size:16px;font-weight:600;cursor:pointer}
.msg{padding:12px 14px;border-radius:8px;margin-top:16px} .err{background:#FBEBE6;color:#8A2E12} .ok{background:#E4EFE9;color:#1F5A40}
code{background:#F1EFEA;padding:2px 6px;border-radius:4px}
</style></head>
<body><main>
<h1>Set admin password</h1>
<p><strong><?= htmlspecialchars($shopName) ?></strong> (site <code><?= htmlspecialchars($site) ?></code>)</p>
<p>Runs only on this computer. Only a scrambled hash of the password is saved.</p>
<?php if ($ok): ?>
  <div class="msg ok"><strong>Saved.</strong> Now upload it: in Claude Code type<br><code>! powershell -ExecutionPolicy Bypass -File deploy/deploy.ps1 -Site <?= htmlspecialchars($site) ?> -Config</code><br>then log in at <a href="<?= htmlspecialchars($adminUrl) ?>"><?= htmlspecialchars(preg_replace('~^https?://~', '', $adminUrl)) ?></a>.</div>
<?php else: ?>
  <?php if ($msg): ?><div class="msg err" role="alert"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <label for="pw1">New RECOVERY password (at least 12 characters)</label>
    <input id="pw1" name="pw1" type="password" required minlength="12" autocomplete="new-password">
    <label for="pw2">Type it again</label>
    <input id="pw2" name="pw2" type="password" required minlength="12" autocomplete="new-password">
    <label class="row"><input type="checkbox" onclick="pw1.type=pw2.type=this.checked?'text':'password'"> Show password</label>
    <label for="contact">Email for website messages (optional)</label>
    <input id="contact" name="contact" type="email" value="<?= htmlspecialchars($contact) ?>">
    <button type="submit">Save password</button>
  </form>
<?php endif; ?>
</main></body></html>
