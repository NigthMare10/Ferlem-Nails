---
name: Studio Lemus - Pearl Ledger
description: Workspace perla, claro y táctil para la operación precisa de un salón de uñas.
colors:
  background: "oklch(97.4% 0.003 325)"
  surface: "oklch(100% 0 0)"
  surface-elevated: "oklch(94.7% 0.012 345)"
  glass: "oklch(100% 0 0 / 0.74)"
  primary: "oklch(36% 0.1 345)"
  primary-strong: "oklch(27% 0.075 342)"
  secondary: "oklch(71% 0.06 354)"
  champagne: "oklch(81% 0.073 79)"
  text: "oklch(25% 0.025 345)"
  text-muted: "oklch(43% 0.023 345)"
  border: "oklch(87% 0.008 345)"
  success: "oklch(50% 0.074 151)"
  warning: "oklch(62% 0.112 72)"
  error: "oklch(50% 0.145 22)"
  focus: "oklch(55% 0.13 342)"
  ambient-rose: "oklch(91% 0.026 352 / 0.24)"
  sidebar-shadow: "oklch(22% 0.04 342 / 0.1)"
  sidebar-inset: "oklch(18% 0.04 344 / 0.38)"
  plum-relief: "oklch(18% 0.04 345 / 0.24)"
  button-shadow: "oklch(25% 0.05 345 / 0.22)"
  field-inset: "oklch(31% 0.03 345 / 0.06)"
  status-inset: "oklch(30% 0.03 345 / 0.06)"
  topbar-shadow: "oklch(30% 0.025 345 / 0.1)"
typography:
  display:
    fontFamily: "DM Serif Display, Georgia, serif"
    fontSize: "clamp(2rem, 3vw, 3.25rem)"
    fontWeight: 400
    lineHeight: 1.05
    letterSpacing: "-0.02em"
  login-display:
    fontFamily: "DM Serif Display, Georgia, serif"
    fontSize: "clamp(2.6rem, 5vw, 5.2rem)"
    fontWeight: 400
    letterSpacing: "-0.045em"
    lineHeight: ".96"
  body:
    fontFamily: "DM Sans, Arial, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "DM Sans, Arial, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: "0.08em"
  title:
    fontFamily: "DM Sans, Arial, sans-serif"
    fontSize: "1rem"
    fontWeight: 650
    lineHeight: 1.2
  section-title:
    fontFamily: "DM Sans, Arial, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 650
    lineHeight: 1.2
  metric:
    fontFamily: "DM Sans, Arial, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 700
    lineHeight: 1.15
rounded:
  control: "10px"
  compact: "12px"
  surface: "16px"
  overlay: "20px"
  pill: "999px"
spacing:
  1: "4px"
  2: "8px"
  3: "12px"
  4: "16px"
  5: "20px"
  6: "24px"
  8: "32px"
  10: "40px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.surface}"
    rounded: "{rounded.control}"
    height: "44px"
  button-secondary:
    backgroundColor: "{colors.surface-elevated}"
    textColor: "{colors.primary-strong}"
    rounded: "{rounded.control}"
    height: "44px"
  field:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.text}"
    rounded: "{rounded.control}"
  navigation-active:
    backgroundColor: "{colors.surface-elevated}"
    textColor: "{colors.primary-strong}"
    rounded: "{rounded.control}"
---

# Design System: Studio Lemus

## Overview

**Creative North Star: "Studio Lemus - Pearl Ledger"**

Studio Lemus feels like a pearl-bound appointment ledger opened on a bright reception desk: calm enough for long shifts, precise enough for charging and booking, and distinct from an administrative template. The visual system uses a clean white/pearl canvas, clear floating navigation, restrained dusty rose and selective plum hierarchy. Beige is not a dominant canvas or surface color.

Brand character appears through the large logo at sign-in, composed editorial headings, soft tactile controls and a small number of highly legible signature moments. Glass belongs to navigation, contextual controls, mobile drawers, overlays, menus and sheets. Soft neomorphism belongs to secondary controls, focused fields, metrics and active navigation, never to every container.

**Implementation status:** 7B VISUAL FIX + POLISH. Contrast, field layers, icon visibility, light branding, responsive overlays and operational surfaces are corrected without changing application contracts.

**Key Characteristics:**
- Warm, organized, and financially trustworthy.
- Feminine through color temperature and editorial restraint, not ornament.
- Information-dense where operationally needed; spacious where decisions begin.
- A single clear primary action per context.

## Colors

The palette is warm and tinted; no surface or text role uses pure black, pure gray, or generic cool SaaS gradients.

### Primary
- **Ciruela de trabajo:** Used for primary actions, active navigation, important amounts, and the deepest branded moments. It remains scarce enough to preserve authority.
- **Ciruela profunda:** Used for pressed states, high-contrast contexts, and the dark logo context.

### Secondary
- **Rosa empolvado:** Used for gentle selection, client-facing warmth, and secondary emphasis, never as a text field for muted gray copy.
- **Champagne funcional:** Used sparingly for achievement, premium highlights, and non-critical emphasis.

### Tertiary
- **Salvia de confirmación:** Reserved for successful payments, confirmed appointments, and positive financial status.

### Neutral
- **Perla fría:** Default near-white canvas; it keeps long sessions clear without beige cast.
- **Blanco limpio:** Primary work surface and form background.
- **Malva tenue:** Elevated or selected surface, never a competing card layer.
- **Tinta ciruela:** Main reading color.
- **Tinta suavizada:** Supporting copy only on neutral surfaces.

**The One Accent Rule.** Primary ciruela appears on one main action and a small set of meaningful states per screen. It is not a blanket heading, border, and icon color.

