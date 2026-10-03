/* ═══════════════════════════════════════════════
   Painel do Paciente
═══════════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', () => {
  // Apanha os dados injetados pelo PHP
  const config = window.PATIENT_CONFIG || {};
  const EVALUATIONS = config.evaluations || [];
  const RECENT_GAMES = config.recentGames || [];
  const PATIENT_DATA = config.patientData || {};
  const NEW_EVAL_COUNT = config.newEvalCount || 0;

  // Data na topbar
  const topbarDate = document.getElementById('topbarDate');
  if (topbarDate) {
    topbarDate.textContent = new Date().toLocaleDateString('pt-PT', {
      weekday: 'short', day: 'numeric', month: 'short', year: 'numeric'
    });
  }

  // Cursor personalizado (se existirem os elementos)
  const dot = document.getElementById('cursorDot');
  const cross = document.getElementById('cursorCross');
  if (dot && cross) {
    document.addEventListener('mousemove', e => {
      dot.style.left = e.clientX + 'px';
      dot.style.top = e.clientY + 'px';
      cross.style.left = e.clientX + 'px';
      cross.style.top = e.clientY + 'px';
    });
    document.querySelectorAll('a, button, .nav-item').forEach(el => {
      el.addEventListener('mouseenter', () => {
        dot.style.width = '12px';
        dot.style.height = '12px';
        dot.style.background = 'var(--teal-d)';
      });
      el.addEventListener('mouseleave', () => {
        dot.style.width = '6px';
        dot.style.height = '6px';
        dot.style.background = 'var(--ink)';
      });
    });
  }

  // Última avaliação no painel
  if (EVALUATIONS.length > 0) {
    const latestDate = document.getElementById('latestEvalDate');
    const latestText = document.getElementById('latestEvalText');
    if (latestDate) latestDate.textContent = EVALUATIONS[0].date;
    if (latestText) latestText.textContent = EVALUATIONS[0].text;
  }

  // Modal de avaliações
  const modalEval = document.getElementById('modalAvaliacoes');
  const modalEvalDate = document.getElementById('modalEvalDate');
  const modalEvalText = document.getElementById('modalEvalText');
  const modalEvalCount = document.getElementById('modalEvalCounter');
  let currentEvalIndex = 0;

  function renderEval(index) {
    const ev = EVALUATIONS[index];
    if (modalEvalDate) modalEvalDate.textContent = 'Consulta de ' + ev.date;
    if (modalEvalText) modalEvalText.textContent = ev.text;
    if (modalEvalCount) modalEvalCount.textContent = (index+1) + ' de ' + EVALUATIONS.length;
    const prevBtn = document.getElementById('prevEvalBtn');
    const nextBtn = document.getElementById('nextEvalBtn');
    if (prevBtn) prevBtn.disabled = (index === 0);
    if (nextBtn) nextBtn.disabled = (index === EVALUATIONS.length - 1);
    if (modalEvalText) modalEvalText.scrollTop = 0;
  }

  document.getElementById('openEvaluationsBtn')?.addEventListener('click', () => {
    if (EVALUATIONS.length === 0) return;
    currentEvalIndex = 0;
    renderEval(currentEvalIndex);
    modalEval?.classList.add('open');
  });
  document.getElementById('closeModalAvaliacoes')?.addEventListener('click', () => {
    modalEval?.classList.remove('open');
  });
  document.getElementById('prevEvalBtn')?.addEventListener('click', () => {
    if (currentEvalIndex > 0) {
      currentEvalIndex--;
      renderEval(currentEvalIndex);
    }
  });
  document.getElementById('nextEvalBtn')?.addEventListener('click', () => {
    if (currentEvalIndex < EVALUATIONS.length - 1) {
      currentEvalIndex++;
      renderEval(currentEvalIndex);
    }
  });


function exportEvaluation(date, text) {
  const p = PATIENT_DATA;
  const html = `
    <!DOCTYPE html>
    <html lang="pt">
    <head>
      <meta charset="UTF-8">
      <title>Relatório de Avaliação - ${date}</title>
      <style>
        @import url('https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,300;12..96,400;12..96,500;12..96,600;12..96,700&family=Instrument+Serif:ital@0;1&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
          font-family: 'Bricolage Grotesque', sans-serif;
          color: #123746;
          background: #F4E9CD;
          padding: 2.5rem;
          max-width: 800px;
          margin: 0 auto;
          line-height: 1.7;
        }
        .report-header {
          display: flex;
          align-items: center;
          gap: 1rem;
          margin-bottom: 2.5rem;
          border-bottom: 1px solid rgba(18,55,70,0.12);
          padding-bottom: 1.5rem;
        }
        .report-logo {
          width: 48px;
          height: 48px;
          background: linear-gradient(135deg, #468189, #77ACA2);
          border-radius: 12px;
          display: flex;
          align-items: center;
          justify-content: center;
          color: white;
          font-weight: 700;
          font-size: 1.4rem;
        }
        .report-title h1 {
          font-family: 'Instrument Serif', serif;
          font-size: 2rem;
          font-weight: 400;
          line-height: 1.1;
        }
        .report-title span {
          font-size: 0.85rem;
          color: #468189;
          letter-spacing: 0.06em;
          font-weight: 600;
          text-transform: uppercase;
        }
        .info-table {
          width: 100%;
          border-collapse: collapse;
          margin-bottom: 2rem;
          background: rgba(255,255,255,0.7);
          border: 1px solid rgba(18,55,70,0.12);
          border-radius: 12px;
          overflow: hidden;
        }
        .info-table td {
          padding: 0.8rem 1.2rem;
          border-bottom: 1px solid rgba(18,55,70,0.06);
          font-size: 0.95rem;
        }
        .info-table td:first-child {
          font-weight: 600;
          color: #123746;
          width: 160px;
          background: rgba(18,55,70,0.03);
        }
        .evaluation-text {
          background: white;
          border: 1px solid rgba(18,55,70,0.15);
          border-radius: 12px;
          padding: 1.8rem;
          font-size: 1rem;
          white-space: pre-wrap;
          line-height: 1.8;
          color: #123746;
        }
        .footer-note {
          margin-top: 2.5rem;
          font-size: 0.8rem;
          color: #9DBEBB;
          text-align: center;
          border-top: 1px solid rgba(18,55,70,0.08);
          padding-top: 1.5rem;
        }
        @media print {
          body { background: white; padding: 1rem; }
          .info-table, .evaluation-text { border-color: #ccc; }
        }
      </style>
    </head>
    <body>
      <div class="report-header">
        <div class="report-logo">RB</div>
        <div class="report-title">
          <span>RehabBox</span>
          <h1>Relatório de Avaliação Clínica</h1>
        </div>
      </div>

      <table class="info-table">
        <tr><td>Paciente</td><td>${escHtml(p.name)}</td></tr>
        <tr><td>Email</td><td>${escHtml(p.email)}</td></tr>
        <tr><td>Condição</td><td>${escHtml(p.condition)}</td></tr>
        <tr><td>Dificuldade</td><td>Nível ${p.difficulty}</td></tr>
        <tr><td>Data da avaliação</td><td>${date}</td></tr>
      </table>

      <div class="evaluation-text">${escHtml(text)}</div>

      <div class="footer-note">
        Documento gerado automaticamente pela plataforma RehabBox. Todos os direitos reservados.
      </div>
    </body>
    </html>
  `;

  const printWindow = window.open('', '_blank', 'width=900,height=700');
  printWindow.document.write(html);
  printWindow.document.close();
  printWindow.focus();
  setTimeout(() => printWindow.print(), 300);
}

  document.getElementById('exportLatestBtn')?.addEventListener('click', () => {
    if (EVALUATIONS.length === 0) return;
    exportEvaluation(EVALUATIONS[0].date, EVALUATIONS[0].text);
  });
  document.getElementById('exportModalBtn')?.addEventListener('click', () => {
    const ev = EVALUATIONS[currentEvalIndex];
    exportEvaluation(ev.date, ev.text);
  });

  // Modal de eliminação
  const modalApagar = document.getElementById('modalApagar');
  const confirmInput = document.getElementById('confirmDeleteInput');

  document.getElementById('navApagar')?.addEventListener('click', (e) => {
    e.preventDefault();
    modalApagar?.classList.add('open');
    if (confirmInput) confirmInput.value = '';
  });
  document.getElementById('closeModalApagar')?.addEventListener('click', () => {
    modalApagar?.classList.remove('open');
  });
  document.getElementById('cancelDeleteBtn')?.addEventListener('click', () => {
    modalApagar?.classList.remove('open');
  });
  document.getElementById('confirmDeleteBtn')?.addEventListener('click', async () => {
    if (confirmInput.value.trim() !== 'ELIMINAR') {
      showToast('Escreva ELIMINAR para confirmar.', 'warn');
      return;
    }
    const btn = document.getElementById('confirmDeleteBtn');
    btn.disabled = true;
    try {
      const response = await fetch('create_deletion_request.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: 'account' })
      });
      if (!response.ok) throw new Error('Erro HTTP: ' + response.status);
      const result = await response.json();
      if (result.success) {
        showToast('Pedido de eliminação enviado com sucesso.', 'ok');
        modalApagar?.classList.remove('open');
      } else {
        showToast(result.message || 'Erro ao enviar pedido.', 'warn');
      }
    } catch (error) {
      console.error('Erro no pedido de eliminação:', error);
      showToast('Erro de rede. Tente novamente.', 'warn');
    } finally {
      btn.disabled = false;
    }
  });

  // Toast
  function showToast(msg, type = 'ok') {
    const toast = document.getElementById('toast');
    if (!toast) return;
    document.getElementById('toastMsg').textContent = msg;
    toast.querySelector('svg').style.stroke = type === 'ok' ? 'var(--teal-m)' : 'var(--amber)';
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3200);
  }

  // Fechar modais ao clicar fora
  window.addEventListener('click', e => {
    if (e.target === modalEval) modalEval?.classList.remove('open');
    if (e.target === modalApagar) modalApagar?.classList.remove('open');
  });

  // Scroll suave para âncoras na sidebar
  document.querySelectorAll('.sidebar .nav-item[href^="#"]').forEach(link => {
    link.addEventListener('click', function(e) {
      e.preventDefault();
      const target = document.getElementById(this.getAttribute('href').substring(1));
      if (target) target.scrollIntoView({ behavior: 'smooth' });
    });
  });

  // Tabela de jogos recentes
  const tbody = document.getElementById('recentGamesBody');
  if (tbody) {
    if (RECENT_GAMES.length === 0) {
      tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--ash);">Nenhuma sessão nos últimos 7 dias.</td></tr>';
    } else {
      tbody.innerHTML = RECENT_GAMES.map(g => `
        <tr>
          <td>${escHtml(g.name)}</td>
          <td>${escHtml(g.date)}</td>
          <td>${g.difficulty}</td>
          <td>${g.final_score}</td>
          <td>${g.duration_minutes} min</td>
        </tr>
      `).join('');
    }
  }

  function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

// Sino de notificações: marcar como lido sem recarregar
const notifBell = document.querySelector('.topbar-notif.has-notifications');
if (notifBell) {
  notifBell.addEventListener('click', async function(e) {
    e.preventDefault();
    try {
     await fetch('mark_eval_read.php');
    } catch (err) {
      console.error(err);
    }
    // Remove animação e indicadores visuais
    this.classList.remove('has-notifications');
    const dot = this.querySelector('.notif-dot');
    if (dot) dot.remove();
    // Remove o número (span a seguir ao sino)
    const countSpan = this.nextElementSibling;
    if (countSpan && countSpan.tagName === 'SPAN') countSpan.remove();
    // Volta ao aspeto "sem notificações"
    this.style.opacity = '0.5';
    this.removeAttribute('href');
    // Scroll suave para a secção de avaliações
    const target = document.getElementById('avaliacoes');
    if (target) target.scrollIntoView({ behavior: 'smooth' });
  });
}


});