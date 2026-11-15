<?php
/**
 * TerraWatch-1 — engine acceptance tests (Document 09 as amended by the addendum).
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 *
 * Usage: php tests/engine_tests.php
 * Every expected value below is copied from Document 09 or the Technical Correction
 * Addendum (section B5, E2, E3). Never edit an expected value to make a test pass.
 * Stored-run tests (SIM-01, SIM-02, SIM-04 rows, DB-04, DB-05) run inside a transaction
 * that is rolled back, so the database is left unchanged.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/src/bootstrap.php';
require APP_ROOT . '/src/services/catalogue.php';
require APP_ROOT . '/src/services/engine.php';
require APP_ROOT . '/src/services/runs.php';

$CAT = catalogue();
$RULES = rules();
$results = ['PASS' => 0, 'FAIL' => 0];
$failures = [];

/** Build an input from Document 09 shorthand: bus / instruments / power / radio / stations / orbit / launch / imaging / add-ons */
function design(string $difficulty, string $objective, string $short): array
{
    $p = array_map('trim', explode('/', $short));
    $list = fn(string $s, string $prefix) => ($s === '' || $s === '-' || strtolower($s) === 'none') ? []
        : array_map(fn($x) => $prefix . trim($x), explode('+', $s));
    return [
        'difficulty' => 'DIFF-' . $difficulty[0], 'objective' => 'OBJ-' . $objective,
        'bus' => 'BUS-' . $p[0], 'instruments' => $list($p[1], 'INS-'), 'power' => 'PWR-' . $p[2],
        'comms' => 'COM-' . $p[3], 'stations' => $list($p[4], 'GS-'), 'orbit' => 'ORB-' . $p[5],
        'launch' => 'LV-' . $p[6], 'imaging' => (int) $p[7], 'addons' => $list($p[8] ?? '-', 'ADD-'),
    ];
}

$D = [
    'D1' => design('Standard', 'LAND',  'M / MSI / P2 / C2 / G1 / O1 / L3 / 5 / -'),
    'D2' => design('Standard', 'COAST', 'M / HSI / P2 / C3 / G1 / O1 / L3 / 5 / -'),
    'D3' => design('Standard', 'CLOUD', 'M / TIR / P2 / C2 / G2 / O3 / L2 / 15 / -'),
    'D4' => design('Standard', 'LAND',  'S / SWIR / P1 / C1 / G1 / O1 / L1 / 10 / -'),
    'D5' => design('Challenge', 'CLOUD', 'M / TIR / P1 / C2 / G2 / O2 / L3 / 15 / DEO'),
    'D6' => design('Standard', 'CLOUD', 'S / WFC / P1 / C2 / G2 / O3 / L2 / 15 / -'),
];
function with(array $d, array $changes): array
{
    return array_merge($d, $changes);
}
function run(array $input): array
{
    global $CAT, $RULES;
    return engine_run($input, $CAT, $RULES);
}
function codes(array $list): array
{
    $c = array_map(fn($x) => $x['code'], $list);
    sort($c);
    return $c;
}

/** Record one test: $checks is a list of [label, observed, expected] (numbers compared at 2 dp). */
function t(string $id, string $feature, array $checks): void
{
    global $results, $failures;
    $bad = [];
    foreach ($checks as [$label, $obs, $exp]) {
        $ok = (is_float($exp) || is_int($exp)) && (is_float($obs) || is_int($obs))
            ? abs(round((float) $obs, 2) - round((float) $exp, 2)) < 0.0001
            : $obs === $exp;
        if (!$ok) {
            $bad[] = "$label: observed " . json_encode($obs) . ', expected ' . json_encode($exp);
        }
    }
    $status = $bad ? 'FAIL' : 'PASS';
    $results[$status]++;
    printf("%s %-8s %s\n", $status, $id, $feature);
    foreach ($bad as $b) {
        echo "         $b\n";
        $failures[] = "$id $b";
    }
}

