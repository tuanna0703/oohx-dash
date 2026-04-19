<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\OohxDataEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public read-only endpoints exposing Data Engine traffic estimates.
 *
 * Nguồn: PostgreSQL + PostGIS trên VPS riêng (139.162.20.95), qua SSH tunnel 5433.
 * External ID = `screens.uuid` của Laravel (chốt với team).
 *
 * Endpoints nằm trong scope `inventory` giống các endpoint khác — Sanctum token
 * phải có ability `inventory` mới truy cập được.
 */
class OohxEstimateController extends Controller
{
    public function __construct(private OohxDataEngine $engine) {}

    /**
     * GET /api/v1/oohx/estimates?city=Hanoi&limit=20
     * Top N screens theo daily_impressions trong 1 city.
     */
    public function topByCity(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city'  => ['required', 'string', 'max:64'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $data = $this->engine->topScreensByImpressions(
            $validated['city'],
            $validated['limit'] ?? 20,
        );

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/v1/oohx/estimates/{externalId}
     * Detail estimate cho 1 screen. 404 nếu chưa có (chưa sync hoặc chưa recompute).
     */
    public function show(string $externalId): JsonResponse
    {
        $estimate = $this->engine->getEstimateByExternalId($externalId);
        abort_unless($estimate, 404, 'Estimate not found');

        return response()->json(['data' => $estimate]);
    }
}
