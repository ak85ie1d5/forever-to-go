/* =========================================================================
   Julia & Jérémy — SPA : routage, i18n (fr/ro), décompte, animations, RSVP.
   ========================================================================= */
(function () {
    'use strict';

    var node = document.getElementById('app-data');
    var APP = node ? JSON.parse(node.textContent) : { locale: 'fr', locales: ['fr'], messages: {} };

    var locale = APP.locale;
    var SECTIONS = ['accueil', 'jour-j', 'hebergements', 'rsvp'];

    /* URL d'une section : ancre simple, aucun besoin de réécriture serveur. */
    function urlFor(route) {
        return route === 'accueil' ? location.pathname + location.search : '#' + route;
    }

    /* --------------------------------------------------------------------- */
    /* i18n                                                                   */
    /* --------------------------------------------------------------------- */
    function format(template, args) {
        if (!args || !args.length) { return template; }
        var i = 0;
        return String(template).replace(/%[sd]/g, function (match) {
            var value = args[i++];
            return value === undefined ? match : value;
        });
    }

    function translate(key, args, code) {
        var dict = APP.messages[code || locale] || {};
        var value = dict[key];
        if (value === undefined) { return key; }
        return format(value, args);
    }

    function argsOf(el) {
        var raw = el.getAttribute('data-i18n-args');
        if (raw === null) { return []; }
        return raw === '' ? [] : raw.split('|');
    }

    function applyLocale(code, scope) {
        var root = scope || document;

        if (!scope) {
            locale = code;
            document.documentElement.lang = code;
            try {
                document.cookie = 'lang=' + code + ';path=/;max-age=' + (400 * 86400) + ';samesite=lax';
            } catch (e) { /* cookies indisponibles */ }

            Array.prototype.forEach.call(document.querySelectorAll('.lang__btn'), function (btn) {
                var active = btn.getAttribute('data-lang') === code;
                btn.classList.toggle('is-active', active);
                btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            var alt = document.querySelector('link[rel="canonical"]');
            if (alt) { alt.setAttribute('href', location.origin + '/?lang=' + code); }
        }

        // Texte
        Array.prototype.forEach.call(root.querySelectorAll('[data-i18n]'), function (el) {
            var key = el.getAttribute('data-i18n');
            var args = argsOf(el);
            if (el.hasAttribute('data-i18n-date')) {
                var localized = el.getAttribute('data-date-' + code);
                if (localized) { args = [localized]; }
            }
            el.textContent = translate(key, args, code);
        });

        // Attributs : data-i18n-attr="placeholder:rsvp.allergens_ph|title:…"
        Array.prototype.forEach.call(root.querySelectorAll('[data-i18n-attr]'), function (el) {
            el.getAttribute('data-i18n-attr').split('|').forEach(function (pair) {
                var bits = pair.split(':');
                if (bits.length === 2) {
                    el.setAttribute(bits[0].trim(), translate(bits[1].trim(), argsOf(el), code));
                }
            });
        });

        if (!scope) { renderCountdown(); }
    }

    Array.prototype.forEach.call(document.querySelectorAll('.lang__btn'), function (btn) {
        btn.addEventListener('click', function () {
            var code = btn.getAttribute('data-lang');
            if (code === locale) { return; }
            applyLocale(code);
            var url = new URL(location.href);
            url.searchParams.set('lang', code);
            history.replaceState(history.state, '', url.pathname + url.search + url.hash);
        });
    });

    /* --------------------------------------------------------------------- */
    /* Routage SPA (History API, aucune rechargement de page)                 */
    /* --------------------------------------------------------------------- */
    var header = document.getElementById('header');

    function headerHeight() {
        return header ? header.offsetHeight - 8 : 70;
    }

    function goTo(route, push) {
        var section = document.getElementById(route === 'accueil' ? 'accueil' : route);
        if (!section) { return; }
        var top = route === 'accueil' ? 0 : section.getBoundingClientRect().top + window.pageYOffset - headerHeight();
        window.scrollTo({ top: top, behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
        if (push !== false) {
            history.pushState({ route: route }, '', urlFor(route));
        }
        setActive(route);
        closeNav();
    }

    function setActive(route) {
        Array.prototype.forEach.call(document.querySelectorAll('.nav__link'), function (link) {
            link.classList.toggle('is-active', link.getAttribute('data-route') === route);
        });
        document.documentElement.setAttribute('data-section', route);
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest ? event.target.closest('a[data-route]') : null;
        if (!link || link.hasAttribute('target')) { return; }
        event.preventDefault();
        goTo(link.getAttribute('data-route'), true);
    });

    window.addEventListener('popstate', function () {
        var hash = location.hash.replace('#', '');
        goTo(SECTIONS.indexOf(hash) >= 0 ? hash : 'accueil', false);
    });

    var initialHash = location.hash.replace('#', '');
    var initialRoute = SECTIONS.indexOf(initialHash) >= 0 ? initialHash : APP.route;
    if (initialRoute && initialRoute !== 'accueil') {
        window.setTimeout(function () { goTo(initialRoute, false); }, 140);
    }

    /* Menu mobile */
    var burger = document.querySelector('.burger');
    var nav = document.getElementById('nav-principal');

    function closeNav() {
        if (!burger || !nav) { return; }
        burger.setAttribute('aria-expanded', 'false');
        nav.classList.remove('is-open');
        document.body.classList.remove('nav-open');
    }

    if (burger && nav) {
        burger.addEventListener('click', function () {
            var open = burger.getAttribute('aria-expanded') === 'true';
            burger.setAttribute('aria-expanded', open ? 'false' : 'true');
            nav.classList.toggle('is-open', !open);
            document.body.classList.toggle('nav-open', !open);
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeNav(); } });
    }

    /* --------------------------------------------------------------------- */
    /* Décompte jusqu'au jour J                                               */
    /* --------------------------------------------------------------------- */
    var target = new Date(APP.weddingDate).getTime();
    var cells = {
        days: document.querySelector('[data-countdown="days"]'),
        hours: document.querySelector('[data-countdown="hours"]'),
        minutes: document.querySelector('[data-countdown="minutes"]'),
        seconds: document.querySelector('[data-countdown="seconds"]')
    };
    var doneMessage = document.querySelector('.countdown__done');
    var grid = document.querySelector('.countdown__grid');

    function pad(value) { return value < 10 ? '0' + value : String(value); }

    function renderCountdown() {
        if (!cells.days) { return; }
        var delta = target - Date.now();

        if (delta <= 0) {
            if (grid) { grid.hidden = true; }
            if (doneMessage) {
                doneMessage.hidden = false;
                doneMessage.textContent = translate(delta > -86400000 ? 'countdown.today' : 'countdown.past', []);
            }
            return;
        }

        var seconds = Math.floor(delta / 1000);
        cells.days.textContent = String(Math.floor(seconds / 86400));
        cells.hours.textContent = pad(Math.floor(seconds / 3600) % 24);
        cells.minutes.textContent = pad(Math.floor(seconds / 60) % 60);
        cells.seconds.textContent = pad(seconds % 60);
    }

    renderCountdown();
    window.setInterval(renderCountdown, 1000);

    /* --------------------------------------------------------------------- */
    /* Animations au défilement                                               */
    /* --------------------------------------------------------------------- */
    function prefersReducedMotion() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function observeReveals(scope) {
        var items = (scope || document).querySelectorAll('.reveal:not(.is-visible)');
        if (!('IntersectionObserver' in window) || prefersReducedMotion()) {
            Array.prototype.forEach.call(items, function (el) { el.classList.add('is-visible'); });
            return;
        }
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -12% 0px', threshold: 0.18 });

        Array.prototype.forEach.call(items, function (el, index) {
            el.style.transitionDelay = Math.min(index % 4, 3) * 90 + 'ms';
            observer.observe(el);
        });
    }

    observeReveals();

    /* Progression de la frise + état de l'en-tête + route courante */
    var spine = document.getElementById('timeline-progress');
    var timeline = document.querySelector('.timeline');
    var sections = Array.prototype.slice.call(document.querySelectorAll('main [data-section]'));
    var ticking = false;

    function onScroll() {
        var y = window.pageYOffset;

        if (header) { header.classList.toggle('is-scrolled', y > 24); }

        if (spine && timeline) {
            var rect = timeline.getBoundingClientRect();
            var viewAnchor = window.innerHeight * 0.62;
            var progress = (viewAnchor - rect.top) / rect.height;
            spine.style.height = Math.max(0, Math.min(1, progress)) * 100 + '%';
        }

        var current = 'accueil';
        sections.forEach(function (section) {
            if (section.getBoundingClientRect().top <= headerHeight() + 40) {
                current = section.getAttribute('data-section');
            }
        });
        if (document.documentElement.getAttribute('data-section') !== current) {
            setActive(current);
            history.replaceState({ route: current }, '', urlFor(current));
        }

        ticking = false;
    }

    window.addEventListener('scroll', function () {
        if (!ticking) {
            ticking = true;
            window.requestAnimationFrame(onScroll);
        }
    }, { passive: true });
    onScroll();

    /* --------------------------------------------------------------------- */
    /* Formulaire de confirmation                                             */
    /* --------------------------------------------------------------------- */
    var zone = document.getElementById('rsvp-zone');

    function bindGuest(fieldset) {
        var toggle = fieldset.querySelector('[data-child]');
        var ageField = fieldset.querySelector('[data-age]');
        var ageInput = ageField ? ageField.querySelector('input') : null;

        if (toggle && ageField && ageInput) {
            var sync = function () {
                ageField.hidden = !toggle.checked;
                ageInput.disabled = !toggle.checked;
                ageInput.required = toggle.checked;
                if (!toggle.checked) { ageInput.value = ''; }
            };
            toggle.addEventListener('change', function () {
                sync();
                if (toggle.checked) { ageInput.focus(); }
            });
            sync();
        }

        var remove = fieldset.querySelector('[data-remove]');
        if (remove) {
            remove.addEventListener('click', function () {
                fieldset.remove();
                renumber();
            });
        }
    }

    function renumber() {
        var list = document.getElementById('guests');
        if (!list) { return; }
        Array.prototype.forEach.call(list.querySelectorAll('[data-guest]'), function (fieldset, index) {
            var number = fieldset.querySelector('.guest__number');
            if (number) {
                number.setAttribute('data-i18n-args', String(index + 1));
                number.textContent = translate('rsvp.guest', [index + 1]);
            }
            Array.prototype.forEach.call(fieldset.querySelectorAll('input'), function (input) {
                if (input.name) {
                    input.name = input.name.replace(/guests\[[^\]]*\]/, 'guests[' + index + ']');
                }
                if (input.id) {
                    var newId = input.id.replace(/-(?:\d+|__I__)$/, '-' + index);
                    var label = fieldset.querySelector('label[for="' + input.id + '"]');
                    input.id = newId;
                    if (label) { label.setAttribute('for', newId); }
                }
            });
        });

        var addButton = document.getElementById('add-guest');
        if (addButton) {
            addButton.hidden = list.querySelectorAll('[data-guest]').length >= 12;
        }
    }

    function initForm() {
        var form = document.getElementById('rsvp-form');
        if (!form) { return; }

        var list = document.getElementById('guests');
        var template = document.getElementById('guest-template');
        var addButton = document.getElementById('add-guest');
        var errorBox = document.getElementById('form-error');
        var submit = document.getElementById('rsvp-submit');

        Array.prototype.forEach.call(list.querySelectorAll('[data-guest]'), bindGuest);
        renumber();

        if (addButton && template) {
            addButton.addEventListener('click', function () {
                var index = list.querySelectorAll('[data-guest]').length;
                var html = template.innerHTML
                    .replace(/__I__/g, String(index))
                    .replace(/__N__/g, String(index + 1));
                var wrapper = document.createElement('div');
                wrapper.innerHTML = html.trim();
                var fieldset = wrapper.firstElementChild;
                list.appendChild(fieldset);
                applyLocale(locale, fieldset);
                bindGuest(fieldset);
                renumber();
                var first = fieldset.querySelector('input[type="text"]');
                if (first) { first.focus(); }
            });
        }

        function showError(message) {
            if (!errorBox) { return; }
            errorBox.textContent = message;
            errorBox.hidden = !message;
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            showError('');

            var guests = [];
            var invalid = null;

            Array.prototype.forEach.call(list.querySelectorAll('[data-guest]'), function (fieldset) {
                var value = function (selector) {
                    var input = fieldset.querySelector(selector);
                    return input ? input.value.trim() : '';
                };
                var isChild = !!fieldset.querySelector('[data-child]:checked');
                var guest = {
                    firstname: value('input[name*="[firstname]"]'),
                    lastname: value('input[name*="[lastname]"]'),
                    allergens: value('input[name*="[allergens]"]'),
                    is_child: isChild,
                    age: isChild ? value('input[name*="[age]"]') : null
                };

                Array.prototype.forEach.call(fieldset.querySelectorAll('input'), function (input) {
                    input.removeAttribute('aria-invalid');
                });

                if (!guest.firstname || !guest.lastname) {
                    invalid = invalid || translate('rsvp.error_names', []);
                    fieldset.querySelector('input[name*="[' + (guest.firstname ? 'lastname' : 'firstname') + ']"]')
                        .setAttribute('aria-invalid', 'true');
                    return;
                }
                if (isChild) {
                    var age = parseInt(guest.age, 10);
                    if (isNaN(age) || age < 0 || age > 17) {
                        invalid = invalid || translate('rsvp.error_age', []);
                        fieldset.querySelector('input[name*="[age]"]').setAttribute('aria-invalid', 'true');
                        return;
                    }
                    guest.age = age;
                }
                guests.push(guest);
            });

            if (invalid || !guests.length) {
                showError(invalid || translate('rsvp.error_names', []));
                return;
            }

            var emailInput = document.getElementById('contact-email');
            var email = emailInput ? emailInput.value.trim() : '';
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
                emailInput.setAttribute('aria-invalid', 'true');
                showError(translate('rsvp.error_email', []));
                return;
            }

            var websiteInput = document.getElementById('website');
            var label = submit.querySelector('span');
            var previous = label.textContent;
            submit.disabled = true;
            label.textContent = translate('rsvp.sending', []);

            fetch(form.getAttribute('action') || '/', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    guests: guests,
                    email: email,
                    locale: locale,
                    website: websiteInput ? websiteInput.value : ''
                })
            })
                .then(function (response) { return response.json().then(function (body) { return { ok: response.ok, body: body }; }); })
                .then(function (result) {
                    if (!result.ok || !result.body.ok) {
                        var errors = result.body.errors || {};
                        showError(errors.names || errors.age || errors.email || errors.global || translate('rsvp.error', []));
                        submit.disabled = false;
                        label.textContent = previous;
                        return;
                    }
                    zone.innerHTML = result.body.summary;
                    applyLocale(locale, zone);
                    initSummary();
                    zone.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'center' });
                })
                .catch(function () {
                    showError(translate('rsvp.error', []));
                    submit.disabled = false;
                    label.textContent = previous;
                });
        });
    }

    function initSummary() {
        var print = document.querySelector('[data-print]');
        if (print) {
            print.addEventListener('click', function () { window.print(); });
        }
    }

    initForm();
    initSummary();
})();
