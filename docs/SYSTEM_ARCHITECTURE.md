# SYSTEM_ARCHITECTURE.md
### Fitness OS — System & Infrastructure Architecture

> **Status:** Draft v1.0 · **Owner:** Architecture · **Last updated:** 2026-06-12
> **Document 3 of 10.** Implements the strategy (`MASTER_PRODUCT.md`) and requirements (`PRODUCT_REQUIREMENTS.md`) using canonical names from `GLOSSARY.md`.
> **Confirmed inputs:** MENA-first, Arabic+English, **RTL & data-residency day-one** (A2) · **B2C+AI MVP first**, then Coach (P2), then Gym (P3) (A1).

---

## 1. Architecture drivers (what forces the design)

| Driver | Source | Architectural consequence |
|---|---|---|
| **User-owned, portable identity spanning tenants** | MASTER §3, A3 | A Person is NOT owned by a tenant → naive per-tenant isolation breaks. Forces a **central identity + graph** plane separate from tenant-scoped operational data. *This is the central tension §4 resolves.* |
| **Three segments, sequenced (B2C→Coach→Gym)** | A1 | P1 needs **no multi-tenancy at all** for end-users; tenancy enters at P2. Build a modular monolith with tenant-awareness designed-in but dormant in P1. |
| **Scale to 10M+ Persons, 100k+ tenants** | NFR-SCAL | Stateless API, read replicas, caching, queue-driven async, partition-ready high-write tables (logs, access events, wearables). |
| **AI is core + expensive** | MASTER §8, NFR-AI | LLM gateway with provider fallback, tiered models, caching, credit metering, async workers, safety-eval gate. |
| **Offline-first mobile** | NFR-REL-002 | Local-first store + sync engine + conflict resolution + idempotent writes. App must function with Brain/network down. |
| **MENA-first + data residency + RTL** | A2 | Region-pinned deployments; isolated in-region DB for residency-sensitive enterprise tenants; regional PSPs; RTL is a UI concern (UI doc) but locale is a data concern here. |
| **Money movement is a revenue line** | MASTER §8 | First-class audited **Ledger**, PCI scope minimization, webhook-driven reconciliation. |

---

## 2. Tech stack (confirmed from brief, with rationale)

| Layer | Choice | Notes |
|---|---|---|
| Backend | **Laravel 11+, PHP 8.3+** | Modular monolith (§5). |
| Primary DB | **MySQL 8** | InnoDB; partitioning for high-write tables; read replicas. |
| Cache / queue / locks | **Redis** | Cache, queues (Horizon), rate limiting, sessions, locks. |
| Search | **Meilisearch** (start) → OpenSearch (scale) | Food/exercise/coach search; typo-tolerant, RTL-aware. |
| Object storage | **S3-compatible** (region-pinned) | Media, progress photos (encrypted), exports. |
| Real-time | **Laravel Reverb** (WebSockets) | Chat, live updates, occupancy; Soketi/Pusher as fallback. |
| Async | **Queues + Horizon** | AI jobs, reports, notifications, sync, webhooks. |
| Web | **TALL** (Tailwind, Alpine, Livewire) | Member/individual web (responsive companion) + coach & gym/owner dashboards. |
| Admin | **Filament** | Super-admin (P1) + tenant back-offices. |
| Mobile | **Flutter, Clean Architecture** | Member app (P1), Trainer app (P2), Staff app (P3). |
| Local mobile DB | **Drift (SQLite)** or **Isar** | Offline-first store + outbox. |
| AI | **LLM gateway, Claude-primary + fallback** | §6; provider-agnostic behind an interface. |
| Infra | **Docker, CI/CD, IaC, observability** | §11. |

> ⚠️ **Challenge — microservices are the wrong call for P1.** The feature breadth tempts a microservice swarm. That would be premature: it multiplies ops cost, breaks transactions, and slows a small team. **Decision: modular monolith first** (§5), with a few things carved out as separate processes from day one only where the runtime profile genuinely differs (AI workers, WebSocket server, ingestion). Extract further services later, guided by real bottlenecks — not speculation.

---

## 3. High-level architecture (containers)

