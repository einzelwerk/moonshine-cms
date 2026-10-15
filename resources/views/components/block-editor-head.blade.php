@props([
    /* The head of one card: one handle, the glyph of the type, the caption the
       content gives it, the badges of its state and the compact actions.
       Rendered on its own after a save, so it takes nothing from the
       surrounding loop. */
    'card',
    'deleteUrl' => '',
    'fragmentEvent' => '',
])
@php
    /** @var \Ewk\MoonShineCms\Editor\BlockCard $card */
@endphp
<div class="cms-card__head" data-cms-card-head="{{ $card->id }}">
    <span
        class="cms-card__handle"
        role="button"
        data-cms-handle="{{ $card->id }}"
        title="{{ __('moonshine-cms::editor.drag') }}"
        aria-label="{{ __('moonshine-cms::editor.drag') }}"
    >
        <x-moonshine::icon icon="bars-3-bottom-right" size="4" />
    </span>

    <button
        type="button"
        class="accordion-btn cms-card__summary"
        x-bind:class="isOpen({{ $card->id }}) && '_is-active'"
        x-on:click.prevent="toggle({{ $card->id }})"
    >
        <span class="cms-card__title">
            @if($card->icon !== '')
                <span class="cms-card__icon" data-cms-icon="block-{{ $card->type }}" aria-hidden="true">{!! $card->icon !!}</span>
            @endif

            <span class="cms-card__type">{{ $card->typeTitle }}</span>

            @if($card->caption !== '')
                <span class="cms-card__caption truncate">{{ $card->caption }}</span>
            @endif

            @if($card->variantTitle)
                <span class="cms-card__variant">{{ $card->variantTitle }}</span>
            @endif
        </span>

        <x-moonshine::icon icon="chevron-down" size="3" />
    </button>

    <span class="cms-card__badges">
        <template x-if="isDirty({{ $card->id }})">
            <x-moonshine::badge color="primary">{{ __('moonshine-cms::editor.dirty_badge') }}</x-moonshine::badge>
        </template>

        @if($card->shared)
            <x-moonshine::badge color="purple">{{ __('moonshine-cms::editor.shared_badge') }}</x-moonshine::badge>
        @endif

        @unless($card->isActive)
            <x-moonshine::badge color="gray">{{ __('moonshine-cms::editor.inactive_badge') }}</x-moonshine::badge>
        @endunless

        @unless($card->known)
            <x-moonshine::badge color="warning">{{ __('moonshine-cms::editor.unknown_badge') }}</x-moonshine::badge>
        @endunless
    </span>

    <span class="cms-card__actions">
        @if($card->switchable)
            <button
                type="button"
                class="btn btn-secondary btn-square cms-card__activity"
                data-cms-toggle-active="{{ $card->id }}"
                x-bind:title="isActive({{ $card->id }})
                    ? @js(__('moonshine-cms::editor.switch_off'))
                    : @js(__('moonshine-cms::editor.switch_on'))"
                x-on:click="toggleActive({{ $card->id }})"
            >
                <span x-show="isActive({{ $card->id }})">
                    <x-moonshine::icon icon="eye" size="4" />
                </span>
                <span x-show="! isActive({{ $card->id }})" x-cloak>
                    <x-moonshine::icon icon="eye-slash" size="4" />
                </span>
            </button>
        @endif

        @if($card->removable && $deleteUrl !== '')
            {!! \Ewk\MoonShineCms\Editor\EditorActions::delete($card, $deleteUrl, $fragmentEvent) !!}
        @endif
    </span>
</div>
