<?php
/**
 * Petit client SMTP (sans dépendance externe) pour notifier les mariés.
 * Lit le DSN depuis MAILER_DSN (.env.local) — ex. smtp://maildev:1025
 * Retombe sur mail() si le SMTP n'est pas joignable.
 */
final class Mailer
{
    private array $dsn;
    private array $errors = [];

    public function __construct(string $dsn = '')
    {
        $this->dsn = self::parseDsn($dsn);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private static function parseDsn(string $dsn): array
    {
        $default = ['scheme' => 'null', 'host' => 'localhost', 'port' => 25, 'user' => null, 'pass' => null, 'tls' => false];
        if ($dsn === '') {
            return $default;
        }
        $parts = parse_url($dsn);
        if ($parts === false || !isset($parts['host'])) {
            return $default;
        }
        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $scheme = $parts['scheme'] ?? 'smtp';

        return [
            'scheme' => $scheme,
            'host'   => $parts['host'],
            'port'   => (int) ($parts['port'] ?? ($scheme === 'smtps' ? 465 : 25)),
            'user'   => isset($parts['user']) ? rawurldecode($parts['user']) : null,
            'pass'   => isset($parts['pass']) ? rawurldecode($parts['pass']) : null,
            'tls'    => $scheme === 'smtps' || (($query['encryption'] ?? '') === 'tls'),
        ];
    }

    /**
     * @param string[] $to
     */
    public function send(array $to, string $subject, string $html, string $text, string $from, string $fromName = '', ?string $replyTo = null): bool
    {
        $to = array_values(array_filter($to, static fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
        if ($to === []) {
            $this->errors[] = 'Aucun destinataire valide.';
            return false;
        }

        [$headers, $body] = self::compose($to, $subject, $html, $text, $from, $fromName, $replyTo);

        if ($this->dsn['scheme'] === 'smtp' || $this->dsn['scheme'] === 'smtps') {
            if ($this->sendSmtp($to, $headers, $body, $from)) {
                return true;
            }
        }

        return $this->sendNative($to, $headers, $body);
    }

    /**
     * Assemble un message MIME multipart/alternative.
     * Partagé par l'envoi SMTP et par la génération de fichiers .eml (bin/invites.php).
     *
     * @param string[] $to
     * @return array{0: array<string, string>, 1: string} [en-têtes, corps]
     */
    public static function compose(array $to, string $subject, string $html, string $text, string $from, string $fromName = '', ?string $replyTo = null): array
    {
        $boundary = 'b' . bin2hex(random_bytes(12));
        $headers  = [
            'From'         => self::formatAddress($from, $fromName),
            'To'           => implode(', ', $to),
            'Subject'      => self::encodeHeader($subject),
            'Date'         => date(DATE_RFC2822),
            'MIME-Version' => '1.0',
            'Content-Type' => 'multipart/alternative; boundary="' . $boundary . '"',
            'Message-ID'   => '<' . bin2hex(random_bytes(10)) . '@' . (gethostname() ?: 'localhost') . '>',
        ];
        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers['Reply-To'] = $replyTo;
        }

        $body = "--$boundary\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text), 76, "\r\n")
            . "\r\n--$boundary\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html), 76, "\r\n")
            . "\r\n--$boundary--\r\n";

        return [$headers, $body];
    }

    /** Message complet (en-têtes + corps), prêt à être écrit dans un fichier .eml. */
    public static function rawMessage(array $to, string $subject, string $html, string $text, string $from, string $fromName = '', ?string $replyTo = null): string
    {
        [$headers, $body] = self::compose($to, $subject, $html, $text, $from, $fromName, $replyTo);
        $raw = '';
        foreach ($headers as $name => $value) {
            $raw .= "$name: $value\r\n";
        }

        return $raw . "\r\n" . $body;
    }

    private function sendNative(array $to, array $headers, string $body): bool
    {
        if (!function_exists('mail')) {
            $this->errors[] = 'mail() indisponible.';
            return false;
        }
        $subject = $headers['Subject'];
        unset($headers['Subject'], $headers['To']);
        $raw = '';
        foreach ($headers as $name => $value) {
            $raw .= "$name: $value\r\n";
        }

        return @mail(implode(', ', $to), $subject, $body, rtrim($raw, "\r\n"));
    }

    private function sendSmtp(array $to, array $headers, string $body, string $from): bool
    {
        $host = ($this->dsn['scheme'] === 'smtps' ? 'ssl://' : '') . $this->dsn['host'];
        $socket = @fsockopen($host, $this->dsn['port'], $errno, $errstr, 8);
        if (!$socket) {
            $this->errors[] = "Connexion SMTP impossible ({$this->dsn['host']}:{$this->dsn['port']}) : $errstr";
            return false;
        }
        stream_set_timeout($socket, 15);

        try {
            $this->expect($socket, 220);
            $ehlo = gethostname() ?: 'localhost';
            $this->command($socket, "EHLO $ehlo", 250);

            if ($this->dsn['tls'] && $this->dsn['scheme'] !== 'smtps') {
                $this->command($socket, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Échec de la négociation TLS.');
                }
                $this->command($socket, "EHLO $ehlo", 250);
            }

            if ($this->dsn['user'] !== null && $this->dsn['user'] !== '') {
                $this->command($socket, 'AUTH LOGIN', 334);
                $this->command($socket, base64_encode($this->dsn['user']), 334);
                $this->command($socket, base64_encode((string) $this->dsn['pass']), 235);
            }

            $this->command($socket, 'MAIL FROM:<' . $from . '>', 250);
            foreach ($to as $recipient) {
                $this->command($socket, 'RCPT TO:<' . $recipient . '>', 250);
            }
            $this->command($socket, 'DATA', 354);

            $raw = '';
            foreach ($headers as $name => $value) {
                $raw .= "$name: $value\r\n";
            }
            $raw .= "\r\n" . $body;
            // Dot-stuffing (RFC 5321 §4.5.2)
            $raw = preg_replace('/^\./m', '..', str_replace("\n", "\r\n", str_replace("\r\n", "\n", $raw)));

            fwrite($socket, $raw . "\r\n.\r\n");
            $this->expect($socket, 250);
            @fwrite($socket, "QUIT\r\n");
        } catch (Throwable $e) {
            $this->errors[] = 'SMTP : ' . $e->getMessage();
            @fclose($socket);
            return false;
        }

        @fclose($socket);

        return true;
    }

    private function command($socket, string $command, int $expected): void
    {
        fwrite($socket, $command . "\r\n");
        $this->expect($socket, $expected);
    }

    private function expect($socket, int $expected): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if ($code !== $expected) {
            throw new RuntimeException("réponse inattendue « " . trim($response) . " » (attendu $expected)");
        }

        return $response;
    }

    private static function formatAddress(string $email, string $name): string
    {
        return $name !== '' ? self::encodeHeader($name) . " <$email>" : $email;
    }

    private static function encodeHeader(string $value): string
    {
        return preg_match('/[\x80-\xFF]/', $value)
            ? '=?UTF-8?B?' . base64_encode($value) . '?='
            : $value;
    }
}
