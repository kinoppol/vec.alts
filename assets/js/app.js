/*
 * Progressive enhancement only. Every screen works with JavaScript disabled;
 * this file adds the theme toggle and the show/hide behaviour of the survey
 * form. ES5 syntax, because school desktops still run old browsers.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'vec-theme';

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        var icons = document.querySelectorAll('[data-theme-icon]');
        var labels = document.querySelectorAll('[data-theme-label]');
        var i;
        for (i = 0; i < icons.length; i++) {
            icons[i].textContent = theme === 'dark' ? '☀️' : '🌙';
        }
        for (i = 0; i < labels.length; i++) {
            labels[i].textContent = theme === 'dark' ? 'สว่าง' : 'มืด';
        }
    }

    function currentTheme() {
        return document.documentElement.getAttribute('data-theme') || 'light';
    }

    function storeTheme(theme) {
        try {
            window.localStorage.setItem(STORAGE_KEY, theme);
        } catch (e) {
            // private browsing or storage disabled: the theme simply will not persist
        }
    }

    function initTheme() {
        var toggles = document.querySelectorAll('[data-theme-toggle]');
        for (var i = 0; i < toggles.length; i++) {
            toggles[i].addEventListener('click', function (ev) {
                ev.preventDefault();
                var next = currentTheme() === 'dark' ? 'light' : 'dark';
                applyTheme(next);
                storeTheme(next);
            });
        }
        applyTheme(currentTheme());
    }

    /*
     * The employment choices reveal a different block of fields. The server
     * renders all three blocks and re-renders correctly on submit, so this is
     * only about avoiding a round trip.
     */
    function initSurveyForm() {
        var form = document.querySelector('[data-survey-form]');
        if (!form) {
            return;
        }
        var radios = form.querySelectorAll('input[name="employment_status"]');
        var blocks = form.querySelectorAll('[data-emp-group]');

        function sync() {
            var selectedGroup = '';
            var i;
            for (i = 0; i < radios.length; i++) {
                var label = radios[i].parentNode;
                if (radios[i].checked) {
                    selectedGroup = radios[i].getAttribute('data-group');
                    if (label && label.classList) {
                        label.classList.add('on');
                    }
                } else if (label && label.classList) {
                    label.classList.remove('on');
                }
            }
            for (i = 0; i < blocks.length; i++) {
                var group = blocks[i].getAttribute('data-emp-group');
                blocks[i].style.display = (group === selectedGroup) ? '' : 'none';
            }
        }

        for (var i = 0; i < radios.length; i++) {
            radios[i].addEventListener('change', sync);
        }
        sync();
    }

    /*
     * Reveal toggle for password fields, so someone typing a long password on
     * a phone keyboard can check it before submitting. The markup ships the
     * button hidden and it is unhidden here, because without JavaScript there
     * is nothing it could do.
     */
    function initPasswordReveal() {
        var buttons = document.querySelectorAll('[data-reveal-password]');
        for (var i = 0; i < buttons.length; i++) {
            (function (button) {
                var field = document.getElementById(button.getAttribute('data-reveal-password'));
                if (!field) {
                    return;
                }
                button.hidden = false;
                button.addEventListener('click', function () {
                    var revealed = field.type === 'text';
                    // Old IE refuses to retype a live password input; leaving
                    // the field as it was beats throwing on the click.
                    try {
                        field.type = revealed ? 'password' : 'text';
                    } catch (e) {
                        return;
                    }
                    button.textContent = revealed ? 'แสดง' : 'ซ่อน';
                    button.setAttribute('aria-pressed', revealed ? 'false' : 'true');
                    button.setAttribute('aria-label', revealed ? 'แสดงรหัสผ่าน' : 'ซ่อนรหัสผ่าน');
                    field.focus();
                });
            }(buttons[i]));
        }
    }

    /*
     * Progress indicator for forms that take a long time on the server.
     *
     * The form still submits normally — this only covers the page so the
     * operator can see something is happening and cannot start it twice.
     * Submit buttons are deliberately NOT disabled: a disabled button is left
     * out of the submitted data, which would drop the name/value that says
     * which action was asked for.
     */
    function initBusyForms() {
        var forms = document.querySelectorAll('form[data-busy]');
        if (!forms.length) {
            return;
        }

        var overlay = null;
        var timer = null;

        function buildOverlay(message, steps) {
            var el = document.createElement('div');
            el.className = 'busy-overlay';
            el.setAttribute('role', 'alert');
            el.setAttribute('aria-live', 'assertive');
            el.setAttribute('aria-busy', 'true');

            var box = document.createElement('div');
            box.className = 'busy-box';

            var spinner = document.createElement('div');
            spinner.className = 'spinner';
            box.appendChild(spinner);

            var title = document.createElement('div');
            title.className = 'busy-title';
            title.textContent = message;
            box.appendChild(title);

            var note = document.createElement('div');
            note.className = 'busy-note';
            note.textContent = 'กรุณาอย่าปิดหรือรีเฟรชหน้านี้จนกว่าจะเสร็จ';
            box.appendChild(note);

            var track = document.createElement('div');
            track.className = 'busy-track';
            track.appendChild(document.createElement('span'));
            box.appendChild(track);

            var elapsed = document.createElement('div');
            elapsed.className = 'busy-elapsed';
            elapsed.textContent = 'ใช้เวลาไปแล้ว 0 วินาที';
            box.appendChild(elapsed);

            if (steps) {
                var list = document.createElement('div');
                list.className = 'busy-steps';
                list.textContent = steps;
                box.appendChild(list);
            }

            el.appendChild(box);
            document.body.appendChild(el);

            // A real count of how long the operator has been waiting. The
            // stages themselves happen on the server, so no attempt is made
            // to guess which one is running.
            var started = new Date().getTime();
            timer = window.setInterval(function () {
                var seconds = Math.round((new Date().getTime() - started) / 1000);
                elapsed.textContent = 'ใช้เวลาไปแล้ว ' + seconds + ' วินาที';
            }, 1000);

            return el;
        }

        function hideOverlay() {
            if (timer) {
                window.clearInterval(timer);
                timer = null;
            }
            if (overlay && overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
            overlay = null;
        }

        for (var i = 0; i < forms.length; i++) {
            (function (form) {
                var pending = false;
                var pressed = null;

                // Remember which button was used, so its own message wins.
                var buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
                for (var b = 0; b < buttons.length; b++) {
                    buttons[b].addEventListener('click', function () {
                        pressed = this;
                    });
                }

                form.addEventListener('submit', function (ev) {
                    if (pending) {
                        // Already running; ignore a second attempt.
                        ev.preventDefault();
                        return;
                    }
                    var message = form.getAttribute('data-busy');
                    if (pressed && pressed.getAttribute('data-busy-message')) {
                        message = pressed.getAttribute('data-busy-message');
                    }
                    pending = true;
                    overlay = buildOverlay(message, form.getAttribute('data-busy-steps'));
                });
            }(forms[i]));
        }

        // Returning via the back button can restore the page from cache with
        // the overlay still on it.
        window.addEventListener('pageshow', hideOverlay);
    }

    /* Confirmation prompts for destructive buttons. */
    function initConfirms() {
        var nodes = document.querySelectorAll('[data-confirm]');
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].addEventListener('click', function (ev) {
                if (!window.confirm(this.getAttribute('data-confirm'))) {
                    ev.preventDefault();
                }
            });
        }
    }

    /* Auto-submit filter selects. */
    function initAutoSubmit() {
        var nodes = document.querySelectorAll('[data-auto-submit]');
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].addEventListener('change', function () {
                if (this.form) {
                    this.form.submit();
                }
            });
        }
    }

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    ready(function () {
        initTheme();
        initSurveyForm();
        initPasswordReveal();
        initConfirms();
        initAutoSubmit();
        initBusyForms();
    });
}());
