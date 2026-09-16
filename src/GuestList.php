<?php
/**
 * Liste des invités, tenue dans un simple fichier CSV (config/invites.csv).
 * Une ligne = une personne. Séparateur « ; » ou « , » détecté automatiquement.
 *
 * Colonnes reconnues (l'ordre n'importe pas, seul l'en-tête compte) :
 *   jeton   (obligatoire) — identifiant du lien personnel, ex. ?i=a7f3k2qd
 *   prenom  (obligatoire)
 *   nom     (obligatoire)
 *   groupe  (facultatif)  — regroupe un foyer : propose les proches à ajouter
 *   langue  (facultatif)  — fr ou ro : langue d'ouverture du site pour cet invité
 */
final class GuestList
{
    /** @var array<string, array{token:string,firstname:string,lastname:string,group:string,locale:string}> */
    private array $byToken = [];
    /** @var array<string, string> clé normalisée du nom => jeton */
    private array $byName = [];

    private const ALIASES = [
        'token'     => ['jeton', 'token', 'cle', 'cléf', 'clef', 'code', 'id'],
        'firstname' => ['prenom', 'prénom', 'firstname', 'first_name', 'first'],
        'lastname'  => ['nom', 'lastname', 'last_name', 'last', 'famille'],
        'group'     => ['groupe', 'group', 'foyer', 'famille_id', 'household'],
        'locale'    => ['langue', 'locale', 'lang', 'language'],
    ];

    public function __construct(private string $file)
    {
        $this->load();
    }

    private function load(): void
    {
        if (!is_readable($this->file)) {
            return;
        }
        $handle = fopen($this->file, 'r');
        if ($handle === false) {
            return;
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            return;
        }
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine) ?? $firstLine; // BOM Excel
        $separator = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

        $header = str_getcsv(trim($firstLine), $separator);
        $map    = [];
        foreach ($header as $position => $label) {
            $normalized = self::slug($label);
            foreach (self::ALIASES as $field => $aliases) {
                if (in_array($normalized, array_map([self::class, 'slug'], $aliases), true)) {
                    $map[$field] = $position;
                }
            }
        }
        if (!isset($map['token'], $map['firstname'], $map['lastname'])) {
            fclose($handle);
            return; // en-tête inutilisable : liste considérée comme vide
        }

        while (($row = fgetcsv($handle, 0, $separator)) !== false) {
            $token = strtolower(trim((string) ($row[$map['token']] ?? '')));
            if ($token === '' || str_starts_with($token, '#')) {
                continue;
            }
            $guest = [
                'token'     => $token,
                'firstname' => trim((string) ($row[$map['firstname']] ?? '')),
                'lastname'  => trim((string) ($row[$map['lastname']] ?? '')),
                'group'     => isset($map['group']) ? trim((string) ($row[$map['group']] ?? '')) : '',
                'locale'    => isset($map['locale']) ? strtolower(trim((string) ($row[$map['locale']] ?? ''))) : '',
            ];
            $this->byToken[$token] = $guest;

            $key = self::nameKey($guest['firstname'], $guest['lastname']);
            if ($key !== '' && !isset($this->byName[$key])) {
                $this->byName[$key] = $token;
            }
        }
        fclose($handle);
    }

    public function isEmpty(): bool
    {
        return $this->byToken === [];
    }

    /** @return array<int, array> */
    public function all(): array
    {
        return array_values($this->byToken);
    }

    public function findByToken(?string $token): ?array
    {
        $token = strtolower(trim((string) $token));

        return $token !== '' ? ($this->byToken[$token] ?? null) : null;
    }

    public function findByName(string $firstname, string $lastname): ?array
    {
        $key = self::nameKey($firstname, $lastname);

        return isset($this->byName[$key]) ? $this->byToken[$this->byName[$key]] : null;
    }

    /**
     * Les autres membres du même groupe (foyer), pour proposer une liste déroulante.
     *
     * @return array<int, array>
     */
    public function groupMembers(array $guest): array
    {
        if (($guest['group'] ?? '') === '') {
            return [];
        }
        $group  = self::slug($guest['group']);
        $others = [];
        foreach ($this->byToken as $candidate) {
            if ($candidate['token'] !== $guest['token'] && self::slug($candidate['group']) === $group) {
                $others[] = $candidate;
            }
        }

        return $others;
    }

    /** Clé de comparaison d'un nom : insensible à la casse, aux accents et à la ponctuation. */
    public static function nameKey(string $firstname, string $lastname): string
    {
        return self::slug($firstname . $lastname);
    }

    private static function slug(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false) {
            $value = strtolower($converted);
        }

        return (string) preg_replace('/[^a-z0-9]+/', '', $value);
    }
}
