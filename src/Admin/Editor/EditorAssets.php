<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Editor;

use MoonShine\AssetManager\InlineCss;
use MoonShine\AssetManager\InlineJs;
use MoonShine\Contracts\AssetManager\AssetElementContract;

/**
 * The stylesheet and the script of the CMS admin, inlined from the files they
 * live in, see {@see \Ewk\MoonShineCms\MoonShine\Components\BlockEditor}: the
 * package ships no build step, and a published asset would have to be
 * republished after every change of a file the application does not own.
 *
 * Whoever needs them asks here. The block editor is not the only screen that
 * does: the link picker is drawn on the form of a menu item and on the header
 * too, and those pages carry no editor. The asset manager keeps one copy of
 * each — identical inline assets are merged — so asking twice on one page
 * costs nothing.
 */
final readonly class EditorAssets
{
    /**
     * @return list<AssetElementContract>
     */
    public static function inline(): array
    {
        $assets = [];

        foreach (['/../../../resources/css/cms-editor.css' => InlineCss::class, '/../../../resources/js/cms-editor.js' => InlineJs::class] as $path => $element) {
            $content = file_get_contents(__DIR__.$path);

            if ($content !== false) {
                $assets[] = $element::make($content);
            }
        }

        return $assets;
    }
}
