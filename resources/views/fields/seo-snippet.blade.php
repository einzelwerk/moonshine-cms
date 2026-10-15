@props([
    'nameAttribute' => '',
    'nameDot' => '',
    'locales' => [],
    'defaultLocale' => null,
    'titles' => [],
    'descriptions' => [],
    'fallbackTitle' => null,
    'previewUrl' => '',
    'titleLength' => 60,
    'descriptionLength' => 160,
])
@php
    $base = $nameAttribute;
    $state = [];

    foreach ($locales as $locale) {
        $title = $titles[$locale] ?? '';
        $description = $descriptions[$locale] ?? '';
        $state[$locale] = [
            'title' => is_string($title) ? $title : '',
            'description' => is_string($description) ? $description : '',
        ];
    }
@endphp
{{-- One layout for every language: the title, the description and the snippet
     of the language the switch of the form selects. The pairs of inputs per
     language, each with its own caption, are gone: a form with three
     languages read as three forms. --}}
<div
    class="cms-seo"
    data-cms-seo-snippet
    data-validation-wrapper
    data-validation-field="{{ $base }}"
    x-data="{
        values: @js($state),
        fallback: @js($fallbackTitle ?? ''),
        shownTitle(locale) {
            const own = (this.values[locale]?.title ?? '').trim();

            return own === '' ? this.fallback : own;
        },
        isFallback(locale) {
            return (this.values[locale]?.title ?? '').trim() === '';
        },
    }"
>
    @foreach ($locales as $locale)
        @php
            $titleName = $base.'[title]['.$locale.']';
            $descriptionName = $base.'[description]['.$locale.']';
            $titleId = $nameDot.'-title-'.$locale;
            $descriptionId = $nameDot.'-description-'.$locale;
        @endphp
        <div
            class="cms-seo__locale"
            data-cms-locale="{{ $locale }}"
            x-show="locale === '{{ $locale }}'"
            @if ($locale !== $defaultLocale) style="display:none" @endif
        >
            <label class="form-label" for="{{ $titleId }}">{{ __('cms-seo::seo.fields.title') }}</label>
            <input
                type="text"
                id="{{ $titleId }}"
                name="{{ $titleName }}"
                class="form-input"
                value="{{ $state[$locale]['title'] }}"
                x-model="values['{{ $locale }}'].title"
            >
            {{-- The counter is a recommendation of the search engine and never a
                 limit: the value is not cut. --}}
            <small
                class="cms-seo__counter"
                x-bind:class="values['{{ $locale }}'].title.length > {{ $titleLength }} && '_is-over'"
            >
                <span x-text="values['{{ $locale }}'].title.length"></span>/{{ $titleLength }},
                <span x-show="values['{{ $locale }}'].title.length > {{ $titleLength }}" x-cloak>{{ __('cms-seo::seo.counters.over') }}</span>
                <span x-show="values['{{ $locale }}'].title.length <= {{ $titleLength }}">{{ __('cms-seo::seo.fallbacks.title') }}</span>
            </small>

            <label class="form-label" for="{{ $descriptionId }}">{{ __('cms-seo::seo.fields.description') }}</label>
            <textarea
                id="{{ $descriptionId }}"
                name="{{ $descriptionName }}"
                class="form-input"
                rows="3"
                x-model="values['{{ $locale }}'].description"
            >{{ $state[$locale]['description'] }}</textarea>
            <small
                class="cms-seo__counter"
                x-bind:class="values['{{ $locale }}'].description.length > {{ $descriptionLength }} && '_is-over'"
            >
                <span x-text="values['{{ $locale }}'].description.length"></span>/{{ $descriptionLength }},
                <span x-show="values['{{ $locale }}'].description.length > {{ $descriptionLength }}" x-cloak>{{ __('cms-seo::seo.counters.over') }}</span>
                <span x-show="values['{{ $locale }}'].description.length <= {{ $descriptionLength }}">{{ __('cms-seo::seo.fallbacks.description') }}</span>
            </small>

            {{-- The snippet of the search results: title, address, description,
                 with the fallback greyed out where the title is empty. --}}
            <div class="cms-seo__preview">
                <small class="cms-seo__preview-caption">{{ __('moonshine-cms::seo.preview.title') }}</small>
                <span
                    class="cms-seo__preview-title"
                    x-bind:class="isFallback('{{ $locale }}') && '_is-fallback'"
                    x-text="shownTitle('{{ $locale }}') || @js((string) __('moonshine-cms::seo.preview.empty_title'))"
                ></span>
                <span class="cms-seo__preview-url">{{ $previewUrl }}</span>
                <span
                    class="cms-seo__preview-description"
                    x-text="values['{{ $locale }}'].description"
                ></span>
            </div>
        </div>
    @endforeach
</div>
