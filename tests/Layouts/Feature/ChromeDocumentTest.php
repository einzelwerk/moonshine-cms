<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Blocks\Links\LinkResolverContract;
use Ewk\Cms\Blocks\Links\LinkResolverRegistry;
use Ewk\Cms\Blocks\Links\PreloadsLinks;
use Ewk\Cms\Blocks\Links\ResolvedLink;
use Ewk\Cms\Layouts\Chrome\ChromeDocument;
use Ewk\Cms\Layouts\Chrome\ChromeSchemaRegistry;
use Ewk\Cms\Layouts\Menus\MenuSlotCatalog;
use Ewk\Cms\Layouts\Menus\MenuSlotDefinition;
use Ewk\Cms\Layouts\Tests\Fixtures\Make;
use Ewk\Cms\Layouts\Tests\Fixtures\TestChromeSchemas;

beforeEach(function (): void {
    TestChromeSchemas::registerAll(app(ChromeSchemaRegistry::class));
});

function chrome(): ChromeDocument
{
    return app(ChromeDocument::class);
}

it('delivers an item whose target cannot be resolved with an empty address and a broken flag', function (): void {
    $menu = Make::menu();
    // Only external addresses have a resolver in this installation, so a link
    // to an entity is a link whose target cannot be resolved.
    Make::item($menu, 'Concert', new Link(LinkKind::ENTITY, ['type' => 'event', 'id' => 12]));
    Make::item($menu, 'Docs', new Link(LinkKind::URL, ['url' => 'https://example.test/docs']), sortOrder: 1);
    Make::item($menu, 'Legal', sortOrder: 2);

    $header = Make::header();
    Make::slot($header, $menu);

    $items = chrome()->build('ru')['menus'][0]['items'];

    expect($items)->toHaveCount(3)
        ->and($items[0]['label'])->toBe('Concert')
        ->and($items[0]['link']['href'])->toBeNull()
        ->and($items[0]['link']['kind'])->toBe('entity')
        ->and($items[0]['broken'])->toBeTrue()
        // A resolvable target is not broken, and an item without a target is
        // a group caption rather than a breakage.
        ->and($items[1]['link']['href'])->toBe('https://example.test/docs')
        ->and($items[1]['broken'])->toBeFalse()
        ->and($items[2]['link'])->toBeNull()
        ->and($items[2]['broken'])->toBeFalse();
});

it('marks an item whose stored link kind is no longer known as broken instead of dropping it', function (): void {
    $menu = Make::menu();
    $item = Make::item($menu, 'Legacy');
    // A kind written by an older version of the installation.
    $item->link = ['kind' => 'gone', 'attributes' => [], 'new_tab' => false];
    $item->save();

    Make::slot(Make::header(), $menu);

    $items = chrome()->build('ru')['menus'][0]['items'];

    expect($items)->toHaveCount(1)
        ->and($items[0]['label'])->toBe('Legacy')
        ->and($items[0]['link'])->toBeNull()
        ->and($items[0]['broken'])->toBeTrue();
});

it('nests the single level of children in editor order', function (): void {
    $menu = Make::menu();
    $about = Make::item($menu, 'About');
    Make::item($menu, 'Team', parent: $about, sortOrder: 1);
    Make::item($menu, 'History', parent: $about, sortOrder: 0);
    Make::item($menu, 'Hidden', parent: $about, sortOrder: 2, active: false);

    Make::slot(Make::footer(), $menu, 'footer_1');

    $items = chrome()->build('ru')['menus'][0]['items'];

    expect($items)->toHaveCount(1)
        ->and(array_column($items[0]['children'], 'label'))->toBe(['History', 'Team']);
});

