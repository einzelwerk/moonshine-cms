<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\Cms\Blocks\Models\CmsVersion;
use Ewk\Cms\Media\Models\CmsMedia;
use Ewk\Cms\Pages\Models\CmsPage;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->actingAsAdmin();
});

it('saves one card and leaves its siblings untouched', function (): void {
    $owner = editorOwner();
    $first = cmsBlock($owner, ['name' => 'Первый', 'content' => ['title' => ['ru' => 'Один']]]);
    $second = cmsBlock($owner, ['name' => 'Второй', 'sort_order' => 1, 'content' => ['title' => ['ru' => 'Два']]]);

    $untouched = $second->updated_at;

    Carbon::setTestNow(Carbon::now()->addMinute());

    $response = $this->post(editorUrl('saveCmsBlock', $owner), [
        'block' => $first->getKey(),
        'version' => blockVersion($first),
        'name' => 'Первый изменён',
        'is_active' => true,
        'content' => [
            'title' => ['ru' => 'Один изменён'],
            'body' => ['ru' => 'Текст'],
        ],
    ]);

    Carbon::setTestNow();

    $response->assertOk();

    $first->refresh();
    $second->refresh();

    expect($first->name)->toBe('Первый изменён')
        ->and($first->content['title']['ru'])->toBe('Один изменён')
        ->and($second->name)->toBe('Второй')
        ->and($second->content['title']['ru'])->toBe('Два')
        ->and($second->updated_at?->equalTo($untouched))->toBeTrue();
});

it('refuses a save whose version is stale or missing and writes nothing', function (): void {
    $owner = editorOwner();
    $block = cmsBlock($owner, ['content' => ['title' => ['ru' => 'Исходный']]]);

    $stale = blockVersion($block);

    Carbon::setTestNow(Carbon::now()->addMinute());
    $block->name = 'Кто-то другой сохранил';
    $block->save();
    Carbon::setTestNow();

    $response = $this->post(editorUrl('saveCmsBlock', $owner), [
        'block' => $block->getKey(),
        'version' => $stale,
        'name' => 'Моя правка',
        'content' => ['title' => ['ru' => 'Моя правка']],
    ]);

    $response->assertStatus(409);
    $response->assertJsonPath('message', __('moonshine-cms::editor.conflict', ['name' => 'Кто-то другой сохранил']));

    // A save that carries no version marker at all is refused the same way.
    $this->post(editorUrl('saveCmsBlock', $owner), [
        'block' => $block->getKey(),
        'name' => 'Без версии',
    ])->assertStatus(409);

    expect($block->refresh()->name)->toBe('Кто-то другой сохранил')
        ->and($block->content['title']['ru'])->toBe('Исходный');
});

