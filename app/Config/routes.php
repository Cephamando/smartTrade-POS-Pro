<?php

/**
 * Whitelisted MVC routes. Keys match index.php?page=
 *
 * auth        — requires a logged-in session (default true)
 * standalone  — view supplies its own HTML shell (no header/footer)
 * action_only — controller script only; no view file
 */
return [
    'login' => ['auth' => false, 'standalone' => true],
    'logout' => ['action_only' => true],
    'dashboard' => [],
    'pos' => ['standalone' => true],
    'products' => [],
    'categories' => [],
    'inventory' => [],
    'locations' => [],
    'users' => [],
    'members' => [],
    'vendors' => [],
    'settings' => [],
    'reports' => [],
    'report_consumption' => [],
    'shifts' => [],
    'z_read' => [],
    'print_shift' => ['standalone' => true],
    'end_shift_action' => ['action_only' => true],
    'receipt' => ['standalone' => true],
    'receive_stock' => [],
    'receiving_bay' => [],
    'purchasing' => [],
    'transfers' => [],
    'refund_items' => [],
    'pickup' => ['standalone' => true],
    'kitchen' => [],
    'kds' => ['standalone' => true],
    'menu' => [],
    'tables' => [],
    'audit' => [],
    'activity' => [],
    'change_password' => [],
    'zra' => [],
    'unspsc' => [],
    'payroll' => [],
    'payslip' => ['standalone' => true],
    'flutterwave' => ['auth' => false, 'standalone' => true],
    'currency' => ['auth' => false, 'standalone' => true],
    'shop' => ['auth' => false, 'standalone' => true],
    'events' => [],
    'guide' => ['action_only' => true],
    'training' => ['action_only' => true],
];
