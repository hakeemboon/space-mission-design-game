<?php
/**
 * TerraWatch-1 — screen 1: Landing.
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/src/app.php';

$cat = catalogue();
$difficulties = array_filter($cat['difficulties'], fn($d) => (int) $d['is_enabled'] === 1);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $nick = trim((string) ($_POST['nickname'] ?? ''));
    $diff = (string) ($_POST['difficulty'] ?? '');
    if ($nick === '' || mb_strlen($nick) > 24) {
        $error = 'Enter a nickname of 1 to 24 characters.';
    } elseif (!isset($difficulties[$diff])) {
        $error = 'Choose a difficulty.';
    } else {
        session_regenerate_id(true);
        $_SESSION['nickname'] = $nick;
        $_SESSION['difficulty'] = $diff;
        unset($_SESSION['mission_id']);
        redirect('/briefing.php');
    }
}
$existing = current_mission();

page_start(); ?>
<section class="relative isolate overflow-hidden rounded-2xl border border-slate-800 bg-gradient-to-br from-slate-900 via-slate-950 to-sky-950 p-6 pb-16 md:p-10 md:pb-16 lg:min-h-[44rem]">
    <?php // Decorative background (Higgsfield, watermarked). Anchored bottom-right so the watermark is never cropped. ?>
    <img src="/assets/img/earth-orbit.webp" alt="Earth's curved horizon seen from low orbit at sunrise (SI-generated image)"
         class="absolute inset-0 -z-10 h-full w-full object-cover object-right-bottom opacity-90" width="1920" height="1080" fetchpriority="high">
    <div class="absolute inset-0 -z-10 bg-gradient-to-r from-slate-950/90 via-slate-950/50 to-transparent lg:via-transparent" aria-hidden="true"></div>
    <div class="max-w-xl">
        <p class="text-sm uppercase tracking-widest text-sky-300">NASA Space Apps Challenge 2026 · Space Mission Design Game</p>
        <h1 class="mt-2 text-3xl font-bold md:text-4xl">Design, fly and fix your own Earth-observation mission.</h1>
        <p class="mt-4 text-slate-300">You are the Mission Design Lead for <strong>TerraWatch-1</strong>, a fictional satellite in low Earth orbit.
            Pick a science question, build the whole mission across the four segments (Launch, Space, Ground, User),
            fly it month by month, and redesign until the people who need the data get it.</p>
        <p class="mt-3 text-sm text-slate-400">This is an educational simulator with a simplified model, not professional engineering software.</p>
    </div>
    <div class="mt-6 max-w-xl rounded-lg border border-slate-700 bg-slate-900/85 p-5 backdrop-blur">
        <?php if ($existing): ?>
            <p class="mb-4 text-sm">You have a mission in progress, <?= e($existing['nickname']) ?>.
                <a class="font-semibold text-sky-300 underline" href="/designer.php">Continue mission</a></p>
        <?php endif; ?>
        <form method="post" class="space-y-4" novalidate>
            <?= csrf_field() ?>
            <?php if ($error): ?><p role="alert" class="rounded border border-rose-600 bg-rose-950 px-3 py-2 text-sm"><?= e($error) ?></p><?php endif; ?>
            <div>
                <label for="nickname" class="block text-sm font-semibold">Nickname or team name</label>
                <input id="nickname" name="nickname" maxlength="24" required autocomplete="off"
                       value="<?= e($_POST['nickname'] ?? ($_SESSION['nickname'] ?? '')) ?>"
                       class="mt-1 w-full rounded border border-slate-600 bg-slate-950 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-sky-400">
                <p class="mt-1 text-xs text-slate-400">Up to 24 characters. No other personal information is collected.</p>
            </div>
            <fieldset>
                <legend class="text-sm font-semibold">Difficulty</legend>
                <div class="mt-2 space-y-2">
                    <?php foreach ($difficulties as $code => $d): ?>
                        <label class="flex cursor-pointer gap-3 rounded border border-slate-700 p-3 hover:border-sky-500 has-[:checked]:border-sky-400 has-[:checked]:bg-sky-950/40">
                            <input type="radio" name="difficulty" value="<?= e($code) ?>" aria-label="<?= e($d['name']) ?>" class="mt-1"
                                <?= (($_POST['difficulty'] ?? $_SESSION['difficulty'] ?? 'DIFF-B') === $code) ? 'checked' : '' ?>>
                            <span><span class="font-semibold"><?= e($d['name']) ?></span>
                                <span class="text-sm text-slate-400">· <?= e(num($d['budget_mu'], 0)) ?> MU · <?= (int) $d['target_months'] ?>-month target</span>
                                <span class="block text-xs text-slate-400"><?= e($d['description']) ?></span></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <button class="<?= btn_primary() ?> w-full" type="submit">Start a new mission</button>
        </form>
    </div>
</section>
<?php page_end('Start', 1);
