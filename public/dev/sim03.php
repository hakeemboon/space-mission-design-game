<?php
/**
 * TerraWatch-1 — SIM-03 check: browser engine (engine.js) vs server engine (engine.php).
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 * DEVELOPMENT ONLY: returns 404 unless config env is 'development'.
 *
 * Runs D1–D6 plus a deterministic sample of 1,500 designs through the PHP engine, then
 * runs the same inputs through engine.js in the browser and compares every displayed
 * value to 2 decimals, the hard-rule and warning sets, lifetime and scores.
 */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/src/app.php';

if (config('env') !== 'development') {
    http_response_code(404);
    exit;
}
// Dev-only page runs an inline comparison script; relax the CSP for this page alone.
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; frame-ancestors 'none'");
$cat = catalogue();
$rules = rules();

$cases = [
    'D1' => ['DIFF-S', 'OBJ-LAND', 'BUS-M', ['INS-MSI'], 'PWR-P2', 'COM-C2', ['GS-G1'], 'ORB-O1', 'LV-L3', 5, []],
    'D2' => ['DIFF-S', 'OBJ-COAST', 'BUS-M', ['INS-HSI'], 'PWR-P2', 'COM-C3', ['GS-G1'], 'ORB-O1', 'LV-L3', 5, []],
    'D3' => ['DIFF-S', 'OBJ-CLOUD', 'BUS-M', ['INS-TIR'], 'PWR-P2', 'COM-C2', ['GS-G2'], 'ORB-O3', 'LV-L2', 15, []],
    'D4' => ['DIFF-S', 'OBJ-LAND', 'BUS-S', ['INS-SWIR'], 'PWR-P1', 'COM-C1', ['GS-G1'], 'ORB-O1', 'LV-L1', 10, []],
    'D5' => ['DIFF-C', 'OBJ-CLOUD', 'BUS-M', ['INS-TIR'], 'PWR-P1', 'COM-C2', ['GS-G2'], 'ORB-O2', 'LV-L3', 15, ['ADD-DEO']],
    'D6' => ['DIFF-S', 'OBJ-CLOUD', 'BUS-S', ['INS-WFC'], 'PWR-P1', 'COM-C2', ['GS-G2'], 'ORB-O3', 'LV-L2', 15, []],
];
// Deterministic sample (fixed-seed LCG, no randomness between runs).
$pick = function (array $list, int &$seed) {
    $seed = ($seed * 1103515245 + 12345) % 2147483648;
    return $list[$seed % count($list)];
};
$seed = 20261115;
$insCodes = array_keys($cat['byCategory']['INSTRUMENT']);
$insSets = array_merge(array_map(fn($i) => [$i], $insCodes), [['INS-WFC', 'INS-SWIR'], ['INS-MSI', 'INS-TIR'], ['INS-HSI', 'INS-WFC'], ['INS-MSI', 'INS-SWIR', 'INS-TIR']]);
$stSets = [['GS-G1'], ['GS-G2'], ['GS-G3'], ['GS-G4'], ['GS-G1', 'GS-G2'], ['GS-G2', 'GS-G3'], ['GS-G1', 'GS-G4']];
$addSets = [[], ['ADD-STO'], ['ADD-DEO'], ['ADD-DEO', 'ADD-STO']];
for ($i = 1; $i <= 1500; $i++) {
    $cases['S' . $i] = [
        $pick(['DIFF-B', 'DIFF-S', 'DIFF-C'], $seed), $pick(array_keys($cat['objectives']), $seed),
        $pick(array_keys($cat['byCategory']['BUS']), $seed), $pick($insSets, $seed),
        $pick(array_keys($cat['byCategory']['POWER']), $seed), $pick(array_keys($cat['byCategory']['COMMS']), $seed),
        $pick($stSets, $seed), $pick(array_keys($cat['orbits']), $seed), $pick(array_keys($cat['launches']), $seed),
        $pick([5, 10, 15], $seed), $pick($addSets, $seed),
    ];
}
$server = [];
foreach ($cases as $id => $c) {
    $in = ['difficulty' => $c[0], 'objective' => $c[1], 'bus' => $c[2], 'instruments' => $c[3], 'power' => $c[4], 'comms' => $c[5],
           'stations' => $c[6], 'orbit' => $c[7], 'launch' => $c[8], 'imaging' => $c[9], 'addons' => $c[10]];
    $r = engine_run($in, $cat, $rules);
    sort($in['instruments']); sort($in['stations']); sort($in['addons']);
    $server[$id] = [
        'input' => $in,
        'calc' => $r['calc'],
        'hard' => codes_of($r['hard']), 'warnings' => codes_of($r['warnings']),
        'lifetime' => $r['prediction']['lifetime_months'] ?? null, 'end' => $r['prediction']['end_reason'] ?? null,
        'scores' => $r['scores'] ?? null, 'outcome' => $r['outcome'] ?? null,
    ];
}
function codes_of(array $l): array
{
    $c = array_map(fn($x) => $x['code'], $l);
    sort($c);
    return $c;
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>SIM-03 check</title></head>
<body style="font-family:monospace;background:#fff;color:#000">
<h1>SIM-03: browser vs server engine</h1>
<pre id="out">running…</pre>
<script type="application/json" id="tw-data"><?= engine_js_json(engine_js_data()) ?></script>
<script type="application/json" id="cases"><?= engine_js_json($server) ?></script>
<script src="/assets/js/engine.js?v=1"></script>
<script>
(function () {
  const D = JSON.parse(document.getElementById('tw-data').textContent);
  const S = JSON.parse(document.getElementById('cases').textContent);
  const fields = ['total_mass_kg','launch_capacity_kg','peak_load_w','peak_capacity_w','contact_min_per_day','average_load_w','daily_demand_wh',
    'daily_generation_wh','eclipse_need_wh','usable_battery_wh','data_generated_mb_day','downlink_mb_day','storage_mb','total_cost_mu','development_months'];
  let pass = 0, fail = 0; const lines = []; const refLines = [];
  for (const id in S) {
    const s = S[id], b = TWEngine.evaluate(D, s.input), bad = [];
    for (const f of fields) {
      const sv = s.calc ? s.calc[f] : null, bv = b.calc ? b.calc[f] : null;
      if (!((sv === null && (bv === null || bv === undefined)) || Math.abs(Number(sv) - Number(bv)) < 0.005)) bad.push(f + ' server ' + sv + ' browser ' + bv);
    }
    const bh = b.hard.map(x => x.code).sort(), bw = b.warnings.map(x => x.code).sort();
    if (JSON.stringify(bh) !== JSON.stringify(s.hard)) bad.push('hard server ' + s.hard + ' browser ' + bh);
    if (JSON.stringify(bw) !== JSON.stringify(s.warnings)) bad.push('warnings server ' + s.warnings + ' browser ' + bw);
    if (s.calc) {
      if (b.prediction.lifetime !== s.lifetime) bad.push('lifetime server ' + s.lifetime + ' browser ' + b.prediction.lifetime);
      if (b.prediction.endReason !== s.end) bad.push('end server ' + s.end + ' browser ' + b.prediction.endReason);
    }
    if (s.scores) {
      for (const k of ['science','efficiency','responsibility','mission']) if (Math.abs(s.scores[k] - b.scores[k]) >= 0.005) bad.push(k + ' server ' + s.scores[k] + ' browser ' + b.scores[k]);
      if (s.outcome !== b.outcome) bad.push('outcome server ' + s.outcome + ' browser ' + b.outcome);
    }
    if (bad.length) { fail++; if (lines.length < 40) lines.push('FAIL ' + id + ': ' + bad.join('; ')); } else pass++;
    if (id[0] === 'D') refLines.push((bad.length ? 'FAIL ' : 'PASS ') + id + (s.scores ? ' science ' + b.scores.science + ' mission ' + b.scores.mission : ''));
  }
  const flight = Object.values(S).filter(s => s.scores).length;
  document.getElementById('out').textContent = refLines.join('\n') + '\n\n' + lines.join('\n') +
    '\n\nSIM-03 RESULT: ' + pass + ' of ' + (pass + fail) + ' designs agree (' + flight + ' flight-ready), ' + fail + ' differ.';
})();
</script>
</body></html>
