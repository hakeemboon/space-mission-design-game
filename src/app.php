<?php
/**
 * TerraWatch-1 — loads everything a page needs, plus page/UI helpers (module M8).
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 */
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/services/catalogue.php';
require __DIR__ . '/services/engine.php';
require __DIR__ . '/services/runs.php';
require __DIR__ . '/services/missions.php';

const SEGMENTS = ['LAUNCH' => 'Launch', 'SPACE' => 'Space', 'GROUND' => 'Ground', 'USER' => 'User'];

function page_start(): void
{
    ob_start();
}

/** Close the page buffer and render it inside the layout. */
function page_end(string $title, ?int $step = null, ?array $segments = null, ?array $mission = null): void
{
    $content = ob_get_clean();
    $missionHeader = null;
    if ($mission) {
        $cat = catalogue();
        $missionHeader = [
            'nickname'   => $mission['nickname'],
            'objective'  => $cat['objectives'][$mission['objective']]['name'] ?? '',
            'difficulty' => $cat['difficulties'][$mission['difficulty']]['name'] ?? '',
            'budget'     => $cat['difficulties'][$mission['difficulty']]['budget_mu'] ?? 0,
            'cost'       => $mission['_cost'] ?? null,
        ];
    }
    require APP_ROOT . '/src/pages/_layout.php';
}

