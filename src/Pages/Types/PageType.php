<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Types;

use Ewk\Cms\Pages\Exceptions\InvalidPageTypeException;
use Ewk\Cms\Pages\Support\PathPattern;
use Ewk\Cms\Pages\Support\Slug;

/**
 * A page type is a declaration and not an enumeration. It is written in the
 * same grammar as a block schema: subject, path, required blocks, limits and
 * the starter set.
 *
 * ```php
 * PageType::make('event')
 *     ->title('Страница события')
 *     ->subjectTitle('Событие')
 *     ->cardinality(Cardinality::TEMPLATE)
 *     ->subject(Subject::model(Event::class)->by('slug')->visible('publiclyVisible'))
 *     ->path('/tours/{tour}/{event}')
 *     ->required(['hero', 'checkout'])
 *     ->limits(['hero' => 1, 'checkout' => 1])
 *     ->starter(['hero', 'checkout', 'about', 'chips', 'schedule', 'location', 'events']);
 * ```
 *
 * The value of `cms_pages.type` stays the key and nothing more: the caption,
 * the behaviour and the constraints are read from the declaration.
 *
 * Every accessor doubles as its own fluent setter, exactly as `BlockSchema`
 * does: called with a value it stores it, called without arguments it reads
 * it back.
 */
final class PageType
{
    private string $title = '';

    private string $subjectTitle = '';

    private Cardinality $cardinality = Cardinality::MANY;

    private ?Subject $subject = null;

    private ?string $path = null;

    private ?PathPattern $pattern = null;

    /** @var list<string> */
    private array $required = [];

    /** @var array<string, int> */
    private array $limits = [];

    /** @var list<string> */
    private array $starter = [];

    private function __construct(private readonly string $key)
    {
    }

    /**
     * @throws InvalidPageTypeException when the key is not a kebab-case identifier
     */
    public static function make(string $key): self
    {
        if (! Slug::isValid($key)) {
            throw InvalidPageTypeException::invalidKey($key);
        }

        return new self($key);
    }

    public function key(): string
    {
        return $this->key;
    }

    /**
     * @return ($title is null ? string : $this)
     */
    public function title(?string $title = null): static|string
    {
        if ($title === null) {
            return $this->title;
        }

        $this->title = $title;

        return $this;
    }

    /**
     * What the subject of the type is called on its own: «Тур» against the
     * «Страница тура» of {@see title()}. A link points at the record and not at
     * the page that renders it, so the cascade of the panel needs the noun and
     * never the caption of the page.
     *
     * A declaration that leaves it out has no second caption and the caller
     * falls back to the title.
     *
     * @return ($subjectTitle is null ? string : $this)
     */
    public function subjectTitle(?string $subjectTitle = null): static|string
    {
        if ($subjectTitle === null) {
            return $this->subjectTitle;
        }

        $this->subjectTitle = $subjectTitle;

        return $this;
    }

    /**
     * @return ($cardinality is null ? Cardinality : $this)
     */
    public function cardinality(?Cardinality $cardinality = null): static|Cardinality
    {
        if ($cardinality === null) {
            return $this->cardinality;
        }

        $this->cardinality = $cardinality;

        return $this;
    }

    /**
     * @return ($subject is null ? Subject|null : $this)
     */
    public function subject(?Subject $subject = null): static|Subject|null
    {
        if ($subject === null) {
            return $this->subject;
        }

        $this->subject = $subject;

        return $this;
    }

    /**
     * The address of a singleton, or the template of a subject page. A type
     * holding many pages declares nothing here: its addresses come from the
     * tree.
     *
     * @return ($path is null ? string|null : $this)
     */
    public function path(?string $path = null): static|string|null
    {
        if ($path === null) {
            return $this->path;
        }

        $this->pattern = PathPattern::fromString($path);
        $this->path = $this->pattern->pattern;

        return $this;
    }

    public function pattern(): ?PathPattern
    {
        return $this->pattern;
    }

    /**
     * The placeholder carrying the subject: the one named after the type
     * itself. `/tours/{tour}/{event}` on type `event` reads `{event}`, while
     * `{tour}` is the context the canonical address is checked against.
     */
    public function subjectPlaceholder(): ?string
    {
        if ($this->pattern === null || ! in_array($this->key, $this->pattern->placeholders(), true)) {
            return null;
        }

        return $this->key;
    }

    /**
     * Block codes that must be present.
     *
     * @param  array<array-key, string>|null  $required
     * @return ($required is null ? list<string> : $this)
     */
    public function required(?array $required = null): static|array
    {
        if ($required === null) {
            return $this->required;
        }

        $this->required = array_values($required);

        return $this;
    }

    /**
     * How many blocks of one code the type allows.
     *
     * @param  array<string, int>|null  $limits
     * @return ($limits is null ? array<string, int> : $this)
     */
    public function limits(?array $limits = null): static|array
    {
        if ($limits === null) {
            return $this->limits;
        }

        $this->limits = $limits;

        return $this;
    }

    /**
     * Block codes a freshly assembled page starts with.
     *
     * @param  array<array-key, string>|null  $starter
     * @return ($starter is null ? list<string> : $this)
     */
    public function starter(?array $starter = null): static|array
    {
        if ($starter === null) {
            return $this->starter;
        }

        $this->starter = array_values($starter);

        return $this;
    }

    public function isTemplate(): bool
    {
        return $this->cardinality === Cardinality::TEMPLATE;
    }

    /**
     * A type whose address is written in the declaration: the tree neither
     * builds it nor may move it.
     */
    public function hasFixedPath(): bool
    {
        return $this->cardinality !== Cardinality::MANY;
    }

    public function limitFor(string $blockCode): ?int
    {
        return $this->limits[$blockCode] ?? null;
    }
}
