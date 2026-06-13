# IMPLEMENTATION_PROGRESS.md
### Fitness OS — Living Progress Tracker

> **Status:** Active · **Owner:** Eng/Product Leadership · **Last updated:** 2026-06-13
> **Document 10 of 10.** The single source of truth for *where we are*. Updated continuously — `BLUEPRINT.md §10` makes "update this file" part of the Definition of Done. Legend: ✅ done · 🟡 in progress · ⬜ not started · 🚫 blocked.

---

## 1. Design phase — the 10 documents

| # | Document | Status | Notes |
|---|---|---|---|
| — | `GLOSSARY.md` (canonical source of truth) | ✅ | Seeded; grows as new entities appear |
| 1 | `MASTER_PRODUCT.md` | ✅ | Vision, flywheel spine, competitor analysis, moats, added features |
| 2 | `PRODUCT_REQUIREMENTS.md` | ✅ | Personas, journeys, FR/NFR with IDs, MVP, roadmap; A1/A2 confirmed |
| 3 | `SYSTEM_ARCHITECTURE.md` | ✅ | Two-plane tenancy, modular monolith, AI brain, ADRs |
| 4 | `DATABASE_DESIGN.md` | ✅ | Central + tenant schemas, ERD, partitioning, invariants |
| 5 | `ROLES_PERMISSIONS.md` | ✅ | Three-layer authz (RBAC × tenant × consent), matrix |
| 6 | `API_SPECIFICATION.md` | ✅ | REST + WS, idempotency/sync contract, endpoint catalogue |
| 7 | `UI_UX_SYSTEM.md` | ✅ | Tokens, components, motion, dark-first + RTL, surfaces |
| 8 | `BLUEPRINT.md` | ✅ | Repo layout, module skeletons, standards, testing, DoD |
| 9 | `EXECUTION_PLAN.md` | ✅ | Phases, milestones, dependency path, go/no-go gates |
| 10 | `IMPLEMENTATION_PROGRESS.md` | ✅ | This tracker |

