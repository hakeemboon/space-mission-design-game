<?php
/**
 * TerraWatch-1 — screen 5: Mission designer (component selection, resource dashboard,
 * constraint validation). All numbers come from the server engine.
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/src/app.php';

$m = require_mission();
if ($m['status'] === 'FLOWN') {
    $last = q('SELECT MAX(id) FROM simulation_runs WHERE mission_id = ?', [$m['id']])->fetchColumn();
    flash('This design has flown and is now fixed. Use "Redesign this mission" to make a changed copy.');
    redirect($last ? '/debrief.php?run=' . (int) $last : '/');
}
$cat = catalogue();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'starter' && $m['difficulty'] === 'DIFF-B') {
        // Beginner starter design: Document 04 worked example B after its fix (reference D4).
        mission_save_design((int) $m['id'], ['bus' => 'BUS-S', 'instruments' => ['INS-SWIR'], 'power' => 'PWR-P1',
            'comms' => 'COM-C1', 'stations' => ['GS-G1'], 'orbit' => 'ORB-O1', 'launch' => 'LV-L1', 'imaging' => '10', 'addons' => []]);
        flash('Starter design loaded. It flies, but it is weak: can you find out why and improve it?');
        redirect('/designer.php');
    }
    mission_save_design((int) $m['id'], $_POST);
    if ($action === 'autosave') { // background draft save from designer.js
        http_response_code(204);
        exit;
    }
    if ($action === 'review') {
        redirect('/preflight.php');
    }
    $seg = preg_replace('/[^a-z]/', '', (string) ($_POST['segment'] ?? ''));
    redirect('/designer.php' . ($seg ? '#seg-' . $seg : ''));
}

$result = engine_run(mission_input($m), $cat, rules());
$c = $result['calc'];
if ($c) {
    $m['_cost'] = $c['total_cost_mu'];
}
$beginner = $m['difficulty'] === 'DIFF-B';
$challenge = $m['difficulty'] === 'DIFF-C';
$objCode = $m['objective'];
$obj = $cat['objectives'][$objCode];
$matrix = $cat['matrix'];
$busRow = $m['bus'] ? $cat['components'][$m['bus']] : null;
$comRow = $m['comms'] ? $cat['components'][$m['comms']] : null;

/** Reasons an option cannot work with the current design (empty when fine). */
function option_problem(string $kind, string $code, array $m, array $cat): ?string
{
    $mx = $cat['matrix'];
    switch ($kind) {
        case 'launch':
            if ($m['orbit'] && !isset($mx['LAUNCH_CAPACITY'][$code][$m['orbit']])) {
                return 'Does not fly to ' . $cat['orbits'][$m['orbit']]['name'];
            }
            return null;
        case 'orbit':
            if ($m['launch'] && !isset($mx['LAUNCH_CAPACITY'][$m['launch']][$code])) {
                return 'Your launcher does not fly here';
            }
            return null;
        case 'power':
            foreach ($mx['CONFLICT'] ?? [] as $a => $bs) {
                foreach ($bs as $b => $_) {
                    if (($a === $code && $b === $m['bus']) || ($b === $code && $a === $m['bus'])) {
                        return 'Does not fit the ' . $cat['components'][$m['bus']]['name'];
                    }
                }
            }
            return null;
        case 'bus':
            if ($m['power']) {
                foreach ($mx['CONFLICT'] ?? [] as $a => $bs) {
                    foreach ($bs as $b => $_) {
                        if (($a === $m['power'] && $b === $code) || ($b === $m['power'] && $a === $code)) {
                            return 'Your power system does not fit this bus';
                        }
                    }
                }
            }
            return null;
        case 'station':
            if ($m['orbit'] && ($mx['GROUND_CONTACT'][$code][$m['orbit']] ?? 0) == 0) {
                return 'Never sees this orbit';
            }
            if ($m['comms']) {
                $band = $cat['components'][$m['comms']]['band'];
                $st = $cat['stations'][$code];
                if (($band === 'X' && !(int) $st['supports_x']) || ($band === 'S' && !(int) $st['supports_s'])) {
                    return 'Cannot hear your ' . $band . '-band radio';
                }
            }
            return null;
    }
    return null;
}

