# MASTER_PRODUCT.md
### Fitness OS — Product Vision, Strategy & Competitive Analysis

> **Status:** Draft v1.0 · **Owner:** Product/Strategy · **Last updated:** 2026-06-12
> **Document 1 of 10.** This is the strategic foundation. Every later document (requirements, architecture, database, API, UI, execution) must trace back to a decision made here. If a feature isn't justified by the strategy below, it is a candidate to cut.

---

## 0. How to read this document

The original brief is a **superb feature inventory**. But a feature inventory is not a strategy, and three crowded markets stapled together is not a moat. This document does four things the brief asked for, in order of leverage:

1. **Improve the vision** — reframe what "Fitness OS" actually is and why it can win.
2. **Find the strategic spine** — the single mechanism (a cross-market flywheel) that makes serving three markets a *strength* instead of three times the work.
3. **Analyze competitors** — segment by segment, named, with how they win and where they're soft.
4. **Add what's missing & identify our moats** — the features and structural advantages the brief didn't include.

Throughout, I **challenge assumptions** explicitly in callout blocks like this:

> ⚠️ **Challenge:** [an assumption in the brief I think is wrong or risky, and what I propose instead]

---

## 1. Executive summary

**Fitness OS is the operating system for the entire fitness value chain** — the individual training, the coach who guides them, and the gym they train in — connected by one identity, one data model, and one AI brain.

The wedge is not "another workout tracker." The market is saturated with those. The wedge is the **flywheel between the three segments**: gyms bring members, members need coaching, coaches need clients and tooling, and every interaction feeds an AI that makes each subsequent interaction better. No single-segment competitor (MyFitnessPal, Trainerize, Mindbody) can replicate this because their data and business model are locked to one segment.

- **Who:** Individuals (B2C), independent coaches (B2B), and gyms/chains (B2B2C enterprise).
- **What:** A multi-tenant SaaS with member/trainer mobile apps (Flutter), web dashboards (Laravel TALL), a Filament super-admin, and a deeply integrated AI coaching + analytics layer.
- **Why now:** (1) AI made personalized coaching marginal-cost-near-zero for the first time; (2) the "coaching middle class" — 50M+ independent online coaches globally — is underserved by clunky tooling; (3) gyms are still running on software from the 2010s with no consumer-grade member experience.
- **The moat:** A proprietary, cross-segment fitness graph (people × programs × adherence × outcomes) that compounds. The more it's used, the better the AI, the higher the retention, the stronger the network effects between segments.

> ⚠️ **Challenge to the brief's framing:** The brief calls this "not a simple workout tracker" but then lists ~120 tracker-style features. The risk is building a **mediocre everything** instead of a **remarkable something**. The strategy below resolves this: we ship a focused B2C+coach wedge first, and the gym/enterprise breadth comes later — *not* because it's less important, but because the flywheel must spin before the enterprise side has value to sell.

---

## 2. The problem (why this is a real opportunity, not just a nicer app)

The fitness software market is large but **structurally broken into silos that don't talk to each other**. A real person's fitness life crosses all three:

| Actor | What they live with today | The pain |
|---|---|---|
| **Individual** | MyFitnessPal for food, Hevy/Strong for lifting, a YouTube channel for guidance, Notes app for measurements, WhatsApp for their coach | Data fragmented across 5+ apps; no single source of truth; generic plans; no accountability |
| **Online coach** | Trainerize/TrueCoach for programming, Stripe for payments, Google Sheets for tracking, Instagram for marketing, Calendly for booking | A "business" duct-taped from 6 tools; can't scale past ~30 clients; churns clients silently; no leverage |
| **Gym** | Mindbody/Glofox for ops, turnstile hardware, a separate PT booking tool, a member app nobody opens | Member app is an afterthought; no engagement loop; members who stop coming churn invisibly; PT is off-platform cash |

**The insight:** the *same human* is an individual user, a coach's client, and a gym member — but no software treats them as one identity. That seam is where retention leaks, where data fragments, and where the opportunity lives.

---

## 3. The reframed vision — what "OS" actually means

An "operating system" is not a bundle of apps. It's a **shared substrate** that everything else runs on. For Fitness OS, the substrate is three layers:

1. **One Identity** — a single Fitness Profile (`person`) that persists across every context. You're a solo user today; your gym onboards and you keep your history; you hire a coach inside the gym and they see (with consent) your real data; you leave the gym and you keep your profile. **The person owns their data; tenants get permissioned access.** This is the inverse of every incumbent, where the gym/coach owns the data and the member is captive.

