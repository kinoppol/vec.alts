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

    /*
     * Chunked transfer of a large dataset.
     *
     * The student list runs to thousands of rows, more than one request can
     * hash and store, so the loop lives here: ask for the total, then request
     * one slice at a time. Each slice is its own request, so none of them can
     * outrun the server's execution limit, and the progress shown is real.
     */
    function initChunkedTransfer() {
        var root = document.getElementById('student-transfer');
        if (!root) {
            return;
        }

        var endpoint = root.getAttribute('data-endpoint');
        var school = root.getAttribute('data-school');
        var token = root.getAttribute('data-token');
        var row = parseInt(root.getAttribute('data-row'), 10) || 100;

        var startBtn = root.querySelector('[data-start]');
        var panel = root.querySelector('[data-panel]');
        var bar = root.querySelector('[data-bar]');
        var percent = root.querySelector('[data-percent]');
        var status = root.querySelector('[data-status]');
        var errorsBox = root.querySelector('[data-errors]');
        var errorList = root.querySelector('[data-error-list]');

        var counters = {
            added: root.querySelector('[data-added]'),
            updated: root.querySelector('[data-updated]'),
            skipped: root.querySelector('[data-skipped]'),
            done: root.querySelector('[data-done]')
        };

        function setText(el, value) {
            if (el) {
                el.textContent = value;
            }
        }

        function post(params, done) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', endpoint, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) {
                    return;
                }
                var parsed = null;
                try {
                    parsed = JSON.parse(xhr.responseText);
                } catch (e) {
                    done({ success: false, message: 'เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง (HTTP ' + xhr.status + ')' });
                    return;
                }
                done(parsed);
            };
            var body = '_token=' + encodeURIComponent(token) +
                       '&school_id=' + encodeURIComponent(school);
            for (var key in params) {
                if (params.hasOwnProperty(key)) {
                    body += '&' + key + '=' + encodeURIComponent(params[key]);
                }
            }
            xhr.send(body);
        }

        function fail(message) {
            status.className = 'cell-dim';
            status.style.color = 'var(--danger)';
            setText(status, message);
            startBtn.disabled = false;
            startBtn.textContent = 'ลองอีกครั้ง';
        }

        function showErrors(list) {
            if (!list || !list.length) {
                return;
            }
            errorsBox.hidden = false;
            errorList.textContent += list.join('\n') + '\n';
        }

        startBtn.addEventListener('click', function () {
            var confirmText = startBtn.getAttribute('data-confirm');
            if (confirmText && !window.confirm(confirmText)) {
                return;
            }

            startBtn.disabled = true;
            startBtn.textContent = 'กำลังโอนข้อมูล…';
            panel.hidden = false;
            status.style.color = '';
            setText(status, 'กำลังนับจำนวนผู้เรียนทั้งหมด…');

            var totals = { added: 0, updated: 0, skipped: 0, done: 0, noLogin: 0 };
            var total = 0;
            var offset = 0;

            function render() {
                setText(counters.added, totals.added);
                setText(counters.updated, totals.updated);
                setText(counters.skipped, totals.skipped);
                setText(counters.done, totals.done);
                setText(counters.noLogin, totals.noLogin);
                if (totals.noLogin > 0 && noLoginBox) {
                    noLoginBox.hidden = false;
                }

                var pct = total > 0 ? Math.min(100, Math.round((totals.done / total) * 100)) : 0;
                bar.style.width = pct + '%';
                setText(percent, pct + '%');
                setText(status, 'โอนแล้ว ' + totals.done +
                    (total > 0 ? ' จาก ' + total : '') + ' รายการ');
            }

            function finish() {
                bar.style.width = '100%';
                setText(percent, '100%');
                setText(status, 'เสร็จสิ้น — เพิ่มใหม่ ' + totals.added +
                    ' คน · ปรับปรุง ' + totals.updated + ' คน' +
                    (totals.skipped ? ' · ข้าม ' + totals.skipped + ' คน' : ''));
                startBtn.disabled = false;
                startBtn.textContent = 'โอนข้อมูลอีกครั้ง';
            }

            function nextBatch() {
                post({ action: 'sync_batch', offset: offset, row: row }, function (res) {
                    if (!res || !res.success) {
                        // Say how far it got, so the operator knows what landed.
                        fail('หยุดที่ ' + totals.done + ' รายการ: ' +
                             ((res && res.message) || 'เกิดข้อผิดพลาด'));
                        return;
                    }
                    var d = res.data || {};
                    totals.added += d.added || 0;
                    totals.updated += d.updated || 0;
                    totals.skipped += d.skipped || 0;
                    totals.noLogin += d.no_login || 0;
                    // fetched counts raw rows from RMS, not rows stored: using
                    // the stored count would stop the loop early whenever a row
                    // was skipped.
                    totals.done += d.fetched || 0;
                    offset += row;
                    showErrors(d.errors);
                    render();

                    if ((d.fetched || 0) < row) {
                        finish();          // a short slice means the end
                        return;
                    }
                    if (total > 0 && totals.done >= total) {
                        finish();          // guard against a source that never shortens
                        return;
                    }
                    nextBatch();
                });
            }

            post({ action: 'count' }, function (res) {
                if (!res || !res.success) {
                    fail((res && res.message) || 'นับจำนวนไม่สำเร็จ');
                    return;
                }
                total = (res.data && res.data.total) || 0;
                render();
                nextBatch();
            });
        });
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
        initChunkedTransfer();
    });
}());
