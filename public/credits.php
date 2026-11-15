<?php
/**
 * TerraWatch-1 — Credits and Use of SI (AI) statement.
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 * Keep this page in step with the submission's SI disclosure: list only tools actually used.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/src/app.php';

page_start(); ?>
<h1 class="text-2xl font-bold">Credits</h1>
<section class="mt-4 max-w-3xl space-y-3 text-sm text-slate-300">
    <p><strong>Space Mission Design Game (TerraWatch-1)</strong> was created for the 2026 NASA Space Apps Challenge, Challenge #13 “Space Mission Design Game”.</p>
    <p>The game concept, the TerraWatch-1 mission direction, the three objectives and the locked technology choices were decided by the team.</p>

    <h2 class="pt-2 text-lg font-semibold text-slate-100">Use of SI (AI) tools</h2>
    <ul class="list-disc space-y-1 pl-5">
        <li><strong>Claude (Anthropic)</strong> assisted with researching the NASA resources, writing the planning documents, and calculating expected values for testing.</li>
        <li><strong>Claude Code (Anthropic)</strong> assisted with writing the application code (HTML, CSS, JavaScript, PHP, SQL) and the seed data. Every generated code and seed file carries a comment saying it was AI-assisted. The team reviewed the code.</li>
        <li><strong>ChatGPT (OpenAI)</strong> assisted with project planning and review.</li>
        <li><strong>Higgsfield</strong> (GPT Image 2.6 model) generated the four decorative background images: Earth from orbit (start page),
            the satellite (briefing), the mission control room (simulation) and the abstract data grid (results). Each carries a visible
            “SI-generated” watermark burned into the image, and its description ends with “(SI-generated image)”. They are decoration only:
            no information in the game depends on them, and all hardware shown is fictional.</li>
        <li>Game component values are fictional; some were proposed with AI assistance and then checked by the team against the rules by enumeration.</li>
        <li>No generated content uses NASA logos, flags, mission patches or mission identifiers. Any SI-generated image in the game carries a visible “SI-generated” watermark.</li>
    </ul>

    <h2 class="pt-2 text-lg font-semibold text-slate-100">Built with</h2>
    <ul class="list-disc space-y-1 pl-5">
        <li>HTML, CSS, <a class="underline" href="https://tailwindcss.com/" target="_blank" rel="noopener">Tailwind CSS</a> (MIT licence), vanilla JavaScript, vanilla PHP and MySQL/MariaDB.</li>
        <li>NASA resources listed in the <a class="underline" href="/sources.php">Mission Library</a>.</li>
    </ul>

    <h2 class="pt-2 text-lg font-semibold text-slate-100">Honest limits</h2>
    <p>This is a simplified educational model. It does not propagate orbits, model link budgets, thermal behaviour or attitude control.
        It is not professional engineering software and not a substitute for NASA's General Mission Analysis Tool.
        Mission Units (MU) are fictional game currency. The game is not endorsed or certified by NASA.</p>
</section>
<?php page_end('Credits');
