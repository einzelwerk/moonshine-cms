<?php

declare(strict_types=1);

return [
    'title' => 'Shared blocks',
    'title_new' => 'New shared block',
    'title_edit' => 'Shared block: :name',
    'code_hint' => 'It reads in the admin address and survives renaming. Leave it empty and it is built from the name.',
    'name_hint' => 'How the block is called in the library. A visitor never sees this name.',
    'editor_hint' => 'A shared block has exactly one root block. Use a container for a section of several blocks.',
    'usages' => 'Where it is inserted',
    'usages_hint' => 'Editing the shared block shows on every page of this list.',
    'usages_empty' => 'Not inserted anywhere yet.',
    'usages_none' => 'Nowhere',
    'usages_count' => 'Insertions: :count',
    'usage_open' => 'Open',
    'usage_owner' => 'Page',
    'usage_updated' => 'Changed',
    'usage_never' => 'No data',
    'usage_unknown_owner' => 'Owner not found',
    'unlink_all' => 'Unlink every insertion',
    'unlink_confirm' => 'Unlink every insertion of this shared block? The block itself stays, but it goes off the pages.',
    'unlink_done' => 'Insertions unlinked: :count. The shared block itself stays.',
    'unlink_nothing' => 'Nothing to unlink: there are no insertions.',
    'unknown_shared_block' => 'Shared block not found',
    'in_use_not_deletable' => 'Shared block «:name» cannot be deleted: it has :count live insertion(s). Unlink them on the form of the block first.',
    'not_shareable' => 'This block type cannot be put into the shared block library.',
    'single_root' => 'A shared block has exactly one root block. Remove the existing one or nest the new one into a container.',
];