// ================================================================= 1. Calculation tests (D1)
$r = run($D['D1']);
$c = $r['calc'];
t('CAL-01', 'Mass', [['total', $c['total_mass_kg'], 80], ['capacity', $c['launch_capacity_kg'], 220], ['margin', $c['mass_margin_kg'], 140]]);
t('CAL-02', 'Peak load', [['peak', $c['peak_load_w'], 100], ['capacity', $c['peak_capacity_w'], 160]]);
t('CAL-03', 'Average load and demand', [['avg', $c['average_load_w'], 43.24], ['demand', $c['daily_demand_wh'], 1037.73]]);
t('CAL-04', 'Generation', [['generation', $c['daily_generation_wh'], 1788.00]]);
t('CAL-05', 'Eclipse need', [['eclipse', $c['eclipse_need_wh'], 25.80], ['usable', $c['usable_battery_wh'], 55]]);
t('CAL-06', 'Data generated', [['generated', $c['data_generated_mb_day'], 4560.00]]);
t('CAL-07', 'Downlink unit conversion', [['downlink', $c['downlink_mb_day'], 6000.00]]);
t('CAL-08', 'Cost', [['cost', $c['total_cost_mu'], 950], ['budget', $c['budget_mu'], 1150]]);
t('CAL-09', 'Scores D1', [['science', $r['scores']['science'], 100.00], ['efficiency', $r['scores']['efficiency'], 93.48],
    ['responsibility', $r['scores']['responsibility'], 100], ['mission', $r['scores']['mission'], 98.70], ['outcome', $r['outcome'], 'SUCCESS']]);

$r = run($D['D2']);
t('CAL-10', 'Coastal reference D2', [['mass', $r['calc']['total_mass_kg'], 88], ['peak', $r['calc']['peak_load_w'], 155],
    ['demand', $r['calc']['daily_demand_wh'], 1109.77], ['generated', $r['calc']['data_generated_mb_day'], 11400],
    ['downlink', $r['calc']['downlink_mb_day'], 30000], ['cost', $r['calc']['total_cost_mu'], 1110],
    ['science', $r['scores']['science'], 100.00], ['efficiency', $r['scores']['efficiency'], 58.70], ['mission', $r['scores']['mission'], 91.74]]);
$r = run($D['D3']);
t('CAL-11', 'Cloud reference D3', [['mass', $r['calc']['total_mass_kg'], 77], ['demand', $r['calc']['daily_demand_wh'], 1092.05],
    ['generation', $r['calc']['daily_generation_wh'], 1776.00], ['generated', $r['calc']['data_generated_mb_day'], 2598.75],
    ['downlink', $r['calc']['downlink_mb_day'], 3000], ['cost', $r['calc']['total_cost_mu'], 940],
    ['science', $r['scores']['science'], 100.00], ['efficiency', $r['scores']['efficiency'], 95.65], ['mission', $r['scores']['mission'], 99.13]]);
$r = run($D['D4']);
t('CAL-12', 'Weak design D4', [['demand', $r['calc']['daily_demand_wh'], 508.76], ['generated', $r['calc']['data_generated_mb_day'], 4560],
    ['downlink', $r['calc']['downlink_mb_day'], 1200], ['data factor', $r['factors']['data'], 0.30], ['science', $r['scores']['science'], 21.00],
    ['efficiency', $r['scores']['efficiency'], 100], ['responsibility', $r['scores']['responsibility'], 60],
    ['mission', $r['scores']['mission'], 40.70], ['outcome', $r['outcome'], 'NOT_MET']]);
$r = run(design('Standard', 'LAND', 'M / WFC+SWIR / P2 / C2 / G1 / O1 / L3 / 5 / -'));
t('CAL-13', 'Second-instrument bonus', [['instrument factor', $r['factors']['instrument'], 0.80], ['data factor (3 dp)', round($r['factors']['data'], 3), 0.855],
    ['science', $r['scores']['science'], 68.40], ['outcome', $r['outcome'], 'PARTIAL']]);
t('CAL-14', 'Development duration', [['D1', run($D['D1'])['calc']['development_months'], 27], ['D2', run($D['D2'])['calc']['development_months'], 29]]);
t('CAL-15', 'Orbit constants (seed)', [
    ['O1 period', $CAT['orbits']['ORB-O1']['period_min'], 94.6], ['O2 period', $CAT['orbits']['ORB-O2']['period_min'], 96.7], ['O3 period', $CAT['orbits']['ORB-O3']['period_min'], 93.6],
    ['O1 n', $CAT['orbits']['ORB-O1']['orbits_per_day'], 15.2], ['O2 n', $CAT['orbits']['ORB-O2']['orbits_per_day'], 14.9], ['O3 n', $CAT['orbits']['ORB-O3']['orbits_per_day'], 15.4]]);

