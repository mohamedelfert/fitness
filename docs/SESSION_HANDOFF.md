# SESSION_HANDOFF.md — Resume Here

> **Purpose:** everything needed to continue Fitness OS in a fresh conversation without starting over.
> **Last updated:** 2026-06-13 · **Repo:** local `/home/mohamed/Desktop/work/fitness` → **remote** `github.com/mohamedelfert/fitness` (branch `main`).

---

## 1. What this project is (30-second version)
**Fitness OS** — a multi-tenant SaaS for the whole fitness value chain: individuals (B2C), coaches (B2B), gyms (B2B enterprise), connected by one user-owned identity, one data graph, and one AI brain. Full strategy in `docs/MASTER_PRODUCT.md`. The 10 design docs live in `docs/`; start with `README.md` then `docs/IMPLEMENTATION_PROGRESS.md` (the live tracker).

## 2. Confirmed decisions (don't re-litigate)
- **A1** — Build sequence: **B2C + AI first**, then Coach (P2), then Gym (P3).
- **A2** — **MENA-first, Arabic + English, RTL & data-residency day-one**, global-ready.
- **A3** — **Person owns their data**; tenants get scoped, revocable consent (three-layer authz: RBAC × tenant × consent).
- Architecture: **modular monolith**, **two-plane tenancy** (central Person/Graph vs tenant-scoped ops), **hybrid** physical tenancy, ULID PKs, append-only logs, double-entry ledger. See `docs/SYSTEM_ARCHITECTURE.md` (ADRs in §12).
- Web platform serves **member/individual + coach + gym** (member web = responsive TALL companion to the Flutter app).

## 3. ⚠️ Build-environment realities (THESE WILL TRIP YOU UP — read before running anything)
- **Sandbox has NO network.** Run `composer`, `npm`, `git push`, `git ls-remote` with `dangerouslyDisableSandbox: true`. Plain file/test/artisan commands run fine sandboxed.
- **Composer is SLOW** (no GitHub token → falls back to git-source clones; SSL timeouts happen). **Never launch two composer jobs at once** — they corrupt `vendor/`. Long commands auto-background; **wait for the completion notification, don't poll or relaunch.** Retry on transient `curl error 28`.
- **PHP is 8.2** (brief wants 8.3+). Laravel 12 runs fine on it.
- **No `pdo_sqlite`** → tests run on **MariaDB** (`fitness_os_test`), configured in `apps/api/phpunit.xml`. Not in-memory SQLite.
- **No `ext-intl`** on host → **Filament was installed with `--ignore-platform-req=ext-intl`**. The admin panel **renders only in Docker/CI** (PHP 8.3 + intl). Its 3 smoke tests **auto-skip on the host** (expected). For local Filament: `sudo apt install php8.2-intl`.
- **Flutter not installed** → mobile apps are placeholder dirs.
- **DB creds:** MariaDB `root`/`root`. Project DBs: `fitness_os` (dev), `fitness_os_test` (test). **Do not touch** other DBs on this machine (`fitness_app*` etc. belong to other projects).
- **Git:** identity set (Mohamed Ibrahiem / mohamedelfert@yahoo.com); credential stored; `git push` works with sandbox disabled.
- **COMMITS MUST NOT include the `Co-Authored-By` trailer** (user instruction — verified absent across history).

## 4. What's DONE (Phase 0 — substantially complete, all pushed to `main`)
- 10 design docs + `GLOSSARY.md` + `docs/AI_BRAIN_SPIKE.md`.
- Monorepo: `apps/ packages/ infra/ docs/ tools/`.
- `packages/design-tokens` — one token source → Tailwind/CSS/Flutter (build verified).
- `packages/api-contracts/openapi.yaml` — P1 slice endpoints.
- **`apps/api`** — Laravel 12 modular monolith:
  - `ModuleServiceProvider` auto-wires each `modules/<X>/` (migrations, `/v1` routes, factories).
  - **Identity**: `Person` (ULID) replacing default User; Sanctum auth.
  - **Training**: append-only, idempotent `SetLog` vertical slice (offline-sync, ADR-005) — `/v1/sessions`, `/v1/sessions/{id}/sets`, `/v1/me/history`.
  - **Platform**: `PlatformUser` (admin guard) + `/v1/health` probe.
  - Observability: `RequestId` middleware (X-Request-Id + log Context), JSON log channel.
  - **Filament v5 super-admin** at `/admin` (admin guard, `PersonResource`, seeder `admin@fitnessos.test` / `password`).
