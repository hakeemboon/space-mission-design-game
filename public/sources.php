<?php
/**
 * TerraWatch-1 — Mission Library: NASA sources, what each shaped, and the fact cards.
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/src/app.php';

$sources = cat_table('reference_sources');
$facts = fact_cards();
$typeWord = ['FACTUAL' => 'Factual source', 'INSPIRATION' => 'Educational inspiration', 'TECHNICAL' => 'Technical reference'];

page_start(); ?>
<h1 class="text-2xl font-bold">Mission Library: the sources behind the game</h1>
<p class="mt-2 max-w-3xl text-slate-300">The game's structure, progression and facts come from the NASA resources listed for the Space Apps
    “Space Mission Design Game” challenge. It uses them as concepts, facts and process models; it does not load a NASA dataset.
    All component values, costs and scores are fictional game values.</p>

<h2 class="mt-6 text-lg font-semibold">Challenge resources</h2>
<div class="mt-2 grid gap-3 md:grid-cols-2">
<?php foreach ($sources as $src): if (!(int) $src['is_challenge_resource']) continue; ?>
    <article class="rounded-lg border border-slate-700 bg-slate-900 p-4 text-sm">
        <h3 class="font-semibold"><a class="underline hover:text-sky-300" target="_blank" rel="noopener" href="<?= e($src['url']) ?>"><?= e($src['title']) ?></a></h3>
        <p class="text-xs text-slate-400"><?= e($src['publisher']) ?> · <?= e($typeWord[$src['source_type']]) ?><?= $src['source_date'] ? ' · ' . e($src['source_date']) : '' ?></p>
        <p class="mt-2 text-slate-300"><span class="text-slate-400">What it shaped:</span> <?= e($src['how_used']) ?></p>
    </article>
<?php endforeach; ?>
</div>

<h2 class="mt-6 text-lg font-semibold">Additional NASA references</h2>
<ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
<?php foreach ($sources as $src): if ((int) $src['is_challenge_resource']) continue; ?>
    <li><a class="underline hover:text-sky-300" target="_blank" rel="noopener" href="<?= e($src['url']) ?>"><?= e($src['title']) ?></a> (<?= e($src['publisher']) ?>): <?= e($src['how_used']) ?></li>
<?php endforeach; ?>
</ul>

<h2 class="mt-6 text-lg font-semibold">Fact cards (NASA facts used in the game)</h2>
<div class="mt-2 grid gap-3 md:grid-cols-2"><?php foreach ($facts as $f) { echo fact_card_html($f); } ?></div>

<h2 class="mt-6 text-lg font-semibold">How to read the numbers</h2>
<ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-300">
    <li><strong>NASA fact:</strong> stated by a NASA source that was opened and read (the fact cards above).</li>
    <li><strong>Educational approximation:</strong> a simplified physics model, such as orbital period and time in sunlight from a circular orbit.</li>
    <li><strong>Game value:</strong> fictional, chosen so the trade-offs are visible: every mass, power, data rate, cost (MU), lifetime and score.</li>
</ul>
<?php page_end('Sources');
