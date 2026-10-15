<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Links;

/**
 * One entry of the «where does it lead» select of the link picker.
 *
 * A kind of the storage and an entry of the select are not the same thing:
 * `entity` is one kind and several entries, one per model an installation gives
 * an address to, because «Тур» and «Событие» are what an editor picks between
 * and `entity` is not.
 */
final readonly class LinkChoice
{
    /**
     * @param  string  $id  the value of the select, e.g. `page`, or `entity:` and the class of a model
     * @param  string  $kind  the stored kind, see {@see \Ewk\Cms\Blocks\Links\LinkKind}
     * @param  string  $label  what the editor reads
     * @param  string|null  $entityType  the class stored in `attributes[type]`, for entity entries only
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $label,
        public ?string $entityType = null,
    ) {
    }

    /**
     * @return array{id: string, kind: string, label: string, type: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'label' => $this->label,
            'type' => $this->entityType,
        ];
    }
}
