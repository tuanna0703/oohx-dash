<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Owner;
use App\Models\OwnerUser;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;


    protected static ?string $navigationGroup = 'Organizations';
    protected static ?string $navigationLabel = 'Users';
    protected static ?int    $navigationSort  = 5;

    // ── Form ─────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Account Info')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->unique(User::class, 'email', ignoreRecord: true)
                        ->maxLength(255),

                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->dehydrateStateUsing(fn($state) => filled($state) ? Hash::make($state) : null)
                        ->dehydrated(fn($state) => filled($state))
                        ->required(fn(string $operation) => $operation === 'create')
                        ->helperText(fn(string $operation) => $operation === 'edit'
                            ? 'Để trống nếu không muốn đổi mật khẩu'
                            : null)
                        ->maxLength(255),

                    Forms\Components\TextInput::make('password_confirmation')
                        ->password()
                        ->revealable()
                        ->same('password')
                        ->required(fn(string $operation) => $operation === 'create')
                        ->dehydrated(false)
                        ->label('Confirm Password'),
                ]),

            Forms\Components\Section::make('System Role')
                ->description('Role Spatie — xác định panel nào user được truy cập.')
                ->schema([
                    Forms\Components\Select::make('roles')
                        ->label('System Role')
                        ->options(fn() => Role::pluck('name', 'name'))
                        ->multiple(false)
                        ->required()
                        ->default('publisher')
                        ->helperText('super_admin → /admin | publisher → /publisher')
                        ->live()
                        // Lấy role hiện tại của user
                        ->afterStateHydrated(function (Forms\Components\Select $component, ?User $record) {
                            if ($record) {
                                $component->state($record->roles->first()?->name);
                            }
                        })
                        // Không dehydrate trực tiếp vào user table — xử lý trong saveRelationships
                        ->dehydrated(false),
                ]),

            Forms\Components\Section::make('Publisher Access')
                ->description('Gán user vào Media Owner để họ đăng nhập được Publisher panel.')
                ->schema([
                    Forms\Components\Select::make('owner_id')
                        ->label('Media Owner')
                        ->options(Owner::active()->pluck('name', 'id'))
                        ->searchable()
                        ->nullable()
                        ->helperText('Chọn owner để tự động tạo OwnerUser record.')
                        ->live()
                        ->afterStateHydrated(function (Forms\Components\Select $component, ?User $record) {
                            if ($record) {
                                $component->state($record->current_owner_id);
                            }
                        })
                        ->dehydrated(false),

                    Forms\Components\Select::make('owner_role')
                        ->label('Role trong Owner')
                        ->options(OwnerUser::ROLES)
                        ->default('read_only')
                        ->required(fn(Forms\Get $get) => filled($get('owner_id')))
                        ->visible(fn(Forms\Get $get) => filled($get('owner_id')))
                        ->helperText(fn(Forms\Get $get) => match ($get('owner_role')) {
                            'owner'          => '👑 Toàn quyền trong owner này.',
                            'manager'        => '🔧 Quản lý inventory & pricing.',
                            'scheduler'      => '📅 Import & quản lý screens.',
                            'read_only'      => '👁 Chỉ xem.',
                            'reporting_only' => '📊 Chỉ xem reports.',
                            'sales_manager'  => '💼 Xem inventory & sales.',
                            default          => '',
                        })
                        ->live()
                        ->dehydrated(false),
                ]),

        ]);
    }

    // ── Table ─────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('system_role')
                    ->label('System Role')
                    ->badge()
                    ->getStateUsing(fn(User $record) => $record->roles->first()?->name ?? '—')
                    ->color(fn(string $state) => match($state) {
                        'super_admin' => 'danger',
                        'publisher'   => 'primary',
                        default       => 'gray',
                    }),

                Tables\Columns\TextColumn::make('current_owner_name')
                    ->label('Current Owner')
                    ->getStateUsing(fn(User $record) => $record->currentOwner?->name ?? '—')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('owner_role')
                    ->label('Owner Role')
                    ->badge()
                    ->getStateUsing(fn(User $record) => $record->ownerUsers->first()?->role ?? null)
                    ->formatStateUsing(fn($state) => $state ? (OwnerUser::ROLES[$state] ?? $state) : '—')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('System Role')
                    ->options(fn() => Role::pluck('name', 'name'))
                    ->query(fn($query, $data) => $data['value']
                        ? $query->role($data['value'])
                        : $query),

                Tables\Filters\SelectFilter::make('owner')
                    ->label('Owner')
                    ->options(Owner::active()->pluck('name', 'id'))
                    ->query(fn($query, $data) => $data['value']
                        ? $query->where('current_owner_id', $data['value'])
                        : $query),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('impersonate')
                    ->label('Login As')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Login as user này?')
                    ->modalDescription(fn(User $record) => "Bạn sẽ đăng nhập với tài khoản {$record->email}.")
                    ->action(function (User $record) {
                        auth()->login($record);
                        $panel = $record->hasRole('super_admin') ? '/admin' : '/publisher';
                        return redirect($panel);
                    }),

                Tables\Actions\Action::make('reset_password')
                    ->label('Reset Password')
                    ->icon('heroicon-o-key')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        \Password::broker()->sendResetLink(['email' => $record->email]);
                        Notification::make()
                            ->title("Đã gửi email reset password đến {$record->email}")
                            ->success()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // ── Pages ─────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
