<?php
/**
 * Trois états possibles :
 *  1. pas de jeton valide  → invitation demandée (aucun formulaire rendu) ;
 *  2. réponse déjà envoyée → récapitulatif ;
 *  3. jeton valide         → formulaire, identité imposée par la liste des invités.
 */

if (!function_exists('render_guest_block')) {
    /**
     * Bloc « invité ».
     * $index = 0 : la personne qui répond (identité verrouillée, ni « Enfant ? » ni « Âge »).
     * $index = -1 : gabarit JS, l'index est remplacé par __I__ et le numéro par __N__.
     *
     * @param array $options identity|companions
     */
    function render_guest_block(I18n $t, int $index, array $options = []): string
    {
        $i       = $index >= 0 ? (string) $index : '__I__';
        $number  = $index >= 0 ? (string) ($index + 1) : '__N__';
        $legend  = e($t->get('rsvp.guest', $index >= 0 ? $index + 1 : 1));
        $lFirst  = e($t->get('rsvp.firstname'));
        $lLast   = e($t->get('rsvp.lastname'));
        $lAller  = e($t->get('rsvp.allergens'));
        $phAller = e($t->get('rsvp.allergens_ph'));
        $lChild  = e($t->get('rsvp.is_child'));
        $lAge    = e($t->get('rsvp.age'));
        $lRemove = e($t->get('rsvp.remove'));

        $isPrimary = $index === 0;
        $identity  = $options['identity'] ?? [];
        $first     = e($identity['firstname'] ?? '');
        $last      = e($identity['lastname'] ?? '');

        // --- Personne qui répond : nom et prénom imposés par la liste -------------
        if ($isPrimary) {
            $who  = e(trim(($identity['firstname'] ?? '') . ' ' . ($identity['lastname'] ?? '')));
            $hint = e($t->get('rsvp.you', trim(($identity['firstname'] ?? '') . ' ' . ($identity['lastname'] ?? ''))));

            return <<<HTML
<fieldset class="guest guest--you" data-guest>
    <legend class="guest__legend">
        <span class="guest__number" data-i18n="rsvp.guest" data-i18n-args="{$number}">{$legend}</span>
    </legend>

    <p class="guest__identity" data-i18n="rsvp.you" data-i18n-args="{$who}">{$hint}</p>

    <div class="grid">
        <div class="field">
            <label for="firstname-{$i}" data-i18n="rsvp.firstname">{$lFirst}</label>
            <input type="text" id="firstname-{$i}" name="guests[{$i}][firstname]" value="{$first}" readonly>
        </div>
        <div class="field">
            <label for="lastname-{$i}" data-i18n="rsvp.lastname">{$lLast}</label>
            <input type="text" id="lastname-{$i}" name="guests[{$i}][lastname]" value="{$last}" readonly>
        </div>
        <div class="field field--full">
            <label for="allergens-{$i}" data-i18n="rsvp.allergens">{$lAller}</label>
            <input type="text" id="allergens-{$i}" name="guests[{$i}][allergens]" maxlength="300"
                   placeholder="{$phAller}" data-i18n-attr="placeholder:rsvp.allergens_ph">
        </div>
    </div>
</fieldset>
HTML;
        }

        // --- Accompagnant : choisi dans le foyer, ou saisi puis vérifié -----------
        $companions = $options['companions'] ?? [];
        $picker     = '';
        $readonly   = '';

        if ($companions !== []) {
            $lWho    = e($t->get('rsvp.companion'));
            $lChoose = e($t->get('rsvp.choose'));
            $lOther  = e($t->get('rsvp.other'));
            $choices = '';
            foreach ($companions as $person) {
                $value = e($person['token']);
                $pf    = e($person['firstname']);
                $pl    = e($person['lastname']);
                $label = e(trim($person['firstname'] . ' ' . $person['lastname']));
                $choices .= "<option value=\"{$value}\" data-first=\"{$pf}\" data-last=\"{$pl}\">{$label}</option>";
            }
            $readonly = ' readonly';
            $picker   = <<<HTML
        <div class="field field--full">
            <label for="who-{$i}" data-i18n="rsvp.companion">{$lWho}</label>
            <select id="who-{$i}" data-companion>
                <option value="" data-i18n="rsvp.choose">{$lChoose}</option>
                {$choices}
                <option value="__other__" data-i18n="rsvp.other">{$lOther}</option>
            </select>
        </div>

HTML;
        }

        return <<<HTML
<fieldset class="guest" data-guest>
    <legend class="guest__legend">
        <span class="guest__number" data-i18n="rsvp.guest" data-i18n-args="{$number}">{$legend}</span>
        <button type="button" class="guest__remove" data-remove>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            <span data-i18n="rsvp.remove">{$lRemove}</span>
        </button>
    </legend>

    <div class="grid">
{$picker}        <input type="hidden" name="guests[{$i}][token]" data-token value="">
        <div class="field">
            <label for="firstname-{$i}" data-i18n="rsvp.firstname">{$lFirst}</label>
            <input type="text" id="firstname-{$i}" name="guests[{$i}][firstname]" value="" maxlength="60" autocomplete="off"{$readonly} required>
        </div>
        <div class="field">
            <label for="lastname-{$i}" data-i18n="rsvp.lastname">{$lLast}</label>
            <input type="text" id="lastname-{$i}" name="guests[{$i}][lastname]" value="" maxlength="60" autocomplete="off"{$readonly} required>
        </div>
        <div class="field field--full">
            <label for="allergens-{$i}" data-i18n="rsvp.allergens">{$lAller}</label>
            <input type="text" id="allergens-{$i}" name="guests[{$i}][allergens]" maxlength="300"
                   placeholder="{$phAller}" data-i18n-attr="placeholder:rsvp.allergens_ph">
        </div>
        <div class="field field--switch">
            <span class="field__label" data-i18n="rsvp.is_child">{$lChild}</span>
            <label class="switch" for="child-{$i}">
                <input type="checkbox" id="child-{$i}" name="guests[{$i}][is_child]" value="1" data-child>
                <span class="switch__track" aria-hidden="true"><span class="switch__thumb"></span></span>
                <span class="sr-only" data-i18n="rsvp.is_child">{$lChild}</span>
            </label>
        </div>
        <div class="field field--age" data-age hidden>
            <label for="age-{$i}" data-i18n="rsvp.age">{$lAge}</label>
            <input type="number" id="age-{$i}" name="guests[{$i}][age]" min="0" max="17" step="1" inputmode="numeric" disabled>
        </div>
    </div>
</fieldset>
HTML;
    }
}

