@php
    /** @var \Ewk\MoonShineCms\Editor\BlockCard $card */
    $card = $node['card'];
@endphp
<div
    @class([
        'cms-card' => true,
        'accordion' => true,
        'cms-card--shared' => $card->shared,
        'cms-card--inactive' => ! $card->isActive,
        'cms-card--unknown' => ! $card->known,
    ])
    x-bind:class="{
        'cms-card--open': isOpen({{ $card->id }}),
        'cms-card--dirty': isDirty({{ $card->id }}),
    }"
    data-cms-block="{{ $card->id }}"
    data-cms-uuid="{{ $card->uuid }}"
    data-cms-version="{{ $card->version }}"
>
    <div class="accordion-item" x-bind:class="isOpen({{ $card->id }}) && '_is-opened'">
        @include('moonshine-cms::components.block-editor-head', [
            'card' => $card,
            'deleteUrl' => $endpoints['remove'] ?? '',
            'fragmentEvent' => $fragmentEvent,
        ])

        <div class="accordion-body cms-card__body" x-bind:class="isOpen({{ $card->id }}) ? 'block' : 'hidden'" x-cloak>
            <div class="accordion-content">
                @if($card->shared && $card->sharedWarning)
                    <p class="cms-card__note">{{ $card->sharedWarning }}</p>
                @endif

                @unless($card->known)
                    <p class="cms-card__note">{{ __('moonshine-cms::editor.unknown_type_row') }}</p>
                @endunless

                {!! $node['form'] !!}

                @if($node['slots'] !== [])
                    <div class="cms-card__slots">
                        @foreach($node['slots'] as $slotNode)
                            @php
                                /** @var \Ewk\MoonShineCms\Editor\SlotView $slot */
                                $slot = $slotNode['slot'];
                            @endphp
                            <section
                                class="cms-card__slot"
                                style="--cms-slot-span: {{ $slot->span }}"
                                data-cms-slot="{{ $slot->name }}"
                                data-cms-span="{{ $slot->span }}"
                            >
                                <div class="cms-card__slot-head">
                                    <span>{{ $slot->title }}</span>

                                    @unless($slot->isFull())
                                        <button
                                            type="button"
                                            class="btn btn-secondary btn-square cms-card__add-to-slot"
                                            title="{{ __('moonshine-cms::editor.add_to_slot') }}"
                                            aria-label="{{ __('moonshine-cms::editor.add_to_slot') }}"
                                            x-on:click="openLibrary({{ $card->id }}, @js($slot->name), {{ count($slotNode['cards']) }})"
                                        >
                                            <x-moonshine::icon icon="plus" size="4" />
                                        </button>
                                    @endunless
                                </div>

                                {{-- The list is the drop target of the sortable: the group is
                                     the same everywhere, so a card travels between the root
                                     and any slot and the server decides whether it may. --}}
                                <div
                                    class="cms-card__slot-list"
                                    data-cms-container
                                    data-id="{{ $card->id }}"
                                    data-slot="{{ $slot->name }}"
                                >
                                    @foreach($slotNode['cards'] as $index => $childNode)
                                        @unless($slot->isFull())
                                            <x-moonshine-cms::block-editor-insert
                                                :parent="$card->id"
                                                :slot-name="$slot->name"
                                                :position="$index"
                                                :key-prefix="$card->id.'-'.$slot->name"
                                            />
                                        @endunless

                                        @include('moonshine-cms::components.block-editor-card', [
                                            'node' => $childNode,
                                            'endpoints' => $endpoints,
                                            'fragmentEvent' => $fragmentEvent,
                                        ])
                                    @endforeach
                                </div>

                                @unless($slot->isFull())
                                    <x-moonshine-cms::block-editor-insert
                                        :parent="$card->id"
                                        :slot-name="$slot->name"
                                        :position="count($slotNode['cards'])"
                                        :key-prefix="$card->id.'-'.$slot->name"
                                    />
                                @endunless
                            </section>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
