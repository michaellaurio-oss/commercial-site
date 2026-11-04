<?php
// Page chrome and shared components.

function page_header(string $title, string $active = '', array $opts = []): void
{
    $shopName = shop('name');
    $fullTitle = $title === '' ? $shopName : $title . ' – ' . $shopName;
    $q = $opts['q'] ?? '';
    $nav = [
        'new'       => ['New Inventory', url('inventory.php', ['newused' => 'New'])],
        'used'      => ['Used Inventory', url('inventory.php', ['newused' => 'Used'])],
        'ammo'      => ['Ammo', '#'],   // no ammo page yet (Michael, 2026-09-30)
        'how'       => ['How to buy', url('how-to-buy.php')],
        'about'     => ['About', url('about.php')],
    ];
    // Hover menus under New / Used Inventory: only the types currently listed.
    $stocked = stocked_types();
    $sub = [];
    foreach (['new' => 'New', 'used' => 'Used'] as $key => $nu) {
        foreach ($stocked[$key] ?? [] as $category => $count) {
            $plural = ['handgun' => 'Handguns', 'rifle' => 'Rifles', 'shotgun' => 'Shotguns'][$category] ?? type_label($category);
            $sub[$key][] = [$plural, url('inventory.php', ['newused' => $nu, 'type' => type_label($category)]), $count];
        }
    }
    // Always last under New Inventory, below a thin line (Michael, 2026-09-30).
    // Links to the inventory filtered to "LEO Sales Only" guns (new + off the CA roster).
    $leoCount = leo_count();
    $sub['new'][] = ['LEO', url('inventory.php', ['leo' => 1]), $leoCount ?: null, true];
    ?>
<!doctype html>
<html lang="en" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= favicon_tags() ?>
<title><?= e($fullTitle) ?></title>
<?php if (!empty($opts['description'])): ?><meta name="description" content="<?= e($opts['description']) ?>">
<?php endif; ?>
<?php if (!empty($opts['noindex']) || shop('demo_notice') !== ''): ?><meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700;800&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
<script>document.documentElement.classList.remove("no-js")</script>
<script src="<?= e(asset('assets/js/site.js')) ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<?php if (shop('demo_notice') !== ''): ?><div class="demo-notice" role="note"><?= e(shop('demo_notice')) ?></div><?php endif; ?>
<header class="site-header">
  <div class="container header-inner">
    <a class="logo" href="<?= e(url()) ?>"><?= e($shopName) ?></a>
    <button type="button" class="menu-toggle" aria-expanded="false" aria-controls="site-nav"><?= icon('menu', 22) ?><span class="sr-only">Menu</span></button>
    <nav id="site-nav" class="site-nav" aria-label="Main">
      <?php foreach ($nav as $key => [$label, $href]): ?>
        <?php if (!empty($sub[$key])): ?>
          <div class="nav-item has-sub">
            <a href="<?= e($href) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= e($label) ?><?= icon('chevron', 16, 'nav-caret') ?></a>
            <ul class="subnav" aria-label="<?= e($label) ?> by type">
              <?php foreach ($sub[$key] as $item): [$subLabel, $subHref, $count] = $item; ?>
                <li<?= !empty($item[3]) ? ' class="subnav-sep"' : '' ?>><a href="<?= e($subHref) ?>"><?= e($subLabel) ?><?php if ($count !== null): ?><span class="subnav-count"><?= (int)$count ?></span><?php endif; ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php else: ?>
          <a href="<?= e($href) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <form class="site-search" role="search" action="<?= e(url('inventory.php')) ?>" method="get">
      <?= icon('search', 18) ?>
      <label for="site-search" class="sr-only">Search inventory</label>
      <input id="site-search" name="q" type="search" value="<?= e($q) ?>" placeholder="Search make, model, caliber">
    </form>
  </div>
</header>
<main id="main">
<?php
}

function page_footer(bool $full = false): void
{
    ?>
</main>
<?php if ($full): ?>
<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <div class="footer-logo"><?= e(shop('name')) ?></div>
      <div class="mt-8"><?= e(shop('tagline')) ?></div>
    </div>
    <div>
      <div class="footer-head">Shop</div>
      <div><a href="<?= e(url('inventory.php')) ?>">All inventory</a></div>
      <div><a href="<?= e(url('inventory.php', ['type' => 'Handgun'])) ?>">Handguns</a></div>
      <div><a href="<?= e(url('inventory.php', ['type' => 'Rifle'])) ?>">Rifles</a></div>
      <div><a href="<?= e(url('inventory.php', ['type' => 'Shotgun'])) ?>">Shotguns</a></div>
    </div>
    <div>
      <div class="footer-head">Visit</div>
      <div><?= e(shop('street')) ?></div>
      <div><?= e(shop('city')) ?></div>
      <div><?= e(shop('hours')) ?></div>
    </div>
    <div>
      <div class="footer-head">Contact</div>
      <div><?= phone_link() ?></div>
      <?php if (shop('phone_alt') !== ''): ?><div><?= e(shop('phone_alt')) ?></div><?php endif; ?>
      <div><a href="<?= e(url('contact.php')) ?>"><?= e(shop('email') !== '' ? shop('email') : 'Send us a message') ?></a></div>
      <?php if (shop('ffl') !== ''): ?><div>FFL #<?= e(shop('ffl')) ?></div><?php endif; ?>
    </div>
  </div>
</footer>
<?php else: ?>
<footer class="site-footer site-footer-compact">
  <div class="container footer-row">
    <div class="footer-logo"><?= e(shop('name')) ?></div>
    <div><?= e(shop('street')) ?>, <?= e(shop('city')) ?> · <?= phone_link() ?><?= shop('ffl') !== '' ? ' · FFL #' . e(shop('ffl')) : '' ?></div>
  </div>
</footer>
<?php endif; ?>
</body>
</html>
<?php
}

