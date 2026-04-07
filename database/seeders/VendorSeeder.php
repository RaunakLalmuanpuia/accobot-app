<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class VendorSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all()->keyBy('name');

        $data = [
            'Tili' => [
                ['name' => 'Office Supplies Co.',  'email' => 'supplies@officesupplies.com', 'phone' => '555-0201'],
                ['name' => 'Tech Hardware Ltd.',   'email' => 'sales@techhardware.com',      'phone' => '555-0202'],
                ['name' => 'Cloud Services Inc.',  'email' => null,                          'phone' => '555-0203'],
            ],
            'Awab' => [
                ['name' => 'Marketing Agency',     'email' => 'hello@marketingagency.com',   'phone' => null],
                ['name' => 'Printing Works',       'email' => 'orders@printingworks.com',    'phone' => '555-0211'],
            ],
            'Eightsis' => [
                ['name' => 'Logistics Partners',   'email' => 'ops@logisticspartners.com',   'phone' => '555-0301'],
                ['name' => 'Design Studio',        'email' => 'hello@designstudio.com',      'phone' => null],
            ],
        ];

        foreach ($data as $tenantName => $rows) {
            $tenant = $tenants->get($tenantName);
            if (! $tenant) continue;

            foreach ($rows as $row) {
                $tenant->vendors()->create($row);
            }
        }
    }
}