it('carries every menu reachable through a slot and no other', function (): void {
    $header = Make::header();
    $footer = Make::footer();

    $primary = Make::menu('primary');
    $legal = Make::menu('legal');
    $disabled = Make::menu('disabled-slot');
    $switchedOff = Make::menu('switched-off', active: false);
    Make::menu('orphan');

    Make::slot($header, $primary, 'header_primary');
    Make::slot($footer, $legal, 'legal');
    Make::slot($footer, $disabled, 'footer_2', enabled: false);
    Make::slot($footer, $switchedOff, 'footer_3');

    $document = chrome()->build('ru');

    expect(array_column($document['menus'], 'code'))->toBe(['legal', 'primary'])
        ->and($document['headers'][0]['slots'])->toBe(['header_primary' => ['primary']])
        // A slot whose menu is switched off or disabled delivers nothing: the
        // frontend is never pointed at a menu the document does not carry.
        ->and($document['footers'][0]['slots'])->toBe(['legal' => ['legal']]);
});

it('keeps a place declared for another record out of the chrome even when a footer holds it', function (): void {
    app(MenuSlotCatalog::class)->register(MenuSlotDefinition::make('mail_legal'));

    $footer = Make::footer();
    $legal = Make::menu('legal');
    $mail = Make::menu('mail-legal');

    Make::slot($footer, $legal, 'legal');
    // A place of a letter hung on the footer of the site by mistake: the
    // frontend does not know it, so the document does not deliver it.
    Make::slot($footer, $mail, 'mail_legal');

    $document = chrome()->build('ru');

    expect(array_column($document['menus'], 'code'))->toBe(['legal'])
        ->and($document['footers'][0]['slots'])->toBe(['legal' => ['legal']]);
});

it('builds the content of a header and a footer through the declared fields', function (): void {
    $header = Make::header('main', [
        'logo' => ['id' => '7f1d9f6c-5b2a-4c1e-9f3d-2b6a8c4e1d55', 'metadata' => ['alt' => ['ru' => 'Логотип']]],
        'sticky' => true,
    ]);

    $footer = Make::footer('main', [
        'copyright' => ['ru' => '© Акме', 'en' => '© Acme'],
        'brand_link' => (new Link(LinkKind::URL, ['url' => 'https://example.test']))->toArray(),
    ]);

    Make::layout('default', default: true, header: $header, footer: $footer);

    $document = chrome()->build('en');

    expect($document['headers'][0]['content'])->toBe([
        'logo' => ['id' => '7f1d9f6c-5b2a-4c1e-9f3d-2b6a8c4e1d55', 'metadata' => ['alt' => ['ru' => 'Логотип']]],
        'sticky' => true,
    ])
        ->and($document['footers'][0]['content']['copyright'])->toBe('© Acme')
        ->and($document['footers'][0]['content']['brand_link']['href'])->toBe('https://example.test')
        ->and($document['layouts'][0])->toBe([
            'code' => 'default',
            'name' => 'Layout default',
            'container' => 'wide',
            'body_class' => 'layout-default',
            'is_default' => true,
            'header' => 'main',
            'footer' => 'main',
        ]);
});

it('leaves out what is switched off', function (): void {
    $hiddenHeader = Make::header('hidden', active: false);
    Make::layout('bare');
    Make::layout('legacy', active: false, sortOrder: 1);
    Make::layout('slim', header: $hiddenHeader, sortOrder: 2);

    $document = chrome()->build('ru');

    expect(array_column($document['layouts'], 'code'))->toBe(['bare', 'slim'])
        ->and($document['headers'])->toBe([])
        // The header is not in the document, so the layout does not name it.
        ->and($document['layouts'][1]['header'])->toBeNull();
});

it('answers one menu by its code', function (): void {
    $menu = Make::menu('legal');
    Make::item($menu, 'Terms', new Link(LinkKind::URL, ['url' => 'https://example.test/terms']));

    expect(chrome()->menu('legal', 'ru'))->toMatchArray([
        'code' => 'legal',
        'name' => 'Меню legal',
        'source' => 'manual',
    ])
        ->and(chrome()->menu('nothing-here', 'ru'))->toBeNull();
});

