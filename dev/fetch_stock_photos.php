<?php
// Bulk-fetch manufacturer stock photos into public/stock-photos/<slug>.jpg (+ <slug>-sm.jpg).
// New listings without their own photos show them, labelled "Stock photo".
//
//   php dev/fetch_stock_photos.php                 every firearm whose spec source is a manufacturer page
//   php dev/fetch_stock_photos.php glock-g19 ...   only these slugs
//   add --force to re-fetch photos that already exist
//
// Reads slugs + pages from the local DB (sites/<SITE, default crosshairs>/data/gun_specs.db); never writes to it. The
// finding/saving rules are shared with Admin > Firearms (public/app/stock_photos.php). Sources
// are logged in dev/stock_photo_sources.csv. The photos are manufacturer images: kept out of
// the public git repo (.gitignore) and uploaded with the site by deploy.ps1.

ini_set('memory_limit', '1024M');
require dirname(__DIR__) . '/public/app/stock_photos.php';

$root = dirname(__DIR__);
$log = "$root/dev/stock_photo_sources.csv";
$args = array_slice($argv, 1);
$force = in_array('--force', $args, true);
$only = array_values(array_filter($args, fn($a) => $a !== '--force'));

$db = new PDO('sqlite:' . $root . '/sites/' . (getenv('SITE') ?: 'crosshairs') . '/data/gun_specs.db', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$rows = array_values(array_filter(
    $db->query('SELECT slug, manufacturer, model, product_url, source_url, image_url FROM firearms ORDER BY manufacturer, model')->fetchAll(PDO::FETCH_ASSOC),
    fn($r) => stock_page_for($r) !== null && (!$only || in_array($r['slug'], $only, true))
));

$lastHit = [];
function polite_get(string $url, array &$lastHit, string $accept = 'text/html,*/*'): array
{
    $host = parse_url($url, PHP_URL_HOST);
    $wait = 1.2 - (microtime(true) - ($lastHit[$host] ?? 0));   // ~1 request per second per site
    if ($wait > 0) {
        usleep((int)($wait * 1e6));
    }
    $lastHit[$host] = microtime(true);
    return stock_http_get($url, $accept);
}

$pages = [];   // several models share a page: fetch it once
$refused = [];
$fh = fopen($log, 'a');
if (ftell($fh) === 0) {
    fputcsv($fh, ['date', 'slug', 'manufacturer', 'model', 'page_url', 'image_url', 'result']);
}
$ok = $skip = $fail = 0;
foreach ($rows as $r) {
    if (!$force && is_file(stock_photos_dir() . '/' . $r['slug'] . '.jpg')) {
        $skip++;
        continue;
    }
    $page = stock_page_for($r);
    $host = parse_url($page, PHP_URL_HOST);
    if (isset($refused[$host])) {
        $pages[$page] = $pages[$page] ?? [null, "skipped: $host refused earlier requests (HTTP {$refused[$host]})"];
    }
    if (!isset($pages[$page])) {
        [$code, $html, $final] = polite_get($page, $lastHit);
        if ($code === 403 || $code === 429) {
            $refused[$host] = $code;   // the site is blocking us: don't keep asking this run
        }
        $img = $code === 200 && $html !== '' ? stock_find_image($html, $final, $r['image_url']) : null;
        $pages[$page] = [$img, $code !== 200 ? "page HTTP $code" : ($img ? '' : 'no image found on page')];
    }
    [$img, $result] = $pages[$page];
    if ($img) {
        [$code, $bytes] = polite_get($img, $lastHit, 'image/avif,image/webp,image/png,image/jpeg,*/*');
        try {
            $result = $code === 200 ? 'ok ' . stock_save($bytes, $r['slug'], null, 80_000_000) : "image HTTP $code";
        } catch (RuntimeException $e) {
            $result = $e->getMessage();
        }
    }
    $good = strpos($result, 'ok ') === 0;
    $good ? $ok++ : $fail++;
    fputcsv($fh, [date('Y-m-d'), $r['slug'], $r['manufacturer'], $r['model'], $page, $img ?? '', $result]);
    printf("%-4s %-45s %s\n", $good ? 'ok' : 'FAIL', $r['slug'], $good ? $result : "$result ($page)");
}
fclose($fh);
echo "\nSaved $ok, failed $fail, already had $skip. Sources: dev/stock_photo_sources.csv\n";
