<?php
// Copy listings (with their photo records and photo files) from one site's backup into another
// site's backup, e.g. Crosshairs' sample listings into Glendale. Run between a -Backup and a
// -Restore of the TARGET site, so its live data (accounts, anything entered) is kept:
//   powershell -ExecutionPolicy Bypass -File deploy/deploy.ps1 -Site glendale -Backup
//   php dev/copy_listings.php backups/20261103-1243 backups/glendale/<that backup>
//   powershell -ExecutionPolicy Bypass -File deploy/deploy.ps1 -Site glendale -Restore <that backup>
// Copies listings that are on the public site (coming soon / available / on hold / pending);
// pass stock numbers after the two folders to copy only those. Firearms are matched by slug;
// a listing whose stock number already exists in the target, or whose firearm isn't there, is skipped.
// Never changes firearms or the CA roster. Internal notes and consignment are not copied.

[$srcDir, $dstDir] = [$argv[1] ?? '', $argv[2] ?? ''];
$only = array_slice($argv, 3);
foreach ([$srcDir, $dstDir] as $d) {
    if (!is_file("$d/gun_specs.db")) {
        fwrite(STDERR, "Usage: php dev/copy_listings.php <source backup folder> <target backup folder> [STOCK# ...]\n(no gun_specs.db in '$d')\n");
        exit(1);
    }
}
$opt = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
$src = new PDO("sqlite:$srcDir/gun_specs.db", null, null, $opt);
$dst = new PDO("sqlite:$dstDir/gun_specs.db", null, null, $opt);
$dst->exec('PRAGMA foreign_keys = ON');

// Columns both databases have (the target may be on a newer migration), minus private / local ones.
$cols = fn(PDO $p, string $t) => array_column($p->query("PRAGMA table_info($t)")->fetchAll(), 'name');
$skipCols = ['id', 'firearm_id', 'internal_notes', 'consignment', 'listing_id'];
$lCols = array_values(array_diff(array_intersect($cols($src, 'listings'), $cols($dst, 'listings')), $skipCols));
$pCols = array_values(array_diff(array_intersect($cols($src, 'listing_photos'), $cols($dst, 'listing_photos')), $skipCols));

$sql = "SELECT l.*, f.slug FROM listings l JOIN firearms f ON f.id = l.firearm_id
        WHERE l.status IN ('coming_soon', 'available', 'on_hold', 'pending')";
if ($only) {
    $sql .= ' AND l.stock_number IN (' . implode(',', array_map([$src, 'quote'], $only)) . ')';
}
$listings = $src->query($sql . ' ORDER BY l.id')->fetchAll();

$fid = $dst->prepare('SELECT id FROM firearms WHERE slug = ?');
$exists = $dst->prepare('SELECT 1 FROM listings WHERE stock_number = ?');
$insL = $dst->prepare('INSERT INTO listings (firearm_id, ' . implode(', ', $lCols) . ') VALUES (?' . str_repeat(', ?', count($lCols)) . ')');
$insP = $dst->prepare('INSERT INTO listing_photos (listing_id, ' . implode(', ', $pCols) . ') VALUES (?' . str_repeat(', ?', count($pCols)) . ')');
$photos = $src->prepare('SELECT * FROM listing_photos WHERE listing_id = ? ORDER BY sort_order, id');

$copied = 0;
$files = 0;
$dst->beginTransaction();
foreach ($listings as $l) {
    $label = $l['stock_number'] ?? ('#' . $l['id'] . ' ' . $l['slug']);
    $fid->execute([$l['slug']]);
    $firearmId = $fid->fetchColumn();
    if ($firearmId === false) {
        echo "skipped $label: firearm {$l['slug']} not in the target\n";
        continue;
    }
    if ($l['stock_number'] !== null) {
        $exists->execute([$l['stock_number']]);
        if ($exists->fetchColumn()) {
            echo "skipped $label: stock number already in the target\n";
            continue;
        }
    }
    $insL->execute(array_merge([$firearmId], array_map(fn($c) => $l[$c], $lCols)));
    $newId = (int)$dst->lastInsertId();
    $photos->execute([$l['id']]);
    $n = 0;
    foreach ($photos->fetchAll() as $p) {
        $path = (string)$p['file_path'];
        if (strpos($path, 'photos/') === 0 && strpos($path, '..') === false) {
            // Listing photo file (and its -sm thumbnail): copy into the target backup.
            foreach ([$path, preg_replace('~\.jpg$~', '-sm.jpg', $path)] as $f) {
                if (is_file("$srcDir/$f")) {
                    @mkdir(dirname("$dstDir/$f"), 0755, true);
                    if (!copy("$srcDir/$f", "$dstDir/$f")) {
                        $dst->rollBack();
                        fwrite(STDERR, "Could not copy $f; nothing was changed in the target database.\n");
                        exit(1);
                    }
                    $files++;
                } elseif ($f === $path) {
                    echo "  note: $label photo file $f is missing in the source backup\n";
                }
            }
        }
        $insP->execute(array_merge([$newId], array_map(fn($c) => $p[$c], $pCols)));
        $n++;
    }
    echo "copied $label ({$l['slug']}, {$l['status']}, $n photo" . ($n === 1 ? '' : 's') . ")\n";
    $copied++;
}
$dst->commit();
echo "$copied listing" . ($copied === 1 ? '' : 's') . " copied, $files photo files. Now -Restore the target backup folder.\n";
