@props([
    /* What the insertion modal offers for one exact point: the categories the
       server narrowed by the slot and by the owner. The list is HTML and not
       a payload the browser assembles: the panel already knows how to draw
       it. */
    'categories' => [],
    'parent' => '',
    'slotName' => '',
    'position' => 0,
])
@php
    $needle = static fn (object $entry): string => mb_strtolower($entry->title.' '.($entry->about ?? ''));
    $all = [];

    foreach ($categories as $category) {
        foreach ($category->entries as $entry) {
            $all[] = $needle($entry);
        }
    }
@endphp
<div
    class="cms-library"
    data-cms-target-parent="{{ $parent }}"
    data-cms-target-slot="{{ $slotName }}"
    data-cms-target-position="{{ $position }}"
    x-data="{
        search: '',
        matches(text) {
            return this.search.trim() === '' || text.includes(this.search.trim().toLowerCase());
        },
        hasAny(list) {
            return list.some(text => this.matches(text));
        },
        pick(payload) {
            window.dispatchEvent(new CustomEvent('cms-library-pick', { detail: payload, bubbles: true }));
        },
    }"
>
    @if($categories === [])
        <p class="cms-library__note">{{ __('moonshine-cms::editor.library_nothing_allowed') }}</p>
    @else
        <input
            type="search"
            class="form-input cms-library__search"
            data-cms-library-search
            placeholder="{{ __('moonshine-cms::editor.search') }}"
            x-model="search"
        >

        <p class="cms-library__note" x-show="! hasAny(@js($all))" x-cloak>
            {{ __('moonshine-cms::editor.library_empty') }}
        </p>

        @foreach($categories as $category)
            @php
                $categoryNeedles = array_map($needle, $category->entries);
            @endphp
            <section
                @class([
                    'cms-library__category' => true,
                    'cms-library__category--shared' => $category->shared,
                ])
                data-cms-category="{{ $category->code }}"
                x-show="hasAny(@js($categoryNeedles))"
            >
                <h4 class="cms-library__category-title">{{ $category->title }}</h4>

                <div class="cms-library__entries">
                    @foreach($category->entries as $entry)
                        <div class="cms-library__entry" x-show="matches(@js($needle($entry)))">
                            <button
                                type="button"
                                class="cms-library__tile"
                                data-cms-icon="{{ $entry->iconKey }}"
                                data-cms-entry="{{ $entry->code }}"
                                x-on:click="pick(@js([
                                    'code' => $entry->code,
                                    'variant' => null,
                                    'shared_block' => $entry->sharedBlockId,
                                    'parent' => $parent,
                                    'slot' => $slotName,
                                    'position' => $position,
                                ]))"
                            >
                                <span class="cms-library__icon">{!! $entry->icon !!}</span>

                                <span class="cms-library__entry-title">
                                    {{ $entry->title }}

                                    @if($entry->shared)
                                        <x-moonshine::badge color="purple">{{ __('moonshine-cms::editor.shared_badge') }}</x-moonshine::badge>
                                    @endif
                                </span>

                                @if($entry->about)
                                    <span class="cms-library__entry-about">{{ $entry->about }}</span>
                                @endif
                            </button>

                            @if($entry->variants !== [])
                                <div class="cms-library__variants">
                                    @foreach($entry->variants as $variant)
                                        <button
                                            type="button"
                                            class="cms-library__variant"
                                            data-cms-icon="{{ $variant->iconKey }}"
                                            x-on:click="pick(@js([
                                                'code' => $entry->code,
                                                'variant' => $variant->code,
                                                'shared_block' => $entry->sharedBlockId,
                                                'parent' => $parent,
                                                'slot' => $slotName,
                                                'position' => $position,
                                            ]))"
                                        >
                                            <span class="cms-library__icon">{!! $variant->icon !!}</span>
                                            {{ $variant->title }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            @if($entry->warning)
                                <p class="cms-library__warning">{{ $entry->warning }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif
</div>
