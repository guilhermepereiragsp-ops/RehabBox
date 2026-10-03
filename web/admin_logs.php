<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ip_security.php';
require_role(['admin']);

if (!function_exists('e')) {
    function e($value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$userName = $_SESSION['user_name'] ?? 'Admin';
$avatar   = strtoupper(mb_substr($userName, 0, 1, 'UTF-8'));

// ── Filtros
$filterResult = trim($_GET['result'] ?? '');
$filterIp     = trim($_GET['ip']     ?? '');
$filterEmail  = trim($_GET['email']  ?? '');
$filterDate   = trim($_GET['date']   ?? '');

// ── Paginação
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

// ── WHERE dinâmico
$where  = ['1=1'];
$params = [];

if ($filterResult === 'success') {
    $where[] = 'l.success = 1';
} elseif ($filterResult === 'fail') {
    $where[] = 'l.success = 0';
}
if ($filterIp !== '') {
    $where[] = 'l.ip_address LIKE ?';
    $params[] = '%' . $filterIp . '%';
}
if ($filterEmail !== '') {
    $where[] = 'l.email_attempted LIKE ?';
    $params[] = '%' . $filterEmail . '%';
}
if ($filterDate !== '') {
    $where[] = 'DATE(l.created_at) = ?';
    $params[] = $filterDate;
}

$whereSQL = implode(' AND ', $where);

// ── Exportar CSV
if (($_GET['export'] ?? '') === 'csv') {
    $exportStmt = db()->prepare("
        SELECT l.id, l.created_at, l.email_attempted,
               COALESCE(u.name, 'Desconhecido') AS user_name,
               u.role, l.ip_address, l.user_agent, l.success, l.failure_reason
        FROM LoginLogs l
        LEFT JOIN Users u ON u.id = l.user_id
        WHERE $whereSQL
        ORDER BY l.created_at DESC
    ");
    $exportStmt->execute($params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tmts_login_logs_' . date('Ymd_His') . '.csv"');

    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Data/Hora', 'Email tentado', 'Utilizador', 'Role', 'IP', 'Sucesso', 'Motivo', 'User Agent']);

    foreach ($exportStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        fputcsv($out, [
            $row['id'], $row['created_at'], $row['email_attempted'],
            $row['user_name'], $row['role'], $row['ip_address'],
            (int) $row['success'] === 1 ? 'Sucesso' : 'Falha',
            $row['failure_reason'], $row['user_agent'],
        ]);
    }
    fclose($out);
    exit;
}

// ── Total de registos
$countStmt = db()->prepare("
    SELECT COUNT(*) FROM LoginLogs l LEFT JOIN Users u ON u.id = l.user_id WHERE $whereSQL
");
$countStmt->execute($params);
$totalRows  = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));

// ── Logs da página atual
$logStmt = db()->prepare("
    SELECT l.id, l.created_at, l.email_attempted, l.ip_address,
           l.user_agent, l.success, l.failure_reason, u.name, u.role
    FROM LoginLogs l
    LEFT JOIN Users u ON u.id = l.user_id
    WHERE $whereSQL
    ORDER BY l.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$logStmt->execute($params);
$logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Estatísticas 24h
$stats = db()->query("
    SELECT COUNT(*) AS total,
           SUM(success = 1) AS success_count,
           SUM(success = 0) AS fail_count,
           COUNT(DISTINCT ip_address) AS unique_ips
    FROM LoginLogs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
")->fetch(PDO::FETCH_ASSOC);

// ── Comparação 24h anteriores
$prev = db()->query("
    SELECT COUNT(*) AS total, SUM(success = 0) AS fail_count
    FROM LoginLogs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
      AND created_at <  DATE_SUB(NOW(), INTERVAL 24 HOUR)
")->fetch(PDO::FETCH_ASSOC);

// ── IPs suspeitos (≥3 falhas em 24h)
$suspectIps = db()->query("
    SELECT l.ip_address, COUNT(*) AS attempts,
           MAX(b.blocked_until) AS blocked_until
    FROM LoginLogs l
    LEFT JOIN BlockedIPs b ON b.ip_address = l.ip_address AND b.blocked_until > NOW()
    WHERE l.success = 0 AND l.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY l.ip_address
    HAVING attempts >= 3
    ORDER BY attempts DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// ── IPs bloqueados
$blockedIps = db()->query("
    SELECT ip_address, blocked_until, blocked_by, reason
    FROM BlockedIPs
    WHERE blocked_until > NOW()
    ORDER BY blocked_until DESC
")->fetchAll(PDO::FETCH_ASSOC);

// ── Feedback após bloqueio/desbloqueio
$feedbackMsg = match ($_GET['msg'] ?? '') {
    'blocked'   => ['type' => 'success', 'text' => 'IP bloqueado com sucesso.'],
    'unblocked' => ['type' => 'success', 'text' => 'IP desbloqueado com sucesso.'],
    default     => null,
};

$blockedNow  = array_column($blockedIps, null, 'ip_address');
$total       = max(1, (int) ($stats['total'] ?? 1));
$successRate = round(((int) ($stats['success_count'] ?? 0)) / $total * 100);
$deltaTotal  = ((int) ($stats['total'] ?? 0))      - ((int) ($prev['total']      ?? 0));
$deltaFail   = ((int) ($stats['fail_count'] ?? 0)) - ((int) ($prev['fail_count'] ?? 0));
$hasAlert    = count($suspectIps) > 0;
// ── Total de clientes (para badge da sidebar)
$totalClients = (int) db()->query("SELECT COUNT(*) FROM Users WHERE role = 'patient'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>RehabBox — Logs de Segurança</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="./assets/images/logo1.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,300;12..96,400;12..96,500;12..96,600;12..96,700;12..96,800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/style.css">
    <style>
        /* Apenas estilos específicos desta página que não existem no style.css global */

        /* Pills de estado */
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: .74rem;
            font-weight: 700;
        }
        .pill-success { color: #15803d; background: rgba(34,197,94,.12); }
        .pill-fail    { color: #b45309; background: rgba(245,158,11,.14); }
        .pill-blocked { color: var(--red, #c0392b); background: rgba(232,64,64,.10); }

        /* Linha suspeita na tabela */
        .row-suspect td { background: rgba(232,64,64,.04); }
        .row-suspect:hover td { background: rgba(232,64,64,.08); }

        /* IP em destaque */
        .ip     { font-family: monospace; white-space: nowrap; }
        .ip-bad { font-family: monospace; color: var(--red, #c0392b); }

        /* Mini barra de progresso nos stat cards */
        .mini-bar-wrap {
            height: 4px;
            background: var(--border, rgba(0,0,0,.08));
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }
        .mini-bar {
            height: 100%;
            border-radius: 2px;
            background: var(--red, #c0392b);
        }

        /* Bloco de IP suspeito */
        .suspect-item {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(232,64,64,.06);
            border: 1px solid rgba(232,64,64,.18);
            padding: 9px 14px;
            border-radius: 10px;
            margin-bottom: 8px;
            font-size: .875rem;
        }
        .suspect-ip    { font-family: monospace; flex: 1; }
        .suspect-count { color: var(--red, #c0392b); font-weight: 700; min-width: 64px; text-align: right; }

        /* Bloco de IP bloqueado */
        .blocked-item {
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid var(--border);
            padding: 9px 14px;
            border-radius: 10px;
            margin-bottom: 8px;
            font-size: .875rem;
        }
        .blocked-ip    { font-family: monospace; flex: 1; }
        .blocked-until { color: var(--text-muted); font-size: .78rem; }

        /* Botões de ação pequenos */
        .btn-sm {
            padding: 4px 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
            font-size: .78rem;
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-sm:hover { background: var(--surface-2); }
        .btn-sm-danger  { border-color: rgba(232,64,64,.35); color: var(--red, #c0392b); }
        .btn-sm-danger:hover  { background: rgba(232,64,64,.08); }
        .btn-sm-success { border-color: rgba(22,163,74,.35); color: #16a34a; }
        .btn-sm-success:hover { background: rgba(22,163,74,.08); }

        /* Alerta de IPs suspeitos */
        .alert-banner {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(232,64,64,.07);
            border: 1px solid rgba(232,64,64,.20);
            border-radius: 12px;
            padding: 12px 18px;
            margin-bottom: 20px;
            font-size: .9rem;
            color: var(--red, #c0392b);
        }
        .alert-banner a { margin-left: auto; color: inherit; font-weight: 700; text-decoration: underline; }

        /* Feedback de ação */
        .feedback {
            padding: 10px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: .875rem;
        }
        .feedback-success {
            background: rgba(22,163,74,.08);
            border: 1px solid rgba(22,163,74,.22);
            color: #15803d;
        }

        /* Filtros rápidos */
        .quick-links { display: flex; flex-direction: column; gap: 7px; }
        .quick-links a {
            padding: 9px 14px;
            border-radius: 9px;
            border: 1px solid var(--border);
            color: var(--text);
            text-decoration: none;
            font-size: .875rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background .15s;
        }
        .quick-links a:hover { background: var(--surface-2); }

        /* Formulário de pesquisa */
        .log-filters {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr auto auto;
            gap: 10px;
            align-items: end;
        }
        .log-filters label {
            display: block;
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--text-muted);
            margin-bottom: 5px;
            font-weight: 700;
        }
        .log-filters input,
        .log-filters select {
            width: 100%;
            padding: 9px 11px;
            border-radius: 9px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
            font-size: .875rem;
            font-family: inherit;
        }

        /* Layout de duas colunas para IPs + filtros rápidos */
        .logs-split {
            display: grid;
            grid-template-columns: 1.5fr .8fr;
            gap: 16px;
            margin-bottom: 20px;
        }

        @media (max-width: 1100px) {
            .logs-split, .log-filters { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="cursor-dot"   id="cursorDot"></div>
<div class="cursor-cross" id="cursorCross"></div>
<div class="layout">

    <!-- ══════ SIDEBAR ══════ -->
    <aside class="sidebar">

      <!-- Logo usa o PNG real -->
      <a href="menu_admin.php" class="sidebar-logo">
        <img src="./assets/images/logo1.png" alt="RehabBox logo">
        <span class="logo-wordmark">Rehab<span>Box</span></span>
      </a>

      <div class="sidebar-label">Principal</div>

      <a href="#" class="nav-item active">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        Dashboard
      </a>

      <a href="#" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Clientes
        <span class="badge" id="clientsBadge"><?= $totalClients ?></span>
      </a>

      <!-- Pedidos de retirada — badge vermelho pulsante -->
      <a href="#" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
        Sessões
      </a>

      <a href="#" class="nav-item">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Pedidos de Revogação
        <span class="badge-danger" id="withdrawalNavBadge" style="display:none;"></span>
      </a>

      <a href="admin_logs.php" class="nav-item">
  <svg viewBox="0 0 24 24">
    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
  </svg>
  Logs de Segurança
</a>

      <div class="sidebar-divider"></div>
      <div class="sidebar-label">Sistema</div>

      <a href="#" class="nav-item">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 1.41 14.14M4.93 4.93a10 10 0 0 0-1.41 14.14"/></svg>
        Hardware
      </a>
      <a href="#" class="nav-item">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M2 12h3m14 0h3M12 2v3m0 14v3M4.22 4.22l2.12 2.12m11.32 11.32l2.12 2.12M4.22 19.78l2.12-2.12m11.32-11.32l2.12-2.12"/></svg>
        Configurações
      </a>

      <div class="sidebar-footer">
        <div class="admin-pill">
          <div class="admin-avatar"><?= e($avatar) ?></div>
          <div>
            <div class="admin-name"><?= e($userName) ?></div>
            <div class="admin-role">Administrador</div>
          </div>
          <a class="admin-logout" title="Sair" href="logout.php">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          </a>
        </div>
      </div>
    </aside>

    <!-- ── Main ── -->
    <div class="main">

        <!-- Topbar -->
        <div class="topbar">
            <span class="topbar-title">Logs de Segurança</span>
            <span class="topbar-sep"></span>
            <span class="topbar-breadcrumb">RehabBox / Admin / Logs de Segurança</span>
            <div class="topbar-right">
                <span class="topbar-date" id="topbarDate"></span>
            </div>
        </div>

        <!-- Page content -->
        <div class="page">

            <!-- Cabeçalho -->
            <div class="page-header">
                <div>
                    <h1>Logs de <em>Segurança</em></h1>
                    <p>Auditoria de tentativas de login, acessos falhados e IPs suspeitos.</p>
                </div>
                <a class="btn-primary"
                   href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>">
                    <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7,10 12,15 17,10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Exportar CSV
                </a>
            </div>

            <!-- Feedback -->
            <?php if ($feedbackMsg): ?>
                <div class="feedback feedback-<?= e($feedbackMsg['type']) ?>">
                    <?= e($feedbackMsg['text']) ?>
                </div>
            <?php endif; ?>

            <!-- Alerta IPs suspeitos -->
            <?php if ($hasAlert): ?>
                <div class="alert-banner">
                    ⚠️ <strong><?= count($suspectIps) ?> IP(s) suspeito(s)</strong> com múltiplas falhas nas últimas 24h.
                    <a href="#suspicious-ips">Ver abaixo ↓</a>
                </div>
            <?php endif; ?>

            <!-- Stat cards — mesma classe do menu_admin -->
            <div class="stats-grid">

                <div class="stat-card" style="--accent:var(--teal-d)">
                    <div class="stat-icon" style="background:rgba(70,129,137,.12);color:var(--teal-d)">
                        <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <div class="stat-label">Eventos nas últimas 24h</div>
                    <div class="stat-value"><?= (int)($stats['total'] ?? 0) ?> <span>eventos</span></div>
                    <?php if ($deltaTotal !== 0): ?>
                        <div class="stat-delta <?= $deltaTotal > 0 ? 'delta-up' : 'delta-down' ?>">
                            <?= $deltaTotal > 0 ? '+' : '' ?><?= $deltaTotal ?> vs 24h anteriores
                        </div>
                    <?php endif; ?>
                </div>

                <div class="stat-card" style="--accent:#22C55E">
                    <div class="stat-icon" style="background:rgba(34,197,94,.10);color:#15803D">
                        <svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>
                    </div>
                    <div class="stat-label">Logins com sucesso</div>
                    <div class="stat-value"><?= (int)($stats['success_count'] ?? 0) ?> <span>logins</span></div>
                    <div class="stat-delta delta-up"><?= $successRate ?>% taxa de sucesso</div>
                </div>

                <div class="stat-card" style="--accent:#E84040">
                    <div class="stat-icon" style="background:rgba(232,64,64,.10);color:var(--red,#c0392b)">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    </div>
                    <div class="stat-label">Tentativas falhadas</div>
                    <div class="stat-value" style="color:var(--red,#c0392b)">
                        <?= (int)($stats['fail_count'] ?? 0) ?> <span>falhas</span>
                    </div>
                    <?php if ($deltaFail !== 0): ?>
                        <div class="stat-delta <?= $deltaFail > 0 ? 'delta-down' : 'delta-up' ?>">
                            <?= $deltaFail > 0 ? '+' : '' ?><?= $deltaFail ?> vs 24h anteriores
                        </div>
                    <?php endif; ?>
                    <div class="mini-bar-wrap">
                        <div class="mini-bar" style="width:<?= min(100, 100 - $successRate) ?>%"></div>
                    </div>
                </div>

                <div class="stat-card" style="--accent:#3B82F6">
                    <div class="stat-icon" style="background:rgba(59,130,246,.10);color:#1D4ED8">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    </div>
                    <div class="stat-label">IPs únicos</div>
                    <div class="stat-value"><?= (int)($stats['unique_ips'] ?? 0) ?> <span>IPs</span></div>
                    <?php if (count($blockedIps) > 0): ?>
                        <div class="stat-delta delta-down"><?= count($blockedIps) ?> bloqueado(s) agora</div>
                    <?php endif; ?>
                </div>

            </div><!-- /stats-grid -->

            <!-- IPs suspeitos + Filtros rápidos -->
            <div class="logs-split" id="suspicious-ips">

                <div class="metric-card">
                    <div class="metric-card-header">
                        <span class="metric-card-title">IPs suspeitos nas últimas 24h</span>
                        <?php if ($hasAlert): ?>
                            <span class="metric-pill-danger"><?= count($suspectIps) ?> suspeito(s)</span>
                        <?php else: ?>
                            <span class="metric-pill">Sem alertas ✓</span>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($suspectIps)): ?>
                        <p style="color:var(--text-muted);font-size:.875rem">Nenhum IP suspeito detetado.</p>
                    <?php else: ?>
                        <?php foreach ($suspectIps as $ip): ?>
                            <div class="suspect-item">
                                <span class="suspect-ip"><?= e($ip['ip_address']) ?></span>
                                <span class="suspect-count"><?= (int)$ip['attempts'] ?> falhas</span>

                                <?php if (!empty($ip['blocked_until'])): ?>
                                    <span class="pill pill-blocked">Bloqueado</span>
                                    <form method="POST" action="admin_block_ip.php" style="margin:0">
                                        <input type="hidden" name="action" value="unblock">
                                        <input type="hidden" name="ip" value="<?= e($ip['ip_address']) ?>">
                                        <button type="submit" class="btn-sm btn-sm-success">Desbloquear</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="admin_block_ip.php"
                                          style="display:flex;gap:6px;margin:0;align-items:center">
                                        <input type="hidden" name="action" value="block">
                                        <input type="hidden" name="ip" value="<?= e($ip['ip_address']) ?>">
                                        <select name="hours" class="btn-sm" style="padding:3px 6px">
                                            <option value="1">1h</option>
                                            <option value="24" selected>24h</option>
                                            <option value="168">7 dias</option>
                                            <option value="720">30 dias</option>
                                        </select>
                                        <button type="submit" class="btn-sm btn-sm-danger">Bloquear</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($blockedIps)): ?>
                        <div class="metric-card-header" style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
                            <span class="metric-card-title">IPs atualmente bloqueados</span>
                        </div>
                        <?php foreach ($blockedIps as $b): ?>
                            <div class="blocked-item">
                                <span class="blocked-ip"><?= e($b['ip_address']) ?></span>
                                <span class="blocked-until">
                                    até <?= e(date('d/m H:i', strtotime($b['blocked_until']))) ?>
                                    — <?= e($b['blocked_by']) ?>
                                </span>
                                <form method="POST" action="admin_block_ip.php" style="margin:0">
                                    <input type="hidden" name="action" value="unblock">
                                    <input type="hidden" name="ip" value="<?= e($b['ip_address']) ?>">
                                    <button type="submit" class="btn-sm btn-sm-success">Desbloquear</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="metric-card">
                    <div class="metric-card-header">
                        <span class="metric-card-title">Filtros rápidos</span>
                    </div>
                    <div class="quick-links">
                        <a href="admin_logs.php?result=fail">🔴 Ver apenas falhas</a>
                        <a href="admin_logs.php?result=success">🟢 Ver apenas sucessos</a>
                        <a href="admin_logs.php?date=<?= date('Y-m-d') ?>">📅 Eventos de hoje</a>
                        <a href="admin_logs.php">✕ Limpar filtros</a>
                    </div>
                </div>

            </div><!-- /logs-split -->

            <!-- Pesquisa -->
            <div class="metric-card" style="margin-bottom:20px">
                <div class="metric-card-header">
                    <span class="metric-card-title">Pesquisar logs</span>
                </div>
                <form method="GET" action="admin_logs.php">
                    <div class="log-filters">
                        <div>
                            <label>Email</label>
                            <input type="text" name="email" placeholder="ex: admin@tmts.pt" value="<?= e($filterEmail) ?>">
                        </div>
                        <div>
                            <label>IP</label>
                            <input type="text" name="ip" placeholder="ex: 127.0.0.1" value="<?= e($filterIp) ?>">
                        </div>
                        <div>
                            <label>Resultado</label>
                            <select name="result">
                                <option value="">Todos</option>
                                <option value="success" <?= $filterResult === 'success' ? 'selected' : '' ?>>Sucesso</option>
                                <option value="fail"    <?= $filterResult === 'fail'    ? 'selected' : '' ?>>Falha</option>
                            </select>
                        </div>
                        <div>
                            <label>Data</label>
                            <input type="date" name="date" value="<?= e($filterDate) ?>">
                        </div>
                        <button class="btn-primary" type="submit" style="white-space:nowrap">Filtrar</button>
                        <a class="btn-ghost-sm" href="admin_logs.php" style="display:flex;align-items:center;justify-content:center;text-decoration:none">Limpar</a>
                    </div>
                </form>
            </div>

            <!-- Contagem -->
            <div style="margin-bottom:12px;color:var(--text-muted);font-size:.875rem">
                <?= number_format($totalRows) ?> registos encontrados
            </div>

            <!-- Tabela -->
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Data/Hora</th>
                            <th>Email tentado</th>
                            <th>Utilizador</th>
                            <th>IP</th>
                            <th>Resultado</th>
                            <th>Motivo</th>
                            <th>Dispositivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">
                                    Nenhum log encontrado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr class="<?= (int)$log['success'] === 0 ? 'row-suspect' : '' ?>">
                                    <td style="color:var(--text-muted)"><?= (int)$log['id'] ?></td>

                                    <td>
                                        <?= e(date('d/m/Y', strtotime($log['created_at']))) ?><br>
                                        <span style="color:var(--text-muted);font-size:.8rem">
                                            <?= e(date('H:i:s', strtotime($log['created_at']))) ?>
                                        </span>
                                    </td>

                                    <td><?= e($log['email_attempted']) ?></td>

                                    <td>
                                        <?php if (!empty($log['name'])): ?>
                                            <?= e($log['name']) ?><br>
                                            <span style="color:var(--text-muted);font-size:.8rem"><?= e($log['role']) ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted)">Desconhecido</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span class="<?= isset($blockedNow[$log['ip_address']]) ? 'ip-bad' : 'ip' ?>">
                                            <?= e($log['ip_address']) ?>
                                        </span>
                                        <?php if (isset($blockedNow[$log['ip_address']])): ?>
                                            <br><span class="pill pill-blocked" style="margin-top:3px">Bloqueado</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if ((int)$log['success'] === 1): ?>
                                            <span class="pill pill-success">✓ Sucesso</span>
                                        <?php else: ?>
                                            <span class="pill pill-fail">✕ Falha</span>
                                        <?php endif; ?>
                                    </td>

                                    <td><?= e($log['failure_reason'] ?: '—') ?></td>

                                    <td style="max-width:240px;color:var(--text-muted);font-size:.8rem">
                                        <?= e(mb_strimwidth($log['user_agent'] ?? '—', 0, 70, '…')) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Paginação — mesma estrutura do menu_admin -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <span class="page-info">
                            Página <?= $page ?> de <?= $totalPages ?>
                        </span>
                        <?php if ($page > 1): ?>
                            <a class="page-btn"
                               href="?<?= e(http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">
                                <svg viewBox="0 0 24 24"><polyline points="15,18 9,12 15,6"/></svg>
                            </a>
                        <?php endif; ?>
                        <button class="page-btn current" disabled><?= $page ?></button>
                        <?php if ($page < $totalPages): ?>
                            <a class="page-btn"
                               href="?<?= e(http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">
                                <svg viewBox="0 0 24 24"><polyline points="9,18 15,12 9,6"/></svg>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div><!-- /table-wrap -->

        </div><!-- /page -->
    </div><!-- /main -->
</div><!-- /layout -->

<script>
(() => {
    const dateEl = document.getElementById('topbarDate');

    if (dateEl) {
        dateEl.textContent = new Date().toLocaleDateString('pt-PT', {
            weekday: 'short',
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    }

    const dot = document.getElementById('cursorDot');
    const cross = document.getElementById('cursorCross');

    if (dot && cross) {
        document.addEventListener('mousemove', e => {
            dot.style.left = e.clientX + 'px';
            dot.style.top = e.clientY + 'px';
            cross.style.left = e.clientX + 'px';
            cross.style.top = e.clientY + 'px';
        }, { passive: true });

        document.querySelectorAll(
            'a, button, .nav-item, .stat-card, .metric-card, .table-wrap tr, .suspect-item, .blocked-item'
        ).forEach(el => {
            el.addEventListener('mouseenter', () => {
                dot.style.width = '12px';
                dot.style.height = '12px';
                dot.style.background = 'var(--teal-d)';
                cross.style.opacity = '0.2';
            });

            el.addEventListener('mouseleave', () => {
                dot.style.width = '6px';
                dot.style.height = '6px';
                dot.style.background = 'var(--ink)';
                cross.style.opacity = '1';
            });
        });
    }
})();
</script>
</body>
</html>