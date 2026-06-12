# UI_UX_SYSTEM.md
### Fitness OS — Design System & Experience Standards

> **Status:** Draft v1.0 · **Owner:** UI/UX Direction · **Last updated:** 2026-06-12
> **Document 7 of 10.** A specification (no component code yet — per the brief). Defines tokens, components, motion, layout, accessibility, **dark-mode-first + RTL day-one** (A2), and the surface inventory across all apps. Implemented later as a shared token set consumed by Flutter (member/trainer/staff) and Tailwind (TALL web + Filament).

---

## 1. Design principles (the north star for every screen)

1. **Calm, not cluttered.** A beginner (Maya) opens the app anxious. The default view answers one question — *"what do I do now?"* — not twenty.
2. **One primary action per screen.** Everything else recedes. The "log" action is always within thumb reach.
3. **Data is the hero, chrome disappears.** Charts, rings, and numbers carry the UI; borders/boxes are minimal.
4. **Dark-mode-first.** Designed in dark; light mode is derived, not the reverse (gyms are dimly lit; athletes train at night; OLED battery).
5. **Motion communicates, never decorates.** Animation confirms an action, shows continuity, or celebrates a milestone — and respects `prefers-reduced-motion`.
6. **RTL and Arabic are first-class.** Every layout is mirror-correct and typographically right in Arabic from day one, not retrofitted.
7. **Earned trust through transparency.** AI suggestions always show *why*; progress never lies (no vanity smoothing).
8. **Accessible by default.** WCAG 2.2 AA is the floor, not a stretch goal.

---

## 2. Brand direction

- **Personality:** focused, premium, athletic, intelligent — closer to a high-end performance brand than a "cute habit app." Restraint over rainbow gamification.
- **Voice:** direct, encouraging, never preachy or shaming. Bilingual tone guides written in EN + AR.
- **Logo/mark usage, app icon, motion signature:** defined in the brand kit (companion to this doc).

---

## 3. Color system (tokens)

Tokens are **semantic** (purpose-named), mapped to a primitive ramp. Components reference semantic tokens only — never raw hex — so theming (dark/light) and white-label (coach branding, FR-CCH-006) are a token swap.

### 3.1 Dark theme (default)
| Semantic token | Value | Use |
|---|---|---|
| `bg/base` | `#0B0E11` | App background (near-black, warm-neutral) |
| `bg/surface` | `#13171C` | Cards, sheets |
| `bg/surface-raised` | `#1B2128` | Elevated surfaces, menus |
| `border/subtle` | `#252C34` | Hairline dividers |
| `text/primary` | `#F5F7FA` | Primary text |
| `text/secondary` | `#A8B2BD` | Secondary text |
| `text/muted` | `#6B7682` | Hints, disabled |
| `brand/primary` | `#00E5A0` | **Signature energetic mint-green** — primary actions, progress |
| `brand/primary-press` | `#00C489` | Pressed/active |
| `accent/electric` | `#4C8DFF` | Secondary accent, links, info |
| `feedback/success` | `#3DDC84` | |
| `feedback/warning` | `#FFB020` | |
| `feedback/danger` | `#FF5C5C` | Destructive, churn alerts |
| `data/1..6` | sequential ramp | Chart series (colorblind-safe set) |

> **Accent rationale:** a single vivid mint-green as the brand signature reads "energy/health" without the cliché of fitness-app orange/red, holds AA contrast on the dark base, and is distinctive against competitors (MFP blue, Strava orange).

### 3.2 Light theme
Derived inversions with maintained contrast: `bg/base #FFFFFF`, `bg/surface #F6F8FA`, `text/primary #0B0E11`, brand unchanged (`#00B888` for AA on light). Full mapping in the token file.

### 3.3 Rules
- **Contrast:** text ≥ 4.5:1, large text/UI ≥ 3:1 (WCAG AA). Brand-on-bg verified both themes.
- **Never encode meaning by color alone** (icon/label always accompanies — colorblind + accessibility).
- **White-label:** coaches override `brand/primary` + logo only; system/semantic tokens stay locked to preserve contrast/accessibility.

