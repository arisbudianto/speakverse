# SpeakVerse M00 staging runbook

## Scope and safety
This is a new isolated staging environment. Do not copy production .env or student data.
Keep production and staging databases, keys, storage and OAuth callback URLs separate.
Do not deploy to production from this workflow.

## Prerequisites
Docker Engine + Compose plugin; reverse proxy with TLS; a staging hostname; backup plan.
Ensure ports 80/443 at the reverse proxy, not MySQL, are accessible externally.
Current application uses Laravel 13 (composer.json), PHP ^8.3, Vite 8 and MySQL-compatible migrations.
Confirm PHP extensions, build and migrations with CI before accepting M00.

## Local verification
1. cp .env.staging.example .env.staging
2. Fill APP_KEY and database passwords securely. Generate a staging APP_KEY separately.
3. docker compose --env-file .env.staging config
4. docker compose --env-file .env.staging build
5. docker compose --env-file .env.staging up -d
6. docker compose --env-file .env.staging exec app php artisan migrate --force
7. docker compose --env-file .env.staging exec app php artisan storage:link
8. curl -f http://127.0.0.1:8080/up
9. Verify login, reading quiz, hints, teacher permissions, uploads, Vite assets, and Google OAuth on staging.

Note: MySQL application password must equal DB_PASSWORD in .env.staging.
The database volume persists; changing MYSQL_PASSWORD does not reset an existing user.

## Promotion process
Feature branch -> PR to staging -> CI green -> deploy exact reviewed commit to staging
-> test migrations/backup/restore/rollback -> reviewer approval -> separate PR staging to main.
The staging workflow is currently a MANUAL RELEASE GATE, NOT a server deployment.
Never run migrate:fresh, db:wipe or destructive seeders on existing datasets.

## Rollback
Keep the previous image tag and database backup.
For non-backward-compatible migrations, restore the corresponding staging DB backup
before restoring the previous image. Verify /up and a representative student flow.

## Acceptance criteria
- No modification of main branch until a separately approved PR
- Build and test pass on PHP 8.4 and MySQL 8.4
- No secrets in repository, logs or artifacts
- Independent staging APP_KEY, MySQL and storage
- Smoke test /up and authenticated user flows pass
- Backup/restore and rollback documented and tested
