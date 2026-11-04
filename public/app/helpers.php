<?php
// Formatting and small shared helpers.

/** A query-string value as text. Arrays (?stock[]=x) become the default instead of "Array" or a crash. */
function get_str(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? $v : $default;
}

/** Like get_str(), but also looks in the posted form (query string first). */
function req_str(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $_POST[$key] ?? $default;
    return is_string($v) ? $v : $default;
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Site-relative URL under the base path, e.g. url('inventory.php', ['type' => 'handgun']). */
function url(string $path = '', array $query = []): string
{
    $u = config('base_url') . ltrim($path, '/');
    $query = array_filter($query, fn($v) => $v !== null && $v !== '');
    if ($query) {
        $u .= '?' . http_build_query($query);
    }
    return $u;
}

function asset(string $path): string
{
    $file = __DIR__ . '/../' . $path;
    $v = is_file($file) ? filemtime($file) : 0;
    return url($path) . '?v=' . $v;
}

/** Photo path from listing_photos: a full URL, or a path relative to the site root. */
function photo_url(?string $path): ?string
{
    if ($path === null || $path === '') {
        return null;
    }
    return preg_match('~^https?://~i', $path) ? $path : url($path);
}

/** Small version of a stored photo (name-sm.jpg made at upload) if it exists. */
function thumb_url(?string $path): ?string
{
    if ($path !== null && preg_match('~^photos/.+\.jpg$~', $path)) {
        $sm = substr($path, 0, -4) . '-sm.jpg';
        if (is_file(__DIR__ . '/../' . $sm)) {
            return url($sm);
        }
    }
    return photo_url($path);
}

const STOCK_PHOTO_NOTE = "Stock photo from the manufacturer. It may not match this exact variant. Check the manufacturer's website or come in to see it.";

/**
 * Manufacturer stock photo of a firearm model: stock-photos/<slug>.jpg (and <slug>-sm.jpg),
 * fetched with dev/fetch_stock_photos.php or uploaded in Admin > Firearms. NULL if none.
 */
function stock_photo_url(?string $slug, bool $small = false): ?string
{
    if ($slug === null || !preg_match('~^[a-z0-9-]+$~', $slug)) {
        return null;
    }
    $rel = 'stock-photos/' . $slug . ($small ? '-sm' : '') . '.jpg';
    $file = __DIR__ . '/../' . $rel;
    return is_file($file) ? url($rel) . '?v=' . filemtime($file) : null;
}

/** Number without trailing zeros: 4.60 -> "4.6", 1154 -> "1,154". */
function num($n, int $maxDecimals = 2): string
{
    $s = number_format((float)$n, $maxDecimals, '.', ',');
    if (strpos($s, '.') !== false) {
        $s = rtrim(rtrim($s, '0'), '.');
    }
    return $s;
}

function money($usd): string
{
    if ($usd === null || $usd === '') {
        return '';
    }
    $usd = (float)$usd;
    return '$' . number_format($usd, floor($usd) == $usd ? 0 : 2);
}

/** "4.02 in / 102 mm" (card) — the metric figure is rounded to whole mm. */
function length_pair($inches, $mm): string
{
    if ($inches === null) {
        return '';
    }
    return num($inches) . ' in / ' . num($mm, 0) . ' mm';
}

/** Weight for cards: ounces and grams for handguns, pounds and kilograms for long guns. */
function weight_pair($oz, ?string $category): string
{
    if ($oz === null) {
        return '';
    }
    if ($category === 'handgun') {
        return num($oz, 1) . ' oz / ' . num(round($oz * 28.3495), 0) . ' g';
    }
    return num($oz / 16, 2) . ' lb / ' . num($oz * 0.0283495, 2) . ' kg';
}

function type_label(string $category): string
{
    return ['handgun' => 'Handgun', 'rifle' => 'Rifle', 'shotgun' => 'Shotgun', 'other' => 'Other'][$category] ?? ucfirst($category);
}

function capacity_text($capacity, bool $long = false): string
{
    if ($capacity === null) {
        return '';
    }
    return $capacity . ($long ? ((int)$capacity === 1 ? ' round' : ' rounds') : ' rds');
}

/** Public status badges: label, colours and tooltip (from CLAUDE.md). */
function status_info(string $status): ?array
{
    $phone = shop('phone');
    $all = [
        'available'   => ['Available',    '#1F5A40', '#E4EFE9', "Ready to buy today. Call $phone or use \"Ask about this gun\" to get started."],
        'on_hold'     => ['On hold',      '#7A4F00', '#FBEED3', "A buyer has asked us to set this gun aside. Holds sometimes fall through, so call $phone or check back soon."],
        'pending'     => ['Sale pending', '#5B3F8C', '#EFE9F7', "A sale is in progress and this gun is waiting on transfer paperwork. Call $phone and we will let you know if it comes back."],
        'coming_soon' => ['Coming soon',  '#1F4E79', '#E3EDF7', "This gun is being inspected and photographed. Call $phone to be notified when it is listed, or check back soon."],
    ];
    if (!isset($all[$status])) {
        return null;
    }
    [$label, $fg, $bg, $tip] = $all[$status];
    return ['label' => $label, 'fg' => $fg, 'bg' => $bg, 'tip' => $tip];
}

const OFF_ROSTER_NOTE = 'Not on current CA handgun roster';
const CA_CAPACITY_NOTE = '(10 round limit in CA)';
const EXEMPT_LABEL = 'Roster exempt';
const EXEMPT_NOTE = 'Single-action revolver: exempt from the California handgun roster, so it is not on the roster and may be sold new to any eligible buyer.';
const LEO_LABEL = 'LEO Sales Only';
const LEO_NOTE = 'New handgun not on the current CA handgun roster: available to qualifying law enforcement buyers only.';

/**
 * "LEO Sales Only": a NEW handgun whose model is marked off the CA roster (Michael, 2026-09-30).
 * Worked out, not stored, so it follows the model's roster status. Unchecked (NULL) models aren't flagged.
 */
function leo_only(?string $newUsed, ?string $category, $caRostered, $exempt = 0): bool
{
    return $newUsed === 'new' && $category === 'handgun' && $caRostered !== null && (int)$caRostered === 0 && !(int)$exempt;
}

/** "LEO Sales Only" badge with LEO_NOTE on hover/focus. $class: extra classes (placement, tip-below...). */
function leo_badge(string $class = ''): string
{
    return '<span class="leo-badge ' . e($class) . '">' . tooltip(e(LEO_LABEL) . icon('info', 13), LEO_NOTE, 'leo-badge-btn') . '</span>';
}

/**
 * One spelling per caliber / action: a known alias (value_aliases, any capitals) becomes its
 * canonical value, e.g. ".38 Spl" -> ".38 Special". Unknown values are only trimmed.
 * Merges are made in Admin > Firearms > Tidy values (migration 006 seeded the first ones).
 */
function canonical_value(string $field, ?string $value): ?string
{
    $v = trim((string)$value);
    if ($v === '') {
        return null;
    }
    try {
        $st = db()->prepare('SELECT canonical FROM value_aliases WHERE field = ? AND alias = ?');
        $st->execute([$field, $v]);
        $c = $st->fetchColumn();
    } catch (PDOException $e) {
        $c = false;   // value_aliases not created yet (before migration 006)
    }
    return $c !== false ? (string)$c : $v;
}

/** Roster facet value for a listing. */
function roster_label(string $category, $caRostered, $exempt = 0): string
{
    if ($category !== 'handgun') {
        return 'Not required (long guns)';
    }
    if ((int)$exempt) {
        return EXEMPT_LABEL;
    }
    if ($caRostered === null) {
        return 'Not yet checked';
    }
    return (int)$caRostered === 1 ? 'On roster' : 'Off roster';
}

/** Hover/focus tooltip. $trigger is trusted HTML; $tip is plain text. */
function tooltip(string $trigger, string $tip, string $class = '', string $style = ''): string
{
    static $n = 0;
    $id = 'tip-' . (++$n);
    return '<span class="tip">'
        . '<button type="button" class="tip-trigger ' . e($class) . '"' . ($style ? ' style="' . e($style) . '"' : '')
        . ' aria-describedby="' . $id . '">' . $trigger . '</button>'
        . '<span role="tooltip" id="' . $id . '" class="tip-bubble">' . e($tip) . '</span></span>';
}

function icon(string $name, int $size = 18, string $extra = ''): string
{
    $paths = [
        'search'  => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
        'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'info'    => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'check'   => '<path d="M5 12l5 5 9-10"/>',
        'close'   => '<path d="M6 6l12 12M18 6L6 18"/>',
        'prev'    => '<path d="M15 6l-6 6 6 6"/>',
        'next'    => '<path d="M9 6l6 6-6 6"/>',
        'chevron' => '<path d="M6 9l6 6 6-6"/>',
        'phone'   => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
        'photo'   => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.5"/><path d="M21 16l-5-5-8 8"/>',
        'zoom'    => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5M11 8v6M8 11h6"/>',
        'camera'  => '<path d="M4 7h3l2-3h6l2 3h3v12H4z"/><circle cx="12" cy="13" r="3.5"/>',
        'graded'  => '<path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9"/>',
        'ruler'   => '<path d="M3 17l14-14 4 4L7 21H3z"/><path d="M7 13l2 2M10 10l2 2M13 7l2 2"/>',
        'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'plus'    => '<path d="M12 5v14M5 12h14"/>',
        'link'    => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
    ];
    return '<svg class="icon ' . e($extra) . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . ($paths[$name] ?? '') . '</svg>';
}

function phone_link(string $class = '', string $label = ''): string
{
    $label = $label !== '' ? $label : e(shop('phone'));
    $tel = shop('phone_tel');
    return $tel !== ''
        ? '<a class="' . e($class) . '" href="tel:' . e($tel) . '">' . $label . '</a>'
        : '<span class="' . e($class) . '">' . $label . '</span>';
}

/**
 * Browser tab / home screen icon. A site can have its own (sites/<site>/favicon.png, uploaded by
 * deploy.ps1 as assets/site-icon.png; served from sites/<site>/ locally); otherwise the shared one.
 */
function favicon_tags(): string
{
    $own = defined('SITE_DIR') ? is_file(SITE_DIR . '/favicon.png') : is_file(__DIR__ . '/../assets/site-icon.png');
    if ($own) {
        $u = e(asset('assets/site-icon.png'));
        return '<link rel="icon" href="' . $u . '" type="image/png">' . "\n" . '<link rel="apple-touch-icon" href="' . $u . '">' . "\n";
    }
    return '<link rel="icon" href="' . e(asset('assets/favicon.svg')) . '" type="image/svg+xml">' . "\n"
         . '<link rel="apple-touch-icon" href="' . e(asset('assets/favicon.png')) . '">' . "\n";
}
