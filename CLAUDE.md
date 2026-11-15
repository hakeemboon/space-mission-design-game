# TerraWatch-1 — Implementation Instructions

Project: Space Mission Design Game, NASA Space Apps Challenge 2026, Challenge #13.
Owner authorization: Implementation is authorized by the project owner on 14 November 2026.
Deadline: 15 November 2026, 23:59 local event time.

## Authority and source order
Read the documents in docs/planning and docs/corrections before coding.
- docs/corrections/Technical_Correction_Addendum.pdf is the latest and overrides any conflicting detail in planning PDFs.
- Document 02 is authoritative for catalogue values.
- Document 04 is authoritative for formulas, rules, events and scores, as amended by the correction addendum.
- Document 05 is authoritative for database design, as reduced by the addendum to 16 MVP tables.
- Document 06 is authoritative for UI/UX.
- Document 09 is authoritative for acceptance test definitions, as amended by the addendum.
- Document 11 is the implementation sequence and security handover, but any conflicting 30-table instruction is superseded by the 16-table addendum.
- Document 10 and docs/submission contain submission requirements and drafts.

If sources conflict or a required value is absent, stop and report the exact conflict. Do not invent a NASA fact or silently change a value.

## Locked project scope
Build one fictional educational LEO Earth-observation mission, TerraWatch-1.
Objectives: land cover, coastal, cloud patterns.
The four mission segments must be visible: Launch, Space, Ground, User.
Core loop: objective → requirements → design → constraints/preflight → deterministic simulation → results/debrief → redesign.
This is an educational simulator, not professional aerospace engineering software or a GMAT replacement.
Mission Units (MU) are fictional game currency, not USD or NASA costs.
Label values as NASA_FACT, EDUCATIONAL_APPROXIMATION or GAME_BALANCE.
No NASA branding, logos, flags or mission identifiers. No real NASA endorsement claims.

## Locked technology
HTML, CSS, Tailwind CSS, vanilla JavaScript, vanilla PHP, MySQL.
Windows 11 development. No React, Laravel, Python or Node.js in the application. No runtime external APIs.
Use Tailwind standalone CLI if available; commit generated CSS. Use PHP PDO prepared statements.
Do not change the stack without the owner's approval.

## Mandatory correction-addendum rules
1. MVP database is 16 tables, not 30. No accounts, user profiles, team tables or activity logs. Use session-only mission ownership. Four cooperative roles are on-screen guidance only.
2. Operational simulation loop runs months 1 through L only, with exactly L monthly metric rows.
3. If energy is the limiting lifetime, emit EV-POWER-NEGATIVE as a terminal event in month L+1; do not create a monthly metric row for that failure month. Report operational lifetime as L, not L+1.
4. If month-1 generation is already below demand: L=0, zero monthly rows, power-negative event in month 1, science score 0. Implement SIM-13.
5. ORB-O2 uses seeded fixed values: 23.5 sunlight hours/day and 2.0 eclipse minutes/orbit. Do not run a generic eclipse equation for ORB-O2 or recompute seeded orbit values at runtime. All orbit sunlight/eclipses are read from seed data.
6. Add results panel “What the users received”: delivered data, average daily delivery versus requirement, observation months, who needs the data, the user question, and applicable limitations. Include “Illustrative. The game does not produce real images or data products.”
7. D1–D6 values are predictions from planning scripts, not software test results. Run the actual software tests and report observed values honestly.
8. Keep the planning scripts in docs/corrections as reference only; they are not application code.

## Build priority under the deadline
- Phase 0: verify PHP/MySQL/Tailwind/repository environment; create minimal app shell and DB connection.
- Phase 1: create the 16-table schema and exact seed data.
- Phase 2: implement server-side validation and deterministic simulation first; add test runner and SIM-04 plus SIM-13.
- Phase 3: deliver a complete plain-styled playable loop for reference designs D1 and D4: design, preflight, run, results, redesign. This is the minimum viable demonstration.
- Only after Phase 3 works: add live meters, user panel, source cards, responsive styling, credits and final polish.
If time is short, cut optional animation, generated visual assets, extra polish and Challenge difficulty before cutting the working mission loop, server-side validation, deterministic simulation, core results or security.

## Security and quality
- PDO prepared statements for all database access.
- Escape all rendered output.
- CSRF/session token on every state-changing request.
- Validate every submitted ID/code/number against seeded catalogue data on the server.
- Never trust browser-calculated values or scores.
- Session cookie HttpOnly and SameSite; no user accounts.
- No credentials in the repository; keep config outside the public web root.
- Errors hidden in production and logged.
- Add an AI-assisted code comment to generated code and seed files.
- Never mark a test passed unless it was executed. Never modify catalogue values or thresholds to make a test pass.

## Reporting
At the end of each phase report: phase/modules, test IDs actually run and results, observed vs expected failures, files changed, deviations and reasons, next action and blockers. Ask before any stack or scope change.
