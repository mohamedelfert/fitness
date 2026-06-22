# PHASE_PLAN.md — Deep Work-Breakdown for All Phases

> **Status:** Living · **Last updated:** 2026-06-13 · **Companion to** `EXECUTION_PLAN.md` (milestones/gates) and `IMPLEMENTATION_PROGRESS.md` (live status).
> **What this is:** every phase decomposed into **Epics → Features → Tasks**, mapped to `FR`/`NFR`/`INV` IDs from `PRODUCT_REQUIREMENTS.md` and `DATABASE_DESIGN.md`. Tasks name the layers they touch (DB / API / service / UI / test) using the **canonical table & endpoint names** from `DATABASE_DESIGN.md`, `API_SPECIFICATION.md`, and `GLOSSARY.md` (source of truth — no invented names).
> **Planning depth:** by request, **all phases are decomposed to task level now**. P0–P3 are grounded in concrete design-doc artifacts (tables exist in the DB doc, endpoints in the API doc). **P4 is decomposed only to the depth its design docs support and flagged `[planning-time]`** — it is genuinely 12–18 months out, the design docs are thin there, and task-level precision would be fiction. The rolling-wave rule still governs *execution*: **re-decompose the next phase at each phase gate** and reconcile statuses, because precision far out is waste. This document gives the full board now; the gate ritual keeps it honest.
> **Legend:** ✅ done · 🟡 in progress · ⬜ todo · 🔒 blocked. **Every task carries the Definition of Done** in `BLUEPRINT.md §10` (tests incl. isolation/consent/a11y, contract match, authz, observability, docs). **TDD is mandatory for feature code** (red → green). **Release-blocking tests** (🚧) gate the relevant phase: tenant isolation `INV-001`, consent `INV-004`, ledger balance `INV-003`, AI safety `INV-005`, append-only `INV-002`.

---

## Epic map (the whole board at a glance)

| Phase | Epics | Theme | Gate |
|---|---|---|---|
| **P0 Foundation** | E0.1–E0.8 | Walking skeleton, infra, safety rails | Skeleton deployed; CI green; one slice round-trips ✅ |
| **P1 B2C + AI MVP** | E1.1–E1.12 | The testable bet: AI coach retains the beginner | D30 retention, North-Star, AI acceptance ≥60%, margin, free→paid ≥3–5% |
| **P2 Coach + marketplace** | E2.1–E2.11 | Light loop 2; first strong ARR | Coach NRR >100%; marketplace liquidity; ledger reconciles |
| **P3 Gym OS** | E3.1–E3.9 | Light loop 3; enterprise prize | Multi-branch reference live; member DAU in-gym; PT revenue captured |
| **P4 Ecosystem & scale** | E4.1–E4.8 | Marketplaces, clinical, data, scale | Per-initiative business cases |

Cross-cutting workstreams run **every** phase (see `EXECUTION_PLAN.md §9` and the dedicated section at the end of this doc): AI cost/quality · security & isolation/consent · i18n/RTL & a11y · observability/SRE · the outcome-labeling graph pipeline.

---

## PHASE 0 — Foundation ✅ (substantially complete)

**Goal:** prove the pipes & safety rails before features. **Gate:** met.

- **E0.1 Monorepo & design system** ✅ — monorepo, `design-tokens` (→ Tailwind/CSS/Flutter), `api-contracts` (OpenAPI).
- **E0.2 Modular Laravel + Core** ✅ — modular monolith, `ModuleServiceProvider` (auto-wires migrations/routes/factories/providers), ULID. *Tenancy/Consent/Audit/Ledger primitives dormant until P2.*
- **E0.3 Identity & auth** 🟡 — `Person` (ULID) + Sanctum ✅; **social OAuth (Apple/Google) ⬜** (`FR-IDN-001`).
- **E0.4 Offline-sync slice (Training)** ✅ — append-only idempotent `SetLog`, `/v1/sessions`, `/sets`, `/me/history` (`FR-TRN-002`, ADR-005).
- **E0.5 Docker + CI** ✅ — compose (MySQL 8 / Redis / Meilisearch / Mailpit); GitHub Actions (Pint + PHPUnit on MySQL 8).
- **E0.6 Filament super-admin shell** ✅ — panel at `/admin`, `admin` guard (`PlatformUser`), `PersonResource`.
- **E0.7 Observability + IaC baseline** ✅ — request-id, `/v1/health`, JSON logs; region-pinned AWS Terraform skeleton.
- **E0.8 AI Brain spike** 🔒 — `docs/AI_BRAIN_SPIKE.md`; **blocked on Q5 (provider key) + Q7 (contraindication ruleset)**.

---

## PHASE 1 — B2C + AI MVP  (task-level depth)

**Thesis:** can an AI coach retain a motivated beginner (Maya) better than the fragmented status quo, while filling the graph? **Gate:** PRD §5 metrics.

