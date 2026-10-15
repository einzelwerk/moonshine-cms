<?php

declare(strict_types=1);

use Ewk\Cms\Seo\JsonLd\JsonLdRegistry;
use Ewk\Cms\Seo\Tests\Fixtures\Make;
use Ewk\Cms\Seo\Tests\Fixtures\SeoOwner;
use Ewk\Cms\Seo\Tests\Fixtures\StaticJsonLdBuilder;

it('ships no builder at all: structured data is a matter of the application', function (): void {
    expect(app(JsonLdRegistry::class)->all())->toBe([]);
});

it('collects the objects of every builder that claims the page, in registration order', function (): void {
    $registry = app(JsonLdRegistry::class);
    $registry->register(new StaticJsonLdBuilder([['@type' => 'Event', 'name' => 'First']]));
    $registry->register(new StaticJsonLdBuilder([['@type' => 'Event', 'name' => 'Second'], ['@type' => 'Event', 'name' => 'Third']]));

    $objects = $registry->build(Make::context(new SeoOwner()));

    expect($objects)->toHaveCount(3)
        ->and(array_column($objects, 'name'))->toBe(['First', 'Second', 'Third']);
});

it('does not ask a builder that does not claim the page', function (): void {
    $registry = app(JsonLdRegistry::class);
    $registry->register(new StaticJsonLdBuilder([['@type' => 'Event']], supports: false));

    expect($registry->build(Make::context(new SeoOwner())))->toBe([]);
});
