@php
    /** @var list<\Ewk\MoonShineCms\MoonShine\Components\MenuTreeNode> $nodes */
    // Two levels is the whole depth: only a root row carries a list of its own.
    $acceptsChildren = $depth < $maxDepth - 1;
@endphp
<ul
    @class(['cms-menu-tree__list', 'cms-menu-tree__list--nested' => $parentId !== null])
    data-cms-menu-container
    data-id="{{ $parentId }}"
>
    @foreach($nodes as $node)
        <li class="cms-menu-tree__item" data-cms-menu-item="{{ $node->id }}">
            <div @class([
                'cms-menu-tree__row' => true,
                'cms-menu-tree__row--inactive' => ! $node->isActive,
            ])>
                <span
                    class="cms-menu-tree__handle"
                    role="button"
                    title="{{ __('moonshine-cms::editor.drag') }}"
                    aria-label="{{ __('moonshine-cms::editor.drag') }}"
                >
                    <x-moonshine::icon icon="bars-3-bottom-right" size="4" />
                </span>

                {{-- The caption opens the same dialog the pencil does. --}}
                <button
                    type="button"
                    class="cms-menu-tree__label"
                    x-on:click="$dispatch(@js(\MoonShine\Support\AlpineJs::event(\MoonShine\Support\Enums\JsEvent::MODAL_TOGGLED, $node->modal)))"
                >{{ $node->label }}</button>

                @if($node->target !== null)
                    <x-moonshine::badge color="gray">{{ $node->target }}</x-moonshine::badge>
                @else
                    <x-moonshine::badge color="purple">{{ __('moonshine-cms::menus.item_group') }}</x-moonshine::badge>
                @endif

                @unless($node->isActive)
                    <x-moonshine::badge color="yellow">{{ __('moonshine-cms::menus.item_inactive') }}</x-moonshine::badge>
                @endunless

                <span class="cms-menu-tree__tail">
                    @if($acceptsChildren)
                        {!! $node->addChildButton !!}
                    @endif

                    {!! $node->editButton !!}
                </span>
            </div>

            {{-- A root row keeps its list even while it is empty: that is where
                 an item is dropped to become nested. --}}
            @if($acceptsChildren)
                @include('moonshine-cms::components.menu-tree-list', [
                    'nodes' => $node->children,
                    'parentId' => $node->id,
                    'depth' => $depth + 1,
                    'maxDepth' => $maxDepth,
                ])
            @endif
        </li>
    @endforeach
</ul>
