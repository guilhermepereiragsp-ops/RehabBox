/* ═══════════════════════════════════════════════
   RehabBox — main.js
═══════════════════════════════════════════════ */
/* ═══════════════════════════════════════════════
   RehabBox — main.js  (index page only)
═══════════════════════════════════════════════ */

/* ── CURSOR ── */
const dot   = document.getElementById('cursorDot');
const cross = document.getElementById('cursorCross');

if (dot && cross) {
  document.addEventListener('mousemove', e => {
    dot.style.left   = e.clientX + 'px';
    dot.style.top    = e.clientY + 'px';
    cross.style.left = e.clientX + 'px';
    cross.style.top  = e.clientY + 'px';
  }, { passive: true });

  document.querySelectorAll(
    'a, button, .game-card, .game-card-lg, .bento-card, .team-card, .testimonial-card, .faq-item, .stat-card'
  ).forEach(el => {
    el.addEventListener('mouseenter', () => {
      dot.style.width      = '12px';
      dot.style.height     = '12px';
      dot.style.background = 'var(--teal-d)';
      cross.style.opacity  = '0.2';
    });
    el.addEventListener('mouseleave', () => {
      dot.style.width      = '6px';
      dot.style.height     = '6px';
      dot.style.background = 'var(--ink)';
      cross.style.opacity  = '1';
    });
  });
}

/* ── SCROLL REVEAL ── */
const revealObs = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (entry.isIntersecting) entry.target.classList.add('in');
  });
}, { threshold: 0.08 });

document.querySelectorAll('.reveal').forEach(el => revealObs.observe(el));

/* ── SMOOTH SCROLL ── */
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const href   = a.getAttribute('href');
    if (href === '#') return;
    const target = document.querySelector(href);
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth' });
    }
  });
});

/* ── NAV SCROLL BACKGROUND ── */
const nav = document.getElementById('mainNav');
if (nav) {
  window.addEventListener('scroll', () => {
    nav.style.background = window.scrollY > 10
      ? 'rgba(18,55,70,0.97)'
      : 'var(--ink)';
  }, { passive: true });
}

/* ── CONTROLLER BUTTON PRESS FEEL ── */
document.querySelectorAll('.ctrl-btn').forEach(btn => {
  btn.addEventListener('mousedown',  () => { btn.style.transform = 'scale(0.92)'; });
  btn.addEventListener('mouseup',    () => { btn.style.transform = 'scale(1.12)'; });
  btn.addEventListener('mouseleave', () => { btn.style.transform = ''; });
});

/* ── FAQ ACCORDION ── */
document.querySelectorAll('.faq-q').forEach(btn => {
  btn.addEventListener('click', () => {
    const isExpanded = btn.getAttribute('aria-expanded') === 'true';

    /* close all items */
    document.querySelectorAll('.faq-q').forEach(b => {
      b.setAttribute('aria-expanded', 'false');
      const answer = b.nextElementSibling;
      if (answer) answer.classList.remove('open');
    });

    /* open the clicked one if it was closed */
    if (!isExpanded) {
      btn.setAttribute('aria-expanded', 'true');
      const answer = btn.nextElementSibling;
      if (answer) answer.classList.add('open');
    }
  });
});

const tabs = document.querySelectorAll('.auth-tab');
const forms = document.querySelectorAll('.auth-form');

tabs.forEach(tab => {
  tab.addEventListener('click', () => {
    const target = tab.dataset.authTab;

    tabs.forEach(t => t.classList.remove('active'));
    forms.forEach(f => f.classList.remove('active'));

    tab.classList.add('active');
    document.getElementById(target + 'Form').classList.add('active');
  });
});

const registerForm = document.getElementById('registerForm');

registerForm?.addEventListener('submit', e => {
  const password = document.getElementById('registerPassword').value;
  const confirm = document.getElementById('registerPasswordConfirm').value;

  if (password !== confirm) {
    e.preventDefault();
    alert('As palavras-passe não coincidem.');
  }
});

/* ═══════════════════════════════════════════════
   RehabBox — admin.js
   Painel de Administração
═══════════════════════════════════════════════ */

/* ══════════════════════════
   DATE in topbar
══════════════════════════ */
const dateEl = document.getElementById('topbarDate');
if (dateEl) {
  dateEl.textContent = new Date().toLocaleDateString('pt-PT', {
    weekday: 'short', day: 'numeric', month: 'short', year: 'numeric'
  });
}

/* ══════════════════════════
   DATA — clients
   Níveis: 1-3 | Jogos: 3 apenas
══════════════════════════ */
const CLIENTS = window.CLIENTS_FROM_DB || [];

const PER_PAGE = 8;
let currentPage    = 1;
let filtered       = [...CLIENTS];
let currentClient  = null;

