<?php
declare(strict_types=1);

// ============================================================
// admin_block_ip.php — bloquear/desbloquear IPs manualmente
// Apenas administradores. Aceita POST.
// ============================================================

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ip_security.php';
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

$action = trim($_POST['action'] ?? '');
$ip     = trim($_POST['ip']     ?? '');
$hours  = max(1, min(720, (int) ($_POST['hours'] ?? 24)));

// Validar IP
if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    http_response_code(400);
    exit('IP inválido.');
}

if ($action === 'block') {
    $stmt = db()->prepare("
        INSERT INTO BlockedIPs (ip_address, blocked_until, reason, blocked_by)
        VALUES (?, DATE_ADD(NOW(), INTERVAL ? HOUR), 'Bloqueado manualmente pelo admin', 'admin')
        ON DUPLICATE KEY UPDATE
            blocked_until = DATE_ADD(NOW(), INTERVAL ? HOUR),
            blocked_at    = NOW(),
            reason        = 'Bloqueado manualmente pelo admin',
            blocked_by    = 'admin'
    ");
    $stmt->execute([$ip, $hours, $hours]);
    header('Location: admin_logs.php?msg=blocked');

} elseif ($action === 'unblock') {
    $stmt = db()->prepare("DELETE FROM BlockedIPs WHERE ip_address = ?");
    $stmt->execute([$ip]);
    header('Location: admin_logs.php?msg=unblocked');

} else {
    http_response_code(400);
    exit('Acção inválida.');
}
exit;