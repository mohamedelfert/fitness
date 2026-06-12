# EXECUTION_PLAN.md
### Fitness OS — Phased Build Plan, Milestones & Gates

> **Status:** Draft v1.0 · **Owner:** Eng/Product Leadership · **Last updated:** 2026-06-12
> **Document 9 of 10.** Turns `BLUEPRINT.md` into a sequenced delivery plan. Honors the confirmed sequencing **B2C+AI → Coach → Gym** (A1) and MENA-first/RTL (A2). Milestones are dependency-ordered; **each phase ends with a go/no-go gate** tied to the success criteria in `PRODUCT_REQUIREMENTS.md §5/§6`. Timeboxes are planning estimates, not commitments.

---

## 1. Operating model

- **Cadence:** 2-week sprints; milestone reviews at each `M#`; phase gates between P1/P2/P3/P4.
- **Vertical slices:** ship thin end-to-end slices (DB→API→app) feature-by-feature, not layer-by-layer — earliest possible real usage.
- **Walking skeleton first:** before features, stand up the deployable skeleton (auth + one logged set, end-to-end, in prod) so CI/CD, observability, and the offline-sync contract are proven on day one.
- **Definition of Done** = `BLUEPRINT.md §10` for every item.

---

## 2. Team shape (lean, scales by phase)

| Function | P1 | P2 | P3 |
|---|---|---|---|
| Laravel/backend | 2–3 | +1 | +2 |
| Flutter | 2 | +1 (trainer) | +1 (staff) |
| AI engineer | 1 | 1 | 1 |
| Web (TALL/Filament) | 1 | +1 | +1 |
| Design (UI/UX) | 1 | 1 | 1 |
| DevOps/infra | 1 (shared) | 1 | +1 |
| QA/automation | 1 | 1 | +1 |
| PM + design lead | 1 + 1 | ↑ | ↑ |

Plus fractional security and a clinical/safety advisor (for the AI contraindication ruleset).

---

## 3. Phase 0 — Foundation (the walking skeleton) · ~M0–M1

**Goal:** prove the pipes before features. *No user value yet, but de-risks everything.*

- Monorepo + `packages/design-tokens` + `api-contracts` scaffolding (`BLUEPRINT §1`).
- Laravel modular-monolith skeleton; `Core` (ULID, Tenancy *dormant*, Consent, Audit, Ledger primitives).
- CI/CD, Docker local, IaC for staging+prod (region-pinned), observability baseline.
- AuthN (Sanctum + social), Person identity, **one vertical slice: log a set offline → sync → see it** (proves ARCH §7 + idempotency contract).
- Filament super-admin shell.
- **Exit:** skeleton deployed to prod; CI runs unit+contract+**tenant-isolation** tests; one real append-only log round-trips offline→online.

---

## 4. Phase 1 — B2C + AI MVP (the testable bet) · ~M1–M5

**Goal:** the thesis test — *can the AI coach retain Maya and fill the Graph?* (PRD §5)

**Build order (dependency-driven):**
1. **Onboarding + PAR-Q+ health screen** (safety gate must exist before AI plans) — `FR-IDN`, `FR-AI-007`.
2. **Exercise library + Training log** (offline-first, timers, history, PRs) — `FR-TRN-*`.
3. **Food DB integration + Nutrition log** (search, barcode, macros, water) — `FR-NUT-001/002/003/006`.
4. **AI Brain core**: program + meal-plan generation with **safety gate + RAG + AICredit metering + provider gateway** — `FR-AI-001/002`, `NFR-AI-*`. *(Hardest, highest-risk; start its spike during step 2.)*
5. **Today screen** (the hero daily loop) + smart notifications — `J1/J2`, `FR-ENG-006`.
6. **Progress + AI analysis**, biometrics, progress photos (encrypted), weekly report — `FR-BIO-*`, `FR-AN-001/005`.
7. **Wearables ingest** (Apple Health / Health Connect) → recovery-aware tips — `FR-BIO-003`, `FR-AI-005`.
8. **Engagement**: goals, habits, streaks/XP — `FR-ENG-001/002/003`.
9. **AI extras**: exercise alternatives, conversational coach, plan-adjustment proposals — `FR-AI-003/006/008`.
10. **SaaS B2C billing**: free/premium plans, credits, payments (regional PSP) — `FR-SAS-002/003/004`.
11. **i18n/RTL hardening** (AR+EN end-to-end), a11y pass, AI-cost dashboards.

**Milestones:** M2 closed-alpha (steps 1–4), M3 beta (5–7), M4 feature-complete (8–10), M5 polish/scale/i18n/a11y.

**🚦 Gate to P2 (all must hold):** D30 retention ≥ target (~35–40% activated) · ≥50% activated log ≥3 sessions/wk (North Star) · AI acceptance ≥60% · **AI COGS/active-user within margin** · free→premium ≥3–5% · zero open tenant-isolation/safety defects.

---

## 5. Phase 2 — Coach platform + marketplace (light loop 2) · ~M5–M9

**Prereq:** turn on **multi-tenancy** (built dormant in P1, ARCH §4.5) and the **Consent layer** for real.