2. **One Graph** — a unified data model linking people → goals → programs → sessions → adherence → biometrics → outcomes. This graph is the proprietary asset (Section 9).

3. **One Brain** — an AI layer that reads the graph and acts in every surface: generating plans, suggesting exercise swaps, flagging churn risk, writing the coach's check-in summary, drafting the gym's win-back campaign.

> ⚠️ **Challenge — data ownership is a strategic weapon, not a legal checkbox.** The brief is silent on *who owns the data*. I'm asserting: **the individual owns their fitness data; tenants are granted scoped, revocable access.** This is contrarian (incumbents lock data to the business) but it's the foundation of the consumer flywheel, GDPR-aligned, and a marketing wedge ("your fitness data follows you for life").

**Product principles** (these govern every later decision):

- **Mobile-first for members & coaches; web-first for operators.** Members live on the phone. Gym front desks and coach analytics live on the web.
- **AI is a copilot, never an autopilot — until it earns trust.** AI drafts; humans approve (especially in coaching). We graduate features from "AI suggests" → "AI does, human reviews" → "AI does" as confidence data accrues.
- **Offline is a first-class state, not an error.** Gyms have dead zones. Logging a set must never fail because of a network blip.
- **Every feature must feed the graph or use the graph.** Features that don't connect to the data asset are vanity. This is our anti-bloat rule.
- **Free tier is a growth engine, not charity.** The free B2C tier is customer acquisition for the paid coach/gym tiers — it's marketing spend, accounted for as such.

---

## 4. The strategic spine: the cross-market flywheel

This is the most important section in the document. **It is the reason the three-market scope is a strength rather than a distraction.**

```
            ┌──────────────────────────────────────────────┐
            │                                                │
            ▼                                                │
   ┌─────────────────┐   members need     ┌─────────────────┐
   │   INDIVIDUALS    │ ───guidance────►   │     COACHES      │
   │   (B2C, free→    │                    │  (B2B, $19–99/mo)│
   │    premium)      │  ◄──coaches need── │                  │
   └─────────────────┘     clients         └─────────────────┘
            ▲                                        │
            │ gyms onboard members          coaches work │
            │ (instant install base)        inside gyms  │
            │                                        ▼
            │                              ┌─────────────────┐
            └──────────────────────────────│      GYMS        │
              gyms upsell premium &        │ (Enterprise,     │
              coaching to members          │  $200–2000+/mo)  │
                                           └─────────────────┘
                          ▲                         │
                          └─────────────────────────┘
                          every interaction feeds THE GRAPH,
                          which makes THE BRAIN smarter,
                          which improves all three experiences
```

**How each loop reinforces the others:**

- **Gym → Individual:** A gym signing up instantly brings hundreds of members onto the consumer app (zero-CAC user acquisition). This is the cheapest growth channel in the model.
- **Individual → Coach:** Engaged free/premium users are warm leads for coaching. We become a **marketplace** matching users to coaches (take-rate revenue, Section 8).
- **Coach → Gym:** Coaches operating on-platform make gyms stickier; gyms recruit coaches who already know the tools.
- **Everyone → Graph → Brain → Everyone:** Adherence and outcome data from millions of sessions trains models that no single-segment player can match. **This is the compounding moat.**

> ⚠️ **Challenge — don't try to spin all three loops at once.** A three-sided flywheel started cold spins nowhere. **Sequencing matters more than scope.** See the GTM in Section 11: we light **one** loop first (B2C wedge → coach marketplace), prove retention and the AI's value, *then* sell gyms a system that already has a living member experience. Selling gym software first (the Mindbody path) gets you ops software with a dead member app — exactly the incumbent weakness we're attacking.

---

## 5. Competitive analysis

The fatal mistake would be to benchmark against one competitor. We compete in **three different arenas** with three different incumbents, plus a fourth (wearables/data) creeping in from the side.

### 5.1 B2C — Individual fitness & nutrition apps

