# SESSION_HANDOFF.md — Resume Here

> **Purpose:** everything needed to continue Fitness OS in a fresh conversation without starting over.
> **Last updated:** 2026-06-22 (AI plan-adjustment proposals — FR-AI-006, on the shared generator base) · **Repo:** local `/home/mohamed/Desktop/work/fitness` → **remote** `github.com/mohamedelfert/fitness` (branch `main`).

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
vendor/bin/phpunit                 # 85 tests, 3 skipped on host (Filament needs intl) — EXPECTED
vendor/bin/pint --test             # style gate (must pass)
php artisan migrate:fresh --seed   # dev DB = fitness_os (MariaDB root/root); seed exercise library
php artisan route:list --path=v1   # see the slice + health routes
# Contract guard (NO CI covers the OpenAPI — run after editing it):
cd /home/mohamed/Desktop/work/fitness && python3 -c "import yaml,re; t=open('packages/api-contracts/openapi.yaml').read(); d=yaml.safe_load(t); s=set(d['components']['schemas']); r=set(re.findall(r'#/components/schemas/(\w+)',t)); print('refs OK' if not r-s else f'DANGLING: {r-s}')"
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
**Done since last handoff (on `main`):** **full deep `docs/PHASE_PLAN.md`** (all phases task-level; P4 planning-time) · **Onboarding profile capture + goals** (`FR-IDN`, `FR-ENG-001`) — new **Engagement** module (`goals`), `GET/PATCH /v1/me`, `POST /v1/onboarding`, `GET/POST /v1/goals`, `AiInputProfile` Brain contract · **Exercise library + search** (`FR-TRN-001/006`) — `GET /v1/exercises` (q/muscle/equipment, cursor, `Accept-Language`) + `GET /v1/exercises/{id}`, `exercises` enriched to spec, `Exercise::scopeSearch` (swap for Meili in prod), bilingual `ExerciseLibrarySeeder` · **Program read model** (`FR-TRN-005`) — `programs→workouts→workout_exercises`, `GET /v1/programs` + `GET /v1/programs/{id}` · **PR auto-detection** (`FR-TRN-004`) — `POST /v1/sessions/{id}/finish` dispatches queued `DetectPersonalRecords` → `personal_records` (max_load/est_1rm/max_reps), `GET /v1/me/records`. Suite now **53 tests / 175 assertions** (3 Filament skips on host).
**E1.3 status:** server-side core done (library, program model, PR read-model). Remaining: **timers** (`FR-TRN-003`, client-side → E1.10 Flutter) + session history filters.
**E1.4 nutrition (new):** food DB + food logging + daily summary + **water/supplement logging** done — Nutrition module: `food_items`/`food_logs`/`water_logs`/`supplement_logs`; `GET /v1/foods` (+barcode), `POST /v1/food-logs`, `POST /v1/water-logs`, `POST /v1/supplement-logs`, `GET /v1/me/nutrition/summary` (now incl. `water_ml`). `App\Casts\LocalizedJson` keeps Arabic substring-searchable across MariaDB/MySQL (reuse for any searchable localized JSON; **verified green on MySQL 8 in CI**). Plus **meal plan read model** (`meal_plans→meal_plan_days→meal_plan_items`, `GET /v1/meal-plans` + `/{id}`). Suite now **77 tests / 244 assertions** (3 Filament skips). Remaining E1.4: recipes, AI photo/voice.
**Immediate Phase-0 leftover:** social OAuth (Apple/Google) on `Person` (`FR-IDN-001`) — slots anytime.
**E1.6 AI Brain — program generation shipped (on `main`, TDD, 8 tests/21 assertions):** new **`AiOrchestration`** module proves the orchestration + safety *mechanism* against a fake `LlmGateway` seam (ADR-004) — no provider key needed, so it was unblocked by Q5/Q7. `POST /v1/ai/program`: `ai-plan.generate` Gate (403 if unscreened) → onboarding check (422) → `ProgramGenerator` (RAG context from `AiInputProfile` → generate → parse → resolve slugs → contraindication post-eval → reject+regenerate, `config('ai.program.max_attempts')`=2 → persist a `programs→workouts→workout_exercises` graph). **INV-005 holds**: nothing persists unless the safety post-eval passes (malformed/hallucinated/contraindicated → 422, never 500). Every call logged to **`ai_interactions`** (tokens/cost_micros/latency/safety_verdict; cost=0 until Q5 pricing). `LlmGateway` default binding (`UnconfiguredLlmGateway`) throws until **Q5** wires the real Claude adapter; `ContraindicationScanner` is a body-part heuristic (`left_knee`→`knee` `str_contains` `knee_injury`) until **Q7** clinical ruleset — both swap in behind the same seam.
**E1.6 — AICredit wallet + meter shipped (on `main`, TDD):** `ai_credit_wallets` + `ai_credit_ledger` (DATABASE_DESIGN §2.5) + **`AiCreditMeter`** — single-entry *signed* ledger (NOT the Plane-B double-entry money ledger; INV-003 does not apply), atomic `lockForUpdate` read-modify-write debit (never goes negative), **debit-once-on-success after persist** (the safety reject+regenerate loop and any 422 are free to the user — verified by test). `POST /v1/ai/program` now: gate (403) → onboarding (422) → **`ensureCanAfford` (402)** → generate → debit (ref = program). `GET /v1/me/ai-credits` auto-provisions an empty wallet. `InsufficientCreditsException` self-renders 402. Config `ai.credits` (`default`/`program`/`meal_plan`/`free_grant`). OpenAPI + contract guard green.
**E1.6 — AI meal-plan generation shipped (on `main`, TDD, 9 tests):** `POST /v1/ai/meal-plan` — `MealPlanGenerator` mirrors `ProgramGenerator`'s sandwich: RAG over the food library → parse → resolve `food_slug`→`FoodItem` → **`DietaryScanner` post-eval** → reject+regenerate → persist `meal_plans→days→items` (INV-005). Decided food-grounding = **library-referenced**: added `slug` (LLM handle) + `dietary_tags` (exclusion flags: dairy/gluten/egg/…) to `food_items` (migration + model + factory + seeder). `DietaryScanner` flags any prescribed food whose `dietary_tags` match the Person's `dietary_restrictions` (allergen/halal exclusions; vegan/vegetarian *preferences* are soft grounding hints, not hard blocks — revisit if one must be enforced). Metered exactly like programs (402 / debit-once / `feature='meal_plan'`). **Suite now 102 tests / 311 assertions (3 Filament skips on host).**
**E1.6 — AI exercise-alternatives shipped (on `main`, TDD, 10 tests):** `POST /v1/ai/exercise-alternatives` — the 3rd generator (`FR-AI-003`). Cheap model tier (`config('ai.exercise_alternatives.tier')`); given an `exercise_slug` (+ optional `count`) it returns safe swap suggestions that train a similar pattern under the athlete's equipment/injuries. Runs the full safety sandwich (reuses `ContraindicationScanner` — a contraindicated swap is an INV-005 hazard), but persists **nothing** (200, not 201) — proposals the member applies later. Validates the source exercise (422 unknown), gated/onboarding/402/debit exactly like the others (`feature='exercise_alternatives'`, debit-once, ref = source exercise). **Suite now 113 tests / 334 assertions (3 Filament skips on host).**
**One decision still open (PHASE_PLAN E1.6):** **AICredit funding trigger** — wallets start *empty*; nothing grants `free_grant` in production yet (tests fund explicitly via the meter; the 402 path is the natural unfunded case). Pre-billing stopgap: pick grant-on-onboarding-completion (cleanest via an event so Identity doesn't depend on AiOrchestration) vs defer to E1.9 plan grants.

**E1.6 — AI daily recommendation shipped (on `main`, TDD, 8 tests):** `GET /v1/ai/recommendations/today` (`FR-AI-004`, the API_SPECIFICATION-named path). An advisory daily nudge — **deliberately NOT an `AiGenerator` subclass**: it prescribes no library entities, so the resolve→contraindication-scan sandwich has nothing to act on; safety is **by construction** (the system prompt forbids specific exercise/medical prescriptions — plans come from the safety-gated generators). Parses leniently (`{message}` JSON *or* a bare line), 422 (never 500) on blank output. **Materialised once per person/day** (`daily_recommendations`, unique `person_id+rec_date`): the day's first call generates + debits one credit; same-day refreshes are **cache-served, free, no second model call** (NFR-AI-001 cost control — server-side, can't trust a client). Gate (403) + onboarding (422) + 402-on-miss like the others. Logging mirrors `AiGenerator` (`ponytail:` 2nd-copy note; extract an `AiInteractionLogger` only on a 3rd non-base caller). OpenAPI + contract guard green. **Suite now 133 tests / 380 assertions (3 Filament skips on host).**

