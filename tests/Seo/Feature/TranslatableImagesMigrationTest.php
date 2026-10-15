<?php

declare(strict_types=1);

use Ewk\Cms\Media\Localization\LanguageCatalog;
use Ewk\Cms\Media\Models\CmsMediaUsage;
use Ewk\Cms\Seo\Models\CmsMetadata;
use Ewk\Cms\Seo\Tests\Fixtures\Make;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

function translatableImagesMigration(): Migration
{
    return require __DIR__.'/../../../database/migrations/seo/2026_10_13_000061_make_cms_metadata_images_translatable.php';
}

it('moves a picture of a card under the default language, its usage with it', function (): void {
    $migration = translatableImagesMigration();
    $migration->down();

    $owner = Make::owner();
    $og = Make::media('cms/og.jpg');
    $tw = Make::media('cms/tw.jpg');

    $id = DB::table('cms_metadata')->insertGetId([
        'model_type' => $owner->getMorphClass(),
        'model_id' => $owner->getKey(),
        'og_image_id' => $og->id,
        'tw_image_id' => $tw->id,
    ]);

    foreach (['og_image_id' => $og->id, 'tw_image_id' => $tw->id] as $column => $mediaId) {
        DB::table('cms_media_usages')->insert([
            'cms_media_id' => $mediaId,
            'usable_type' => CmsMetadata::MORPH_ALIAS,
            'usable_id' => $id,
            'field' => $column,
            'path' => $column,
        ]);
    }

    $migration->up();

    $locale = app(LanguageCatalog::class)->defaultLocale();
    $metadata = CmsMetadata::query()->findOrFail($id);

    expect($metadata->og_image)->toBe([$locale => $og->id])
        ->and($metadata->tw_image)->toBe([$locale => $tw->id])
        ->and(CmsMediaUsage::query()->where('usable_id', $id)->orderBy('field')->get(['field', 'path'])->toArray())
        ->toBe([
            ['field' => 'og_image', 'path' => 'og_image.'.$locale],
            ['field' => 'tw_image', 'path' => 'tw_image.'.$locale],
        ]);
});
