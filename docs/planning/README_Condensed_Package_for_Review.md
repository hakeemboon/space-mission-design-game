# Space Mission Design Game — Planning Package for Review

NASA Space Apps Challenge 2026, Challenge #13 (Space Mission Design Game)
Prepared 14 November 2026. Stage: research, planning and documentation only. **No application code has been written.**

## 0. How to use this file

This is a condensed, self-contained version of an 11-document planning package. It carries every locked value, formula, rule and decision needed to review the plan. The full documents (with the per-document sections: purpose, content, decisions, assumptions, open questions, dependencies) live in a shared doc the team can export tab by tab.

### Roles

| Party | Role | Does | Does not |
| --- | --- | --- | --- |
| ChatGPT | Planner and reviewer | Reviews this package against the project prompt; approves or requests changes; reviews each implementation phase report | Write application code; change catalogue values without a version bump |
| Claude | Research and documentation (done); implementer and developer (after authorisation) | Builds the game as specified; runs the tests; reports per phase | Start before authorisation; change values, formulas or thresholds to make a test pass |
| Higgsfield | Visual generation only | Background images and optional short clips (assets A01–A05, A14) | Text, icons, diagrams, facts, people, anything with NASA branding |
| Team members | Owners | Decide, authorise, usability-test, submit, confirm the AI disclosure | Delegate submission or disclosure |

### What the reviewer is asked to do

1. Confirm the plan meets the project prompt (section 11 has the compliance table).
2. Accept or overrule the provisional decisions in section 9.
3. Flag any value, rule or scope item to change **before** implementation starts.
4. State explicitly whether implementation is authorised.

## 1. Project in one paragraph

A browser game in which the player, as Mission Design Lead, designs a fictional low-Earth-orbit Earth-observation mission (TerraWatch-1) across NASA's four mission segments (launch, space, ground, user), checks it against engineering constraints, flies it in a deterministic month-by-month simulation, reads the result and redesigns. Stack (locked): HTML, CSS, Tailwind CSS, vanilla JavaScript, vanilla PHP, MySQL, developed on Windows 11.

## 2. Source verification (Document 01)

| # | Resource | Result |
| --- | --- | --- |
| 1 | Design and Develop Science Missions (NASA) | Listed address not reachable by the research tool; identical deck read from NASA NTRS (26 March 2025). Gives the four segments, ConOps role, life-cycle phases. Its slides on requirements, trade-offs and verification are titles only. |
| 2 | General Mission Analysis Tool (NASA) | Opened. Version R2025a, open source. Used only as the professional contrast case. |
| 3 | Concept Maturity Levels (JPL) | Record not opened. Content taken from NASA Ames and Goddard pages that describe the same framework (CML 1 consultation, 2 feasibility, 3 trade space, 4 point design). |
| 4 | NASA Ames Mission Design Center | Opened. |
| 5 | NASA Goddard Mission Design Lab | Opened. Four study types; subsystems estimate mass, power, data rate. |
| 6 | NASA Mission Viz | Opened. Repository of browser orbit demos and tutorials. |
| 7 | Build It Yourself: Satellite! (NASA Webb) | Opened. Objective-first design game; needs 1024x768; no built-in accessibility. |
| 8 | CubeSat 101 (NASA) | Listed address not opened; same document read from nasa.gov through chapter 6. 1U = 10 cm cube, about 1–1.33 kg; typical build 18–24 months; debris section says deorbit "in a reasonable amount of time" with no figure in years. |
| 9 | Canadian Space Agency digital games | Opened. |

All findings agree with the team's uploaded resources file. One wording difference: NASA's slide defines the user segment as scientists and the public.
No numeric spacecraft specification is taken from any source. **No NASA dataset is used**; NASA resources are conceptual and factual sources. This is the main risk to the Relevance score.

## 3. Parameters (Document 02)

Labels: NASA_FACT (13 sourced fact cards, never tunable), EDUCATIONAL_APPROXIMATION (simplified models), GAME_BALANCE (fictional, all component values and Mission Units).

Units: kg, W, Wh, Mbps, MB, GB, minutes, months, MU. 1 MB = 8 Mb. 1 GB = 1,000 MB. 1 month = 30 days.

### Orbits (computed from T = 2π√(a³/μ); eclipse fraction = asin(R/a)/π)

