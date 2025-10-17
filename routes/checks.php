<?php
// ------------------------- CHECKS -------------------------

// Create check
if ($path === '/checks' && $method === 'POST') {
    $payload = getAuthPayload($auth);
    if (!$payload)
        json(['error' => 'unauthorized'], 401);

    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['client_id']) || empty($data['amount'])) {
        json(['error' => 'client_id and amount required'], 400);
    }

    $id = $checkMgr->createCheck(
        $data['client_id'],
        $data['amount'],
        $data['company_id'] ?? null,
        $payload['sub']
    );

    json(['ok' => true, 'check_id' => $id], 201);
}

// Update check status
if (preg_match('#^/checks/(\d+)/status$#', $path, $m) && $method === 'POST') {
    $payload = getAuthPayload($auth);
    if (!$payload)
        json(['error' => 'unauthorized'], 401);

    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['status']))
        json(['error' => 'status required'], 400);

    try {
        $checkMgr->updateStatus((int) $m[1], $data['status'], $payload['sub']);
        json(['ok' => true]);
    } catch (Exception $e) {
        json(['error' => $e->getMessage()], 400);
    }
}

// Search checks by status, client_id, date range with pagination + sorting
if ($path === '/checks' && $method === 'GET') {
    $payload = getAuthPayload($auth);
    if (!$payload)
        json(['error' => 'unauthorized'], 401);

    $status = $_GET['status'] ?? null;
    $client_id = $_GET['client_id'] ?? null;
    $from = $_GET['from'] ?? null; // YYYY-MM-DD
    $to = $_GET['to'] ?? null; // YYYY-MM-DD
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 10;
    $offset = ($page - 1) * $limit;

    // Sorting
    $allowedSort = ['id', 'amount', 'status', 'created_at'];
    $sort_by = $_GET['sort_by'] ?? 'id';
    $sort_order = strtoupper($_GET['sort_order'] ?? 'DESC');

    if (!in_array($sort_by, $allowedSort)) {
        json(['error' => 'invalid sort_by'], 400);
    }
    if (!in_array($sort_order, ['ASC', 'DESC'])) {
        json(['error' => 'invalid sort_order'], 400);
    }

    try {
        $result = $checkMgr->getChecks(
            $payload['sub'],
            $status,
            $client_id,
            $from,
            $to,
            $limit,
            $offset,
            $sort_by,
            $sort_order
        );

        json([
            'ok' => true,
            'page' => $page,
            'limit' => $limit,
            'total' => $result['total'],
            'sort_by' => $sort_by,
            'sort_order' => $sort_order,
            'filters' => [
                'status' => $status,
                'client_id' => $client_id,
                'from' => $from,
                'to' => $to
            ],
            'checks' => $result['data']
        ]);
    } catch (Exception $e) {
        json(['error' => $e->getMessage()], 400);
    }
}

// ✅ Get specific check by ID with client info
if (preg_match('#^/checks/(\d+)$#', $path, $matches) && $method === 'GET') {
    $payload = getAuthPayload($auth);
    if (!$payload) {
        json(['error' => 'unauthorized'], 401);
    }

    $check_id = (int) $matches[1];

    try {
        $check = $checkMgr->getCheckByIdWithCompanyBank($payload['sub'], $check_id);

        if (!$check) {
            json(['error' => 'Check not found'], 404);
        }

        json([
            'ok' => true,
            'check' => [
                'id' => (int) $check['id'],
                'check_number' => $check['check_number'],
                'client_id' => $check['client_id'],
                'amount' => $check['amount'],
                'status' => $check['status'],
                'created_at' => $check['created_at'],
                'company' => [
                    'id' => (int) $check['company_id'],
                    'logo' => $check['company_logo'],
                    'address' => $check['company_address'],
                    'phone' => $check['company_phone'],
                    'email' => $check['company_email'],
                    'bank' => [
                        'id' => (int) $check['bank_id'],
                        'bank_name' => $check['bank_name'],
                        'logo' => $check['bank_logo'],
                        'account' => (int) $check['bank_account'],
                        'routing' => (int) $check['bank_routing']
                    ]
                ]
            ]
        ]);
    } catch (Exception $e) {
        json(['error' => $e->getMessage()], 400);
    }
}