**E1.6 — AI plan-adjustment proposals shipped (on `main`, TDD, 12 tests):** `POST /v1/ai/plan-adjustment` — the 4th generator (`FR-AI-006`), the twin of exercise-alternatives on the `AiGenerator` base. Given a `program_id` (+ optional `goal`) it reviews the program and returns safe proposed changes (swap/add/remove/adjust, each referencing an `exercise_slug` + rationale). Runs the full safety sandwich over the **prescribed** exercises only (reuses `ContraindicationScanner`; `replaces_slug` — what's removed — is never the hazard), persists **nothing** (200). The program is **person-owned → unknown/cross-person id is 404** (not 422 like the shared exercise library; copied `ProgramController::show`'s scoped `findOrFail`). **Empty adjustments = a valid "no changes recommended" → 200 empty list, debited** (a successful review): the base's empty-slugs path returns an empty collection, not a failed attempt (the one deliberate deviation from the alternatives mirror; pinned by a test + `ponytail:` comment in `resolve()`). `strong` tier (reasons over whole-program progression). Metered exactly like the others (`feature='plan_adjustment'`, 402 / debit-once / ref = program). OpenAPI + contract guard green. **Suite now 125 tests / 360 assertions (3 Filament skips on host).**

**E1.6 — generator base extracted (on `main`, pure behavior-preserving refactor):** the three generators now extend **`AiGenerator`** (Services) — the safety sandwich (build→generate→parse→resolve→scan→reject/regenerate→finalize→exhaust) + `ai_interactions` logging/cost live there ONCE (the INV-005 loop is now single-sourced, not three copies). **`SafetyScanner`** interface (`unsafeSlugs(Person, iterable): array`) is implemented by both `ContraindicationScanner` + `DietaryScanner`; the base holds one. `finalize()` is abstract returning `mixed` so program/meal-plan persist a graph (wrapping their own transaction) while exercise-alternatives returns un-persisted proposals; per-call extra inputs (e.g. the source exercise) flow through a `$context` array param, not instance state. Net −175 lines in the generators.

