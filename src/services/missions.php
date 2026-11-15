<?php
/**
 * TerraWatch-1 — mission service (module M4): create, load, save draft, copy for redesign.
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 *
 * No accounts: a mission belongs to the browser session that created it. Ownership is a
 * random per-session key whose SHA-256 is stored on the mission. Every load checks it.
 */
declare(strict_types=1);

function owner_hash(): string
{
    if (empty($_SESSION['owner_key'])) {
        $_SESSION['owner_key'] = bin2hex(random_bytes(32));
    }
    return hash('sha256', $_SESSION['owner_key']);
}

function mission_create(string $nickname, string $objectiveCode, string $difficultyCode): int
{
    $cat = catalogue();
    q('INSERT INTO missions (session_key_hash, nickname, objective_id, difficulty_id) VALUES (?,?,?,?)', [
        owner_hash(), $nickname, $cat['objectives'][$objectiveCode]['id'], $cat['difficulties'][$difficultyCode]['id'],
    ]);
    return (int) db()->lastInsertId();
}

/** Load a mission owned by this session, with its design as catalogue codes. Null if not found/not owned. */
function mission_load(?int $id): ?array
{
    if (!$id) {
        return null;
    }
    $m = q('SELECT m.*, o.code AS objective, d.code AS difficulty,
                   b.code AS bus, p.code AS power, c.code AS comms, ob.code AS orbit, l.code AS launch
            FROM missions m
            LEFT JOIN objectives o ON o.id = m.objective_id
            LEFT JOIN difficulty_settings d ON d.id = m.difficulty_id
            LEFT JOIN components b ON b.id = m.bus_id
            LEFT JOIN components p ON p.id = m.power_id
            LEFT JOIN components c ON c.id = m.comms_id
            LEFT JOIN orbit_profiles ob ON ob.id = m.orbit_id
            LEFT JOIN launch_profiles l ON l.id = m.launch_id
            WHERE m.id = ? AND m.session_key_hash = ?', [$id, owner_hash()])->fetch();
    if (!$m) {
        return null;
    }
    $m['instruments'] = [];
    $m['addons'] = [];
    $m['stations'] = [];
    $rows = q('SELECT s.item_type, c.code AS ccode, g.code AS gcode FROM mission_selections s
               LEFT JOIN components c ON c.id = s.component_id
               LEFT JOIN ground_stations g ON g.id = s.station_id
               WHERE s.mission_id = ? ORDER BY s.id', [$id])->fetchAll();
    foreach ($rows as $r) {
        if ($r['item_type'] === 'INSTRUMENT') {
            $m['instruments'][] = $r['ccode'];
        } elseif ($r['item_type'] === 'ADDON') {
            $m['addons'][] = $r['ccode'];
        } else {
            $m['stations'][] = $r['gcode'];
        }
    }
    $m['imaging'] = $m['imaging_minutes'] !== null ? (int) $m['imaging_minutes'] : null;
    return $m;
}

/** The current session's active mission, or null. */
function current_mission(): ?array
{
    return mission_load(isset($_SESSION['mission_id']) ? (int) $_SESSION['mission_id'] : null);
}

/** Engine input built from the stored mission (never from browser-calculated values). */
function mission_input(array $m): array
{
    return [
        'objective' => $m['objective'], 'difficulty' => $m['difficulty'], 'bus' => $m['bus'],
        'instruments' => $m['instruments'], 'power' => $m['power'], 'comms' => $m['comms'],
        'stations' => $m['stations'], 'orbit' => $m['orbit'], 'launch' => $m['launch'],
        'imaging' => $m['imaging'], 'addons' => $m['addons'],
    ];
}

/**
 * Save a posted design onto a draft. Every value is checked against the catalogue on the
 * server; unknown codes are dropped (rule H01 then reports the gap). Limits on counts are
 * enforced by the engine, so a player sees the rule message instead of silent trimming.
 */
function mission_save_design(int $id, array $post): void
{
    $cat = catalogue();
    $pick = fn($v, array $pool) => (is_string($v) && isset($pool[$v])) ? (int) $pool[$v]['id'] : null;
    $multi = function ($v, array $pool): array {
        if (!is_array($v)) {
            return [];
        }
        $ids = [];
        foreach (array_slice(array_values($v), 0, 6) as $code) {
            if (is_string($code) && isset($pool[$code])) {
                $ids[] = (int) $pool[$code]['id'];
            }
        }
        return array_values(array_unique($ids));
    };
    $options = array_map('intval', explode(',', (string) rules()['params']['IMAGING_OPTIONS']));
    $img = isset($post['imaging']) && ctype_digit((string) $post['imaging']) && in_array((int) $post['imaging'], $options, true)
        ? (int) $post['imaging'] : null;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        q("UPDATE missions SET bus_id=?, power_id=?, comms_id=?, orbit_id=?, launch_id=?, imaging_minutes=?
           WHERE id=? AND session_key_hash=? AND status='DRAFT'", [
            $pick($post['bus'] ?? null, $cat['byCategory']['BUS']),
            $pick($post['power'] ?? null, $cat['byCategory']['POWER']),
            $pick($post['comms'] ?? null, $cat['byCategory']['COMMS']),
            $pick($post['orbit'] ?? null, $cat['orbits']),
            $pick($post['launch'] ?? null, $cat['launches']),
            $img, $id, owner_hash(),
        ]);
        q('DELETE FROM mission_selections WHERE mission_id = ?', [$id]);
        foreach ($multi($post['instruments'] ?? [], $cat['byCategory']['INSTRUMENT']) as $cid) {
            q("INSERT INTO mission_selections (mission_id, item_type, component_id) VALUES (?, 'INSTRUMENT', ?)", [$id, $cid]);
        }
        foreach ($multi($post['addons'] ?? [], $cat['byCategory']['ADDON']) as $cid) {
            q("INSERT INTO mission_selections (mission_id, item_type, component_id) VALUES (?, 'ADDON', ?)", [$id, $cid]);
        }
        foreach ($multi($post['stations'] ?? [], $cat['stations']) as $sid) {
            q("INSERT INTO mission_selections (mission_id, item_type, station_id) VALUES (?, 'STATION', ?)", [$id, $sid]);
        }
        $pdo->commit();
    } catch (Throwable $t) {
        $pdo->rollBack();
        throw $t;
    }
}

