-- TerraWatch-1 — MVP database schema: 16 tables (Technical Correction Addendum, section C1).
-- AI-assisted: written with Claude Code (Anthropic) and reviewed by the team.
-- Engine InnoDB, charset utf8mb4. Catalogue rows carry a stable `code` matching Document 02.
-- Snapshots and summaries are JSON text in LONGTEXT so the schema works on MySQL or MariaDB.

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS simulation_events;
DROP TABLE IF EXISTS simulation_metrics;
DROP TABLE IF EXISTS simulation_runs;
DROP TABLE IF EXISTS mission_selections;
DROP TABLE IF EXISTS missions;
DROP TABLE IF EXISTS fact_cards;
DROP TABLE IF EXISTS reference_sources;
DROP TABLE IF EXISTS game_rules;
DROP TABLE IF EXISTS rule_versions;
DROP TABLE IF EXISTS compatibility_matrix;
DROP TABLE IF EXISTS ground_stations;
DROP TABLE IF EXISTS launch_profiles;
DROP TABLE IF EXISTS orbit_profiles;
DROP TABLE IF EXISTS components;
DROP TABLE IF EXISTS difficulty_settings;
DROP TABLE IF EXISTS objectives;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. objectives (3 rows)
CREATE TABLE objectives (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code                VARCHAR(16)  NOT NULL,
    name                VARCHAR(80)  NOT NULL,
    science_question    VARCHAR(255) NOT NULL,
    user_personas       VARCHAR(255) NOT NULL,
    user_question       VARCHAR(255) NOT NULL,
    required_mb_per_day DECIMAL(10,2) NOT NULL,
    sort_order          TINYINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_objectives_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. difficulty_settings (3 rows)
CREATE TABLE difficulty_settings (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code                  VARCHAR(16) NOT NULL,
    name                  VARCHAR(40) NOT NULL,
    budget_mu             DECIMAL(10,2) NOT NULL,
    target_months         SMALLINT UNSIGNED NOT NULL,
    schedule_limit_months SMALLINT UNSIGNED NULL,
    assist_level          ENUM('FULL','STANDARD','MINIMAL') NOT NULL,
    description           VARCHAR(255) NOT NULL,
    is_enabled            TINYINT(1) NOT NULL DEFAULT 1,
    sort_order            TINYINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_difficulty_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. components (16 rows; category is a column). Category-specific columns are nullable.
--    BUS: power_w = housekeeping load. INSTRUMENT/COMMS/ADDON: power_w = draw.
CREATE TABLE components (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code               VARCHAR(16) NOT NULL,
    category           ENUM('BUS','INSTRUMENT','POWER','COMMS','ADDON') NOT NULL,
    name               VARCHAR(80) NOT NULL,
    description        VARCHAR(255) NOT NULL,
    mass_kg            DECIMAL(10,2) NOT NULL,
    cost_mu            DECIMAL(10,2) NOT NULL,
    power_w            DECIMAL(10,2) NOT NULL DEFAULT 0,
    payload_limit_kg   DECIMAL(10,2) NULL,
    instrument_slots   TINYINT UNSIGNED NULL,
    peak_capacity_w    DECIMAL(10,2) NULL,
    storage_gb         DECIMAL(10,2) NULL,
    design_life_months SMALLINT UNSIGNED NULL,
    dev_months         SMALLINT UNSIGNED NULL,
    data_rate_mbps     DECIMAL(10,2) NULL,
    generation_w       DECIMAL(10,2) NULL,
    battery_wh         DECIMAL(10,2) NULL,
    band               ENUM('S','X') NULL,
    provides_disposal  TINYINT(1) NOT NULL DEFAULT 0,
    classification     ENUM('NASA_FACT','EDUCATIONAL_APPROXIMATION','GAME_BALANCE') NOT NULL DEFAULT 'GAME_BALANCE',
    is_active          TINYINT(1) NOT NULL DEFAULT 1,
    sort_order         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_components_code (code),
    KEY ix_components_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. orbit_profiles (3 rows). Seeded values are engine inputs and are never recomputed.
CREATE TABLE orbit_profiles (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code               VARCHAR(16) NOT NULL,
    name               VARCHAR(80) NOT NULL,
    description        VARCHAR(255) NOT NULL,
    altitude_km        DECIMAL(10,2) NOT NULL,
    inclination_label  VARCHAR(60) NOT NULL,
    period_min         DECIMAL(10,2) NOT NULL,
    orbits_per_day     DECIMAL(10,2) NOT NULL,
    sunlight_h_per_day DECIMAL(10,2) NOT NULL,
    eclipse_min        DECIMAL(10,2) NOT NULL,
    sunlight_model     ENUM('GENERAL_ECLIPSE','DAWN_DUSK_FIXED') NOT NULL,
    decay_months       SMALLINT UNSIGNED NOT NULL,
    needs_disposal_aid TINYINT(1) NOT NULL DEFAULT 0,
    classification     ENUM('NASA_FACT','EDUCATIONAL_APPROXIMATION','GAME_BALANCE') NOT NULL DEFAULT 'EDUCATIONAL_APPROXIMATION',
    sort_order         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_orbit_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. launch_profiles (3 rows). Capacities per orbit live in compatibility_matrix.
CREATE TABLE launch_profiles (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(16) NOT NULL,
    name        VARCHAR(80) NOT NULL,
    description VARCHAR(255) NOT NULL,
    cost_mu     DECIMAL(10,2) NOT NULL,
    sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_launch_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. ground_stations (4 rows with band flags). Contact minutes live in compatibility_matrix.
CREATE TABLE ground_stations (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code           VARCHAR(16) NOT NULL,
    name           VARCHAR(80) NOT NULL,
    latitude_label VARCHAR(60) NOT NULL,
    supports_s     TINYINT(1) NOT NULL DEFAULT 0,
    supports_x     TINYINT(1) NOT NULL DEFAULT 0,
    cost_mu        DECIMAL(10,2) NOT NULL,
    sort_order     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_station_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. compatibility_matrix (43 rows): INSTRUMENT_SUIT 15, ORBIT_SUIT 9,
--    LAUNCH_CAPACITY 6, GROUND_CONTACT 12, CONFLICT 1. Codes are checked by tests/db_check.php.
CREATE TABLE compatibility_matrix (
    id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kind   ENUM('INSTRUMENT_SUIT','ORBIT_SUIT','LAUNCH_CAPACITY','GROUND_CONTACT','CONFLICT') NOT NULL,
    a_code VARCHAR(16) NOT NULL,
    b_code VARCHAR(16) NOT NULL,
    value  DECIMAL(10,2) NOT NULL,
    note   VARCHAR(255) NULL,
    UNIQUE KEY uq_matrix (kind, a_code, b_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. rule_versions
CREATE TABLE rule_versions (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    version           VARCHAR(16) NOT NULL,
    catalogue_version VARCHAR(16) NOT NULL,
    notes             VARCHAR(255) NULL,
    is_current        TINYINT(1) NOT NULL DEFAULT 0,
    released_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rule_version (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. game_rules: hard rules, warnings, events, parameters, and fixed text (messages, feedback).
CREATE TABLE game_rules (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rule_version_id  INT UNSIGNED NOT NULL,
    rule_code        VARCHAR(40) NOT NULL,
    rule_type        ENUM('HARD','SOFT','EVENT','PARAM','TEXT') NOT NULL,
    segment          ENUM('LAUNCH','SPACE','GROUND','USER','ALL') NULL,
    severity         ENUM('INFO','WARNING','CRITICAL') NULL,
    param_value      DECIMAL(14,6) NULL,
    message_template TEXT NULL,
    feedback_text    TEXT NULL,
    sort_order       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_game_rules (rule_version_id, rule_code),
    CONSTRAINT fk_rules_version FOREIGN KEY (rule_version_id) REFERENCES rule_versions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. reference_sources (11 rows: 9 challenge resources + 2 NASA orbit pages)
CREATE TABLE reference_sources (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code                  VARCHAR(16) NOT NULL,
    title                 VARCHAR(255) NOT NULL,
    url                   VARCHAR(500) NOT NULL,
    publisher             VARCHAR(120) NOT NULL,
    source_type           ENUM('FACTUAL','INSPIRATION','TECHNICAL') NOT NULL,
    is_challenge_resource TINYINT(1) NOT NULL DEFAULT 0,
    source_date           VARCHAR(60) NULL,
    verified_on           DATE NULL,
    how_used              VARCHAR(500) NOT NULL,
    sort_order            TINYINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_source_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. fact_cards (13 rows, F01 to F13)
CREATE TABLE fact_cards (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code           VARCHAR(8) NOT NULL,
    statement      VARCHAR(500) NOT NULL,
    classification ENUM('NASA_FACT','EDUCATIONAL_APPROXIMATION','GAME_BALANCE') NOT NULL DEFAULT 'NASA_FACT',
    screen         VARCHAR(40) NOT NULL,
    source_id      INT UNSIGNED NOT NULL,
    source_detail  VARCHAR(120) NULL,
    UNIQUE KEY uq_fact_code (code),
    KEY ix_fact_screen (screen),
    CONSTRAINT fk_fact_source FOREIGN KEY (source_id) REFERENCES reference_sources(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. missions: one saved design, owned by a browser session (no accounts).
CREATE TABLE missions (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_key_hash  CHAR(64) NOT NULL,
    nickname          VARCHAR(24) NOT NULL,
    objective_id      INT UNSIGNED NULL,
    difficulty_id     INT UNSIGNED NULL,
    bus_id            INT UNSIGNED NULL,
    power_id          INT UNSIGNED NULL,
    comms_id          INT UNSIGNED NULL,
    orbit_id          INT UNSIGNED NULL,
    launch_id         INT UNSIGNED NULL,
    imaging_minutes   TINYINT UNSIGNED NULL,
    status            ENUM('DRAFT','FLOWN') NOT NULL DEFAULT 'DRAFT',
    parent_mission_id INT UNSIGNED NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY ix_missions_session (session_key_hash),
    KEY ix_missions_objective (objective_id),
    CONSTRAINT fk_m_objective  FOREIGN KEY (objective_id)  REFERENCES objectives(id),
    CONSTRAINT fk_m_difficulty FOREIGN KEY (difficulty_id) REFERENCES difficulty_settings(id),
    CONSTRAINT fk_m_bus        FOREIGN KEY (bus_id)        REFERENCES components(id),
    CONSTRAINT fk_m_power      FOREIGN KEY (power_id)      REFERENCES components(id),
    CONSTRAINT fk_m_comms      FOREIGN KEY (comms_id)      REFERENCES components(id),
    CONSTRAINT fk_m_orbit      FOREIGN KEY (orbit_id)      REFERENCES orbit_profiles(id),
    CONSTRAINT fk_m_launch     FOREIGN KEY (launch_id)     REFERENCES launch_profiles(id),
    CONSTRAINT fk_m_parent     FOREIGN KEY (parent_mission_id) REFERENCES missions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. mission_selections: instruments, add-ons and stations. Exactly one of component_id / station_id is set.
CREATE TABLE mission_selections (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mission_id   INT UNSIGNED NOT NULL,
    item_type    ENUM('INSTRUMENT','ADDON','STATION') NOT NULL,
    component_id INT UNSIGNED NULL,
    station_id   INT UNSIGNED NULL,
    UNIQUE KEY uq_sel_component (mission_id, component_id),
    UNIQUE KEY uq_sel_station (mission_id, station_id),
    CONSTRAINT ck_sel_one_target CHECK (
        (item_type = 'STATION' AND station_id IS NOT NULL AND component_id IS NULL) OR
        (item_type <> 'STATION' AND component_id IS NOT NULL AND station_id IS NULL)
    ),
    CONSTRAINT fk_sel_mission   FOREIGN KEY (mission_id)   REFERENCES missions(id) ON DELETE CASCADE,
    CONSTRAINT fk_sel_component FOREIGN KEY (component_id) REFERENCES components(id),
    CONSTRAINT fk_sel_station   FOREIGN KEY (station_id)   REFERENCES ground_stations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. simulation_runs: immutable; full input snapshot and SHA-256 hash; summary JSON
--     holds C01–C18, warnings, requirement results R1–R6, failure_month and user outcome.
CREATE TABLE simulation_runs (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mission_id           INT UNSIGNED NOT NULL,
    run_number           SMALLINT UNSIGNED NOT NULL,
    rule_version_id      INT UNSIGNED NOT NULL,
    input_snapshot       LONGTEXT NOT NULL,
    input_hash           CHAR(64) NOT NULL,
    operational_months   SMALLINT UNSIGNED NOT NULL,
    end_reason           ENUM('TARGET','ENERGY','BUS_LIFE','DECAY') NOT NULL,
    science_score        DECIMAL(5,2) NOT NULL,
    efficiency_score     DECIMAL(5,2) NOT NULL,
    responsibility_score DECIMAL(5,2) NOT NULL,
    mission_score        DECIMAL(5,2) NOT NULL,
    outcome              ENUM('SUCCESS','PARTIAL','NOT_MET') NOT NULL,
    summary              LONGTEXT NOT NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_run_number (mission_id, run_number),
    KEY ix_run_hash (input_hash),
    KEY ix_run_outcome (outcome, mission_score),
    CONSTRAINT fk_run_mission FOREIGN KEY (mission_id) REFERENCES missions(id),
    CONSTRAINT fk_run_version FOREIGN KEY (rule_version_id) REFERENCES rule_versions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. simulation_metrics: exactly one row per operational month (1..L)
CREATE TABLE simulation_metrics (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_id        INT UNSIGNED NOT NULL,
    month_no      SMALLINT UNSIGNED NOT NULL,
    phase         ENUM('COMMISSIONING','SCIENCE') NOT NULL,
    generation_wh DECIMAL(12,2) NOT NULL,
    demand_wh     DECIMAL(12,2) NOT NULL,
    generated_mb  DECIMAL(14,2) NOT NULL,
    sent_mb       DECIMAL(14,2) NOT NULL,
    backlog_mb    DECIMAL(14,2) NOT NULL,
    lost_mb       DECIMAL(14,2) NOT NULL,
    UNIQUE KEY uq_metric_month (run_id, month_no),
    CONSTRAINT fk_metric_run FOREIGN KEY (run_id) REFERENCES simulation_runs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. simulation_events: ordered events
CREATE TABLE simulation_events (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_id     INT UNSIGNED NOT NULL,
    seq        SMALLINT UNSIGNED NOT NULL,
    month_no   SMALLINT UNSIGNED NOT NULL,
    event_code VARCHAR(32) NOT NULL,
    severity   ENUM('INFO','WARNING','CRITICAL') NOT NULL,
    segment    ENUM('LAUNCH','SPACE','GROUND','USER','ALL') NOT NULL,
    message    VARCHAR(500) NOT NULL,
    UNIQUE KEY uq_event_seq (run_id, seq),
    CONSTRAINT fk_event_run FOREIGN KEY (run_id) REFERENCES simulation_runs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
