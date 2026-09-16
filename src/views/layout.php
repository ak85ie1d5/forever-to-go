<?php
/**
 * Squelette de la SPA.
 * @var array $config
 * @var I18n $t
 * @var string $locale
 * @var DateTimeImmutable $weddingDate
 * @var DateTimeImmutable $deadline
 * @var array|null $submission
 * @var array $prefill
 * @var array $jsPayload
 * @var array $allMessages
 */
$base      = base_url();
$ogImage   = $base . '/assets/medias/og-share.jpg';
$initials  = $config['couple']['initials'];
$altLocale = $locale === 'fr' ? 'ro' : 'fr';
?>
<!DOCTYPE html>
<html lang="<?= e($locale) ?>" data-section="<?= e($jsPayload['route']) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title data-i18n="meta.title"><?= e($t->get('meta.title')) ?></title>
<meta name="description" data-i18n-attr="content:meta.description" content="<?= e($t->get('meta.description')) ?>">
<meta name="theme-color" content="#F7F3EC">
<meta name="robots" content="index, follow">
<link rel="canonical" href="<?= e($base) ?>/">
<link rel="alternate" hreflang="fr" href="<?= e($base) ?>/?lang=fr">
<link rel="alternate" hreflang="ro" href="<?= e($base) ?>/?lang=ro">
<link rel="alternate" hreflang="x-default" href="<?= e($base) ?>/">

<!-- OpenGraph -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="Julia &amp; Jérémy">
<meta property="og:title" data-i18n-attr="content:meta.og_title" content="<?= e($t->get('meta.og_title')) ?>">
<meta property="og:description" data-i18n-attr="content:meta.og_desc" content="<?= e($t->get('meta.og_desc')) ?>">
<meta property="og:url" content="<?= e($base) ?>/">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta property="og:image:secure_url" content="<?= e($ogImage) ?>">
<meta property="og:image:type" content="image/jpeg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" data-i18n-attr="content:meta.og_title" content="<?= e($t->get('meta.og_title')) ?>">
<meta property="og:locale" content="<?= e($t->get('locale_tag')) ?>">
<meta property="og:locale:alternate" content="<?= e($allMessages[$altLocale]['locale_tag']) ?>">
<meta property="article:published_time" content="<?= e($weddingDate->format(DateTimeInterface::ATOM)) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" data-i18n-attr="content:meta.og_title" content="<?= e($t->get('meta.og_title')) ?>">
<meta name="twitter:description" data-i18n-attr="content:meta.og_desc" content="<?= e($t->get('meta.og_desc')) ?>">
<meta name="twitter:image" content="<?= e($ogImage) ?>">

<link rel="icon" href="/assets/medias/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/assets/medias/og-share.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Pinyon+Script&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css?v=<?= e((string) @filemtime(__DIR__ . '/../../public/assets/css/style.css')) ?>">

<script type="application/ld+json">
<?= json_encode([
    '@context'  => 'https://schema.org',
    '@type'     => 'Event',
    'name'      => 'Julia & Jérémy',
    'startDate' => $weddingDate->format(DateTimeInterface::ATOM),
    'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
    'location'  => [
        '@type'   => 'Place',
        'name'    => 'Château de Pontarmé',
        'address' => ['@type' => 'PostalAddress', 'postalCode' => '60520', 'addressLocality' => 'Pontarmé', 'addressCountry' => 'FR'],
    ],
    'image'       => [$ogImage],
    'description' => $t->get('meta.og_desc'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
</script>
</head>
<body class="no-js">
<script>document.body.classList.remove('no-js');</script>

<a class="skip-link" href="#contenu" data-i18n="nav.home"><?= e($t->get('nav.home')) ?></a>

<header class="site-header" id="header">
    <div class="site-header__inner">
        <a class="brand" href="#accueil" data-route="accueil" aria-label="Julia &amp; Jérémy">
            <span class="brand__monogram"><?= e($initials) ?></span>
            <span class="brand__names">Julia &amp; Jérémy</span>
        </a>

        <button class="burger" type="button" aria-expanded="false" aria-controls="nav-principal">
            <span class="burger__bar"></span><span class="burger__bar"></span><span class="burger__bar"></span>
            <span class="sr-only" data-i18n="nav.menu"><?= e($t->get('nav.menu')) ?></span>
        </button>

        <nav class="nav" id="nav-principal" aria-label="Navigation">
            <a class="nav__link" href="#accueil" data-route="accueil" data-i18n="nav.home"><?= e($t->get('nav.home')) ?></a>
            <a class="nav__link" href="#jour-j" data-route="jour-j" data-i18n="nav.day"><?= e($t->get('nav.day')) ?></a>
            <a class="nav__link" href="#hebergements" data-route="hebergements" data-i18n="nav.stay"><?= e($t->get('nav.stay')) ?></a>
            <a class="nav__link nav__link--cta" href="#rsvp" data-route="rsvp" data-i18n="nav.rsvp"><?= e($t->get('nav.rsvp')) ?></a>

            <div class="lang" role="group" aria-label="Langue / Limbă">
                <?php foreach ($config['locales'] as $code => $label): ?>
                    <button type="button" class="lang__btn<?= $code === $locale ? ' is-active' : '' ?>" data-lang="<?= e($code) ?>" lang="<?= e($code) ?>" title="<?= e($label) ?>"><?= e(strtoupper($code)) ?></button>
                <?php endforeach; ?>
            </div>
        </nav>
    </div>
    <div class="site-header__lace" aria-hidden="true"></div>
</header>

<main id="contenu">
    <?php include __DIR__ . '/hero.php'; ?>
    <?php include __DIR__ . '/timeline.php'; ?>
    <?php include __DIR__ . '/stay.php'; ?>
    <?php include __DIR__ . '/rsvp.php'; ?>
</main>

<footer class="footer">
    <div class="footer__lace" aria-hidden="true"></div>
    <div class="footer__inner">
        <p class="footer__names script">Julia &amp; Jérémy</p>
        <p class="footer__date" data-i18n="footer.date"><?= e($t->get('footer.date')) ?></p>
        <p class="footer__place" data-i18n="footer.place"><?= e($t->get('footer.place')) ?></p>
        <p class="footer__made" data-i18n="footer.made"><?= e($t->get('footer.made')) ?></p>
    </div>
</footer>

<script id="app-data" type="application/json"><?= json_encode($jsPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="/assets/js/app.js?v=<?= e((string) @filemtime(__DIR__ . '/../../public/assets/js/app.js')) ?>" defer></script>
</body>
</html>
