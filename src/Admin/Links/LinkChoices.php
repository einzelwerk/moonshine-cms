<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Links;

use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Pages\Types\PageType;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Illuminate\Container\Container;
use Throwable;

/**
 * The entries of the first select of the link picker, in the order an editor
 * reads them: a page of the site, the entities of the catalogue, a catalogue
 * with filters, a section, a file, an action, a service address, and the three
 * plain targets.
 *
 * Only the kinds the field allows are offered, and the entity entries only
 * where there is something to point at: an installation whose page types
 * declare no subject has no «Тур» to offer, and an empty select entry is worse
 * than no entry at all.
 *
 * The package knows no domain classes. The entries come from the registry of
 * page types: every template type names the model it renders and the title an
 * editor reads.
 */
final readonly class LinkChoices
{
    /**
     * The order the entries are drawn in, kind by kind. The entity entries
     * take the place of the `entity` kind.
     */
    private const array ORDER = [
        LinkKind::PAGE,
        LinkKind::ENTITY,
        LinkKind::LISTING,
        LinkKind::ANCHOR,
        LinkKind::FILE,
        LinkKind::ACTION,
        LinkKind::ROUTE,
        LinkKind::URL,
        LinkKind::EMAIL,
        LinkKind::PHONE,
    ];

    /**
     * @param  list<string>  $allowedKinds  empty means every kind
     * @return list<LinkChoice>
     */
    public static function build(array $allowedKinds, ?PageTypeRegistry $types = null): array
    {
        $types ??= self::registry();
        $choices = [];

        foreach (self::ORDER as $kind) {
            if ($allowedKinds !== [] && ! in_array($kind->value, $allowedKinds, true)) {
                continue;
            }

            if ($kind === LinkKind::ENTITY) {
                array_push($choices, ...self::entities($types));

                continue;
            }

            $choices[] = new LinkChoice(
                id: $kind->value,
                kind: $kind->value,
                label: (string) __('moonshine-cms::links.kinds.'.$kind->value),
            );
        }

        return $choices;
    }

    /**
     * The entry that stands for a stored value: the entity entry of its class
     * when the link points at a record, the plain kind otherwise. A stored
     * class no type declares any more has no entry, and the picker then falls
     * back to its first one.
     *
     * @param  list<LinkChoice>  $choices
     */
    public static function match(array $choices, string $kind, mixed $entityType): ?LinkChoice
    {
        $type = is_string($entityType) ? trim($entityType) : '';

        foreach ($choices as $choice) {
            if ($choice->kind !== $kind) {
                continue;
            }

            if ($kind !== LinkKind::ENTITY->value || $choice->entityType === $type) {
                return $choice;
            }
        }

        return null;
    }

    /**
     * One entry per model an installation gives an address to. Two types over
     * one class are impossible by the rules of the registry, so the first one
     * wins and nothing is ever offered twice.
     *
     * @return list<LinkChoice>
     */
    private static function entities(?PageTypeRegistry $types): array
    {
        if (! $types instanceof PageTypeRegistry) {
            return [];
        }

        $entries = [];

        foreach ($types->all() as $type) {
            $subject = $type->subject();

            if ($subject === null || isset($entries[$subject->modelClass])) {
                continue;
            }

            $entries[$subject->modelClass] = new LinkChoice(
                id: LinkKind::ENTITY->value.':'.$subject->modelClass,
                kind: LinkKind::ENTITY->value,
                label: self::titleOf($type),
                entityType: $subject->modelClass,
            );
        }

        return array_values($entries);
    }

    /**
     * A link points at the record and not at the page that renders it, so the
     * entry reads «Тур» and not «Страница тура»: the declaration carries that
     * noun of its own. A type that declares none falls back to its title, and a
     * declaration without either would show the key, which is a code and not a
     * caption.
     */
    private static function titleOf(PageType $type): string
    {
        foreach ([$type->subjectTitle(), $type->title()] as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return $type->key();
    }

    private static function registry(): ?PageTypeRegistry
    {
        try {
            return Container::getInstance()->make(PageTypeRegistry::class);
        } catch (Throwable) {
            // An installation without the page tree still edits blocks, and a
            // link there simply offers no entities.
            return null;
        }
    }
}
