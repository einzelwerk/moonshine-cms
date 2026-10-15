/*
 * The Alpine component of the block editor.
 *
 * It owns four things and nothing else: which cards are open, which language
 * the translatable inputs show, which cards carry unsaved changes, and the
 * drag and drop of the cards. Everything that writes goes through an address
 * of the panel; the structure itself is never decided here.
 *
 * Sorting rides the sortable of MoonShine, so the page grows no dependency of
 * its own. The request it can send on its own carries no slot, so the move is
 * sent from the callback instead: a card dropped into a named slot of a
 * container has to say which one.
 */

/* How long a rich text editor is given to build itself before the cards look
   for it, and how long before they look one last time. */
const RICH_TEXT_DELAY = 400;
const RICH_TEXT_RETRY = 1600;

document.addEventListener('alpine:init', () => {
    window.Alpine.data('cmsBlockEditor', (config = {}) => ({
        token: config.token || '',
        endpoints: config.endpoints || {},
        labels: config.labels || {},
        locales: config.locales || [],
        locale: config.defaultLocale || '',
        event: config.event || '',
        stateKey: config.stateKey || 'cms-editor',
        modal: config.modal || 'cms-library',
        group: config.group || 'cms-blocks',
        ids: config.ids || [],
        open: {},
        clean: {},
        touched: {},
        dirty: [],
        saving: 0,

        init() {
            this.open = this.restore('open', {});
            this.locale = this.restore('locale', this.locale) || this.locale;

            this.$nextTick(() => {
                this.baseline();
                this.bindSortables();
                this.bindRichText();
                this.refreshEmpty();
            });

            // A rich text editor builds itself on its own schedule, and the
            // panel offers no event for it: the cards are looked at again a
            // little later, and a second time for a slow one.
            [RICH_TEXT_DELAY, RICH_TEXT_RETRY].forEach(delay => {
                window.setTimeout(() => this.bindRichText(), delay);
            });
        },

        /* ------------------------------------------------- what is remembered */

        restore(key, fallback) {
            try {
                const stored = sessionStorage.getItem(this.stateKey + ':' + key);

                return stored === null ? fallback : JSON.parse(stored);
            } catch (e) {
                return fallback;
            }
        },

        persist(key, value) {
            try {
                sessionStorage.setItem(this.stateKey + ':' + key, JSON.stringify(value));
            } catch (e) {
                // A browser that refuses storage still edits blocks.
            }
        },

        isOpen(id) {
            return this.open[id] === true;
        },

        toggle(id) {
            this.open[id] = !this.isOpen(id);
            this.persist('open', this.open);
            this.$nextTick(() => this.bindRichText());
        },

        expandAll() {
            this.ids.forEach(id => {
                this.open[id] = true;
            });
            this.persist('open', this.open);
        },

        collapseAll() {
            this.open = {};
            this.persist('open', this.open);
        },

        /* ---------------------------------------------------------- language */

        setLocale(locale) {
            this.locale = locale;
            this.persist('locale', locale);
            this.$nextTick(() => this.refreshEmpty());
        },

        // Which translatable inputs are still empty in the language they carry.
        // The mark sits on the wrapper the adapter tagged, so a language the
        // catalogue adds later needs no change here.
        refreshEmpty() {
            this.$root.querySelectorAll('[data-cms-locale]').forEach(wrapper => {
                const inputs = wrapper.querySelectorAll('input, textarea, select');
                let filled = false;

                inputs.forEach(input => {
                    if (input.type !== 'hidden' && String(input.value || '').trim() !== '') {
                        filled = true;
                    }
                });

                filled
                    ? wrapper.removeAttribute('data-cms-empty')
                    : wrapper.setAttribute('data-cms-empty', '');
            });
        },

        /* ------------------------------------------------------- dirty cards */

        forms() {
            return Array.from(this.$root.querySelectorAll('form[data-cms-form]'));
        },

        formOf(id) {
            return this.$root.querySelector('form[data-cms-form="' + id + '"]');
        },

        // What the card holds right now, control by control. Deliberately not
        // `FormData`: a control the panel would not send is missing from it,
        // and whether it would send one changes while the form is being
        // built. A rich text editor takes the name off its textarea, and so
        // does a field the panel hides by the value of another. Read where it
        // stands instead, a control is the same before and after that.
        //
        // A file is compared by name and size: the value itself never survives
        // a serialisation.
        serialize(form) {
            const parts = [];

            form.querySelectorAll('input, select, textarea').forEach(element => {
                const key = this.keyOf(element);

                if (key !== null) {
                    parts.push(key + '=' + this.fieldValue(element));
                }
            });

            return parts.join('&');
        },

        // The name the card was drawn with, kept on the element: a widget that
        // renames or unnames an input must not rename what it is measured by.
        // A switch and a radio are keyed by their value as well, because the
        // group shares one name.
        keyOf(element) {
            if (element.dataset.cmsKey === undefined) {
                const name = element.getAttribute('name') || element.id;

                if (name === '' || name === '_token') {
                    return null;
                }

                element.dataset.cmsKey = element.type === 'checkbox' || element.type === 'radio'
                    ? name + '#' + element.value
                    : name;
            }

            return element.dataset.cmsKey;
        },

        // Never `valueOf`: Alpine resolves a method of an expression's scope
        // through `Reflect.has`, which finds `Object.prototype.valueOf` on the
        // first object of the stack and hands that back instead of this one.
        // The baseline then reads `[object Object]` for every control and a
        // card edited without hovering it first stays «unchanged».
        fieldValue(element) {
            if (element.type === 'checkbox' || element.type === 'radio') {
                return element.checked ? '1' : '0';
            }

            if (element.type === 'file') {
                return Array.from(element.files || [])
                    .map(file => file.name + ':' + file.size)
                    .join(',');
            }

            // A rich text editor holds the text of the field in itself until
            // the form is sent, so the textarea under it is empty meanwhile.
            const editor = element.id !== '' && window.tinymce ? window.tinymce.get(element.id) : null;

            if (editor !== null && editor !== undefined) {
                return editor.getContent();
            }

            if (element.multiple && element.tagName === 'SELECT') {
                return Array.from(element.selectedOptions).map(option => option.value).join(',');
            }

            return String(element.value);
        },

        // The state every card is measured against. Taken once the cards are
        // drawn and again after each successful save.
        baseline() {
            this.clean = {};
            this.touched = {};
            this.forms().forEach(form => {
                this.clean[form.dataset.cmsForm] = this.serialize(form);
            });
            this.dirty = [];
        },

        // Comparing the whole form instead of remembering which input fired
        // is what catches an editor that writes into its textarea on its own,
        // a rich text field among them.
        //
        // A card nobody has touched yet is measured again instead: what
        // differs there is a widget that finished building itself after the
        // page was drawn, and «changed» has to mean an edit of the editor and
        // nothing else.
        refreshDirty() {
            const dirty = [];

            this.forms().forEach(form => {
                const id = form.dataset.cmsForm;
                const current = this.serialize(form);

                if (this.touched[id] !== true) {
                    this.clean[id] = current;

                    return;
                }

                if (this.clean[id] !== current) {
                    dirty.push(id);
                }
            });

            this.dirty = dirty;
        },

        touch(event) {
            const form = event.target.closest('form[data-cms-form]');

            if (form === null) {
                return;
            }

            this.touched[form.dataset.cmsForm] = true;
            this.refreshDirty();
            this.refreshEmpty();
        },

        // A rich text editor lives in a frame of its own, so nothing it does
        // reaches the form: it says so itself instead. Bound once per field,
        // and only once the editor exists.
        bindRichText() {
            if (! window.tinymce) {
                return;
            }

            this.$root.querySelectorAll('form[data-cms-form] textarea[id]').forEach(area => {
                if (area.dataset.cmsRichBound === '1') {
                    return;
                }

                const editor = window.tinymce.get(area.id);
                const form = area.closest('form[data-cms-form]');

                if (! editor || form === null) {
                    return;
                }

                area.dataset.cmsRichBound = '1';

                editor.on('change input undo redo', () => {
                    this.touched[form.dataset.cmsForm] = true;
                    this.refreshDirty();
                });
            });
        },

        isDirty(id) {
            return this.dirty.includes(String(id));
        },

        // One card reported a save of its own: only that card is measured
        // again, so a neighbour with unsaved text keeps its mark.
        rebaseline(id) {
            const form = this.formOf(id);

            if (form === null) {
                return;
            }

            this.saving = Math.max(0, this.saving - 1);
            this.clean[form.dataset.cmsForm] = this.serialize(form);
            this.touched[form.dataset.cmsForm] = false;
            this.refreshDirty();
        },

        /* --------------------------------------------------------- save all */

        // Every card posts to its own address, so «save all» is the submit of
        // each changed card and not a request of its own: the optimistic lock
        // and the refusal of every card work exactly as they do alone.
        saveAll() {
            this.refreshDirty();

            const ids = this.dirty.slice();

            if (ids.length === 0) {
                window.MoonShine.ui.toast(this.labels.nothingToSave, 'default');

                return;
            }

            // A card whose inputs are invalid has to be visible: a browser
            // refuses to report a problem it cannot show.
            ids.forEach(id => {
                const form = this.formOf(id);

                if (form !== null && !form.checkValidity()) {
                    this.open[id] = true;
                }
            });

            this.persist('open', this.open);
            this.saving = ids.length;

            this.$nextTick(() => {
                ids.forEach(id => {
                    window.dispatchEvent(new CustomEvent('form_submit:cms-block-' + id));
                });
            });
        },

        // The switch of the card head and the switch under «more» are one
        // value: the head flips the input of the form and the card becomes
        // unsaved, exactly as a typed change does.
        toggleActive(id) {
            const input = this.$root.querySelector('input[type="checkbox"][data-cms-active="' + id + '"]');

            if (input === null) {
                return;
            }

            input.checked = !input.checked;
            input.dispatchEvent(new Event('change', {bubbles: true}));
        },

        isActive(id) {
            const input = this.$root.querySelector('input[type="checkbox"][data-cms-active="' + id + '"]');

            return input === null ? true : input.checked;
        },

        /* ---------------------------------------------------------- library */

        // The address carries the point the block goes to, and the modal loads
        // it every time: what may be inserted depends on the slot.
        openLibrary(parent, slot, position) {
            const url = new URL(this.endpoints.library, window.location.origin);

            url.searchParams.set('position', position);

            if (parent !== null && parent !== '') {
                url.searchParams.set('parent', parent);
            }

            if (slot !== null && slot !== '') {
                url.searchParams.set('slot', slot);
            }

            window.MoonShine.ui.toggleModal(this.modal, url.toString());
        },

        async insert(detail) {
            const body = await this.post(this.endpoints.insert, detail || {});

            window.MoonShine.ui.toggleModal(this.modal);

            // A block that was just added is the one the editor wants to fill.
            if (body !== null && body.block) {
                this.open[body.block] = true;
                this.persist('open', this.open);
            }

            if (body !== null) {
                this.reload();
            }
        },

        /* ------------------------------------------------------------ order */

        bindSortables() {
            this.$root.querySelectorAll('[data-cms-container]').forEach(element => {
                if (element.dataset.cmsBound === '1') {
                    return;
                }

                element.dataset.cmsBound = '1';

                window.MoonShine.iterable.sortable(
                    element,
                    null,
                    this.group,
                    null,
                    {
                        handle: '.cms-card__handle',
                        draggable: '[data-cms-block]',
                        animation: 150,
                        ghostClass: 'cms-card--ghost',
                        fallbackOnBody: true,
                    },
                    event => this.sorted(event),
                );
            });
        },

        async sorted(event) {
            const container = event.to;
            const cards = Array.from(container.querySelectorAll(':scope > [data-cms-block]'));

            const body = await this.post(this.endpoints.move, {
                id: event.item.dataset.cmsBlock,
                parent: container.dataset.id || '',
                slot: container.dataset.slot || '',
                index: cards.indexOf(event.item),
            });

            // A refused move leaves the card where the pointer dropped it, so
            // the list is drawn again from what the storage actually holds.
            this.reload();

            return body;
        },

        /* --------------------------------------------------------- requests */

        reload() {
            window.dispatchEvent(new CustomEvent(this.event));
        },

        async post(url, payload) {
            let response;

            try {
                response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.token,
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });
            } catch (e) {
                window.MoonShine.ui.toast(this.labels.requestFailed, 'error');

                return null;
            }

            const body = await response.json().catch(() => ({}));

            if (!response.ok) {
                let message = body.message || this.labels.requestFailed;

                if (Array.isArray(body.problems)) {
                    message += ' ' + body.problems.join('; ');
                }

                window.MoonShine.ui.toast(message, 'error');

                return null;
            }

            if (body.message) {
                window.MoonShine.ui.toast(body.message, body.messageType || 'success');
            }

            return body;
        },
    }));
});

