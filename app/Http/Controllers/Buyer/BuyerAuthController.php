<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class BuyerAuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()->organizations()->exists()) {
            return redirect('/my');
        }
        return view('buyer.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email hoặc mật khẩu không đúng.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = Auth::user();

        // Set current_organization_id if user has organizations
        if (! $user->current_organization_id && $user->organizations()->exists()) {
            $user->update([
                'current_organization_id' => $user->organizations()->first()->id,
            ]);
        }

        return redirect()->intended(route('buyer.dashboard'));
    }

    public function showRegister(): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()->organizations()->exists()) {
            return redirect('/my');
        }
        return view('buyer.auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'email'             => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'          => ['required', 'confirmed', Password::min(8)],
            'organization_name' => ['required', 'string', 'max:255'],
            'organization_type' => ['required', 'in:agency,client,brand'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $org = Organization::create([
                'name' => $data['organization_name'],
                'slug' => Str::slug($data['organization_name']) . '-' . Str::random(4),
                'type' => $data['organization_type'],
            ]);

            OrganizationUser::create([
                'organization_id' => $org->id,
                'user_id'         => $user->id,
                'role'            => OrganizationUser::ROLE_ADMIN,
            ]);

            $user->update(['current_organization_id' => $org->id]);

            return $user;
        });

        Auth::login($user);

        return redirect()->route('buyer.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('fp.index');
    }
}
