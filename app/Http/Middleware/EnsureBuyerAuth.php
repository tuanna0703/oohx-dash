<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBuyerAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect('/login');
        }

        // User must have at least one organization (buyer account)
        if (! $request->user()->organizations()->exists()) {
            // User is logged in but not a buyer — show register to create org
            return redirect('/register')
                ->withErrors(['organization' => 'Bạn cần tạo tổ chức để sử dụng tính năng này.']);
        }

        // Auto-set current_organization_id if missing
        if (! $request->user()->current_organization_id) {
            $request->user()->update([
                'current_organization_id' => $request->user()->organizations()->first()->id,
            ]);
        }

        return $next($request);
    }
}
