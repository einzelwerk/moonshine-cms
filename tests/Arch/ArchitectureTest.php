<?php

declare(strict_types=1);

/*
 * The package is one install, but its parts keep the layers they were
 * written in: the blocks are the core everything else stands on, the pages,
 * the layouts and the SEO part build on the blocks, and only the admin knows
 * MoonShine. A dependency against the arrows below would make a part
 * impossible to read, test or replace on its own.
 */

arch('the blocks depend on no other part of the package')
    ->expect('Ewk\Cms\Blocks')
    ->not->toUse([
        'Ewk\Cms\Pages',
        'Ewk\Cms\Layouts',
        'Ewk\Cms\Seo',
        'Ewk\MoonShineCms',
    ]);

arch('the pages depend on the blocks only')
    ->expect('Ewk\Cms\Pages')
    ->not->toUse([
        'Ewk\Cms\Layouts',
        'Ewk\Cms\Seo',
        'Ewk\MoonShineCms',
    ]);

arch('the layouts depend on the blocks only')
    ->expect('Ewk\Cms\Layouts')
    ->not->toUse([
        'Ewk\Cms\Seo',
        'Ewk\MoonShineCms',
    ]);

// The chrome, the menus and their links are complete without a tree of
// pages: the shell a page is drawn in reaches the answer of the resolver
// through one contributor, which the provider registers when the pages are
// there, and nothing else of the layouts knows the pages.
arch('the layouts reach the pages through the resolve contributor alone')
    ->expect('Ewk\Cms\Layouts')
    ->not->toUse('Ewk\Cms\Pages')
    ->ignoring([
        'Ewk\Cms\Layouts\CmsLayoutsServiceProvider',
        'Ewk\Cms\Layouts\Selection\LayoutResolveContributor',
    ]);

arch('the SEO part depends on the blocks and the pages only')
    ->expect('Ewk\Cms\Seo')
    ->not->toUse([
        'Ewk\Cms\Layouts',
        'Ewk\MoonShineCms',
    ]);

arch('the core parts do not depend on MoonShine')
    ->expect(['Ewk\Cms\Blocks', 'Ewk\Cms\Pages', 'Ewk\Cms\Layouts', 'Ewk\Cms\Seo'])
    ->not->toUse(['MoonShine', 'Ewk\Cms\Media\MoonShine']);

arch('ewk/moonshine-cms does not depend on App')
    ->expect(['Ewk\Cms\Blocks', 'Ewk\Cms\Pages', 'Ewk\Cms\Layouts', 'Ewk\Cms\Seo', 'Ewk\MoonShineCms'])
    ->not->toUse('App');

// The admin of the media library lives in `ewk/moonshine-media` under
// `Ewk\Cms\Media\MoonShine`; none of its former places in this package may
// come back.
arch('ewk/moonshine-cms keeps no copy of the media admin')
    ->expect(['Ewk\Cms\Blocks', 'Ewk\Cms\Pages', 'Ewk\Cms\Layouts', 'Ewk\Cms\Seo', 'Ewk\MoonShineCms'])
    ->not->toUse([
        'Ewk\MoonShineCms\Media',
        'Ewk\MoonShineCms\Concerns\InteractsWithMediaAttachments',
        'Ewk\MoonShineCms\Http\Controllers\MediaLibraryController',
        'Ewk\MoonShineCms\Http\Controllers\MediaLibraryCommandsController',
        'Ewk\MoonShineCms\MoonShine\Components\MediaLibrary',
        'Ewk\MoonShineCms\MoonShine\Fields\MediaPicker',
        'Ewk\MoonShineCms\MoonShine\Fields\MediaAttachmentsField',
        'Ewk\MoonShineCms\MoonShine\Fields\AttachmentPreview',
        'Ewk\MoonShineCms\MoonShine\Fields\CompositeValueField',
        'Ewk\MoonShineCms\MoonShine\Fields\Concerns\NarrowsToKinds',
        'Ewk\MoonShineCms\MoonShine\Pages\MediaLibraryPage',
        'Ewk\MoonShineCms\Support\DisplayTime',
        'Ewk\Cms\Blocks\Localization\LanguageCatalog',
        'Ewk\Cms\Blocks\Localization\ConfigLanguageCatalog',
    ]);

arch('ewk/moonshine-cms has strict types everywhere')
    ->expect(['Ewk\Cms\Blocks', 'Ewk\Cms\Pages', 'Ewk\Cms\Layouts', 'Ewk\Cms\Seo', 'Ewk\MoonShineCms'])
    ->toUseStrictTypes();

/*
 * The expectations above read PHP classes only. A view, a script, a route
 * file or a configuration file names a class in plain text, so those are
 * read as text: nothing the package ships may name a class of an
 * application, or a class of the media admin by its former name here.
 */
it('names no class of an application or of the former media admin anywhere it ships', function (): void {
    $root = dirname(__DIR__, 2);
    $offenders = [];

    foreach (['src', 'config', 'routes', 'resources', 'lang', 'database'] as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());

            $patterns = [
                // A class of an application.
                '/(?<![\w\\\\])App\\\\+[A-Z]/' => [],
                // The media admin under its former namespace.
                '/Ewk\\\\+MoonShineCms\\\\+(?:Media\\\\+|Support\\\\+DisplayTime\b|Concerns\\\\+InteractsWithMediaAttachments\b|Http\\\\+Controllers\\\\+MediaLibrary|MoonShine\\\\+(?:Components\\\\+MediaLibrary\b|Pages\\\\+MediaLibraryPage\b|Fields\\\\+(?:MediaPicker|MediaAttachmentsField|AttachmentPreview|CompositeValueField|Concerns\\\\+NarrowsToKinds)\b))/' => [],
                // The language catalog under its former namespace.
                '/Ewk\\\\+Cms\\\\+Blocks\\\\+Localization\\\\+(?:Config)?LanguageCatalog\b/' => [],
                // Its views, words and configuration under the former names.
                '/moonshine-cms::(?:media\.|library\.|components\.media-|fields\.(?:media-|attachment-preview))|moonshine-cms\.(?:thumbnail_preset|display_timezone)|cms-blocks\.(?:locales|default_locale)/' => [],
            ];

            $relative = substr($file->getPathname(), strlen($root) + 1);

            foreach ($patterns as $pattern => $allowed) {
                if (! in_array($relative, $allowed, true) && preg_match($pattern, $content) === 1) {
                    $offenders[] = $relative;

                    break;
                }
            }
        }
    }

    expect($offenders)->toBe([]);
});