| ID | Orbit | Alt km | Period min | Orbits/day | Sunlight h/day | Eclipse min | Decay months | Disposal aid | Suit. Land / Coast / Cloud |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| ORB-O1 | Sun-synchronous, mid-morning | 500 | 94.6 | 15.2 | 14.9 | 35.8 | 84 | No | 1.0 / 1.0 / 0.85 |
| ORB-O2 | Sun-synchronous, dawn-dusk | 600 | 96.7 | 14.9 | 23.5 | 2.0 | 999 (does not decay) | Yes | 0.6 / 0.6 / 0.9 |
| ORB-O3 | 45° inclined | 450 | 93.6 | 15.4 | 14.8 | 35.9 | 36 | No | 0.7 / 0.8 / 1.0 |

Cross-check: the formula gives 98.9 min at 705 km; NASA states about 99 minutes for Aqua at about 705 km.

### Buses

| ID | Mass kg | Payload limit kg | Slots | Peak capacity W | Housekeeping W | Storage GB | Design life mo | Dev mo | Cost MU |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| BUS-S | 14 | 8 | 1 | 60 | 18 | 16 | 24 | 18 | 150 |
| BUS-M | 55 | 25 | 2 | 160 | 40 | 64 | 36 | 24 | 280 |
| BUS-L | 120 | 45 | 3 | 320 | 85 | 128 | 60 | 32 | 430 |

### Instruments

| ID | Name | Mass kg | Power W | Rate Mbps | Integr. mo | Cost MU | Land | Coast | Cloud |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| INS-WFC | Wide-field visible camera | 4 | 12 | 2 | 1 | 60 | 0.4 | 0.3 | 0.8 |
| INS-SWIR | Short-wave infrared camera | 7 | 25 | 4 | 2 | 100 | 0.7 | 0.4 | 0.6 |
| INS-MSI | Multispectral imager | 12 | 35 | 8 | 3 | 140 | 1.0 | 0.7 | 0.5 |
| INS-TIR | Thermal infrared radiometer | 9 | 30 | 1.5 | 3 | 120 | 0.5 | 0.5 | 1.0 |
| INS-HSI | Hyperspectral coastal imager | 18 | 55 | 20 | 5 | 220 | 0.8 | 1.0 | 0.2 |

### Power, radios, add-ons

| ID | Mass kg | Key values | Cost MU |
| --- | --- | --- | --- |
| PWR-P1 | 4 | 50 W generation, 40 Wh battery | 50 |
| PWR-P2 | 10 | 120 W, 110 Wh | 110 |
| PWR-P3 | 19 | 260 W, 260 Wh; not allowed on BUS-S | 190 |
| COM-C1 | 1.5 | 10 W, 2 Mbps, S-band | 40 |
| COM-C2 | 3 | 25 W, 10 Mbps, S-band | 90 |
| COM-C3 | 5 | 60 W, 50 Mbps, X-band | 170 |
| ADD-STO | 2 | +64 GB, 5 W continuous | 40 |
| ADD-DEO | 2 | Deorbit kit, 0 W | 25 |

### Ground stations (contact minutes per day) and launch capacity (kg)

| Station | Bands | O1 | O2 | O3 | Cost MU |
| --- | --- | --- | --- | --- | --- |
| GS-G1 Aurora Polar | S, X | 80 | 80 | 0 | 120 |
| GS-G2 Campus (mid-latitude) | S | 32 | 32 | 40 | 40 |
| GS-G3 Equator Point | S, X | 24 | 24 | 56 | 70 |
| GS-G4 OrbitLink network | S, X | 96 | 96 | 96 | 200 |

| Launch | O1 | O2 | O3 | Cost MU |
| --- | --- | --- | --- | --- |
| LV-L1 Rideshare slot | 60 | – | – | 120 |
| LV-L2 Dedicated small launcher | 150 | 130 | 220 | 300 |
| LV-L3 Large rideshare port | 220 | 200 | – | 210 |

### Objectives, difficulty, thresholds

- Required delivered data: Land 4,000 MB/day; Coastal 8,000; Cloud 2,500.
- Difficulty: Beginner 1,300 MU / 12 months; Standard 1,150 / 24; Challenge 1,075 / 36.
- Imaging time per orbit: 5, 10 or 15 minutes. Commissioning: month 1, no science data.
- Usable battery fraction 0.5. Solar generation loss 0.25% per month, linear.
- Warnings: mass margin under 10% of capacity; generation under 115% of demand.
- Disposal acceptable if decay ≤ 300 months or ADD-DEO fitted.
- Outcome bands on science score: success ≥ 75; partial 50–74; objective not met < 50.

## 4. Simulation engine (Document 04)

Deterministic: same inputs + catalogue version + rule version = same result. No randomness, no clock. Server (PHP) is authoritative; browser mirrors for display. Round to 2 decimals before comparing or storing. A value exactly at a limit passes.

### Calculations