/*
 * The link picker.
 *
 * It owns the cascade and nothing else: which entry of «where does it lead» is
 * chosen, and the values of the step that entry asks for. Where the target is
 * searched, the select of MoonShine does the searching; what the panel cannot
 * answer with one address — the sections of a page, the filters of a catalogue
 * — is asked here, because both depend on a page the editor has just picked.
 *
 * Only the hidden input carrying the kind is named on the server. Inside a row
 * of a repeater the panel rewrites that one name, and every other input is
 * named after it, so a row renames itself completely.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('cmsLinkPicker', (config = {}) => ({
        choices: config.choices || [],
        choice: config.choice || '',
        base: config.base || '',
        ownerPage: config.ownerPage || '',
        endpoints: config.endpoints || {},
        labels: config.labels || {},
        entity: config.entity || {},
        page: config.page || '',
        anchor: config.anchor || '',
        route: config.route || '',
        routes: config.routes || [],
        params: config.params || {},
        filterValues: config.filterValues || {},
        listAnchor: config.listAnchor || '',
        newTab: !!config.newTab,
        file: config.file || {value: '', label: '', image: ''},
        mediaPicker: config.mediaPicker || '',
        modal: config.modal || 'cms-media-picker',
        mediaKey: '',

        anchors: [],
        lists: [],
        filters: [],
        asked: {anchors: null, catalog: null},
        observer: null,

        init() {
            // Inside a row of a repeater the panel rewrites the name of the
            // kind input, and it does so on its own schedule: the names of
            // this field are derived from that one, so the rewrite is watched
            // instead of guessed.
            if (this.$refs.kindInput && window.MutationObserver) {
                this.observer = new MutationObserver(() => this.syncBase());
                this.observer.observe(this.$refs.kindInput, {attributes: true, attributeFilter: ['name']});
            }

            // The key this picker answers to when the media dialog reports a
            // choice: a page carries one dialog and several fields, and a row
            // of a repeater is renumbered on the client, so the key is made
            // here and not on the server.
            this.mediaKey = 'cms-link-' + Math.random().toString(36).slice(2, 10);

            this.$nextTick(() => {
                this.syncBase();
                this.refresh();
            });
        },

        destroy() {
            if (this.observer) {
                this.observer.disconnect();
                this.observer = null;
            }
        },

        /* ---------------------------------------------------------- a file */

        // The same dialog a picture of a block opens, with every kind of file
        // offered: a link may point at a video or a price list as easily as at
        // a picture.
        openFilePicker() {
            if (this.mediaPicker === '') {
                return;
            }

            const url = new URL(this.mediaPicker, window.location.origin);

            url.searchParams.set('multiple', '0');
            url.searchParams.set('target', this.mediaKey);
            url.searchParams.set('scope', 'link');

            if (this.file.value) {
                url.searchParams.set('selected', this.file.value);
            }

            window.MoonShine.ui.toggleModal(this.modal, url.toString());
        },

        fileChosen(event) {
            const detail = event && event.detail ? event.detail : {};

            if (detail.target !== this.mediaKey) {
                return;
            }

            const item = (detail.items || [])[0];

            if (!item) {
                return;
            }

            this.file = {
                value: item.id,
                label: item.name,
                image: item.thumb_url || '',
            };
        },

        clearFile() {
            this.file = {value: '', label: '', image: ''};
        },

        // However the dialog ended, the keyboard comes back to the button that
        // opened it, the way the media field does it.
        fileDialogClosed(event) {
            const detail = event && event.detail ? event.detail : {};

            if (detail.target !== this.mediaKey) {
                return;
            }

            this.$nextTick(() => {
                const button = this.$el.querySelector('[data-cms-media-open]');

                if (button && button.offsetParent !== null) {
                    button.focus();
                }
            });
        },

        /* ------------------------------------------------------ the cascade */

        entry() {
            return this.choices.find(item => item.id === this.choice) || null;
        },

        get kind() {
            const entry = this.entry();

            return entry === null ? '' : entry.kind;
        },

        entityType() {
            const entry = this.entry();

            return entry !== null && entry.type ? entry.type : '';
        },

        // The stored reference is a type and a key; the option of the select
        // carries both, so the key is read back out of it.
        entityKey() {
            const packed = this.entity[this.entityType()] || '';
            const at = packed.indexOf('::');

            return at === -1 ? '' : packed.slice(at + 2);
        },

        chosen() {
            if (!this.allowsNewTab()) {
                this.newTab = false;
            }

            this.refresh();
        },

        onPage(value) {
            this.page = value;
            this.refresh();
        },

        refresh() {
            if (this.kind === 'anchor') {
                this.loadAnchors();
            }

            if (this.kind === 'list') {
                this.loadCatalog();
            }
        },

        /* --------------------------------------------------- what is visible */

        // A page target, a catalogue link and a section of another page all
        // point at a page, so one select serves the three of them. A section
        // of the page being edited needs none: the owner is that page.
        usesPage() {
            const kind = this.kind;

            return kind === 'page' || kind === 'list' || (kind === 'anchor' && !this.ownerPage);
        },

        pageUuid() {
            return this.kind === 'anchor' && this.ownerPage ? this.ownerPage : this.page;
        },

        pageLabel() {
            if (this.kind === 'list') {
                return this.labels.catalogPage || '';
            }

            return this.kind === 'anchor' ? this.labels.anchorPage || '' : this.labels.page || '';
        },

        // A new tab is a choice only where the target is a document: an anchor
        // inside the page, an e-mail and a phone number open no tab at all.
        allowsNewTab() {
            return ['page', 'entity', 'list', 'file', 'url'].indexOf(this.kind) !== -1;
        },

        filterActive(filter, shape) {
            if (this.kind !== 'list') {
                return false;
            }

            const matches = shape === 'options' ? filter.type === 'options' : filter.type !== 'options';

            return matches && String(this.filterValues[filter.name] || '') !== '';
        },

        routeParams() {
            const found = this.routes.find(item => item.name === this.route);

            return found ? found.params : [];
        },

        paramLabel(name) {
            return (this.labels.routeParam || ':name').replace(':name', name);
        },

        anchorNote() {
            if (this.kind !== 'anchor') {
                return '';
            }

            if (!this.pageUuid()) {
                return this.labels.anchorPageEmpty || '';
            }

            return this.anchors.length === 0 ? this.labels.anchorsEmpty || '' : '';
        },

        catalogNote() {
            if (this.kind !== 'list') {
                return '';
            }

            if (!this.page) {
                return this.labels.catalogPageEmpty || '';
            }

            return this.filters.length === 0 ? this.labels.filtersEmpty || '' : '';
        },

        /* ------------------------------------------------------------- names */

        syncBase() {
            const live = this.$refs.kindInput ? this.$refs.kindInput.getAttribute('name') : null;

            if (live) {
                this.base = live.replace(/\[kind\]$/, '');
            }
        },

        attr(name) {
            return this.base + '[attributes][' + name + ']';
        },

        own(name) {
            return this.base + '[' + name + ']';
        },

        filterName(name) {
            return this.attr('filters') + '[' + name + ']';
        },

        paramName(name) {
            return this.attr('params') + '[' + name + ']';
        },

        /* ---------------------------------------------- what the server knows */

        async loadAnchors() {
            const page = this.pageUuid();

            if (!page || !this.endpoints.anchors) {
                this.anchors = [];

                return;
            }

            if (this.asked.anchors === page) {
                return;
            }

            this.asked.anchors = page;
            this.anchors = await this.ask(this.endpoints.anchors, {page: page});

            // The options arrive after the select was drawn, so the stored
            // value is put back on it once they are there.
            this.$nextTick(() => {
                if (this.$refs.anchorSelect) {
                    this.$refs.anchorSelect.value = this.anchor;
                }
            });
        },

        async loadCatalog() {
            const page = this.page;

            if (!page || !this.endpoints.catalog) {
                this.lists = [];
                this.filters = [];

                return;
            }

            if (this.asked.catalog === page) {
                return;
            }

            this.asked.catalog = page;

            const body = await this.ask(this.endpoints.catalog, {page: page});

            this.lists = body && body.lists ? body.lists : [];
            this.filters = body && body.filters ? body.filters : [];

            this.$nextTick(() => this.restore());
        },

        restore() {
            if (this.$refs.listSelect) {
                this.$refs.listSelect.value = this.listAnchor;
            }

            this.$root.querySelectorAll('[data-cms-filter]').forEach(element => {
                element.value = this.filterValues[element.dataset.cmsFilter] || '';
            });
        },

        async ask(url, params) {
            const target = new URL(url, window.location.origin);

            Object.keys(params).forEach(key => target.searchParams.set(key, params[key]));

            try {
                const response = await fetch(target.toString(), {
                    headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                });

                return response.ok ? await response.json() : [];
            } catch (e) {
                // A lookup that did not answer leaves the step empty; the card
                // is still editable and still saves what it already holds.
                return [];
            }
        },
    }));
});

