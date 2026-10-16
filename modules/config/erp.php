<?php

return [
    'navigation' => [
        ['label' => 'Overview', 'route' => 'admin.dashboard', 'pattern' => 'admin.dashboard', 'icon' => 'chart-pie', 'group' => 'Workspace'],
        ['label' => 'Sales', 'route' => 'sales.home', 'pattern' => 'sales.*', 'icon' => 'chart-line', 'group' => 'Operations'],
        ['label' => 'Orders & products', 'route' => 'ecommerce.home', 'pattern' => 'ecommerce.*', 'icon' => 'basket-shopping', 'group' => 'Operations'],
        ['label' => 'Inventory', 'route' => 'inventory.home', 'pattern' => 'inventory.*', 'icon' => 'boxes-stacked', 'group' => 'Operations'],
        ['label' => 'Procurement', 'route' => 'procurement.home', 'pattern' => 'procurement.*', 'icon' => 'clipboard-list', 'group' => 'Operations'],
        ['label' => 'Supply chain', 'route' => 'supplychain.home', 'pattern' => 'supplychain.*', 'icon' => 'truck-fast', 'group' => 'Operations'],
        ['label' => 'Customer service', 'route' => 'customerservice.home', 'pattern' => 'customerservice.*', 'icon' => 'headset', 'group' => 'Operations'],
        ['label' => 'Finance', 'route' => 'finance.home', 'pattern' => 'finance.*', 'icon' => 'wallet', 'group' => 'Management'],
        ['label' => 'People', 'route' => 'hr.home', 'pattern' => 'hr.*', 'icon' => 'users', 'group' => 'Management'],
        ['label' => 'Projects', 'route' => 'projects.home', 'pattern' => 'projects.*', 'icon' => 'diagram-project', 'group' => 'Management'],
        ['label' => 'Store settings', 'route' => 'commerce.settings', 'pattern' => 'commerce.*', 'icon' => 'sliders', 'group' => 'Management'],
        ['label' => 'Reports', 'route' => 'reports.home', 'pattern' => 'reports.*', 'icon' => 'file-lines', 'group' => 'Management'],
        ['label' => 'Access control', 'route' => 'access.management', 'pattern' => 'access.*', 'icon' => 'user-shield', 'group' => 'Management'],
    ],
    'sections' => [
        'ecommerce' => ['Overview' => 'ecommerce.home', 'Orders' => 'ecommerce.orders', 'Products' => 'ecommerce.products', 'Store settings' => 'commerce.settings', 'Cancellations & returns' => 'ecommerce.adjustments'],
        'inventory' => ['Overview' => 'inventory.home', 'Stock' => 'inventory.stock', 'Tracking' => 'inventory.tracking', 'Receiving' => 'inventory.receiving'],
        'procurement' => ['Overview' => 'procurement.home', 'Requisitions' => 'procurement.create', 'Purchase orders' => 'procurement.createpo', 'Status' => 'procurement.status'],
        'supplychain' => ['Overview' => 'supplychain.home', 'Logistics' => 'supplychain.logistic', 'Tracking' => 'supplychain.tracking'],
        'finance' => ['Overview' => 'finance.home', 'Accounts' => 'finance.accounts', 'Invoices' => 'finance.invoice', 'Receipts & refunds' => 'finance.payments', 'Approvals' => 'finance.approval', 'Ledger controls' => 'finance.controls', 'Supplier payables' => 'finance.supplier-payables'],
        'hr' => ['Overview' => 'hr.home', 'Applications' => 'hr.applications', 'Attendance' => 'hr.attendance', 'Leave' => 'hr.leave', 'Payroll' => 'hr.payroll'],
        'projects' => ['Overview' => 'projects.home', 'Projects' => 'projects.projects', 'Budgets' => 'projects.budget', 'Resources' => 'projects.resources'],
        'reports' => ['Overview' => 'reports.home', 'Summary' => 'reports.summary'],
        'sales' => ['Overview' => 'sales.home', 'Tickets' => 'sales.ticket'],
    ],
];
