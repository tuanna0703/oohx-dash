<?php

namespace App\Models;

use App\Traits\HasOwnerScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasUlids, SoftDeletes, HasOwnerScope;

    // Type constants
    public const TYPE_SINGLE  = 'single';
    public const TYPE_PACKAGE = 'package';
    public const TYPE_CUSTOM  = 'custom';

    // Category constants
    public const CATEGORY_BILLBOARD      = 'billboard';
    public const CATEGORY_LED            = 'led';
    public const CATEGORY_LCD            = 'lcd';
    public const CATEGORY_POSM           = 'posm';
    public const CATEGORY_STANDEE        = 'standee';
    public const CATEGORY_ESCALATOR_WRAP = 'escalator_wrap';
    public const CATEGORY_CEILING_HANG   = 'ceiling_hang';
    public const CATEGORY_LIGHTBOX       = 'lightbox';

    public const CATEGORIES = [
        self::CATEGORY_BILLBOARD      => 'Billboard',
        self::CATEGORY_LED            => 'LED Digital',
        self::CATEGORY_LCD            => 'LCD',
        self::CATEGORY_POSM           => 'POSM',
        self::CATEGORY_STANDEE        => 'Standee',
        self::CATEGORY_ESCALATOR_WRAP => 'Dán thang cuốn',
        self::CATEGORY_CEILING_HANG   => 'Thả trần',
        self::CATEGORY_LIGHTBOX       => 'Lightbox',
    ];

    protected $fillable = [
        'owner_id', 'network_id', 'site_id',
        'name', 'slug', 'type', 'category', 'listing_mode',
        'min_quantity', 'max_quantity', 'total_units',
        'floor_price', 'currency', 'price_unit',
        'description', 'short_description', 'cover_photo', 'photos', 'specs',
        'city', 'region', 'package_options',
        'status', 'featured', 'sort_order',
        'meta_title', 'meta_description',
    ];

    protected $casts = [
        'photos'          => 'array',
        'specs'           => 'array',
        'package_options' => 'array',
        'floor_price'     => 'decimal:2',
        'min_quantity'    => 'integer',
        'max_quantity'    => 'integer',
        'total_units'     => 'integer',
        'featured'        => 'boolean',
        'sort_order'      => 'integer',
    ];

    // ── Relationships ──

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function screens(): BelongsToMany
    {
        return $this->belongsToMany(Screen::class, 'product_screens')
            ->using(ProductScreen::class)
            ->withPivot('is_primary', 'sort_order')
            ->orderByPivot('sort_order');
    }

    public function primaryScreen(): BelongsToMany
    {
        return $this->screens()->wherePivot('is_primary', true);
    }

    public function bookingLines(): HasMany
    {
        return $this->hasMany(BookingLine::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    // ── Accessors ──

    public function getCoverUrlAttribute(): ?string
    {
        if ($this->cover_photo) {
            return asset('storage/' . $this->cover_photo);
        }
        // Fallback: first photo
        $first = collect($this->photos)->first();
        return $first ? asset('storage/' . $first) : null;
    }

    public function getPhotoUrlsAttribute(): array
    {
        return collect($this->photos ?? [])
            ->map(fn ($p) => asset('storage/' . $p))
            ->toArray();
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function getPriceDisplayAttribute(): string
    {
        $price = number_format($this->floor_price, 0, ',', '.');
        $unit = match ($this->price_unit) {
            'month'    => '/tháng',
            'week'     => '/tuần',
            'day'      => '/ngày',
            'campaign' => '/campaign',
            default    => '',
        };

        if ($this->type === self::TYPE_PACKAGE && $this->min_quantity > 1) {
            return $price . ' ₫' . $unit . '/' . $this->min_quantity . ' vị trí';
        }

        return $price . ' ₫' . $unit;
    }

    public function getScreenCountAttribute(): int
    {
        return $this->screens()->count();
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByCity($query, string $city)
    {
        return $query->where('city', 'like', '%' . $city . '%');
    }

    // ── Helpers ──

    public function isSingle(): bool  { return $this->type === self::TYPE_SINGLE; }
    public function isPackage(): bool  { return $this->type === self::TYPE_PACKAGE; }
    public function isCustom(): bool   { return $this->type === self::TYPE_CUSTOM; }
}
