# PRODUCT_REQUIREMENTS.md
### Fitness OS — Personas, Journeys, Requirements & Roadmap

> **Status:** Draft v1.0 · **Owner:** Product · **Last updated:** 2026-06-12
> **Document 2 of 10.** Operationalizes the strategy in `MASTER_PRODUCT.md`. Uses canonical names from `GLOSSARY.md`. Requirements are tagged with stable IDs (`FR-###`, `NFR-###`) and a **Phase** (P1=MVP, P2=Coach, P3=Gym, P4=Ecosystem) so later documents (API, DB, roles, execution) can reference them.

---

## 0. ⚠️ ASSUMPTIONS — PLEASE CORRECT ME

These shape the entire roadmap. I've chosen defaults with rationale; flag any you disagree with and I'll revise before they propagate into 8 more documents.

| # | Assumption | Default I'm using | Why | Reversible? |
|---|---|---|---|---|
| A1 | **MVP segment cut** | **B2C (individual) + AI is Phase 1.** Coach = P2, Gym = P3. | The brief treats all three as co-equal; I'm **deliberately diverging** (MASTER_PRODUCT §11). The flywheel only spins if the consumer experience exists first — it's *also* the gym member app, our #1 differentiator vs. Mindbody. Shipping three half-products kills the company. | ✅ **CONFIRMED 2026-06-12** by stakeholder. |
| A2 | **Launch geography / language** | MENA-first, **global-ready; Arabic + English, RTL day-one.** | Has architectural teeth (RTL, localized food DB, regional payments, data residency). | ✅ **CONFIRMED 2026-06-12** by stakeholder. |
| A3 | **Data ownership** | **Person owns data; Tenants get scoped, revocable consent.** | Strategic moat + GDPR alignment (MASTER_PRODUCT §3). | Hard to reverse later — baked into DB design. |
| A4 | **Wearables in scope** | Yes — read-only ingest from Apple Health / Health Connect in **P1**. | Recovery-aware programming is now table stakes; brief omitted it. | Yes. |
| A5 | **AI human-in-the-loop** | AI **drafts**, humans **approve** for any coach→client output. | Trust + liability (MASTER_PRODUCT §13). | Yes (graduated autonomy). |
| A6 | **Pricing placeholders** | B2C $9.99–14.99/mo; Coach $19/$49/$99; Gym $200–2000+/mo. | Benchmarked to incumbents. | Yes — pricing is a GTM decision, not architectural. |

---

## 1. Personas

Personas are written as the *jobs* each actor is hiring the product to do — not demographics. Each maps to roles defined in `ROLES_PERMISSIONS.md`.

### B2C — Individuals

**P1 · "Maya" — The Motivated Beginner** (largest segment, top-of-funnel)
- *Context:* 28, desk job, joined a gym in January, overwhelmed by what to do, intimidated, inconsistent.
- *Jobs:* "Tell me exactly what to do today." "Is what I'm eating okay?" "Am I making progress?"
- *Pains:* Decision paralysis, no accountability, generic YouTube plans don't fit her, quits in week 6.
- *Success:* A daily plan she trusts, visible progress, a streak she doesn't want to break.
- *Why she's the priority:* Highest volume, highest churn — if AI coaching can retain *her*, the model works and the graph fills.

**P2 · "Karim" — The Intermediate Self-Coached Lifter**
- *Context:* 32, trains 4×/week, uses Hevy + MyFitnessPal + a spreadsheet, knows the basics.
- *Jobs:* "Give me smart progressive overload without me doing the math." "Unify my fragmented tracking." "Auto-adjust when I'm fried."
- *Pains:* Tool fragmentation, plateaus, no recovery awareness.
- *Success:* One app replacing three, with programming smarter than his spreadsheet.

**P3 · "Sara" — The Outcome-Driven Optimizer**
- *Context:* 35, data-loving, wears a Whoop, wants body-recomp with evidence.
- *Jobs:* "Show me what's actually working." "Integrate my wearable." "Hold me to my macros."
- *Success:* Deep analytics, wearable integration, trend insight — and eventually a real coach for the last 10%.

### B2B — Coaches

