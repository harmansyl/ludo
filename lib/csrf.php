<?php
// ludo/lib/csrf.php
// --- already defined: csrf_generate(), csrf_verify(), get_csrf_token() ---

function csrf_token() {
    return htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Generate/return CSRF token for this session */
function get_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Verify CSRF token (JSON POSTs send X-CSRF-Token header) */
function csrf_verify(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return; // check only POST
    $expected = $_SESSION['csrf_token'] ?? '';
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$expected || !$provided || !hash_equals($expected, $provided)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok'=>false,'error'=>'CSRF token mismatch']);
        exit;
    }
}
