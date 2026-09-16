<?php
declare(strict_types=1);

/**
 * Julia & Jérémy — 23 octobre 2027, château de Pontarmé.
 * Front controller unique : sert la SPA et traite le formulaire de présence (POST JSON).
 */

$root = dirname(__DIR__);

require $root . '/src/helpers.php';
require $root . '/src/Mailer.php';
require $root . '/src/Rsvp.php';

$config = require $root . '/config/settings.php';
$env    = env_load($root . '/.env.local');

date_default_timezone_set('Europe/Paris');
mb_internal_encoding('UTF-8');

// ---------------------------------------------------------------------------
// Langue
// ---------------------------------------------------------------------------
$locale   = detect_locale($config['locales'], $config['default_locale']);
$messages = require $root . '/config/lang/' . $locale . '.php';
$t        = new I18n($messages, $locale);

$allMessages = [];
foreach (array_keys($config['locales']) as $code) {
    $allMessages[$code] = require $root . '/config/lang/' . $code . '.php';
}

if (($_COOKIE['lang'] ?? null) !== $locale) {
    setcookie('lang', $locale, [
        'expires'  => time() + 400 * 86400,
        'path'     => '/',
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

// ---------------------------------------------------------------------------
// Dates clés
// ---------------------------------------------------------------------------
$weddingDate = new DateTimeImmutable($config['wedding_date']);
$deadline    = new DateTimeImmutable($config['rsvp_deadline']);

// ---------------------------------------------------------------------------
// Réponse déjà envoyée ?
// ---------------------------------------------------------------------------
$rsvp       = new Rsvp($config['storage_dir']);
$submission = $rsvp->find($_COOKIE[Rsvp::COOKIE] ?? null);

// ---------------------------------------------------------------------------
// POST : enregistrement d'une confirmation (appel fetch depuis la SPA)
// ---------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    // La SPA envoie du JSON ; sans JavaScript on retombe sur un POST classique + redirection.
    $wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
    }
    header('X-Content-Type-Options: nosniff');

    $respond = static function (int $status, array $body) use ($wantsJson): never {
        if ($wantsJson) {
            http_response_code($status);
            echo json_encode($body, JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: /' . ($status < 400 ? '?s=rsvp' : '?s=rsvp&error=1') . '#rsvp', true, 303);
        exit;
    };

    $raw     = file_get_contents('php://input') ?: '';
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    // Déjà répondu : on renvoie simplement le récapitulatif existant.
    if ($submission !== null) {
        $respond(200, ['ok' => true, 'already' => true, 'summary' => render_summary($submission, $allMessages, $config, $locale)]);
    }

    // Pot de miel anti-robots.
    if (trim((string) ($payload['website'] ?? '')) !== '') {
        $respond(422, ['ok' => false, 'errors' => ['global' => $t->get('rsvp.error')]]);
    }

    [$errors, $data] = $rsvp->validate($payload, $t);

    if ($errors !== []) {
        $respond(422, ['ok' => false, 'errors' => $errors]);
    }

    if (!$rsvp->save($data)) {
        $respond(500, ['ok' => false, 'errors' => ['global' => $t->get('rsvp.error')]]);
    }

    $rsvp->rememberCookie($data['token']);

    $mailer = new Mailer((string) ($env['MAILER_DSN'] ?? getenv('MAILER_DSN') ?: ''));
    $mailSent = $rsvp->notify($data, $config['mail'], $mailer, $t);
    if (!$mailSent) {
        error_log('[RSVP] Notification e-mail non envoyée (' . $data['token'] . ') : ' . implode(' | ', $mailer->errors()));
    }

    $respond(200, [
        'ok'      => true,
        'mail'    => $mailSent,
        'summary' => render_summary($data, $allMessages, $config, $locale),
    ]);
}

// ---------------------------------------------------------------------------
// Rendu de la SPA
// ---------------------------------------------------------------------------

/** Récapitulatif (fragment HTML réutilisé côté serveur et après envoi fetch). */
function render_summary(array $data, array $allMessages, array $config, string $viewLocale): string
{
    ob_start();
    include dirname(__DIR__) . '/src/views/summary.php';

    return trim((string) ob_get_clean());
}

/** Aplatit les traductions en clés pointées pour le JavaScript. */
function flatten_messages(array $messages, string $prefix = ''): array
{
    $flat = [];
    foreach ($messages as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        if (is_array($value) && array_is_list($value) && $value !== [] && is_string($value[0])) {
            $flat[$path] = $value; // listes (mois…)
        } elseif (is_array($value)) {
            $flat += flatten_messages($value, $path);
        } else {
            $flat[$path] = $value;
        }
    }

    return $flat;
}

// Ancrage initial : ?s=rsvp ou /rsvp (si la réécriture d'URL est active).
$routes = ['' => 'accueil', 'le-jour-j' => 'jour-j', 'hebergements' => 'hebergements', 'rsvp' => 'rsvp'];
$path   = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/');
$route  = $routes[(string) ($_GET['s'] ?? $path)] ?? ($routes[$path] ?? 'accueil');

$prefill = [
    'firstname' => mb_substr(trim((string) ($_GET['firstname'] ?? '')), 0, 60),
    'lastname'  => mb_substr(trim((string) ($_GET['lastname'] ?? '')), 0, 60),
];

$jsPayload = [
    'locale'      => $locale,
    'locales'     => array_keys($config['locales']),
    'weddingDate' => $weddingDate->format(DateTimeInterface::ATOM),
    'route'       => $route,
    'submitted'   => $submission !== null,
    'messages'    => array_map(static fn($m) => flatten_messages($m), $allMessages),
];

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

include $root . '/src/views/layout.php';