- Total mass = bus + instruments + power + radio + add-ons. Mass margin = launch capacity − total mass.
- Peak load = housekeeping + Σ instrument power + radio power + add-on power.
- Contact minutes = Σ over selected stations that support the radio's band.
- Imaging duty = imaging minutes ÷ period.
- Average load = housekeeping + Σ instrument power × duty + radio power × contact ÷ 1,440 + add-on power.
- Daily demand (Wh) = average load × 24. Daily generation (Wh) = generation power × sunlight hours.
- Eclipse need (Wh) = average load × eclipse minutes ÷ 60. Usable battery = battery × 0.5.
- Data generated (MB/day) = Σ rate (Mbps) × imaging minutes × 60 × orbits/day ÷ 8.
- Downlink (MB/day) = radio rate (Mbps) × contact minutes × 60 ÷ 8.
- Storage (MB) = (bus GB + 64 if ADD-STO) × 1,000. Cost = Σ all selections.
- Development months = bus development + Σ instrument integration (reported, not scored).

### Hard failures (block launch; all are listed)

H01 incomplete or invalid selection · H02 instruments > slots · H03 payload mass > bus limit · H04 forbidden pair (PWR-P3 on BUS-S) · H05 launcher does not serve orbit · H06 mass > capacity · H07 peak load > bus capacity · H08 station lacks the radio's band · H09 zero contact minutes · H10 generation < demand · H11 eclipse need > usable battery · H12 cost > budget.

### Warnings (never block)

W01 mass margin < 10% · W02 generation < 115% of demand · W03 generated > downlink · W04 storage overflows · W05 no disposal plan · W06 lifetime below target · W07 best instrument suitability < 0.8 · W08 orbit suitability < 0.8 · W09 delivered data below requirement · W10 a selected station never sees the orbit · W11 (Challenge) development > 30 months.

### Mission simulation

- Lifetime L = min(target, bus design life, orbit decay, last month where generation × (1 − 0.0025 m) ≥ demand).
- Each month 2..L: available = backlog + generated × 30; sent = min(available, downlink × 30); lost = remainder above storage; backlog = remainder capped at storage.
- Events: launch, commissioned, first data, six-monthly milestone, backlog, storage full, energy low, power negative, bus end of life, orbit decay, target reached, disposal ok / risk.
- End reason order when several apply in the same month: target reached, energy, bus life, decay.

### Scores (game-only metrics)

- Science = 100 × instrument factor × orbit factor × data factor × lifetime factor.
  - Instrument factor = best suitability + 0.25 × second best, capped at 1.
  - Orbit factor = orbit suitability for the objective.
  - Data factor = average delivered MB/day over months 2..L ÷ required, capped at 1.
  - Lifetime factor = L ÷ target, capped at 1.
- Efficiency = 50 × min(1, budget left ÷ 20% of budget) + 50 × min(1, mass margin ÷ 20% of capacity).
- Responsibility = 60 if disposal acceptable + 40 if no data lost.
- Mission score = 0.7 science + 0.2 efficiency + 0.1 responsibility.

## 5. Verified reference results (Document 09)

All numbers below were produced by running the rules above in a planning script.

| Ref | Difficulty, objective | Design (bus / instr / power / radio / station / orbit / launch / imaging / add-on) | Key results |
| --- | --- | --- | --- |
| D1 | Standard, land | M / MSI / P2 / C2 / G1 / O1 / L3 / 5 / – | Mass 80 of 220 kg; peak 100 W; demand 1,037.73 Wh; generation 1,788.00; data 4,560 generated, 6,000 downlink; cost 950; science 100.00; efficiency 93.48; mission 98.70 |
| D2 | Standard, coastal | M / HSI / P2 / C3 / G1 / O1 / L3 / 5 / – | Mass 88; peak 155; cost 1,110; science 100.00; efficiency 58.70; mission 91.74 |
| D3 | Standard, cloud | M / TIR / P2 / C2 / G2 / O3 / L2 / 15 / – | Mass 77; cost 940; science 100.00; mission 99.13 |
| D4 | Standard, land | S / SWIR / P1 / C1 / G1 / O1 / L1 / 10 / – | Data 4,560 generated, 1,200 downlink; data factor 0.30; science 21.00; mission 40.70; warnings W03, W04, W07, W09 |
| D5 | Challenge, cloud | M / TIR / P1 / C2 / G2 / O2 / L3 / 15 / DEO | Power-negative in month 31; lifetime 30 of 36; science 72.00; mission 76.40 |
| D6 | Standard, cloud | S / WFC / P1 / C2 / G2 / O3 / L2 / 15 / – | Mass 25; cost 690; science 80.00 |