**Design phase: COMPLETE.** Ready for stakeholder review before code (per the brief's "no code until documented" rule).

---

## 2. Decisions log

| ID | Decision | Date | Source |
|---|---|---|---|
| A1 | MVP sequencing **B2C+AI → Coach → Gym** | 2026-06-12 | Stakeholder confirmed |
| A2 | **MENA-first, Arabic+English, RTL & residency day-one**, global-ready | 2026-06-12 | Stakeholder confirmed |
| A3 | **Person owns data; tenants get scoped, revocable consent** | 2026-06-12 | Proposed default (in effect) |
| A4 | Wearable ingest (Apple Health / Health Connect) in P1 | 2026-06-12 | Proposed default |
| A5 | AI **drafts**, human **approves** for coach→client output | 2026-06-12 | Proposed default |
| ADR-001..009 | Architecture decisions | 2026-06-12 | `SYSTEM_ARCHITECTURE.md §12` |

---

## 3. Open questions / pending confirmations

| # | Question | Needed by | Default in effect |
|---|---|---|---|
| Q1 | Confirm A3/A4/A5 defaults (data ownership, wearables-in-P1, AI human-in-loop) | Before P1 build | Using defaults |
| Q2 | Pricing (B2C/coach/gym tiers) | Before P1 billing (step 10) | Placeholders (PRD §0) |
| Q3 | PSP selection per MENA country (Paymob/HyperPay/Tap coverage) | Before P1 billing | TBD |
| Q4 | Food DB & exercise-media licensing (Arabic-localized) | Before P1 nutrition/training | TBD (build-vs-buy, MASTER §12) |
| Q5 | AI provider contract & model tiers | Phase 0 spike | Claude-primary + fallback |
| Q6 | `stancl/tenancy` validation (single+multi DB on Laravel 11) | Before P2 | Candidate, needs spike |
| Q7 | AI contraindication ruleset source (clinical advisor) | Before AI plans ship | TBD |

---

## 4. Build status by phase (no code yet — all ⬜)

### Phase 0 — Foundation / walking skeleton  ✅ substantially complete (social OAuth + AI spike remain)
| Item | Status | Notes |
|---|---|---|
| Monorepo skeleton + docs/ + git init | ✅ | `apps/ packages/ infra/ docs/ tools/` |
| `packages/design-tokens` → Tailwind/CSS/Flutter | ✅ | build verified, 3 targets emitted |
| `packages/api-contracts/openapi.yaml` | ✅ | P1 slice endpoints |
| Laravel 12 modular monolith + ModuleServiceProvider | ✅ | autoload/migrations/routes auto-wired |
| `Person` identity (ULID) + Sanctum auth | 🟡 | done; **social OAuth** not yet |
| Core: ULID ✅ · Tenancy/Consent/Audit/Ledger | 🟡 | **dormant — deferred to P2** (no tenants in B2C P1) |
| **Vertical slice: offline log → sync → display** | ✅ | **TDD: 4 tests / 17 assertions green** — append-only + idempotent replay + cross-person isolation + auth, verified end-to-end |
| CI (GitHub Actions) + Docker dev stack | ✅ | `.github/workflows/ci.yml` runs Pint + PHPUnit on **MySQL 8**; compose: MySQL 8 + Redis + Meilisearch + Mailpit |
| Observability baseline | ✅ | request-id middleware (X-Request-Id + log Context), `/v1/health` probe (DB required, Redis best-effort), JSON log channel (NFR-OPS-001) |
| IaC (Terraform) baseline | ✅ | region-pinned AWS skeleton (encrypted S3 media + ECR); full stack (VPC/RDS/Redis/compute/WAF) deferred to account confirmation |
| Filament super-admin shell | ✅ | Filament v5 panel at `/admin`; dedicated `admin` guard (`PlatformUser`, separate from Persons); `PersonResource`; rendering needs ext-intl (Docker/CI) |
| AI Brain spike | ⬜ | planned — `docs/AI_BRAIN_SPIKE.md` |

**Build-env notes (this machine):** PHP **8.2** (brief: 8.3+); **MariaDB 10.11** local (prod: MySQL 8 — `utf8mb4_0900_ai_ci` is MySQL-only); **no `pdo_sqlite`** so tests run on MariaDB `fitness_os_test`; **Flutter absent** → mobile deferred; Composer uses slow git-source path (no GitHub token).

### Phase 1 — B2C + AI MVP (FR-level)
| FR group | Feature | Status |
|---|---|---|
| FR-IDN, FR-AI-007, FR-ENG-001 | Onboarding + PAR-Q+ health screen | 🟡 **PAR-Q+ screen + AI safety gate ✅** (6 tests). **Onboarding profile capture ✅** (TDD, 14 tests/51 assertions): `GET/PATCH /v1/me`, `POST /v1/onboarding`, `GET/POST /v1/goals` (new Engagement module + `goals` table); training profile (experience/equipment/schedule/diet/injuries) in `onboarding_state.profile`; `AiInputProfile` assembles the Brain contract incl. injuries + screen status. Social OAuth + first-plan handoff (needs E1.6) remain. |
| FR-TRN-001/006 | Exercise library + search | 🟡 **Browse/search API ✅** (TDD, 10 tests): `GET /v1/exercises` (q + muscle/equipment filters, cursor, Accept-Language localized) + `GET /v1/exercises/{id}`; `exercises` enriched to spec (secondary_muscles/mechanics/media_keys); DB-backed search (Meili = prod path); bilingual dev seeder w/ contraindications. **Program builder + timers/PR-detection still ⬜.** |
| FR-TRN-005 | Program model (programs→workouts→workout_exercises) | 🟡 **Read model ✅** (TDD, 4 tests): `GET /v1/programs`, `GET /v1/programs/{id}` (nested workouts→exercises, person-scoped, cross-person→404). Tables + models + factories ready for AI generation (E1.6) to populate. Interactive builder (coach/advanced) is P2. |
| FR-TRN-004 | PR auto-detection read-model | ✅ (TDD, 6 tests): `POST /v1/sessions/{id}/finish` dispatches queued `DetectPersonalRecords` → derives `personal_records` (max_load, est_1rm Epley, max_reps) from `set_logs`; `GET /v1/me/records`. Async (off hot path), idempotent, person-scoped. |
| FR-TRN-002/003 | Training log polish (timers, history filters) | ⬜ — SetLog append/idempotent ✅ (P0); **timers are largely client-side** (Flutter, E1.10); history filters pending. |
| FR-NUT-001/002/003 | Food DB + food logging + daily summary | 🟡 **Core ✅** (TDD, 13 tests): new **Nutrition** module — `food_items` (localized, barcode) + `food_logs` (append-only, idempotent); `GET /v1/foods?q=` (localized search incl. Arabic via `LocalizedJson` cast), `GET /v1/foods/barcode/{code}`, `POST /v1/food-logs` (snapshots servings×macros, or custom), `GET /v1/me/nutrition/summary?date=`; bilingual `FoodLibrarySeeder`. **Water/supplements, recipes, meal_plans, AI photo/voice still ⬜.** |
| FR-NUT-006/007 | Water + supplement logging | ✅ (TDD, 6 tests): append-only idempotent `water_logs`/`supplement_logs`; `POST /v1/water-logs`, `POST /v1/supplement-logs`; water folded into `/v1/me/nutrition/summary`. |
| FR-NUT-009, FR-NUT-004/005 | Recipes · meal plans · AI photo/voice logging | ⬜ |
| FR-AI-001/002 + NFR-AI | AI Brain core (gen + safety gate + RAG + credit meter + gateway) | ⬜ |
| FR-ENG-006, J2 | Today screen + smart notifications | ⬜ |
| FR-BIO-*, FR-AN-001/005 | Progress + AI analysis + biometrics + photos + weekly report | ⬜ |
| FR-BIO-003, FR-AI-005 | Wearables ingest + recovery tips | ⬜ |
| FR-ENG-001/002/003 | Goals, habits, streaks/XP | ⬜ |
| FR-AI-003/006/008 | Exercise alternatives, conversational coach, plan-adjust | ⬜ |
| FR-SAS-002/003/004 | B2C billing + credits + payments | ⬜ |
| NFR-UX-003, NFR-UX-001 | i18n/RTL hardening + a11y pass | ⬜ |
| **P1 GATE** | Retention/North-Star/AI-acceptance/margin/conversion criteria | ⬜ |

### Phase 2 — Coach + marketplace
| Area | Status |
|---|---|
| Tenancy activation + consent enforcement | ⬜ |
| Coach core (clients/templates/assignments/check-ins/chat) | ⬜ |
| AI-drafted check-ins (human-approved) | ⬜ |
| ChurnRisk + playbooks | ⬜ |
| Payments + double-entry ledger + take-rate | ⬜ |
| Branding/white-label + mini-CRM | ⬜ |
| Marketplace (discovery/match/trial→paid) | ⬜ |
| Extra wearables + community | ⬜ |
| **P2 GATE** | Coach NRR >100% · marketplace liquidity · ledger reconciliation | ⬜ |

### Phase 3 — Gym OS
| Area | Status |
|---|---|
| Multi-branch + membership lifecycle | ⬜ |
| Access control + occupancy | ⬜ |
| Classes/bookings/waitlists/resources | ⬜ |
| Staff/commissions/payroll | ⬜ |
| Staff app + POS-lite | ⬜ |
| Reporting/CRM/broadcasts/waivers | ⬜ |
| In-gym engagement loop + churn/win-back | ⬜ |
| **P3 GATE** | Multi-branch reference live · member DAU · PT revenue captured | ⬜ |

### Phase 4 — Ecosystem
| Area | Status |
|---|---|
| Template marketplace · commerce · inventory · clinical/corporate · data product · vision AI · intl expansion | ⬜ |

---

## 5. Cross-cutting workstream health (updated each sprint)

| Workstream | Status | Notes |
|---|---|---|
| AI cost & quality (margin guardrail) | ⬜ | Dashboards required from M2 |
| Security/compliance (isolation, consent, GDPR, residency) | ⬜ | Release-blocking tests |
| i18n/RTL & a11y | ⬜ | Kept green every sprint |
| Observability/SRE (SLOs, DR drills) | ⬜ | |
| Graph/outcome-labeling pipeline (the moat) | ⬜ | Seed in P1 |

---

## 6. How to keep this current (the rule)

- Every PR that completes a tracked item flips its status here (enforced by DoD, `BLUEPRINT §10`).
- New decisions → append to §2 and, if architectural, add an ADR.
- New unknowns → add to §3 with "needed by".
- Update the top `Last updated` date on every change.

---

## 7. Immediate next actions (post-review)

1. **Stakeholder review** of all 10 documents; confirm/adjust open questions Q1–Q7.
2. Resolve **Q4 (licensing)** and **Q3 (PSP)** — external dependencies with lead time.
3. Kick off **Phase 0** (walking skeleton) + the **AI Brain spike** (Q5/Q7) in parallel.
4. Stand up the monorepo, design-tokens, and CI per `BLUEPRINT.md`.

---

> **Design phase complete.** Per the brief, no application code is written until these documents are reviewed and approved. This tracker becomes the heartbeat of the build once Phase 0 begins.
