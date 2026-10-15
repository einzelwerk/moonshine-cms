@props([
    /* The two views of one record. A segmented switch and not a row of tabs:
       the form under it already has tabs of its own, and two rows of tabs read
       as a hierarchy that is not there. */
    'settingsUrl' => '',
    'editorUrl' => '',
    'editorActive' => false,
])
<div class="cms-switcher" role="group" aria-label="{{ __('moonshine-cms::editor.title') }}">
    <a
        href="{{ $settingsUrl }}"
        @class(['cms-switcher__item', '_is-active' => ! $editorActive])
        @if(! $editorActive) aria-current="page" @endif
    >
        <x-moonshine::icon icon="cog-6-tooth" size="4" />
        {{ __('moonshine-cms::editor.settings') }}
    </a>

    <a
        href="{{ $editorUrl }}"
        @class(['cms-switcher__item', '_is-active' => $editorActive])
        @if($editorActive) aria-current="page" @endif
    >
        <x-moonshine::icon icon="squares-2x2" size="4" />
        {{ __('moonshine-cms::editor.title') }}
    </a>
</div>
