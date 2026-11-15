<?php
/**
 * TerraWatch-1 — screen 4: Requirements R1–R6 for the chosen objective.
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/src/app.php';

$m = require_mission();
$cat = catalogue();
$obj = $cat['objectives'][$m['objective']];
$dif = $cat['difficulties'][$m['difficulty']];
$p = rules()['params'];
$best = ['OBJ-LAND' => 'Multispectral or hyperspectral imager', 'OBJ-COAST' => 'Hyperspectral coastal imager', 'OBJ-CLOUD' => 'Thermal radiometer or wide-field camera'];
$orbitHint = ['OBJ-LAND' => 'Sun-synchronous mid-morning (ORB-O1)', 'OBJ-COAST' => 'ORB-O1 or ORB-O3', 'OBJ-CLOUD' => 'Any orbit'];
$reqs = [
    ['R1', 'Measurement', 'Best instrument suitability at least ' . $p['REQ_INSTRUMENT_SUIT_MIN'], $best[$m['objective']], 'Space', 'The right instrument has to see what the users care about.'],
    ['R2', 'Coverage and lighting', 'Orbit suitability at least ' . $p['REQ_ORBIT_SUIT_MIN'], $orbitHint[$m['objective']], 'Launch and Space', 'Where and when you look decides lighting and which places you see.'],
    ['R3', 'Data delivered to users', num($obj['required_mb_per_day'], 0) . ' MB per day', 'Instrument data rate, imaging time, radio, stations', 'Space and Ground', 'Data only counts once it reaches the ground and the users.'],
    ['R4', 'Operational lifetime', $dif['target_months'] . ' months', 'Bus design life, orbit lifetime, power margin', 'Space and Launch', 'Seeing change takes time; the mission must keep working.'],
    ['R5', 'Total cost within budget', num($dif['budget_mu'], 0) . ' MU', 'Every purchase, including stations and launch', 'All segments', 'Mission Units are fictional, but the trade-off is real.'],
    ['R6', 'Responsible end of mission', 'Natural decay or a deorbit kit', 'Orbit choice or deorbit kit', 'Space', 'A dead satellite left in orbit is a hazard to others.'],
];
$f03 = fact_cards('requirements');

page_start(); ?>
<h1 class="text-2xl font-bold">Mission requirements</h1>
<p class="mt-2 max-w-3xl text-slate-300"><strong><?= e($obj['name']) ?>:</strong> “<?= e($obj['science_question']) ?>” turns into six checkable requirements.
    Each one belongs to a segment. You can come back to this page from the designer at any time.</p>
<div class="mt-4 overflow-x-auto">
<table class="w-full min-w-[640px] text-left text-sm">
    <thead class="border-b border-slate-700 text-slate-400"><tr><th class="py-2 pr-3">Req</th><th class="pr-3">Requirement</th><th class="pr-3">Target</th><th class="pr-3">What satisfies it</th><th class="pr-3">Segment</th><th>Why</th></tr></thead>
    <tbody>
    <?php foreach ($reqs as $r): ?>
        <tr class="border-b border-slate-800 align-top"><td class="py-2 pr-3 font-semibold"><?= e($r[0]) ?></td><td class="pr-3"><?= e($r[1]) ?></td>
            <td class="pr-3"><?= e($r[2]) ?></td><td class="pr-3 text-slate-300"><?= e($r[3]) ?></td><td class="pr-3"><?= e($r[4]) ?></td><td class="text-slate-400"><?= e($r[5]) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<div class="mt-4 max-w-xl"><?php foreach ($f03 as $f) { echo fact_card_html($f); } ?></div>
<div class="mt-6"><a class="<?= btn_primary() ?>" href="/designer.php">Open the mission designer</a></div>
<?php page_end('Requirements', 4, segment_status(null, $m), $m);