/** One selectable card. */
function card(string $type, string $name, string $value, bool $checked, string $title, array $facts, ?string $problem, bool $disable, string $desc = '', string $kind = ''): string
{
    $id = $name . '-' . preg_replace('/[^A-Za-z0-9]/', '', $value);
    $dis = ($disable && !$checked) ? ' disabled' : '';
    $cls = $dis ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:border-sky-500';
    $data = $kind !== '' ? ' data-kind="' . e($kind) . '" data-title="' . e($title) . '"' : '';
    $html = '<label for="' . e($id) . '"' . $data . ' class="flex flex-col rounded-lg border-2 border-slate-700 bg-slate-900 p-3 ' . $cls
        . ' has-[:checked]:border-sky-400 has-[:checked]:bg-sky-950/40">'
        . '<span class="flex items-start gap-2"><input id="' . e($id) . '" type="' . $type . '" name="' . e($name) . '" value="' . e($value) . '"'
        . ($checked ? ' checked' : '') . $dis . ' aria-label="' . e($title . ($problem ? ' (' . $problem . ')' : '')) . '" class="mt-1"><span class="font-semibold">' . e($title) . '</span></span>';
    if ($desc !== '') {
        $html .= '<span class="mt-1 text-xs text-slate-400">' . e($desc) . '</span>';
    }
    $html .= '<span class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-300">';
    foreach ($facts as $f) {
        $html .= '<span>' . e($f) . '</span>';
    }
    $html .= '</span>';
    if ($kind !== '' || $problem) {
        $html .= '<span class="tw-problem mt-2">' . ($problem ? status_badge('warn', $problem) : '') . '</span>';
    }
    return $html . '</label>';
}

$sel = fn(string $field, string $code) => in_array($code, (array) $m[$field], true) || $m[$field] === $code;
$grouped = ['LAUNCH' => [], 'SPACE' => [], 'GROUND' => [], 'USER' => [], 'ALL' => []];
foreach ($result['hard'] as $h) { $grouped[$h['segment']][] = ['fail', $h]; }
foreach ($result['warnings'] as $w) { $grouped[$w['segment']][] = ['warn', $w]; }
$segAnchor = ['LAUNCH' => 'launch', 'SPACE' => 'space', 'GROUND' => 'ground', 'USER' => 'user', 'ALL' => 'space'];

page_start(); ?>
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold">Mission designer</h1>
        <p class="text-sm text-slate-400">Choose in any order: the dashboard updates as you click. When there are no red failures, press <strong>Review for launch</strong>.</p>
    </div>
    <a class="text-sm text-sky-300 underline" href="/requirements.php">View requirements R1–R6</a>
</div>
<div class="mt-3"><?= model_label() ?></div>
<?php if ($beginner): ?>
    <form method="post" class="mt-3"><?= csrf_field() ?><input type="hidden" name="action" value="starter">
        <button class="<?= btn_secondary() ?> text-sm" type="submit">Load the starter design (a weak design to improve)</button></form>
<?php endif; ?>