### E1.1 — Onboarding & health screen  🟡
- **PAR-Q+ health screen + AI safety gate** ✅ (`FR-AI-007`) — `health_screens` (append-only), `Parq` scoring, `ai-plan.generate` gate.
- **Onboarding profile capture** ✅ (`FR-IDN`, `FR-ENG-001`) — TDD, 14 tests/51 assertions:
  - DB: `goals` table (new **Engagement** module); training profile (experience_level, equipment, training_days_per_week, dietary prefs/restrictions, injuries) in `persons.onboarding_state.profile`; demographic basics on `persons`.
  - API: `GET/PATCH /v1/me`, `POST /v1/onboarding` (multi-step submit → marks complete), `GET/POST /v1/goals` — all under `auth:sanctum`; vocab-validated.
  - Service: `Identity\Support\AiInputProfile::for(Person)` assembles the **Brain contract** (goals, level, equipment, schedule, diet, injuries, demographics, PAR-Q status, `ready_for_ai`); `OnboardingProfile` holds the shared vocab + validation.
  - Tests: profile persists; onboarding marks complete; goals scoped to Person; **injuries + screen status carried for contraindication gating**; `ready_for_ai` false unless screen `passed` AND onboarding complete.
- **Onboarding completion → first plan handoff** ⬜ — `ai_input_profile.ready_for_ai` now signals readiness; wiring J1 to end at a generated plan depends on E1.6.

### E1.2 — Identity completeness  ⬜
- Social OAuth (Apple/Google) `FR-IDN-001` · GDPR export (`/v1/me/export`) + account deletion (`FR-IDN-004`) · profile/account management · push-token registration (`FR-APP-006`).

### E1.3 — Training  🟡
- **Exercise library + search** ✅ (`FR-TRN-001/006`) — TDD, 10 tests: `GET /v1/exercises` (q + muscle/equipment filters, cursor, `Accept-Language` localized) + `GET /v1/exercises/{id}`; `exercises` enriched to the DB-design spec (`secondary_muscles`, `mechanics`, `media_keys`); `Exercise::scopeSearch` DB-backed (one method to swap for **Meilisearch** in prod); bilingual dev seeder with contraindications (full licensed dataset = Q4). *Names are canonical (not localized) — Arabic-name search is a deliberate open follow-up, not invented here.*
- **Program model** ✅ (`FR-TRN-005`) — TDD, 4 tests: `programs → workouts → workout_exercises` tables + models + factories; `GET /v1/programs`, `GET /v1/programs/{id}` (nested, person-scoped, cross-person→404). Read surface for the Today loop; **AI generation (E1.6) and the coach/advanced interactive builder (P2) populate it.**
- **PR auto-detection** read-model ✅ (`FR-TRN-004`) — TDD, 6 tests: `POST /v1/sessions/{id}/finish` dispatches the queued `DetectPersonalRecords` job → derives `personal_records` (max_load, Epley est_1rm, max_reps) from `set_logs`; `GET /v1/me/records`. Async (off the logging hot path, `NFR-SCAL-001`), idempotent recompute, person-scoped (finish 404s cross-person). Feeds progress analytics + `outcomes` (graph pipeline).
- Logging polish remaining: rest/interval/EMOM/AMRAP **timers** (`FR-TRN-003`, largely client-side → E1.10 Flutter); session history filters. ⬜
- (SetLog append/idempotent ✅ from P0.)

### E1.4 — Nutrition  🟡
- **Food DB + logging + daily summary** ✅ (`FR-NUT-001/002/003`) — TDD, 13 tests: new **Nutrition** module; `food_items` (localized `name_i18n` via `App\Casts\LocalizedJson` so Arabic stays substring-searchable on MariaDB+MySQL, barcode-indexed) + append-only idempotent `food_logs`. `GET /v1/foods?q=` (localized search), `GET /v1/foods/barcode/{code}`, `POST /v1/food-logs` (snapshots servings×per-serving macros, or custom entry), `GET /v1/me/nutrition/summary?date=`; bilingual `FoodLibrarySeeder`.
- **Water + supplement logging** ✅ (`FR-NUT-006/007`) — TDD, 6 tests: append-only idempotent `water_logs`/`supplement_logs`; `POST /v1/water-logs`, `POST /v1/supplement-logs`; water rolled into the daily summary. Recipes/custom foods (`FR-NUT-009`) ⬜.
- **Meal plan read model** ✅ (`FR-AI-002`) — TDD, 4 tests: `meal_plans → meal_plan_days → meal_plan_items` tables/models/factories; `GET /v1/meal-plans`, `GET /v1/meal-plans/{id}` (nested days→items, person-scoped, cross-person→404). Nutrition analog of programs; **AI generation (E1.6) populates it.** Recipes/custom foods (`FR-NUT-009`) ⬜.
- AI food-image recognition (`FR-NUT-004`) + voice logging (`FR-NUT-005`) — buy/partner vision initially. ⬜

