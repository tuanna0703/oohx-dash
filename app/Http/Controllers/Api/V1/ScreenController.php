<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Screen;
use App\Services\ScreenRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ScreenController extends Controller
{
    public function __construct(private ScreenRegistryService $registry) {}

    public function index(): JsonResponse
    {
        $screens = QueryBuilder::for(Screen::class)
            ->allowedFilters([
                'active','status','player_type',
                AllowedFilter::exact('site_id'),
                AllowedFilter::exact('owner_id'),
                AllowedFilter::callback('venue_type', fn($q,$v) =>
                    $q->whereHas('inventory',fn($iq)=>$iq->where('venue_type',$v))
                ),
                AllowedFilter::callback('programmatic', fn($q,$v) =>
                    $q->whereHas('inventory',fn($iq)=>$iq->where('programmatic_enabled',(bool)$v))
                ),
            ])
            ->allowedSorts(['name','created_at','last_heartbeat_at'])
            ->allowedIncludes(['spec','inventory','site','multipliers'])
            ->paginate(20);
        return response()->json($screens);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'owner_id'          => 'required|ulid|exists:owners,id',
            'site_external_id'  => 'required|string',
            'external_id'       => 'required|string|max:75',
            'name'              => 'required|string|max:199',
            'description'       => 'nullable|string',
            'internal_notes'    => 'nullable|string',
            'player_type'       => 'sometimes|in:adtrue_android,adtrue_webview,third_party,vast_only',
            'uuid'              => 'nullable|uuid',
            'spec'              => 'nullable|array',
            'spec.width_px'     => 'required_with:spec|integer|min:1',
            'spec.height_px'    => 'required_with:spec|integer|min:1',
            'inventory'         => 'nullable|array',
            'multiplier_string' => 'nullable|string',
        ]);
        return response()->json(
            $this->registry->registerScreen($data, $data['owner_id']), 201
        );
    }

    public function show(Screen $screen): JsonResponse
    {
        return response()->json(
            $screen->load(['spec','inventory','site','externalIds'])->append(['is_online'])
        );
    }

    public function update(Request $request, Screen $screen): JsonResponse
    {
        $data = $request->validate([
            'name'           => 'sometimes|string|max:199',
            'description'    => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'active'         => 'sometimes|boolean',
            'player_type'    => 'sometimes|in:adtrue_android,adtrue_webview,third_party,vast_only',
            'player_version' => 'nullable|string',
            'spec'           => 'nullable|array',
            'inventory'      => 'nullable|array',
        ]);
        $screen->update(collect($data)->except(['spec','inventory'])->toArray());
        if (!empty($data['spec']))      $this->registry->saveSpec($screen,$data['spec']);
        if (!empty($data['inventory'])) $this->registry->saveInventory($screen,$data['inventory']);
        return response()->json($screen->load(['spec','inventory']));
    }

    public function destroy(Screen $screen): JsonResponse
    {
        $screen->delete();
        return response()->json(null,204);
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
            'multiplier_string' => 'required_without:multipliers|nullable|string',
            'multipliers'       => 'required_without:multiplier_string|nullable|array',
        ]);
        if (!empty($data['multiplier_string']))
            $this->registry->saveMultipliersFromString($screen,$data['multiplier_string']);
        else
            $this->registry->saveMultipliers($screen,$data['multipliers']);
        return response()->json(['message'=>'Multipliers updated']);
    }

    public function toggleProgrammatic(Screen $screen): JsonResponse
    {
        $inv = $screen->inventory;
        if (!$inv) return response()->json(['message'=>'No inventory settings'],422);
        $inv->update(['programmatic_enabled'=>!$inv->programmatic_enabled]);
        return response()->json(['programmatic_enabled'=>$inv->programmatic_enabled]);
    }
}
