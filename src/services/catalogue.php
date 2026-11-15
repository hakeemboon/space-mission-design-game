<?php
/**
 * TerraWatch-1 — catalogue service (module M3).
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 *
 * Loads every catalogue row, the compatibility matrix and the current rule set,
 * keyed by stable code. Numeric columns are cast to float/int so the engine
 * never does arithmetic on strings. Loaded once per request.
 */
declare(strict_types=1);

const CATALOGUE_NUMERIC = [
    'required_mb_per_day', 'budget_mu', 'target_months', 'schedule_limit_months',
    'mass_kg', 'cost_mu', 'power_w', 'payload_limit_kg', 'instrument_slots', 'peak_capacity_w',
    'storage_gb', 'design_life_months', 'dev_months', 'data_rate_mbps', 'generation_w', 'battery_wh',
    'provides_disposal', 'is_active', 'is_enabled', 'altitude_km', 'period_min', 'orbits_per_day',
    'sunlight_h_per_day', 'eclipse_min', 'decay_months', 'needs_disposal_aid',
    'supports_s', 'supports_x', 'id', 'sort_order', 'is_challenge_resource', 'source_id',
];

function cat_cast(array $row): array
{
    foreach ($row as $k => $v) {
        if ($v !== null && in_array($k, CATALOGUE_NUMERIC, true)) {
            $row[$k] = (strpos((string) $v, '.') !== false) ? (float) $v : (int) $v;
        }
    }
    return $row;
}

/** Rows of a table keyed by code, in sort order. */
function cat_table(string $table): array
{
    $allowed = ['objectives', 'difficulty_settings', 'components', 'orbit_profiles',
        'launch_profiles', 'ground_stations', 'reference_sources'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException("Unknown catalogue table $table");
    }
    $out = [];
    foreach (q("SELECT * FROM `$table` ORDER BY sort_order, id")->fetchAll() as $row) {
        $out[$row['code']] = cat_cast($row);
    }
    return $out;
}

/**
 * Full catalogue: objectives, difficulties, components (all and by category),
 * orbits, launches, stations and the compatibility matrix as
 * matrix[kind][a_code][b_code] = float.
 */
function catalogue(): array
{
    static $cat = null;
    if ($cat !== null) {
        return $cat;
    }
    $components = cat_table('components');
    $byCategory = ['BUS' => [], 'INSTRUMENT' => [], 'POWER' => [], 'COMMS' => [], 'ADDON' => []];
    foreach ($components as $code => $c) {
        $byCategory[$c['category']][$code] = $c;
    }
    $matrix = [];
    foreach (q('SELECT kind, a_code, b_code, value FROM compatibility_matrix')->fetchAll() as $r) {
        $matrix[$r['kind']][$r['a_code']][$r['b_code']] = (float) $r['value'];
    }
    $cat = [
        'objectives'   => cat_table('objectives'),
        'difficulties' => cat_table('difficulty_settings'),
        'components'   => $components,
        'byCategory'   => $byCategory,
        'orbits'       => cat_table('orbit_profiles'),
        'launches'     => cat_table('launch_profiles'),
        'stations'     => cat_table('ground_stations'),
        'matrix'       => $matrix,
    ];
    return $cat;
}

/** The current rule version row plus its rules keyed by code. */
function rules(): array
{
    static $rules = null;
    if ($rules !== null) {
        return $rules;
    }
    $version = q('SELECT * FROM rule_versions WHERE is_current = 1 LIMIT 1')->fetch();
    if (!$version) {
        throw new RuntimeException('No current rule version.');
    }
    $byCode = [];
    $params = [];
    foreach (q('SELECT * FROM game_rules WHERE rule_version_id = ? ORDER BY sort_order', [$version['id']])->fetchAll() as $r) {
        $byCode[$r['rule_code']] = $r;
        if ($r['rule_type'] === 'PARAM') {
            $params[$r['rule_code']] = $r['param_value'] !== null ? (float) $r['param_value'] : $r['message_template'];
        }
    }
    $rules = [
        'version_id'        => (int) $version['id'],
        'version'           => $version['version'],
        'catalogue_version' => $version['catalogue_version'],
        'rules'             => $byCode,
        'params'            => $params,
    ];
    return $rules;
}

/** A numeric parameter from game_rules (throws if missing, never guesses). */
function param(string $code): float
{
    $p = rules()['params'];
    if (!array_key_exists($code, $p) || !is_float($p[$code])) {
        throw new RuntimeException("Missing numeric parameter $code in game_rules.");
    }
    return $p[$code];
}

/** Fill {placeholders} in a rule's text. */
function rule_text(string $code, array $vars = [], string $field = 'message_template'): string
{
    $rule = rules()['rules'][$code] ?? null;
    if ($rule === null) {
        throw new RuntimeException("Missing rule text $code in game_rules.");
    }
    $text = (string) $rule[$field];
    foreach ($vars as $k => $v) {
        $text = str_replace('{' . $k . '}', (string) $v, $text);
    }
    return $text;
}

/** Fact cards for a screen, with their source. */
function fact_cards(?string $screen = null): array
{
    $sql = 'SELECT f.code, f.statement, f.classification, f.screen, f.source_detail,
                   s.title AS source_title, s.url AS source_url, s.publisher AS source_publisher
            FROM fact_cards f JOIN reference_sources s ON s.id = f.source_id';
    return $screen === null
        ? q($sql . ' ORDER BY f.code')->fetchAll()
        : q($sql . ' WHERE f.screen = ? ORDER BY f.code', [$screen])->fetchAll();
}