### E1.5 — Body, progress & wearables  ⬜
- Biometrics (weight/bodyfat/measurements) `FR-BIO-001`; progress photos (encrypted, signed URLs) `FR-BIO-002`.
- Wearable ingest: Apple Health / Health Connect (steps/HR/sleep/HRV) `FR-BIO-003`; recovery-aware tips `FR-AI-005`.

### E1.6 — AI Brain core  🔒 (gated by E1.1 safety gate; needs Q5/Q7)
- `LlmGateway` interface + Claude-primary + fallback (ADR-004); model tiering & caching (`NFR-AI-001`).
- RAG over the Person's graph (grounding, cite sources `NFR-AI-003`).
- Generation: Program ✅ (`FR-AI-001`), MealPlan ✅ (`FR-AI-002`), exercise alternatives ✅ (`FR-AI-003`, cheap-tier, `POST /v1/ai/exercise-alternatives` — safe swaps, no persist), plan-adjustment proposals ✅ (`FR-AI-006`, `POST /v1/ai/plan-adjustment` — reviews a program, returns safe contraindication-checked changes, no persist, 200; empty = "no changes recommended"), daily recommendation (`FR-AI-004`) ⬜, conversational coach (`FR-AI-008`) ⬜.
- **Safety post-eval gate** ✅ (reject+regenerate) `NFR-AI-002`/`INV-005`; every endpoint `Gate::authorize('ai-plan.generate')`. Shipped for Program (contraindication scan) + MealPlan (`DietaryScanner` — exclusion/allergen match vs `dietary_restrictions`) gen, vs the fake gateway. Heuristics until Q7 (clinical) / Q4 (food ontology).
- AICredit metering + enforcement + wallet/ledger ✅ (`FR-SAS-004`) — `ai_credit_wallets`/`ai_credit_ledger` + `AiCreditMeter` (atomic `lockForUpdate` debit, single-entry signed ledger, debit-once-on-success), `POST /v1/ai/program` → 402 when broke, `GET /v1/me/ai-credits`; wallets funded by config `ai.credits.free_grant` until E1.9 plan grants. `ai_interactions` logging ✅; cost dashboard (`NFR-OPS-002`) ⬜.
- Async via queues; graceful degradation if Brain down (`NFR-REL-004`). ⬜
- **Decided — MealPlan generation food-grounding** (`FR-AI-002`): chose (a) **library-referenced** — added `slug` + `dietary_tags` to `food_items` so the dietary safety scan is real (mirrors exercise contraindications) and grounding cuts hallucination. (b) free-form-macros was rejected: weaker safety post-eval, which INV-005 makes release-blocking. Open follow-on: dietary *preferences* (vegan/vegetarian) are currently soft grounding hints, not hard blocks — revisit if a preference needs enforcing.
- **Deferred decision — AICredit funding trigger** (`FR-SAS-004`): wallets currently start empty; production needs an explicit grant. Options: grant `free_grant` on onboarding completion (couples Identity→AiOrchestration, cleanest via an event) vs wait for E1.9 plan grants. Picked B-style stopgap for now (config grant, tests fund explicitly).

### E1.7 — AI analytics  ⬜
- Progress analysis + goal projection (`FR-AN-001`); adherence analytics (`FR-AN-002`); weekly report (`FR-AN-005`). Computed as async read-models.

### E1.8 — Engagement  ⬜
- Goals tracking (`FR-ENG-001`); habits + behavioral nudges (`FR-ENG-002`); XP/levels/badges/streaks (`FR-ENG-003`); smart notifications, per-user learned timing (`FR-ENG-006`).

### E1.9 — SaaS billing (B2C)  ⬜
- Plans (free/premium) + AICredits (`FR-SAS-002`); subscriptions, trials, coupons, upgrade/downgrade+proration (`FR-SAS-003`); payments via **regional PSP** (Q3) + credit top-up.

### E1.10 — Member app (Flutter)  ⬜
- Clean-arch app; offline-first store + sync engine (`FR-APP-007`, ADR-005); Today (hero), Log Workout, Log Food, Progress, AI Coach chat, Plans, Profile, Wearables; push (`FR-APP-006`); consume `design-tokens` theme + RTL.

### E1.11 — Member web (TALL)  ⬜
- Responsive companion (`FR-APP-003a`): Today, log workout/food, progress & charts, AI coach, plans, account/billing — same API + tokens, `web` guard (Person).

### E1.12 — i18n/RTL & a11y hardening  ⬜
- AR+EN end-to-end, RTL, units/currency (`NFR-UX-003`); WCAG 2.2 AA, axe/Flutter-semantics in CI (`NFR-UX-001`).

