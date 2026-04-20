<?php

namespace App\Models\Oohx\Config;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * config.seasonality_factors — multiplier per (city, month) để adjust estimates
 * theo mùa vụ (Tet low, summer high, ...).
 *
 * Composite PK (city, month) → Eloquent không native support → dùng pattern:
 *   - $primaryKey = null
 *   - $incrementing = false
 *   - scopeByKey() helper query (city, month)
 *   - getRouteKey() / resolveRouteBinding() — synthetic "city:month" string
 *
 * Data được seed bên Python CLI `seed-seasonality`. Laravel chỉ edit qua
 * ConfigManagerService để có audit log.
 *
 * CHECK constraints phía DB:
 *   - month ∈ 1..12
 *   - factor > 0 AND factor <= 2
 */
class SeasonalityFactor extends Model
{
    protected $connection   = 'oohx_control';
    protected $table        = 'config.seasonality_factors';
    protected $primaryKey   = null;
    public    $incrementing = false;
    public    $timestamps   = false;

    protected $fillable = ['city', 'month', 'factor', 'note', 'updated_by', 'updated_at'];

    protected $casts = [
        'month'      => 'integer',
        'factor'     => 'float',
        'updated_at' => 'datetime',
    ];

    /**
     * Synthetic route key "city:month" — cho Filament record routing.
     */
    public function getRouteKey(): string
    {
        return "{$this->city}:{$this->month}";
    }

    public function getRouteKeyName(): string
    {
        return 'composite_key';
    }

    /**
     * Resolve "city:month" back thành model instance.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        [$city, $month] = array_pad(explode(':', (string) $value, 2), 2, null);
        if (! $city || ! $month) return null;
        return static::byKey($city, (int) $month)->first();
    }

    // ── Scopes ─────────────────────────────────────────────────────────

    public function scopeByKey(Builder $q, string $city, int $month): Builder
    {
        return $q->where('city', $city)->where('month', $month);
    }

    public function scopeForCity(Builder $q, string $city): Builder
    {
        return $q->where('city', $city);
    }

    public function scopeForMonth(Builder $q, int $month): Builder
    {
        return $q->where('month', $month);
    }

    // ── Accessors for UI ──────────────────────────────────────────────

    public function getMonthLabelAttribute(): string
    {
        return [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',
                7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'][(int) $this->month] ?? (string) $this->month;
    }

    /**
     * Factor color tier cho heatmap + badges.
     * Returns 'success' (amplify) | 'warning' (neutral) | 'danger' (dampen) | 'gray'
     */
    public function getFactorColorAttribute(): string
    {
        $f = (float) ($this->factor ?? 0);
        if ($f <= 0) return 'gray';
        if ($f > 1.05) return 'success';
        if ($f < 0.95) return 'danger';
        return 'warning';
    }
}
