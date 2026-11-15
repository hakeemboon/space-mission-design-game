-- TerraWatch-1 — seed data, catalogue version 1.0.0, rule version 1.0.0.
-- AI-assisted: written with Claude Code (Anthropic) and reviewed by the team.
--
-- CLASSIFICATION OF VALUES IN THIS FILE
--   GAME_BALANCE: every component mass, power, data rate, storage, lifetime, cost (MU),
--     suitability score, launch capacity, contact minutes, decay lifetime, budget,
--     required data per day, threshold and score weight. Fictional; NOT NASA specifications.
--   EDUCATIONAL_APPROXIMATION: orbit period, orbits per day, sunlight hours and eclipse
--     minutes (circular two-body orbit, cylindrical shadow). ORB-O2 uses a fixed 0.98
--     sunlit fraction (DAWN_DUSK_FIXED). These seeded one-decimal values are engine inputs
--     and must never be recomputed at run time.
--   NASA_FACT: the 13 fact cards only, each linked to the source it came from.
-- Source of every value: Document 02 (Scientific Parameters Register) as amended by the
-- Technical Correction Addendum. Do not change a value without bumping the catalogue version.

-- ---------------------------------------------------------------- objectives
INSERT INTO objectives (code, name, science_question, user_personas, user_question, required_mb_per_day, sort_order) VALUES
('OBJ-LAND',  'Land-cover observation',
 'How are forests, farmland and built-up areas changing from season to season and year to year?',
 'Regional land-use planner; agricultural adviser',
 'Where did forest, farmland and built-up areas change from season to season?', 4000.00, 1),
('OBJ-COAST', 'Coastal observation',
 'How are shorelines, sediment and water colour changing along the coast?',
 'Coastal-zone manager; fisheries officer',
 'Where are shorelines, sediment and water colour changing along the coast?', 8000.00, 2),
('OBJ-CLOUD', 'Cloud-pattern observation',
 'How do cloud systems form and move through the day and night?',
 'Weather forecaster; school science class',
 'How do cloud systems form and move through the day and night?', 2500.00, 3);

-- ---------------------------------------------------------------- difficulty_settings
INSERT INTO difficulty_settings (code, name, budget_mu, target_months, schedule_limit_months, assist_level, description, is_enabled, sort_order) VALUES
('DIFF-B', 'Beginner',  1300.00, 12, NULL, 'FULL',     'Bigger budget, 12-month target, options that cannot work are greyed out, hints shown automatically.', 1, 1),
('DIFF-S', 'Standard',  1150.00, 24, NULL, 'STANDARD', 'Normal budget, 24-month target, hints when you open a warning.', 1, 2),
('DIFF-C', 'Challenge', 1075.00, 36, 30,   'MINIMAL',  'Tight budget, 36-month target, development schedule warning above 30 months.', 0, 3);

-- ---------------------------------------------------------------- components (16)
-- Buses: power_w = housekeeping load (W)
INSERT INTO components (code, category, name, description, mass_kg, cost_mu, power_w, payload_limit_kg, instrument_slots, peak_capacity_w, storage_gb, design_life_months, dev_months, sort_order) VALUES
('BUS-S', 'BUS', 'Kestrel (small bus)',     'Small, cheap and short-lived. Room for one light instrument.',  14.00, 150.00, 18.00,  8.00, 1,  60.00,  16.00, 24, 18, 1),
('BUS-M', 'BUS', 'Heron (medium bus)',      'Balanced size, power and lifetime. Room for two instruments.',  55.00, 280.00, 40.00, 25.00, 2, 160.00,  64.00, 36, 24, 2),
('BUS-L', 'BUS', 'Albatross (large bus)',   'Heavy and expensive, long-lived. Room for three instruments.', 120.00, 430.00, 85.00, 45.00, 3, 320.00, 128.00, 60, 32, 3);

