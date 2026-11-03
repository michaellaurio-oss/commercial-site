<?php
require __DIR__ . '/app/bootstrap.php';

$all = public_listings();
$justListed = array_slice(array_values(array_filter($all, fn($l) => $l['status'] === 'available')), 0, 4);
$counts = ['handgun' => 0, 'rifle' => 0, 'shotgun' => 0];
foreach ($all as $l) {
    if (isset($counts[$l['category']])) {
        $counts[$l['category']]++;
    }
}
// The shop's own pictures: Admin > Home page (assets/hero/order.json), else the site's shop.php 'hero_images'.
require_once __DIR__ . '/app/hero.php';
$heroSlides = array_map(fn($f) => asset('assets/hero/' . $f), hero_shown());
$heroPhoto = null;
foreach ($justListed as $l) {
    if ($l['primary_photo']) {
        $heroPhoto = photo_url($l['primary_photo']);
        break;
    }
}

page_header('', 'home', ['description' => shop('hero_text')]);
?>
<div class="container">
  <section class="hero">
    <div class="hero-copy">
      <div class="hero-kicker"><?= e(shop('hero_kicker')) ?></div>
      <h1><?= e(shop('hero_title')) ?></h1>
      <p><?= e(shop('hero_text')) ?></p>
      <div class="hero-ctas">
        <a class="btn btn-dark" href="<?= e(url('inventory.php')) ?>">Browse inventory <?= icon('arrow') ?></a>
        <a class="btn btn-outline" href="<?= e(url('how-to-buy.php')) ?>">How buying works</a>
      </div>
    </div>
    <?php if ($heroSlides): ?>
    <div class="hero-photo hero-slides" data-hero-slides role="img" aria-label="Pictures of the store">
      <?php foreach ($heroSlides as $i => $src): ?>
        <img src="<?= e($src) ?>" alt=""<?= $i === 0 ? '' : ' loading="lazy"' ?> class="<?= $i === 0 ? 'is-on' : '' ?>">
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="hero-photo ph">
      <?php if ($heroPhoto): ?>
        <img src="<?= e($heroPhoto) ?>" alt="">
      <?php else: ?>
        <?= icon('photo', 40) ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </section>

  <section class="promises" aria-label="What you get with every listing">
    <div class="promise"><?= icon('camera', 24) ?><div><div class="promise-title">Used guns photographed as they are</div><div class="promise-text">Every used gun is shown in its own photos — you see the one you get.</div></div></div>
    <div class="promise"><?= icon('graded', 24) ?><div><div class="promise-title">Graded condition, noted wear</div><div class="promise-text">Condition, round count, modifications and what's included.</div></div></div>
    <div class="promise"><?= icon('ruler', 24) ?><div><div class="promise-title">Full specs, imperial &amp; metric</div><div class="promise-text">Barrel, length, weight, trigger pull and more.</div></div></div>
  </section>

  <section class="home-section" aria-labelledby="just-listed">
    <div class="section-head">
      <div>
        <h2 id="just-listed" class="section-title">Just listed</h2>
        <p class="page-sub">Recently added to inventory.</p>
      </div>
      <a class="arrow-link" href="<?= e(url('inventory.php')) ?>">View all inventory <?= icon('arrow', 16) ?></a>
    </div>
    <?php if ($justListed): ?>
      <div class="home-grid">
        <?php foreach ($justListed as $l) echo render_card(card_data($l), false); ?>
      </div>
    <?php else: ?>
      <div class="empty-note">New listings are on the way. Call <?= phone_link() ?> to ask what's in the shop.</div>
    <?php endif; ?>
  </section>

  <section class="home-section" aria-labelledby="shop-by-type">
    <h2 id="shop-by-type" class="section-title">Shop by type</h2>
    <div class="type-tiles">
      <?php
      $tiles = [
          ['Handgun', 'Handguns', 'Pistols and revolvers', $counts['handgun']],
          ['Rifle', 'Rifles', 'Lever, bolt and semi-auto', $counts['rifle']],
          ['Shotgun', 'Shotguns', 'Pump, semi-auto and break-action', $counts['shotgun']],
      ];
      foreach ($tiles as $i => [$type, $name, $desc, $n]): ?>
        <a class="type-tile" href="<?= e(url('inventory.php', ['type' => $type])) ?>">
          <div class="num">0<?= $i + 1 ?> · <?= $n ?> listed</div>
          <div class="row">
            <div><div class="name"><?= e($name) ?></div><div class="desc"><?= e($desc) ?></div></div>
            <?= icon('arrow', 28) ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="how-band" aria-labelledby="how-buying">
    <div class="section-head">
      <h2 id="how-buying" class="section-title">How buying works</h2>
      <p>All sales go through a licensed dealer (FFL) with a background check and any state waiting period.</p>
    </div>
    <div class="steps">
      <div class="step"><div class="step-num">Step 1</div><div class="step-title">Find your gun</div><div class="step-text">Browse photos, condition notes and full specs for every listing.</div></div>
      <div class="step"><div class="step-num">Step 2</div><div class="step-title">Contact us to reserve it</div><div class="step-text">Send the stock number and we'll confirm availability.<?= shop('hold_policy') !== '' ? ' ' . e(shop('hold_policy')) : '' ?></div></div>
      <div class="step"><div class="step-num">Step 3</div><div class="step-title">Background check and pick up</div><div class="step-text">Bring your ID and Firearm Safety Certificate to <?= e(shop('transfer_at')) ?> to start the background check, then pick up your gun after California's 10-day waiting period.</div></div>
    </div>
  </section>
</div>
<?php page_footer(true);
