<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Fields;

use Ewk\Cms\Blocks\Links\LinkResolverRegistry;
use Illuminate\Container\Container;
use Throwable;

/**
 * One value out of a declared set. Choosing a database record is the `entity`
 * field instead; a select covers sets without an entity behind them.
 *
 * The set may also depend on the owner of the block: a source selector offers
 * `subject` only where a subject exists. Such a field names a {@see
 * FieldOptionsProvider} class, and the declared options stay as the answer for
 * a context nobody filled in.
 */
final class SelectField extends Field
{
    /** @var array<string, string> */
    private array $options = [];

    /** @var class-string<FieldOptionsProvider>|null */
    private ?string $optionsProvider = null;

    /**
     * @param  array<string, string>  $options  value => label
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * The set the owner of the block decides, see {@see FieldOptionsProvider}.
     * The declared options stay the default answer, so a caller with no
     * context in hand still gets a sensible field.
     *
     * @param  class-string<FieldOptionsProvider>  $provider
     */
    public function optionsFor(string $provider): static
    {
        $this->optionsProvider = $provider;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return class-string<FieldOptionsProvider>|null
     */
    public function getOptionsProvider(): ?string
    {
        return $this->optionsProvider;
    }

    /**
     * What this field offers to the owner at hand.
     *
     * A context that names no owner is not a narrowing but the absence of
     * one, and it is answered with the declared options: a rule derived
     * without a context must never be stricter than the form the editor saw.
     * The declared options answer as well outside a booted application and
     * for a provider the container cannot build — the model layer of this
     * package works without a container on purpose, and a field without
     * options is worse than a field with the declared ones.
     *
     * @return array<string, string>
     */
    public function optionsIn(FieldContext $context): array
    {
        $provider = $this->optionsProvider;

        if ($provider === null || $context->owner === null) {
            return $this->options;
        }

        try {
            $resolved = Container::getInstance()->make($provider);
        } catch (Throwable) {
            return $this->options;
        }

        return $resolved instanceof FieldOptionsProvider ? $resolved->options($context) : $this->options;
    }

    public function normalize(mixed $raw): mixed
    {
        return self::stringOrNull($raw);
    }

    public function rules(): array
    {
        return $this->rulesIn(FieldContext::none());
    }

    /**
     * A value outside what the owner offers is refused on save as well: the
     * form is a hint, the rule is the guarantee.
     */
    public function rulesIn(FieldContext $context): array
    {
        $rules = ['string'];
        $options = $this->optionsIn($context);

        if ($options !== []) {
            $rules[] = 'in:'.implode(',', array_keys($options));
        }

        return $rules;
    }

    public function toPayload(
        mixed $storedValue,
        string $locale,
        string $fallbackLocale,
        ?LinkResolverRegistry $linkResolver = null,
    ): mixed {
        return $storedValue === null ? $this->default : $this->normalize($storedValue);
    }
}
