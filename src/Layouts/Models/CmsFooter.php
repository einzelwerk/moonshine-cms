<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Models;

use Ewk\Cms\Layouts\Chrome\ChromeKind;
use Ewk\Cms\Layouts\Concerns\HasChromeContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A variant of the footer, the twin of {@see CmsHeader}: logo, copyright,
 * payment icons and brand line live in its declared content, the link columns
 * live in menus put into its slots.
 *
 * @property int $id
 * @property string $uuid
 * @property string $code
 * @property array<string, string> $name
 * @property bool $is_active
 * @property array<array-key, mixed>|null $content
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CmsMenuSlotAssignment> $menuSlots
 */
final class CmsFooter extends Model
{
    use HasChromeContent;

    /** @see CmsHeader::MORPH_ALIAS */
    public const string MORPH_ALIAS = 'cms_footer';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'is_active',
        'content',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'name' => 'array',
        'content' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (CmsFooter $footer): void {
            $footer->uuid = $footer->uuid ?: (string) Str::uuid();
        });
    }

    public function chromeKind(): ChromeKind
    {
        return ChromeKind::FOOTER;
    }

    /**
     * @return MorphMany<CmsMenuSlotAssignment, $this>
     */
    public function menuSlots(): MorphMany
    {
        return $this->morphMany(CmsMenuSlotAssignment::class, 'owner')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @param  Builder<CmsFooter>  $query
     * @return Builder<CmsFooter>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
