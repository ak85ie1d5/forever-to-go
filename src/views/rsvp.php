<?php
/** Formulaire de confirmation de présence — ou récapitulatif si déjà répondu. */

if (!function_exists('render_guest_block')) {
    /**
     * Bloc « invité ». $index = -1 produit le gabarit JS (index remplacé par __I__).
     */
    function render_guest_block(I18n $t, int $index, array $prefill = []): string
    {
        $i        = $index >= 0 ? (string) $index : '__I__';
        $number   = $index >= 0 ? (string) ($index + 1) : '__N__';
        $first    = e($prefill['firstname'] ?? '');
        $last     = e($prefill['lastname'] ?? '');
        $legend   = e($t->get('rsvp.guest', $index >= 0 ? $index + 1 : 1));
        $lFirst   = e($t->get('rsvp.firstname'));
        $lLast    = e($t->get('rsvp.lastname'));
        $lAller   = e($t->get('rsvp.allergens'));
        $phAller  = e($t->get('rsvp.allergens_ph'));
        $lChild   = e($t->get('rsvp.is_child'));
        $lAge     = e($t->get('rsvp.age'));
        $lRemove  = e($t->get('rsvp.remove'));

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
        <div class="field">
            <label for="firstname-{$i}" data-i18n="rsvp.firstname">{$lFirst}</label>
            <input type="text" id="firstname-{$i}" name="guests[{$i}][firstname]" value="{$first}" maxlength="60" autocomplete="given-name" required>
        </div>
        <div class="field">
            <label for="lastname-{$i}" data-i18n="rsvp.lastname">{$lLast}</label>
            <input type="text" id="lastname-{$i}" name="guests[{$i}][lastname]" value="{$last}" maxlength="60" autocomplete="family-name" required>
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
        <?php else: ?>
            <form class="form" id="rsvp-form" method="post" action="/" novalidate>
                <p class="form__intro" data-i18n="rsvp.intro"><?= e($t->get('rsvp.intro')) ?></p>

                <div id="guests">
                    <?= render_guest_block($t, 0, $prefill) ?>
                </div>

                <button type="button" class="btn btn--ghost btn--add" id="add-guest">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    <span data-i18n="rsvp.add"><?= e($t->get('rsvp.add')) ?></span>
                </button>

                <div class="field field--full">
                    <label for="contact-email" data-i18n="rsvp.email"><?= e($t->get('rsvp.email')) ?></label>
                    <input type="email" id="contact-email" name="email" maxlength="120" autocomplete="email"
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

            <template id="guest-template"><?= render_guest_block($t, -1) ?></template>
        <?php endif; ?>
    </div>
</section>
