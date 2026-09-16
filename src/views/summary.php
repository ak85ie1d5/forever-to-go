<?php
/**
 * Récapitulatif d'une confirmation déjà envoyée (remplace définitivement le formulaire).
 * @var array $data         Confirmation stockée
 * @var array $allMessages  Traductions de toutes les langues
 * @var array $config
 * @var string $viewLocale  Langue d'affichage courante
 */
$translators = [];
foreach ($allMessages as $code => $messages) {
    $translators[$code] = new I18n($messages, $code);
}
$current  = $translators[$viewLocale] ?? reset($translators);
$sentAt   = new DateTimeImmutable($data['created_at']);
$firstName = $data['guests'][0]['firstname'] ?? '';
$dates    = [];
foreach ($translators as $code => $translator) {
    $dates[$code] = $translator->date($sentAt);
}
$dateAttrs = '';
foreach ($dates as $code => $value) {
    $dateAttrs .= ' data-date-' . $code . '="' . e($value) . '"';
}
?>
<div class="summary" id="summary">
    <div class="summary__seal" aria-hidden="true">
        <svg viewBox="0 0 64 64" role="presentation"><circle cx="32" cy="32" r="30" fill="#F3EADC" stroke="#C7B08A" stroke-width="1"/><path d="M20 33.5 28.5 42 45 24" fill="none" stroke="#B08D57" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </div>

    <h3 class="summary__title" data-i18n="summary.title"><?= e($current->get('summary.title')) ?></h3>
    <p class="summary__thanks" data-i18n="summary.thanks" data-i18n-args="<?= e($firstName) ?>"><?= e($current->get('summary.thanks', $firstName)) ?></p>
    <p class="summary__meta" data-i18n="summary.sent_on" data-i18n-date="1"<?= $dateAttrs ?>><?= e($current->get('summary.sent_on', $dates[$viewLocale] ?? reset($dates))) ?></p>

    <p class="summary__count" data-i18n="summary.total" data-i18n-args="<?= count($data['guests']) ?>"><?= e($current->get('summary.total', count($data['guests']))) ?></p>

    <ul class="summary__list">
        <?php foreach ($data['guests'] as $guest): ?>
            <li class="summary__guest">
                <p class="summary__name"><?= e(trim($guest['firstname'] . ' ' . $guest['lastname'])) ?></p>
                <p class="summary__tags">
                    <?php if (!empty($guest['is_child'])): ?>
                        <span class="tag tag--child" data-i18n="rsvp.child"><?= e($current->get('rsvp.child')) ?></span>
                        <?php if ($guest['age'] !== null): ?>
                            <span class="tag"><?= (int) $guest['age'] ?> <span data-i18n="rsvp.years"><?= e($current->get('rsvp.years')) ?></span></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="tag" data-i18n="rsvp.no_child"><?= e($current->get('rsvp.no_child')) ?></span>
                    <?php endif; ?>
                </p>
                <p class="summary__allergens">
                    <span class="summary__label" data-i18n="summary.allergens"><?= e($current->get('summary.allergens')) ?></span>
                    <?php if (trim((string) $guest['allergens']) !== ''): ?>
                        <span><?= e($guest['allergens']) ?></span>
                    <?php else: ?>
                        <span data-i18n="rsvp.none"><?= e($current->get('rsvp.none')) ?></span>
                    <?php endif; ?>
                </p>
            </li>
        <?php endforeach; ?>
    </ul>

    <p class="summary__contact">
        <?php $contactMail = $config['mail']['to'][1] ?? $config['mail']['to'][0]; ?>
        <span data-i18n="summary.contact" data-i18n-args="<?= e($contactMail) ?>"><?= e($current->get('summary.contact', $contactMail)) ?></span>
    </p>

    <button type="button" class="btn btn--ghost" data-print data-i18n="summary.print"><?= e($current->get('summary.print')) ?></button>
</div>
