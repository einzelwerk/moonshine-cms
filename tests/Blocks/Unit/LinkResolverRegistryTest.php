<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Blocks\Links\LinkResolverRegistry;
use Ewk\Cms\Blocks\Links\Resolvers\EmailLinkResolver;
use Ewk\Cms\Blocks\Links\Resolvers\PhoneLinkResolver;
use Ewk\Cms\Blocks\Links\Resolvers\UrlLinkResolver;

function linkRegistry(): LinkResolverRegistry
{
    $registry = new LinkResolverRegistry();
    $registry->register(new UrlLinkResolver());
    $registry->register(new EmailLinkResolver());
    $registry->register(new PhoneLinkResolver());

    return $registry;
}

it('resolves an external address and marks it external', function (): void {
    $resolved = linkRegistry()->resolve(
        new Link(LinkKind::URL, ['url' => 'https://example.com'], newTab: true),
        'ru',
    );

    expect($resolved->toArray())->toBe([
        'kind' => 'url',
        'href' => 'https://example.com',
        'external' => true,
        'new_tab' => true,
        'action' => null,
    ]);
});

it('resolves mail and phone into their schemes without marking them external', function (): void {
    $mail = linkRegistry()->resolve(new Link(LinkKind::EMAIL, ['email' => 'sales@example.com']), 'ru');
    $phone = linkRegistry()->resolve(new Link(LinkKind::PHONE, ['phone' => '+420123456789']), 'ru');

    expect($mail->href)->toBe('mailto:sales@example.com')
        ->and($mail->external)->toBeFalse()
        ->and($phone->href)->toBe('tel:+420123456789')
        ->and($phone->external)->toBeFalse();
});

it('round-trips a stored link through its array shape', function (): void {
    $stored = [
        'kind' => 'list',
        'attributes' => ['page_uuid' => '9f3c', 'filters' => ['city' => 3]],
        'new_tab' => false,
    ];

    $link = Link::fromArray($stored);

    expect($link->kind)->toBe(LinkKind::LISTING)
        ->and($link->attribute('filters'))->toBe(['city' => 3])
        ->and($link->toArray())->toBe($stored);
});
