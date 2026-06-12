# API_SPECIFICATION.md
### Fitness OS — API Contract & Conventions

> **Status:** Draft v1.0 · **Owner:** Backend / API · **Last updated:** 2026-06-12
> **Document 6 of 10.** REST + WebSockets per the brief. Every endpoint enforces the three-layer authorization (`ROLES_PERMISSIONS.md`), operates on entities from `DATABASE_DESIGN.md`, and traces to requirements in `PRODUCT_REQUIREMENTS.md`. This is the contract; controllers come later (not now).

---

## 1. Conventions

| Topic | Rule |
|---|---|
| **Base / versioning** | `https://api.fitnessos.com/v1/…`. URI-versioned major; additive changes are non-breaking; breaking → `v2`. |
| **Format** | JSON only; `application/json`; snake_case fields; ULIDs as IDs. |
| **Auth** | `Authorization: Bearer <token>` (Laravel Sanctum for mobile/SPA; OAuth social for sign-in). Short-lived access + refresh. |
| **Tenant context** | Resolved from token (and subdomain/custom-domain for white-label). **Never** taken from request body (ARCH §4.4). Optional `X-Tenant` only validated against the actor's memberships. |
| **Locale / units** | `Accept-Language` (ar/en, RTL handled client-side); `X-Unit-System: metric\|imperial`. |
| **Pagination** | Cursor-based: `?cursor=&limit=` → `{ data, meta:{ next_cursor } }`. (Offset only for small admin lists.) |
| **Filtering/sort** | `?filter[field]=…&sort=-created_at`; whitelisted per resource. |
| **Idempotency** | **Mutations accept `Idempotency-Key` (client ULID)**; replays return the original result. Mandatory for offline sync & payments (ARCH §7). |
| **Rate limiting** | Per-actor + per-IP; **stricter buckets on AI endpoints** (NFR-SEC-007). Returns `429` + `Retry-After`. |
| **Concurrency** | `ETag`/`If-Match` on mutable resources; `409` on conflict. |
| **Time** | ISO-8601 UTC; client sends `*_at` with offset where relevant. |
| **Field selection** | `?fields=` sparse fieldsets; `?include=` for relationships (bounded). |

### 1.1 Standard error model (RFC-9457 problem+json)
```json
{
  "type": "https://api.fitnessos.com/errors/validation",
  "title": "Validation failed",
  "status": 422,
  "code": "VALIDATION_ERROR",
  "detail": "The email field is required.",
  "errors": { "email": ["required"] },
  "request_id": "01J..."
}
```
| Status | Meaning |
|---|---|
| 400/422 | Bad request / validation |
| 401 / 403 | Unauthenticated / authorized-layer-failed (RBAC, tenant, or **consent** — body says which) |
| 404 | Not found **or cross-tenant hidden** (we 404 rather than 403 to avoid leaking existence) |
| 409 | Conflict (ETag / idempotency mismatch) |
| 402 / 429 | Payment/credits required · rate-limited (incl. **AICredits exhausted**) |
| 5xx | Server; always carries `request_id` |

### 1.2 Response envelope
```json
{ "data": { … }, "meta": { … }, "links": { … } }
```

---

## 2. Authentication & account

| Method | Path | Purpose | Phase |
|---|---|---|---|
| POST | `/v1/auth/register` | Create Person + account (FR-IDN-001) | P1 |
| POST | `/v1/auth/login` | Email/password | P1 |
| POST | `/v1/auth/social` | Apple/Google OAuth exchange | P1 |
| POST | `/v1/auth/refresh` · `/logout` | Token lifecycle | P1 |
| GET/PATCH | `/v1/me` | Person profile (locale, units, tz) | P1 |
| GET | `/v1/health-screen/questions` | PAR-Q+ questionnaire (7 questions) to render | P1 |
| POST | `/v1/me/health-screen` | Submit PAR-Q+ → passed/flagged; sets the AI-plan gate (FR-AI-007) | P1 |
| GET | `/v1/me/health-screen` | Current screen status + latest screening | P1 |
| GET | `/v1/me/export` | Full data export (FR-IDN-004) | P1 |
| DELETE | `/v1/me` | Account deletion (cascades + revokes consent) | P1 |
| GET/POST/DELETE | `/v1/me/consents` | List/grant/revoke consent scopes (FR-IDN-003) | P2 |

---

## 3. The Graph — training, nutrition, body (P1, central plane)

**Training**
| Method | Path | Notes |
|---|---|---|
| GET | `/v1/exercises` | Library; search/filter (Meili-backed), localized |
| GET | `/v1/programs` · `/v1/programs/{id}` | Person's programs |
| POST | `/v1/sessions` | Start a session (idempotent) |
| POST | `/v1/sessions/{id}/sets` | **Append SetLog** (offline-synced, idempotent, immutable) |
| POST | `/v1/sessions/{id}/finish` | Close session → triggers PR/outcome jobs |
| GET | `/v1/me/records` | Personal records (read-model) |
| GET | `/v1/me/history` | Session history (cursor) |

