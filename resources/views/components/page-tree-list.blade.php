@php
    /** @var list<\Ewk\MoonShineCms\Tree\PageNode> $nodes */
    $zone = static fn (int $position): string => ($parentId ?? 'root').'-'.$position;
@endphp
<ul @class(['cms-tree__list', 'cms-tree__list--nested' => $parentId !== null])>
    @foreach($nodes as $node)
        <li
            class="cms-tree__dropzone"
            x-bind:class="over === '{{ $zone($loop->index) }}' && 'cms-tree__dropzone--over'"
            x-on:dragover.prevent="over = '{{ $zone($loop->index) }}'"
            x-on:dragleave="over = null"
            x-on:drop.prevent="dropOn(@js($parentId), {{ $loop->index }}, $el)"
        ></li>

        <li data-cms-page="{{ $node->id }}" data-cms-path="{{ $node->path }}">
            <div
                @class([
                    'cms-tree__row' => true,
                    'cms-tree__row--inactive' => ! $node->isActive,
                ])
                x-bind:class="{
                    'cms-tree__row--dragged': dragged === {{ $node->id }},
                    'cms-tree__row--into': over === 'into-{{ $node->id }}',
                }"
                @if($depth < $maxDepth)
                    x-on:dragover.prevent="dragged !== {{ $node->id }} && (over = 'into-{{ $node->id }}')"
                    x-on:dragleave="over = null"
                    x-on:drop.prevent="dropOn({{ $node->id }}, null)"
                @endif
            >
                @if($node->fixed)
                    <span class="cms-tree__handle cms-tree__handle--fixed" aria-hidden="true" title="{{ __('moonshine-cms::pages.fixed_hint') }}">⠿</span>
                @else
                    <span
                        class="cms-tree__handle"
                        aria-hidden="true"
                        draggable="true"
                        x-on:dragstart="startDrag({{ $node->id }})"
                        x-on:dragend="endDrag()"
                    >⠿</span>
                @endif

                <a class="cms-tree__caption" href="{{ $node->formUrl }}">{{ $node->caption }}</a>

                <span class="cms-tree__path">{{ $node->path }}</span>

                <x-moonshine::badge color="gray">{{ $node->typeTitle }}</x-moonshine::badge>

                @if($node->fixed)
                    <x-moonshine::badge color="purple">{{ __('moonshine-cms::pages.fixed_badge') }}</x-moonshine::badge>
                @endif

                @unless($node->isActive)
                    <x-moonshine::badge color="yellow">{{ __('moonshine-cms::pages.inactive_badge') }}</x-moonshine::badge>
                @endunless

                <span class="cms-tree__tail">
                    <x-moonshine::link-button :href="$node->blocksUrl" class="btn-sm">
                        {{ __('moonshine-cms::editor.title') }}
                    </x-moonshine::link-button>

                    @if($depth < $maxDepth)
                        <x-moonshine::link-button :href="$node->addChildUrl" class="btn-sm" data-cms-add-child="{{ $node->id }}">
                            {{ __('moonshine-cms::pages.add_child') }}
                        </x-moonshine::link-button>
                    @endif
                </span>
            </div>

            @if($node->children !== [])
                @include('moonshine-cms::components.page-tree-list', [
                    'nodes' => $node->children,
                    'parentId' => $node->id,
                    'depth' => $depth + 1,
                    'maxDepth' => $maxDepth,
                ])
            @endif
        </li>
    @endforeach

    @if($nodes !== [])
        <li
            class="cms-tree__dropzone"
            x-bind:class="over === '{{ $zone(count($nodes)) }}' && 'cms-tree__dropzone--over'"
            x-on:dragover.prevent="over = '{{ $zone(count($nodes)) }}'"
            x-on:dragleave="over = null"
            x-on:drop.prevent="dropOn(@js($parentId), {{ count($nodes) }}, $el)"
        ></li>
    @endif
</ul>