**P4 · "Coach Omar" — The Scaling Online Coach** (core paying B2B persona)
- *Context:* 30, ex-PT, 25 online clients via Trainerize + WhatsApp + Stripe + Sheets, capped at ~30 clients by admin overhead.
- *Jobs:* "Let me handle 100 clients without losing the personal touch." "Automate the boilerplate (check-in reviews, program tweaks)." "Run my whole business in one place — payments, scheduling, leads."
- *Pains:* Drowning in admin, clients churn silently, can't grow revenue without working more hours.
- *Success:* AI drafts 80% of programming/check-ins for his approval; clients use an app they love; revenue scales without proportional time.

**P5 · "Coach Lina" — The Aspiring Solopreneur**
- *Context:* 26, building a coaching side-business, strong on social, weak on systems.
- *Jobs:* "Help me get and convert clients (landing page, intake, trial)." "Make me look professional (branding)." "Don't make me a feature of someone else's app."
- *Success:* Branded client experience, lead funnel, first 10 paying clients.

### B2B Enterprise — Gyms

**P6 · "Tarek" — The Gym Owner / Operator**
- *Context:* Owns 3 branches, ~2,500 members, uses Mindbody + a turnstile vendor + cash PT off-book.
- *Jobs:* "Reduce member churn." "Capture the PT revenue leaking off-platform." "See all branches in one dashboard." "Give members an app they actually open."
- *Pains:* Members churn invisibly, clunky software, fragmented PT revenue, no engagement loop.
- *Success:* Churn down, on-platform PT revenue up, members engaged daily, one source of truth across branches.

**P7 · "Nour" — The Branch Manager**
- *Jobs:* "Run the day: check-ins, class schedule, staff, sales pipeline, today's revenue."
- *Success:* A web dashboard that runs the front desk and surfaces what needs attention.

**P8 · "Hassan" — The Front-Desk Staff**
- *Jobs:* "Check members in fast, sell a membership, take a payment, handle a freeze — without friction."
- *Success:* Sub-3-second check-in, frictionless POS-lite, minimal training.

**P9 · "Trainer Dina" — The In-Gym Trainer**
- *Jobs:* Same as Coach Omar but employed by the gym; "manage my assigned clients, my schedule, my commissions."

### Platform

**P10 · "Yusuf" — The Super Admin / Platform Operator (us)**
- *Jobs:* "Manage tenants, monitor AI cost & abuse, track platform revenue, run support, keep the system healthy."

---

## 2. Key user journeys

Journeys below are the *critical paths*; full flows live in UI_UX_SYSTEM.md. Each notes the **Phase**.

### J1 — B2C Onboarding → First Plan (P1) *(the make-or-break journey)*
1. Install → sign up (social/email) → **PAR-Q+ health screen** (safety gate, `FR-AI-007`).
2. Goal selection, experience level, equipment access, schedule, dietary prefs/restrictions, injuries.
3. Optional wearable connect (Apple Health / Health Connect).
4. **Brain generates** first Program + MealPlan in <10s, shows reasoning, lets user tweak.
5. Land on **Today** screen: today's workout + nutrition targets + one daily recommendation.
6. Activation goal: **log first Session or FoodLog within 24h** (North Star event).

### J2 — Daily Loop (P1) *(retention engine)*
- Morning push (timing learned per user) → "Today" → log workout (offline-capable) → log meals (barcode/AI photo/voice) → see progress ring close → streak/XP feedback → optional evening check-in.

### J3 — Progress & Insight (P1)
- Log weight/measurement/photo → **AI progress analysis** ("trend is +X, on track for goal by date") → weekly AI report → plan auto-adjust suggestion (user approves).

### J4 — B2C → Coach conversion (P2, marketplace)
- Engaged user sees "Work with a coach" → matched to Coaches by goal/budget/specialty → trial → becomes a Client (data carries over with consent).

### J5 — Coach onboards & scales (P2)
- Coach signs up → builds/imports Templates → invites clients (or receives marketplace leads) → assigns Programs → **AI drafts weekly check-in summaries** → coach reviews/edits/sends → monitors adherence dashboard → gets churn-risk alerts → acts.

### J6 — Gym onboarding & member migration (P3)
- Gym signs up → configures Branches, MembershipPlans, classes, staff, access hardware → **bulk-imports members** → members get app invite (instant install base) → front desk runs check-ins/POS → owner sees cross-branch analytics + churn alerts.

### J7 — Gym member daily (P3) *(reuses J1/J2 — same consumer app)*
- Member checks in (QR/NFC) → books a class → does workout (gym or coach assigned) → logs → engagement loop → owner sees the member is active (anti-churn signal).

---

## 3. Functional requirements

