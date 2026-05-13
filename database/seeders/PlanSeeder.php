<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('plans.plans') as $slug => $data) {
            Plan::updateOrCreate(
                ['slug' => $slug],
                [
                    'name'             => $data['name'],
                    'price'            => $data['price'],
                    'tenant_type'      => $data['tenant_type'],
                    'razorpay_plan_id' => $data['razorpay_plan_id'],
                    'features'         => $data['features'],
                    'is_addon'         => $data['is_addon'],
                    'is_active'        => true,
                ]
            );
        }
    }
}