-- Instruments: dev_months = integration months
INSERT INTO components (code, category, name, description, mass_kg, cost_mu, power_w, data_rate_mbps, dev_months, sort_order) VALUES
('INS-WFC',  'INSTRUMENT', 'Wide-field visible camera',     'Broad pictures in visible light; low detail, low data.',                4.00,  60.00, 12.00,  2.00, 1, 4),
('INS-SWIR', 'INSTRUMENT', 'Short-wave infrared camera',    'Reflected infrared that separates vegetation, soil and moisture.',      7.00, 100.00, 25.00,  4.00, 2, 5),
('INS-MSI',  'INSTRUMENT', 'Multispectral imager',          'Several colour bands at moderate detail.',                             12.00, 140.00, 35.00,  8.00, 3, 6),
('INS-TIR',  'INSTRUMENT', 'Thermal infrared radiometer',   'Heat emitted by surfaces and cloud tops, day and night.',               9.00, 120.00, 30.00,  1.50, 3, 7),
('INS-HSI',  'INSTRUMENT', 'Hyperspectral coastal imager',  'Many narrow colour bands for water colour and sediment.',              18.00, 220.00, 55.00, 20.00, 5, 8);

-- Power systems
INSERT INTO components (code, category, name, description, mass_kg, cost_mu, generation_w, battery_wh, sort_order) VALUES
('PWR-P1', 'POWER', 'Body-mounted panels', 'Light and cheap; modest power and a small battery.',  4.00,  50.00,  50.00,  40.00,  9),
('PWR-P2', 'POWER', 'Deployable arrays',   'Fold-out arrays; good power for most designs.',      10.00, 110.00, 120.00, 110.00, 10),
('PWR-P3', 'POWER', 'Sun-tracking arrays', 'Most power and the biggest battery. Not on the small bus.', 19.00, 190.00, 260.00, 260.00, 11);

-- Communications
INSERT INTO components (code, category, name, description, mass_kg, cost_mu, power_w, data_rate_mbps, band, sort_order) VALUES
('COM-C1', 'COMMS', 'Low-rate radio',     'S-band, 2 Mbps. Light and frugal.',              1.50,  40.00, 10.00,  2.00, 'S', 12),
('COM-C2', 'COMMS', 'High-rate radio',    'S-band, 10 Mbps.',                               3.00,  90.00, 25.00, 10.00, 'S', 13),
('COM-C3', 'COMMS', 'X-band transmitter', 'X-band, 50 Mbps. Needs an X-band ground station.', 5.00, 170.00, 60.00, 50.00, 'X', 14);

-- Add-ons
INSERT INTO components (code, category, name, description, mass_kg, cost_mu, power_w, storage_gb, provides_disposal, sort_order) VALUES
('ADD-STO', 'ADDON', 'Memory expansion', 'Adds 64 GB of onboard storage; draws 5 W continuously.', 2.00, 40.00, 5.00, 64.00, 0, 15),
('ADD-DEO', 'ADDON', 'Deorbit kit',      'Brings the satellite down at end of mission.',           2.00, 25.00, 0.00, NULL,  1, 16);

-- ---------------------------------------------------------------- orbit_profiles (3)
INSERT INTO orbit_profiles (code, name, description, altitude_km, inclination_label, period_min, orbits_per_day, sunlight_h_per_day, eclipse_min, sunlight_model, decay_months, needs_disposal_aid, sort_order) VALUES
('ORB-O1', 'Sun-synchronous, mid-morning', 'Consistent lighting and near-global coverage; about 36 minutes of eclipse per orbit.',
 500.00, 'Sun-synchronous (near-polar)', 94.60, 15.20, 14.90, 35.80, 'GENERAL_ECLIPSE', 84, 0, 1),
('ORB-O2', 'Sun-synchronous, dawn-dusk', 'Almost continuous sunlight for power, but a low Sun angle for cameras. Stays up so long that a deorbit kit is expected.',
 600.00, 'Sun-synchronous (near-polar)', 96.70, 14.90, 23.50, 2.00, 'DAWN_DUSK_FIXED', 999, 1, 2),
('ORB-O3', '45-degree inclined', 'Frequent passes over the tropics and mid-latitudes; no polar coverage; shortest natural lifetime.',
 450.00, '45 degrees', 93.60, 15.40, 14.80, 35.90, 'GENERAL_ECLIPSE', 36, 0, 3);