**Intra-phase order:** E1.1 → E1.3/E1.4 (data to log) → E1.6 (Brain, when key lands) → E1.5/E1.7/E1.8 → E1.9 → E1.10/E1.11 → E1.12. **E1.2 social OAuth** can slot anytime.

---

## PHASE 2 — Coach platform + marketplace  (task-level depth)

**Thesis:** does the AI-leveraged coach (Omar) scale past the ~30-client admin ceiling, and does B2C→Coach conversion seed marketplace liquidity? **Prereq:** activate the dormant **multi-tenancy + Consent layer** (ARCH §4.5). **Gate:** coach NRR >100% · marketplace match→trial→paid funnel proven · `ledger_entries` reconcile 100% vs PSP · consent enforcement verified.

> P2 turns on **Plane B** (tenant-scoped DB) for the first time. From here on **`INV-001` (tenant isolation)** and **`INV-004` (consent)** tests are 🚧 release-blocking on every endpoint that touches tenant or Graph data. All `/v1/coach/*` and `/v1/marketplace/*` paths resolve tenant context from the token only (never the body — ARCH §4.4), and Graph reads of a Client go through the **Layer-3 consent gate**.

### E2.1 — Tenancy & consent activation  ⬜ *(must land first — everything else depends on it)*
- **Feature: turn on tenant resolution & scoping** (`FR-SAS-001`, `NFR-SEC-002`)
  - DB: activate `tenants` [A] registry (`type`, `slug`, `custom_domain`, `region`, `db_mode`, `db_ref`); `memberships` [B] (`type=client|gym_member|staff`); add the mandatory `tenant_id` global scope to every Plane-B model.
  - Service: `TenantContext` resolver from Sanctum token + optional `X-Tenant` validated against the actor's `memberships`; hybrid pooled/dedicated DB connection switch (ADR-003) keyed on `tenants.db_mode`/`db_ref`.
  - API: middleware that binds tenant context before routing; cross-tenant access returns **404** (existence hidden, not 403) per `API_SPECIFICATION.md §13`.
  - Test 🚧: `INV-001` — no query returns rows across tenants; pooled-vs-dedicated parity; `X-Tenant` spoof rejected.
- **Feature: consent scopes** (`FR-IDN-003`, `INV-004`)
  - DB: `consent_scopes` [B-bridge] (`person_id, tenant_id, data_class[training|nutrition|biometrics|health|messaging], granted_at, revoked_at`).
  - API: `GET/POST/DELETE /v1/me/consents` (Person grants/revokes); consent-failure returns **403 `CONSENT_REQUIRED`** + missing `data_class`.
  - Service: query-time consent gate (Layer 3) wrapping all tenant-actor reads of Plane-A Graph data; revocation blocks access immediately.
  - Test 🚧: `INV-004` — tenant reads Graph **iff** active scope; revoke mid-session blocks next read; per-`data_class` granularity holds.

### E2.2 — Coaching core  ⬜
- **Feature: clients & profiles** (`FR-CCH-001`)
  - DB: `coach_clients` [B] roster (denormalized from `memberships` type=client; stage, ChurnRisk, EngagementScore columns).
  - API: `GET/POST /v1/coach/clients` (roster; invite a Person → creates `membership` + pending consent); `GET /v1/coach/clients/{id}` (profile — Graph fields consent-gated).
  - Test: invite flow creates membership; client Graph fields hidden until consent granted (ties to `INV-004`).
- **Feature: templates** (`FR-CCH-004`)
  - DB: `templates` [B] (`kind=program|meal_plan`, `structure_json`, `visibility`).
  - API: `GET/POST /v1/coach/templates`.
  - Service: clone a `template` → a Person's `programs`/`meal_plans` (reuses P1 Graph models; `source=coach`, `coach_id` set).
- **Feature: assignments** (`FR-CCH-004`)
  - DB: `assignments` [B] (`coach_id, client_person_id, template_id, program_id, schedule_json, status`).
  - API: `POST /v1/coach/assignments` (assign/clone template→client).
  - Test: assignment materializes a client-visible `program`/`meal_plan`; schedule respected; consent required to write into client Graph.
- **Feature: adherence dashboard** (`FR-CCH-005`)
  - Service: surface the per-Client `adherence_events` rollup (built in P1) into the coach roster; no new hot-path compute.

### E2.3 — Check-ins & messaging  ⬜
- **Feature: custom check-in forms** (`FR-CCH-002`)
  - DB: `checkin_forms` [B] (schema/fields), `checkin_submissions` [B] (responses + media + metrics snapshot).
  - API: `GET/POST /v1/coach/checkin-forms`; `GET/POST /v1/coach/checkins`; scheduling of recurring check-ins.
  - Test: submission stores metrics snapshot; consent gates any Graph metrics pulled in.
