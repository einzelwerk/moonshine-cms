<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Tests\Fixtures;

use Ewk\Cms\Pages\Models\CmsPage as PageModel;
use Ewk\Cms\Pages\Services\PageTree;

/**
 * Rows for the tests.
 */
final class Make
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function page(string $slug, ?PageModel $parent = null, string $type = 'page', array $attributes = [], ?int $position = null): PageModel
    {
        return app(PageTree::class)->create(
            new PageModel($attributes + ['type' => $type, 'slug' => $slug, 'name' => ['ru' => $slug]]),
            $parent,
            $position,
        );
    }

    public static function concert(string $series, string $slug, bool $active = true, bool $hidden = false): Concert
    {
        $owner = Series::query()->firstOrCreate(['slug' => $series]);

        return Concert::query()->create([
            'series_id' => $owner->id,
            'slug' => $slug,
            'is_active' => $active,
            'is_hidden' => $hidden,
        ]);
    }
}
