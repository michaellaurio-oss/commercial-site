<?php
// Site configuration, read in this order (later wins):
//   1. the defaults below (shared by every site)
//   2. site.php: this site's web folder and address. On the server it is app/site.php, uploaded by
//      deploy.ps1 from sites/<site>/site.php; locally dev/router.php points SITE_DIR at sites/<site>/.
//   3. config.local.php (same folder, never committed): secrets; see config.local.example.php.

$config = [
    // URL path the site is served from, with leading and trailing slash.
    'base_url'            => '/gun-site/',
    // SQLite database file (the data/ folder is blocked from the web by .htaccess).
    'db_path'             => __DIR__ . '/../data/gun_specs.db',
    // password_hash() of the RECOVERY password (the old shared admin password). Empty = admin login disabled.
    'admin_password_hash' => '',
    // Public address of the site, used in emailed password-reset links (never taken from the request).
    'site_url'            => 'https://the-laurios.com',
    // Where "Ask about this gun" messages are emailed. Empty = saved to the DB only.
    'contact_to'          => '',
    'contact_from'        => '',
    // Show PHP errors in the browser (local development only).
    'debug'               => false,
];

$siteFile = site_file('site.php');
if (is_file($siteFile)) {
    $config = array_replace($config, require $siteFile);
}
if (defined('SITE_DIR')) {
    // Local development: each site keeps its own database in sites/<site>/data/.
    $config['db_path'] = SITE_DIR . '/data/gun_specs.db';
}
$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $config = array_replace($config, require $local);
}

return $config;