## Typography

**Display Font:** DM Serif Display, Georgia, serif.
**Body Font:** DM Sans, Arial, sans-serif.
**Label/Mono Font:** DM Sans, Arial, sans-serif with tabular numerals enabled for monetary data.

**Character:** The serif is a calm editorial signal for page titles, login, and important moments only. DM Sans is the operational voice: clear at small sizes, stable in tables, and readable for HNL amounts and form labels.

### Hierarchy
- **Display:** Used only for login and high-level welcome or section moments; never for dense tables or dialogs.
- **Headline:** Sans serif, semibold; primary screen title and modal title.
- **Title:** Sans serif, semibold; groups, cards, and calendar context.
- **Body:** 16px equivalent minimum for input and reading text; monetary columns use tabular figures.
- **Label:** Compact uppercase only for metadata, never for paragraphs or primary instructions.

**The Operational Numerals Rule.** Amounts, durations, dates, and quantities align with tabular figures and do not use display type.

## Layout

Use a 4px base rhythm with tight relationships at 8–16px and task changes at 24–40px. Desktop uses a clear floating navigation piece and an in-flow contextual capsule, eliminating the reserved topbar space that previously broke views; tablet favors two-column or master-detail arrangements; mobile is a task-first single column with filters and secondary actions in drawers or sheets.

At 320px, essential actions remain visible and targetable. Data tables transform to semantically complete mobile cards; information is reduced by priority, not hidden arbitrarily. The sale total and appointment action remain in the lower thumb zone when context warrants it.

## Elevation & Depth

Depth is tonal first, shadow second. Standard content is mostly flat on the ambient canvas, using proximity rather than a card. Raised controls use paired light/dark shadows. Glass surfaces add bounded translucency and blur only when they sit over real moving or layered content.

### Shadow Vocabulary
- **Ambient rest:** Paired soft light/dark shadow for interactive secondary surfaces, never a dark glow.
- **Overlay lift:** Slightly stronger soft shadow for dialogs, drawers, and bottom sheets.
- **Pressed relief:** Inset/outset tonal contrast for selected controls, active navigation, focused fields, and secondary buttons; not for content cards.

**The Glass Has a Job Rule.** Glass is reserved for topbar, navigation on mobile, overlays, dialogs, and highlighted summary panels. It is not a default card treatment.

## Shapes

Controls have gently curved 10px corners, common surfaces 16px, and overlays 20px. A radius communicates touchability or containment, not decoration. Dividers are subtle warm borders; strong colored side borders and rounded-card border accents are avoided.

## Components

### Buttons
- **Shape:** Tactile but restrained; 44px minimum height.
- **Primary:** Ciruela fill on light text, one per local action group.
- **Secondary:** Porcelain/malva tonal treatment with delicate relief; not an outlined clone of the primary button.
- **Hover / Focus:** 180–220ms color, shadow, or transform response; focus is a visible ciruela ring.

### Cards / Containers
- **Corner Style:** Surface radius only when a real group needs containment.
- **Background:** Porcelain or a single tonal elevation level.
- **Shadow Strategy:** Flat by default; lift only for interaction or hierarchy.
- **Internal Padding:** 16–24px depending on density.

### Inputs / Fields
- **Style:** Quiet porcelain field, warm border, direct label, and 44px minimum control height.
- **Focus:** Clear focus ring and border shift, never color-only validation.
- **Error / Disabled:** Plain-language message beside the source; disabled states explain why when a prerequisite is missing.
- **Contrast contract:** Typed text always uses `text`, placeholders and labels use `text-muted`, field icons use `text-muted`, and no outline segment may paint over field content.

### Navigation
- **Style:** Role-aware operational groups, explicit labels, and an inset active state integrated into the rail. Mobile navigation uses a compact glass panel without obscuring the current task.
- **Shell implementation:** The desktop rail groups Jornada, Movimiento, and El estudio; it can collapse to an icon rail with tooltips. The mobile drawer closes after navigation and the topbar remains 64px.

### Metrics
- **Style:** Metrics earn a surface only when they change the next decision. Use a small number of varied hierarchy levels instead of a uniform card grid.

## Do's and Don'ts

### Do:
- **Do** use the transparent black Studio Lemus logo on light surfaces and the transparent white version only on deep-plum contexts.
- **Do** derive optimized logo exports from the supplied source before adding them to runtime UI.
- **Do** make operational status readable in text, icon, and color together.
- **Do** reserve subtle motion for feedback, continuity, and focus.
- **Do** respect `prefers-reduced-motion` while retaining clear state change.

### Don't:
- **Don't** use Inter, Arial, or system UI as the design identity.
- **Don't** use purple-to-blue gradients, dark glows, bounce, elastic motion, or constant animation.
- **Don't** place every group inside cards or nest cards.
- **Don't** use gray text on colored surfaces, pure black/gray, or rounded-square icon tiles as a repeated visual device.
- **Don't** use the supplied gold, beige, JPEG, PDF, or AI logo presentations directly in product UI unless a documented future context specifically requires them.
- **Don't** add screen-level restyling during Phase 7A Prompt 1; use the new base components only when a later scoped phase authorizes the screen.

## Rejected Anti-Reference

**Rejected direction: Soft Future Atelier.** Its deep-plum rail and floating fixed topbar made the workspace heavy; the topbar reserved vertical space and broke some views. Its nominal glass and neomorphism were too weak to carry a material point of view, producing a category-interchangeable SaaS shell. Pearl Ledger replaces that arrangement with clear glass navigation, in-flow context and tactile pearl surfaces. Do not reintroduce heavy dark navigation, fixed topbar offsets, generic card grids or decorative accent borders.
