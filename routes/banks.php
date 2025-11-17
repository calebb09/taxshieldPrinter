<?php
// routes/banks.php

// GET /banks — list all banks
if ($path === '/banks' && $method === 'GET') {
    $payload = getAuthPayload($auth);
    if (!$payload)
        json(['error' => 'unauthorized'], 401);

    $banks = $banksMgr->getAll();
    json(['ok' => true, 'banks' => $banks]);
}

// GET /banks/{id} — view single bank
if (preg_match('#^/banks/(\d+)$#', $path, $matches) && $method === 'GET') {
    $payload = getAuthPayload($auth);
    if (!$payload)
        json(['error' => 'unauthorized'], 401);

    $bank = $banksMgr->getById((int) $matches[1]);
    if (!$bank)
        json(['error' => 'Bank not found'], 404);

    json(['ok' => true, 'bank' => $bank]);
}

// POST /banks — create bank
if ($path === '/banks' && $method === 'POST') {
    $payload = getAuthPayload($auth);
    if (!$payload)
        json(['error' => 'unauthorized'], 401);

    // $_POST contains text fields, $_FILES contains uploaded files
    $bank_name = $_POST['bank_name'] ?? null;
    $bank_account = $_POST['bank_account'] ?? null;
    $bank_routing = $_POST['bank_routing'] ?? null;

    if (empty($bank_name) || empty($bank_account) || empty($bank_routing)) {
        json(['error' => 'All fields are required'], 400);
    }

    // Handle logo file upload
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        json(['error' => 'Logo upload failed'], 400);
    }

    // Validate file type and size (optional: add size limit, e.g., 5MB)
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    $fileType = mime_content_type($_FILES['logo']['tmp_name']);
    if (!in_array($fileType, $allowedTypes)) {
        json(['error' => 'Invalid file type. Only JPG, JPEG, PNG allowed.'], 400);
    }
    if ($_FILES['logo']['size'] > $maxSize) {
        json(['error' => 'File too large. Maximum 5MB allowed.'], 400);
    }

    $uploadDir = __DIR__ . '/../uploads/banks/';
    $parentDir = __DIR__ . '/../uploads/';

    // Ensure parent directory exists and is writable
    if (!file_exists($parentDir)) {
        if (!mkdir($parentDir, 0777, true)) {
            json(['error' => 'Failed to create parent upload directory'], 500);
        }
    }
    if (!is_writable($parentDir)) {
        json(['error' => 'Parent upload directory not writable. Check server permissions.'], 500);
    }

    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            json(['error' => 'Failed to create upload directory'], 500);
        }
    }
    if (!is_writable($uploadDir)) {
        json(['error' => 'Upload directory not writable'], 500);
    }

    // Sanitize filename
    $originalName = basename($_FILES['logo']['name']);
    $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '', $originalName);
    if (empty($sanitizedName)) {
        $sanitizedName = 'logo.png'; // Fallback
    }
    $extension = pathinfo($sanitizedName, PATHINFO_EXTENSION);
    if (empty($extension)) {
        $extension = 'png'; // Default extension based on MIME
    }
    $filename = time() . '_' . preg_replace('/\.[^.]*$/', '', $sanitizedName) . '.' . $extension;
    $targetPath = $uploadDir . $filename;

    if (!move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
        $lastError = error_get_last();
        $details = $lastError ? $lastError['message'] : 'Unknown error';
        json(['error' => 'Failed to move uploaded file', 'details' => $details], 500);
    }

    // Verify file was written successfully
    if (!file_exists($targetPath)) {
        json(['error' => 'File upload incomplete'], 500);
    }

    // Save the relative path in database
    $logoPath = 'uploads/banks/' . $filename;

    // Prepare data for Banks class
    $data = [
        'logo' => $logoPath,
        'bank_name' => $bank_name,
        'bank_account' => $bank_account,
        'bank_routing' => $bank_routing
    ];

    $bankId = $banksMgr->create($data, $payload['sub']);

    json(['ok' => true, 'message' => 'Bank created', 'bank_id' => $bankId, 'logo' => $logoPath]);
}

// PUT /banks/{id} — update bank
if (preg_match('#^/banks/(\d+)$#', $path, $matches) && $method === 'POST') {
    $bankId = (int) $matches[1];

    // AUTH
    $payload = getAuthPayload($auth);
    if (!$payload) {
        json(['error' => 'unauthorized'], 401);
    }

    // Collect text fields
    $data = [];

    if (!empty($_POST['bank_name'])) {
        $data['bank_name'] = $_POST['bank_name'];
    }

    if (!empty($_POST['bank_account'])) {
        $data['bank_account'] = $_POST['bank_account'];
    }

    if (!empty($_POST['bank_routing'])) {
        $data['bank_routing'] = $_POST['bank_routing'];
    }

    // Handle file upload (logo)
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {

        $uploadDir = __DIR__ . '/../uploads/banks/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $filename = time() . '_' . basename($_FILES['logo']['name']);
        $targetPath = $uploadDir . $filename;

        if (!move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
            json(['error' => 'Failed to move uploaded file'], 500);
        }

        $data['logo'] = 'uploads/banks/' . $filename;
    }

    // No fields found?
    if (empty($data)) {
        json(['error' => 'No data to update'], 400);
    }

    // Perform the update
    $ok = $banksMgr->update($bankId, $data);

    if ($ok) {
        json([
            'ok' => true,
            'message' => 'Bank updated successfully',
            'bank_id' => $bankId,
            'updated' => $data
        ]);
    } else {
        json(['error' => 'Failed to update bank'], 500);
    }
}


// DELETE /banks/{id} — delete bank
// DELETE /banks/{id}
if (preg_match('#^/banks/(\d+)$#', $path, $matches) && $method === 'DELETE') {

    $payload = getAuthPayload($auth);
    if (!$payload)
        json(['error' => 'unauthorized'], 401);

    $bankId = (int) $matches[1];

    $bank = $banksMgr->getById($bankId);
    if (!$bank || $bank['created_by'] !== $payload['sub']) {
        json(['error' => 'Bank not found or unauthorized'], 404);
    }

    // Delete bank logo
    if (!empty($bank['logo'])) {
        $fullPath = __DIR__ . '/../' . $bank['logo'];
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    // Delete all companies linked to this bank
    $companies = $companyMgr->getByBankId($bankId);

    $deletedCompanies = 0;

    foreach ($companies as $company) {

        // Ensure $company is an array
        if (!is_array($company))
            continue;

        $companyId = (int) $company['id'];

        // Delete company logo
        if (!empty($company['logo'])) {
            $companyLogoPath = __DIR__ . '/../' . $company['logo'];
            if (file_exists($companyLogoPath)) {
                unlink($companyLogoPath);
            }
        }

        // Delete company record
        $companyMgr->delete($companyId);
        $deletedCompanies++;
    }

    // Delete the bank last
    $banksMgr->delete($bankId);

    json([
        'ok' => true,
        'message' => 'Bank and related companies deleted successfully',
        'deleted_bank_id' => $bankId,
        'deleted_companies_count' => $deletedCompanies
    ]);
}