**Nutrition**
| Method | Path | Notes |
|---|---|---|
| GET | `/v1/foods?q=` · `/v1/foods/barcode/{code}` | Search / barcode lookup (FR-NUT-001/003) |
| POST | `/v1/food-logs` | Append FoodLog (idempotent); `source=search\|barcode\|image\|voice` |
| POST | `/v1/food-logs/recognize` | AI image recognition (FR-NUT-004) → candidate items |
| POST | `/v1/food-logs/voice` | Voice log (FR-NUT-005) |
| POST | `/v1/water-logs` · `/v1/supplement-logs` | Intake events |
| GET | `/v1/recipes` · POST `/v1/recipes` | Recipes/custom foods |
| GET | `/v1/meal-plans` · `/v1/meal-plans/{id}` | Person's meal plans (backs `meal_plans`; generated via `/v1/ai/meal-plan`) |
| GET | `/v1/me/nutrition/summary?date=` | Daily macro/calorie rollup |

**Body & wearables**
| Method | Path | Notes |
|---|---|---|
| POST/GET | `/v1/biometrics` | Weight/bodyfat/measurements (FR-BIO-001) |
| POST | `/v1/progress-photos` | Signed-URL upload; encrypted (FR-BIO-002) |
| POST | `/v1/wearables/connect` · `/v1/wearables/ingest` | Apple Health / Health Connect (FR-BIO-003) |

---

## 4. AI Brain (P1) — streaming + metered

| Method | Path | Notes |
|---|---|---|
| POST | `/v1/ai/program` | Generate Program (FR-AI-001). **Streams** partial output (SSE); passes safety gate; debits AICredits |
| POST | `/v1/ai/meal-plan` | Generate MealPlan (FR-AI-002) |
| POST | `/v1/ai/exercise-alternatives` | Swaps under equipment/injury constraints (FR-AI-003); cheap-tier model |
| POST | `/v1/ai/coach/chat` | Conversational coach, grounded in Graph (FR-AI-008); streamed |
| GET | `/v1/ai/recommendations/today` | Daily recommendation/motivation (FR-AI-004) |
| GET | `/v1/ai/recovery` | Recovery advice from wearable+soreness (FR-AI-005) |
| POST | `/v1/ai/plan-adjustment` | Proposes progression/deload; **returns a proposal the user approves** (FR-AI-006) |
| GET | `/v1/me/ai-credits` | Wallet balance (FR-SAS-004) |

**AI response contract:** every AI response includes `confidence`, `safety_verdict`, `grounding` (which Graph data was used), and `interaction_id` (→ `ai_interactions`). `402` when credits exhausted. Heavy generations may return `202 + job_id` and stream/poll.

---

## 5. Analytics & engagement (P1)

| Method | Path | Notes |
|---|---|---|
| GET | `/v1/me/progress` | Trend analysis + goal projection (FR-AN-001) |
| GET | `/v1/me/adherence` | Adherence analytics (FR-AN-002) |
| GET | `/v1/me/reports/weekly` | Weekly AI report (FR-AN-005) |
| GET/POST | `/v1/goals` · `/v1/habits` · `/v1/habit-logs` | Engagement (FR-ENG-001/002) |
| GET | `/v1/me/gamification` | XP/level/badges/streaks (FR-ENG-003) |
| GET/POST | `/v1/challenges` | Challenges (FR-ENG-004) |

---

## 6. SaaS billing (P1)

| Method | Path | Notes |
|---|---|---|
| GET | `/v1/plans?audience=b2c` | Available plans (FR-SAS-002) |
| POST | `/v1/subscriptions` | Subscribe (PSP); trials/coupons (FR-SAS-003) |
| PATCH | `/v1/subscriptions/{id}` | Upgrade/downgrade (prorate) |
| POST | `/v1/ai-credits/purchase` | Top-up credits |
| POST | `/v1/webhooks/psp/{provider}` | **PSP webhooks** (signed, idempotent, → ledger reconciliation) |

---

## 7. Coaching (P2, tenant plane)

| Method | Path | Notes |
|---|---|---|
| GET/POST | `/v1/coach/clients` | Roster; invite (FR-CCH-001) |
| GET | `/v1/coach/clients/{id}` | Client profile — **Graph data gated by consent scope** (Layer 3) |
| GET/POST | `/v1/coach/templates` | Program/MealPlan templates (FR-CCH-004) |
| POST | `/v1/coach/assignments` | Assign template/program to client |
| GET/POST | `/v1/coach/checkin-forms` · `/v1/coach/checkins` | Forms + submissions (FR-CCH-002) |
| POST | `/v1/coach/checkins/{id}/ai-draft` | **AI drafts** summary (FR-AI-009) → coach edits → `POST …/send` (human approval) |
| GET | `/v1/coach/clients/{id}/churn-risk` | ChurnRisk + playbook (FR-AN-004/006) |
| CRUD | `/v1/coach/branding` | White-label (FR-CCH-006) |
| CRUD | `/v1/coach/billing/*` | Packages, payments, payouts (FR-CCH-007, FR-FIN) |
| GET/POST | `/v1/coach/leads` · `/v1/coach/funnels` | Mini-CRM (FR-CCH-008) |
| GET | `/v1/coach/analytics/revenue` | Revenue analytics (FR-CCH-010) |