-- ---------------------------------------------------------------- launch_profiles (3)
INSERT INTO launch_profiles (code, name, description, cost_mu, sort_order) VALUES
('LV-L1', 'Rideshare slot',           'Cheapest ride, but only to the main passenger''s orbit (ORB-O1) and only 60 kg.', 120.00, 1),
('LV-L2', 'Dedicated small launcher', 'Goes to any of the three orbits; most capacity to the inclined orbit.',          300.00, 2),
('LV-L3', 'Large rideshare port',     'Lots of capacity to the two Sun-synchronous orbits; does not fly to ORB-O3.',   210.00, 3);

-- ---------------------------------------------------------------- ground_stations (4, fictional names)
INSERT INTO ground_stations (code, name, latitude_label, supports_s, supports_x, cost_mu, sort_order) VALUES
('GS-G1', 'Aurora Polar Station',          'Polar',                1, 1, 120.00, 1),
('GS-G2', 'Campus Station',                'Mid-latitude',         1, 0,  40.00, 2),
('GS-G3', 'Equator Point Station',         'Equatorial',           1, 1,  70.00, 3),
('GS-G4', 'OrbitLink commercial network',  'Global network',       1, 1, 200.00, 4);

-- ---------------------------------------------------------------- compatibility_matrix (43)
-- INSTRUMENT_SUIT (15): a = instrument, b = objective
INSERT INTO compatibility_matrix (kind, a_code, b_code, value) VALUES
('INSTRUMENT_SUIT','INS-WFC','OBJ-LAND',0.40),('INSTRUMENT_SUIT','INS-WFC','OBJ-COAST',0.30),('INSTRUMENT_SUIT','INS-WFC','OBJ-CLOUD',0.80),
('INSTRUMENT_SUIT','INS-SWIR','OBJ-LAND',0.70),('INSTRUMENT_SUIT','INS-SWIR','OBJ-COAST',0.40),('INSTRUMENT_SUIT','INS-SWIR','OBJ-CLOUD',0.60),
('INSTRUMENT_SUIT','INS-MSI','OBJ-LAND',1.00),('INSTRUMENT_SUIT','INS-MSI','OBJ-COAST',0.70),('INSTRUMENT_SUIT','INS-MSI','OBJ-CLOUD',0.50),
('INSTRUMENT_SUIT','INS-TIR','OBJ-LAND',0.50),('INSTRUMENT_SUIT','INS-TIR','OBJ-COAST',0.50),('INSTRUMENT_SUIT','INS-TIR','OBJ-CLOUD',1.00),
('INSTRUMENT_SUIT','INS-HSI','OBJ-LAND',0.80),('INSTRUMENT_SUIT','INS-HSI','OBJ-COAST',1.00),('INSTRUMENT_SUIT','INS-HSI','OBJ-CLOUD',0.20);
-- ORBIT_SUIT (9): a = orbit, b = objective
INSERT INTO compatibility_matrix (kind, a_code, b_code, value) VALUES
('ORBIT_SUIT','ORB-O1','OBJ-LAND',1.00),('ORBIT_SUIT','ORB-O1','OBJ-COAST',1.00),('ORBIT_SUIT','ORB-O1','OBJ-CLOUD',0.85),
('ORBIT_SUIT','ORB-O2','OBJ-LAND',0.60),('ORBIT_SUIT','ORB-O2','OBJ-COAST',0.60),('ORBIT_SUIT','ORB-O2','OBJ-CLOUD',0.90),
('ORBIT_SUIT','ORB-O3','OBJ-LAND',0.70),('ORBIT_SUIT','ORB-O3','OBJ-COAST',0.80),('ORBIT_SUIT','ORB-O3','OBJ-CLOUD',1.00);
-- LAUNCH_CAPACITY (6): a = launch profile, b = orbit, value = kg. A missing pair means not served.
INSERT INTO compatibility_matrix (kind, a_code, b_code, value) VALUES
('LAUNCH_CAPACITY','LV-L1','ORB-O1', 60.00),
('LAUNCH_CAPACITY','LV-L2','ORB-O1',150.00),('LAUNCH_CAPACITY','LV-L2','ORB-O2',130.00),('LAUNCH_CAPACITY','LV-L2','ORB-O3',220.00),
('LAUNCH_CAPACITY','LV-L3','ORB-O1',220.00),('LAUNCH_CAPACITY','LV-L3','ORB-O2',200.00);
-- GROUND_CONTACT (12): a = station, b = orbit, value = contact minutes per day (passes x 8 min)
INSERT INTO compatibility_matrix (kind, a_code, b_code, value) VALUES
('GROUND_CONTACT','GS-G1','ORB-O1',80.00),('GROUND_CONTACT','GS-G1','ORB-O2',80.00),('GROUND_CONTACT','GS-G1','ORB-O3', 0.00),
('GROUND_CONTACT','GS-G2','ORB-O1',32.00),('GROUND_CONTACT','GS-G2','ORB-O2',32.00),('GROUND_CONTACT','GS-G2','ORB-O3',40.00),
('GROUND_CONTACT','GS-G3','ORB-O1',24.00),('GROUND_CONTACT','GS-G3','ORB-O2',24.00),('GROUND_CONTACT','GS-G3','ORB-O3',56.00),
('GROUND_CONTACT','GS-G4','ORB-O1',96.00),('GROUND_CONTACT','GS-G4','ORB-O2',96.00),('GROUND_CONTACT','GS-G4','ORB-O3',96.00);
-- CONFLICT (1)
INSERT INTO compatibility_matrix (kind, a_code, b_code, value, note) VALUES
('CONFLICT','PWR-P3','BUS-S',1.00,'Sun-tracking arrays do not fit the small bus.');