**Build order:**
1. Tenancy activation + Consent enforcement live (isolation tests expand) — `FR-IDN-003`, `NFR-SEC-002`.
2. Trainer app + coach web: clients, profiles (consent-gated), templates, assignments — `FR-CCH-001/004`.
3. Check-ins + messaging (real-time via Reverb) — `FR-CCH-002/003`.
4. **AI-drafted check-ins & program tweaks (human-approved)** — `FR-AI-009`.
5. ChurnRisk for clients + alert playbooks — `FR-AN-004/006`.
6. **Payments & double-entry Ledger** (coach billing, packages, payouts, take-rate) — `FR-FIN-001/003`, PSP+Stripe Connect.
7. Coach branding/white-label + mini-CRM/funnels — `FR-CCH-006/008`.
8. **Marketplace**: coach discovery/matching, trial→paid, take-rate — `FR-MKT-001/002`.
9. Additional wearables (Garmin/Whoop/Fitbit), community/social.

**Milestones:** M6 tenancy+consent live; M7 coach core; M8 payments/ledger+AI check-ins; M9 marketplace.

**🚦 Gate to P3:** coach NRR >100% · marketplace liquidity (match→trial→paid funnel proven) · ledger reconciles 100% vs PSP · consent enforcement verified.

---

## 6. Phase 3 — Gym OS (light loop 3, enterprise prize) · ~M9–M14

**Build order:**
1. Multi-branch tenant model, membership plans + lifecycle (freeze/upgrade/transfer/family) — `FR-GYM-001/002/020`.
2. **Access control** (QR/NFC/barcode/gate) + AccessEvents + live occupancy (`<3s` check-in) — `FR-GYM-004/005`, `NFR-PERF-004`.
3. Classes/scheduling + bookings/waitlists/no-show fees + resources — `FR-GYM-010/011/012`.
4. Staff/trainer mgmt, schedules, attendance, commissions, payroll — `FR-GYM-013/014`.
5. Staff app (fast check-in, sell/freeze, POS-lite) — `FR-APP-005`, `FR-FIN-005`.
6. Centralized cross-branch reporting, broadcasts, sales CRM, waivers — `FR-GYM-021/022/023/006`.
7. **Member engagement loop = the P1 consumer app inside the gym** (the differentiator) + gym-level ChurnRisk/win-back.
8. Bulk member import + onboarding tooling (instant install base).

**Milestones:** M10 membership+access; M11 classes/staff; M12 staff app+POS; M13 reporting/CRM/comms; M14 engagement loop + pilot gym live.

**🚦 Gate to P4:** ≥1 multi-branch reference customer live · member-app DAU inside the gym proven · on-platform PT revenue captured.

---

## 7. Phase 4 — Ecosystem & scale · M14+

Template marketplace, in-app commerce/affiliate, inventory/store, equipment maintenance, clinical/pro track, corporate wellness, anonymized data product, future AI vision (form/posture), deep international expansion. Sequenced by data/market signals.

---

## 8. Critical path & dependencies

```
Phase0 skeleton ─► P1[ PAR-Q+ ─► training log ─► nutrition log ─► AI Brain ─► Today loop ]
                                                                     │
                          (AI safety gate blocks all AI features) ───┘
P1 gate ─► P2[ tenancy+consent ─► coach core ─► payments/ledger ─► AI check-ins ─► marketplace ]
P2 gate ─► P3[ membership ─► access control ─► classes/staff ─► staff app ─► engagement loop ]
```

**Hard dependencies:** PAR-Q+/safety gate **before** any AI plan ships · tenancy+consent **before** any coach sees client data · ledger **before** any payout/take-rate · the consumer app (P1) **before** the gym member experience (P3) has value.

---

## 9. Cross-cutting workstreams (run continuously, all phases)

- **AI cost & quality** — model tiering, caching, eval harness, margin dashboards.
- **Security & compliance** — isolation/consent tests, audits, GDPR, residency, pen-test before each public launch.
- **i18n/RTL & a11y** — kept green every sprint (cheaper than retrofitting).
- **Observability & SRE** — SLOs, alerting, DR drills (test restores).
- **Data/graph integrity** — outcome labeling pipeline (the moat) seeded in P1, enriched every phase.

---

## 10. Top risks & mitigations (execution-level)

| Risk | Mitigation |
|---|---|
| AI Brain underdelivers (the whole P1 thesis) | Start spike in Phase 0; eval harness; human-fallback content; gate on acceptance metric |
| AI cost blows margin | Tiered models, caching, credit metering, cost dashboards from M2 |
| Scope creep pulls P2/P3 into P1 | Feature flags + strict gates; "feeds-or-uses-Graph" cut rule; phase reviews |
| Tenant isolation defect | Release-blocking isolation tests; dedicated-DB option for enterprise |
| Offline sync data loss | Append-only logs + ULID idempotency proven in Phase 0 skeleton |
| Payment/ledger errors | Double-entry invariant tests; webhook reconciliation; dual-control on material ops |
| Hiring/throughput | Lean team, vertical slices, buy-don't-build commodities (MASTER §12) |

---

## 11. What "millions of users" readiness means per phase

- **P1:** correct partitioning on high-write tables, read replicas wired, caching, stateless API, load test the log + AI paths. (Architecture supports scale; we don't over-provision early.)
- **P2:** tenancy at scale (pooled + dedicated), ledger throughput.
- **P3:** access-event/occupancy write volume, cross-branch reporting via read-models/OLAP.
- **Ongoing:** partition→read-model→shard, in that order, by evidence (ARCH §8).

---

> **Next document:** `IMPLEMENTATION_PROGRESS.md` — the living tracker: design-doc status, milestone/feature status by FR-ID, and the rule that keeps it current.
