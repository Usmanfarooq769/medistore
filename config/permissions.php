<?php

/**
 * Fine-grained permissions.
 * A user's effective permission = per-user override (users.permissions JSON)  >  role default below.
 * Admin always has everything. Manage overrides in Users & Roles.
 */
return [
    'labels' => [
        'sales.override_discount' => 'Approve discounts above the limit',
        'sales.price_edit'        => 'Edit price at POS',
        'sales.void'              => 'Void an invoice',
        'sales.return'            => 'Process customer returns',
        'sales.credit'            => 'Sell on credit (due)',
        'purchases.manage'        => 'Create purchases / purchase orders',
        'stock.adjust'            => 'Adjust / write-off stock',
        'medicines.manage'        => 'Add / edit medicines',
        'customers.manage'        => 'Add / edit customers',
        'reports.view'            => 'View reports',
        'reports.profit'          => 'See cost and profit figures',
        'controlled.register'     => 'View controlled / narcotics register',
        'export.data'             => 'Export tables to Excel / PDF',
        'safety.override'         => 'Override drug-safety warnings',
    ],

    'roles' => [
        'admin' => ['*'],
        'branch_manager' => [
            'sales.override_discount', 'sales.price_edit', 'sales.void', 'sales.return', 'sales.credit',
            'purchases.manage', 'stock.adjust', 'medicines.manage', 'customers.manage',
            'reports.view', 'reports.profit', 'controlled.register', 'export.data', 'safety.override',
        ],
        'pharmacist' => [
            'sales.return', 'sales.credit', 'customers.manage', 'controlled.register', 'safety.override', 'reports.view',
        ],
        'cashier' => ['sales.credit', 'customers.manage'],
    ],

    /** Allergy groups -> ingredient keywords (cross-reactivity). */
    'allergy_groups' => [
        'penicillin'    => ['penicillin', 'amoxicillin', 'ampicillin', 'amoxycillin', 'flucloxacillin', 'cloxacillin', 'piperacillin', 'clavulan', 'co-amoxiclav', 'augmentin'],
        'cephalosporin' => ['cef', 'ceph', 'cefixime', 'ceftriaxone', 'cefuroxime', 'cefpodoxime'],
        'sulfa'         => ['sulfa', 'sulpha', 'sulfamethoxazole', 'cotrimoxazole', 'co-trimoxazole', 'sulfadiazine'],
        'nsaid'         => ['ibuprofen', 'diclofenac', 'naproxen', 'ketorolac', 'mefenamic', 'aspirin', 'celecoxib', 'etoricoxib', 'indomethacin', 'piroxicam', 'meloxicam'],
        'aspirin'       => ['aspirin', 'acetylsalicylic'],
        'macrolide'     => ['azithromycin', 'clarithromycin', 'erythromycin'],
        'quinolone'     => ['ciprofloxacin', 'levofloxacin', 'moxifloxacin', 'ofloxacin', 'norfloxacin'],
    ],
];
