# ROLES_PERMISSIONS.md
### Fitness OS — Authorization Model, Roles & Permission Matrix

> **Status:** Draft v1.0 · **Owner:** Security / Backend · **Last updated:** 2026-06-12
> **Document 5 of 10.** Implements the three-layer authorization from `SYSTEM_ARCHITECTURE.md §10`. References entities in `DATABASE_DESIGN.md` and requirements in `PRODUCT_REQUIREMENTS.md §3`.

---

## 1. The authorization model — three layers, all must pass

A request is authorized **only if all three layers grant it**. This is the single most important security concept in the platform.

```
   ┌──────────────────────────────────────────────────────────────┐
   │ LAYER 1 — RBAC: does the actor's ROLE include this PERMISSION? │
   └──────────────────────────────┬───────────────────────────────┘
                                   ▼ pass
   ┌──────────────────────────────────────────────────────────────┐
   │ LAYER 2 — TENANT SCOPE: is the target row in the actor's       │
   │           resolved tenant? (Plane B only)                      │
   └──────────────────────────────┬───────────────────────────────┘
                                   ▼ pass
   ┌──────────────────────────────────────────────────────────────┐
   │ LAYER 3 — CONSENT SCOPE: if a TENANT actor is reading a        │
   │           PERSON's central Graph, is there an active           │
   │           consent_scope for that data class? (Plane A bridge)  │
   └──────────────────────────────┬───────────────────────────────┘
                                   ▼ all pass → ALLOW
```

- **Layer 1 (RBAC)** — capability check. Implemented with a roles/permissions system (e.g., `spatie/laravel-permission`), permissions are **per-tenant-context** (a coach has roles only within their own tenant).
- **Layer 2 (Tenant scope)** — data isolation. The global scope from ARCH §4.4 guarantees Plane-B queries never cross tenants. Tenant is **resolved from the authenticated context**, never from a user-supplied ID. (INV-001)
- **Layer 3 (Consent scope)** — the unique-to-us layer. Because Persons own their data (A3), a coach/gym reading a member's training or biometric history must hold an active `consent_scope` for that `data_class`. Revocation is immediate. (INV-004)

> ⚠️ **Why three layers and not the usual one:** ordinary SaaS only needs RBAC + tenant scope. Our **user-owned-data** model adds the consent layer — a coach with full RBAC permissions *still* can't see a client's body-fat history unless the client granted the `biometrics` scope. This is a feature (trust/marketing) and a compliance posture (GDPR), not overhead.

---

## 2. Actor types & scope of roles

| Actor type | Lives in | Roles scoped to |
|---|---|---|
| **Person (end-user)** | Central plane | Themselves — owns their Graph; not a "role" but an **ownership** principle |
| **Coach** | Their own Tenant (individual_coach) | That tenant |
| **Gym staff** (owner/manager/front-desk/trainer) | A Gym/Fitness-Company Tenant | That tenant (+ branch sub-scope) |
| **Super Admin / Platform staff** | Platform (no tenant) | Cross-platform (audited, least-privilege) |

A single human (Person) may hold **multiple roles across multiple tenants simultaneously** (e.g., a B2C user who is also a coach and a member of a gym). Roles are evaluated **in the resolved tenant context of each request.**

---

## 3. Role catalogue

### 3.1 Self / B2C (P1)
**`person`** — not a tenant role; an **owner**. Over their own Graph they have full CRUD (subject to append-only invariants), can grant/revoke consent, export, and delete. They cannot, by default, see anyone else's data.

### 3.2 Coaching tenant (P2)
| Role | Description |
|---|---|
| `coach.owner` | The coach; full control of their tenant, billing, branding, clients, templates, payments |
| `coach.assistant` | Delegated helper (e.g., junior coach/VA); manage clients & programs, no billing/payout access |

### 3.3 Gym / Fitness-Company tenant (P3)
| Role | Description |
|---|---|
| `gym.owner` | Full control across all branches; billing, finance, staff, settings |
| `gym.org_admin` | Org-level admin (multi-branch ops, reports) without billing-owner powers |
| `branch.manager` | Full control of **one branch** (Layer-2 sub-scope = branch); staff, classes, sales, daily finance |
| `staff.frontdesk` | Check-ins, sell memberships, take payments (POS-lite), freezes — no analytics/finance reports |
| `staff.trainer` | In-gym trainer: assigned clients, own schedule, own commissions, programming |
| `staff.accountant` | Finance/reports/refunds; no member-personal-data beyond billing |

### 3.4 Platform (P1)
| Role | Description |
|---|---|
| `platform.superadmin` | Break-glass full access (heavily audited; ideally never used routinely) |
| `platform.support` | Support tooling, **time-boxed, consented impersonation** (audited) |
| `platform.finance` | Platform revenue, payouts, take-rate reconciliation |
| `platform.ai_ops` | AI usage/cost/abuse monitoring, model config — **no access to personal Graph content** |
| `platform.readonly` | Observability/analytics dashboards, no mutations |

---

## 4. Permission matrix (representative — not exhaustive)

Legend: ✅ allowed · 🔶 allowed **with consent scope** (Layer 3) · 🏢 branch-limited · ❌ denied · ⬜ N/A

