<?php
/**
 * TerraWatch-1 — validation and simulation engine (modules M5 and M6).
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 *
 * SIMPLIFIED EDUCATIONAL MODEL. Implements Document 04 as amended by the Technical
 * Correction Addendum. Not professional mission analysis; not a substitute for GMAT.
 *
 * Design:
 *   1. engine_normalize()  — stage 1 structure check (rule H01) on raw input codes.
 *   2. engine_snapshot()   — copies every catalogue row, matrix entry and rule the
 *                            engine will read into one array (stored with each run).
 *   3. engine_evaluate()   — a pure function of the snapshot: calculations C01–C18,
 *                            hard rules, warnings, lifetime, monthly loop, events,
 *                            requirements, scores, feedback and the user panel.
 * No randomness, no clock, no database access inside engine_evaluate(), so identical
 * snapshots always give identical results. Orbit sunlight/eclipse/period values are read
 * from seed data and are never recomputed (no eclipse or period equation exists here).
 */
declare(strict_types=1);

// ------------------------------------------------------------------ number helpers

/** Round half up to 2 decimals (all values compared or stored go through this). */
function r2(float $x): float
{
    return round($x, 2, PHP_ROUND_HALF_UP);
}

/** Display a number in messages: integers without decimals, otherwise 2 decimals. */
function fmt(float $x): string
{
    $x = r2($x);
    return abs($x - round($x)) < 0.000001 ? number_format($x, 0, '.', ',') : number_format($x, 2, '.', ',');
}

// ------------------------------------------------------------------ stage 1: structure (H01)

/**
 * Validate the raw design against the catalogue. Returns [normalizedInput|null, problems[]].
 * Input keys: objective, difficulty, bus, instruments[], power, comms, stations[], orbit,
 * launch, imaging, addons[] — all catalogue codes except imaging (minutes).
 */
function engine_normalize(array $in, array $cat, array $rules): array
{
    $problems = [];
    $one = function (string $key, array $pool, string $label) use ($in, &$problems): ?string {
        $v = $in[$key] ?? null;
        if (!is_string($v) || $v === '') {
            $problems[] = "no $label selected";
            return null;
        }
        if (!isset($pool[$v])) {
            $problems[] = "unknown $label";
            return null;
        }
        return $v;
    };
    $many = function (string $key, array $pool, string $label, int $min, int $max) use ($in, &$problems): array {
        $v = $in[$key] ?? [];
        if (!is_array($v)) {
            $problems[] = "invalid $label list";
            return [];
        }
        $v = array_values($v);
        foreach ($v as $code) {
            if (!is_string($code) || !isset($pool[$code])) {
                $problems[] = "unknown $label";
                return [];
            }
        }
        if (count($v) !== count(array_unique($v))) {
            $problems[] = "the same $label was selected twice";
        }
        if (count($v) < $min) {
            $problems[] = "no $label selected";
        }
        if (count($v) > $max) {
            $problems[] = "more than $max {$label}s selected";
        }
        $v = array_values(array_unique($v));
        sort($v, SORT_STRING); // order of selection never changes the result or the hash
        return $v;
    };

    $p = $rules['params'];
    $norm = [
        'objective'   => $one('objective', $cat['objectives'], 'objective'),
        'difficulty'  => $one('difficulty', $cat['difficulties'], 'difficulty'),
        'bus'         => $one('bus', $cat['byCategory']['BUS'], 'bus'),
        'instruments' => $many('instruments', $cat['byCategory']['INSTRUMENT'], 'instrument', 1, (int) $p['MAX_INSTRUMENTS']),
        'power'       => $one('power', $cat['byCategory']['POWER'], 'power system'),
        'comms'       => $one('comms', $cat['byCategory']['COMMS'], 'radio'),
        'stations'    => $many('stations', $cat['stations'], 'ground station', 1, (int) $p['MAX_STATIONS']),
        'orbit'       => $one('orbit', $cat['orbits'], 'orbit'),
        'launch'      => $one('launch', $cat['launches'], 'launcher'),
        'addons'      => $many('addons', $cat['byCategory']['ADDON'], 'add-on', 0, count($cat['byCategory']['ADDON'])),
        'imaging'     => null,
    ];
    $options = array_map('intval', explode(',', (string) $p['IMAGING_OPTIONS']));
    $img = $in['imaging'] ?? null;
    if (is_string($img) && ctype_digit($img)) {
        $img = (int) $img;
    }
    if (!is_int($img) || !in_array($img, $options, true)) {
        $problems[] = 'imaging time must be ' . implode(', ', $options) . ' minutes per orbit';
    } else {
        $norm['imaging'] = $img;
    }
    $problems = array_values(array_unique($problems));
    return [$problems ? null : $norm, $problems];
}

