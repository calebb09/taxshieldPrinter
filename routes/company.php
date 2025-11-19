<?php
// routes/banks.php

// GET /banks — list all banks
if ($path === '/company' && $method === 'GET') {
    $payload = getAuthPayload($auth);
    if (!$payload)
        json(['error' => 'unauthorized'], 401);

    $companies = $companyMgr->getAll();
    json(['ok' => true, 'companies' => $companies]);
}

// GET /banks/{id} — view single bank
if (preg_match('#^/company/(\d+)$#', $path, $matches) && $method === 'GET') {
    $payload = getAuthPayload($auth);
    if (!$payload)
        json(['error' => 'unauthorized'], 401);

    $companies = $companyMgr->getById((int) $matches[1]);
    if (!$companies)
        json(['error' => 'Bank not found'], 404);

    json(['ok' => true, 'company' => $companies]);
}

// POST /banks — create bank
if ($path === '/company' && $method === 'POST') {
    $payload = getAuthPayload($auth);
    if (!$payload)
        json(['error' => 'unauthorized'], 401);

    // $_POST contains text fields, $_FILES contains uploaded files
    $bankId = $_POST['bankId'] ?? null;
    $companyTitle = $_POST['name'] ?? null;
    $address = $_POST['address'] ?? null;
    $phone = $_POST['phone'] ?? null;
    $email = $_POST['email'] ?? null;

    if (empty($bankId) || empty($address) || empty($phone) || empty($email)) {
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

    $uploadDir = __DIR__ . '/../uploads/companies/';
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
    $logoPath = 'uploads/companies/' . $filename;

    // Prepare data for Banks class
    $data = [
        'logo' => $logoPath,
        'bankId' => $bankId,
        'name' => $companyTitle,
        'address' => $address,
        'phone' => $phone,
        'email' => $email
    ];

    $companyId = $companyMgr->create($data, $payload['sub']);

    json(['ok' => true, 'message' => 'company created', 'companyId' => $companyId, 'logo' => $logoPath]);
}

// UPDATE COMPANIES 
// UPDATE /companies/{id}
if (preg_match('#^/company/(\d+)$#', $path, $matches) && $method === 'POST') {

    // Auth check
    $payload = getAuthPayload($auth);
    if (!$payload) {
        json(['error' => 'unauthorized'], 401);
    }

    $companyId = (int) $matches[1];

    // Fetch company to verify ownership
    $company = $companyMgr->getById($companyId);
    if (!$company || $company['created_by'] !== $payload['sub']) {
        json(['error' => 'Company not found or unauthorized'], 404);
    }

    // Collect fields to update
    $data = [];

    // if (!empty($_POST['name'])) {
    //     $data['name'] = $_POST['name'];
    // }

    if (!empty($_POST['address'])) {
        $data['address'] = $_POST['address'];
    }

    if (!empty($_POST['phone'])) {
        $data['phone'] = $_POST['phone'];
    }

    if (!empty($_POST['email'])) {
        $data['email'] = $_POST['email'];
    }

    if (!empty($_POST['bankId'])) {
        $data['bankId'] = (int) $_POST['bankId'];
    }

    /*
     * ---------------------------------------
     * OPTIONAL LOGO UPLOAD
     * ---------------------------------------
     */
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {

        $uploadDir = __DIR__ . '/../uploads/companies/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $filename = time() . '_' . basename($_FILES['logo']['name']);
        $targetPath = $uploadDir . $filename;

        if (!move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
            json(['error' => 'Failed to upload file'], 500);
        }

        // Delete old logo if exists
        if (!empty($company['logo'])) {
            $oldLogoPath = __DIR__ . '/../' . $company['logo'];
            if (file_exists($oldLogoPath)) {
                unlink($oldLogoPath);
            }
        }

        // Save new logo path
        $data['logo'] = 'uploads/companies/' . $filename;
    }

    // No fields changed?
    if (empty($data)) {
        json(['error' => 'No data to update'], 400);
    }

    // Update in DB
    $ok = $companyMgr->update($companyId, $data);

    if ($ok) {
        json([
            'ok' => true,
            'message' => 'Company updated successfully',
            'company_id' => $companyId,
            'updated_fields' => $data
        ]);
    } else {
        json(['error' => 'Failed to update company'], 500);
    }
}


// DELETE /banks/{id} — delete bank
if (preg_match('#^/company/(\d+)$#', $path, $matches) && $method === 'DELETE') {
    $payload = getAuthPayload($auth);
    if (!$payload)
        json(['error' => 'unauthorized'], 401);

    $companyId = (int) $matches[1];
    $company = $companyMgr->getById($companyId);
    if (!$company || !isset($company['created_by']) || $company['created_by'] !== $payload['sub']) {
        json(['error' => 'Company not found or unauthorized'], 404);
    }

    // Delete associated logo file if it exists
    if (!empty($company['logo'])) {
        $fullPath = __DIR__ . '/../' . $company['logo'];
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    $companyMgr->delete($companyId);
    json(['ok' => true, 'message' => 'Company deleted']);
}