<?php

namespace App\Http\Controllers;

use App\Models\OrganizationUser;
use App\Models\OwnerUser;
use App\Models\UserInvitation;
use App\Services\UserInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function __construct(
        private UserInvitationService $service,
    ) {}

    /** GET /invitations/{token}/accept — render accept form */
    public function show(string $token): View
    {
        $invitation = UserInvitation::where('token', $token)->first();

        if (! $invitation) {
            return view('invitations.error', ['message' => 'Lời mời không tồn tại hoặc đã bị thu hồi.'], 410);
        }

        if ($invitation->isAccepted()) {
            return view('invitations.error', ['message' => 'Lời mời đã được sử dụng. Vui lòng đăng nhập trực tiếp.'], 410);
        }

        if ($invitation->isExpired()) {
            return view('invitations.error', ['message' => 'Lời mời đã hết hạn. Liên hệ quản trị viên để được mời lại.'], 410);
        }

        $tenant = $invitation->tenant();
        $roleLabel = match ($invitation->tenant_type) {
            UserInvitation::TENANT_OWNER        => OwnerUser::ROLES[$invitation->role] ?? $invitation->role,
            UserInvitation::TENANT_ORGANIZATION => OrganizationUser::ROLES[$invitation->role] ?? $invitation->role,
            default                             => $invitation->role,
        };

        $existingUser = \App\Models\User::where('email', $invitation->email)->first();

        return view('invitations.accept', [
            'invitation'    => $invitation,
            'tenant'        => $tenant,
            'roleLabel'     => $roleLabel,
            'isExistingUser' => (bool) $existingUser,
        ]);
    }

    /** POST /invitations/{token}/accept — chấp nhận và login */
    public function store(Request $request, string $token): RedirectResponse
    {
        $existingUser = null;
        $invitation = UserInvitation::where('token', $token)->first();
        if ($invitation) {
            $existingUser = \App\Models\User::where('email', $invitation->email)->first();
        }

        $rules = [
            'name'     => $existingUser ? 'nullable|string|max:255' : 'required|string|max:255',
            'password' => $existingUser ? 'nullable|string|min:8|confirmed' : 'required|string|min:8|confirmed',
        ];
        $data = $request->validate($rules);

        try {
            $user = $this->service->accept(
                $token,
                $data['name'] ?? '',
                $data['password'] ?? '',
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['invitation' => $e->getMessage()]);
        }

        Auth::login($user);

        $redirectPath = match ($invitation?->tenant_type) {
            UserInvitation::TENANT_OWNER        => '/publisher',
            UserInvitation::TENANT_ORGANIZATION => '/buyer',
            default                             => '/admin',
        };

        return redirect($redirectPath);
    }
}
