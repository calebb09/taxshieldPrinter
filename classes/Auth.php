<?php
// classes/Auth.php
class Auth
{
    private $pdo;
    private $config;

    public function __construct($pdo, $config)
    {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    // Create JWT (HS256)
    public function createJWT($userId, $username, $role, $expSeconds = 3600)
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode([
            'iss' => $this->config->jwt_issuer,
            'iat' => time(),
            'exp' => time() + $expSeconds,
            'sub' => $userId,
            'username' => $username,
            'role' => $role
        ]));
        $sig = hash_hmac('sha256', "$header.$payload", $this->config->jwt_secret, true);
        $signature = $this->base64UrlEncode($sig);
        return "$header.$payload.$signature";
    }

    public function verifyJWT($token)
    {
        if (!$token)
            return false;
        $parts = explode('.', $token);
        if (count($parts) !== 3)
            return false;
        list($headerB64, $payloadB64, $sigB64) = $parts;
        $sigCheck = $this->base64UrlEncode(hash_hmac('sha256', "$headerB64.$payloadB64", $this->config->jwt_secret, true));
        if (!hash_equals($sigCheck, $sigB64))
            return false;
        $payload = json_decode($this->base64UrlDecode($payloadB64), true);
        if (!$payload)
            return false;
        if (isset($payload['exp']) && time() > $payload['exp'])
            return false;
        return $payload;
    }

    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    private function base64UrlDecode($data)
    {
        $remainder = strlen($data) % 4;
        if ($remainder)
            $data .= str_repeat('=', 4 - $remainder);
        return base64_decode(strtr($data, '-_', '+/'));
    }

    // Register user
    public function register($username, $email, $password, $full_name = null, $role = 'employee')
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username,email,password_hash,full_name,role) VALUES (:u,:e,:p,:f,:r)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':u' => $username, ':e' => $email, ':p' => $hash, ':f' => $full_name, ':r' => $role]);
        return $this->pdo->lastInsertId();
    }

    public function login($usernameOrEmail, $password)
    {
        $sql = "SELECT * FROM users WHERE username = :x OR email = :x LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':x' => $usernameOrEmail]);
        $user = $stmt->fetch();
        if (!$user)
            return false;
        if (!password_verify($password, $user['password_hash']))
            return false;
        return $user;
    }

    //////////////change password and reset password
    // inside class Auth { ... add these methods ...

    /**
     * Change password for authenticated user (requires current password)
     */
    public function changePassword($userId, $currentPassword, $newPassword)
    {
        // fetch user
        $stmt = $this->pdo->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user)
            return false;

        if (!password_verify($currentPassword, $user['password_hash'])) {
            // current password mismatch
            return false;
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $u = $this->pdo->prepare("UPDATE users SET password_hash = :ph WHERE id = :id");
        $u->execute([':ph' => $newHash, ':id' => $userId]);
        return true;
    }

    /**
     * Create a password reset token for an email. Returns array with token (plain) and expires_at on success, or false if no user.
     * IMPORTANT: In production do not return token in API response — send by email instead.
     */
    // classes/Auth.php
    public function createPasswordResetToken($email)
    {
        // check if user exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false; // no user found
        }

        $token = bin2hex(random_bytes(32));
        // $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

        $stmt = $this->pdo->prepare("
        INSERT INTO password_resets (user_id, token) 
        VALUES (:user_id, :token)
    ");
        $stmt->execute([
            ':user_id' => $user['id'],
            ':token' => $token,

        ]);

        return [
            'token' => $token,
            'user_id' => $user['id'],

        ];
    }




    /**
     * Verify a password reset token and return the record including user_id if valid, or false if invalid/expired.
     */
    public function verifyPasswordResetToken($token)
    {
        $stmt = $this->pdo->prepare("
        SELECT pr.id, pr.user_id, pr.expires_at, u.email
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token = :token
          AND pr.expires_at >= NOW()
        LIMIT 1
    ");
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    }

    /**
     * Consume (delete) a password reset token after use
     */
    public function consumePasswordResetToken($token)
    {
        $stmt = $this->pdo->prepare("DELETE FROM password_resets WHERE token = :token");
        return $stmt->execute([':token' => $token]);
    }


    /**
     * Set password by user id (used during reset)
     */
    public function setPasswordByUserId($userId, $newPassword)
    {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("UPDATE users SET password_hash = :ph WHERE id = :id");
        return $stmt->execute([':ph' => $newHash, ':id' => $userId]);
    }

    /**
 * Return user record by id (public fields only)
 */
    public function getUserById($id) {
        $stmt = $this->pdo->prepare("
            SELECT id, username, email, full_name, role, created_at
            FROM users WHERE id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