| Competitor | Strength | Weakness we exploit |
|---|---|---|
| **MyFitnessPal** | Largest food database (~20M items), brand, barcode scanning | Aggressive paywall + ads, dated UX, weak workout side, no coaching, AI is bolted-on |
| **Fitbod** | Excellent adaptive strength programming, clean UX | Lifting-only, no nutrition, no human coaching, no community |
| **Hevy / Strong** | Beloved by lifters, great logging UX, social feed | Tracking-only; no nutrition, no AI plans, no coaching/gym side |
| **Cronometer** | Best-in-class micronutrient accuracy | Niche/clinical, weak engagement, no training |
| **Freeletics / Centr / Fitbod** | AI/celebrity-led guided programs | Closed content libraries, not a platform, no coach/gym layer |
| **Whoop / Oura / Apple Fitness+** | Wearable data + recovery science | Hardware-gated; don't program training or nutrition; no human layer |
| **Noom** | Behavioral/psychology angle, strong retention via coaching | Weight-loss-only positioning, human coaches are scripted/low-leverage, expensive |

**Our B2C edge:** the only app that unifies *training + nutrition + AI coaching + a path to a real human coach + your gym* in one identity, with data you own.

### 5.2 B2B — Coaching platforms

| Competitor | Strength | Weakness we exploit |
|---|---|---|
| **Trainerize** | Market leader for online PT, big exercise library, integrations | Dated UX, client app engagement is poor, AI is minimal, no gym-grade ops |
| **TrueCoach** | Loved by coaches, clean programming UX | Programming-centric; weak nutrition, weak business/payments, no member-side delight |
| **Everfit** | Modern, good automation, habit coaching | Still single-segment; no gym OS; AI still shallow |
| **PT Distinction / My PT Hub** | Feature-rich, affordable | Cluttered UX, no AI differentiation |
| **Kahunas / CoachAccountable** | Accountability/automation focus | Narrow, no consumer-grade client app |