// ================================================================= Addendum E2: D1–D6 reference table
$E2 = [ // mass, cap, peak, peakcap, demand, generation, generated, downlink, cost, budget, L, target, sci, eff, resp, mission, end
    'D1' => [80, 220, 100, 160, 1037.73, 1788.00, 4560.00, 6000.00, 950, 1150, 24, 24, 100.00, 93.48, 100, 98.70, 'TARGET'],
    'D2' => [88, 220, 155, 160, 1109.77, 1788.00, 11400.00, 30000.00, 1110, 1150, 24, 24, 100.00, 58.70, 100, 91.74, 'TARGET'],
    'D3' => [77, 220, 95, 160, 1092.05, 1776.00, 2598.75, 3000.00, 940, 1150, 24, 24, 100.00, 95.65, 100, 99.13, 'TARGET'],
    'D4' => [26.5, 60, 53, 60, 508.76, 745.00, 4560.00, 1200.00, 580, 1150, 24, 24, 21.00, 100.00, 60, 40.70, 'TARGET'],
    'D5' => [73, 200, 95, 160, 1085.02, 1175.00, 2514.38, 2400.00, 815, 1075, 30, 36, 72.00, 100.00, 60, 76.40, 'ENERGY'],
    'D6' => [25, 220, 55, 60, 494.82, 740.00, 3465.00, 3000.00, 690, 1150, 24, 24, 80.00, 100.00, 60, 82.00, 'TARGET'],
];
foreach ($E2 as $ref => $e) {
    $r = run($D[$ref]);
    $c = $r['calc'];
    t("E2-$ref", "Reference results $ref (addendum E2)", [
        ['flight ready', $r['flight_ready'], true],
        ['mass', $c['total_mass_kg'], $e[0]], ['capacity', $c['launch_capacity_kg'], $e[1]],
        ['peak', $c['peak_load_w'], $e[2]], ['peak capacity', $c['peak_capacity_w'], $e[3]],
        ['demand', $c['daily_demand_wh'], $e[4]], ['generation', $c['daily_generation_wh'], $e[5]],
        ['generated', $c['data_generated_mb_day'], $e[6]], ['downlink', $c['downlink_mb_day'], $e[7]],
        ['cost', $c['total_cost_mu'], $e[8]], ['budget', $c['budget_mu'], $e[9]],
        ['lifetime', $r['operational_months'] ?? null, $e[10]], ['target', $c['target_months'], $e[11]],
        ['science', $r['scores']['science'] ?? null, $e[12]], ['efficiency', $r['scores']['efficiency'] ?? null, $e[13]],
        ['responsibility', $r['scores']['responsibility'] ?? null, $e[14]], ['mission', $r['scores']['mission'] ?? null, $e[15]],
        ['end reason', $r['end_reason'] ?? null, $e[16]],
    ]);
}

// ================================================================= 2. Hard failures
$hrd = [
    ['HRD-01', 'H01 incomplete', with($D['D1'], ['comms' => null]), ['H01']],
    ['HRD-02', 'H02 slots', design('Standard', 'LAND', 'S / WFC+SWIR / P1 / C1 / G2 / O1 / L1 / 5 / -'), ['H02', 'H03', 'H07']],
    ['HRD-03', 'H03 payload mass', design('Standard', 'COAST', 'S / HSI / P1 / C1 / G2 / O1 / L1 / 5 / -'), ['H03', 'H07']],
    ['HRD-04', 'H04 conflict', design('Standard', 'CLOUD', 'S / WFC / P3 / C1 / G2 / O1 / L1 / 5 / -'), ['H04']],
    ['HRD-05', 'H05 launch and orbit', design('Standard', 'CLOUD', 'S / WFC / P1 / C1 / G2 / O3 / L1 / 5 / -'), ['H05']],
    ['HRD-06', 'H06 mass', with($D['D1'], ['launch' => 'LV-L1']), ['H06']],
    ['HRD-07', 'H07 peak power', with($D['D4'], ['comms' => 'COM-C2']), ['H07']],
    ['HRD-08', 'H08 band', with($D['D2'], ['stations' => ['GS-G2']]), ['H08', 'H09']],
    ['HRD-09', 'H09 visibility', with($D['D3'], ['stations' => ['GS-G1']]), ['H09']],
    ['HRD-10', 'H10 energy', design('Beginner', 'LAND', 'L / MSI / P2 / C2 / G1 / O1 / L3 / 5 / -'), ['H10']],
    ['HRD-11', 'H11 battery', design('Beginner', 'LAND', 'L / MSI / P1 / C2 / G1 / O1 / L3 / 5 / -'), ['H10', 'H11']],
    ['HRD-12', 'H12 budget', with($D['D2'], ['difficulty' => 'DIFF-C']), ['H12']],
];
foreach ($hrd as [$id, $feature, $input, $expected]) {
    $r = run($input);
    $checks = [['rule set', codes($r['hard']), $expected], ['no run (not flight-ready)', $r['flight_ready'], false]];
    if ($id === 'HRD-10') {
        $checks[] = ['demand', $r['calc']['daily_demand_wh'], 2117.73];
        $checks[] = ['generation', $r['calc']['daily_generation_wh'], 1788.00];
        $checks[] = ['eclipse need', $r['calc']['eclipse_need_wh'], 52.65];
    }
    if ($id === 'HRD-11') {
        $checks[] = ['eclipse need', $r['calc']['eclipse_need_wh'], 52.65];
        $checks[] = ['usable battery', $r['calc']['usable_battery_wh'], 20];
    }
    t($id, $feature, $checks);
}
$r = run($hrd[1][2]);
t('HRD-13', 'All failures listed (engine side; visual check pending)', [['count', count($r['hard']), 3]]);