/** Copy a mission into a new draft (redesign). Optionally change difficulty or objective. */
function mission_copy(array $m, ?string $difficulty = null): int
{
    $cat = catalogue();
    $diffId = $difficulty !== null ? $cat['difficulties'][$difficulty]['id'] : $m['difficulty_id'];
    q("INSERT INTO missions (session_key_hash, nickname, objective_id, difficulty_id, bus_id, power_id, comms_id,
          orbit_id, launch_id, imaging_minutes, status, parent_mission_id)
       VALUES (?,?,?,?,?,?,?,?,?,?, 'DRAFT', ?)", [
        owner_hash(), $m['nickname'], $m['objective_id'], $diffId, $m['bus_id'], $m['power_id'], $m['comms_id'],
        $m['orbit_id'], $m['launch_id'], $m['imaging_minutes'], $m['id'],
    ]);
    $new = (int) db()->lastInsertId();
    q('INSERT INTO mission_selections (mission_id, item_type, component_id, station_id)
       SELECT ?, item_type, component_id, station_id FROM mission_selections WHERE mission_id = ?', [$new, $m['id']]);
    return $new;
}

/** Load a run only if its mission belongs to this session. */
function owned_run(?int $runId): ?array
{
    if (!$runId) {
        return null;
    }
    $ok = q('SELECT r.id FROM simulation_runs r JOIN missions m ON m.id = r.mission_id
             WHERE r.id = ? AND m.session_key_hash = ?', [$runId, owner_hash()])->fetchColumn();
    return $ok ? load_run((int) $ok) : null;
}

/** All runs flown by this session for an objective, oldest first. */
function session_runs(string $objectiveCode): array
{
    return q('SELECT r.id, r.mission_id, r.science_score, r.mission_score, r.outcome, r.operational_months,
                     r.created_at, d.name AS difficulty, r.input_snapshot
              FROM simulation_runs r
              JOIN missions m ON m.id = r.mission_id
              JOIN objectives o ON o.id = m.objective_id
              JOIN difficulty_settings d ON d.id = m.difficulty_id
              WHERE m.session_key_hash = ? AND o.code = ?
              ORDER BY r.id', [owner_hash(), $objectiveCode])->fetchAll();
}
