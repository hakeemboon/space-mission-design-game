<?php
/**
 * TerraWatch-1 — screen 3: Objective selection. Creates the mission draft.
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/src/app.php';

if (empty($_SESSION['nickname']) || empty($_SESSION['difficulty'])) {
    redirect('/');
}
$cat = catalogue();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $obj = (string) ($_POST['objective'] ?? '');
    if (isset($cat['objectives'][$obj])) {
        $_SESSION['mission_id'] = mission_create($_SESSION['nickname'], $obj, $_SESSION['difficulty']);
        redirect('/requirements.php');
    }
    flash('Choose one of the three objectives.');
    redirect('/objective.php');
}
$hints = [
    'OBJ-LAND'  => ['Wants consistent lighting to compare the same place over time', 'Needs a lot of data sent to the ground', 'Best with a multispectral imager'],
    'OBJ-COAST' => ['Needs fine colour detail over narrow coastal strips', 'Needs the most data of all three', 'The heaviest, most power-hungry instrument'],
    'OBJ-CLOUD' => ['Needs frequent looks, including at night', 'Needs the least data', 'Thermal infrared works in darkness'],
];
$tag = ['OBJ-LAND' => ['info', 'Medium'], 'OBJ-COAST' => ['fail', 'Hardest'], 'OBJ-CLOUD' => ['ok', 'Most forgiving']];

page_start(); ?>
<h1 class="text-2xl font-bold">Choose your science objective</h1>
<p class="mt-2 text-slate-300">The objective drives every later decision: what you measure, from where, and how much data the users need.</p>
<form method="post" class="mt-6">
    <?= csrf_field() ?>
    <div class="grid gap-4 md:grid-cols-3">
        <?php foreach ($cat['objectives'] as $code => $o): ?>
            <label class="flex cursor-pointer flex-col rounded-lg border-2 border-slate-700 bg-slate-900 p-4 hover:border-sky-500 has-[:checked]:border-sky-400 has-[:checked]:bg-sky-950/40">
                <span class="flex items-start justify-between gap-2">
                    <span class="text-lg font-semibold"><?= e($o['name']) ?></span>
                    <?= status_badge($tag[$code][0], $tag[$code][1]) ?>
                </span>
                <span class="mt-2 text-sm italic text-slate-300">“<?= e($o['science_question']) ?>”</span>
                <span class="mt-3 text-xs text-slate-400">Users: <?= e($o['user_personas']) ?></span>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-300">
                    <?php foreach ($hints[$code] as $h): ?><li><?= e($h) ?></li><?php endforeach; ?>
                </ul>
                <span class="mt-3 text-xs text-slate-400">Required: <?= e(num($o['required_mb_per_day'], 0)) ?> MB delivered per day</span>
                <span class="mt-4 flex items-center gap-2 text-sm font-semibold"><input type="radio" name="objective" value="<?= e($code) ?>" aria-label="<?= e($o['name']) ?>" required> Select</span>
            </label>
        <?php endforeach; ?>
    </div>
    <button class="<?= btn_primary() ?> mt-6" type="submit">Continue to requirements</button>
</form>
<?php page_end('Objective', 3);
