<?php

namespace Database\Seeders;

use App\Models\ApiClient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ApiClientSeeder extends Seeder
{
    public function run(): void
    {
        ApiClient::updateOrCreate(
            ['client_id' => 'oohx_marketplace'],
            [
                'client_secret' => Hash::make(env('OOHX_CLIENT_SECRET', 'bonbon@2022')),
                'name'          => 'OOHX Marketplace',
                'scopes'        => ['inventory', 'booking', 'reporting'],
                'active'        => true,
            ]
        );
    }
}
