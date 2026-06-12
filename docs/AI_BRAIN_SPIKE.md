# AI_BRAIN_SPIKE.md
### Phase 0 De-risking Spike — The AI Brain

> **Status:** Planned · **Owner:** AI Engineer · **Runs in parallel with Phase 0 skeleton** (`EXECUTION_PLAN.md §3–§4`)
> A spike is a **time-boxed experiment to answer a question**, not production code. It is throwaway by default; only the validated approach graduates into the `AiOrchestration` module.

---

## 1. Why this spike exists (the bet it de-risks)

The entire P1 thesis — *"an AI coach can retain a motivated beginner better than the fragmented status quo"* — and the **gross-margin model** both rest on the AI Brain. A document cannot tell us whether the AI is *good enough* or *affordable enough*. Only measurement can. We run this **before** committing to P1 feature breadth, so course-correction is cheap.

This spike directly attacks the top execution risk in `EXECUTION_PLAN.md §10`: *"AI Brain underdelivers."*

---

## 2. Questions to answer (success = answered, not "built")

| # | Question | Why it's existential |
|---|---|---|
| Q-A | Can we generate a **safe, personalized, structured** Program + MealPlan that a coach rates ≥ "good"? | This is the core product promise (FR-AI-001/002). |
| Q-B | What is the **cost per generation** (tokens × price) at each model tier? | Sets the gross-margin model + AICredit pricing (NFR-AI-001). |
| Q-C | Does the **safety gate** reliably block contraindicated output for injury/PAR-Q+ cases? | Safety-critical, non-negotiable (FR-AI-007, INV-005). |
| Q-D | What is **p95 latency**, and is streaming partial output acceptable? | UX gate (NFR-PERF-002, < 10s p95). |
| Q-E | Does **RAG grounding over the Person's Graph** measurably beat a context-free prompt? | The grounding-in-own-data differentiator (NFR-AI-003). |
| Q-F | Does **model tiering** (cheap model for swaps, strong for full plans) hold quality while cutting cost? | The margin lever (ARCH §6). |

---

## 3. What we build (minimal, throwaway)

- A thin `LlmGateway` interface with **two adapters** (primary = Claude; one fallback provider) to prove provider-agnosticism (ADR-004) — *latest Claude model per `claude-api` reference at build time.*
- **Structured-output** generation (JSON schema for Program/MealPlan) — no free-form prescriptions.
- A **safety sandwich**: pre-check (eligibility from PAR-Q+/injuries) → generate → post-eval (contraindication scan) → reject+regenerate on fail.
- A tiny **RAG step**: assemble Graph context (goals, injuries, equipment, recent sessions) into the prompt; A/B against no-context.
- A **cost/latency meter** logging `tokens_in/out`, `cost_micros`, `latency_ms`, `safety_verdict`, `confidence` per call (the shape of `ai_interactions`, DATABASE_DESIGN §2.5).

Runs as a CLI / set of feature tests against fixture personas — **not wired into the API yet.**

---

## 4. Method

1. **Fixture personas** (10–15): cover Maya (beginner), Karim (intermediate), Sara (optimizer) + edge cases: knee injury, shoulder impingement, pregnancy flag, vegan, Ramadan fasting schedule, home-only equipment, Arabic-language request.
2. Generate Program + MealPlan for each, at two model tiers, with and without RAG grounding.
3. **Expert rubric scoring** (a coach/clinical advisor rates 1–5 on: safety, personalization fit, progression logic, nutrition adequacy, clarity).
4. **Adversarial safety set**: cases that *must* be refused/adjusted (contraindicated movements for the stated injury). Measure block rate.
5. Record cost & latency for every call; compute per-generation and projected per-active-user cost.

---

## 5. Decision criteria (go / adjust / stop)

| Metric | Go | Adjust | Stop-and-rethink |
|---|---|---|---|
| Expert quality (avg) | ≥ 4.0/5 | 3.0–3.9 | < 3.0 |
| Safety block rate (adversarial) | 100% | 95–99% (tighten gate) | < 95% |
| Cost / full generation | within margin target | 1–2× target (optimize: tiering/caching) | ≫ target with no path down |
| p95 latency (streamed) | < 10s | 10–20s | > 20s |
| RAG lift vs. no-context | clear quality lift | marginal | none (drop RAG complexity) |

**Output of the spike:** a 1-page memo answering Q-A…Q-F with numbers, a recommended model-tier map, the validated cost/active-user figure (feeds pricing, `PRODUCT_REQUIREMENTS.md` Q2), and a go/adjust/stop call on the P1 AI scope. The validated gateway + safety-gate design then graduates into the `AiOrchestration` module.

---

## 6. Dependencies & open items

- **Q5** (AI provider contract / model access) — needed to start; see `IMPLEMENTATION_PROGRESS.md §3`.
- **Q7** (clinical contraindication ruleset source) — needed for the safety post-eval; engage the clinical advisor.
- Cost target number — derive from the B2C price point (Q2) and target gross margin.

> This spike is throwaway. Its **findings** are the deliverable; its **code** is not. Do not let spike code leak into production without re-implementation under the `BLUEPRINT.md` DoD.
