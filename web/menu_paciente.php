<?php

declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_role(['patient']);

$userId    = $_SESSION['user_id'];
$userName  = $_SESSION['user_name'] ?? 'Paciente';
$userEmail = $_SESSION['user_email'] ?? '';

/* ═══════════════════════════════════════
   1. PERFIL DO PACIENTE
═══════════════════════════════════════ */
$stmt = db()->prepare("
    SELECT u.name, u.email, p.disease_status, p.difficulty, p.last_eval_check
    FROM Patient p
    INNER JOIN Users u ON u.id = p.id
    WHERE p.id = ?
");
$stmt->execute([$userId]);
$patient = $stmt->fetch();
$lastCheck = $patient['last_eval_check'] ?? '1970-01-01 00:00:00';
if (!$patient) {
  die('Perfil de paciente não encontrado.');
}

$nameParts = explode(' ', trim($patient['name']));

$firstName = $nameParts[0] ?? '';
$lastName  = end($nameParts) ?: '';

$initials = strtoupper(
  mb_substr($firstName, 0, 1, 'UTF-8') .
    mb_substr($lastName, 0, 1, 'UTF-8')
);
/* ═══════════════════════════════════════
   2. AVALIAÇÕES CLÍNICAS
═══════════════════════════════════════ */
$stmtEval = db()->prepare("
    SELECT
        id,
        description,
        ev_date
    FROM Evaluation
    WHERE patient_id = ?
    ORDER BY ev_date DESC
");
$stmtEval->execute([$userId]);
$evaluations = $stmtEval->fetchAll();

$latestEval = $evaluations[0] ?? null;

/* ═══════════════════════════════════════
   3. SESSÕES (últimos 7 dias)
   ✔ substitui Game → GameSession + Game agregado
═══════════════════════════════════════ */
$stmtSessions = db()->prepare("
    SELECT
        gs.id,
        gs.session_date,
        COUNT(g.id) AS total_games,
        COALESCE(ROUND(AVG(g.final_score)),0) AS avg_score,
        COALESCE(SUM(g.duration_minutes),0) AS duration_minutes
    FROM GameSession gs
    LEFT JOIN Game g ON g.game_group_id = gs.id
    WHERE gs.patient_id = ?
      AND gs.session_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY gs.id, gs.session_date
    ORDER BY gs.session_date DESC
");
$stmtSessions->execute([$userId]);
$recentSessions = $stmtSessions->fetchAll();

/* array para JS */
$recentGamesArray = [];
foreach ($recentSessions as $s) {
  $recentGamesArray[] = [
    'session_id'       => (int)$s['id'],
    'date'             => date('d/m/Y', strtotime($s['session_date'])),
    'total_games'      => (int)$s['total_games'],
    'avg_score'        => (int)$s['avg_score'],
    'duration_minutes' => (int)$s['duration_minutes'],
  ];
}

/* ═══════════════════════════════════════
   4. NOTIFICAÇÕES (avaliações novas)
═══════════════════════════════════════ */

$stmtNew = db()->prepare("
    SELECT COUNT(*)
    FROM Evaluation
    WHERE patient_id = ?
      AND ev_date > ?
");
$stmtNew->execute([$userId, $lastCheck]);
$newEvalCount = (int)$stmtNew->fetchColumn();


/* marcar como lido */
if (isset($_GET['mark_read'])) {
    $update = db()->prepare("UPDATE Patient SET last_eval_check = NOW() WHERE id = ?");
    $update->execute([$userId]);
  header('Location: menu_paciente.php#avaliacoes');
    exit;
}

/* ═══════════════════════════════════════
   5. ARRAY DE AVALIAÇÕES (JS)
═══════════════════════════════════════ */
$evaluationsArray = [];

foreach ($evaluations as $ev) {
  $evaluationsArray[] = [
    'date' => $ev['ev_date']
      ? date('d/m/Y', strtotime($ev['ev_date']))
      : '—',
    'text' => $ev['description'] ?? '',
  ];
}

/* ═══════════════════════════════════════
   6. DADOS DO PACIENTE
═══════════════════════════════════════ */
$nameParts = explode(' ', trim($patient['name']));

$patientData = [
  'name'       => $patient['name'],
  'email'      => $patient['email'],
  'condition'  => $patient['disease_status'] ?? 'Não especificada',
  'difficulty' => (int)$patient['difficulty'],
];

/* ═══════════════════════════════════════
   7. CONFIG FINAL JS
═══════════════════════════════════════ */
$patientConfig = [
  'evaluations'  => $evaluationsArray,
  'recentGames'  => $recentGamesArray,
  'patientData'  => $patientData,
  'newEvalCount' => $newEvalCount,
];
?>

<!DOCTYPE html>
<html lang="pt">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>RehabBox — Painel do Paciente</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,300;12..96,400;12..96,500;12..96,600;12..96,700;12..96,800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./assets/css/style.css">
  <link rel="stylesheet" href="./assets/css/patient.css">
  <link rel="icon" type="image/png" href="./assets/images/logo1.png">
</head>

<body>

  <div class="cursor-dot" id="cursorDot"></div>
  <div class="cursor-cross" id="cursorCross"></div>

  <div class="layout">
    <aside class="sidebar">
      <a href="patient.php" class="sidebar-logo">
        <img src="./assets/images/logo1.png" alt="RehabBox logo">
        <span class="logo-wordmark">Rehab<span>Box</span></span>
      </a>
      <div class="sidebar-label">Navegação</div>

      <a href="#topo" class="nav-item active" id="navDashboard">
        <svg viewBox="0 0 24 24">
          <rect x="3" y="3" width="7" height="7" rx="1" />
          <rect x="14" y="3" width="7" height="7" rx="1" />
          <rect x="3" y="14" width="7" height="7" rx="1" />
          <rect x="14" y="14" width="7" height="7" rx="1" />
        </svg>
        Painel
      </a>
      <a href="#avaliacoes" class="nav-item" id="navAvaliacoes">
        <svg viewBox="0 0 24 24">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
          <polyline points="14,2 14,8 20,8" />
        </svg>
        Avaliações
      </a>
      <a href="#jogos-recentes" class="nav-item" id="navJogos">
        <svg viewBox="0 0 24 24">
          <polygon points="5,3 19,12 5,21 5,3" />
        </svg>
        Jogos Recentes
      </a>

      <div class="sidebar-divider"></div>
      <div class="sidebar-label">Conta</div>

      <a href="#" class="nav-item" id="navApagar">
        <svg viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="10" />
          <line x1="12" y1="8" x2="12" y2="12" />
          <line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
        Pedir Revogação de Consentimento
      </a>

      <div class="sidebar-footer">
        <div class="admin-pill">
          <div class="admin-avatar"><?= e($initials) ?></div>
          <div>
            <div class="admin-name"><?= e($patient['name']) ?></div>
            <div class="admin-role">Paciente</div>
          </div>
          <a class="admin-logout" title="Sair" href="logout.php">
            <svg viewBox="0 0 24 24">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
              <polyline points="16,17 21,12 16,7" />
              <line x1="21" y1="12" x2="9" y2="12" />
            </svg>
          </a>
        </div>
      </div>
    </aside>

    <div class="main" id="topo">
      <div class="topbar">
        <span class="topbar-title">Painel do Paciente</span>
        <span class="topbar-sep"></span>
        <span class="topbar-breadcrumb">RehabBox / Paciente</span>
        <div class="topbar-right">
          <span class="topbar-date" id="topbarDate"></span>

          <!-- Sino de notificações -->
          <?php if ($newEvalCount > 0): ?>
            <a href="#avaliacoes" class="topbar-notif has-notifications" title="<?= $newEvalCount ?> nova(s) avaliação(ões)">
              <svg viewBox="0 0 24 24" width="16" height="16" stroke="var(--ink)" fill="none" stroke-width="1.8" stroke-linecap="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
              </svg>
              <div class="notif-dot"></div>
            </a>
            <span style="font-size:0.7rem; font-weight:700; background:var(--red); color:#fff; padding:1px 7px; border-radius:100px;"><?= $newEvalCount ?></span>
          <?php else: ?>
            <a href="#avaliacoes" class="topbar-notif" title="Sem novas avaliações" style="opacity:0.5;">
              <svg viewBox="0 0 24 24" width="16" height="16" stroke="var(--ink)" fill="none" stroke-width="1.8" stroke-linecap="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
              </svg>
            </a>
          <?php endif; ?>
        </div>
      </div>

      <div class="page">
        <div class="patient-stack">
          <div class="patient-card">
            <div class="patient-info">
              <h2>O meu perfil</h2>
              <div class="patient-meta">
                <p><strong>Nome:</strong> <?= e($patient['name']) ?></p>
                <p><strong>Email:</strong> <?= e($patient['email']) ?></p>
                <p><strong>Condição:</strong> <?= e($patient['disease_status'] ?? 'Não especificada') ?></p>
                <p><strong>Dificuldade atual:</strong> Nível <?= (int)$patient['difficulty'] ?></p>
              </div>
            </div>
          </div>

          <div class="patient-card" id="avaliacoes">
            <div class="patient-info">
              <h2>Última Avaliação</h2>

              <div class="patient-meta">
                <p>
                  <strong>Data:</strong>
                  <span id="latestEvalDate">
                    <?php
                    if (!empty($latestEval['ev_date'])) {
                      echo date('d/m/Y', strtotime($latestEval['ev_date']));
                    } else {
                      echo '—';
                    }
                    ?>
                  </span>
                </p>
              </div>

              <div class="evaluation-text-box" id="latestEvalText">
                <?= $latestEval ? e($latestEval['description']) : 'Nenhuma avaliação registada.' ?>
              </div>
            </div>

            <div style="display: flex; align-items: center;">
              <button class="btn-action" id="openEvaluationsBtn">
                Ver todas as avaliações
              </button>

              <button class="btn-action btn-export" id="exportLatestBtn">
                Exportar
              </button>
            </div>
          </div>
        </div>

        <div class="metric-card" id="jogos-recentes" style="margin-top:2rem;">
          <div class="metric-card-header">
            <span class="metric-card-title">Sessões de jogo recentes</span>
            <span class="metric-pill">Últimos 7 dias</span>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Jogo</th>
                  <th>Data</th>
                  <th>Dificuldade</th>
                  <th>Pontuação</th>
                  <th>Duração</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($recentSessions)): ?>

                  <?php foreach ($recentSessions as $s): ?>
                    <tr>
                      <td><?= (int)$s['total_games'] ?> jogos</td>

                      <td>
                        <?= !empty($s['session_date'])
                          ? date('d/m/Y', strtotime($s['session_date']))
                          : '—' ?>
                      </td>

                      <td>
                        <?= isset($patient['difficulty'])
                          ? (int)$patient['difficulty']
                          : '—' ?>
                      </td>

                      <td>
                        <?= (int)$s['avg_score'] ?>%
                      </td>

                      <td>
                        <?= (int)$s['duration_minutes'] ?> min
                      </td>
                    </tr>
                  <?php endforeach; ?>

                <?php else: ?>

                  <tr>
                    <td colspan="5" style="text-align:center; opacity:0.6;">
                      Sem sessões recentes
                    </td>
                  </tr>

                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Avaliações -->
  <div class="modal-backdrop" id="modalAvaliacoes">
    <div class="modal">
      <div class="modal-header">
        <div class="modal-icon" style="background:rgba(70,129,137,.12);color:var(--teal-d)">
          <svg viewBox="0 0 24 24">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <polyline points="14,2 14,8 20,8" />
          </svg>
        </div>
        <div>
          <div class="modal-title">Histórico de Avaliações</div>
          <div class="modal-sub" id="modalEvalDate">Carregando...</div>
        </div>
        <button class="modal-close" id="closeModalAvaliacoes">
          <svg viewBox="0 0 24 24">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="eval-text-box" id="modalEvalText"></div>
      </div>
      <div class="modal-footer">
        <span id="modalEvalCounter">1 de 12</span>
        <div style="display:flex; gap:0.5rem;">
          <button class="btn-ghost-sm" id="prevEvalBtn">&larr; Anterior</button>
          <button class="btn-ghost-sm" id="nextEvalBtn">Seguinte &rarr;</button>
          <button class="btn-ghost-sm" id="exportModalBtn" title="Exportar esta avaliação">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
              <polyline points="7 10 12 15 17 10" />
              <line x1="12" y1="15" x2="12" y2="3" />
            </svg>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Eliminação -->
  <div class="modal-backdrop" id="modalApagar">
    <div class="modal">
      <div class="modal-header">
        <div class="modal-icon" style="background:rgba(232,64,64,.10);color:var(--red)">
          <svg viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="12" />
            <line x1="12" y1="16" x2="12.01" y2="16" />
          </svg>
        </div>
        <div>
          <div class="modal-title">Pedido de Revogação de Consentimento</div>
          <div class="modal-sub">Esta ação requer confirmação</div>
        </div>
        <button class="modal-close" id="closeModalApagar">
          <svg viewBox="0 0 24 24">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="danger-box">
          <p>Ao confirmar, será enviado um <strong>pedido de revogação</strong> dos dados da sua conta para a equipa RehabBox. Esta ação não pode ser revertida.</p>
        </div>
        <div class="form-group">
          <label>Escreva ELIMINAR para confirmar</label>
          <input id="confirmDeleteInput" type="text" placeholder="ELIMINAR">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-ghost-sm" id="cancelDeleteBtn">Cancelar</button>
        <button class="btn-danger" id="confirmDeleteBtn">
          <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2">
            <polyline points="3,6 5,6 21,6" />
            <path d="M19,6l-1,14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5,6" />
          </svg>
          Enviar Pedido
        </button>
      </div>
    </div>
  </div>

  <div class="toast" id="toast">
    <svg viewBox="0 0 24 24">
      <polyline points="20,6 9,17 4,12" />
    </svg>
    <span id="toastMsg"></span>
  </div>

  <!-- Configuração para o JavaScript -->
  <script>
    window.PATIENT_CONFIG = <?= json_encode($patientConfig, JSON_UNESCAPED_UNICODE) ?>;
  </script>

  <!-- JavaScript do painel do paciente -->
  <script src="./assets/js/patient.js"></script>

</body>

</html>