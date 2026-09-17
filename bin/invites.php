<?php
declare(strict_types=1);

/**
 * Gestion de la liste des invités (à lancer dans le conteneur PHP).
 *
 *   php bin/invites.php jetons            génère les jetons manquants dans le CSV
 *   php bin/invites.php liens             affiche le lien personnel de chaque invité
 *   php bin/invites.php emails [jeton]    prépare les invitations (aperçu .html + .eml)
 *   php bin/invites.php envoyer [jeton]   envoie les invitations — toutes, ou une seule
 *   php bin/invites.php etat              qui a répondu, qui n'a pas encore répondu
 *
 * L'adresse du site vient du nom d'hôte de la machine (HOSTNAME, puis gethostname()),
 * et à défaut de URL=... dans .env.local.
 * Exemple : docker compose exec php php bin/invites.php envoyer
 */

$root = dirname(__DIR__);
require $root . '/src/helpers.php';
require $root . '/src/Rsvp.php';
require $root . '/src/GuestList.php';
require $root . '/src/Mailer.php';

$config  = require $root . '/config/settings.php';
$csv     = $config['guest_list'];
$command = $argv[1] ?? 'etat';
$token   = $argv[2] ?? null;   // facultatif : restreint à un seul invité

/**
 * Adresse publique du site, dans cet ordre :
 *   1. HOSTNAME dans .env.local, puis dans l'environnement (Docker, systemd, shell) ;
 *   2. nom d'hôte du système via gethostname() — le seul disponible sous cron ou systemd,
 *      où HOSTNAME n'est pas exporté.
 *
 * $strict interdit un hôte inutilisable dans un e-mail (id de conteneur, localhost…).
 */
function site_url(string $root, bool $strict = false): string
{
    $candidates = [
        env_get('HOSTNAME'),          // .env.local, puis variable d'environnement
        (string) (gethostname() ?: ''), // nom de machine : seul disponible sous cron/systemd
    ];

    $url = '';
    foreach ($candidates as $candidate) {
        $candidate = trim($candidate);
        if ($candidate !== '') {
            $url = $candidate;
            break;
        }
    }

    if ($url === '') {
        fwrite(STDERR, "Adresse du site inconnue.\n  → renseignez HOSTNAME=mariage.votre-domaine.fr dans .env.local\n");
        exit(1);
    }
    if (!preg_match('~^https?://~i', $url)) {
        $url = 'https://' . $url;
    }
    $url  = rtrim($url, '/');
    $host = (string) parse_url($url, PHP_URL_HOST);

    // Un hôte sans point (id de conteneur Docker) ou local ne mène nulle part depuis une boîte mail.
    $usable = str_contains($host, '.')
        && !in_array($host, ['localhost', '127.0.0.1', '::1'], true)
        && !str_ends_with($host, '.local');

    if (!$usable) {
        $message = "L'adresse du site n'est pas publique : $url\n"
            . "  → sur le serveur : hostnamectl set-hostname mariage.votre-domaine.fr\n"
            . "  → ou renseignez HOSTNAME=mariage.votre-domaine.fr dans .env.local\n";
        if ($strict) {
            fwrite(STDERR, $message);
            exit(1);
        }
        fwrite(STDERR, "Attention — " . $message . "\n");
    }

    return $url;
}

/** Message d'invitation d'un invité : sujet, html, texte, lien. */
function invitation(array $guest, string $site, array $config, string $root): array
{
    $locale = isset($config['locales'][$guest['locale']]) ? $guest['locale'] : $config['default_locale'];
    $link   = $site . '/?i=' . rawurlencode($guest['token']) . '&lang=' . $locale;

    $template = $root . '/templates/invitation.' . $locale . '.php';
    if (!is_file($template)) {
        fwrite(STDERR, "Gabarit manquant : $template\n");
        exit(1);
    }

    $translator = new I18n(require $root . '/config/lang/' . $locale . '.php', $locale);
    $deadline   = $translator->date(new DateTimeImmutable($config['rsvp_deadline']), false);
    $contact    = $config['mail']['to'][1] ?? ($config['mail']['to'][0] ?? '');

    $mail = (static function (array $guest, string $link, string $site, string $deadline, string $contact, string $template): array {
        return require $template;
    })($guest, $link, $site, $deadline, $contact, $template);

    return $mail + ['link' => $link, 'locale' => $locale, 'contact' => $contact];
}

/** Les invités concernés : tous, ou celui dont le jeton est passé en paramètre. */
function selection(GuestList $list, ?string $token): array
{
    if ($token === null) {
        return $list->all();
    }
    $guest = $list->findByToken($token);
    if ($guest === null) {
        fwrite(STDERR, "Jeton inconnu : $token\n");
        exit(1);
    }

    return [$guest];
}

function read_csv(string $file): array
{
    if (!is_readable($file)) {
        fwrite(STDERR, "Liste introuvable : $file\n  → copiez config/invites.example.csv vers var/rsvp/invites.csv\n");
        exit(1);
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    $first = preg_replace('/^\xEF\xBB\xBF/', '', $lines[0] ?? '') ?? '';
    $sep   = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
    $rows  = array_map(static fn($line) => str_getcsv($line, $sep), $lines);

    return [array_shift($rows), $rows, $sep];
}

function column(array $header, array $names): ?int
{
    foreach ($header as $index => $label) {
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z]/', '', (string) $label)));
        if (in_array($slug, $names, true)) {
            return $index;
        }
    }

    return null;
}