-- ---------------------------------------------------------------- rule_versions
INSERT INTO rule_versions (version, catalogue_version, notes, is_current) VALUES
('1.0.0', '1.0.0', 'Documents 02 and 04 as amended by the Technical Correction Addendum (14 Nov 2026).', 1);
SET @rv = LAST_INSERT_ID();

-- ---------------------------------------------------------------- game_rules: parameters (GAME_BALANCE unless noted)
INSERT INTO game_rules (rule_version_id, rule_code, rule_type, param_value, message_template, sort_order) VALUES
(@rv,'USABLE_BATTERY_FRACTION','PARAM',0.5,'G06 usable battery fraction',1),
(@rv,'SOLAR_LOSS_PER_MONTH','PARAM',0.0025,'G07 solar generation loss per month, linear',2),
(@rv,'MASS_MARGIN_WARN','PARAM',0.10,'G08 warn when mass margin is below this share of launch capacity',3),
(@rv,'ENERGY_MARGIN_WARN','PARAM',1.15,'G09 warn when generation is below this multiple of demand',4),
(@rv,'DISPOSAL_DECAY_LIMIT','PARAM',300,'G10 disposal acceptable if natural decay within this many months, or deorbit kit fitted',5),
(@rv,'SECOND_INSTRUMENT_BONUS','PARAM',0.25,'G11 share of the second-best instrument suitability added',6),
(@rv,'SUCCESS_THRESHOLD','PARAM',75,'G12 science score for mission success',7),
(@rv,'PARTIAL_THRESHOLD','PARAM',50,'G12 science score for partial success',8),
(@rv,'WEIGHT_SCIENCE','PARAM',0.7,'G13 mission score weight',9),
(@rv,'WEIGHT_EFFICIENCY','PARAM',0.2,'G13 mission score weight',10),
(@rv,'WEIGHT_RESPONSIBILITY','PARAM',0.1,'G13 mission score weight',11),
(@rv,'EFFICIENCY_BUDGET_REF','PARAM',0.2,'Budget left is scored against this share of the budget',12),
(@rv,'EFFICIENCY_MASS_REF','PARAM',0.2,'Mass margin is scored against this share of launch capacity',13),
(@rv,'RESP_DISPOSAL_POINTS','PARAM',60,'Responsibility points for acceptable disposal',14),
(@rv,'RESP_NO_LOSS_POINTS','PARAM',40,'Responsibility points when no data was lost',15),
(@rv,'REQ_INSTRUMENT_SUIT_MIN','PARAM',0.8,'R1 / W07 best instrument suitability threshold',16),
(@rv,'REQ_ORBIT_SUIT_MIN','PARAM',0.8,'R2 / W08 orbit suitability threshold',17),
(@rv,'ENERGY_LOW_EVENT','PARAM',0.15,'EV-ENERGY-LOW fires the first month energy margin is below this',18),
(@rv,'MILESTONE_INTERVAL','PARAM',6,'EV-MILESTONE every this many months',19),
(@rv,'COMMISSIONING_MONTHS','PARAM',1,'Months with no science data after launch',20),
(@rv,'DAYS_PER_MONTH','PARAM',30,'Convention: one month is 30 days',21),
(@rv,'MB_PER_GB','PARAM',1000,'Convention: 1 GB = 1,000 MB',22),
(@rv,'BITS_PER_BYTE','PARAM',8,'Convention: 1 MB = 8 Mb',23),
(@rv,'MINUTES_PER_DAY','PARAM',1440,'Convention',24),
(@rv,'MAX_INSTRUMENTS','PARAM',3,'H01: one to three distinct instruments',25),
(@rv,'MAX_STATIONS','PARAM',2,'H01: one or two distinct stations',26);
INSERT INTO game_rules (rule_version_id, rule_code, rule_type, message_template, sort_order) VALUES
(@rv,'IMAGING_OPTIONS','PARAM','5,10,15',27);

