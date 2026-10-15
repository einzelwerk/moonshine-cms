<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Tests\Fixtures\Sitemap;

use DateTimeInterface;
use Ewk\Cms\Blocks\Models\CmsVersion;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Types\Cardinality;
use Ewk\Cms\Pages\Types\PageType;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Ewk\Cms\Pages\Types\Subject;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * The site a sitemap test stands on: a home page, pages of the tree and a
 * template type of concerts living under their series.
 */
final class SitemapWorld
{
    public static function build(): void
    {
        Schema::create('test_sitemap_series', static function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
        });

        Schema::create('test_sitemap_concerts', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('series_id');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
        });

        $types = app(PageTypeRegistry::class);

        $types->register(PageType::make('home')->cardinality(Cardinality::SINGLE)->path('/'));
        $types->register(PageType::make('page')->cardinality(Cardinality::MANY));
        $types->register(
            PageType::make('concert')
                ->cardinality(Cardinality::TEMPLATE)
                ->subject(
                    Subject::model(Concert::class)
                        ->by('slug')
                        ->visible('publiclyVisible')
                        ->context(['series' => 'series.slug']),
                )
                ->path('/series/{series}/{concert}'),
        );
    }

    public static function page(
        string $path,
        string $type = 'page',
        bool $active = true,
        ?DateTimeInterface $publishedAt = new Carbon('2026-09-14 10:00:00'),
    ): CmsPage {
        $slug = trim($path, '/') === '' ? 'home' : basename($path);

        $page = new CmsPage([
            'type' => $type,
            'name' => ['ru' => $slug],
            'slug' => $slug,
            'is_active' => $active,
        ]);
        $page->path = $path;
        $page->depth = substr_count(trim($path, '/'), '/');
        $page->save();

        if ($publishedAt !== null) {
            self::publish($page, $publishedAt);
        }

        return $page;
    }

    public static function publish(CmsPage $page, DateTimeInterface $at): CmsVersion
    {
        return CmsVersion::query()->create([
            'owner_type' => CmsPage::MORPH_ALIAS,
            'owner_id' => $page->id,
            'number' => 1,
            'snapshot' => ['blocks' => []],
            'locales' => ['ru'],
            'published_at' => $at,
        ]);
    }

    /**
     * The template every concert is drawn by. It has no address of its own.
     */
    public static function template(?DateTimeInterface $publishedAt = new Carbon('2026-09-01 10:00:00'), bool $active = true): CmsPage
    {
        return self::page('/series/{series}/{concert}', 'concert', $active, $publishedAt);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function concert(string $slug, string $series = 'jazz', array $attributes = [], ?DateTimeInterface $updatedAt = null): Concert
    {
        $parent = Series::query()->firstOrCreate(['slug' => $series]);

        $concert = new Concert($attributes + ['series_id' => $parent->id, 'slug' => $slug]);

        if ($updatedAt !== null) {
            $concert->timestamps = false;
            $concert->created_at = new Carbon($updatedAt);
            $concert->updated_at = new Carbon($updatedAt);
        }

        $concert->save();

        return $concert;
    }
}