**Our coach edge:** a client app members *actually open daily* (because it's also their personal tracker + gym app), AI that drafts the 80% of programming/check-ins that's boilerplate so coaches scale past 100 clients, and built-in business (payments, scheduling, marketplace leads).

### 5.3 B2B Enterprise — Gym management

| Competitor | Strength | Weakness we exploit |
|---|---|---|
| **Mindbody** | Dominant, huge install base, marketplace, payments | Expensive, bloated, notoriously clunky UX, member app is an afterthought, weak on strength-gym workflows |
| **Glofox (ABC)** | Strong for boutique studios, good branding | Studio-class billing, less depth for big-box/multi-branch, no coaching/AI layer |
| **Zen Planner / Wodify** | Great for CrossFit/martial arts (class + WOD) | Niche, dated, no AI, weak nutrition |
| **PushPress** | Modern, good for gym owners, free tier | US-centric, smaller ecosystem, no AI coaching layer, no consumer flywheel |
| **EGym / Technogym** | Hardware + software integration | Hardware-locked, expensive, closed |
| **GymMaster / Perfect Gym** | Solid access control & multi-site | Operations-first; member experience is utilitarian |

**Our gym edge:** the member app is the *best consumer fitness app on the market* (because it's the same B2C product), AI-driven retention/churn prevention out of the box, on-platform PT that captures revenue currently lost to cash, and a coach marketplace that turns the gym into a fitness ecosystem.

### 5.4 The flanking threat — platform & wearable players

- **Apple (Fitness+, Health), Google (Fit), Samsung** could bundle. Our defense: they won't touch the *business* side (coaches, gyms, payments, multi-tenancy) — that's our fortress.
- **Strava** owns social/endurance. We integrate rather than compete on running/cycling social.
- **Generative-AI-native upstarts** are the real future threat. Our defense is the **proprietary graph + the human/business layer** — pure-AI apps can generate a plan but can't onboard a gym's 2,000 members or process a coach's payments.

> ⚠️ **Challenge — "compete with the best global apps" is necessary but not sufficient.** Matching MyFitnessPal's food DB or Trainerize's exercise library is **table stakes**, not a strategy. We will *license/aggregate* commodity assets (food data, exercise media — Section 12) rather than rebuild them, and spend our scarce engineering on the **graph + brain + cross-segment identity** that no one can copy.

---

## 6. Competitive advantages & moats (ranked by durability)

1. **Cross-segment data graph (compounding, hardest to copy).** Outcome-labeled training/nutrition/adherence data across millions of real people. Single-segment competitors structurally cannot assemble this.
2. **Single-identity, user-owned data.** Creates consumer trust + portability that locks users in *by choice* and creates switching costs for the businesses around them.
3. **Three-sided network effects.** More gyms → more members → more coach demand → more coaches → stickier gyms. Each side raises the others' switching cost.
4. **AI cost curve.** Personalized coaching at near-zero marginal cost undercuts the human-coach-only economics of Noom and the static-content model of Freeletics.
5. **Distribution via gyms (zero-CAC).** Every gym signed delivers a captive install base — a channel incumbents in B2C don't have and gym incumbents don't exploit.
6. **Modern tech & UX as a wedge** (least durable, but real today). Incumbents are saddled with a decade of legacy UI; we land deals on "your members will actually use this."

---

## 7. Features the brief is missing (additions)

The brief is broad. Here's what a billion-dollar version needs that wasn't listed, grouped by leverage.

### 7.1 The graph & retention engine (highest priority — this is the moat)
- **Unified Fitness Profile / portability** — the user-owned identity described in §3.
- **Outcome tracking & labeling** — explicitly link programs/plans to measured results so the AI learns *what actually works for whom*. (No competitor closes this loop well.)
- **Churn-risk scoring as a first-class object** — for gyms *and* coaches, with automated, approvable win-back playbooks. (The brief lists "churn prediction" under AI but not the action loop around it.)
- **Engagement/health score per member** surfaced to operators — the single number a gym owner checks every morning.

### 7.2 Marketplace & monetization (new revenue lines)
- **Coach Marketplace** — match engaged B2C users to vetted coaches; platform take-rate. (Turns the free tier into a revenue funnel.)
- **Programs/Template Marketplace** — coaches sell programs; revenue share. (Trainerize/Everfit don't do this well.)
- **In-app commerce** — supplements/merch/affiliate, the natural extension of grocery lists and supplement tracking.

### 7.3 Hardware & data ecosystem (defensibility + B2C delight)
- **Wearable & health integrations** — Apple Health, Google Fit/Health Connect, Garmin, Whoop, Fitbit, smart scales (Withings). Recovery/HRV/sleep data feeds the brain. **The brief omits wearables entirely — this is a major gap** given the whole industry is moving to recovery-aware programming.
- **Recovery-aware programming** — auto-deload/adjust based on sleep, HRV, soreness check-ins.

### 7.4 Coach leverage & trust (turns "tools" into a "business")
- **AI-drafted check-ins & weekly client summaries** — the single biggest time-saver for scaling coaches.
- **Coach branding / white-label** (logo, colors, custom domain) — coaches resent feeling like a feature of someone else's app.
- **Lead capture / mini-CRM & funnels** for coaches (landing pages, intake forms, free-trial flows).
- **Group/cohort coaching & challenges** (one coach → many clients economics).

### 7.5 Gym operations the brief under-specs
- **Lead management / sales CRM & trial pipeline** (gyms live or die on sales) — listed nowhere in the brief.
- **Waitlists & no-show/late-cancel policies & fees** for classes.
- **Capacity/occupancy limits & live occupancy** (post-COVID expectation).
- **Member app retention loop inside the gym** (challenges, leaderboards tied to check-ins).
- **Digital waivers, e-signatures, document storage** (liability/compliance).
- **Communications: broadcast email/SMS/push campaigns** to member segments.

### 7.6 Trust, safety, and clinical edges
- **Health screening (PAR-Q+) & medical/injury contraindication gating** on AI-generated plans — *safety-critical*. AI must not prescribe contraindicated movements. The brief lists "injuries" as data but no **safety guardrail**.
- **Disclaimers, scope-of-practice & liability framework** (we are not prescribing medical/clinical advice).
- **Pro/clinical track (future)** — physios, dietitians (RD), corporate wellness, insurers (a large future TAM the brief hints at via "Fitness Company").

### 7.7 Behavioral & engagement science
- **Habit-formation engine** beyond raw streaks (the brief lists streaks/XP/badges — these decay; pair them with behavioral nudges, identity-based goals, and "don't break the chain" psychology à la Noom).
- **Smart notifications** — timing/frequency learned per user (not spam).

### 7.8 Internationalization & accessibility (non-negotiable for "global")
- **Multi-language, multi-currency, multi-unit (metric/imperial), localized food databases, RTL support** (the user base here is likely MENA + global — Arabic/RTL must be designed in from day one, not retrofitted).
- **Localized payment methods & tax/VAT handling** per region.

---

## 8. Business model & monetization (expanded)

The brief's model is sound but leaves money on the table. Layered model:

**Subscription (core ARR):**
- **B2C:** Free (graph-feeding funnel) → Premium (AI coach, advanced analytics, integrations) → likely **$9.99–14.99/mo**.
- **Coach:** tiered by active-client count — **$19 / $49 / $99+/mo** (compete with Trainerize/TrueCoach).
- **Gym:** per-location base + per-active-member pricing — **$200–$2,000+/mo** with multi-branch enterprise tiers.

**Usage & credits:**
- **AI credits** metered above plan limits (protects margin on expensive model calls — see Architecture doc for cost controls). The brief lists "AI credits" ✅ — we make this a real metering system, not an afterthought.

**Transactional (the upside the brief misses):**
- **Coach marketplace take-rate** (e.g., 10–20% of coaching fees transacted on-platform).
- **Payment processing margin** (gyms/coaches process member payments through us; we earn on top of Stripe — the Mindbody/Glofox model, a large revenue line).
- **Program/template marketplace** revenue share.
- **In-app commerce / affiliate** (supplements, gear).

**Enterprise:**
- Custom/annual contracts, white-label, API access, dedicated support, corporate wellness deals.

> ⚠️ **Challenge — payments are a revenue *line*, not just a feature.** The brief lists "payments" as a checkbox under each segment. In reality, **payment processing margin + marketplace take-rate may eventually exceed SaaS subscription revenue** (it does for Mindbody). The architecture and database must treat money movement as a first-class, audited, PCI-scoped domain from day one. Flagging this now so the Database and Architecture docs design for it.

---

## 9. The data graph as the core asset (why we win long-term)

Everything above converges here. The defensible asset is not the app — apps get cloned in a quarter. It's the **fitness graph**:

```
person ─┬─ goals ─┬─ programs ──── sessions ──── set/rep/load logs
        │         └─ meal_plans ── food_logs ──── macros/micros
        ├─ biometrics (weight, bodyfat, measurements, photos)
        ├─ wearable_streams (HRV, sleep, steps, HR)
        ├─ adherence_events (did/didn't, when, how consistent)
        └─ outcomes (PRs, body comp Δ, goal attainment)  ◄── the labels
```

With outcomes as **labels**, this becomes a supervised dataset answering *"what intervention produced what result for which body/context?"* — the single most valuable question in fitness, and one no single-segment competitor can answer. This dataset:
- Powers increasingly better AI plans (compounding quality).
- Enables outcome-based marketing ("members on Fitness OS coaching retain 2.3× longer").
- Becomes a future B2B data/insights product (anonymized, consented) for the industry.

This is why **§3's "every feature feeds or uses the graph"** is a hard rule, not a slogan.

---

## 10. North Star & key metrics

- **North Star Metric:** **Weekly Active Logged Sessions** (training *or* nutrition logged) — captures real engagement across all three segments and directly feeds the graph. (Not "downloads," not "MAU" — those don't predict outcomes or retention.)
- **B2C:** D1/D7/D30 retention, free→premium conversion, weekly logging streaks.
- **Coach:** active clients per coach, client retention, coach NRR, time-to-first-program.
- **Gym:** member check-in frequency, member churn rate, % members active in app, on-platform PT revenue.
- **AI:** suggestion acceptance rate, plan-completion rate, AI cost per active user (margin guardrail).
- **Business:** NRR (target >110%), CAC by channel (gym channel ≈ near-zero), LTV/CAC, gross margin (watch AI COGS).

---

## 11. Go-to-market & sequencing (the part that de-risks the scope)

> ⚠️ **Challenge — the biggest risk in this project is scope-induced death.** Three full products at once is how ambitious platforms die. The flywheel (§4) tells us the *order* to light the loops.

- **Phase 1 — B2C wedge + AI (light the first loop).** Win individuals with the best AI training+nutrition app and user-owned data. Prove retention and that the AI is genuinely good. *This is the MVP focus (detailed in PRODUCT_REQUIREMENTS.md).* Without a working consumer experience, the gym member app — our #1 differentiator vs. Mindbody — doesn't exist.
- **Phase 2 — Coach platform + marketplace (connect loop 2).** Onboard independent coaches; let engaged B2C users become their clients. Now we have a two-sided network and our first strong ARR.
- **Phase 3 — Gym OS (connect loop 3, the enterprise prize).** Sell gyms a system that *already has* the best member app and a coach network. Land-and-expand into multi-branch chains.
- **Phase 4 — Ecosystem.** Marketplaces, commerce, clinical/corporate, data products, international expansion at scale.

Geography: I'm assuming a **MENA-first, global-ready** launch (founder/market signals). That makes **Arabic/RTL, local payment rails, and localized food data day-one architecture concerns**, not later add-ons.

---

## 12. Build vs. buy vs. license (so we spend engineering where it matters)

| Asset | Decision | Why |
|---|---|---|
| Food database | **License/aggregate** (e.g., Open Food Facts + commercial feed) | Rebuilding 20M foods is a multi-year distraction; it's commodity |
| Exercise media/library | **License + augment** | Quality video is expensive; differentiate on programming, not footage |
| Payments | **Buy (Stripe Connect / regional PSPs)** + own the ledger | Don't become a payments company; do own reconciliation/ledger |
| Barcode/food-image AI | **Buy/partner initially, build later** | Commodity vision; revisit once the graph justifies a custom model |
| Push/SMS/email | **Buy (FCM/APNs, Twilio, SES)** | Infra, not differentiation |
| **The graph, the brain (orchestration), cross-segment identity, multi-tenancy** | **Build — this is the company** | These are the moat; everything else serves them |

---

## 13. Risks & mitigations

| Risk | Severity | Mitigation |
|---|---|---|
| **Scope overwhelm** (build everything, ship nothing) | 🔴 Critical | Strict phasing (§11); MVP discipline; "feeds-or-uses-the-graph" cut rule |
| **AI cost/margin blowout** | 🔴 High | Credit metering, model tiering, caching, smaller models for routine tasks (Architecture doc) |
| **AI safety/liability** (bad plan injures someone) | 🔴 High | PAR-Q+ screening, injury/contraindication gating, human-in-loop for coaching, disclaimers, scope-of-practice |
| **Cold-start / empty marketplace** | 🟠 Med | Light B2C loop first; seed coaches; gym channel for instant user base |
| **Incumbent retaliation / bundling (Apple)** | 🟠 Med | Own the business layer they won't touch; portability as differentiator |
| **Data privacy/regulatory (health data, GDPR)** | 🟠 Med | User-owned model, consent framework, regional data residency, audit logs |
| **Multi-tenant data leakage** | 🔴 High | Tenancy isolation strategy is a top architecture priority (next docs) |
| **Trust in AI coaching** | 🟠 Med | Graduated autonomy (suggest→review→do); show the reasoning; let coaches override |

---

## 14. Explicit non-goals (scope discipline)

To keep the "do everything" pressure honest, here is what we are **not** doing — at least not in early phases:

- **Not** building proprietary hardware (we integrate with wearables, not compete with Whoop/Garmin).
- **Not** rebuilding commodity datasets (food DB, exercise videos — license them, §12).
- **Not** a medical/clinical diagnostic product (we screen and refer; we don't diagnose).
- **Not** an endurance-sport social network (we integrate Strava rather than fight it).
- **Not** launching all three segments simultaneously (§11 sequencing).
- **POS / inventory / equipment maintenance** (brief's "Future" list) — correctly deferred; confirmed as post-Phase-3.

---

## 15. Summary — the one-paragraph thesis

Fitness OS wins not by being a better workout tracker, a better coaching tool, or better gym software — each of those markets has entrenched incumbents. It wins by being the **only system that connects all three through one user-owned identity, one compounding data graph, and one AI brain**, lit up in a deliberate sequence (consumer → coach → gym) so each loop bootstraps the next. The defensible asset is the outcome-labeled cross-segment graph, which no single-segment competitor can assemble. Everything in the documents that follow — requirements, architecture, database, API, UI, execution — exists to build and protect that graph and the flywheel around it.

---

### Open questions to resolve before/within the next documents
1. **Launch geography & primary language** — confirm MENA-first + Arabic/RTL day-one? (Drives i18n, payments, food data.)
2. **Multi-tenancy model** — single-DB-with-tenant-scoping vs. DB-per-tenant vs. hybrid? (Decided in SYSTEM_ARCHITECTURE.md; lean **hybrid: shared DB for B2C, isolated schemas for enterprise gyms** — to be argued there.)
3. **MVP segment cut** — confirm Phase-1 = B2C + AI before coach/gym? (Drives PRODUCT_REQUIREMENTS.md MVP.)
4. **Payments partner & regions** — Stripe Connect vs. regional PSPs for MENA.
5. **AI provider strategy** — managed API (Claude/GPT) vs. multi-provider gateway with fallback + cost routing. (Architecture doc.)

---

> **Next document:** `PRODUCT_REQUIREMENTS.md` — personas, journeys, functional & non-functional requirements, and a phased MVP→V2→V3 roadmap that operationalizes the sequencing in §11.