-- ---------------------------------------------------------------- game_rules: hard failures (Document 04)
INSERT INTO game_rules (rule_version_id, rule_code, rule_type, segment, message_template, feedback_text, sort_order) VALUES
(@rv,'H01','HARD','ALL',   'Your design is incomplete: {detail}.', 'Choose one of each required part: bus, at least one instrument, power system, radio, at least one ground station, orbit and launcher.', 101),
(@rv,'H02','HARD','SPACE', 'This bus has room for {limit} instrument(s); you selected {value}.', 'Remove an instrument or choose a larger bus.', 102),
(@rv,'H03','HARD','SPACE', 'The instruments are too heavy for this bus: {value} kg against a {limit} kg payload limit.', 'Choose lighter instruments or power system, a larger bus, or a launcher with more capacity.', 103),
(@rv,'H04','HARD','SPACE', 'These two parts do not fit together: {detail}.', 'Choose a different power system or a larger bus.', 104),
(@rv,'H05','HARD','LAUNCH','This launcher does not fly to that orbit.', 'Pick a launcher that serves your orbit, or a different orbit.', 105),
(@rv,'H06','HARD','LAUNCH','The spacecraft is too heavy for this launch: {value} kg against {limit} kg.', 'Choose lighter instruments or power system, a larger bus, or a launcher with more capacity.', 106),
(@rv,'H07','HARD','SPACE', 'Too much is switched on at once for this bus: {value} W against {limit} W.', 'Use a lower-power radio or instrument, or a bus with more power capacity.', 107),
(@rv,'H08','HARD','GROUND','This station cannot hear this radio: {detail}.', 'Pick a station that supports your radio''s band and can see your orbit.', 108),
(@rv,'H09','HARD','GROUND','No selected station ever sees this orbit (0 contact minutes per day).', 'Pick a station that supports your radio''s band and can see your orbit.', 109),
(@rv,'H10','HARD','SPACE', 'The arrays do not make enough energy per day: {value} Wh generated against {limit} Wh needed.', 'Fit a larger power system, image for fewer minutes, or consider the dawn-dusk orbit.', 110),
(@rv,'H11','HARD','SPACE', 'The battery cannot carry the spacecraft through eclipse: {value} Wh needed against {limit} Wh usable.', 'Fit a power system with a larger battery.', 111),
(@rv,'H12','HARD','ALL',   'The mission is over budget: {value} MU against {limit} MU.', 'Find the most expensive item that is larger than the requirement needs.', 112);