/*
 * The rows of a repeater.
 *
 * A row of slides is as tall as a small form, and a block of five of them
 * cannot be read at all: the editor scrolls past pictures and links looking
 * for the one row it came to change. So a row gets a head of its own — the
 * grab handle, the title it reads out of its own first filled field, and the
 * delete button in the corner — and the head folds the row away.
 *
 * The panel builds the rows itself and rebuilds them on add, remove and
 * reorder, so nothing is decorated once and forgotten: an observer per
 * repeater picks up whatever appears. The head is assembled here and not in a
 * template because the template belongs to the panel.
 */
(() => {
    if (window.cmsRepeaterRows) {
        return;
    }

    window.cmsRepeaterRows = true;

    const CHEVRON = '<svg class="cms-row__chevron" viewBox="0 0 20 20" fill="none" aria-hidden="true">'
        + '<path d="M6 8l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    /* The value the row is named by: the first filled field an editor typed
       into. A search box of a select carries a value of its own and is never
       one, and neither is anything the head itself holds. */
    const titleOf = row => {
        const inputs = row.querySelectorAll('input, textarea');

        for (const input of inputs) {
            if (input.type === 'hidden' || input.type === 'checkbox' || input.type === 'radio' || input.type === 'file') {
                continue;
            }

            if (input.closest('.ts-wrapper') !== null || input.closest('.cms-row__head') !== null) {
                continue;
            }

            const value = String(input.value || '').trim();

            if (value !== '') {
                return value;
            }
        }

        return '';
    };

    const numberOf = row => {
        const tr = row.closest('tr');
        const body = tr === null ? null : tr.parentElement;

        if (body === null) {
            return 1;
        }

        return Array.prototype.indexOf.call(body.children, tr) + 1;
    };

    const retitle = row => {
        const title = row.querySelector(':scope > .cms-row__head .cms-row__title');

        if (title === null) {
            return;
        }

        const named = titleOf(row);
        const fallback = (row.closest('[data-cms-row-label]')?.dataset.cmsRowLabel || 'Row :number')
            .replace(':number', String(numberOf(row)));

        const text = named === '' ? fallback : named;

        // Written only when it actually changes: the head is watched by an
        // observer of its own, and rewriting the same text would wake it on
        // every frame.
        if (title.textContent !== text) {
            title.textContent = text;
        }

        if (title.classList.contains('_is-fallback') !== (named === '')) {
            title.classList.toggle('_is-fallback', named === '');
        }
    };

    /* The grip itself and not the band it came in: the band is a twelve column
       grid of the panel and would take half the head with it. Emptied, it is
       hidden rather than removed, because the panel owns that markup. */
    const gripOf = row => {
        const handle = row.querySelector('[data-field-selector="__handle"]');

        if (handle === null) {
            return null;
        }

        const band = handle.closest('.grid');

        if (band !== null) {
            band.classList.add('cms-row__band');
        }

        return handle;
    };

    const decorate = row => {
        if (row.querySelector(':scope > .cms-row__head') !== null) {
            return;
        }

        const head = document.createElement('div');
        const toggle = document.createElement('button');
        const title = document.createElement('span');

        head.className = 'cms-row__head';
        toggle.type = 'button';
        toggle.className = 'cms-row__toggle';
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('title', row.closest('[data-cms-row-toggle]')?.dataset.cmsRowToggle || '');
        title.className = 'cms-row__title';
        toggle.append(title);
        toggle.insertAdjacentHTML('beforeend', CHEVRON);

        head.append(toggle);
        row.prepend(head);

        toggle.addEventListener('click', () => {
            const collapsed = row.classList.toggle('_is-collapsed');

            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        });
    };

    /* The grip and the buttons are taken into the head whenever they are found
       outside it: the panel draws them on its own schedule and redraws them
       after a row is added or reordered, so one pass at build time would leave
       a row without its delete button. */
    const adopt = row => {
        const head = row.querySelector(':scope > .cms-row__head');

        if (head === null) {
            return;
        }

        const grip = gripOf(row);
        const toggle = head.querySelector(':scope > .cms-row__toggle');

        if (grip !== null && grip.parentElement !== head) {
            head.insertBefore(grip, toggle);
        }

        const actions = row.querySelector(':scope > .flex');

        if (actions !== null) {
            head.append(actions);
        }
    };

    const scan = root => {
        root.querySelectorAll('td.table-grid').forEach(row => {
            decorate(row);
            adopt(row);
            retitle(row);
        });
    };

    const watch = repeater => {
        if (repeater.dataset.cmsRowsWatched === '1') {
            return;
        }

        repeater.dataset.cmsRowsWatched = '1';

        let queued = false;

        const observer = new MutationObserver(() => {
            if (queued) {
                return;
            }

            queued = true;
            window.requestAnimationFrame(() => {
                queued = false;
                scan(repeater);
            });
        });

        observer.observe(repeater, {childList: true, subtree: true});
        scan(repeater);
    };

    const bind = () => document.querySelectorAll('[data-cms-repeater]').forEach(watch);

    document.addEventListener('input', event => {
        const row = event.target.closest?.('td.table-grid');

        if (row !== null && row !== undefined) {
            retitle(row);
        }
    });

    document.addEventListener('DOMContentLoaded', bind);

    /* A card of the editor is redrawn after it is saved, and a repeater that
       arrives with it has never been seen here. Binding is idempotent, so the
       document is watched for new ones instead of guessing an event. */
    let pending = false;

    new MutationObserver(() => {
        if (pending) {
            return;
        }

        pending = true;
        window.requestAnimationFrame(() => {
            pending = false;
            bind();
        });
    }).observe(document.documentElement, {childList: true, subtree: true});

    bind();
})();
