@props([
    'nameAttribute' => '',
    'nameDot' => '',
    'locales' => [],
    'defaultLocale' => null,
    'values' => [],
    'multiline' => false,
    'switcher' => false,
    'slugSource' => false,
    'recommended' => null,
    'required' => false,
    'disabled' => false,
    'readonly' => false,
])
<div class="cms-translatable" data-cms-translatable data-validation-wrapper data-validation-field="{{ $nameAttribute }}">
    @foreach ($locales as $locale)
        @php
            $name = $nameAttribute.'['.$locale.']';
            $id = $nameDot.'-'.$locale;
            $value = $values[$locale] ?? '';
            $value = is_string($value) ? $value : '';
            // With a switch on the form the language is a mark on the input and
            // never a column of captions: every language sits in the same place
            // and only the chosen one is shown.
            $hidden = $switcher && $locale !== $defaultLocale;
            $isSlugSource = $slugSource && $locale === $defaultLocale;
        @endphp
        <div
            @class(['cms-translatable__row', 'cms-translatable__row--switched' => $switcher])
            @if ($switcher)
                data-cms-locale="{{ $locale }}"
                x-show="locale === '{{ $locale }}'"
                @if ($hidden) style="display:none" @endif
            @else
                style="display:flex;align-items:flex-start;gap:.75rem;margin-bottom:.5rem"
            @endif
            @if ($recommended !== null) x-data="{ value: @js($value) }" @endif
        >
            @unless ($switcher)
                <label for="{{ $id }}" class="cms-translatable__locale" style="min-width:2.5rem;padding-top:.6rem;font-weight:600">
                    {{ mb_strtoupper($locale) }}@if ($locale === $defaultLocale && $required)<span class="text-error">*</span>@endif
                </label>
            @endunless
            <div style="flex:1">
                @if ($multiline)
                    <textarea
                        id="{{ $id }}"
                        name="{{ $name }}"
                        class="form-input"
                        rows="3"
                        @if ($recommended !== null) x-model="value" @endif
                        @if ($isSlugSource) data-cms-slug-source @endif
                        @disabled($disabled)
                        @readonly($readonly)
                    >{{ $value }}</textarea>
                @else
                    <input
                        type="text"
                        id="{{ $id }}"
                        name="{{ $name }}"
                        class="form-input"
                        value="{{ $value }}"
                        @if ($recommended !== null) x-model="value" @endif
                        @if ($isSlugSource) data-cms-slug-source @endif
                        @disabled($disabled)
                        @readonly($readonly)
                    >
                @endif

                @if ($recommended !== null)
                    {{-- The counter is a recommendation of the search engine and never a
                         limit: the value is not cut. --}}
                    <small class="cms-translatable__counter" x-bind:class="value.length > {{ $recommended }} ? 'text-error' : ''">
                        <span x-text="value.length">{{ mb_strlen($value) }}</span>/{{ $recommended }}
                    </small>
                    <small class="cms-translatable__hint" style="display:block">
                        <span x-show="value.length <= {{ $recommended }}">{{ __('cms-seo::seo.counters.recommended', ['max' => $recommended]) }}</span>
                        <span x-show="value.length > {{ $recommended }}" x-cloak class="text-error">{{ __('cms-seo::seo.counters.over') }}</span>
                    </small>
                @endif
            </div>
        </div>
    @endforeach
</div>