- **Feature: real-time messaging** (`FR-CCH-003`, `FR-APP-008`)
  - DB: `conversations` [B], `messages` [B] (media attachments).
  - API/WS: `private-conversation.{id}` channel (Reverb), authorized by membership **+** consent (three-layer channel auth, `API_SPECIFICATION.md §11`).
  - Test 🚧: channel auth rejects non-member / non-consented; offline-send replays idempotently.

### E2.4 — AI coach leverage  ⬜
- **Feature: AI-drafted check-in summaries & program tweaks, human-approved** (`FR-AI-009`, A5)
  - API: `POST /v1/coach/checkins/{id}/ai-draft` → draft; coach edits → `POST …/send` (the human-approval step).
  - Service: reuse the P1 `LlmGateway` + safety gate; record `ai_interactions` with `confidence`/`safety_verdict`; **nothing sends to a client without explicit coach approval** (`NFR-AI-004`).
  - Test 🚧: no client-bound AI output persists/sends without approval; draft passes safety gate (`INV-005`); credits metered.

### E2.5 — ChurnRisk + playbooks (clients)  ⬜
- **Feature: client churn scoring** (`FR-AN-004`)
  - DB: `churn_risk` [B] read-model (per-membership; refreshed async — never inline).
  - API: `GET /v1/coach/clients/{id}/churn-risk`.
- **Feature: win-back playbooks** (`FR-AN-006`)
  - Service: alerts/playbooks triggered by ChurnRisk/adherence drops; coach-approvable automated win-back (A5 human-in-loop).
  - WS: churn alerts on `private-coach.{tenantId}`.
  - Test: scoring is async/off-hot-path; playbook actions require approval where they reach the client.

### E2.6 — Payments & double-entry Ledger  ⭐⬜ *(highest-integrity epic in P2)*
- **Feature: payment methods & charges** (`FR-FIN-001`, `NFR-SEC-004`)
  - DB: `payment_methods` [B] (PSP tokens only — no PAN), `payments` [B] (`payer_person_id, amount_micros, currency, psp, psp_ref, status`).
  - API: `CRUD /v1/coach/billing/*` (packages, payments, payouts); Stripe Connect + regional PSP (Q3).
- **Feature: invoices, receipts, refunds, promos** (`FR-FIN-001/002`)
  - DB: `invoices` [B] / `invoice_lines` [B], `receipts` [B], `refunds` [B], `discounts` [B], `promotions` [B].
- **Feature: double-entry ledger + reconciliation** (`FR-FIN-003`, `INV-003`, ADR-006)
  - DB: `ledger_entries` [B] (`account, debit_micros, credit_micros, currency, ref_type, ref_id, posted_at`); `psp_events` [B] for webhook reconciliation; `payouts` [B], `commissions` [B], `platform_fees` [B] (marketplace take-rate).
  - API: `POST /v1/webhooks/psp/{provider}` (signed, idempotent → ledger reconciliation).
  - Service: every money movement posts balanced entries; webhook reconciler matches `payments`↔`psp_events`; money is integer minor units + currency (`INV-006`).
  - Test 🚧: `INV-003` — entries for any transaction sum to zero; webhook replay idempotent; refund reverses correctly; reconciliation flags drift.
- **Feature: revenue analytics** (`FR-CCH-010`, `FR-FIN-004`)
  - API: `GET /v1/coach/analytics/revenue` (served from read-models, not hot aggregation).

### E2.7 — Coach branding & CRM  ⬜
- **Feature: white-label** (`FR-CCH-006`)
  - DB: `coach_profiles` [B] (logo, colors, custom domain, bio, specialties, languages, pricing).
  - API: `CRUD /v1/coach/branding`; tenant resolution also honors custom domain (ARCH §4.4).
  - UI: themed client experience via `design-tokens` overrides.
- **Feature: mini-CRM / funnels** (`FR-CCH-008`)
  - DB: `leads` [B] (funnel B2C→Client), `trials` [B].
  - API: `GET/POST /v1/coach/leads` · `/v1/coach/funnels`; lead-capture/landing pages.

### E2.8 — Marketplace  ⬜
- **Feature: coach discovery & matching** (`FR-MKT-001`)
  - DB: `coach_listings` [A/B] discovery index (specialty, price, language, rating) — surfaced centrally for cross-tenant matching.
  - API: `GET /v1/marketplace/coaches?goal=&lang=&budget=`.
- **Feature: trial→paid + take-rate** (`FR-MKT-002`)
  - API: `POST /v1/marketplace/coaches/{id}/trial`.
  - Service: trial→Client conversion **carries the Person's Graph over with consent** (J4); platform take-rate posts `platform_fees` to the ledger.
  - Test: conversion creates membership + consent prompt; take-rate ledgered (`INV-003`).

### E2.9 — Trainer app (Flutter) + Coach web (TALL)  ⬜
- **Feature: trainer Flutter app** (`FR-APP-002`) — clients, programs, check-ins, chat, analytics, schedule; reuses P1 offline-sync engine + tokens + RTL.
- **Feature: coach web dashboard** (`FR-APP-003`) — roster, templates, assignments, check-ins, churn, revenue; `web` guard + tenant context.