Organized by domain. **MoSCoW within phase:** (M)ust / (S)hould / (C)ould. Format: `ID · requirement · Phase · Priority`.

### 3.1 Identity, accounts & consent
- `FR-IDN-001` · Person can create one portable identity; auth via email + social (Apple/Google) · P1 · M
- `FR-IDN-002` · Person profile persists across Tenants; no data loss when joining/leaving a Tenant · P1 · M
- `FR-IDN-003` · Consent Scopes: Person grants/revokes Tenant access to data classes (training, nutrition, biometrics, health) · P2 · M
- `FR-IDN-004` · Data export (full portability) + account deletion (GDPR) · P1 · M
- `FR-IDN-005` · A single Person may simultaneously hold B2C, Client, and Member memberships · P3 · M

### 3.2 AI Brain — coaching
- `FR-AI-001` · Generate personalized Program from goal/level/equipment/schedule/injuries · P1 · M
- `FR-AI-002` · Generate personalized MealPlan from goal/macros/restrictions/preferences · P1 · M
- `FR-AI-003` · Suggest Exercise alternatives (equipment/injury/preference constraints) · P1 · M
- `FR-AI-004` · Daily recommendation + motivation, context-aware · P1 · S
- `FR-AI-005` · Recovery recommendations using wearable + soreness/check-in data · P1 · S
- `FR-AI-006` · Plan auto-adjustment proposals (progression/deload), human-approved · P1 · S
- `FR-AI-007` · **PAR-Q+ screening + injury/contraindication gating on all generated plans (safety-critical)** · P1 · M
- `FR-AI-008` · Conversational AI coach (chat) grounded in the Person's graph · P1 · S
- `FR-AI-009` · AI drafts coach→client check-in summaries & program tweaks for approval · P2 · M
- `FR-AI-010` · Graduated autonomy: every AI action records confidence + an audit trail · P1 · M

### 3.3 AI Brain — analytics
- `FR-AN-001` · Progress analysis (weight/measurement/photo trends, goal projection) · P1 · M
- `FR-AN-002` · Adherence analytics (per Person; per Client for coach; per Member for gym) · P1 · M
- `FR-AN-003` · Performance trends (volume, intensity, PRs) · P1 · S
- `FR-AN-004` · ChurnRisk scoring (coach clients P2; gym members P3) · P2 · M
- `FR-AN-005` · Weekly automated reports (Person, Coach, Gym scopes) · P1 · S
- `FR-AN-006` · Alerts/playbooks (e.g., win-back) triggered by ChurnRisk/adherence drops · P2 · S

### 3.4 Training
- `FR-TRN-001` · Exercise library (media, instructions, muscles, equipment, contraindications) · P1 · M
- `FR-TRN-002` · Log Sessions/SetLogs (reps, load, RPE/RIR, tempo, rest); **offline-first** · P1 · M
- `FR-TRN-003` · Workout timer (rest/interval/EMOM/AMRAP) · P1 · M
- `FR-TRN-004` · Workout history + Personal Records auto-detection · P1 · M
- `FR-TRN-005` · Program builder (coach/advanced user): meso/microcycle structure · P2 · M
- `FR-TRN-006` · Exercise video & instruction playback (licensed media) · P1 · S

### 3.5 Nutrition
- `FR-NUT-001` · Food database search (licensed/aggregated, localized) · P1 · M
- `FR-NUT-002` · FoodLog with calorie + macro tracking; micros where available · P1 · M
- `FR-NUT-003` · Barcode scanning · P1 · M
- `FR-NUT-004` · AI food-image recognition · P1 · S
- `FR-NUT-005` · Voice food logging · P1 · C
- `FR-NUT-006` · Water tracking · P1 · S
- `FR-NUT-007` · Supplement tracking · P1 · C
- `FR-NUT-008` · Auto-generated grocery lists from MealPlan · P1 · C
- `FR-NUT-009` · Recipes / custom foods / meal templates · P1 · S

### 3.6 Body, progress & wearables
- `FR-BIO-001` · Weight, body-fat, circumference measurements · P1 · M
- `FR-BIO-002` · Progress photos (private, encrypted, optional comparison) · P1 · M
- `FR-BIO-003` · Wearable ingest: Apple Health / Health Connect (steps, HR, sleep, HRV) · P1 · S
- `FR-BIO-004` · Garmin/Whoop/Fitbit/Withings integrations · P2 · C