---

## 8. Marketplace (P2)

| Method | Path | Notes |
|---|---|---|
| GET | `/v1/marketplace/coaches?goal=&lang=&budget=` | Discovery/matching (FR-MKT-001) |
| POST | `/v1/marketplace/coaches/{id}/trial` | Start trial → conversion (FR-MKT-002) |
| GET/POST | `/v1/marketplace/templates` | Template marketplace (FR-MKT-003, P3) |

---

## 9. Gym Ops (P3, tenant plane; many branch-scoped)

**Members & access**
| Method | Path | Notes |
|---|---|---|
| CRUD | `/v1/gym/members` | Registration; bulk import (FR-GYM-001) |
| CRUD | `/v1/gym/membership-plans` | Plans (FR-GYM-001) |
| POST | `/v1/gym/subscriptions` · `…/{id}/freeze` · `/upgrade` · `/transfer` | Lifecycle (FR-GYM-002/020) |
| POST | `/v1/gym/access/check-in` | **QR/NFC/barcode/gate** check-in; `<3s` (NFR-PERF-004); append AccessEvent |
| GET | `/v1/gym/branches/{id}/occupancy` | Live occupancy (FR-GYM-005) |
| GET | `/v1/gym/attendance` | Attendance history/analytics |
| POST | `/v1/gym/waivers/{id}/sign` | E-signature (FR-GYM-006) |

**Classes, staff, ops, finance**
| Method | Path | Notes |
|---|---|---|
| CRUD | `/v1/gym/classes` · `/v1/gym/class-sessions` | Scheduling (FR-GYM-010) |
| POST | `/v1/gym/bookings` · `…/{id}/waitlist` · `/cancel` | Bookings, waitlist, no-show/late-fees (FR-GYM-011) |
| CRUD | `/v1/gym/resources` | Court/room reservations (FR-GYM-012) |
| CRUD | `/v1/gym/staff` · `/schedules` · `/attendance` | Staff (FR-GYM-013) |
| GET | `/v1/gym/staff/{id}/commissions` · `/v1/gym/payroll` | Commissions/payroll (FR-GYM-014) |
| GET | `/v1/gym/reports?scope=branch\|org` | Centralized reporting (FR-GYM-021) |
| POST | `/v1/gym/broadcasts` | Segmented push/email/SMS (FR-GYM-022) |
| CRUD | `/v1/gym/sales-pipeline` | CRM (FR-GYM-023) |
| POST | `/v1/gym/pos/sale` | POS-lite (FR-FIN-005) |
| CRUD | `/v1/gym/finance/*` | Invoices, refunds, discounts, ledger views (FR-FIN) |

---

## 10. Super-admin (P1) — exposed via Filament + admin API

`/v1/admin/tenants`, `/admin/subscriptions`, `/admin/ai-usage`, `/admin/abuse`, `/admin/revenue`, `/admin/support/impersonate` (time-boxed, consented, audited), `/admin/system/health`. All `platform.*` role-gated (ROLES §3.4) and audited (NFR-SEC-006).

---

## 11. Real-time (WebSockets via Reverb)

| Channel | Scope | Events |
|---|---|---|
| `private-person.{id}` | self | sync nudges, AI job done, notifications |
| `private-conversation.{id}` | coach↔client (consent+membership) | messages, typing, read (FR-CCH-003) |
| `private-tenant.{tenantId}.branch.{branchId}` | gym staff | live occupancy, check-ins, bookings |
| `private-coach.{tenantId}` | coach | client activity, churn alerts |

Channel authorization reuses the **three-layer** model. Live updates complement (never replace) the offline-first pull sync.

---

## 12. Offline sync contract (mobile)

- `POST /v1/sync/batch` — submit an **outbox** of mutations (each with client ULID + op); server applies idempotently, returns per-op result + canonical state.
- `GET /v1/sync/changes?since=cursor` — pull deltas for the Person's domains.
- Append-only logs never conflict; mutable records resolve last-write-wins per field with server authority (ARCH §7).

---

## 13. Cross-cutting guarantees

- **Every mutation** is authorized by all three layers, audited where sensitive, idempotent, and rate-limited.
- **Cross-tenant access returns 404** (existence hidden), never 403 (INV-001, NFR-SEC-002).
- **Consent failures return 403** with `code=CONSENT_REQUIRED` and the missing `data_class`.
- **AI endpoints** always meter credits and attach safety/grounding metadata.
- **Contract tests** in CI validate this spec against implementations as they're built.

---

> **Next document:** `UI_UX_SYSTEM.md` — the design system (color, type, components, motion, layout), dark-mode-first + RTL, and the surface inventory across member/trainer/staff apps, web dashboards, and admin.
