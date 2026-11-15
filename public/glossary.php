<?php
/**
 * TerraWatch-1 — Help and glossary: every term in plain language.
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 * Definitions describe how the term is used in this simplified game.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/src/app.php';

$terms = [
    'The mission system' => [
        ['Mission segments', 'A science mission has four parts that must work together: launch, space, ground and user. (NASA fact card F01.)'],
        ['Launch segment', 'The rocket and launch service that put the satellite into its orbit.'],
        ['Space segment', 'The satellite itself: its bus, instruments, power system and radio.'],
        ['Ground segment', 'The ground stations and mission control that talk to the satellite and receive its data.'],
        ['User segment', 'The people who use the data to answer a question, such as a land-use planner or a weather forecaster.'],
        ['Requirement', 'A checkable target that the design must meet, such as "deliver 4,000 MB of data per day".'],
    ],
    'Orbits and launch' => [
        ['Low Earth orbit', 'An orbit close to Earth, roughly 160 to 2,000 km up (NASA fact card F04). All TerraWatch-1 orbits are 450 to 600 km.'],
        ['Inclination', 'The angle between the orbit and the equator. 0 degrees follows the equator; 90 degrees passes over the poles.'],
        ['Sun-synchronous orbit', 'A near-polar orbit that crosses the equator at about the same local time every day, so lighting stays consistent.'],
        ['Dawn-dusk orbit', 'A Sun-synchronous orbit that follows the line between day and night. The satellite is almost always in sunlight, but the Sun is low for cameras.'],
        ['Eclipse', 'The part of each orbit spent in Earth\'s shadow. The battery has to power the satellite then.'],
        ['Launch capacity', 'The most mass a launcher can carry to a given orbit. Each launcher only serves some orbits.'],
        ['Orbital decay', 'Air drag slowly pulls a low satellite down. Lower orbits come down sooner.'],
    ],
    'The spacecraft' => [
        ['Spacecraft bus', 'The body of the satellite: structure, computer and basic systems. It limits how many instruments and how much power you can have.'],
        ['Payload', 'The science instruments the bus carries.'],
        ['Housekeeping load', 'Power the bus itself always uses, even when nothing else is on.'],
        ['Peak power', 'Power needed if everything is switched on at once. It must not exceed what the bus can handle.'],
        ['Energy (Wh)', 'Power over time. The arrays must make at least as much energy each day as the satellite uses.'],
        ['Usable battery', 'In this game, half of the battery\'s capacity can be used, to protect it.'],
        ['Design life', 'How many months the bus is built to keep working.'],
        ['Deorbit kit', 'An add-on that brings the satellite down at the end of the mission, so it does not stay in orbit as debris.'],
    ],
    'Data and communication' => [
        ['Data rate (Mbps)', 'Megabits per second. 8 megabits make 1 megabyte (MB). 1,000 MB make 1 GB.'],
        ['Imaging time', 'Minutes per orbit the instruments are taking data. More time means more data and more energy.'],
        ['Downlink', 'Sending data from the satellite to a ground station.'],
        ['Contact minutes', 'Minutes per day the satellite can talk to your ground stations. A station far from the orbit\'s path never sees it.'],
        ['S-band and X-band', 'Radio frequency bands. A station can only hear radios in a band it supports. X-band is faster here.'],
        ['Backlog', 'Data waiting on board because it could not be sent yet. When storage is full, new data is lost.'],
    ],
    'Time, money and scores' => [
        ['Development time', 'Months to design, build and test before launch. Reported, not scored.'],
        ['Operational lifetime', 'Months the satellite works after launch. Month 1 is commissioning (checking the satellite), with no science data.'],
        ['Mission Units (MU)', 'Fictional game currency. Not real money and not NASA costs.'],
        ['Science score', 'A game-only score from 0 to 100: instrument fit × orbit fit × data delivered × lifetime. 75 or more is a success.'],
        ['Mission score', 'Ranks designs: 70% science, 20% efficiency (money and mass left over), 10% responsibility (clean disposal, no data lost).'],
        ['Hard failure', 'A problem that stops launch, such as being too heavy for the launcher.'],
        ['Warning', 'A problem that is allowed but will cost you, such as data piling up on board.'],
    ],
];

page_start(); ?>
<h1 class="text-2xl font-bold">Help and glossary</h1>
<section class="mt-4 max-w-3xl rounded-lg border border-slate-700 bg-slate-900 p-4 text-sm text-slate-300">
    <h2 class="font-semibold text-slate-100">How to play</h2>
    <ol class="mt-2 list-decimal space-y-1 pl-5">
        <li>Choose a science objective. It decides who the users are and what they need.</li>
        <li>Read the six requirements.</li>
        <li>In the designer, pick a launcher, orbit, bus, instruments, power system, radio and ground stations. The meters update as you choose.</li>
        <li>Fix every red failure. Warnings are allowed, but they cost science or points.</li>
        <li>Review for launch, then launch. Watch the mission month by month.</li>
        <li>Read the results and the debrief, then redesign and try to do better.</li>
    </ol>
    <p class="mt-2">Playing as a team on one device? Split the segments: a Mission Director (budget and launch), a Science Specialist (instruments and objective), a Spacecraft Engineer (bus, power, radio) and a Ground and Operations Specialist (stations and orbit).</p>
</section>
<?php foreach ($terms as $group => $list): ?>
    <h2 class="mt-6 text-lg font-semibold"><?= e($group) ?></h2>
    <dl class="mt-2 grid max-w-4xl gap-x-6 gap-y-2 text-sm md:grid-cols-[14rem_1fr]">
        <?php foreach ($list as [$term, $def]): ?>
            <dt class="font-semibold"><?= e($term) ?></dt><dd class="text-slate-300"><?= e($def) ?></dd>
        <?php endforeach; ?>
    </dl>
<?php endforeach; ?>
<p class="mt-6 text-xs text-slate-400">These are simplified explanations for the game. See the <a class="underline" href="/sources.php">Mission Library</a> for the NASA sources.</p>
<?php page_end('Help');
