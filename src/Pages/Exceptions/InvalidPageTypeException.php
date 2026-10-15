<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Exceptions;

use Ewk\Cms\Pages\Types\SubjectPresenter;
use RuntimeException;

/**
 * A declaration that cannot work is refused at registration time: a template
 * without a subject resolves nothing, and a subject without a visibility
 * criterion makes the resolver, the catalog and the sitemap disagree about the
 * very same entity.
 */
final class InvalidPageTypeException extends RuntimeException
{
    public static function malformedPlaceholder(string $pattern, string $segment): self
    {
        return new self(sprintf('Path template [%s] has a malformed placeholder in segment [%s].', $pattern, $segment));
    }

    public static function missingVisibility(string $key): self
    {
        return new self(sprintf(
            'Subject of page type [%s] declares no visibility criterion; without it the resolver would serve hidden entities.',
            $key,
        ));
    }

    public static function missingSubject(string $key): self
    {
        return new self(sprintf('Template page type [%s] declares no subject.', $key));
    }

    public static function missingPath(string $key): self
    {
        return new self(sprintf('Page type [%s] declares no path template.', $key));
    }

    public static function unexpectedSubject(string $key): self
    {
        return new self(sprintf('Page type [%s] is not a template, so it cannot declare a subject.', $key));
    }

    public static function unexpectedPath(string $key): self
    {
        return new self(sprintf(
            'Page type [%s] holds many pages, so its address comes from the tree and cannot be declared.',
            $key,
        ));
    }

    public static function subjectPlaceholderMissing(string $key, string $pattern): self
    {
        return new self(sprintf(
            'Path template [%s] of page type [%s] carries no {%s} placeholder to read the subject from.',
            $pattern,
            $key,
            $key,
        ));
    }

    public static function unexpectedPlaceholders(string $key, string $pattern): self
    {
        return new self(sprintf(
            'Path [%s] of singleton page type [%s] carries placeholders; only a template resolves them.',
            $pattern,
            $key,
        ));
    }

    public static function invalidPresenter(string $key, string $presenter): self
    {
        return new self(sprintf(
            'Presenter [%s] of page type [%s] does not implement %s.',
            $presenter,
            $key,
            SubjectPresenter::class,
        ));
    }

    public static function invalidKey(string $key): self
    {
        return new self(sprintf('Page type key [%s] is not a kebab-case identifier.', $key));
    }
}
