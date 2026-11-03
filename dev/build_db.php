<?php
// Build a site's local database, sites/<site>/data/gun_specs.db, from the SQL files.
//   php dev/build_db.php demo   schema.sql + demo_seed.sql      (sample data, safe to share)
//   php dev/build_db.php real   schema.sql + archive/seed_inventory.sql (the old inventory import; all drafts)
//   SITE=glendale php dev/build_db.php demo   the same for another site (default: crosshairs)
//   An optional second argument writes to another file, e.g. php dev/build_db.php real sites/x/live-initial.db
// Overwrites the existing database file, so never point this at the live server copy.

$mode = $argv[1] ?? 'demo';
$root = dirname(__DIR__);
$seeds = ['demo' => 'demo_seed.sql', 'real' => 'archive/seed_inventory.sql'];
if (!isset($seeds[$mode])) {
    fwrite(STDERR, "Usage: php dev/build_db.php [demo|real]\n");
    exit(1);
}
$site = getenv('SITE') ?: 'crosshairs';
$target = isset($argv[2]) ? $argv[2] : "$root/sites/$site/data/gun_specs.db";
if (!is_dir(dirname($target))) {
    mkdir(dirname($target), 0755, true);
}
@unlink($target);

$pdo = new PDO('sqlite:' . $target, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach (['schema.sql', $seeds[$mode]] as $f) {
    $sql = file_get_contents("$root/$f");
    if ($sql === false) {
        fwrite(STDERR, "Missing $f\n");
        exit(1);
    }
    $pdo->exec($sql);
    echo "Ran $f\n";
}
foreach (['firearms', 'listings', 'listings_public'] as $t) {
    echo str_pad($t, 16) . $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn() . "\n";
}
