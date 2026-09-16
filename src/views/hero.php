<?php
/** Section d'accueil : cachet de cire, noms, décompte. */
$now  = new DateTimeImmutable('now');
$diff = $now < $weddingDate ? $now->diff($weddingDate) : null;
$units = [
    'days'    => $diff ? (int) $diff->days : 0,
    'hours'   => $diff ? (int) $diff->h : 0,
    'minutes' => $diff ? (int) $diff->i : 0,
    'seconds' => $diff ? (int) $diff->s : 0,
];
?>
<section class="hero section" id="accueil" data-section="accueil">
    <div class="hero__lace hero__lace--tl" aria-hidden="true"></div>
    <div class="hero__lace hero__lace--br" aria-hidden="true"></div>

    <div class="hero__inner">
        <div class="seal" aria-hidden="true">
            <svg viewBox="0 0 160 160" role="presentation" focusable="false">
                <defs>
                    <radialGradient id="wax" cx="38%" cy="32%" r="78%">
                        <stop offset="0%" stop-color="#FFFDF7"/>
                        <stop offset="55%" stop-color="#F3EADC"/>
                        <stop offset="100%" stop-color="#DCCBB4"/>
                    </radialGradient>
                    <filter id="sealShadow" x="-25%" y="-25%" width="150%" height="150%">
                        <feDropShadow dx="0" dy="4" stdDeviation="5" flood-color="#8d7b60" flood-opacity=".28"/>
                    </filter>
                </defs>
                <path filter="url(#sealShadow)" fill="url(#wax)" d="M80 6c9 0 13 8 21 10s17-3 24 3 3 15 8 22 14 9 15 18-7 13-8 22 5 17-1 24-15 3-22 8-9 14-18 15-13-7-22-8-17 5-24-1-3-15-8-22-14-9-15-18 7-13 8-22-5-17 1-24 15-3 22-8S71 5 80 6Z"/>
                <circle cx="80" cy="80" r="52" fill="none" stroke="#C7B08A" stroke-opacity=".55" stroke-width="1"/>
                <circle cx="80" cy="80" r="47" fill="none" stroke="#C7B08A" stroke-opacity=".35" stroke-width=".6" stroke-dasharray="1 4"/>
                <text x="80" y="97" text-anchor="middle" class="seal__initials"><?= e($config['couple']['initials']) ?></text>
            </svg>
        </div>

        <p class="kicker" data-i18n="hero.save"><?= e($t->get('hero.save')) ?></p>

        <h1 class="hero__names">
            <span class="script">Julia</span>
            <span class="hero__amp">&amp;</span>
            <span class="script">Jérémy</span>
        </h1>

        <p class="hero__union" data-i18n="hero.union"><?= e($t->get('hero.union')) ?></p>

        <div class="rule" aria-hidden="true"><span></span>&#10022;<span></span></div>

        <p class="hero__date" data-i18n="hero.date"><?= e($t->get('hero.date')) ?></p>
        <p class="hero__place" data-i18n="hero.place"><?= e($t->get('hero.place')) ?></p>

        <section class="countdown" aria-live="polite" id="countdown">
            <p class="countdown__title" data-i18n="countdown.title"><?= e($t->get('countdown.title')) ?></p>
            <div class="countdown__grid">
                <?php foreach ($units as $key => $value): ?>
                    <div class="countdown__cell">
                        <span class="countdown__value" data-countdown="<?= e($key) ?>"><?= e(str_pad((string) $value, 2, '0', STR_PAD_LEFT)) ?></span>
                        <span class="countdown__label" data-i18n="countdown.<?= e($key) ?>"><?= e($t->get('countdown.' . $key)) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="countdown__done" hidden data-i18n="countdown.today"><?= e($t->get('countdown.today')) ?></p>
        </section>

        <p class="hero__invite" data-i18n="hero.invite"><?= e($t->get('hero.invite')) ?></p>

        <a class="btn btn--primary" href="#rsvp" data-route="rsvp" data-i18n="hero.cta"><?= e($t->get('hero.cta')) ?></a>

        <a class="hero__scroll" href="#jour-j" data-route="jour-j">
            <span data-i18n="hero.scroll"><?= e($t->get('hero.scroll')) ?></span>
            <span class="hero__scroll-line" aria-hidden="true"></span>
        </a>
    </div>
</section>