### E2.10 — Wearables & community  ⬜
- Additional wearable integrations: Garmin/Whoop/Fitbit/Withings (`FR-BIO-004`) — ingest into the P1 `wearable_streams` model.
- Opt-in community feed / social; integrate Strava for endurance (`FR-ENG-005`).

### E2.11 — Cohort/group coaching  ⬜
- Group/cohort coaching (`FR-CCH-009`) — many-clients-per-assignment; cohort challenges reuse P1 `challenges`/`challenge_participants`. *(Marked P3 in PRD; pulled adjacent to coaching core — re-confirm at the P2 gate.)*

**Intra-phase order:** E2.1 (tenancy+consent, blocking) → E2.2/E2.3 (coach core + check-ins/chat) → E2.4 (AI leverage) → E2.6 (payments/ledger) → E2.5/E2.7 → E2.8 (marketplace) → E2.9 (apps) → E2.10/E2.11. Maps to `EXECUTION_PLAN §5` milestones M6–M9.

---

## PHASE 3 — Gym OS  (task-level depth)

**Thesis:** does the consumer app inside the gym (J7) cut member churn and capture leaking PT revenue for Tarek, across branches? **Prereq:** P2 tenancy/consent live; gym is a `tenants.type=gym` with many `branches`. **Gate:** ≥1 multi-branch reference customer live · member-app DAU inside the gym proven · on-platform PT revenue captured.

> P3 introduces **branch-scoped** data and **high-write** tables (`access_events`). `<3s` check-in (`NFR-PERF-004`) and occupancy correctness are the hard NFRs. The differentiator is **E3.7 — reusing the P1 consumer app inside the gym**; everything else is table-stakes ops software the incumbents already have.

### E3.1 — Membership & multi-branch  ⬜
- **Feature: branches & multi-branch org** (`FR-GYM-020/021`)
  - DB: `branches` [B] (`name, address, geo, capacity, timezone`); branch-manager scoping via `memberships`/roles; `member_transfers` [B] between branches.
  - API: `CRUD /v1/gym/branches`; `GET /v1/gym/reports?scope=branch|org` (centralized cross-branch reporting, served from read-models).
- **Feature: membership plans & lifecycle** (`FR-GYM-001/002/003`)
  - DB: `membership_plans` [B] (`branch_scope, price_micros, duration, access_rules_json, class_entitlements_json`); `gym_subscriptions` [B] (`member_person_id, membership_plan_id, status[active|frozen|expired|cancelled], freeze_history_json`); `family_memberships` [B].
  - API: `CRUD /v1/gym/members` (registration + **bulk import**); `CRUD /v1/gym/membership-plans`; `POST /v1/gym/subscriptions` · `…/{id}/freeze` · `/upgrade` · `/transfer`.
  - Test: lifecycle transitions valid (freeze/upgrade/transfer/expire); family links; tenant isolation `INV-001`.

### E3.2 — Access control  ⬜
- **Feature: credentials** (`FR-GYM-004`)
  - DB: `access_cards` [B] (`person_id, type[qr|barcode|nfc], token, active`).
- **Feature: check-in & occupancy** (`FR-GYM-005`, `NFR-PERF-004`)
  - DB: `access_events` [B] — **high-write, append-only**, partitioned by `occurred_at`; composite index `(tenant_id, branch_id, occurred_at)`.
  - API: `POST /v1/gym/access/check-in` (QR/NFC/barcode/gate; **`<3s` end-to-end**); `GET /v1/gym/branches/{id}/occupancy` (live); `GET /v1/gym/attendance`.
  - Service: occupancy as an incrementally-maintained read-model (never `COUNT(*)` on hot path); smart-gate hardware integration adapter.
  - WS: `private-tenant.{tenantId}.branch.{branchId}` — live occupancy/check-ins to staff.
  - Test 🚧: check-in p95 `<3s` under load; `access_events` never updated/deleted (`INV-002`); occupancy never negative; isolation `INV-001`.

### E3.3 — Classes & scheduling  ⬜
- **Feature: schedule** (`FR-GYM-010/012`)
  - DB: `class_definitions` [B]; `class_sessions` [B] (`kind[group|pt|resource]` — **one bookable model** covers group classes, PT sessions, and court/resource reservations); `resources` [B] (courts/rooms/equipment).
  - API: `CRUD /v1/gym/classes` · `/v1/gym/class-sessions`; `CRUD /v1/gym/resources`.
