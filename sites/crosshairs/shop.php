<?php
// Shop details shown across the site (uploaded as app/shop.php by deploy.ps1 -Site crosshairs).
// Empty values are hidden on the pages.
// Current content: demo prepared for Crosshairs Gun Store, from the public information on
// https://crosshairsgunstore.com (retrieved 2026-09-29). Not the store's official website.

return [
    'name'          => 'Crosshairs Gun Store',
    'tagline'       => 'Veteran owned and operated in Torrance, California.',
    'street'        => '3902 Pacific Coast Hwy, Ste D',
    'city'          => 'Torrance, CA 90505',
    'hours'         => 'Tuesday–Saturday, 11 AM – 6 PM',
    'phone'         => '(310) 791-4327',
    'phone_tel'     => '+13107914327',
    'phone_alt'     => '(866) 927-4668',
    'email'         => '',            // not published; the contact form is used instead
    'ffl'           => '',            // not published
    'hold_policy'   => '',
    'transfer_at'   => 'our Torrance store',
    // How to buy page, after "A Firearm Safety Certificate (FSC)."
    'fsc_note'      => 'No FSC yet? Take the 25-question test at the shop: $25 ($15 state fee plus $10 processing), and you can get it the same day as your purchase.',

    // About page: line under the phone number, meta description, and the text above "Visit" (HTML, written here by hand).
    'contact_note'  => 'The shop is often too busy to answer the phone, so a message is usually the quickest way to reach us.',
    'about_description' => 'About Crosshairs Gun Store: veteran-owned gun store in Torrance, CA. Services, hours and contact details.',
    'about_html'    => <<<'HTML'
    <p>Crosshairs is a veteran owned and operated gun store that has been serving shooters in California for almost a decade, from our shop on Pacific Coast Highway in Torrance. We sell pistols, rifles, shotguns, ammunition and accessories, and we're NRA affiliated.</p>
    <p>Brands we carry include Glock, SIG Sauer, Ruger, Dan Wesson, Heckler &amp; Koch, Colt, Springfield Armory, Smith &amp; Wesson and Beretta.</p>

    <h2>Services</h2>
    <ul>
      <li><strong>FFL and ammunition transfers.</strong> Buy online and have it shipped to us: $75 per long gun, $50 per handgun or receiver, $15 for each additional firearm, plus the $37.19 California DROS fee. Law enforcement: $50 rifle, $30 handgun or receiver. Ammunition: $10 per 1,000 rounds plus the $1 California ammunition DROS fee.</li>
      <li><strong>Private party transfers.</strong> Buyer and seller meet at the shop by appointment (Wednesdays and Thursdays): $10 per firearm plus the $37.19 California DROS fee.</li>
      <li><strong>Consignment.</strong> We hold your firearm for the state's 30-day period, then sell it for you; our fee is 25% of the selling price.</li>
      <li><strong>We buy used guns.</strong> Send us the details and we'll inspect the firearm and make an offer.</li>
      <li><strong>Firearms shipping.</strong> $30 per long gun, $20 per handgun or receiver, plus FedEx or USPS charges.</li>
      <li><strong>Gunsmithing, classes and training.</strong></li>
    </ul>
    <p class="muted" style="font-size:15px">Fees as listed on the store's website; please call to confirm current pricing.</p>
HTML,

    // Home page hero
    'hero_kicker'   => 'Veteran owned · Torrance, CA',
    'hero_title'    => 'New and used guns, fully specified.',
    'hero_text'     => 'Every used gun is shown in photos of the actual firearm, with an honest condition report. New guns come with manufacturer photos and factory specs.',

    // Demo notice: shown on every page and keeps search engines away while this is a preview.
    // Set to '' once the store adopts the site.
    // Home page picture, cycled in order (files in public/assets/hero/). Empty = a photo of a listed gun.
    'hero_images'   => ['store-inside.jpg', 'flag.jpg', 'storefront.jpg', 'storefront-2.jpg'],
    'demo_notice'   => 'Demo site prepared for Crosshairs Gun Store — not the store\'s official website. For the real store visit crosshairsgunstore.com or call (310) 791-4327.',
];
