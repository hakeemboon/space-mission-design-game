/**
 * TerraWatch-1 — live designer (module M9): updates meters, constraint list, segment rail,
 * option warnings and the user panel on every selection, and autosaves the draft.
 * Display only: the server re-validates on save, review and launch.
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 */
(function () {
  'use strict';
  const form = document.getElementById('designer');
  const dataEl = document.getElementById('tw-data');
  if (!form || !dataEl || !window.TWEngine) return;
  const D = JSON.parse(dataEl.textContent);
  const { evaluate, r2 } = window.TWEngine;
  const $ = id => document.getElementById(id);
  const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const num = (x, d = 1) => Number(x).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
  const SEG = { LAUNCH: 'launch', SPACE: 'space', GROUND: 'ground', USER: 'user', ALL: 'space' };

  function badge(kind, label) {
    const map = {
      ok: ['✓', 'bg-emerald-900/60 text-emerald-200 border-emerald-600'],
      warn: ['!', 'bg-amber-900/60 text-amber-200 border-amber-500'],
      fail: ['✕', 'bg-rose-900/60 text-rose-200 border-rose-500'],
      info: ['i', 'bg-sky-900/60 text-sky-200 border-sky-600'],
    };
    const [icon, cls] = map[kind] || map.info;
    return `<span class="inline-flex items-center gap-1 rounded border px-2 py-0.5 text-xs font-semibold ${cls}"><span aria-hidden="true">${icon}</span>${esc(label)}</span>`;
  }

  function meters(c) {
    return [
      ['Mass', c.total_mass_kg, c.launch_capacity_kg, 'kg', 'Total mass against launch capacity'],
      ['Peak power', c.peak_load_w, c.peak_capacity_w, 'W', 'Everything switched on at once against the bus limit'],
      ['Energy per day', c.daily_demand_wh, c.daily_generation_wh, 'Wh', 'Daily demand against daily generation'],
      ['Battery', c.eclipse_need_wh, c.usable_battery_wh, 'Wh', 'Energy needed through eclipse against usable battery'],
      ['Data per day', c.data_generated_mb_day, c.downlink_mb_day, 'MB', 'Data made against data that can be sent down'],
      ['Budget', c.total_cost_mu, c.budget_mu, 'MU', 'Total cost against budget (fictional game currency)'],
    ];
  }

  function meterHtml([label, used, limit, unit, help], required) {
    if (limit === null) {
      return `<div class="rounded border border-slate-700 p-3"><div class="flex justify-between text-sm"><span class="font-semibold">${esc(label)}</span>${badge('fail', 'No limit')}</div><p class="mt-1 text-xs text-slate-400">This launcher does not serve the chosen orbit.</p></div>`;
    }
    const over = r2(used) > r2(limit);
    const pct = limit > 0 ? Math.min(100, Math.max(0, used / limit * 100)) : 100;
    const tight = !over && limit > 0 && (limit - used) / limit < 0.10;
    let kind = over ? 'fail' : (tight ? 'warn' : 'ok'), word = over ? 'Over' : (tight ? 'Tight' : 'OK');
    if (label === 'Data per day') { kind = over ? 'warn' : 'ok'; word = over ? 'Backlog' : 'OK'; }
    const bar = { ok: 'bg-emerald-500', warn: 'bg-amber-400', fail: 'bg-rose-500' }[kind];
    return `<div class="rounded border border-slate-700 bg-slate-900 p-3">
      <div class="flex items-center justify-between gap-2 text-sm"><span class="font-semibold">${esc(label)}</span>${badge(kind, word)}</div>
      <div class="mt-2 h-2 rounded bg-slate-700" aria-hidden="true"><div class="h-2 rounded ${bar}" style="width:${pct.toFixed(1)}%"></div></div>
      <p class="mt-1 text-xs text-slate-300">${num(used)} / ${num(limit)} ${unit} · ${over ? 'short by ' + num(used - limit) : 'margin ' + num(limit - used)} ${unit}</p>
      ${required !== undefined ? `<p class="text-xs text-slate-400">Users need ${num(required, 0)} MB/day delivered.</p>` : ''}
      <p class="sr-only">${esc(help)}</p></div>`;
  }

  function readInput() {
    const fd = new FormData(form);
    return {
      objective: D.objective, difficulty: D.difficulty,
      bus: fd.get('bus') || null, power: fd.get('power') || null, comms: fd.get('comms') || null,
      orbit: fd.get('orbit') || null, launch: fd.get('launch') || null,
      imaging: fd.get('imaging') ? Number(fd.get('imaging')) : null,
      instruments: fd.getAll('instruments[]').sort(), stations: fd.getAll('stations[]').sort(), addons: fd.getAll('addons[]').sort(),
    };
  }

  function optionProblem(kind, code, n) {
    const M = D.cat.matrix, cat = D.cat;
    const conflict = (a, b) => (M.CONFLICT[a] && M.CONFLICT[a][b]) || (M.CONFLICT[b] && M.CONFLICT[b][a]);
    if (kind === 'launch' && n.orbit && !(M.LAUNCH_CAPACITY[code] || {})[n.orbit]) return 'Does not fly to ' + cat.orbits[n.orbit].name;
    if (kind === 'orbit' && n.launch && !(M.LAUNCH_CAPACITY[n.launch] || {})[code]) return 'Your launcher does not fly here';
    if (kind === 'power' && n.bus && conflict(code, n.bus)) return 'Does not fit the ' + cat.components[n.bus].name;
    if (kind === 'bus' && n.power && conflict(n.power, code)) return 'Your power system does not fit this bus';
    if (kind === 'station') {
      if (n.orbit && Number(M.GROUND_CONTACT[code][n.orbit] || 0) === 0) return 'Never sees this orbit';
      if (n.comms) {
        const band = cat.components[n.comms].band, st = cat.stations[code];
        if ((band === 'X' && !Number(st.supports_x)) || (band === 'S' && !Number(st.supports_s))) return 'Cannot hear your ' + band + '-band radio';
      }
    }
    return null;
  }

  function segmentStatus(res, n) {
    const s = { LAUNCH: 'todo', SPACE: 'todo', GROUND: 'todo', USER: 'ok' };
    if (n.launch || n.orbit) s.LAUNCH = 'ok';
    if (n.bus || n.instruments.length || n.power || n.comms) s.SPACE = 'ok';
    if (n.stations.length) s.GROUND = 'ok';
    for (const w of res.warnings) if (s[w.segment] && s[w.segment] !== 'fail') s[w.segment] = 'warn';
    for (const h of res.hard) if (s[h.segment]) s[h.segment] = 'fail';
    return s;
  }

  const RAIL = {
    todo: ['To do', '○', 'border-slate-600 text-slate-300'],
    ok: ['Complete', '✓', 'border-emerald-500 text-emerald-300'],
    warn: ['Warning', '!', 'border-amber-400 text-amber-300'],
    fail: ['Failure', '✕', 'border-rose-500 text-rose-300'],
  };

  let lastAnnounce = '';
  function update() {
    const n = readInput();
    const res = evaluate(D, n);
    const c = res.calc;

    // Option warning chips and (Beginner) greying out
    form.querySelectorAll('[data-kind]').forEach(card => {
      const input = card.querySelector('input');
      const prob = optionProblem(card.dataset.kind, input.value, n);
      const slot = card.querySelector('.tw-problem');
      slot.innerHTML = prob ? badge('warn', prob) : '';
      input.setAttribute('aria-label', card.dataset.title + (prob ? ' (' + prob + ')' : ''));
      if (D.beginner) {
        const off = !!prob && !input.checked;
        input.disabled = off;
        card.classList.toggle('opacity-50', off);
        card.classList.toggle('cursor-not-allowed', off);
      }
    });

    // Slots counter
    const busRow = n.bus ? D.cat.components[n.bus] : null;
    $('slot-count').textContent = `(${n.instruments.length} of ${busRow ? busRow.instrument_slots : '—'} slots used)`;

    // Dashboard
    if (c) {
      let html = meters(c).map(m => meterHtml(m, m[0] === 'Data per day' ? c.required_mb_day : undefined)).join('');
      html += `<div class="rounded border border-slate-700 bg-slate-900 p-3 text-sm">
        <p><span class="text-slate-400">Predicted operational lifetime:</span> ${res.prediction.lifetime} of ${c.target_months} months</p>
        <p><span class="text-slate-400">Development time:</span> ${c.development_months} months (not scored)</p>`;
      if (!D.challenge) {
        const band = !res.flightReady ? ['fail', 'Not flight-ready'] : (res.outcome === 'SUCCESS' ? ['ok', 'On track'] : ['warn', 'At risk']);
        html += `<p class="mt-1"><span class="text-slate-400">Predicted science:</span> ${badge(band[0], band[1])}</p>`;
      }
      $('dash-live').innerHTML = html + '</div>';
    } else {
      $('dash-live').innerHTML = '<p class="rounded border border-slate-700 bg-slate-900 p-3 text-sm text-slate-300">The meters appear once every required part is chosen.</p>';
    }

    // Constraint list grouped by segment
    const items = [];
    for (const sg of ['LAUNCH', 'SPACE', 'GROUND', 'USER', 'ALL']) {
      for (const h of res.hard.filter(x => x.segment === sg)) items.push(['fail', h, sg]);
      for (const w of res.warnings.filter(x => x.segment === sg)) items.push(['warn', w, sg]);
    }
    $('dash-checks').innerHTML = items.length ? items.map(([kind, it, sg]) => `
      <li class="rounded border ${kind === 'fail' ? 'border-rose-700' : 'border-amber-700'} bg-slate-900 p-2">
        <div class="flex items-start gap-2">${badge(kind, it.code)} <a class="hover:underline" href="#seg-${SEG[sg]}">${esc(it.message)}</a></div>
        ${(D.beginner || kind === 'fail') ? `<p class="mt-1 text-xs text-sky-200">Fix: ${esc(it.feedback)}</p>`
          : (!D.challenge ? `<details class="mt-1 text-xs text-sky-200"><summary class="cursor-pointer">Show a hint</summary>${esc(it.feedback)}</details>` : '')}
      </li>`).join('') : `<li>${badge('ok', 'All clear')} No failures or warnings.</li>`;

    // Phone/tablet summary + polite announcement
    const nh = res.hard.length, nw = res.warnings.length;
    let worst = null;
    if (c) for (const m of meters(c)) {
      if (m[2] === null) { worst = [m[0], Infinity]; break; }
      const ratio = m[2] > 0 ? m[1] / m[2] : Infinity;
      if (!worst || ratio > worst[1]) worst = [m[0], ratio];
    }
    $('dash-summary-text').innerHTML = (nh ? badge('fail', nh + ' failure' + (nh === 1 ? '' : 's')) : badge('ok', 'No failures'))
      + (nw ? ' ' + badge('warn', nw + ' warning' + (nw === 1 ? '' : 's')) : '')
      + (worst ? ` <span class="text-slate-300">Tightest: ${esc(worst[0])} ${isFinite(worst[1]) ? Math.round(worst[1] * 100) + '% used' : ''}</span>` : '');
    const announce = `${nh} failures, ${nw} warnings`;
    if (announce !== lastAnnounce) { $('dash-live-region').textContent = 'Design updated: ' + announce + '.'; lastAnnounce = announce; }

    // Segment rail
    const st = segmentStatus(res, n);
    document.querySelectorAll('#segment-rail [data-seg]').forEach(el => {
      const [word, icon, cls] = RAIL[st[el.dataset.seg]];
      el.className = 'rounded border-2 ' + cls + ' px-2 py-1 text-center text-sm md:py-1.5 md:text-base';
      el.querySelector('[data-status]').innerHTML = `<span aria-hidden="true">${icon}</span> ${word}`;
    });

    // Header budget
    const hb = $('hdr-budget');
    if (hb && c) hb.textContent = `${num(c.budget_mu - c.total_cost_mu, 0)} of ${num(c.budget_mu, 0)} MU left`;

    // User segment R1–R3
    const ur = $('user-reqs');
    if (ur) {
      if (c) {
        const req = D.cat.objectives[D.objective].required_mb_per_day;
        const deliv = Math.min(c.data_generated_mb_day, c.downlink_mb_day);
        const p = res.prediction;
        ur.innerHTML = `<li>${badge(p.instrumentFactor >= 0.8 ? 'ok' : 'warn', 'R1')} Instrument fit ${p.instrumentFactor.toFixed(2)} (needs 0.80)</li>
          <li>${badge(p.orbitFactor >= 0.8 ? 'ok' : 'warn', 'R2')} Orbit fit ${p.orbitFactor.toFixed(2)} (needs 0.80)</li>
          <li>${badge(deliv >= req ? 'ok' : 'warn', 'R3')} Up to ${num(deliv, 0)} MB/day can reach users (needs ${num(req, 0)})</li>`;
      } else {
        ur.innerHTML = '';
      }
    }
  }

  // Autosave the draft (server validates every value; nothing calculated here is sent).
  let saveTimer = null;
  function autosave() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
      const fd = new FormData(form);
      fd.set('action', 'autosave');
      $('save-state').textContent = 'Saving…';
      // Note: form.action is shadowed by the buttons named "action", so post to this page's own path.
      fetch(location.pathname, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
        .then(r => { $('save-state').textContent = r.status === 204 ? 'Draft saved' : 'Not saved: press Update dashboard'; })
        .catch(() => { $('save-state').textContent = 'Not saved: press Update dashboard'; });
    }, 500);
  }

  form.addEventListener('change', () => { update(); autosave(); });
  update();
})();
