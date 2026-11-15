<?php
/**
 * TerraWatch-1 — screen 7: Simulation. Replays the stored run month by month.
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 * The run is already saved when this page opens; nothing here can change it.
 * Without JavaScript the full log and month table are shown instead of the replay.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/src/app.php';

$run = owned_run(isset($_GET['run']) ? (int) $_GET['run'] : null);
if (!$run) {
    flash('That mission run was not found in this session.');
    redirect('/');
}
$m = mission_load((int) $run['mission_id']);
$m['_cost'] = $run['summary']['calc']['total_cost_mu'];
$snap = $run['snapshot'];
$sevKind = ['INFO' => 'info', 'WARNING' => 'warn', 'CRITICAL' => 'fail'];
$L = (int) $run['operational_months'];
$endMonth = $run['summary']['failure_month'] ?? $L;

// Illustrative station markers around the Earth disc (not real positions).
$angleByLabel = ['Polar' => -90, 'Mid-latitude' => -40, 'Equatorial' => 0, 'Global network' => 180];
$band = $snap['components'][$snap['input']['comms']]['band'];
$stations = [];
foreach ($snap['stations'] as $code => $st) {
    $contact = (float) $snap['matrix']['GROUND_CONTACT'][$code];
    $hears = $band === 'X' ? (int) $st['supports_x'] === 1 : (int) $st['supports_s'] === 1;
    $stations[] = ['name' => $st['name'], 'angle' => $angleByLabel[$st['latitude_label']] ?? 0, 'contact' => $contact > 0 && $hears, 'minutes' => $contact];
}
$replay = [
    'L' => $L, 'endMonth' => (int) $endMonth, 'lifetimeText' => $run['summary']['lifetime_text'],
    'months' => array_map(fn($r) => ['m' => (int) $r['month_no'], 'gen' => (float) $r['generation_wh'], 'dem' => (float) $r['demand_wh'],
        'made' => (float) $r['generated_mb'], 'sent' => (float) $r['sent_mb'], 'backlog' => (float) $r['backlog_mb'], 'lost' => (float) $r['lost_mb']], $run['months']),
    'events' => array_map(fn($e) => ['seq' => (int) $e['seq'], 'month' => (int) $e['month_no'], 'severity' => $e['severity'], 'message' => $e['message']], $run['events']),
];

page_start(); ?>
<h1 class="text-2xl font-bold">Mission simulation</h1>
<p class="mt-1 text-sm text-slate-400">Run <?= (int) $run['run_number'] ?> · <?= e(design_summary($snap['input'], catalogue())) ?></p>
<div class="mt-3"><?= model_label() ?></div>

<section id="replay" class="mt-6 grid gap-6 lg:grid-cols-3" hidden aria-label="Mission replay">
    <div class="space-y-3 lg:col-span-1">
        <div class="rounded-lg border border-slate-700 bg-slate-900 p-3">
            <svg viewBox="0 0 200 200" class="mx-auto w-full max-w-[16rem]" role="img" aria-labelledby="orbit-title">
                <title id="orbit-title">Illustrative orbit view: the satellite circles Earth and flashes when it is in contact with one of your ground stations.</title>
                <circle cx="100" cy="100" r="72" fill="none" class="stroke-slate-600" stroke-dasharray="3 4"/>
                <circle cx="100" cy="100" r="44" class="fill-sky-900 stroke-sky-500"/>
                <path d="M70 92 q12 -14 26 -4 q10 8 22 -2 M76 112 q14 8 30 0" fill="none" class="stroke-emerald-600" stroke-width="3"/>
                <?php foreach ($stations as $s): $rad = deg2rad($s['angle']); $x = 100 + 44 * cos($rad); $y = 100 + 44 * sin($rad); ?>
                    <g data-station-angle="<?= (int) $s['angle'] ?>" data-contact="<?= $s['contact'] ? '1' : '0' ?>">
                        <rect x="<?= number_format($x - 4, 1, '.', '') ?>" y="<?= number_format($y - 4, 1, '.', '') ?>" width="8" height="8"
                              class="<?= $s['contact'] ? 'fill-amber-300' : 'fill-slate-500' ?>"><title><?= e($s['name']) ?></title></rect>
                    </g>
                <?php endforeach; ?>
                <circle id="orbit-flash" cx="100" cy="100" r="58" fill="none" class="stroke-amber-300" stroke-width="3" opacity="0"/>
                <circle id="orbit-sat" cx="100" cy="28" r="5" class="fill-white"/>
            </svg>
            <ul class="mt-2 space-y-1 text-xs text-slate-300">
                <?php foreach ($stations as $s): ?>
                    <li><span aria-hidden="true"><?= $s['contact'] ? '■' : '□' ?></span> <?= e($s['name']) ?>:
                        <?= $s['contact'] ? e(num($s['minutes'], 0)) . ' contact min/day' : 'no contact with this design' ?></li>
                <?php endforeach; ?>
            </ul>
            <p id="orbit-note" class="mt-2 text-xs text-slate-400">Illustrative only: not to scale and not a real ground track.</p>
        </div>
        <div class="rounded-lg border border-slate-700 bg-slate-900 p-3">
            <p class="text-lg font-semibold" id="replay-month">Month 0</p>
            <p class="text-sm text-sky-300" id="replay-phase">Launch</p>
            <p class="mt-1 text-xs text-slate-300" id="replay-row" aria-live="polite"></p>
            <label class="mt-3 block text-xs text-slate-400" for="replay-progress">Jump to month</label>
            <input id="replay-progress" type="range" min="0" max="<?= (int) $endMonth ?>" value="0" class="w-full">
            <div class="mt-3 flex flex-wrap gap-2">
                <button id="btn-play" type="button" aria-pressed="false" class="<?= btn_primary() ?>">Play</button>
                <button id="btn-speed" type="button" class="<?= btn_secondary() ?>">Speed: 1×</button>
                <button id="btn-skip" type="button" class="<?= btn_secondary() ?>">Skip to end</button>
            </div>
            <p id="replay-alert" role="alert" hidden class="mt-3 rounded border border-rose-600 bg-rose-950 p-2 text-sm"></p>
        </div>
    </div>
    <div class="min-w-0 space-y-4 lg:col-span-2">
        <figure class="rounded-lg border border-slate-700 bg-slate-900 p-3">
            <figcaption class="text-sm font-semibold">Data per month (MB):
                <span class="text-sky-300">— made</span> · <span class="text-emerald-300">— sent to the ground</span> · <span class="text-amber-300">- - waiting on board</span></figcaption>
            <svg id="chart-data" viewBox="0 0 600 180" class="mt-2 w-full" role="img" aria-label="Line chart of data made, sent and waiting each month. The full numbers are in the month-by-month table below."></svg>
        </figure>
        <figure class="rounded-lg border border-slate-700 bg-slate-900 p-3">
            <figcaption class="text-sm font-semibold">Energy per day (Wh): <span class="text-emerald-300">— generation</span> · <span class="text-rose-300">- - demand</span>
                <span class="block text-xs font-normal text-slate-400">Generation falls slowly as the solar arrays age. If it drops below demand, the mission ends.</span></figcaption>
            <svg id="chart-energy" viewBox="0 0 600 180" class="mt-2 w-full" role="img" aria-label="Line chart of daily energy generation against demand each month. The full numbers are in the month-by-month table below."></svg>
        </figure>
        <section aria-labelledby="h-livelog">
            <h2 id="h-livelog" class="text-lg font-semibold">Mission control log</h2>
            <ol id="event-log" class="mt-2 max-h-72 space-y-2 overflow-auto pr-1 text-sm">
                <?php foreach ($run['events'] as $ev): ?>
                    <li data-month="<?= (int) $ev['month_no'] ?>" class="rounded border border-slate-700 bg-slate-950 p-2">
                        <div class="flex flex-wrap items-center gap-2"><?= status_badge($sevKind[$ev['severity']], 'Month ' . (int) $ev['month_no']) ?>
                            <span class="text-xs text-slate-400"><?= e(SEGMENTS[$ev['segment']] ?? 'All') ?> segment</span></div>
                        <p class="mt-1"><?= e($ev['message']) ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
        <p id="replay-end" hidden class="rounded border border-sky-700 bg-sky-950/40 p-3 text-sm"><?= e($run['summary']['lifetime_text']) ?>
            <a class="ml-2 font-semibold text-sky-300 underline" href="/results.php?run=<?= (int) $run['id'] ?>">See the results</a></p>
    </div>
</section>

<details data-replay-fallback open class="mt-6 rounded-lg border border-slate-800 p-3">
    <summary class="cursor-pointer font-semibold">Full month-by-month table and event list</summary>
    <noscript><h2 class="mt-3 text-lg font-semibold">Mission control log</h2>
        <ol class="mt-2 space-y-2 text-sm">
        <?php foreach ($run['events'] as $ev): ?>
            <li class="rounded border border-slate-700 bg-slate-900 p-2"><?= status_badge($sevKind[$ev['severity']], 'Month ' . (int) $ev['month_no']) ?> <?= e($ev['message']) ?></li>
        <?php endforeach; ?>
        </ol></noscript>
    <?php if (!$run['months']): ?>
        <p class="mt-2 text-sm">The spacecraft never completed a month of operations.</p>
    <?php else: ?>
    <div class="mt-3 max-h-[34rem] overflow-auto rounded border border-slate-800">
    <table class="w-full min-w-[620px] text-right text-sm">
        <thead class="sticky top-0 bg-slate-900 text-slate-400"><tr>
            <th class="px-2 py-2 text-left">Month</th><th class="px-2 text-left">Phase</th><th class="px-2">Generation (Wh/day)</th><th class="px-2">Demand (Wh/day)</th>
            <th class="px-2">Made (MB)</th><th class="px-2">Sent (MB)</th><th class="px-2">Waiting (MB)</th><th class="px-2">Lost (MB)</th></tr></thead>
        <tbody>
        <?php foreach ($run['months'] as $row): $lost = (float) $row['lost_mb'] > 0; ?>
            <tr class="border-t border-slate-800 <?= $lost ? 'bg-rose-950/40' : '' ?>">
                <td class="px-2 py-1 text-left"><?= (int) $row['month_no'] ?></td>
                <td class="px-2 text-left"><?= $row['phase'] === 'COMMISSIONING' ? 'Commissioning' : 'Science' ?></td>
                <td class="px-2"><?= e(num($row['generation_wh'])) ?></td><td class="px-2"><?= e(num($row['demand_wh'])) ?></td>
                <td class="px-2"><?= e(num($row['generated_mb'], 0)) ?></td><td class="px-2"><?= e(num($row['sent_mb'], 0)) ?></td>
                <td class="px-2"><?= e(num($row['backlog_mb'], 0)) ?></td><td class="whitespace-nowrap px-2"><?= $lost ? '<span aria-hidden="true">✕ </span>' : '' ?><?= e(num($row['lost_mb'], 0)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
    <p class="mt-3 text-sm"><?= e($run['summary']['lifetime_text']) ?></p>
</details>

<div class="mt-6"><a class="<?= btn_primary() ?>" href="/results.php?run=<?= (int) $run['id'] ?>">See the results</a></div>
<script type="application/json" id="replay-data"><?= engine_js_json($replay) ?></script>
<script src="/assets/js/replay.js?v=2" defer></script>
<?php page_end('Simulation', 7, segment_status(['warnings' => $run['summary']['warnings'], 'hard' => []], $m), $m);
