@props([
    'nodes' => [],
    'fragmentEvent' => 'fragment_updated:cms-menu-tree',
    'maxDepth' => 2,
    'addButton' => null,
    'moveUrl' => '',
    'group' => 'cms-menu-items',
    'labels' => [],
])
<div
    class="cms-menu-tree"
    data-cms-menu-tree
    x-data="cmsMenuTree({
        token: '{{ csrf_token() }}',
        moveUrl: @js($moveUrl),
        labels: @js($labels),
        event: '{{ $fragmentEvent }}',
        group: @js($group),
    })"
>
    <div class="cms-menu-tree__head">
        <span class="cms-menu-tree__title">{{ __('moonshine-cms::menus.items') }}</span>

        <span class="cms-menu-tree__tail">
            {!! $addButton !!}
        </span>
    </div>

    <p class="cms-menu-tree__hint">{{ __('moonshine-cms::menus.items_hint') }}</p>

    <template x-if="error">
        <x-moonshine::alert type="error" class="cms-menu-tree__error">
            <span x-text="error"></span>
        </x-moonshine::alert>
    </template>

    @if($nodes === [])
        <p class="cms-menu-tree__empty">{{ __('moonshine-cms::menus.items_empty') }}</p>
    @endif

    {{-- The root container is drawn even when it is empty: it is where the
         first item is dropped after it is created. --}}
    @include('moonshine-cms::components.menu-tree-list', [
        'nodes' => $nodes,
        'parentId' => null,
        'depth' => 0,
        'maxDepth' => $maxDepth,
    ])
</div>
