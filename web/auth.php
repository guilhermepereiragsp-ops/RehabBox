<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

function start_secure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }
}

function verify_recaptcha(string $token): bool {
    if ($token === '') {
        return false;
    }

    $data = http_build_query([
        'secret' => RECAPTCHA_SECRET_KEY,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $data,
            'timeout' => 10,
        ],
    ]);

    $result = file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);

    if ($result === false) {
        return false;
    }

    $json = json_decode($result, true);

    return isset($json['success']) && $json['success'] === true;
}

function find_user_by_email(string $email): ?array {
    $stmt = db()->prepare("
        SELECT id, name, email, password, role, is_active
        FROM Users
        WHERE email = :email
        LIMIT 1
    ");

    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function login_user(array $user): void {
    start_secure_session();
    session_regenerate_id(true);

    $_SESSION['user_id']    = (int) $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];
}

function redirect_by_role(): void {
    $role = $_SESSION['user_role'] ?? '';

    if ($role === 'admin') {
        header('Location: menu_admin.php');
    } elseif ($role === 'doctor') {
        header('Location: menu_medico.php');
    } elseif ($role === 'patient') {
        header('Location: menu_paciente.php');
    } else {
        header('Location: login.php');
    }

    exit;
}

function require_login(): void {
    start_secure_session();

    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function require_role(array $roles): void {
    require_login();

    if (!in_array($_SESSION['user_role'] ?? '', $roles, true)) {
        redirect_by_role();
    }
}

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function log_login_attempt(?int $userId, string $email, bool $success, ?string $reason = null): void {
    $stmt = db()->prepare("
        INSERT INTO LoginLogs
            (user_id, email_attempted, ip_address, user_agent, success, failure_reason)
        VALUES
            (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $userId,
        $email,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        $success ? 1 : 0,
        $reason,
    ]);
}