<?php
// Shop details shown across the site (uploaded as app/shop.php by deploy.ps1 -Site glendale).
// Empty values are hidden on the pages.
// Current content: demo prepared for Glendale Gun Gallery (Gun Gallery Inc.), from the public
// information on https://glendalegungallery.com (retrieved 2026-11-03). Not the store's official website.

return [
    'name'          => 'Glendale Gun Gallery',
    'tagline'       => 'Professional firearm sales and service for the civilian and law enforcement communities.',
    'street'        => '4019 San Fernando Rd',
    'city'          => 'Glendale, CA 91204',
    'hours'         => 'Monday–Friday, 11 AM – 7 PM · Saturday–Sunday, 11 AM – 6 PM',
    'phone'         => '(818) 244-9800',
    'phone_tel'     => '+18182449800',
    'phone_alt'     => '',
    'email'         => '',            // the store publishes one; left out so messages use the contact form while this is a demo
    'ffl'           => '',            // not published
    'hold_policy'   => '',
    'transfer_at'   => 'our Glendale store',
    // How to buy page, after "A Firearm Safety Certificate (FSC)."
    'fsc_note'      => 'No FSC yet? Call us and we\'ll tell you how to get one.',

    // Home page hero
    'hero_kicker'   => 'Civilian & law enforcement · Glendale, CA',
    'hero_title'    => 'New and used guns, fully specified.',
    'hero_text'     => 'Every used gun is shown in photos of the actual firearm, with an honest condition report. New guns come with manufacturer photos and factory specs.',

    // About page: line under the phone number, meta description, and the text above "Visit" (HTML).
    'contact_note'  => '',
    'about_description' => 'About Glendale Gun Gallery: firearm sales and service for civilians and law enforcement in Glendale, CA. Services, hours and contact details.',
    'about_html'    => <<<'HTML'
    <p>Glendale Gun Gallery is a firearms retailer on San Fernando Road in Glendale, offering professional firearm sales and service to the civilian and law enforcement communities.</p>

    <h2>Services</h2>
    <ul>
      <li><strong>Civilian sales.</strong> California-compliant handguns, rifles and shotguns, plus scopes, sights, cases and accessories.</li>
      <li><strong>Law enforcement.</strong> Sales and factory support for agencies including the Glendale and Los Angeles Police Departments.</li>
      <li><strong>We buy and sell used guns.</strong></li>
      <li><strong>On-site gunsmith.</strong></li>
      <li><strong>Firearm and ammunition transfers.</strong> Ammunition: $60 for 1 to 1,000 rounds; orders over 1,000 rounds are 10% of the invoice amount, plus California sales tax on the ammunition invoice. Ammunition left more than 7 days is charged $5 per day storage.</li>
    </ul>
    <p class="muted" style="font-size:15px">Fees as listed on the store's website; please call to confirm current pricing.</p>
HTML,

    // Home page picture, cycled in order (files in sites/glendale/hero/). Empty = a photo of a listed gun.
    'hero_images'   => [],
    // Demo notice: shown on every page and keeps search engines away while this is a preview.
    // Set to '' once the store adopts the site.
    'demo_notice'   => 'Demo site prepared for Glendale Gun Gallery — not the store\'s official website. For the real store visit glendalegungallery.com or call (818) 244-9800.',
];
