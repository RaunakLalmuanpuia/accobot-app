<?php

/**
 * Permission groups for the dashboard "Your Access" card.
 * Add new groups here when new permission sets are introduced.
 * The Vue component reads this directly — no frontend changes needed.
 */
return [
    ['label' => 'Members',      'perms' => ['members.view', 'members.invite', 'members.remove', 'members.suspend', 'members.assign_role']],
    ['label' => 'Clients',      'perms' => ['clients.view', 'clients.create', 'clients.edit', 'clients.delete']],
    ['label' => 'Vendors',      'perms' => ['vendors.view', 'vendors.create', 'vendors.edit', 'vendors.delete']],
    ['label' => 'Inventory',    'perms' => ['products.view', 'products.create', 'products.edit', 'products.delete']],
    ['label' => 'Narration Heads',  'perms' => ['narration_heads.view', 'narration_heads.create', 'narration_heads.edit', 'narration_heads.delete']],
    ['label' => 'Accounting',   'perms' => ['invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete', 'reports.view', 'reports.export']],
    ['label' => 'Banking',      'perms' => ['transactions.view', 'transactions.review', 'transactions.edit', 'transactions.import']],
    ['label' => 'Assistant',    'perms' => ['chat.view']],
    ['label' => 'Integrations', 'perms' => ['integrations.view', 'integrations.manage']],
    ['label' => 'Settings',     'perms' => ['tenant.view_settings', 'tenant.update_settings']],
    ['label' => 'Audit',        'perms' => ['audit.view']],
];
