@props([
    'picker',
    'choices' => [],
    'choice' => '',
    'kind' => '',
    'entityType' => null,
    'entityChoices' => [],
    'linkAttributes' => [],
    'newTab' => false,
    'ownerPage' => null,
    'endpoints' => [],
    'selectedPage' => null,
    'selectedEntity' => null,
    'file' => ['value' => '', 'label' => '', 'image' => ''],
    'mediaPicker' => '',
    'modal' => 'cms-media-picker',
    'modalMarkup' => '',
    'anchor' => '',
    'action' => '',
    'route' => '',
    'actions' => [],
    'routes' => [],
    'params' => [],
    'filters' => [],
])
@php
    /** @var \Ewk\MoonShineCms\MoonShine\Fields\LinkPicker $picker */
    $step = static fn (string $key): string => (string) __('moonshine-cms::links.steps.'.$key);
    $offered = array_column($choices, 'kind');
    $offers = static fn (string $kind): bool => in_array($kind, $offered, true);
    $chosenEntities = [];

    foreach ($entityChoices as $entity) {
        $chosenEntities[$entity['type']] = $entity['type'] === $entityType && is_array($selectedEntity)
            ? $selectedEntity['value']
            : '';
    }

    $labels = [
        'page' => $step('page'),
        'catalogPage' => $step('catalog_page'),
        'anchorPage' => $step('anchor_page'),
        'routeParam' => $step('route_param'),
        'anchorsEmpty' => (string) __('moonshine-cms::links.empty.anchors'),
        'anchorPageEmpty' => (string) __('moonshine-cms::links.empty.anchor_page'),
        'filtersEmpty' => (string) __('moonshine-cms::links.empty.filters'),
        'catalogPageEmpty' => (string) __('moonshine-cms::links.empty.catalog_page'),
    ];
@endphp
<div
    class="cms-link"
    data-cms-link
    data-validation-wrapper
    data-validation-field="{{ $picker->getNameAttribute() }}"
    x-data="cmsLinkPicker({
        choices: @js($choices),
        file: @js($file),
        mediaPicker: @js($mediaPicker),
        modal: @js($modal),
        choice: @js($choice),
        base: @js($picker->getNameAttribute()),
        ownerPage: @js($ownerPage),
        endpoints: @js($endpoints),
        labels: @js($labels),
        entity: @js((object) $chosenEntities),
        anchor: @js($anchor),
        route: @js($route),
        routes: @js($routes),
        params: @js((object) $params),
        filterValues: @js((object) $filters),
        listAnchor: @js($anchor),
        page: @js($selectedPage['value'] ?? ''),
        newTab: @js($newTab),
    })"
    x-on:focusin="syncBase()"
    x-on:cms-media-pick.window="fileChosen($event)"
    x-on:cms-media-close.window="fileDialogClosed($event)"
