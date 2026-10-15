<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Http\Resolve;

/**
 * The answer of {@see ResolvePage} before it becomes a response. A miss is this
 * object with {@see ResolveStatus::NOT_FOUND} and never an exception: the
 * outcome is a field of the answer.
 */
final readonly class ResolveOutcome
{
    private function __construct(
        public ResolveStatus $status,
        public ?LocatedPage $located = null,
        public ?ResolveDocument $document = null,
        public ?string $language = null,
    ) {
    }

    public static function located(LocatedPage $located): self
    {
        return new self(ResolveStatus::FOUND, $located);
    }

    public static function found(LocatedPage $located, ResolveDocument $document): self
    {
        return new self(ResolveStatus::FOUND, $located, $document);
    }

    public static function languagePrefix(string $language): self
    {
        return new self(ResolveStatus::LANGUAGE_PREFIX, language: $language);
    }

    public static function notFound(): self
    {
        return new self(ResolveStatus::NOT_FOUND);
    }

    public function isFound(): bool
    {
        return $this->status === ResolveStatus::FOUND;
    }
}
