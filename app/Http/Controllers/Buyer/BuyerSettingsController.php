<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class BuyerSettingsController extends Controller
{
    public function index(Request $request): View
    {
        return view('buyer.dashboard.settings', [
            'user' => $request->user(),
            'org'  => $request->user()->currentOrganization,
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $request->user()->id],
        ]);

        $request->user()->update($data);

        return back()->with('success', 'Thông tin cá nhân đã được cập nhật');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update([
            'password' => Hash::make($request->input('password')),
        ]);

        return back()->with('success', 'Mật khẩu đã được đổi');
    }

    public function updateOrganization(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'billing_phone' => ['nullable', 'string', 'max:30'],
            'tax_id'        => ['nullable', 'string', 'max:50'],
            'website'       => ['nullable', 'url', 'max:255'],
        ]);

        $request->user()->currentOrganization->update($data);

        return back()->with('success', 'Thông tin tổ chức đã được cập nhật');
    }
}
