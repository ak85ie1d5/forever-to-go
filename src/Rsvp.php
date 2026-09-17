<?php
/** Validation, stockage et notification des confirmations de présence. */

final class Rsvp
{
    public const COOKIE = 'rsvp_token';
    public const MAX_GUESTS = 12;

    public function __construct(private string $storageDir)
    {
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0775, true);
        }
    }

    /**
     * Nettoie et valide la charge utile du formulaire.
     * L'identité du premier invité vient du jeton (jamais du navigateur) et chaque
     * accompagnant doit correspondre à une ligne de la liste non encore confirmée.
     *
     * @param array $invite Ligne de la liste correspondant au jeton du lien
     * @return array{0: array<string>, 1: array} [erreurs, données]
     */
    public function validate(array $payload, I18n $t, GuestList $list, array $invite): array
    {
        $errors = [];
        $guests = [];
        $used   = [];

        $raw = $payload['guests'] ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }
        $raw = array_slice(array_values($raw), 0, self::MAX_GUESTS);

        foreach ($raw as $position => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $firstname = $this->clean($entry['firstname'] ?? '', 60);
            $lastname  = $this->clean($entry['lastname'] ?? '', 60);
            $allergens = $this->clean($entry['allergens'] ?? '', 300);
            $age       = null;

            if ($position === 0) {
                $person = $invite; // identité imposée par le lien d'invitation
            } else {
                if ($firstname === '' && $lastname === '') {
                    continue; // ligne laissée vide : on l'ignore
                }
                if ($firstname === '' || $lastname === '') {
                    $errors['names'] = $t->get('rsvp.error_names');
                    continue;
                }
                $person = $list->findByToken($this->clean($entry['token'] ?? '', 40))
                    ?? $list->findByName($firstname, $lastname);

                if ($person === null) {
                    $errors['unknown'] = $t->get('rsvp.error_unknown', trim($firstname . ' ' . $lastname));
                    continue;
                }
            }

            // Enfant ou non : c'est la liste qui fait foi, jamais le formulaire.
            $isChild  = GuestList::isChild($person);
            $fullName = trim($person['firstname'] . ' ' . $person['lastname']);

            if (isset($used[$person['token']])) {
                $errors['duplicate'] = $t->get('rsvp.error_duplicate', $fullName);
                continue;
            }
            if ($this->findByInvite($person['token']) !== null) {
                $errors['already'] = $t->get('rsvp.error_already', $fullName);
                continue;
            }

            if ($isChild) {
                $age = filter_var($entry['age'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 17]]);
                if ($age === false) {
                    $errors['age'] = $t->get('rsvp.error_age');
                    $age = null;
                }
            }

            // Coiffure et maquillage : proposés aux invitées, l'autorisation vient de la liste.
            $mayBeautify = GuestList::offersBeauty($person);

            $used[$person['token']] = true;
            $guests[] = [
                'token'     => $person['token'],
                'firstname' => $person['firstname'],
                'lastname'  => $person['lastname'],
                'allergens' => $allergens,
                'is_child'  => $isChild,
                'age'       => $age,
                'hair'      => $mayBeautify && filter_var($entry['hair'] ?? false, FILTER_VALIDATE_BOOL),
                'makeup'    => $mayBeautify && filter_var($entry['makeup'] ?? false, FILTER_VALIDATE_BOOL),
            ];
        }

        if ($guests === [] && $errors === []) {
            $errors['names'] = $t->get('rsvp.error_names');
        }

        $email = $this->clean($payload['email'] ?? '', 120);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = $t->get('rsvp.error_email');
        }

        $data = [
            'token'      => bin2hex(random_bytes(16)),
            'invite'     => $invite['token'],
            'guests_tokens' => array_keys($used),
            'created_at' => (new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')))->format(DateTimeInterface::ATOM),
            'locale'     => $t->locale,
            'email'      => $email,
            'guests'     => $guests,
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
        ];

        return [$errors, $data];
    }

    public function save(array $data): bool
    {
        $file = $this->storageDir . '/' . $data['token'] . '.json';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // L'écriture est silencieuse : un dossier non inscriptible doit produire une
        // réponse JSON propre, pas une alerte PHP au milieu du flux.
        if ($json === false || @file_put_contents($file, $json, LOCK_EX) === false) {
            error_log('[RSVP] Écriture impossible dans ' . $file . ' — vérifiez les droits du dossier var/rsvp');
            return false;
        }
        foreach ($data['guests_tokens'] ?? [] as $inviteToken) {
            $dir = $this->storageDir . '/tokens';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @file_put_contents($dir . '/' . $this->safeToken($inviteToken) . '.json', json_encode(['record' => $data['token']]), LOCK_EX);
        }

        @file_put_contents(
            $this->storageDir . '/index.jsonl',
            json_encode([
                'token'      => $data['token'],
                'created_at' => $data['created_at'],
                'names'      => array_map(static fn($g) => trim($g['firstname'] . ' ' . $g['lastname']), $data['guests']),
                'count'      => count($data['guests']),
            ], JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );

        return true;
    }

    public function find(?string $token): ?array
    {
        if (!is_string($token) || !preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $file = $this->storageDir . '/' . $token . '.json';
        if (!is_readable($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : null;
    }

    /** Réponse déjà enregistrée pour ce jeton d'invitation (personne déjà confirmée) ? */
    public function findByInvite(?string $inviteToken): ?array
    {
        $safe = $this->safeToken((string) $inviteToken);
        if ($safe === '') {
            return null;
        }
        $pointer = $this->storageDir . '/tokens/' . $safe . '.json';
        if (!is_readable($pointer)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($pointer), true);

        return is_array($data) ? $this->find($data['record'] ?? null) : null;
    }

    private function safeToken(string $token): string
    {
        return (string) preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($token)));
    }

    /** Dépose le cookie qui verrouille l'accès au formulaire. */
    public function rememberCookie(string $token): void
    {
        setcookie(self::COOKIE, $token, [
            'expires'  => time() + 400 * 86400,
            'path'     => '/',
            'secure'   => (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $token;
    }

    /** Notifie les mariés (et l'invité s'il a laissé son e-mail). */
    public function notify(array $data, array $mailConfig, Mailer $mailer, I18n $t): bool
    {
        $names   = implode(', ', array_map(static fn($g) => trim($g['firstname'] . ' ' . $g['lastname']), $data['guests']));
        $subject = 'RSVP — ' . $names . ' (' . count($data['guests']) . ')';
        $html    = $this->emailHtml($data, $t);
        $text    = $this->emailText($data, $t);

        $sent = $mailer->send(
            $mailConfig['to'],
            $subject,
            $html,
            $text,
            $mailConfig['from'],
            $mailConfig['from_name'],
            $data['email'] !== '' ? $data['email'] : null
        );

        if ($data['email'] !== '') {
            $mailer->send(
                [$data['email']],
                $t->get('summary.title'),
                $html,
                $text,
                $mailConfig['from'],
                $mailConfig['from_name']
            );
        }

        return $sent;
    }

    private function emailHtml(array $data, I18n $t): string
    {
        $rows = '';
        foreach ($data['guests'] as $g) {
            $type = $g['is_child']
                ? e($t->get('rsvp.child')) . ($g['age'] !== null ? ' — ' . (int) $g['age'] . ' ' . e($t->get('rsvp.years')) : '')
                : e($t->get('rsvp.no_child'));
            $beauty = array_filter([
                !empty($g['hair']) ? $t->get('rsvp.hair') : null,
                !empty($g['makeup']) ? $t->get('rsvp.makeup') : null,
            ]);
            $rows .= '<tr>'
                . '<td style="padding:10px 14px;border-bottom:1px solid #EADFCB;">' . e(trim($g['firstname'] . ' ' . $g['lastname'])) . '</td>'
                . '<td style="padding:10px 14px;border-bottom:1px solid #EADFCB;">' . $type . '</td>'
                . '<td style="padding:10px 14px;border-bottom:1px solid #EADFCB;">' . ($g['allergens'] !== '' ? e($g['allergens']) : '—') . '</td>'
                . '<td style="padding:10px 14px;border-bottom:1px solid #EADFCB;">' . ($beauty !== [] ? e(implode(' + ', $beauty)) : '—') . '</td>'
                . '</tr>';
        }
        $date = new DateTimeImmutable($data['created_at']);

        return '<!DOCTYPE html><html lang="' . e($t->locale) . '"><body style="margin:0;background:#F7F3EC;font-family:Georgia,\'Times New Roman\',serif;color:#2E2E2E;">'
            . '<div style="max-width:640px;margin:0 auto;padding:32px;">'
            . '<p style="letter-spacing:.35em;text-transform:uppercase;font-size:12px;color:#B08D57;margin:0 0 8px;">Julia &amp; Jérémy · 23.10.2027</p>'
            . '<h1 style="font-size:26px;font-weight:400;margin:0 0 4px;">Nouvelle confirmation de présence</h1>'
            . '<p style="color:#6b655d;margin:0 0 24px;">' . e($t->date($date)) . ' — ' . count($data['guests']) . ' personne(s)</p>'
            . '<table style="width:100%;border-collapse:collapse;background:#FFFEF9;border:1px solid #EADFCB;">'
            . '<tr style="background:#EADFCB;"><th align="left" style="padding:10px 14px;font-weight:500;">Invité</th>'
            . '<th align="left" style="padding:10px 14px;font-weight:500;">Type</th>'
            . '<th align="left" style="padding:10px 14px;font-weight:500;">Allergènes</th>'
            . '<th align="left" style="padding:10px 14px;font-weight:500;">Coiffure / Maquillage (13:00)</th></tr>'
            . $rows . '</table>'
            . ($data['email'] !== '' ? '<p style="margin:20px 0 0;">E-mail de contact : <a href="mailto:' . e($data['email']) . '" style="color:#B08D57;">' . e($data['email']) . '</a></p>' : '')
            . '<p style="margin:28px 0 0;font-size:12px;color:#9a9288;">Référence : ' . e($data['token']) . '</p>'
            . '</div></body></html>';
    }

    private function emailText(array $data, I18n $t): string
    {
        $lines = ['Nouvelle confirmation de présence — Julia & Jérémy, 23/10/2027', ''];
        foreach ($data['guests'] as $g) {
            $type = $g['is_child']
                ? $t->get('rsvp.child') . ($g['age'] !== null ? ' (' . (int) $g['age'] . ' ' . $t->get('rsvp.years') . ')' : '')
                : $t->get('rsvp.no_child');
            $beauty = array_filter([
                !empty($g['hair']) ? $t->get('rsvp.hair') : null,
                !empty($g['makeup']) ? $t->get('rsvp.makeup') : null,
            ]);
            $lines[] = '- ' . trim($g['firstname'] . ' ' . $g['lastname']) . ' [' . $type . ']'
                . ($g['allergens'] !== '' ? ' — ' . $t->get('summary.allergens') . ' : ' . $g['allergens'] : '')
                . ($beauty !== [] ? ' — ' . $t->get('rsvp.beauty_title') . ' : ' . implode(' + ', $beauty) : '');
        }
        $lines[] = '';
        $lines[] = 'Total : ' . count($data['guests']) . ' personne(s)';
        if ($data['email'] !== '') {
            $lines[] = 'E-mail de contact : ' . $data['email'];
        }
        $lines[] = 'Reçu le ' . $t->date(new DateTimeImmutable($data['created_at']));
        $lines[] = 'Référence : ' . $data['token'];

        return implode("\n", $lines);
    }

    private function clean(mixed $value, int $max): string
    {
        if (!is_scalar($value)) {
            return '';
        }
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $value) ?? '';
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return mb_substr($value, 0, $max);
    }
}
