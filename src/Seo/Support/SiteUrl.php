<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Support;

use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;

/**
 * The second mode of address building: absolute, with the language in it.
 *
 * Addresses inside the content of a block stay relative and without a
 * language — there a link is resolved by the frontend with its own policy.
 * Here an address leaves for a tag a search engine reads, and a relative one
 * in `alternates` or in `openGraph` is simply wrong.
 */
final readonly class SiteUrl
{
    public const string PREFIX_ALWAYS = 'always';

    public const string PREFIX_NEVER = 'never';

    public function __construct(private Repository $config)
    {
    }

    /**
     * `$path` is the normalized path of a page, with a leading slash and
     * without a trailing one, the root being `/`. The root of a language is
     * `https://site/ru` and not `https://site/ru/`: a trailing slash would
     * make the canonical address of the home page differ from the address the
     * visitor is on.
     */
    public function absolute(string $path, string $locale): string
    {
        $base = rtrim($this->siteUrl(), '/');
        $prefix = '';

        if ($this->localePrefix() === self::PREFIX_ALWAYS) {
            $code = trim($locale, '/');

            // `https://site//afisha` would be a different address from the one
            // the visitor is on, and a canonical tag pointing at it is worse
            // than no tag at all. A missing language is a bug of the caller.
            if ($code === '') {
                throw new InvalidArgumentException('A language code is required while cms-seo.locale_prefix is "always".');
            }

            $prefix = '/'.$code;
        }

        $suffix = $this->pathSuffix($path);

        if ($prefix === '' && $suffix === '') {
            return $base.'/';
        }

        return $base.$prefix.$suffix;
    }

    /**
     * Whether the language is a part of an address at all. Without a prefix
     * every language of a page shares one address, so there is nothing for
     * a set of language versions to tell apart.
     */
    public function prefixesLanguage(): bool
    {
        return $this->localePrefix() === self::PREFIX_ALWAYS;
    }

    /**
     * The root contributes nothing: the language prefix already ends the
     * address, and without a prefix the caller gets the bare site address.
     */
    private function pathSuffix(string $path): string
    {
        $trimmed = trim($path, '/');

        return $trimmed === '' ? '' : '/'.$trimmed;
    }

    private function siteUrl(): string
    {
        $configured = $this->config->get('cms-seo.site_url');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $fallback = $this->config->get('app.url');

        return is_string($fallback) && $fallback !== '' ? $fallback : 'http://localhost';
    }

    private function localePrefix(): string
    {
        $configured = $this->config->get('cms-seo.locale_prefix', self::PREFIX_ALWAYS);

        return $configured === self::PREFIX_NEVER ? self::PREFIX_NEVER : self::PREFIX_ALWAYS;
    }
}
