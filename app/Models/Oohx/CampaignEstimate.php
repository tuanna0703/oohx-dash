<?php

namespace App\Models\Oohx;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only projection của `output.campaign_estimates` trên Data Engine VPS.
 *
 * Phase 4.1 handoff §5 — stable JSON contract. Laravel chỉ SELECT, không write.
 * Data Engine worker tự INSERT khi `job_type='campaign_estimate'` chạy xong.
 *
 * Connection `oohx` (readonly) — không có write permissions trên output.*
 */
class CampaignEstimate extends Model
{
    protected $connection = 'oohx';
    protected $table      = 'output.campaign_estimates';
    public    $timestamps = false;

    protected $casts = [
        // Note: screen_ids = Postgres `bigint[]` → text format "{1,2,3}", KHÔNG phải JSON.
        // Laravel 'array' cast dùng json_decode → fail → empty. Xử lý qua accessor thay vì cast.
        'duration_days'                  => 'integer',
        'screens_with_estimate'          => 'integer',
        'screens_missing_estimate'       => 'integer',
        'total_daily_impressions'        => 'float',
        'total_impressions_for_duration' => 'float',
        'unique_geohash_cells'           => 'integer',
        'estimated_unique_reach'         => 'float',
        'estimated_frequency'            => 'float',
        'total_budget'                   => 'float',
        'estimated_cpm'                  => 'float',
        'avg_confidence'                 => 'float',
        'formula_version_id'             => 'integer',
        'computed_at'                    => 'datetime',
    ];

    // ── Accessors for UI ────────────────────────────────────────────────

    /**
     * Parse Postgres `bigint[]` text format "{1,2,3,42,58}" → PHP list<int>.
     * Laravel 'array' cast không work với PG array (không phải JSON).
     * Tolerant: JSON format [1,2,3] hoặc already-array cũng xử lý được.
     *
     * @return list<int>
     */
    public function getScreenIdsAttribute($value): array
    {
        if (is_array($value)) return array_map('intval', $value);
        if ($value === null || $value === '' || $value === '{}') return [];

        // PG text format: {1,2,3} → strip braces → explode
        if (is_string($value)) {
            $trimmed = trim($value);
            if (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) {
                $inner = trim($trimmed, '{}');
                if ($inner === '') return [];
                return array_values(array_map(
                    fn ($v) => (int) trim($v, '" '),
                    explode(',', $inner),
                ));
            }

            // Fallback: try JSON decode (in case DE stores as JSONB)
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) return array_map('intval', $decoded);
        }

        return [];
    }

    /**
     * Confidence tier label + color — reused từ ScreenEstimate pattern.
     */
    public function getConfidenceTierAttribute(): string
    {
        $s = $this->avg_confidence;
        if ($s === null) return 'unknown';
        if ($s >= 0.7) return 'high';
        if ($s >= 0.5) return 'mid';
        return 'low';
    }

    public function getConfidenceColorAttribute(): string
    {
        return match ($this->confidence_tier) {
            'high'    => 'success',
            'mid'     => 'warning',
            'low'     => 'danger',
            default   => 'gray',
        };
    }

    /**
     * Frequency warning — handoff §6.2: frequency > 100 → over-saturation.
     */
    public function getFrequencyWarningAttribute(): ?string
    {
        $f = $this->estimated_frequency;
        if ($f === null) return null;
        if ($f > 100)  return 'Over-saturation — same viewers exposed nhiều lần';
        if ($f > 50)   return 'Frequency cao — cân nhắc thêm screens ở vùng khác';
        return null;
    }

    public function getFrequencyColorAttribute(): string
    {
        $f = $this->estimated_frequency;
        if ($f === null) return 'gray';
        if ($f > 100) return 'danger';
        if ($f > 50)  return 'warning';
        return 'success';
    }

    /**
     * Missing screens warning — handoff §6.2.
     */
    public function getMissingScreensWarningAttribute(): ?string
    {
        $miss = $this->screens_missing_estimate ?? 0;
        if ($miss === 0) return null;
        $total = count($this->screen_ids ?? []);
        return "{$miss}/{$total} screens chưa có estimate — kết quả không đầy đủ";
    }

    /**
     * Total screen count for display (từ screen_ids array).
     */
    public function getScreenCountAttribute(): int
    {
        return count($this->screen_ids ?? []);
    }

    /**
     * Impressions/budget summary line cho table.
     */
    public function getBudgetLabelAttribute(): string
    {
        $b = $this->total_budget;
        return $b ? number_format($b, 0, '.', ',') . ' VND' : '—';
    }
}
