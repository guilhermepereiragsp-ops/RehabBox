<?php
require_once __DIR__ . '/auth.php';
require_role(['admin']);

$userName = $_SESSION['user_name'] ?? 'Admin';
$avatar   = strtoupper(mb_substr($userName, 0, 1, 'UTF-8'));

/* ═══════════════════════════════════════
   ACÇÃO: APROVAR / REJEITAR REGISTO
═══════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action'])) {

    $userId = (int) ($_POST['user_id'] ?? 0);

    if ($_POST['_action'] === 'approve' && $userId > 0) {
        $confirmedRole = $_POST['confirmed_role'] ?? '';
        if (!in_array($confirmedRole, ['patient', 'doctor'], true)) {
            die('Role inválido');
        }

        db()->beginTransaction();
        try {
            db()->prepare("UPDATE Users SET role = ?, is_active = 1 WHERE id = ? AND role = 'pending'")
               ->execute([$confirmedRole, $userId]);

            if ($confirmedRole === 'patient') {
                db()->prepare("INSERT IGNORE INTO Patient (id) VALUES (?)")->execute([$userId]);
            } elseif ($confirmedRole === 'doctor') {
                db()->prepare("INSERT IGNORE INTO Doctor (id) VALUES (?)")->execute([$userId]);
            }

            db()->prepare("DELETE FROM PendingRegistrations WHERE user_id = ?")
               ->execute([$userId]);

            db()->commit();

            $u = db()->prepare("SELECT name, email FROM Users WHERE id = ?");
            $u->execute([$userId]);
            $u = $u->fetch();
            if ($u) {
                notify_user_approved($u['name'], $u['email'], $confirmedRole);
            }

        } catch (Exception $e) {
            db()->rollBack();
        }

    } elseif ($_POST['_action'] === 'reject' && $userId > 0) {
        $u = db()->prepare("SELECT name, email FROM Users WHERE id = ?");
        $u->execute([$userId]);
        $u = $u->fetch();

        db()->prepare("DELETE FROM Users WHERE id = ? AND role = 'pending'")
           ->execute([$userId]);

        if ($u) {
            notify_user_rejected($u['name'], $u['email']);
        }

    } elseif ($_POST['_action'] === 'revocation_approve' && $userId > 0) {
        db()->prepare("UPDATE ConsentRevocationRequest SET status='approved', handled_by=?, handled_at=NOW() WHERE patient_id=? AND status='pending'")
           ->execute([$_SESSION['user_id'], $userId]);
        db()->prepare("DELETE FROM Users WHERE id = ?")->execute([$userId]);

    } elseif ($_POST['_action'] === 'revocation_reject' && $userId > 0) {
        db()->prepare("UPDATE ConsentRevocationRequest SET status='rejected', handled_by=?, handled_at=NOW() WHERE patient_id=? AND status='pending'")
           ->execute([$_SESSION['user_id'], $userId]);
    }

    header('Location: menu_admin.php');
    exit;
}

/* ═══════════════════════════════════════
   DADOS — PACIENTES
═══════════════════════════════════════ */
$stmt = db()->query("
    SELECT
        u.id, u.name, u.email, u.is_active,
        p.difficulty, p.doctor_id, p.disease_status,
        COUNT(DISTINCT gs.id)              AS sessions,
        MAX(gs.session_date)               AS last_session,
        COALESCE(ROUND(AVG(g.final_score)),0) AS avg_score
    FROM Patient p
    INNER JOIN Users u ON u.id = p.id
    LEFT JOIN GameSession gs ON gs.patient_id = p.id
    LEFT JOIN Game g ON g.game_group_id = gs.id
    WHERE u.role = 'patient'
    GROUP BY u.id, u.name, u.email, u.is_active, p.difficulty, p.doctor_id, p.disease_status
    ORDER BY u.name ASC
");
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ═══════════════════════════════════════
   DADOS — PENDENTES
═══════════════════════════════════════ */
$pendingStmt = db()->query("
    SELECT
        u.id, u.name, u.email,
        pr.requested_role, pr.justification, pr.submitted_at
    FROM PendingRegistrations pr
    INNER JOIN Users u ON u.id = pr.user_id
    ORDER BY pr.submitted_at DESC
");
$pendingRegistrations = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
$pendingCount = count($pendingRegistrations);

/* ═══════════════════════════════════════
   DADOS — ESTATÍSTICAS
═══════════════════════════════════════ */
$statsStmt = db()->query("
SELECT
    (SELECT COUNT(*) FROM Patient) AS total_patients,
    (SELECT COUNT(DISTINCT patient_id) FROM GameSession) AS active_patients,
    (SELECT COUNT(*) FROM GameSession WHERE DATE(session_date) = CURDATE()) AS sessions_today,
    (SELECT COALESCE(ROUND(AVG(session_avg)),0)
     FROM (SELECT AVG(g.final_score) AS session_avg
           FROM GameSession gs LEFT JOIN Game g ON g.game_group_id = gs.id
           GROUP BY gs.id) t
    ) AS avg_precision,
    (SELECT COALESCE(ROUND(AVG(session_duration)),0)
     FROM (SELECT SUM(g.duration_minutes) AS session_duration
           FROM GameSession gs LEFT JOIN Game g ON g.game_group_id = gs.id
           GROUP BY gs.id) t
    ) AS avg_duration
");
$stats = $statsStmt->fetch();

/* ═══════════════════════════════════════
   DADOS — JOGOS MAIS USADOS
═══════════════════════════════════════ */
$gamesStmt = db()->query("
    SELECT name, COUNT(*) AS total FROM Game GROUP BY name ORDER BY total DESC LIMIT 3
");
$gamesRows   = $gamesStmt->fetchAll();
$totalGames  = array_sum(array_column($gamesRows, 'total'));
$mostUsedGames = [];
foreach ($gamesRows as $game) {
    $percent = $totalGames > 0 ? round(((int)$game['total'] / $totalGames) * 100) : 0;
    $mostUsedGames[] = ['name' => $game['name'], 'percent' => $percent];
}

/* ═══════════════════════════════════════
   DADOS — CHART 7 DIAS
═══════════════════════════════════════ */
$chartStmt = db()->query("
    SELECT DATE(session_date) AS game_date, COUNT(*) AS total
    FROM GameSession
    WHERE session_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(session_date)
    ORDER BY DATE(session_date)
");
$chartRows = $chartStmt->fetchAll();

$sessionsLast7Days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $sessionsLast7Days[$date] = 0;
}
foreach ($chartRows as $row) {
    $sessionsLast7Days[$row['game_date']] = (int)$row['total'];
}

$chartData   = array_values($sessionsLast7Days);
$daysPt = ['Mon'=>'Seg','Tue'=>'Ter','Wed'=>'Qua','Thu'=>'Qui','Fri'=>'Sex','Sat'=>'Sáb','Sun'=>'Dom'];
$chartLabels = array_map(
    fn($d) => $daysPt[date('D', strtotime($d))] ?? date('D', strtotime($d)),
    array_keys($sessionsLast7Days)
);

/* ═══════════════════════════════════════
   DADOS — DELTAS
═══════════════════════════════════════ */
$activePatientsDelta = db()->query("
    SELECT
        (SELECT COUNT(DISTINCT patient_id) FROM GameSession
         WHERE MONTH(session_date)=MONTH(CURDATE()) AND YEAR(session_date)=YEAR(CURDATE())) AS this_month,
        (SELECT COUNT(DISTINCT patient_id) FROM GameSession
         WHERE MONTH(session_date)=MONTH(CURDATE()-INTERVAL 1 MONTH)
           AND YEAR(session_date)=YEAR(CURDATE()-INTERVAL 1 MONTH)) AS last_month
")->fetch();
$activePatientsChange = (int)$activePatientsDelta['this_month'] - (int)$activePatientsDelta['last_month'];

$sessionsDelta = db()->query("
    SELECT
        (SELECT COUNT(*) FROM GameSession WHERE DATE(session_date)=CURDATE()) AS today,
        (SELECT COUNT(*) FROM GameSession WHERE DATE(session_date)=CURDATE()-INTERVAL 1 DAY) AS yesterday
")->fetch();
$sessionsChange = (int)$sessionsDelta['today'] - (int)$sessionsDelta['yesterday'];

$precisionDelta = db()->query("
    SELECT
        (SELECT COALESCE(AVG(avg_score),0) FROM
            (SELECT AVG(g.final_score) AS avg_score FROM GameSession gs
             LEFT JOIN Game g ON g.game_group_id=gs.id
             WHERE gs.session_date >= CURDATE()-INTERVAL 7 DAY GROUP BY gs.id) t) AS last7,
        (SELECT COALESCE(AVG(avg_score),0) FROM
            (SELECT AVG(g.final_score) AS avg_score FROM GameSession gs
             LEFT JOIN Game g ON g.game_group_id=gs.id
             WHERE gs.session_date BETWEEN CURDATE()-INTERVAL 14 DAY AND CURDATE()-INTERVAL 7 DAY GROUP BY gs.id) t) AS prev7
")->fetch();
$precisionChange = round($precisionDelta['last7'] - $precisionDelta['prev7'], 1);

$timeDelta = db()->query("
    SELECT
        (SELECT COALESCE(AVG(duration),0) FROM
            (SELECT SUM(g.duration_minutes) AS duration FROM GameSession gs
             LEFT JOIN Game g ON g.game_group_id=gs.id
             WHERE gs.session_date >= CURDATE()-INTERVAL 7 DAY GROUP BY gs.id) t) AS last7,
        (SELECT COALESCE(AVG(duration),0) FROM
            (SELECT SUM(g.duration_minutes) AS duration FROM GameSession gs
             LEFT JOIN Game g ON g.game_group_id=gs.id
             WHERE gs.session_date BETWEEN CURDATE()-INTERVAL 14 DAY AND CURDATE()-INTERVAL 7 DAY GROUP BY gs.id) t) AS prev7
")->fetch();
$timeChange = round($timeDelta['last7'] - $timeDelta['prev7'], 0);

/* ═══════════════════════════════════════
   DADOS — CLIENTES (para JS)
═══════════════════════════════════════ */
$clients = [];
foreach ($patients as $p) {
    $nameParts = explode(' ', trim($p['name']));
    $initials  = strtoupper(
        mb_substr($nameParts[0] ?? '', 0, 1, 'UTF-8') .
        mb_substr(end($nameParts) ?: '', 0, 1, 'UTF-8')
    );
    $lastSession = $p['last_session'] ? date('d/m/Y', strtotime($p['last_session'])) : 'Sem sessões';
    $status      = ((int)$p['is_active'] === 1) ? 'active' : 'inactive';

    $clients[] = [
        'id'             => 'RB' . str_pad((string)$p['id'], 3, '0', STR_PAD_LEFT),
        'db_id'          => (int)$p['id'],
        'name'           => $p['name'],
        'email'          => $p['email'],
        'status'         => $status,
        'level'          => (int)$p['difficulty'],
        'doctor_id'      => $p['doctor_id'],
        'disease_status' => $p['disease_status'],
        'sessions'       => (int)$p['sessions'],
        'lastSession'    => $lastSession,
        'precision'      => (int)$p['avg_score'],
        'spark'          => [20, 35, 30, 45, 55, 60, 70],
        'withdrawal'     => null,
        'color'          => '#468189',
        'initials'       => $initials,
    ];
}

$totalClients  = count($clients);
$activeClients = count(array_filter($clients, fn($c) => $c['status'] === 'active'));

/* ═══════════════════════════════════════
   DADOS — MÉDICOS + LOGS
═══════════════════════════════════════ */
$doctors = db()->query("
    SELECT u.id, u.name FROM Users u INNER JOIN Doctor d ON d.id = u.id ORDER BY u.name
")->fetchAll(PDO::FETCH_ASSOC);

$loginLogs = db()->query("
    SELECT l.id, l.email_attempted, l.ip_address, l.user_agent,
           l.success, l.failure_reason, l.created_at, u.name, u.role
    FROM LoginLogs l
    LEFT JOIN Users u ON u.id = l.user_id
    ORDER BY l.created_at DESC LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

/* ═══════════════════════════════════════
   DADOS — REVOGAÇÕES DE CONSENTIMENTO
═══════════════════════════════════════ */
$revocationStmt = db()->query("
    SELECT
        crr.id, crr.patient_id, crr.request_date, crr.reason, crr.status,
        u.name AS patient_name, u.email AS patient_email
    FROM ConsentRevocationRequest crr
    INNER JOIN Patient p ON p.id = crr.patient_id
    INNER JOIN Users u ON u.id = p.id
    WHERE crr.status = 'pending'
    ORDER BY crr.request_date DESC
");
$revocationRequests = $revocationStmt->fetchAll(PDO::FETCH_ASSOC);
$revocationCount    = count($revocationRequests);


?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>RehabBox — Painel Admin</title>
  <link rel="icon" type="image/png" href="./assets/images/logo1.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,300;12..96,400;12..96,500;12..96,600;12..96,700;12..96,800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./assets/css/style.css">
</head>
<body>

  <div class="cursor-dot"   id="cursorDot"></div>
  <div class="cursor-cross" id="cursorCross"></div>

  <div class="layout">

    <!-- ══════ SIDEBAR ══════ -->
    <aside class="sidebar">
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

      <a href="#" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
        Sessões
      </a>

      <!-- Badge de pendentes na sidebar -->
      <a href="#pendentes" class="nav-item" id="navPendentes">
        <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-2-3.46"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
        Aprovações Pendentes
        <?php if ($pendingCount > 0): ?>
          <span class="badge-danger"><?= $pendingCount ?></span>
        <?php endif; ?>
      </a>

      <a href="#" class="nav-item">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Pedidos de Revogação
        <span class="badge-danger" id="withdrawalNavBadge" style="display:none;"></span>
      </a>

      <a href="admin_logs.php" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
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

    <!-- ══════ MAIN ══════ -->
    <div class="main">

      <!-- TOPBAR -->
      <div class="topbar">
        <span class="topbar-title">Painel Admin</span>
        <span class="topbar-sep"></span>
        <span class="topbar-breadcrumb">RehabBox / Admin / Dashboard</span>
        <div class="topbar-right">
          <span class="topbar-date" id="topbarDate"></span>
          <div class="topbar-notif" title="Pedidos de revogação de consentimento">
            <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <div class="notif-dot" id="notifDot" style="display:none;"></div>
          </div>
          
        </div>
      </div>

      <!-- WITHDRAWAL ALERT BANNER -->
      <div class="withdrawal-banner hidden" id="withdrawalBanner">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span id="withdrawalBannerMsg"></span>
        <a href="#" onclick="document.getElementById('window.REVOCATION_COUNT = <?= $revocationCount ?>').scrollIntoView({behavior:'smooth'});return false;">
          Ver pedidos →
        </a>
      </div>

      <div class="page">

        <!-- Page header -->
        <div class="page-header">
          <div>
            <h1>Gestão de <em>Clientes</em></h1>
            <p>Pesquise, edite e gira os perfis de pacientes e os seus dados de reabilitação.</p>
          </div>
          <button class="btn-primary" onclick="openModal('edit', {})">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Novo Paciente
          </button>
        </div>

        <!-- STAT CARDS -->
        <div class="stats-grid">
          <div class="stat-card" style="--accent:var(--teal-d)">
            <div class="stat-icon" style="background:rgba(70,129,137,.12);color:var(--teal-d)">
              <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="stat-label">Pacientes Ativos</div>
            <div class="stat-value"><?= $activeClients ?> <span>/ <?= $totalClients ?> total</span></div>
            <div class="stat-delta <?= $activePatientsChange >= 0 ? 'delta-up' : 'delta-down' ?>">
              <?= $activePatientsChange >= 0 ? '+' : '' ?><?= $activePatientsChange ?> este mês
            </div>
          </div>

          <div class="stat-card" style="--accent:#22C55E">
            <div class="stat-icon" style="background:rgba(34,197,94,.10);color:#15803D">
              <svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            </div>
            <div class="stat-label">Sessões Hoje</div>
            <div class="stat-value"><?= (int)$stats['sessions_today'] ?> <span>sessões</span></div>
            <div class="stat-delta <?= $sessionsChange >= 0 ? 'delta-up' : 'delta-down' ?>">
              <?= $sessionsChange >= 0 ? '+' : '' ?><?= $sessionsChange ?> vs ontem
            </div>
          </div>

          <div class="stat-card" style="--accent:#3B82F6">
            <div class="stat-icon" style="background:rgba(59,130,246,.10);color:#1D4ED8">
              <svg viewBox="0 0 24 24"><polyline points="22,12 18,12 15,21 9,3 6,12 2,12"/></svg>
            </div>
            <div class="stat-label">Precisão Média</div>
            <div class="stat-value"><?= (int)$stats['avg_precision'] ?> <span>%</span></div>
            <div class="stat-delta <?= $precisionChange >= 0 ? 'delta-up' : 'delta-down' ?>">
              <?= $precisionChange >= 0 ? '+' : '' ?><?= $precisionChange ?>% esta semana
            </div>
          </div>

          <div class="stat-card" style="--accent:#F59E0B">
            <div class="stat-icon" style="background:rgba(245,158,11,.10);color:#B45309">
              <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
            </div>
            <div class="stat-label">Tempo Médio / Sessão</div>
            <div class="stat-value"><?= (int)$stats['avg_duration'] ?> <span>min</span></div>
            <div class="stat-delta <?= $timeChange >= 0 ? 'delta-up' : 'delta-down' ?>">
              <?= $timeChange >= 0 ? '+' : '' ?><?= $timeChange ?> min vs semana passada
            </div>
          </div>
        </div>

        <!-- CHARTS ROW -->
        <div class="metrics-row">
          <div class="metric-card">
            <div class="metric-card-header">
              <span class="metric-card-title">Sessões — últimos 7 dias</span>
              <span class="metric-pill">Semana atual</span>
            </div>
            <div class="bar-chart" id="barChart"></div>
          </div>

          <div class="metric-card">
            <div class="metric-card-header">
              <span class="metric-card-title">Jogos mais usados</span>
            </div>
            <div class="game-usage-list" id="mostUsedGames"></div>
          </div>
        </div>

        <!-- ══════════════════════════════════════════
             PENDENTES — APROVAÇÃO DE REGISTOS
        ══════════════════════════════════════════ -->
        <div class="metric-card" id="pendentes" style="margin-bottom:1.75rem;">
          <div class="metric-card-header">
            <span class="metric-card-title">Pedidos de Registo Pendentes</span>
            <?php if ($pendingCount > 0): ?>
              <span class="metric-pill-danger"><?= $pendingCount ?> pendente<?= $pendingCount > 1 ? 's' : '' ?></span>
            <?php else: ?>
              <span class="metric-pill">Nenhum pendente</span>
            <?php endif; ?>
          </div>

          <?php if (empty($pendingRegistrations)): ?>
            <div style="padding:2rem 0;text-align:center;opacity:.5;">
              <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:.5rem"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22,4 12,14.01 9,11.01"/></svg>
              <p style="margin:0;font-size:.9rem;">Sem pedidos pendentes.</p>
            </div>
          <?php else: ?>
            <div class="table-wrap" style="margin-top:.5rem;">
              <table>
                <thead>
                  <tr>
                    <th>Utilizador</th>
                    <th>Role pedido</th>
                    <th>Justificação</th>
                    <th>Data do pedido</th>
                    <th>Aprovar como</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($pendingRegistrations as $pr):
                    $roleLabel = $pr['requested_role'] === 'doctor' ? 'Médico' : 'Paciente';
                    $submitted = date('d/m/Y H:i', strtotime($pr['submitted_at']));
                    $nameParts = explode(' ', trim($pr['name']));
                    $initials  = strtoupper(
                      mb_substr($nameParts[0] ?? '', 0, 1, 'UTF-8') .
                      mb_substr(end($nameParts) ?: '', 0, 1, 'UTF-8')
                    );
                  ?>
                  <tr>
                    <td>
                      <div class="client-cell">
                        <div class="client-avatar" style="background:#468189"><?= e($initials) ?></div>
                        <div>
                          <div class="client-name"><?= e($pr['name']) ?></div>
                          <div class="client-email"><?= e($pr['email']) ?></div>
                        </div>
                      </div>
                    </td>
                    <td>
                      <span class="status-pill <?= $pr['requested_role'] === 'doctor' ? 'status-active' : 'status-inactive' ?>">
                        <?= $roleLabel ?>
                      </span>
                    </td>
                    <td>
                      <span style="font-size:.85rem;opacity:.8;">
                        <?= $pr['justification'] ? e($pr['justification']) : '<em style="opacity:.5">—</em>' ?>
                      </span>
                    </td>
                    <td style="font-size:.85rem;opacity:.75;"><?= $submitted ?></td>
                    <td>
                      <!-- Admin pode confirmar/corrigir o role antes de aprovar -->
                      <select id="confirmRole_<?= $pr['id'] ?>" class="filter-select" style="padding:.35rem .7rem;font-size:.82rem;">
                        <option value="patient"  <?= $pr['requested_role'] === 'patient' ? 'selected' : '' ?>>Paciente</option>
                        <option value="doctor"   <?= $pr['requested_role'] === 'doctor'  ? 'selected' : '' ?>>Médico</option>
                      </select>
                    </td>
                    <td>
                      <div style="display:flex;gap:.5rem;">
                        <!-- APROVAR -->
                        <form method="POST" action="menu_admin.php" style="display:inline;">
                          <input type="hidden" name="_action"      value="approve">
                          <input type="hidden" name="user_id"      value="<?= (int)$pr['id'] ?>">
                          <input type="hidden" name="confirmed_role" id="approveRoleInput_<?= $pr['id'] ?>" value="<?= e($pr['requested_role']) ?>">
                          <button type="submit"
                                  class="btn-primary"
                                  style="padding:.35rem .9rem;font-size:.82rem;"
                                  onclick="
                                    document.getElementById('approveRoleInput_<?= $pr['id'] ?>').value =
                                      document.getElementById('confirmRole_<?= $pr['id'] ?>').value;
                                  ">
                            Aprovar
                          </button>
                        </form>
                        <!-- REJEITAR -->
                        <form method="POST" action="menu_admin.php" style="display:inline;"
                              onsubmit="return confirm('Tens a certeza que queres rejeitar e eliminar este pedido?')">
                          <input type="hidden" name="_action" value="reject">
                          <input type="hidden" name="user_id" value="<?= (int)$pr['id'] ?>">
                          <button type="submit" class="btn-danger" style="padding:.35rem .9rem;font-size:.82rem;">
                            Rejeitar
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- WITHDRAWAL REQUESTS -->
<div class="metric-card" style="margin-bottom:1.75rem;">
  <div class="metric-card-header">
    <span class="metric-card-title">Pedidos de Revogação de Consentimento</span>
    <?php if ($revocationCount > 0): ?>
      <span class="metric-pill-danger"><?= $revocationCount ?> pendente<?= $revocationCount > 1 ? 's' : '' ?></span>
    <?php else: ?>
      <span class="metric-pill">Nenhum pendente</span>
    <?php endif; ?>
  </div>

  <?php if (empty($revocationRequests)): ?>
    <div style="padding:2rem 0;text-align:center;opacity:.5;">
      <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:.5rem"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22,4 12,14.01 9,11.01"/></svg>
      <p style="margin:0;font-size:.9rem;">Sem pedidos de revogação pendentes.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap" style="margin-top:.5rem;">
      <table>
        <thead>
          <tr>
            <th>Paciente</th>
            
            <th>Data do Pedido</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($revocationRequests as $rr):
            $nameParts = explode(' ', trim($rr['patient_name']));
            $initials  = strtoupper(
              mb_substr($nameParts[0] ?? '', 0, 1, 'UTF-8') .
              mb_substr(end($nameParts) ?: '', 0, 1, 'UTF-8')
            );
            $reqDate = date('d/m/Y H:i', strtotime($rr['request_date']));
          ?>
          <tr>
            <td>
              <div class="client-cell">
                <div class="client-avatar" style="background:#e84040"><?= e($initials) ?></div>
                <div>
                  <div class="client-name"><?= e($rr['patient_name']) ?></div>
                  <div class="client-email"><?= e($rr['patient_email']) ?></div>
                </div>
              </div>
            </td>
          
            <td style="font-size:.85rem;opacity:.75;"><?= $reqDate ?></td>
            <td>
              <div style="display:flex;gap:.5rem;">
                <!-- APROVAR (elimina conta) -->
                <form method="POST" action="menu_admin.php" style="display:inline;"
                      onsubmit="return confirm('Isto vai eliminar permanentemente a conta e todos os dados do paciente. Tens a certeza?')">
                  <input type="hidden" name="_action" value="revocation_approve">
                  <input type="hidden" name="user_id" value="<?= (int)$rr['patient_id'] ?>">
                  <button type="submit" class="btn-danger" style="padding:.35rem .9rem;font-size:.82rem;">
                    Aprovar &amp; Eliminar Conta
                  </button>
                </form>
                <!-- REJEITAR -->
                <form method="POST" action="menu_admin.php" style="display:inline;">
                  <input type="hidden" name="_action" value="revocation_reject">
                  <input type="hidden" name="user_id" value="<?= (int)$rr['patient_id'] ?>">
                  <button type="submit" class="btn-ghost-sm" style="padding:.35rem .9rem;font-size:.82rem;">
                    Rejeitar
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

        <!-- SEARCH & FILTERS -->
        <div class="search-bar">
          <div class="search-wrap">
            <svg class="search-icon" viewBox="0 0 24 24">
              <circle cx="11" cy="11" r="8"/>
              <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input class="search-input" id="searchInput" type="text"
                   placeholder="Pesquisar por nome, email ou ID…"
                   oninput="filterClients()">
          </div>
          <select class="filter-select" id="filterStatus" onchange="filterClients()">
            <option value="">Todos os estados</option>
            <option value="active">Ativo</option>
            <option value="inactive">Inativo</option>
            <option value="alert">Alerta</option>
          </select>
          <select class="filter-select" id="filterLevel" onchange="filterClients()">
            <option value="">Todos os níveis</option>
            <option value="1">Nível 1</option>
            <option value="2">Nível 2</option>
            <option value="3">Nível 3</option>
          </select>
          <span class="search-count" id="searchCount"><?= $totalClients ?> clientes</span>
        </div>

        <!-- TABLE -->
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Paciente</th>
                <th>Estado</th>
                <th>Nível</th>
                <th>Sessões</th>
                <th>Última Sessão</th>
                <th>Precisão</th>
                <th>Evolução</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody id="clientTableBody"></tbody>
          </table>

          <div class="pagination">
            <span class="page-info" id="pageInfo">Mostrando 1–8 de <?= $totalClients ?></span>
            <button class="page-btn" onclick="changePage(-1)">
              <svg viewBox="0 0 24 24"><polyline points="15,18 9,12 15,6"/></svg>
            </button>
            <button class="page-btn current" id="pageNum">1</button>
            <button class="page-btn" onclick="changePage(1)">
              <svg viewBox="0 0 24 24"><polyline points="9,18 15,12 9,6"/></svg>
            </button>
          </div>
        </div>

      </div><!-- /page -->
    </div><!-- /main -->
  </div><!-- /layout -->


  <!-- ════════ MODAL — EDITAR PACIENTE ════════ -->
  <div class="modal-backdrop" id="modalEdit">
    <div class="modal">
      <div class="modal-header">
        <div class="modal-icon" style="background:rgba(70,129,137,.12);color:var(--teal-d)">
          <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </div>
        <div>
          <div class="modal-title" id="modalEditTitle">Editar Paciente</div>
          <div class="modal-sub"   id="modalEditSub">Atualizar dados do perfil</div>
        </div>
        <button class="modal-close" onclick="closeModal('edit')">
          <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label>Nome</label>
            <input id="editName" type="text" placeholder="Nome completo">
          </div>
          <div class="form-group">
            <label>Email</label>
            <input id="editEmail" type="email" placeholder="email@exemplo.pt">
          </div>
        </div>
        <div class="form-group">
          <label>Médico Responsável</label>
          <select id="editDoctor">
            <option value="">Selecione um médico</option>
            <?php foreach($doctors as $doctor): ?>
              <option value="<?= $doctor['id'] ?>"><?= htmlspecialchars($doctor['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Estado da Doença</label>
          <select id="editDiseaseStatus">
            <option value="Parkinson estadio inicial">Parkinson estadio inicial</option>
            <option value="Parkinson estadio moderado">Parkinson estadio moderado</option>
            <option value="Parkinson estadio avançado">Parkinson estadio avançado</option>
          </select>
        </div>
        <div class="form-row" id="editOptionsRow">
          <div class="form-group">
            <label>Estado</label>
            <select id="editStatus">
              <option value="active">Ativo</option>
              <option value="inactive">Inativo</option>
              <option value="alert">Alerta</option>
            </select>
          </div>
          <div class="form-group">
            <label>Nível Atual</label>
            <select id="editLevel" disabled>
              <option value="1">1</option>
            </select>
            <small style="opacity:.7">O nível é definido automaticamente e apenas pode ser alterado pelo médico.</small>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-ghost-sm" onclick="closeModal('edit')">Cancelar</button>
        <button class="btn-primary" onclick="saveEdit()">
          <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5" stroke-linecap="round"><polyline points="20,6 9,17 4,12"/></svg>
          Guardar
        </button>
      </div>
    </div>
  </div>

  <!-- ════════ MODAL — APAGAR DADOS ════════ -->
  <div class="modal-backdrop" id="modalData">
    <div class="modal">
      <div class="modal-header">
        <div class="modal-icon" style="background:rgba(59,130,246,.10);color:var(--blue)">
          <svg viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
        </div>
        <div>
          <div class="modal-title">Apagar Dados</div>
          <div class="modal-sub" id="modalDataSub">Selecione os dados a eliminar</div>
        </div>
        <button class="modal-close" onclick="closeModal('data')">
          <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="danger-box">
          <p>Os dados selecionados serão <strong>permanentemente eliminados</strong> e não poderão ser recuperados.</p>
        </div>
        <div class="data-check-list">
          <div class="data-check"><input type="checkbox" id="d1"><label for="d1">Histórico de sessões</label><span id="dCount1">— sessões</span></div>
          <div class="data-check"><input type="checkbox" id="d2"><label for="d2">Pontuações e métricas</label><span>Todos os registos</span></div>
          <div class="data-check"><input type="checkbox" id="d3"><label for="d3">Configurações de jogo</label><span>Nível e preferências</span></div>
          <div class="data-check"><input type="checkbox" id="d4"><label for="d4">Notas clínicas</label><span>Observações do terapeuta</span></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-ghost-sm" onclick="closeModal('data')">Cancelar</button>
        <button class="btn-danger" onclick="deleteData()">
          <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round"><polyline points="3,6 5,6 21,6"/><path d="M19,6l-1,14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5,6"/><path d="M10,11v6"/><path d="M14,11v6"/></svg>
          Apagar Selecionados
        </button>
      </div>
    </div>
  </div>

  <!-- ════════ MODAL — ELIMINAR CONTA ════════ -->
  <div class="modal-backdrop" id="modalDelete">
    <div class="modal">
      <div class="modal-header">
        <div class="modal-icon" style="background:rgba(232,64,64,.10);color:var(--red)">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div>
          <div class="modal-title">Eliminar Conta</div>
          <div class="modal-sub" id="modalDeleteSub">Esta ação é irreversível</div>
        </div>
        <button class="modal-close" onclick="closeModal('delete')">
          <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="danger-box">
          <p>Está prestes a <strong>eliminar permanentemente</strong> a conta e todos os dados associados.</p>
        </div>
        <div class="form-group">
          <label>Confirme escrevendo DELETE</label>
          <input id="deleteConfirm" type="text" placeholder="DELETE">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-ghost-sm" onclick="closeModal('delete')">Cancelar</button>
        <button class="btn-danger" onclick="confirmDelete()">
          <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round"><polyline points="3,6 5,6 21,6"/><path d="M19,6l-1,14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5,6"/></svg>
          Eliminar Conta
        </button>
      </div>
    </div>
  </div>

  <!-- TOAST -->
  <div class="toast" id="toast">
    <svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>
    <span id="toastMsg">Ação concluída.</span>
  </div>

  <script>
    window.CLIENTS_FROM_DB         = <?= json_encode($clients,       JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.SESSIONS_LAST_7_DAYS    = <?= json_encode($chartData) ?>;
    window.SESSIONS_LAST_7_LABELS  = <?= json_encode($chartLabels,   JSON_UNESCAPED_UNICODE) ?>;
    window.MOST_USED_GAMES         = <?= json_encode($mostUsedGames, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>

  <script src="./assets/js/main.js?v=12"></script>
</body>
</html>