<?php
/** Fonctions utilitaires partagées (i18n, échappement, dates, env). */

/** Échappement HTML. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Charge un fichier .env (format KEY=VALUE) dans un tableau. */
function env_load(string $file): array
{
    if (!is_readable($file)) {
        return [];
    }
    $vars = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $vars[trim($key)] = trim(trim($value), "\"'");
    }

    return $vars;
}

/** Traductions : accès par chemin pointé, ex. t('rsvp.title'). */
final class I18n
{
    public function __construct(
        private array $messages,
        public readonly string $locale,
    ) {
    }

    public function get(string $path, string|int|null ...$args): string
    {
        $node = $this->messages;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                return $path;
            }
            $node = $node[$segment];
        }
        if (!is_string($node)) {
            return $path;
        }

        return $args === [] ? $node : vsprintf($node, $args);
    }

    public function all(): array
    {
        return $this->messages;
    }

    /** Date longue localisée : « samedi 23 octobre 2027 à 16:00 ». */
    public function date(DateTimeInterface $date, bool $withTime = true): string
    {
        $months = $this->messages['months'] ?? [];
        $month  = $months[(int) $date->format('n') - 1] ?? $date->format('F');
        $out    = $date->format('j') . ' ' . $month . ' ' . $date->format('Y');

        return $withTime ? $out . ' ' . ($this->messages['at'] ?? '') . ' ' . $date->format('H:i') : $out;
    }
}

/** Détermine la langue demandée (GET > cookie > Accept-Language > défaut). */
function detect_locale(array $available, string $default): string
{
    $candidate = $_GET['lang'] ?? $_COOKIE['lang'] ?? null;
    if (is_string($candidate) && isset($available[$candidate])) {
        return $candidate;
    }
    $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    foreach (explode(',', $header) as $chunk) {
        $code = strtolower(substr(trim(explode(';', $chunk)[0]), 0, 2));
        if (isset($available[$code])) {
            return $code;
        }
    }

    return $default;
}

/** URL absolue du site (utile pour les balises OpenGraph). */
function base_url(): string
{
    $https  = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');

    return ($https ? 'https://' : 'http://') . $host . $script;
}
