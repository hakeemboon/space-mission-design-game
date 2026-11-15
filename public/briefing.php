<?php
/**
 * TerraWatch-1 — screen 2: Mission briefing (introduces the four segments).
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/src/app.php';

if (empty($_SESSION['nickname'])) {
    redirect('/');
}
$segments = [
    'Launch' => ['A rocket carries the satellite to its orbit. Each launcher only goes to certain orbits and can only lift so much mass.', 'You choose: launcher and orbit.'],
    'Space'  => ['The satellite itself: a spacecraft bus, science instruments, a power system and a radio.', 'You choose: bus, instruments, power, radio, add-ons and imaging time.'],
    'Ground' => ['Ground stations talk to the satellite when it passes overhead and download its data.', 'You choose: one or two ground stations.'],
    'User'   => ['Scientists and other people use the data to answer real questions.', 'Your objective decides who the users are and how much data they need.'],
];
$f01 = fact_cards('briefing');

page_start(); ?>
<div class="grid gap-6 md:grid-cols-[1fr_minmax(0,22rem)] md:items-center">
    <div>
        <h1 class="text-2xl font-bold">Mission briefing</h1>
        <p class="mt-3 max-w-3xl text-slate-300">An Earth-science agency needs a new observation satellite, and you, <?= e($_SESSION['nickname']) ?>, are its Mission Design Lead.
            Your job is not just to build a spacecraft: a science mission only works if every part of the system works together.</p>
    </div>
    <figure>
        <img src="/assets/img/satellite.webp" width="1200" height="797" loading="lazy"
             alt="A small generic Earth-observation satellite with two solar panels above Earth (SI-generated image)"
             class="w-full rounded-lg border border-slate-700 bg-slate-900">
        <figcaption class="mt-1 text-xs text-slate-400">Artist's impression of a fictional satellite. All hardware in this game is fictional.</figcaption>
    </figure>
</div>
<h2 class="mt-6 text-lg font-semibold">The four mission segments</h2>
<div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <?php $i = 1; foreach ($segments as $name => [$what, $choose]): ?>
        <details class="rounded-lg border border-slate-700 bg-slate-900 p-4" open>
            <summary class="cursor-pointer font-semibold"><?= $i++ ?>. <?= e($name) ?> segment</summary>
            <p class="mt-2 text-sm text-slate-300"><?= e($what) ?></p>
            <p class="mt-2 text-xs text-sky-300"><?= e($choose) ?></p>
        </details>
    <?php endforeach; ?>
</div>
<p class="mt-4 text-sm text-slate-400">Data flows from Space to Ground to the Users. Hardware flows from Launch to Space. If any link is weak, the users do not get what they need, and you redesign.</p>
<div class="mt-4 max-w-xl"><?php foreach ($f01 as $f) { echo fact_card_html($f); } ?></div>
<div class="mt-6"><a class="<?= btn_primary() ?>" href="/objective.php">Choose a science objective</a></div>
<?php page_end('Briefing', 2);
