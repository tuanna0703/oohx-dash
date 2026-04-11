<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Screen technical specifications.
 *
 * Marketplace fields (Phase 1):
 * @property int         $width_px
 * @property int         $height_px
 * @property float|null  $width_cm
 * @property float|null  $height_cm
 * @property string|null $photo_url
 * @property bool        $allow_image
 * @property bool        $allow_video
 *
 * @internal AdOps fields (Phase 2 — hidden from frontpage):
 * @property string|null $resolution_preset
 * @property string|null $width_unit
 * @property string|null $height_unit
 * @property string|null $facing_direction
 * @property bool        $allow_html
 * @property bool        $allow_zip
 * @property bool        $allow_vast
 * @property array|null  $allowed_languages
 */
class ScreenSpec extends Model
{
    use HasFactory;

    protected $table = 'screen_specs';

    protected $fillable = [
        // Marketplace
        'screen_id', 'width_px', 'height_px', 'width_cm', 'height_cm',
        'photo_url', 'photos', 'allow_image', 'allow_video',

        // AdOps (Phase 2)
        'resolution_preset', 'width_unit', 'height_unit',
        'facing_direction',
        'allow_html', 'allow_zip', 'allow_vast',
        'allowed_languages',
    ];

    protected $casts = [
        'allow_image'       => 'boolean',
        'allow_video'       => 'boolean',
        'allow_html'        => 'boolean',
        'allow_zip'         => 'boolean',
        'allow_vast'        => 'boolean',
        'allowed_languages' => 'array',
        'photos'            => 'array',
        'width_cm'          => 'decimal:1',
        'height_cm'         => 'decimal:1',
    ];

    // ── Relationships ───────────────────────────────────────

    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    // ── Accessors ───────────────────────────────────────────

    /**
     * Get the first photo as a full public URL.
     * Supports: photos array, photo_url string, full URL, or relative storage path.
     */
    public function getPhotoAttribute(): ?string
    {
        $path = collect($this->photos)->first() ?? $this->photo_url;
        if (! $path) return null;

        // Already a full URL
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset('storage/' . $path);
    }

    /**
     * Get all photos as full public URLs.
     */
    public function getPhotoUrlsAttribute(): array
    {
        $paths = $this->photos ?? ($this->photo_url ? [$this->photo_url] : []);

        return collect($paths)->map(function ($path) {
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
            return asset('storage/' . $path);
        })->all();
    }

    public function getOrientationAttribute(): string
    {
        if ($this->width_px > $this->height_px) {
            return 'landscape';
        }
        if ($this->width_px < $this->height_px) {
            return 'portrait';
        }

        return 'square';
    }

    public function getAspectRatioAttribute(): string
    {
        if (! $this->width_px || ! $this->height_px) {
            return 'unknown';
        }

        $gcd = $this->gcd($this->width_px, $this->height_px);

        return ($this->width_px / $gcd) . ':' . ($this->height_px / $gcd);
    }

    private function gcd(int $a, int $b): int
    {
        return $b === 0 ? $a : $this->gcd($b, $a % $b);
    }
}
