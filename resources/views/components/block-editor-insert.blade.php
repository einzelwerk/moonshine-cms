@props([
    /* The place a new block goes: between two cards, at the top of a list, at
       its end or inside a named slot — insertion goes to an exact position
       and never only to the end. */
    'parent' => null,
    'slotName' => null,
    'position' => 0,
    'keyPrefix' => 'root',
])
@php
    $parentArg = $parent === null ? '' : (string) (int) $parent;
    $slotArg = $slotName === null ? '' : (string) $slotName;
@endphp
<div class="cms-insert">
    <button
        type="button"
        class="cms-insert__button"
        data-cms-insert-at="{{ $keyPrefix.'-'.$position }}"
        aria-label="{{ __('moonshine-cms::editor.insert_here') }}"
        title="{{ __('moonshine-cms::editor.insert_here') }}"
        x-on:click="openLibrary(@js($parentArg), @js($slotArg), {{ $position }})"
    >
        <x-moonshine::icon icon="plus" size="4" />
    </button>
</div>
