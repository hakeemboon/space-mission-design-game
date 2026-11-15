# Paste this into Claude Code with this folder opened

You are now the implementation agent for the NASA Space Apps Challenge 2026 project, Space Mission Design Game / TerraWatch-1. The owner has authorized implementation.

1. Read CLAUDE.md first, then read the authoritative planning PDFs in docs/planning and the correction addendum in docs/corrections. Do not begin from memory or summaries.
2. The correction addendum overrides every conflicting planning document. In particular, build the 16-table MVP database, not the older 30-table design.
3. Start with Phase 0 only. Inspect the Windows 11 environment and report whether PHP, MySQL/MariaDB and Tailwind standalone CLI are available. Do not change the required stack. Do not install software, alter system settings, or upload credentials without asking the owner.
4. If the environment is ready, create the app skeleton and a minimal PHP page that loads Tailwind styles and reads one row from MySQL. Then report the files changed and exact verification performed.
5. If PHP or MySQL is missing, stop after Phase 0 and give the fastest safe setup options that preserve the locked stack. Do not pretend the app is running.
6. Once Phase 0 is verified, continue in the document's phase order. Prioritize a plain but complete playable cycle by Phase 3. Do not build optional animation, accounts, external APIs, multi-device collaboration or other excluded features.
7. Implement validation and simulation on the PHP server. Browser calculations are display-only. Implement SIM-04 and SIM-13 exactly as amended. Use the seeded orbit values; ORB-O2 is fixed at 23.5 sunlight hours/day and 2.0 eclipse minutes/orbit.
8. Include the “What the users received” results panel and its required illustrative-data disclaimer.
9. Treat all D1–D6 values as predicted reference values until the actual application is run. Execute the software tests and report observed values, not assumed passes.
10. Keep all generated code and seed files AI-acknowledged in comments. Do not use NASA branding or imply NASA endorsement.
11. At the end of each phase, stop and report: test IDs actually run, pass/fail results, observed vs expected values, files changed, deviations, blockers and next phase. Never change formulas, thresholds or catalogue values to force a pass.

Begin now with Phase 0. Be concise, methodical and deadline-aware. The priority is a working, honest demo, not a large unfinished feature set.
