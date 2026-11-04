<?php
// Shared by every admin page: login check, layout, flash messages.
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/security.php';
require __DIR__ . '/../app/photos.php';
require __DIR__ . '/../app/stock_photos.php';
require __DIR__ . '/../app/migrate.php';
require __DIR__ . '/../app/roster.php';
require __DIR__ . '/_auth.php';

header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');

// Apply any pending database updates (app/migrations) before anything touches the tables.
try {
    apply_migrations(db());
    auto_backup();
} catch (Throwable $e) {
    // Runs before the login check, so visitors see only a generic message; details go to the server log.
    error_log('gun-site: ' . $e->getMessage());
    http_response_code(500);
    exit('The site is being updated. Please try again in a few minutes.');
}

const ADMIN_IDLE_SECONDS = 3600;   // signed out after an hour without a click (was 4 h; shared counter computers)
const LISTING_STATUSES = ['draft', 'coming_soon', 'available', 'on_hold', 'pending', 'sold', 'withdrawn'];
const CONDITIONS = ['New', 'Like New', 'Excellent', 'Very Good', 'Good', 'Fair', 'Poor'];
const CATEGORIES = ['handgun', 'rifle', 'shotgun', 'other'];

const PUBLIC_STATUSES = ['coming_soon', 'available', 'on_hold', 'pending'];

/** Next free stock number in the U-0001 series. */
function next_stock_number(): string
{
    $max = db()->query("SELECT MAX(CAST(SUBSTR(stock_number, 3) AS INTEGER)) FROM listings WHERE stock_number GLOB 'U-[0-9]*'")->fetchColumn();
    return 'U-' . str_pad((string)((int)$max + 1), 4, '0', STR_PAD_LEFT);
}

function status_name(string $s): string
{
    return ['draft' => 'Draft (hidden)', 'coming_soon' => 'Coming soon', 'available' => 'Available', 'on_hold' => 'On hold',
            'pending' => 'Sale pending', 'sold' => 'Sold (hidden)', 'withdrawn' => 'Withdrawn (hidden)'][$s] ?? $s;
}

function is_logged_in(): bool
{
    start_session();
    if (empty($_SESSION['admin']) || (time() - ($_SESSION['admin_seen'] ?? 0)) > ADMIN_IDLE_SECONDS || !refresh_admin_session()) {
        unset($_SESSION['admin'], $_SESSION['admin_user']);
        return false;
    }
    $_SESSION['admin_seen'] = time();
    return true;
}

function require_login(): void
{
    if (!is_logged_in()) {
        $back = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . url('admin/login.php', ['next' => $back]));
        exit;
    }
    // A temporary password must be replaced before anything else.
    if (!empty($_SESSION['admin_user']['must_change']) && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'account.php') {
        header('Location: ' . url('admin/account.php'));
        exit;
    }
}

/** POST-only actions must pass CSRF; anything else is rejected. */
function require_post_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
        flash('Your session expired. Please try again.', 'err');
        redirect_back();
    }
}

function flash(string $msg, string $type = 'ok'): void
{
    start_session();
    $_SESSION['flash'][] = [$type, $msg];
}

function redirect(string $to): void
{
    // Forms sent by admin.js (photos shrunk in the browser first) get the address as JSON and
    // go there themselves, so the flash messages show on that page instead of being used up by fetch().
    if (($_POST['_js'] ?? '') === '1') {
        header('Content-Type: application/json');
        echo json_encode(['redirect' => $to]);
        exit;
    }
    header('Location: ' . $to, true, 303);
    exit;
}

function redirect_back(): void
{
    // Only the path and query of the Referer are used, never its host, so a forged
    // Referer like https://evil.example/gun-site/admin/x can't send the browser elsewhere.
    $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $base = config('base_url') . 'admin/';
    $path = (string)(parse_url($ref, PHP_URL_PATH) ?: '');
    $query = (string)(parse_url($ref, PHP_URL_QUERY) ?: '');
    $ok = strpos($path, $base) === 0 && strpos($path, '//') === false && strpos($path, '\\') === false;
    redirect($ok ? $path . ($query !== '' ? '?' . $query : '') : url('admin/'));
}

