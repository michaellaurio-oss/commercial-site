<?php
// Starting database for a NEW site, from another site's database (e.g. a -Backup copy):
// keeps the firearm catalog (firearms, manufacturer specs, CA roster copy, caliber/action spellings)
// and the schema/migrations; empties everything that belongs to the other store: listings and their
// photos, contact messages, admin accounts, activity log, password resets, login attempts.
//   php dev/new_site_db.php backups/20261103-1243/gun_specs.db sites/glendale/live-initial.db
// The new site's first owner is created on the first visit to its admin login page
// (with that site's recovery password from sites/<site>/config.local.php).

[$src, $dst] = [$argv[1] ?? '', $argv[2] ?? ''];
if (!is_file($src) || $dst === '') {
    fwrite(STDERR, "Usage: php dev/new_site_db.php <source.db> <new.db>\n");
    exit(1);
}
if (file_exists($dst)) {
    fwrite(STDERR, "$dst already exists; delete it first if you really want a new one.\n");
    exit(1);
}
if (!is_dir(dirname($dst))) {
    mkdir(dirname($dst), 0755, true);
}

$pdo = new PDO('sqlite:' . $src, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('VACUUM INTO ' . $pdo->quote($dst));   // consistent copy; the source is not changed
$pdo = new PDO('sqlite:' . $dst, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$clear = ['listing_photos', 'listings', 'contact_messages', 'audit_log', 'password_resets', 'login_attempts', 'admins'];
$pdo->beginTransaction();
// The "always one active owner" triggers would refuse to delete the last owner: set them aside.
$trg = $pdo->query("SELECT name, sql FROM sqlite_master WHERE type = 'trigger' AND tbl_name = 'admins'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($trg as $t) {
    $pdo->exec('DROP TRIGGER ' . $t['name']);
}
foreach ($clear as $table) {
    $n = $pdo->exec("DELETE FROM $table");
    echo str_pad($table, 18) . "removed $n\n";
}
foreach ($trg as $t) {
    $pdo->exec($t['sql']);
}
$pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('" . implode("','", $clear) . "')");
$pdo->commit();

$bad = $pdo->query('PRAGMA foreign_key_check')->fetchAll();
if ($bad) {
    fwrite(STDERR, "Foreign key problems: " . json_encode($bad) . "\n");
    exit(1);
}
$pdo->exec('VACUUM');
foreach (['firearms', 'ca_roster', 'value_aliases', 'schema_migrations', 'listings', 'admins'] as $t) {
    echo str_pad($t, 18) . $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn() . "\n";
}
echo "Wrote $dst\n";
