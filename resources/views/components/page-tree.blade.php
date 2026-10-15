@props([
    'nodes' => [],
    'templates' => [],
    'templatesHint' => '',
    'fragmentEvent' => 'fragment_updated:cms-page-tree',
    'maxDepth' => 4,
    'moveUrl' => '',
    'labels' => [],
])
<div
    class="cms-tree"
    data-cms-page-tree
    x-bind:data-dragging="dragged === null ? '0' : '1'"
    x-data="{
        token: '{{ csrf_token() }}',
        moveUrl: @js($moveUrl),
        labels: @js($labels),
        event: '{{ $fragmentEvent }}',
        dragged: null,
        over: null,
        error: null,

        startDrag(id) {
            this.dragged = id;
        },

        endDrag() {
            this.dragged = null;
            this.over = null;
        },

        async dropOn(parent, position, zone = null) {
            this.over = null;

            if (this.dragged === null) {
                return;
            }

            const page = this.dragged;
            this.dragged = null;

            // The tree counts the position with the dragged node still in
            // the list; the tree service counts it without. A drop below
            // the node's own place in its own list is one off.
            if (zone !== null && position !== null) {
                const node = this.$root.querySelector('[data-cms-page=\'' + page + '\']');

                if (node !== null && node.parentElement === zone.parentElement) {
                    const siblings = Array.from(zone.parentElement.children).filter((el) => el.hasAttribute('data-cms-page'));
                    const index = siblings.indexOf(node);

                    if (index !== -1 && index < position) {
                        position -= 1;
                    }
                }
            }

            await this.post(this.moveUrl, { page: page, parent: parent, position: position });
        },

        async post(url, payload) {
            this.error = null;

            const response = await fetch(url, {
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

            if (! response.ok) {
                this.error = body.message || this.labels.requestFailed;

                return null;
            }

            if (body.message) {
                window.MoonShine?.ui?.toast(body.message, 'success');
            }

            window.dispatchEvent(new CustomEvent(this.event));

            return body;
        },
    }"
>
    <template x-if="error">
        <x-moonshine::alert type="error" class="cms-tree__error">
            <span x-text="error"></span>
        </x-moonshine::alert>
    </template>

    @if($nodes === [])
        <p class="cms-tree__empty">{{ __('moonshine-cms::pages.tree_empty') }}</p>
    @else
        @include('moonshine-cms::components.page-tree-list', [
            'nodes' => $nodes,
            'parentId' => null,
            'depth' => 0,
            'maxDepth' => $maxDepth,
        ])
    @endif

    {{-- The templates of the entity types live under the pages and not in a
         section of their own: there is nothing to create and nothing to
         delete in them, so they are tiles and not nodes of the tree. --}}
    @if($templates !== [])
        <div class="cms-templates" data-cms-templates>
            <div class="cms-templates__head">
                <span class="cms-templates__title">{{ __('moonshine-cms::pages.templates') }}</span>
                <span class="cms-templates__hint">{{ $templatesHint }}</span>
            </div>

            <div class="cms-templates__list">
                @foreach($templates as $template)
                    <div class="cms-templates__tile" data-cms-template="{{ $template->type }}">
                        <span class="cms-templates__name">{{ $template->title }}</span>
                        <span class="cms-templates__description">{{ $template->description }}</span>

                        <span class="cms-templates__tail">
                            <x-moonshine::badge :color="$template->tone">{{ $template->status }}</x-moonshine::badge>

                            @if($template->editorUrl !== '')
                                <x-moonshine::link-button :href="$template->editorUrl" class="btn-sm">
                                    {{ __('moonshine-cms::pages.open_editor') }}
                                </x-moonshine::link-button>
                            @else
                                {{-- Assembling writes a row, so the tile posts
                                     with the token of the panel and asks first. --}}
                                {!! \Ewk\MoonShineCms\Editor\EditorActions::confirmed(
                                    (string) __('moonshine-cms::pages.assemble_template'),
                                    $template->assembleUrl,
                                    (string) __('moonshine-cms::pages.assemble_confirm'),
                                    'cms-assemble-'.$template->type,
                                    'squares-plus',
                                    'data-cms-assemble',
                                ) !!}
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
