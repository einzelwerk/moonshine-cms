<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Types;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * The one way an entity of a template page reaches an answer: the type declares
 * the presenter, this service calls it.
 *
 * It exists so that there is a single copy of the two steps — find the type an
 * entity belongs to, then hand the entity to the presenter of that type. The
 * resolver walks them with the type it already matched the address against, a
 * block of a template walks them with nothing but the entity, and both must
 * produce the same `data`: a second copy would be a second contract for the
 * same entity on the frontend.
 *
 * The container is asked for the presenter at the moment of the call and never
 * in the constructor: a presenter reuses the public resources of an
 * installation, and those are not built while the registry is.
 */
final readonly class SubjectPresentation
{
    public function __construct(
        private PageTypeRegistry $types,
        private Container $container,
    ) {
    }

    public function typeOf(Model $subject): ?PageType
    {
        return $this->types->forSubject($subject);
    }

    /**
     * The envelope a block of a template page hands out: the key of the type
     * and the entity as its presenter shows it. `null` for no entity and for
     * an entity no declared type stands for — a block must then answer without
     * a subject rather than with an invented one.
     *
     * @return array{type: string, data: array<string, mixed>}|null
     */
    public function payload(?Model $subject, Request $request): ?array
    {
        if ($subject === null) {
            return null;
        }

        $type = $this->typeOf($subject);

        if ($type === null) {
            return null;
        }

        return ['type' => $type->key(), 'data' => $this->data($type, $subject, $request)];
    }

    /**
     * The entity as the presenter of the type shows it. A type without a
     * presenter answers with an empty array: the address did resolve to an
     * entity, and the caller must not have to guess whether it did.
     *
     * @return array<string, mixed>
     */
    public function data(PageType $type, Model $subject, Request $request): array
    {
        $declared = $type->subject();
        $presenter = $declared?->presenterClass;

        if ($presenter === null) {
            return [];
        }

        /** @var SubjectPresenter $instance */
        $instance = $this->container->make($presenter);

        return $instance->present($subject, $request);
    }
}
