@props([
    'fragmentName' => 'cms-blocks',
    'fragmentEvent' => 'fragment_updated:cms-blocks',
    'cards' => [],
    'cardIds' => [],
    'labels' => [],
    'locales' => [],
    'defaultLocale' => null,
    'state' => null,
    'hint' => null,
    'lead' => null,
    'library' => null,
    'stateKey' => 'cms-editor',
    'group' => 'cms-blocks',
    'endpoints' => [],
])
<div
    class="cms-editor"
    x-on:input="touch($event)"
    x-on:change="touch($event)"
    x-on:focusout="touch($event)"
    x-on:cms-block-saved.window="rebaseline($event.detail.block)"
    x-on:cms-library-pick.window="insert($event.detail)"
    x-data="cmsBlockEditor({
        token: '{{ csrf_token() }}',
        endpoints: @js($endpoints),
        labels: @js($labels),
        locales: @js($locales),
        defaultLocale: @js($defaultLocale),
        event: '{{ $fragmentEvent }}',
        stateKey: @js($stateKey),
        modal: @js(\Ewk\MoonShineCms\MoonShine\Components\BlockEditor::LIBRARY_MODAL),
        group: @js($group),
        ids: @js($cardIds),
    })"
>
    @if($lead !== null || count($locales) > 1)
        {{-- One line above the editor: the switch of the record on the left,
             the language of the whole editor on the right. The same line the
             form of the record carries, so neither moves when an editor walks
             between the two. --}}
        <div class="cms-editor__bar">
            @if($lead !== null)
                <div class="cms-editor__lead">{!! $lead !!}</div>
            @endif

            @if(count($locales) > 1)
                <span class="cms-editor__bar-label">{{ __('moonshine-cms::editor.language') }}</span>

                {{-- One switch for the whole editor: every translatable field
                     shows the language it selects, in place. --}}
                <div class="cms-editor__locales" role="group" aria-label="{{ __('moonshine-cms::editor.language') }}">
                    @foreach($locales as $locale)
                        <button
                            type="button"
                            class="cms-editor__locale"
                            data-cms-locale-switch="{{ $locale }}"
                            x-bind:class="locale === @js($locale) && '_is-active'"
                            x-on:click="setLocale(@js($locale))"
                        >{{ mb_strtoupper($locale) }}</button>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <div class="cms-editor__panel">
        <div class="cms-editor__state" data-cms-status="{{ $state?->status->value }}">
            <x-moonshine::badge :color="$state?->tone() ?? 'gray'">
                {{ $state?->label() }}
            </x-moonshine::badge>

            @if($state?->publishedAt)
                <span class="cms-editor__published-at">{{ \Ewk\Cms\Media\MoonShine\Support\DisplayTime::local($state->publishedAt)->toDateTimeString() }}</span>
            @endif

            @if($state && $state->incompleteLocales !== [])
                <span class="cms-editor__incomplete">
                    {{ __('moonshine-cms::editor.incomplete_locales') }}
                    {{ implode(', ', array_map('mb_strtoupper', $state->incompleteLocales)) }}
                </span>
            @endif

            @if($cards !== [])
                <button type="button" class="cms-editor__toggle" x-on:click="expandAll()">
                    {{ __('moonshine-cms::editor.expand_all') }}
                </button>
                <button type="button" class="cms-editor__toggle" x-on:click="collapseAll()">
                    {{ __('moonshine-cms::editor.collapse_all') }}
                </button>
            @endif
        </div>

        {{-- The count is measured again whenever the pointer comes near the
             buttons: a disabled button reports no pointer of its own, so the
             row it stands in does it for the whole group. --}}
        <div class="cms-editor__actions" x-on:mouseenter="refreshDirty()">
            @if($cards !== [])
                {{-- Nothing to save is a button that cannot be pressed: the
                     count next to the caption says how many cards are
                     waiting. --}}
                <button
                    type="button"
                    class="btn btn-primary cms-editor__save-all"
                    data-cms-save-all
                    x-bind:disabled="saving > 0 || dirty.length === 0"
                    x-on:mouseenter="refreshDirty()"
                    x-on:focus="refreshDirty()"
                    x-on:click="saveAll()"
                >
                    <x-moonshine::icon icon="check" size="4" />
                    {{ __('moonshine-cms::editor.save_all') }}
                    <span x-show="dirty.length > 0" x-cloak>(<span x-text="dirty.length"></span>)</span>
                </button>
            @endif

            <button
                type="button"
                class="btn btn-secondary cms-editor__add"
                data-cms-add
                x-on:click="openLibrary('', '', {{ count($cards) }})"
            >
                <x-moonshine::icon icon="plus" size="4" />
                {{ __('moonshine-cms::editor.add') }}
            </button>

            {!! \Ewk\MoonShineCms\Editor\EditorActions::publish($endpoints['publish'] ?? '', $fragmentEvent) !!}
        </div>

        @if($hint)
            <p class="cms-editor__hint">{{ $hint }}</p>
        @endif

        @if($state && $state->problems !== [])
            <p class="cms-editor__hint cms-editor__problems">
                {{ __('moonshine-cms::editor.problems') }}
                {{ implode('; ', $state->problems) }}
            </p>
        @endif

        @if($cards === [])
            <p class="cms-editor__empty">{{ __('moonshine-cms::editor.empty') }}</p>
        @endif
    </div>

    <div class="cms-editor__list" data-cms-container data-id="" data-slot="">
        @foreach($cards as $node)
            <x-moonshine-cms::block-editor-insert
                :parent="null"
                :slot-name="null"
                :position="$loop->index"
                key-prefix="root"
            />

            @include('moonshine-cms::components.block-editor-card', [
                'node' => $node,
                'endpoints' => $endpoints,
                'fragmentEvent' => $fragmentEvent,
            ])
        @endforeach
    </div>

    @if($cards !== [])
        <x-moonshine-cms::block-editor-insert
            :parent="null"
            :slot-name="null"
            :position="count($cards)"
            key-prefix="root"
        />
    @endif

    {!! $library !!}

    {{-- The dialog of the media library, for the editor whose only pictures
         live inside a slider: a field of a repeater row never draws it, see
         \Ewk\Cms\Media\MoonShine\Library\PickerModal, so the page draws it here when
         no field of its own has. --}}
    {!! \Ewk\Cms\Media\MoonShine\Library\PickerModal::once() !!}
</div>
