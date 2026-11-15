<?php
/**
 * TerraWatch-1 — screen 8: Mission results, including "What the users received".
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/src/app.php';

$run = owned_run(isset($_GET['run']) ? (int) $_GET['run'] : null);
if (!$run) {
    flash('That mission run was not found in this session.');
    redirect('/');
}
$m = mission_load((int) $run['mission_id']);
$s = $run['summary'];
$m['_cost'] = $s['calc']['total_cost_mu'];
$banner = [
    'SUCCESS' => ['ok', 'Mission success', 'border-emerald-500 bg-emerald-950/50'],
    'PARTIAL' => ['warn', 'Partial success', 'border-amber-400 bg-amber-950/40'],
    'NOT_MET' => ['fail', 'Objective not met', 'border-rose-500 bg-rose-950/40'],
][$run['outcome']];
$factors = [];
foreach (['instrument', 'orbit', 'data', 'lifetime'] as $k) { // formula order (stored JSON is key-sorted)
    $factors[$k] = (float) $s['factors'][$k];
}
$lowest = array_keys($factors, min($factors))[0];
$factorInfo = [
    'instrument' => ['Instrument fit', 'Best instrument suitability plus 0.25 × the second best, capped at 1.'],
    'orbit'      => ['Orbit fit', 'Orbit suitability for this objective.'],
    'data'       => ['Data delivered', 'Average MB delivered per day ÷ MB required per day, capped at 1.'],
    'lifetime'   => ['Lifetime', 'Months operated ÷ target months, capped at 1.'],
];
// The same formulas worked through with this run's own numbers (from the stored snapshot and summary).
$snap = $run['snapshot'];
$suits = [];
foreach ($snap['matrix']['INSTRUMENT_SUIT'] as $code => $v) {
    $suits[] = [$snap['components'][$code]['name'], (float) $v];
}
usort($suits, fn($a, $b) => $b[1] <=> $a[1]);
$instWork = e($suits[0][0]) . ' fits ' . number_format($suits[0][1], 2);
if (isset($suits[1])) {
    $instWork .= ', plus 0.25 × ' . e($suits[1][0]) . ' ' . number_format($suits[1][1], 2)
        . ' = ' . number_format(min(1, $suits[0][1] + 0.25 * $suits[1][1]), 2) . ($suits[0][1] + 0.25 * $suits[1][1] > 1 ? ' (capped at 1)' : '');
}
$work = [
    'instrument' => $instWork,
    'orbit'      => e($snap['orbit']['name']) . ' fits this objective ' . number_format($factors['orbit'], 2),
    'data'       => e(num($s['totals']['avg_delivered_mb_day'], 0)) . ' MB/day delivered ÷ ' . e(num($s['calc']['required_mb_day'], 0))
                    . ' MB/day required = ' . number_format($factors['data'], 2) . ($factors['data'] >= 1 ? ' (capped at 1)' : ''),
    'lifetime'   => (int) $run['operational_months'] . ' months ÷ ' . (int) $s['calc']['target_months'] . '-month target = ' . number_format($factors['lifetime'], 2),
];
$scoreWork = '100 × ' . implode(' × ', array_map(fn($v) => number_format($v, 2), $factors)) . ' = ' . number_format((float) $run['science_score'], 2);
$endWords = ['TARGET' => 'Target lifetime reached', 'ENERGY' => 'Power shortfall', 'BUS_LIFE' => 'Bus reached its design life', 'DECAY' => 'Orbit decayed'];
$u = $s['user'];

page_start(); ?>
<div class="relative isolate overflow-hidden rounded-lg border-2 <?= $banner[2] ?> p-5 sm:pb-12">
    <img src="/assets/img/data-background.webp" width="1920" height="823" alt="Abstract dark-blue data grid (SI-generated image)"
         class="absolute inset-0 -z-10 hidden h-full w-full object-cover object-right-bottom opacity-90 sm:block">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold"><span aria-hidden="true"><?= ['ok' => '✓', 'warn' => '!', 'fail' => '✕'][$banner[0]] ?></span> <?= e($banner[1]) ?></h1>
        <p class="text-lg">Science score <strong class="text-3xl"><?= e(number_format((float) $run['science_score'], 2)) ?></strong> / 100</p>
    </div>
    <p class="mt-1 text-xs text-slate-300">The science score is a game-only metric, not a measure of real scientific return. Success is 75 or more; partial success is 50 to 74.</p>
</div>
<div class="mt-3"><?= model_label() ?></div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <section aria-labelledby="h-factors">
        <h2 id="h-factors" class="text-lg font-semibold">Where the score came from</h2>
        <p class="text-sm text-slate-400">Science = 100 × instrument × orbit × data × lifetime. One weak factor pulls everything down.</p>
        <div class="mt-3 space-y-3">
        <?php foreach ($factors as $k => $v): $isLow = $k === $lowest && $v < 1; ?>
            <details class="rounded border <?= $isLow ? 'border-amber-400' : 'border-slate-700' ?> bg-slate-900 p-3">
                <summary class="flex cursor-pointer items-center justify-between gap-2 text-sm">
                    <span class="font-semibold"><?= e($factorInfo[$k][0]) ?> <?= $isLow ? status_badge('warn', 'Weakest') : '' ?></span>
                    <span><?= e(number_format((float) $v, 2)) ?></span></summary>
                <div class="mt-2 h-2 rounded bg-slate-700" aria-hidden="true"><div class="h-2 rounded <?= $isLow ? 'bg-amber-400' : 'bg-sky-500' ?>" style="width:<?= number_format(min(1, (float) $v) * 100, 1, '.', '') ?>%"></div></div>
                <p class="mt-2 text-xs text-slate-300"><?= e($factorInfo[$k][1]) ?></p>
                <p class="mt-1 text-xs text-sky-200">Your mission: <?= $work[$k] ?></p>
            </details>
        <?php endforeach; ?>
        </div>
        <p class="mt-2 text-sm text-slate-300">Your score: <?= e($scoreWork) ?> <span class="text-xs text-slate-400">(each factor shown rounded)</span></p>
        <div class="mt-4 grid grid-cols-3 gap-2 text-center text-sm">
            <div class="rounded border border-slate-700 bg-slate-900 p-2"><p class="text-xs text-slate-400">Efficiency</p><p class="text-xl font-semibold"><?= e(number_format((float) $run['efficiency_score'], 2)) ?></p></div>
            <div class="rounded border border-slate-700 bg-slate-900 p-2"><p class="text-xs text-slate-400">Responsibility</p><p class="text-xl font-semibold"><?= e(number_format((float) $run['responsibility_score'], 0)) ?></p></div>
            <div class="rounded border border-sky-600 bg-slate-900 p-2"><p class="text-xs text-slate-400">Mission score</p><p class="text-xl font-semibold"><?= e(number_format((float) $run['mission_score'], 2)) ?></p></div>
        </div>
        <p class="mt-2 text-xs text-slate-400">Mission score = 70% science + 20% efficiency (money and mass left over) + 10% responsibility (clean disposal, no data lost).</p>
    </section>

    <section aria-labelledby="h-users" class="rounded-lg border border-sky-700 bg-sky-950/30 p-4">
        <h2 id="h-users" class="text-lg font-semibold">What the users received</h2>
        <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
            <dt class="text-slate-400">Delivered</dt><dd><?= e(num($u['delivered_gb'], 0)) ?> GB over <?= (int) $u['observation_months'] ?> months of observations</dd>
            <dt class="text-slate-400">Per day</dt><dd><?= e(num($u['avg_mb_per_day'], 0)) ?> MB on average, against <?= e(num($u['required_mb_per_day'], 0)) ?> MB needed</dd>
            <dt class="text-slate-400">Who needs it</dt><dd><?= e($u['personas']) ?></dd>
            <dt class="text-slate-400">Their question</dt><dd><?= e($u['question']) ?></dd>
        </dl>
        <h3 class="mt-3 text-sm font-semibold">What limitations remain</h3>
        <ul class="mt-1 list-disc space-y-1 pl-5 text-sm">
            <?php foreach ($u['limitations'] as $l): ?><li><?= e($l) ?></li><?php endforeach; ?>
        </ul>
        <p class="mt-3 text-xs italic text-slate-400"><?= e($u['disclaimer']) ?></p>
    </section>
</div>

<section class="mt-6" aria-labelledby="h-req">
    <h2 id="h-req" class="text-lg font-semibold">Requirements</h2>
    <div class="mt-2 overflow-x-auto">
    <table class="w-full min-w-[560px] text-left text-sm">
        <thead class="border-b border-slate-700 text-slate-400"><tr><th class="py-2 pr-3">Req</th><th class="pr-3">Requirement</th><th class="pr-3">Segment</th><th class="pr-3">Measured</th><th class="pr-3">Required</th><th>Result</th></tr></thead>
        <tbody>
        <?php foreach ($s['requirements'] as $r):
            $fmtv = fn($v) => is_numeric($v) ? num((float) $v, (float) $v < 10 ? 2 : 0) : $v; ?>
            <tr class="border-b border-slate-800"><td class="py-2 pr-3 font-semibold"><?= e($r['code']) ?></td><td class="pr-3"><?= e($r['name']) ?></td>
                <td class="pr-3"><?= e(SEGMENTS[$r['segment']] ?? 'All') ?></td>
                <td class="pr-3"><?= e($fmtv($r['measured'])) ?> <?= e($r['unit']) ?></td><td class="pr-3"><?= e($fmtv($r['required'])) ?> <?= e($r['unit']) ?></td>
                <td><?= $r['met'] ? status_badge('ok', 'Met') : status_badge('fail', 'Not met') ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>

<section class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
    <div class="rounded border border-slate-700 bg-slate-900 p-3"><p class="text-xs text-slate-400">Data delivered</p><p class="font-semibold"><?= e(num($s['totals']['delivered_mb'] / 1000, 0)) ?> GB</p></div>
    <div class="rounded border border-slate-700 bg-slate-900 p-3"><p class="text-xs text-slate-400">Data lost</p><p class="font-semibold"><?= e(num($s['totals']['lost_mb'] / 1000, 0)) ?> GB</p></div>
    <div class="rounded border border-slate-700 bg-slate-900 p-3"><p class="text-xs text-slate-400">Months operated</p><p class="font-semibold"><?= e($s['lifetime_text']) ?></p></div>
    <div class="rounded border border-slate-700 bg-slate-900 p-3"><p class="text-xs text-slate-400">Why it ended</p><p class="font-semibold"><?= e($endWords[$run['end_reason']]) ?></p></div>
</section>
<div class="mt-4 max-w-xl"><?php foreach (fact_cards('results') as $f) { echo fact_card_html($f); } ?></div>

<div class="mt-6 flex flex-wrap gap-3">
    <a class="<?= btn_secondary() ?>" href="/simulation.php?run=<?= (int) $run['id'] ?>">Replay simulation</a>
    <a class="<?= btn_primary() ?>" href="/debrief.php?run=<?= (int) $run['id'] ?>">Debrief and redesign</a>
</div>
<?php page_end('Results', 8, segment_status(['warnings' => $s['warnings'], 'hard' => []], $m), $m);