- **CI** (`.github/workflows/ci.yml`): Pint + PHPUnit on MySQL 8.
- **Docker** (`docker-compose.yml`): MySQL 8 + Redis + Meilisearch + Mailpit + api/queue.
- **IaC** (`infra/terraform`): region-pinned AWS baseline (S3 + ECR); full stack deferred. Nothing applied.
- **Tests:** `12 tests / 27 assertions` green (3 Filament tests skip on host). Pint clean.

## 5. How to run / verify (copy-paste)
```bash
cd /home/mohamed/Desktop/work/fitness/apps/api
vendor/bin/phpunit                 # 12 tests, 3 skipped on host (Filament needs intl) — EXPECTED
vendor/bin/pint --test             # style gate (must pass)
php artisan migrate:fresh          # dev DB = fitness_os (MariaDB root/root)
php artisan route:list --path=v1   # see the slice + health routes
# Full app incl. Filament rendering (verified working):
docker compose up -d                 # api on :8000, mysql :3310, redis :6380, meili :7700, mailpit :8025
docker compose exec api php artisan db:seed --class="Database\Seeders\PlatformAdminSeeder"
#   → http://localhost:8000/v1/health  → {"status":"ok",...}
#   → http://localhost:8000/admin      → login admin@fitnessos.test / password
```

**Docker notes (debugged & fixed 2026-06-13):**
- Host ports are **env-overridable** (`MYSQL_PORT`, `REDIS_PORT`, `API_PORT`, ...) — defaults dodge the other projects' containers on this machine (which hold 3306/3307/3308). Override in a root `.env` if one clashes.
- The container reads **`apps/api/.env.docker`** (mounted over `/app/.env`), NOT the host `.env`. Reason: `php artisan serve` forwards only `.env`-file vars to its request child, so container `environment:` vars were invisible to served requests (DB/redis showed "down"). Edit `.env.docker` for container config; host `.env` stays for host-CLI dev.

## 6. What's NEXT (pick up here)
**Immediate Phase-0 leftovers:** social OAuth (Apple/Google) on `Person`.
**Phase 1 (B2C + AI MVP) — recommended first task:** **Onboarding + PAR-Q+ health-screen gate** (`FR-IDN` + `FR-AI-007`) — the safety prerequisite before any AI plan generation. Then: exercise library + training log polish → nutrition/food log → **AI Brain** (gen + safety gate + RAG + credit metering).
**AI Brain spike** (`docs/AI_BRAIN_SPIKE.md`) is the highest-risk item — **blocked on**:
- **Q5** — AI provider API key (default plan: Claude-primary + fallback gateway).
- **Q7** — clinical contraindication ruleset source (for the safety post-eval).

## 7. Open questions (from IMPLEMENTATION_PROGRESS §3)
Q1 confirm A3/A4/A5 defaults · Q2 pricing · Q3 MENA PSP (Paymob/HyperPay/Tap) · Q4 food-DB & exercise-media licensing (Arabic) · Q5 AI provider key · Q6 `stancl/tenancy` spike · Q7 contraindication ruleset.

## 8. Workflow reminders
- Work follows the docs; keep `GLOSSARY.md` the source of truth for entity names (prevents cross-doc drift).
- TDD for feature code (red → green); tenant-isolation/consent/safety tests are release-blocking (`docs/BLUEPRINT.md` §5, §10).
- Update `docs/IMPLEMENTATION_PROGRESS.md` as items complete.
- Commit per logical change, push to `main`, **no `Co-Authored-By` trailer**.