### 3.7 Engagement & gamification
- `FR-ENG-001` · Goals (create, track, projected attainment) · P1 · M
- `FR-ENG-002` · Habit tracking with behavioral nudges (beyond raw streaks) · P1 · S
- `FR-ENG-003` · XP / Levels / Badges / Streaks · P1 · S
- `FR-ENG-004` · Challenges (individual; gym/coach cohort in P2/P3) · P1 · C
- `FR-ENG-005` · Community feed / social (opt-in; integrate Strava for endurance) · P2 · C
- `FR-ENG-006` · Smart notifications (per-user learned timing/frequency) · P1 · S

### 3.8 Coaching (B2B)
- `FR-CCH-001` · Client profiles (health info, goals, injuries, notes) · P2 · M
- `FR-CCH-002` · Customizable CheckIn forms + scheduling · P2 · M
- `FR-CCH-003` · In-app messaging (Client↔Coach), media attachments · P2 · M
- `FR-CCH-004` · Program & MealPlan Templates; assign/clone to Clients · P2 · M
- `FR-CCH-005` · Adherence monitoring dashboard · P2 · M
- `FR-CCH-006` · Coach branding / white-label (logo, colors, custom domain) · P2 · S
- `FR-CCH-007` · Coach business: subscriptions, packages, payments, scheduling, video consults · P2 · M
- `FR-CCH-008` · Lead capture / mini-CRM / funnel pages · P2 · S
- `FR-CCH-009` · Group/cohort coaching · P3 · C
- `FR-CCH-010` · Revenue analytics for coach · P2 · S

### 3.9 Marketplace (B2B/B2C bridge)
- `FR-MKT-001` · Coach discovery & matching (goal/specialty/budget/language) · P2 · S
- `FR-MKT-002` · Trial → paid conversion flow with platform take-rate · P2 · S
- `FR-MKT-003` · Template/Program marketplace (coach-sold, revenue share) · P3 · C
- `FR-MKT-004` · In-app commerce / affiliate (supplements, gear) · P4 · C

### 3.10 Gym — membership & access
- `FR-GYM-001` · Member registration + MembershipPlans (price/duration/access/entitlements) · P3 · M
- `FR-GYM-002` · Renewals, upgrades, downgrades, freezes, family memberships, discounts · P3 · M
- `FR-GYM-003` · Member history & lifecycle states · P3 · M
- `FR-GYM-004` · Access control: QR, barcode card, NFC, smart-gate integration · P3 · M
- `FR-GYM-005` · AccessEvents: entry/exit, attendance history, live occupancy & limits · P3 · M
- `FR-GYM-006` · Digital waivers / e-signature / document storage · P3 · S

### 3.11 Gym — classes, scheduling, staff
- `FR-GYM-010` · Class scheduling (group classes, yoga, swim, cross-training) · P3 · M
- `FR-GYM-011` · Bookings with capacity, waitlists, no-show/late-cancel policies & fees · P3 · M
- `FR-GYM-012` · PT session & court/resource reservations · P3 · S
- `FR-GYM-013` · Staff/trainer profiles, schedules, attendance · P3 · M
- `FR-GYM-014` · Commissions, payroll support, performance analytics · P3 · S

### 3.12 Gym — multi-branch & operations
- `FR-GYM-020` · Multi-branch management, branch managers, shared memberships, transfers · P3 · M
- `FR-GYM-021` · Centralized cross-branch reporting · P3 · M
- `FR-GYM-022` · Broadcast communications (push/email/SMS) to member segments · P3 · S
- `FR-GYM-023` · Sales CRM / lead & trial pipeline · P3 · S

### 3.13 Finance & payments (cross-segment)
- `FR-FIN-001` · Payments, invoices, receipts (coach + gym) · P2/P3 · M
- `FR-FIN-002` · Refunds, discounts, promotions, coupons · P2/P3 · M
- `FR-FIN-003` · Double-entry **Ledger** + reconciliation (audited money movement) · P2 · M
- `FR-FIN-004` · Revenue reports & analytics · P2/P3 · M
- `FR-FIN-005` · POS-lite (front-desk sales) · P3 · S
- `FR-FIN-006` · Inventory / supplements store / equipment maintenance · P4 · C

