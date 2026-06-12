# Fitness OS

> The operating system for the entire fitness value chain — individuals, coaches, and gyms — connected by one user-owned identity, one data graph, and one AI brain.

**Status:** Phase 0 (foundation / walking skeleton). Design phase complete; implementation beginning per [`docs/EXECUTION_PLAN.md`](docs/EXECUTION_PLAN.md).

## Repository layout (monorepo)

```
apps/
  api/            Laravel modular monolith (backend)        ← Phase 0/1
  web/            TALL dashboards (coach/gym)               ← P2/P3
  admin/          Filament super-admin                      ← P1
  mobile_member/  Flutter member app                        ← P1 (Flutter not yet provisioned)
  mobile_trainer/ Flutter trainer app                       ← P2
  mobile_staff/   Flutter gym-staff app                     ← P3
packages/
  design-tokens/  Source-of-truth tokens → Tailwind + Flutter + Filament themes
  api-contracts/  OpenAPI spec + generated clients
  flutter_core/   Shared Flutter: theme, networking, sync engine
infra/            Docker, Terraform (IaC), CI
docs/             The 10 design documents + ADRs + GLOSSARY (source of truth)
tools/            scripts, generators
```

## The design documents (read in order)

| # | Doc | Purpose |
|---|---|---|
| — | [GLOSSARY](docs/GLOSSARY.md) | Canonical entity names — source of truth |
| 1 | [MASTER_PRODUCT](docs/MASTER_PRODUCT.md) | Vision, flywheel strategy, competitors, moats |
| 2 | [PRODUCT_REQUIREMENTS](docs/PRODUCT_REQUIREMENTS.md) | Personas, journeys, FR/NFR, MVP, roadmap |
| 3 | [SYSTEM_ARCHITECTURE](docs/SYSTEM_ARCHITECTURE.md) | Two-plane tenancy, modular monolith, AI brain, ADRs |
| 4 | [DATABASE_DESIGN](docs/DATABASE_DESIGN.md) | Entities, ERD, partitioning, invariants |
| 5 | [ROLES_PERMISSIONS](docs/ROLES_PERMISSIONS.md) | Three-layer authorization model |
| 6 | [API_SPECIFICATION](docs/API_SPECIFICATION.md) | REST + WebSocket contract |
| 7 | [UI_UX_SYSTEM](docs/UI_UX_SYSTEM.md) | Design system, tokens, RTL, dark-first |
| 8 | [BLUEPRINT](docs/BLUEPRINT.md) | Engineering handbook & repo blueprint |
| 9 | [EXECUTION_PLAN](docs/EXECUTION_PLAN.md) | Phased build plan & gates |
| 10 | [IMPLEMENTATION_PROGRESS](docs/IMPLEMENTATION_PROGRESS.md) | Living progress tracker |

## Confirmed decisions
- **A1** — Build sequence: **B2C + AI first**, then Coach (P2), then Gym (P3).
- **A2** — **MENA-first, Arabic + English, RTL & data-residency day-one**, global-ready.
- **A3** — The **Person owns their data**; tenants get scoped, revocable consent.

## Local toolchain (this machine)
PHP 8.2 · Composer 2.8 · Node 20 · Docker · MariaDB 10.11 · Redis 7. Flutter **not yet installed** (mobile apps deferred within Phase 0).

> Note: brief targets PHP 8.3+ / MySQL 8; local env has PHP 8.2 / MariaDB. Both are compatible with Laravel 11+ for development; production targets the brief's versions (see `infra/`).

## Getting started (once Phase 0 lands)
See [`docs/BLUEPRINT.md`](docs/BLUEPRINT.md) §7 (environments) and `apps/api/README` after scaffolding completes.
