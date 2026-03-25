<?php

namespace App\Filament\Publisher\Resources\OwnerUserResource\Pages;

use App\Filament\Publisher\Resources\OwnerUserResource;
use App\Models\OwnerUser;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ListOwnerUsers extends ListRecords
{
    protected static string $resource = OwnerUserResource::class;

    protected function getHeaderActions(): array
    {
        $user      = auth()->user();
        $canManage = $user?->hasRole('super_admin') || OwnerUser::where('owner_id', $user?->current_owner_id)
                ->where('user_id', $user?->id)
                ->where('role', 'owner')
                ->exists();

        if (! $canManage) return [];

        return [
            Actions\Action::make('invite_existing')
                ->label('Invite User')
                ->icon('heroicon-o-user-plus')
                ->color('primary')
                ->form([
                    Forms\Components\TextInput::make('email')
                        ->label('Email address')
                        ->email()
                        ->required()
                        ->placeholder('user@example.com')
                        ->helperText('Nhập email đã có tài khoản hoặc sẽ được tạo mới.'),

                    Forms\Components\Select::make('role')
                        ->label('Role')
                        ->options(OwnerUser::ROLES)
                        ->default('read_only')
                        ->required()
                        ->live()
                        ->helperText(fn(Forms\Get $get) => match ($get('role')) {
                            'owner'          => '👑 Toàn quyền: quản lý team, inventory, pricing, reports.',
                            'manager'        => '🔧 Quản lý inventory & pricing, xem reports.',
                            'scheduler'      => '📅 Thêm/sửa screens, import file.',
                            'read_only'      => '👁 Chỉ xem screens & sites.',
                            'reporting_only' => '📊 Chỉ xem & export reports.',
                            'sales_manager'  => '💼 Xem inventory & sales dashboard.',
                            default          => '',
                        }),

                    Forms\Components\CheckboxList::make('allowed_network_ids')
                        ->label('Giới hạn Networks (để trống = tất cả)')
                        ->options(fn() => \App\Models\Network::where(
                            'owner_id', auth()->user()->current_owner_id
                        )->pluck('name', 'id'))
                        ->columns(2)
                        ->visible(fn(Forms\Get $get) => in_array($get('role'), ['scheduler', 'read_only'])),
                ])
                ->action(function (array $data): void {
                    $ownerId = auth()->user()->current_owner_id;
                    $email   = trim($data['email']);

                    $user = User::firstOrCreate(
                        ['email' => $email],
                        [
                            'name'     => Str::before($email, '@'),
                            'password' => Hash::make(Str::random(16)),
                        ]
                    );

                    if (! $user->hasRole('publisher')) {
                        $user->assignRole('publisher');
                    }

                    $existing = OwnerUser::where('owner_id', $ownerId)
                        ->where('user_id', $user->id)
                        ->first();

                    if ($existing) {
                        Notification::make()
                            ->title("{$email} đã là thành viên")
                            ->warning()->send();
                        return;
                    }

                    OwnerUser::create([
                        'owner_id'            => $ownerId,
                        'user_id'             => $user->id,
                        'role'                => $data['role'],
                        'allowed_network_ids' => $data['allowed_network_ids'] ?? null,
                    ]);

                    if (! $user->current_owner_id) {
                        $user->update(['current_owner_id' => $ownerId]);
                    }

                    Notification::make()
                        ->title("✅ Đã thêm {$email}")
                        ->body('Role: ' . (OwnerUser::ROLES[$data['role']] ?? $data['role']))
                        ->success()->send();
                }),

            Actions\Action::make('create_new_user')
                ->label('Create New User')
                ->icon('heroicon-o-user-circle')
                ->color('gray')
                ->form([
                    Forms\Components\TextInput::make('name')
                        ->label('Full Name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->unique(User::class, 'email')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('password')
                        ->label('Password')
                        ->password()
                        ->revealable()
                        ->required()
                        ->minLength(8),

                    Forms\Components\Select::make('role')
                        ->label('Role')
                        ->options(OwnerUser::ROLES)
                        ->default('read_only')
                        ->required()
                        ->live()
                        ->helperText(fn(Forms\Get $get) => match ($get('role')) {
                            'owner'          => '👑 Toàn quyền: quản lý team, inventory, pricing, reports.',
                            'manager'        => '🔧 Quản lý inventory & pricing, xem reports.',
                            'scheduler'      => '📅 Thêm/sửa screens, import file.',
                            'read_only'      => '👁 Chỉ xem screens & sites.',
                            'reporting_only' => '📊 Chỉ xem & export reports.',
                            'sales_manager'  => '💼 Xem inventory & sales dashboard.',
                            default          => '',
                        }),

                    Forms\Components\CheckboxList::make('allowed_network_ids')
                        ->label('Giới hạn Networks (để trống = tất cả)')
                        ->options(fn() => \App\Models\Network::where(
                            'owner_id', auth()->user()->current_owner_id
                        )->pluck('name', 'id'))
                        ->columns(2)
                        ->visible(fn(Forms\Get $get) => in_array($get('role'), ['scheduler', 'read_only'])),
                ])
                ->action(function (array $data): void {
                    $ownerId = auth()->user()->current_owner_id;

                    $user = User::create([
                        'name'             => $data['name'],
                        'email'            => $data['email'],
                        'password'         => Hash::make($data['password']),
                        'current_owner_id' => $ownerId,
                    ]);

                    $user->assignRole('publisher');

                    OwnerUser::create([
                        'owner_id'            => $ownerId,
                        'user_id'             => $user->id,
                        'role'                => $data['role'],
                        'allowed_network_ids' => $data['allowed_network_ids'] ?? null,
                    ]);

                    Notification::make()
                        ->title("✅ Đã tạo user {$data['email']}")
                        ->body('Role: ' . (OwnerUser::ROLES[$data['role']] ?? $data['role']))
                        ->success()->send();
                }),
        ];
    }
}