>
    {{-- The one input named on the server: a row of a repeater renames it on
         the client and everything else is named after it. --}}
    <input type="hidden" x-ref="kindInput" value="{{ $kind }}" x-effect="$el.value = kind" {{ $picker->inputAttributes('[kind]') }}>
    <input type="hidden" x-bind:name="attr('type')" x-effect="$el.value = entityType()" x-bind:disabled="kind !== 'entity'">
    <input type="hidden" x-bind:name="attr('id')" x-effect="$el.value = entityKey()" x-bind:disabled="kind !== 'entity'">

    <div class="cms-link__row">
        <label class="cms-link__field cms-link__field--kind">
            <span class="cms-link__label">{{ __('moonshine-cms::links.kind') }}</span>
            <select class="form-select cms-link__choice" x-model="choice" x-on:change="chosen()">
                @foreach($choices as $item)
                    <option value="{{ $item['id'] }}" @selected($item['id'] === $choice)>{{ $item['label'] }}</option>
                @endforeach
            </select>
        </label>

        @if($choices === [])
            <p class="cms-link__note">{{ __('moonshine-cms::links.empty.kinds') }}</p>
        @endif

        {{-- A page of the tree. One select serves three kinds: a page target, a
             catalogue link, and a section that sits on another page. --}}
        @if($offers('page') || $offers('list') || $offers('anchor'))
            <label class="cms-link__field" x-show="usesPage()" x-cloak>
                <span class="cms-link__label" x-text="pageLabel()"></span>
                <select
                    class="cms-link__select"
                    x-data="select(@js($endpoints['pages'] ?? ''), {}, {})"
                    data-search-enabled="1"
                    data-async-on-init="1"
                    data-async-on-init-dropdown="1"
                    placeholder="{{ __('moonshine-cms::links.choose') }}"
                    x-bind:name="attr('page')"
                    x-bind:disabled="! usesPage()"
                    x-on:change="onPage($event.target.value)"
                >
                    <option value=""></option>
                    @if(is_array($selectedPage))
                        <option value="{{ $selectedPage['value'] }}" selected>{{ $selectedPage['label'] }}</option>
                    @endif
                </select>
            </label>
        @endif

        {{-- One select per class the installation gives an address to: «Тур»
             and «Событие» are separate answers to «where does it lead». --}}
        @foreach($entityChoices as $entity)
            <label class="cms-link__field" x-show="entityType() === @js($entity['type'])" x-cloak>
                <span class="cms-link__label">{{ $entity['label'] }}</span>
                <select
                    class="cms-link__select"
                    x-data="select(@js(($endpoints['entities'] ?? '').'?type='.urlencode((string) $entity['type'])), {}, {})"
                    data-search-enabled="1"
                    data-async-on-init="1"
                    data-async-on-init-dropdown="1"
                    placeholder="{{ __('moonshine-cms::links.choose') }}"
                    x-on:change="entity[@js($entity['type'])] = $event.target.value"
                >
                    <option value=""></option>
                    @if($entity['type'] === $entityType && is_array($selectedEntity))
                        <option value="{{ $selectedEntity['value'] }}" selected>{{ $selectedEntity['label'] }}</option>
                    @endif
                </select>
            </label>
        @endforeach

        {{-- The sections of the page: the blocks that carry an anchor, named
             the way the cards of the editor name them. --}}
        @if($offers('anchor'))
            <label class="cms-link__field" x-show="kind === 'anchor'" x-cloak>
                <span class="cms-link__label">{{ $step('anchor') }}</span>
                <select
                    class="form-select"
                    x-ref="anchorSelect"
                    x-bind:name="attr('anchor')"
                    x-bind:disabled="kind !== 'anchor'"
                    x-on:change="anchor = $event.target.value"
                >
                    <option value="">{{ __('moonshine-cms::links.choose') }}</option>
                    <template x-for="row in anchors" :key="row.value">
                        <option x-bind:value="row.value" x-bind:selected="row.value === anchor" x-text="row.label"></option>
                    </template>
                </select>
                <span class="cms-link__note" x-show="anchorNote() !== ''" x-text="anchorNote()"></span>
            </label>
        @endif

        {{-- A file of the library, chosen in the library: the same dialog the
             picture of a block opens, and not a list of the last fifty files
             with a thumbnail glued to each line. --}}
        @if($offers('file'))
            <div class="cms-link__field" x-show="kind === 'file'" x-cloak>
                <span class="cms-link__label">{{ $step('file') }}</span>
                <input type="hidden" x-bind:name="attr('file')" x-bind:disabled="kind !== 'file'" x-bind:value="file.value">

                <div class="cms-link__file">
                    <span class="cms-link__file-thumb" x-show="file.image" x-cloak>
                        <img x-bind:src="file.image" alt="">
                    </span>
                    <span class="cms-link__file-name" x-text="file.label || @js((string) __('moonshine-cms::links.empty.file'))"></span>

                    <button type="button" class="btn btn-secondary" data-cms-media-open x-on:click="openFilePicker()">
                        {{ __('moonshine-cms::links.choose_file') }}
                    </button>
                    <button type="button" class="btn btn-secondary" x-show="file.value" x-cloak x-on:click="clearFile()">
                        {{ __('moonshine-cms::links.clear_file') }}
                    </button>
                </div>
            </div>
        @endif

        @if($offers('action'))
            <label class="cms-link__field" x-show="kind === 'action'" x-cloak>
                <span class="cms-link__label">{{ $step('action') }}</span>
                <select class="form-select" x-bind:name="attr('action')" x-bind:disabled="kind !== 'action'">
                    <option value="">{{ __('moonshine-cms::links.choose') }}</option>
                    @foreach($actions as $name => $label)
                        <option value="{{ $name }}" @selected($name === $action)>{{ $label }}</option>
                    @endforeach
                </select>
                @if($actions === [])
                    <span class="cms-link__note">{{ __('moonshine-cms::links.empty.actions') }}</span>
                @endif
            </label>
        @endif

        @if($offers('route'))
            <label class="cms-link__field" x-show="kind === 'route'" x-cloak>
                <span class="cms-link__label">{{ $step('route') }}</span>
                <select
                    class="form-select"
                    x-model="route"
                    x-bind:name="attr('route')"
                    x-bind:disabled="kind !== 'route'"
                >
                    <option value="">{{ __('moonshine-cms::links.choose') }}</option>
                    @foreach($routes as $item)
                        <option value="{{ $item['name'] }}" @selected($item['name'] === $route)>{{ $item['label'] }}</option>
                    @endforeach
                </select>
                @if($routes === [])
                    <span class="cms-link__note">{{ __('moonshine-cms::links.empty.routes') }}</span>
                @endif
            </label>
        @endif

        @foreach(['url' => 'url', 'email' => 'email', 'phone' => 'tel'] as $plain => $inputType)
            @continue(! $offers($plain))
            <label class="cms-link__field" x-show="kind === '{{ $plain }}'" x-cloak>
                <span class="cms-link__label">{{ $step($plain) }}</span>
                <input
                    type="{{ $inputType }}"
                    class="form-input"
                    placeholder="{{ __('moonshine-cms::links.placeholders.'.$plain) }}"
                    value="{{ is_scalar($linkAttributes[$plain] ?? null) ? $linkAttributes[$plain] : '' }}"
                    x-bind:name="attr('{{ $plain }}')"
                    x-bind:disabled="kind !== '{{ $plain }}'"
                >
                @if($plain !== 'email')
                    <span class="cms-link__note">{{ __('moonshine-cms::links.hints.'.$plain) }}</span>
                @endif
            </label>
        @endforeach

        <label class="cms-link__new-tab" x-show="allowsNewTab()" x-cloak>
            {{-- The empty caption keeps the switch on the line of the controls
                 beside it: every column of the row is caption over control. --}}
            <span class="cms-link__label" aria-hidden="true"></span>
            <span class="cms-link__new-tab-box">
                <input type="hidden" value="0" x-bind:name="own('new_tab')">
                <input type="checkbox" class="form-checkbox" value="1" x-model="newTab" x-bind:name="own('new_tab')">
                <span>{{ __('moonshine-cms::links.new_tab') }}</span>
            </span>
        </label>
    </div>

    {{-- A catalogue link: the values of the filters the target page declares,
         and, where the page holds more than one list, which one they belong
         to. The address itself is built by the resolver and never stored. --}}
    @if($offers('list'))
        <div class="cms-link__catalog" x-show="kind === 'list'" x-cloak>
            <label class="cms-link__field" x-show="lists.length > 1">
                <span class="cms-link__label">{{ $step('list') }}</span>
                <select
                    class="form-select"
                    x-ref="listSelect"
                    x-bind:name="attr('anchor')"
                    x-bind:disabled="kind !== 'list' || lists.length < 2"
                    x-on:change="listAnchor = $event.target.value"
                >
                    <option value="">{{ __('moonshine-cms::links.choose') }}</option>
                    <template x-for="row in lists" :key="row.anchor">
                        <option x-bind:value="row.anchor" x-bind:selected="row.anchor === listAnchor" x-text="row.label"></option>
                    </template>
                </select>
            </label>

            <template x-for="filter in filters" :key="filter.name">
                <label class="cms-link__field">
                    <span class="cms-link__label" x-text="filter.label"></span>
                    <select
                        x-show="filter.type === 'options'"
                        class="form-select"
                        x-bind:data-cms-filter="filter.name"
                        x-bind:name="filterName(filter.name)"
                        x-bind:disabled="! filterActive(filter, 'options')"
                        x-on:change="filterValues[filter.name] = $event.target.value"
                    >
                        <option value="">{{ __('moonshine-cms::links.any') }}</option>
                        <template x-for="(label, value) in filter.options" :key="value">
                            <option
                                x-bind:value="value"
                                x-bind:selected="String(value) === String(filterValues[filter.name] ?? '')"
                                x-text="label"
                            ></option>
                        </template>
                    </select>
                    <input
                        x-show="filter.type !== 'options'"
                        x-bind:type="filter.type === 'date' ? 'date' : 'text'"
                        class="form-input"
                        x-bind:name="filterName(filter.name)"
                        x-bind:disabled="! filterActive(filter, 'text')"
                        x-bind:value="filterValues[filter.name] ?? ''"
                        x-on:input="filterValues[filter.name] = $event.target.value"
                    >
                </label>
            </template>

            <p class="cms-link__note" x-show="catalogNote() !== ''" x-text="catalogNote()"></p>
        </div>
    @endif

    {{-- A service address of the frontend with a segment of its own: the
         manifest names the segment, the link carries its value. --}}
    @if($offers('route'))
        <div class="cms-link__params" x-show="kind === 'route' && routeParams().length > 0" x-cloak>
            <template x-for="name in routeParams()" :key="name">
                <label class="cms-link__field">
                    <span class="cms-link__label" x-text="paramLabel(name)"></span>
                    <input
                        type="text"
                        class="form-input"
                        x-bind:name="paramName(name)"
                        x-bind:disabled="kind !== 'route'"
                        x-bind:value="params[name] ?? ''"
                        x-on:input="params[name] = $event.target.value"
                    >
                </label>
            </template>
        </div>
    @endif
</div>
{!! $modalMarkup !!}
