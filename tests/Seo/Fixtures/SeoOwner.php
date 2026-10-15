<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Tests\Fixtures;

use Ewk\Cms\Blocks\Localization\LocalizedValueResolver;
use Ewk\Cms\Seo\Concerns\HasSeoMetadata;
use Ewk\Cms\Seo\Contracts\ProvidesSeoFallbacks;
use Illuminate\Database\Eloquent\Model;

/**
 * What a model of an installation looks like from the outside: it owns
 * metadata and it answers what goes into an empty title and an empty picture.
 *
 * @property array<string, string>|null $name
 * @property string|null $cover_url
 */
final class SeoOwner extends Model implements ProvidesSeoFallbacks
{
    use HasSeoMetadata;

    protected $table = 'test_seo_owners';

    protected $fillable = ['name', 'cover_url'];

    /** @var array<string, string> */
    protected $casts = ['name' => 'array'];

    public function seoFallbackTitle(string $locale): ?string
    {
        $value = LocalizedValueResolver::resolve($this->name, $locale, 'ru');

        return $value === '' ? null : $value;
    }

    public function seoFallbackImage(string $locale): ?string
    {
        return $this->cover_url;
    }
}