<div class="mt-4 grid gap-6 lg:grid-cols-3">
<form method="post" id="designer" class="space-y-8 lg:col-span-2">
    <?= csrf_field() ?>
    <?php // Phone/tablet: compact sticky summary (worst meter + issue count) linking to the full dashboard.
    $worst = null;
    if ($c) {
        foreach (meters($c) as $mt) {
            if ($mt[2] === null) { $worst = [$mt[0], INF]; break; }
            $ratio = $mt[2] > 0 ? $mt[1] / $mt[2] : INF;
            if ($worst === null || $ratio > $worst[1]) { $worst = [$mt[0], $ratio]; }
        }
    }
    $nHard = count($result['hard']); $nWarn = count($result['warnings']); ?>
    <a href="#h-dash" class="sticky top-0 z-10 flex flex-wrap items-center gap-2 rounded border border-slate-700 bg-slate-900 px-3 py-2 text-sm shadow-lg shadow-black/60 lg:hidden">
        <span id="dash-summary-text" class="flex flex-wrap items-center gap-2">
        <?= $nHard ? status_badge('fail', $nHard . ' failure' . ($nHard === 1 ? '' : 's')) : status_badge('ok', 'No failures') ?>
        <?= $nWarn ? status_badge('warn', $nWarn . ' warning' . ($nWarn === 1 ? '' : 's')) : '' ?>
        <?php if ($worst): ?><span class="text-slate-300">Tightest: <?= e($worst[0]) ?> <?= is_finite($worst[1]) ? e(number_format($worst[1] * 100, 0)) . '% used' : '' ?></span><?php endif; ?>
        </span>
        <span class="ml-auto text-sky-300 underline">Dashboard</span>
    </a>
    <p id="dash-live-region" class="sr-only" aria-live="polite"></p>

    <section id="seg-launch" aria-labelledby="h-launch">
        <h2 id="h-launch" class="text-xl font-semibold">1 · Launch segment</h2>
        <p class="text-sm text-slate-400">The launcher and the orbit limit each other: a launcher only serves some orbits, with a mass limit for each.</p>
        <h3 class="mt-3 font-semibold">Launch profile</h3>
        <div class="mt-2 grid gap-3 sm:grid-cols-3">
        <?php foreach ($cat['launches'] as $code => $l):
            $caps = [];
            foreach ($cat['orbits'] as $oc => $o) {
                $caps[] = $oc . ': ' . (isset($matrix['LAUNCH_CAPACITY'][$code][$oc]) ? num($matrix['LAUNCH_CAPACITY'][$code][$oc], 0) . ' kg' : '—');
            }
            $prob = option_problem('launch', $code, $m, $cat);
            echo card('radio', 'launch', $code, $sel('launch', $code), $l['name'], array_merge([num($l['cost_mu'], 0) . ' MU'], $caps), $prob, $beginner && $prob !== null, $l['description'], 'launch');
        endforeach; ?>
        </div>
        <h3 class="mt-4 font-semibold">Orbit</h3>
        <div class="mt-2 grid gap-3 sm:grid-cols-3">
        <?php foreach ($cat['orbits'] as $code => $o):
            $prob = option_problem('orbit', $code, $m, $cat);
            echo card('radio', 'orbit', $code, $sel('orbit', $code), $code . ' · ' . $o['name'], [
                num($o['altitude_km'], 0) . ' km', num($o['sunlight_h_per_day']) . ' h sunlight/day', num($o['eclipse_min']) . ' min eclipse/orbit',
                'Fit for objective: ' . number_format($matrix['ORBIT_SUIT'][$code][$objCode], 2),
                $o['needs_disposal_aid'] ? 'Needs deorbit kit' : 'Decays in ' . $o['decay_months'] . ' months'], $prob, $beginner && $prob !== null, $o['description'], 'orbit');
        endforeach; ?>
        </div>
        <div class="mt-3 grid gap-3 md:grid-cols-2"><?php foreach (fact_cards('designer-orbit') as $i => $f) { if ($i < 2) echo fact_card_html($f); } ?></div>
    </section>

    <section id="seg-space" aria-labelledby="h-space">
        <h2 id="h-space" class="text-xl font-semibold">2 · Space segment</h2>
        <h3 class="mt-3 font-semibold">Spacecraft bus</h3>
        <div class="mt-2 grid gap-3 sm:grid-cols-3">
        <?php foreach ($cat['byCategory']['BUS'] as $code => $b):
            $prob = option_problem('bus', $code, $m, $cat);
            echo card('radio', 'bus', $code, $sel('bus', $code), $b['name'], [
                num($b['mass_kg'], 0) . ' kg', $b['instrument_slots'] . ' instrument slot(s)', 'payload ≤ ' . num($b['payload_limit_kg'], 0) . ' kg',
                'peak ' . num($b['peak_capacity_w'], 0) . ' W', 'uses ' . num($b['power_w'], 0) . ' W', num($b['storage_gb'], 0) . ' GB',
                $b['design_life_months'] . '-month life', num($b['cost_mu'], 0) . ' MU'], $prob, $beginner && $prob !== null, $b['description'], 'bus');
        endforeach; ?>
        </div>
        <h3 class="mt-4 font-semibold">Instruments <span id="slot-count" class="text-sm font-normal text-slate-400">(<?= count($m['instruments']) ?> of <?= $busRow ? (int) $busRow['instrument_slots'] : '—' ?> slots used)</span></h3>
        <div class="mt-2 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($cat['byCategory']['INSTRUMENT'] as $code => $i):
            echo card('checkbox', 'instruments[]', $code, $sel('instruments', $code), $i['name'], [
                num($i['mass_kg'], 0) . ' kg', num($i['power_w'], 0) . ' W', num($i['data_rate_mbps']) . ' Mbps',
                'Fit for objective: ' . number_format($matrix['INSTRUMENT_SUIT'][$code][$objCode], 2), num($i['cost_mu'], 0) . ' MU'], null, false, $i['description']);
        endforeach; ?>
        </div>
        <fieldset class="mt-4">
            <legend class="font-semibold">Imaging time per orbit</legend>
            <div class="mt-2 flex flex-wrap gap-2">
            <?php foreach (explode(',', (string) rules()['params']['IMAGING_OPTIONS']) as $opt): ?>
                <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded border-2 border-slate-700 px-4 has-[:checked]:border-sky-400 has-[:checked]:bg-sky-950/40">
                    <input type="radio" name="imaging" value="<?= (int) $opt ?>" <?= $m['imaging'] === (int) $opt ? 'checked' : '' ?>> <?= (int) $opt ?> minutes</label>
            <?php endforeach; ?>
            </div>
            <p class="mt-1 text-xs text-slate-400">Longer imaging makes more data and uses more energy.</p>
        </fieldset>
        <h3 class="mt-4 font-semibold">Power system</h3>
        <div class="mt-2 grid gap-3 sm:grid-cols-3">
        <?php foreach ($cat['byCategory']['POWER'] as $code => $p):
            $prob = option_problem('power', $code, $m, $cat);
            echo card('radio', 'power', $code, $sel('power', $code), $p['name'], [
                num($p['mass_kg'], 0) . ' kg', num($p['generation_w'], 0) . ' W in sunlight', num($p['battery_wh'], 0) . ' Wh battery', num($p['cost_mu'], 0) . ' MU'],
                $prob, $beginner && $prob !== null, $p['description'], 'power');
        endforeach; ?>
        </div>
        <h3 class="mt-4 font-semibold">Radio</h3>
        <div class="mt-2 grid gap-3 sm:grid-cols-3">
        <?php foreach ($cat['byCategory']['COMMS'] as $code => $r):
            echo card('radio', 'comms', $code, $sel('comms', $code), $r['name'], [
                num($r['mass_kg']) . ' kg', num($r['power_w'], 0) . ' W', num($r['data_rate_mbps'], 0) . ' Mbps', $r['band'] . '-band', num($r['cost_mu'], 0) . ' MU'], null, false, $r['description']);
        endforeach; ?>
        </div>
        <h3 class="mt-4 font-semibold">Add-ons <span class="text-sm font-normal text-slate-400">(optional)</span></h3>
        <div class="mt-2 grid gap-3 sm:grid-cols-2">
        <?php foreach ($cat['byCategory']['ADDON'] as $code => $a):
            echo card('checkbox', 'addons[]', $code, $sel('addons', $code), $a['name'], [
                num($a['mass_kg'], 0) . ' kg', num($a['power_w'], 0) . ' W', num($a['cost_mu'], 0) . ' MU'], null, false, $a['description']);
        endforeach; ?>
        </div>
    </section>

    <section id="seg-ground" aria-labelledby="h-ground">
        <h2 id="h-ground" class="text-xl font-semibold">3 · Ground segment</h2>
        <p class="text-sm text-slate-400">Pick one or two stations. Contact minutes add up, but only for stations that support your radio's band.</p>
        <div class="mt-2 grid gap-3 sm:grid-cols-2">
        <?php foreach ($cat['stations'] as $code => $g):
            $mins = $m['orbit'] ? num($matrix['GROUND_CONTACT'][$code][$m['orbit']], 0) . ' contact min/day on your orbit' : 'Pick an orbit to see contact minutes';
            $bands = ((int) $g['supports_s'] ? 'S' : '') . ((int) $g['supports_s'] && (int) $g['supports_x'] ? ' + ' : '') . ((int) $g['supports_x'] ? 'X' : '') . '-band';
            $prob = option_problem('station', $code, $m, $cat);
            echo card('checkbox', 'stations[]', $code, $sel('stations', $code), $g['name'], [$g['latitude_label'], $bands, $mins, num($g['cost_mu'], 0) . ' MU'],
                $prob, $beginner && $prob !== null, '', 'station');
        endforeach; ?>
        </div>
        <p class="mt-2 text-xs text-slate-400">Station names are fictional.</p>
    </section>

    <section id="seg-user" aria-labelledby="h-user">
        <h2 id="h-user" class="text-xl font-semibold">4 · User segment</h2>
        <div class="mt-2 rounded-lg border border-slate-700 bg-slate-900 p-4 text-sm">
            <p><span class="text-slate-400">Who needs the data:</span> <?= e($obj['user_personas']) ?></p>
            <p class="mt-1"><span class="text-slate-400">Their question:</span> <?= e($obj['user_question']) ?></p>
            <p class="mt-1"><span class="text-slate-400">They need:</span> <?= e(num($obj['required_mb_per_day'], 0)) ?> MB delivered per day</p>
            <ul id="user-reqs" class="mt-3 space-y-1">
            <?php if ($c): ?>
                    <?php $best = $result['prediction']['instrument_factor']; $orbitF = $result['prediction']['orbit_factor'];
                    $deliv = min($c['data_generated_mb_day'], $c['downlink_mb_day']); ?>
                    <li><?= status_badge($best >= 0.8 ? 'ok' : 'warn', 'R1') ?> Instrument fit <?= e(number_format($best, 2)) ?> (needs 0.80)</li>
                    <li><?= status_badge($orbitF >= 0.8 ? 'ok' : 'warn', 'R2') ?> Orbit fit <?= e(number_format($orbitF, 2)) ?> (needs 0.80)</li>
                    <li><?= status_badge($deliv >= $obj['required_mb_per_day'] ? 'ok' : 'warn', 'R3') ?> Up to <?= e(num($deliv, 0)) ?> MB/day can reach users (needs <?= e(num($obj['required_mb_per_day'], 0)) ?>)</li>
            <?php endif; ?>
            </ul>
        </div>
    </section>

    <div class="sticky bottom-0 flex flex-wrap gap-3 border-t border-slate-800 bg-slate-950 py-3 shadow-[0_-8px_16px_rgba(0,0,0,0.6)]">
        <button class="<?= btn_secondary() ?>" type="submit" name="action" value="save">Update dashboard</button>
        <button class="<?= btn_primary() ?>" type="submit" name="action" value="review">Review for launch</button>
        <span id="save-state" class="self-center text-xs text-slate-400" aria-live="polite"></span>
    </div>
