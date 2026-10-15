<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Params;

use Illuminate\Container\Container;
use Illuminate\Validation\Rule;

/**
 * One value out of a declared set. The set is either written down in the
 * schema or computed by a provider class, see {@see OptionsProvider}.
 */
final class EnumParam extends Param
{
    /** @var array<string|int, string>|class-string<OptionsProvider>|null */
    private array|string|null $options = null;

    /**
     * @param  array<string|int, string>|class-string<OptionsProvider>  $options  value => label, or the provider class
     */
    public function options(array|string $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * The set as it stands right now. A provider is instantiated through the
     * container at the moment of the call: holding the instance in the
     * declaration would freeze it for the lifetime of the worker.
     *
     * @return array<string|int, string>
     */
    public function getOptions(): array
    {
        if ($this->options === null) {
            return [];
        }

        if (is_array($this->options)) {
            return $this->options;
        }

        $provider = Container::getInstance()->make($this->options);

        return $provider instanceof OptionsProvider ? $provider->options() : [];
    }

    /**
     * @return array<string|int, string>|class-string<OptionsProvider>|null
     */
    public function getOptionsDeclaration(): array|string|null
    {
        return $this->options;
    }

    /**
     * The rule object rather than a joined string: a value carrying a comma
     * would split an `in:` list in two.
     *
     * An empty set refuses every value that arrives instead of accepting any:
     * a provider that has nothing to offer today (no cities, no months with
     * events) is a set of none, not a set of all. Absence of the parameter is a
     * different matter and stays legal while it is `nullable()`.
     */
    public function rules(): array
    {
        return $this->rulesAgainst($this->getOptions());
    }

    /**
     * {@see self::rules()} against a set the caller has already computed:
     * one resolution of the parameters checks and then normalizes the same
     * value, and a provider is a query of the installation, so it is asked
     * once for both and not once for each.
     *
     * @param  array<string|int, string>  $options  the set as {@see self::getOptions()} returned it
     * @return list<mixed>
     */
    public function rulesAgainst(array $options): array
    {
        return [Rule::in(array_keys($options))];
    }

    /**
     * The key of the matching option, with its own type: a set keyed by
     * integers hands back an integer, so a comparison in the block does not
     * have to be loose.
     */
    public function normalize(mixed $raw): mixed
    {
        return $this->normalizeAgainst($raw, $this->getOptions());
    }

    /**
     * {@see self::normalize()} against a set the caller has already
     * computed, for the reason given at {@see self::rulesAgainst()}.
     *
     * @param  array<string|int, string>  $options  the set as {@see self::getOptions()} returned it
     */
    public function normalizeAgainst(mixed $raw, array $options): mixed
    {
        if (! is_scalar($raw)) {
            return null;
        }

        $value = (string) $raw;

        foreach (array_keys($options) as $option) {
            if ((string) $option === $value) {
                return $option;
            }
        }

        return $value;
    }
}
