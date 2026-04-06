<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Client;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $businesses = Business::all()->keyBy('name');

        $data = [
            'Alpha Accounting Firm' => [
                ['name' => 'Rahul Enterprises',   'email' => 'rahul@enterprises.com', 'phone' => '9876543210'],
                ['name' => 'Priya Trading Co.',   'email' => 'priya@trading.com',     'phone' => '9123456780'],
                ['name' => 'Suresh & Sons',        'email' => 'suresh@sons.com',       'phone' => null],
            ],
            'Beta Finance Group' => [
                ['name' => 'NextGen Tech Pvt Ltd', 'email' => 'hello@nextgen.com',     'phone' => '9988776655'],
                ['name' => 'Meera Exports',        'email' => 'meera@exports.in',      'phone' => '9871234560'],
            ],
            'Gamma Tax Consultants' => [
                ['name' => 'Kiran Motors',         'email' => 'kiran@motors.com',      'phone' => '9090909090'],
                ['name' => 'Laxmi Jewellers',      'email' => null,                    'phone' => '9876501234'],
                ['name' => 'Amit Constructions',   'email' => 'amit@constructions.in', 'phone' => null],
            ],
        ];

        foreach ($data as $businessName => $clients) {
            $business = $businesses->get($businessName);
            if (!$business) continue;

            foreach ($clients as $client) {
                $business->clients()->create($client);
            }
        }
    }
}
