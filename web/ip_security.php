<?php
declare(strict_types=1);

// ============================================================
// ip_security.php — funções de bloqueio automático de IPs
// Incluir em auth.php: require_once __DIR__ . '/ip_security.php';
// ============================================================

/**
 * Verifica se um IP está bloqueado neste momento.
 */
function isIpBlocked(string $ip): bool
{
    $stmt = db()->prepare("
        SELECT id FROM BlockedIPs
        WHERE ip_address    = ?
          AND blocked_until > NOW()
        LIMIT 1
    ");
    $stmt->execute([$ip]);
    return $stmt->rowCount() > 0;
}

/**
 * Devolve quantas falhas de login houve para um IP nos últimos N minutos.
 */
function countRecentFailures(string $ip, int $minutes = 30): int
{
    $stmt = db()->prepare("
        SELECT COUNT(*) FROM LoginLogs
        WHERE ip_address  = ?
          AND success     = 0
          AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ");
    $stmt->execute([$ip, $minutes]);
    return (int) $stmt->fetchColumn();
}

/**
 * Bloqueia um IP por N minutos.
 * Se já estava bloqueado, estende o bloqueio.
 */
function autoBlockIp(string $ip, int $durationMinutes = 60, string $reason = 'Demasiadas tentativas falhadas'): void
{
    $stmt = db()->prepare("
        INSERT INTO BlockedIPs (ip_address, blocked_until, reason, blocked_by)
        VALUES (?, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?, 'auto')
        ON DUPLICATE KEY UPDATE
            blocked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE),
            blocked_at    = NOW(),
            reason        = ?,
            blocked_by    = 'auto'
    ");
    $stmt->execute([$ip, $durationMinutes, $reason, $durationMinutes, $reason]);
}

/**
 * Regista uma tentativa de login e aplica bloqueio automático se necessário.
 * Chamar APÓS verificar as credenciais.
 *
 * @param bool     $success      Login bem-sucedido?
 * @param string   $email        Email tentado
 * @param int|null $userId       ID do utilizador (null se não encontrado)
 * @param string   $failReason   Motivo da falha (ignorado em caso de sucesso)
 */
function recordLoginAttempt(
    bool   $success,
    string $email,
    ?int   $userId    = null,
    string $failReason = ''
): void {
    $ip        = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Guardar log
    $stmt = db()->prepare("
        INSERT INTO LoginLogs (user_id, email_attempted, ip_address, user_agent, success, failure_reason)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $email, $ip, $userAgent, $success ? 1 : 0, $success ? null : $failReason]);

    // Lógica de bloqueio automático em caso de falha
    if (!$success) {
        $failures = countRecentFailures($ip, minutes: 30);

        if ($failures >= 10) {
            autoBlockIp($ip, durationMinutes: 1440, reason: 'Bloqueio automático — 10+ falhas em 30 min');
        } elseif ($failures >= 5) {
            autoBlockIp($ip, durationMinutes: 60, reason: 'Bloqueio automático — 5+ falhas em 30 min');
        }
    }
}