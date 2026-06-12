# IMPLEMENTATION_PROGRESS.md
### Fitness OS — Living Progress Tracker

> **Status:** Active · **Owner:** Eng/Product Leadership · **Last updated:** 2026-06-12
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

### Phase 0 — Foundation / walking skeleton  🟡 in progress
| Item | Status | Notes |
|---|---|---|
| Monorepo skeleton + docs/ + git init | ✅ | `apps/ packages/ infra/ docs/ tools/` |
| `packages/design-tokens` → Tailwind/CSS/Flutter | ✅ | build verified, 3 targets emitted |
| `packages/api-contracts/openapi.yaml` | ✅ | P1 slice endpoints |
| Laravel 12 modular monolith + ModuleServiceProvider | ✅ | autoload/migrations/routes auto-wired |
| `Person` identity (ULID) + Sanctum auth | 🟡 | done; **social OAuth** not yet |
| Core: ULID ✅ · Tenancy/Consent/Audit/Ledger | 🟡 | **dormant — deferred to P2** (no tenants in B2C P1) |
| **Vertical slice: offline log → sync → display** | ✅ | **TDD: 4 tests / 17 assertions green** — append-only + idempotent replay + cross-person isolation + auth, verified end-to-end |
| CI/CD + Docker + IaC + observability | ⬜ | next |
| Filament super-admin shell | ⬜ | next (`composer require filament`) |
| AI Brain spike | ⬜ | planned — `docs/AI_BRAIN_SPIKE.md` |

**Build-env notes (this machine):** PHP **8.2** (brief: 8.3+); **MariaDB 10.11** local (prod: MySQL 8 — `utf8mb4_0900_ai_ci` is MySQL-only); **no `pdo_sqlite`** so tests run on MariaDB `fitness_os_test`; **Flutter absent** → mobile deferred; Composer uses slow git-source path (no GitHub token).

### Phase 1 — B2C + AI MVP (FR-level)
| FR group | Feature | Status |
|---|---|---|
| FR-IDN, FR-AI-007 | Onboarding + PAR-Q+ health screen | ⬜ |
| FR-TRN-* | Exercise library + training log (offline, timers, history, PRs) | ⬜ |
| FR-NUT-001/002/003/006 | Food DB + nutrition log (search/barcode/macros/water) | ⬜ |
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
