<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Services\ScreenRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(private ScreenRegistryService $registry) {}

    public function hivestackBulk(Request $request, Owner $owner): JsonResponse
    {
        $request->validate(['file'=>'required|file|mimes:xlsx|max:10240']);
        $path    = $request->file('file')->store('imports/hivestack');
        $results = $this->registry->importHivestackXlsx(storage_path('app/'.$path), $owner->id);
        return response()->json(['message'=>'Import completed','results'=>$results]);
    }
}
