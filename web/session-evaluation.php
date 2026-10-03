<?php
require_once __DIR__ . '/auth.php';
require_role(['doctor', 'admin']);

/* ══════════════════════════════════════════════════════
   PARÂMETROS
   ?id=     → GameSession.id  (sessão clínica)
   ?patient → Patient.id
══════════════════════════════════════════════════════ */
$sessionId = isset($_GET['id'])      ? (int)$_GET['id']      : 0;
$patientId = isset($_GET['patient']) ? (int)$_GET['patient'] : 0;

if ($sessionId <= 0 || $patientId <= 0) {
  header('Location: menu_medico.php');
  exit;
}

$userName     = $_SESSION['user_name'] ?? 'Médico';
$avatar       = strtoupper(mb_substr($userName, 0, 2, 'UTF-8'));
$authDoctorId = (int)($_SESSION['user_id'] ?? 0);

$pdo = db();

/* ══════════════════════════════════════════════════════
   DADOS DA GAMESESSION + JOGOS AGREGADOS + PACIENTE
══════════════════════════════════════════════════════ */
$sessStmt = $pdo->prepare("
    SELECT
        gs.id AS session_id,
        gs.session_date,
        COUNT(g.id) AS total_games,
        COALESCE(ROUND(AVG(g.final_score)), 0) AS avg_score,
        COALESCE(SUM(g.duration_minutes), 0) AS total_duration,
        MAX(g.difficulty) AS level,
        u.name AS patient_name,
        p.difficulty AS patient_level
    FROM GameSession gs
    INNER JOIN Patient p ON p.id = gs.patient_id
    INNER JOIN Users u ON u.id = p.id
    LEFT JOIN Game g ON g.game_group_id = gs.id
    WHERE gs.id = ? AND gs.patient_id = ?
    GROUP BY gs.id, gs.session_date, u.name, p.difficulty
");
$sessStmt->execute([$sessionId, $patientId]);
$game = $sessStmt->fetch();

if (!$game) {
  header('Location: menu_medico.php');
  exit;
}

/* ══════════════════════════════════════════════════════
   NÚMERO ORDINAL DA SESSÃO
   Conta quantas GameSession deste paciente têm
   session_date ≤ a desta, por ordem cronológica.
══════════════════════════════════════════════════════ */
$rankStmt = $pdo->prepare("
    SELECT COUNT(*) AS rank_num
    FROM GameSession
    WHERE patient_id = ?
      AND (session_date < ? OR (session_date = ? AND id <= ?))
");
$rankStmt->execute([
  $patientId,
  $game['session_date'],
  $game['session_date'],
  $sessionId
]);
$sessionNum = (int)$rankStmt->fetchColumn();

/* ══════════════════════════════════════════════════════
   HISTÓRICO DE SESSÕES (para gráficos — por GameSession)
   Agrega final_score e duration por sessão.
══════════════════════════════════════════════════════ */
$histStmt = $pdo->prepare("
    SELECT
        gs.id,
        gs.session_date,
        COALESCE(ROUND(AVG(g.final_score)), 0) AS final_score,
        COALESCE(SUM(g.duration_minutes), 0) AS duration_minutes,
        ROW_NUMBER() OVER (
            ORDER BY gs.session_date ASC, gs.id ASC
        ) AS session_order
    FROM GameSession gs
    LEFT JOIN Game g ON g.game_group_id = gs.id
    WHERE gs.patient_id = ?
    GROUP BY gs.id, gs.session_date
    ORDER BY gs.session_date ASC, gs.id ASC
");
$histStmt->execute([$patientId]);
$allSessions = $histStmt->fetchAll();

$last10 = array_slice($allSessions, -10);
$last5  = array_slice($allSessions, -5);
$last1  = array_slice($allSessions, -1);

function buildChartData(array $sessions): array
{
  return array_values(array_map(fn($s) => [
    'v' => (int)$s['final_score'],
    'l' => 'S' . (int)$s['session_order'],
    't' => (int)$s['duration_minutes'],
  ], $sessions));
}

$chartData = [
  1  => buildChartData($last1),
  5  => buildChartData($last5),
  10 => buildChartData($last10),
];

/* ══════════════════════════════════════════════════════
   MÉTRICAS DA SESSÃO ATUAL
══════════════════════════════════════════════════════ */
$dateFmt     = $game['session_date']
  ? date('d M Y', strtotime($game['session_date']))
  : '—';
$totalDur    = (int)$game['total_duration'];
$durationFmt = $totalDur > 0 ? $totalDur . 'm' : '—';
$precision   = (int)$game['avg_score'];
$gameLevel   = $game['level'] ?? $game['patient_level'];

/* Delta vs sessão anterior */
$prevStmt = $pdo->prepare("
    SELECT COALESCE(ROUND(AVG(g.final_score)), 0) AS avg_score
    FROM GameSession gs
    LEFT JOIN Game g ON g.game_group_id = gs.id
    WHERE gs.patient_id = ?
      AND (gs.session_date < ? OR (gs.session_date = ? AND gs.id < ?))
    GROUP BY gs.id
    ORDER BY gs.session_date DESC, gs.id DESC
    LIMIT 1
");
$prevStmt->execute([
  $patientId,
  $game['session_date'],
  $game['session_date'],
  $sessionId
]);
$prev      = $prevStmt->fetch();
$prevScore = $prev ? (int)$prev['avg_score'] : null;
$scoreDiff = $prevScore !== null ? $precision - $prevScore : null;

$patientName = $game['patient_name'];
$nameParts   = explode(' ', trim($patientName));
$initials    = strtoupper(
  mb_substr($nameParts[0] ?? '', 0, 1, 'UTF-8') .
    mb_substr(end($nameParts) ?: '', 0, 1, 'UTF-8')
);

/* ══════════════════════════════════════════════════════
   JOGO 2 - MATRIZ DE CORES
══════════════════════════════════════════════════════ */

$colorsStmt = $pdo->prepare("
    SELECT
        cg.ronda,
        cg.cor_acendeu,
        cg.cor_clicada,
        cg.acertou,
        g.id AS game_id
    FROM ColorGame cg
    INNER JOIN Game g ON g.id = cg.game_id
    WHERE g.game_group_id = ?
");
$colorsStmt->execute([$sessionId]);
$colorRows = $colorsStmt->fetchAll(PDO::FETCH_ASSOC);

$j2ErrorByColor = [];

foreach ($colorRows as $row) {
  $session = $row['game_id'];
  $color = strtolower($row['cor_acendeu']);

  if (!isset($j2ErrorByColor[$session])) {
    $j2ErrorByColor[$session] = [];
  }

  if (!isset($j2ErrorByColor[$session][$color])) {
    $j2ErrorByColor[$session][$color] = [
      'total' => 0,
      'errors' => 0
    ];
  }

  $j2ErrorByColor[$session][$color]['total']++;

  if ((int)$row['acertou'] === 0) {
    $j2ErrorByColor[$session][$color]['errors']++;
  }
}

$j2ErrorByColorChart = [];

foreach ($j2ErrorByColor as $sid => $colors) {
  $entry = [
    'session' => $sid,
    'colors'  => []
  ];

  foreach ($colors as $color => $stats) {
    $entry['colors'][] = [
      'color'  => $color,
      'errors' => (int)$stats['errors'],
      'total'  => (int)$stats['total']
    ];
  }

  $j2ErrorByColorChart[] = $entry;
}

$j2ProgressStmt = $pdo->prepare("
    SELECT
        gs.id AS session_id,
        g.id AS game_id,
        cg.cor_acendeu,
        cg.acertou
    FROM GameSession gs
    INNER JOIN Game g ON g.game_group_id = gs.id
    INNER JOIN ColorGame cg ON cg.game_id = g.id
    WHERE gs.patient_id = ?
    ORDER BY gs.session_date ASC, gs.id ASC
");

$j2ProgressStmt->execute([$patientId]);
$j2ProgressRaw = $j2ProgressStmt->fetchAll(PDO::FETCH_ASSOC);

$j2ProgressGrouped = [];

foreach ($j2ProgressRaw as $row) {
  $sid = $row['game_id'];
  $color = strtolower($row['cor_acendeu']);

  if (!isset($j2ProgressGrouped[$sid])) {
    $j2ProgressGrouped[$sid] = [];
  }

  if (!isset($j2ProgressGrouped[$sid][$color])) {
    $j2ProgressGrouped[$sid][$color] = [
      'total' => 0,
      'errors' => 0
    ];
  }

  $j2ProgressGrouped[$sid][$color]['total']++;

  if ((int)$row['acertou'] === 0) {
    $j2ProgressGrouped[$sid][$color]['errors']++;
  }
}

$j2ProgressChart = [];

foreach ($j2ProgressGrouped as $sid => $colors) {
  $entry = [
    'session' => $sid,
    'colors' => []
  ];

  foreach ($colors as $color => $stats) {
    $entry['colors'][] = [
      'color'  => $color,
      'errors' => (int)$stats['errors'],
      'total'  => (int)$stats['total']
    ];
  }

  $j2ProgressChart[] = $entry;
}

$mistakesStmt = $pdo->prepare("
    SELECT
        m.timestamp,
        m.description,
        m.game_id,
        g.game_group_id
    FROM Mistakes m
    INNER JOIN Game g ON g.id = m.game_id
    WHERE g.game_group_id = ?
    ORDER BY m.timestamp ASC
");

$mistakesStmt->execute([$sessionId]);
$mistakesRows = $mistakesStmt->fetchAll(PDO::FETCH_ASSOC);

$j1Mistakes = $mistakesRows ?? [];
$j2Colors   = $colorRows ?? [];
$j3Games    = $allSessions ?? [];

/*---------------JOGO 1 -------------*/
$j1GamesStmt = $pdo->prepare("
    SELECT 
        g.final_score,
        g.duration_minutes,
        g.id
    FROM Game g
    WHERE g.game_group_id = ?
      AND g.name = 'Obstáculos'
    ORDER BY g.id ASC
");

$j1GamesStmt->execute([$sessionId]);
$j1Games = $j1GamesStmt->fetchAll();

$currentGameIds = array_column($pdo->query("
    SELECT id FROM Game WHERE game_group_id = $sessionId
")->fetchAll(), 'id');

$j1HistoryStmt = $pdo->prepare("
    SELECT
        gs.id,
        gs.session_date,
        g.id AS game_id,
        g.final_score,
        g.duration_minutes,

        ROW_NUMBER() OVER (
            ORDER BY gs.session_date ASC, gs.id ASC
        ) AS session_order

    FROM GameSession gs
    INNER JOIN Game g
        ON g.game_group_id = gs.id

    WHERE gs.patient_id = ?
      AND g.name = 'Obstáculos'

    ORDER BY gs.session_date ASC, gs.id ASC
");

$j1HistoryStmt->execute([$patientId]);
$j1History = $j1HistoryStmt->fetchAll(PDO::FETCH_ASSOC);

$mistakesByGameStmt = $pdo->prepare("
    SELECT m.game_id, COUNT(*) as total_mistakes
    FROM Mistakes m
    INNER JOIN Game g ON g.id = m.game_id
    WHERE g.game_group_id = ?
    GROUP BY m.game_id
    ORDER BY m.game_id ASC
");
$mistakesByGameStmt->execute([$sessionId]);
$mistakesByGame = $mistakesByGameStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$j2Total = count($j2Colors);
$j2Errors = count(array_filter($j2Colors, fn($c) => !$c['acertou']));
$j2Accuracy = $j2Total > 0
  ? round((($j2Total - $j2Errors) / $j2Total) * 100)
  : 0;

$j3Proxy = array_map(function ($g) {
  return [
    'session' => $g['session_order'],
    'time' => $g['duration_minutes'], // proxy
    'score' => $g['final_score']
  ];
}, $allSessions);

$j3Stats = [
  'hits' => 0,
  'early' => 0,
  'timeout' => 0
];

$j3GameStmt = $pdo->prepare("
    SELECT id, final_score
    FROM Game
    WHERE game_group_id = ?
      AND name = 'Touch'
");

$j3GameStmt->execute([$sessionId]);
$j3Game = $j3GameStmt->fetch(PDO::FETCH_ASSOC);

if ($j3Game) {

  $mistakeStmt = $pdo->prepare("
        SELECT description
        FROM Mistakes
        WHERE game_id = ?
    ");

  $mistakeStmt->execute([$j3Game['id']]);

  $mistakes = $mistakeStmt->fetchAll(PDO::FETCH_COLUMN);

  foreach ($mistakes as $desc) {

    if (stripos($desc, 'Muito Cedo') !== false) {
      $j3Stats['early']++;
    } elseif (stripos($desc, 'Timeout') !== false) {
      $j3Stats['timeout']++;
    }
  }

  /*
     * Acertos = total de rondas - erros
     * Assumindo 7 rondas
     */
  $totalRounds = 7;

  $errors = $j3Stats['early'] + $j3Stats['timeout'];

  $j3Stats['hits'] = max(0, $totalRounds - $errors);
}

$j3DonutHistory = [];

$j3HistoryStmt = $pdo->prepare("
    SELECT 
    gs.id AS session_id,
    g.id AS game_id
FROM GameSession gs
INNER JOIN Game g 
    ON g.game_group_id = gs.id
WHERE gs.patient_id = ?
  AND g.name = 'Touch'
");

$j3HistoryStmt->execute([$patientId]);

$sessions = $j3HistoryStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($sessions as $gameRow) {
  $sId   = (int)$gameRow['session_id'];
  $gId   = (int)$gameRow['game_id'];

  $mistStmt = $pdo->prepare("
    SELECT description
    FROM Mistakes
    WHERE game_id = ?
  ");
  $mistStmt->execute([$gId]);
  $mistakes = $mistStmt->fetchAll(PDO::FETCH_COLUMN);

  $early   = 0;
  $timeout = 0;

  foreach ($mistakes as $desc) {
    if (stripos($desc, 'Cedo') !== false)    $early++;
    if (stripos($desc, 'Timeout') !== false) $timeout++;
  }

  $totalRounds = 7;
  $hits = max(0, $totalRounds - $early - $timeout);

  $j3DonutHistory[] = [
    'session' => $sId,
    'hits'    => $hits,
    'early'   => $early,
    'timeout' => $timeout
  ];
}
?>
<!DOCTYPE html>
<html lang="pt">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>RehabBox — Avaliação da Sessão #<?= $sessionNum ?></title>
  <link rel="icon" type="image/png" href="./assets/images/logo1.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,300;12..96,400;12..96,500;12..96,600;12..96,700;12..96,800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">

  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
  <!-- Stylesheet -->
  <link rel="stylesheet" href="./assets/css/style.css">
  <link rel="stylesheet" href="./assets/css/session-evaluation.css">
</head>

<body>

  <div class="cursor-dot" id="cursorDot"></div>
  <div class="cursor-cross" id="cursorCross"></div>

  <div class="layout">

    <!-- ══════ SIDEBAR ══════ -->
    <aside class="sidebar">
      <a href="doctor.php" class="sidebar-logo">
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

      <a href="doctor.php" class="nav-item">
        <svg viewBox="0 0 24 24">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
          <circle cx="9" cy="7" r="4" />
          <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
          <path d="M16 3.13a4 4 0 0 1 0 7.75" />
        </svg>
        Pacientes
      </a>
      <a href="patient-details.php?id=<?= $patientId ?>" class="nav-item">
        <svg viewBox="0 0 24 24">
          <rect x="3" y="4" width="18" height="18" rx="2" />
          <line x1="16" y1="2" x2="16" y2="6" />
          <line x1="8" y1="2" x2="8" y2="6" />
          <line x1="3" y1="10" x2="21" y2="10" />
        </svg>
        Sessões
      </a>
      <a href="#" class="nav-item active">
        <svg viewBox="0 0 24 24">
          <path d="M9 11l3 3L22 4" />
          <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
        </svg>
        Avaliação
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
          <div class="doctor-avatar"><?= e($avatar) ?></div>
          <div>
            <div class="doctor-name"><?= e($userName) ?></div>
            <div class="doctor-role">Médico</div>
          </div>
          <a class="doctor-logout" title="Sair" href="logout.php">
            <svg viewBox="0 0 24 24">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
              <polyline points="16,17 21,12 16,7" />
              <line x1="21" y1="12" x2="9" y2="12" />
            </svg>
          </a>
        </div>
      </div>
    </aside>

    <!-- ══════ MAIN ══════ -->
    <div class="main">

      <div class="topbar">
        <a href="patient-details.php?id=<?= $patientId ?>" class="topbar-back" title="Voltar">
          <svg viewBox="0 0 24 24">
            <polyline points="15,18 9,12 15,6" />
          </svg>
        </a>
        <span class="topbar-title">Dashboard de Avaliação</span>
        <span class="topbar-sep"></span>
        <span class="topbar-breadcrumb">
          RehabBox / Médico / <?= e($patientName) ?> / Sessão #<?= $sessionNum ?>
        </span>
        <div class="topbar-right">
          <select class="filter-select" id="sessionFilter" onchange="updateCharts()">
            <option value="1">Sessão Atual</option>
            <option value="5" selected>Últimas 5 sessões</option>
            <option value="10">Últimas 10 sessões</option>
          </select>
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

        <!-- SESSION HERO -->
        <div class="session-hero">
          <div class="session-hero-left">
            <div class="session-avatar"><?= $sessionNum ?></div>
            <div>
              <h1 class="session-hero-title">
                Sessão <em>#<?= $sessionNum ?></em> — <?= e($patientName) ?>
              </h1>
              <div class="session-hero-meta">
                <span>
                  <svg viewBox="0 0 24 24">
                    <rect x="3" y="4" width="18" height="18" rx="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                  </svg>
                  <?= e($dateFmt) ?>
                </span>
                <span>
                  <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" />
                    <polyline points="12,6 12,12 16,14" />
                  </svg>
                  Duração: <?= e($durationFmt) ?>
                </span>
                <span>
                  <svg viewBox="0 0 24 24">
                    <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26 12,2" />
                  </svg>
                  Nível <?= (int)$gameLevel ?>
                </span>
              </div>
            </div>
          </div>
          <div style="display:flex;gap:.6rem;flex-wrap:wrap;">

            <a href="patient-details.php?id=<?= $patientId ?>" class="btn-sm">
              <svg viewBox="0 0 24 24">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
              </svg>
              Ver paciente
            </a>
          </div>
        </div>

        <!-- GLOBAL STATS -->
        <div class="stats-strip">
          <div class="stat-card" style="--accent:var(--teal-d)">
            <div class="stat-icon" style="background:rgba(70,129,137,.12);color:var(--teal-d)">
              <svg viewBox="0 0 24 24">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                <polyline points="22,4 12,14.01 9,11.01" />
              </svg>
            </div>
            <div class="stat-label">Precisão Global</div>
            <div class="stat-value"><?= $j1Games ? round(array_sum(array_column($j1Games, 'final_score')) / count($j1Games)) : 0 ?><small>%</small></div>
            <div class="stat-delta <?= $scoreDiff !== null && $scoreDiff >= 0 ? 'delta-up' : 'delta-down' ?>">
              <?php if ($scoreDiff !== null): ?>
                <?= $scoreDiff >= 0 ? '↑' : '↓' ?> <?= $scoreDiff >= 0 ? '+' : '' ?><?= $scoreDiff ?>% vs sessão anterior
              <?php else: ?>
                — primeira sessão
              <?php endif; ?>
            </div>
          </div>

          <div class="stat-card" style="--accent:var(--teal-m)">
            <div class="stat-icon" style="background:rgba(119,172,162,.12);color:var(--teal-m)">
              <svg viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10" />
                <polyline points="12,6 12,12 16,14" />
              </svg>
            </div>
            <div class="stat-label">Duração Total</div>
            <div class="stat-value"><?= $game['total_duration'] ?><small>min</small></div>
            <div class="stat-delta delta-neu">— duração da sessão</div>
          </div>

          <div class="stat-card" style="--accent:#3B82F6">
            <div class="stat-icon" style="background:rgba(59,130,246,.12);color:#3B82F6">
              <svg viewBox="0 0 24 24">
                <path d="M3 12l5-5 4 4 5-6 4 4" />
              </svg>
            </div>
            <div class="stat-label">Nível</div>
            <div class="stat-value"><?= $gameLevel ?></div>
            <div class="stat-delta delta-up">↑ dificuldade</div>
          </div>

          <div class="stat-card" style="--accent:var(--amber)">
            <div class="stat-icon" style="background:rgba(245,158,11,.12);color:var(--amber)">
              <svg viewBox="0 0 24 24">
                <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26 12,2" />
              </svg>
            </div>
            <div class="stat-label">Sessão Nº</div>
            <div class="stat-value"><?= $sessionNum ?></div>
            <div class="stat-delta delta-up">— do paciente</div>
          </div>
        </div>

        <?php /* ── GAME CARDS — colar em substituição dos três .game-card existentes ── */ ?>




        <!-- ════ JOGO 1 — LABIRINTO DE CORES ════ -->
        <div class="game-card">
          <div class="game-card-header">
            <div class="game-card-title-row">
              <div class="game-num" style="background:rgba(59,130,246,.1);color:#3B82F6">J2</div>
              <div>
                <div class="game-card-title">Jogo 1 — Labirinto de Cores</div>
                <div style="font-size:.72rem;color:rgba(18,55,70,.45);margin-top:.1rem">Precisão cognitiva e reconhecimento de cores</div>
              </div>
              <span class="game-tag" style="background:rgba(59,130,246,.1);color:#3B82F6">Cognição</span>
            </div>
          </div>

          <div class="charts-row">

            <!-- Gráfico 1: Heatmap -->
            <div class="chart-box">
              <div class="chart-title">
                Sequência de cores
                <span class="chart-subtitle">acertos por cor × ronda</span>
              </div>
              <div id="j2Heatmap" class="heatmap-wrap"></div>
            </div>

            <!-- Gráfico 2: Barras % erro por cor, várias sessões -->
            <div class="chart-box">
              <div class="chart-title">
                Erros por cor — várias sessões
                <span class="chart-subtitle">% de erro por cor</span>
              </div>
              <div style="position:relative;width:100%;height:160px">
                <canvas id="j2ErrorBars"
                  role="img"
                  aria-label="Percentagem de erros por cor em cada sessão">
                  Distribuição de erros por cor ao longo das sessões.
                </canvas>
              </div>
              <div class="grouped-legend" style="margin-top:.6rem">
                <div class="gl-item">
                  <div class="gl-dot" style="background:rgba(37,99,235,.75)"></div>Azul
                </div>
                <div class="gl-item">
                  <div class="gl-dot" style="background:rgba(22,163,74,.75)"></div>Verde
                </div>
                <div class="gl-item">
                  <div class="gl-dot" style="background:rgba(220,38,38,.75)"></div>Vermelho
                </div>
              </div>
            </div>

          </div><!-- /charts-row -->

          <div class="game-card-metrics">
            <div class="game-metric">
              <div class="gm-label">Acertos</div>
              <div class="gm-value"><?= $j2Accuracy ?><small>%</small></div>
              <div class="gm-delta delta-up">↑ estimado</div>
            </div>
            <div class="game-metric">
              <div class="gm-label">Total tentativas</div>
              <div class="gm-value"><?= $j2Total ?></div>
              <div class="gm-delta delta-neu">— esta sessão</div>
            </div>
            <div class="game-metric">
              <div class="gm-label">Erros</div>
              <div class="gm-value"><?= $j2Errors ?></div>
              <div class="gm-delta <?= $j2Errors > 0 ? 'delta-down' : 'delta-up' ?>">
                <?= $j2Errors > 0 ? '↑ a melhorar' : '↓ sem erros' ?>
              </div>
            </div>
            <div class="game-metric">
              <div class="gm-label">Evolução</div>
              <div class="gm-value" style="color:var(--teal-d)">↑<small> positivo</small></div>
              <div class="gm-delta delta-up">↑ em melhoria</div>
            </div>
          </div>
        </div>

        <!-- ════ J2 + J3 LADO A LADO ════ -->
        <div class="charts-row2" style="align-items:stretch; gap:1rem;">

          <!-- ════ JOGO 2 — OBSTÁCULOS ════ -->
          <div class="game-card" style="flex:1">
            <div class="game-card-header">
              <div class="game-card-title-row">
                <div class="game-num" style="background:rgba(70,129,137,.12);color:var(--teal-d)">J1</div>
                <div>
                  <div class="game-card-title">Jogo 2 — Obstáculos</div>
                  <div style="font-size:.72rem;color:rgba(18,55,70,.45);margin-top:.1rem">
                    Tempo de reação e precisão motora
                  </div>
                </div>
                <span class="game-tag" style="background:rgba(70,129,137,.1);color:var(--teal-d)">
                  Coordenação
                </span>
              </div>
            </div>

            <!-- GRÁFICO J2 -->
            <div class="chart-box">
              <div class="chart-title">
                Desempenho no jogo Obstáculos
                <span class="chart-subtitle">análise comparativa</span>
              </div>

              <div style="position:relative;width:100%;height:160px">
                <canvas id="j1Bars"
                  role="img"
                  aria-label="Barras comparativas de acertos por sessão">
                </canvas>
              </div>

            </div>
          </div>

          <!-- ════ JOGO 3 — RITMO E PRECISÃO ════ -->
          <div class="game-card" style="flex:1">
            <div class="game-card-header">
              <div class="game-card-title-row">
                <div class="game-num" style="background:rgba(124,58,237,.1);color:#7C3AED">J3</div>
                <div>
                  <div class="game-card-title">Jogo 3 — Ritmo e Precisão</div>
                  <div style="font-size:.72rem;color:rgba(18,55,70,.45);margin-top:.1rem">
                    Mobilidade, ritmo e coordenação motora
                  </div>
                </div>
                <span class="game-tag" style="background:rgba(124,58,237,.1);color:#7C3AED">
                  Mobilidade
                </span>
              </div>
            </div>

            <!-- GRÁFICO J3 -->
            <div class="chart-box">
              <div class="chart-title">
                Distribuição de respostas
                <span class="chart-subtitle">acertos · cedo · tarde</span>
              </div>

              <div class="donut-wrap">
                <div style="position:relative;width:130px;height:130px;flex-shrink:0">
                  <canvas id="j3Donut"
                    role="img"
                    aria-label="Distribuição de respostas no Jogo 3">
                  </canvas>
                </div>

                <div class="donut-legend" id="j3DonutLegend"></div>
              </div>

            </div>
          </div>

        </div>

      </div><!-- /page -->
    </div><!-- /main -->
  </div><!-- /layout -->



  <!-- Dados PHP → JS -->
  <script>
    window.J1_DATA = <?= json_encode($j1Games ?? []) ?>;
    window.J1_HISTORY = <?= json_encode($j1History) ?>;
    window.J1_MISTAKES = <?= json_encode($mistakesByGame ?? []) ?>;
    window.J2_DATA = <?= json_encode($colorRows ?? []) ?>;
    window.J2_PROGRESS = <?= json_encode($j2ProgressChart) ?>;
    window.J3_DATA = <?= json_encode($allSessions ?? []) ?>;
    window.J3_STATS = <?= json_encode($j3Stats) ?>;
    window.J3_DONUT_DATA = <?= json_encode($j3DonutHistory) ?>;
    window.CURRENT_SESSION_ID = <?= $sessionId ?>;
    window.PRECISION = <?= $j1Games ? round(array_sum(array_column($j1Games, 'final_score')) / count($j1Games)) : 0 ?>;
    window.SESSION_ID = <?= $sessionId ?>;
    window.PATIENT_ID = <?= $patientId ?>;
    window.DOCTOR_ID = <?= $authDoctorId ?>;
    window.COLOR_DATA = <?= json_encode($colorRows ?? []) ?>;
    window.J2_ERROR_BY_COLOR = <?= json_encode($j2ErrorByColorChart) ?>;
    window.CURRENT_GAME_IDS  = <?= json_encode($currentGameIds) ?>;

    /* ── CURSOR ── */
    const dot = document.getElementById('cursorDot');
    const cross = document.getElementById('cursorCross');

    if (dot && cross) {
      dot.style.opacity = '0';
      cross.style.opacity = '0';

      document.addEventListener('mousemove', e => {
        dot.style.opacity = '1';
        cross.style.opacity = '1';
        dot.style.left = e.clientX + 'px';
        dot.style.top = e.clientY + 'px';
        cross.style.left = e.clientX + 'px';
        cross.style.top = e.clientY + 'px';
      });

      document.addEventListener('mouseleave', () => {
        dot.style.opacity = '0';
        cross.style.opacity = '0';
      });
    }
  </script>

  <script src="./assets/js/session-charts.js"></script>
</body>

</html>