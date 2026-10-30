<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Tests\Fixtures;

use Ewk\Cms\Seo\Concerns\HasSeoMetadata;
use Ewk\Cms\Seo\Contracts\ProvidesSeoDescriptionFallback;
use Ewk\Cms\Seo\Contracts\ProvidesSeoFallbacks;
use Illuminate\Database\Eloquent\Model;

/**
 * An owner that opts into a description of its own, like a tour with the text
 * its organizer wrote.
 */
final class DescribedOwner extends Model implements ProvidesSeoDescriptionFallback, ProvidesSeoFallbacks
{
    use HasSeoMetadata;

    public ?string $about = null;

    protected $table = 'test_seo_owners';

    protected $fillable = ['name', 'cover_url'];

    public function seoFallbackTitle(string $locale): ?string
    {
        return null;
    }

    public function seoFallbackImage(string $locale): ?string
    {
        return null;
    }

    public function seoFallbackDescription(string $locale): ?string
    {
        return $this->about;
    }
}
