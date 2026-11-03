<?php
// Included first by every page.

declare(strict_types=1);

// Buffer page output so headers (session cookie, redirects) can still be sent after HTML starts.
ob_start();

/**
 * A per-site file (site.php, shop.php). On the server deploy.ps1 uploads them into app/ from
 * sites/<site>/; locally dev/router.php defines SITE_DIR = sites/<site> so they are read there.
 */
function site_file(string $name): string
{
    return (defined('SITE_DIR') ? SITE_DIR : __DIR__) . '/' . $name;
}

$GLOBALS['config'] = require __DIR__ . '/config.php';
$GLOBALS['shop']   = require site_file('shop.php');

if (config('debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

require __DIR__ . '/helpers.php';
require __DIR__ . '/listings.php';
require __DIR__ . '/layout.php';

function config(string $key)
{
    return $GLOBALS['config'][$key] ?? null;
}

function shop(string $key): string
{
    return (string)($GLOBALS['shop'][$key] ?? '');
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $path = config('db_path');
        if (!is_file($path)) {
            throw new RuntimeException('Database file not found.');
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
    }
    return $pdo;
}
