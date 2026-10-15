<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Concerns;

use Ewk\Cms\Seo\Contracts\ProvidesSeoFallbacks;
use Ewk\MoonShineCms\Actions\SaveSeoMetadata;
use Ewk\MoonShineCms\MoonShine\Components\LocaleScope;
use Ewk\MoonShineCms\MoonShine\Seo\SeoFields;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use MoonShine\Contracts\Core\CrudResourceContract;
use MoonShine\Contracts\Core\HasResourceContract;
use MoonShine\UI\Components\Tabs\Tab;

/**
 * What a resource of an installation needs to carry the metadata tab: the tab
 * itself and its rules on the form page, the write on the resource.
 *
 * The write is a separate step on purpose. Metadata belongs to a row of
 * `cms_metadata` and not to the edited record, so it cannot ride along on the
 * apply of the panel; a resource calls {@see self::saveSeo()} once its own
 * record is saved and has a key, which is the earliest moment a polymorphic
 * row can point at it.
 *
 * The services are resolved when a method is called and never injected into a
 * constructor: a resource of MoonShine is built by the panel, and a container
 * captured in a constructor is the wrong request under Octane.
 */
trait InteractsWithSeoFields
{
    /**
     * @param  Model|null  $owner  the record being edited; omitted, it is taken
     *                             from the resource of the page, and a form that
     *                             creates a record has none
     * @param  string|null  $fallbackTitle  what an empty title falls back to, shown
     *                                      greyed out in the preview; omitted, the
     *                                      owner is asked through its own contract
     * @param  bool  $localeScope  whether the tab carries a language switch of
     *                             its own: every value of the tab is one per
     *                             language, and only the chosen one is shown.
     *                             A form already drawn inside a
     *                             {@see LocaleScope} passes `false`, so its one
     *                             switch drives the tab as well
     */
    protected function seoTab(?Model $owner = null, ?string $fallbackTitle = null, bool $localeScope = true): Tab
    {
        $owner ??= $this->seoOwner();
        $fallbackTitle ??= $owner instanceof ProvidesSeoFallbacks
            ? $owner->seoFallbackTitle(app()->getLocale())
            : null;

        $components = self::seoFields()->components($owner, $fallbackTitle);

        return Tab::make(
            (string) __('moonshine-cms::seo.tab'),
            $localeScope ? [LocaleScope::wrap($components, stateKey: 'cms-seo-form')] : $components,
        )->icon('magnifying-glass');
    }

    /**
     * @return array<string, list<string|ValidationRule>>
     */
    protected function seoRules(): array
    {
        return self::seoFields()->rules();
    }

    /**
     * @param  array<array-key, mixed>  $input  the submitted form, uploads included
     */
    protected function saveSeo(Model $owner, array $input): void
    {
        Container::getInstance()->make(SaveSeoMetadata::class)($owner, $input);
    }

    /**
     * The record the form edits, when the class using the trait is a page of
     * a resource: a resource itself hands the record to {@see self::saveSeo()}
     * and never asks for it here.
     */
    private function seoOwner(): ?Model
    {
        if (! $this instanceof HasResourceContract) {
            return null;
        }

        $resource = $this->getResource();
        $item = $resource instanceof CrudResourceContract ? $resource->getItem() : null;

        return $item instanceof Model && $item->exists ? $item : null;
    }

    private static function seoFields(): SeoFields
    {
        return Container::getInstance()->make(SeoFields::class);
    }
}