### 3.14 SaaS platform & billing
- `FR-SAS-001` · Multi-tenant tenant types: Individual-Coach, Gym, Fitness Company · P2 · M
- `FR-SAS-002` · SaaS Plans: free/premium/AI tiers, usage limits, AICredits · P1 · M
- `FR-SAS-003` · Coupons, trials, upgrades/downgrades, proration · P1 · M
- `FR-SAS-004` · AICredit metering + enforcement + top-up · P1 · M
- `FR-SAS-005` · Super-admin: tenant/user/subscription mgmt, AI-usage & abuse monitoring, revenue analytics, system monitoring, support tooling · P1→ · M

### 3.15 Apps & surfaces
- `FR-APP-001` · Member/B2C Flutter app (workouts, nutrition, AI, progress, integrations) · P1 · M
- `FR-APP-002` · Trainer Flutter app (clients, programs, check-ins, chat, analytics, schedule) · P2 · M
- `FR-APP-003` · Web dashboards — **member/individual (responsive companion to the mobile app)**, coach, gym owner, manager, staff · P1.5/P2/P3 · M
- `FR-APP-003a` · **Member web app (TALL)**: Today, log workout/food, progress & charts, AI coach, plans, account/billing — same API + design tokens as the Flutter member app · P1.5/P2 · S
- `FR-APP-004` · Filament super-admin dashboard · P1 · M
- `FR-APP-005` · Gym staff app (check-in, member mgmt, payments) · P3 · S
- `FR-APP-006` · Push notifications across apps · P1 · M
- `FR-APP-007` · Offline support (training/nutrition logging) with sync · P1 · M
- `FR-APP-008` · Real-time chat & live updates (WebSockets) · P2 · M

---

## 4. Non-functional requirements

Designed for "millions of users" per the brief. Detailed mechanisms in SYSTEM_ARCHITECTURE.md.

### Performance `NFR-PERF`
- `NFR-PERF-001` · API p95 < 300ms for reads, < 600ms for writes (excl. AI) · M
- `NFR-PERF-002` · AI plan generation < 10s p95 (stream partial output) · M
- `NFR-PERF-003` · Mobile cold start < 2s; logging interaction < 100ms perceived (optimistic UI) · M
- `NFR-PERF-004` · Gym check-in (scan→confirm) < 3s end-to-end · M

### Scalability `NFR-SCAL`
- `NFR-SCAL-001` · Horizontal scale of stateless API; queue-based async for AI/reports/notifications · M
- `NFR-SCAL-002` · Support 10M+ Persons, 100k+ Tenants without re-architecture · M
- `NFR-SCAL-003` · Read-heavy analytics served from read replicas / pre-aggregated stores · S
- `NFR-SCAL-004` · WearableStream/time-series ingest handles high write volume (sharded/partitioned) · S

### Availability & reliability `NFR-REL`
- `NFR-REL-001` · 99.9% uptime core APIs (enterprise SLA 99.95%) · M
- `NFR-REL-002` · Offline-first mobile: no data loss on network failure; conflict-resolved sync · M
- `NFR-REL-003` · Automated backups + tested restore (RPO ≤ 15min, RTO ≤ 1h) · M
- `NFR-REL-004` · Graceful degradation: if Brain is down, app still logs & functions · M

### Security & privacy `NFR-SEC`
- `NFR-SEC-001` · Encryption at rest + in transit; progress photos & health data extra-protected · M
- `NFR-SEC-002` · **Hard tenant isolation; verified no cross-tenant data access** · M
- `NFR-SEC-003` · RBAC + scoped consent enforcement (see ROLES_PERMISSIONS.md) · M
- `NFR-SEC-004` · PCI-DSS scope minimization (tokenized payments via PSP) · M
- `NFR-SEC-005` · GDPR: consent, export, deletion, data residency by region · M
- `NFR-SEC-006` · Audit logging of sensitive actions (money, health data, AI actions, admin) · M
- `NFR-SEC-007` · Rate limiting, abuse/fraud detection (esp. AI endpoints) · M

### AI quality, cost & safety `NFR-AI`
- `NFR-AI-001` · AI COGS guardrail: cost/active-user tracked; tiered models; cache & batch · M
- `NFR-AI-002` · No contraindicated prescriptions (safety eval gate before output) · M
- `NFR-AI-003` · Hallucination/grounding checks; cite the graph data used · S
- `NFR-AI-004` · Human-in-the-loop enforced for coach→client outputs (A5) · M

