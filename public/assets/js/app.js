/* =========================================================================
   Worldbuilder — all client-side behaviour. No dependencies, no build step.
   Each initialiser is independent and no-ops when its markup is absent.
   ========================================================================= */

(function () {
    'use strict';

    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var base = (document.querySelector('meta[name="app-base"]') || {}).content || '';

    /** The current locale's whole catalog, embedded once by shell.php — see App\Language on the PHP side. */
    var i18n = {};
    try {
        var i18nTag = document.querySelector('script[data-i18n]');
        if (i18nTag) { i18n = JSON.parse(i18nTag.textContent); }
    } catch (e) { /* malformed or missing — every t() call just falls back to its own English text */ }

    /** Looks up `key` in the current locale; %s in the result is replaced, in order, by any extra arguments. */
    function t(key) {
        var text = Object.prototype.hasOwnProperty.call(i18n, key) ? i18n[key] : key;
        for (var i = 1; i < arguments.length; i++) { text = text.replace('%s', arguments[i]); }
        return text;
    }

    /**
     * Greedy lane packing shared by the linear timeline and month-grid calendar.
     * Returns lane indices parallel to `items`. `minSpan` keeps a lane claimed
     * for at least that long, so a very short item still reads as occupying room.
     */
    function assignLanes(items, getStart, getEnd, minSpan) {
        minSpan = minSpan || 0;
        var laneEnds = [];

        return items.map(function (item) {
            var start = getStart(item);
            var end = getEnd(item);
            var lane = laneEnds.length;
            for (var l = 0; l < laneEnds.length; l++) {
                if (laneEnds[l] <= start) { lane = l; break; }
            }
            laneEnds[lane] = Math.max(end, start + minSpan);

            return lane;
        });
    }

    // Perceived brightness (ITU-R BT.601) — picks light or dark text for a
    // freely-chosen bar colour.
    function textOn(hex) {
        var m = /^#([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex || '');
        if (!m) { return '#fff'; }
        var r = parseInt(m[1], 16), g = parseInt(m[2], 16), b = parseInt(m[3], 16);
        var yiq = (r * 299 + g * 587 + b * 114) / 1000;
        return yiq >= 150 ? '#111' : '#fff';
    }

    function ordinal(day) {
        var abs = Math.abs(day);
        var suffix = 'th';
        if (abs % 100 < 11 || abs % 100 > 13) {
            suffix = { 1: 'st', 2: 'nd', 3: 'rd' }[abs % 10] || 'th';
        }
        return day + suffix;
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTheme();
        initFlashes();
        initConfirms();
        initDialogs();
        initIconPicker();
        initEditors();
        initTagInputs();
        initRelationFields();
        initConnectPicker();
        initConnectionNotes();
        initConnectionEdit();
        initLayoutEditor();
        initArchiveSorting();
        initLayoutSwitch();
        initFormatHints();
        initMapCutouts();
        initMapAreaFields();
        initWorldMap();
        initArchiveTree();
        initArchivesPanel();
        initFavorites();
        initPinboard();
        initTimeline();
        initTimelineCalendar();
        initFilterPanels();
        initDatePickers();
        initCalendarSettings();
    });

    /* --- collapsing sub-archives in the sidebar ---------------------------- */

    function initArchiveTree() {
        var toggles = document.querySelectorAll('[data-archive-toggle]');
        if (!toggles.length) { return; }

        var collapsed = {};
        try {
            (JSON.parse(localStorage.getItem('wb-archives-collapsed') || '[]') || [])
                .forEach(function (id) { collapsed[id] = true; });
        } catch (e) { /* private mode, or nonsense in storage */ }

        var remember = function () {
            try {
                localStorage.setItem('wb-archives-collapsed', JSON.stringify(Object.keys(collapsed)));
            } catch (e) { /* private mode */ }
        };

        var apply = function (toggle) {
            var id = toggle.dataset.archiveToggle;
            var children = document.querySelector('[data-archive-children="' + id + '"]');
            if (!children) { return; }

            // Whatever archive is open, or an ancestor of it, stays visible
            // however it was left — collapsing it would hide where you are.
            var open = !collapsed[id] || !!children.querySelector('.archive-link.is-open, .archive-link.is-active');

            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', t(open ? 'Collapse %s' : 'Expand %s', toggle.dataset.archiveName));
            children.hidden = !open;
        };

        toggles.forEach(function (toggle) {
            apply(toggle);
            toggle.addEventListener('click', function () {
                var id = toggle.dataset.archiveToggle;
                if (collapsed[id]) { delete collapsed[id]; } else { collapsed[id] = true; }
                remember();
                apply(toggle);
            });
        });
    }

    /* --- collapsing the whole archives list, on the canvas-style views ---- */

    function initArchivesPanel() {
        var panel = document.querySelector('[data-archives-panel]');
        var toggle = document.querySelector('[data-archives-toggle]');
        var body = document.querySelector('[data-archives-body]');
        if (!panel || !toggle || !body) { return; }

        var open = true;
        try { open = localStorage.getItem('wb-archives-panel') !== 'closed'; } catch (e) { /* private mode */ }

        var apply = function () {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', t(open ? 'Collapse Archives' : 'Expand Archives'));
            body.hidden = !open;
        };

        apply();
        toggle.addEventListener('click', function () {
            open = !open;
            try { localStorage.setItem('wb-archives-panel', open ? 'open' : 'closed'); } catch (e) { /* private mode */ }
            apply();
        });
    }

    /* --- the in-page archive filter, on the Pinboard and Timeline/Calendar
       stages: folds to a slim rail so the canvas beside it gets the room. --- */

    function initFilterPanels() {
        var panels = document.querySelectorAll('.pinboard-filter');
        if (!panels.length) { return; }

        var open = true;
        try { open = localStorage.getItem('wb-filter-panel') !== 'closed'; } catch (e) { /* private mode */ }

        Array.prototype.forEach.call(panels, function (panel) {
            var toggle = panel.querySelector('[data-filter-toggle]');
            if (!toggle) { return; }

            var apply = function () {
                panel.classList.toggle('is-collapsed', !open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                toggle.setAttribute('aria-label', t(open ? 'Collapse Archives' : 'Expand Archives'));
            };

            apply();
            toggle.addEventListener('click', function () {
                open = !open;
                try { localStorage.setItem('wb-filter-panel', open ? 'open' : 'closed'); } catch (e) { /* private mode */ }
                apply();
                // The linear timeline only re-measures its stage on a window
                // resize; the calendar grid and pinboard canvas are already
                // fluid, so this is a no-op for them.
                window.dispatchEvent(new Event('resize'));
            });
        });
    }

    /* --- favourites panel ------------------------------------------------- */

    function initFavorites() {
        var wrap = document.querySelector('[data-favorites]');
        if (!wrap) { return; }

        var panel = wrap.querySelector('.favorites-panel');
        var buttons = wrap.querySelectorAll('[data-favorites-toggle]');
        var open = false;

        try { open = localStorage.getItem('wb-favorites') === 'open'; } catch (e) { /* private mode */ }

        var apply = function () {
            wrap.classList.toggle('is-open', open);

            Array.prototype.forEach.call(buttons, function (button) {
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
            });

            // Off-screen alone wouldn't stop tab/screen-reader access.
            if (panel) {
                if ('inert' in panel) {
                    panel.inert = !open;
                } else {
                    panel.setAttribute('aria-hidden', open ? 'false' : 'true');
                }
            }
        };

        apply();

        // Forces layout before the transition class is added, so the panel
        // doesn't slide in on load.
        void wrap.offsetWidth;
        wrap.classList.add('is-ready');

        Array.prototype.forEach.call(buttons, function (button) {
            button.addEventListener('click', function () {
                open = !open;
                apply();
                try {
                    localStorage.setItem('wb-favorites', open ? 'open' : 'shut');
                } catch (e) { /* private mode */ }
            });
        });

        document.addEventListener('keydown', function (event) {
            // Not while something modal is up: Escape belongs to that first.
            if (event.key !== 'Escape' || !open || document.querySelector('dialog[open]')) { return; }
            open = false;
            apply();
            try { localStorage.setItem('wb-favorites', 'shut'); } catch (e) { /* private mode */ }
        });
    }

    /* --- export format hints ---------------------------------------------- */

    function initFormatHints() {
        document.querySelectorAll('[data-format-picker]').forEach(function (select) {
            var hint = select.parentNode.querySelector('[data-format-hint]');
            if (!hint) { return; }

            var show = function () {
                var option = select.options[select.selectedIndex];
                hint.textContent = option ? (option.dataset.hint || '') : '';
            };

            select.addEventListener('change', show);
            show();
        });
    }

    /* --- theme ---------------------------------------------------------- */

    function initTheme() {
        document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                var next = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
                document.documentElement.dataset.theme = next;
                try { localStorage.setItem('wb-theme', next); } catch (e) { /* private mode */ }
            });
        });
    }

    /* --- flash messages -------------------------------------------------- */

    function initFlashes() {
        document.querySelectorAll('.flash').forEach(function (flash) {
            var close = flash.querySelector('[data-dismiss]');
            if (close) {
                close.addEventListener('click', function () { flash.remove(); });
            }
            // Errors stay until dismissed; confirmations get out of the way.
            if (!flash.classList.contains('flash--error')) {
                setTimeout(function () { flash.remove(); }, 5000);
            }
        });
    }

    /* --- confirm before destructive posts -------------------------------- */

    function initConfirms() {
        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!window.confirm(form.dataset.confirm)) {
                    event.preventDefault();
                }
            });
        });
    }

    /* --- dialogs --------------------------------------------------------- */

    function initDialogs() {
        document.querySelectorAll('[data-open-dialog]').forEach(function (trigger) {
            trigger.addEventListener('click', function () {
                var dialog = document.getElementById(trigger.dataset.openDialog);
                if (!dialog) { return; }
                if (typeof dialog.showModal === 'function') {
                    dialog.showModal();
                } else {
                    dialog.setAttribute('open', '');
                }
                var first = dialog.querySelector('input:not([type=hidden])');
                if (first) { first.focus(); }
            });
        });

        document.querySelectorAll('[data-close-dialog]').forEach(function (button) {
            button.addEventListener('click', function () {
                var dialog = button.closest('dialog');
                if (!dialog) { return; }
                if (typeof dialog.close === 'function') {
                    dialog.close();
                } else {
                    dialog.removeAttribute('open');
                }
            });
        });
    }

    /* --- rich text ------------------------------------------------------- */

    // Classes, not inline styles: the sanitiser allow-lists class names, not
    // style attributes.
    var BLOCK_SELECTOR = 'p,h1,h2,h3,h4,blockquote,li,pre,div';
    var ALIGN_CLASSES = ['align-left', 'align-center', 'align-right', 'align-justify'];
    var MAX_INDENT = 4;

    /** Innermost blocks the selection touches; a list item aligns independently of its list. */
    function selectedBlocks(surface) {
        var selection = window.getSelection();
        if (!selection || selection.rangeCount === 0) { return []; }

        var range = selection.getRangeAt(0);
        var all = Array.prototype.slice.call(surface.querySelectorAll(BLOCK_SELECTOR));
        var innermost = all.filter(function (el) {
            return !all.some(function (other) { return other !== el && el.contains(other); });
        });

        var touched = innermost.filter(function (el) {
            try { return range.intersectsNode(el); } catch (e) { return false; }
        });

        if (touched.length) { return touched; }

        // Caret sitting in loose text with no block around it.
        var node = range.startContainer;
        while (node && node !== surface) {
            if (node.nodeType === 1 && node.matches && node.matches(BLOCK_SELECTOR)) {
                return [node];
            }
            node = node.parentNode;
        }

        return [];
    }

    /** An element with no classes left should not keep an empty attribute. */
    function tidyClasses(el) {
        if (el.classList.length === 0) { el.removeAttribute('class'); }
    }

    function currentIndent(el) {
        for (var i = MAX_INDENT; i >= 1; i--) {
            if (el.classList.contains('indent-' + i)) { return i; }
        }
        return 0;
    }

    function initEditors() {
        document.querySelectorAll('[data-editor]').forEach(function (wrapper) {
            var surface = wrapper.querySelector('.editor-surface');
            var store = wrapper.querySelector('[data-editor-value]');
            if (!surface || !store) { return; }

            var block = wrapper.closest('.field-block');
            var counter = block ? block.querySelector('[data-word-count]') : null;

            var sync = function () {
                store.value = surface.innerHTML;
                if (counter) {
                    var words = surface.textContent.trim().match(/\S+/g);
                    var n = words ? words.length : 0;
                    counter.textContent = n.toLocaleString() + (n === 1 ? ' word' : ' words');
                }
            };

            // Blocks are what alignment attaches to, so loose text needs one
            // before it can be aligned.
            var blocksOrWrap = function () {
                var blocks = selectedBlocks(surface);
                if (blocks.length === 0) {
                    document.execCommand('formatBlock', false, 'p');
                    blocks = selectedBlocks(surface);
                }
                return blocks;
            };

            wrapper.querySelectorAll('[data-align]').forEach(function (button) {
                button.addEventListener('mousedown', function (event) { event.preventDefault(); });
                button.addEventListener('click', function () {
                    surface.focus();
                    var wanted = button.dataset.align;
                    blocksOrWrap().forEach(function (el) {
                        var had = el.classList.contains('align-' + wanted);
                        ALIGN_CLASSES.forEach(function (cls) { el.classList.remove(cls); });
                        // Pressing the active alignment again returns to default.
                        if (!had && wanted !== 'left') { el.classList.add('align-' + wanted); }
                        tidyClasses(el);
                    });
                    sync();
                    refresh();
                });
            });

            wrapper.querySelectorAll('[data-indent]').forEach(function (button) {
                button.addEventListener('mousedown', function (event) { event.preventDefault(); });
                button.addEventListener('click', function () {
                    surface.focus();
                    var step = button.dataset.indent === 'out' ? -1 : 1;
                    blocksOrWrap().forEach(function (el) {
                        var now = currentIndent(el);
                        var next = Math.min(MAX_INDENT, Math.max(0, now + step));
                        if (now) { el.classList.remove('indent-' + now); }
                        if (next) { el.classList.add('indent-' + next); }
                        tidyClasses(el);
                    });
                    sync();
                    refresh();
                });
            });

            // A toggle, not a level: mixed selections turn it on for all.
            wrapper.querySelectorAll('[data-first-line]').forEach(function (button) {
                button.addEventListener('mousedown', function (event) { event.preventDefault(); });
                button.addEventListener('click', function () {
                    surface.focus();
                    var blocks = blocksOrWrap();
                    var turnOn = blocks.some(function (el) {
                        return !el.classList.contains('first-line-indent');
                    });
                    blocks.forEach(function (el) {
                        el.classList.toggle('first-line-indent', turnOn);
                        tidyClasses(el);
                    });
                    sync();
                    refresh();
                });
            });

            // Reflects the caret's current alignment and first-line-indent state.
            var refresh = function () {
                var blocks = selectedBlocks(surface);
                var active = 'left';
                if (blocks.length) {
                    ALIGN_CLASSES.forEach(function (cls) {
                        if (blocks[0].classList.contains(cls)) { active = cls.slice(6); }
                    });
                }
                wrapper.querySelectorAll('[data-align]').forEach(function (button) {
                    button.classList.toggle('is-active', button.dataset.align === active);
                });

                var indented = blocks.length > 0
                    && blocks[0].classList.contains('first-line-indent');
                wrapper.querySelectorAll('[data-first-line]').forEach(function (button) {
                    button.classList.toggle('is-active', indented);
                });
            };

            ['keyup', 'mouseup', 'focus'].forEach(function (event) {
                surface.addEventListener(event, refresh);
            });

            wrapper.querySelectorAll('[data-cmd]').forEach(function (button) {
                button.addEventListener('mousedown', function (event) { event.preventDefault(); });
                button.addEventListener('click', function () {
                    surface.focus();
                    document.execCommand(button.dataset.cmd, false, null);
                    sync();
                });
            });

            wrapper.querySelectorAll('[data-block]').forEach(function (button) {
                button.addEventListener('mousedown', function (event) { event.preventDefault(); });
                button.addEventListener('click', function () {
                    surface.focus();
                    document.execCommand('formatBlock', false, button.dataset.block);
                    sync();
                });
            });

            initLinkPicker(wrapper, surface, sync);

            // Stripped now rather than on save, so what's shown matches what's stored.
            surface.addEventListener('paste', function (event) {
                event.preventDefault();
                var text = (event.clipboardData || window.clipboardData).getData('text/plain');
                document.execCommand('insertText', false, text);
                sync();
            });

            surface.addEventListener('input', sync);
            surface.addEventListener('blur', sync);

            var form = wrapper.closest('form');
            if (form) { form.addEventListener('submit', sync); }
        });
    }


    /* --- links between entries -------------------------------------------- */

    // URL_LIKE: already a usable address. DOMAIN_LIKE: a bare domain needing a scheme.
    var URL_LIKE = /^(https?:\/\/|mailto:|\/)/i;
    var DOMAIN_LIKE = /^[\w-]+(\.[\w-]+)+([\/?#].*)?$/;

    /**
     * Link button: searches for entries rather than taking a raw address. Entry
     * links store the guid, not the address, so renames don't break them
     * (App\EntryLinks resolves it on render); typed addresses are stored as-is.
     */
    function initLinkPicker(wrapper, surface, sync) {
        var button = wrapper.querySelector('[data-link]');
        if (!button) { return; }

        var toolbar = wrapper.querySelector('.editor-toolbar');

        // Built here rather than in the two views that hold an editor, so the
        // markup exists once.
        var panel = document.createElement('div');
        panel.className = 'editor-link';
        panel.hidden = true;

        var search = document.createElement('input');
        search.type = 'text';
        search.className = 'input editor-link-search';
        search.autocomplete = 'off';
        search.placeholder = t('Search entries, or paste an address…');

        var results = document.createElement('ul');
        results.className = 'editor-link-results';

        panel.appendChild(search);
        panel.appendChild(results);
        wrapper.insertBefore(panel, surface);

        var saved = null;      // the selection the panel was opened on
        var editing = null;    // the link the caret was already inside, if any
        var timer = null;
        var highlighted = -1;

        /** The link the caret sits in, if it sits in one. */
        var linkAt = function () {
            var selection = window.getSelection();
            if (!selection || selection.rangeCount === 0) { return null; }

            var node = selection.getRangeAt(0).startContainer;
            while (node && node !== surface) {
                if (node.nodeType === 1 && node.nodeName === 'A') { return node; }
                node = node.parentNode;
            }

            return null;
        };

        // Focusing the search box clears the selection, so it's restored before writing.
        var restore = function () {
            surface.focus();
            if (!saved) { return; }

            var selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(saved);
        };

        var close = function (refocus) {
            panel.hidden = true;
            results.innerHTML = '';
            highlighted = -1;
            if (refocus) { restore(); }
        };

        var open = function () {
            var selection = window.getSelection();
            saved = selection && selection.rangeCount ? selection.getRangeAt(0) : null;
            editing = linkAt();

            panel.hidden = false;
            // Measured, not fixed: the toolbar can wrap to two rows in a narrow column.
            panel.style.top = (toolbar ? toolbar.offsetHeight : 0) + 'px';

            search.value = '';
            search.focus();
            load();
        };

        /** Writes the link: over the one the caret was in, or around the selection. */
        var apply = function (href, guid, label) {
            restore();

            if (editing) {
                editing.setAttribute('href', href);
                if (guid) {
                    editing.setAttribute('data-entry', guid);
                } else {
                    editing.removeAttribute('data-entry');
                }
                sync();
                close(true);
                return;
            }

            var selection = window.getSelection();

            if (!selection || selection.isCollapsed) {
                // Nothing was selected, so the entry's own name becomes the
                // words of the link.
                var anchor = document.createElement('a');
                anchor.setAttribute('href', href);
                if (guid) { anchor.setAttribute('data-entry', guid); }
                anchor.textContent = label || href;

                if (selection && selection.rangeCount) {
                    var range = selection.getRangeAt(0);
                    range.insertNode(anchor);
                    range.setStartAfter(anchor);
                    range.collapse(true);
                    selection.removeAllRanges();
                    selection.addRange(range);
                } else {
                    surface.appendChild(anchor);
                }
            } else {
                // Selection can span several elements, so execCommand does the
                // wrapping; it returns nothing, so a throwaway href finds the result.
                var token = '#wb-link-' + Date.now();
                document.execCommand('createLink', false, token);

                Array.prototype.forEach.call(
                    surface.querySelectorAll('a[href$="' + token + '"]'),
                    function (made) {
                        made.setAttribute('href', href);
                        if (guid) { made.setAttribute('data-entry', guid); }
                    }
                );
            }

            sync();
            close(true);
        };

        /** Keeps the words, drops the link around them. */
        var unlink = function () {
            restore();

            var link = editing || linkAt();
            if (link && link.parentNode) {
                while (link.firstChild) {
                    link.parentNode.insertBefore(link.firstChild, link);
                }
                link.parentNode.removeChild(link);
            }

            sync();
            close(true);
        };

        var row = function (icon, title, where, pick) {
            var item = document.createElement('li');

            var option = document.createElement('button');
            option.type = 'button';
            option.className = 'editor-link-result';

            var mark = document.createElement('span');
            mark.className = 'chip-icon';
            mark.textContent = icon;

            var name = document.createElement('span');
            name.className = 'editor-link-title';
            name.textContent = title;

            option.appendChild(mark);
            option.appendChild(name);

            if (where) {
                var note = document.createElement('span');
                note.className = 'editor-link-where';
                note.textContent = where;
                option.appendChild(note);
            }

            // mousedown, not click: by click time the search box has lost focus
            // and the saved selection with it.
            option.addEventListener('mousedown', function (event) {
                event.preventDefault();
                pick();
            });

            item.appendChild(option);
            results.appendChild(item);
        };

        var render = function (entries) {
            results.innerHTML = '';
            highlighted = -1;

            var text = search.value.trim();

            if (editing) {
                row('✕', t('Remove this link'), '', unlink);
            }

            if (URL_LIKE.test(text) || DOMAIN_LIKE.test(text)) {
                var href = URL_LIKE.test(text) ? text : 'https://' + text;
                row('↗', t('Link to %s', href), 'address', function () {
                    apply(href, null, text);
                });
            }

            entries.forEach(function (entry) {
                row(entry.icon || '•', entry.title, entry.category, function () {
                    apply(entry.url, entry.guid, entry.title);
                });
            });

            if (!results.children.length) {
                var empty = document.createElement('li');
                empty.className = 'relation-empty';
                empty.textContent = t('Nothing matches.');
                results.appendChild(empty);
            }
        };

        var load = function () {
            fetch(base + '/api/lookup?scope=entries&q=' + encodeURIComponent(search.value.trim()), {
                headers: { 'Accept': 'application/json' }
            })
                .then(function (response) { return response.json(); })
                .then(function (data) { render(data.results || []); })
                .catch(function () { render([]); });
        };

        button.addEventListener('mousedown', function (event) { event.preventDefault(); });
        button.addEventListener('click', function () {
            if (panel.hidden) { open(); } else { close(true); }
        });

        search.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(load, 180);
        });

        search.addEventListener('keydown', function (event) {
            var options = results.querySelectorAll('.editor-link-result');

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                if (!options.length) { return; }
                event.preventDefault();
                highlighted += (event.key === 'ArrowDown' ? 1 : -1);
                if (highlighted < 0) { highlighted = options.length - 1; }
                if (highlighted >= options.length) { highlighted = 0; }
                Array.prototype.forEach.call(options, function (option, index) {
                    option.classList.toggle('is-highlighted', index === highlighted);
                });
                options[highlighted].scrollIntoView({ block: 'nearest' });
                return;
            }

            if (event.key === 'Enter') {
                // With nothing chosen, the first row is what the list is
                // offering, so Enter takes it.
                event.preventDefault();
                var wanted = options[highlighted >= 0 ? highlighted : 0];
                if (wanted) { wanted.dispatchEvent(new MouseEvent('mousedown')); }
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                close(true);
            }
        });

        search.addEventListener('blur', function () {
            // Long enough for a pick to land, short enough not to linger.
            setTimeout(function () {
                if (document.activeElement !== search) { close(false); }
            }, 140);
        });

        // The shortcut every other editor uses for this.
        surface.addEventListener('keydown', function (event) {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                open();
            }
        });
    }


    /* --- choosing an icon -------------------------------------------------- */

    /**
     * One chooser shared by every icon field. The field that opened it is
     * remembered rather than passed in, since the dialog lives outside all forms.
     */
    function initIconPicker() {
        var dialog = document.querySelector('[data-icon-picker]');
        if (!dialog) { return; }

        var search = dialog.querySelector('[data-icon-search]');
        var groups = dialog.querySelectorAll('[data-icon-group]');
        var options = dialog.querySelectorAll('.icon-option');
        var empty = dialog.querySelector('[data-icon-empty]');
        var custom = dialog.querySelector('[data-icon-custom]');
        var field = null;

        var close = function () {
            if (typeof dialog.close === 'function') {
                dialog.close();
            } else {
                dialog.removeAttribute('open');
            }
        };

        /** Writes the choice back into the field that opened the chooser. */
        var choose = function (glyph) {
            if (!field) { return; }

            var value = field.querySelector('[data-icon-value]');
            var shown = field.querySelector('[data-icon-shown]');

            if (value) { value.value = glyph; }
            if (shown) {
                shown.textContent = glyph === '' ? '•' : glyph;
                shown.classList.toggle('is-empty', glyph === '');
            }

            var trigger = field.querySelector('[data-icon-trigger]');
            if (trigger) {
                trigger.setAttribute('aria-label',
                    glyph === '' ? t('Choose an icon') : t('Choose an icon — currently %s', glyph));
            }

            close();
        };

        /** Narrows the grid to whatever the words match, and hides emptied groups. */
        var filter = function () {
            var needle = (search ? search.value : '').trim().toLowerCase();
            var first = null;

            Array.prototype.forEach.call(options, function (option) {
                // Matches word-starts only (so "ord" won't match "sword"); the
                // glyph itself also matches, for pasting one in directly.
                var hit = needle === ''
                    || option.dataset.icon === needle
                    || option.dataset.keywords.split(' ').some(function (word) {
                        return word.indexOf(needle) === 0;
                    });

                option.hidden = !hit;
                option.classList.remove('is-first');
                if (hit && !first) { first = option; }
            });

            Array.prototype.forEach.call(groups, function (group) {
                group.hidden = !group.querySelector('.icon-option:not([hidden])');
            });

            // Only worth pointing at the first result while a search narrows
            // things down; over the whole list it would just be the top left.
            if (first && needle !== '') { first.classList.add('is-first'); }
            if (empty) { empty.hidden = !!first; }
        };

        Array.prototype.forEach.call(options, function (option) {
            option.addEventListener('click', function () {
                choose(option.dataset.icon);
            });
        });

        document.querySelectorAll('[data-icon-trigger]').forEach(function (trigger) {
            trigger.addEventListener('click', function () {
                field = trigger.closest('[data-icon-field]');

                var value = field ? field.querySelector('[data-icon-value]') : null;
                var current = value ? value.value : '';

                // Marks the current value so the chooser opens showing it.
                Array.prototype.forEach.call(options, function (option) {
                    option.classList.toggle('is-current', current !== '' && option.dataset.icon === current);
                });

                if (search) { search.value = ''; }
                if (custom) { custom.value = current; }
                filter();

                if (typeof dialog.showModal === 'function') {
                    dialog.showModal();
                } else {
                    dialog.setAttribute('open', '');
                }

                var chosen = dialog.querySelector('.icon-option.is-current');
                if (chosen) { chosen.scrollIntoView({ block: 'center' }); }
                if (search) { search.focus(); }
            });
        });

        if (search) {
            search.addEventListener('input', filter);

            search.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter') { return; }

                // Enter would otherwise reach the form the field sits in.
                event.preventDefault();
                var first = dialog.querySelector('.icon-option:not([hidden])');
                if (first) { choose(first.dataset.icon); }
            });
        }

        dialog.querySelectorAll('[data-icon-custom-use]').forEach(function (button) {
            button.addEventListener('click', function () {
                choose(custom ? custom.value.trim() : '');
            });
        });

        if (custom) {
            custom.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter') { return; }
                event.preventDefault();
                choose(custom.value.trim());
            });
        }

        dialog.querySelectorAll('[data-icon-clear]').forEach(function (button) {
            button.addEventListener('click', function () { choose(''); });
        });
    }

    /* --- tag input ------------------------------------------------------- */

    function initTagInputs() {
        document.querySelectorAll('[data-tag-input]').forEach(function (box) {
            var entry = box.querySelector('.tag-entry');
            var menu = box.querySelector('[data-tag-menu]');
            var name = box.dataset.name;
            if (!entry) { return; }

            var allowCustom = box.dataset.allowCustom === '1';
            var suggestions = [];
            try { suggestions = JSON.parse(box.dataset.suggestions || '[]'); } catch (e) { /* ignore */ }

            var chosen = function () {
                return Array.prototype.map.call(
                    box.querySelectorAll('input[type=hidden]'),
                    function (input) { return input.value.toLowerCase(); }
                );
            };

            var addTag = function (value) {
                value = (value || '').trim();
                if (!value) { return; }
                if (!allowCustom && suggestions.indexOf(value) === -1) { return; }

                var already = chosen().indexOf(value.toLowerCase()) !== -1;
                if (already) { return; }

                var pill = document.createElement('span');
                pill.className = 'tag-pill';
                pill.textContent = value;

                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = name;
                hidden.value = value;

                var remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'tag-remove';
                remove.setAttribute('aria-label', t('Remove'));
                remove.textContent = '×';

                pill.appendChild(hidden);
                pill.appendChild(remove);
                box.insertBefore(pill, entry);
            };

            /* --- suggestion menu ------------------------------------------- */

            var hideMenu = function () {
                if (menu) { menu.hidden = true; menu.innerHTML = ''; }
            };

            var showMenu = function () {
                if (!menu) { return; }

                var typed = entry.value.trim();
                var taken = chosen();
                var matches = suggestions.filter(function (tag) {
                    return taken.indexOf(tag.toLowerCase()) === -1
                        && (typed === '' || tag.toLowerCase().indexOf(typed.toLowerCase()) !== -1);
                }).slice(0, 8);

                // An exact match needs no "new tag" row; anything else does.
                var exact = suggestions.some(function (t) { return t.toLowerCase() === typed.toLowerCase(); });
                var canCreate = allowCustom && typed !== '' && !exact
                    && taken.indexOf(typed.toLowerCase()) === -1;

                if (!matches.length && !canCreate) { hideMenu(); return; }

                menu.innerHTML = '';

                if (canCreate) {
                    var create = document.createElement('li');
                    create.className = 'tag-option tag-option--new';
                    create.textContent = t('＋ New tag “%s”', typed);
                    create.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        addTag(typed);
                        entry.value = '';
                        hideMenu();
                    });
                    menu.appendChild(create);
                }

                matches.forEach(function (tag) {
                    var option = document.createElement('li');
                    option.className = 'tag-option';
                    option.textContent = tag;
                    option.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        addTag(tag);
                        entry.value = '';
                        hideMenu();
                    });
                    menu.appendChild(option);
                });

                menu.hidden = false;
            };

            entry.addEventListener('input', showMenu);
            entry.addEventListener('focus', showMenu);
            entry.addEventListener('blur', function () {
                setTimeout(function () {
                    addTag(entry.value);
                    entry.value = '';
                    hideMenu();
                }, 120);
            });

            entry.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ',') {
                    event.preventDefault();
                    addTag(entry.value);
                    entry.value = '';
                    hideMenu();
                    return;
                }
                if (event.key === 'Escape') { hideMenu(); return; }
                if (event.key === 'Backspace' && entry.value === '') {
                    var pills = box.querySelectorAll('.tag-pill');
                    if (pills.length) { pills[pills.length - 1].remove(); }
                }
            });

            box.addEventListener('click', function (event) {
                if (event.target.classList.contains('tag-remove')) {
                    event.target.closest('.tag-pill').remove();
                    return;
                }
                if (event.target === box) { entry.focus(); }
            });

            /* --- the "in use" chips underneath the field -------------------- */

            var hint = box.parentNode.querySelector('.tag-cloud-hint');

            // Tags already on this entry aren't offered again; re-run whenever pills change.
            var syncChips = function () {
                if (!hint) { return; }
                var taken = chosen();
                var anyLeft = false;

                hint.querySelectorAll('[data-tag-add]').forEach(function (chip) {
                    var used = taken.indexOf(chip.dataset.tagAdd.toLowerCase()) !== -1;
                    chip.hidden = used;
                    anyLeft = anyLeft || !used;
                });

                hint.classList.toggle('is-empty', !anyLeft);
            };

            if (hint) {
                hint.addEventListener('click', function (event) {
                    var chip = event.target.closest('[data-tag-add]');
                    if (!chip) { return; }
                    event.preventDefault();
                    addTag(chip.dataset.tagAdd);
                });
            }

            // Every path that changes the pills ends up here.
            var observer = new MutationObserver(syncChips);
            observer.observe(box, { childList: true });
            syncChips();
        });
    }

    /* --- relation picker -------------------------------------------------- */

    function initRelationFields() {
        document.querySelectorAll('[data-relation]').forEach(function (field) {
            var search = field.querySelector('[data-relation-search]');
            var results = field.querySelector('[data-relation-results]');
            var selected = field.querySelector('[data-relation-selected]');
            if (!search || !results || !selected) { return; }

            var multiple = field.dataset.multiple === '1';
            var name = field.dataset.name;
            var typed = field.dataset.typed === '1';
            var fieldId = field.dataset.fieldId;
            var typeOptions = [];
            if (typed) {
                try { typeOptions = JSON.parse(field.dataset.relationTypes || '[]'); } catch (e) { typeOptions = []; }
            }
            var timer = null;
            var highlighted = -1;

            var chosenIds = function () {
                return Array.prototype.map.call(
                    selected.querySelectorAll('input[type=hidden]'),
                    function (input) { return input.value; }
                );
            };

            var addSelection = function (item) {
                if (chosenIds().indexOf(String(item.id)) !== -1) { return; }
                if (!multiple) { selected.innerHTML = ''; }

                var pill = document.createElement('span');
                pill.className = 'tag-pill';

                var icon = document.createElement('span');
                icon.className = 'chip-icon';
                icon.textContent = item.icon || '•';

                var label = document.createElement('span');
                label.textContent = item.title;

                pill.appendChild(icon);
                pill.appendChild(label);

                if (typed) {
                    var typeSelect = document.createElement('select');
                    typeSelect.className = 'relation-type-select';
                    typeSelect.name = 'relation_types[' + fieldId + '][]';

                    var blankOption = document.createElement('option');
                    blankOption.value = '';
                    blankOption.textContent = '— type —';
                    typeSelect.appendChild(blankOption);

                    typeOptions.forEach(function (typeName) {
                        var option = document.createElement('option');
                        option.value = typeName;
                        option.textContent = typeName;
                        typeSelect.appendChild(option);
                    });

                    pill.appendChild(typeSelect);
                }

                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = name;
                hidden.value = item.id;

                var remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'tag-remove';
                remove.setAttribute('aria-label', t('Remove'));
                remove.textContent = '×';

                pill.appendChild(hidden);
                pill.appendChild(remove);
                selected.appendChild(pill);

                search.value = '';
                hideResults();
            };

            var hideResults = function () {
                results.hidden = true;
                results.innerHTML = '';
                highlighted = -1;
            };

            var render = function (items) {
                results.innerHTML = '';
                if (!items.length) {
                    var empty = document.createElement('li');
                    empty.className = 'relation-empty';
                    empty.textContent = t('No entries match.');
                    results.appendChild(empty);
                    results.hidden = false;
                    return;
                }

                items.forEach(function (item) {
                    var option = document.createElement('li');
                    option.className = 'relation-result';
                    option.setAttribute('role', 'option');

                    var icon = document.createElement('span');
                    icon.className = 'chip-icon';
                    icon.textContent = item.icon || '•';

                    var title = document.createElement('span');
                    title.textContent = item.title;

                    var category = document.createElement('span');
                    category.className = 'relation-result-cat';
                    category.textContent = item.category;

                    option.appendChild(icon);
                    option.appendChild(title);
                    option.appendChild(category);
                    option.addEventListener('mousedown', function (event) {
                        event.preventDefault();
                        addSelection(item);
                    });

                    results.appendChild(option);
                });

                results.hidden = false;
                highlighted = -1;
            };

            var query = function () {
                var params = new URLSearchParams();
                params.set('q', search.value.trim());
                if (field.dataset.category) { params.set('category', field.dataset.category); }
                if (field.dataset.exclude) { params.set('exclude', field.dataset.exclude); }

                // What is already linked never appears in the list again.
                var taken = chosenIds();
                if (taken.length) { params.set('taken_entries', taken.join(',')); }

                fetch(field.dataset.endpoint + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json' }
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        // Filtered again here: the list may have been fetched
                        // before the most recent pick.
                        var picked = chosenIds();
                        render((data.results || []).filter(function (item) {
                            return picked.indexOf(String(item.id)) === -1;
                        }));
                    })
                    .catch(function () { hideResults(); });
            };

            search.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(query, 180);
            });

            search.addEventListener('focus', query);

            search.addEventListener('keydown', function (event) {
                var options = results.querySelectorAll('.relation-result');
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    if (!options.length) { return; }
                    event.preventDefault();
                    highlighted += (event.key === 'ArrowDown' ? 1 : -1);
                    if (highlighted < 0) { highlighted = options.length - 1; }
                    if (highlighted >= options.length) { highlighted = 0; }
                    options.forEach(function (option, index) {
                        option.classList.toggle('is-highlighted', index === highlighted);
                    });
                    options[highlighted].scrollIntoView({ block: 'nearest' });
                    return;
                }
                if (event.key === 'Enter') {
                    // Enter inside the picker chooses; it must not submit the form.
                    if (highlighted >= 0 && options[highlighted]) {
                        event.preventDefault();
                        options[highlighted].dispatchEvent(new MouseEvent('mousedown'));
                    } else if (!results.hidden) {
                        event.preventDefault();
                    }
                    return;
                }
                if (event.key === 'Escape') { hideResults(); }
            });

            search.addEventListener('blur', function () {
                setTimeout(hideResults, 120);
            });

            selected.addEventListener('click', function (event) {
                if (event.target.classList.contains('tag-remove')) {
                    event.target.closest('.tag-pill').remove();
                }
            });
        });
    }

    /* --- connection picker (the right-hand rail's "Add connection" modal) -- */

    /**
     * Searches entries and chapters. Picking one just fills the hidden fields
     * and shows the pick — nothing posts until Add is pressed.
     */
    function initConnectPicker() {
        document.querySelectorAll('[data-connect-picker]').forEach(function (dialog) {
            var search = dialog.querySelector('[data-connect-search]');
            var results = dialog.querySelector('[data-connect-results]');
            var toType = dialog.querySelector('[data-connect-to-type]');
            var toId = dialog.querySelector('[data-connect-to-id]');
            var picked = dialog.querySelector('[data-connect-picked]');
            var pickedIcon = dialog.querySelector('[data-connect-picked-icon]');
            var pickedTitle = dialog.querySelector('[data-connect-picked-title]');
            var pickedClear = dialog.querySelector('[data-connect-picked-clear]');
            var submitButton = dialog.querySelector('[data-connect-submit]');
            var note = dialog.querySelector('input[name="note"]');
            if (!search || !results || !toType || !toId || !picked
                || !pickedIcon || !pickedTitle || !pickedClear || !submitButton) {
                return;
            }

            var timer = null;
            var highlighted = -1;

            var hide = function () {
                results.hidden = true;
                results.innerHTML = '';
                highlighted = -1;
            };

            var unpick = function () {
                toType.value = '';
                toId.value = '';
                picked.hidden = true;
                search.hidden = false;
                search.value = '';
                submitButton.disabled = true;
            };

            var choose = function (item) {
                toType.value = item.type;
                toId.value = item.id;
                pickedIcon.textContent = item.icon || '•';
                pickedTitle.textContent = item.title;
                picked.hidden = false;
                search.hidden = true;
                hide();
                submitButton.disabled = false;
                if (note) { note.focus(); }
            };

            pickedClear.addEventListener('click', function () {
                unpick();
                search.focus();
            });

            // Reset on the trigger's click, not just the dialog's `close` event,
            // so a reused dialog never carries over the last pick or note.
            var resetAll = function () {
                unpick();
                hide();
                if (note) { note.value = ''; }
            };

            dialog.addEventListener('close', resetAll);

            // Captured at the document so this reset runs before the trigger opens the dialog.
            document.addEventListener('click', function (event) {
                if (event.target.closest('[data-open-dialog="' + dialog.id + '"]')) { resetAll(); }
            }, true);

            var render = function (items) {
                results.innerHTML = '';

                if (!items.length) {
                    var empty = document.createElement('li');
                    empty.className = 'relation-empty';
                    empty.textContent = t('Nothing matches.');
                    results.appendChild(empty);
                    results.hidden = false;
                    return;
                }

                items.forEach(function (item) {
                    var option = document.createElement('li');
                    option.className = 'relation-result';

                    var icon = document.createElement('span');
                    icon.className = 'chip-icon';
                    icon.textContent = item.icon || '•';

                    var title = document.createElement('span');
                    title.textContent = item.title;

                    var where = document.createElement('span');
                    where.className = 'relation-result-cat';
                    where.textContent = item.category;

                    option.appendChild(icon);
                    option.appendChild(title);
                    option.appendChild(where);
                    option.addEventListener('mousedown', function (event) {
                        event.preventDefault();
                        choose(item);
                    });

                    results.appendChild(option);
                });

                results.hidden = false;
                highlighted = -1;
            };

            var query = function () {
                var params = new URLSearchParams();
                params.set('q', search.value.trim());
                params.set('scope', 'all');
                params.set('exclude', dialog.dataset.exclude || '0');
                params.set('exclude_type', dialog.dataset.excludeType || 'entry');

                // Existing connections are excluded so the picker can't offer a link that would be refused.
                if (dialog.dataset.connectedEntries) {
                    params.set('taken_entries', dialog.dataset.connectedEntries);
                }
                if (dialog.dataset.connectedChapters) {
                    params.set('taken_chapters', dialog.dataset.connectedChapters);
                }

                fetch(dialog.dataset.endpoint + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json' }
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) { render(data.results || []); })
                    .catch(hide);
            };

            search.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(query, 180);
            });

            search.addEventListener('focus', query);

            search.addEventListener('keydown', function (event) {
                var options = results.querySelectorAll('.relation-result');

                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    if (!options.length) { return; }
                    event.preventDefault();
                    highlighted += (event.key === 'ArrowDown' ? 1 : -1);
                    if (highlighted < 0) { highlighted = options.length - 1; }
                    if (highlighted >= options.length) { highlighted = 0; }
                    options.forEach(function (option, index) {
                        option.classList.toggle('is-highlighted', index === highlighted);
                    });
                    options[highlighted].scrollIntoView({ block: 'nearest' });
                    return;
                }

                if (event.key === 'Enter') {
                    // Never submit an empty pick — that would post a blank target.
                    event.preventDefault();
                    if (highlighted >= 0 && options[highlighted]) {
                        options[highlighted].dispatchEvent(new MouseEvent('mousedown'));
                    }
                    return;
                }

                if (event.key === 'Escape') { hide(); }
            });

            search.addEventListener('blur', function () {
                setTimeout(hide, 140);
            });
        });
    }

    /* --- connection notes (the right-hand rail) ----------------------------- */

    /** A connection's note lives in a data attribute and renders via the shared hover-card tip. */
    function initConnectionNotes() {
        document.querySelectorAll('[data-rail-tip]').forEach(function (tip) {
            var rail = tip.closest('.rail');
            if (!rail) { return; }

            var targets = rail.querySelectorAll('.rail-link[data-rail-note]');
            if (!targets.length) { return; }

            targets.forEach(function (link) {
                link.addEventListener('pointerenter', function () {
                    tip.textContent = '';
                    var strong = document.createElement('strong');
                    strong.textContent = '“' + link.dataset.railNote + '”';
                    tip.appendChild(strong);
                    tip.hidden = false;
                });

                link.addEventListener('pointerleave', function () { tip.hidden = true; });
            });

            // Fixed to the viewport, not the rail, since the rail scrolls.
            rail.addEventListener('pointermove', function (event) {
                if (tip.hidden) { return; }

                var x = event.clientX + 14;
                var y = event.clientY + 14;
                var size = tip.getBoundingClientRect();

                if (x + size.width > window.innerWidth) { x = Math.max(0, event.clientX - size.width - 14); }
                if (y + size.height > window.innerHeight) { y = Math.max(0, event.clientY - size.height - 14); }

                tip.style.left = Math.round(x) + 'px';
                tip.style.top = Math.round(y) + 'px';
            });
        });
    }

    /* --- connection edit (the right-hand rail) ------------------------------ */

    /** One shared dialog for every connection row; its form is filled in from whichever pen icon was clicked. */
    function initConnectionEdit() {
        var dialog = document.getElementById('edit-connection-modal');
        if (!dialog) { return; }

        var form = dialog.querySelector('[data-edit-connection-form]');
        var titleLine = dialog.querySelector('[data-edit-connection-title]');
        var note = dialog.querySelector('#edit-connection-note');
        if (!form || !titleLine || !note) { return; }

        document.querySelectorAll('[data-rail-edit]').forEach(function (button) {
            button.addEventListener('click', function () {
                form.action = base + '/connections/' + button.dataset.connectionId + '/update';
                titleLine.textContent = button.dataset.connectionTitle || '';
                note.value = button.dataset.connectionNote || '';

                if (typeof dialog.showModal === 'function') { dialog.showModal(); }
                else { dialog.setAttribute('open', ''); }
                note.focus();
                note.select();
            });
        });
    }

    /* --- layout editor ---------------------------------------------------- */

    function initLayoutEditor() {
        var form = document.querySelector('[data-layout-editor]');
        if (!form) { return; }

        var list = form.querySelector('[data-field-rows]');
        var template = document.querySelector('[data-field-template]');
        var addButton = form.querySelector('[data-add-field]');
        var dirtyHint = form.querySelector('[data-dirty-hint]');
        var dirty = false;

        var markDirty = function () {
            dirty = true;
            if (dirtyHint) { dirtyHint.hidden = false; }
        };

        // Field names carry their row index, so they are rewritten after every
        // add, remove or reorder.
        var reindex = function () {
            list.querySelectorAll('[data-field-row]').forEach(function (row, index) {
                row.querySelectorAll('[data-name-template]').forEach(function (input) {
                    input.name = input.dataset.nameTemplate.replace('__i__', index);
                });
            });
        };

        var applyTypeVisibility = function (row) {
            var select = row.querySelector('[data-field-type]');
            if (!select) { return; }
            var type = select.value;

            row.querySelectorAll('[data-opt-for]').forEach(function (option) {
                var types = option.dataset.optFor.split(',');
                option.style.display = types.indexOf(type) === -1 ? 'none' : '';
            });
        };

        // The Types textarea only makes sense once "Enable relation typing" is ticked.
        var applyRelationTypedVisibility = function (row) {
            var toggle = row.querySelector('[data-relation-typed-toggle]');
            var typesOpt = row.querySelector('[data-relation-types-opt]');
            if (!toggle || !typesOpt) { return; }

            typesOpt.style.display = toggle.checked ? '' : 'none';
        };

        var wireRow = function (row) {
            applyTypeVisibility(row);
            applyRelationTypedVisibility(row);

            var typeSelect = row.querySelector('[data-field-type]');
            if (typeSelect) {
                typeSelect.addEventListener('change', function () {
                    applyTypeVisibility(row);
                    row.classList.add('is-open');
                    markDirty();
                });
            }

            var relationTypedToggle = row.querySelector('[data-relation-typed-toggle]');
            if (relationTypedToggle) {
                relationTypedToggle.addEventListener('change', function () {
                    applyRelationTypedVisibility(row);
                });
            }

            var toggle = row.querySelector('[data-toggle-row]');
            if (toggle) {
                toggle.addEventListener('click', function () {
                    row.classList.toggle('is-open');
                });
            }

            var remove = row.querySelector('[data-remove-row]');
            if (remove) {
                remove.addEventListener('click', function () {
                    var label = (row.querySelector('input[type=text]') || {}).value || t('this field');
                    var existing = (row.querySelector('input[type=hidden]') || {}).value;
                    // Removal archives rather than deletes, so the wording doesn't threaten data loss.
                    var warning = existing
                        ? t('Take “%s” out of this layout?\n\nNothing is deleted — whatever entries have stored in it is kept, and you can put the field back from the Fields page.', label)
                        : t('Remove “%s”?', label);
                    if (!window.confirm(warning)) { return; }
                    row.remove();
                    reindex();
                    markDirty();
                });
            }

            row.querySelectorAll('input, select, textarea').forEach(function (input) {
                input.addEventListener('input', markDirty);
                input.addEventListener('change', markDirty);
            });

            makeDraggable(row, list, function () {
                reindex();
                markDirty();
            });
        };

        list.querySelectorAll('[data-field-row]').forEach(wireRow);
        reindex();

        if (addButton && template) {
            addButton.addEventListener('click', function () {
                var fragment = template.content.cloneNode(true);
                var row = fragment.querySelector('[data-field-row]');
                list.appendChild(fragment);

                var hint = form.querySelector('[data-empty-hint]');
                if (hint) { hint.remove(); }

                wireRow(row);
                reindex();
                markDirty();

                var label = row.querySelector('input[type=text]');
                if (label) { label.focus(); }
            });
        }

        form.addEventListener('submit', function () {
            dirty = false;
            reindex();
        });

        window.addEventListener('beforeunload', function (event) {
            if (!dirty) { return; }
            event.preventDefault();
            event.returnValue = '';
        });
    }

    /* --- archive reordering ----------------------------------------------- */

    function initArchiveSorting() {
        var list = document.querySelector('[data-sortable-archives]');
        if (!list) { return; }

        var persist = function () {
            var body = new URLSearchParams();
            body.set('_token', csrf);
            list.querySelectorAll('[data-sortable-item]').forEach(function (item) {
                body.append('order[]', item.dataset.id);
            });

            fetch(list.dataset.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            }).catch(function () { /* order is cosmetic; a failure can wait */ });
        };

        list.querySelectorAll('[data-sortable-item]').forEach(function (item) {
            makeDraggable(item, list, persist);
        });
    }

    /* --- shared drag-and-drop --------------------------------------------- */

    /**
     * Rows are only draggable while their handle is held, so text inside them
     * stays selectable.
     */
    function makeDraggable(row, list, onDrop) {
        var handle = row.querySelector('[data-drag-handle]');
        if (!handle) { return; }

        handle.addEventListener('mousedown', function () { row.draggable = true; });
        document.addEventListener('mouseup', function () { row.draggable = false; });

        row.addEventListener('dragstart', function (event) {
            row.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            // Firefox refuses to start a drag without payload.
            event.dataTransfer.setData('text/plain', '');
        });

        row.addEventListener('dragend', function () {
            row.classList.remove('is-dragging');
            row.draggable = false;
            list.querySelectorAll('.is-drop-target').forEach(function (element) {
                element.classList.remove('is-drop-target');
            });
            onDrop();
        });

        row.addEventListener('dragover', function (event) {
            var dragging = list.querySelector('.is-dragging');
            if (!dragging || dragging === row) { return; }

            // Rows with a parent may only move among their own siblings, not re-parent.
            if (row.dataset.parent !== undefined
                && row.dataset.parent !== dragging.dataset.parent) {
                return;
            }

            event.preventDefault();

            var box = row.getBoundingClientRect();
            var below = (event.clientY - box.top) > box.height / 2;
            row.parentNode.insertBefore(dragging, below ? row.nextSibling : row);
        });
    }

    /* --- layout switcher on the entry form -------------------------------- */

    function initLayoutSwitch() {
        var select = document.querySelector('[data-layout-switch]');
        if (!select) { return; }

        var initial = select.value;

        select.addEventListener('change', function () {
            if (select.dataset.warn === '1') {
                var ok = window.confirm(
                    t('Show this entry with a different layout? Unsaved changes on this page will be lost.')
                );
                if (!ok) {
                    select.value = initial;
                    return;
                }
            }
            window.location.href = select.dataset.base
                + (select.dataset.base.indexOf('?') === -1 ? '?' : '&')
                + 'layout=' + encodeURIComponent(select.value);
        });
    }

    /* --- world map ------------------------------------------------------- */

    // Cutouts render at the map's full extent, then crop client-side once the
    // shape can be measured — avoids parsing SVG path data in PHP.
    var CUT_MIN_VIEW_W = 1200;
    var CUT_MIN_VIEW_H = 900;
    var CUT_MIN_HEIGHT = 150;
    var CUT_MAX_HEIGHT = 460;

    function cropToShape(svg, padding) {
        var shape = svg.querySelector('.mapcut-shape');
        if (!shape || typeof shape.getBBox !== 'function') { return null; }

        var box;
        try { box = shape.getBBox(); } catch (e) { return null; }
        if (!box || !box.width || !box.height) { return null; }

        // Padding only matters once the shape outgrows the minimum window.
        var pad = Math.max(box.width, box.height) * (padding === undefined ? 0.12 : padding);

        var w = Math.max(CUT_MIN_VIEW_W, box.width + pad * 2);
        var h = Math.max(CUT_MIN_VIEW_H, box.height + pad * 2);

        // Held to the map's own proportions so the picture isn't squeezed.
        var ratio = CUT_MIN_VIEW_W / CUT_MIN_VIEW_H;
        if (w / h > ratio) { h = w / ratio; } else { w = h * ratio; }

        var view = {
            x: Math.round(box.x + box.width / 2 - w / 2),
            y: Math.round(box.y + box.height / 2 - h / 2),
            w: Math.round(w),
            h: Math.round(h)
        };

        // Keeps the frame on the map; running off the edge would pad with empty page, not more world.
        var world = (svg.getAttribute('viewBox') || '').split(/[\s,]+/).map(Number);
        if (world.length === 4 && world[2] > 0) {
            view.w = Math.min(view.w, world[2]);
            view.h = Math.min(view.h, world[3]);
            view.x = Math.max(world[0], Math.min(view.x, world[0] + world[2] - view.w));
            view.y = Math.max(world[1], Math.min(view.y, world[1] + world[3] - view.h));
        }

        svg.setAttribute('viewBox', [view.x, view.y, view.w, view.h].join(' '));

        // Height follows the shape's own proportions so nothing is cropped or
        // letterboxed; preserveAspectRatio is "meet", so this only needs to be close.
        var width = svg.clientWidth || svg.getBoundingClientRect().width;
        if (width) {
            var wanted = width * (view.h / view.w);
            svg.style.height =
                Math.round(Math.max(CUT_MIN_HEIGHT, Math.min(CUT_MAX_HEIGHT, wanted))) + 'px';
        }

        return { shape: shape, view: view };
    }

    /** Points inside the traced shape, shown on the entry's cutout — isPointInFill(), not a bounding box. */
    function placeCutoutPoints(wrap, cropped) {
        var overlay = wrap.querySelector('[data-mapcut-points]');
        if (!overlay || !cropped) { return; }

        var shape = cropped.shape;
        var svg = shape.ownerSVGElement;
        if (!svg || typeof shape.isPointInFill !== 'function') { overlay.remove(); return; }

        var kept = 0;

        overlay.querySelectorAll('.mapcut-point').forEach(function (link) {
            var pt;
            try {
                pt = svg.createSVGPoint();
                pt.x = parseFloat(link.dataset.x);
                pt.y = parseFloat(link.dataset.y);
            } catch (e) {
                link.remove();
                return;
            }

            var inside = false;
            try { inside = shape.isPointInFill(pt); } catch (e) { inside = false; }

            if (inside) {
                kept++;
            } else {
                link.remove();
            }
        });

        if (!kept) {
            overlay.remove();
            return;
        }

        var v = cropped.view;
        overlay.setAttribute('viewBox', [v.x, v.y, v.w, v.h].join(' '));
        overlay.style.height = wrap.querySelector('.mapcut-svg').style.height;
    }

    function initMapCutouts() {
        document.querySelectorAll('[data-mapcut]').forEach(function (svg) {
            var cropped = cropToShape(svg);
            var wrap = svg.closest('.mapcut-wrap');
            if (wrap) { placeCutoutPoints(wrap, cropped); }
        });

        // Reuses the world map's hover card, so it appears instantly rather
        // than after the browser's tooltip delay.
        document.querySelectorAll('.mapcut-wrap').forEach(function (wrap) {
            var targets = wrap.querySelectorAll('[data-tip-title]');
            if (!targets.length) { return; }

            var tip = document.createElement('div');
            tip.className = 'worldmap-tip';
            tip.hidden = true;
            wrap.appendChild(tip);

            bindHoverTips(wrap, tip, targets);
        });
    }

    /**
     * Small card naming whatever the pointer is over, following it inside `surface`.
     * Uses textContent, not innerHTML, since titles are user-written.
     */
    function bindHoverTips(surface, tip, targets) {
        if (!tip || !targets || !targets.length) { return; }

        targets.forEach(function (el) {
            el.addEventListener('pointerenter', function () {
                var icon = el.dataset.tipIcon || el.dataset.icon || '';
                var title = el.dataset.tipTitle || el.dataset.title || '';
                var sub = el.dataset.tipSub || el.dataset.archive || '';

                tip.textContent = '';

                var strong = document.createElement('strong');
                strong.textContent = (icon ? icon + ' ' : '') + title;
                tip.appendChild(strong);

                if (sub) {
                    var span = document.createElement('span');
                    span.textContent = sub;
                    tip.appendChild(span);
                }

                tip.hidden = false;
            });

            el.addEventListener('pointerleave', function () { tip.hidden = true; });
        });

        surface.addEventListener('pointermove', function (event) {
            if (tip.hidden) { return; }

            var rect = surface.getBoundingClientRect();
            var x = event.clientX - rect.left + 14;
            var y = event.clientY - rect.top + 14;

            // Flips to the other side of the pointer when the card would hang off the edge.
            var size = tip.getBoundingClientRect();
            if (x + size.width > rect.width) { x = Math.max(0, x - size.width - 28); }
            if (y + size.height > rect.height) { y = Math.max(0, y - size.height - 28); }

            tip.style.left = Math.round(x) + 'px';
            tip.style.top = Math.round(y) + 'px';
        });
    }

    /* --- map area field -------------------------------------------------- */

    // The shape is drawn on the map page; this only lets an entry release it.
    function initMapAreaFields() {
        document.querySelectorAll('[data-maparea-ro]').forEach(function (wrapper) {
            var store = wrapper.querySelector('[data-maparea-value]');
            var clear = wrapper.querySelector('[data-maparea-clear]');
            if (!store || !clear) { return; }

            clear.addEventListener('click', function () {
                if (!window.confirm(t('Remove this shape from the map?'))) { return; }
                store.value = '';
                var svg = wrapper.querySelector('svg');
                if (svg) { svg.remove(); }
                var bar = wrapper.querySelector('.maparea-ro-bar');
                if (bar) {
                    // %s is markup, not escaped — intentional.
                    bar.innerHTML = '<span class="field-help">' + t('Shape removed. Save the entry to confirm.') + '</span>';
                }
            });
        });
    }

    /* --- the world map --------------------------------------------------- */

    function initWorldMap() {
        var root = document.querySelector('[data-worldmap]');
        if (!root) { return; }

        var svg = root.querySelector('[data-worldmap-svg]');
        var stage = root.querySelector('[data-worldmap-stage]');
        var tip = root.querySelector('[data-worldmap-tip]');
        if (!svg || !stage) { return; }

        var width = parseInt(root.dataset.width, 10) || 4000;
        var height = parseInt(root.dataset.height, 10) || 3000;
        var home = { x: 0, y: 0, w: width, h: height };
        var view = { x: home.x, y: home.y, w: home.w, h: home.h };

        var apply = function () {
            svg.setAttribute('viewBox', [view.x, view.y, view.w, view.h].join(' '));
        };

        // Zoom is bounded — past the whole map, or in past a few hundred units, isn't useful.
        var MIN_W = width / 40;
        var MAX_W = width * 1.6;

        var zoomTo = function (w, originX, originY) {
            w = Math.max(MIN_W, Math.min(MAX_W, w));
            var ratio = w / view.w;
            var h = view.h * ratio;

            // Keep the point under the cursor where it is.
            view.x = originX - (originX - view.x) * ratio;
            view.y = originY - (originY - view.y) * ratio;
            view.w = w;
            view.h = h;
            apply();
        };

        /** Page pixels to map coordinates. */
        var toMap = function (event) {
            var rect = svg.getBoundingClientRect();
            // The SVG letterboxes itself (xMidYMid meet), so the drawn area is
            // not always the whole element.
            var scale = Math.min(rect.width / view.w, rect.height / view.h);
            var drawnW = view.w * scale;
            var drawnH = view.h * scale;
            var offsetX = rect.left + (rect.width - drawnW) / 2;
            var offsetY = rect.top + (rect.height - drawnH) / 2;

            return {
                x: view.x + (event.clientX - offsetX) / scale,
                y: view.y + (event.clientY - offsetY) / scale,
                scale: scale
            };
        };

        // Dragging a picture is a native browser gesture; it fought the pan.
        stage.addEventListener('dragstart', function (event) { event.preventDefault(); });

        stage.addEventListener('wheel', function (event) {
            event.preventDefault();
            var at = toMap(event);
            zoomTo(view.w * (event.deltaY > 0 ? 1.15 : 0.87), at.x, at.y);
        }, { passive: false });

        var dragging = false;
        var last = null;

        stage.addEventListener('pointerdown', function (event) {
            if (event.button !== 0) { return; }
            dragging = true;
            last = toMap(event);
            stage.classList.add('is-dragging');
            stage.setPointerCapture(event.pointerId);
        });

        stage.addEventListener('pointermove', function (event) {
            if (!dragging || !last) { return; }
            var scale = toMap(event).scale;
            view.x -= (event.movementX || 0) / scale;
            view.y -= (event.movementY || 0) / scale;
            apply();
        });

        var endDrag = function () {
            dragging = false;
            last = null;
            stage.classList.remove('is-dragging');
        };
        stage.addEventListener('pointerup', endDrag);
        stage.addEventListener('pointercancel', endDrag);

        // A drag that moved should not also follow the region's link.
        var pressedAt = null;
        stage.addEventListener('pointerdown', function (event) {
            pressedAt = { x: event.clientX, y: event.clientY };
        });
        stage.addEventListener('click', function (event) {
            if (!pressedAt) { return; }
            var moved = Math.abs(event.clientX - pressedAt.x) + Math.abs(event.clientY - pressedAt.y);
            if (moved > 6) { event.preventDefault(); }
        }, true);

        root.querySelectorAll('[data-worldmap-reset]').forEach(function (button) {
            button.addEventListener('click', function () {
                view = { x: home.x, y: home.y, w: home.w, h: home.h };
                apply();
            });
        });

        /* --- layers --- */

        var groups = svg.querySelectorAll('.worldmap-layer-group');
        var buttons = root.querySelectorAll('[data-worldmap-layer]');

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                var wanted = button.dataset.worldmapLayer;

                groups.forEach(function (group) {
                    if (group.dataset.layer === wanted) {
                        group.removeAttribute('hidden');
                    } else {
                        group.setAttribute('hidden', '');
                    }
                });

                buttons.forEach(function (other) {
                    var on = other === button;
                    other.classList.toggle('is-active', on);
                    other.setAttribute('aria-selected', on ? 'true' : 'false');
                });

                // The single "Remove map" button always targets whichever
                // layer is currently open.
                var deleteSlug = root.querySelector('[data-worldmap-delete-slug]');
                if (deleteSlug) {
                    deleteSlug.value = wanted;
                }

                // Keeps the reader's place: layers share a coordinate space, so the view carries over.
                apply();
            });
        });

        /* --- hover label --- */

        // Points carry the same data as regions and deserve the same label.
        bindHoverTips(stage, tip, svg.querySelectorAll('.worldmap-region, .worldmap-point, .worldmap-path'));

        /* --- arriving from a cutout --- */

        var focus = parseInt(root.dataset.focus, 10) || 0;
        if (focus) {
            var target = svg.querySelector(
                '.worldmap-region[data-entry="' + focus + '"] path, ' +
                '.worldmap-path[data-entry="' + focus + '"] .worldmap-path-line'
            );
            if (target && typeof target.getBBox === 'function') {
                try {
                    var box = target.getBBox();
                    var pad = Math.max(box.width, box.height) * 0.6;
                    view = {
                        x: box.x - pad,
                        y: box.y - pad,
                        w: box.width + pad * 2,
                        h: box.height + pad * 2
                    };
                    apply();
                } catch (e) { /* not rendered yet; the full view will do */ }
            }
        }

        apply();

        // Tracing shares this map's coordinate maths rather than working it out
        // a second time.
        var api = {
            root: root,
            svg: svg,
            stage: stage,
            toMap: toMap,
            base: root.dataset.base || ''
        };

        initMapTracing(api);
        initMapPoints(api);
        initMapList(api);
    }

    /* --- tracing a shape on the map -------------------------------------- */

    function initMapTracing(map) {
        var root = map.root;
        var svg = map.svg;
        var stage = map.stage;

        var startBtn = root.querySelector('[data-trace-start]');
        var startPathBtn = root.querySelector('[data-tracepath-start]');
        var controls = root.querySelector('[data-trace-controls]');
        var panel = root.querySelector('[data-trace-assign]');
        if (!startBtn || !controls || !panel) { return; }

        // Only wording, minimum point count, and ring-closing differ between
        // tracing a shape and a path.
        var KIND_INFO = {
            area: {
                heading: t('Assign this shape'),
                save: t('Save shape'),
                hint: t('Click along the border, choose the entry, then save.'),
                minPoints: 3,
                tooFew: t('Draw at least three points on the map first.'),
                choose: t('Choose the entry this shape belongs to.'),
                empty: t('Nothing here can hold a shape yet. Add a Map area field to a layout first.')
            },
            path: {
                heading: t('Assign this path'),
                save: t('Save path'),
                hint: t('Click along the path, choose the entry, then save.'),
                minPoints: 2,
                tooFew: t('Draw at least two points on the map first.'),
                choose: t('Choose the entry this path belongs to.'),
                empty: t('Nothing here can hold a path yet. Add a Map path field to a layout first.')
            },
            point: {
                heading: t('Name this point'),
                save: t('Save point'),
                hint: t('Click the spot, choose the entry, then save.'),
                choose: t('Choose the entry this point belongs to.'),
                empty: t('Nothing here can hold a point yet. Add a Map point field to a layout first.')
            }
        };

        var layerGroup = svg.querySelector('[data-trace-layer]');
        var pathEl = svg.querySelector('[data-trace-path]');
        var dotsEl = svg.querySelector('[data-trace-points]');
        var countEl = root.querySelector('[data-trace-count]');
        var saveBtn = root.querySelector('[data-trace-save]');
        var search = root.querySelector('[data-trace-search]');
        var results = root.querySelector('[data-trace-results]');
        var note = root.querySelector('[data-trace-note]');
        var layerName = root.querySelector('[data-trace-layer-name]');

        var points = [];
        // Picking an entry only selects it; the shape saves on Save, so shape
        // and entry can be chosen in either order.
        var chosen = null;
        var picker = root.querySelector('[data-symbol-picker]');
        var symbol = 'city';

        if (picker) {
            picker.addEventListener('click', function (event) {
                var button = event.target.closest('[data-symbol]');
                if (!button) { return; }
                symbol = button.dataset.symbol;
                picker.querySelectorAll('[data-symbol]').forEach(function (other) {
                    other.classList.toggle('is-active', other === button);
                });

                // Show the choice where the point will land.
                var ghost = root.querySelector('[data-point-ghost]');
                if (ghost) { ghost.textContent = button.textContent.trim(); }
            });
        }

        var toPath = function (list, close) {
            if (!list.length) { return ''; }
            var d = 'M ' + list.map(function (p) { return p.x + ' ' + p.y; }).join(' L ');
            return close ? d + ' Z' : d;
        };

        var draw = function () {
            // Drawn closed once there are 3+ points, so what's shown matches
            // what saves. Paths never close.
            var closes = panel.dataset.kind === 'area' && points.length >= 3;
            pathEl.classList.toggle('trace-path--line', panel.dataset.kind === 'path');
            pathEl.setAttribute('d', toPath(points, closes));
            var dots = '';
            points.forEach(function (p) {
                // Radius is in map units — small at full view, but big enough to see and aim at.
                dots += '<circle class="trace-dot" cx="' + p.x + '" cy="' + p.y + '" r="16"></circle>';
            });
            dotsEl.innerHTML = dots;
            if (countEl) { countEl.textContent = String(points.length); }
        };

        var setTracing = function (on) {
            root.classList.toggle('is-tracing', on);
            controls.hidden = !on;
            startBtn.classList.toggle('is-active', on && panel.dataset.kind === 'area');
            if (startPathBtn) {
                startPathBtn.classList.toggle('is-active', on && panel.dataset.kind === 'path');
            }
            // SVG elements have no .hidden property; only the attribute works.
            if (on) { layerGroup.removeAttribute('hidden'); }
            else { layerGroup.setAttribute('hidden', ''); }
            // Regions and paths must not swallow clicks meant for the canvas.
            svg.querySelectorAll('.worldmap-region, .worldmap-path').forEach(function (region) {
                region.style.pointerEvents = on ? 'none' : '';
            });
        };

        var chosenLabel = root.querySelector('[data-trace-chosen]');

        var mark = function () {
            if (!results) { return; }
            results.querySelectorAll('.trace-result').forEach(function (row) {
                row.classList.toggle('is-selected', row.dataset.id === chosen);
            });
        };

        var select = function (id, title) {
            chosen = id;
            mark();

            // Named in the footer as well, so the choice survives scrolling the
            // list or narrowing the search.
            if (chosenLabel) {
                chosenLabel.hidden = !title;
                chosenLabel.textContent = title ? '→ ' + title : '';
            }
        };

        var reset = function () {
            points = [];
            select(null, '');
            draw();
            setTracing(false);
            panel.hidden = true;
            if (results) { results.innerHTML = ''; }
            if (search) { search.value = ''; }
            if (note) { note.textContent = ''; }
        };

        var startTrace = function (kind) {
            root.dispatchEvent(new CustomEvent('map:reset-modes', { detail: 'trace' }));
            points = [];
            select(null, '');

            // Opens with the mode immediately, so the page doesn't shift twice (draw, then name).
            panel.hidden = false;
            panel.dataset.kind = kind;
            draw();

            var undo = root.querySelector('[data-trace-undo]');
            if (undo) { undo.disabled = false; }
            setTracing(true);

            var info = KIND_INFO[kind];
            if (picker) { picker.hidden = true; }
            var heading = root.querySelector('[data-assign-title]');
            if (heading) { heading.textContent = info.heading; }
            if (saveBtn) { saveBtn.textContent = info.save; }
            if (note) { note.textContent = info.hint; }
            if (search) { search.value = ''; }
            load('');
        };

        startBtn.addEventListener('click', function () { startTrace('area'); });
        if (startPathBtn) {
            startPathBtn.addEventListener('click', function () { startTrace('path'); });
        }

        // Placing a point uses the same panel, so tracing steps aside.
        root.addEventListener('map:reset-modes', function (event) {
            if (event.detail !== 'trace') {
                points = [];
                select(null, '');
                draw();
                setTracing(false);
            }
        });

        // Placing a point uses the same panel; it opens with that mode.
        root.addEventListener('map:assign-open', function (event) {
            var kind = event.detail.kind;
            var info = KIND_INFO[kind] || KIND_INFO.area;
            panel.dataset.kind = kind;
            select(null, '');
            if (picker) { picker.hidden = kind !== 'point'; }
            var heading = root.querySelector('[data-assign-title]');
            if (heading) { heading.textContent = info.heading; }
            if (saveBtn) { saveBtn.textContent = info.save; }
            if (search) { search.value = ''; search.focus(); }
            if (note) { note.textContent = kind === 'point' ? info.hint : ''; }
            load('');
        });

        root.querySelector('[data-trace-discard]').addEventListener('click', reset);

        root.querySelector('[data-trace-undo]').addEventListener('click', function () {
            points.pop();
            draw();
        });

        // A click that was really a drag must not drop a point.
        var down = null;
        stage.addEventListener('pointerdown', function (event) {
            down = { x: event.clientX, y: event.clientY };
        });

        stage.addEventListener('click', function (event) {
            if (!root.classList.contains('is-tracing')) { return; }
            if (down && Math.abs(event.clientX - down.x) + Math.abs(event.clientY - down.y) > 6) { return; }

            var at = map.toMap(event);
            points.push({ x: Math.round(at.x), y: Math.round(at.y) });
            draw();

            if (layerName) {
                var active = root.querySelector('[data-worldmap-layer].is-active');
                layerName.textContent = active
                    ? 'on ' + active.childNodes[0].nodeValue.trim()
                    : '';
            }
        });

        // Double-click signals the last point; drops the duplicate the second click made.
        stage.addEventListener('dblclick', function (event) {
            if (!root.classList.contains('is-tracing')) { return; }
            event.preventDefault();
            points.pop();
            draw();
            if (search) { search.focus(); }
        });

        document.addEventListener('keydown', function (event) {
            if (panel.hidden) { return; }
            if (event.key === 'Escape') { reset(); }
            if (event.key === 'z' && (event.ctrlKey || event.metaKey)
                && root.classList.contains('is-tracing')) {
                event.preventDefault();
                points.pop();
                draw();
            }
        });

        /* --- picking the entry --- */

        var load = function (query) {
            if (!results) { return; }

            var kind = panel.dataset.kind || 'area';

            fetch(map.base + '/map/lookup?kind=' + kind + '&q=' + encodeURIComponent(query), {
                headers: { 'Accept': 'application/json' }
            }).then(function (r) {
                return r.json();
            }).then(function (data) {
                var rows = data.results || [];
                if (!rows.length) {
                    // KIND_INFO[kind].empty is markup-free plain text, but built via +; not escaped since it never contains user data.
                    results.innerHTML = '<li class="trace-empty">' + KIND_INFO[kind].empty + '</li>';
                    return;
                }
                var html = '';
                rows.forEach(function (row) {
                    html += '<li><button type="button" class="trace-result" data-id="' + row.id + '">' +
                        '<span class="trace-result-icon">' + row.icon + '</span>' +
                        '<span class="trace-result-title">' + row.title + '</span>' +
                        '<span class="trace-result-archive">' + row.archive + '</span>' +
                        (row.has_shape ? '<span class="trace-result-warn">'
                            + (kind === 'path' ? t('replaces its path') : kind === 'point' ? t('replaces its point') : t('replaces its shape'))
                            + '</span>' : '') +
                        '</button></li>';
                });
                results.innerHTML = html;
                mark();
            }).catch(function () {
                results.innerHTML = '<li class="trace-empty">' + t('Could not reach the server.') + '</li>';
            });
        };

        if (search) {
            var timer = null;
            search.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () { load(search.value.trim()); }, 160);
            });
        }

        // Clicking a result only marks it. Nothing is written until Save.
        results.addEventListener('click', function (event) {
            var button = event.target.closest('.trace-result');
            if (!button) { return; }

            select(button.dataset.id,
                (button.querySelector('.trace-result-title') || {}).textContent || '');
            if (note) { note.textContent = ''; }
        });

        var save = function () {
            var kind = panel.dataset.kind || 'area';
            var info = KIND_INFO[kind];

            if ((kind === 'area' || kind === 'path') && points.length < info.minPoints) {
                if (note) { note.textContent = info.tooFew; }
                return;
            }

            if (kind === 'point') {
                // The ghost sits off-canvas until a spot is chosen.
                var ghost = root.querySelector('[data-point-ghost]');
                var ghostX = ghost ? parseFloat(ghost.getAttribute('x')) : -999;

                if (!(ghostX >= 0)) {
                    if (note) { note.textContent = t('Click the spot on the map first.'); }
                    return;
                }
            }

            if (!chosen) {
                if (note) { note.textContent = info.choose; }
                if (search) { search.focus(); }
                return;
            }

            var active = root.querySelector('[data-worldmap-layer].is-active');
            var layer = active ? active.dataset.worldmapLayer : 'surface';

            var body = new FormData();
            body.append('entry', chosen);
            body.append('layer', layer);
            body.append('_token', csrf);

            if (kind === 'point') {
                // The point module knows where the pin went.
                body.append('symbol', symbol);
                root.dispatchEvent(new CustomEvent('map:assign-payload', {
                    detail: { kind: 'point', body: body }
                }));
            } else if (kind === 'path') {
                body.append('kind', 'path');
                // A path never closes, whatever the save button is called.
                body.append('path', toPath(points, false));
            } else {
                body.append('kind', 'area');
                // Closing the ring is the save's job now, not a separate click.
                body.append('path', toPath(points, true));
            }

            if (note) { note.textContent = t('Saving...'); }
            if (saveBtn) { saveBtn.disabled = true; }

            fetch(map.base + '/map/assign', { method: 'POST', body: body })
                .then(function (r) {
                    return r.json().then(function (d) { return { ok: r.ok, data: d }; });
                })
                .then(function (res) {
                    if (!res.ok || !res.data.ok) {
                        if (note) { note.textContent = res.data.error || t('That did not save.'); }
                        if (saveBtn) { saveBtn.disabled = false; }
                        return;
                    }
                    // Reload so the new region joins the map properly.
                    window.location.href = map.base + '/map?layer=' + layer + '&focus=' + chosen;
                })
                .catch(function () {
                    if (note) { note.textContent = t('Could not reach the server.'); }
                    if (saveBtn) { saveBtn.disabled = false; }
                });
        };

        if (saveBtn) { saveBtn.addEventListener('click', save); }

        // Enter saves, from the search box or anywhere else in the panel.
        document.addEventListener('keydown', function (event) {
            if (panel.hidden || event.key !== 'Enter') { return; }
            event.preventDefault();
            save();
        });
    }

    /* --- placing a point ------------------------------------------------- */

    function initMapPoints(map) {
        var root = map.root;
        var svg = map.svg;
        var stage = map.stage;

        var startBtn = root.querySelector('[data-point-start]');
        var controls = root.querySelector('[data-point-controls]');
        var panel = root.querySelector('[data-trace-assign]');
        var hint = root.querySelector('[data-point-hint]');
        if (!startBtn || !controls || !panel) { return; }

        var group = svg.querySelector('[data-point-layer]');
        var ghost = svg.querySelector('[data-point-ghost]');
        var placed = null;

        var setPlacing = function (on) {
            root.classList.toggle('is-placing', on);
            controls.hidden = !on;
            startBtn.classList.toggle('is-active', on);
            // Same as the trace layer: attribute, not property.
            if (on) { group.removeAttribute('hidden'); }
            else { group.setAttribute('hidden', ''); }
            svg.querySelectorAll('.worldmap-region, .worldmap-point, .worldmap-path').forEach(function (el) {
                el.style.pointerEvents = on ? 'none' : '';
            });
        };

        var reset = function () {
            placed = null;
            setPlacing(false);
            ghost.setAttribute('x', '-999');
            ghost.setAttribute('y', '-999');
        };

        startBtn.addEventListener('click', function () {
            // Placing and tracing are different modes; leaving one enters the other cleanly.
            root.dispatchEvent(new CustomEvent('map:reset-modes'));
            placed = null;
            setPlacing(true);

            // Open before the map is clicked, so placing the point does not
            // shift the page a second time.
            panel.hidden = false;
            panel.dataset.kind = 'point';
            root.dispatchEvent(new CustomEvent('map:assign-open', { detail: { kind: 'point' } }));
            if (hint) { hint.textContent = t('Click the spot on the map.'); }
        });

        // The panel Cancel serves both modes now.
        root.querySelector('[data-trace-discard]').addEventListener('click', function () {
            reset();
            panel.hidden = true;
        });

        root.addEventListener('map:reset-modes', function (event) {
            if (event.detail !== 'point') { reset(); }
        });

        var down = null;
        stage.addEventListener('pointerdown', function (event) {
            down = { x: event.clientX, y: event.clientY };
        });

        stage.addEventListener('click', function (event) {
            if (!root.classList.contains('is-placing')) { return; }
            if (down && Math.abs(event.clientX - down.x) + Math.abs(event.clientY - down.y) > 6) { return; }

            var at = map.toMap(event);
            placed = { x: Math.round(at.x * 10) / 10, y: Math.round(at.y * 10) / 10 };
            ghost.setAttribute('x', placed.x);
            ghost.setAttribute('y', placed.y);

            if (hint) {
                hint.textContent = t('Placed at %d, %d. Click again to move it.',
                    Math.round(placed.x), Math.round(placed.y));
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && root.classList.contains('is-placing')) {
                reset();
                panel.hidden = true;
            }
        });

        // The assign panel asks for the payload when a result is clicked.
        root.addEventListener('map:assign-payload', function (event) {
            if (event.detail.kind !== 'point' || !placed) { return; }
            event.detail.body.append('kind', 'point');
            event.detail.body.append('x', placed.x);
            event.detail.body.append('y', placed.y);
        });
    }

    /* --- the on-this-layer list ------------------------------------------ */

    function initMapList(map) {
        var root = map.root;
        var svg = map.svg;

        var list = root.querySelector('[data-worldmap-list]');
        if (!list) { return; }

        var items = list.querySelector('[data-list-items]');
        var countEl = list.querySelector('[data-list-count]');

        var activeLayer = function () {
            var group = svg.querySelector('.worldmap-layer-group:not([hidden])');
            return group ? group.dataset.layer : null;
        };

        var build = function () {
            var layer = activeLayer();
            if (!layer) { return; }

            var group = svg.querySelector('.worldmap-layer-group[data-layer="' + layer + '"]');
            var things = group.querySelectorAll('.worldmap-region, .worldmap-point, .worldmap-path');

            if (countEl) { countEl.textContent = String(things.length); }

            if (!things.length) {
                items.innerHTML = '<li class="trace-empty">' + t('Nothing on this layer yet.') + '</li>';
                return;
            }

            var html = '';
            things.forEach(function (el, index) {
                var kind = el.classList.contains('worldmap-point') ? '◎'
                    : el.classList.contains('worldmap-path') ? '〰'
                    : '▱';
                el.dataset.listIndex = String(index);
                html += '<li class="worldmap-list-item">' +
                    '<label>' +
                        '<input type="checkbox" data-list-toggle="' + index + '"' +
                            (el.style.display === 'none' ? '' : ' checked') + '>' +
                        '<span class="worldmap-list-kind">' + kind + '</span>' +
                        '<span class="worldmap-list-title">' + el.dataset.title + '</span>' +
                        '<span class="worldmap-list-archive">' + el.dataset.archive + '</span>' +
                    '</label>' +
                    '<a class="worldmap-list-open" href="' + el.getAttribute('href') + '">open</a>' +
                    '</li>';
            });
            items.innerHTML = html;
        };

        var setVisible = function (index, on) {
            var layer = activeLayer();
            var group = svg.querySelector('.worldmap-layer-group[data-layer="' + layer + '"]');
            var el = group.querySelector('[data-list-index="' + index + '"]');
            if (el) { el.style.display = on ? '' : 'none'; }
        };

        items.addEventListener('change', function (event) {
            var box = event.target.closest('[data-list-toggle]');
            if (!box) { return; }
            setVisible(box.dataset.listToggle, box.checked);
        });

        list.querySelector('[data-list-all]').addEventListener('click', function () {
            items.querySelectorAll('[data-list-toggle]').forEach(function (box) {
                box.checked = true;
                setVisible(box.dataset.listToggle, true);
            });
        });

        list.querySelector('[data-list-none]').addEventListener('click', function () {
            items.querySelectorAll('[data-list-toggle]').forEach(function (box) {
                box.checked = false;
                setVisible(box.dataset.listToggle, false);
            });
        });

        // Highlight on hover, so a name in the list can be found on the map.
        items.addEventListener('pointerover', function (event) {
            var row = event.target.closest('.worldmap-list-item');
            if (!row) { return; }
            var box = row.querySelector('[data-list-toggle]');
            var layer = activeLayer();
            var group = svg.querySelector('.worldmap-layer-group[data-layer="' + layer + '"]');
            var el = group.querySelector('[data-list-index="' + box.dataset.listToggle + '"]');
            if (el) { el.classList.add('is-hovered'); }
        });

        items.addEventListener('pointerout', function (event) {
            var row = event.target.closest('.worldmap-list-item');
            if (!row) { return; }
            svg.querySelectorAll('.is-hovered').forEach(function (el) {
                el.classList.remove('is-hovered');
            });
        });

        root.querySelectorAll('[data-worldmap-layer]').forEach(function (button) {
            button.addEventListener('click', function () { setTimeout(build, 0); });
        });

        build();
    }

    /* --- pinboard --------------------------------------------------------- */

    // Large enough that a board never reaches an edge; the stage pans/zooms it.
    var BOARD_W = 6000;
    var BOARD_H = 4500;
    var BOARD_MID = { x: BOARD_W / 2, y: BOARD_H / 2 };

    // Half a pin, for meeting strings at its edge rather than under it.
    var PIN_HW = 87;
    var PIN_HH = 23;

    // Force-directed layout: rest length, repulsion, pull to center, spring/damping.
    var BOARD_REST = 200;
    var BOARD_PUSH = 70000;
    var BOARD_PULL = 0.003;
    var BOARD_SPRING = 0.038;
    var BOARD_DAMP = 0.82;

    // Clear air between two pins, edge to edge.
    var PIN_GAP_X = 22;
    var PIN_GAP_Y = 16;

    // Prising two pins apart can push one into a third, so the pass is repeated a few times.
    var BOARD_PASSES = 6;

    // How many neighbours one click can open — bounded by board room
    // (PinboardRepo::MAX_PINS server-side), not an artificial batch size.
    var BOARD_BATCH = 240;

    function initPinboard() {
        var root = document.querySelector('[data-pinboard]');
        if (!root) { return; }

        var stage = root.querySelector('[data-pinboard-stage]');
        var canvas = root.querySelector('[data-pinboard-canvas]');
        var strings = root.querySelector('[data-pinboard-strings]');
        var pinLayer = root.querySelector('[data-pinboard-pins]');
        var blank = root.querySelector('[data-pinboard-blank]');
        var note = root.querySelector('[data-pinboard-note]');
        var inspect = root.querySelector('[data-pinboard-inspect]');
        var search = root.querySelector('[data-pinboard-search]');
        var results = root.querySelector('[data-pinboard-results]');
        if (!stage || !canvas || !strings || !pinLayer) { return; }

        var api = (root.dataset.base || '') + '/pinboard';

        // id -> { id, data, x, y, vx, vy, held, opened, kept, el }
        //   held   the reader has put it somewhere; the arrangement leaves it
        //   opened its neighbours have been fetched onto the board
        //   kept   pinned by hand, so folding something else away leaves it
        var pins = {};
        var edges = [];
        var startId = parseInt(root.dataset.start, 10) || 0;
        var shown = { connection: true, field: true };
        var chosen = null;      // the string being inspected

        var view = { x: 0, y: 0, scale: 1 };

        /* --- which archives the board may show -------------------------------- */

        // Stored as "off" rather than "on" so a newly added archive defaults to visible.
        var hidden = {};

        try {
            (JSON.parse(localStorage.getItem('wb-pinboard-hidden') || '[]') || [])
                .forEach(function (id) { hidden[id] = true; });
        } catch (e) { /* private mode, or nonsense in storage */ }

        var hiddenList = function () { return Object.keys(hidden); };

        var rememberHidden = function () {
            try {
                localStorage.setItem('wb-pinboard-hidden', JSON.stringify(hiddenList()));
            } catch (e) { /* private mode */ }
        };

        // Pins hidden by archive wait here rather than being discarded, so
        // switching the archive back on restores them.
        var parked = {};

        var applyFilter = function () {
            var id;

            for (id in pins) {
                if (hidden[pins[id].data.category]) {
                    if (pins[id].el) { pins[id].el.remove(); }
                    pins[id].el = null;
                    parked[id] = pins[id];
                    delete pins[id];
                }
            }

            for (id in parked) {
                if (!hidden[parked[id].data.category]) {
                    pins[id] = parked[id];
                    delete parked[id];
                }
            }
        };

        /* --- the canvas ---------------------------------------------------- */

        var applyView = function () {
            canvas.style.transform =
                'translate(' + view.x + 'px,' + view.y + 'px) scale(' + view.scale + ')';
        };

        /** Page pixels to canvas coordinates. */
        var toCanvas = function (event) {
            var rect = stage.getBoundingClientRect();

            return {
                x: (event.clientX - rect.left - view.x) / view.scale,
                y: (event.clientY - rect.top - view.y) / view.scale
            };
        };

        var centreOn = function (x, y) {
            var rect = stage.getBoundingClientRect();
            view.x = rect.width / 2 - x * view.scale;
            view.y = rect.height / 2 - y * view.scale;
            applyView();
        };

        var fit = function () {
            var all = Object.keys(pins);
            if (all.length === 0) { return; }

            var minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
            all.forEach(function (id) {
                var pin = pins[id];
                minX = Math.min(minX, pin.x - PIN_HW);
                minY = Math.min(minY, pin.y - PIN_HH);
                maxX = Math.max(maxX, pin.x + PIN_HW);
                maxY = Math.max(maxY, pin.y + PIN_HH);
            });

            var rect = stage.getBoundingClientRect();
            var pad = 60;
            var scale = Math.min(
                (rect.width - pad) / Math.max(1, maxX - minX),
                (rect.height - pad) / Math.max(1, maxY - minY)
            );

            // Never zoomed out past readability; panning is the better answer beyond that.
            view.scale = Math.max(0.45, Math.min(1.2, scale));
            centreOn((minX + maxX) / 2, (minY + maxY) / 2);
        };

        /* --- talking to the server ----------------------------------------- */

        var ids = function () { return Object.keys(pins); };

        var say = function (message) {
            if (!note) { return; }
            note.textContent = message || '';
            note.hidden = !message;
        };

        /**
         * Fetches the board. `open` asks for one pin's neighbours too — the only
         * way new pins arrive. `said` is the message left on screen after.
         */
        var load = function (open, origin, said) {
            var params = new URLSearchParams();
            params.set('pins', ids().join(','));
            if (hiddenList().length) { params.set('hidden', hiddenList().join(',')); }
            if (open) {
                params.set('open', String(open));
                params.set('take', String(BOARD_BATCH));
            }

            return fetch(api + '/graph?' + params.toString(), {
                headers: { 'Accept': 'application/json' }
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data.ok) { throw new Error('refused'); }
                    absorb(data, origin || open || 0);

                    if (data.full) {
                        say(t('That is as many pins as the board will hold — take some off to open more.'));
                    } else if (data.more > 0) {
                        say(data.more + ' more tied to that one. Click it again to bring them in.');
                    } else {
                        say(said || '');
                    }
                })
                .catch(function () { say(t('The board could not be reached.')); });
        };

        /** Takes the server's answer as truth, keeping existing positions; new pins ring the opener. */
        var absorb = function (data, originId) {
            var origin = pins[originId] || null;
            var arriving = data.nodes.filter(function (node) { return !pins[node.id]; });
            var turn = Math.random() * Math.PI * 2;
            // Wide enough for the whole batch to fit around the ring without overlapping.
            var ring = Math.max(250, arriving.length * (PIN_HW * 2 + PIN_GAP_X) / (Math.PI * 2));
            var index = 0;

            data.nodes.forEach(function (node) {
                var pin = pins[node.id];

                if (pin) {
                    pin.data = node;
                    return;
                }

                // Spread by the golden angle: successive pins never line up,
                // however many arrive at once.
                var angle = turn + index * 2.399963;
                index++;

                pins[node.id] = {
                    id: node.id,
                    data: node,
                    x: (origin ? origin.x : BOARD_MID.x) + Math.cos(angle) * ring,
                    y: (origin ? origin.y : BOARD_MID.y) + Math.sin(angle) * ring,
                    vx: 0,
                    vy: 0,
                    held: false,
                    opened: false,
                    kept: !origin,
                    el: null
                };
            });

            // Anything asked for but not returned is no longer shown — archive
            // off, or the entry is gone.
            var returned = {};
            data.nodes.forEach(function (node) { returned[node.id] = true; });

            for (var id in pins) {
                if (!returned[id]) {
                    if (pins[id].el) { pins[id].el.remove(); }
                    delete pins[id];
                }
            }

            edges = data.edges;
            renderPins();
            renderStrings();
            heat();
        };

        /* --- the arrangement ------------------------------------------------ */

        var alpha = 0;
        var running = false;
        var pendingFit = false;

        var heat = function () {
            alpha = 1;
            if (running) { return; }
            running = true;
            window.requestAnimationFrame(step);
        };

        var step = function () {
            settle();
            place();

            alpha *= 0.955;
            if (alpha > 0.015) {
                window.requestAnimationFrame(step);
                return;
            }

            running = false;

            // Framed only once movement stops, so fit() aims at the final layout, not mid-spread.
            if (pendingFit) {
                pendingFit = false;
                fit();
            }
        };

        /** One turn of the arrangement: push everything apart, pull strings in. */
        var settle = function () {
            var list = [];
            for (var id in pins) { list.push(pins[id]); }
            if (list.length < 2) { return; }

            var i, j, a, b, dx, dy, far, force;

            for (i = 0; i < list.length; i++) {
                a = list[i];
                for (j = i + 1; j < list.length; j++) {
                    b = list[j];
                    dx = b.x - a.x;
                    dy = b.y - a.y;
                    far = Math.sqrt(dx * dx + dy * dy) || 0.01;

                    // Two pins on the same spot have no direction to separate
                    // along, so they are given one.
                    if (far < 1) {
                        dx = Math.random() - 0.5;
                        dy = Math.random() - 0.5;
                        far = 1;
                    }

                    force = Math.min(BOARD_PUSH / (far * far), 40);
                    a.vx -= (dx / far) * force;
                    a.vy -= (dy / far) * force;
                    b.vx += (dx / far) * force;
                    b.vy += (dy / far) * force;
                }

                // Weak pull to center, unscaled by pin count — a big board would
                // otherwise be crushed inward.
                a.vx += (BOARD_MID.x - a.x) * BOARD_PULL;
                a.vy += (BOARD_MID.y - a.y) * BOARD_PULL;
            }

            edges.forEach(function (edge) {
                var from = pins[edge.a];
                var to = pins[edge.b];
                if (!from || !to) { return; }

                var ex = to.x - from.x;
                var ey = to.y - from.y;
                var length = Math.sqrt(ex * ex + ey * ey) || 0.01;
                var pull = (length - BOARD_REST) * BOARD_SPRING;

                from.vx += (ex / length) * pull;
                from.vy += (ey / length) * pull;
                to.vx -= (ex / length) * pull;
                to.vy -= (ey / length) * pull;
            });

            list.forEach(function (pin) {
                if (pin.held) { pin.vx = 0; pin.vy = 0; return; }

                pin.vx *= BOARD_DAMP;
                pin.vy *= BOARD_DAMP;
                pin.x += pin.vx * alpha;
                pin.y += pin.vy * alpha;

                // The canvas is generous, but not endless.
                pin.x = Math.max(PIN_HW, Math.min(BOARD_W - PIN_HW, pin.x));
                pin.y = Math.max(PIN_HH, Math.min(BOARD_H - PIN_HH, pin.y));
            });

            separate(list);
        };

        /**
         * Prises apart any two overlapping pin cards. Repulsion alone can't do this
         * (it works on center distance, and a pin is wider than tall).
         */
        var separate = function (list) {
            var reachX = PIN_HW * 2 + PIN_GAP_X;
            var reachY = PIN_HH * 2 + PIN_GAP_Y;
            var pass, i, j, a, b, dx, dy, overX, overY, shift;

            for (pass = 0; pass < BOARD_PASSES; pass++) {
                for (i = 0; i < list.length; i++) {
                    a = list[i];
                    for (j = i + 1; j < list.length; j++) {
                        b = list[j];

                        dx = b.x - a.x;
                        dy = b.y - a.y;
                        overX = reachX - Math.abs(dx);
                        overY = reachY - Math.abs(dy);

                        if (overX <= 0 || overY <= 0) { continue; }

                        // A pin the reader has placed does not move; the other
                        // one gets out of its way entirely.
                        var share = a.held === b.held ? 0.5 : 1;

                        if (overX / reachX < overY / reachY) {
                            shift = overX * (dx < 0 ? -1 : 1);
                            if (!a.held) { a.x -= shift * (b.held ? 1 : share); }
                            if (!b.held) { b.x += shift * (a.held ? 1 : share); }
                        } else {
                            shift = overY * (dy < 0 ? -1 : 1);
                            if (!a.held) { a.y -= shift * (b.held ? 1 : share); }
                            if (!b.held) { b.y += shift * (a.held ? 1 : share); }
                        }
                    }
                }
            }
        };

        /* --- drawing --------------------------------------------------------- */

        /** Where a string should meet a pin: its edge, on the side it comes from. */
        var meet = function (pin, towardX, towardY) {
            var dx = towardX - pin.x;
            var dy = towardY - pin.y;
            if (dx === 0 && dy === 0) { return { x: pin.x, y: pin.y }; }

            var scale = Math.min(
                dx === 0 ? Infinity : PIN_HW / Math.abs(dx),
                dy === 0 ? Infinity : PIN_HH / Math.abs(dy)
            );

            return { x: pin.x + dx * scale, y: pin.y + dy * scale };
        };

        var place = function () {
            for (var id in pins) {
                var pin = pins[id];
                if (!pin.el) { continue; }
                pin.el.style.transform =
                    'translate(' + Math.round(pin.x) + 'px,' + Math.round(pin.y) + 'px)'
                    + ' translate(-50%, -50%)';
            }

            edges.forEach(function (edge) {
                if (!edge.el) { return; }

                var from = pins[edge.a];
                var to = pins[edge.b];
                if (!from || !to) { return; }

                var start = meet(from, to.x, to.y);
                var end = meet(to, from.x, from.y);
                var d;

                if (edge.bow) {
                    // Two entries can be tied twice — a connection and a field
                    // link, or two fields. Bowing them apart keeps both visible.
                    var mx = (start.x + end.x) / 2;
                    var my = (start.y + end.y) / 2;
                    var nx = -(end.y - start.y);
                    var ny = end.x - start.x;
                    var length = Math.sqrt(nx * nx + ny * ny) || 1;

                    d = 'M' + start.x + ' ' + start.y
                      + 'Q' + (mx + (nx / length) * edge.bow) + ' '
                            + (my + (ny / length) * edge.bow) + ' '
                            + end.x + ' ' + end.y;
                } else {
                    d = 'M' + start.x + ' ' + start.y + 'L' + end.x + ' ' + end.y;
                }

                edge.el.setAttribute('d', d);
                edge.hit.setAttribute('d', d);
            });
        };

        var key = function (edge) { return edge.kind + ':' + edge.id; };

        /* --- what a string is, without clicking it ---------------------------- */

        // Reuses the world map's hover card. A dashed string doesn't show which
        // field links, so hovering names it.
        var tip = document.createElement('div');
        tip.className = 'worldmap-tip pinboard-tip';
        tip.hidden = true;
        stage.appendChild(tip);

        var tellAbout = function (edge) {
            var from = pins[edge.a];
            var to = pins[edge.b];
            if (!from || !to) { return; }

            tip.textContent = '';

            var heading = document.createElement('strong');
            // A field link is named by its field; a connection by its note, if
            // it was given one.
            heading.textContent = edge.kind === 'field'
                ? (edge.label || t('Field link'))
                : (edge.note ? '“' + edge.note + '”' : t('Connection'));

            var ends = document.createElement('span');
            ends.textContent = from.data.title
                + (edge.kind === 'field' ? ' → ' : ' — ')
                + to.data.title;

            tip.appendChild(heading);
            tip.appendChild(ends);
            tip.hidden = false;
        };

        var hideTip = function () { tip.hidden = true; };

        // Bound once here, not per string — strings rebuild on every board change.
        stage.addEventListener('pointermove', function (event) {
            if (tip.hidden) { return; }

            var rect = stage.getBoundingClientRect();
            var x = event.clientX - rect.left + 14;
            var y = event.clientY - rect.top + 14;
            var size = tip.getBoundingClientRect();

            if (x + size.width > rect.width) { x = Math.max(0, x - size.width - 28); }
            if (y + size.height > rect.height) { y = Math.max(0, y - size.height - 28); }

            tip.style.left = Math.round(x) + 'px';
            tip.style.top = Math.round(y) + 'px';
        });

        var renderPins = function () {
            var wanted = {};
            for (var id in pins) { wanted[id] = true; }

            // Anything no longer on the board goes. Snapshotted first, since
            // `children` is a live list and removing while walking it skips items.
            Array.prototype.slice.call(pinLayer.children).forEach(function (el) {
                if (!wanted[el.dataset.id]) { el.remove(); }
            });

            for (var pinId in pins) {
                var pin = pins[pinId];
                if (!pin.el || !pin.el.isConnected) { pin.el = buildPin(pin); }
                dressPin(pin);
            }

            if (blank) { blank.hidden = Object.keys(pins).length > 0; }
        };

        var buildPin = function (pin) {
            var el = document.createElement('div');
            el.className = 'pinboard-pin';
            el.dataset.id = String(pin.id);
            el.style.setProperty('--archive-color', pin.data.color || 'var(--accent)');

            var icon = document.createElement('span');
            icon.className = 'pinboard-pin-icon';
            icon.textContent = pin.data.icon || '•';

            var body = document.createElement('span');
            body.className = 'pinboard-pin-body';

            var title = document.createElement('span');
            title.className = 'pinboard-pin-title';
            title.textContent = pin.data.title;

            var archive = document.createElement('span');
            archive.className = 'pinboard-pin-archive';
            archive.textContent = pin.data.archive;

            body.appendChild(title);
            body.appendChild(archive);

            var more = document.createElement('span');
            more.className = 'pinboard-pin-more';

            var tools = document.createElement('span');
            tools.className = 'pinboard-pin-tools';

            var visit = document.createElement('a');
            visit.className = 'pinboard-pin-tool';
            visit.href = pin.data.url;
            visit.title = t('Open %s', pin.data.title);
            visit.textContent = '↗';

            var drop = document.createElement('button');
            drop.type = 'button';
            drop.className = 'pinboard-pin-tool';
            drop.title = t('Take off the board');
            drop.textContent = '✕';
            drop.addEventListener('click', function (event) {
                event.stopPropagation();
                unpin(pin.id);
            });

            tools.appendChild(visit);
            tools.appendChild(drop);

            var ring = document.createElement('span');
            ring.className = 'pinboard-pin-string';
            ring.title = t('Drag to another pin to tie them together');

            // The second handle, for a link through one of this entry's own relation fields.
            var link = null;
            if ((pin.data.fields || []).length) {
                link = document.createElement('span');
                link.className = 'pinboard-pin-link';
                link.title = pin.data.fields.length === 1
                    ? t('Drag to an entry this one can point at, through “%s”', pin.data.fields[0].label)
                    : t('Drag to an entry this one can point at, through one of its fields');
            }

            el.appendChild(icon);
            el.appendChild(body);
            el.appendChild(more);
            el.appendChild(tools);
            el.appendChild(ring);
            if (link) { el.appendChild(link); }

            bindPin(pin, el, ring, link);
            pinLayer.appendChild(el);

            return el;
        };

        /** How many entries this one is tied to that are not on the board (counted in neighbours, not strings). */
        var hiddenFor = function (pin) {
            var neighbours = {};

            edges.forEach(function (edge) {
                if (edge.a === pin.id) { neighbours[edge.b] = true; }
                if (edge.b === pin.id) { neighbours[edge.a] = true; }
            });

            return Math.max(0, (pin.data.degree || 0) - Object.keys(neighbours).length);
        };

        /** Everything about a pin that can change while it is on the board. */
        var dressPin = function (pin) {
            var hidden = hiddenFor(pin);
            var more = pin.el.querySelector('.pinboard-pin-more');

            more.textContent = hidden > 0 ? '+' + hidden : (pin.data.degree ? '✓' : '·');
            more.classList.toggle('is-none', hidden === 0);
            more.title = hidden > 0
                ? t('%d more not on the board — click to open them', hidden)
                : (pin.data.degree
                    ? t('Everything this is tied to is on the board')
                    : t('Not tied to anything yet'));

            pin.el.classList.toggle('is-start', pin.id === startId);
            pin.el.classList.toggle('is-open', pin.opened);
        };

        var renderStrings = function () {
            strings.textContent = '';

            // How many strings each pair already has, so the second and third
            // can be bowed out of the way of the first.
            var pairs = {};

            edges.forEach(function (edge) {
                var pair = Math.min(edge.a, edge.b) + '-' + Math.max(edge.a, edge.b);
                pairs[pair] = (pairs[pair] || 0) + 1;
                var index = pairs[pair] - 1;
                edge.bow = index === 0 ? 0 : Math.ceil(index / 2) * 34 * (index % 2 ? 1 : -1);

                var visible = shown[edge.kind] !== false;

                var line = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                line.setAttribute('class',
                    'pinboard-string' + (edge.kind === 'field' ? ' pinboard-string--field' : ''));
                if (!visible) { line.setAttribute('hidden', 'hidden'); }

                var hit = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                hit.setAttribute('class', 'pinboard-hit');
                if (!visible) { hit.setAttribute('hidden', 'hidden'); }

                hit.addEventListener('pointerenter', function () {
                    line.classList.add('is-lit');
                    tellAbout(edge);
                });
                hit.addEventListener('pointerleave', function () {
                    line.classList.remove('is-lit');
                    hideTip();
                });
                hit.addEventListener('click', function (event) {
                    event.stopPropagation();
                    look(edge);
                });

                edge.el = line;
                edge.hit = hit;

                if (chosen && key(chosen) === key(edge)) {
                    chosen = edge;
                    line.classList.add('is-chosen');
                }

                strings.appendChild(line);
                strings.appendChild(hit);
            });

            place();
        };

        /* --- what a pin does ------------------------------------------------- */

        var bindPin = function (pin, el, ring, link) {
            var moved = false;
            var grab = null;

            el.addEventListener('pointerdown', function (event) {
                if (event.button !== 0) { return; }
                if (event.target.closest('.pinboard-pin-tool')) { return; }
                if (event.target === ring || event.target === link) { return; }

                event.stopPropagation();
                moved = false;
                var at = toCanvas(event);
                grab = { dx: pin.x - at.x, dy: pin.y - at.y, x: event.clientX, y: event.clientY };
                pin.held = true;
                el.classList.add('is-dragging');
            });

            el.addEventListener('pointermove', function (event) {
                if (!grab) { return; }

                // Only after real movement — taking it on press would misdirect the click.
                if (!moved) {
                    if (Math.abs(event.clientX - grab.x) + Math.abs(event.clientY - grab.y) < 4) {
                        return;
                    }
                    moved = true;
                    try { el.setPointerCapture(event.pointerId); } catch (e) { /* gone already */ }
                }

                var at = toCanvas(event);
                pin.x = at.x + grab.dx;
                pin.y = at.y + grab.dy;
                place();
            });

            var release = function (event) {
                if (!grab) { return; }
                grab = null;
                el.classList.remove('is-dragging');

                if (moved && el.hasPointerCapture(event.pointerId)) {
                    el.releasePointerCapture(event.pointerId);
                }

                // A pin left where it was put stays there; the arrangement
                // works around it.
                if (!moved) { pin.held = false; }
                heat();
            };

            el.addEventListener('pointerup', release);
            el.addEventListener('pointercancel', release);

            el.addEventListener('click', function (event) {
                if (event.target.closest('.pinboard-pin-tool')) { return; }
                if (moved) { return; }
                event.stopPropagation();

                // Opening again brings the next handful; folding is what a
                // click means only once there is nothing left to bring.
                if (pin.opened && hiddenFor(pin) === 0) {
                    fold(pin);
                } else {
                    open(pin);
                }
            });

            ring.addEventListener('pointerdown', function (event) {
                event.stopPropagation();
                event.preventDefault();
                startString(pin, event, 'connection');
            });

            if (link) {
                link.addEventListener('pointerdown', function (event) {
                    event.stopPropagation();
                    event.preventDefault();
                    startString(pin, event, 'field');
                });
            }
        };

        /**
         * Which of an entry's relation fields could point at another entry —
         * respecting archive restrictions, excluding fields already pointing there.
         *
         * @return array of fields, empty when the link cannot be made at all
         */
        var fieldsBetween = function (from, to) {
            return (from.data.fields || []).filter(function (field) {
                if (field.targets.length && field.targets.indexOf(to.data.category) === -1) {
                    return false;
                }

                return field.holds.indexOf(to.id) === -1;
            });
        };

        /** Whether anything on `from` could point at `to` at all. */
        var canReach = function (from, to) {
            return from.id !== to.id && fieldsBetween(from, to).length > 0;
        };

        var open = function (pin) {
            pin.opened = true;
            dressPin(pin);
            load(pin.id, pin.id);
        };

        /** Folds a pin's neighbours away — only the ones it brought and nothing else needs. */
        var fold = function (pin) {
            pin.opened = false;

            var doomed = [];

            for (var id in pins) {
                var other = pins[id];
                if (other.id === pin.id || other.kept || other.opened || other.id === startId) {
                    continue;
                }

                var elsewhere = false;
                edges.forEach(function (edge) {
                    var far = edge.a === other.id ? edge.b : (edge.b === other.id ? edge.a : 0);
                    if (far && far !== pin.id && pins[far]) { elsewhere = true; }
                });

                if (!elsewhere) { doomed.push(other.id); }
            }

            var around = 0;
            edges.forEach(function (edge) {
                if (edge.a === pin.id || edge.b === pin.id) { around++; }
            });

            if (doomed.length === 0) {
                dressPin(pin);
                say(around === 0
                    ? t('That one is not tied to anything yet.')
                    : t('Nothing to fold away — everything that one touches is tied to the rest of the board as well.'));
                return;
            }

            doomed.forEach(function (id) {
                if (pins[id].el) { pins[id].el.remove(); }
                delete pins[id];
            });

            // Folding takes away only what this pin alone was holding up.
            load(0, 0, doomed.length < around
                ? tn(doomed.length,
                    'Folded away %d pin; the rest are tied to something else on the board.',
                    'Folded away %d pins; the rest are tied to something else on the board.')
                : '');
        };

        var unpin = function (id) {
            if (pins[id]) {
                if (pins[id].el) { pins[id].el.remove(); }
                delete pins[id];
            }
            if (id === startId) { startId = 0; }
            if (Object.keys(pins).length === 0) {
                edges = [];
                renderStrings();
                renderPins();
                return;
            }
            load(0, 0);
        };

        var add = function (id) {
            if (pins[id]) {
                pins[id].kept = true;
                return;
            }
            // The first pin on an empty board opens itself, same as arriving from
            // an entry's own page; a pin added to a going board does not.
            var first = Object.keys(pins).length === 0;

            // Placed at the centre first, then loaded: absorb() only positions
            // pins it hasn't seen, so this lands where the reader is looking.
            pins[id] = {
                id: id, data: { title: '', archive: '', icon: '•', color: '', url: '#', degree: 0 },
                x: BOARD_MID.x, y: BOARD_MID.y, vx: 0, vy: 0,
                held: false, opened: first, kept: true, el: null
            };

            if (!startId) { startId = id; }

            if (first) {
                pendingFit = true;
                load(id, id);
            } else {
                load(0, 0);
            }
        };

        /* --- tying two pins together ----------------------------------------- */

        var startString = function (pin, event, kind) {
            var field = kind === 'field';

            var draft = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            draft.setAttribute('class', 'pinboard-draft' + (field ? ' pinboard-draft--field' : ''));
            strings.appendChild(draft);
            stage.classList.add('is-stringing');

            // A field link can only go where one of this entry's own fields can
            // point; flagged while the string is in the air, not refused after the drop.
            if (field) {
                for (var id in pins) {
                    if (!pins[id].el || pins[id].id === pin.id) { continue; }
                    pins[id].el.classList.toggle('is-forbidden', !canReach(pin, pins[id]));
                }
            }

            var over = null;

            var track = function (moveEvent) {
                var at = toCanvas(moveEvent);
                var start = meet(pin, at.x, at.y);
                draft.setAttribute('d', 'M' + start.x + ' ' + start.y + 'L' + at.x + ' ' + at.y);

                var under = document.elementFromPoint(moveEvent.clientX, moveEvent.clientY);
                var card = under ? under.closest('.pinboard-pin') : null;
                var target = card && card.dataset.id !== String(pin.id) ? card : null;

                // Somewhere it cannot go is not a target, however much it's hovered over.
                if (target && field && target.classList.contains('is-forbidden')) { target = null; }

                if (over !== target) {
                    if (over) { over.classList.remove('is-target'); }
                    over = target;
                    if (over) { over.classList.add('is-target'); }
                }
            };

            var finish = function (upEvent) {
                document.removeEventListener('pointermove', track);
                document.removeEventListener('pointerup', finish);
                draft.remove();
                stage.classList.remove('is-stringing');

                if (over) { over.classList.remove('is-target'); }
                Array.prototype.slice.call(pinLayer.children).forEach(function (card) {
                    card.classList.remove('is-forbidden');
                });

                var under = document.elementFromPoint(upEvent.clientX, upEvent.clientY);
                var card = under ? under.closest('.pinboard-pin') : null;
                if (!card || card.dataset.id === String(pin.id)) { return; }

                var target = pins[card.dataset.id];
                if (!target) { return; }

                if (!field) {
                    offerConnection(pin, target);
                    return;
                }

                offerFields(pin, target);
            };

            document.addEventListener('pointermove', track);
            document.addEventListener('pointerup', finish);
        };

        var tie = function (a, b, note) {
            var body = new FormData();
            body.append('a', String(a));
            body.append('b', String(b));
            if (note) { body.append('note', note); }
            body.append('_token', csrf);

            fetch(api + '/connect', { method: 'POST', body: body })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        say(data.error || t('That could not be tied.'));
                        return;
                    }
                    load(0, 0, data.added ? '' : t('Those two were already tied together.'));
                })
                .catch(function () { say(t('The board could not be reached.')); });
        };

        /**
         * A string dropped on a pin has both ends settled; only the note is left
         * to give it. Backed by fetch-and-redraw rather than a page submit, since
         * navigating away would lose the board.
         */
        var connectDialog = document.getElementById('pinboard-connect-modal');
        var connectTitle = connectDialog && connectDialog.querySelector('[data-pinboard-connect-title]');
        var connectEnds = connectDialog && connectDialog.querySelector('[data-pinboard-connect-ends]');
        var connectNote = connectDialog && connectDialog.querySelector('[data-pinboard-connect-note]');
        var connectAdd = connectDialog && connectDialog.querySelector('[data-pinboard-connect-add]');
        var pendingTie = null;
        var pendingEditId = null;

        var closeConnectDialog = function () {
            if (typeof connectDialog.close === 'function') { connectDialog.close(); }
            else { connectDialog.removeAttribute('open'); }
        };

        var offerConnection = function (from, to) {
            if (!connectDialog || !connectEnds || !connectNote || !connectAdd) {
                tie(from.id, to.id);
                return;
            }

            pendingTie = { a: from.id, b: to.id };
            pendingEditId = null;
            if (connectTitle) { connectTitle.textContent = t('Add connection'); }
            connectAdd.textContent = t('Add');
            connectEnds.textContent = from.data.title + ' — ' + to.data.title;
            connectNote.value = '';

            if (typeof connectDialog.showModal === 'function') { connectDialog.showModal(); }
            else { connectDialog.setAttribute('open', ''); }
            connectNote.focus();
        };

        /** The same dialog, aimed at a string that already exists — only the note changes. */
        var offerNoteEdit = function (edge) {
            if (!connectDialog || !connectEnds || !connectNote || !connectAdd) { return; }

            var from = pins[edge.a];
            var to = pins[edge.b];
            if (!from || !to) { return; }

            pendingTie = null;
            pendingEditId = edge.id;
            if (connectTitle) { connectTitle.textContent = t('Edit connection'); }
            connectAdd.textContent = t('Save');
            connectEnds.textContent = from.data.title + ' — ' + to.data.title;
            connectNote.value = edge.note || '';

            if (typeof connectDialog.showModal === 'function') { connectDialog.showModal(); }
            else { connectDialog.setAttribute('open', ''); }
            connectNote.focus();
            connectNote.select();
        };

        var updateNote = function (id, note) {
            var body = new FormData();
            body.append('connection', String(id));
            body.append('note', note);
            body.append('_token', csrf);

            fetch(api + '/note', { method: 'POST', body: body })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        say(data.error || t('That could not be saved.'));
                        return;
                    }
                    // Re-opens the inspect panel on the same string with the fresh note;
                    // load() reassigns `chosen` to the newly fetched edge.
                    var wasChosen = chosen && chosen.kind === 'connection' && chosen.id === id;
                    load(0, 0).then(function () {
                        if (wasChosen && chosen) { look(chosen); }
                    });
                })
                .catch(function () { say(t('The board could not be reached.')); });
        };

        if (connectDialog && connectAdd && connectNote) {
            connectAdd.addEventListener('click', function () {
                if (pendingEditId) {
                    var id = pendingEditId;
                    updateNote(id, connectNote.value);
                    pendingTie = null;
                    pendingEditId = null;
                    closeConnectDialog();
                    return;
                }

                if (!pendingTie) { return; }
                tie(pendingTie.a, pendingTie.b, connectNote.value);
                pendingTie = null;
                closeConnectDialog();
            });

            connectNote.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    connectAdd.click();
                }
            });

            // Cancel, Escape, or the backdrop all end up here — just drop the pending pick.
            connectDialog.addEventListener('close', function () {
                pendingTie = null;
                pendingEditId = null;
            });
        }

        /** A field link has landed on a pin: made directly with one usable field, asked with several. */
        var offerFields = function (from, to) {
            var usable = fieldsBetween(from, to);

            if (usable.length === 0) {
                // Either nothing on this entry reaches that archive, or the one
                // that does is already pointing there. Both are worth saying.
                var reaches = (from.data.fields || []).some(function (field) {
                    return !field.targets.length || field.targets.indexOf(to.data.category) !== -1;
                });

                say(reaches
                    ? t('“%s” already points at “%s”.', from.data.title, to.data.title)
                    : t('Nothing on “%s” can point at a %s.', from.data.title, to.data.archive.replace(/s$/, '')));
                return;
            }

            if (usable.length === 1) {
                makeLink(from, to, usable[0]);
                return;
            }

            askWhichField(from, to, usable);
        };

        /** The name of what a one-at-a-time field is holding, if it is on the board. */
        var heldName = function (field) {
            var held = pins[field.holds[0]];

            return held ? held.data.title : null;
        };

        var askWhichField = function (from, to, usable) {
            if (!inspect) { return; }

            clearLook();
            inspect.textContent = '';
            inspect.hidden = false;

            var kind = document.createElement('span');
            kind.className = 'pinboard-inspect-kind';
            kind.textContent = t('Through which field?');
            inspect.appendChild(kind);

            var ends = document.createElement('span');
            ends.className = 'pinboard-inspect-ends';
            ends.textContent = from.data.title + ' → ' + to.data.title;
            inspect.appendChild(ends);

            usable.forEach(function (field) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn--sm';

                var name = field.label;
                if (!field.multiple && field.holds.length) {
                    var held = heldName(field);
                    name += held ? t(' (replaces %s)', held) : t(' (replaces what it holds)');
                }
                button.textContent = name;

                button.addEventListener('click', function () {
                    clearLook();
                    makeLink(from, to, field);
                });

                inspect.appendChild(button);
            });

            var cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.className = 'btn btn--ghost btn--sm';
            cancel.textContent = t('Cancel');
            cancel.addEventListener('click', clearLook);
            inspect.appendChild(cancel);
        };

        var makeLink = function (from, to, field) {
            var body = new FormData();
            body.append('entry', String(from.id));
            body.append('field', String(field.id));
            body.append('target', String(to.id));
            body.append('_token', csrf);

            fetch(api + '/link', { method: 'POST', body: body })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        say(data.error || t('That link could not be made.'));
                        return;
                    }

                    load(0, 0, data.replaced
                        ? t('“%s” holds one at a time, so it points at “%s” now.', field.label, to.data.title)
                        : '');
                })
                .catch(function () { say(t('The board could not be reached.')); });
        };

        /* --- reading a string -------------------------------------------------- */

        var look = function (edge) {
            if (chosen && chosen.el) { chosen.el.classList.remove('is-chosen'); }
            chosen = edge;
            if (edge.el) { edge.el.classList.add('is-chosen'); }

            var from = pins[edge.a];
            var to = pins[edge.b];
            if (!inspect || !from || !to) { return; }

            inspect.textContent = '';
            inspect.hidden = false;

            var kind = document.createElement('span');
            kind.className = 'pinboard-inspect-kind';
            kind.textContent = edge.kind === 'field' ? t('Field link') : t('Connection');
            inspect.appendChild(kind);

            var ends = document.createElement('span');
            ends.className = 'pinboard-inspect-ends';

            var left = document.createElement('a');
            left.href = from.data.url;
            left.textContent = from.data.title;

            var right = document.createElement('a');
            right.href = to.data.url;
            right.textContent = to.data.title;

            ends.appendChild(left);
            // A field link points one way; a connection reads the same from
            // either end, so it gets a line rather than an arrow.
            ends.appendChild(document.createTextNode(edge.kind === 'field' ? ' → ' : ' — '));
            ends.appendChild(right);
            inspect.appendChild(ends);

            if (edge.kind === 'field') {
                var where = document.createElement('span');
                where.className = 'field-help pinboard-inspect-note';
                where.textContent = t('through “%s” on %s.', edge.label || t('a field'), from.data.title);
                inspect.appendChild(where);

                // The board can make these now, so it must be able to take one back out.
                var undo = document.createElement('button');
                undo.type = 'button';
                undo.className = 'btn btn--danger btn--sm';
                undo.textContent = t('Remove this link');
                undo.addEventListener('click', function () {
                    if (!window.confirm(t('Stop “%s” pointing at “%s” through “%s”?',
                        from.data.title, to.data.title, edge.label || t('that field')))) {
                        return;
                    }
                    unlink(edge);
                });
                inspect.appendChild(undo);
            } else {
                var noteText = document.createElement('span');
                noteText.className = 'field-help pinboard-inspect-note';
                noteText.textContent = edge.note ? '“' + edge.note + '”' : t('No note on this one.');
                inspect.appendChild(noteText);

                var edit = document.createElement('button');
                edit.type = 'button';
                edit.className = 'btn btn--sm';
                edit.textContent = t('Edit');
                edit.addEventListener('click', function () { offerNoteEdit(edge); });
                inspect.appendChild(edit);

                var cut = document.createElement('button');
                cut.type = 'button';
                cut.className = 'btn btn--danger btn--sm';
                cut.textContent = t('Cut this string');
                cut.addEventListener('click', function () {
                    if (!window.confirm(t('Cut the connection between “%s” and “%s”?', from.data.title, to.data.title))) { return; }
                    snip(edge);
                });
                inspect.appendChild(cut);
            }

            var close = document.createElement('button');
            close.type = 'button';
            close.className = 'btn btn--ghost btn--sm';
            close.textContent = t('Close');
            close.addEventListener('click', clearLook);
            inspect.appendChild(close);
        };

        var clearLook = function () {
            if (chosen && chosen.el) { chosen.el.classList.remove('is-chosen'); }
            chosen = null;
            if (inspect) { inspect.hidden = true; inspect.textContent = ''; }
        };

        var snip = function (edge) {
            var body = new FormData();
            body.append('connection', String(edge.id));
            body.append('_token', csrf);

            fetch(api + '/disconnect', { method: 'POST', body: body })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        say(data.error || t('That could not be cut.'));
                        return;
                    }
                    clearLook();
                    load(0, 0);
                })
                .catch(function () { say(t('The board could not be reached.')); });
        };

        /** The same, for a link that belongs to a relation field. */
        var unlink = function (edge) {
            var body = new FormData();
            body.append('link', String(edge.id));
            body.append('_token', csrf);

            fetch(api + '/unlink', { method: 'POST', body: body })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        say(data.error || t('That could not be removed.'));
                        return;
                    }
                    clearLook();
                    load(0, 0);
                })
                .catch(function () { say(t('The board could not be reached.')); });
        };

        /* --- panning, zooming, and the rest ------------------------------------ */

        stage.addEventListener('wheel', function (event) {
            event.preventDefault();
            var rect = stage.getBoundingClientRect();
            var px = event.clientX - rect.left;
            var py = event.clientY - rect.top;
            var at = toCanvas(event);

            view.scale = Math.max(0.2, Math.min(2.2,
                view.scale * (event.deltaY > 0 ? 0.88 : 1.14)));

            // Keep whatever is under the pointer under the pointer.
            view.x = px - at.x * view.scale;
            view.y = py - at.y * view.scale;
            applyView();
        }, { passive: false });

        var panning = null;

        stage.addEventListener('pointerdown', function (event) {
            if (event.button !== 0) { return; }

            // Pins and buttons inside the stage are not the start of a pan.
            if (event.target.closest('.pinboard-pin, button, a, input, label')) { return; }

            panning = { x: event.clientX, y: event.clientY, vx: view.x, vy: view.y, held: false };
            stage.classList.add('is-dragging');
        });

        stage.addEventListener('pointermove', function (event) {
            if (!panning) { return; }

            // Only taken once the pointer has actually moved, so a plain click still
            // reaches whatever was pressed rather than firing on the stage.
            if (!panning.held) {
                if (Math.abs(event.clientX - panning.x) + Math.abs(event.clientY - panning.y) < 4) {
                    return;
                }
                panning.held = true;
                try { stage.setPointerCapture(event.pointerId); } catch (e) { /* gone already */ }
            }

            view.x = panning.vx + (event.clientX - panning.x);
            view.y = panning.vy + (event.clientY - panning.y);
            applyView();
        });

        var endPan = function (event) {
            if (panning && panning.held && stage.hasPointerCapture(event.pointerId)) {
                stage.releasePointerCapture(event.pointerId);
            }
            panning = null;
            stage.classList.remove('is-dragging');
        };
        stage.addEventListener('pointerup', endPan);
        stage.addEventListener('pointercancel', endPan);

        // Clicking the board itself, rather than a string, puts the reading
        // panel away.
        stage.addEventListener('click', function (event) {
            if (event.target.closest('.pinboard-pin') || event.target.closest('.pinboard-hit')) {
                return;
            }
            clearLook();
        });

        root.querySelectorAll('[data-pinboard-kind]').forEach(function (box) {
            box.addEventListener('change', function () {
                shown[box.dataset.pinboardKind] = box.checked;
                renderStrings();
                renderPins();
            });
        });

        root.querySelectorAll('[data-pinboard-fit]').forEach(function (button) {
            button.addEventListener('click', fit);
        });

        root.querySelectorAll('[data-pinboard-clear]').forEach(function (button) {
            button.addEventListener('click', function () {
                pins = {};
                edges = [];
                startId = 0;
                clearLook();
                say('');
                pinLayer.textContent = '';
                renderStrings();
                renderPins();
            });
        });

        root.querySelectorAll('[data-pinboard-pin]').forEach(function (button) {
            button.addEventListener('click', function () {
                add(parseInt(button.dataset.pinboardPin, 10));
            });
        });

        /* --- the archive filter --------------------------------------------------- */

        var boxes = root.querySelectorAll('[data-pinboard-archive]');

        var everyArchive = Array.prototype.map.call(boxes, function (box) {
            return box.dataset.pinboardArchive;
        });

        /** The archives still switched on, for the pickers that take a list. */
        var allowed = function () {
            return everyArchive.filter(function (id) { return !hidden[id]; });
        };

        var dressFilter = function () {
            Array.prototype.forEach.call(boxes, function (box) {
                var off = !!hidden[box.dataset.pinboardArchive];
                box.checked = !off;
                box.closest('.pinboard-archive').classList.toggle('is-off', off);
            });

            // A suggestion for an archive that is switched off would put a pin
            // on the board that the board would then refuse to show.
            root.querySelectorAll('[data-pinboard-suggestion-archive]').forEach(function (chip) {
                chip.hidden = !!hidden[chip.dataset.pinboardSuggestionArchive];
            });
        };

        Array.prototype.forEach.call(boxes, function (box) {
            box.addEventListener('change', function () {
                var id = box.dataset.pinboardArchive;

                if (box.checked) { delete hidden[id]; } else { hidden[id] = true; }

                rememberHidden();
                dressFilter();
                applyFilter();
                renderPins();
                load(0, 0);
            });
        });

        root.querySelectorAll('[data-pinboard-archives-all]').forEach(function (button) {
            button.addEventListener('click', function () {
                // Every archive back on, which is also the way out of having
                // switched off the one the board was about.
                hidden = {};
                rememberHidden();
                dressFilter();
                applyFilter();
                renderPins();
                load(0, 0);
            });
        });

        dressFilter();

        /* --- the search ---------------------------------------------------------- */

        if (search && results) {
            var timer = null;
            var highlighted = -1;

            var hide = function () {
                results.hidden = true;
                results.textContent = '';
                highlighted = -1;
            };

            var query = function () {
                var params = new URLSearchParams();
                params.set('scope', 'entries');
                params.set('q', search.value.trim());
                // Nothing already on the board is offered again.
                params.set('taken_entries', ids().join(','));

                // Nor anything from an archive that's switched off, since it couldn't be shown.
                if (hiddenList().length) { params.set('category', allowed().join(',')); }

                fetch((root.dataset.base || '') + '/api/lookup?' + params.toString(), {
                    headers: { 'Accept': 'application/json' }
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        results.textContent = '';

                        if (!(data.results || []).length) {
                            var empty = document.createElement('li');
                            empty.className = 'relation-empty';
                            empty.textContent = t('Nothing matches.');
                            results.appendChild(empty);
                            results.hidden = false;
                            return;
                        }

                        data.results.forEach(function (item) {
                            var option = document.createElement('li');
                            option.className = 'relation-result';

                            var icon = document.createElement('span');
                            icon.className = 'chip-icon';
                            icon.textContent = item.icon || '•';

                            var title = document.createElement('span');
                            title.textContent = item.title;

                            var where = document.createElement('span');
                            where.className = 'relation-result-cat';
                            where.textContent = item.category;

                            option.appendChild(icon);
                            option.appendChild(title);
                            option.appendChild(where);
                            option.addEventListener('mousedown', function (event) {
                                event.preventDefault();
                                search.value = '';
                                hide();
                                add(item.id);
                            });

                            results.appendChild(option);
                        });

                        results.hidden = false;
                        highlighted = -1;
                    })
                    .catch(hide);
            };

            search.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(query, 180);
            });
            search.addEventListener('focus', query);
            search.addEventListener('blur', function () { setTimeout(hide, 140); });

            search.addEventListener('keydown', function (event) {
                var options = results.querySelectorAll('.relation-result');

                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    if (!options.length) { return; }
                    event.preventDefault();
                    highlighted += (event.key === 'ArrowDown' ? 1 : -1);
                    if (highlighted < 0) { highlighted = options.length - 1; }
                    if (highlighted >= options.length) { highlighted = 0; }
                    options.forEach(function (option, index) {
                        option.classList.toggle('is-highlighted', index === highlighted);
                    });
                    return;
                }

                if (event.key === 'Enter') {
                    event.preventDefault();
                    var wanted = options[highlighted >= 0 ? highlighted : 0];
                    if (wanted) { wanted.dispatchEvent(new MouseEvent('mousedown')); }
                    return;
                }

                if (event.key === 'Escape') { hide(); }
            });
        }

        /* --- opening --------------------------------------------------------- */

        view.scale = 1;
        centreOn(BOARD_MID.x, BOARD_MID.y);

        if (startId > 0) {
            pins[startId] = {
                id: startId,
                data: { title: '', archive: '', icon: '•', color: '', url: '#', degree: 0 },
                x: BOARD_MID.x, y: BOARD_MID.y, vx: 0, vy: 0,
                held: false, opened: true, kept: true, el: null
            };
            // Opened at once: a board with one pin and nothing around it says
            // less than the entry's own page already does.
            pendingFit = true;
            load(startId, startId);
        }
    }

    /* --- timeline ------------------------------------------------------------
       Points and era bars are plain positioned HTML, not SVG — only ever a
       horizontal transform, so left/width in pixels is simplest. */

    function initTimeline() {
        var root = document.querySelector('[data-timeline]');
        if (!root) { return; }

        var stage = root.querySelector('[data-timeline-stage]');
        var axisLayer = root.querySelector('[data-timeline-axis]');
        var pointsLayer = root.querySelector('[data-timeline-points]');
        var erasLayer = root.querySelector('[data-timeline-eras]');
        var blank = root.querySelector('[data-timeline-blank]');
        var note = root.querySelector('[data-timeline-note]');
        var tip = root.querySelector('[data-timeline-tip]');
        if (!stage || !axisLayer || !pointsLayer || !erasLayer) { return; }

        var api = (root.dataset.base || '') + '/timeline';
        var siteBase = root.dataset.base || '';
        var focusId = parseInt(root.dataset.focus, 10) || 0;
        var epochName = root.dataset.epochName || '';
        var epochAbbr = root.dataset.epochAbbr || '';

        // "Nice numbers" for gridline spacing; list ends are the zoom limits.
        var TARGET_TICK_PX = 90;
        var NICE_STEPS = [1, 2, 5, 10, 20, 50, 100, 200, 500, 1000, 2000, 5000, 10000];
        var MAX_PX_PER_YEAR = TARGET_TICK_PX / NICE_STEPS[0];
        var MIN_PX_PER_YEAR = TARGET_TICK_PX / NICE_STEPS[NICE_STEPS.length - 1];

        // Where a fresh visit opens: fifty-year gridlines, not zoomed to fit everything.
        var INITIAL_PX_PER_YEAR = TARGET_TICK_PX / 50;

        var points = [];
        var eras = [];
        var focusKey = null;
        var view = { center: 0, pxPerYear: 4 };

        /* --- which archives the timeline may show -------------------------- */

        // Stored as "off" rather than "on" so a newly added archive defaults to visible.
        var hidden = {};

        try {
            (JSON.parse(localStorage.getItem('wb-timeline-hidden') || '[]') || [])
                .forEach(function (id) { hidden[id] = true; });
        } catch (e) { /* private mode, or nonsense in storage */ }

        var rememberHidden = function () {
            try {
                localStorage.setItem('wb-timeline-hidden', JSON.stringify(Object.keys(hidden)));
            } catch (e) { /* private mode */ }
        };

        var niceStep = function (pxPerYear) {
            var raw = TARGET_TICK_PX / pxPerYear;
            for (var i = 0; i < NICE_STEPS.length; i++) {
                if (NICE_STEPS[i] >= raw) { return NICE_STEPS[i]; }
            }
            return NICE_STEPS[NICE_STEPS.length - 1];
        };

        var yearToX = function (year, width) {
            return width / 2 + (year - view.center) * view.pxPerYear;
        };

        var formatYear = function (year) {
            var n = year < 0 ? '−' + Math.abs(year) : String(year);
            return epochAbbr ? n + ' ' + epochAbbr : n;
        };

        var say = function (message) {
            if (!note) { return; }
            note.textContent = message || '';
            note.hidden = !message;
        };

        /* --- the hover card — the same card the pinboard uses --- */

        var tellAbout = function (item, yearsText) {
            if (!tip) { return; }
            tip.textContent = '';

            var strong = document.createElement('strong');
            strong.textContent = item.title;
            var span = document.createElement('span');
            span.textContent = item.archive + ' — ' + item.field + ' — ' + yearsText;

            tip.appendChild(strong);
            tip.appendChild(span);
            tip.hidden = false;
        };

        var hideTip = function () { if (tip) { tip.hidden = true; } };

        stage.addEventListener('pointermove', function (event) {
            if (!tip || tip.hidden) { return; }

            var rect = stage.getBoundingClientRect();
            var x = event.clientX - rect.left + 14;
            var y = event.clientY - rect.top + 14;
            var size = tip.getBoundingClientRect();

            if (x + size.width > rect.width) { x = Math.max(0, x - size.width - 28); }
            if (y + size.height > rect.height) { y = Math.max(0, y - size.height - 28); }

            tip.style.left = Math.round(x) + 'px';
            tip.style.top = Math.round(y) + 'px';
        });

        /* --- rendering --- */

        var renderAxis = function (width) {
            axisLayer.textContent = '';

            var line = document.createElement('div');
            line.className = 'timeline-axis-line';
            axisLayer.appendChild(line);

            var step = niceStep(view.pxPerYear);
            var leftYear = view.center - (width / 2) / view.pxPerYear;
            var rightYear = view.center + (width / 2) / view.pxPerYear;
            var first = Math.ceil(leftYear / step) * step;

            for (var year = first; year <= rightYear; year += step) {
                var x = yearToX(year, width);

                var tick = document.createElement('div');
                tick.className = 'timeline-tick';
                tick.style.left = Math.round(x) + 'px';
                axisLayer.appendChild(tick);

                var label = document.createElement('div');
                label.className = 'timeline-tick-label';
                label.style.left = Math.round(x) + 'px';
                label.textContent = formatYear(year);
                axisLayer.appendChild(label);
            }

            // Year zero — the epoch itself — marked apart from the rest,
            // whenever it is anywhere near what is actually on screen.
            if (0 >= leftYear - step && 0 <= rightYear + step) {
                var ex = yearToX(0, width);

                var epoch = document.createElement('div');
                epoch.className = 'timeline-epoch';
                epoch.style.left = Math.round(ex) + 'px';
                axisLayer.appendChild(epoch);

                var epochLabel = document.createElement('div');
                epochLabel.className = 'timeline-epoch-label';
                epochLabel.style.left = Math.round(ex) + 'px';
                epochLabel.textContent = epochName || '0';
                axisLayer.appendChild(epochLabel);
            }
        };

        var renderPoints = function (width, midY) {
            pointsLayer.textContent = '';

            // Only what is roughly on screen, left to right, so the
            // row-staggering below reads consistently as the view pans.
            var visible = points
                .map(function (p, i) { return { p: p, i: i, x: yearToX(p.year, width) }; })
                .filter(function (item) {
                    return !hidden[item.p.category] && item.x > -80 && item.x < width + 80;
                })
                .sort(function (a, b) { return a.x - b.x; });

            var rowLastX = [-Infinity, -Infinity, -Infinity];
            var MIN_GAP = 70;

            visible.forEach(function (item) {
                var row = rowLastX.length - 1;
                for (var r = 0; r < rowLastX.length; r++) {
                    if (item.x - rowLastX[r] >= MIN_GAP) { row = r; break; }
                }
                rowLastX[row] = item.x;

                var el = document.createElement('a');
                el.className = 'timeline-point';
                el.href = siteBase + item.p.url;
                el.style.left = Math.round(item.x) + 'px';
                el.style.top = (midY - row * 22) + 'px';
                el.style.setProperty('--point-color', item.p.color || 'var(--accent)');
                if (focusKey === 'p:' + item.i) { el.classList.add('is-focused'); }

                var dot = document.createElement('span');
                dot.className = 'timeline-point-dot';
                el.appendChild(dot);

                var pointLabel = document.createElement('span');
                pointLabel.className = 'timeline-point-label';
                pointLabel.textContent = item.p.title;
                el.appendChild(pointLabel);

                el.addEventListener('pointerenter', function () {
                    tellAbout(item.p, formatYear(item.p.year));
                });
                el.addEventListener('pointerleave', hideTip);

                pointsLayer.appendChild(el);
            });
        };

        var renderEras = function (width, midY) {
            erasLayer.textContent = '';

            var visible = eras
                .map(function (e, i) {
                    return { e: e, i: i, x1: yearToX(e.from, width), x2: yearToX(e.to, width) };
                })
                .filter(function (item) {
                    return !hidden[item.e.category] && item.x2 > -40 && item.x1 < width + 40;
                })
                .sort(function (a, b) { return a.x1 - b.x1; });

            var lanes = assignLanes(visible,
                function (item) { return item.x1; },
                function (item) { return item.x2; },
                60);

            visible.forEach(function (item, i) {
                var lane = lanes[i];
                var w = Math.max(6, item.x2 - item.x1);
                var color = item.e.color || '';

                var el = document.createElement('a');
                el.className = 'timeline-era';
                el.href = siteBase + item.e.url;
                el.style.left = Math.round(item.x1) + 'px';
                el.style.width = Math.round(w) + 'px';
                // Clear of the tick labels sitting just under the axis line,
                // not stacked right on top of them.
                el.style.top = (midY + 32 + lane * 24) + 'px';
                el.style.setProperty('--era-color', color || 'var(--accent)');
                el.style.color = textOn(color);
                if (focusKey === 'e:' + item.i) { el.classList.add('is-focused'); }
                el.textContent = item.e.title;

                el.addEventListener('pointerenter', function () {
                    tellAbout(item.e, formatYear(item.e.from) + ' – ' + formatYear(item.e.to));
                });
                el.addEventListener('pointerleave', hideTip);

                erasLayer.appendChild(el);
            });
        };

        var render = function () {
            var width = stage.clientWidth || 900;
            var height = stage.clientHeight || 400;

            renderAxis(width);
            renderEras(width, height / 2);
            renderPoints(width, height / 2);
        };

        /* --- panning and zooming --- */

        var panning = null;

        stage.addEventListener('pointerdown', function (event) {
            if (event.button !== 0) { return; }
            if (event.target.closest('.timeline-point, .timeline-era, button, a')) { return; }

            panning = { x: event.clientX, startCenter: view.center, held: false };
            stage.classList.add('is-dragging');
        });

        stage.addEventListener('pointermove', function (event) {
            if (!panning) { return; }

            if (!panning.held) {
                if (Math.abs(event.clientX - panning.x) < 4) { return; }
                panning.held = true;
                try { stage.setPointerCapture(event.pointerId); } catch (e) { /* gone already */ }
            }

            view.center = panning.startCenter - (event.clientX - panning.x) / view.pxPerYear;
            render();
        });

        var endPan = function (event) {
            if (panning && panning.held && stage.hasPointerCapture(event.pointerId)) {
                stage.releasePointerCapture(event.pointerId);
            }
            panning = null;
            stage.classList.remove('is-dragging');
        };
        stage.addEventListener('pointerup', endPan);
        stage.addEventListener('pointercancel', endPan);

        // Keeps the year under the cursor fixed while the wheel zooms, the
        // same way the world map keeps the point under the cursor fixed.
        var zoomAt = function (factor, clientX) {
            var rect = stage.getBoundingClientRect();
            var atX = clientX === undefined ? rect.width / 2 : clientX - rect.left;
            var yearUnderCursor = view.center + (atX - rect.width / 2) / view.pxPerYear;

            view.pxPerYear = Math.min(MAX_PX_PER_YEAR, Math.max(MIN_PX_PER_YEAR, view.pxPerYear * factor));
            view.center = yearUnderCursor - (atX - rect.width / 2) / view.pxPerYear;
            render();
        };

        stage.addEventListener('wheel', function (event) {
            event.preventDefault();
            zoomAt(event.deltaY < 0 ? 1.2 : 1 / 1.2, event.clientX);
        }, { passive: false });

        var zoomInButton = root.querySelector('[data-timeline-zoom-in]');
        var zoomOutButton = root.querySelector('[data-timeline-zoom-out]');
        var fitButton = root.querySelector('[data-timeline-fit]');

        if (zoomInButton) { zoomInButton.addEventListener('click', function () { zoomAt(1.5); }); }
        if (zoomOutButton) { zoomOutButton.addEventListener('click', function () { zoomAt(1 / 1.5); }); }
        if (fitButton) {
            fitButton.addEventListener('click', function () { fit(0); render(); });
        }

        window.addEventListener('resize', render);

        /* --- the archive filter ------------------------------------------- */

        var archiveBoxes = root.querySelectorAll('[data-timeline-archive]');

        var dressFilter = function () {
            Array.prototype.forEach.call(archiveBoxes, function (box) {
                var off = !!hidden[box.dataset.timelineArchive];
                box.checked = !off;
                box.closest('.pinboard-archive').classList.toggle('is-off', off);
            });
        };
        dressFilter();

        Array.prototype.forEach.call(archiveBoxes, function (box) {
            box.addEventListener('change', function () {
                var id = box.dataset.timelineArchive;

                if (box.checked) { delete hidden[id]; } else { hidden[id] = true; }

                rememberHidden();
                dressFilter();
                render();
            });
        });

        var archivesAllButton = root.querySelector('[data-timeline-archives-all]');
        if (archivesAllButton) {
            archivesAllButton.addEventListener('click', function () {
                hidden = {};
                rememberHidden();
                dressFilter();
                render();
            });
        }

        /* --- talking to the server --- */

        var findFocusYear = function (entryId) {
            for (var i = 0; i < points.length; i++) {
                if (points[i].entry_id === entryId) { return { year: points[i].year, key: 'p:' + i }; }
            }
            for (var j = 0; j < eras.length; j++) {
                if (eras[j].entry_id === entryId) {
                    return { year: (eras[j].from + eras[j].to) / 2, key: 'e:' + j };
                }
            }
            return null;
        };

        /** Centres on one entry if asked and it has a year, else on everything there is. */
        var fit = function (entryId) {
            var width = stage.clientWidth || 900;

            if (entryId) {
                var found = findFocusYear(entryId);
                if (found) {
                    focusKey = found.key;
                    view.center = found.year;
                    view.pxPerYear = Math.min(MAX_PX_PER_YEAR, Math.max(MIN_PX_PER_YEAR, width / 200));
                    return;
                }
            }

            var years = [];
            points.forEach(function (p) { if (!hidden[p.category]) { years.push(p.year); } });
            eras.forEach(function (e) {
                if (!hidden[e.category]) { years.push(e.from, e.to); }
            });

            if (!years.length) {
                view.center = 0;
                view.pxPerYear = 4;
                return;
            }

            var min = Math.min.apply(null, years);
            var max = Math.max.apply(null, years);
            var span = Math.max(max - min, 10);

            view.center = (min + max) / 2;
            // A little air on each side, so the earliest and latest events
            // are not pinned to the very edge of the stage.
            view.pxPerYear = Math.min(MAX_PX_PER_YEAR, Math.max(MIN_PX_PER_YEAR, width / (span * 1.3)));
        };

        var load = function () {
            return fetch(api + '/events', { headers: { 'Accept': 'application/json' } })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data.ok) { throw new Error('refused'); }

                    points = data.points || [];
                    eras = data.eras || [];

                    if (blank) { blank.hidden = points.length > 0 || eras.length > 0; }

                    fit(focusId);
                    // A fresh visit opens on the epoch itself at a fixed zoom, not fit-to-data.
                    if (!focusId) {
                        view.center = 0;
                        view.pxPerYear = INITIAL_PX_PER_YEAR;
                    }
                    render();
                })
                .catch(function () { say(t('The timeline could not be reached.')); });
        };

        load();
    }

    /* --- calendar math shared by the datepicker and the calendar grid ----
       Mirrors App\Calendar (src/Calendar.php) by hand rather than fetched,
       so it can run on every keystroke with no round trip. */

    function readCalendarConfig() {
        var el = document.querySelector('[data-calendar-config]');
        if (!el) { return null; }
        try {
            var config = JSON.parse(el.textContent);
            return config && config.months && config.months.length ? config : null;
        } catch (e) {
            return null;
        }
    }

    function calendarFloorMod(a, n) {
        return ((a % n) + n) % n;
    }

    function calendarMonthLength(config, year, month) {
        var months = config.months || [];
        var index = month - 1;
        if (index < 0 || index >= months.length) { return 0; }

        var days = months[index].days || 0;

        (config.leap_rules || []).forEach(function (rule) {
            if (rule.month !== month) { return; }
            var every = Math.max(1, rule.every_years || 1);
            var offset = rule.offset || 0;
            if (calendarFloorMod(year - offset, every) === 0) {
                days += rule.extra_days || 0;
            }
        });

        return Math.max(0, days);
    }

    /** Mirrors App\Calendar::slots(). */
    function calendarSlots(config, year) {
        var months = config.months || [];
        var count = months.length;

        var byPosition = {};
        (config.intercalary || []).forEach(function (block, i) {
            var after = Math.max(0, Math.min(count, block.after_month || 0));
            byPosition[after] = byPosition[after] || [];
            byPosition[after].push({
                kind: 'intercalary', ref: i, label: block.name, days: Math.max(0, block.days || 0),
            });
        });

        var slots = (byPosition[0] || []).slice();
        months.forEach(function (month, index) {
            var number = index + 1;
            slots.push({
                kind: 'month', ref: number, label: month.name,
                days: calendarMonthLength(config, year, number),
            });
            (byPosition[number] || []).forEach(function (slot) { slots.push(slot); });
        });

        return slots;
    }

    /* --- the Year / Month / Day picker on a Date or Era field -------------- */

    /**
     * One Year/Month/Day picker. Exposed on its own since a freshly-cloned
     * repeatable row (a Cycle holiday's reference date) needs it wired individually.
     */
    function wireDatePicker(picker, config) {
        var yearInput = picker.querySelector('[data-date-year]');
        var slotSelect = picker.querySelector('[data-date-slot]');
        var daySelect = picker.querySelector('[data-date-day]');
        if (!yearInput || !slotSelect || !daySelect) { return; }

        var slotDays = function (slotValue) {
            var m = /^([mi]):(\d+)$/.exec(slotValue || '');
            if (!m) { return 0; }

            if (m[1] === 'i') {
                var block = (config.intercalary || [])[parseInt(m[2], 10)];
                return block ? Math.max(0, block.days || 0) : 0;
            }

            var year = parseInt(yearInput.value, 10);
            return calendarMonthLength(config, isNaN(year) ? 1 : year, parseInt(m[2], 10));
        };

        var refreshDays = function () {
            var count = slotSelect.value ? slotDays(slotSelect.value) : 0;
            var current = parseInt(daySelect.value, 10);
            daySelect.innerHTML = '';

            if (count === 0) {
                var placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = '—';
                daySelect.appendChild(placeholder);
                return;
            }

            for (var d = 1; d <= count; d++) {
                var opt = document.createElement('option');
                opt.value = String(d);
                opt.textContent = String(d);
                daySelect.appendChild(opt);
            }

            daySelect.value = String(isNaN(current) ? 1 : Math.min(Math.max(current, 1), count));
        };

        yearInput.addEventListener('input', refreshDays);
        slotSelect.addEventListener('change', refreshDays);
    }

    function initDatePickers() {
        var config = readCalendarConfig();
        if (!config) { return; }

        document.querySelectorAll('[data-date-picker]').forEach(function (picker) {
            wireDatePicker(picker, config);
        });
    }

    /* --- the month-grid calendar view, linked from the Timeline ------------ */

    function initTimelineCalendar() {
        var root = document.querySelector('[data-calendar-grid]');
        if (!root) { return; }

        var config = readCalendarConfig();
        if (!config) { return; }

        // Resolved server-side; see App\Calendar::holidaysForSurface(). Each is a day -> [names] map.
        var holidayMaps = { month: {}, intercalary: [] };
        var holidaysEl = document.querySelector('[data-calendar-holidays]');
        if (holidaysEl) {
            try {
                var parsedHolidays = JSON.parse(holidaysEl.textContent);
                holidayMaps.month = parsedHolidays.month || {};
                holidayMaps.intercalary = parsedHolidays.intercalary || [];
            } catch (e) { /* left at the empty defaults above */ }
        }

        var siteBase = root.dataset.base || '';
        var year = parseInt(root.dataset.year, 10);
        var month = parseInt(root.dataset.month, 10);
        var startWeekday = root.dataset.startWeekday === '' ? 0 : parseInt(root.dataset.startWeekday, 10);
        var monthDays = parseInt(root.dataset.monthDays, 10) || 0;
        var epochAbbr = root.dataset.epochAbbr || '';
        var weekdayCount = (config.weekdays || []).length || 1;

        var weekdayRow = root.querySelector('[data-cal-weekday-row]');
        var weeksEl = root.querySelector('[data-cal-weeks]');
        var stripBefore = root.querySelector('[data-cal-strip-before]');
        var stripAfter = root.querySelector('[data-cal-strip-after]');
        var blank = root.querySelector('[data-cal-blank]');
        var note = root.querySelector('[data-cal-note]');
        var stage = root.querySelector('[data-cal-stage]');
        var tip = root.querySelector('[data-cal-tip]');
        var filter = root.querySelector('[data-timeline-filter]');
        if (!weeksEl || !stage) { return; }

        /* --- which archives may show, shared with the linear timeline --- */

        var hidden = {};
        try {
            (JSON.parse(localStorage.getItem('wb-timeline-hidden') || '[]') || [])
                .forEach(function (id) { hidden[id] = true; });
        } catch (e) { /* private mode, or nonsense in storage */ }

        var rememberHidden = function () {
            try {
                localStorage.setItem('wb-timeline-hidden', JSON.stringify(Object.keys(hidden)));
            } catch (e) { /* private mode */ }
        };

        if (filter) {
            var checkboxes = filter.querySelectorAll('[data-timeline-archive]');
            checkboxes.forEach(function (cb) {
                cb.checked = !hidden[cb.dataset.timelineArchive];
                cb.addEventListener('change', function () {
                    if (cb.checked) { delete hidden[cb.dataset.timelineArchive]; }
                    else { hidden[cb.dataset.timelineArchive] = true; }
                    rememberHidden();
                    render();
                });
            });

            var allButton = filter.querySelector('[data-timeline-archives-all]');
            if (allButton) {
                allButton.addEventListener('click', function () {
                    hidden = {};
                    rememberHidden();
                    checkboxes.forEach(function (cb) { cb.checked = true; });
                    render();
                });
            }
        }

        var say = function (message) {
            if (!note) { return; }
            note.textContent = message || '';
            note.hidden = !message;
        };

        /* --- the hover card — the same card the linear timeline uses --- */

        var tellAbout = function (item, dateText) {
            if (!tip) { return; }
            tip.textContent = '';

            var strong = document.createElement('strong');
            strong.textContent = item.title;
            var span = document.createElement('span');
            span.textContent = item.archive + ' — ' + item.field + ' — ' + dateText;

            tip.appendChild(strong);
            tip.appendChild(span);
            tip.hidden = false;
        };

        var hideTip = function () { if (tip) { tip.hidden = true; } };

        stage.addEventListener('pointermove', function (event) {
            if (!tip || tip.hidden) { return; }

            var rect = stage.getBoundingClientRect();
            var x = event.clientX - rect.left + 14;
            var y = event.clientY - rect.top + 14;
            var size = tip.getBoundingClientRect();

            if (x + size.width > rect.width) { x = Math.max(0, x - size.width - 28); }
            if (y + size.height > rect.height) { y = Math.max(0, y - size.height - 28); }

            tip.style.left = Math.round(x) + 'px';
            tip.style.top = Math.round(y) + 'px';
        });

        /* --- date formatting for the hover card ------------------------- */

        var formatDate = function (y, kind, ref, day) {
            var label;
            if (kind === 'intercalary') {
                var block = (config.intercalary || [])[ref];
                label = 'day of ' + (block ? block.name : 'special days');
            } else {
                var m = (config.months || [])[ref - 1];
                label = m ? m.name : ('Month ' + ref);
            }

            var yearText = y < 0 ? '−' + Math.abs(y) : String(y);
            if (epochAbbr) { yearText += ' ' + epochAbbr; }

            return ordinal(day) + ' ' + label + ', ' + yearText;
        };

        /* --- slot ordering, to tell whether an era crosses this month's edges --- */

        var slotOrder = {};
        calendarSlots(config, year).forEach(function (slot, i) {
            slotOrder[slot.kind + ':' + slot.ref] = i;
        });
        var viewedOrder = slotOrder['month:' + month];

        // -Infinity/Infinity for a different year sorts a whole year's worth
        // of slots before/after anything in the viewed year in one comparison.
        var slotWeight = function (y, kind, ref) {
            if (y < year) { return -Infinity; }
            if (y > year) { return Infinity; }
            var known = slotOrder[kind + ':' + ref];
            return known === undefined ? (kind === 'month' ? ref : 0) : known;
        };

        /* --- fetching --- */

        var points = [];
        var eras = [];

        var load = function () {
            return fetch(siteBase + '/timeline/events', { headers: { 'Accept': 'application/json' } })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data.ok) { throw new Error('refused'); }
                    points = (data.points || []).filter(function (p) { return p.kind; });
                    eras = (data.eras || []).filter(function (e) { return e.fromKind && e.toKind; });
                    render();
                })
                .catch(function () { say(t('The calendar could not be reached.')); });
        };

        /* --- one surface: a month grid, or the single-row intercalary strip --- */

        // Returns the built rows: [[{day, col}, ...], ...].
        var buildRows = function (dayCount, firstCol, columns) {
            var rows = [];
            var row = [];
            for (var c = 0; c < firstCol; c++) { row.push(null); }
            for (var day = 1; day <= dayCount; day++) {
                row.push(day);
                if (row.length === columns) { rows.push(row); row = []; }
            }
            if (row.length > 0) {
                while (row.length < columns) { row.push(null); }
                rows.push(row);
            }
            return rows;
        };

        // Matching points/eras for one surface (a month, or one intercalary
        // block), rendered into `container` as `rows` of `columns` cells.
        var renderSurface = function (container, rows, columns, kind, ref, dayCount, holidayMap) {
            container.innerHTML = '';
            if (rows.length === 0) { return; }

            rows.forEach(function (row) {
                var rowEl = document.createElement('div');
                rowEl.className = 'cal-week';
                rowEl.style.setProperty('--cal-columns', columns);

                row.forEach(function (day) {
                    var cell = document.createElement('div');
                    cell.className = 'cal-day' + (day === null ? ' cal-day--blank' : '');
                    if (day !== null) {
                        var num = document.createElement('span');
                        num.className = 'cal-day-num';
                        num.textContent = String(day);
                        cell.appendChild(num);

                        var holidayNames = (holidayMap || {})[day];
                        if (holidayNames && holidayNames.length > 0) {
                            cell.classList.add('cal-day--holiday');
                            var holidayLabel = document.createElement('span');
                            holidayLabel.className = 'cal-day-holiday';
                            holidayLabel.textContent = holidayNames.join(', ');
                            holidayLabel.title = holidayLabel.textContent;
                            cell.appendChild(holidayLabel);
                        }

                        var dots = document.createElement('div');
                        dots.className = 'cal-day-dots';
                        cell.appendChild(dots);

                        var matches = points.filter(function (p) {
                            return !hidden[p.category] && p.year === year && p.kind === kind
                                && p.ref === ref && p.day === day;
                        });

                        matches.slice(0, 4).forEach(function (p) {
                            var entry = document.createElement('a');
                            entry.className = 'cal-entry';
                            entry.href = siteBase + p.url;
                            entry.style.setProperty('--point-color', p.color || 'var(--accent)');
                            entry.addEventListener('pointerenter', function () {
                                tellAbout(p, formatDate(p.year, p.kind, p.ref, p.day));
                            });
                            entry.addEventListener('pointerleave', hideTip);

                            var dot = document.createElement('span');
                            dot.className = 'cal-dot';
                            entry.appendChild(dot);

                            var name = document.createElement('span');
                            name.className = 'cal-entry-name';
                            name.textContent = p.title;
                            entry.appendChild(name);

                            dots.appendChild(entry);
                        });

                        if (matches.length > 4) {
                            var more = document.createElement('span');
                            more.className = 'cal-dot-more';
                            more.textContent = '+' + (matches.length - 4);
                            dots.appendChild(more);
                        }
                    }
                    rowEl.appendChild(cell);
                });

                container.appendChild(rowEl);
            });

            renderEraBars(container, rows, columns, kind, ref, dayCount);
        };

        var renderEraBars = function (container, rows, columns, kind, ref, dayCount) {
            var here = slotWeight(year, kind, ref);

            var relevant = eras.filter(function (e) {
                if (hidden[e.category]) { return false; }
                var startsBefore = slotWeight(e.from, e.fromKind, e.fromRef) <= here;
                var endsAfter = slotWeight(e.to, e.toKind, e.toRef) >= here;
                return startsBefore && endsAfter;
            });
            if (relevant.length === 0) { return; }

            var segmentsByRow = {};

            relevant.forEach(function (era) {
                // A non-exact slot match means the real endpoint is in another slot,
                // so this surface only sees the truncated edge.
                var truncStart = slotWeight(era.from, era.fromKind, era.fromRef) < here;
                var truncEnd = slotWeight(era.to, era.toKind, era.toRef) > here;
                var startDay = truncStart ? 1 : era.fromDay;
                var endDay = truncEnd ? dayCount : era.toDay;

                rows.forEach(function (row, rowIndex) {
                    var firstDay = null, lastDay = null;
                    row.forEach(function (day, col) {
                        if (day === null) { return; }
                        if (day >= startDay && day <= endDay) {
                            if (firstDay === null) { firstDay = { day: day, col: col }; }
                            lastDay = { day: day, col: col };
                        }
                    });
                    if (firstDay === null) { return; }

                    segmentsByRow[rowIndex] = segmentsByRow[rowIndex] || [];
                    segmentsByRow[rowIndex].push({
                        era: era,
                        colStart: firstDay.col,
                        colEnd: lastDay.col,
                        truncStart: truncStart || firstDay.day !== startDay,
                        truncEnd: truncEnd || lastDay.day !== endDay,
                    });
                });
            });

            var rowEls = container.querySelectorAll('.cal-week');

            Object.keys(segmentsByRow).forEach(function (rowIndex) {
                var segments = segmentsByRow[rowIndex];
                var lanes = assignLanes(segments,
                    function (s) { return s.colStart; },
                    function (s) { return s.colEnd; },
                    0);

                var overlay = document.createElement('div');
                overlay.className = 'cal-era-overlay';

                segments.forEach(function (segment, i) {
                    var lane = lanes[i];
                    var el = document.createElement('a');
                    el.className = 'cal-era-bar'
                        + (segment.truncStart ? ' cal-era-bar--open-start' : '')
                        + (segment.truncEnd ? ' cal-era-bar--open-end' : '');
                    el.href = siteBase + segment.era.url;
                    el.style.left = (segment.colStart / columns * 100) + '%';
                    el.style.width = ((segment.colEnd - segment.colStart + 1) / columns * 100) + '%';
                    el.style.top = (lane * 20) + 'px';
                    el.style.setProperty('--era-color', segment.era.color || 'var(--accent)');
                    el.style.color = textOn(segment.era.color);
                    el.textContent = segment.era.title;

                    el.addEventListener('pointerenter', function () {
                        tellAbout(segment.era,
                            formatDate(segment.era.from, segment.era.fromKind, segment.era.fromRef, segment.era.fromDay)
                            + ' – '
                            + formatDate(segment.era.to, segment.era.toKind, segment.era.toRef, segment.era.toDay));
                    });
                    el.addEventListener('pointerleave', hideTip);

                    overlay.appendChild(el);
                });

                var maxLane = Math.max.apply(null, lanes);
                overlay.style.height = ((maxLane + 1) * 20) + 'px';
                rowEls[rowIndex].appendChild(overlay);
                rowEls[rowIndex].style.paddingBottom = ((maxLane + 1) * 20) + 'px';
            });
        };

        var render = function () {
            if (weekdayRow) {
                weekdayRow.innerHTML = '';
                (config.weekdays || []).forEach(function (name) {
                    var cell = document.createElement('div');
                    cell.className = 'cal-weekday-name';
                    cell.textContent = name;
                    weekdayRow.appendChild(cell);
                });
            }

            var rows = buildRows(monthDays, startWeekday, weekdayCount);
            renderSurface(weeksEl, rows, weekdayCount, 'month', month, monthDays, holidayMaps.month);

            var beforeBlocks = (config.intercalary || [])
                .map(function (b, i) { return { block: b, index: i }; })
                .filter(function (x) { return month === 1 && (x.block.after_month || 0) === 0; });
            var afterBlocks = (config.intercalary || [])
                .map(function (b, i) { return { block: b, index: i }; })
                .filter(function (x) { return (x.block.after_month || 0) === month; });

            var renderStrip = function (container, blocks) {
                if (!container) { return; }
                container.innerHTML = '';
                container.hidden = blocks.length === 0;

                blocks.forEach(function (x) {
                    var heading = document.createElement('h3');
                    heading.className = 'cal-strip-title';
                    heading.textContent = x.block.name + ' (' + x.block.days + (x.block.days === 1 ? ' day' : ' days') + ')';
                    container.appendChild(heading);

                    var body = document.createElement('div');
                    body.className = 'cal-strip-body';
                    container.appendChild(body);

                    var stripRows = buildRows(x.block.days, 0, Math.max(1, x.block.days));
                    renderSurface(body, stripRows, Math.max(1, x.block.days), 'intercalary', x.index, x.block.days,
                        holidayMaps.intercalary[x.index]);
                });
            };

            renderStrip(stripBefore, beforeBlocks);
            renderStrip(stripAfter, afterBlocks);

            if (blank) {
                var anyPoints = points.some(function (p) {
                    return !hidden[p.category] && p.year === year
                        && ((p.kind === 'month' && p.ref === month)
                            || (p.kind === 'intercalary' && afterBlocks.concat(beforeBlocks).some(function (x) { return x.index === p.ref; })));
                });
                var anyEras = eras.some(function (e) {
                    if (hidden[e.category]) { return false; }
                    var startsBefore = slotWeight(e.from, e.fromKind, e.fromRef) <= viewedOrder;
                    var endsAfter = slotWeight(e.to, e.toKind, e.toRef) >= viewedOrder;
                    return startsBefore && endsAfter;
                });
                blank.hidden = anyPoints || anyEras;
            }
        };

        load();
    }

    /* --- the repeatable month/weekday/rule lists on Settings -> Calendar --- */

    /**
     * One add/remove/reorder list, generic over what a row holds, so the
     * Settings -> Calendar lists don't each need their own copy.
     *
     * `onWire`, if given, runs on every row (existing and newly added) for
     * whatever a list needs beyond add/remove/reorder/dirty.
     */
    function initRepeatableRows(list, template, addButton, markDirty, onWire) {
        if (!list) { return; }

        var reindex = function () {
            list.querySelectorAll('[data-repeat-row]').forEach(function (row, index) {
                row.querySelectorAll('[data-name-template]').forEach(function (input) {
                    input.name = input.dataset.nameTemplate.replace('__i__', index);
                });
            });
        };

        var wireRow = function (row) {
            var remove = row.querySelector('[data-remove-row]');
            if (remove) {
                remove.addEventListener('click', function () {
                    row.remove();
                    reindex();
                    if (markDirty) { markDirty(); }
                });
            }

            row.querySelectorAll('input, select').forEach(function (input) {
                input.addEventListener('input', function () { if (markDirty) { markDirty(); } });
                input.addEventListener('change', function () { if (markDirty) { markDirty(); } });
            });

            makeDraggable(row, list, function () {
                reindex();
                if (markDirty) { markDirty(); }
            });

            if (onWire) { onWire(row); }
        };

        list.querySelectorAll('[data-repeat-row]').forEach(wireRow);
        reindex();

        if (addButton && template) {
            addButton.addEventListener('click', function () {
                var fragment = template.content.cloneNode(true);
                var row = fragment.querySelector('[data-repeat-row]');
                list.appendChild(fragment);

                wireRow(row);
                reindex();
                if (markDirty) { markDirty(); }

                var firstInput = row.querySelector('input');
                if (firstInput) { firstInput.focus(); }
            });
        }
    }

    function initCalendarSettings() {
        var root = document.querySelector('[data-calendar-settings]');
        if (!root) { return; }

        var config = readCalendarConfig();
        var dirtyHint = root.querySelector('[data-dirty-hint]');
        var markDirty = function () { if (dirtyHint) { dirtyHint.hidden = false; } };

        // A holiday row shows only the fields its own type actually uses.
        var applyHolidayTypeVisibility = function (row) {
            var type = row.querySelector('[data-holiday-type]');
            if (!type) { return; }

            row.querySelectorAll('[data-holiday-for]').forEach(function (group) {
                group.hidden = group.dataset.holidayFor !== type.value;
            });
        };

        var wireHolidayRow = function (row) {
            applyHolidayTypeVisibility(row);

            var type = row.querySelector('[data-holiday-type]');
            if (type) {
                type.addEventListener('change', function () { applyHolidayTypeVisibility(row); });
            }

            // A Cycle row's date picker, cloned after initDatePickers()'s page-load pass.
            if (config) {
                row.querySelectorAll('[data-date-picker]').forEach(function (picker) {
                    wireDatePicker(picker, config);
                });
            }
        };

        ['months', 'weekdays', 'intercalary', 'leap_rules'].forEach(function (key) {
            var list = root.querySelector('[data-repeat-list="' + key + '"]');
            var template = root.querySelector('[data-repeat-template="' + key + '"]');
            var addButton = root.querySelector('[data-repeat-add="' + key + '"]');
            initRepeatableRows(list, template, addButton, markDirty);
        });

        initRepeatableRows(
            root.querySelector('[data-repeat-list="holidays"]'),
            root.querySelector('[data-repeat-template="holidays"]'),
            root.querySelector('[data-repeat-add="holidays"]'),
            markDirty,
            wireHolidayRow
        );
    }
})();
