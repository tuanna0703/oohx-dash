<?php

namespace App\Filament\Resources\OwnerResource\RelationManagers;

use App\Models\OwnerUser;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class OwnerUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';  // Owner->users() hasMany OwnerUser

    protected static ?string $title = 'Publisher Users';
    protected static ?string $icon  = 'heroicon-o-users';

    // ── Form ─────────────────────────────────────────────────────────────────

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([

                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->placeholder('user@example.com')
                    ->maxLength(255)
                    ->dehydrated(false)   // không map sang OwnerUser column
                    ->visibleOn('create')
                    ->columnSpan(2),

                Forms\Components\Select::make('role')
                    ->label('Role')
                    ->options(OwnerUser::ROLES)
                    ->default('read_only')
                    ->required()
                    ->live()
                    ->columnSpan(1),

                Forms\Components\Placeholder::make('role_desc')
                    ->label('Quyền hạn')
                    ->content(fn(Forms\Get $get) => match ($get('role')) {
                        'owner'          => '👑 Toàn quyền — quản lý team, inventory, pricing, reports.',
                        'manager'        => '🔧 Quản lý inventory & pricing, xem reports. Không invite user.',
                        'scheduler'      => '📅 Thêm/sửa screens, import file. Không xem reports.',
                        'read_only'      => '👁 Chỉ xem screens & sites.',
                        'reporting_only' => '📊 Chỉ xem & export reports.',
                        'sales_manager'  => '💼 Xem inventory & sales dashboard.',
                        default          => '',
                    })
                    ->columnSpan(1),

                Forms\Components\CheckboxList::make('allowed_network_ids')
                    ->label('Giới hạn Networks (để trống = tất cả)')
                    ->options(fn() => \App\Models\Network::where('owner_id', $this->getOwnerRecord()->id)
                        ->pluck('name', 'id'))
                    ->columns(2)
                    ->columnSpan(2)
                    ->visible(fn(Forms\Get $get) => in_array($get('role'), ['scheduler', 'read_only'])),

            ]),
        ]);
    }

    // ── Table ─────────────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user.email')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->placeholder('(no name)'),

                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\BadgeColumn::make('role')
                    ->formatStateUsing(fn($state) => OwnerUser::ROLES[$state] ?? $state)
                    ->colors([
                        'danger'  => 'owner',
                        'primary' => 'manager',
                        'warning' => 'scheduler',
                        'gray'    => 'read_only',
                        'info'    => 'reporting_only',
                        'success' => 'sales_manager',
                    ]),

                Tables\Columns\TextColumn::make('allowed_network_ids')
                    ->label('Networks')
                    ->formatStateUsing(fn($state) => $state ? count($state) . ' restricted' : 'All')
                    ->badge()
                    ->color(fn($state) => $state ? 'warning' : 'success'),

                Tables\Columns\TextColumn::make('user.last_login_at')
                    ->label('Last Login')
                    ->dateTime()
                    ->placeholder('Never')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->date()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('invite')
                    ->label('Invite User')
                    ->icon('heroicon-o-user-plus')
                    ->color('primary')
                    ->form([
                        Forms\Components\TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->placeholder('user@example.com'),

                        Forms\Components\Select::make('role')
                            ->label('Role')
                            ->options(OwnerUser::ROLES)
                            ->default('read_only')
                            ->required()
                            ->live(),

                        Forms\Components\Placeholder::make('role_desc')
                            ->label('Quyền hạn')
                            ->content(fn(Forms\Get $get) => match ($get('role')) {
                                'owner'          => '👑 Toàn quyền — quản lý team, inventory, pricing, reports.',
                                'manager'        => '🔧 Quản lý inventory & pricing, xem reports. Không invite user.',
                                'scheduler'      => '📅 Thêm/sửa screens, import file. Không xem reports.',
                                'read_only'      => '👁 Chỉ xem screens & sites.',
                                'reporting_only' => '📊 Chỉ xem & export reports.',
                                'sales_manager'  => '💼 Xem inventory & sales dashboard.',
                                default          => '',
                            }),

                        Forms\Components\CheckboxList::make('allowed_network_ids')
                            ->label('Giới hạn Networks (để trống = tất cả)')
                            ->options(fn() => \App\Models\Network::where('owner_id', $this->getOwnerRecord()->id)
                                ->pluck('name', 'id'))
                            ->columns(2)
                            ->visible(fn(Forms\Get $get) => in_array($get('role'), ['scheduler', 'read_only'])),
                    ])
                    ->action(function (array $data): void {
                        $ownerId = $this->getOwnerRecord()->id;
                        $email   = trim($data['email']);

                        // Tìm hoặc tạo user
                        $user = User::firstOrCreate(
                            ['email' => $email],
                            [
                                'name'     => Str::before($email, '@'),
                                'password' => bcrypt(Str::random(16)),
                            ]
                        );

                        // Đã là member?
                        if (OwnerUser::where('owner_id', $ownerId)->where('user_id', $user->id)->exists()) {
                            Notification::make()
                                ->title("{$email} đã là thành viên của owner này")
                                ->warning()->send();
                            return;
                        }

                        OwnerUser::create([
                            'owner_id'            => $ownerId,
                            'user_id'             => $user->id,
                            'role'                => $data['role'],
                            'allowed_network_ids' => $data['allowed_network_ids'] ?? null,
                        ]);

                        // Gán current_owner_id nếu user chưa có
                        if (! $user->current_owner_id) {
                            $user->update(['current_owner_id' => $ownerId]);
                        }

                        Notification::make()
                            ->title("✅ Đã thêm {$email} với role " . (OwnerUser::ROLES[$data['role']] ?? $data['role']))
                            ->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('edit_role')
                    ->label('Edit Role')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->form(fn(OwnerUser $record) => [
                        Forms\Components\Select::make('role')
                            ->label('Role')
                            ->options(OwnerUser::ROLES)
                            ->default($record->role)
                            ->required()
                            ->live(),

                        Forms\Components\Placeholder::make('role_desc')
                            ->label('Quyền hạn')
                            ->content(fn(Forms\Get $get) => match ($get('role')) {
                                'owner'          => '👑 Toàn quyền — quản lý team, inventory, pricing, reports.',
                                'manager'        => '🔧 Quản lý inventory & pricing, xem reports.',
                                'scheduler'      => '📅 Thêm/sửa screens, import file.',
                                'read_only'      => '👁 Chỉ xem screens & sites.',
                                'reporting_only' => '📊 Chỉ xem & export reports.',
                                'sales_manager'  => '💼 Xem inventory & sales dashboard.',
                                default          => '',
                            }),

                        Forms\Components\CheckboxList::make('allowed_network_ids')
                            ->label('Giới hạn Networks')
                            ->options(fn() => \App\Models\Network::where('owner_id', $this->getOwnerRecord()->id)
                                ->pluck('name', 'id'))
                            ->default($record->allowed_network_ids)
                            ->columns(2)
                            ->visible(fn(Forms\Get $get) => in_array($get('role'), ['scheduler', 'read_only'])),
                    ])
                    ->action(function (OwnerUser $record, array $data): void {
                        $record->update([
                            'role'                => $data['role'],
                            'allowed_network_ids' => $data['allowed_network_ids'] ?? null,
                        ]);
                        Notification::make()
                            ->title('Role đã được cập nhật')
                            ->success()->send();
                    }),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('reset_password')
                        ->label('Reset Password')
                        ->icon('heroicon-o-key')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Reset mật khẩu?')
                        ->modalDescription(fn(OwnerUser $record) => "Gửi email reset password đến {$record->user?->email}.")
                        ->action(function (OwnerUser $record): void {
                            $user = $record->user;
                            if ($user) {
                                \Password::broker()->sendResetLink(['email' => $user->email]);
                                Notification::make()
                                    ->title("Đã gửi email reset password đến {$user->email}")
                                    ->success()->send();
                            }
                        }),

                    Tables\Actions\Action::make('remove')
                        ->label('Remove')
                        ->icon('heroicon-o-user-minus')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Remove khỏi Owner?')
                        ->modalDescription(fn(OwnerUser $record) => "User {$record->user?->email} sẽ mất quyền truy cập owner này.")
                        ->action(function (OwnerUser $record): void {
                            $record->delete();
                            Notification::make()->title('Đã xoá khỏi owner')->success()->send();
                        }),
                ]),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Chưa có user nào')
            ->emptyStateDescription('Nhấn "Invite User" để thêm thành viên.')
            ->emptyStateIcon('heroicon-o-users');
    }


}