// ------------------------------------------------------------------ snapshot

/** Everything the engine reads, copied from the catalogue for one normalized design. */
function engine_snapshot(array $n, array $cat, array $rules): array
{
    $m = $cat['matrix'];
    $selectedComponents = array_merge([$n['bus'], $n['power'], $n['comms']], $n['instruments'], $n['addons']);
    $components = [];
    foreach ($selectedComponents as $code) {
        $components[$code] = $cat['components'][$code];
    }
    $matrix = ['INSTRUMENT_SUIT' => [], 'ORBIT_SUIT' => [], 'LAUNCH_CAPACITY' => [], 'GROUND_CONTACT' => [], 'CONFLICT' => []];
    foreach ($n['instruments'] as $i) {
        $matrix['INSTRUMENT_SUIT'][$i] = $m['INSTRUMENT_SUIT'][$i][$n['objective']];
    }
    $matrix['ORBIT_SUIT'][$n['orbit']] = $m['ORBIT_SUIT'][$n['orbit']][$n['objective']];
    $matrix['LAUNCH_CAPACITY'] = isset($m['LAUNCH_CAPACITY'][$n['launch']][$n['orbit']])
        ? [$n['launch'] => $m['LAUNCH_CAPACITY'][$n['launch']][$n['orbit']]] : [];
    foreach ($n['stations'] as $s) {
        $matrix['GROUND_CONTACT'][$s] = $m['GROUND_CONTACT'][$s][$n['orbit']] ?? 0.0;
    }
    foreach ($m['CONFLICT'] ?? [] as $a => $bs) {
        foreach ($bs as $b => $v) {
            if (in_array($a, $selectedComponents, true) && in_array($b, $selectedComponents, true)) {
                $matrix['CONFLICT'][] = [$a, $b];
            }
        }
    }
    $stations = [];
    foreach ($n['stations'] as $s) {
        $stations[$s] = $cat['stations'][$s];
    }
    $ruleRows = [];
    foreach ($rules['rules'] as $code => $r) {
        $ruleRows[$code] = [
            'type' => $r['rule_type'], 'segment' => $r['segment'], 'severity' => $r['severity'],
            'value' => $r['param_value'] !== null ? (float) $r['param_value'] : null,
            'message' => $r['message_template'], 'feedback' => $r['feedback_text'],
        ];
    }
    return [
        'input'             => $n,
        'rule_version'      => $rules['version'],
        'catalogue_version' => $rules['catalogue_version'],
        'objective'         => $cat['objectives'][$n['objective']],
        'difficulty'        => $cat['difficulties'][$n['difficulty']],
        'components'        => $components,
        'orbit'             => $cat['orbits'][$n['orbit']],
        'launch'            => $cat['launches'][$n['launch']],
        'stations'          => $stations,
        'matrix'            => $matrix,
        'rules'             => $ruleRows,
    ];
}

