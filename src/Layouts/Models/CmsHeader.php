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
 * A variant of the header: an own record with an own code, not an option of a
 * layout. Its structure is frontend code — search, cart, language switcher —
 * and only the declared content and the menus of its slots are edited here.
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
final class CmsHeader extends Model
{
    use HasChromeContent;

    /**
     * What the polymorphic columns of the CMS store for a header. An
     * alias instead of the class name keeps the rows readable and survives
     * a renaming of the model.
     */
    public const string MORPH_ALIAS = 'cms_header';

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
        static::creating(function (CmsHeader $header): void {
            $header->uuid = $header->uuid ?: (string) Str::uuid();
        });
    }

    public function chromeKind(): ChromeKind
    {
        return ChromeKind::HEADER;
    }

    /**
     * The menus put into the slots of this header, in editor order.
     *
     * @return MorphMany<CmsMenuSlotAssignment, $this>
     */
    public function menuSlots(): MorphMany
    {
        return $this->morphMany(CmsMenuSlotAssignment::class, 'owner')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @param  Builder<CmsHeader>  $query
     * @return Builder<CmsHeader>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
