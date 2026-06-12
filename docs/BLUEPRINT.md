# BLUEPRINT.md
### Fitness OS — Engineering Handbook & Repository Blueprint

> **Status:** Draft v1.0 · **Owner:** Eng Leadership · **Last updated:** 2026-06-12
> **Document 8 of 10.** The **bridge** between abstract design and execution. `SYSTEM_ARCHITECTURE.md` says *what & why*; `EXECUTION_PLAN.md` says *when & in what order*; **this says *how we build it*** — repo layout, module skeletons, standards, conventions, environments, and the "definition of done." Onboarding a new engineer should require only this document + the architecture.

---

## 1. Repository strategy

**Monorepo** (single repo, multiple deployables) — chosen because shared contracts (API types, **design tokens**, lint config) must stay in lockstep across backend, web, and three Flutter apps, and a small team benefits from atomic cross-cutting changes.

```
fitness-os/
├── apps/
│   ├── api/                  # Laravel 11 modular monolith (the backend)
│   ├── web/                  # TALL dashboards (Livewire) — coach/gym/owner
│   ├── admin/                # Filament super-admin (may live inside api/)
│   ├── mobile_member/        # Flutter — Member app (P1)
│   ├── mobile_trainer/       # Flutter — Trainer app (P2)
│   └── mobile_staff/         # Flutter — Gym staff app (P3)
├── packages/
│   ├── design-tokens/        # SoT tokens → generates Tailwind config + Flutter theme + Filament theme
│   ├── api-contracts/        # OpenAPI spec (from API_SPECIFICATION.md) + generated clients
│   ├── flutter_core/         # Shared Flutter: theme, networking, sync engine, design widgets
│   └── eslint-php-config/    # Shared lint/format presets
├── infra/
│   ├── docker/               # Dockerfiles, compose for local
│   ├── terraform/            # IaC (envs, region-pinned, residency)
│   └── ci/                   # Pipeline definitions
├── docs/                     # These 10 documents + ADRs + GLOSSARY.md
└── tools/                    # scripts, generators, seeders
```

> Flutter apps may instead be one app with flavors if overlap stays high; revisit at P2. Default to separate apps sharing `packages/flutter_core`.

---

## 2. Backend module skeleton (Laravel modular monolith)