// ================================================================= 3. Warnings and boundaries
$r = run($D['D1']);
t('WRN-01', 'No warnings (D1)', [['warnings', codes($r['warnings']), []]]);
$r = run($D['D4']);
t('WRN-02', 'W03, W04, W07, W09 (D4)', [['warnings', codes($r['warnings']), ['W03', 'W04', 'W07', 'W09']], ['first loss month', $r['totals']['first_loss_month'], 2]]);
$r = run($D['D5']);
t('WRN-03', 'W02, W06 (D5)', [['warnings', codes($r['warnings']), ['W02', 'W03', 'W04', 'W06', 'W09']]]);
$r = run(with($D['D3'], ['orbit' => 'ORB-O2', 'launch' => 'LV-L3']));
t('WRN-04', 'W05 disposal', [['warnings', codes($r['warnings']), ['W03', 'W04', 'W05', 'W09']], ['responsibility', $r['scores']['responsibility'], 0],
    ['science', $r['scores']['science'], 86.40], ['mission', $r['scores']['mission'], 80.48]]);
$r = run(with($D['D3'], ['stations' => ['GS-G1', 'GS-G2']]));
t('WRN-05', 'W10 blind station', [['has W10', in_array('W10', codes($r['warnings']), true), true], ['contact', $r['calc']['contact_min_per_day'], 40],
    ['cost', $r['calc']['total_cost_mu'], 1060], ['science', $r['scores']['science'] ?? null, 100.00]]);
$r = run(design('Beginner', 'CLOUD', 'L / WFC / P3 / C3 / G3 / O1 / L2 / 5 / STO'));
t('WRN-06', 'W01 and mass boundary', [['flight ready', $r['flight_ready'], true], ['mass', $r['calc']['total_mass_kg'], 150], ['capacity', $r['calc']['launch_capacity_kg'], 150],
    ['W01 and W09 present', count(array_intersect(['W01', 'W09'], codes($r['warnings']))), 2], ['science', $r['scores']['science'] ?? null, 31.01]]);
$r = run(with($D['D2'], ['stations' => ['GS-G3'], 'addons' => ['ADD-STO']]));
t('WRN-07', 'Peak power boundary', [['flight ready', $r['flight_ready'], true], ['peak', $r['calc']['peak_load_w'], 160],
    ['science', $r['scores']['science'] ?? null, 100.00], ['warnings', codes($r['warnings']), ['W03', 'W04']]]);
$r = run(design('Standard', 'CLOUD', 'M / WFC / P2 / C1 / G1+G4 / O1 / L2 / 5 / STO'));
t('WRN-08', 'Budget boundary', [['flight ready', $r['flight_ready'], true], ['cost', $r['calc']['total_cost_mu'], 1150], ['science', $r['scores']['science'] ?? null, 31.01]]);
$r = run(with($D['D1'], ['imaging' => 7]));
t('WRN-09', 'Invalid imaging value', [['rule set', codes($r['hard']), ['H01']], ['no snapshot', $r['snapshot'], null]]);
$r = run(with($D['D1'], ['instruments' => ['INS-MSI', 'INS-MSI']]));
t('WRN-10', 'Duplicate instrument', [['rule set', codes($r['hard']), ['H01']], ['no snapshot', $r['snapshot'], null]]);

