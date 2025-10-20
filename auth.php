<?php
require_once __DIR__ . '/config.php';

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_login(): void {
    if (!current_user()) {
        header('Location: ' . base_url('/admin/login.php'));
        exit;
    }
}

function login_user(array $user): void {
    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'email' => $user['email']
    ];
}

function logout_user(): void {
    unset($_SESSION['user']);
}

function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function rand_code(int $len = 6): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i=0; $i<$len; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet)-1)];
    }
    return $out;
}

function ensure_upload_dirs(): void {
    global $UPLOAD_DIR;
    if (!is_dir($UPLOAD_DIR)) {
        @mkdir($UPLOAD_DIR, 0777, true);
    }
}