function status_badge(string $status, bool $large = false): string
{
    $s = status_info($status);
    if (!$s) {
        return '';
    }
    $inner = ($large ? '<span class="dot" style="background:' . e($s['fg']) . '"></span>' : '')
        . e($s['label']) . icon('info', $large ? 14 : 12);
    return tooltip($inner, $s['tip'], 'badge' . ($large ? ' badge-lg' : ''), 'color:' . $s['fg'] . ';background:' . $s['bg']);
}

/**
 * "Compare" checkbox for a listing (inventory cards and the gun page). assets/js/compare.js
 * saves the pick; the data-* attributes are what the compare tray shows.
 */
function compare_toggle(array $c, string $class = 'cmp-pick'): string
{
    return '<label class="' . e($class) . '"><input type="checkbox" data-compare="' . (int)$c['id'] . '"'
        . ' data-make="' . e($c['make']) . '" data-model="' . e($c['model']) . '" data-photo="' . e($c['photo'] ?? '') . '">'
        . ' Compare<span class="sr-only"> ' . e($c['make'] . ' ' . $c['model']) . '</span></label>';
}

/** Listing card. Keep in sync with renderCard() in assets/js/inventory.js. */
function render_card(array $c, bool $showStock = true, bool $compare = false): string
{
    ob_start();
    ?>
<article class="card">
  <div class="card-photo">
    <?php if ($c['photo']): ?>
      <img src="<?= e($c['photo']) ?>" alt="" loading="lazy">
    <?php else: ?>
      <span class="ph-text">Photo coming soon</span>
    <?php endif; ?>
    <span class="chip"><?= e($c['type']) ?></span>
    <?php if ($c['status'] !== 'available'): ?><span class="card-status"><?= status_badge($c['status']) ?></span><?php endif; ?>
    <?php if ($compare): ?><?= compare_toggle($c) ?><?php endif; ?>
    <?php if (!empty($c['stockPhoto'])): ?><?= stock_photo_tag('card-stock-tag tip-right') ?><?php endif; ?>
  </div>
  <div class="card-body">
    <?php if (!empty($c['leo'])): ?><?= leo_badge('card-leo') ?><?php endif; ?>
    <div class="card-head">
      <div class="min0">
        <div class="card-make"><?= e($c['make']) ?></div>
        <h3 class="card-model"><a class="card-link" href="<?= e($c['url']) ?>"><?= e($c['model']) ?></a></h3>
      </div>
      <?php if ($showStock && $c['stock'] !== ''): ?><div class="card-stock">#<?= e($c['stock']) ?></div><?php endif; ?>
    </div>
    <dl class="card-specs">
      <div><dt>Caliber</dt><dd><?= $c["caliber"] !== "" ? nowrap_pair($c["caliber"]) : "—" ?></dd></div>
      <div><dt>Capacity</dt><dd><?= e($c['capacity'] ?: '—') ?></dd><?php if ($c['overTen']): ?><dd class="ca-note"><?= e(CA_CAPACITY_NOTE) ?></dd><?php endif; ?></div>
      <?php if ($c['barrel'] !== ''): ?><div><dt>Barrel</dt><dd><?= nowrap_pair($c["barrel"]) ?></dd></div><?php endif; ?>
      <?php if ($c['weight'] !== ''): ?><div><dt>Weight</dt><dd><?= nowrap_pair($c["weight"]) ?></dd></div><?php endif; ?>
    </dl>
    <div class="card-foot">
      <div class="tags">
        <?php if ($c['newused'] === 'New'): ?><span class="tag tag-new">New</span>
        <?php else: ?><span class="tag">Used<?= $c['condition'] !== '' ? ' · ' . e($c['condition']) : '' ?></span><?php endif; ?>
        <?php if ($c['roster'] === 'On roster'): ?><span class="tag tag-roster">CA Roster</span><?php endif; ?>
        <?php if ($c['roster'] === EXEMPT_LABEL): ?><?= tooltip(e(EXEMPT_LABEL) . icon('info', 12), EXEMPT_NOTE, 'tag tag-help') ?><?php endif; ?>
        <?php if ($c['roster'] === 'Off roster' && empty($c['leo'])): ?><?= tooltip('Off roster' . icon('info', 12), OFF_ROSTER_NOTE, 'tag tag-help') ?><?php endif; ?>
        <?php if ($c['mods'] === 'Modified'): ?><span class="tag">Modified</span><?php endif; ?>
      </div>
      <span class="card-price"><?= e($c['priceText']) ?></span>
    </div>
  </div>
</article>
<?php
    return ob_get_clean();
}