Balance check (designs scoring 75+, land / coastal / cloud): Beginner 3,329 / 1,203 / 8,724; Standard 921 / 174 / 2,887; Challenge 272 / 23 / 1,043. Three-instrument designs: 747 flight-ready at Beginner, 18 at Standard, none at Challenge.

Test plan: 95 cases in ten groups (calculation, hard failures, warnings and boundaries, compatibility, repeatability and lifetime, functional, database, security, responsive and accessibility, usability and demo). Two warnings (W10, W11) are specified but were not exercised by the script. H11 never fires without H10 in this catalogue.

Demo run: D4 (score 21) redesigned to D1 (score 100).

## 6. Mission architecture and requirements (Document 03)

Objectives and user personas (fictional adults, job titles only): land cover (land-use planner, agricultural adviser); coastal (coastal-zone manager, fisheries officer); cloud patterns (weather forecaster, school science class).

Requirements per objective: R1 best instrument suitability ≥ 0.8 (space) · R2 orbit suitability ≥ 0.8 (launch, space) · R3 data delivered per day (space, ground) · R4 operational lifetime (space, launch) · R5 cost within budget (all) · R6 responsible end of mission (space).

Concept of operations: launch → deploy → one month commissioning → image 5/10/15 minutes per orbit → store → downlink at station passes → mission control checks → data to users → continue until a lifetime limit → disposal.

## 7. Database (Document 05)

30 MySQL tables (InnoDB, utf8mb4) in four groups:

- Scenario and rules (8): mission_scenarios, mission_objectives, objective_requirements, difficulty_settings, rule_versions, game_rules, reference_sources, source_mappings.
- Catalogue (10): component_categories, components (one table for all 16 parts, nullable category columns), component_conflicts, instrument_suitability, orbit_profiles, orbit_suitability, launch_profiles, launch_orbit_capacity, ground_stations, ground_orbit_contact.
- Player missions (7): users (nickname + session only), team_roles, missions, mission_selections, mission_stations, mission_members, mission_activity_logs.
- Simulation results (5): simulation_runs (input snapshot as JSON text, SHA-256 input hash, rule version, scores), simulation_findings, simulation_metrics (monthly), simulation_events, run_requirements.

Runs are immutable. A failed preflight creates a log row and no run. Deferred: user_profiles, teams, team_members, mission_decisions. First to cut if late: mission_activity_logs, simulation_findings, mission_members.

## 8. Screens, gameplay, assets (Documents 06–08)

Nine pages: landing, briefing, objective, requirements, mission designer (component selection + six-meter resource dashboard + constraint list), preflight review, simulation, results, debrief and redesign. A four-segment rail is visible on pages 4–9. Minimum width 360 px. Status never by colour alone. "Simplified educational model" and "fictional game currency" labels are required.

Gameplay: seven learning objectives; difficulty changes budget, lifetime target and help shown, never formulas; no timers or random setbacks; no dead ends; Beginner offers a weak starter design (D4). Same-device play for up to four: Mission Director, Science Specialist, Spacecraft Engineer, Ground and Operations Specialist (advisory roles, not enforced; sign-off not stored).

Assets: 14 listed. Essential visuals (icons, meters, charts, orbit animation) are coded in SVG/CSS. Higgsfield images are optional decoration, each with a coded fallback, a burned-in visible "SI-generated" watermark, no people, no text, no NASA branding.

## 9. Decisions

Provisional answers given from the project prompt and official documents; the team may overrule any.

| # | Decision | Provisional answer |
| --- | --- | --- |
| B1 | Catalogue values and rules | Approved as written |
| B2 | NASA dataset | None; stated openly in the submission |
| B3 | Demo format | Slides, 7 maximum, English |
| B4 | Hosting | Public code repository as the Final Project link; hosted playable copy only if ready by Sunday 17:00 |
| B5 | Tailwind and local setup | Tailwind standalone command-line executable (no Node.js); any local PHP + MySQL/MariaDB bundle; snapshots as JSON text in LONGTEXT |
| B6 | Challenge difficulty | Seeded; enabled only if the designer phase ends on time |
| B8 | Progress storage | Session only |

Still needed from the team: team name and listed members (max six per team); owners for demo, assets and testing; reading the public Participant Project Submission Guide; **explicit authorisation to start implementation**.

## 10. Submission rules, risks, schedule (Document 10 and items C, E)

