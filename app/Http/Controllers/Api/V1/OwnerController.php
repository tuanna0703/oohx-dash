<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;

class OwnerController extends Controller
{
    public function index(): JsonResponse
    {
        $owners = QueryBuilder::for(Owner::class)
            ->allowedFilters(['status','type','onboard_method'])
            ->allowedSorts(['name','created_at'])
            ->withCount(['sites','screens'])
            ->paginate(20);
        return response()->json($owners);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'slug'              => 'required|string|unique:owners,slug',
            'type'              => 'required|in:retailer,media_owner,self',
            'onboard_method'    => 'required|in:cms,api,vast,hardware',
            'revenue_share_pct' => 'nullable|numeric|min:0|max:100',
            'billing_info'      => 'nullable|array',
            'notes'             => 'nullable|string',
        ]);
        return response()->json(Owner::create($data), 201);
    }

    public function show(Owner $owner): JsonResponse
    {
        return response()->json(
            $owner->load(['sites','networks'])->append(['total_screens','programmatic_screens'])
        );
    }

    public function update(Request $request, Owner $owner): JsonResponse
    {
        $data = $request->validate([
            'name'              => 'sometimes|string|max:255',
            'status'            => 'sometimes|in:pending,active,suspended',
            'revenue_share_pct' => 'sometimes|numeric|min:0|max:100',
            'onboard_method'    => 'sometimes|in:cms,api,vast,hardware',
            'billing_info'      => 'sometimes|nullable|array',
        ]);
        $owner->update($data);
        return response()->json($owner);
    }

    public function destroy(Owner $owner): JsonResponse
    {
        $owner->delete();
        return response()->json(null, 204);
    }

    public function switchContext(Owner $owner, Request $request): JsonResponse
    {
        if (!$request->user()->switchOwner($owner->id)) {
            return response()->json(['message'=>'Access denied'],403);
        }
        return response()->json(['message'=>'Switched','owner'=>$owner]);
    }

    public function stats(Owner $owner): JsonResponse
    {
        return response()->json([
            'total_screens'        => $owner->screens()->count(),
            'active_screens'       => $owner->screens()->where('active',true)->count(),
            'online_screens'       => $owner->screens()->where('status','online')->count(),
            'programmatic_screens' => $owner->screens()
                ->whereHas('inventory',fn($q)=>$q->where('programmatic_enabled',true))->count(),
            'total_sites'          => $owner->sites()->count(),
            'revenue_last_30d'     => DB::table('impression_logs')
                ->where('owner_id',$owner->id)
                ->where('played_at','>=',now()->subDays(30))->sum('revenue_owner'),
            'impressions_last_30d' => DB::table('impression_logs')
                ->where('owner_id',$owner->id)
                ->where('played_at','>=',now()->subDays(30))->sum('imp_count'),
        ]);
    }
}