Each bounded context from `SYSTEM_ARCHITECTURE.md §5` is a **self-contained module**. Modules own their tables/migrations and expose a public **service interface**; cross-module calls go through interfaces/events only (never another module's models or tables).

```
apps/api/
├── app/
│   ├── Core/                 # cross-cutting: Tenancy, Consent, Auth, Ledger primitives, ULID, Audit
│   └── Support/
├── modules/
│   ├── Identity/             # Plane A
│   │   ├── Domain/           # entities, value objects, events, contracts
│   │   ├── Application/      # use-cases/services, DTOs
│   │   ├── Infrastructure/   # Eloquent models, repositories, external adapters
│   │   ├── Http/             # controllers, requests, resources, policies
│   │   ├── Database/         # migrations, factories, seeders
│   │   └── Tests/
│   ├── Graph/                # Plane A
│   ├── Training/  Nutrition/  Engagement/
│   ├── AiOrchestration/      # LlmGateway, RAG, safety gate, credit metering
│   ├── Billing/  Notifications/  Admin/   # P1
│   ├── Coaching/  Marketplace/  Payments/ # P2 (Plane B)
│   └── GymOps/  Analytics/                # P3 (Plane B)
```

**Module contract rules**
- Public API of a module = its `Application` service interfaces + emitted domain events. Everything else is internal.
- A module **never** imports another module's Eloquent model. Need data? Call the owning module's service or subscribe to its event.
- **Plane A vs Plane B** is explicit per module (DB §1); Plane-B models extend a `TenantScopedModel` base that applies the global scope + `tenant_id` (ARCH §4.4).
- The **Consent gate** (`app/Core/Consent`) is the only path for a tenant actor to read Plane-A Graph data (ROLES §1, Layer 3).

---

## 3. Flutter app skeleton (Clean Architecture)

```
apps/mobile_member/lib/
├── core/            # DI, routing, env, error, localization (ar/en, RTL)
├── features/
│   └── workout_log/
│       ├── domain/        # entities, repositories (abstract), use-cases
│       ├── data/          # models, remote (api-contracts client), local (Drift), repo impl
│       └── presentation/  # state (Riverpod/Bloc), screens, widgets
└── shared/          # from packages/flutter_core: theme, sync, design widgets
```
- **Offline-first** (ARCH §7): local Drift store is the UI source of truth; an **outbox + sync engine** (in `flutter_core`) handles idempotent upload (client ULIDs) and delta pull. Append-only logs never conflict.
- **Theme** generated from `packages/design-tokens`; **RTL** via logical layout + `Directionality` driven by locale.
- App functions with the Brain/network down (graceful degradation, NFR-REL-004).

---

## 4. Coding standards

| Area | Standard |
|---|---|
| PHP | PSR-12; **Pint** (format), **Larastan/PHPStan level max**, **Rector** for upgrades |
| Architecture rules | Enforced in CI: no cross-module model imports; Plane-B models must extend `TenantScopedModel`; no raw tenant-unscoped queries |
| Dart/Flutter | `flutter_lints` + custom rules; `dart format`; feature-first structure |
| Naming | Per `GLOSSARY.md`; DB snake_case, code PascalCase, ULIDs everywhere |
| Money | Integer minor units + currency type — lint forbids float money (INV-006) |
| Errors | RFC-9457 problem+json (API §1.1); typed domain exceptions |
| i18n | No hardcoded user-facing strings; all via locale files (en/ar); RTL-safe |
| Secrets | Never in code/repo; env + KMS/Vault |
| Comments | Match surrounding density; explain *why*, not *what* |

---

## 5. Testing strategy (the safety net for a multi-tenant, money-handling, AI system)

| Layer | What | Tooling |
|---|---|---|
| Unit | Domain logic, use-cases, ledger balancing (INV-003) | Pest/PHPUnit; Dart test |
| Feature/integration | API endpoints vs `API_SPECIFICATION.md` | Pest + DB |
| **Tenant-isolation tests** ⭐ | Assert cross-tenant access → 404; consent gate enforced (INV-001/004) | CI-mandatory gate |
| **Contract tests** | OpenAPI ↔ implementation ↔ generated clients | Spectator/Dredd |
| Authorization tests | RBAC × tenant × consent matrix (ROLES §4) | Policy tests |
| AI safety tests | Contraindication gate rejects unsafe output (INV-005) | Eval harness |
| Offline/sync tests | Idempotent replay, conflict resolution | Flutter integration |
| Visual/a11y | Dark+light, LTR+RTL, AR+EN, axe/semantics | Snapshot + axe |
| Load | High-write tables, AI cost under load | k6/Artillery |

**TDD for domain & money logic.** Tenant-isolation, consent, and AI-safety tests are **release-blocking** — these are the invariants that, if broken, are catastrophic.

---

## 6. Git & workflow

- **Trunk-based**, short-lived feature branches, PRs required, ≥1 review.
- Conventional Commits → automated changelog/versioning.
- PR template requires: linked `FR/NFR` IDs, tests, the **"feeds-or-uses-the-Graph" check** (MASTER §3 anti-bloat rule), and migration/tenancy review when DB changes.
- Generated artifacts (API clients, theme) regenerated in CI, not hand-edited.

---

## 7. Environments & configuration

| Env | Purpose | Data |
|---|---|---|
| local | Docker-compose (api, mysql, redis, meili, reverb, mailpit) | seeded fixtures |
| ci | ephemeral test run | factories |
| preview | per-PR ephemeral | synthetic |
| staging | prod-parity rehearsal | anonymized |
| production | region-pinned, residency-aware (A2) | live |

- **12-factor config** via env; per-region deploys; feature flags gate phased features (P1/P2/P3) and **graduated AI autonomy**.
- **Tenant `db_mode`** (pooled vs dedicated, DB §2.7) handled by tenancy bootstrapping in `app/Core/Tenancy`.

---

## 8. Observability & ops conventions (NFR-OPS)

- Structured logs with `request_id` + tenant/person context (PII-scrubbed); centralized.
- Metrics + traces (OpenTelemetry); error tracking (Sentry).
- **Required dashboards:** API latency/error SLOs; queue depth per queue (AI/notify/sync isolated); **per-tenant & per-AI-feature cost/usage** (the margin guardrail, NFR-AI-001); North-Star (weekly active logged sessions).
- Alerting on SLO burn, queue backlog, AI cost anomalies, ledger imbalance, tenant-isolation test failures.

---

## 9. Security conventions (NFR-SEC)

- Default-deny authorization; three-layer checks on every endpoint (ROLES §1).
- Tokenized payments only; no PAN in our systems (PCI minimization).
- Encryption at rest + in transit; progress photos & health data extra-protected.
- Immutable audit log for money, health data, AI actions, admin/impersonation.
- Dependency scanning, SAST, secret scanning in CI; rotated secrets.
- Rate limits + abuse detection, with stricter AI buckets.

---

## 10. Definition of Done (applies to every feature)

A feature is **done** only when: code + tests (incl. isolation/consent/a11y where relevant) pass in CI · API matches `API_SPECIFICATION.md` (contract test green) · authorized per `ROLES_PERMISSIONS.md` · UI meets the `UI_UX_SYSTEM.md` DoD (dark/light, RTL/LTR, AR/EN, states, a11y) · observability/audit wired · docs/ADR updated · the **feeds-or-uses-the-Graph** check passes · `IMPLEMENTATION_PROGRESS.md` updated.

---

## 11. ADR process

Architectural decisions recorded as `docs/adr/NNNN-title.md` (context → decision → consequences). Seeded from `SYSTEM_ARCHITECTURE.md §12`. Significant deviations from these blueprints require a new ADR.

---

> **Next document:** `EXECUTION_PLAN.md` — the phased, milestone-driven build sequence (teams, order, dependencies, risks, success gates) that turns this blueprint into shipped software.
