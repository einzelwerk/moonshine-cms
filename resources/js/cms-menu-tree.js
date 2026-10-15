/*
 * The tree of menu items.
 *
 * It owns one thing: the drag and drop of the items. Sorting rides the
 * sortable of MoonShine, exactly as the block editor does, so the page grows
 * no dependency of its own and an editor meets one gesture in both places.
 *
 * Where an item may land is decided by the server: the request says which
 * parent and which position the pointer chose, and the list is drawn again
 * from what the storage actually holds, so a refused move leaves nothing on
 * the screen that is not in the database.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('cmsMenuTree', (config = {}) => ({
        token: config.token || '',
        moveUrl: config.moveUrl || '',
        labels: config.labels || {},
        event: config.event || '',
        group: config.group || 'cms-menu-items',
        error: null,

        init() {
            this.$nextTick(() => this.bindSortables());
        },

        bindSortables() {
            this.$root.querySelectorAll('[data-cms-menu-container]').forEach(element => {
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
                        handle: '.cms-menu-tree__handle',
                        draggable: '[data-cms-menu-item]',
                        animation: 150,
                        ghostClass: 'cms-menu-tree__item--ghost',
                        fallbackOnBody: true,
                    },
                    event => this.sorted(event),
                );
            });
        },

        async sorted(event) {
            const container = event.to;
            const items = Array.from(container.querySelectorAll(':scope > [data-cms-menu-item]'));

            await this.post({
                item: event.item.dataset.cmsMenuItem,
                parent: container.dataset.id || null,
                position: items.indexOf(event.item),
            });

            this.reload();
        },

        async post(payload) {
            this.error = null;

            const response = await fetch(this.moveUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.token,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const body = await response.json().catch(() => ({}));

            if (!response.ok) {
                this.error = body.message || this.labels.requestFailed;

                return null;
            }

            if (body.message) {
                window.MoonShine?.ui?.toast(body.message, 'success');
            }

            return body;
        },

        reload() {
            window.dispatchEvent(new CustomEvent(this.event));
        },
    }));
});