</form>

<aside class="space-y-3 lg:sticky lg:top-4 lg:self-start" aria-labelledby="h-dash">
    <h2 id="h-dash" class="text-lg font-semibold">Resource dashboard</h2>
    <div id="dash-live" class="space-y-3">
    <?php if ($c): ?>
        <?php foreach (meters($c) as $mt) { echo meter_html($mt, $mt[0] === 'Data per day' ? $c['required_mb_day'] : null); } ?>
        <div class="rounded border border-slate-700 bg-slate-900 p-3 text-sm">
            <p><span class="text-slate-400">Predicted operational lifetime:</span> <?= (int) $result['prediction']['lifetime_months'] ?> of <?= (int) $c['target_months'] ?> months</p>
            <p><span class="text-slate-400">Development time:</span> <?= (int) $c['development_months'] ?> months (not scored)</p>
            <?php if (!$challenge):
                $band = !$result['flight_ready'] ? ['fail', 'Not flight-ready'] : ($result['outcome'] === 'SUCCESS' ? ['ok', 'On track'] : ['warn', 'At risk']); ?>
                <p class="mt-1"><span class="text-slate-400">Predicted science:</span> <?= status_badge($band[0], $band[1]) ?></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="rounded border border-slate-700 bg-slate-900 p-3 text-sm text-slate-300">The meters appear once every required part is chosen.</p>
    <?php endif; ?>
    </div>

    <h2 class="pt-2 text-lg font-semibold">Constraint checks</h2>
    <ul id="dash-checks" class="space-y-2 text-sm">
    <?php if (!$result['hard'] && !$result['warnings']): ?>
        <li><?= status_badge('ok', 'All clear') ?> No failures or warnings.</li>
    <?php endif; ?>
    <?php foreach (['LAUNCH', 'SPACE', 'GROUND', 'USER', 'ALL'] as $sg): foreach ($grouped[$sg] as [$kind, $item]): ?>
        <li class="rounded border <?= $kind === 'fail' ? 'border-rose-700' : 'border-amber-700' ?> bg-slate-900 p-2">
            <div class="flex items-start gap-2"><?= status_badge($kind, $item['code']) ?>
                <a class="hover:underline" href="#seg-<?= $segAnchor[$sg] ?>"><?= e($item['message']) ?></a></div>
            <?php if ($beginner || $kind === 'fail'): ?>
                <p class="mt-1 text-xs text-sky-200">Fix: <?= e($item['feedback']) ?></p>
            <?php elseif (!$challenge): ?>
                <details class="mt-1 text-xs text-sky-200"><summary class="cursor-pointer">Show a hint</summary><?= e($item['feedback']) ?></details>
            <?php endif; ?>
        </li>
    <?php endforeach; endforeach; ?>
    </ul>
</aside>
</div>
<?php
$twData = engine_js_data() + ['objective' => $m['objective'], 'difficulty' => $m['difficulty'], 'beginner' => $beginner, 'challenge' => $challenge];
?>
<script type="application/json" id="tw-data"><?= engine_js_json($twData) ?></script>
<script src="/assets/js/engine.js?v=1" defer></script>
<script src="/assets/js/designer.js?v=4" defer></script>
<?php page_end('Designer', 5, segment_status($result, $m), $m);
