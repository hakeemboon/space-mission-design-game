<?php
/**
 * TerraWatch-1 — screen 9: Debrief and redesign.
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/src/app.php';

$run = owned_run(isset($_GET['run']) ? (int) $_GET['run'] : (isset($_POST['run']) ? (int) $_POST['run'] : null));
if (!$run) {
    flash('That mission run was not found in this session.');
    redirect('/');
}
$m = mission_load((int) $run['mission_id']);
$cat = catalogue();
$s = $run['summary'];

// Next enabled difficulty after a success.
$order = array_keys($cat['difficulties']);
$pos = array_search($m['difficulty'], $order, true);
$next = null;
for ($i = $pos + 1; $i < count($order); $i++) {
    if ((int) $cat['difficulties'][$order[$i]]['is_enabled'] === 1) {
        $next = $order[$i];
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'redesign') {
        $_SESSION['mission_id'] = mission_copy($m);
        $seg = strtolower($s['feedback'][0]['segment'] ?? 'space');
        $seg = in_array($seg, ['launch', 'space', 'ground', 'user'], true) ? $seg : 'space';
        flash('Redesign started: this is a copy of your flown design. Change what the debrief suggested, then launch again.');
        redirect('/designer.php#seg-' . $seg);
    }
    if ($action === 'raise' && $next !== null && $run['outcome'] === 'SUCCESS') {
        $_SESSION['difficulty'] = $next;
        $_SESSION['mission_id'] = mission_copy($m, $next);
        flash('Difficulty raised to ' . $cat['difficulties'][$next]['name'] . '. Your design was copied; check the new budget and target.');
        redirect('/designer.php');
    }
    if ($action === 'objective') {
        $_SESSION['difficulty'] = $m['difficulty'];
        unset($_SESSION['mission_id']);
        redirect('/objective.php');
    }
    redirect('/debrief.php?run=' . (int) $run['id']);
}

$m['_cost'] = $s['calc']['total_cost_mu'];
$personas = array_map('trim', explode(';', $m ? $cat['objectives'][$m['objective']]['user_personas'] : ''));
$reaction = [
    'SUCCESS' => 'This gives us what we need to answer our question. Thank you.',
    'PARTIAL' => 'We can use some of this, but there are gaps we will have to work around.',
    'NOT_MET' => 'We cannot answer our question with this. Too much is missing.',
][$run['outcome']];
$history = session_runs($m['objective']);
$prev = null;

page_start(); ?>
<h1 class="text-2xl font-bold">Debrief</h1>
<p class="mt-1 text-slate-300"><?= e(['SUCCESS' => 'Mission success.', 'PARTIAL' => 'Partial success.', 'NOT_MET' => 'Objective not met.'][$run['outcome']]) ?>
    Science <?= e(number_format((float) $run['science_score'], 2)) ?>, mission score <?= e(number_format((float) $run['mission_score'], 2)) ?>.</p>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <section aria-labelledby="h-sugg">
        <h2 id="h-sugg" class="text-lg font-semibold">What to try next</h2>
        <?php if ($s['feedback']): ?>
            <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm">
                <?php foreach ($s['feedback'] as $f): ?>
                    <li><span class="font-semibold"><?= e($f['text']) ?></span>
                        <span class="block text-xs text-slate-400">Because of: <?= e($f['source']) ?> · <?= e(SEGMENTS[$f['segment']] ?? 'All') ?> segment</span></li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <p class="mt-2 text-sm">No weak spots left. Stretch question: can you reach the same science for 100 MU less?</p>
        <?php endif; ?>
        <form method="post" class="mt-4 flex flex-wrap gap-3">
            <?= csrf_field() ?><input type="hidden" name="run" value="<?= (int) $run['id'] ?>">
            <button class="<?= btn_primary() ?>" name="action" value="redesign" type="submit">Redesign this mission</button>
            <button class="<?= btn_secondary() ?>" name="action" value="objective" type="submit">Try another objective</button>
            <?php if ($run['outcome'] === 'SUCCESS' && $next !== null): ?>
                <button class="<?= btn_secondary() ?>" name="action" value="raise" type="submit">Raise difficulty to <?= e($cat['difficulties'][$next]['name']) ?></button>
            <?php endif; ?>
        </form>
    </section>
    <section aria-labelledby="h-users">
        <h2 id="h-users" class="text-lg font-semibold">What the users say</h2>
        <ul class="mt-2 space-y-2 text-sm">
            <?php foreach ($personas as $p): ?>
                <li class="rounded border border-slate-700 bg-slate-900 p-3"><span class="font-semibold"><?= e(ucfirst($p)) ?>:</span> “<?= e($reaction) ?>”</li>
            <?php endforeach; ?>
        </ul>
        <p class="mt-1 text-xs text-slate-400">Fictional users, described by job title only.</p>
    </section>
</div>

<section class="mt-6" aria-labelledby="h-compare">
    <h2 id="h-compare" class="text-lg font-semibold">Your runs for this objective</h2>
    <div class="mt-2 overflow-x-auto">
    <table class="w-full min-w-[640px] text-left text-sm">
        <thead class="border-b border-slate-700 text-slate-400"><tr><th class="py-2 pr-3">Run</th><th class="pr-3">Difficulty</th><th class="pr-3">Design</th><th class="pr-3 text-right">Science</th><th class="pr-3 text-right">Mission</th><th>Outcome</th></tr></thead>
        <tbody>
        <?php foreach ($history as $i => $h):
            $snap = json_decode($h['input_snapshot'], true);
            $delta = $prev !== null ? (float) $h['science_score'] - (float) $prev['science_score'] : null; ?>
            <tr class="border-b border-slate-800 <?= (int) $h['id'] === (int) $run['id'] ? 'bg-sky-950/40' : '' ?>">
                <td class="py-2 pr-3"><a class="underline" href="/results.php?run=<?= (int) $h['id'] ?>">#<?= $i + 1 ?></a></td>
                <td class="pr-3"><?= e($h['difficulty']) ?></td>
                <td class="pr-3 text-slate-300"><?= e(design_summary($snap['input'], $cat)) ?></td>
                <td class="pr-3 text-right"><?= e(number_format((float) $h['science_score'], 2)) ?>
                    <?php if ($delta !== null && abs($delta) >= 0.005): ?><span class="text-xs <?= $delta > 0 ? 'text-emerald-300' : 'text-rose-300' ?>">(<?= $delta > 0 ? '+' : '' ?><?= e(number_format($delta, 2)) ?>)</span><?php endif; ?></td>
                <td class="pr-3 text-right"><?= e(number_format((float) $h['mission_score'], 2)) ?></td>
                <td><?= e(['SUCCESS' => 'Success', 'PARTIAL' => 'Partial', 'NOT_MET' => 'Not met'][$h['outcome']]) ?></td>
            </tr>
        <?php $prev = $h; endforeach; ?>
        </tbody>
    </table>
    </div>
</section>

<section class="mt-6 rounded-lg border border-slate-700 bg-slate-900 p-4 text-sm" aria-labelledby="h-real">
    <h2 id="h-real" class="font-semibold">What real mission designers do</h2>
    <p class="mt-1 text-slate-300">Real teams work the same loop, with far more detail: concept-study teams estimate every subsystem's mass, power and data rate, compare options,
        and settle on one point design. Professional trajectory and mission analysis uses tools such as NASA's General Mission Analysis Tool, which this simplified game does not replace.</p>
    <p class="mt-2"><a class="text-sky-300 underline" href="/sources.php">See the NASA sources behind this game</a></p>
</section>
<?php page_end('Debrief', 9, segment_status(['warnings' => $s['warnings'], 'hard' => []], $m), $m);