/** Flash message shown once on the next page. */
function flash(?string $msg = null): ?string
{
    if ($msg !== null) {
        $_SESSION['flash'] = $msg;
        return null;
    }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

/** Segment rail status from an engine result and the design's completeness. */
function segment_status(?array $result, ?array $mission): array
{
    $status = array_fill_keys(array_keys(SEGMENTS), 'todo');
    if ($mission) {
        if ($mission['launch'] || $mission['orbit']) {
            $status['LAUNCH'] = 'ok';
        }
        if ($mission['bus'] || $mission['instruments'] || $mission['power'] || $mission['comms']) {
            $status['SPACE'] = 'ok';
        }
        if ($mission['stations']) {
            $status['GROUND'] = 'ok';
        }
        if ($mission['objective']) {
            $status['USER'] = 'ok';
        }
    }
    if ($result) {
        foreach ($result['warnings'] ?? [] as $w) {
            if (isset($status[$w['segment']]) && $status[$w['segment']] !== 'fail') {
                $status[$w['segment']] = 'warn';
            }
        }
        foreach ($result['hard'] ?? [] as $h) {
            if (isset($status[$h['segment']])) {
                $status[$h['segment']] = 'fail';
            }
        }
    }
    return $status;
}

/** Badge for a status, never colour alone (icon + word). */
function status_badge(string $kind, string $label): string
{
    $map = [
        'ok'   => ['✓', 'bg-emerald-900/60 text-emerald-200 border-emerald-600'],
        'warn' => ['!', 'bg-amber-900/60 text-amber-200 border-amber-500'],
        'fail' => ['✕', 'bg-rose-900/60 text-rose-200 border-rose-500'],
        'info' => ['i', 'bg-sky-900/60 text-sky-200 border-sky-600'],
    ];
    [$icon, $cls] = $map[$kind] ?? $map['info'];
    return '<span class="inline-flex items-center gap-1 rounded border px-2 py-0.5 text-xs font-semibold ' . $cls . '">'
        . '<span aria-hidden="true">' . $icon . '</span>' . e($label) . '</span>';
}

/** Six resource meters from engine calculations. */
function meters(array $c): array
{
    return [
        ['Mass', $c['total_mass_kg'], $c['launch_capacity_kg'], 'kg', 'Total mass against launch capacity', 'LAUNCH'],
        ['Peak power', $c['peak_load_w'], $c['peak_capacity_w'], 'W', 'Everything switched on at once against the bus limit', 'SPACE'],
        ['Energy per day', $c['daily_demand_wh'], $c['daily_generation_wh'], 'Wh', 'Daily demand against daily generation', 'SPACE'],
        ['Battery', $c['eclipse_need_wh'], $c['usable_battery_wh'], 'Wh', 'Energy needed through eclipse against usable battery', 'SPACE'],
        ['Data per day', $c['data_generated_mb_day'], $c['downlink_mb_day'], 'MB', 'Data made against data that can be sent down', 'GROUND'],
        ['Budget', $c['total_cost_mu'], $c['budget_mu'], 'MU', 'Total cost against budget (fictional game currency)', 'ALL'],
    ];
}

function meter_html(array $m, ?float $required = null): string
{
    [$label, $used, $limit, $unit, $help] = $m;
    if ($limit === null) {
        return '<div class="rounded border border-slate-700 p-3"><div class="flex justify-between text-sm"><span class="font-semibold">'
            . e($label) . '</span>' . status_badge('fail', 'No limit') . '</div><p class="mt-1 text-xs text-slate-400">This launcher does not serve the chosen orbit.</p></div>';
    }
    $over = r2((float) $used) > r2((float) $limit);
    $pct = $limit > 0 ? min(100, max(0, $used / $limit * 100)) : 100;
    $tight = !$over && $limit > 0 && ($limit - $used) / $limit < 0.10;
    $kind = $over ? 'fail' : ($tight ? 'warn' : 'ok');
    $word = $over ? 'Over' : ($tight ? 'Tight' : 'OK');
    if ($label === 'Data per day') { // more data than downlink is a warning, not a failure
        $kind = $over ? 'warn' : 'ok';
        $word = $over ? 'Backlog' : 'OK';
    }
    $bar = ['ok' => 'bg-emerald-500', 'warn' => 'bg-amber-400', 'fail' => 'bg-rose-500'][$kind];
    $html = '<div class="rounded border border-slate-700 bg-slate-900 p-3">'
        . '<div class="flex items-center justify-between gap-2 text-sm"><span class="font-semibold">' . e($label) . '</span>' . status_badge($kind, $word) . '</div>'
        . '<div class="mt-2 h-2 rounded bg-slate-700" aria-hidden="true"><div class="h-2 rounded ' . $bar . '" style="width:' . number_format($pct, 1, '.', '') . '%"></div></div>'
        . '<p class="mt-1 text-xs text-slate-300">' . e(num($used)) . ' / ' . e(num($limit)) . ' ' . e($unit)
        . ($over ? ' · short by ' . e(num($used - $limit)) : ' · margin ' . e(num($limit - $used))) . ' ' . e($unit) . '</p>';
    if ($required !== null) {
        $html .= '<p class="text-xs text-slate-400">Users need ' . e(num($required, 0)) . ' MB/day delivered.</p>';
    }
    return $html . '<p class="sr-only">' . e($help) . '</p></div>';
}

/** A fact card with its source link. */
function fact_card_html(array $f): string
{
    return '<aside class="rounded border border-sky-800 bg-sky-950/50 p-3 text-sm">'
        . '<p class="text-xs font-semibold uppercase tracking-wide text-sky-300">Fact card ' . e($f['code']) . ' · NASA source</p>'
        . '<p class="mt-1">' . e($f['statement']) . '</p>'
        . '<p class="mt-1 text-xs text-slate-400">Source: <a class="underline hover:text-white" target="_blank" rel="noopener" href="' . e($f['source_url']) . '">'
        . e($f['source_title']) . '</a> (' . e($f['source_publisher']) . ($f['source_detail'] ? ', ' . e($f['source_detail']) : '') . ')</p></aside>';
}

function btn_primary(): string
{
    return 'inline-flex min-h-11 items-center justify-center rounded bg-sky-600 px-4 py-2 font-semibold text-white hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-300 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-400';
}

function btn_secondary(): string
{
    return 'inline-flex min-h-11 items-center justify-center rounded border border-slate-500 px-4 py-2 text-slate-100 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-300';
}

/** "Simplified educational model" label required on designer, simulation and results. */
function model_label(): string
{
    return '<p class="rounded border border-slate-700 bg-slate-900 px-3 py-2 text-xs text-slate-300">'
        . '<strong>Simplified educational model.</strong> Values are fictional game values unless shown as a NASA fact card. '
        . 'Mission Units (MU) are fictional game currency. Not a substitute for professional tools such as NASA\'s GMAT.</p>';
}

/** Require an active mission or send the player back to the start. */
function require_mission(): array
{
    $m = current_mission();
    if (!$m) {
        flash('Start a mission first.');
        redirect('/');
    }
    return $m;
}

/** Catalogue and rule text for the browser mirror (display only). Same values the server uses. */
function engine_js_data(): array
{
    $cat = catalogue();
    $rulesForJs = [];
    foreach (rules()['rules'] as $code => $r) {
        if (in_array($r['rule_type'], ['HARD', 'SOFT'], true)) {
            $rulesForJs[$code] = ['segment' => $r['segment'], 'message' => $r['message_template'], 'feedback' => $r['feedback_text']];
        }
    }
    return [
        'params' => rules()['params'], 'rules' => $rulesForJs,
        'cat' => [
            'components' => $cat['components'],
            'byCategory' => array_map(fn($rows) => array_fill_keys(array_keys($rows), true), $cat['byCategory']),
            'orbits' => $cat['orbits'], 'launches' => $cat['launches'], 'stations' => $cat['stations'],
            'objectives' => $cat['objectives'], 'difficulties' => $cat['difficulties'], 'matrix' => $cat['matrix'],
        ],
    ];
}

/** JSON safe to place inside a <script type="application/json"> element. */
function engine_js_json(array $data): string
{
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
}

/** Short design description for tables. */
function design_summary(array $input, array $cat): string
{
    $name = fn($code, $pool) => $code && isset($pool[$code]) ? $pool[$code]['name'] : '—';
    $parts = [
        $name($input['bus'], $cat['components']),
        implode(' + ', array_map(fn($c) => $cat['components'][$c]['name'], $input['instruments'])),
        $name($input['orbit'], $cat['orbits']),
        implode(' + ', array_map(fn($c) => $cat['stations'][$c]['name'], $input['stations'])),
    ];
    return implode(' · ', array_filter($parts));
}
