<?php
// ------------------------- CLIENTS -------------------------

if ($path === '/clients' && $method === 'POST') {
    $payload = getAuthPayload($auth);  // <-- works now
    if (!$payload)
        json(['error' => 'unauthorized'], 401);

    $data = json_decode(file_get_contents('php://input'), true);
    $id = $clientMgr->createClient($data, $payload['sub']);
    json(['ok' => true, 'client_id' => $id], 201);
}

// Get client by ID
if (preg_match('#^/clients/(\d+)$#', $path, $m) && $method === 'GET') {
    $payload = getAuthPayload($auth);
    if (!$payload)
        json(['error' => 'unauthorized'], 401);

    $client = $clientMgr->getClient((int) $m[1]);
    if (!$client)
        json(['error' => 'not found'], 404);

    json($client);
}

// List clients with pagination
if ($path === '/clients' && $method === 'GET') {
    $payload = getAuthPayload($auth);
    if (!$payload) {
        json(['error' => 'unauthorized'], 401);
    }

    // Filters
    $filters = [];
    if (!empty($_GET['branch'])) {
        $filters['branch'] = (int) $_GET['branch'];
    }
    if (!empty($_GET['name'])) {
        $filters['name'] = $_GET['name'];
    }

    // Pagination
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 10;
    $offset = ($page - 1) * $limit;

    // Sorting
    $allowedSort = ['id', 'name', 'email', 'created_at'];
    $sort_by = $_GET['sort_by'] ?? 'id';
    $sort_order = strtoupper($_GET['sort_order'] ?? 'DESC');

    if (!in_array($sort_by, $allowedSort)) {
        json(['error' => 'invalid sort_by'], 400);
    }
    if (!in_array($sort_order, ['ASC', 'DESC'])) {
        json(['error' => 'invalid sort_order'], 400);
    }

    try {
        $result = $clientMgr->listClients($filters, $limit, $offset, $sort_by, $sort_order);

        json([
            'ok' => true,
            'page' => $page,
            'limit' => $limit,
            'total' => $result['total'],
            'sort_by' => $sort_by,
            'sort_order' => $sort_order,
            'filters' => $filters,
            'clients' => $result['data']
        ]);
    } catch (Exception $e) {
        json(['error' => $e->getMessage()], 400);
    }
}


// ------------------------- CLIENTS SEARCH -------------------------
if ($path === '/clients/search' && $method === 'GET') {
    $payload = getAuthPayload($auth);
    if (!$payload)
        json(['error' => 'unauthorized'], 401);

    $name = $_GET['name'] ?? null;
    $email = $_GET['email'] ?? null;
    $mobile = $_GET['mobile'] ?? null;

    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 10;
    $offset = ($page - 1) * $limit;

    // Sorting
    $allowedSort = ['id', 'name', 'email', 'mobile', 'created_at'];
    $sort_by = $_GET['sort_by'] ?? 'id';
    $sort_order = strtoupper($_GET['sort_order'] ?? 'DESC');

    if (!in_array($sort_by, $allowedSort)) {
        json(['error' => 'invalid sort_by'], 400);
    }
    if (!in_array($sort_order, ['ASC', 'DESC'])) {
        json(['error' => 'invalid sort_order'], 400);
    }

    try {
        $result = $clientMgr->searchClients(
            $payload['sub'],
            $name,
            $email,
            $mobile,
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
                'name' => $name,
                'email' => $email,
                'mobile' => $mobile
            ],
            'clients' => $result['data']
        ]);
    } catch (Exception $e) {
        json(['error' => $e->getMessage()], 400);
    }
}