// ================================================================= 4. Compatibility
$valid = 0;
$h05 = 0;
$caps = [];
foreach (['LV-L1', 'LV-L2', 'LV-L3'] as $l) {
    foreach (['ORB-O1', 'ORB-O2', 'ORB-O3'] as $o) {
        $r = run(with($D['D1'], ['launch' => $l, 'orbit' => $o]));
        if (in_array('H05', codes($r['hard']), true)) {
            $h05++;
        } else {
            $valid++;
            $caps[] = $r['calc']['launch_capacity_kg'];
        }
    }
}
t('CMP-01', 'Launch and orbit table', [['valid pairs', $valid, 6], ['H05 pairs', $h05, 3], ['capacities', $caps, [60.0, 150.0, 130.0, 220.0, 220.0, 200.0]]]);
$gsDoc = ['GS-G1' => [80, 80, 0], 'GS-G2' => [32, 32, 40], 'GS-G3' => [24, 24, 56], 'GS-G4' => [96, 96, 96]];
$cmp2 = [];
foreach ($gsDoc as $g => $mins) {
    foreach (['ORB-O1', 'ORB-O2', 'ORB-O3'] as $i => $o) {
        $cmp2[] = ["$g/$o", $CAT['matrix']['GROUND_CONTACT'][$g][$o], (float) $mins[$i]];
    }
}
t('CMP-02', 'Station and orbit table', $cmp2);
$fails = [];
foreach (['COM-C1', 'COM-C2', 'COM-C3'] as $cm) {
    foreach (['GS-G1', 'GS-G2', 'GS-G3', 'GS-G4'] as $g) {
        $r = run(with($D['D1'], ['comms' => $cm, 'stations' => [$g]]));
        if (in_array('H08', codes($r['hard']), true)) {
            $fails[] = "$cm+$g";
        }
    }
}
t('CMP-03', 'Band support', [['H08 pairs', $fails, ['COM-C3+GS-G2']]]);
$engineSrc = file_get_contents(APP_ROOT . '/src/services/engine.php');
$code = preg_replace('~/\*.*?\*/|//[^\n]*~s', '', $engineSrc); // ignore comments
$r = run($D['D5']);
t('CMP-06', 'No eclipse/period formula at run time; ORB-O2 from seed', [
    ['no asin/sqrt/pi/pow in engine code', (bool) preg_match('/\b(asin|sqrt|pi|M_PI|pow)\s*\(/', $code), false],
    ['O2 sunlight from snapshot', $r['snapshot']['orbit']['sunlight_h_per_day'], 23.5],
    ['O2 eclipse from snapshot', $r['snapshot']['orbit']['eclipse_min'], 2.0],
    ['D5 generation uses 23.5 h', $r['calc']['daily_generation_wh'], 1175.00]]);

// ================================================================= 5. Simulation and lifetime
$r = run($D['D5']);
$pn = array_values(array_filter($r['events'], fn($e) => $e['code'] === 'EV-POWER-NEGATIVE'));
$lastRow = end($r['months']);
t('SIM-04', 'Energy-limited life D5 (addendum B5)', [
    ['operational_months', $r['operational_months'], 30], ['end_reason', $r['end_reason'], 'ENERGY'], ['failure_month', $r['failure_month'], 31],
    ['monthly rows', count($r['months']), 30], ['last row month', $lastRow['month'], 30],
    ['one EV-POWER-NEGATIVE', count($pn), 1], ['event month', $pn[0]['month'] ?? null, 31],
    ['message has 1,083.94', str_contains($pn[0]['message'] ?? '', '1,083.94'), true],
    ['message has 1,085.02', str_contains($pn[0]['message'] ?? '', '1,085.02'), true],
    ['message has "operated for 30 months"', str_contains($pn[0]['message'] ?? '', 'operated for 30 months'), true],
    ['results text "30 of 36 months"', str_contains($r['lifetime_text'], '30 of 36 months'), true],
    ['science', $r['scores']['science'], 72.00], ['lifetime factor (4 dp)', $r['factors']['lifetime'], 0.8333],
    ['mission', $r['scores']['mission'], 76.40], ['outcome', $r['outcome'], 'PARTIAL']]);
