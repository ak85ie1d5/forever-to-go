<?php
/**
 * Limiteur d'essais : empêche de deviner un jeton d'invitation par force brute.
 * Un petit fichier par adresse IP, purgé automatiquement à l'expiration de la fenêtre.
 */
final class Throttle
{
    public function __construct(
        private string $dir,
        private int $max = 20,       // essais infructueux tolérés…
        private int $window = 3600,  // …par heure
    ) {
    }

    /** Trop d'essais récents pour cette adresse ? */
    public function blocked(string $key): bool
    {
        $state = $this->read($key);

        return $state !== null && $state['n'] >= $this->max;
    }

    /** Enregistre un essai infructueux. */
    public function hit(string $key): void
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0775, true)) {
            return;
        }
        $state = $this->read($key) ?? ['n' => 0, 't' => time()];
        $state['n']++;
        @file_put_contents($this->file($key), json_encode($state), LOCK_EX);
    }

    private function read(string $key): ?array
    {
        $file = $this->file($key);
        if (!is_readable($file)) {
            return null;
        }
        $state = json_decode((string) file_get_contents($file), true);
        if (!is_array($state) || !isset($state['n'], $state['t'])) {
            return null;
        }
        if (time() - (int) $state['t'] > $this->window) {
            @unlink($file); // fenêtre écoulée : on repart de zéro
            return null;
        }

        return ['n' => (int) $state['n'], 't' => (int) $state['t']];
    }

    private function file(string $key): string
    {
        return $this->dir . '/' . hash('sha256', $key) . '.json';
    }
}
