/* ═══════════════════════════════════════════════════════════════════
   session-charts.js  —  RehabBox · Avaliação de Sessão
   Chart.js 4.x deve estar carregado antes deste ficheiro.
═══════════════════════════════════════════════════════════════════ */
(function () {

  /* ── tema ── */
  const isDark = matchMedia('(prefers-color-scheme: dark)').matches;
  const textCol = isDark ? 'rgba(200,200,190,.6)' : 'rgba(18,55,70,.45)';
  const gridCol = isDark ? 'rgba(255,255,255,.05)' : 'rgba(18,55,70,.05)';
  const WHITE = isDark ? '#1c1c1a' : '#ffffff';

  /* ── regressão linear: devolve y previsto para cada x ── */
  function linReg(xs, ys) {
    const n = xs.length; if (!n) return [];
    const mx = xs.reduce((a, b) => a + b, 0) / n, my = ys.reduce((a, b) => a + b, 0) / n;
    let num = 0, den = 0;
    xs.forEach((x, i) => { num += (x - mx) * (ys[i] - my); den += (x - mx) ** 2; });
    const m = den ? num / den : 0, b = my - m * mx;
    return xs.map(x => Math.round(m * x + b));
  }

  /* ── opções base ── */
  const BASE_SCALES = {
    x: { grid: { color: gridCol }, ticks: { color: textCol, font: { size: 11 } } },
    y: { grid: { color: gridCol }, ticks: { color: textCol, font: { size: 11 } } }
  };
  const BASE_PLUGINS = {
    legend: { display: false },
    tooltip: { boxPadding: 4, titleFont: { size: 12 }, bodyFont: { size: 12 } }
  };

  /* ── registo de instâncias para destroy no updateCharts ── */
  const _charts = {};

  function draw(id, cfg) {

    const canvas = document.getElementById(id);
    if (!canvas) return;

    const ctx = canvas.getContext('2d');

    if (_charts[id]) {
      _charts[id].destroy();
      delete _charts[id];
    }

    _charts[id] = new Chart(ctx, cfg);
  }

  /* ════════════════════════════════════════════════════════
     JOGO 1 — OBSTÁCULOS
     J1_DATA: [{ id, final_score, duration_minutes }, …]
  ════════════════════════════════════════════════════════ */

  /* Gráfico 1 — Regressão linear de erros por tentativa */
  function buildJ1Regression() {
    const raw = window.J1_DATA || [];
    const mistakes = window.J1_MISTAKES || {};
    if (!raw.length) return;

    const xs = raw.map((_, i) => i + 1);
    const scores = raw.map(d => Math.round(+d.final_score || 0));
    const errors = raw.map(d => parseInt(mistakes[d.id] || 0));
    const trendScores = linReg(xs, scores);
    const trendErrors = linReg(xs, errors);

    draw('j1Regression', {
      type: 'line',
      data: {
        labels: raw.map(d => 'J' + d.id),
        datasets: [
          {
            label: 'Score (%)',
            data: scores,
            borderColor: '#1D9E75',
            backgroundColor: 'rgba(29,158,117,.08)',
            borderWidth: 2, pointRadius: 4,
            pointBackgroundColor: '#1D9E75',
            tension: .3, fill: true, order: 1,
            yAxisID: 'yScore'
          },
          {
            label: 'Erros',
            data: errors,
            borderColor: '#E24B4A',
            backgroundColor: 'transparent',
            borderWidth: 2, borderDash: [5, 4],
            pointRadius: 4,
            pointBackgroundColor: '#E24B4A',
            tension: .3, fill: false, order: 2,
            yAxisID: 'yErrors'
          },
          {
            label: 'Tend. score',
            data: trendScores,
            borderColor: 'rgba(29,158,117,.3)',
            borderWidth: 1.5, borderDash: [3, 3],
            pointRadius: 0, fill: false,
            tension: .4, order: 3,
            yAxisID: 'yScore'
          },
          {
            label: 'Tend. erros',
            data: trendErrors,
            borderColor: 'rgba(226,75,74,.25)',
            borderWidth: 1.5, borderDash: [3, 3],
            pointRadius: 0, fill: false,
            tension: .4, order: 4,
            yAxisID: 'yErrors'
          }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: BASE_PLUGINS,
        scales: {
          x: BASE_SCALES.x,
          yScore: {
            ...BASE_SCALES.y,
            position: 'left',
            min: 0, max: 100,
            ticks: { ...BASE_SCALES.y.ticks, callback: v => v + '%' }
          },
          yErrors: {
            ...BASE_SCALES.y,
            position: 'right',
            min: 0,
            grid: { drawOnChartArea: false },
            ticks: {
              ...BASE_SCALES.y.ticks,
              stepSize: 1,
              callback: v => Number.isInteger(v) ? v : null
            }
          }
        }
      }
    });
  }

  /* Gráfico 2 — Barras comparativas de acertos entre sessões */
  function buildJ1Bars(nSessions = 5, sessionParam = null) {
    let raw = window.J1_HISTORY || [];

    if (nSessions === 1 && sessionParam) {
      raw = raw.filter(d => String(d.id) === String(sessionParam));
    } else {
      raw = raw.slice(-nSessions);
    }

    if (!raw.length) return;

    const labels = raw.map(d => 'S' + d.session_order);
    const scores = raw.map(d => Math.round(+d.final_score || 0));
    const avg = Math.round(scores.reduce((a, b) => a + b, 0) / scores.length);
    const bgCol = raw.map(d =>
      +d.id === +window.SESSION_ID ? '#1D9E75' : 'rgba(29,158,117,.4)'
    );

    draw('j1Bars', {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Acertos (%)',
            data: scores,
            backgroundColor: bgCol,
            borderRadius: 4,
            borderSkipped: false
          },
          {
            label: 'Média',
            data: Array(scores.length).fill(avg),
            type: 'line',
            borderColor: 'rgba(29,158,117,.5)',
            borderDash: [5, 4],
            borderWidth: 1.5,
            pointRadius: 0,
            fill: false
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: BASE_PLUGINS,
        scales: {
          x: BASE_SCALES.x,
          y: {
            ...BASE_SCALES.y,
            min: 0,
            max: 100,
            ticks: { ...BASE_SCALES.y.ticks, callback: v => v + '%' }
          }
        }
      }
    });
  }

  /* ════════════════════════════════════════════════════════
     JOGO 2 — LABIRINTO DE CORES
     COLOR_DATA: [{ ronda, cor_acendeu, cor_clicada, acertou, game_id }]
     J2_ERROR_BY_COLOR: [{ session, colors:[{color,errors,total}] }]
  ════════════════════════════════════════════════════════ */

  /* Gráfico 1 — Heatmap (mantido funcional, melhorado) */
  function buildJ2Heatmap() {
    const data = window.COLOR_DATA || [];
    const colors = ['azul', 'verde', 'vermelho'];
    const MAX = 8;

    /* matriz cor × ronda */
    const matrix = {};
    colors.forEach(c => { matrix[c] = {}; for (let r = 1; r <= MAX; r++) matrix[c][r] = []; });
    data.forEach(row => {
      const c = (row.cor_acendeu || '').toLowerCase();
      const r = +row.ronda;
      if (!colors.includes(c) || r < 1 || r > MAX) return;
      matrix[c][r].push(+row.acertou);
    });

    function cell(vals) {
      if (!vals.length) return `<div class="heatmap-cell"></div>`;
      const hits = vals.filter(v => v === 1).length;
      const total = vals.length;
      if (hits === total) return `<div class="heatmap-cell hc-hit">✓</div>`;
      if (hits === 0) return `<div class="heatmap-cell hc-miss">✗</div>`;
      return `<div class="heatmap-cell hc-partial">${hits}/${total}</div>`;
    }

    const colLabels = Array.from({ length: MAX }, (_, i) =>
      `<div class="heatmap-col-label">${i + 1}</div>`).join('');

    let html = `<div class="heatmap-header"><div></div>${colLabels}</div><div class="heatmap-body">`;
    colors.forEach(c => {
      const label = c.charAt(0).toUpperCase() + c.slice(1);
      html += `<div class="heatmap-row">
        <div class="heatmap-row-label">${label}</div>
        ${Array.from({ length: MAX }, (_, i) => cell(matrix[c][i + 1])).join('')}
      </div>`;
    });
    html += `</div>
    <div class="heatmap-legend">
      <div class="hm-leg-item"><div class="hm-leg-dot" style="background:#22c55e55"></div>Acerto</div>
      <div class="hm-leg-item"><div class="hm-leg-dot" style="background:#ef444455"></div>Erro</div>
    </div>`;

    const el = document.getElementById('j2Heatmap');
    if (el) el.innerHTML = html;
  }

  /* Gráfico 2 — Barras de % de erros por cor para várias sessões */
  function buildJ2ErrorBars(nSessions = 5, sessionParam = null) {
    const rawAll = window.J2_PROGRESS || [];
    if (!rawAll.length) return;

    let filtered;

    if (nSessions === 1 && sessionParam) {
      const gameIds = (window.CURRENT_GAME_IDS || []).map(String);
      filtered = rawAll.filter(s => gameIds.includes(String(s.session)));
    } else {
      filtered = rawAll.sort((a, b) => a.session - b.session).slice(-nSessions);
    }

    if (!filtered.length) return;

    const labels = filtered.map((_, i) => 'S' + (i + 1));

    const colorDefs = [
      { key: 'azul', label: 'Azul', bg: 'rgba(37,99,235,.75)' },
      { key: 'verde', label: 'Verde', bg: 'rgba(22,163,74,.75)' },
      { key: 'vermelho', label: 'Vermelho', bg: 'rgba(220,38,38,.75)' }
    ];

    const datasets = colorDefs.map(cd => ({
      label: cd.label,
      data: filtered.map(sess => {
        const found = (sess.colors || []).find(x => x.color === cd.key);
        return found ? (found.errors || 0) : 0;
      }),
      backgroundColor: cd.bg,
      borderRadius: 3,
      borderSkipped: false
    }));

    draw('j2ErrorBars', {
      type: 'bar',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          ...BASE_PLUGINS,
          tooltip: { ...BASE_PLUGINS.tooltip, mode: 'index', intersect: false }
        },
        scales: {
          x: BASE_SCALES.x,
          y: {
            ...BASE_SCALES.y,
            min: 0,
            ticks: {
              ...BASE_SCALES.y.ticks,
              stepSize: 1,
              callback: v => Number.isInteger(v) ? v : null
            }
          }
        }
      }
    });
  }
  /* ════════════════════════════════════════════════════════
     JOGO 3 — RITMO E PRECISÃO
  ════════════════════════════════════════════════════════ */

  /* Gráfico 1 — Regressão do tempo de reação por tentativa
     Usa J1_DATA (duration_minutes por jogo) como proxy de velocidade.
     Quando houver tabela própria de timing, subsitui aqui. */
  function buildJ3Reaction() {
    const raw = (window.J1_DATA || []).length >= 2
      ? window.J1_DATA
      : window.J3_DATA || [];

    if (!raw.length) return;

    const xs = raw.map((_, i) => i + 1);
    /* converte minutos → segundos como proxy de duração/tempo */
    const ys = raw.map(d => Math.round((+d.duration_minutes || 1) * 60));
    const trend = linReg(xs, ys);

    draw('j3Reaction', {
      type: 'scatter',
      data: {
        datasets: [
          {
            label: 'Tempo (s)', data: xs.map((x, i) => ({ x, y: ys[i] })),
            backgroundColor: 'rgba(124,58,237,.7)',
            pointRadius: 5, pointHoverRadius: 7
          },
          {
            label: 'Tendência', data: xs.map((x, i) => ({ x, y: trend[i] })),
            type: 'line', borderColor: 'rgba(124,58,237,.35)',
            borderWidth: 2, borderDash: [4, 4], pointRadius: 0,
            fill: false, tension: .4
          }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: BASE_PLUGINS,
        scales: {
          x: {
            ...BASE_SCALES.x,
            title: { display: true, text: 'Tentativa', color: textCol, font: { size: 11 } }
          },
          y: {
            ...BASE_SCALES.y,
            title: { display: true, text: 's', color: textCol, font: { size: 11 } },
            ticks: { ...BASE_SCALES.y.ticks, callback: v => v + 's' }
          }
        }
      }
    });
  }

  /* Gráfico 2 — Donut: acertos, cedo, tarde
     Classifica COLOR_DATA por ronda (≤3 = cedo) e acertou */
  function buildJ3Donut(nSessions = 5, sessionParam = null) {
    const all = window.J3_DONUT_DATA || [];
    let raw;

    if (nSessions === 1 && sessionParam) {
      // J3_DONUT_DATA tem session = session_id (GameSession.id)
      raw = all.filter(s => String(s.session) === String(sessionParam));
    } else {
      raw = all.slice(-nSessions);
    }

    if (!raw.length) return;

    let hits = 0;
    let early = 0;
    let timeout = 0;

    raw.forEach(s => {
      hits += Number(s.hits || 0);
      early += Number(s.early || 0);
      timeout += Number(s.timeout || 0);
    });

    const total = hits + early + timeout || 1;

    const vals = [
      Math.round(hits / total * 100),
      Math.round(early / total * 100),
      Math.round(timeout / total * 100)
    ];

    const labels = ['Acertos', 'Muito Cedo', 'Timeout'];

    const colors = ['#1D9E75', '#F59E0B', '#7C3AED'];

    const absolute = [hits, early, timeout];

    draw('j3Donut', {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{
          data: absolute,
          backgroundColor: colors,
          borderWidth: 2,
          borderColor: WHITE,
          hoverOffset: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '62%',
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: ctx => `${ctx.label}: ${ctx.parsed}`
            }
          }
        }
      }
    });

    // ✔ LEGEND A (mantida exatamente como tinhas)
    const lg = document.getElementById('j3DonutLegend');

    if (lg) {
      lg.innerHTML = labels.map((l, i) => `
      <div class="dl-item">
        <div class="dl-dot" style="background:${colors[i]}"></div>
        <span class="dl-label">${l}</span>
        <span class="dl-val">${vals[i]}%</span>
      </div>
    `).join('');
    }
  }

  /* ════════════════════════════════════════════════════════
     API PÚBLICA
  ════════════════════════════════════════════════════════ */
  window.updateCharts = function () {

    const mode = parseInt(
      document.getElementById('sessionFilter')?.value || 5
    );

    const selectedSession = window.CURRENT_SESSION_ID;

    const isSingleSession = mode === 1;

    const sessionParam = isSingleSession ? selectedSession : null;

    console.log("===== UPDATE CHARTS =====");
    console.log("mode (nSessions):", mode);
    console.log("CURRENT_SESSION_ID:", selectedSession);
    console.log("isSingleSession:", isSingleSession);
    console.log("sessionParam:", sessionParam);

    buildJ1Regression(mode, sessionParam);
    buildJ1Bars(mode, sessionParam);

    buildJ2Heatmap(mode, sessionParam);
    buildJ2ErrorBars(mode, sessionParam);

    buildJ3Reaction(mode, sessionParam);
    buildJ3Donut(mode, sessionParam);
  };

  document.getElementById('sessionFilter')?.addEventListener('change', window.updateCharts);
  document.addEventListener('DOMContentLoaded', window.updateCharts);

})();