Rules from the official documents: summary 50–200 words; exactly one demo link (7 slides max, title slide counts, or a video of 30 seconds or less); public, no login; English; no one under 18 named, shown or heard; no NASA logos, flags or mission identifiers in generated content; generated images and video watermarked; generated code and data acknowledged; at least one NASA challenge resource for global awards; criteria Impact, Creativity, Validity, Relevance, Presentation (1–5 each). Window: Saturday 14 November 09:00 to Sunday 15 November 23:59 local event time; "Submit for Judging" must be clicked, and clicked again after any edit.

AI disclosure (actual use to date): Claude for research, planning documents and test expectations; ChatGPT as planner and reviewer (as reported by the team). Planned, to confirm at submission: Claude for application code; Higgsfield for images.

Top risks: scope (30 tables, 9 screens, 23 rules in two days); browser and server calculations drifting; no public PHP hosting; environment setup time; time lost on visuals; Relevance judged weak without a dataset; submission slips (private link, slide count, missed re-submit).

Schedule: Saturday — kick-off 09:00, environment, schema and seed, engine with test page by 18:00, plain playable cycle by 22:30. Sunday — designer experience by 11:30, sources and replay by 14:00, usability tests, hardening and deploy by 17:00, slides by 19:00, link tests, first submit at 20:00, final submit 22:30, no edits after 23:30.

Build phases: 0 environment · 1 schema and seed · 2 engine proven by tests · 3 playable cycle (gate) · 4 designer experience · 5 explanation and sources · 6 harden and present. Nothing after phase 3 starts until D1 and D4 can be designed, flown and redesigned.

## 11. Compliance with the project prompt

| Prompt demand | Result |
| --- | --- |
| Task 1 NASA resource research | Met; six of nine listed addresses opened directly, the rest read from NASA copies or NASA pages describing them |
| Task 2 parameters register | Met |
| Task 3 mission architecture | Met |
| Task 4 simulation specification, deterministic | Met |
| Task 5 database design, no SQL files | Met; diagram shows table groups, foreign keys listed per table |
| Task 6 UI/UX | Met |
| Task 7 gameplay, education, difficulty | Met |
| Task 8 visual asset and Higgsfield plan | Met; nothing generated yet |
| Task 9 testing and QA | Met; 95 cases |
| Task 10 judging and submission | Met; team-specific placeholders remain |
| Task 11 implementation handover | Met |
| Items A–E and cross-document check | Met |
| Stack unchanged, scope locked, no application code | Met |

## 12. Known limits (stated plainly)

- Component values, costs and Mission Units are fictional and labelled as such; they are not NASA specifications.
- The simulation does not model orbits, link budgets, thermal or attitude behaviour.
- The JPL Concept Maturity Levels paper and the public submission guide were not read first-hand.
- Play-time figures are estimates until usability tests are run.
- The game is not endorsed or certified by NASA and is not a substitute for NASA's General Mission Analysis Tool.

## 13. Implementation handover prompt (to be given to Claude after authorisation)

```text
ROLE: You are the implementation agent for "Space Mission Design Game" (NASA Space Apps Challenge 2026, Challenge #13). The planning stage is approved. Build the MVP exactly as specified in the planning documents.

STACK (locked): HTML, CSS, Tailwind CSS, vanilla JavaScript, vanilla PHP, MySQL, on Windows 11. No frameworks, no Node.js in the application, no external APIs.

AUTHORITATIVE DOCUMENTS: Document 02 for every value. Document 04 for every formula, rule, event and score. Document 05 for the schema. Document 06 for the screens. Document 09 for acceptance tests. Document 11 for scope, modules, phases, security and reporting. If two documents disagree, stop and report it.

BUILD ORDER: Follow the seven phases. Do not start phase 4 until a player can complete design, preflight, simulation, results and redesign for reference designs D1 and D4.

RULES:
1. The server computes validation, simulation and scores. The browser mirrors calculations for display only.
2. The simulation is deterministic. No random numbers, no dates or times in calculations.
3. Read thresholds and parameters from the game_rules table.
4. Seed data must match Document 02 exactly. Never change a value, formula or threshold to make a test pass.
5. Use PDO prepared statements, escape all output, and protect every state-changing request with a session token.
6. Label simplified models and fictional values in the interface. Mission Units are fictional. Do not claim NASA endorsement. Do not use NASA logos or mission identifiers.
7. Add a header comment to every generated code and seed file stating it was AI-assisted.
8. Do not add features outside the approved scope.

ACCEPTANCE: Reference designs D1 to D6 must produce the documented values. Running the same design twice must give identical stored results.

REPORTING: At the end of each phase report: what works as test IDs passed, what fails with observed and expected values, any deviation and why, files changed, and what is needed next. Never report a test as passed unless it was run.
```
