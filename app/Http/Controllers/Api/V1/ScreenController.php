<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Screen;
use App\Models\ScreenInventory;
use App\Models\ScreenSpec;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ScreenController extends Controller
{
    public function index(): JsonResponse
    {
        $screens = QueryBuilder::for(Screen::class)
            ->allowedFilters([
                'active', 'status', 'player_type',
                AllowedFilter::exact('site_id'),
                AllowedFilter::exact('owner_id'),
                AllowedFilter::callback('venue_type', fn ($q, $v) =>
                    $q->whereHas('inventory', fn ($iq) => $iq->where('venue_type', $v))
                ),
                AllowedFilter::callback('programmatic', fn ($q, $v) =>
                    $q->whereHas('inventory', fn ($iq) => $iq->where('programmatic_enabled', (bool) $v))
                ),
            ])
            ->allowedSorts(['name', 'created_at', 'last_heartbeat_at'])
            ->allowedIncludes(['spec', 'inventory', 'site'])
            ->paginate(20);

        return response()->json($screens);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'owner_id'         => 'required|ulid|exists:owners,id',
            'site_external_id' => 'required|string',
            'external_id'      => 'required|string|max:75',
            'name'             => 'required|string|max:199',
            'description'      => 'nullable|string',
            'player_type'      => 'sometimes|in:adtrue_android,adtrue_webview,third_party,vast_only',
            'uuid'             => 'nullable|uuid',
            'spec'             => 'nullable|array',
            'spec.width_px'    => 'required_with:spec|integer|min:1',
            'spec.height_px'   => 'required_with:spec|integer|min:1',
            'inventory'        => 'nullable|array',
        ]);

        $site = Site::where('owner_id', $data['owner_id'])
            ->where('external_id', $data['site_external_id'])
            ->first();

        if (! $site) {
            return response()->json(['message' => 'Site not found'], 422);
        }

        $screen = Screen::create([
            'owner_id'    => $data['owner_id'],
            'site_id'     => $site->id,
            'external_id' => $data['external_id'],
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'player_type' => $data['player_type'] ?? 'adtrue_android',
            'uuid'        => $data['uuid'] ?? null,
            'active'      => true,
            'status'      => 'offline',
        ]);

        if (! empty($data['spec'])) {
            $this->saveSpec($screen, $data['spec']);
        }
        if (! empty($data['inventory'])) {
            $this->saveInventory($screen, $data['inventory']);
        }

        return response()->json($screen->load(['spec', 'inventory']), 201);
    }

    public function show(Screen $screen): JsonResponse
    {
        return response()->json(
            $screen->load(['spec', 'inventory', 'site'])->append(['is_online'])
        );
    }

    public function update(Request $request, Screen $screen): JsonResponse
    {
        $data = $request->validate([
            'name'           => 'sometimes|string|max:199',
            'description'    => 'nullable|string',
            'active'         => 'sometimes|boolean',
            'player_type'    => 'sometimes|in:adtrue_android,adtrue_webview,third_party,vast_only',
            'player_version' => 'nullable|string',
            'spec'           => 'nullable|array',
            'inventory'      => 'nullable|array',
        ]);

        $screen->update(collect($data)->except(['spec', 'inventory'])->toArray());

        if (! empty($data['spec'])) {
            $this->saveSpec($screen, $data['spec']);
        }
        if (! empty($data['inventory'])) {
            $this->saveInventory($screen, $data['inventory']);
        }

        return response()->json($screen->load(['spec', 'inventory']));
    }

    public function destroy(Screen $screen): JsonResponse
    {
        $screen->delete();

        return response()->json(null, 204);
    }

    public function multipliers(Screen $screen): JsonResponse
    {
        return response()->json(
            $screen->multipliers()
                ->orderBy('day_of_week')->orderBy('hour_of_day')
                ->get()->groupBy('day_of_week')
        );
    }

    public function updateMultipliers(Request $request, Screen $screen): JsonResponse
    {
        $data = $request->validate([
            'multipliers'   => 'required|array',
            'multipliers.*.day_of_week' => 'required|integer|min:0|max:6',
            'multipliers.*.hour_of_day' => 'required|integer|min:0|max:23',
            'multipliers.*.multiplier'  => 'required|numeric|min:0',
        ]);

        foreach ($data['multipliers'] as $m) {
            $screen->multipliers()->updateOrCreate(
                ['day_of_week' => $m['day_of_week'], 'hour_of_day' => $m['hour_of_day']],
                ['multiplier' => $m['multiplier']]
            );
        }

        return response()->json(['message' => 'Multipliers updated']);
    }

    public function toggleProgrammatic(Screen $screen): JsonResponse
    {
        $inv = $screen->inventory;
        if (! $inv) {
            return response()->json(['message' => 'No inventory settings'], 422);
        }

        $inv->update(['programmatic_enabled' => ! $inv->programmatic_enabled]);

        return response()->json(['programmatic_enabled' => $inv->programmatic_enabled]);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function saveSpec(Screen $screen, array $data): void
    {
        ScreenSpec::updateOrCreate(
            ['screen_id' => $screen->id],
            array_filter([
                'width_px'    => $data['width_px'] ?? 1920,
                'height_px'   => $data['height_px'] ?? 1080,
                'width_cm'    => $data['width_cm'] ?? null,
                'height_cm'   => $data['height_cm'] ?? null,
                'photo_url'   => $data['photo_url'] ?? null,
                'allow_image' => $data['allow_image'] ?? true,
                'allow_video' => $data['allow_video'] ?? true,
            ], fn ($v) => $v !== null)
        );
    }

    private function saveInventory(Screen $screen, array $data): void
    {
        ScreenInventory::updateOrCreate(
            ['screen_id' => $screen->id],
            array_filter([
                'venue_type'           => $data['venue_type'] ?? null,
                'vn_category_id'       => $data['vn_category_id'] ?? null,
                'floor_cpm'            => $data['floor_cpm'] ?? null,
                'floor_cpm_currency'   => $data['floor_cpm_currency'] ?? 'VND',
                'spot_length'          => $data['spot_length'] ?? 15,
                'weekly_impressions'   => $data['weekly_impressions'] ?? null,
                'programmatic_enabled' => $data['programmatic_enabled'] ?? false,
                'timezone'             => $data['timezone'] ?? 'Asia/Ho_Chi_Minh',
            ], fn ($v) => $v !== null)
        );
    }
}
