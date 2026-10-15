@props([
    'count' => 0,
    'table' => null,
    /* The one way out of a refused deletion: it is offered only where there
       is something to unlink. */
    'unlinkUrl' => null,
])
<div class="cms-usages" data-cms-shared-usages>
    <div class="cms-usages__head">
        <span class="cms-usages__title">{{ __('moonshine-cms::shared.usages') }}</span>

        {{-- A count of zero next to «not inserted anywhere yet» says the same
             thing twice, so the badge appears only once there is a count. --}}
        @if($count > 0)
            <x-moonshine::badge color="gray">
                {{ __('moonshine-cms::shared.usages_count', ['count' => $count]) }}
            </x-moonshine::badge>
        @endif
    </div>

    <p class="cms-usages__hint">{{ __('moonshine-cms::shared.usages_hint') }}</p>

    @if($count === 0)
        <p class="cms-usages__empty">{{ __('moonshine-cms::shared.usages_empty') }}</p>
    @else
        {!! $table !!}

        @if($unlinkUrl !== null)
            <div class="cms-usages__actions">
                {!! \Ewk\MoonShineCms\Editor\EditorActions::confirmed(
                    (string) __('moonshine-cms::shared.unlink_all'),
                    $unlinkUrl,
                    (string) __('moonshine-cms::shared.unlink_confirm'),
                    'cms-unlink-shared',
                    'link',
                    'data-cms-unlink',
                ) !!}
            </div>
        @endif
    @endif
</div>
