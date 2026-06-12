# GLOSSARY.md — Canonical Entities & Terms (Source of Truth)

> **Purpose:** This is a *working artifact*, not one of the 10 deliverables. It exists to prevent cross-document drift: every other document (requirements, architecture, database, API, roles) must use these exact names and definitions. When a later document needs a new entity or term, add it here first.
> **Status:** Living · **Last updated:** 2026-06-12 (seeded from MASTER_PRODUCT.md §9)

## Naming conventions
- **Entities:** singular, snake_case in DB (`meal_plan`), PascalCase in code (`MealPlan`), Title Case in prose ("Meal Plan").
- **IDs:** ULIDs (sortable, non-sequential) for all public-facing resources; see SYSTEM_ARCHITECTURE.md.
- **Requirement IDs:** `FR-###` functional, `NFR-###` non-functional, `INV-###` invariant.

## Core domain (the Graph — see MASTER_PRODUCT.md §9)
| Term | Definition |
|---|---|
| **Person** | The single, portable, user-owned identity. Exists independent of any tenant. A Person may be a B2C user, a coach's client, and a gym member simultaneously — always the same Person. |
| **Account / User** | Authenticated login bound to a Person. (One Person → one auth identity.) |
| **Tenant** | An isolated business context: an Individual-Coach workspace, a Gym, or a Fitness Company. Owns scoped data and billing. |
| **Membership** | A Person's relationship to a Tenant (member of gym, client of coach), with a role and consent scope. |
| **Goal** | A Person's target (fat loss, strength, hypertrophy, event, health metric), time-bound. |
| **Program** | A structured training plan: a sequence of Workouts over time (mesocycle/microcycle aware). |
| **Workout** | A single training session template (ordered Exercises with prescriptions). |
| **Exercise** | A movement in the library (media, instructions, muscles, equipment, contraindications). |
| **SetLog** | A logged set: exercise, reps, load, RPE/RIR, tempo, timestamp. |
| **Session** | An instance of a performed Workout (the actual execution + its SetLogs). |
| **MealPlan** | Structured nutrition plan (targets + meals/recipes). |
| **FoodItem** | An entry in the food database (macros/micros, serving units, barcode). |
| **FoodLog** | A logged consumption event tied to a Person + time + FoodItem/Recipe. |
| **Biometric** | A measured body data point: weight, body-fat, circumference, progress photo. |
| **WearableStream** | Time-series health data ingested from a device (HRV, sleep, steps, HR). |
| **AdherenceEvent** | Did/didn't-do signal (workout completed, meal logged, check-in submitted). |
| **Outcome** | A measured result used as a training label (PR, body-comp Δ, goal attainment). |
| **CheckIn** | Periodic structured client→coach report (form responses + metrics + media). |

## Coaching domain
| Term | Definition |
|---|---|
| **Coach** | A Person operating a coaching business (own Tenant) or employed by a Gym Tenant. |
| **Client** | A Person in a coaching Membership with a Coach. |
| **Template** | A reusable Program or MealPlan a Coach assigns/clones. |
| **Assignment** | A Template/Program instance bound to a specific Client with a schedule. |

## Gym / enterprise domain
| Term | Definition |
|---|---|
| **Gym** | A Tenant of type gym; may have multiple Branches. |
| **Branch** | A physical location under a Gym Tenant. |
| **MembershipPlan** | A sellable plan (price, duration, access rules, class entitlements). |
| **Subscription (Gym)** | A Person's active MembershipPlan instance at a gym (state: active/frozen/expired). DB: `gym_subscriptions` [B]. |
| **AccessEvent** | An entry/exit record via QR/NFC/barcode/gate. |
| **ClassSession** | A scheduled group class instance with capacity, instructor, bookings. |
| **Booking** | A Person's reservation of a ClassSession or PT slot (states incl. waitlist, no-show). |
| **Staff** | A Person employed by a Gym Tenant (trainer, front-desk, manager). |

## Platform / SaaS domain
| Term | Definition |
|---|---|
| **Plan (SaaS)** | A subscription tier sold to a Tenant or B2C Person (limits, AI credits, features). DB: `plans` [A]. |
| **Subscription (SaaS)** | A Person's or Tenant's active SaaS Plan instance (billing). DB: `saas_subscriptions` [A]. **Distinct from Subscription (Gym).** |
| **AICredit** | Metered unit consumed by AI operations; replenished by Plan or purchased. |
| **Brain** | The AI orchestration layer (plan generation, analytics, recommendations). |
| **EngagementScore** | Per-Person health/engagement metric surfaced to operators. |
| **ChurnRisk** | Predicted likelihood a Person lapses; drives win-back playbooks. |
| **Marketplace** | Surfaces matching Persons↔Coaches and selling Templates (take-rate revenue). |

## Cross-cutting concepts
| Term | Definition |
|---|---|
| **Consent Scope** | Permissioned, revocable grant from a Person to a Tenant over specific data classes. |
| **Tenancy Isolation** | Mechanism guaranteeing one Tenant cannot read another's data (see ARCHITECTURE). |
| **North Star Metric** | Weekly Active Logged Sessions (training or nutrition). |