-- ---------------------------------------------------------------- game_rules: warnings (Document 04)
INSERT INTO game_rules (rule_version_id, rule_code, rule_type, segment, message_template, feedback_text, sort_order) VALUES
(@rv,'W01','SOFT','LAUNCH','Mass margin is only {value} kg, below 10% of the {limit} kg launch capacity.', 'Choose lighter instruments or power system, a larger bus, or a launcher with more capacity.', 201),
(@rv,'W02','SOFT','SPACE', 'Energy margin is thin: {value} Wh generated against {limit} Wh needed (below 115%).', 'Fit a larger power system, image for fewer minutes, or consider the dawn-dusk orbit.', 202),
(@rv,'W03','SOFT','GROUND','More data is made ({value} MB/day) than can be sent ({limit} MB/day). Storage fills in about {detail} days.', 'Add contact time, a faster radio or memory; or image less.', 203),
(@rv,'W04','SOFT','SPACE', 'Storage will overflow before the target lifetime, so some data will be lost.', 'Add contact time, a faster radio or memory; or image less.', 204),
(@rv,'W05','SOFT','SPACE', 'This orbit does not decay on its own and no deorbit kit is fitted.', 'Add the deorbit kit or choose an orbit that decays naturally.', 205),
(@rv,'W06','SOFT','SPACE', 'Predicted operational lifetime is {value} months, below the {limit}-month target.', 'Choose a longer-lived bus, a higher orbit, or more power margin.', 206),
(@rv,'W07','SOFT','USER',  'Best instrument suitability is {value}, below 0.8 (requirement R1).', 'Match the instrument to the objective.', 207),
(@rv,'W08','SOFT','USER',  'Orbit suitability is {value}, below 0.8 (requirement R2).', 'Match the orbit to the objective.', 208),
(@rv,'W09','SOFT','USER',  'Data reaching users is {value} MB/day, below the {limit} MB/day required (requirement R3).', 'Add contact time, a faster radio or memory; or image less.', 209),
(@rv,'W10','SOFT','GROUND','{detail} never sees this orbit, while your other station does.', 'Pick a station that supports your radio''s band and can see your orbit.', 210),
(@rv,'W11','SOFT','ALL',   'Development takes {value} months, above the {limit}-month schedule limit.', 'Choose a bus or instruments that are quicker to build.', 211);

-- ---------------------------------------------------------------- game_rules: events (Document 04 + addendum B3)
INSERT INTO game_rules (rule_version_id, rule_code, rule_type, segment, severity, message_template, sort_order) VALUES
(@rv,'EV-LAUNCH','EVENT','LAUNCH','INFO','Month 0: launch on {launch}. Mass {mass} kg of {capacity} kg capacity.',301),
(@rv,'EV-COMMISSIONED','EVENT','SPACE','INFO','Month 1: arrays deployed and health checks complete. Science operations begin.',302),
(@rv,'EV-FIRST-DATA','EVENT','GROUND','INFO','Month {month}: first science data received on the ground ({sent} MB this month).',303),
(@rv,'EV-MILESTONE','EVENT','USER','INFO','Month {month}: {delivered} GB delivered to users so far.',304),
(@rv,'EV-BACKLOG','EVENT','GROUND','WARNING','Month {month}: data is waiting on board ({backlog} MB). More is made than can be sent.',305),
(@rv,'EV-STORAGE-FULL','EVENT','SPACE','CRITICAL','Month {month}: storage full. {lost} MB recorded this month was overwritten before it could be sent.',306),
(@rv,'EV-ENERGY-LOW','EVENT','SPACE','WARNING','Month {month}: energy margin fell below 15% ({generation} Wh generated against {demand} Wh needed).',307),
(@rv,'EV-POWER-NEGATIVE','EVENT','SPACE','CRITICAL','Month {failure_month}: daily generation ({generation} Wh) fell below daily demand ({demand} Wh). The spacecraft entered safe mode and science operations ended. It operated for {L} months.',308),
(@rv,'EV-BUS-EOL','EVENT','SPACE','WARNING','Month {month}: the bus reached its {life}-month design life and the mission ended.',309),
(@rv,'EV-ORBIT-DECAY','EVENT','LAUNCH','WARNING','Month {month}: atmospheric drag brought the orbit down and the mission ended.',310),
(@rv,'EV-TARGET-REACHED','EVENT','USER','INFO','Month {month}: the {target}-month target lifetime was reached.',311),
(@rv,'EV-DISPOSAL-OK','EVENT','SPACE','INFO','Month {month}: end of mission. {how}',312),
(@rv,'EV-DISPOSAL-RISK','EVENT','SPACE','WARNING','Month {month}: end of mission. This orbit does not decay on its own and no deorbit kit was fitted, so the satellite stays up as debris.',313);

