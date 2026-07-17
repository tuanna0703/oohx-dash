<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicReflectionRequest;
use App\Services\PublicReflectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Tiếp nhận và công bố phản ánh của tổ chức xã hội (yêu cầu review mục 3).
 */
class PublicReflectionController extends Controller
{
    public function __construct(private readonly PublicReflectionService $service) {}

    public function create(): View
    {
        return view('frontpage.reflections.create');
    }

    public function store(StorePublicReflectionRequest $request): RedirectResponse
    {
        $reflection = $this->service->record($request->validated(), $request->ip());

        return redirect()
            ->route('fp.reflections.create')
            ->with('reflection_code', $reflection->code);
    }

    public function index(): View
    {
        return view('frontpage.reflections.index', [
            'reflections' => $this->service->published(),
        ]);
    }
}
