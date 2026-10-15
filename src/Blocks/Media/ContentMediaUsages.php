<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Media;

use Ewk\Cms\Blocks\Fields\Field;
use Ewk\Cms\Media\Models\CmsMedia;
use Ewk\Cms\Media\Services\MediaUsageRegistrar;
use Illuminate\Database\Eloquent\Model;

/**
 * Registers what a bag of content uses: saving rewrites the usages of the
 * owner, deleting releases them, and a record with usages cannot be deleted at
 * all.
 *
 * The accounting lives here, in the core, and not in the admin: an import, a
 * seeder and a console never open a form, and a rule that only the form
 * enforces is a rule that holds until the first script.
 *
 * The owner of a usage is the record the content belongs to — a block, a
 * header, a footer — and never the page above it: a page holds many blocks,
 * and «this file is used by that page» cannot say which one to edit.
 */
final readonly class ContentMediaUsages
{
    public function __construct(
        private MediaUsageRegistrar $registrar,
        private MediaReferenceCollector $collector,
    ) {
    }

    /**
     * @param  array<array-key, mixed>|null  $content
     * @param  list<Field>  $fields
     */
    public function sync(Model $owner, string $field, ?array $content, array $fields): void
    {
        $references = is_array($content) ? $this->collector->collect($content, $fields) : [];

        $this->registrar->sync(
            $owner->getMorphClass(),
            self::keyOf($owner),
            $field,
            $this->identify($references),
        );
    }

    public function release(Model $owner): void
    {
        $this->registrar->release($owner->getMorphClass(), self::keyOf($owner));
    }

    /**
     * Content stores the uuid of a record, usages store its key. A uuid no
     * record answers is left out rather than written as a dangling usage: it
     * would block the deletion of a record that does not exist.
     *
     * @param  array<string, string>  $references  dotted path => media uuid
     * @return array<string, int>  dotted path => media id
     */
    private function identify(array $references): array
    {
        if ($references === []) {
            return [];
        }

        /** @var array<string, int> $ids */
        $ids = CmsMedia::query()
            ->whereIn('uuid', array_values(array_unique($references)))
            ->pluck('id', 'uuid')
            ->all();

        $identified = [];

        foreach ($references as $path => $uuid) {
            if (isset($ids[$uuid])) {
                $identified[$path] = $ids[$uuid];
            }
        }

        return $identified;
    }

    private static function keyOf(Model $owner): int|string
    {
        $key = $owner->getKey();

        return is_int($key) || is_string($key) ? $key : '';
    }
}