-- ---------------------------------------------------------------- game_rules: fixed text (feedback for science factors, user panel, results)
INSERT INTO game_rules (rule_version_id, rule_code, rule_type, message_template, sort_order) VALUES
(@rv,'FB-LOW-DATA','TEXT','Add contact time, a faster radio or memory; or image less.',401),
(@rv,'FB-LOW-LIFETIME','TEXT','Choose a longer-lived bus, a higher orbit, or more power margin.',402),
(@rv,'FB-LOW-INSTRUMENT','TEXT','Match the instrument to the objective.',403),
(@rv,'FB-LOW-ORBIT','TEXT','Match the orbit to the objective.',404),
(@rv,'U4-DATA','TEXT','Only {percent}% of the data needed each day reached the ground, so some areas or dates are missing.',411),
(@rv,'U4-LOST','TEXT','{lost} GB recorded on board was overwritten before it could be sent.',412),
(@rv,'U4-LIFETIME','TEXT','Observations stopped after {L} of {target} months, so longer-term change cannot be followed.',413),
(@rv,'U4-INSTRUMENT','TEXT','The instrument is not the best match for this question, so some features cannot be told apart.',414),
(@rv,'U4-ORBIT','TEXT','From this orbit, lighting or coverage is not ideal for this question.',415),
(@rv,'U4-NONE','TEXT','Within this simplified model, the users received what they asked for.',416),
(@rv,'U-DISCLAIMER','TEXT','Illustrative. The game does not produce real images or data products.',417),
(@rv,'RESULT-LIFETIME','TEXT','Operational lifetime: {L} of {target} months.',418),
(@rv,'RESULT-LIFETIME-ENERGY','TEXT','Operational lifetime: {L} of {target} months. Ended by a power shortfall predicted for month {failure_month}.',419);

-- ---------------------------------------------------------------- reference_sources (11)
INSERT INTO reference_sources (code, title, url, publisher, source_type, is_challenge_resource, source_date, verified_on, how_used, sort_order) VALUES
('SRC-01','Design and Develop Science Missions','https://www.nasa.gov/wp-content/uploads/2025/04/250323-design-and-develop-science-mission.pdf','NASA','FACTUAL',1,'26 March 2025','2026-11-13','The four mission segments (launch, space, ground, user) that structure every screen; concept of operations; life-cycle phase names on the timeline.',1),
('SRC-02','General Mission Analysis Tool (GMAT)','https://software.nasa.gov/software/GSC-19468-1','NASA','TECHNICAL',1,'Version R2025a','2026-11-13','Named as the professional tool this simplified game is not. No GMAT output is used.',2),
('SRC-03','Concept Maturity Levels','https://dataverse.jpl.nasa.gov/dataset.xhtml?persistentId=hdl:2014/44299','NASA Jet Propulsion Laboratory','FACTUAL',1,'2013 (AIAA SPACE)',NULL,'The simplified four-step progression from idea to point design. Content taken from the NASA Ames and Goddard pages that describe it.',3),
('SRC-04','NASA Ames Mission Design Center: About Our Studies','https://www.nasa.gov/ames-engineering/spaceflight-division/mission-design-center/about-our-studies/','NASA Ames Research Center','FACTUAL',1,'Updated 18 December 2023','2026-11-13','Feasibility within constraints and trade-space comparison: the constraint dashboard and the redesign loop.',4),
('SRC-05','Mission Design Lab','https://etd.gsfc.nasa.gov/capabilities/integrated-design-center/mission-design-lab/','NASA Goddard Space Flight Center','FACTUAL',1,'2026 update','2026-11-13','Mass, power and data rate as the resources every subsystem estimates (the dashboard); end of mission as part of design.',5),
('SRC-06','NASA Mission Viz','https://nasa.github.io/mission-viz/','NASA','INSPIRATION',1,'Tutorials about 2015 to 2018','2026-11-13','Idea for a simple orbit and ground-contact view on the simulation screen. No code reused.',6),
('SRC-07','Build It Yourself: Satellite!','https://science.nasa.gov/mission/webb/game-build-it-yourself/','NASA','INSPIRATION',1,'Modified 17 December 2025','2026-11-13','Objective-first flow, options that explain why they cannot work, three difficulty levels.',7),
('SRC-08','CubeSat 101: Basic Concepts and Processes for First-Time CubeSat Developers','https://www.nasa.gov/wp-content/uploads/2017/03/nasa_csli_cubesat_101_508.pdf','NASA CubeSat Launch Initiative','FACTUAL',1,'October 2017','2026-11-13','Small-satellite size and development-time facts; development time versus operational lifetime.',8),
('SRC-09','Digital games and activities','https://www.asc-csa.gc.ca/eng/youth-educators/digital-games-and-activities.asp','Canadian Space Agency','INSPIRATION',1,'Modified 26 November 2025','2026-11-13','Space Agency Partner inspiration for showing the user segment as named people who need the data. No CSA content reused.',9),
('SRC-10','Catalog of Earth Satellite Orbits','https://science.nasa.gov/earth/earth-observatory/catalog-of-earth-satellite-orbits/','NASA Earth Observatory','FACTUAL',0,NULL,'2026-11-13','Definitions of inclination and Sun-synchronous orbits; the Aqua orbital period used to cross-check the period formula.',10),
('SRC-11','Earth Observation Data Basics: Orbits','https://www.earthdata.nasa.gov/learn/earth-observation-data-basics/orbits','NASA Earthdata','FACTUAL',0,NULL,'2026-11-13','Low Earth orbit altitude range; non-polar orbits cover only a band of latitudes.',11);

