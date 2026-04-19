<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only client cho OOHX Data Engine (PostGIS service trên 139.162.20.95).
 *
 * Estimates (daily_passby, daily_impressions, reach, confidence...) được Data
 * Engine precompute từ POI/road network enrichment. Laravel chỉ SELECT qua
 * SSH tunnel (connection `oohx` trong config/database.php).
 *
 * External ID khớp với `screens.uuid` bên Laravel (đã chốt team).
 */
class OohxDataEngine
{
    private function connection()
    {
        return DB::connection('oohx');
    }

    /**
     * Lấy estimate cho 1 screen theo uuid Laravel.
     */
    public function getEstimateByExternalId(string $externalId): ?object
    {
        return $this->connection()->selectOne("
            SELECT s.external_id,
                   s.name,
                   s.indoor_outdoor,
                   s.city,
                   s.zone_type,
                   e.estimated_daily_passby,
                   e.estimated_daily_screen_flow,
                   e.estimated_daily_ots,
                   e.estimated_daily_impressions,
                   e.estimated_weekly_impressions,
                   e.estimated_monthly_impressions,
                   e.estimated_daily_reach,
                   e.estimated_weekly_reach,
                   e.estimated_monthly_reach,
                   e.estimated_frequency,
                   e.confidence_score,
                   e.estimation_method,
                   e.model_version,
                   e.last_calculated_at
            FROM output.screen_traffic_estimates e
            JOIN core.screens s ON s.id = e.screen_id
            WHERE s.external_id = ?
        ", [$externalId]);
    }

    /**
     * Bulk lookup — 1 query cho N screens. Collection keyed by external_id.
     * Dùng khi render listing/map để tránh N+1.
     */
    public function getEstimatesByExternalIds(array $externalIds): Collection
    {
        if (empty($externalIds)) {
            return collect();
        }

        $placeholders = implode(',', array_fill(0, count($externalIds), '?'));

        $rows = $this->connection()->select("
            SELECT s.external_id,
                   s.indoor_outdoor,
                   e.estimated_daily_impressions,
                   e.estimated_monthly_impressions,
                   e.estimated_daily_reach,
                   e.estimated_monthly_reach,
                   e.confidence_score,
                   e.estimation_method,
                   e.last_calculated_at
            FROM output.screen_traffic_estimates e
            JOIN core.screens s ON s.id = e.screen_id
            WHERE s.external_id IN ($placeholders)
        ", $externalIds);

        return collect($rows)->keyBy('external_id');
    }

    /**
     * Top N screens theo daily_impressions trong 1 city.
     * `city` phải khớp giá trị Data Engine biết (Hanoi, HCMC, Danang...).
     */
    public function topScreensByImpressions(string $city, int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));

        return $this->connection()->select("
            SELECT s.external_id,
                   s.name,
                   s.indoor_outdoor,
                   s.zone_type,
                   e.estimated_daily_impressions,
                   e.estimated_monthly_impressions,
                   e.confidence_score
            FROM output.screen_traffic_estimates e
            JOIN core.screens s ON s.id = e.screen_id
            WHERE s.city = ?
            ORDER BY e.estimated_daily_impressions DESC NULLS LAST
            LIMIT ?
        ", [$city, $limit]);
    }
}
