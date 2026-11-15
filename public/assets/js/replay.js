/**
 * TerraWatch-1 — simulation replay (module M10): month playback, running charts, an
 * illustrative orbit and ground-contact view, and the mission-control log.
 * It only replays the stored run; it cannot change any result.
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 */
(function () {
  'use strict';
  const el = document.getElementById('replay-data');
  if (!el) return;
  const R = JSON.parse(el.textContent);
  const $ = id => document.getElementById(id);
  const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const NS = 'http://www.w3.org/2000/svg';
  const months = R.months;                 // operational months 1..L only
  const endMonth = R.endMonth;             // L, or L+1 for a power failure
  let month = 0, playing = false, speed = 1, timer = null;
  const pausedOnce = new Set();

  document.getElementById('replay').hidden = false;
  document.querySelectorAll('[data-replay-fallback]').forEach(n => { n.open = false; });

  // ---------- charts (SVG polylines drawn up to the current month)
  function chart(svgId, series, maxVal, minVal) {
    minVal = minVal || 0;
    const svg = $(svgId), W = 600, H = 180, pad = 40;
    const n = Math.max(1, endMonth);
    const x = m => pad + (W - pad - 8) * (m / n);
    const y = v => H - 20 - (H - 34) * (maxVal > minVal ? (v - minVal) / (maxVal - minVal) : 0);
    svg.innerHTML = '';
    const axis = document.createElementNS(NS, 'path');
    axis.setAttribute('d', `M${pad} 10 V${H - 20} H${W - 8}`);
    axis.setAttribute('class', 'stroke-slate-500'); axis.setAttribute('fill', 'none');
    svg.appendChild(axis);
    const lab = (txt, xx, yy, anchor) => { const t = document.createElementNS(NS, 'text'); t.textContent = txt; t.setAttribute('x', xx); t.setAttribute('y', yy); t.setAttribute('text-anchor', anchor || 'start'); t.setAttribute('class', 'fill-slate-400 text-[10px]'); svg.appendChild(t); };
    lab(Math.round(minVal).toLocaleString('en-US'), pad - 4, H - 18, 'end'); lab(Math.round(maxVal).toLocaleString('en-US'), pad - 4, 18, 'end');
    lab('month ' + n, W - 8, H - 4, 'end'); lab('month 0', pad, H - 4);
    const lines = series.map(s => {
      const p = document.createElementNS(NS, 'polyline');
      p.setAttribute('fill', 'none'); p.setAttribute('stroke-width', '2.5');
      p.setAttribute('class', s.cls); if (s.dash) p.setAttribute('stroke-dasharray', '6 4');
      svg.appendChild(p);
      return { p, s };
    });
    return function draw(upto) {
      for (const { p, s } of lines) {
        const pts = months.filter(r => r.m <= upto).map(r => `${x(r.m).toFixed(1)},${y(s.get(r)).toFixed(1)}`);
        p.setAttribute('points', pts.join(' '));
      }
    };
  }
  const maxData = Math.max(1, ...months.map(r => Math.max(r.made, r.sent, r.backlog)));
  const maxEnergy = Math.max(1, ...months.map(r => Math.max(r.gen, r.dem))) * 1.03;
  // Zoom the energy axis so the slow solar-array ageing is visible (axis labels show the range).
  const minEnergy = months.length ? Math.max(0, Math.min(...months.map(r => Math.min(r.gen, r.dem))) * 0.95) : 0;
  const drawData = chart('chart-data', [
    { get: r => r.made, cls: 'stroke-sky-400' },
    { get: r => r.sent, cls: 'stroke-emerald-400' },
    { get: r => r.backlog, cls: 'stroke-amber-400', dash: true },
  ], maxData);
  const drawEnergy = chart('chart-energy', [
    { get: r => r.gen, cls: 'stroke-emerald-400' },
    { get: r => r.dem, cls: 'stroke-rose-400', dash: true },
  ], maxEnergy, minEnergy);

  // ---------- orbit view (illustrative: not to scale, not a real ground track)
  const sat = $('orbit-sat'), flash = $('orbit-flash');
  const stationEls = [...document.querySelectorAll('[data-station-angle]')];
  let angle = -90, raf = null, last = 0;
  function orbitFrame(t) {
    if (!last) last = t;
    const dt = Math.min(64, t - last); last = t;
    if (playing && !reduce) angle = (angle + dt * 0.12 * speed) % 360;
    const rad = angle * Math.PI / 180, cx = 100 + 72 * Math.cos(rad), cy = 100 + 72 * Math.sin(rad);
    sat.setAttribute('cx', cx.toFixed(1)); sat.setAttribute('cy', cy.toFixed(1));
    let near = false;
    for (const s of stationEls) {
      if (s.dataset.contact !== '1') continue;
      const d = Math.abs(((angle - Number(s.dataset.stationAngle)) % 360 + 540) % 360 - 180);
      if (d < 14) near = true;
    }
    const operating = month >= 1 && month <= R.L;
    flash.setAttribute('opacity', near && operating && playing ? '0.9' : '0');
    raf = requestAnimationFrame(orbitFrame);
  }

  // ---------- log and status
  const logItems = [...document.querySelectorAll('#event-log [data-month]')];
  function render() {
    drawData(month); drawEnergy(month);
    logItems.forEach(li => { li.hidden = Number(li.dataset.month) > month; });
    const row = months.find(r => r.m === month);
    let phase = month === 0 ? 'Launch' : (month > R.L ? 'Mission ended' : (month === 1 ? 'Commissioning' : 'Science operations'));
    $('replay-month').textContent = 'Month ' + month + ' of ' + endMonth;
    $('replay-phase').textContent = phase;
    $('replay-row').textContent = row
      ? `Generation ${row.gen.toLocaleString('en-US')} Wh/day against demand ${row.dem.toLocaleString('en-US')} Wh/day · made ${Math.round(row.made).toLocaleString('en-US')} MB · sent ${Math.round(row.sent).toLocaleString('en-US')} MB · waiting ${Math.round(row.backlog).toLocaleString('en-US')} MB`
        + (row.lost > 0 ? ` · lost ${Math.round(row.lost).toLocaleString('en-US')} MB` : '')
      : (month === 0 ? 'Launch day.' : R.lifetimeText);
    $('replay-progress').value = month;
    const latest = logItems.filter(li => Number(li.dataset.month) <= month).pop();
    if (latest) latest.scrollIntoView({ block: 'nearest' });
    $('replay-end').hidden = month < endMonth;
  }

  function step() {
    if (month >= endMonth) { stop(); return; }
    month++;
    render();
    const critical = R.events.filter(e => e.month === month && e.severity === 'CRITICAL' && !pausedOnce.has(e.seq));
    if (critical.length) {
      critical.forEach(e => pausedOnce.add(e.seq));
      stop();
      $('replay-alert').textContent = 'Paused at a critical event: ' + critical.map(e => e.message).join(' ') + ' Press Play to continue.';
      $('replay-alert').hidden = false;
      return;
    }
    if (month >= endMonth) stop();
  }
  function play() {
    if (month >= endMonth) { month = 0; pausedOnce.clear(); }
    $('replay-alert').hidden = true;
    playing = true; $('btn-play').textContent = 'Pause'; $('btn-play').setAttribute('aria-pressed', 'true');
    clearInterval(timer); timer = setInterval(step, 700 / speed);
  }
  function stop() {
    playing = false; clearInterval(timer);
    $('btn-play').textContent = month >= endMonth ? 'Replay' : 'Play'; $('btn-play').setAttribute('aria-pressed', 'false');
  }

  $('btn-play').addEventListener('click', () => (playing ? stop() : play()));
  $('btn-speed').addEventListener('click', () => {
    speed = speed === 1 ? 4 : 1;
    $('btn-speed').textContent = 'Speed: ' + speed + '×';
    if (playing) { clearInterval(timer); timer = setInterval(step, 700 / speed); }
  });
  $('btn-skip').addEventListener('click', () => { stop(); month = endMonth; $('replay-alert').hidden = true; render(); $('btn-play').textContent = 'Replay'; });
  $('replay-progress').max = endMonth;
  $('replay-progress').addEventListener('input', e => { stop(); month = Number(e.target.value); render(); });

  if (reduce) { $('orbit-note').textContent = 'Reduced motion is on: the orbit view is shown as a still diagram. Charts still update.'; }
  render();
  raf = requestAnimationFrame(orbitFrame);
  if (!reduce) play();
})();
