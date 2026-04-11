<?php

namespace App\Filament\Widgets;

use App\Models\OwnerUser;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class PermissionStatsWidget extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected function getStats(): array
    {
        $totalUsers = DB::table('users')->count();

        $systemRoleStats = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->select('roles.name', DB::raw('COUNT(*) as count'))
            ->where('model_type', 'App\\Models\\User')
            ->groupBy('roles.name')
            ->pluck('count', 'name')
            ->toArray();

        $totalMemberships = DB::table('owner_users')->count();

        return [
            Stat::make('Total Users', $totalUsers)
                ->icon('heroicon-o-users')
                ->color('primary'),

            Stat::make('super_admin', $systemRoleStats['super_admin'] ?? 0)
                ->icon('heroicon-o-shield-check')
                ->color('danger'),

            Stat::make('publisher', $systemRoleStats['publisher'] ?? 0)
                ->icon('heroicon-o-building-office')
                ->color('primary'),

            Stat::make('Owner Memberships', $totalMemberships)
                ->icon('heroicon-o-user-group')
                ->color('success'),
        ];
    }
}
