<?php

declare(strict_types=1);

use Ewk\Cms\Layouts\Chrome\ChromeKind;
use Ewk\Cms\Layouts\Chrome\ChromeSchemaRegistry;
use Ewk\Cms\Layouts\Tests\Fixtures\Make;
use Ewk\Cms\Layouts\Tests\Fixtures\TestChromeSchemas;

it('keeps only declared fields and stores them in the shape of their type', function (): void {
    TestChromeSchemas::registerAll(app(ChromeSchemaRegistry::class));

    $footer = Make::footer('main', [
        'copyright' => ['ru' => '  © Акме  ', 'en' => ''],
        'tracking_id' => 'UA-1',
    ]);

    expect($footer->fresh()?->content)->toBe([
        // Trimmed by the text field, per language, and the undeclared key of
        // the request is gone: the schema is the whole contract of `content`.
        'copyright' => ['ru' => '© Акме', 'en' => null],
    ]);
});

it('leaves the stored content alone while no schema is declared for the kind', function (): void {
    $header = Make::header('main', ['anything' => ['deep' => 1]]);

    expect(app(ChromeSchemaRegistry::class)->has(ChromeKind::HEADER))->toBeFalse()
        ->and($header->fresh()?->content)->toBe(['anything' => ['deep' => 1]]);
});

it('normalizes a media reference of a header down to its stored shape', function (): void {
    TestChromeSchemas::registerAll(app(ChromeSchemaRegistry::class));

    $header = Make::header('main', [
        'logo' => ['id' => '7f1d9f6c-5b2a-4c1e-9f3d-2b6a8c4e1d55', 'url' => 'https://cdn.test/logo.svg'],
        'sticky' => 1,
    ]);

    expect($header->fresh()?->content)->toBe([
        // The resolved address of a media file is never stored on the record.
        'logo' => ['id' => '7f1d9f6c-5b2a-4c1e-9f3d-2b6a8c4e1d55'],
        'sticky' => true,
    ]);
});