switch ($command) {
    // -----------------------------------------------------------------------
    case 'jetons':
        [$header, $rows, $sep] = read_csv($csv);
        $tokenAt = column($header, ['jeton', 'token', 'code', 'id']);
        if ($tokenAt === null) {
            fwrite(STDERR, "Colonne « jeton » absente de l'en-tête.\n");
            exit(1);
        }
        $added = 0;
        foreach ($rows as $index => $row) {
            if (trim((string) ($row[$tokenAt] ?? '')) === '' && trim(implode('', $row)) !== '') {
                $rows[$index][$tokenAt] = substr(bin2hex(random_bytes(8)), 0, 12);
                $added++;
            }
        }
        $out = fopen($csv, 'w');
        fputcsv($out, $header, $sep);
        foreach ($rows as $row) {
            if (trim(implode('', $row)) !== '') {
                fputcsv($out, $row, $sep);
            }
        }
        fclose($out);
        echo "$added jeton(s) ajouté(s) dans $csv\n";
        break;

    // -----------------------------------------------------------------------
    case 'liens':
        $site = site_url($root);
        foreach (selection(new GuestList($csv), $token) as $guest) {
            printf("%-28s %s\n", trim($guest['firstname'] . ' ' . $guest['lastname']), invitation($guest, $site, $config, $root)['link']);
        }
        break;

    // -----------------------------------------------------------------------
    case 'emails':
        $site   = site_url($root);
        $outDir = dirname($config['storage_dir']) . '/invitations';
        if (!is_dir($outDir) && !@mkdir($outDir, 0775, true)) {
            fwrite(STDERR, "Impossible de créer $outDir\n");
            exit(1);
        }

        $rows = [['email', 'prenom', 'nom', 'langue', 'sujet', 'lien']];
        $done = 0;
        foreach (selection(new GuestList($csv), $token) as $guest) {
            $mail = invitation($guest, $site, $config, $root);
            file_put_contents($outDir . '/' . $guest['token'] . '.html', $mail['html']);
            if (filter_var($guest['email'], FILTER_VALIDATE_EMAIL)) {
                file_put_contents(
                    $outDir . '/' . $guest['token'] . '.eml',
                    Mailer::rawMessage([$guest['email']], $mail['subject'], $mail['html'], $mail['text'], $config['mail']['from'], $config['mail']['from_name'], $mail['contact'])
                );
            }
            $rows[] = [$guest['email'], $guest['firstname'], $guest['lastname'], $mail['locale'], $mail['subject'], $mail['link']];
            $done++;
        }

        $handle = fopen($outDir . '/publipostage.csv', 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }
        fclose($handle);
        echo "$done invitation(s) préparée(s) dans $outDir\n";
        break;

    // -----------------------------------------------------------------------
    case 'envoyer':
        $site = site_url($root, true); // hôte public obligatoire : un envoi ne se rattrape pas
        echo "Site : $site\n\n";

        $mailer = new Mailer(env_get('MAILER_DSN'));
        $sent   = 0;
        $skipped = [];

        foreach (selection(new GuestList($csv), $token) as $guest) {
            $name = trim($guest['firstname'] . ' ' . $guest['lastname']);
            if (!filter_var($guest['email'], FILTER_VALIDATE_EMAIL)) {
                $skipped[] = $name;
                continue;
            }
            $mail = invitation($guest, $site, $config, $root);
            $ok   = $mailer->send([$guest['email']], $mail['subject'], $mail['html'], $mail['text'], $config['mail']['from'], $config['mail']['from_name'], $mail['contact']);
            printf("%-28s %-30s %s\n", $name, $guest['email'], $ok ? 'envoyé' : 'ÉCHEC');
            $sent += $ok ? 1 : 0;
            usleep(300000);
        }

        echo "\n$sent invitation(s) envoyée(s).\n";
        if ($skipped !== []) {
            echo 'Sans adresse e-mail : ' . implode(', ', $skipped) . "\n";
        }
        if ($mailer->errors() !== []) {
            echo 'Erreurs : ' . implode(' | ', $mailer->errors()) . "\n";
        }
        break;

    // -----------------------------------------------------------------------
    case 'etat':
    default:
        $list = new GuestList($csv);
        $rsvp = new Rsvp($config['storage_dir']);
        $yes  = [];
        $no   = [];
        foreach ($list->all() as $guest) {
            $answer = $rsvp->findByInvite($guest['token']);
            $name   = trim($guest['firstname'] . ' ' . $guest['lastname']);
            if ($answer === null) {
                $no[] = $name;
                continue;
            }
            $self = null;
            foreach ($answer['guests'] as $person) {
                if (($person['token'] ?? '') === $guest['token']) {
                    $self = $person;
                }
            }
            $detail = $self && !empty($self['is_child']) ? 'enfant' : 'adulte';
            if ($self && trim((string) $self['allergens']) !== '') {
                $detail .= ', ' . $self['allergens'];
            }
            $yes[] = sprintf('%-28s %s  (%s)', $name, substr((string) $answer['created_at'], 0, 10), $detail);
        }
        echo 'Confirmés (' . count($yes) . ") :\n";
        echo $yes === [] ? "  —\n" : '  ' . implode("\n  ", $yes) . "\n";
        echo "\nSans réponse (" . count($no) . ") :\n";
        echo $no === [] ? "  —\n" : '  ' . implode("\n  ", $no) . "\n";
        break;
}
