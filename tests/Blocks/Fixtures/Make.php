<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Media\Models\CmsMedia;
use Illuminate\Database\Eloquent\Model;

final class Make
{
    public static function owner(): BlockOwner
    {
        return BlockOwner::query()->create(['is_active' => true]);
    }

    /**
     * A block of an owner row, or of the BlockOwner with that id when the
     * test needs no row at all.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function block(Model|int $owner = 1, array $attributes = []): CmsBlock
    {
        return CmsBlock::create([
            'blockable_type' => $owner instanceof Model ? $owner->getMorphClass() : BlockOwner::class,
            'blockable_id' => $owner instanceof Model ? $owner->getKey() : $owner,
            'type' => 'text',
            'name' => 'Блок',
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function media(string $name = 'photo.jpg', array $attributes = []): CmsMedia
    {
        return CmsMedia::query()->create([
            'disk' => 'public',
            'path' => 'ab/'.uniqid('', true).'/v1/original.jpg',
            'original_name' => $name,
            'mime' => 'image/jpeg',
            'size' => 1024,
            ...$attributes,
        ]);
    }
}