---

## 4. Typography

**Dual-script system** (A2). Variable fonts; one Latin + one Arabic, metrically harmonized.

| Script | Family | Notes |
|---|---|---|
| Latin (EN) | **Inter** (variable) | Excellent legibility, tabular figures for data |
| Arabic (AR) | **IBM Plex Sans Arabic** (or Tajawal) | Harmonized x-height & weight with Inter for mixed strings |
| Numerals/data | Inter **tabular** figures | Aligned columns in tables/charts |

### Type scale (4px-based, fluid on web)
| Token | Size / line | Use |
|---|---|---|
| `display` | 40 / 48 | Hero numbers (today's calories, big PRs) |
| `h1` | 28 / 36 | Screen titles |
| `h2` | 22 / 30 | Section headers |
| `h3` | 18 / 26 | Card titles |
| `body` | 16 / 24 | Default |
| `body-sm` | 14 / 20 | Secondary |
| `caption` | 12 / 16 | Labels, metadata |
| `mono/data` | 14 tabular | Metrics, timers |

**Rules:** max ~2 weights per screen (Regular/Semibold); Arabic line-height +1–2px (taller script); never justify Arabic body; numerals always tabular in data contexts.

---

## 5. Spacing, radius, elevation, layout

- **Spacing scale (4px base):** `0,4,8,12,16,20,24,32,40,48,64`. Components/screens use the scale exclusively.
- **Radius:** `sm 8 · md 12 · lg 16 · xl 24 · full`. Cards `lg`; buttons `md`; sheets `xl` (top corners).
- **Elevation:** dark theme uses **surface lightness + subtle border**, not heavy shadows (shadows read poorly on dark). Light theme uses soft shadows.
- **Grid:** mobile = 4px baseline, 16px screen gutters, single-column thumb-first; web = 12-col responsive (breakpoints `sm 640 · md 768 · lg 1024 · xl 1280 · 2xl 1536`).
- **Touch targets:** ≥ 44×44pt (48 preferred); primary log action reachable one-handed.
- **Safe areas / keyboard insets** respected on mobile.

---

## 6. RTL & internationalization (first-class, A2)

- **Logical properties everywhere:** start/end, never left/right. Layout mirrors automatically for `dir=rtl`.
- **Mirror:** navigation, back/forward, progress direction, sliders, carousels, drawers. **Do NOT mirror:** clocks, media playback controls, charts with a time x-axis (time still flows naturally), brand logos, numerals.
- **Bidi text:** correct handling of mixed AR/EN/numbers; icons paired with text flip side accordingly.
- **Formatting:** locale-aware dates, numbers, units (metric/imperial per `unit_system`), currency (multi-currency).
- **Content length:** designs tolerate +30–40% text expansion (AR/translations); no fixed-width labels.
- **Testing:** every component has an RTL + an Arabic-content snapshot in the visual test suite.

---

## 7. Component library (the kit)

Grouped; each component has states (default/hover/press/focus/disabled/loading/error), dark+light, LTR+RTL, and an a11y contract.

**Foundations:** Button (primary/secondary/ghost/destructive), Icon Button, Input/Field, Select, Stepper, Slider, Toggle, Checkbox/Radio, Segmented Control, Chip/Tag, Badge, Avatar, Tooltip, Skeleton/Shimmer.

**Layout & nav:** App Bar, Bottom Tab Bar (member app), Side Nav (web), Breadcrumbs, Tabs, Bottom Sheet, Modal/Dialog, Drawer, Card, List Row, Accordion, Empty State, Pull-to-refresh.

**Fitness-specific (the differentiators):**
- **Progress Ring** (calories/macros/activity) — animated, the daily hero.
- **Set Logger** — large-tap reps/load steppers, RPE/RIR selector, rest **Timer** with haptics.
- **Macro Bars** — protein/carb/fat with target vs. actual.
- **Exercise Card** — media thumbnail, muscles, swap action.
- **Trend Chart** — weight/strength/volume with goal projection line.
- **Streak/XP** elements — restrained, celebratory only at milestones.
- **AI Suggestion Card** — shows the recommendation **+ a "why" disclosure** + accept/modify (FR-AI-010 transparency).
- **Check-in Form** renderer (coach), **Booking Calendar** (gym), **Occupancy Gauge**, **Check-in Scanner** UI.

**Data & dashboards (web):** KPI Stat Card, Data Table (sortable/cursor-paginated, sticky header, RTL-aware), Chart suite (line/area/bar/donut/heatmap — colorblind-safe `data/1..6`), Filter Bar, Date-range Picker, Cohort/Funnel viz, Map (branch/occupancy).

**Feedback:** Toast/Snackbar, Inline Validation, Banner (info/warn/danger), Confirm Dialog, Progress/Loading, Celebration (PR/level-up — reduced-motion aware).

---

## 8. Motion system

| Token | Duration / curve | Use |
|---|---|---|
| `motion/instant` | 100ms ease-out | State feedback (toggles, taps) |
| `motion/quick` | 200ms ease-out | Most transitions |
| `motion/standard` | 300ms standard | Sheets, page transitions |
| `motion/expressive` | 450ms spring | Celebrations (PR, level-up, streak) |

**Principles:** every tap acknowledges within `instant`; optimistic UI updates before the network returns (NFR-PERF-003); list reordering/morphing uses shared-element continuity; **all motion honors `prefers-reduced-motion`** (degrade to fades/none); 60fps target — no animation blocks the log action.

---

## 9. Accessibility (WCAG 2.2 AA floor)

- Full screen-reader semantics (labels, roles, live regions for timers/AI streaming).
- Focus visible + logical order; complete keyboard operability on web.
- Color never the sole signal; AA contrast enforced by token design.
- Dynamic type / text scaling without breakage; tolerate 200% zoom.
- Captions/transcripts for exercise videos; haptics as a non-visual cue for timers/sets.
- Reduced-motion + high-contrast modes supported.
- a11y checks in CI (axe for web; Flutter semantics tests).

---

## 10. Surface inventory (where the system is applied)

| Surface | Stack | Phase | Key screens |
|---|---|---|---|
| **Member app** | Flutter | P1 | Onboarding+PAR-Q+, Today (hero), Log Workout, Log Food (search/barcode/photo/voice), Progress, AI Coach chat, Plans, Profile, Wearables, Membership/booking (P3) |
| **Trainer app** | Flutter | P2 | Clients, Client detail (consent-gated), Program/Template builder, Check-ins (+AI draft review), Chat, Schedule, Analytics |
| **Coach web** | TALL | P2 | Dashboard, clients, program builder, branding, billing, CRM, revenue |
| **Gym owner/manager web** | TALL | P3 | Cross-branch dashboard, members, classes/calendar, staff, sales CRM, finance, churn/engagement |
| **Front-desk / Staff app** | Flutter | P3 | Fast check-in scanner, sell/freeze membership, POS-lite |
| **Super-admin** | Filament | P1 | Tenants, subs, AI usage/cost, abuse, revenue, system health, support/impersonation |

**Shared-token guarantee:** a single source-of-truth token set (color/type/space/motion) is generated into Flutter theme + Tailwind config + Filament theme, so all surfaces stay visually consistent and re-theme together.

---

## 11. Design ops

- **Tokens are the contract** between design and engineering; design changes flow through tokens, not ad-hoc CSS/widgets.
- Figma library mirrors this component list; components annotated with a11y + RTL specs.
- **Definition of Done for any UI:** dark+light, LTR+RTL, AR+EN content, a11y pass, reduced-motion, loading/empty/error states, 44pt targets.

---

> **Next document:** `BLUEPRINT.md` — the engineering handbook & repo blueprint: how all the above fits into a concrete monorepo structure, coding standards, module skeletons, environments, and conventions. (Distinct from ARCHITECTURE's *what/why* and EXECUTION_PLAN's *when*.)
