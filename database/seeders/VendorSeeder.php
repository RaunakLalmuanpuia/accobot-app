<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class VendorSeeder extends Seeder
{
    public function run(): void
    {
        $businesses = Business::all()->keyBy('name');

        $vendors = [
            'Alpha Accounting Firm' => [
                ['name' => 'Office Supplies Co.',  'email' => 'supplies@officesupplies.com', 'phone' => '555-0201'],
                ['name' => 'Tech Hardware Ltd.',   'email' => 'sales@techhardware.com',      'phone' => '555-0202'],
                ['name' => 'Cloud Services Inc.',  'email' => null,                          'phone' => '555-0203'],
            ],
            'Beta Finance Group' => [
                ['name' => 'Marketing Agency',     'email' => 'hello@marketingagency.com',   'phone' => null],
                ['name' => 'Printing Works',       'email' => 'orders@printingworks.com',    'phone' => '555-0211'],
            ],
            'Gamma Tax Consultants' => [
                ['name' => 'Adobe Systems',        'email' => 'enterprise@adobe.com',        'phone' => '555-0221'],
                ['name' => 'Font Foundry',         'email' => null,                          'phone' => null],
                ['name' => 'Stock Photo House',    'email' => 'billing@stockphotos.com',     'phone' => '555-0223'],
            ],
        ];

        foreach ($vendors as $businessName => $rows) {
            $business = $businesses->get($businessName);
            if (! $business) continue;

            foreach ($rows as $row) {
                Vendor::create(array_merge($row, ['business_id' => $business->id]));
            }
        }
    }
}
