<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Support;

use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * Keeps only the root keys declared by the block type's own fields. Applied on
 * write only: unknown keys of an existing row are ignored when reading, never
 * silently deleted, so editing a schema does not destroy data.
 */
final class ContentFilter
{
    /**
     * @param  array<array-key, mixed>  $content
     * @return array<array-key, mixed>
     */
    public function filter(array $content, BlockSchema $schema): array
    {
        $declared = [];

        foreach ($schema->fields() as $field) {
            $declared[$field->name()] = true;
        }

        // The size of the portion and the mode are the settings of the engine
        // that live in `content`: they belong to the pagination of the block
        // type, not to its fields, and each survives a write only while the
        // schema lets the editor change it. A block type must not declare a
        // field under either name.
        if ($schema->pagination()?->editable === true) {
            $declared['per_page'] = true;
        }

        if ($schema->pagination()?->modeEditable === true) {
            $declared['pagination_mode'] = true;
        }

        return array_intersect_key($content, $declared);
    }
}