/** Canonical JSON (keys sorted recursively) and its SHA-256. */
function canonical_json(array $data): string
{
    $sort = function ($v) use (&$sort) {
        if (is_array($v)) {
            if (array_keys($v) !== range(0, count($v) - 1)) {
                ksort($v, SORT_STRING);
            }
            return array_map($sort, $v);
        }
        return $v;
    };
    return json_encode($sort($data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
}

function snapshot_hash(array $snapshot): string
{
    return hash('sha256', canonical_json($snapshot));
}

// ------------------------------------------------------------------ evaluation (pure)

function engine_evaluate(array $s): array
{
    $n    = $s['input'];
    $R    = $s['rules'];
    $P    = fn(string $code): float => (float) $R[$code]['value'];
    $text = function (string $code, array $vars = [], string $field = 'message') use ($R): string {
        $t = (string) $R[$code][$field];
        foreach ($vars as $k => $v) {
            $t = str_replace('{' . $k . '}', (string) $v, $t);
        }
        return $t;
    };

    $bus   = $s['components'][$n['bus']];
    $pwr   = $s['components'][$n['power']];
    $com   = $s['components'][$n['comms']];
    $ins   = array_map(fn($c) => $s['components'][$c], $n['instruments']);
    $add   = array_map(fn($c) => $s['components'][$c], $n['addons']);
    $orb   = $s['orbit'];
    $obj   = $s['objective'];
    $dif   = $s['difficulty'];
    $hasDeo = in_array('ADD-DEO', $n['addons'], true);
    $sum   = fn(array $rows, string $f): float => array_sum(array_map(fn($r) => (float) ($r[$f] ?? 0), $rows));

    // ---- Stage 2: calculations C01–C18
    $payload  = $sum($ins, 'mass_kg');                                                     // C01
    $mass     = (float) $bus['mass_kg'] + $payload + (float) $pwr['mass_kg'] + (float) $com['mass_kg'] + $sum($add, 'mass_kg'); // C02
    $capacity = $s['matrix']['LAUNCH_CAPACITY'][$n['launch']] ?? null;                     // C03 (null: not served)
    $marginKg = $capacity !== null ? $capacity - $mass : null;                             // C04
    $peak     = (float) $bus['power_w'] + $sum($ins, 'power_w') + (float) $com['power_w'] + $sum($add, 'power_w'); // C05
    $contact  = 0.0;                                                                       // C06
    $bandKey  = $com['band'] === 'X' ? 'supports_x' : 'supports_s';
    $deafStations = [];
    foreach ($s['stations'] as $code => $st) {
        if ((int) $st[$bandKey] === 1) {
            $contact += (float) $s['matrix']['GROUND_CONTACT'][$code];
        } else {
            $deafStations[] = $st['name'];
        }
    }
    $duty     = $n['imaging'] / (float) $orb['period_min'];                                 // C07
    $minDay   = $P('MINUTES_PER_DAY');
    $avgLoad  = (float) $bus['power_w'] + $sum($ins, 'power_w') * $duty
              + (float) $com['power_w'] * $contact / $minDay + $sum($add, 'power_w');      // C08
    $demand   = $avgLoad * 24;                                                             // C09
    $genLaunch = (float) $pwr['generation_w'] * (float) $orb['sunlight_h_per_day'];        // C10 (seeded sunlight hours)
    $energyMargin = $demand > 0 ? ($genLaunch - $demand) / $demand : 0.0;                  // C11
    $eclipseNeed  = $avgLoad * (float) $orb['eclipse_min'] / 60;                           // C12 (seeded eclipse minutes)
    $usableBatt   = (float) $pwr['battery_wh'] * $P('USABLE_BATTERY_FRACTION');            // C13
    $bits    = $P('BITS_PER_BYTE');
    $dataGen = $sum($ins, 'data_rate_mbps') * $n['imaging'] * 60 * (float) $orb['orbits_per_day'] / $bits; // C14
    $downlink = (float) $com['data_rate_mbps'] * $contact * 60 / $bits;                    // C15
    $storage = ((float) $bus['storage_gb'] + $sum($add, 'storage_gb')) * $P('MB_PER_GB');  // C16
    $cost    = (float) $bus['cost_mu'] + $sum($ins, 'cost_mu') + (float) $pwr['cost_mu'] + (float) $com['cost_mu']
             + $sum(array_values($s['stations']), 'cost_mu') + (float) $s['launch']['cost_mu'] + $sum($add, 'cost_mu'); // C17
    $devMonths = (int) $bus['dev_months'] + (int) $sum($ins, 'dev_months');                // C18
    $budget  = (float) $dif['budget_mu'];
    $target  = (int) $dif['target_months'];

    $calc = [
        'payload_mass_kg' => r2($payload), 'total_mass_kg' => r2($mass),
        'launch_capacity_kg' => $capacity !== null ? r2($capacity) : null,
        'mass_margin_kg' => $marginKg !== null ? r2($marginKg) : null,
        'mass_margin_pct' => ($capacity ?? 0) > 0 ? r2($marginKg / $capacity * 100) : null,
        'peak_load_w' => r2($peak), 'peak_capacity_w' => r2((float) $bus['peak_capacity_w']),
        'contact_min_per_day' => r2($contact), 'imaging_duty' => round($duty, 4),
        'average_load_w' => r2($avgLoad), 'daily_demand_wh' => r2($demand),
        'daily_generation_wh' => r2($genLaunch), 'energy_margin_pct' => r2($energyMargin * 100),
        'eclipse_need_wh' => r2($eclipseNeed), 'usable_battery_wh' => r2($usableBatt),
        'data_generated_mb_day' => r2($dataGen), 'downlink_mb_day' => r2($downlink),
        'required_mb_day' => r2((float) $obj['required_mb_per_day']),
        'storage_mb' => r2($storage), 'total_cost_mu' => r2($cost), 'budget_mu' => r2($budget),
        'development_months' => $devMonths, 'target_months' => $target,
        'instrument_slots' => (int) $bus['instrument_slots'], 'instruments_selected' => count($ins),
        'payload_limit_kg' => r2((float) $bus['payload_limit_kg']),
    ];

    // ---- Stage 3: hard failures (all listed). A value exactly at a limit passes.
    $hard = [];
    $addHard = function (string $code, array $vars = []) use (&$hard, $text, $R): void {
        $hard[] = ['code' => $code, 'segment' => $R[$code]['segment'], 'message' => $text($code, $vars),
                   'feedback' => (string) $R[$code]['feedback']];
    };
    if (count($ins) > (int) $bus['instrument_slots']) {
        $addHard('H02', ['limit' => (int) $bus['instrument_slots'], 'value' => count($ins)]);
    }
    if (r2($payload) > r2((float) $bus['payload_limit_kg'])) {
        $addHard('H03', ['value' => fmt($payload), 'limit' => fmt((float) $bus['payload_limit_kg'])]);
    }
    if ($s['matrix']['CONFLICT']) {
        $pairs = array_map(fn($p) => $s['components'][$p[0]]['name'] . ' with ' . $s['components'][$p[1]]['name'], $s['matrix']['CONFLICT']);
        $addHard('H04', ['detail' => implode('; ', $pairs)]);
    }
    if ($capacity === null) {
        $addHard('H05');
    } elseif (r2($mass) > r2($capacity)) {
        $addHard('H06', ['value' => fmt($mass), 'limit' => fmt($capacity)]);
    }
    if (r2($peak) > r2((float) $bus['peak_capacity_w'])) {
        $addHard('H07', ['value' => fmt($peak), 'limit' => fmt((float) $bus['peak_capacity_w'])]);
    }
    if ($deafStations) {
        $addHard('H08', ['detail' => implode(', ', $deafStations) . ' cannot receive ' . $com['band'] . '-band']);
    }
    if (r2($contact) == 0.0) {
        $addHard('H09');
    }
    if (r2($genLaunch) < r2($demand)) {
        $addHard('H10', ['value' => fmt($genLaunch), 'limit' => fmt($demand)]);
    }
    if (r2($eclipseNeed) > r2($usableBatt)) {
        $addHard('H11', ['value' => fmt($eclipseNeed), 'limit' => fmt($usableBatt)]);
    }
    if (r2($cost) > r2($budget)) {
        $addHard('H12', ['value' => fmt($cost), 'limit' => fmt($budget)]);
    }

    // ---- Suitability and lifetime prediction (needed by warnings and simulation)
    $suits = array_map(fn($c) => (float) $s['matrix']['INSTRUMENT_SUIT'][$c], $n['instruments']);
    rsort($suits);
    $bestSuit  = $suits[0];
    $fInst     = min(1.0, $bestSuit + (isset($suits[1]) ? $P('SECOND_INSTRUMENT_BONUS') * $suits[1] : 0.0));
    $fOrbit    = (float) $s['matrix']['ORBIT_SUIT'][$n['orbit']];
    $loss      = $P('SOLAR_LOSS_PER_MONTH');
    $genMonth  = fn(int $m): float => $genLaunch * (1 - $loss * $m);

    // Energy-limited month: last month m (<= target) whose generation still covers demand.
    $energyLimit = null;
    for ($m = 1; $m <= $target; $m++) {
        if (r2($genMonth($m)) < r2($demand)) {
            $energyLimit = $m - 1;
            break;
        }
    }
    $limits = ['TARGET' => $target, 'ENERGY' => $energyLimit, 'BUS_LIFE' => (int) $bus['design_life_months'],
               'DECAY' => (int) $orb['decay_months']];
    $L = min(array_filter($limits, fn($v) => $v !== null));
    $endReason = null;
    foreach (['TARGET', 'ENERGY', 'BUS_LIFE', 'DECAY'] as $reason) { // tie order: addendum B2.4
        if ($limits[$reason] === $L) {
            $endReason = $reason;
            break;
        }
    }
    $failureMonth = $endReason === 'ENERGY' ? $L + 1 : null;
    $disposalOk = ((int) $orb['decay_months'] <= $P('DISPOSAL_DECAY_LIMIT')) || $hasDeo;

    // ---- Stage 4: monthly loop, months 1..L only (exactly L rows)
    $days = $P('DAYS_PER_MONTH');
    $months = [];
    $backlog = 0.0;
    $delivered = 0.0;
    $lost = 0.0;
    $firstLoss = null;
    $firstSent = null;
    $firstBacklog = null;
    $firstEnergyLow = null;
    $commissioning = (int) $P('COMMISSIONING_MONTHS');
    for ($m = 1; $m <= $L; $m++) {
        $g = $genMonth($m);
        $row = ['month' => $m, 'phase' => $m <= $commissioning ? 'COMMISSIONING' : 'SCIENCE',
                'generation_wh' => r2($g), 'demand_wh' => r2($demand),
                'generated_mb' => 0.0, 'sent_mb' => 0.0, 'backlog_mb' => 0.0, 'lost_mb' => 0.0];
        if ($m > $commissioning) {
            $made      = $dataGen * $days;
            $available = $backlog + $made;
            $sent      = min($available, $downlink * $days);
            $remaining = $available - $sent;
            $lostNow   = max(0.0, $remaining - $storage);
            $backlog   = min($storage, $remaining);
            $delivered += $sent;
            $lost      += $lostNow;
            $row['generated_mb'] = r2($made);
            $row['sent_mb'] = r2($sent);
            $row['backlog_mb'] = r2($backlog);
            $row['lost_mb'] = r2($lostNow);
            if ($firstSent === null && r2($sent) > 0) {
                $firstSent = $m;
            }
            if ($firstBacklog === null && r2($backlog) > 0) {
                $firstBacklog = $m;
            }
            if ($firstLoss === null && r2($lostNow) > 0) {
                $firstLoss = $m;
            }
        }
        if ($firstEnergyLow === null && $demand > 0 && ($g - $demand) / $demand < $P('ENERGY_LOW_EVENT')) {
            $firstEnergyLow = $m;
        }
        $months[] = $row;
    }
    $scienceMonths = max(0, $L - $commissioning);
    $avgDelivered = $scienceMonths > 0 ? $delivered / ($days * $scienceMonths) : 0.0;
    $required = (float) $obj['required_mb_per_day'];
    $fData = $L > 1 ? min(1.0, $avgDelivered / $required) : 0.0;
    $fLife = $target > 0 ? min(1.0, $L / $target) : 0.0;

    // ---- Warnings (never block). W04/W06 use the predicted mission.
    $warn = [];
    $addWarn = function (string $code, array $vars = []) use (&$warn, $text, $R): void {
        $warn[] = ['code' => $code, 'segment' => $R[$code]['segment'], 'message' => $text($code, $vars),
                   'feedback' => (string) $R[$code]['feedback']];
    };
    if ($capacity !== null && r2($marginKg) < r2($P('MASS_MARGIN_WARN') * $capacity)) {
        $addWarn('W01', ['value' => fmt($marginKg), 'limit' => fmt($capacity)]);
    }
    if (r2($genLaunch) < r2($P('ENERGY_MARGIN_WARN') * $demand)) {
        $addWarn('W02', ['value' => fmt($genLaunch), 'limit' => fmt($demand)]);
    }
    if (r2($dataGen) > r2($downlink)) {
        $addWarn('W03', ['value' => fmt($dataGen), 'limit' => fmt($downlink),
            'detail' => fmt($storage / ($dataGen - $downlink))]);
    }
    if (!$hard && r2($lost) > 0) {
        $addWarn('W04');
    }
    if ((int) $orb['needs_disposal_aid'] === 1 && !$hasDeo) {
        $addWarn('W05');
    }
    if (!$hard && $L < $target) {
        $addWarn('W06', ['value' => $L, 'limit' => $target]);
    }
    if (r2($bestSuit) < r2($P('REQ_INSTRUMENT_SUIT_MIN'))) {
        $addWarn('W07', ['value' => number_format($bestSuit, 2)]);
    }
    if (r2($fOrbit) < r2($P('REQ_ORBIT_SUIT_MIN'))) {
        $addWarn('W08', ['value' => number_format($fOrbit, 2)]);
    }
    if (r2(min($dataGen, $downlink)) < r2($required)) {
        $addWarn('W09', ['value' => fmt(min($dataGen, $downlink)), 'limit' => fmt($required)]);
    }
    $blind = [];
    $seeing = 0;
    foreach ($s['stations'] as $code => $st) {
        if ((float) $s['matrix']['GROUND_CONTACT'][$code] == 0.0) {
            $blind[] = $st['name'];
        } else {
            $seeing++;
        }
    }
    if ($blind && $seeing > 0) {
        $addWarn('W10', ['detail' => implode(', ', $blind)]);
    }
    if ($dif['schedule_limit_months'] !== null && $devMonths > (int) $dif['schedule_limit_months']) {
        $addWarn('W11', ['value' => $devMonths, 'limit' => (int) $dif['schedule_limit_months']]);
    }

    $result = [
        'flight_ready' => !$hard,
        'calc'         => $calc,
        'hard'         => $hard,
        'warnings'     => $warn,
        'prediction'   => ['lifetime_months' => $L, 'end_reason' => $endReason, 'failure_month' => $failureMonth,
                           'limits' => $limits, 'instrument_factor' => r2($fInst), 'orbit_factor' => r2($fOrbit)],
    ];
    if ($hard) {
        $result['feedback'] = engine_feedback($hard, [], $warn, $R);
        return $result;
    }

    // ---- Scores (game-only metrics)
    $science = 100 * $fInst * $fOrbit * $fData * $fLife;
    $eff = 50 * min(1.0, ($budget - $cost) / ($P('EFFICIENCY_BUDGET_REF') * $budget))
         + 50 * min(1.0, $marginKg / ($P('EFFICIENCY_MASS_REF') * $capacity));
    $resp = ($disposalOk ? $P('RESP_DISPOSAL_POINTS') : 0) + (r2($lost) == 0.0 ? $P('RESP_NO_LOSS_POINTS') : 0);
    $missionScore = $P('WEIGHT_SCIENCE') * $science + $P('WEIGHT_EFFICIENCY') * $eff + $P('WEIGHT_RESPONSIBILITY') * $resp;
    $sci2 = r2($science);
    $outcome = $sci2 >= $P('SUCCESS_THRESHOLD') ? 'SUCCESS' : ($sci2 >= $P('PARTIAL_THRESHOLD') ? 'PARTIAL' : 'NOT_MET');

    // ---- Events
    $events = [];
    $ev = function (string $code, int $month, array $vars) use (&$events, $text, $R): void {
        $events[] = ['seq' => count($events) + 1, 'month' => $month, 'code' => $code,
                     'severity' => $R[$code]['severity'], 'segment' => $R[$code]['segment'],
                     'message' => $text($code, $vars + ['month' => $month])];
    };
    $ev('EV-LAUNCH', 0, ['launch' => $s['launch']['name'], 'mass' => fmt($mass), 'capacity' => fmt($capacity)]);
    $cum = 0.0;
    $interval = (int) $P('MILESTONE_INTERVAL');
    foreach ($months as $row) {
        $m = $row['month'];
        $cum += $row['sent_mb'];
        if ($m === $commissioning) {
            $ev('EV-COMMISSIONED', $m, []);
        }
        if ($m === $firstSent) {
            $ev('EV-FIRST-DATA', $m, ['sent' => fmt($row['sent_mb'])]);
        }
        if ($m % $interval === 0) {
            $ev('EV-MILESTONE', $m, ['delivered' => fmt($cum / $P('MB_PER_GB'))]);
        }
        if ($m === $firstBacklog) {
            $ev('EV-BACKLOG', $m, ['backlog' => fmt($row['backlog_mb'])]);
        }
        if ($m === $firstLoss) {
            $ev('EV-STORAGE-FULL', $m, ['lost' => fmt($row['lost_mb'])]);
        }
        if ($m === $firstEnergyLow) {
            $ev('EV-ENERGY-LOW', $m, ['generation' => fmt($row['generation_wh']), 'demand' => fmt($demand)]);
        }
    }
    $endMonth = $L;
    switch ($endReason) {
        case 'TARGET':
            $ev('EV-TARGET-REACHED', $L, ['target' => $target]);
            break;
        case 'ENERGY': // terminal event in the predicted failure month L+1; no monthly row for it
            $endMonth = $failureMonth;
            $ev('EV-POWER-NEGATIVE', $failureMonth, [
                'failure_month' => $failureMonth,
                'generation' => number_format(r2($genMonth($failureMonth)), 2, '.', ','),
                'demand' => number_format(r2($demand), 2, '.', ','),
                'L' => $L,
            ]);
            break;
        case 'BUS_LIFE':
            $ev('EV-BUS-EOL', $L, ['life' => (int) $bus['design_life_months']]);
            break;
        case 'DECAY':
            $ev('EV-ORBIT-DECAY', $L, []);
            break;
    }
    if ($disposalOk) {
        $ev('EV-DISPOSAL-OK', $endMonth, ['how' => $hasDeo
            ? 'The deorbit kit brings the satellite down so it does not become debris.'
            : 'Atmospheric drag will bring the satellite down within the disposal threshold.']);
    } else {
        $ev('EV-DISPOSAL-RISK', $endMonth, []);
    }

    // ---- Requirements R1–R6
    $requirements = [
        ['code' => 'R1', 'name' => 'Measurement: best instrument suitability', 'segment' => 'SPACE',
         'measured' => r2($bestSuit), 'required' => r2($P('REQ_INSTRUMENT_SUIT_MIN')), 'unit' => '', 'met' => r2($bestSuit) >= r2($P('REQ_INSTRUMENT_SUIT_MIN'))],
        ['code' => 'R2', 'name' => 'Coverage and lighting: orbit suitability', 'segment' => 'LAUNCH',
         'measured' => r2($fOrbit), 'required' => r2($P('REQ_ORBIT_SUIT_MIN')), 'unit' => '', 'met' => r2($fOrbit) >= r2($P('REQ_ORBIT_SUIT_MIN'))],
        ['code' => 'R3', 'name' => 'Data delivered to users per day', 'segment' => 'GROUND',
         'measured' => r2($avgDelivered), 'required' => r2($required), 'unit' => 'MB/day', 'met' => r2($avgDelivered) >= r2($required)],
        ['code' => 'R4', 'name' => 'Operational lifetime', 'segment' => 'SPACE',
         'measured' => $L, 'required' => $target, 'unit' => 'months', 'met' => $L >= $target],
        ['code' => 'R5', 'name' => 'Total cost within budget', 'segment' => 'ALL',
         'measured' => r2($cost), 'required' => r2($budget), 'unit' => 'MU', 'met' => r2($cost) <= r2($budget)],
        ['code' => 'R6', 'name' => 'Responsible end of mission', 'segment' => 'SPACE',
         'measured' => $disposalOk ? 'Yes' : 'No', 'required' => 'Yes', 'unit' => '', 'met' => $disposalOk],
    ];

    $factors = ['instrument' => $fInst, 'orbit' => $fOrbit, 'data' => $fData, 'lifetime' => $fLife];

    // ---- User panel (addendum section D)
    $mbPerGb = $P('MB_PER_GB');
    $limitations = [];
    if (r2($fData) < 1) {
        $limitations[] = $text('U4-DATA', ['percent' => (string) round($fData * 100)]);
    }
    if (r2($lost) > 0) {
        $limitations[] = $text('U4-LOST', ['lost' => fmt($lost / $mbPerGb)]);
    }
    if (r2($fLife) < 1) {
        $limitations[] = $text('U4-LIFETIME', ['L' => $L, 'target' => $target]);
    }
    if (r2($fInst) < 1) {
        $limitations[] = $text('U4-INSTRUMENT');
    }
    if (r2($fOrbit) < 1) {
        $limitations[] = $text('U4-ORBIT');
    }
    if (!$limitations) {
        $limitations[] = $text('U4-NONE');
    }
    $user = [
        'delivered_gb'      => r2($delivered / $mbPerGb),
        'avg_mb_per_day'    => r2($avgDelivered),
        'required_mb_per_day' => r2($required),
        'observation_months' => $scienceMonths,
        'personas'          => $obj['user_personas'],
        'question'          => $obj['user_question'],
        'limitations'       => $limitations,
        'disclaimer'        => $text('U-DISCLAIMER'),
    ];

    $lifetimeText = $endReason === 'ENERGY'
        ? $text('RESULT-LIFETIME-ENERGY', ['L' => $L, 'target' => $target, 'failure_month' => $failureMonth])
        : $text('RESULT-LIFETIME', ['L' => $L, 'target' => $target]);

    $result += [
        'operational_months' => $L,
        'end_reason'         => $endReason,
        'failure_month'      => $failureMonth,
        'lifetime_text'      => $lifetimeText,
        'months'             => $months,
        'events'             => $events,
        'totals'             => ['delivered_mb' => r2($delivered), 'lost_mb' => r2($lost), 'avg_delivered_mb_day' => r2($avgDelivered),
                                 'first_loss_month' => $firstLoss, 'science_months' => $scienceMonths],
        'factors'            => array_map(fn($v) => round($v, 4), $factors),
        'scores'             => ['science' => $sci2, 'efficiency' => r2($eff), 'responsibility' => r2($resp),
                                 'mission' => r2($missionScore)],
        'outcome'            => $outcome,
        'disposal_ok'        => $disposalOk,
        'requirements'       => $requirements,
        'user'               => $user,
    ];
    $result['feedback'] = engine_feedback([], $factors, $warn, $R);
    return $result;
}

/**
 * Up to three suggestions: hard failures first, then the lowest science factor
 * below 1, then warnings in rule order. Identical suggestions are shown once.
 */
function engine_feedback(array $hard, array $factors, array $warnings, array $R): array
{
    $items = [];
    foreach ($hard as $h) {
        $items[] = ['source' => $h['code'], 'segment' => $h['segment'], 'text' => $h['feedback']];
    }
    if ($factors) {
        $low = null;
        foreach ($factors as $name => $v) { // ties resolved in this order: instrument, orbit, data, lifetime
            if (r2($v) < 1 && ($low === null || $v < $factors[$low])) {
                $low = $name;
            }
        }
        if ($low !== null) {
            $map = ['instrument' => ['FB-LOW-INSTRUMENT', 'SPACE'], 'orbit' => ['FB-LOW-ORBIT', 'LAUNCH'],
                    'data' => ['FB-LOW-DATA', 'GROUND'], 'lifetime' => ['FB-LOW-LIFETIME', 'SPACE']];
            $items[] = ['source' => 'low ' . $low . ' factor', 'segment' => $map[$low][1], 'text' => (string) $R[$map[$low][0]]['message']];
        }
    }
    foreach ($warnings as $w) {
        $items[] = ['source' => $w['code'], 'segment' => $w['segment'], 'text' => $w['feedback']];
    }
    $out = [];
    $seen = [];
    foreach ($items as $it) {
        if (isset($seen[$it['text']])) {
            continue;
        }
        $seen[$it['text']] = true;
        $out[] = $it;
        if (count($out) === 3) {
            break;
        }
    }
    return $out;
}

/** Convenience: validate + snapshot + evaluate raw input. */
function engine_run(array $input, array $cat, array $rules): array
{
    [$norm, $problems] = engine_normalize($input, $cat, $rules);
    if ($norm === null) {
        $R = $rules['rules']['H01'];
        $hard = [['code' => 'H01', 'segment' => 'ALL',
                  'message' => str_replace('{detail}', implode('; ', $problems), (string) $R['message_template']),
                  'feedback' => (string) $R['feedback_text']]];
        return ['flight_ready' => false, 'hard' => $hard, 'warnings' => [], 'calc' => null,
                'feedback' => [['source' => 'H01', 'segment' => 'ALL', 'text' => (string) $R['feedback_text']]],
                'snapshot' => null, 'hash' => null];
    }
    $snapshot = engine_snapshot($norm, $cat, $rules);
    $result = engine_evaluate($snapshot);
    $result['snapshot'] = $snapshot;
    $result['hash'] = snapshot_hash($snapshot);
    return $result;
}