- **Feature: bookings, waitlists, fees** (`FR-GYM-011`)
  - DB: `bookings` [B] (`class_session_id, person_id, status[booked|waitlist|attended|no_show|late_cancel], fee_micros`).
  - API: `POST /v1/gym/bookings` · `…/{id}/waitlist` · `/cancel`.
  - Service: capacity enforcement, waitlist promotion, no-show/late-cancel fee → posts to ledger (`INV-003`).
  - Test: no overbooking (concurrency/`409`); waitlist promotes on cancel; fee ledgered.

### E3.4 — Staff & payroll  ⬜
- **Feature: staff management** (`FR-GYM-013`)
  - DB: `staff` [B], `staff_schedules` [B], `staff_attendance` [B].
  - API: `CRUD /v1/gym/staff` · `/schedules` · `/attendance`.
- **Feature: commissions & payroll** (`FR-GYM-014`)
  - DB: `commissions` [B], `payroll_runs` [B].
  - API: `GET /v1/gym/staff/{id}/commissions` · `/v1/gym/payroll`.
  - Service: commission accrual posts ledger entries; payroll runs reconcile (`INV-003`); performance analytics as read-models.

### E3.5 — Staff app + POS-lite  ⬜
- **Feature: staff Flutter app** (`FR-APP-005`) — fast check-in scanner (the `<3s` path), member lookup, sell/freeze membership.
- **Feature: POS-lite** (`FR-FIN-005`)
  - API: `POST /v1/gym/pos/sale`; `CRUD /v1/gym/finance/*` (invoices, refunds, discounts, ledger views).
  - Test 🚧: every sale ledgered and balanced (`INV-003`); offline-tolerant idempotent sale.

### E3.6 — Gym ops  ⬜
- **Feature: broadcasts** (`FR-GYM-022`) — DB `broadcasts` [B]; `POST /v1/gym/broadcasts` (segmented push/email/SMS to member segments).
- **Feature: sales CRM** (`FR-GYM-023`) — DB `sales_pipeline` [B]; `CRUD /v1/gym/sales-pipeline` (trial pipeline).
- **Feature: digital waivers** (`FR-GYM-006`) — DB `waivers` [B] (`doc_key, signed_at, ip`); `POST /v1/gym/waivers/{id}/sign` (e-signature, document storage).

### E3.7 — In-gym engagement loop  ⭐⬜ *(the differentiator — not table stakes)*
- **Feature: reuse the consumer app inside the gym** (J7) — the same P1 Flutter app, now aware of the member's gym membership; check-in → book class → assigned workout → log → engagement loop. No new consumer app; gym context layered onto the existing Person experience.
- **Feature: gym-level churn & win-back** — `churn_risk`/`engagement_scores` read-models scoped to gym membership; win-back tied to `access_events` gaps; challenges/leaderboards tied to check-ins (reuses P1 `challenges`).
- Test: member with simultaneous B2C + gym membership sees one unified Person experience (`FR-IDN-005`); gym sees engagement signal without breaching consent.

### E3.8 — Gym owner/manager web  ⬜
- **Feature: dashboards** (`FR-APP-003`) — cross-branch owner dashboard + per-branch manager view: members, calendar, staff, finance, churn/engagement; role-scoped (owner vs branch manager vs front-desk); served from read-models.

### E3.9 — Member migration tooling  ⬜
- **Feature: bulk import + app invites** — bulk-import existing members (instant install base, zero-CAC) + app-invite flow; creates `persons` + `memberships` + `gym_subscriptions`; dedupe against existing Persons (`FR-IDN-005` — same Person across contexts).

**Intra-phase order:** E3.1 (membership/branches) → E3.2 (access/occupancy) → E3.3/E3.4 (classes/staff) → E3.5 (staff app/POS) → E3.6/E3.8 (ops/dashboards) → E3.9 (migration) → **E3.7 (engagement loop) is the payoff** and runs alongside once members exist. Maps to `EXECUTION_PLAN §6` milestones M10–M14.

---

## PHASE 4 — Ecosystem & scale  `[planning-time — re-decompose at the P3 gate]`

> **Honesty note:** P4 is 12–18 months out and **the design docs are intentionally thin here** (`PRODUCT_REQUIREMENTS §6`, `EXECUTION_PLAN §7` are paragraph-level; there is no DB/API/roles detail for most P4 items). Decomposing P4 to the concrete DB/API task level that P0–P3 enjoy would mean **inventing tables and endpoints that don't exist in any design doc** — exactly the cross-doc drift `GLOSSARY.md` exists to prevent. So P4 is decomposed to the depth the docs actually support: epic → known features → *what must be designed first*. Each epic is **sequenced by data/market signal, gated by its own business case**, and **re-decomposed to task level at the P3 gate** when its docs are written. Where a design doc is missing, the task is explicitly "**design doc first**", not a code task.

### E4.1 — Template/Program marketplace  ⬜ (`FR-MKT-003`)
- Coach-sold templates with revenue share. **Has partial design support:** `templates` [B] + `template_sales` [B] + `platform_fees` exist (DB doc §3.2/§3.3) and `GET/POST /v1/marketplace/templates` is stubbed (API §8). 
- Design-first: pricing/rev-share model, content moderation, discovery ranking. Then: storefront UI, purchase→clone flow, settlement via ledger (`INV-003`).

