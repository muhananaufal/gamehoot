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

   On Windows with the Docker engine inside WSL, also set `PROJECT_PATH` to the WSL path of the project (for example `/mnt/c/Projects/devivace/pentahoot`).

3. Build the image, install dependencies, and prepare the database:

   ```bash
   docker compose build
   docker compose run --rm -T app composer install
   docker compose run --rm -T app php artisan key:generate
   docker compose run --rm -T app php artisan migrate
   ```

4. Enable the Git hooks (Pint on staged PHP files, Conventional Commits check):

   ```bash
   git config core.hooksPath .githooks
   ```

5. Start the app at http://localhost:8000:

   ```bash
   docker compose up
   ```

## Quality gate

The same checks run in CI on every push. Run them before every commit and merge:

```bash
docker compose run --rm -T app php artisan test
docker compose run --rm -T app vendor/bin/pint --test
docker compose run --rm -T app vendor/bin/phpstan analyse
docker compose run --rm -T app composer audit
```

Tests refuse to run against a database whose name does not end in `_test`, because they drop every table first.
