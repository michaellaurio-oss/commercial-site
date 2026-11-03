<?php
// Live server: sites/<site>/config.local.php, uploaded as app/config.local.php with deploy.ps1 -Config.
// Local development: copy to public/app/config.local.php (shared by every site locally).
// Neither is committed. The web folder (base_url) is set per site in sites/<site>/site.php.
// Generate a password hash with:  php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"
// Generate an ip_salt with:       php -r "echo bin2hex(random_bytes(32));"
// (deploy/password-tool.php fills in both.)

return [
    // 'db_path'             => __DIR__ . '/../data/gun_specs.db',
    'admin_password_hash' => '',
    // Random secret used to hash visitor IP addresses (login throttling, contact form).
    'ip_salt'             => '',
    'contact_to'          => '',
    'contact_from'        => '',
    'debug'               => false,
];
