<?php
require __DIR__ . '/app/bootstrap.php';

page_header('How to buy', 'how', ['description' => 'How buying a firearm works in California: documents, Firearm Safety Certificate, background check and the 10-day waiting period.']);
?>
<div class="container" style="padding-top:48px">
  <div class="crumbs"><a href="<?= e(url()) ?>">Home</a> / How to buy</div>
  <h1 class="page-title">How buying works</h1>
  <p class="page-sub">Every sale goes through a background check and California's 10-day waiting period.</p>

  <div class="prose">
    <h2>1. Find your gun</h2>
    <p>Every listing shows photos of the actual gun, its condition report and full factory specs. Note the <strong>stock number</strong> shown on the listing.</p>

    <h2>2. Contact us to reserve it</h2>
    <p>Call <?= phone_link() ?> or use <a href="<?= e(url('contact.php')) ?>" style="text-decoration:underline">Ask about this gun</a> with the stock number, and we'll confirm it's available.<?= shop('hold_policy') !== '' ? ' ' . e(shop('hold_policy')) : '' ?></p>

    <h2>3. Bring the right documents</h2>
    <p>You'll need all of these with you when we start the background check; we can't begin without them:</p>
    <ul>
      <li>A valid California driver's license or ID card. If it isn't REAL ID compliant, also bring your valid U.S. passport or original birth certificate.</li>
      <li>A second government document showing your street address; your California vehicle registration is preferred.</li>
      <li>A Firearm Safety Certificate (FSC).<?= shop('fsc_note') !== '' ? ' ' . e(shop('fsc_note')) : '' ?></li>
    </ul>

    <h2>4. Background check and 10-day wait</h2>
    <p>We submit your Dealer Record of Sale (DROS) to the California DOJ, which starts the mandatory 10-day waiting period. It usually ends 10 calendar days after the DROS is started; in rare cases the state can extend it by up to 20 days.</p>

    <h2>5. Pick up</h2>
    <p>Once the waiting period is over and you're cleared, come back to <?= e(shop('transfer_at')) ?> to take your gun home.</p>

    <h2>Ammunition</h2>
    <p>California requires a background check for every ammunition purchase, using the same ID documents. There's no limit on quantity or caliber. Ammunition bought online can't be shipped to your home in California; have it shipped to us and we'll run the ammunition check when it arrives.</p>

    <h2>Questions?</h2>
    <p>State rules change from time to time. Call <?= phone_link() ?> or <a href="<?= e(url('contact.php')) ?>" style="text-decoration:underline">send us a message</a> and we'll walk you through what applies to your purchase.</p>
  </div>
</div>
<?php page_footer();
