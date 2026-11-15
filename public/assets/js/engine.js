/**
 * TerraWatch-1 — browser mirror of the engine (Document 04 stages 1–3 plus the lifetime
 * prediction), used ONLY for live display in the designer. The PHP engine on the server
 * is authoritative: browser values are never sent, stored or trusted.
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 *
 * Mirrors src/services/engine.php. If you change one, change the other, and run the
 * SIM-03 check (/dev/sim03.php in development) to confirm they still agree.
 */
(function (global) {
  'use strict';

  /** Round half away from zero to 2 decimals, like PHP round($x, 2). */
  function r2(x) {
    // PHP pre-rounds to 15 significant digits before rounding; do the same so 1.005 -> 1.01.
    const s = x < 0 ? -1 : 1;
    return s * Math.round(Number((Math.abs(x) * 100).toPrecision(15))) / 100;
  }
  function fmt(x) {
    x = r2(x);
    const dec = Math.abs(x - Math.round(x)) < 0.000001 ? 0 : 2;
    return x.toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec });
  }
  const sum = (rows, f) => rows.reduce((a, r) => a + Number(r[f] || 0), 0);

  function text(D, code, vars, field) {
    let t = String(D.rules[code][field || 'message'] || '');
    for (const k in vars) t = t.split('{' + k + '}').join(String(vars[k]));
    return t;
  }

  /** Stage 1 (H01): returns list of problems; empty when the design is complete. */
  function structure(D, n) {
    const p = [];
    const cat = D.cat;
    const one = (v, pool, label) => { if (!v) p.push('no ' + label + ' selected'); else if (!pool[v]) p.push('unknown ' + label); };
    const many = (v, pool, label, min, max) => {
      v = v || [];
      if (v.some(c => !pool[c])) { p.push('unknown ' + label); return; }
      if (new Set(v).size !== v.length) p.push('the same ' + label + ' was selected twice');
      if (v.length < min) p.push('no ' + label + ' selected');
      if (v.length > max) p.push('more than ' + max + ' ' + label + 's selected');
    };
    one(n.objective, cat.objectives, 'objective');
    one(n.difficulty, cat.difficulties, 'difficulty');
    one(n.bus, cat.byCategory.BUS, 'bus');
    many(n.instruments, cat.byCategory.INSTRUMENT, 'instrument', 1, D.params.MAX_INSTRUMENTS);
    one(n.power, cat.byCategory.POWER, 'power system');
    one(n.comms, cat.byCategory.COMMS, 'radio');
    many(n.stations, cat.stations, 'ground station', 1, D.params.MAX_STATIONS);
    one(n.orbit, cat.orbits, 'orbit');
    one(n.launch, cat.launches, 'launcher');
    many(n.addons, cat.byCategory.ADDON, 'add-on', 0, 99);
    const opts = String(D.params.IMAGING_OPTIONS).split(',').map(Number);
    if (!opts.includes(Number(n.imaging))) p.push('imaging time must be ' + opts.join(', ') + ' minutes per orbit');
    return [...new Set(p)];
  }

  function evaluate(D, n) {
    const P = D.params, R = D.rules, cat = D.cat, M = cat.matrix;
    const problems = structure(D, n);
    if (problems.length) {
      return { flightReady: false, calc: null, warnings: [],
        hard: [{ code: 'H01', segment: 'ALL', message: text(D, 'H01', { detail: problems.join('; ') }), feedback: R.H01.feedback }] };
    }
    const C = cat.components;
    const bus = C[n.bus], pwr = C[n.power], com = C[n.comms];
    const ins = n.instruments.map(c => C[c]), add = n.addons.map(c => C[c]);
    const orb = cat.orbits[n.orbit], obj = cat.objectives[n.objective], dif = cat.difficulties[n.difficulty];
    const stations = n.stations.map(s => [s, cat.stations[s]]);
    const hasDeo = n.addons.includes('ADD-DEO');
    const img = Number(n.imaging);

    const payload = sum(ins, 'mass_kg');
    const mass = bus.mass_kg + payload + pwr.mass_kg + com.mass_kg + sum(add, 'mass_kg');
    const capacity = (M.LAUNCH_CAPACITY[n.launch] || {})[n.orbit];
    const hasCap = capacity !== undefined;
    const marginKg = hasCap ? capacity - mass : null;
    const peak = bus.power_w + sum(ins, 'power_w') + com.power_w + sum(add, 'power_w');
    const bandKey = com.band === 'X' ? 'supports_x' : 'supports_s';
    let contact = 0; const deaf = [];
    for (const [code, st] of stations) {
      if (Number(st[bandKey]) === 1) contact += Number(M.GROUND_CONTACT[code][n.orbit] || 0); else deaf.push(st.name);
    }
    const duty = img / orb.period_min;
    const avgLoad = bus.power_w + sum(ins, 'power_w') * duty + com.power_w * contact / P.MINUTES_PER_DAY + sum(add, 'power_w');
    const demand = avgLoad * 24;
    const gen = pwr.generation_w * orb.sunlight_h_per_day;        // seeded sunlight hours, never recomputed
    const eclipseNeed = avgLoad * orb.eclipse_min / 60;            // seeded eclipse minutes
    const usable = pwr.battery_wh * P.USABLE_BATTERY_FRACTION;
    const dataGen = sum(ins, 'data_rate_mbps') * img * 60 * orb.orbits_per_day / P.BITS_PER_BYTE;
    const downlink = com.data_rate_mbps * contact * 60 / P.BITS_PER_BYTE;
    const storage = (bus.storage_gb + sum(add, 'storage_gb')) * P.MB_PER_GB;
    const cost = bus.cost_mu + sum(ins, 'cost_mu') + pwr.cost_mu + com.cost_mu
      + stations.reduce((a, [, st]) => a + st.cost_mu, 0) + cat.launches[n.launch].cost_mu + sum(add, 'cost_mu');
    const devMonths = bus.dev_months + sum(ins, 'dev_months');
    const budget = dif.budget_mu, target = dif.target_months;

    const calc = {
      total_mass_kg: r2(mass), launch_capacity_kg: hasCap ? r2(capacity) : null, mass_margin_kg: hasCap ? r2(marginKg) : null,
      peak_load_w: r2(peak), peak_capacity_w: r2(bus.peak_capacity_w), contact_min_per_day: r2(contact),
      average_load_w: r2(avgLoad), daily_demand_wh: r2(demand), daily_generation_wh: r2(gen),
      eclipse_need_wh: r2(eclipseNeed), usable_battery_wh: r2(usable), data_generated_mb_day: r2(dataGen),
      downlink_mb_day: r2(downlink), required_mb_day: r2(obj.required_mb_per_day), storage_mb: r2(storage),
      total_cost_mu: r2(cost), budget_mu: r2(budget), development_months: devMonths, target_months: target,
      instrument_slots: bus.instrument_slots, instruments_selected: ins.length,
    };

    const hard = [];
    const addHard = (code, vars) => hard.push({ code, segment: R[code].segment, message: text(D, code, vars || {}), feedback: R[code].feedback });
    if (ins.length > bus.instrument_slots) addHard('H02', { limit: bus.instrument_slots, value: ins.length });
    if (r2(payload) > r2(bus.payload_limit_kg)) addHard('H03', { value: fmt(payload), limit: fmt(bus.payload_limit_kg) });
    const selected = [n.bus, n.power, n.comms, ...n.instruments, ...n.addons];
    const pairs = [];
    for (const a in (M.CONFLICT || {})) for (const b in M.CONFLICT[a]) if (selected.includes(a) && selected.includes(b)) pairs.push(C[a].name + ' with ' + C[b].name);
    if (pairs.length) addHard('H04', { detail: pairs.join('; ') });
    if (!hasCap) addHard('H05');
    else if (r2(mass) > r2(capacity)) addHard('H06', { value: fmt(mass), limit: fmt(capacity) });
    if (r2(peak) > r2(bus.peak_capacity_w)) addHard('H07', { value: fmt(peak), limit: fmt(bus.peak_capacity_w) });
    if (deaf.length) addHard('H08', { detail: deaf.join(', ') + ' cannot receive ' + com.band + '-band' });
    if (r2(contact) === 0) addHard('H09');
    if (r2(gen) < r2(demand)) addHard('H10', { value: fmt(gen), limit: fmt(demand) });
    if (r2(eclipseNeed) > r2(usable)) addHard('H11', { value: fmt(eclipseNeed), limit: fmt(usable) });
    if (r2(cost) > r2(budget)) addHard('H12', { value: fmt(cost), limit: fmt(budget) });

    const suits = n.instruments.map(c => M.INSTRUMENT_SUIT[c][n.objective]).sort((a, b) => b - a);
    const best = suits[0];
    const fInst = Math.min(1, best + (suits.length > 1 ? P.SECOND_INSTRUMENT_BONUS * suits[1] : 0));
    const fOrbit = M.ORBIT_SUIT[n.orbit][n.objective];
    const genMonth = m => gen * (1 - P.SOLAR_LOSS_PER_MONTH * m);
    let energyLimit = null;
    for (let m = 1; m <= target; m++) if (r2(genMonth(m)) < r2(demand)) { energyLimit = m - 1; break; }
    const limits = { TARGET: target, ENERGY: energyLimit, BUS_LIFE: bus.design_life_months, DECAY: orb.decay_months };
    const L = Math.min(...Object.values(limits).filter(v => v !== null));
    const endReason = ['TARGET', 'ENERGY', 'BUS_LIFE', 'DECAY'].find(k => limits[k] === L);

    // Monthly loop months 1..L (same as server) for data delivered / lost.
    const days = P.DAYS_PER_MONTH, comm = P.COMMISSIONING_MONTHS;
    let backlog = 0, delivered = 0, lost = 0;
    for (let m = comm + 1; m <= L; m++) {
      const available = backlog + dataGen * days;
      const sent = Math.min(available, downlink * days);
      const remaining = available - sent;
      lost += Math.max(0, remaining - storage);
      backlog = Math.min(storage, remaining);
      delivered += sent;
    }
    const sciMonths = Math.max(0, L - comm);
    const avgDelivered = sciMonths > 0 ? delivered / (days * sciMonths) : 0;
    const fData = L > 1 ? Math.min(1, avgDelivered / obj.required_mb_per_day) : 0;
    const fLife = target > 0 ? Math.min(1, L / target) : 0;

    const warnings = [];
    const addWarn = (code, vars) => warnings.push({ code, segment: R[code].segment, message: text(D, code, vars || {}), feedback: R[code].feedback });
    if (hasCap && r2(marginKg) < r2(P.MASS_MARGIN_WARN * capacity)) addWarn('W01', { value: fmt(marginKg), limit: fmt(capacity) });
    if (r2(gen) < r2(P.ENERGY_MARGIN_WARN * demand)) addWarn('W02', { value: fmt(gen), limit: fmt(demand) });
    if (r2(dataGen) > r2(downlink)) addWarn('W03', { value: fmt(dataGen), limit: fmt(downlink), detail: fmt(storage / (dataGen - downlink)) });
    if (!hard.length && r2(lost) > 0) addWarn('W04');
    if (Number(orb.needs_disposal_aid) === 1 && !hasDeo) addWarn('W05');
    if (!hard.length && L < target) addWarn('W06', { value: L, limit: target });
    if (r2(best) < r2(P.REQ_INSTRUMENT_SUIT_MIN)) addWarn('W07', { value: best.toFixed(2) });
    if (r2(fOrbit) < r2(P.REQ_ORBIT_SUIT_MIN)) addWarn('W08', { value: fOrbit.toFixed(2) });
    if (r2(Math.min(dataGen, downlink)) < r2(obj.required_mb_per_day)) addWarn('W09', { value: fmt(Math.min(dataGen, downlink)), limit: fmt(obj.required_mb_per_day) });
    const blind = stations.filter(([code]) => Number(M.GROUND_CONTACT[code][n.orbit] || 0) === 0).map(([, st]) => st.name);
    if (blind.length && blind.length < stations.length) addWarn('W10', { detail: blind.join(', ') });
    if (dif.schedule_limit_months !== null && devMonths > dif.schedule_limit_months) addWarn('W11', { value: devMonths, limit: dif.schedule_limit_months });

    const res = { flightReady: !hard.length, calc, hard, warnings,
      prediction: { lifetime: L, endReason, instrumentFactor: r2(fInst), orbitFactor: r2(fOrbit), deliveredPerDay: r2(avgDelivered) } };
    if (!hard.length) {
      const science = 100 * fInst * fOrbit * fData * fLife;
      const eff = 50 * Math.min(1, (budget - cost) / (P.EFFICIENCY_BUDGET_REF * budget)) + 50 * Math.min(1, marginKg / (P.EFFICIENCY_MASS_REF * capacity));
      const disposalOk = orb.decay_months <= P.DISPOSAL_DECAY_LIMIT || hasDeo;
      const resp = (disposalOk ? P.RESP_DISPOSAL_POINTS : 0) + (r2(lost) === 0 ? P.RESP_NO_LOSS_POINTS : 0);
      const mission = P.WEIGHT_SCIENCE * science + P.WEIGHT_EFFICIENCY * eff + P.WEIGHT_RESPONSIBILITY * resp;
      const s2 = r2(science);
      res.scores = { science: s2, efficiency: r2(eff), responsibility: r2(resp), mission: r2(mission) };
      res.outcome = s2 >= P.SUCCESS_THRESHOLD ? 'SUCCESS' : (s2 >= P.PARTIAL_THRESHOLD ? 'PARTIAL' : 'NOT_MET');
    }
    return res;
  }

  global.TWEngine = { evaluate, r2, fmt };
})(window);
