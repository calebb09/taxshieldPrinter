<?php
// ------------------------- DASHBOARD / ANALYTICS -------------------------

// Summary totals
if ($path === '/dashboard/summary' && $method === 'GET') {
    $payload = getAuthPayload($auth);
    if (!$payload)
        json(['error' => 'unauthorized'], 401);

    $totals = $checkMgr->getTotalsAndRecent();
    json([
        'totals' => $totals,
        'low_balance_warning' => $config->low_balance_warning ?? 2000
    ]);
}

// Checks per day
if ($path === '/dashboard/checks_per_day' && $method === 'GET') {
    $payload = getAuthPayload($auth);
    if (!$payload)
        json(['error' => 'unauthorized'], 401);

    $days = !empty($_GET['days']) ? (int) $_GET['days'] : 14;
    $data = $checkMgr->checksPerDay($days);
    json($data);
}
