<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A shell of a page addressed by its code. The code is the contract with the
 * frontend: every code has a component there, and a page references a layout by
 * that string, which is why no foreign key points at this table from the pages
 * package.
 *
 * A layout carries no content of its own — container width, a service class
 * and which header and footer it uses. Drawing anything inside the shell is
 * the job of blocks.
 *
 * @property int $id
 * @property string $uuid
 * @property string $code
 * @property array<string, string> $name
 * @property bool $is_active
 * @property bool $is_default
 * @property int|null $cms_header_id
 * @property int|null $cms_footer_id
 * @property string|null $container
 * @property string|null $body_class
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CmsHeader|null $cmsHeader
 * @property-read CmsFooter|null $cmsFooter
 */
final class CmsLayout extends Model
{
    /** @see CmsHeader::MORPH_ALIAS */
    public const string MORPH_ALIAS = 'cms_layout';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'is_active',
        'is_default',
        'cms_header_id',
        'cms_footer_id',
        'container',
        'body_class',
        'sort_order',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'name' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'cms_header_id' => 'integer',
        'cms_footer_id' => 'integer',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (CmsLayout $layout): void {
            $layout->uuid = $layout->uuid ?: (string) Str::uuid();
        });
    }

    /**
     * @return BelongsTo<CmsHeader, $this>
     */
    public function cmsHeader(): BelongsTo
    {
        return $this->belongsTo(CmsHeader::class);
    }

    /**
     * @return BelongsTo<CmsFooter, $this>
     */
    public function cmsFooter(): BelongsTo
    {
        return $this->belongsTo(CmsFooter::class);
    }

    /**
     * @param  Builder<CmsLayout>  $query
     * @return Builder<CmsLayout>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