```
                                  ┌─────────────────────────────────────────────┐
   Flutter Member App (P1) ─┐     │                  EDGE                          │
   Flutter Trainer App (P2) ─┼──► │  CDN · WAF/Firewall · API Gateway/LB · TLS     │
   Flutter Staff App (P3) ──┘     └───────────────────────┬───────────────────────┘
   TALL Web Dashboards ───────────────────────────────────┤
   Filament Super-Admin ──────────────────────────────────┤
                                                           ▼
   ┌───────────────────────────────────────────────────────────────────────────────┐
   │                       LARAVEL MODULAR MONOLITH (stateless, horizontally scaled)  │
   │  Identity │ Graph │ Training │ Nutrition │ Coaching │ Gym │ Billing │ Marketplace │
   │  AI-Orchestration │ Notifications │ Analytics │ Admin  (bounded modules, §5)      │
   └───┬───────────────┬───────────────┬───────────────┬───────────────┬─────────────┘
       │               │               │               │               │
       ▼               ▼               ▼               ▼               ▼
 ┌──────────┐   ┌────────────┐   ┌──────────┐   ┌────────────┐   ┌──────────────┐
 │ MySQL 8  │   │   Redis    │   │  Search  │   │  S3 media  │   │ Queue Workers │
 │ central  │   │ cache/queue│   │(Meili/OS)│   │ (encrypted)│   │  (Horizon)    │
 │ + tenant │   │ /rt/locks  │   └──────────┘   └────────────┘   └──────┬───────┘
 │ DBs (§4) │   └─────┬──────┘                                          │
 └────┬─────┘         │                                                 ▼
      │ read replicas │         ┌─────────────────────┐        ┌─────────────────┐
      ▼               ▼         │  Reverb WS server   │        │  AI Brain layer │
 [replicas/OLAP]  [pub/sub] ───►│ (chat, live, occ.)  │        │  LLM gateway →  │
                                └─────────────────────┘        │  Claude/others  │
                                                               │  + RAG + safety │
   External: PSPs (Stripe Connect + Paymob/HyperPay/Tap) ·     │  + cost meter   │
   FCM/APNs · Apple Health/Health Connect · Wearables APIs ·   └─────────────────┘
   Access-control hardware (QR/NFC/gates) · Email/SMS (SES/Twilio)
```

**Separate processes from day one** (not microservices, just right-sized runtimes): API workers, **queue workers** (Horizon), **WebSocket server** (Reverb), and **AI worker pool** (long-running, isolated for cost/latency/failure containment). Everything else lives in the monolith.

---

## 4. Multi-tenancy strategy ⭐ (the highest-stakes decision)

This is argued, not asserted, because it cascades into DB, API, security, and cost.

### 4.1 The core tension
Standard SaaS multi-tenancy assumes **the tenant owns the data**. Our model inverts this (A3): **the Person owns their fitness data; tenants get scoped access.** A single Person is simultaneously a B2C user, a coach's Client, and a gym Member. A naive "every row has `tenant_id`, isolate by tenant" model **cannot represent a Person who belongs to many tenants and exists with no tenant at all** (pure B2C).

### 4.2 Resolution — Two planes, not one
We split the system into two data planes:

**Plane A — Central / Platform (the Person + the Graph), NOT tenant-isolated:**
- `Person`, auth, the user-owned **Graph** (Goals, Programs, Sessions/SetLogs, FoodLogs, Biometrics, WearableStreams, AdherenceEvents, Outcomes).
- Global shared assets: Exercise library, Food database, AI models/prompts.
- SaaS Plans, AICredits, Marketplace, platform analytics, super-admin.
- *Why central:* the Person and their data must persist across and outlive any tenant. This plane is the moat (MASTER §9).

**Plane B — Tenant-scoped (operational business data), isolated by tenant:**
- **Coach tenant:** client roster, Templates, CheckIn forms, coach payments, branding.
- **Gym tenant:** Branches, MembershipPlans, Subscriptions, AccessEvents, ClassSessions, Bookings, Staff, payroll, gym finance, sales CRM.
- *Why tenant-scoped:* this is the tenant's business; it never spans tenants and may carry residency/compliance requirements.

**The bridge — `Membership` + `Consent Scope`:** a Person joins a tenant via a `Membership` row (in the tenant plane) that references the central `Person`. The Person grants a **revocable Consent Scope** controlling which classes of their central Graph the tenant may read. *This is the only legal join between the two planes, and it is authorization-enforced, not just a foreign key.*

### 4.3 Physical isolation strategy for Plane B — Hybrid (pooled + dedicated)

| Option | Isolation | Cost/ops | Verdict |
|---|---|---|---|
| **Single shared DB, `tenant_id` row-scoping** | Logical (global scope) | Cheapest, simplest, easy cross-tenant analytics | Default for the **long tail** (coaches, SMB gyms) |
| **Schema/DB per tenant** | Strong physical | Higher ops, migrations × N, costly at 100k tenants | Reserve for **enterprise** (big chains, residency) |
| **DB per tenant for everyone** | Strongest | Untenable at 100k tenants | ❌ Rejected |
| **Shared everything, no scoping** | None | — | ❌ Rejected (leakage risk) |

