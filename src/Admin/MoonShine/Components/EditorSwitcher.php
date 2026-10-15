<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Components;

use Ewk\MoonShineCms\Editor\EditorAssets;
use MoonShine\Contracts\AssetManager\AssetElementContract;
use MoonShine\UI\Components\MoonShineComponent;

/**
 * «Settings | Blocks»: the two halves of one record, drawn as the tabs of the
 * panel and not as a pair of buttons. They read as a switch between two views
 * of the same thing, which is what the tabs inside the form already say, and a
 * pair of filled buttons said «two actions» instead.
 *
 * The two pages live apart because a card of the editor is a form of its own
 * and HTML forbids nesting those.
 */
final class EditorSwitcher extends MoonShineComponent
{
    protected string $view = 'moonshine-cms::components.editor-switcher';

    public function __construct(
        private readonly string $settingsUrl,
        private readonly string $editorUrl,
        private readonly bool $editorActive,
    ) {
        parent::__construct();
    }

    public static function between(string $settingsUrl, string $editorUrl, bool $editorActive): self
    {
        return new self($settingsUrl, $editorUrl, $editorActive);
    }

    /**
     * @return list<AssetElementContract>
     */
    protected function assets(): array
    {
        return EditorAssets::inline();
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        return [
            'settingsUrl' => $this->settingsUrl,
            'editorUrl' => $this->editorUrl,
            'editorActive' => $this->editorActive,
        ];
    }
}
