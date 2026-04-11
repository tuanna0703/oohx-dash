<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Screen inventory — marketplace + adops metadata.
 *
 * Marketplace fields (Phase 1):
 * @property string      $venue_type
 * @property float       $floor_cpm
 * @property string      $floor_cpm_currency
 * @property int|null    $weekly_impressions
 * @property array|null  $operating_hours
 * @property string      $timezone
 * @property int         $spot_length
 * @property bool        $programmatic_enabled
 *
 * @internal AdOps fields (Phase 2 — hidden from UI by default):
 * @property int         $max_spot_length
 * @property int         $min_spot_length
 * @property int|null    $loop_length
 * @property float|null  $floor_cpm_usd
 * @property bool        $pmp_only
 * @property bool        $ad_server_enabled
 * @property bool        $deals_enabled
 * @property int         $share_of_voice_max_pct
 * @property int|null    $screen_count_override
 * @property int         $frequency_cap
 * @property int         $category_frequency_cap
 * @property bool        $strict_frequency_capping
 */
class ScreenInventory extends Model
{
    use HasFactory;

    protected $table = 'screen_inventory';

    protected $fillable = [
        // Marketplace
        'screen_id', 'network_id', 'network_name', 'venue_type',
        'spot_length', 'floor_cpm', 'floor_cpm_currency',
        'weekly_impressions', 'operating_hours', 'timezone',
        'programmatic_enabled',

        // AdOps (Phase 2)
        'max_spot_length', 'min_spot_length', 'loop_length',
        'floor_cpm_usd',
        'pmp_only', 'ad_server_enabled', 'deals_enabled',
        'share_of_voice_max_pct',
        'screen_count_override',
        'frequency_cap', 'category_frequency_cap', 'strict_frequency_capping',
    ];

    protected $casts = [
        'operating_hours'          => 'array',
        'programmatic_enabled'     => 'boolean',
        'pmp_only'                 => 'boolean',
        'ad_server_enabled'        => 'boolean',
        'deals_enabled'            => 'boolean',
        'strict_frequency_capping' => 'boolean',
        'floor_cpm'                => 'decimal:2',
        'floor_cpm_usd'            => 'decimal:4',
    ];

    // ── Relationships ───────────────────────────────────────

    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class);
    }

    // ── Helpers ─────────────────────────────────────────────

    public function computeFloorCpmUsd(float $rate = 25000): float
    {
        if (! $this->floor_cpm) {
            return 0;
        }
        if ($this->floor_cpm_currency === 'USD') {
            return (float) $this->floor_cpm;
        }

        return round($this->floor_cpm / $rate, 4);
    }

    public function getDailyImpressionsAttribute(): ?int
    {
        return $this->weekly_impressions
            ? (int) round($this->weekly_impressions / 7)
            : null;
    }

    /** @internal AdOps — số màn hình thực tế (override hoặc mặc định 1) */
    public function getEffectiveScreenCountAttribute(): int
    {
        return ($this->screen_count_override && $this->screen_count_override > 0)
            ? (int) $this->screen_count_override
            : 1;
    }
}