**Phase 1 — recommended next (per PHASE_PLAN intra-phase order):** remaining **E1.6** generators — **daily-rec `FR-AI-004`** (advisory, NOT a natural fit for the `AiGenerator` base — it prescribes no library entities, so the resolve→scan safety hooks are no-ops; consider a thinner path) and **conversational coach `FR-AI-008`** (different shape: chat state / message history / streaming) — plus RAG A/B; streaming) + the **AICredit funding-trigger** wiring (open decision above). Then **E1.5 biometrics/progress photos/wearables** (unblocked). When Q5 lands: bind a real `LlmGateway` adapter in `AiOrchestrationServiceProvider`, add `config/ai.php` pricing, run the spike (`docs/AI_BRAIN_SPIKE.md`).
**AI Brain spike** (`docs/AI_BRAIN_SPIKE.md`) is the highest-risk item — **blocked on**:
- **Q5** — AI provider API key (default plan: Claude-primary + fallback gateway).
- **Q7** — clinical contraindication ruleset source (for the safety post-eval).

## 7. Open questions (from IMPLEMENTATION_PROGRESS §3)
Q1 confirm A3/A4/A5 defaults · Q2 pricing · Q3 MENA PSP (Paymob/HyperPay/Tap) · Q4 food-DB & exercise-media licensing (Arabic) · Q5 AI provider key · Q6 `stancl/tenancy` spike · Q7 contraindication ruleset.
**Q8 (new) — localized exercise-name search.** `exercises.name` is canonical per DB design (only instructions are localized), so Arabic *name* search currently returns nothing (search doesn't error — it's robust). MENA-first may want Arabic-name matching → deliberate decision: add `name_i18n` (+ extend search) vs rely on localized instructions. Raise before the member app's library UX lands. Tie-in with Q4 (Arabic media/licensing).

## 8. Workflow reminders
- Work follows the docs; keep `GLOSSARY.md` the source of truth for entity names (prevents cross-doc drift).
- TDD for feature code (red → green); tenant-isolation/consent/safety tests are release-blocking (`docs/BLUEPRINT.md` §5, §10).
- Update `docs/IMPLEMENTATION_PROGRESS.md` as items complete.
- Commit per logical change, push to `main`, **no `Co-Authored-By` trailer**.