**Decision: Hybrid.** Plane B uses a **shared database with mandatory, framework-enforced `tenant_id` scoping** for the pooled majority, with the ability to **provision a dedicated, region-pinned database for enterprise tenants** that need isolation, performance guarantees, or data residency (e.g., a Gulf-region gym chain). Candidate package: `stancl/tenancy` (supports both single-DB and multi-DB on Laravel) — to be validated in a spike. Tenant resolution by subdomain/custom-domain (coach white-label, FR-CCH-006) or by authenticated tenant context.

### 4.4 Isolation enforcement (defense in depth — leakage is a 🔴 critical risk)
1. **Mandatory global scope:** every Plane-B model is `tenant_id`-scoped via a base model + global query scope; raw queries are forbidden by lint/review.
2. **Resolved tenant context per request** (middleware) — no implicit "current tenant" from user-supplied IDs.
3. **Consent enforcement layer** gates every central-Graph read initiated by a tenant actor.
4. **Automated tests** that assert cross-tenant access returns 404/403 (NFR-SEC-002) — part of CI.
5. **Dedicated DB option** removes the shared-row risk entirely for enterprise.

### 4.5 Why this is right for our sequencing
In **P1 (B2C)** there are *no tenants* — only Persons and the central Graph. Multi-tenancy infrastructure is **built dormant** (the abstractions exist; no tenant rows yet), so P2 turns it on without re-architecture. This is exactly why B2C-first is also architecturally cheaper.

> Database-level detail (tables, keys, partitioning) is specified in `DATABASE_DESIGN.md`, which inherits these two planes verbatim.

---

## 5. Module decomposition (bounded contexts in a modular monolith)

Each module owns its tables, exposes a service interface, and communicates with others via **events** (in-process now, queue-backed; extractable to services later). Modules map directly to requirement domains in `PRODUCT_REQUIREMENTS.md §3`.

| Module | Plane | Phase | Responsibility |
|---|---|---|---|
| **Identity & Consent** | A | P1 | Person, auth, profiles, consent scopes, portability/GDPR |
| **Graph** | A | P1 | Goals, Sessions/SetLogs, FoodLogs, Biometrics, Wearables, Adherence, Outcomes |
| **Training** | A | P1 | Exercise library, Programs, Workouts, timers, PRs |
| **Nutrition** | A | P1 | Food DB, FoodLogs, barcode, recipes, water, supplements |
| **AI Orchestration (Brain)** | A | P1 | Plan/meal generation, recommendations, analytics, safety gate, RAG, cost metering |
| **Engagement** | A | P1 | Goals, habits, XP/levels/badges/streaks, challenges, notifications-triggers |
| **Billing & Plans (SaaS)** | A | P1 | Plans, AICredits, B2C subscriptions, coupons, trials |
| **Notifications** | A | P1 | Push (FCM/APNs), email, SMS fan-out |
| **Coaching** | B | P2 | Clients, templates, assignments, check-ins, messaging, coach branding |
| **Marketplace** | A/B | P2 | Coach discovery/matching, take-rate, template sales |
| **Payments & Ledger** | B | P2 | PSP integration, invoices, refunds, double-entry ledger, reconciliation |
| **Gym Ops** | B | P3 | Membership lifecycle, access control, classes/bookings, staff, multi-branch, CRM |
| **Analytics & Reporting** | A/B | P1→ | Churn, engagement scores, dashboards (read-model) |
| **Admin (Super-Admin)** | A | P1 | Tenant/user/sub mgmt, AI-usage & abuse, revenue, system health, support |

**Inter-module rule:** modules call each other only through published interfaces/events — never reach into another module's tables. This keeps future service extraction cheap and prevents the monolith from becoming a big ball of mud.

---

## 6. AI Brain architecture (core + cost-critical)

```
 Request (sync or queued) ─► AI Orchestrator
   │  1. Build context: RAG over the Person's Graph (goals, history, constraints, injuries)
   │  2. Policy/Safety pre-check: PAR-Q+ status, contraindications (FR-AI-007)
   │  3. Model routing: pick tier by task (cheap model for swaps; strong model for full plan)
   │  4. LLM Gateway ──► Claude (primary) ──fallback──► alt provider
   │  5. Structured output (schema-validated) — no free-form prescriptions
   │  6. Safety post-eval gate (NFR-AI-002): reject contraindicated output, regenerate
   │  7. Persist + attach reasoning + confidence + audit (FR-AI-010)
   │  8. Meter AICredits (FR-SAS-004); enforce limits
   ▼
 Result (streamed to client where latency matters)
```

