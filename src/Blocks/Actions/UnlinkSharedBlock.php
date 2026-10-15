<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Actions;

use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Illuminate\Support\Facades\DB;

/**
 * Unlinks every insertion of a library entry. Deleting an original that is
 * still in use is refused; the admin lists the usages and offers this action
 * instead.
 *
 * Unlinking removes the insertion rows. It does not turn them into
 * independent copies of the content and it does not delete the shared block
 * itself.
 *
 * The rows go one by one and not in a single `delete()` over the query: a mass
 * delete fires no model events, so the owners of the insertions would keep the
 * mark of their last change while their content had just changed. There are as
 * many rows here as there are pages showing the entry, which is a list an
 * editor can read.
 */
final readonly class UnlinkSharedBlock
{
    /**
     * @return int the number of insertions removed
     */
    public function handle(CmsSharedBlock $sharedBlock): int
    {
        return DB::transaction(static function () use ($sharedBlock): int {
            $removed = 0;

            foreach ($sharedBlock->insertions()->get() as $insertion) {
                $insertion->delete();
                $removed++;
            }

            return $removed;
        });
    }
}
