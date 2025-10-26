<?php
// ludo/lib/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Require a logged-in user or exit with JSON 401 */
function auth_require(): int {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok'=>false,'error'=>'Authentication required']);
        exit;
    }
    return (int)$_SESSION['user_id'];
}

/** Get logged-in user id or null */
function auth_userid(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/** Is current user admin (1/0)? */
function auth_is_admin(): bool {
    return !empty($_SESSION['is_admin']);
}

/** Back-compat shims for older code */
function current_user(): ?int { return auth_userid(); }
function is_admin(): bool { return auth_is_admin(); }

/** Log a user in (store session flags) */
function login_user(int $userId, bool $isAdmin = false): void {
    $_SESSION['user_id'] = $userId;
    $_SESSION['is_admin'] = $isAdmin ? 1 : 0;
}

/** Destroy the session safely */
function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Register a new user with validation + hashing */
function register_user(PDO $pdo, string $username, string $phone, string $password): array {
    $username = trim($username);
    $phone = preg_replace('/\D+/', '', $phone);
    if ($username === '' || $phone === '' || $password === '') {
        return ['ok'=>false,'error'=>'All fields are required'];
    }
    if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
        return ['ok'=>false,'error'=>'Username: 3–20 chars, letters/digits/_ only'];
    }
    if (!preg_match('/^[0-9]{10,15}$/', $phone)) {
        return ['ok'=>false,'error'=>'Phone must be 10–15 digits'];
    }

    $st = $pdo->prepare("SELECT id FROM users WHERE username=? OR phone=? LIMIT 1");
    $st->execute([$username,$phone]);
    if ($st->fetch()) return ['ok'=>false,'error'=>'Username or phone already exists'];

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $st = $pdo->prepare("INSERT INTO users (username, phone, password_hash, is_admin, created_at) VALUES (?,?,?,?,NOW())");
    $ok = $st->execute([$username, $phone, $hash, 0]);
    if (!$ok) return ['ok'=>false,'error'=>'Registration failed'];

    return ['ok'=>true,'user_id'=>(int)$pdo->lastInsertId()];
}

/** Verify login (username OR phone) + password */
function verify_login(PDO $pdo, string $userOrPhone, string $password): array {
    $st = $pdo->prepare("SELECT id, username, phone, password_hash, is_admin FROM users WHERE username=? OR phone=? LIMIT 1");
    $st->execute([$userOrPhone, $userOrPhone]);
    $u = $st->fetch();
    if (!$u || !password_verify($password, $u['password_hash'])) {
        return ['ok'=>false,'error'=>'Invalid credentials'];
    }
    return ['ok'=>true,'user'=>[
        'id'=>(int)$u['id'],
        'username'=>$u['username'],
        'phone'=>$u['phone'],
        'is_admin'=>(int)$u['is_admin'] === 1
    ]];
}
