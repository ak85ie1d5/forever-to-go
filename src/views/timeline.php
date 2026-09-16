<?php
/** Déroulement de la journée — animé au défilement. */
$icons = [
    'rings'   => '<circle cx="9.5" cy="15" r="6.2"/><circle cx="16" cy="15" r="6.2"/><path d="M12.75 5.4 15 8.2h-4.5L12.75 5.4Z"/>',
    'church'  => '<path d="M12.5 2.5v5M10 5h5"/><path d="M4 21v-7.6l8.5-5.4 8.5 5.4V21"/><path d="M10 21v-4.6a2.5 2.5 0 0 1 5 0V21"/><path d="M2 21h21"/>',
    'glasses' => '<path d="M4 3h6l-1.2 6a1.8 1.8 0 0 1-3.6 0L4 3Z"/><path d="M15 3h6l-1.2 6a1.8 1.8 0 0 1-3.6 0L15 3Z"/><path d="M7 11v9M18 11v9M4.5 21h5M15.5 21h5"/>',
];
?>
<section class="section section--day" id="jour-j" data-section="jour-j">
    <div class="section__head reveal">
        <p class="kicker" data-i18n="day.subtitle"><?= e($t->get('day.subtitle')) ?></p>
        <h2 class="section__title script" data-i18n="day.title"><?= e($t->get('day.title')) ?></h2>
        <div class="rule" aria-hidden="true"><span></span>&#10022;<span></span></div>
    </div>

    <div class="timeline">
        <div class="timeline__spine" aria-hidden="true"><span class="timeline__spine-fill" id="timeline-progress"></span></div>

        <ol class="timeline__list">
            <?php foreach ($config['schedule'] as $index => $step): $id = $step['id']; ?>
                <li class="timeline__item reveal" data-step="<?= e((string) ($index + 1)) ?>">
                    <div class="timeline__dot" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round"><?= $icons[$step['icon']] ?></svg>
                    </div>

                    <article class="timeline__card">
                        <p class="timeline__time"><?= e($step['time']) ?></p>
                        <h3 class="timeline__title" data-i18n="schedule.<?= e($id) ?>.title"><?= e($t->get('schedule.' . $id . '.title')) ?></h3>
                        <p class="timeline__place" data-i18n="schedule.<?= e($id) ?>.place"><?= e($t->get('schedule.' . $id . '.place')) ?></p>
                        <p class="timeline__address" data-i18n="schedule.<?= e($id) ?>.address"><?= e($t->get('schedule.' . $id . '.address')) ?></p>
                        <p class="timeline__text" data-i18n="schedule.<?= e($id) ?>.text"><?= e($t->get('schedule.' . $id . '.text')) ?></p>
                        <a class="link" href="<?= e($step['map']) ?>" target="_blank" rel="noopener noreferrer">
                            <span data-i18n="day.map"><?= e($t->get('day.map')) ?></span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true"><path d="M5 12h13M13 6l6 6-6 6"/></svg>
                        </a>
                    </article>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>

    <p class="section__outro reveal" data-i18n="day.outro"><?= e($t->get('day.outro')) ?></p>
</section>