it('is the same document for everyone: nothing request scoped can enter it', function (): void {
    $header = Make::header('main', ['logo' => null, 'sticky' => false]);
    $menu = Make::menu();
    $root = Make::item($menu, 'About');
    Make::item($menu, 'Team', parent: $root);
    Make::slot($header, $menu);
    Make::layout('default', default: true, header: $header);

    $document = chrome()->build('ru');

    // The shape is a closed list of keys, and the language is the only input:
    // there is no user, session or request anywhere in this package.
    expect(array_keys($document))->toBe(['locale', 'layouts', 'headers', 'footers', 'menus'])
        ->and(array_keys($document['layouts'][0]))
        ->toBe(['code', 'name', 'container', 'body_class', 'is_default', 'header', 'footer'])
        ->and(array_keys($document['headers'][0]))->toBe(['code', 'name', 'content', 'slots'])
        ->and(array_keys($document['menus'][0]))->toBe(['code', 'name', 'source', 'items'])
        ->and(array_keys($document['menus'][0]['items'][0]))
        ->toBe(['label', 'link', 'broken', 'icon', 'variant', 'children'])
        ->and(array_keys($document['menus'][0]['items'][0]['children'][0]))
        ->toBe(['label', 'link', 'broken', 'icon', 'variant', 'children']);

    // Two builds of the same database are byte for byte the same answer.
    expect(chrome()->build('ru'))->toBe($document);
});

/**
 * A resolver of pages that writes down what it is asked, in order.
 */
function recordingPageResolver(): LinkResolverContract&PreloadsLinks
{
    $resolver = new class () implements LinkResolverContract, PreloadsLinks {
        /** @var list<string> */
        public array $calls = [];

        public function supports(LinkKind $kind): bool
        {
            return $kind === LinkKind::PAGE;
        }

        public function resolve(Link $link, string $locale): ResolvedLink
        {
            $page = $link->attribute('page');
            $this->calls[] = 'resolve '.(is_string($page) ? $page : '?');

            return new ResolvedLink(LinkKind::PAGE, '/'.(is_string($page) ? $page : ''), false, $link->newTab);
        }

        public function preload(array $links): void
        {
            $this->calls[] = 'preload '.implode(',', array_map(
                static fn (Link $link): string => (string) $link->attribute('page'),
                $links,
            ));
        }
    };

    app(LinkResolverRegistry::class)->register($resolver);

    return $resolver;
}

it('hands every target of every menu to the resolvers before resolving a single one', function (): void {
    $resolver = recordingPageResolver();

    $main = Make::menu('main');
    $about = Make::item($main, 'About', new Link(LinkKind::PAGE, ['page' => 'about']));
    Make::item($main, 'Team', new Link(LinkKind::PAGE, ['page' => 'team']), parent: $about, sortOrder: 1);
    Make::item($main, 'Docs', new Link(LinkKind::URL, ['url' => 'https://example.test/docs']), sortOrder: 1);
    Make::item($main, 'Caption', sortOrder: 2);

    $legal = Make::menu('legal');
    Make::item($legal, 'AGB', new Link(LinkKind::PAGE, ['page' => 'agb']));

    $header = Make::header();
    Make::slot($header, $main);
    Make::slot(Make::footer(), $legal, 'footer_1');

    $document = chrome()->build('ru');

    // One batch for the whole document, in the order of the menus and their
    // items, and only then one resolution per item. The answer is the one a
    // resolution without the batch gives.
    expect($resolver->calls[0])->toBe('preload agb,about,team')
        ->and(array_slice($resolver->calls, 1))->toEqualCanonicalizing(['resolve agb', 'resolve about', 'resolve team'])
        ->and($document['menus'][1]['items'][0]['link']['href'])->toBe('/about')
        ->and($document['menus'][1]['items'][0]['children'][0]['link']['href'])->toBe('/team');
});

it('hands the targets of a single menu to the resolvers before resolving them', function (): void {
    $resolver = recordingPageResolver();

    $menu = Make::menu('main');
    Make::item($menu, 'About', new Link(LinkKind::PAGE, ['page' => 'about']));
    Make::item($menu, 'AGB', new Link(LinkKind::PAGE, ['page' => 'agb']), sortOrder: 1);

    $answer = chrome()->menu('main', 'ru');

    expect($resolver->calls)->toBe(['preload about,agb', 'resolve about', 'resolve agb'])
        ->and($answer['items'][1]['link']['href'] ?? null)->toBe('/agb');
});