**Design decisions:**
- **Provider-agnostic gateway** behind a `LlmGateway` interface → Claude-primary with automatic fallback and per-task model tiering. (Multi-provider avoids lock-in and enables cost/latency routing.)
- **RAG over the Graph, not the open web** — grounding in the Person's own data is the differentiator and reduces hallucination (NFR-AI-003). Cite which data points were used.
- **Cost controls (NFR-AI-001):** task-tiered models (small for exercise swaps/daily tips, large for full program/meal generation), aggressive caching of near-identical generations, batching of analytics/report jobs, and **hard AICredit metering** with graceful degradation.
- **Safety is a gate, not a suggestion:** generation is sandwiched between a pre-check (eligibility) and a post-eval (contraindication scan) before anything reaches a user. Coach→client outputs additionally require **human approval** (A5, FR-AI-009).
- **Async by default:** heavy generation and all analytics run on the **AI worker pool** via queues; the app never blocks and **functions if the Brain is down** (NFR-REL-004).
- **Graduated autonomy:** every AI action stores confidence + outcome; acceptance/outcome data later promotes features from *suggest* → *auto-with-review* → *auto*.

---

## 7. Offline-first mobile sync architecture

```
 Flutter (Clean Arch: presentation → domain (use-cases) → data (repos))
   Local store (Drift/SQLite) = source of truth for the UI
        │  optimistic write → UI updates instantly (NFR-PERF-003)
        ▼
   Outbox (pending mutations, each with client-generated ULID + op type)
        │  background sync when online
        ▼
   API: idempotent endpoints (dedupe by client ULID)
        │  conflict resolution:
        │   • logs (Sessions, FoodLogs) are append-only & immutable → no conflicts
        │   • mutable records → last-write-wins per field + server authority
        │   • server returns canonical state; client reconciles
        ▼
   Server persists → emits events → pushes deltas back (pull + WS nudge)
```

- **Append-only logs eliminate most conflicts** — a logged set or meal is a fact, never edited in place (corrections are new events). This is both a sync simplification and a clean audit/graph property.
- **Client-generated ULIDs** make every write idempotent and retry-safe.
- **Sync is per-domain and resumable**; large media (photos) uploads are deferred/chunked.

---

## 8. Data stores & scaling strategy

| Concern | Approach | Scale path |
|---|---|---|
| Transactional | MySQL 8 (central + tenant DBs) | Read replicas → partition high-write tables → shard by Person/tenant if needed |
| High-write tables (SetLogs, FoodLogs, AccessEvents, WearableStreams) | Time/tenant **partitioning** from the start | Move wearable time-series to a TSDB if volume demands |
| Cache | Redis (query cache, computed scores, sessions) | Cluster mode |
| Search | Meilisearch (RTL/typo tolerant) | OpenSearch cluster at scale |
| Media | S3-compatible, region-pinned, signed URLs, encrypted | CDN in front |
| Analytics/reporting | Read replicas + pre-aggregated read-models (NFR-SCAL-003) | Dedicated OLAP/warehouse (e.g., ClickHouse) in P3+ |
| Real-time | Reverb + Redis pub/sub | Horizontal WS nodes behind sticky LB |

**Scaling principles:** stateless API (scale horizontally), everything slow is queued, reads served from replicas/read-models, caching at every layer, partition before you shard, shard only when a single primary is the proven bottleneck. Designed so 10M Persons / 100k tenants needs *more instances*, not a *rewrite* (NFR-SCAL-002).

---

## 9. Payments architecture

- **PSPs:** Stripe Connect (global, marketplace payouts) + **regional MENA rails (Paymob / HyperPay / Tap)** for local cards and methods (A2). Provider-agnostic `PaymentGateway` interface.
- **PCI scope minimization (NFR-SEC-004):** card data never touches our servers — tokenized via PSP SDK/Elements; we store tokens only.
- **Double-entry Ledger (FR-FIN-003):** every money movement (charge, refund, payout, take-rate, commission) is a balanced ledger entry. The ledger — not the PSP — is our source of truth, reconciled against PSP webhooks.
- **Webhook-driven, idempotent reconciliation;** all financial mutations audited (NFR-SEC-006).
- Marketplace take-rate and coach/staff commissions computed on the ledger.

---

## 10. Security architecture (defense in depth)