/** Turn SQLite trigger / constraint errors into something readable. */
function friendly_db_error(Throwable $e): string
{
    $m = $e->getMessage();
    if (preg_match('/: \d+ (.*)$/s', $m, $x)) {
        $m = $x[1];
    }
    if (stripos($m, 'UNIQUE constraint failed: listings.stock_number') !== false) {
        return 'That stock number is already used by another listing.';
    }
    if (stripos($m, 'CHECK constraint failed') !== false && stripos($m, 'stock_number') !== false) {
        return 'A stock number is required once a listing is past Draft / Coming soon.';
    }
    if (stripos($m, 'UNIQUE constraint failed: firearms.slug') !== false) {
        return 'Another firearm already uses that URL name (slug).';
    }
    return $m;
}

function admin_header(string $title, string $active = ''): void
{
    $nav = ['listings' => ['Listings', url('admin/')], 'models' => ['Firearms', url('admin/models.php')]];
    if (is_owner()) {
        $nav['roster'] = ['CA roster', url('admin/roster.php')];
    }
    $nav['messages'] = ['Messages', url('admin/messages.php')];
    if (is_owner()) {
        $nav['sales'] = ['Sales', url('admin/sales.php')];
        $nav['hero'] = ['Home page', url('admin/hero.php')];
        $nav['admins'] = ['Admins', url('admin/admins.php')];
        $nav['log'] = ['Activity log', url('admin/log.php')];
    }
    $me = current_admin();
    $unread = 0;
    try {
        $unread = (int)db()->query('SELECT COUNT(*) FROM contact_messages WHERE handled = 0')->fetchColumn();
    } catch (Throwable $e) {
    }
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= favicon_tags() ?>
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> – Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
<script src="<?= e(asset('assets/js/admin.js')) ?>" defer></script>
</head>
<body class="admin">
<a class="skip-link" href="#main">Skip to content</a>
<header class="admin-bar">
  <div class="container admin-bar-inner">
    <a class="logo" href="<?= e(url('admin/')) ?>">Admin</a>
    <?php if (!empty($_SESSION['admin'])): ?>
      <nav aria-label="Admin">
        <?php foreach ($nav as $k => [$label, $href]): ?>
          <a href="<?= e($href) ?>"<?= $k === $active ? ' aria-current="page"' : '' ?>><?= e($label) ?><?= $k === 'messages' && $unread ? ' <span class="fcount">' . $unread . '</span>' : '' ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="admin-bar-right">
        <a href="<?= e(url()) ?>" target="_blank" rel="noopener">View site</a>
        <?php if ($me): ?><a href="<?= e(url('admin/account.php')) ?>" title="My account and password"><?= e($me['name']) ?> <span class="muted">(<?= $me['role'] === 'owner' ? 'owner' : 'staff' ?>)</span></a><?php endif; ?>
        <form method="post" action="<?= e(url('admin/logout.php')) ?>"><?= csrf_field() ?><button type="submit" class="link-btn">Log out</button></form>
      </div>
    <?php endif; ?>
  </div>
</header>
<main id="main" class="container admin-main">
<?php
    foreach ($_SESSION['flash'] ?? [] as [$type, $msg]) {
        echo '<div class="alert alert-' . ($type === 'err' ? 'err' : 'ok') . '" role="' . ($type === 'err' ? 'alert' : 'status') . '">' . e($msg) . '</div>';
    }
    unset($_SESSION['flash']);
}

function admin_footer(): void
{
    echo "</main>\n</body>\n</html>\n";
}

function opt(string $value, string $label, $current): string
{
    return '<option value="' . e($value) . '"' . ((string)$current === $value ? ' selected' : '') . '>' . e($label) . '</option>';
}

/** Nullable form values: '' -> NULL, numbers parsed. */
function in_text(string $k, int $max = 5000): ?string
{
    $v = post_str($k, $max);
    return $v === '' ? null : $v;
}
function in_num(string $k): ?float
{
    $v = str_replace([',', '$'], '', post_str($k, 40));
    return $v === '' || !is_numeric($v) ? null : (float)$v;
}
function in_int(string $k): ?int
{
    $v = in_num($k);
    return $v === null ? null : (int)round($v);
}
function in_bool(string $k): ?int
{
    $v = post_str($k, 3);
    return $v === '' ? null : ($v === '1' ? 1 : 0);
}
