<?php
require __DIR__ . '/app/bootstrap.php';

page_header('About', 'about', ['description' => shop('about_description')]);
?>
<div class="container" style="padding-top:48px">
  <div class="crumbs"><a href="<?= e(url()) ?>">Home</a> / About</div>
  <h1 class="page-title">About <?= e(shop('name')) ?></h1>

  <div class="prose">
<?= shop('about_html') /* trusted HTML from the site's own shop.php */ ?>

    <h2>Visit</h2>
    <p><?= e(shop('street')) ?><br><?= e(shop('city')) ?><br><?= e(shop('hours')) ?></p>

    <h2>Contact</h2>
    <p>Phone: <?= phone_link() ?><?= shop('phone_alt') !== '' ? ' or ' . e(shop('phone_alt')) : '' ?><?= shop('ffl') !== '' ? '<br>FFL #' . e(shop('ffl')) : '' ?></p>
<?php if (shop('contact_note') !== ''): ?>    <p><?= e(shop('contact_note')) ?></p>
<?php endif; ?>    <p><a class="btn btn-accent" href="<?= e(url('contact.php')) ?>">Send us a message</a></p>
  </div>
</div>
<?php page_footer();
