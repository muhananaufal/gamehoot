# Pentahoot

Internal event game platform: Pentahoot voting awards, Tebak Kata, and Tebak Gambar, played from a host panel, a projector screen, and participants' phones.

- Specification (every decision, with IDs): [`docs/pentahoot-spec.html`](docs/pentahoot-spec.html)
- Working rules for developers and agents: [`AGENTS.md`](AGENTS.md)
- Changes: [`CHANGELOG.md`](CHANGELOG.md)

## Local setup (O9)

Requirements: Docker with Compose, Git, and a MySQL 8.0+ server on your machine. The database is not a container (A9); the app reaches it through `host.docker.internal`.

1. Create the databases and a user on your MySQL server:

   ```sql
   CREATE DATABASE pentahoot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE DATABASE pentahoot_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'pentahoot'@'%' IDENTIFIED BY '<choose a password>';
   GRANT ALL PRIVILEGES ON pentahoot.* TO 'pentahoot'@'%';
   GRANT ALL PRIVILEGES ON pentahoot_test.* TO 'pentahoot'@'%';
   ```

2. Copy the environment file and fill in `DB_PASSWORD`:

   ```bash
   cp .env.example .env
   ```

   Fill in `REVERB_APP_ID`, `REVERB_APP_KEY` and `REVERB_APP_SECRET` with random values of your own (for example `php -r "echo bin2hex(random_bytes(16));"`). Web pages refuse to load while one of them is empty (K6).

   On Windows with the Docker engine inside WSL, also set `PROJECT_PATH` to the WSL path of the project (for example `/mnt/c/Projects/devivace/pentahoot`). If port 8000 or 8080 is already taken on your machine, set `APP_PORT` (and `APP_URL`) or `REVERB_CLIENT_PORT` to a free one.

3. Build the image, install dependencies, and prepare the database:

   ```bash
   docker compose build
   docker compose run --rm -T app composer install
   docker compose run --rm -T app php artisan key:generate
   docker compose run --rm -T app php artisan migrate
   docker compose run --rm -T app php artisan storage:link
   docker compose run --rm -T node npm ci
   ```

   `storage:link` makes the public question images of Tebak Gambar reachable under `/media` (F16).

4. Enable the Git hooks (Pint on staged PHP files, ESLint and Prettier on staged JS files, Conventional Commits check):

   ```bash
   git config core.hooksPath .githooks
   ```

5. Start the app at http://localhost:8000, or your `APP_PORT`. The `reverb` service runs the WebSocket server on port 8080 (`REVERB_CLIENT_PORT`), and the `node` service runs the Vite dev server on port 5173:

   ```bash
   docker compose up
   ```

## Realtime (F1–F23)

Every change a screen can see bumps `events.state_version` and sends the full snapshot over Reverb after the transaction commits. Screens drop older versions, poll `/{event}/state` while the socket is down, and refresh after a reconnect with a random delay.

To see it working: open an event's **Live control** page (`/host/{event}`), open the **Public View** (`/{event}/screen`) in another window, then claim a name from a phone or a private window. The join count updates on both without a reload. With `docker compose stop reverb`, both screens show a connection warning and keep updating every few seconds.

## Quality gate

The same checks run in CI on every push. Run them before every commit and merge:

```bash
docker compose run --rm -T app php artisan test
docker compose run --rm -T app vendor/bin/pint --test
docker compose run --rm -T app vendor/bin/phpstan analyse
docker compose run --rm -T app composer audit
docker compose run --rm -T node npx eslint .
docker compose run --rm -T node npx prettier --check resources/js tests/js "*.config.js"
docker compose run --rm -T node npx vitest run
```

Tests refuse to run against a database whose name does not end in `_test`, because they drop every table first.

## Browser tests (W11)

Browser tests drive real Chromium and WebKit (Safari) with Playwright, using the built assets. They live in `tests/Browser` and run in the `browser` image, not in `php artisan test`:

```bash
docker compose build browser                    # once, and after Dockerfile changes
docker compose run --rm -T node npm run build   # stop the Vite dev server first
docker compose run --rm browser php artisan test --testsuite=Browser --browser chrome
docker compose run --rm browser php artisan test --testsuite=Browser --browser safari
```

Browser tests run without a Reverb server, so their live screens update by polling; the socket path is covered by feature tests and the manual check above. Failure screenshots are written to `tests/Browser/Screenshots`. Known limits of the plugin (file uploads, WebKit sign in) are listed under W11 in the specification.
