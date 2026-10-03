<?php
require_once __DIR__ . '/auth.php';
require_role(['doctor', 'admin']);

$userName = $_SESSION['user_name'] ?? 'Médico';
$userRole = $_SESSION['user_role'] ?? 'doctor';
$avatar   = strtoupper(mb_substr($userName, 0, 2, 'UTF-8'));

// ── Estatísticas globais do médico ──────────────────────────────────────────
$statsStmt = db()->query("
    SELECT
        (SELECT COUNT(*) FROM Patient) AS total_patients,

        (SELECT COUNT(DISTINCT gs.patient_id)
         FROM GameSession gs) AS active_patients,

        (SELECT COUNT(*) FROM GameSession) AS total_sessions,

        (SELECT COUNT(*)
         FROM GameSession
         WHERE session_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ) AS sessions_week,

        (SELECT COUNT(DISTINCT patient_id)
         FROM GameSession
         WHERE DATE(session_date) = CURDATE()
        ) AS sessions_today,

   (SELECT COUNT(*)
 FROM GameSession gs
 WHERE gs.session_date > COALESCE(
     (SELECT MAX(e.ev_date)
      FROM Evaluation e
      WHERE e.patient_id = gs.patient_id),
     '1970-01-01'
 )
) AS pending_evaluations
");
$stats = $statsStmt->fetch();

$pendingEvaluations = (int)$stats['pending_evaluations'];

// ── Lista de pacientes ───────────────────────────────────────────────────────
$patientsStmt = db()->query("
    SELECT
        u.id,
        u.name,
        u.email,
        p.difficulty,

        COUNT(DISTINCT gs.id) AS sessions,

        MAX(gs.session_date) AS last_session,

        COALESCE(
            ROUND(AVG(session_scores.session_score)),
            0
        ) AS avg_score

    FROM Patient p

    INNER JOIN Users u
        ON u.id = p.id

    LEFT JOIN GameSession gs
        ON gs.patient_id = p.id

    LEFT JOIN (
        SELECT
            game_group_id,
            AVG(final_score) AS session_score
        FROM Game
        WHERE game_group_id IS NOT NULL
        GROUP BY game_group_id
    ) session_scores
        ON session_scores.game_group_id = gs.id

    GROUP BY
        u.id,
        u.name,
        u.email,
        p.difficulty

    ORDER BY u.name ASC
");
$patientsRows = $patientsStmt->fetchAll();

// (restante do código sem alterações)

$patients = [];
foreach ($patientsRows as $p) {
    $nameParts = explode(' ', trim($p['name']));
    $initials  = strtoupper(
        mb_substr($nameParts[0] ?? '', 0, 1, 'UTF-8') .
        mb_substr(end($nameParts) ?: '', 0, 1, 'UTF-8')
    );

    $lastSession = $p['last_session']
        ? date('d/m/Y', strtotime($p['last_session']))
        : 'Sem sessões';

    $status = ((int)$p['sessions'] === 0) ? 'inactive' : 'active';

    $colors = [
        'linear-gradient(135deg,#468189,#77ACA2)',
        'linear-gradient(135deg,#0F766E,#77ACA2)',
        'linear-gradient(135deg,#1D4ED8,#3B82F6)',
        'linear-gradient(135deg,#15803D,#22C55E)',
        'linear-gradient(135deg,#7C3AED,#A78BFA)',
    ];
    $colorIndex = $p['id'] % count($colors);

    $patients[] = [
        'id'          => 'P' . str_pad((string)$p['id'], 3, '0', STR_PAD_LEFT),
        'dbId'        => (int)$p['id'],
        'name'        => $p['name'],
        'email'       => $p['email'],
        'initials'    => $initials,
        'color'       => $colors[$colorIndex],
        'level'       => (int)$p['difficulty'],
        'status'      => $status,
        'sessions'    => (int)$p['sessions'],
        'lastSession' => $lastSession,
        'avgScore'    => (int)$p['avg_score'],
    ];
}

$totalPatients  = (int)$stats['total_patients'];
$activePatients = (int)$stats['active_patients'];
$totalSessions  = (int)$stats['total_sessions'];
$sessionsWeek   = (int)$stats['sessions_week'];
$sessionsToday  = (int)$stats['sessions_today'];

?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>RehabBox — Área Clínica</title>
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

      <a href="menu_medico.php" class="sidebar-logo">
        <img src="./assets/images/logo1.png" alt="RehabBox logo">
        <span class="logo-wordmark">Rehab<span>Box</span></span>
      </a>

      <div class="sidebar-label">Clínica</div>

      <a href="menu_medico.php" class="nav-item active">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Pacientes
        <span class="badge"><?= $totalPatients ?></span>
      </a>
      <a href="#" class="nav-item">
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Sessões
      </a>
      <a href="#" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        Avaliação
        <span class="badge"><?= $pendingEvaluations ?></span>
      </a>
      <a href="#" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Relatórios
      </a>

      <div class="sidebar-divider"></div>
      <div class="sidebar-label">Configurações</div>

      <a href="#" class="nav-item">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M2 12h3m14 0h3M12 2v3m0 14v3M4.22 4.22l2.12 2.12m11.32 11.32l2.12 2.12M4.22 19.78l2.12-2.12m11.32-11.32l2.12-2.12"/></svg>
        Preferências
      </a>

      <div class="sidebar-footer">
        <div class="admin-pill">
          <div class="admin-avatar"><?= e($avatar) ?></div>
          <div>
            <div class="admin-name"><?= e($userName) ?></div>
            <div class="admin-role">Médico</div>
          </div>
          <a class="admin-logout" title="Sair" href="logout.php">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          </a>
        </div>
      </div>

    </aside>

    <!-- ══════ MAIN ══════ -->
    <div class="main">

      <div class="topbar">
        <span class="topbar-title">Painel Médico</span>
        <span class="topbar-sep"></span>
        <span class="topbar-breadcrumb">RehabBox / Médico / Pacientes</span>
        <div class="topbar-right">
          <span class="topbar-date" id="topbarDate"></span>
          <div class="topbar-notif">
            <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <div class="notif-dot"></div>
          </div>
        </div>
      </div>

      <div class="page">

        <!-- HEADER -->
        <div class="page-header">
          <div>
            <h1>Gestão de <em>Pacientes</em></h1>
            <p>Acompanhamento clínico e sessões de reabilitação.</p>
          </div>
          <!--<button class="btn-primary" onclick="showToast('Funcionalidade em desenvolvimento')">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Novo Paciente 
          </button> -->
        </div>

        <!-- STAT CARDS -->
        <div class="stats-grid">
          <div class="stat-card" style="--accent:var(--teal-d)">
            <div class="stat-icon" style="background:rgba(70,129,137,.12);color:var(--teal-d)">
              <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            </div>
            <div class="stat-label">Pacientes Ativos</div>
            <div class="stat-value"><?= $activePatients ?></div>
            <div class="stat-delta delta-up">↑ com sessões registadas</div>
          </div>

          <div class="stat-card" style="--accent:var(--teal-m)">
            <div class="stat-icon" style="background:rgba(119,172,162,.12);color:var(--teal-m)">
              <svg viewBox="0 0 24 24"><path d="M3 12l5-5 4 4 5-6 4 4"/></svg>
            </div>
            <div class="stat-label">Sessões Concluídas</div>
            <div class="stat-value"><?= (int)$stats['total_sessions'] ?></div>
            <div class="stat-delta delta-up">↑ <?= (int)$stats['sessions_week'] ?> esta semana</div>
          </div>

          <div class="stat-card" style="--accent:var(--amber)">
            <div class="stat-icon" style="background:rgba(245,158,11,.12);color:var(--amber)">
              <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            </div>
            <div class="stat-label">Avaliações Pendentes</div>
            <div class="stat-value"><?= $pendingEvaluations ?></div>
            <div class="stat-delta delta-warn">⚠ requerem atenção</div>
          </div>
        </div>

        <!-- SEARCH + FILTERS -->
        <div class="search-bar">
          <div class="search-wrap">
            <svg class="search-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input class="search-input" id="searchInput" type="text" placeholder="Pesquisar por nome ou ID…" oninput="filterPatients()">
          </div>
          <select class="filter-select" id="filterLevel" onchange="filterPatients()">
            <option value="">Todos os níveis</option>
            <option value="1">Nível 1</option>
            <option value="2">Nível 2</option>
            <option value="3">Nível 3</option>
          </select>
          <select class="filter-select" id="filterStatus" onchange="filterPatients()">
            <option value="">Todos os estados</option>
            <option value="active">Ativo</option>
            <option value="inactive">Inativo</option>
          </select>
          <span class="search-count" id="searchCount">— pacientes</span>
        </div>

        <!-- TABLE -->
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Paciente</th>
                <th>Nível</th>
                <th>Estado</th>
                <th>Sessões</th>
                <th>Última Sessão</th>
                <th>Precisão</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="patientTableBody"></tbody>
          </table>

          <div class="pagination">
            <span class="page-info" id="pageInfo">Mostrando 1–8 de 12</span>
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

  <!-- TOAST -->
  <div class="toast" id="toast">
    <svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>
    <span id="toastMsg"></span>
  </div>

<script>
  window.PATIENTS_FROM_DB = <?= json_encode($patients, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>

<script>
/* ── CURSOR ── */
const dot   = document.getElementById('cursorDot');
const cross = document.getElementById('cursorCross');
document.addEventListener('mousemove', e => {
  dot.style.left   = e.clientX + 'px'; dot.style.top   = e.clientY + 'px';
  cross.style.left = e.clientX + 'px'; cross.style.top = e.clientY + 'px';
});

function bindCursor() {
  document.querySelectorAll('a,button,.patient-row,.action-btn,.nav-item,.stat-card').forEach(el => {
    el.addEventListener('mouseenter', () => { dot.style.width='11px'; dot.style.height='11px'; dot.style.background='var(--teal-d)'; });
    el.addEventListener('mouseleave', () => { dot.style.width='6px';  dot.style.height='6px';  dot.style.background='var(--ink)'; });
  });
}
bindCursor();

/* ── DATE ── */
document.getElementById('topbarDate').textContent =
  new Date().toLocaleDateString('pt-PT', { weekday:'short', day:'numeric', month:'short' });

/* ── DATA ── */
const PATIENTS = window.PATIENTS_FROM_DB;
const PER_PAGE = 8;
let currentPage = 1;
let filtered = [...PATIENTS];

function statusBadge(s) {
  if (s === 'active')   return `<span class="status-badge s-active"><div class="sd"></div>Ativo</span>`;
  if (s === 'inactive') return `<span class="status-badge s-inactive"><div class="sd"></div>Inativo</span>`;
  return `<span class="status-badge s-pending"><div class="sd"></div>Pendente</span>`;
}

function renderTable() {
  const start = (currentPage - 1) * PER_PAGE;
  const page  = filtered.slice(start, start + PER_PAGE);
  document.getElementById('patientTableBody').innerHTML = page.map(p => `
    <tr class="patient-row">
      <td>
        <div class="td-user">
          <div class="td-avatar" style="background:${p.color}">${p.initials}</div>
          <div>
            <div class="td-name">${p.name}</div>
            <div class="td-sub">ID: ${p.id}</div>
          </div>
        </div>
      </td>
      <td><span class="level-badge">Nível ${p.level}</span></td>
      <td>${statusBadge(p.status)}</td>
      <td>${p.sessions}</td>
      <td>${p.lastSession}</td>
      <td>${p.avgScore}%</td>
      <td>
        <div class="actions">
          <a class="action-btn" href="patient-details.php?id=${p.dbId}" title="Ver detalhes">
            <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </a>
          
        </div>
      </td>
    </tr>
  `).join('');

  const total = filtered.length;
  const end   = Math.min(start + PER_PAGE, total);
  document.getElementById('pageInfo').textContent    = `Mostrando ${Math.min(start + 1, total)}–${end} de ${total}`;
  document.getElementById('searchCount').textContent = `${total} pacientes`;
  document.getElementById('pageNum').textContent     = currentPage;
  bindCursor();
}

function filterPatients() {
  const q  = document.getElementById('searchInput').value.toLowerCase();
  const lv = document.getElementById('filterLevel').value;
  const st = document.getElementById('filterStatus').value;
  filtered = PATIENTS.filter(p => {
    const mQ  = !q  || p.name.toLowerCase().includes(q) || p.id.toLowerCase().includes(q);
    const mLv = !lv || p.level === +lv;
    const mSt = !st || p.status === st;
    return mQ && mLv && mSt;
  });
  currentPage = 1;
  renderTable();
}

function changePage(dir) {
  const max = Math.ceil(filtered.length / PER_PAGE);
  currentPage = Math.max(1, Math.min(max, currentPage + dir));
  renderTable();
}

function showToast(msg) {
  const t = document.getElementById('toast');
  document.getElementById('toastMsg').textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3000);
}

renderTable();
</script>
</body>
</html>
