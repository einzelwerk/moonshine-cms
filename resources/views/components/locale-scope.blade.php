@props([
    /* The scope of one entity form: the language every translatable field
       follows, and the address built from the name while a record is
       created. */
    'components' => [],
    'locales' => [],
    'defaultLocale' => null,
    'stateKey' => 'cms-form',
    'slugAuto' => false,
    'lead' => null,
])
{{-- `space-elements` is the vertical rhythm of a MoonShine form, and it is
     given to the direct children of the `<form>`. This wrapper stands between
     the two, so it carries the class on itself: the fields keep the spacing of
     the panel instead of a number copied into the stylesheet of the CMS. --}}
<div
    class="cms-form space-elements"
    data-cms-form-scope
    x-data="cmsForm({
        locales: @js($locales),
        defaultLocale: @js($defaultLocale),
        stateKey: @js($stateKey),
        slugAuto: @js($slugAuto),
    })"
>
    @if($lead !== null || count($locales) > 1)
        <div class="cms-form__bar">
            @if($lead !== null)
                <div class="cms-form__lead">{!! $lead !!}</div>
            @endif

            @if(count($locales) > 1)
                <span class="cms-form__bar-label">{{ __('moonshine-cms::seo.language') }}</span>

                <div class="cms-form__locales" role="group" aria-label="{{ __('moonshine-cms::seo.language') }}">
                    @foreach($locales as $locale)
                        <button
                            type="button"
                            class="cms-form__locale"
                            data-cms-locale-switch="{{ $locale }}"
                            x-bind:class="locale === @js($locale) && '_is-active'"
                            x-on:click="setLocale(@js($locale))"
                        >{{ mb_strtoupper($locale) }}</button>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @foreach($components as $component)
        {!! $component !!}
    @endforeach

    {{-- The dialog of the media library, for a form whose only picture sits in
         a repeater row: a field there never draws it, see
         \Ewk\Cms\Media\MoonShine\Library\PickerModal. --}}
    {!! \Ewk\Cms\Media\MoonShine\Library\PickerModal::once() !!}
</div>
