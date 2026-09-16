<?php
declare(strict_types=1);

/**
 * Utilitaire de gestion de la liste des invités (à lancer dans le conteneur PHP) :
 *
 *   php bin/invites.php jetons              génère les jetons manquants dans le CSV
 *   php bin/invites.php liens [URL]         affiche le lien personnel de chaque invité
 *   php bin/invites.php etat                qui a répondu, qui n'a pas encore répondu
 *
 * Exemple : docker compose exec php php bin/invites.php liens https://mariage.exemple.fr
 */

$root = dirname(__DIR__);
require $root . '/src/helpers.php';
require $root . '/src/Rsvp.php';
require $root . '/src/GuestList.php';

$config  = require $root . '/config/settings.php';
$csv     = $config['guest_list'];
$command = $argv[1] ?? 'etat';

/** Relit le CSV brut (en-tête + lignes) en conservant le séparateur. */
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
    case 'tokens':
        [$header, $rows, $sep] = read_csv($csv);
        $tokenAt = column($header, ['jeton', 'token', 'code', 'id']);
        if ($tokenAt === null) {
            fwrite(STDERR, "Colonne « jeton » absente de l'en-tête.\n");
            exit(1);
        }
        $added = 0;
        foreach ($rows as $index => $row) {
            if (trim((string) ($row[$tokenAt] ?? '')) === '' && trim(implode('', $row)) !== '') {
                $rows[$index][$tokenAt] = substr(bin2hex(random_bytes(8)), 0, 8);
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
    case 'links':
        $base = rtrim($argv[2] ?? (string) (env_load($root . '/.env.local')['URL'] ?? 'https://exemple.fr'), '/');
        $list = new GuestList($csv);
        foreach ($list->all() as $guest) {
            printf("%-28s %s/?i=%s\n", trim($guest['firstname'] . ' ' . $guest['lastname']), $base, $guest['token']);
        }
        break;

    // -----------------------------------------------------------------------
    case 'etat':
    case 'status':
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
        echo "Confirmés (" . count($yes) . ") :\n";
        echo $yes === [] ? "  —\n" : '  ' . implode("\n  ", $yes) . "\n";
        echo "\nSans réponse (" . count($no) . ") :\n";
        echo $no === [] ? "  —\n" : '  ' . implode("\n  ", $no) . "\n";
        break;
}