### E4.2 — Commerce & inventory  ⬜ (`FR-MKT-004`, `FR-FIN-006`)
- In-app commerce/affiliate (supplements, gear), supplements store, inventory management. **No design support yet** → **design doc first** (catalogue, cart/checkout, inventory, fulfillment/affiliate model, tax). Then DB/API.

### E4.3 — Equipment maintenance  ⬜
- Gym asset tracking & maintenance schedules. **No design support** → **design doc first** (asset model, maintenance lifecycle, alerts). Plane-B, gym-scoped.

### E4.4 — Clinical & corporate  ⬜
- Physio/RD pro track, corporate wellness, insurer partnerships. **No design support** → **design doc first** + clinical/compliance review (this touches regulated health advice — needs the safety/clinical advisor, cf. Q7). Likely new roles in `ROLES_PERMISSIONS.md` and new consent `data_class` values.

### E4.5 — Data/insights product  ⬜
- Anonymized, consented B2B insights — **the Graph monetized** (MASTER §9). **Depends on the outcome-labeling pipeline** seeded in P1 (`outcomes` table) maturing. Design-first: anonymization/k-anonymity guarantees, consent basis, aggregation/OLAP, productization. **Privacy review is release-blocking.**
- This is the long-term moat; its quality is a function of how well `outcomes`/`adherence_events` were captured in P1–P3, which is why the graph pipeline is a cross-cutting workstream from day one.

### E4.6 — Future AI vision  ⬜ (`MASTER §` future vision)
- Body-transformation analysis, exercise form correction, posture analysis. **R&D / spike first** (model availability, on-device vs server, cost, accuracy, liability). Reuses `progress_photos` (encrypted) + `LlmGateway`/vision provider. Safety gate (`INV-005`) extends to vision output.

### E4.7 — Deep i18n expansion  ⬜
- More languages, regional payment rails, localized food data (`food_items` localization deepens), more residency regions. Mostly *extension* of P1–P2 mechanisms (RTL/locale/PSP/residency already architectural) rather than net-new design — the cheapest P4 epic because the bones exist.

### E4.8 — Scale hardening  ⬜ (ARCH §8)
- Apply **partition → read-model → shard** in that order, by evidence: OLAP/warehouse for reporting; TSDB migration for `wearable_streams`/`access_events`; multi-region. **Evidence-gated** — do only what load tests prove necessary; don't pre-shard. Shard keys already chosen (`person_id` Plane A, `tenant_id` Plane B; ULIDs make it clean).

---

## Cross-cutting workstreams (every phase — see `EXECUTION_PLAN §9`)

These are not a phase; they run continuously and are tracked in `IMPLEMENTATION_PROGRESS §5`.

| Workstream | What it means task-by-task | Release-blocking artifact |
|---|---|---|
| **AI cost & quality** | Model tiering/caching, eval harness, `ai_interactions` cost dashboards (`NFR-OPS-002`), margin guardrail (`NFR-AI-001`) | AI COGS/active-user within margin (P1 gate) |
| **Security & isolation/consent** | Isolation tests (`INV-001`), consent tests (`INV-004`), GDPR export/delete, residency, pen-test before each public launch | Zero open isolation/consent/safety defects (every gate) |
| **i18n/RTL & a11y** | AR+EN + RTL kept green every sprint; WCAG 2.2 AA, axe/Flutter-semantics in CI (`NFR-UX-001/003`) | a11y + RTL CI gates green |
| **Observability & SRE** | SLOs, alerting, tracing (`NFR-OPS-001`), DR drills / tested restores (`NFR-REL-003`) | RPO ≤15min / RTO ≤1h proven |
| **Graph/outcome-labeling pipeline** | `outcomes` + `adherence_events` captured cleanly from P1; the supervised dataset that becomes E4.5 and improves the Brain | Pipeline seeded P1, enriched each phase |

---

## How this plan is maintained
- **Rolling-wave execution discipline holds even though all phases are decomposed:** at each **phase gate**, re-decompose the *next* phase against its (by-then-written) design docs, reconcile statuses with `IMPLEMENTATION_PROGRESS.md`, and **re-decompose P4 items to task level only when their design doc exists** (don't execute the `[planning-time]` bullets as-is).
- New work → add as a task under the right epic with its `FR`/`NFR`/`INV` ref; if it doesn't map to an existing requirement, add it to `PRODUCT_REQUIREMENTS.md` first, and any new entity to `GLOSSARY.md` first (source of truth — prevents drift).
- Status of the **current** epic's tasks is mirrored in `IMPLEMENTATION_PROGRESS.md`; this file holds the full breadth, that file holds the live status.
