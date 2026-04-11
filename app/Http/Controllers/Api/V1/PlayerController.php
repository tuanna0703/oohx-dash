<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ImpressionLog;
use App\Models\Screen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    public function heartbeat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'screen_uuid'    => 'required|uuid',
            'player_version' => 'nullable|string',
        ]);

        $screen = Screen::where('uuid', $data['screen_uuid'])->first();
        if (! $screen) {
            return response()->json(['message' => 'Screen not found'], 404);
        }

        $screen->update([
            'last_heartbeat_at' => now(),
            'status'            => 'online',
            'player_version'    => $data['player_version'] ?? $screen->player_version,
        ]);

        return response()->json([
            'status'           => 'ok',
            'server_time'      => now()->toIso8601String(),
            'operating'        => $screen->isOperating(),
            'playlist_version' => $screen->inventory?->updated_at?->timestamp,
        ]);
    }

    public function impression(Request $request): JsonResponse
    {
        $data = $request->validate([
            'screen_uuid'  => 'required|uuid',
            'campaign_id'  => 'nullable|integer',
            'creative_id'  => 'nullable|integer',
            'duration_sec' => 'required|integer|min:1',
            'played_at'    => 'nullable|date',
            'deal_type'    => 'nullable|in:direct,rtb,pmp',
            'proof_url'    => 'nullable|url',
        ]);

        $screen = Screen::where('uuid', $data['screen_uuid'])->first();
        if (! $screen) {
            return response()->json(['message' => 'Screen not found'], 404);
        }

        $multiplier = $screen->getCurrentMultiplier();

        $log = ImpressionLog::create([
            'screen_id'          => $screen->id,
            'owner_id'           => $screen->owner_id,
            'campaign_id'        => $data['campaign_id'] ?? null,
            'creative_id'        => $data['creative_id'] ?? null,
            'played_at'          => $data['played_at'] ?? now(),
            'duration_sec'       => $data['duration_sec'],
            'multiplier_applied' => $multiplier,
            'imp_count'          => max(1, $screen->inventory?->effective_screen_count ?? 1) * $multiplier,
            'deal_type'          => $data['deal_type'] ?? 'direct',
            'proof_url'          => $data['proof_url'] ?? null,
            'source'             => 'adtrue_player',
        ]);

        return response()->json([
            'id'         => $log->id,
            'imp_count'  => $log->imp_count,
            'multiplier' => $log->multiplier_applied,
        ], 201);
    }
}
