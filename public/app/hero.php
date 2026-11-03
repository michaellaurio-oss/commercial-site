<?php
// Home page pictures: JPEGs in assets/hero/, the shown ones and their order in assets/hero/order.json.
// Managed in Admin > Home page; with no order.json the list in shop.php ('hero_images') is used.

function hero_dir(): string
{
    // Locally each site's pictures are in sites/<site>/hero/ (dev/router.php serves them as assets/hero/).
    return defined('SITE_DIR') ? SITE_DIR . '/hero' : __DIR__ . '/../assets/hero';
}

/** Every picture in the folder (original files only, not the -sm thumbnails). */
function hero_all_files(): array
{
    $out = [];
    foreach (glob(hero_dir() . '/*.jpg') ?: [] as $f) {
        $name = basename($f);
        if (preg_match('~^[a-z0-9-]+\.jpg$~', $name) && substr($name, -7) !== '-sm.jpg') {
            $out[] = $name;
        }
    }
    sort($out);
    return $out;
}

/** The pictures shown on the home page, in order (only files that exist). */
function hero_shown(): array
{
    $file = hero_dir() . '/order.json';
    $list = null;
    if (is_file($file)) {
        $j = json_decode((string)file_get_contents($file), true);
        if (is_array($j)) {
            $list = $j;
        }
    }
    if ($list === null) {
        $list = (array)($GLOBALS['shop']['hero_images'] ?? []);
    }
    $have = array_flip(hero_all_files());
    $out = [];
    foreach ($list as $name) {
        if (is_string($name) && isset($have[$name]) && !in_array($name, $out, true)) {
            $out[] = $name;
        }
    }
    return $out;
}

function hero_save_shown(array $names): void
{
    $dir = hero_dir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Could not create the home page pictures folder.');
    }
    if (file_put_contents($dir . '/order.json', json_encode(array_values($names)), LOCK_EX) === false) {
        throw new RuntimeException('Could not save the order.');
    }
}

function hero_delete_files(string $name): void
{
    if (preg_match('~^[a-z0-9-]+\.jpg$~', $name)) {
        @unlink(hero_dir() . '/' . $name);
        @unlink(hero_dir() . '/' . substr($name, 0, -4) . '-sm.jpg');
    }
}
