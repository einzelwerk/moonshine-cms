<?php

declare(strict_types=1);

use Ewk\Cms\Layouts\Chrome\ChromeSchemaRegistry;
use Ewk\Cms\Layouts\Models\CmsFooter;
use Ewk\Cms\Layouts\Models\CmsHeader;
use Ewk\Cms\Layouts\Tests\Fixtures\Make;
use Ewk\Cms\Layouts\Tests\Fixtures\TestChromeSchemas;
use Ewk\Cms\Media\Exceptions\MediaInUseException;
use Ewk\Cms\Media\Models\CmsMediaUsage;

beforeEach(function (): void {
    TestChromeSchemas::registerAll(app(ChromeSchemaRegistry::class));
});

/**
 * @return array<string, int>
 */
function chromeUsages(CmsHeader|CmsFooter $owner): array
{
    /** @var array<string, int> $rows */
    $rows = CmsMediaUsage::query()
        ->where('usable_type', $owner->getMorphClass())
        ->where('usable_id', $owner->getKey())
        ->pluck('cms_media_id', 'path')
        ->all();

    return $rows;
}

it('registers the logo of a header as a usage of that header', function (): void {
    $logo = Make::logo();

    $header = CmsHeader::query()->create([
        'code' => 'main',
        'name' => ['ru' => 'Основная'],
        'is_active' => true,
        'content' => ['logo' => ['id' => $logo->uuid], 'sticky' => true],
    ]);

    expect(chromeUsages($header))->toBe(['logo' => $logo->id])
        ->and(fn () => $logo->delete())->toThrow(MediaInUseException::class);
});

it('releases the usages of a header that is deleted', function (): void {
    $logo = Make::logo();

    $header = CmsHeader::query()->create([
        'code' => 'main',
        'name' => ['ru' => 'Основная'],
        'is_active' => true,
        'content' => ['logo' => ['id' => $logo->uuid]],
    ]);

    $header->delete();

    expect(chromeUsages($header))->toBe([])
        ->and($logo->fresh()?->delete())->toBeTrue();
});

it('keeps the usages of a kind whose schema is no longer declared', function (): void {
    $logo = Make::logo();

    $header = CmsHeader::query()->create([
        'code' => 'main',
        'name' => ['ru' => 'Основная'],
        'is_active' => true,
        'content' => ['logo' => ['id' => $logo->uuid]],
    ]);

    // An installation that has dropped the declaration reads the content as an
    // opaque bag: rewriting the usages out of an empty schema would release a
    // file the header still shows.
    app()->forgetInstance(ChromeSchemaRegistry::class);
    app()->instance(ChromeSchemaRegistry::class, new ChromeSchemaRegistry());

    $header->touch();

    expect(chromeUsages($header))->toBe(['logo' => $logo->id])
        ->and(fn () => $logo->fresh()?->delete())->toThrow(MediaInUseException::class);
});

it('writes no usages for a footer whose schema declares no media', function (): void {
    $footer = CmsFooter::query()->create([
        'code' => 'main',
        'name' => ['ru' => 'Основной'],
        'is_active' => true,
        'content' => ['copyright' => ['ru' => 'Acme']],
    ]);

    expect(chromeUsages($footer))->toBe([]);
});
