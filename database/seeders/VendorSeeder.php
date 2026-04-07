<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class VendorSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all()->keyBy('name');

        $data = [
            'Beta Finance Group' => [
                ['name' => 'Office Supplies Co.',  'email' => 'supplies@officesupplies.com', 'phone' => '555-0201'],
                ['name' => 'Tech Hardware Ltd.',   'email' => 'sales@techhardware.com',      'phone' => '555-0202'],
                ['name' => 'Cloud Services Inc.',  'email' => null,                          'phone' => '555-0203'],
            ],
            'Delta Retail Co.' => [
                ['name' => 'Marketing Agency',     'email' => 'hello@marketingagency.com',   'phone' => null],
                ['name' => 'Printing Works',       'email' => 'orders@printingworks.com',    'phone' => '555-0211'],
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
