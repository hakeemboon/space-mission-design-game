# Space Mission Design Game: TerraWatch-1

An educational browser game for the **2026 NASA Space Apps Challenge, Challenge #13 "Space Mission Design Game"**.

You are the Mission Design Lead for **TerraWatch-1**, a fictional satellite in low Earth orbit. Choose a science question about Earth (land cover, coasts or clouds), turn it into requirements, and design the whole mission across the four segments NASA describes: **Launch, Space, Ground and User**. Live meters show mass, power, energy, battery, data and budget as you choose. A deterministic simulation then flies the mission month by month, explains what worked and what failed, shows what the users actually received, and lets you redesign and try again.

> Simplified educational model. Not professional engineering software and not a substitute for NASA's General Mission Analysis Tool (GMAT). Mission Units (MU) are fictional game currency. Not endorsed or certified by NASA.

## How to play

1. Start: enter a nickname and pick Beginner or Standard.
2. Read the briefing on the four mission segments.
3. Choose an objective: land cover, coastal or cloud patterns.
4. Read the six requirements (R1 to R6).
5. Design: launcher, orbit, bus, instruments, imaging time, power system, radio, add-ons and ground stations. Fix every red failure.
6. Review for launch, launch, and watch the replay.
7. Read the results (score factors with your own numbers, and "What the users received"), then debrief and redesign.

Suggested demo: fly the weak starter design (science 21, objective not met), follow the debrief advice, and redesign it into a success (science 100).

## What is real and what is a game value

| Label | Meaning | Examples |
| --- | --- | --- |
| NASA fact | Stated by a NASA source that was opened and read; shown as fact cards with a link | The four mission segments; Sun-synchronous orbits |
| Educational approximation | Simplified physics | Orbital period, sunlight hours and eclipse minutes, seeded per orbit |
| Game value | Fictional, chosen so trade-offs are visible | Every mass, power, data rate, cost, lifetime and score |

All NASA resources used are listed in the in-game **Mission Library** (`/sources.php`).

## Technology

HTML, CSS, Tailwind CSS (standalone CLI, no Node.js), vanilla JavaScript, vanilla PHP 8.2 and MySQL/MariaDB. There are no frameworks and no runtime external APIs.

- The **server (PHP) is authoritative** for validation, simulation and scores (`src/services/engine.php`).
- The browser mirror (`public/assets/js/engine.js`) is for live display only. Test SIM-03 checks that the two agree.
- The simulation is deterministic. Every run stores a full input snapshot and its SHA-256 hash, so it can be replayed exactly.
- The database has 16 tables (`database/schema.sql`), seeded from the planning register (`database/seed.sql`).

## Run it locally (Windows + XAMPP)

1. Start MySQL/MariaDB.
2. Create a database `terrawatch` and a user for it.
3. Copy `config/config.example.php` to `config/config.local.php` and fill in the host, port, database, user and password.
4. Build the schema and seed data:
   ```
   php database/install.php
   ```
5. Optional, only after changing classes: rebuild the CSS. Download the Tailwind standalone CLI into `tools/` first.
   ```
   tools/tailwindcss.exe -i resources/css/input.css -o public/assets/css/app.css --minify
   ```
6. Serve the `public/` folder:
   ```
   php -S 127.0.0.1:8000 -t public
   ```

The app must be served from the root of a domain or subdomain, because it uses absolute paths such as `/designer.php`.

## Tests

| Command | What it checks |
| --- | --- |
| `php tests/db_check.php` | Seed counts and every catalogue value against the planning register (DB-01 to DB-03) |
| `php tests/engine_tests.php` | 64 acceptance tests: calculations, hard rules, warnings, compatibility, simulation, reference designs D1–D6, repeatability (rolled back) |
| `bash tests/play_cycle.sh` | End-to-end play-through over HTTP: D4 flown, then redesigned into D1 |
| `bash tests/security.sh [base] [prod_base]` | SEC-01 to SEC-09 |
| `/dev/sim03.php` (development only) | Browser engine against server engine on 1,506 designs |

## Deploying to shared hosting (for example cPanel)

1. Create a MySQL database and user in the hosting panel.
2. Import the database:
   - With SSH: run `php database/install.php`.
   - Without SSH: import `database/schema.sql`, then `database/seed.sql`, in phpMyAdmin.
3. Upload the project:
   - Best: point the domain's web root at `public/`.
   - Otherwise: upload the whole folder; the root `.htaccess` sends requests into `public/` and blocks the private folders.
4. Create `config/config.local.php` with the host's database details and set `'env' => 'production'`. That hides errors (they go to `storage/logs/`) and disables `/dev/`.
5. Make sure `storage/logs/` is writable.
6. Open the site in a private window and play one full mission.

## Security

- **Database and output:** PDO prepared statements everywhere, and every rendered value is escaped.
- **Forms and cookies:** a session token on every state-changing request; session cookies are HttpOnly and SameSite.
- **Headers:** Content-Security-Policy, X-Frame-Options, nosniff.
- **Server checks:** every submitted code is checked against the catalogue on the server, and browser-calculated values are never trusted.
- **No accounts or personal data:** only a nickname of up to 24 characters.

## Use of SI (AI) tools

- **Claude (Anthropic):** researching the NASA resources, writing the planning documents, and calculating expected test values.
- **Claude Code (Anthropic):** writing the application code and seed data. Every generated file carries an "AI-assisted" comment. The team reviewed the code.
- **ChatGPT (OpenAI):** project planning and review.
- **Higgsfield (GPT Image 2.6 model):** the four decorative background images in `public/assets/img/` (Earth from orbit, satellite, mission control, data grid). Each has a visible "SI-generated" watermark burned into the file, and alt text ending "(SI-generated image)". They are decoration only, and all hardware shown is fictional.

The team chose the game concept, the TerraWatch-1 mission direction and the technology. No generated content uses NASA logos, flags or mission identifiers.

## Credits and licence

- Tailwind CSS is MIT-licensed.
- NASA resources are linked, not copied. See the Mission Library.
- Background images were generated with Higgsfield for this project and are watermarked "SI-generated".
- The project licence is to be chosen by the team before publishing.
