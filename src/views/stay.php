<?php
/** Hébergements autour du château de Pontarmé. */
?>
<section class="section section--stay" id="hebergements" data-section="hebergements">
    <div class="section__head reveal">
        <p class="kicker" data-i18n="stay.subtitle"><?= e($t->get('stay.subtitle')) ?></p>
        <h2 class="section__title script" data-i18n="stay.title"><?= e($t->get('stay.title')) ?></h2>
        <div class="rule" aria-hidden="true"><span></span>&#10022;<span></span></div>
    </div>

    <div class="cards">
        <?php foreach ($config['accommodations'] as $place): ?>
            <article class="card reveal">
                <div class="card__media">
                    <img src="/<?= e($place['photo']) ?>" alt="<?= e($place['name']) ?>" loading="lazy" decoding="async" width="640" height="420">
                    <?php if ($place['distance'] !== null): ?>
                        <span class="card__badge" data-i18n="stay.distance" data-i18n-args="<?= e((string) $place['distance']) ?>"><?= e($t->get('stay.distance', (string) $place['distance'])) ?></span>
                    <?php else: ?>
                        <span class="card__badge" data-i18n="stay.nearby"><?= e($t->get('stay.nearby')) ?></span>
                    <?php endif; ?>
                </div>

                <div class="card__body">
                    <h3 class="card__title"><?= e($place['name']) ?></h3>
                    <p class="card__address">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.1" aria-hidden="true"><path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/></svg>
                        <span><?= e($place['address']) ?></span>
                    </p>
                    <?php if ($place['phone'] !== null): ?>
                        <p class="card__phone">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.1" aria-hidden="true"><path d="M6.5 3h3l1.6 4-2 1.4a12.5 12.5 0 0 0 6.5 6.5l1.4-2 4 1.6v3A2.5 2.5 0 0 1 18.4 20 15.4 15.4 0 0 1 4 5.6 2.5 2.5 0 0 1 6.5 3Z"/></svg>
                            <a href="tel:<?= e(str_replace(' ', '', $place['phone'])) ?>"><?= e($place['phone']) ?></a>
                        </p>
                    <?php endif; ?>

                    <div class="card__actions">
                        <a class="btn btn--ghost" href="<?= e($place['url']) ?>" target="_blank" rel="noopener noreferrer" data-i18n="stay.book"><?= e($t->get('stay.book')) ?></a>
                        <?php if ($place['phone'] !== null): ?>
                            <a class="btn btn--link" href="tel:<?= e(str_replace(' ', '', $place['phone'])) ?>" data-i18n="stay.call"><?= e($t->get('stay.call')) ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <p class="section__note reveal" data-i18n="stay.note"><?= e($t->get('stay.note')) ?></p>
</section>
