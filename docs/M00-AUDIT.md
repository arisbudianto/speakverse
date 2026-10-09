# M00 compatibility audit

Verified in main source tree:
- composer.json: Laravel ^13.7, PHP ^8.3, Socialite ^5.29, PHPUnit ^12.5.12.
- package.json: Vite ^8, Tailwind, Alpine.js, axios.
- config/database.php supports mysql and sqlite; phpunit.xml defaults to SQLite memory.
- bootstrap/app.php registers /up health endpoint.
- app/Services/Learning already contains DiagnosticEngine, ScaffoldingEngine,
  AdaptationPolicy, InteractionLogger and TeacherOverrideService.
- tests/Unit/ScaffoldingEngineLeakTest.php exists.
- public/build is committed, so CI rebuilds assets to avoid stale artifacts.
- public/storage is present and should be checked for symlink compatibility.
- Repository is public: audit commit history for secrets and student data.

Decisions:
- No framework downgrade; preserve existing app/ routes/ migrations/ models/ and composer.lock.
- Docker uses PHP 8.4 + MySQL 8.4, with Node 22 asset build.
- CI tests against MySQL, not only SQLite.
- Staging workflow deliberately does not deploy without server details.

Open verification items:
- Runtime compatibility, migrations, file uploads, auth, Google OAuth, jobs,
  third-party LLM APIs, actual staging hostname and TLS, and production data safety.