### 4.1 Self & coaching
| Capability (→ FR) | person (self) | coach.owner | coach.assistant | platform.support |
|---|---|---|---|---|
| View own Graph (FR-TRN/NUT/BIO) | ✅ | ⬜ | ⬜ | 🔶 (impersonation, audited) |
| Generate AI plan (FR-AI-001/002) | ✅ | ✅ (for client) | ✅ | ❌ |
| Grant/revoke consent (FR-IDN-003) | ✅ | ❌ | ❌ | ❌ |
| Export/delete own data (FR-IDN-004) | ✅ | ❌ | ❌ | ❌ |
| View client's training history | ⬜ | 🔶 | 🔶 | ❌ |
| View client's biometrics/photos | ⬜ | 🔶 (separate scope) | 🔶 | ❌ |
| Assign program/template (FR-CCH-004) | ⬜ | ✅ | ✅ | ❌ |
| AI-draft → **send** check-in (FR-AI-009) | ⬜ | ✅ (human approve) | ✅ (human approve) | ❌ |
| Coach billing/payouts (FR-FIN) | ⬜ | ✅ | ❌ | ❌ |
| Edit branding/white-label (FR-CCH-006) | ⬜ | ✅ | ❌ | ❌ |

### 4.2 Gym
| Capability (→ FR) | gym.owner | branch.manager | staff.frontdesk | staff.trainer | staff.accountant |
|---|---|---|---|---|---|
| Member check-in (FR-GYM-004/005) | ✅ | 🏢 | 🏢 | ❌ | ❌ |
| Create/sell membership (FR-GYM-001) | ✅ | 🏢 | 🏢 | ❌ | ❌ |
| Freeze/upgrade/transfer (FR-GYM-002/020) | ✅ | 🏢 | 🏢 (freeze only) | ❌ | ❌ |
| Take payment / POS-lite (FR-FIN-005) | ✅ | 🏢 | 🏢 | ❌ | ✅ |
| Refunds (FR-FIN-002) | ✅ | 🏢 | ❌ | ❌ | ✅ |
| Manage classes/bookings (FR-GYM-010/011) | ✅ | 🏢 | 🏢 (book only) | 🏢 (own) | ❌ |
| Manage staff/payroll (FR-GYM-013/014) | ✅ | 🏢 (schedule) | ❌ | ❌ | ✅ (payroll) |
| View member Graph (with consent) | 🔶 | 🔶🏢 | ❌ | 🔶 (assigned only) | ❌ |
| Cross-branch reports (FR-GYM-021) | ✅ | ❌ | ❌ | ❌ | ✅ (finance) |
| Broadcast comms (FR-GYM-022) | ✅ | 🏢 | ❌ | ❌ | ❌ |
| Sales CRM (FR-GYM-023) | ✅ | 🏢 | 🏢 | ❌ | ❌ |

### 4.3 Platform
| Capability | superadmin | support | finance | ai_ops | readonly |
|---|---|---|---|---|---|
| Manage tenants/subs (FR-SAS-005) | ✅ | 🔶 (limited) | ❌ | ❌ | 👁 |
| Impersonate (time-boxed, consented, audited) | ✅ | ✅ | ❌ | ❌ | ❌ |
| View AI usage/cost/abuse (NFR-OPS-002) | ✅ | 👁 | 👁 | ✅ | 👁 |
| View personal Graph content | ❌* | 🔶 (impersonation only) | ❌ | ❌ | ❌ |
| Platform revenue/payouts | ✅ | ❌ | ✅ | ❌ | 👁 |
| Change AI models/prompts | ✅ | ❌ | ❌ | ✅ | ❌ |

\* Even superadmin does not get casual access to personal Graph content; access is via audited impersonation only. (👁 = read-only/aggregate)

---

## 5. Special authorization rules

1. **Person data sovereignty overrides RBAC.** No tenant role can read a Person's central Graph data class without an active consent scope — full stop (INV-004). Consent revocation takes effect on the next request.
2. **Impersonation is consented, time-boxed, and audited.** Support never reads raw data without an explicit, logged impersonation session bounded in time; the Person is notified per policy.
3. **Branch sub-scoping (🏢).** Gym roles below `org_admin` are further constrained to their assigned branch(es) — a second tenant-scope dimension.
4. **AI actions are attributable.** Every AI-generated artifact records the initiating actor and is gated; coach→client outputs require human approval before delivery (A5).
5. **Financial actions are dual-controlled where material** (e.g., large refunds/payouts may require `owner`/`accountant` co-approval) and always audited (NFR-SEC-006).
6. **Least privilege & default-deny.** Unlisted capability = denied. New permissions are added explicitly.
7. **Self-service revocation cascades.** Leaving a gym/coach revokes that tenant's consent scopes automatically; the Person keeps all their data (A3).

---

## 6. Implementation notes (for later code, not now)

- RBAC via a permission package; **permissions registered per module**, roles assembled from permissions.
- Tenant scope enforced by the global query scope + resolved-context middleware (ARCH §4.4); **never trust client-supplied tenant/branch IDs**.
- Consent layer is a dedicated gate/policy invoked on every cross-plane (tenant→Person Graph) read.
- Laravel **Policies** map 1:1 to entities; **Gates** for cross-cutting (consent, impersonation).
- CI includes **authorization tests**: cross-tenant denial, consent enforcement, branch sub-scope, and least-privilege regression.

---

> **Next document:** `API_SPECIFICATION.md` — REST resources, conventions, auth, versioning, error model, real-time channels, and endpoint catalogue, all carrying these three authorization layers.