| Layer | Control |
|---|---|
| Edge | WAF/Firewall, DDoS mitigation, TLS everywhere, rate limiting (NFR-SEC-007) |
| AuthN | Laravel Sanctum (mobile tokens) + social OAuth (Apple/Google); short-lived tokens + refresh |
| AuthZ | **RBAC (roles/permissions) × tenant scope × consent scope** — three-layer check (see ROLES_PERMISSIONS.md) |
| Tenant isolation | Global scopes + resolved context + dedicated-DB option + CI leakage tests (§4.4) |
| Data | Encryption at rest + in transit; **progress photos & health data encrypted with extra protection**; field-level encryption for sensitive PII |
| Payments | PCI scope minimization, tokenization |
| Secrets | Vault/KMS, no secrets in code, rotated |
| Audit | Immutable audit log for money, health data, AI actions, admin actions (NFR-SEC-006) |
| Privacy | Consent framework, data export/delete, **region-pinned residency** (A2), GDPR-aligned (NFR-SEC-005) |
| AI abuse | Per-Person/per-tenant rate limits + anomaly detection on AI endpoints |

---

## 11. Infrastructure & DevOps

- **Containerization:** Docker for all services; reproducible local dev (docker-compose) ↔ prod parity.
- **Orchestration:** start with a managed container platform; **Kubernetes when scale/multi-region justifies it** (don't pay k8s complexity tax on day one). Region-pinned clusters for residency.
- **Environments:** local → CI → staging → production; ephemeral preview envs for PRs.
- **CI/CD:** automated tests (unit/feature/tenant-isolation/contract), build, staged rollout (canary/rolling), fast rollback (NFR-OPS-003).
- **IaC:** infrastructure as code (Terraform) — reproducible, reviewable.
- **Observability:** centralized logs, metrics, distributed tracing (OpenTelemetry), error tracking (Sentry), uptime/SLO dashboards. **Per-tenant & per-AI-feature cost/usage dashboards** (NFR-OPS-002) — critical for the AI-margin guardrail.
- **Backups/DR:** automated encrypted backups, **tested restores**, RPO ≤15min / RTO ≤1h (NFR-REL-003); cross-AZ; multi-region path for residency + DR.
- **Queues:** Horizon dashboards, autoscaling worker pools (separate queues for AI vs. notifications vs. sync so one can't starve another).

---

## 12. Key Architecture Decision Records (ADR summary)

| ADR | Decision | Rationale | Status |
|---|---|---|---|
| ADR-001 | **Modular monolith**, not microservices | Small team, transactional integrity, speed; extract later by evidence | Accepted |
| ADR-002 | **Two-plane model** (central Person/Graph + tenant-scoped ops) | Resolves user-owned-identity vs. tenant-isolation tension (§4) | Accepted |
| ADR-003 | **Hybrid tenancy** (pooled shared-DB + dedicated for enterprise) | Cost at 100k tenants + residency/isolation for enterprise | Accepted |
| ADR-004 | **Provider-agnostic LLM gateway, Claude-primary + fallback** | Avoid lock-in, enable cost/latency routing & resilience | Accepted |
| ADR-005 | **Offline-first with append-only logs + ULID idempotency** | Gym dead zones; conflict-free sync; clean audit/graph | Accepted |
| ADR-006 | **Double-entry ledger as money source-of-truth** | Payments are a revenue line, must be auditable & reconciled | Accepted |
| ADR-007 | **Multi-tenancy built dormant in P1** | B2C has no tenants; turn on in P2 with no re-architecture | Accepted |
| ADR-008 | **Region-pinned, residency-aware deployment** | MENA-first + enterprise compliance (A2) | Accepted |
| ADR-009 | **ULIDs as primary identifiers** | Sortable, non-enumerable, sync/idempotency friendly | Accepted |

---

## 13. Risks & open items carried forward

| Item | Owner doc | Note |
|---|---|---|
| Exact tenant package & spike (stancl/tenancy validation) | DATABASE_DESIGN / spike | Validate single+multi DB on Laravel 11 |
| Food DB & exercise media licensing (localized, Arabic) | Product/Legal | MASTER §12 build-vs-buy |
| TSDB adoption threshold for wearables | Architecture | Revisit when write volume demands |
| OLAP/warehouse introduction | Architecture | P3 analytics depth |
| Concrete PSP selection per MENA country | Payments | Paymob/HyperPay/Tap coverage matrix |
| AI safety eval implementation | AI/Clinical | Contraindication ruleset source |

---

> **Next document:** `DATABASE_DESIGN.md` — entities, relationships, the central vs. tenant DB split (inheriting the two-plane model in §4), key/partitioning strategy, and an ERD. Will register every new entity in `GLOSSARY.md` first.