/* ── helpers ── */
function withdrawalClients() {
  return CLIENTS.filter(c => c.withdrawal !== null);
}

function statusBadge(s) {
  if (s === 'active')   return `<span class="status-badge s-active"><div class="sd"></div>Ativo</span>`;
  if (s === 'inactive') return `<span class="status-badge s-inactive"><div class="sd"></div>Inativo</span>`;
  return `<span class="status-badge s-alert"><div class="sd"></div>Alerta</span>`;
}

function sparkHtml(arr) {
  const max = Math.max(...arr, 1);
  return '<div class="sparkline">' +
    arr.map(v =>
      `<div class="spark-bar" style="height:${Math.round((v / max) * 22)}px;opacity:${0.4 + 0.6 * (v / max)}"></div>`
    ).join('') +
    '</div>';
}

/* ══════════════════════════
   WITHDRAWAL ALERTS
══════════════════════════ */
function updateWithdrawalUI() {
  const pending = withdrawalClients();
  const count   = pending.length;

  // nav badge
  const navBadge = document.getElementById('withdrawalNavBadge');
  if (navBadge) navBadge.textContent = count > 0 ? count : '';
  if (navBadge) navBadge.style.display = count > 0 ? 'inline-block' : 'none';

  // topbar bell dot
  const bellDot = document.getElementById('notifDot');
  if (bellDot) bellDot.style.display = count > 0 ? 'block' : 'none';

  // bell count badge
  const bellCount = document.getElementById('bellCount');
  if (bellCount) {
    bellCount.textContent = count > 0 ? count : '';
    bellCount.style.display = count > 0 ? 'inline-block' : 'none';
  }

  // banner
  const banner    = document.getElementById('withdrawalBanner');
  const bannerMsg = document.getElementById('withdrawalBannerMsg');
  if (banner && bannerMsg) {
    if (count > 0) {
      bannerMsg.innerHTML = `<strong>${count} ${count === 1 ? 'paciente solicitou' : 'pacientes solicitaram'}</strong> retirada de consentimento de dados.`;
      banner.classList.remove('hidden');
    } else {
      banner.classList.add('hidden');
    }
  }

  // withdrawal list card
  const wdList = document.getElementById('withdrawalList');
  if (wdList) {
    if (pending.length === 0) {
      wdList.innerHTML = '<div class="wd-empty">Nenhum pedido pendente.</div>';
    } else {
      wdList.innerHTML = pending.map(c => {
        const typeLabel = c.withdrawal === 'account' ? 'Eliminar conta' : 'Apagar dados';
        const typeClass = c.withdrawal === 'account' ? 'account' : 'data';
        return `
          <div class="withdrawal-item" id="wd-${c.id}">
            <div class="wd-avatar" style="background:${c.color}">${c.initials}</div>
            <div>
              <div class="wd-name">${c.name}</div>
              <div class="wd-date">${c.id} · Pedido hoje</div>
            </div>
            <span class="wd-type ${typeClass}">${typeLabel}</span>
            <button class="wd-action-btn" onclick="handleWithdrawal('${c.id}')">Tratar</button>
          </div>`;
      }).join('');
    }
  }
}

function handleWithdrawal(id) {
  const c = CLIENTS.find(x => x.id === id);
  if (!c) return;
  if (c.withdrawal === 'account') {
    openModal('delete', c);
  } else {
    openModal('data', c);
  }
}

/* ══════════════════════════
   TABLE RENDER
══════════════════════════ */
function renderTable() {
  const start = (currentPage - 1) * PER_PAGE;
  const page  = filtered.slice(start, start + PER_PAGE);
  const tbody = document.getElementById('clientTableBody');
   if (!tbody) return; //

  tbody.innerHTML = page.map(c => {
    const wdTag = c.withdrawal
      ? `<div class="td-withdrawal-tag">
           <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
           ${c.withdrawal === 'account' ? 'Quer apagar conta' : 'Quer apagar dados'}
         </div>`
      : '';

    return `
      <tr class="client-row${c.withdrawal ? ' has-withdrawal' : ''}" data-id="${c.id}">
        <td>
          <div class="td-user">
            <div class="td-avatar" style="background:${c.color}">${c.initials}</div>
            <div>
              <div class="td-name">${c.name}</div>
              <div class="td-email">${c.email}</div>
              ${wdTag}
            </div>
          </div>
        </td>
        <td>${statusBadge(c.status)}</td>
        <td><span class="level-badge">Nível ${c.level}</span></td>
        <td>${c.sessions}</td>
        <td>${c.lastSession}</td>
        <td>${c.precision}%</td>
        <td>${sparkHtml(c.spark)}</td>
        <td>
          <div class="actions">
            <button class="action-btn ab-edit" title="Editar"
              onclick="openModal('edit', ${safeJSON(c)})">
              <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="action-btn ab-data" title="Apagar Dados"
              onclick="openModal('data', ${safeJSON(c)})">
              <svg viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
            </button>
            <button class="action-btn ab-del" title="Eliminar Conta"
              onclick="openModal('delete', ${safeJSON(c)})">
              <svg viewBox="0 0 24 24"><polyline points="3,6 5,6 21,6"/><path d="M19,6l-1,14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5,6"/><path d="M10,11v6"/><path d="M14,11v6"/></svg>
            </button>
          </div>
        </td>
      </tr>`;
  }).join('');

  const total = filtered.length;
  const end   = Math.min(start + PER_PAGE, total);
  document.getElementById('pageInfo').textContent  = `Mostrando ${start + 1}–${end} de ${total}`;
  document.getElementById('searchCount').textContent = `${total} clientes`;
  document.getElementById('pageNum').textContent   = currentPage;

  // re-apply cursor to dynamically rendered rows
  if (typeof applyCursorHover === 'function') {
  applyCursorHover('.action-btn, .client-row, .wd-action-btn');
}
}