it('reports the validation of the schema and writes nothing', function (): void {
    $owner = editorOwner();
    $block = cmsBlock($owner, ['type' => 'every_field', 'name' => 'Всё']);

    $response = $this->post(editorUrl('saveCmsBlock', $owner), [
        'block' => $block->getKey(),
        'version' => blockVersion($block),
        'name' => 'Всё',
        'content' => [
            'title' => ['ru' => str_repeat('я', 200)],
            'weight' => 42,
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content.title.ru', 'content.weight']);

    expect($block->refresh()->content)->toBe([]);
});

it('stores the reference of the file and the description of this one place', function (): void {
    $owner = editorOwner();
    $block = cmsBlock($owner, ['type' => 'every_field', 'name' => 'Всё']);
    $media = CmsMedia::query()->create([
        'disk' => 'public',
        'path' => 'cms/poster.jpg',
        'original_name' => 'poster.jpg',
        'name' => 'Афиша',
        'mime' => 'image/jpeg',
        'size' => 2048,
    ]);

    $response = $this->post(editorUrl('saveCmsBlock', $owner), [
        'block' => $block->getKey(),
        'version' => blockVersion($block),
        'name' => 'Всё',
        'content' => [
            'title' => ['ru' => 'Заголовок'],
            'image' => [
                'id' => $media->uuid,
                'metadata' => [
                    'alt' => ['ru' => '  Афиша на сцене  ', 'en' => '   '],
                    // Not the form's to write: the rest of the metadata
                    // belongs to the record of the file.
                    'caption' => ['ru' => 'Подпись'],
                ],
            ],
        ],
    ]);

    $response->assertOk();

    expect($block->refresh()->content['image'])->toBe([
        'id' => $media->uuid,
        // Trimmed, and the language that was left blank inherits the
        // description of the file instead of storing an empty string.
        'metadata' => ['alt' => ['ru' => 'Афиша на сцене']],
    ]);
});

it('keeps no metadata at all when the description of the place is empty', function (): void {
    $owner = editorOwner();
    $block = cmsBlock($owner, ['type' => 'every_field', 'name' => 'Всё']);
    $media = CmsMedia::query()->create([
        'disk' => 'public',
        'path' => 'cms/poster.jpg',
        'original_name' => 'poster.jpg',
        'name' => 'Афиша',
        'mime' => 'image/jpeg',
        'size' => 2048,
    ]);

    $this->post(editorUrl('saveCmsBlock', $owner), [
        'block' => $block->getKey(),
        'version' => blockVersion($block),
        'name' => 'Всё',
        'content' => [
            'title' => ['ru' => 'Заголовок'],
            'image' => ['id' => $media->uuid, 'metadata' => ['alt' => ['ru' => '', 'en' => '']]],
        ],
    ])->assertOk();

    expect($block->refresh()->content['image'])->toBe(['id' => $media->uuid]);
});

it('stores a link of a repeater row in the shape the core declares', function (): void {
    $target = CmsPage::query()->create([
        'type' => 'page',
        'name' => ['ru' => 'Контакты'],
        'slug' => 'contacts',
        'path' => '/contacts',
        'is_active' => true,
    ]);

    $owner = editorOwner();
    $block = cmsBlock($owner, ['type' => 'slides', 'name' => 'Слайдер']);

    // Exactly what the picker submits: the chosen kind, the input of that
    // kind and nothing of the others, per row of the repeater.
    $response = $this->post(editorUrl('saveCmsBlock', $owner), [
        'block' => $block->getKey(),
        'version' => blockVersion($block),
        'name' => 'Слайдер',
        'content' => [
            'slides' => [
                ['title' => ['ru' => 'Первый'], 'link' => ['kind' => 'url', 'attributes' => ['url' => 'https://example.test'], 'new_tab' => '0']],
                ['title' => ['ru' => 'Второй'], 'link' => ['kind' => 'page', 'attributes' => ['page' => $target->uuid], 'new_tab' => '1']],
            ],
        ],
    ]);

    $response->assertOk();

    $slides = $block->refresh()->content['slides'];

    expect($slides[0]['link'])->toBe(['kind' => 'url', 'attributes' => ['url' => 'https://example.test'], 'new_tab' => false])
        ->and($slides[1]['link'])->toBe(['kind' => 'page', 'attributes' => ['page' => $target->uuid], 'new_tab' => true]);
});

it('refuses a block of another owner', function (): void {
    $owner = editorOwner();
    $other = editorOwner(['name' => 'Other']);
    $foreign = cmsBlock($other);

    $this->post(editorUrl('saveCmsBlock', $owner), [
        'block' => $foreign->getKey(),
        'version' => blockVersion($foreign),
        'name' => 'Чужой',
    ])->assertNotFound();
});

it('reorders through the move action and the order persists', function (): void {
    $owner = editorOwner();
    $first = cmsBlock($owner, ['name' => 'A']);
    $second = cmsBlock($owner, ['name' => 'B', 'sort_order' => 1]);
    $third = cmsBlock($owner, ['name' => 'C', 'sort_order' => 2]);

    $this->post(editorUrl('moveCmsBlock', $owner), [
        'block' => $third->getKey(),
        'position' => 0,
    ])->assertOk();

    $order = CmsBlock::query()
        ->where('blockable_id', $owner->getKey())
        ->orderBy('sort_order')
        ->pluck('name')
        ->all();

    expect($order)->toBe(['C', 'A', 'B'])
        ->and($third->refresh()->sort_order)->toBe(0)
        ->and($first->refresh()->sort_order)->toBe(1)
        ->and($second->refresh()->sort_order)->toBe(2);
});

it('moves a block into a slot of a container', function (): void {
    $owner = editorOwner();
    $panel = cmsBlock($owner, ['type' => 'panel', 'name' => 'Панель']);
    $text = cmsBlock($owner, ['name' => 'Текст', 'sort_order' => 1]);

    $this->post(editorUrl('moveCmsBlock', $owner), [
        'block' => $text->getKey(),
        'parent' => $panel->getKey(),
        'slot' => 'body',
    ])->assertOk();

    $text->refresh();

    expect($text->parent_id)->toBe((int) $panel->getKey())
        ->and($text->slot)->toBe('body');
});

it('refuses a move the slot does not accept and changes nothing', function (): void {
    $owner = editorOwner();
    $panel = cmsBlock($owner, ['type' => 'panel', 'name' => 'Панель']);
    $every = cmsBlock($owner, ['type' => 'every_field', 'name' => 'Всё', 'sort_order' => 1]);

    $this->post(editorUrl('moveCmsBlock', $owner), [
        'block' => $every->getKey(),
        'parent' => $panel->getKey(),
        'slot' => 'body',
    ])->assertStatus(422);

    expect($every->refresh()->parent_id)->toBeNull()
        ->and($every->slot)->toBeNull();
});

it('inserts a type from the library at an exact position', function (): void {
    $owner = editorOwner();
    cmsBlock($owner, ['name' => 'A']);
    cmsBlock($owner, ['name' => 'B', 'sort_order' => 1]);

    $this->post(editorUrl('insertCmsBlock', $owner), [
        'code' => 'every_field',
        'variant' => 'narrow',
        'position' => 1,
    ])->assertOk();

    $order = CmsBlock::query()
        ->where('blockable_id', $owner->getKey())
        ->orderBy('sort_order')
        ->get();

    expect($order->pluck('type')->all())->toBe(['text', 'every_field', 'text'])
        ->and($order[1]->variant)->toBe('narrow')
        ->and($order[1]->content)->toBe([]);
});

it('inserts into a slot of a container at an exact position', function (): void {
    $owner = editorOwner();
    $panel = cmsBlock($owner, ['type' => 'panel', 'name' => 'Панель']);
    $existing = cmsBlock($owner, [
        'name' => 'Уже там',
        'parent_id' => $panel->getKey(),
        'slot' => 'body',
        'sort_order' => 0,
    ]);

    // The plus between two cards inside a slot: the position travels with the
    // parent and the slot.
    $this->post(editorUrl('insertCmsBlock', $owner), [
        'code' => 'text',
        'parent' => $panel->getKey(),
        'slot' => 'body',
        'position' => 0,
    ])->assertOk();

    $children = CmsBlock::query()
        ->where('parent_id', $panel->getKey())
        ->where('slot', 'body')
        ->orderBy('sort_order')
        ->get();

    expect($children->pluck('sort_order')->all())->toBe([0, 1])
        // The new row took the top and pushed the one that was there down.
        ->and($children[0]->getKey())->not->toBe($existing->getKey())
        ->and($children[0]->type)->toBe('text')
        ->and($children[1]->getKey())->toBe($existing->getKey());
});

it('inserts a reference to a shared library record', function (): void {
    $owner = editorOwner();
    $shared = CmsSharedBlock::query()->create(['code' => 'promo', 'name' => 'Промо', 'is_active' => true]);
    cmsBlock($shared, ['name' => 'Внутри']);

    $this->post(editorUrl('insertCmsBlock', $owner), [
        'shared_block' => $shared->getKey(),
    ])->assertOk();

    $inserted = CmsBlock::query()
        ->where('blockable_type', $owner->getMorphClass())
        ->where('blockable_id', $owner->getKey())
        ->firstOrFail();

    expect($inserted->type)->toBe('shared')
        ->and($inserted->cms_shared_block_id)->toBe((int) $shared->getKey());
});

it('refuses an insertion of an unknown type', function (): void {
    $owner = editorOwner();

    $this->post(editorUrl('insertCmsBlock', $owner), ['code' => ''])->assertStatus(422);

    expect(CmsBlock::query()->count())->toBe(0);
});

it('deletes a block and closes the hole in the order', function (): void {
    $owner = editorOwner();
    $first = cmsBlock($owner, ['name' => 'A']);
    $second = cmsBlock($owner, ['name' => 'B', 'sort_order' => 1]);
    $third = cmsBlock($owner, ['name' => 'C', 'sort_order' => 2]);

    $this->post(editorUrl('deleteCmsBlock', $owner), ['block' => $first->getKey()])->assertOk();

    expect(CmsBlock::query()->whereKey($first->getKey())->exists())->toBeFalse()
        ->and($second->refresh()->sort_order)->toBe(0)
        ->and($third->refresh()->sort_order)->toBe(1);
});

it('surfaces the refusal to delete a container that still holds children', function (): void {
    $owner = editorOwner();
    $panel = cmsBlock($owner, ['type' => 'panel', 'name' => 'Панель']);
    cmsBlock($owner, [
        'name' => 'Внутри',
        'parent_id' => $panel->getKey(),
        'slot' => 'body',
    ]);

    $response = $this->post(editorUrl('deleteCmsBlock', $owner), ['block' => $panel->getKey()]);

    $response->assertStatus(422);
    $response->assertJsonPath('message', __('moonshine-cms::editor.container_not_empty'));

    expect(CmsBlock::query()->whereKey($panel->getKey())->exists())->toBeTrue();
});

it('refuses to delete the last instance of a block code the owner requires', function (): void {
    $owner = editorOwner(['required_blocks' => ['text']]);
    $block = cmsBlock($owner, ['name' => 'Обязательный']);

    $response = $this->post(editorUrl('deleteCmsBlock', $owner), ['block' => $block->getKey()]);

    $response->assertStatus(422);
    $response->assertJsonPath('message', __('moonshine-cms::editor.required_block'));

    expect(CmsBlock::query()->whereKey($block->getKey())->exists())->toBeTrue();
});

it('deletes a required block while a second instance of the code remains', function (): void {
    $owner = editorOwner(['required_blocks' => ['text']]);
    $first = cmsBlock($owner, ['name' => 'Первый']);
    cmsBlock($owner, ['name' => 'Второй', 'sort_order' => 1]);

    $this->post(editorUrl('deleteCmsBlock', $owner), ['block' => $first->getKey()])->assertOk();

    expect(CmsBlock::query()->whereKey($first->getKey())->exists())->toBeFalse();
});

it('refuses to switch off the last active instance of a required block code', function (): void {
    $owner = editorOwner(['required_blocks' => ['text']]);
    $block = cmsBlock($owner, ['name' => 'Обязательный', 'content' => ['body' => ['ru' => 'Текст']]]);

    $response = $this->post(editorUrl('saveCmsBlock', $owner), [
        'block' => $block->getKey(),
        'version' => blockVersion($block),
        'name' => 'Обязательный',
        'is_active' => false,
        'content' => ['body' => ['ru' => 'Новый текст']],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['is_active']);

    // Nothing at all is written: the refusal is not a partial save.
    $block->refresh();

    expect($block->is_active)->toBeTrue()
        ->and($block->content['body']['ru'] ?? null)->toBe('Текст');
});

it('switches off a required block while another instance of the code stays active', function (): void {
    $owner = editorOwner(['required_blocks' => ['text']]);
    $first = cmsBlock($owner, ['name' => 'Первый']);
    cmsBlock($owner, ['name' => 'Второй', 'sort_order' => 1]);

    $this->post(editorUrl('saveCmsBlock', $owner), [
        'block' => $first->getKey(),
        'version' => blockVersion($first),
        'name' => 'Первый',
        'is_active' => false,
        'content' => ['body' => ['ru' => 'Текст']],
    ])->assertOk();

    expect($first->refresh()->is_active)->toBeFalse();
});

it('publishes the owner and reports the version number', function (): void {
    $owner = editorOwner();
    cmsBlock($owner, ['content' => ['body' => ['ru' => 'Текст', 'en' => 'Text']]]);

    $response = $this->post(editorUrl('publishCmsOwner', $owner));

    $response->assertOk();
    $response->assertJsonPath('number', 1);

    expect(CmsVersion::query()->count())->toBe(1);
});

it('reports the whole problem list of a rejected publication and writes nothing', function (): void {
    $owner = editorOwner();
    cmsBlock($owner, ['type' => 'panel', 'name' => 'Панель']);

    $response = $this->post(editorUrl('publishCmsOwner', $owner));

    $response->assertStatus(422);
    $response->assertJsonPath('message', __('moonshine-cms::editor.publication_rejected'));

    expect($response->json('problems'))->not->toBeEmpty()
        ->and(CmsVersion::query()->count())->toBe(0);
});

it('serves the library of one insertion point as the tiles of the modal', function (): void {
    $owner = editorOwner();
    $panel = cmsBlock($owner, ['type' => 'panel', 'name' => 'Панель']);

    // The modal of the panel loads HTML, so the library answers with the tiles
    // and the point they insert at.
    $html = $this->get(editorUrl('cmsBlockLibrary', $owner).'&parent='.$panel->getKey().'&slot=body&position=1')
        ->assertOk()
        ->getContent() ?: '';

    expect(libraryEntryCodes($html))->toBe(['text'])
        ->and($html)->toContain('data-cms-target-slot="body"')
        ->and($html)->toContain('data-cms-target-position="1"');
});

it('offers the whole catalogue when no slot narrows it', function (): void {
    $owner = editorOwner();

    $html = $this->get(editorUrl('cmsBlockLibrary', $owner))->assertOk()->getContent() ?: '';

    expect(libraryEntryCodes($html))->toContain('text')->toContain('panel')
        ->and($html)->toContain(__('moonshine-cms::editor.search'));
});

it('saves the size of the portion and the mode of a dynamic block into its content', function (): void {
    $owner = editorOwner();
    $block = cmsBlock($owner, ['type' => 'feed', 'name' => 'Лента']);

    $response = $this->post(editorUrl('saveCmsBlock', $owner), [
        'block' => $block->getKey(),
        'version' => blockVersion($block),
        'name' => 'Лента',
        'is_active' => true,
        'content' => [
            'title' => ['ru' => 'Лента'],
            'per_page' => 8,
            'pagination_mode' => 'more',
        ],
    ]);

    $response->assertOk();

    $block->refresh();

    // Neither key is a field of the schema, and both survive the write, see
    // `Ewk\Cms\Blocks\Support\ContentFilter`.
    expect($block->content['per_page'])->toBe(8)
        ->and($block->content['pagination_mode'])->toBe('more')
        ->and($block->content['title']['ru'])->toBe('Лента')
        // The anchor of a dynamic block is generated and then kept.
        ->and($block->anchor)->toBe('feed');
});

it('refuses a portion above the ceiling of the schema and a mode it does not allow', function (): void {
    $owner = editorOwner();
    $block = cmsBlock($owner, ['type' => 'feed', 'name' => 'Лента']);

    submitForm(editorUrl('saveCmsBlock', $owner), [
        'block' => $block->getKey(),
        'version' => blockVersion($block),
        'name' => 'Лента',
        'is_active' => true,
        'content' => ['per_page' => 200],
    ])->assertStatus(422)->assertJsonValidationErrors('content.per_page');

    submitForm(editorUrl('saveCmsBlock', $owner), [
        'block' => $block->getKey(),
        'version' => blockVersion($block->refresh()),
        'name' => 'Лента',
        'is_active' => true,
        'content' => ['pagination_mode' => 'none'],
    ])->assertStatus(422)->assertJsonValidationErrors('content.pagination_mode');

    expect($block->refresh()->content['per_page'] ?? null)->toBeNull();
});

it('drops the pagination settings of a block whose schema does not hand them over', function (): void {
    $owner = editorOwner();
    $block = cmsBlock($owner, ['type' => 'every_field', 'name' => 'Всё']);

    $this->post(editorUrl('saveCmsBlock', $owner), [
        'block' => $block->getKey(),
        'version' => blockVersion($block),
        'name' => 'Всё',
        'is_active' => true,
        'content' => ['title' => ['ru' => 'Всё'], 'per_page' => 8, 'pagination_mode' => 'more'],
    ])->assertOk();

    expect($block->refresh()->content)->not->toHaveKey('per_page')
        ->and($block->content)->not->toHaveKey('pagination_mode');
});

it('moves a card by the payload the sortable of the panel sends', function (): void {
    $owner = editorOwner();
    $panel = cmsBlock($owner, ['type' => 'panel', 'name' => 'Панель']);
    $first = cmsBlock($owner, ['name' => 'A', 'sort_order' => 1]);
    $second = cmsBlock($owner, ['name' => 'B', 'sort_order' => 2]);

    // `id` names the dragged row, `parent` and `slot` the list it landed in,
    // `index` the place it took there.
    $this->post(editorUrl('moveCmsBlock', $owner), [
        'id' => $second->getKey(),
        'parent' => $panel->getKey(),
        'slot' => 'body',
        'index' => 0,
    ])->assertOk();

    expect($second->refresh()->parent_id)->toBe((int) $panel->getKey())
        ->and($second->slot)->toBe('body')
        ->and($second->sort_order)->toBe(0)
        ->and($first->refresh()->parent_id)->toBeNull();
});

it('refuses to move a block on anything but a post', function (): void {
    $owner = editorOwner();
    $block = cmsBlock($owner);

    $this->get(editorUrl('moveCmsBlock', $owner).'&id='.$block->getKey().'&index=0')->assertStatus(405);
});

it('saves a card whose name is empty', function (): void {
    $owner = editorOwner();
    $block = cmsBlock($owner, ['name' => 'Старое имя']);

    // The name is not typed any more: the head of the card reads the content,
    // so an empty one is no refusal.
    submitForm(editorUrl('saveCmsBlock', $owner), [
        'block' => $block->getKey(),
        'version' => blockVersion($block),
        'name' => '',
        'is_active' => true,
        'content' => ['body' => ['ru' => 'Текст']],
    ])->assertOk();

    expect($block->refresh()->name)->toBe('')
        ->and($block->content['body']['ru'])->toBe('Текст');
});

it('answers a save with the new version and the head of that one card', function (): void {
    $owner = editorOwner();
    $block = cmsBlock($owner, ['type' => 'every_field', 'name' => 'Всё']);

    $response = $this->post(editorUrl('saveCmsBlock', $owner), [
        'block' => $block->getKey(),
        'version' => blockVersion($block),
        'content' => ['title' => ['ru' => 'Rammstein в Берлине']],
    ])->assertOk();

    // The form of the card is left alone — a rich text editor inside it does
    // not survive being replaced — and only the head is drawn again.
    $html = $response->json('htmlData.0.html');

    expect($response->json('htmlData.0.selector'))->toBe('[data-cms-card-head="'.$block->getKey().'"]')
        ->and($html)->toContain('Rammstein в Берлине')
        ->and($response->json('version'))->toBe(blockVersion($block->refresh()));
});
