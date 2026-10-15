<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Components;

use Ewk\Cms\Media\Localization\LanguageCatalog;
use Illuminate\Container\Container;
use MoonShine\AssetManager\InlineCss;
use MoonShine\AssetManager\InlineJs;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Components\AbstractWithComponents;

/**
 * The wrapper every entity form of the CMS is drawn in: one language switch
 * above the fields with the switch of the record beside it, and the address
 * built from the name while a record is created.
 *
 * It exists because both things are decisions about the whole form. A
 * translatable field shows the language the switch selects, in the place the
 * form declares it, instead of sinking into a tab strip of its own; and a field
 * of a form cannot host the scope its neighbours read.
 *
 * Nothing but the scope: the fields are those of the form, and a form without
 * translatable values simply carries no switch.
 */
final class LocaleScope extends AbstractWithComponents
{
    protected string $view = 'moonshine-cms::components.locale-scope';

    private string $stateKey = 'cms-form';

    private bool $slugAuto = false;

    private bool $withSwitch = true;

    private ?ComponentContract $lead = null;

    /**
     * @param  iterable<array-key, ComponentContract>  $components
     * @param  bool  $switch  whether the form has translatable values to switch
     *                        between at all: a form without them shows no
     *                        language buttons
     */
    public static function wrap(
        iterable $components,
        string $stateKey,
        bool $slugAuto = false,
        bool $switch = true,
        ?ComponentContract $lead = null,
    ): self {
        $scope = self::make($components);
        $scope->stateKey = $stateKey;
        $scope->slugAuto = $slugAuto;
        $scope->withSwitch = $switch;
        $scope->lead = $lead;

        return $scope;
    }

    /**
     * @return list<\MoonShine\Contracts\AssetManager\AssetElementContract>
     */
    protected function assets(): array
    {
        $assets = [];
        $css = file_get_contents(__DIR__.'/../../../../resources/css/cms-forms.css');
        $js = file_get_contents(__DIR__.'/../../../../resources/js/cms-forms.js');

        if ($css !== false) {
            $assets[] = InlineCss::make($css);
        }

        if ($js !== false) {
            $assets[] = InlineJs::make($js);
        }

        return $assets;
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        $catalog = Container::getInstance()->make(LanguageCatalog::class);

        return [
            'locales' => $this->withSwitch ? $catalog->locales() : [],
            'defaultLocale' => $catalog->defaultLocale(),
            'stateKey' => $this->stateKey,
            'slugAuto' => $this->slugAuto,
            // What belongs to the whole record and not to a field: the switch
            // between the settings and the blocks shares the line with the
            // language instead of costing a row of its own above the form.
            'lead' => $this->lead,
        ];
    }
}
