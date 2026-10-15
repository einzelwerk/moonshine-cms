<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Links;

use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Blocks\Links\LinkResolverContract;
use Ewk\Cms\Blocks\Links\ResolvedLink;
use Ewk\Cms\Blocks\Links\ValidatesLinkTarget;
use Ewk\Cms\Pages\Support\SubjectPath;
use Ewk\Cms\Pages\Types\PageType;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The address of an entity of the catalog. The link stores the class of the
 * record and its key, exactly as an {@see \Ewk\Cms\Blocks\Fields\EntityField}
 * does, and never the address: the address changes with the first rename, the
 * key does not.
 *
 * The type of page an entity belongs to is found by the class the declarations
 * name and the address comes out of the path template that type declares. A
 * second way of building the address of a tour is exactly how two different
 * addresses for one tour appear.
 *
 * The class is never instantiated to find its type: a model with a constructor
 * of its own, or an abstract one named by a stale link, would take the public
 * `/v1/resolve` down with it. The class of a record is a string, and matching
 * strings needs no object.
 *
 * An entity that is gone, or that its own visibility criterion does not let
 * through, is delivered as `href: null` with the kind kept: a hidden record and
 * a deleted one are the same thing to a visitor. A class no page type stands
 * over answers the same way and without a query at all: there would be no
 * template to build an address out of anyway.
 *
 * Whether the installation has assembled the template page of the type yet is
 * deliberately not checked here. That is a state of the installation, which the
 * resolver of addresses reports on its own; refusing every link until the
 * templates exist would make the links break while the CMS is being filled.
 */
final class EntityLinkResolver implements LinkResolverContract, ValidatesLinkTarget
{
    /**
     * The type of a class, remembered for as long as this resolver lives. The
     * declarations of an installation are read once at boot and never change
     * afterwards, while a page of twenty links would otherwise walk the whole
     * registry twenty times.
     *
     * @var array<string, PageType|null>
     */
    private array $typeByClass = [];

    public function __construct(private readonly PageTypeRegistry $types)
    {
    }

    public function supports(LinkKind $kind): bool
    {
        return $kind === LinkKind::ENTITY;
    }

    public function resolve(Link $link, string $locale): ResolvedLink
    {
        $class = self::modelClass($link->attribute('type'));
        $type = $class === null ? null : $this->typeOf($class);

        return new ResolvedLink(
            kind: LinkKind::ENTITY,
            href: $class === null || $type === null ? null : $this->href($type, $class, $link->attribute('id')),
            // An entity of this installation is an address of this installation.
            external: false,
            newTab: $link->newTab,
        );
    }

    /**
     * What only this resolver can answer: whether anything of this installation
     * could ever give the named class an address. A class with no page type
     * over it is a mistake in the form, exactly like a route that is not in the
     * manifest, and not a record that went away.
     */
    public function problem(array $attributes): ?string
    {
        $declared = $attributes['type'] ?? null;

        if (! is_string($declared) || trim($declared) === '') {
            // An empty target is the refusal of the field itself.
            return null;
        }

        $class = self::modelClass($declared);

        return $class !== null && $this->typeOf($class) !== null
            ? null
            : (string) __('cms-pages::links.unknown_entity_type', ['type' => trim($declared)]);
    }

    /**
     * @param  class-string<Model>  $class
     */
    private function href(PageType $type, string $class, mixed $id): ?string
    {
        $entity = $this->entity($type, $class, $id);

        return $entity === null ? null : SubjectPath::of($type, $entity);
    }

    /**
     * The record, read through the visibility criterion its page type declares
     * — the very same scope the resolver of addresses applies, so a link and an
     * address never disagree about what is public.
     *
     * @param  class-string<Model>  $class
     */
    private function entity(PageType $type, string $class, mixed $id): ?Model
    {
        if ((! is_int($id) && ! is_string($id)) || $id === '') {
            return null;
        }

        /** @var Builder<Model> $query */
        $query = $class::query()->whereKey($id);

        $scope = $type->subject()?->visibilityScope;

        if ($scope !== null) {
            $query->{$scope}();
        }

        return $query->first();
    }

    /**
     * The type declared over this class, its parents included: a model an
     * installation extends is answered by the type of the model it extends,
     * exactly as {@see PageTypeRegistry::forSubject()} answers for an instance.
     *
     * @param  class-string<Model>  $class
     */
    private function typeOf(string $class): ?PageType
    {
        if (array_key_exists($class, $this->typeByClass)) {
            return $this->typeByClass[$class];
        }

        $found = null;

        foreach ($this->types->all() as $type) {
            $declared = $type->subject();

            if ($declared !== null && is_a($class, $declared->modelClass, true)) {
                $found = $type;

                break;
            }
        }

        return $this->typeByClass[$class] = $found;
    }

    /**
     * The class the stored type names: either the class itself, the way an
     * entity field of a block schema declares its targets, or a morph alias of
     * an installation that keeps a morph map.
     *
     * @return class-string<Model>|null
     */
    private static function modelClass(mixed $type): ?string
    {
        if (! is_string($type) || trim($type) === '') {
            return null;
        }

        $type = trim($type);

        if (is_a($type, Model::class, true)) {
            /** @var class-string<Model> $type */
            return $type;
        }

        // A morph map answers with the class it was given, so there is nothing
        // left to check here beyond the alias being known at all.
        return Relation::getMorphedModel($type);
    }
}