/* serialise a client object safely for inline onclick */
function safeJSON(obj) {
  return JSON.stringify(obj).replace(/'/g, '&#39;').replace(/"/g, '&quot;');
}

/* ══════════════════════════
   FILTER & PAGINATION
══════════════════════════ */
function filterClients() {
  const q  = document.getElementById('searchInput').value.toLowerCase();
  const st = document.getElementById('filterStatus').value;
  const lv = document.getElementById('filterLevel').value;

  filtered = CLIENTS.filter(c => {
    const matchQ  = !q  || c.name.toLowerCase().includes(q) || c.email.toLowerCase().includes(q) || c.id.toLowerCase().includes(q);
    const matchSt = !st || c.status === st;
    const matchLv = !lv || c.level === +lv;
    return matchQ && matchSt && matchLv;
  });

  currentPage = 1;
  renderTable();
}

function changePage(dir) {
  const maxPage = Math.ceil(filtered.length / PER_PAGE);
  currentPage   = Math.max(1, Math.min(maxPage, currentPage + dir));
  renderTable();
}

/* ══════════════════════════
   MODALS
══════════════════════════ */
function getDbId(client) {
  return client?.db_id || client?.id?.replace('RB', '').replace(/^0+/, '');
}

async function postPatientAction(data) {
  const res = await fetch('admin_patient_action.php', {
    method: 'POST',
    body: data
  });

  const json = await res.json();

  if (!json.success) {
    throw new Error(json.message || 'Erro ao executar ação.');
  }

  return json;
}

function openModal(type, client = {}) {
  currentClient = client;

  if (type === 'edit') {
    const isNew = !client.db_id;

    document.getElementById('modalEditTitle').textContent = isNew
      ? 'Novo Paciente'
      : `Editar — ${client.name}`;

    document.getElementById('modalEditSub').textContent = isNew
      ? 'Criar novo perfil de paciente'
      : `ID: ${client.id}`;

    document.getElementById('editName').value = client.name || '';
    document.getElementById('editEmail').value = client.email || '';
    document.getElementById('editStatus').value = client.status || 'active';
    document.getElementById('editLevel').value = client.level || 1;
    document.getElementById('editDoctor').value = client.doctor_id || '';
    document.getElementById('editDiseaseStatus').value =
      client.disease_status || 'Parkinson estadio inicial';

    document.getElementById('modalEdit').classList.add('open');
    return;
  }

  if (!client || !client.db_id) {
    showToast('Paciente inválido.', 'warn');
    return;
  }

  if (type === 'data') {
    document.getElementById('modalDataSub').textContent = `Paciente: ${client.name}`;
    document.getElementById('dCount1').textContent = `${client.sessions} sessões`;
    ['d1', 'd2', 'd3', 'd4'].forEach(id => document.getElementById(id).checked = false);
    document.getElementById('modalData').classList.add('open');
    return;
  }

  document.getElementById('modalDeleteSub').textContent = `Paciente: ${client.name}`;
  document.getElementById('deleteConfirm').value = '';
  document.getElementById('modalDelete').classList.add('open');
}

function closeModal(type) {
  const modal = document.getElementById('modal' + type.charAt(0).toUpperCase() + type.slice(1));

  if (modal) {
    modal.classList.remove('open');
  }
}

function validatePatientForm() {
  const name = document.getElementById('editName').value.trim();
  const email = document.getElementById('editEmail').value.trim();
  const disease = document.getElementById('editDiseaseStatus')?.value || '';

  if (name.length < 3) {
    showToast('O nome deve ter pelo menos 3 caracteres.', 'warn');
    return false;
  }

  if (!/^[A-Za-zÀ-ÿ\s'-]+$/.test(name)) {
    showToast('O nome só deve conter letras e espaços.', 'warn');
    return false;
  }

  if (email === '') {
    showToast('O email é obrigatório.', 'warn');
    return false;
  }

  if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
    showToast('Insira um email válido. Exemplo: nome@email.com', 'warn');
    return false;
  }

  if (disease.trim() === '') {
    showToast('Indique o estado da doença.', 'warn');
    return false;
  }

  return true;
}

async function saveEdit() {
  if (!validatePatientForm()) return;
  try {
    const fd = new FormData();

    const isNew = !currentClient?.db_id;

    fd.append('action', isNew ? 'create_patient' : 'update_patient');

    if (!isNew) {
      fd.append('id', getDbId(currentClient));
    }

    fd.append('name', document.getElementById('editName').value.trim());
    fd.append('email', document.getElementById('editEmail').value.trim());
    fd.append('disease_status', document.getElementById('editDiseaseStatus').value);
    fd.append('doctor_id', document.getElementById('editDoctor').value);
    fd.append('status', document.getElementById('editStatus').value);

    const result = await postPatientAction(fd);

    closeModal('edit');

    if (result.tempPassword) {
      alert(`Paciente criado.\nPassword temporária: ${result.tempPassword}\nGuarda-a agora, porque não volta a aparecer.`);
    }

    showToast(result.message, 'ok');

    setTimeout(() => window.location.reload(), 700);

  } catch (err) {
    showToast(err.message, 'warn');
  }
}

async function deleteData() {
  const any = ['d1', 'd2', 'd3', 'd4'].some(id => document.getElementById(id).checked);

  if (!any) {
    showToast('Selecione pelo menos um tipo de dados', 'warn');
    return;
  }

  try {
    const fd = new FormData();
    fd.append('action', 'delete_patient_data');
    fd.append('id', getDbId(currentClient));

    const result = await postPatientAction(fd);

    closeModal('data');
    showToast(result.message, 'ok');

    setTimeout(() => window.location.reload(), 700);

  } catch (err) {
    showToast(err.message, 'warn');
  }
}

async function confirmDelete() {
  if (document.getElementById('deleteConfirm').value !== 'DELETE') {
    showToast('Escreva DELETE para confirmar', 'warn');
    return;
  }

  try {
    const fd = new FormData();
    fd.append('action', 'delete_patient');
    fd.append('id', getDbId(currentClient));

    const result = await postPatientAction(fd);

    closeModal('delete');
    showToast(result.message, 'ok');

    setTimeout(() => window.location.reload(), 700);

  } catch (err) {
    showToast(err.message, 'warn');
  }
}

/* ══════════════════════════
   TOAST
══════════════════════════ */
function showToast(msg, type = 'ok') {
  const toast  = document.getElementById('toast');
  const icon   = toast.querySelector('svg');
  document.getElementById('toastMsg').textContent = msg;
  icon.style.stroke = type === 'ok' ? 'var(--teal-m)' : 'var(--amber)';
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 3200);
}

