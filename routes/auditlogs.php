<?php
// Get audit logs
if ($path === '/audit-logs' && $method === 'GET') {
    $payload = getAuthPayload($auth);
    if (!$payload)
        json(['error' => 'unauthorized'], 401);

    $filters = [];
    if (!empty($_GET['user_id']))
        $filters['user_id'] = (int) $_GET['user_id'];
    if (!empty($_GET['from']))
        $filters['from'] = $_GET['from']; // YYYY-MM-DD
    if (!empty($_GET['to']))
        $filters['to'] = $_GET['to']; // YYYY-MM-DD

    // Pagination
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 10;
    $offset = ($page - 1) * $limit;

    // Sorting
    $allowedSort = ['id', 'user_id', 'action', 'created_at'];
    $sort_by = $_GET['sort_by'] ?? 'created_at';
    $sort_order = strtoupper($_GET['sort_order'] ?? 'DESC');

    if (!in_array($sort_by, $allowedSort)) {
        json(['error' => 'invalid sort_by'], 400);
    }
    if (!in_array($sort_order, ['ASC', 'DESC'])) {
        json(['error' => 'invalid sort_order'], 400);
    }

    try {
        $result = $audit->getLogs($filters, $limit, $offset, $sort_by, $sort_order);

        json([
            'ok' => true,
            'page' => $page,
            'limit' => $limit,
            'total' => $result['total'],
            'sort_by' => $sort_by,
            'sort_order' => $sort_order,
            'filters' => $filters,
            'logs' => $result['data']
        ]);
    } catch (Exception $e) {
        json(['error' => $e->getMessage()], 400);
    }
}
