<?php
/**
 * TerraWatch-1 — shared page layout: header, progress steps and four-segment rail.
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 *
 * Variables: $title, $content, optional $step (1–9), optional $segments
 * (['LAUNCH'=>status, 'SPACE'=>..., 'GROUND'=>..., 'USER'=>...]),
 * status one of: todo | ok | warn | fail.
 */
$step     = $step ?? null;
$segments = $segments ?? null;
$segmentLabels = ['LAUNCH' => 'Launch', 'SPACE' => 'Space', 'GROUND' => 'Ground', 'USER' => 'User'];
$statusText = [
    'todo' => ['To do', '○', 'border-slate-600 text-slate-300'],
    'ok'   => ['Complete',    '✓', 'border-emerald-500 text-emerald-300'],
    'warn' => ['Warning',     '!', 'border-amber-400 text-amber-300'],
    'fail' => ['Failure',     '✕', 'border-rose-500 text-rose-300'],
];
$steps = ['Landing', 'Briefing', 'Objective', 'Requirements', 'Designer', 'Preflight', 'Simulation', 'Results', 'Debrief'];
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · Space Mission Design Game</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:p-2 focus:bg-white focus:text-black">Skip to content</a>
<header class="border-b border-slate-800 bg-slate-900/80">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-3">
        <a href="/" class="font-semibold tracking-wide">TerraWatch-1 <span class="text-slate-400 font-normal">· Space Mission Design Game</span></a>
        <nav class="flex gap-4 text-sm text-slate-300 md:text-base">
            <a class="hover:text-white underline-offset-4 hover:underline" href="/glossary.php">Help</a>
            <a class="hover:text-white underline-offset-4 hover:underline" href="/sources.php">Sources</a>
            <a class="hover:text-white underline-offset-4 hover:underline" href="/credits.php">Credits</a>
        </nav>
    </div>
    <?php if ($step !== null): ?>
    <?php // Phone: one compact line with a progress bar. Tablet/laptop: all nine steps. ?>
    <div class="mx-auto max-w-6xl px-4 pb-3 md:hidden">
        <p class="text-sm text-slate-300">Step <?= (int) $step ?> of <?= count($steps) ?>: <span class="font-semibold text-white"><?= e($steps[$step - 1]) ?></span></p>
        <div class="mt-1 h-1.5 rounded bg-slate-700" aria-hidden="true"><div class="h-1.5 rounded bg-sky-500" style="width:<?= round($step / count($steps) * 100) ?>%"></div></div>
    </div>
    <ol class="mx-auto hidden max-w-6xl flex-wrap gap-1 px-4 pb-3 text-sm md:flex lg:text-base" aria-label="Progress">
        <?php foreach ($steps as $i => $label): $n = $i + 1; ?>
            <li class="whitespace-nowrap rounded px-3 py-1 <?= $n === $step ? 'bg-sky-600 font-semibold text-white' : ($n < $step ? 'text-slate-200' : 'text-slate-400') ?>"
                <?= $n === $step ? 'aria-current="step"' : '' ?>><?= $n ?>. <?= e($label) ?></li>
        <?php endforeach; ?>
    </ol>
    <?php endif; ?>
    <?php if ($segments !== null): ?>
    <div id="segment-rail" class="mx-auto grid max-w-6xl grid-cols-4 gap-2 px-4 pb-3" aria-label="Mission segments">
        <?php foreach ($segmentLabels as $code => $label): $s = $statusText[$segments[$code] ?? 'todo']; ?>
            <div data-seg="<?= e($code) ?>" class="rounded border-2 <?= $s[2] ?> px-2 py-1 text-center text-sm md:py-1.5 md:text-base">
                <span class="font-semibold"><?= e($label) ?></span>
                <span data-status class="block whitespace-nowrap text-xs md:text-sm"><span aria-hidden="true"><?= $s[1] ?></span> <?= e($s[0]) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</header>
<?php $missionHeader = $missionHeader ?? null; if ($missionHeader): ?>
<div class="border-b border-slate-800 bg-slate-900/40">
    <div class="mx-auto flex max-w-6xl flex-wrap gap-x-6 gap-y-1 px-4 py-2 text-sm">
        <span><span class="text-slate-400">Mission lead:</span> <?= e($missionHeader['nickname']) ?></span>
        <span><span class="text-slate-400">Objective:</span> <?= e($missionHeader['objective']) ?></span>
        <span><span class="text-slate-400">Difficulty:</span> <?= e($missionHeader['difficulty']) ?></span>
        <span title="Mission Units are fictional game currency"><span class="text-slate-400">Budget:</span>
            <span id="hdr-budget"><?= $missionHeader['cost'] !== null
                ? e(num($missionHeader['budget'] - $missionHeader['cost'], 0)) . ' of ' . e(num($missionHeader['budget'], 0)) . ' MU left'
                : e(num($missionHeader['budget'], 0)) . ' MU' ?></span>
            <span class="text-xs text-slate-400">(fictional game currency)</span></span>
    </div>
</div>
<?php endif; ?>
<main id="main" class="mx-auto max-w-6xl px-4 py-6">
    <?php $flashMsg = function_exists('flash') ? flash() : null; if ($flashMsg): ?>
        <div role="status" class="mb-4 rounded border border-sky-700 bg-sky-950 px-3 py-2 text-sm"><?= e($flashMsg) ?></div>
    <?php endif; ?>
    <?= $content ?>
</main>
<footer class="mx-auto max-w-6xl px-4 py-8 text-xs text-slate-400">
    Educational simulator built for the NASA Space Apps Challenge 2026 (Challenge #13). Simplified educational model,
    not professional engineering software and not a substitute for NASA's General Mission Analysis Tool.
    Mission Units (MU) are fictional game currency. Not endorsed or certified by NASA.
</footer>
</body>
</html>
