<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;

class SiteController extends Controller
{
    public function index(): JsonResponse
    {
        $sites = QueryBuilder::for(Site::class)
            ->allowedFilters(['status','city','region','country'])
            ->allowedSorts(['name','created_at','city'])
            ->withCount('screens')
            ->paginate(20);
        return response()->json($sites);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'owner_id'    => 'required|ulid|exists:owners,id',
            'external_id' => 'required|string|max:75',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'lat'         => 'nullable|numeric|between:-90,90',
            'lon'         => 'nullable|numeric|between:-180,180',
            'address'     => 'nullable|string',
            'city'        => 'nullable|string',
            'region'      => 'nullable|string',
            'country'     => 'nullable|string|size:2',
        ]);
        return response()->json(Site::create($data), 201);
    }

    public function show(Site $site): JsonResponse
    {
        return response()->json(
            $site->load('screens.spec','screens.inventory')->append('screen_count')
        );
    }

    public function update(Request $request, Site $site): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'lat'         => 'nullable|numeric',
            'lon'         => 'nullable|numeric',
            'address'     => 'nullable|string',
            'city'        => 'nullable|string',
            'region'      => 'nullable|string',
            'status'      => 'sometimes|in:active,paused,closed',
        ]);
        $site->update($data);
        return response()->json($site);
    }

    public function destroy(Site $site): JsonResponse
    {
        $site->delete();
        return response()->json(null,204);
    }
}
