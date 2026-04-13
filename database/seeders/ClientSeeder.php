<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all()->keyBy('name');

        $data = [
            'Tili' => [
                ['name' => 'Rahul Enterprises',   'email' => 'rahul@enterprises.com', 'phone' => '9876543210'],
                ['name' => 'Priya Trading Co.',   'email' => 'priya@trading.com',     'phone' => '9123456780'],
                ['name' => 'Suresh & Sons',        'email' => 'suresh@sons.com',       'phone' => null],
            ],
            'Awab' => [
                ['name' => 'NextGen Tech Pvt Ltd', 'email' => 'hello@nextgen.com',     'phone' => '9988776655'],
                ['name' => 'Meera Exports',        'email' => 'meera@exports.in',      'phone' => '9871234560'],
            ],
            'Eightsis' => [
                ['name' => 'Horizon Retail Ltd.',  'email' => 'contact@horizon.com',   'phone' => '9090909090'],
                ['name' => 'Apex Solutions',       'email' => 'info@apexsolutions.io', 'phone' => null],
            ],
        ];

        foreach ($data as $tenantName => $clients) {
            $tenant = $tenants->get($tenantName);
            if (! $tenant) continue;

            foreach ($clients as $client) {
                \App\Models\Client::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $client['name']],
                    array_merge($client, ['tenant_id' => $tenant->id])
                );
            }
        }
    }
}