/* ══════════════════════════
   BAR CHART — sessions / day
══════════════════════════ */
(function buildChart() {
  const data = window.SESSIONS_LAST_7_DAYS || [0, 0, 0, 0, 0, 0, 0];
const labels = window.SESSIONS_LAST_7_LABELS || ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
  const max    = Math.max(...data);
  const wrap   = document.getElementById('barChart');
  if (!wrap) return;

  data.forEach((v, i) => {
    const w = document.createElement('div');
    w.className = 'bar-wrap';

    const b = document.createElement('div');
    b.className = 'bar';
    b.style.height = Math.round((v / max) * 80) + 'px';
    b.title = `${labels[i]}: ${v} sessões`;

    const l = document.createElement('div');
    l.className = 'bar-label';
    l.textContent = labels[i];

    w.append(b, l);
    wrap.append(w);
  });
})();



/* jogos mais usados - menu admin */
function renderMostUsedGames() {
  const wrap = document.getElementById('mostUsedGames');
  if (!wrap) return;

  const games = window.MOST_USED_GAMES || [];

  if (games.length === 0) {
    wrap.innerHTML = '<div class="wd-empty">Sem jogos registados.</div>';
    return;
  }

  wrap.innerHTML = games.map(game => `
    <div class="game-usage-item">
      <div class="game-usage-top">
        <span class="game-usage-name">🎮 ${game.name}</span>
        <span class="game-usage-pct">${game.percent}%</span>
      </div>
      <div class="game-usage-bar">
        <div class="game-usage-fill" style="width:${game.percent}%"></div>
      </div>
    </div>
  `).join('');
}

/* ══════════════════════════
   INIT
══════════════════════════ */
if (document.getElementById('clientTableBody')) renderTable();
if (document.getElementById('withdrawalList'))  updateWithdrawalUI();
if (document.getElementById('mostUsedGames'))   renderMostUsedGames();