### Accessibility, i18n & UX `NFR-UX`
- `NFR-UX-001` · WCAG 2.2 AA; full screen-reader support · M
- `NFR-UX-002` · Dark-mode-first; light mode supported · M
- `NFR-UX-003` · i18n: multi-language, **RTL (Arabic) day-one**, multi-currency, metric/imperial · M
- `NFR-UX-004` · Smooth 60fps animations; perceived-instant interactions · S

### Observability & ops `NFR-OPS`
- `NFR-OPS-001` · Centralized logging, metrics, tracing, error tracking · M
- `NFR-OPS-002` · Per-tenant & per-AI-feature usage/cost dashboards · M
- `NFR-OPS-003` · CI/CD with automated tests, staged rollout, fast rollback · M

---

## 5. MVP definition (Phase 1) — the testable bet

**Thesis being tested:** *Can an AI coach retain a motivated beginner (Maya) better than the fragmented status quo, while filling the graph?*

**In scope (P1 · Must):**
- B2C Flutter app + Filament super-admin + minimal web marketing/account.
- Identity & portable profile, PAR-Q+ screening, GDPR export/delete.
- AI: Program + MealPlan generation, exercise alternatives, daily recommendation, progress analysis, conversational coach, safety gating, AICredit metering.
- Training: exercise library, offline logging, timers, history, PRs.
- Nutrition: food DB, barcode, calorie/macro tracking, AI photo logging (S), water.
- Body: weight/measurements/photos; Apple Health + Health Connect ingest.
- Engagement: goals, habits, streaks/XP, smart notifications.
- SaaS: free + premium B2C plans, AICredits, payments for premium.
- NFRs: offline-first, security baseline, i18n+RTL scaffolding, observability.

**Explicitly out of MVP:** coaching tools, marketplace, gym ops, multi-branch, POS, advanced wearables, community/social, commerce.

**MVP success criteria (go/no-go for P2):**
- D30 retention ≥ best-in-class B2C benchmark (target ≥ 35–40% for activated users).
- ≥ 50% of activated users log ≥ 3 sessions/week (North Star).
- AI suggestion acceptance ≥ 60%; AI COGS/active-user within margin target.
- Free→premium conversion ≥ 3–5%.

---

## 6. Roadmap — V2 / V3 / V4

### Phase 2 (V2) — Coach platform + marketplace *(connect loop 2)*
- Trainer app + coach web dashboard; client mgmt, templates, assignments, check-ins, messaging.
- AI-drafted check-ins & program tweaks (human-approved); ChurnRisk for clients; alerts/playbooks.
- Coach business: payments, packages, scheduling, video consults, branding/white-label, mini-CRM.
- Coach marketplace (discovery, matching, trials, take-rate). Ledger + revenue analytics.
- Garmin/Whoop/Fitbit integrations; community/social.
- **Gate to P3:** coach NRR > 100%, marketplace liquidity proven.

### Phase 3 (V3) — Gym OS *(connect loop 3, enterprise prize)*
- Gym/manager/staff web dashboards + staff app.
- Membership lifecycle, access control + hardware, classes/bookings/waitlists, staff/commissions/payroll.
- Multi-branch, transfers, centralized reporting, sales CRM, broadcast comms, POS-lite.
- Member engagement loop reusing the P1 consumer app; gym-level ChurnRisk + win-back.
- **Gate to P4:** multi-branch reference customer live; member app DAU inside gyms proven.

### Phase 4 (V4) — Ecosystem & scale
- Template marketplace, in-app commerce/affiliate, inventory/store, equipment maintenance.
- Clinical/pro track (physios, RDs), corporate wellness, insurer partnerships.
- Data/insights B2B product (anonymized, consented).
- Future AI vision: body-transformation analysis, exercise form correction, posture analysis.
- Deep international expansion (more languages, payment rails, food data).

---

## 7. Traceability & out-of-scope log

- Every `FR/NFR` here will be referenced by `DATABASE_DESIGN.md` (entities), `API_SPECIFICATION.md` (endpoints), `ROLES_PERMISSIONS.md` (who can do it), and `EXECUTION_PLAN.md` (when it's built).
- **Deferred (with rationale):** POS/inventory/equipment (P4 — not core to flywheel); proprietary hardware (never — integrate instead); clinical diagnosis (never — screen & refer); endurance social network (integrate Strava).

---

> **Next document:** `SYSTEM_ARCHITECTURE.md`. **Blocked on assumption A2 (geography/language)** — confirming that one fact now, because it's a near-irreversible architectural input (RTL, localized food data, regional payment rails, data residency).