$contactMail = $config['mail']['to'][1] ?? $config['mail']['to'][0];
?>
<section class="section section--rsvp" id="rsvp" data-section="rsvp">
    <div class="section__head reveal">
        <p class="kicker" data-i18n="rsvp.subtitle" data-i18n-args="<?= e($t->date($deadline, false)) ?>"><?= e($t->get('rsvp.subtitle', $t->date($deadline, false))) ?></p>
        <h2 class="section__title script" data-i18n="rsvp.title"><?= e($t->get('rsvp.title')) ?></h2>
        <div class="rule" aria-hidden="true"><span></span>&#10022;<span></span></div>
    </div>

    <div class="paper reveal" id="rsvp-zone">
        <?php if ($submission !== null): ?>
            <?= render_summary($submission, $allMessages, $config, $locale) ?>

        <?php elseif ($invite === null): ?>
            <div class="gate">
                <div class="gate__seal" aria-hidden="true">
                    <svg viewBox="0 0 64 64" role="presentation">
                        <circle cx="32" cy="32" r="30" fill="#F3EADC" stroke="#C7B08A" stroke-width="1"/>
                        <path d="M24 30v-5a8 8 0 0 1 16 0v5" fill="none" stroke="#B08D57" stroke-width="2" stroke-linecap="round"/>
                        <rect x="21" y="30" width="22" height="16" rx="2" fill="none" stroke="#B08D57" stroke-width="2"/>
                    </svg>
                </div>
                <h3 class="gate__title" data-i18n="rsvp.gate_title"><?= e($t->get('rsvp.gate_title')) ?></h3>
                <?php if (!empty($invalidInvite)): ?>
                    <p class="form__error" data-i18n="rsvp.error_invite"><?= e($t->get('rsvp.error_invite')) ?></p>
                <?php endif; ?>
                <p class="gate__text" data-i18n="rsvp.gate_text"><?= e($t->get('rsvp.gate_text')) ?></p>
                <p class="gate__help" data-i18n="rsvp.gate_help" data-i18n-args="<?= e($contactMail) ?>"><?= e($t->get('rsvp.gate_help', $contactMail)) ?></p>
                <a class="btn btn--ghost" href="mailto:<?= e($contactMail) ?>"><?= e($contactMail) ?></a>
            </div>

        <?php else: ?>
            <form class="form" id="rsvp-form" method="post" action="/?i=<?= e($invite['token']) ?>" novalidate
                  data-invite="<?= e($invite['token']) ?>"
                  data-max="<?= e((string) (1 + ($companions === [] ? Rsvp::MAX_GUESTS - 1 : count($companions)))) ?>">
                <p class="form__intro" data-i18n="rsvp.intro"><?= e($t->get('rsvp.intro')) ?></p>

                <div id="guests">
                    <?= render_guest_block($t, 0, ['identity' => $invite]) ?>
                </div>

                <button type="button" class="btn btn--ghost btn--add" id="add-guest">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    <span data-i18n="rsvp.add"><?= e($t->get('rsvp.add')) ?></span>
                </button>

                <div class="field field--full">
                    <label for="contact-email" data-i18n="rsvp.email"><?= e($t->get('rsvp.email')) ?></label>
                    <input type="email" id="contact-email" name="email" maxlength="120" autocomplete="email"
                           value="<?= e(filter_var($invite['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '') ?>"
                           placeholder="<?= e($t->get('rsvp.email_ph')) ?>" data-i18n-attr="placeholder:rsvp.email_ph">
                </div>

                <div class="hp" aria-hidden="true">
                    <label for="website">Ne pas remplir</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <p class="form__error" id="form-error" role="alert" hidden></p>

                <button type="submit" class="btn btn--primary btn--submit" id="rsvp-submit">
                    <span data-i18n="rsvp.submit"><?= e($t->get('rsvp.submit')) ?></span>
                </button>
            </form>

            <template id="guest-template"><?= render_guest_block($t, -1, ['companions' => $companions]) ?></template>
        <?php endif; ?>
    </div>
</section>