$r = run(with($D['D5'], ['difficulty' => 'DIFF-S']));
t('SIM-05', 'Same design, shorter target', [['lifetime', $r['operational_months'], 24], ['science', $r['scores']['science'], 86.40]]);
$r = run(with($D['D6'], ['difficulty' => 'DIFF-C']));
t('SIM-06', 'Bus-limited life', [['lifetime', $r['operational_months'], 24], ['target', $r['calc']['target_months'], 36], ['end reason', $r['end_reason'], 'BUS_LIFE'],
    ['EV-BUS-EOL present', in_array('EV-BUS-EOL', array_column($r['events'], 'code'), true), true],
    ['science', $r['scores']['science'], 53.33], ['W06', in_array('W06', codes($r['warnings']), true), true]]);
$r = run(with($D['D3'], ['difficulty' => 'DIFF-C']));
t('SIM-07', 'Decay equals target', [['lifetime', $r['operational_months'], 36], ['end reason', $r['end_reason'], 'TARGET'], ['science', $r['scores']['science'], 100.00]]);
$r = run($D['D1']);
$first = array_values(array_filter($r['events'], fn($e) => $e['code'] === 'EV-FIRST-DATA'));
t('SIM-08', 'Commissioning', [['month 1 generated', $r['months'][0]['generated_mb'], 0.0], ['month 1 sent', $r['months'][0]['sent_mb'], 0.0],
    ['EV-FIRST-DATA month', $first[0]['month'] ?? null, 2]]);
$r = run(with($D['D2'], ['stations' => ['GS-G3']]));
t('SIM-09', 'Backlog and loss', [['downlink MB/day', $r['calc']['downlink_mb_day'], 9000], ['first loss month', $r['totals']['first_loss_month'], 2],
    ['lost MB', $r['totals']['lost_mb'], 1592000], ['responsibility', $r['scores']['responsibility'], 60], ['mission', $r['scores']['mission'], 89.91]]);
$r = run(with($D['D2'], ['stations' => ['GS-G3'], 'addons' => ['ADD-STO']]));
t('SIM-10', 'Memory expansion', [['first loss month', $r['totals']['first_loss_month'], 3], ['lost MB', $r['totals']['lost_mb'], 1528000]]);
$r = run($D['D1']);
t('SIM-11', 'Cumulative delivery', [['delivered MB', $r['totals']['delivered_mb'], 3146400], ['science months', $r['totals']['science_months'], 23], ['lost', $r['totals']['lost_mb'], 0]]);
$r = run(design('Standard', 'CLOUD', 'M / HSI / P1 / C2 / G3 / O2 / L3 / 15 / -'));
$pn = array_values(array_filter($r['events'] ?? [], fn($e) => $e['code'] === 'EV-POWER-NEGATIVE'));
t('SIM-13', 'Lifetime 0 edge case (addendum B2)', [
    ['flight ready', $r['flight_ready'], true], ['demand', $r['calc']['daily_demand_wh'], 1174.76], ['generation at launch', $r['calc']['daily_generation_wh'], 1175.00],
    ['W02 raised', in_array('W02', codes($r['warnings']), true), true],
    ['lifetime', $r['operational_months'] ?? null, 0], ['monthly rows', count($r['months'] ?? [1]), 0],
    ['EV-POWER-NEGATIVE month', $pn[0]['month'] ?? null, 1], ['science', $r['scores']['science'] ?? null, 0.00],
    ['efficiency', $r['scores']['efficiency'] ?? null, 100.00], ['responsibility', $r['scores']['responsibility'] ?? null, 40], ['mission', $r['scores']['mission'] ?? null, 24.00]]);

// ================================================================= FUN-10 user panel (engine output)
$r = run($D['D4']);
$lim = $r['user']['limitations'];
t('FUN-10e', 'User panel D4 / D1 (engine text; screen check in Phase 3)', [
    ['D4 personas', $r['user']['personas'], 'Regional land-use planner; agricultural adviser'],
    ['D4 limitation count', count($lim), 3],
    ['D4 data 30%', str_contains($lim[0] ?? '', 'Only 30%'), true],
    ['D4 data lost', str_contains($lim[1] ?? '', 'overwritten'), true],
    ['D4 instrument', str_contains($lim[2] ?? '', 'not the best match'), true],
    ['D1 only "received what they asked for"', run($D['D1'])['user']['limitations'], ['Within this simplified model, the users received what they asked for.']],
    ['disclaimer', $r['user']['disclaimer'], 'Illustrative. The game does not produce real images or data products.']]);
