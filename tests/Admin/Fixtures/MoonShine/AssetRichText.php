<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Tests\Fixtures\MoonShine;

use MoonShine\AssetManager\Js;
use MoonShine\UI\Fields\Textarea;

/**
 * A rich text field that brings a script of its own, the way
 * `moonshine/tinymce` does. The package ships no editor, so the configured
 * class is the only thing the tests can stand in for.
 */
final class AssetRichText extends Textarea
{
    public const string ASSET = 'vendor/moonshine-cms-tests/rich-text.js';

    /**
     * @return list<\MoonShine\Contracts\AssetManager\AssetElementContract>
     */
    protected function assets(): array
    {
        return [Js::make(self::ASSET)];
    }
}
