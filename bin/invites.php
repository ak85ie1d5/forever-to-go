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
 * $strict interdit un hôte inutilisable dans un e-mail (id de conteneur, localhost…) ;
 * $warn affiche un simple avertissement dans le cas contraire.
 */
function site_url(string $root, bool $strict = false, bool $warn = true): string
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
        if ($warn) {
            fwrite(STDERR, "Attention — " . $message . "\n");
        }
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

/** DSN sans identifiants, pour l'affichage. */
function dsn_label(string $dsn): string
{
    if ($dsn === '') {
        return 'mail() du système';
    }
    $parts = parse_url($dsn);
    if ($parts === false || !isset($parts['host'])) {
        return '(DSN illisible)';
    }
    $label = ($parts['scheme'] ?? 'smtp') . '://';
    if (isset($parts['user'])) {
        $label .= $parts['user'] . ':***@';
    }

    return $label . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
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
            $name = trim($guest['firstname'] . ' ' . $guest['lastname']);
            if (GuestList::isChild($guest)) {
                printf("%-28s (enfant — déclaré par ses parents)\n", $name);
                continue;
            }
            printf("%-28s %s\n", $name, invitation($guest, $site, $config, $root)['link']);
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
        $kids = [];
        foreach (selection(new GuestList($csv), $token) as $guest) {
            // Les enfants sont déclarés par leurs parents : pas d'invitation séparée.
            if (GuestList::isChild($guest)) {
                $kids[] = trim($guest['firstname'] . ' ' . $guest['lastname']);
                continue;
            }
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
        if ($kids !== []) {
            echo 'Enfants ignorés (déclarés par leurs parents) : ' . implode(', ', $kids) . "\n";
        }
        break;

    // -----------------------------------------------------------------------
    case 'envoyer':
        $dsn = env_get('MAILER_DSN');

        // Boîte de test locale (maildev, mailpit…) : rien ne sort de la machine,
        // une adresse de site locale est donc parfaitement acceptable.
        $dsnHost  = strtolower((string) parse_url($dsn, PHP_URL_HOST));
        $testInbox = in_array($dsnHost, ['maildev', 'mailpit', 'mailhog', 'localhost', '127.0.0.1', '::1'], true);

        // Vers un vrai serveur d'envoi, en revanche, un lien inutilisable ne se rattrape pas.
        $site = site_url($root, !$testInbox, !$testInbox);

        echo "Site : $site\n";
        echo 'Envoi : ' . dsn_label($dsn)
            . ($testInbox ? " — boîte de test locale, les invités ne recevront rien.\n\n" : "\n\n");

        $mailer  = new Mailer($dsn);
        $sent    = 0;
        $skipped = [];
        $kids    = [];

        foreach (selection(new GuestList($csv), $token) as $guest) {
            $name = trim($guest['firstname'] . ' ' . $guest['lastname']);
            if (GuestList::isChild($guest)) {
                $kids[] = $name;
                continue;
            }
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
        if ($kids !== []) {
            echo 'Enfants, invités via leurs parents : ' . implode(', ', $kids) . "\n";
        }
        if ($skipped !== []) {
            echo 'ADULTES SANS ADRESSE — à inviter autrement : ' . implode(', ', $skipped) . "\n";
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
        $hair = 0;
        $makeup = 0;
        foreach ($list->all() as $guest) {
            $answer = $rsvp->findByInvite($guest['token']);
            $name   = trim($guest['firstname'] . ' ' . $guest['lastname']);
            if ($answer === null) {
                $no[] = $name . (GuestList::isChild($guest) ? ' (enfant)' : '');
                continue;
            }
            $self = null;
            foreach ($answer['guests'] as $person) {
                if (($person['token'] ?? '') === $guest['token']) {
                    $self = $person;
                }
            }
            $hair   += $self && !empty($self['hair']) ? 1 : 0;
            $makeup += $self && !empty($self['makeup']) ? 1 : 0;

            $detail = $self && !empty($self['is_child']) ? 'enfant' : 'adulte';
            if ($self && trim((string) $self['allergens']) !== '') {
                $detail .= ', ' . $self['allergens'];
            }
            foreach (['hair' => 'coiffure', 'makeup' => 'maquillage'] as $key => $label) {
                if ($self && !empty($self[$key])) {
                    $detail .= ', ' . $label;
                }
            }
            $yes[] = sprintf('%-28s %s  (%s)', $name, substr((string) $answer['created_at'], 0, 10), $detail);
        }
        echo 'Confirmés (' . count($yes) . ") :\n";
        echo $yes === [] ? "  —\n" : '  ' . implode("\n  ", $yes) . "\n";
        echo "\nSans réponse (" . count($no) . ") :\n";
        echo $no === [] ? "  —\n" : '  ' . implode("\n  ", $no) . "\n";
        echo "\nPrestations du 23/10 à 13:00 — coiffure : $hair · maquillage : $makeup\n";

        // Un enfant ne reçoit pas d'invitation : il faut qu'un adulte de son foyer
        // en reçoive une, sans quoi personne ne pourra le déclarer.
        $orphans = [];
        foreach ($list->all() as $guest) {
            if (!GuestList::isChild($guest)) {
                continue;
            }
            $reachable = false;
            foreach ($list->groupMembers($guest) as $member) {
                if (!GuestList::isChild($member) && filter_var($member['email'], FILTER_VALIDATE_EMAIL)) {
                    $reachable = true;
                    break;
                }
            }
            if (!$reachable) {
                $orphans[] = trim($guest['firstname'] . ' ' . $guest['lastname'])
                    . ($guest['group'] === '' ? ' (aucun groupe)' : ' (groupe « ' . $guest['group'] . ' »)');
            }
        }
        if ($orphans !== []) {
            echo "\nÀ CORRIGER — enfants que personne ne peut déclarer,\n"
                . "faute d'un adulte avec adresse e-mail dans leur groupe :\n  "
                . implode("\n  ", $orphans) . "\n";
        }
        break;
}