t('FB-D4', 'Feedback points first at the data factor (Doc 04 example B)', [['first suggestion source', $r['feedback'][0]['source'] ?? null, 'low data factor']]);

// ================================================================= Stored runs (rolled back)
$pdo = db();
$pdo->beginTransaction();
try {
    $mk = function (string $nick) {
        q('INSERT INTO missions (session_key_hash, nickname) VALUES (?, ?)', [hash('sha256', $nick), $nick]);
        return (int) db()->lastInsertId();
    };
    $strip = function (array $run): string {
        unset($run['id'], $run['mission_id'], $run['run_number'], $run['created_at']);
        return json_encode($run);
    };
    $m1 = $mk('tester-one');
    $ids = [store_run($m1, run($D['D1'])), store_run($m1, run($D['D1'])), store_run($m1, run($D['D1']))];
    $runs = array_map('load_run', $ids);
    t('SIM-01', 'Repeatability: D1 three times', [
        ['same hash', count(array_unique(array_column($runs, 'input_hash'))), 1],
        ['identical results apart from ids/timestamps', count(array_unique(array_map($strip, $runs))), 1],
        ['24 monthly rows stored', count($runs[0]['months']), 24]]);
    $m2 = $mk('someone-else');
    $other = load_run(store_run($m2, run($D['D1'])));
    t('SIM-02', 'Independence from user', [['same result for another nickname', $strip($other), $strip($runs[0])]]);

    $d5 = load_run(store_run($m1, run($D['D5'])));
    $pnStored = array_values(array_filter($d5['events'], fn($e) => $e['event_code'] === 'EV-POWER-NEGATIVE'));
    t('SIM-04s', 'D5 stored rows and summary', [
        ['operational_months', (int) $d5['operational_months'], 30], ['end_reason', $d5['end_reason'], 'ENERGY'],
        ['summary failure_month', $d5['summary']['failure_month'], 31], ['stored monthly rows', count($d5['months']), 30],
        ['last stored month', (int) end($d5['months'])['month_no'], 30], ['stored event month', (int) ($pnStored[0]['month_no'] ?? 0), 31],
        ['science', (float) $d5['science_score'], 72.00]]);

    // DB-05: recompute D1 from its stored snapshot alone
    $replay = engine_evaluate($runs[0]['snapshot']);
    t('DB-05', 'Snapshot completeness', [['science', $replay['scores']['science'], (float) $runs[0]['science_score']],
        ['mission', $replay['scores']['mission'], (float) $runs[0]['mission_score']],
        ['hash of stored snapshot', snapshot_hash($runs[0]['snapshot']), $runs[0]['input_hash']]]);

    // DB-04: an error while writing events leaves no run or child rows
    $before = (int) q('SELECT COUNT(*) FROM simulation_runs')->fetchColumn();
    $bad = run($D['D1']);
    $bad['events'][] = ['seq' => 1, 'month' => 99, 'code' => 'EV-DUP', 'severity' => 'INFO', 'segment' => 'ALL', 'message' => 'duplicate seq'];
    $pdo->exec('SAVEPOINT db04');
    $threw = false;
    try {
        // store_run uses the outer transaction here, so emulate its atomic write with a savepoint
        store_run($m1, $bad);
    } catch (PDOException $e) {
        $threw = true;
        $pdo->exec('ROLLBACK TO SAVEPOINT db04');
    }
    $after = (int) q('SELECT COUNT(*) FROM simulation_runs')->fetchColumn();
    $orphans = (int) q('SELECT COUNT(*) FROM simulation_metrics m LEFT JOIN simulation_runs r ON r.id = m.run_id WHERE r.id IS NULL')->fetchColumn();
    t('DB-04', 'Atomic run write', [['error raised', $threw, true], ['run count unchanged', $after, $before], ['no orphan metric rows', $orphans, 0]]);
} finally {
    $pdo->rollBack();
}

// ================================================================= summary
$total = $results['PASS'] + $results['FAIL'];
echo "\n{$results['PASS']} of $total passed, {$results['FAIL']} failed.\n";
exit($results['FAIL'] ? 1 : 0);
