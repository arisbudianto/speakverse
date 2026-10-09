# SpeakVerse M00: infrastructure and staging

This additive module supports the existing SpeakVerse Laravel 13 application.
It does not replace README.md or change existing application code.

## Overview
- `docker/php/Dockerfile`: PHP 8.4 FPM and Node 22 Vite build
- `docker/nginx/default.conf`: Nginx routing
- `compose.yaml`: app, web and isolated MySQL 8.4
- `.github/workflows/ci.yml`: Composer, Vite, MySQL migrations, PHPUnit and image build
- `.github/workflows/staging.yml`: manual staging release gate, no server deploy
- `docs/M00-AUDIT.md`: compatibility findings
- `docs/M00-STAGING.md`: staging, backup and rollback procedure

Read `docs/M00-STAGING.md` before running any commands.
The staging Docker configuration must be validated before live use.
Never commit `.env.staging` or actual credentials.

## Git workflow
feature/M00-foundation -> Pull Request -> staging -> staging validation
-> separate approved Pull Request -> main -> production release.

## Known limitation
No staging host or deployment credentials have been configured.
CI execution and runtime tests require GitHub Actions and a Docker-capable host.
