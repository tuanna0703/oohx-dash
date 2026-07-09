<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Seed Spatie Permission roles dùng làm panel-access gate:
 *  - super_admin → /admin
 *  - publisher   → /publisher
 *  - buyer       → /buyer
 *
 * Idempotent — có thể chạy lại nhiều lần.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['super_admin', 'publisher', 'buyer'] as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }
}
