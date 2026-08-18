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
        initConfirms();
        initAutoSubmit();
    });
}());
