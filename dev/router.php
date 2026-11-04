<?php
// Local dev server that mimics the live sub-folder of one site (sites/<site>/):
//   php -S localhost:8000 dev/router.php                 Crosshairs demo: http://localhost:8000/gun-site/
//   SITE=glendale php -S localhost:8000 dev/router.php   Glendale demo:   http://localhost:8000/ggg/
// The site's shop details, database (sites/<site>/data/) and home page pictures (sites/<site>/hero/)
// are used; uploaded listing photos (public/photos/) are shared by every site locally.

$site = getenv('SITE') ?: 'crosshairs';
if (!preg_match('~^[a-z0-9-]+$~', $site) || !is_file(__DIR__ . "/../sites/$site/site.php")) {
    http_response_code(500);
    echo "Unknown site '$site' (no sites/$site/site.php)";
    return true;
}
define('SITE_DIR', realpath(__DIR__ . "/../sites/$site"));
$base = (require SITE_DIR . '/site.php')['base_url'];
$public = realpath(__DIR__ . '/../public');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/' || $path === rtrim($base, '/')) {
    header('Location: ' . $base);
    return true;
}
if (strpos($path, $base) !== 0) {
    http_response_code(404);
    echo 'Not found (site lives under ' . $base . ')';
    return true;
}

$rel = substr($path, strlen($base));
// Same protection the .htaccess files give on the live server.
if (preg_match('~^(app|data)(/|$)~', $rel) || preg_match('~(^|/)[._]~', $rel)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

if ($rel === 'assets/site-icon.png') {
    // This site's own tab icon, if it has one.
    if (is_file(SITE_DIR . '/favicon.png')) {
        header('Content-Type: image/png');
        readfile(SITE_DIR . '/favicon.png');
        return true;
    }
    http_response_code(404);
    echo 'Not found';
    return true;
}
if (strpos($rel, 'assets/hero/') === 0) {
    // This site's home page pictures.
    $file = realpath(SITE_DIR . '/hero/' . substr($rel, strlen('assets/hero/')));
    if ($file !== false && strpos($file, SITE_DIR . DIRECTORY_SEPARATOR . 'hero') === 0) {
        header('Content-Type: image/jpeg');
        readfile($file);
        return true;
    }
    http_response_code(404);
    echo 'Not found';
    return true;
}

$file = realpath($public . '/' . $rel);
if ($file !== false && is_dir($file)) {
    $file = realpath($file . '/index.php');
}
if ($file === false || strpos($file, $public) !== 0) {
    http_response_code(404);
    echo 'Not found';
    return true;
}

if (substr($file, -4) === '.php') {
    chdir(dirname($file));
    $_SERVER['SCRIPT_NAME'] = $path;
    $_SERVER['SCRIPT_FILENAME'] = $file;
    require $file;
    return true;
}

$types = ['css' => 'text/css', 'js' => 'text/javascript', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
          'png' => 'image/png', 'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon'];
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
readfile($file);
return true;
