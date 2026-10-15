/*
 * The Alpine component of the entity forms of the CMS: pages, menus, menu
 * items, headers, footers, shared blocks.
 *
 * It owns two things and nothing else. Which language the translatable inputs
 * show — the same switch the block editor carries, so an editor meets one
 * gesture everywhere and a translatable field stays in the place the form
 * declares it. And the address built from the name while a record is being
 * created, so the field is filled in front of the editor instead of appearing
 * out of the save.
 *
 * The address is built here and on the server both: what is typed here is a
 * preview of what the save would derive anyway, and a browser with no
 * javascript still gets its address from the name.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('cmsForm', (config = {}) => ({
        locales: config.locales || [],
        locale: config.defaultLocale || '',
        stateKey: config.stateKey || 'cms-form',
        // Only while a record is created: renaming an existing one never
        // rewrites its address.
        slugAuto: config.slugAuto === true,

        init() {
            this.locale = this.restore() || this.locale;

            this.$nextTick(() => {
                this.refreshEmpty();
                this.bindSlug();
            });
        },

        /* ---------------------------------------------------------- language */

        setLocale(locale) {
            this.locale = locale;
            this.persist(locale);
            this.$nextTick(() => this.refreshEmpty());
        },

        // Which translatable inputs are still empty in the language they carry.
        // The mark sits on the wrapper the field tagged, so a language the
        // catalogue adds later needs no change here.
        refreshEmpty() {
            this.$root.querySelectorAll('[data-cms-locale]').forEach(wrapper => {
                const inputs = wrapper.querySelectorAll('input, textarea, select');
                let filled = false;

                inputs.forEach(input => {
                    // A hidden input is a mechanism and not something an
                    // editor filled — except where a field says otherwise:
                    // the pictures a record owns travel as uuids in hidden
                    // inputs, and a box holding one is not an empty box.
                    const counts = input.type !== 'hidden' || input.dataset.cmsCounts !== undefined;

                    if (counts && String(input.value || '').trim() !== '') {
                        filled = true;
                    }
                });

                filled
                    ? wrapper.removeAttribute('data-cms-empty')
                    : wrapper.setAttribute('data-cms-empty', '');
            });
        },

        restore() {
            try {
                return sessionStorage.getItem(this.stateKey + ':locale');
            } catch (e) {
                return null;
            }
        },

        persist(locale) {
            try {
                sessionStorage.setItem(this.stateKey + ':locale', locale);
            } catch (e) {
                // A browser that refuses storage still edits records.
            }
        },

        /* ----------------------------------------------------------- address */

        bindSlug() {
            const target = this.$root.querySelector('[data-cms-slug-target]');
            const source = this.$root.querySelector('[data-cms-slug-source]');

            if (target === null || source === null || !this.slugAuto) {
                return;
            }

            // A hand written address is the editor's, and a name typed
            // afterwards does not take it back.
            target.addEventListener('input', () => {
                this.slugAuto = false;
            });

            source.addEventListener('input', () => {
                if (this.slugAuto) {
                    target.value = this.slugify(source.value);
                }
            });
        },

        // The same shape the server derives: lowercase latin, digits and
        // hyphens. Cyrillic is transliterated the way the pages package does
        // it, so the preview and the stored value agree.
        slugify(value) {
            const map = {
                а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh',
                з: 'z', и: 'i', й: 'i', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o',
                п: 'p', р: 'r', с: 's', т: 't', у: 'u', ф: 'f', х: 'h', ц: 'ts',
                ч: 'ch', ш: 'sh', щ: 'sch', ъ: '', ы: 'y', ь: '', э: 'e',
                ю: 'yu', я: 'ya', ä: 'ae', ö: 'oe', ü: 'ue', ß: 'ss',
            };

            return String(value || '')
                .toLowerCase()
                .split('')
                .map(letter => (letter in map ? map[letter] : letter))
                .join('')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        },
    }));
});
