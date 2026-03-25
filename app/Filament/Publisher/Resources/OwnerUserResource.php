<?php

namespace App\Filament\Publisher\Resources;

use App\Filament\Publisher\Resources\OwnerUserResource\Pages;
use App\Models\OwnerUser;
use App\Models\User;
use App\Services\TenantPermission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OwnerUserResource extends Resource
{
    protected static ?string $model = OwnerUser::class;


    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Team Members';
    protected static ?string $modelLabel      = 'Team Member';
    protected static ?string $pluralModelLabel = 'Team Members';
    protected static ?int    $navigationSort  = 90;

    // ── Chỉ hiển thị nếu user có quyền manage_users ──────────────────────────

    public static function canViewAny(): bool
    {
        return TenantPermission::check('manage_users')
            || TenantPermission::check('view_inventory'); // owner & manager thấy, chỉ owner mới edit
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (! $user) return false;
        if ($user->hasRole('super_admin')) return true;

        return \App\Models\OwnerUser::where('owner_id', $user->current_owner_id)
            ->where('user_id', $user->id)
            ->where('role', 'owner')
            ->exists();
    }
    // ── Chỉ scope data theo current_owner_id ─────────────────────────────────

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('owner_id', auth()->user()->current_owner_id)
            ->with('user');
    }

    // ── Form ─────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Invite Team Member')->schema([

                Forms\Components\TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->placeholder('user@example.com')
                    ->helperText('Nếu email chưa có tài khoản, hệ thống sẽ tự tạo và gửi invite.')
                    ->dehydrated(false)   // không map trực tiếp vào OwnerUser
                    ->visibleOn('create'),

                Forms\Components\Select::make('role')
                    ->label('Role')
                    ->options(OwnerUser::roleOptions())
                    ->required()
                    ->default('read_only')
                    ->helperText(fn(?string $state) => static::roleDescription($state))
                    ->live(),

                Forms\Components\CheckboxList::make('allowed_network_ids')
                    ->label('Restrict to Networks (optional)')
                    ->helperText('Để trống = truy cập tất cả networks. Chỉ áp dụng cho Scheduler và Read only.')
                    ->options(fn() => \App\Models\Network::where('owner_id', auth()->user()->current_owner_id)
                        ->pluck('name', 'id'))
                    ->columns(2)
                    ->visible(fn(Forms\Get $get) => in_array($get('role'), ['scheduler', 'read_only'])),

            ])->columns(1),
        ]);
    }

    // ── Table ─────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        $user = auth()->user();
        $canManage = $user?->hasRole('super_admin') || \App\Models\OwnerUser::where('owner_id', $user?->current_owner_id)
                ->where('user_id', $user?->id)
                ->where('role', 'owner')
                ->exists();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\BadgeColumn::make('role')
                    ->label('Role')
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

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->date()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible($canManage)
                    ->before(function (OwnerUser $record) {
                        // Không cho sửa role owner khác nếu không phải super_admin
                        if ($record->role === 'owner' && ! auth()->user()->hasRole('super_admin')) {
                            Notification::make()
                                ->title('Không thể sửa quyền Owner')
                                ->danger()->send();
                            $this->halt();
                        }
                    }),

                Tables\Actions\Action::make('remove')
                    ->label('Remove')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Remove team member?')
                    ->modalDescription('User sẽ không còn truy cập được vào owner này.')
                    ->visible($canManage)
                    ->action(function (OwnerUser $record) {
                        if ($record->user_id === auth()->id()) {
                            Notification::make()->title('Không thể tự xoá chính mình')->danger()->send();
                            return;
                        }
                        if ($record->role === 'owner' && ! auth()->user()->hasRole('super_admin')) {
                            Notification::make()->title('Không thể xoá Owner khác')->danger()->send();
                            return;
                        }
                        $record->delete();
                        Notification::make()->title('Đã xoá khỏi team')->success()->send();
                    }),
            ])
            ->bulkActions([])  // không cho bulk delete để tránh nhầm
            ->emptyStateHeading('Chưa có team member')
            ->emptyStateDescription('Mời thành viên vào owner này để cộng tác.')
            ->emptyStateIcon('heroicon-o-users');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOwnerUsers::route('/'),
            'create' => Pages\CreateOwnerUser::route('/create'),
            'edit'   => Pages\EditOwnerUser::route('/{record}/edit'),
        ];
    }

    // ── Role description helper ───────────────────────────────────────────────

    private static function roleDescription(?string $role): string
    {
        return match ($role) {
            'owner'          => 'Toàn quyền: quản lý team, inventory, pricing, reports.',
            'manager'        => 'Quản lý inventory, pricing và xem reports. Không quản lý được users.',
            'scheduler'      => 'Thêm/sửa screens và import inventory. Không xem được reports.',
            'read_only'      => 'Chỉ xem screens và sites, không chỉnh sửa.',
            'reporting_only' => 'Chỉ xem và export reports, không thấy inventory.',
            'sales_manager'  => 'Xem inventory và sales dashboard. Không chỉnh sửa.',
            default          => '',
        };
    }


}
