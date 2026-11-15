<?php
/**
 * TerraWatch-1 — simulation run storage (module M6).
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 *
 * A flight-ready result is stored as one immutable simulation_runs row plus exactly
 * L simulation_metrics rows and its ordered simulation_events, in one transaction.
 * Runs are never updated or deleted by the application.
 */
declare(strict_types=1);

/** Store a flight-ready engine result for a mission. Returns the new run id. */
function store_run(int $missionId, array $result): int
{
    if (empty($result['flight_ready']) || empty($result['snapshot'])) {
        throw new LogicException('Only flight-ready results with a snapshot are stored.');
    }
    $pdo = db();
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $next = (int) q('SELECT COALESCE(MAX(run_number), 0) + 1 FROM simulation_runs WHERE mission_id = ? FOR UPDATE', [$missionId])->fetchColumn();
        $versionId = (int) q('SELECT id FROM rule_versions WHERE version = ?', [$result['snapshot']['rule_version']])->fetchColumn();
        $summary = [
            'calc' => $result['calc'], 'warnings' => $result['warnings'], 'prediction' => $result['prediction'],
            'failure_month' => $result['failure_month'], 'lifetime_text' => $result['lifetime_text'],
            'totals' => $result['totals'], 'factors' => $result['factors'], 'disposal_ok' => $result['disposal_ok'],
            'requirements' => $result['requirements'], 'user' => $result['user'], 'feedback' => $result['feedback'],
        ];
        q('INSERT INTO simulation_runs (mission_id, run_number, rule_version_id, input_snapshot, input_hash,
              operational_months, end_reason, science_score, efficiency_score, responsibility_score, mission_score,
              outcome, summary) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', [
            $missionId, $next, $versionId, canonical_json($result['snapshot']), $result['hash'],
            $result['operational_months'], $result['end_reason'], $result['scores']['science'],
            $result['scores']['efficiency'], $result['scores']['responsibility'], $result['scores']['mission'],
            $result['outcome'], canonical_json($summary),
        ]);
        $runId = (int) $pdo->lastInsertId();
        $m = $pdo->prepare('INSERT INTO simulation_metrics (run_id, month_no, phase, generation_wh, demand_wh,
              generated_mb, sent_mb, backlog_mb, lost_mb) VALUES (?,?,?,?,?,?,?,?,?)');
        foreach ($result['months'] as $row) {
            $m->execute([$runId, $row['month'], $row['phase'], $row['generation_wh'], $row['demand_wh'],
                $row['generated_mb'], $row['sent_mb'], $row['backlog_mb'], $row['lost_mb']]);
        }
        $e = $pdo->prepare('INSERT INTO simulation_events (run_id, seq, month_no, event_code, severity, segment, message)
              VALUES (?,?,?,?,?,?,?)');
        foreach ($result['events'] as $ev) {
            $e->execute([$runId, $ev['seq'], $ev['month'], $ev['code'], $ev['severity'], $ev['segment'], $ev['message']]);
        }
        q("UPDATE missions SET status = 'FLOWN' WHERE id = ?", [$missionId]);
        if ($ownTransaction) {
            $pdo->commit();
        }
        return $runId;
    } catch (Throwable $t) {
        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $t;
    }
}

/** Load a stored run with its metrics and events. */
function load_run(int $runId): ?array
{
    $run = q('SELECT * FROM simulation_runs WHERE id = ?', [$runId])->fetch();
    if (!$run) {
        return null;
    }
    $run['summary'] = json_decode($run['summary'], true, 512, JSON_THROW_ON_ERROR);
    $run['snapshot'] = json_decode($run['input_snapshot'], true, 512, JSON_THROW_ON_ERROR);
    unset($run['input_snapshot']);
    $run['months'] = q('SELECT month_no, phase, generation_wh, demand_wh, generated_mb, sent_mb, backlog_mb, lost_mb
                        FROM simulation_metrics WHERE run_id = ? ORDER BY month_no', [$runId])->fetchAll();
    $run['events'] = q('SELECT seq, month_no, event_code, severity, segment, message
                        FROM simulation_events WHERE run_id = ? ORDER BY seq', [$runId])->fetchAll();
    return $run;
}