-- ---------------------------------------------------------------- fact_cards (13, NASA_FACT)
INSERT INTO fact_cards (code, statement, screen, source_id, source_detail)
SELECT 'F01','A science mission has four segments: launch, space, ground and user.','briefing',id,NULL FROM reference_sources WHERE code='SRC-01' UNION ALL
SELECT 'F02','A mission''s life cycle runs through named phases, from Pre-Phase A (concept study) to Phase E (operations).','preflight',id,NULL FROM reference_sources WHERE code='SRC-01' UNION ALL
SELECT 'F03','The Concept of Operations is written early and describes how the system will be used.','requirements',id,NULL FROM reference_sources WHERE code='SRC-01' UNION ALL
SELECT 'F04','Low Earth orbit spans roughly 160 to 2,000 km altitude.','designer-orbit',id,NULL FROM reference_sources WHERE code='SRC-11' UNION ALL
SELECT 'F05','NASA''s Aqua satellite orbits at about 705 km and takes about 99 minutes per orbit.','designer-orbit',id,NULL FROM reference_sources WHERE code='SRC-10' UNION ALL
SELECT 'F06','Inclination is the angle between the orbit plane and the equator: 0 degrees over the equator, 90 degrees over the poles.','designer-orbit',id,NULL FROM reference_sources WHERE code='SRC-10' UNION ALL
SELECT 'F07','A Sun-synchronous orbit crosses the equator at about the same local solar time each day, which keeps lighting consistent for comparing images over years.','designer-orbit',id,NULL FROM reference_sources WHERE code='SRC-10' UNION ALL
SELECT 'F08','A polar-orbiting satellite views most of Earth twice in 24 hours, once in daylight and once in darkness.','designer-instrument',id,NULL FROM reference_sources WHERE code='SRC-10' UNION ALL
SELECT 'F09','A non-polar low orbit covers only a band of latitudes (for example, GPM covers 65 degrees north to 65 degrees south).','designer-ground',id,NULL FROM reference_sources WHERE code='SRC-11' UNION ALL
SELECT 'F10','Atmospheric drag pulls low-orbit satellites down, and more strongly when the Sun is active.','results',id,NULL FROM reference_sources WHERE code='SRC-10' UNION ALL
SELECT 'F11','A 1U CubeSat is a 10 cm cube of about 1 to 1.33 kg.','designer-bus',id,'Section 1.1' FROM reference_sources WHERE code='SRC-08' UNION ALL
SELECT 'F12','A CubeSat typically takes 18 to 24 months to design, build, test and deliver.','preflight',id,'Chapter 2' FROM reference_sources WHERE code='SRC-08' UNION ALL
SELECT 'F13','In concept studies, each spacecraft subsystem estimates its mass, power and data rate.','designer',id,NULL FROM reference_sources WHERE code='SRC-05';
