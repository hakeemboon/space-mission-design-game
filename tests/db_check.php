<?php
/**
 * TerraWatch-1 — Phase 1 database checks. Command line only.
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 *
 * Usage: php tests/db_check.php
 *   DB-01  seed counts (addendum E3)
 *   SEED   every seeded catalogue value equals an independent copy of Document 02
 *          (taken from docs/corrections/planning_script_tune.py)
 *   MATRIX every compatibility_matrix code exists in the catalogue
 *   DB-02  foreign keys reject a selection for a missing component
 *   DB-03  uniqueness rejects a duplicate component code
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/src/bootstrap.php';
require APP_ROOT . '/src/services/catalogue.php';

$pass = 0;
$fail = 0;
function check(string $id, bool $ok, string $detail): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? 'PASS ' : 'FAIL ') . str_pad($id, 10) . $detail . "\n";
}

// ---------------------------------------------------------------- DB-01
$expected = [
    'components' => 16, 'orbit_profiles' => 3, 'launch_profiles' => 3, 'ground_stations' => 4,
    'compatibility_matrix' => 43, 'objectives' => 3, 'difficulty_settings' => 3,
    'reference_sources' => 11, 'fact_cards' => 13,
];
$allCounts = true;
$observed = [];
foreach ($expected as $table => $n) {
    $got = (int) q("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    $observed[] = "$table=$got";
    if ($got !== $n) {
        $allCounts = false;
        echo "      $table: expected $n, observed $got\n";
    }
}
check('DB-01', $allCounts, implode(', ', $observed));
$kinds = q('SELECT kind, COUNT(*) n FROM compatibility_matrix GROUP BY kind ORDER BY kind')->fetchAll(PDO::FETCH_KEY_PAIR);
$kindExpected = ['CONFLICT' => 1, 'GROUND_CONTACT' => 12, 'INSTRUMENT_SUIT' => 15, 'LAUNCH_CAPACITY' => 6, 'ORBIT_SUIT' => 9];
check('DB-01b', array_map('intval', $kinds) == $kindExpected, 'matrix by kind ' . json_encode($kinds));
$tables = (int) q("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
check('SCHEMA', $tables === 16, "tables in database = $tables (expected 16)");

// ---------------------------------------------------------------- SEED vs Document 02 (planning-script copy)
$doc = [
    'BUS-S' => ['mass_kg' => 14, 'payload_limit_kg' => 8, 'instrument_slots' => 1, 'peak_capacity_w' => 60, 'power_w' => 18, 'storage_gb' => 16, 'cost_mu' => 150, 'design_life_months' => 24, 'dev_months' => 18],
    'BUS-M' => ['mass_kg' => 55, 'payload_limit_kg' => 25, 'instrument_slots' => 2, 'peak_capacity_w' => 160, 'power_w' => 40, 'storage_gb' => 64, 'cost_mu' => 280, 'design_life_months' => 36, 'dev_months' => 24],
    'BUS-L' => ['mass_kg' => 120, 'payload_limit_kg' => 45, 'instrument_slots' => 3, 'peak_capacity_w' => 320, 'power_w' => 85, 'storage_gb' => 128, 'cost_mu' => 430, 'design_life_months' => 60, 'dev_months' => 32],
    'INS-WFC'  => ['mass_kg' => 4,  'power_w' => 12, 'data_rate_mbps' => 2,   'cost_mu' => 60,  'dev_months' => 1],
    'INS-SWIR' => ['mass_kg' => 7,  'power_w' => 25, 'data_rate_mbps' => 4,   'cost_mu' => 100, 'dev_months' => 2],
    'INS-MSI'  => ['mass_kg' => 12, 'power_w' => 35, 'data_rate_mbps' => 8,   'cost_mu' => 140, 'dev_months' => 3],
    'INS-TIR'  => ['mass_kg' => 9,  'power_w' => 30, 'data_rate_mbps' => 1.5, 'cost_mu' => 120, 'dev_months' => 3],
    'INS-HSI'  => ['mass_kg' => 18, 'power_w' => 55, 'data_rate_mbps' => 20,  'cost_mu' => 220, 'dev_months' => 5],
    'PWR-P1' => ['mass_kg' => 4,  'generation_w' => 50,  'battery_wh' => 40,  'cost_mu' => 50],
    'PWR-P2' => ['mass_kg' => 10, 'generation_w' => 120, 'battery_wh' => 110, 'cost_mu' => 110],
    'PWR-P3' => ['mass_kg' => 19, 'generation_w' => 260, 'battery_wh' => 260, 'cost_mu' => 190],
    'COM-C1' => ['mass_kg' => 1.5, 'power_w' => 10, 'data_rate_mbps' => 2,  'band' => 'S', 'cost_mu' => 40],
    'COM-C2' => ['mass_kg' => 3,   'power_w' => 25, 'data_rate_mbps' => 10, 'band' => 'S', 'cost_mu' => 90],
    'COM-C3' => ['mass_kg' => 5,   'power_w' => 60, 'data_rate_mbps' => 50, 'band' => 'X', 'cost_mu' => 170],
    'ADD-STO' => ['mass_kg' => 2, 'power_w' => 5, 'storage_gb' => 64, 'cost_mu' => 40, 'provides_disposal' => 0],
    'ADD-DEO' => ['mass_kg' => 2, 'power_w' => 0, 'cost_mu' => 25, 'provides_disposal' => 1],
];
$cat = catalogue();
$mismatch = [];
foreach ($doc as $code => $fields) {
    foreach ($fields as $f => $v) {
        $got = $cat['components'][$code][$f] ?? null;
        if (is_string($v) ? $got !== $v : abs((float) $got - $v) > 1e-9) {
            $mismatch[] = "$code.$f expected $v observed " . var_export($got, true);
        }
    }
}
$orbDoc = [
    'ORB-O1' => ['altitude_km' => 500, 'period_min' => 94.6, 'orbits_per_day' => 15.2, 'sunlight_h_per_day' => 14.9, 'eclipse_min' => 35.8, 'decay_months' => 84,  'needs_disposal_aid' => 0, 'sunlight_model' => 'GENERAL_ECLIPSE'],
    'ORB-O2' => ['altitude_km' => 600, 'period_min' => 96.7, 'orbits_per_day' => 14.9, 'sunlight_h_per_day' => 23.5, 'eclipse_min' => 2.0,  'decay_months' => 999, 'needs_disposal_aid' => 1, 'sunlight_model' => 'DAWN_DUSK_FIXED'],
    'ORB-O3' => ['altitude_km' => 450, 'period_min' => 93.6, 'orbits_per_day' => 15.4, 'sunlight_h_per_day' => 14.8, 'eclipse_min' => 35.9, 'decay_months' => 36,  'needs_disposal_aid' => 0, 'sunlight_model' => 'GENERAL_ECLIPSE'],
];
foreach ($orbDoc as $code => $fields) {
    foreach ($fields as $f => $v) {
        $got = $cat['orbits'][$code][$f] ?? null;
        if (is_string($v) ? $got !== $v : abs((float) $got - $v) > 1e-9) {
            $mismatch[] = "$code.$f expected $v observed " . var_export($got, true);
        }
    }
}
$simple = [
    ['launches', 'LV-L1', 'cost_mu', 120], ['launches', 'LV-L2', 'cost_mu', 300], ['launches', 'LV-L3', 'cost_mu', 210],
    ['stations', 'GS-G1', 'cost_mu', 120], ['stations', 'GS-G2', 'cost_mu', 40], ['stations', 'GS-G3', 'cost_mu', 70], ['stations', 'GS-G4', 'cost_mu', 200],
    ['stations', 'GS-G1', 'supports_x', 1], ['stations', 'GS-G2', 'supports_x', 0], ['stations', 'GS-G3', 'supports_x', 1], ['stations', 'GS-G4', 'supports_x', 1],
    ['objectives', 'OBJ-LAND', 'required_mb_per_day', 4000], ['objectives', 'OBJ-COAST', 'required_mb_per_day', 8000], ['objectives', 'OBJ-CLOUD', 'required_mb_per_day', 2500],
    ['difficulties', 'DIFF-B', 'budget_mu', 1300], ['difficulties', 'DIFF-S', 'budget_mu', 1150], ['difficulties', 'DIFF-C', 'budget_mu', 1075],
    ['difficulties', 'DIFF-B', 'target_months', 12], ['difficulties', 'DIFF-S', 'target_months', 24], ['difficulties', 'DIFF-C', 'target_months', 36],
];
foreach ($simple as [$t, $code, $f, $v]) {
    $got = $cat[$t][$code][$f] ?? null;
    if (abs((float) $got - $v) > 1e-9) {
        $mismatch[] = "$code.$f expected $v observed " . var_export($got, true);
    }
}
$matrixDoc = [
    'INSTRUMENT_SUIT' => ['INS-WFC' => [.4, .3, .8], 'INS-SWIR' => [.7, .4, .6], 'INS-MSI' => [1, .7, .5], 'INS-TIR' => [.5, .5, 1], 'INS-HSI' => [.8, 1, .2]],
    'ORBIT_SUIT'      => ['ORB-O1' => [1, 1, .85], 'ORB-O2' => [.6, .6, .9], 'ORB-O3' => [.7, .8, 1]],
];
$objs = ['OBJ-LAND', 'OBJ-COAST', 'OBJ-CLOUD'];
foreach ($matrixDoc as $kind => $rows) {
    foreach ($rows as $a => $vals) {
        foreach ($vals as $i => $v) {
            $got = $cat['matrix'][$kind][$a][$objs[$i]] ?? null;
            if ($got === null || abs($got - $v) > 1e-9) {
                $mismatch[] = "$kind $a/{$objs[$i]} expected $v observed " . var_export($got, true);
            }
        }
    }
}
$lv = ['LV-L1' => ['ORB-O1' => 60], 'LV-L2' => ['ORB-O1' => 150, 'ORB-O2' => 130, 'ORB-O3' => 220], 'LV-L3' => ['ORB-O1' => 220, 'ORB-O2' => 200]];
if (($cat['matrix']['LAUNCH_CAPACITY'] ?? []) != array_map(fn($r) => array_map('floatval', $r), $lv)) {
    $mismatch[] = 'LAUNCH_CAPACITY table differs: ' . json_encode($cat['matrix']['LAUNCH_CAPACITY'] ?? null);
}
$gs = ['GS-G1' => [80, 80, 0], 'GS-G2' => [32, 32, 40], 'GS-G3' => [24, 24, 56], 'GS-G4' => [96, 96, 96]];
foreach ($gs as $s => $vals) {
    foreach (['ORB-O1', 'ORB-O2', 'ORB-O3'] as $i => $o) {
        $got = $cat['matrix']['GROUND_CONTACT'][$s][$o] ?? null;
        if ($got === null || abs($got - $vals[$i]) > 1e-9) {
            $mismatch[] = "GROUND_CONTACT $s/$o expected {$vals[$i]} observed " . var_export($got, true);
        }
    }
}
if (($cat['matrix']['CONFLICT']['PWR-P3']['BUS-S'] ?? null) !== 1.0) {
    $mismatch[] = 'CONFLICT PWR-P3/BUS-S missing';
}
foreach ($mismatch as $m) {
    echo "      $m\n";
}
check('SEED', !$mismatch, 'catalogue values vs Document 02: ' . count($mismatch) . ' mismatches');

// ---------------------------------------------------------------- MATRIX code integrity
$known = array_merge(array_keys($cat['components']), array_keys($cat['orbits']), array_keys($cat['launches']),
    array_keys($cat['stations']), array_keys($cat['objectives']));
$bad = [];
foreach (q('SELECT kind, a_code, b_code FROM compatibility_matrix')->fetchAll() as $r) {
    foreach (['a_code', 'b_code'] as $col) {
        if (!in_array($r[$col], $known, true)) {
            $bad[] = "{$r['kind']} {$r[$col]}";
        }
    }
}
check('MATRIX', !$bad, 'all matrix codes exist in the catalogue' . ($bad ? ': ' . implode(', ', $bad) : ''));

// ---------------------------------------------------------------- rules present
$r = rules();
$needed = array_merge(
    array_map(fn($i) => sprintf('H%02d', $i), range(1, 12)),
    array_map(fn($i) => sprintf('W%02d', $i), range(1, 11)),
    ['EV-LAUNCH', 'EV-COMMISSIONED', 'EV-FIRST-DATA', 'EV-MILESTONE', 'EV-BACKLOG', 'EV-STORAGE-FULL', 'EV-ENERGY-LOW',
     'EV-POWER-NEGATIVE', 'EV-BUS-EOL', 'EV-ORBIT-DECAY', 'EV-TARGET-REACHED', 'EV-DISPOSAL-OK', 'EV-DISPOSAL-RISK']
);
$missing = array_values(array_diff($needed, array_keys($r['rules'])));
check('RULES', !$missing && $r['version'] === '1.0.0',
    "rule version {$r['version']}: 12 hard, 11 warnings, 13 events present" . ($missing ? '; missing ' . implode(',', $missing) : ''));

// ---------------------------------------------------------------- DB-02 / DB-03 (rolled back)
$pdo = db();
$pdo->beginTransaction();
try {
    q("INSERT INTO missions (session_key_hash, nickname) VALUES (?, 'db-test')", [str_repeat('0', 64)]);
    $mid = (int) $pdo->lastInsertId();
    $rejected = false;
    try {
        q("INSERT INTO mission_selections (mission_id, item_type, component_id) VALUES (?, 'INSTRUMENT', 999999)", [$mid]);
    } catch (PDOException $e) {
        $rejected = true;
    }
    check('DB-02', $rejected, 'selection for a missing component_id is rejected by the foreign key');

    $rejected = false;
    try {
        q("INSERT INTO components (code, category, name, description, mass_kg, cost_mu) VALUES ('BUS-M','BUS','dup','dup',1,1)");
    } catch (PDOException $e) {
        $rejected = true;
    }
    check('DB-03', $rejected, 'duplicate component code BUS-M is rejected');

    $rejected = false;
    try {
        q("INSERT INTO mission_selections (mission_id, item_type, component_id) VALUES (?, 'STATION', 1)", [$mid]);
    } catch (PDOException $e) {
        $rejected = true;
    }
    check('CHECK', $rejected, 'a STATION selection without station_id is rejected');
} finally {
    $pdo->rollBack();
}

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
