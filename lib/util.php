<?php
// ludo/lib/util.php

/** Read JSON body safely (returns array) */
function json_input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Emit JSON and exit */
function json_out(array $payload): void {
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

/** Sanitize a room/tournament code */
function clean_code(string $s): string {
    return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $s));
}

// other util functions here...

/**
 * Write an entry into the audit log
 */
function audit_log($userId, $action, $details = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, details) VALUES (?,?,?)");
        $stmt->execute([$userId, $action, $details]);
    } catch (Exception $e) {
        // fail silently to avoid blocking app
    }
}
