<?php
/**
 * TerraWatch-1 — screen 6: Preflight review and launch gate.
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 * Launch re-runs the engine on the server from the stored design; nothing from the
 * browser except the session token is trusted.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/src/app.php';

$m = require_mission();
if ($m['status'] === 'FLOWN') {
    redirect('/designer.php');
}
$cat = catalogue();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $result = engine_run(mission_input($m), $cat, rules());
    if (!$result['flight_ready']) {
        flash('Not flight-ready: fix the listed issues first.');
        redirect('/preflight.php');
    }
    $runId = store_run((int) $m['id'], $result);
    redirect('/simulation.php?run=' . $runId);
}

$result = engine_run(mission_input($m), $cat, rules());
$c = $result['calc'];
if ($c) {
    $m['_cost'] = $c['total_cost_mu'];
}
$rows = [];
$add = function (string $seg, string $what, ?array $comp, string $extra = '') use (&$rows) {
    $rows[] = [$seg, $what, $comp['name'] ?? '—', $comp['mass_kg'] ?? null, $comp['power_w'] ?? null, $comp['cost_mu'] ?? null, $extra];
};
if ($m['launch']) { $add('Launch', 'Launcher', $cat['launches'][$m['launch']]); }
if ($m['orbit']) { $o = $cat['orbits'][$m['orbit']]; $rows[] = ['Launch', 'Orbit', $o['name'], null, null, null, num($o['altitude_km'], 0) . ' km']; }
if ($m['bus']) { $add('Space', 'Bus', $cat['components'][$m['bus']], 'housekeeping load'); }
foreach ($m['instruments'] as $i) { $add('Space', 'Instrument', $cat['components'][$i]); }
if ($m['power']) { $add('Space', 'Power system', $cat['components'][$m['power']]); }
if ($m['comms']) { $add('Space', 'Radio', $cat['components'][$m['comms']]); }
foreach ($m['addons'] as $a) { $add('Space', 'Add-on', $cat['components'][$a]); }
if ($m['imaging']) { $rows[] = ['Space', 'Imaging time', $m['imaging'] . ' minutes per orbit', null, null, null, '']; }
foreach ($m['stations'] as $s) { $add('Ground', 'Ground station', $cat['stations'][$s]); }
$issues = count($result['hard']);

page_start(); ?>
<h1 class="text-2xl font-bold">Preflight review</h1>
<p class="mt-2 text-slate-300">A design review before committing: check the equipment list, the margins and every issue. A failed review costs nothing; that is what reviews are for.</p>
<div class="mt-4 overflow-x-auto">
<table class="w-full min-w-[640px] text-left text-sm">
    <thead class="border-b border-slate-700 text-slate-400"><tr><th class="py-2 pr-3">Segment</th><th class="pr-3">Item</th><th class="pr-3">Choice</th><th class="pr-3 text-right">Mass (kg)</th><th class="pr-3 text-right">Power (W)</th><th class="pr-3 text-right">Cost (MU)</th><th>Note</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr class="border-b border-slate-800"><td class="py-2 pr-3"><?= e($r[0]) ?></td><td class="pr-3"><?= e($r[1]) ?></td><td class="pr-3"><?= e($r[2]) ?></td>
            <td class="pr-3 text-right"><?= $r[3] !== null ? e(num($r[3])) : '' ?></td><td class="pr-3 text-right"><?= $r[4] !== null ? e(num($r[4], 0)) : '' ?></td>
            <td class="pr-3 text-right"><?= $r[5] !== null ? e(num($r[5], 0)) : '' ?></td><td class="text-slate-400"><?= e($r[6]) ?></td></tr>
    <?php endforeach; ?>
    <?php if ($c): ?>
        <tr class="font-semibold"><td class="py-2" colspan="3">Totals</td><td class="pr-3 text-right"><?= e(num($c['total_mass_kg'])) ?></td>
            <td class="pr-3 text-right"><?= e(num($c['peak_load_w'], 0)) ?> peak</td><td class="pr-3 text-right"><?= e(num($c['total_cost_mu'], 0)) ?></td><td></td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

<?php if ($c): ?>
<div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    <?php foreach (meters($c) as $mt) { echo meter_html($mt, $mt[0] === 'Data per day' ? $c['required_mb_day'] : null); } ?>
</div>
<div class="mt-3 grid gap-3 sm:grid-cols-2">
    <p class="rounded border border-slate-700 bg-slate-900 p-3 text-sm"><span class="text-slate-400">Development time (build and test, before launch):</span> <?= (int) $c['development_months'] ?> months</p>
    <p class="rounded border border-slate-700 bg-slate-900 p-3 text-sm"><span class="text-slate-400">Predicted operational lifetime (after launch):</span> <?= (int) $result['prediction']['lifetime_months'] ?> of <?= (int) $c['target_months'] ?> months</p>
</div>
<?php endif; ?>

<h2 class="mt-6 text-lg font-semibold">Issues</h2>
<ul class="mt-2 space-y-2 text-sm">
    <?php foreach ($result['hard'] as $h): ?>
        <li class="rounded border border-rose-700 bg-slate-900 p-2"><?= status_badge('fail', $h['code']) ?> <?= e($h['message']) ?>
            <p class="mt-1 text-xs text-sky-200">Fix: <?= e($h['feedback']) ?></p></li>
    <?php endforeach; ?>
    <?php foreach ($result['warnings'] as $w): ?>
        <li class="rounded border border-amber-700 bg-slate-900 p-2"><?= status_badge('warn', $w['code']) ?> <?= e($w['message']) ?>
            <span class="text-xs text-slate-400">(warning: allowed, but it will cost you)</span></li>
    <?php endforeach; ?>
    <?php if (!$result['hard'] && !$result['warnings']): ?><li><?= status_badge('ok', 'Clear') ?> No failures or warnings.</li><?php endif; ?>
</ul>
<div class="mt-4 grid gap-3 md:grid-cols-2"><?php foreach (fact_cards('preflight') as $f) { echo fact_card_html($f); } ?></div>

<div class="mt-6 flex flex-wrap gap-3">
    <a class="<?= btn_secondary() ?>" href="/designer.php">Back to designer</a>
    <?php if ($result['flight_ready']): ?>
        <form method="post"><?= csrf_field() ?><button class="<?= btn_primary() ?>" type="submit">Launch</button></form>
    <?php else: ?>
        <a class="<?= btn_primary() ?> !bg-rose-800" href="/designer.php">Not flight-ready: <?= $issues ?> issue<?= $issues === 1 ? '' : 's' ?></a>
    <?php endif; ?>
</div>
<?php page_end('Preflight', 6, segment_status($result, $m), $m);
