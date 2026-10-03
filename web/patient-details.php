<?php
require_once __DIR__ . '/auth.php';
require_role(['doctor', 'admin']);

/* ══════════════════════════════════════════════════════
   VALIDAR ID
══════════════════════════════════════════════════════ */
$patientId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($patientId <= 0) {
  header('Location: menu_medico.php');
  exit;
}

/* ══════════════════════════════════════════════════════
   UTILIZADOR AUTENTICADO
══════════════════════════════════════════════════════ */
$authName   = $_SESSION['user_name'] ?? 'Médico';
$authRole   = $_SESSION['user_role'] ?? 'doctor';
$authAvatar = strtoupper(mb_substr($authName, 0, 2, 'UTF-8'));

/* ── ID do médico autenticado (necessário para Evaluation.doctor_id) ── */
$authDoctorId = (int)($_SESSION['user_id'] ?? 0);

/* ══════════════════════════════════════════════════════
   DADOS DO PACIENTE
   Tabelas: Users + Patient
══════════════════════════════════════════════════════ */
$patStmt = db()->prepare("
    SELECT
        u.id,
        u.name,
        u.email,
        p.disease_status,
        p.difficulty
    FROM Users u
    INNER JOIN Patient p ON p.id = u.id
    WHERE u.id = :id AND u.role = 'patient'
");
$patStmt->execute([':id' => $patientId]);
$patient = $patStmt->fetch();

if (!$patient) {
  header('Location: menu_medico.php');
  exit;
}

/* ── Iniciais e nome formatado ── */
$nameParts = explode(' ', trim($patient['name']));
$firstName = $nameParts[0] ?? '';
$lastName  = end($nameParts) ?: '';
$initials  = strtoupper(
  mb_substr($firstName, 0, 1, 'UTF-8') .
    mb_substr($lastName,  0, 1, 'UTF-8')
);

/* ══════════════════════════════════════════════════════
   ESTATÍSTICAS AGREGADAS
   Lógica: cada GameSession é uma "sessão clínica".
           Cada Game dentro dela é um jogo individual.
   
   - total_sessions  → COUNT(GameSession)
   - avg_score       → média dos final_score de todos os Game do paciente
   - best_score      → melhor final_score individual
   - avg_duration    → soma de duration_minutes por sessão, depois média entre sessões
   - first/last      → data min/max de GameSession
══════════════════════════════════════════════════════ */
$pdo = db();

$statsStmt = $pdo->prepare("
    SELECT
        COUNT(DISTINCT gs.id)                              AS total_sessions,
        COALESCE(ROUND(AVG(g.final_score)), 0)             AS avg_score,
        COALESCE(MAX(g.final_score), 0)                    AS best_score,
        COALESCE(ROUND(AVG(sess_dur.total_dur)), 0)        AS avg_duration,
        MIN(gs.session_date)                               AS first_session,
        MAX(gs.session_date)                               AS last_session
    FROM GameSession gs
    LEFT JOIN Game g
        ON g.game_group_id = gs.id
    LEFT JOIN (
        SELECT game_group_id, SUM(duration_minutes) AS total_dur
        FROM Game
        GROUP BY game_group_id
    ) sess_dur
        ON sess_dur.game_group_id = gs.id
    WHERE gs.patient_id = :id
");
$statsStmt->execute([':id' => $patientId]);
$stats = $statsStmt->fetch();

/* ── Formatos legíveis ── */
$totalSessions = (int)$stats['total_sessions'];
$avgScore      = (int)$stats['avg_score'];
$bestScore     = (int)$stats['best_score'];
$avgDuration   = (int)$stats['avg_duration'];
$currentLevel  = (int)$patient['difficulty'];

$firstSessionFmt = $stats['first_session']
  ? date('M Y', strtotime($stats['first_session']))
  : '—';

$lastSessionFmt = $stats['last_session']
  ? date('d/m/Y', strtotime($stats['last_session']))
  : 'Sem sessões';

/* ══════════════════════════════════════════════════════
   DELTA DE PRECISÃO — últimas 5 sessões vs anteriores 5
   Agrupa por GameSession e calcula avg de final_score
   por sessão, depois tira a média dos dois grupos.
══════════════════════════════════════════════════════ */
$last5Stmt = $pdo->prepare("
    SELECT COALESCE(ROUND(AVG(sess_avg)), 0) AS avg_last5
    FROM (
        SELECT gs.id, AVG(g.final_score) AS sess_avg
        FROM GameSession gs
        INNER JOIN Game g ON g.game_group_id = gs.id
        WHERE gs.patient_id = :id
        GROUP BY gs.id
        ORDER BY gs.session_date DESC, gs.id DESC
        LIMIT 5
    ) t
");
$last5Stmt->execute([':id' => $patientId]);
$last5Avg = (int)$last5Stmt->fetchColumn();

$prev5Stmt = $pdo->prepare("
    SELECT COALESCE(ROUND(AVG(sess_avg)), 0) AS avg_prev5
    FROM (
        SELECT gs.id, AVG(g.final_score) AS sess_avg
        FROM GameSession gs
        INNER JOIN Game g ON g.game_group_id = gs.id
        WHERE gs.patient_id = :id
        GROUP BY gs.id
        ORDER BY gs.session_date DESC, gs.id DESC
        LIMIT 5 OFFSET 5
    ) t
");
$prev5Stmt->execute([':id' => $patientId]);
$prev5Avg = (int)$prev5Stmt->fetchColumn();

$precisionDelta = ($prev5Avg > 0) ? $last5Avg - $prev5Avg : null;

/* ══════════════════════════════════════════════════════
   SESSÕES DESTE MÊS
══════════════════════════════════════════════════════ */
$monthStmt = $pdo->prepare("
    SELECT COUNT(*) AS cnt
    FROM GameSession
    WHERE patient_id = :id
      AND YEAR(session_date)  = YEAR(CURDATE())
      AND MONTH(session_date) = MONTH(CURDATE())
");
$monthStmt->execute([':id' => $patientId]);
$sessionsThisMonth = (int)$monthStmt->fetchColumn();

/* ══════════════════════════════════════════════════════
   HISTÓRICO DE SESSÕES
   Uma linha por GameSession, com jogos agregados.
   → O "id" passado ao link é o GameSession.id
══════════════════════════════════════════════════════ */
$gamesStmt = $pdo->prepare("
    SELECT
        gs.id                                            AS session_id,
        gs.session_date,
        COUNT(g.id)                                      AS total_games,
        COALESCE(ROUND(AVG(g.final_score)), 0)           AS avg_score,
        COALESCE(SUM(g.duration_minutes), 0)             AS duration_minutes,
        MAX(g.difficulty)                                AS max_level
    FROM GameSession gs
    LEFT JOIN Game g ON g.game_group_id = gs.id
    WHERE gs.patient_id = :id
    GROUP BY gs.id, gs.session_date
    ORDER BY gs.session_date DESC, gs.id DESC
");
$gamesStmt->execute([':id' => $patientId]);
$gamesRows = $gamesStmt->fetchAll();

/* ── Numerar sessões (mais recente = nº total) ── */
$sessions = [];
foreach ($gamesRows as $i => $g) {
  $sessionNum  = $totalSessions - $i;
  $durationMin = (int)$g['duration_minutes'];
  $durationFmt = $durationMin > 0 ? $durationMin . 'm' : '—';
  $dateFmt     = $g['session_date']
    ? date('d M Y', strtotime($g['session_date']))
    : '—';
  $totalGames  = (int)$g['total_games'];

  $sessions[] = [
    'num'       => $sessionNum,
    'id'        => (int)$g['session_id'],   // GameSession.id → passado ao session-evaluation.php
    'date'      => $dateFmt,
    'desc'      => $totalGames > 0
      ? $totalGames . ' ' . ($totalGames === 1 ? 'jogo' : 'jogos')
      : 'Sessão sem jogos',
    'precision' => (int)$g['avg_score'],
    'time'      => $durationFmt,
    'level'     => $g['max_level'] ?? $currentLevel,
  ];
}

/* ══════════════════════════════════════════════════════
   SPARKLINE — últimas 7 sessões (cronológico)
══════════════════════════════════════════════════════ */
$sparkData = array_map(
  fn($s) => $s['precision'],
  array_reverse(array_slice($sessions, 0, 7))
);
while (count($sparkData) < 7) array_unshift($sparkData, 0);

/* ══════════════════════════════════════════════════════
   RING — stroke-dashoffset (circunferência ≈ 138)
══════════════════════════════════════════════════════ */
$ringCirc   = 138;
$ringOffset = round($ringCirc - ($ringCirc * $avgScore / 100));

/* ══════════════════════════════════════════════════════
   BADGE SIDEBAR — total de pacientes
══════════════════════════════════════════════════════ */
$totalPatientsCount = (int)$pdo->query("SELECT COUNT(*) FROM Patient")->fetchColumn();

/* ══════════════════════════════════════════════════════
   AVALIAÇÕES PENDENTES
   Sessões do paciente que ainda não têm Evaluation
══════════════════════════════════════════════════════ */
$pendingStmt = $pdo->prepare("
    SELECT COUNT(*) AS pending
    FROM GameSession gs
    WHERE gs.patient_id = :id
      AND NOT EXISTS (
          SELECT 1
          FROM Evaluation e
          WHERE e.patient_id = gs.patient_id
            AND DATE(e.ev_date) >= DATE(gs.session_date)
      )
");
$pendingStmt->execute([':id' => $patientId]);
$pendingEvaluations = (int)$pendingStmt->fetchColumn();

/* ══════════════════════════════════════════════
   AVALIAÇÕES DO PACIENTE
══════════════════════════════════════════════ */
$evalStmt = $pdo->prepare("
    SELECT
        e.id,
        e.description,
        e.ev_date AS date
    FROM Evaluation e
    WHERE e.patient_id = :id
    ORDER BY e.ev_date DESC
");
$evalStmt->execute([':id' => $patientId]);
$evaluations = $evalStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>RehabBox — Detalhes do Paciente</title>
  <link rel="icon" type="image/png" href="./assets/images/logo1.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,300;12..96,400;12..96,500;12..96,600;12..96,700;12..96,800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">

  <!-- Stylesheet -->
  <link rel="stylesheet" href="./assets/css/style.css">
  <link rel="stylesheet" href="./assets/css/patient-details.css">

</head>

<body>

  <div class="cursor-dot" id="cursorDot"></div>
  <div class="cursor-cross" id="cursorCross"></div>

  <div class="layout">

    <!-- ════════════════ SIDEBAR ════════════════ -->
    <aside class="sidebar">
      <a href="menu_medico.php" class="sidebar-logo">
        <div class="logo-mark">
          <svg viewBox="0 0 24 24" fill="none" stroke="#F4E9CD" stroke-width="2" stroke-linecap="round">
            <path d="M12 2L2 7l10 5 10-5-10-5z" />
            <path d="M2 17l10 5 10-5" />
            <path d="M2 12l10 5 10-5" />
          </svg>
        </div>
        <span class="logo-wordmark">Rehab<span>Box</span></span>
      </a>

      <div class="sidebar-label">Clínica</div>
      <a href="menu_medico.php" class="nav-item">
        <svg viewBox="0 0 24 24">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
          <circle cx="9" cy="7" r="4" />
          <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
          <path d="M16 3.13a4 4 0 0 1 0 7.75" />
        </svg>
        Pacientes
        <span class="badge"><?= $totalPatientsCount ?></span>
      </a>
      <a href="#" class="nav-item active">
        <svg viewBox="0 0 24 24">
          <rect x="3" y="4" width="18" height="18" rx="2" />
          <line x1="16" y1="2" x2="16" y2="6" />
          <line x1="8" y1="2" x2="8" y2="6" />
          <line x1="3" y1="10" x2="21" y2="10" />
        </svg>
        Sessões
      </a>
      <a href="#" class="nav-item">
        <svg viewBox="0 0 24 24">
          <path d="M9 11l3 3L22 4" />
          <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
        </svg>
        Avaliação
        <?php if ($pendingEvaluations > 0): ?>
          <span class="badge"><?= $pendingEvaluations ?></span>
        <?php endif; ?>
      </a>
      <a href="#" class="nav-item">
        <svg viewBox="0 0 24 24">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
          <polyline points="14,2 14,8 20,8" />
          <line x1="16" y1="13" x2="8" y2="13" />
          <line x1="16" y1="17" x2="8" y2="17" />
        </svg>
        Relatórios
      </a>

      <div class="sidebar-divider"></div>
      <div class="sidebar-label">Configurações</div>
      <a href="#" class="nav-item">
        <svg viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="3" />
          <path d="M2 12h3m14 0h3M12 2v3m0 14v3M4.22 4.22l2.12 2.12m11.32 11.32l2.12 2.12M4.22 19.78l2.12-2.12m11.32-11.32l2.12-2.12" />
        </svg>
        Preferências
      </a>

      <div class="sidebar-footer">
        <div class="doctor-pill">
          <div class="doctor-avatar"><?= htmlspecialchars($authAvatar) ?></div>
          <div>
            <div class="doctor-name"><?= htmlspecialchars($authName) ?></div>
            <div class="doctor-role"><?= $authRole === 'admin' ? 'Administrador' : 'Médico' ?></div>
          </div>
          <a href="logout.php" class="doctor-logout" title="Terminar sessão">
            <svg viewBox="0 0 24 24" stroke-linecap="round">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
              <polyline points="16,17 21,12 16,7" />
              <line x1="21" y1="12" x2="9" y2="12" />
            </svg>
          </a>
        </div>
      </div>
    </aside>

    <!-- ════════════════ MAIN ════════════════ -->
    <div class="main">

      <div class="topbar">
        <a href="menu_medico.php" class="topbar-back" title="Voltar">
          <svg viewBox="0 0 24 24">
            <polyline points="15,18 9,12 15,6" />
          </svg>
        </a>
        <span class="topbar-title">Detalhes do Paciente</span>
        <span class="topbar-sep"></span>
        <span class="topbar-breadcrumb">
          RehabBox / Médico / Pacientes / <?= htmlspecialchars($patient['name']) ?>
        </span>
        <div class="topbar-right">
          <span class="topbar-date" id="topbarDate"></span>
          <div class="topbar-notif">
            <svg viewBox="0 0 24 24">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
              <path d="M13.73 21a2 2 0 0 1-3.46 0" />
            </svg>
            <div class="notif-dot"></div>
          </div>
        </div>
      </div>

      <div class="page">

        <!-- PATIENT HERO -->
        <div class="patient-hero">
          <div class="patient-identity">
            <div class="patient-avatar-lg"><?= htmlspecialchars($initials) ?></div>
            <div>
              <h1 class="patient-hero-name">
                <?= htmlspecialchars($firstName) ?> <em><?= htmlspecialchars($lastName) ?></em>
              </h1>
              <div class="patient-hero-meta">
                <?php if ($patient['disease_status']): ?>
                  <span>
                    <svg viewBox="0 0 24 24">
                      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                      <circle cx="12" cy="7" r="4" />
                    </svg>
                    <?= htmlspecialchars($patient['disease_status']) ?>
                  </span>
                <?php endif; ?>
                <?php if ($stats['first_session']): ?>
                  <span>
                    <svg viewBox="0 0 24 24">
                      <rect x="3" y="4" width="18" height="18" rx="2" />
                      <line x1="16" y1="2" x2="16" y2="6" />
                      <line x1="8" y1="2" x2="8" y2="6" />
                      <line x1="3" y1="10" x2="21" y2="10" />
                    </svg>
                    Início: <?= $firstSessionFmt ?>
                  </span>
                <?php endif; ?>
                <span>
                  <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" />
                    <polyline points="12,6 12,12 16,14" />
                  </svg>
                  Última sessão:
                  <?php
                  if (!$stats['last_session']) {
                    echo 'Sem sessões';
                  } elseif (date('Y-m-d', strtotime($stats['last_session'])) === date('Y-m-d')) {
                    echo 'Hoje';
                  } else {
                    echo htmlspecialchars($lastSessionFmt);
                  }
                  ?>
                </span>
              </div>
              <div class="patient-hero-actions">
                <button class="btn-primary" onclick="scrollToEvaluation()">
                  <svg viewBox="0 0 24 24">
                    <path d="M12 5v14M5 12h14" />
                  </svg>
                  Nova Avaliação
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- STAT CARDS -->
        <div class="stats-grid">

          <div class="stat-card" style="--accent:var(--teal-d)">
            <div class="stat-icon" style="background:rgba(70,129,137,.12);color:var(--teal-d)">
              <svg viewBox="0 0 24 24">
                <path d="M3 12l5-5 4 4 5-6 4 4" />
              </svg>
            </div>
            <div class="stat-label">Sessões Totais</div>
            <div class="stat-value"><?= $totalSessions ?></div>
            <div class="stat-delta <?= $sessionsThisMonth > 0 ? 'delta-up' : 'delta-neu' ?>">
              <?= $sessionsThisMonth > 0 ? "↑ {$sessionsThisMonth} este mês" : '— sem sessões este mês' ?>
            </div>
          </div>

          <div class="stat-card" style="--accent:var(--teal-m)">
            <div class="stat-icon" style="background:rgba(119,172,162,.12);color:var(--teal-m)">
              <svg viewBox="0 0 24 24">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                <polyline points="22,4 12,14.01 9,11.01" />
              </svg>
            </div>
            <div class="stat-label">Precisão Média</div>
            <div class="stat-value"><?= $avgScore ?><small>%</small></div>
            <div class="stat-delta <?= $precisionDelta === null ? 'delta-neu' : ($precisionDelta >= 0 ? 'delta-up' : 'delta-warn') ?>">
              <?php if ($precisionDelta === null): ?>
                — dados insuficientes
              <?php elseif ($precisionDelta >= 0): ?>
                ↑ +<?= $precisionDelta ?>% últimas 5 sessões
              <?php else: ?>
                ↓ <?= $precisionDelta ?>% últimas 5 sessões
              <?php endif; ?>
            </div>
          </div>

          <div class="stat-card" style="--accent:var(--amber)">
            <div class="stat-icon" style="background:rgba(245,158,11,.12);color:var(--amber)">
              <svg viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10" />
                <polyline points="12,6 12,12 16,14" />
              </svg>
            </div>
            <div class="stat-label">Tempo Médio</div>
            <div class="stat-value"><?= $avgDuration ?><small>m</small></div>
            <div class="stat-delta delta-neu">— por sessão</div>
          </div>

          <div class="stat-card" style="--accent:#3B82F6">
            <div class="stat-icon" style="background:rgba(59,130,246,.12);color:#3B82F6">
              <svg viewBox="0 0 24 24">
                <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26 12,2" />
              </svg>
            </div>
            <div class="stat-label">Nível Atual</div>
            <div class="stat-value"><?= $currentLevel ?></div>
            <div class="stat-delta delta-up">↑ dificuldade <?= $patient['disease_status'] ? 'definida' : 'padrão' ?></div>
          </div>

        </div>

        <!-- CONTENT GRID -->
        <div class="content-grid">

          <!-- HISTÓRICO DE SESSÕES -->
          <div class="card">
            <div class="card-header">
              <span class="card-title">Histórico de Sessões</span>
              <span class="card-pill"><?= $totalSessions ?> <?= $totalSessions === 1 ? 'sessão' : 'sessões' ?></span>
            </div>

            <div id="sessionList"></div>

            <div style="padding:.85rem 1.5rem;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
              <span style="font-size:.78rem;color:rgba(18,55,70,.5)" id="sessPageInfo">—</span>
              <div style="display:flex;align-items:center;gap:.5rem;">
                <button class="btn-sm-ghost" style="padding:.35rem .7rem;" onclick="sessChangePage(-1)">
                  <svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15,18 9,12 15,6" />
                  </svg>
                </button>
                <span id="sessPageNum" style="font-size:.82rem;font-weight:600;color:var(--ink);min-width:20px;text-align:center">1</span>
                <button class="btn-sm-ghost" style="padding:.35rem .7rem;" onclick="sessChangePage(1)">
                  <svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9,18 15,12 9,6" />
                  </svg>
                </button>
              </div>
            </div>

          </div>


          <!-- SIDEBAR CARDS -->
          <div>
            <div class="side-card">
              <div class="side-label">Resumo Clínico</div>
              <h3>Evolução do paciente</h3>
              <div class="progress-ring-wrap">
                <svg class="ring-svg" width="52" height="52" viewBox="0 0 52 52">
                  <circle class="ring-bg" cx="26" cy="26" r="22" />
                  <circle class="ring-fg" cx="26" cy="26" r="22" />
                </svg>
                <div>
                  <div class="ring-label"><?= $avgScore ?>%</div>
                  <div class="ring-sub">precisão global</div>
                </div>
                <div style="margin-left:auto">
                  <div class="sparkline" id="miniSpark"></div>
                  <div style="font-size:.62rem;color:rgba(18,55,70,.4);margin-top:3px;text-align:right">últimas 7 sessões</div>
                </div>
              </div>
              <div class="summary-list">
                <div class="summary-row"><span class="s-key">Nível atual</span><span class="s-val">Nível <?= $currentLevel ?></span></div>
                <div class="summary-row"><span class="s-key">Sessões concluídas</span><span class="s-val"><?= $totalSessions ?></span></div>
                <div class="summary-row"><span class="s-key">Melhor precisão</span><span class="s-val"><?= $bestScore ?>%</span></div>
                <div class="summary-row"><span class="s-key">Início da reabilitação</span><span class="s-val"><?= $firstSessionFmt ?: '—' ?></span></div>
              </div>
            </div>

            <div class="side-card">
              <div class="side-label">Controlo de Dificuldade</div>
              <h3>Nível da próxima sessão</h3>
              <p>Selecione o nível de dificuldade a atribuir ao paciente para a próxima sessão.</p>
              <div class="difficulty-control">
                <div class="level-selector" id="levelSelector"></div>
                <div class="level-info" id="levelInfo">
                  <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                  </svg>
                  <span id="levelInfoText">—</span>
                </div>
                <button class="btn-primary" onclick="saveLevel()">
                  <svg viewBox="0 0 24 24">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                    <polyline points="17,21 17,13 7,13" />
                    <polyline points="7,3 7,8 15,8" />
                  </svg>
                  Guardar nível
                </button>
              </div>
            </div>
          </div>

        </div>

        <!-- ══════════════════════════════════════════════════════
           BLOCO DE AVALIAÇÃO CLÍNICA
      ══════════════════════════════════════════════════════ -->
        <div class="eval-section" id="evalSection">

          <div class="eval-section-header">
            <div class="eval-section-title-row">
              <div class="eval-icon">
                <svg viewBox="0 0 24 24">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                  <polyline points="14,2 14,8 20,8" />
                  <line x1="16" y1="13" x2="8" y2="13" />
                  <line x1="16" y1="17" x2="8" y2="17" />
                  <polyline points="10,9 9,9 8,9" />
                </svg>
              </div>
              <div>
                <div class="eval-section-title">Avaliação Clínica do Paciente</div>
                <div class="eval-section-sub">Notas e observações visíveis pelo paciente</div>
              </div>
            </div>
            <span class="eval-count-pill" id="evalCountPill">
              <?= count($evaluations) ?> <?= count($evaluations) === 1 ? 'nota' : 'notas' ?>
            </span>
          </div>

          <div class="eval-body">

            <!-- ── FORMULÁRIO ── -->
            <div class="eval-form-col">
              <label class="eval-form-label" for="evalTextarea">Nova avaliação</label>

              <!-- Etiquetas rápidas -->
              <div class="eval-tags">
                <button class="eval-tag" onclick="insertTag(this, 'Boa evolução.')">Boa evolução</button>
                <button class="eval-tag" onclick="insertTag(this, 'Manter nível de dificuldade.')">Manter nível</button>
                <button class="eval-tag" onclick="insertTag(this, 'Aumentar dificuldade na próxima sessão.')">Aumentar dificuldade</button>
                <button class="eval-tag" onclick="insertTag(this, 'Necessita mais prática.')">Mais prática</button>
                <button class="eval-tag" onclick="insertTag(this, 'Excelente desempenho.')">Excelente</button>
                <button class="eval-tag" onclick="insertTag(this, 'Sinais de fadiga observados.')">Fadiga</button>
              </div>

              <textarea
                id="evalTextarea"
                class="eval-textarea"
                placeholder="Escreva a sua avaliação clínica… O paciente poderá consultar esta nota na sua área pessoal."
                maxlength="2000"
                oninput="updateCharCount()"><?= '' /* vazio por defeito */ ?></textarea>

              <div class="eval-form-meta">
                <span class="eval-char-count" id="evalCharCount">0 / 2000 caracteres</span>
                <span class="eval-sending" id="evalSending">
                  <span class="eval-sending-dot"></span>
                  A guardar…
                </span>
              </div>

              <div class="eval-submit-row">
                <button class="btn-eval-submit" id="evalSubmitBtn" onclick="submitEval()" disabled>
                  <svg viewBox="0 0 24 24">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                    <polyline points="17,21 17,13 7,13" />
                    <polyline points="7,3 7,8 15,8" />
                  </svg>
                  Guardar avaliação
                </button>
                <button class="btn-eval-clear" onclick="clearForm()">
                  <svg viewBox="0 0 24 24">
                    <polyline points="3,6 5,6 21,6" />
                    <path d="M19,6l-1,14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5,6" />
                  </svg>
                  Limpar
                </button>
              </div>
            </div>

            <!-- ── HISTÓRICO ── -->
            <div class="eval-history-col">
              <span class="eval-history-label">Avaliações anteriores</span>

              <?php if (empty($evaluations)): ?>
                <div class="eval-history-empty" id="evalEmptyState">
                  <svg viewBox="0 0 24 24">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                    <polyline points="14,2 14,8 20,8" />
                    <line x1="16" y1="13" x2="8" y2="13" />
                    <line x1="16" y1="17" x2="8" y2="17" />
                  </svg>
                  <p>Ainda não existe nenhuma avaliação clínica para esta sessão. Escreva a primeira nota ao lado.</p>
                </div>
              <?php else: ?>
                <div style="display:none" id="evalEmptyState"><!-- placeholder --></div>
              <?php endif; ?>

              <div class="eval-history-list" id="evalHistoryList">
                <?php foreach ($evaluations as $ev): ?>
                  <div class="eval-entry" id="eval-<?= $ev['id'] ?>">
                    <div class="eval-entry-header">
                      <span class="eval-entry-date">
                        <svg viewBox="0 0 24 24">
                          <rect x="3" y="4" width="18" height="18" rx="2" />
                          <line x1="16" y1="2" x2="16" y2="6" />
                          <line x1="8" y1="2" x2="8" y2="6" />
                          <line x1="3" y1="10" x2="21" y2="10" />
                        </svg>
                        <?= e($ev['date']) ?>
                      </span>
                      <button class="eval-entry-del" title="Apagar avaliação" onclick="deleteEval(<?= $ev['id'] ?>, this)">
                        <svg viewBox="0 0 24 24">
                          <polyline points="3,6 5,6 21,6" />
                          <path d="M19,6l-1,14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5,6" />
                        </svg>
                      </button>
                    </div>
                    <div class="eval-entry-text"><?= e($ev['description']) ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

          </div><!-- /eval-body -->

          <div class="eval-section-footer">
            <svg class="eval-footer-icon" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="12" />
              <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            <span class="eval-footer-text">
              As avaliações guardadas aqui são visíveis pelo(a) paciente <strong><?= e($patient['name']) ?></strong> na sua área pessoal.
              Cada nota fica associada à data atual.
            </span>
          </div>

        </div><!-- /eval-section -->
      </div>
    </div>
  </div>

  <div class="toast" id="toast">
    <svg viewBox="0 0 24 24">
      <polyline points="20,6 9,17 4,12" />
    </svg>
    <span id="toastMsg"></span>
  </div>

  <script>
    window.SESSIONS_FROM_DB = <?= json_encode($sessions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.SPARK_DATA = <?= json_encode($sparkData, JSON_UNESCAPED_UNICODE) ?>;
    window.CURRENT_LEVEL = <?= (int)$currentLevel ?>;
    window.PATIENT_ID = <?= (int)$patientId ?>;
    window.DOCTOR_ID = <?= (int)$authDoctorId ?>;
  </script>

  <script>
    /* ── CURSOR ── */
    const dot = document.getElementById('cursorDot');
    const cross = document.getElementById('cursorCross');
    document.addEventListener('mousemove', e => {
      dot.style.left = e.clientX + 'px';
      dot.style.top = e.clientY + 'px';
      cross.style.left = e.clientX + 'px';
      cross.style.top = e.clientY + 'px';
    });

    function bindCursor() {
      document.querySelectorAll('a,button,.session-item,.nav-item,.stat-card,.level-btn').forEach(el => {
        el.addEventListener('mouseenter', () => {
          dot.style.width = '11px';
          dot.style.height = '11px';
          dot.style.background = 'var(--teal-d)';
        });
        el.addEventListener('mouseleave', () => {
          dot.style.width = '6px';
          dot.style.height = '6px';
          dot.style.background = 'var(--ink)';
        });
      });
    }
    bindCursor();

    document.getElementById('topbarDate').textContent =
      new Date().toLocaleDateString('pt-PT', {
        weekday: 'short',
        day: 'numeric',
        month: 'short'
      });

    /* ── SESSIONS ── */
    const SESSIONS = window.SESSIONS_FROM_DB;
    const PER_PAGE = 6;
    let sessPage = 1;

    function precisionClass(p) {
      return p >= 80 ? 'pill-green' : p >= 65 ? 'pill-amber' : '';
    }

    function renderSessions() {
      const start = (sessPage - 1) * PER_PAGE;
      const page = SESSIONS.slice(start, start + PER_PAGE);

      document.getElementById('sessionList').innerHTML = page.map(s => `
    <div class="session-item">
      <div class="session-num">#${s.num}</div>
      <div class="session-info">
        <div class="session-title">${s.desc}</div>
        <div class="session-date">${s.date} · Nível ${s.level}</div>
      </div>
      <div class="session-badges">
        <span class="metric-pill ${precisionClass(s.precision)}">
          <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22,4 12,14.01 9,11.01"/></svg>
          ${s.precision}%
        </span>
        <span class="metric-pill">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
          ${s.time}
        </span>
      </div>
      <a href="session-evaluation.php?id=${s.id}&patient=${window.PATIENT_ID}" class="btn-sm">
        Ver
        <svg viewBox="0 0 24 24"><polyline points="9,18 15,12 9,6"/></svg>
      </a>
    </div>
  `).join('');

      const total = SESSIONS.length;
      const end = Math.min(start + PER_PAGE, total);
      document.getElementById('sessPageInfo').textContent = total > 0 ?
        `Mostrando ${start + 1}–${end} de ${total}` :
        'Sem sessões registadas';
      document.getElementById('sessPageNum').textContent = sessPage;
      bindCursor();
    }

    function sessChangePage(dir) {
      const max = Math.ceil(SESSIONS.length / PER_PAGE) || 1;
      sessPage = Math.max(1, Math.min(max, sessPage + dir));
      renderSessions();
    }

    /* ── SPARKLINE ── */
    const sparkData = window.SPARK_DATA;
    const sparkWrap = document.getElementById('miniSpark');
    const sparkMax = Math.max(...sparkData, 1);
    sparkData.forEach(v => {
      const b = document.createElement('div');
      b.className = 'spark-bar';
      b.style.height = Math.round((v / sparkMax) * 28) + 'px';
      b.style.opacity = 0.4 + 0.6 * (v / sparkMax);
      sparkWrap.appendChild(b);
    });

    /* ── LEVEL SELECTOR ── */
    const LEVEL_DESC = [
      'Movimentos simples e lentos. Ideal para início de reabilitação ou após período inativo.',
      'Sequências de movimento com ritmo moderado.',
      'Exercícios com maior exigência de coordenação e tempo de reação.',
      'Alta precisão e velocidade. Para pacientes em fase avançada.',
      'Nível máximo. Requer excelente coordenação motora e condição física.',
    ];

    let currentLevel = window.CURRENT_LEVEL;
    let selectedLevel = window.CURRENT_LEVEL;

    function renderLevels() {
      const wrap = document.getElementById('levelSelector');
      wrap.innerHTML = '';
      for (let i = 1; i <= 3; i++) {
        const btn = document.createElement('button');
        btn.className = 'level-btn' +
          (i === currentLevel ? ' current' : '') +
          (i === selectedLevel ? ' selected' : '');
        btn.innerHTML = `<span class="lv-num">${i}</span><span class="lv-lbl">Nível</span>`;
        btn.onclick = () => {
          selectedLevel = i;
          renderLevels();
        };
        wrap.appendChild(btn);
      }
      document.getElementById('levelInfoText').textContent = LEVEL_DESC[selectedLevel - 1];
      bindCursor();
    }

    function saveLevel() {
      fetch('api/update_level.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            patient_id: window.PATIENT_ID,
            level: selectedLevel
          })
        })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            currentLevel = selectedLevel;
            renderLevels();
          }
          showToast(data.success ?
            `Nível ${selectedLevel} guardado` :
            'Erro ao guardar o nível');
        })
        .catch(() => {
          currentLevel = selectedLevel;
          renderLevels();
          showToast(`Nível ${selectedLevel} guardado`);
        });
    }

    function showToast(msg) {
      const t = document.getElementById('toast');
      document.getElementById('toastMsg').textContent = msg;
      t.classList.add('show');
      setTimeout(() => t.classList.remove('show'), 3000);
    }

    renderSessions();
    renderLevels();

    /* ════════════════════════════════════════════
   AVALIAÇÃO CLÍNICA
════════════════════════════════════════════ */
    const textarea = document.getElementById('evalTextarea');
    const submitBtn = document.getElementById('evalSubmitBtn');
    const charCount = document.getElementById('evalCharCount');
    const sendingEl = document.getElementById('evalSending');
    const historyList = document.getElementById('evalHistoryList');
    const emptyState = document.getElementById('evalEmptyState');
    const countPill = document.getElementById('evalCountPill');
    let evalCount = window.EVAL_COUNT;

    function updateCharCount() {
      const len = textarea.value.trim().length;
      const full = textarea.value.length;
      charCount.textContent = `${full} / 2000 caracteres`;
      charCount.className = 'eval-char-count' + (full > 1800 ? (full >= 2000 ? ' over' : ' warn') : '');
      submitBtn.disabled = len < 5;
    }

    function insertTag(btn, text) {
      const cur = textarea.value;
      const sep = cur.length > 0 && !cur.endsWith(' ') && !cur.endsWith('\n') ? ' ' : '';
      textarea.value = cur + sep + text + ' ';
      textarea.focus();
      updateCharCount();
      document.querySelectorAll('.eval-tag').forEach(t => t.classList.remove('active'));
      btn.classList.add('active');
      setTimeout(() => btn.classList.remove('active'), 800);
    }

    function clearForm() {
      textarea.value = '';
      updateCharCount();
    }

    function updatePill() {
      countPill.textContent = `${evalCount} ${evalCount === 1 ? 'nota' : 'notas'}`;
    }

    function toggleEmpty() {
      if (evalCount === 0) {
        emptyState.style.display = 'flex';
        historyList.style.display = 'none';
      } else {
        emptyState.style.display = 'none';
        historyList.style.display = 'flex';
      }
    }

    async function submitEval() {
      const text = textarea.value.trim();
      if (text.length < 5) return;

      submitBtn.disabled = true;
      sendingEl.classList.add('visible');

      try {
        const res = await fetch('api/save_evaluation.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            patient_id: window.PATIENT_ID,
            doctor_id: window.DOCTOR_ID,
            text: text
          })
        });

        const data = await res.json();

        if (data.success) {

          /* data atual */
          const today = new Date().toLocaleDateString('pt-PT', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
          });

          const entry = document.createElement('div');
          entry.className = 'eval-entry eval-entry-new';
          entry.id = 'eval-' + data.id;

          entry.innerHTML = `
        <div class="eval-entry-header">
          <span class="eval-entry-date">
            <svg viewBox="0 0 24 24">
              <rect x="3" y="4" width="18" height="18" rx="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/>
              <line x1="8" y1="2" x2="8" y2="6"/>
              <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            ${today}
          </span>

          <button class="eval-entry-del" title="Apagar avaliação"
            onclick="deleteEval(${data.id}, this)">
            <svg viewBox="0 0 24 24">
              <polyline points="3,6 5,6 21,6"/>
              <path d="M19,6l-1,14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5,6"/>
            </svg>
          </button>
        </div>

        <div class="eval-entry-text">
          ${escHtml(text)}
        </div>
      `;

          historyList.prepend(entry);

          evalCount++;
          updatePill();
          toggleEmpty();
          clearForm();

          showToast('Avaliação guardada com sucesso');

        } else {
          showToast(data.error || 'Erro ao guardar avaliação', true);
          submitBtn.disabled = false;
        }

      } catch (e) {
        showToast('Erro de ligação ao servidor', true);
        submitBtn.disabled = false;
      } finally {
        sendingEl.classList.remove('visible');
      }
    }

    async function deleteEval(id, btn) {
      if (!confirm('Apagar esta avaliação? Esta ação não pode ser desfeita.')) return;
      btn.disabled = true;

      try {
        const res = await fetch('api/delete_evaluation.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            id
          })
        });
        const data = await res.json();

        if (data.success) {
          const entry = document.getElementById('eval-' + id);
          if (entry) {
            entry.style.transition = 'opacity .25s, transform .25s';
            entry.style.opacity = '0';
            entry.style.transform = 'translateX(12px)';
            setTimeout(() => {
              entry.remove();
              evalCount--;
              updatePill();
              toggleEmpty();
            }, 280);
          }
          showToast('Avaliação apagada');
        } else {
          showToast(data.error || 'Erro ao apagar', true);
          btn.disabled = false;
        }
      } catch {
        showToast('Erro de ligação ao servidor', true);
        btn.disabled = false;
      }
    }

    function escHtml(s) {
      return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    /* ── Inicializar estado do histórico ── */
    toggleEmpty();


    function scrollToEvaluation() {
      const el = document.getElementById('evalSection');
      if (!el) return;

      el.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });

      // opcional: focar textarea automaticamente
      setTimeout(() => {
        const ta = document.getElementById('evalTextarea');
        if (ta) ta.focus();
      }, 400);
    }
  </script>

  </div>


</body>

</html>