<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class AccountingSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found — skipping AccountingSeeder.');
            return;
        }

        foreach ($tenants as $tenant) {
            $this->seedForTenant($tenant);
        }

        $this->command->info('Accounting seed data created for all tenants. Run `php artisan embed:data` to generate embeddings.');
    }

    private function seedForTenant(Tenant $tenant): void
    {
        // Sample clients
        $clients = [
            [
                'name'    => 'Rajan Enterprises',
                'email'   => 'billing@rajanenterprises.in',
                'phone'   => '+91-98100-11001',
                'address' => '14, Connaught Place, New Delhi, Delhi 110001',
                'company' => 'Rajan Enterprises',
                'tax_id'  => '07AABCR1234A1Z5',
            ],
            [
                'name'    => 'Priya Mehta',
                'email'   => 'priya.mehta@gmail.com',
                'phone'   => '+91-98200-22002',
                'address' => '302, Bandra West, Mumbai, Maharashtra 400050',
                'company' => 'Mehta Consulting',
                'tax_id'  => '27AABCM5678B1Z3',
            ],
            [
                'name'    => 'Suntech Solutions Pvt Ltd',
                'email'   => 'accounts@suntechsolutions.in',
                'phone'   => '+91-98300-33003',
                'address' => '56, Koramangala, Bengaluru, Karnataka 560034',
                'company' => 'Suntech Solutions Pvt Ltd',
                'tax_id'  => '29AABCS9012C1Z1',
            ],
            [
                'name'    => 'Ananya Singh',
                'email'   => 'ananya@ananyadesign.in',
                'phone'   => '+91-98400-44004',
                'address' => '88, Jubilee Hills, Hyderabad, Telangana 500033',
                'company' => 'Ananya Singh Design Studio',
            ],
        ];

        foreach ($clients as $data) {
            Client::firstOrCreate(
                ['tenant_id' => $tenant->id, 'email' => $data['email']],
                array_merge($data, ['tenant_id' => $tenant->id])
            );
        }

        // Sample products / services with full classification hierarchy
        $products = [
            // Television
            [
                'name'           => 'Brand A - 19" LED TV',
                'description'    => '19 inch LED television with HD display and energy saving mode',
                'sku'            => 'TV-A1-19LED',
                'unit'           => 'piece',
                'unit_price'     => 12500.00,
                'tax_rate'       => 28.00,
                'stock_quantity' => 30,
                'category'       => 'Television',
                'sub_category'   => 'LED TV',
                'main_group'     => 'Grade One',
                'sub_group'      => 'Brand A',
            ],
            [
                'name'           => 'Brand A - 17" Smart TV',
                'description'    => '17 inch Smart TV with Wi-Fi, Android OS, and streaming apps',
                'sku'            => 'TV-A1-17STV',
                'unit'           => 'piece',
                'unit_price'     => 14500.00,
                'tax_rate'       => 28.00,
                'stock_quantity' => 20,
                'category'       => 'Television',
                'sub_category'   => 'Smart TV',
                'main_group'     => 'Grade One',
                'sub_group'      => 'Brand A',
            ],
            [
                'name'           => 'Brand B - 19" LED TV',
                'description'    => '19 inch LED television, basic model with remote and wall mount',
                'sku'            => 'TV-B2-19LED',
                'unit'           => 'piece',
                'unit_price'     => 9500.00,
                'tax_rate'       => 28.00,
                'stock_quantity' => 50,
                'category'       => 'Television',
                'sub_category'   => 'LED TV',
                'main_group'     => 'Grade Two',
                'sub_group'      => 'Brand B',
            ],
            [
                'name'           => 'Brand B - 17" Smart TV',
                'description'    => '17 inch Smart TV with basic streaming and HDMI input',
                'sku'            => 'TV-B2-17STV',
                'unit'           => 'piece',
                'unit_price'     => 11000.00,
                'tax_rate'       => 28.00,
                'stock_quantity' => 35,
                'category'       => 'Television',
                'sub_category'   => 'Smart TV',
                'main_group'     => 'Grade Two',
                'sub_group'      => 'Brand B',
            ],

            // Furniture
            [
                'name'           => 'Ergonomic Office Desk',
                'description'    => 'Height-adjustable standing desk, 160×80 cm, with cable management',
                'sku'            => 'FURN-DESK-001',
                'unit'           => 'piece',
                'unit_price'     => 18500.00,
                'tax_rate'       => 18.00,
                'stock_quantity' => 25,
                'category'       => 'Furniture',
                'sub_category'   => 'Desks',
                'main_group'     => 'Premium',
                'sub_group'      => null,
            ],
            [
                'name'           => 'Ergonomic Office Chair',
                'description'    => 'Mesh back office chair with lumbar support and adjustable armrests',
                'sku'            => 'FURN-CHR-001',
                'unit'           => 'piece',
                'unit_price'     => 11200.00,
                'tax_rate'       => 18.00,
                'stock_quantity' => 40,
                'category'       => 'Furniture',
                'sub_category'   => 'Chairs',
                'main_group'     => 'Premium',
                'sub_group'      => null,
            ],

            // Services
            [
                'name'           => 'Web Design Service',
                'description'    => 'Custom website design including UI/UX and responsive layout',
                'sku'            => 'SVC-WD-001',
                'unit'           => 'hour',
                'unit_price'     => 1500.00,
                'tax_rate'       => 18.00,
                'stock_quantity' => 999,
                'category'       => 'Services',
                'sub_category'   => 'Design',
                'main_group'     => 'Digital',
                'sub_group'      => null,
            ],
            [
                'name'           => 'Software Consulting',
                'description'    => 'Technical consulting, architecture review, and code audit',
                'sku'            => 'SVC-CONS-001',
                'unit'           => 'hour',
                'unit_price'     => 2500.00,
                'tax_rate'       => 18.00,
                'stock_quantity' => 999,
                'category'       => 'Services',
                'sub_category'   => 'Consulting',
                'main_group'     => 'Digital',
                'sub_group'      => null,
            ],
            [
                'name'           => 'Annual Maintenance Plan',
                'description'    => 'Yearly software maintenance, updates, and priority support',
                'sku'            => 'SVC-MAINT-001',
                'unit'           => 'year',
                'unit_price'     => 36000.00,
                'tax_rate'       => 18.00,
                'stock_quantity' => 999,
                'category'       => 'Services',
                'sub_category'   => 'Maintenance',
                'main_group'     => 'Digital',
                'sub_group'      => null,
            ],
        ];

        foreach ($products as $data) {
            Product::firstOrCreate(
                ['tenant_id' => $tenant->id, 'sku' => $data['sku']],
                array_merge($data, ['tenant_id' => $tenant->id])
            );
        }

    }
}
