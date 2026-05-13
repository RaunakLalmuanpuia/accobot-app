<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plan definitions
    |--------------------------------------------------------------------------
    |
    | Each entry maps a plan slug to its display name, monthly price (in paise),
    | applicable tenant type, and the list of feature slugs the plan includes.
    |
    | Prices are overridable at runtime via env vars — no deploy needed.
    | Razorpay plan IDs are set here after creating plans in the dashboard.
    |
    */

    'plans' => [

        'business_ca' => [
            'name'             => 'Business – CA Plan',
            'price'            => (int) env('PLAN_PRICE_BUSINESS_CA', 149900),
            'tenant_type'      => 'business',
            'razorpay_plan_id' => env('RAZORPAY_PLAN_ID_BUSINESS_CA'),
            'features'         => ['invoicing', 'tally_sync', 'group_chat', 'ai_assistant'],
            'is_addon'         => false,
        ],

        'business_solo' => [
            'name'             => 'Business – Solo Plan',
            'price'            => (int) env('PLAN_PRICE_BUSINESS_SOLO', 299900),
            'tenant_type'      => 'business',
            'razorpay_plan_id' => env('RAZORPAY_PLAN_ID_BUSINESS_SOLO'),
            'features'         => ['invoicing', 'tally_sync', 'group_chat', 'ai_assistant'],
            'is_addon'         => false,
        ],

        'personal' => [
            'name'             => 'Personal Plan',
            'price'            => (int) env('PLAN_PRICE_PERSONAL', 99900),
            'tenant_type'      => 'business',
            'razorpay_plan_id' => env('RAZORPAY_PLAN_ID_PERSONAL'),
            'features'         => ['invoicing'],
            'is_addon'         => false,
        ],

        'ca_firm' => [
            'name'             => 'CA Firm Plan',
            'price'            => (int) env('PLAN_PRICE_CA_FIRM', 0),  // TBD — set before trial ends
            'tenant_type'      => 'ca_firm',
            'razorpay_plan_id' => env('RAZORPAY_PLAN_ID_CA_FIRM'),
            'features'         => ['invoicing', 'tally_sync', 'group_chat', 'ai_assistant', 'ca_clients'],
            'is_addon'         => false,
        ],

        'ai_addon' => [
            'name'             => 'AI Assistance',
            'price'            => (int) env('PLAN_PRICE_AI_ADDON', 49900),
            'tenant_type'      => 'any',
            'razorpay_plan_id' => env('RAZORPAY_PLAN_ID_AI_ADDON'),
            'features'         => ['ai_assistant'],
            'is_addon'         => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | CA firm trial length (days)
    |--------------------------------------------------------------------------
    */
    'ca_trial_days' => (int) env('CA_TRIAL_DAYS', 14),

];
