<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ScreenRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    public function __construct(private ScreenRegistryService $registry) {}

    public function heartbeat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'screen_uuid'    => 'required|uuid',
            'player_version' => 'nullable|string',
        ]);
        $screen = $this->registry->heartbeat($data['screen_uuid']);
        if (!$screen) return response()->json(['message'=>'Screen not found'],404);
        if (!empty($data['player_version'])) $screen->update(['player_version'=>$data['player_version']]);
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
        $log = $this->registry->logImpression($data);
        return response()->json([
            'id'=>$log->id,'imp_count'=>$log->imp_count,'multiplier'=>$log->multiplier_applied,
        ],201);
    }
}
