<?php
require_once __DIR__ . '/../functions/sendEmail.php';
if ($path === '/register' && $method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (empty($body['username']) || empty($body['password']) || empty($body['email'])) {
        json(['error' => 'username, email and password required'], 400);
    }
    try {
        $id = $auth->register(
            $body['username'],
            $body['email'],
            $body['password'],
            $body['full_name'] ?? null,
            $body['role'] ?? 'employee'
        );
        json(['ok' => true, 'user_id' => $id], 201);
    } catch (Exception $e) {
        json(['error' => $e->getMessage()], 500);
    }
}

if ($path === '/login' && $method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (empty($body['username']) || empty($body['password'])) {
        json(['error' => 'username and password required'], 400);
    }

    $user = $auth->login($body['username'], $body['password']);
    if (!$user)
        json(['error' => 'invalid credentials'], 401);

    $token = $auth->createJWT($user['id'], $user['username'], $user['role'], 3600 * 8760);
    $audit->log($user['id'], 'login', 'login_success');
    json([
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ]
    ]);
}


// --------------------------------change password and reset password routes -----------------------------

// Change password (authenticated)
if ($path === '/change-password' && $method === 'PUT') {
    $payload = getAuthPayload($auth);
    if (!$payload)
        json(['error' => 'unauthorized'], 401);

    $body = json_decode(file_get_contents('php://input'), true);
    $current = $body['current_password'] ?? null;
    $new = $body['new_password'] ?? null;

    if (empty($current) || empty($new)) {
        json(['error' => 'current_password and new_password required'], 400);
    }

    $userId = $payload['sub'];
    $ok = $auth->changePassword($userId, $current, $new);
    if (!$ok) {
        json(['error' => 'current password incorrect or update failed'], 400);
    }

    // audit log (support both possible audit method names)
    if (method_exists($audit, 'createLog')) {
        $audit->createLog($userId, 'change_password', 'User changed password');
    } elseif (method_exists($audit, 'log')) {
        $audit->log($userId, 'change_password', 'User changed password');
    }

    json(['ok' => true, 'message' => 'Password changed']);
}

// Forgot password — request reset (public)
if ($path === '/forgot-password' && $method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $email = $body['email'] ?? null;

    if (empty($email)) {
        json(['error' => 'email required'], 400);
    }

    $res = $auth->createPasswordResetToken($email);

    if (!$res) {
        // Explicit error if email not found
        json(['error' => 'Email address not found'], 404);
    }

    $debug = (!empty($_GET['debug']) && $_GET['debug'] == '1');
    $token = $res['token'];
    $resetLink = 'http://yourdomain/taxshield/reset-password?token=' . $token;

    if ($debug) {
        json([
            'ok' => true,
            'message' => 'Password reset token generated.',
            'reset_token' => $token,
            'reset_link' => $resetLink
        ]);
    } else {
        $ToEmail = $email;
        $ToSubject = "Password Reset Request";
        $EmailBody = '
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="font-family: Arial, sans-serif; background-color: #f5f5f5; padding: 20px;">
                <tr>
                    <td align="center">
                        <!-- Main Container -->
                        <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
                            <!-- Header -->
                            <tr>
                                <td align="center" style="background-color: #4CAF50; padding: 20px; color: #ffffff; font-size: 24px; font-weight: bold;">
                                    TaxShield
                                </td>
                            </tr>

                            <!-- Body -->
                            <tr>
                                <td style="padding: 30px; color: #333333; font-size: 16px; line-height: 1.5;">
                                    <p>Hello,</p>
                                    <p>We received a request to reset your password.</p>
                                    <p>Please click the button below to reset your password:</p>

                                    <p style="text-align: center; margin: 30px 0;">
                                        <a href="' . $resetLink . '" style="background-color: #4CAF50; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 4px; display: inline-block;">
                                            Reset Password
                                        </a>
                                    </p>

                                    <p>If you did not request this, please ignore this email.</p>
                                    <p>Thank you,<br>TaxShield Team</p>
                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td align="center" style="background-color: #f0f0f0; padding: 15px; font-size: 12px; color: #777777;">
                                    &copy; 2025 TaxShield. All rights reserved.
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>';
        $sent = sendEmail($ToEmail, $ToSubject, $EmailBody);
        if ($sent) {
            json(['ok' => true, 'message' => 'Reset instructions have been sent to your email.']);
        } else {
            json(['error' => 'Failed to send reset email'], 500);
        }
    }
}


// Reset password (public) - provide token + new_password
if ($path === '/reset-password' && $method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $token = $body['token'] ?? null;
    $new = $body['new_password'] ?? null;
    if (empty($token) || empty($new))
        json(['error' => 'token and new_password required'], 400);

    $row = $auth->verifyPasswordResetToken($token);
    if (!$row)
        json(['error' => 'invalid or expired token'], 400);

    $userId = $row['user_id'];
    $ok = $auth->setPasswordByUserId($userId, $new);
    if (!$ok)
        json(['error' => 'failed to set new password'], 500);

    // consume token
    $auth->consumePasswordResetToken($token);

    // audit log
    if (method_exists($audit, 'createLog')) {
        $audit->createLog($userId, 'reset_password', 'Password reset via token');
    } elseif (method_exists($audit, 'log')) {
        $audit->log($userId, 'reset_password', 'Password reset via token');
    }

    json(['ok' => true, 'message' => 'Password reset successful']);
}

// Get profile of the logged-in user
if ($path === '/profile' && $method === 'GET') {
    $payload = getAuthPayload($auth);
    if (!$payload)
        json(['error' => 'unauthorized'], 401);

    // payload['sub'] should be the user id (from createJWT)
    $userId = (int) $payload['sub'];

    try {
        $user = $auth->getUserById($userId);
        if (!$user)
            json(['error' => 'user not found'], 404);

        // hide any sensitive fields just in case (e.g. password_hash)
        unset($user['password_hash']);
        // unset($user['password']);

        json(['ok' => true, 'user' => $user]);
    } catch (Exception $e) {
        json(['error' => $e->getMessage()], 500);
    }
}

if ($path === '/check_user_exist' && $method === "GET") {
    try {
        if ($auth->anyUsersExist()) {
            json(['ok' => true, 'message' => 'At least one user exists']);
        } else {
            json(['ok' => false, 'message' => 'No users found']);
        }
    } catch (Exception $e) {
        json(['error' => $e->getMessage()], 500);
    }
}
