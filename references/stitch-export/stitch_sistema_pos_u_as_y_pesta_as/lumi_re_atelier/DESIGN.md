# Design System Specification: Editorial Elegance for Professional Wellness

## 1. Overview & Creative North Star: "The Curated Canvas"
The North Star for this design system is **The Curated Canvas**. This is not a utility-first interface; it is an editorial experience that treats appointment scheduling and salon management with the same reverence as a high-end fashion magazine. 

We break the "SaaS Template" look by rejecting rigid, boxed-in layouts. Instead, we utilize **intentional asymmetry**, allowing high-quality imagery to bleed to the edges, and leveraging "white space" as a functional element rather than a void. The UI should feel like a series of physical layers—fine linen paper, frosted glass, and polished rose gold—stacked with surgical precision.

---

## 2. Color & Tonal Architecture
The palette is built on a foundation of warmth and skin-toned neutrals, punctuated by the "Deep Charcoal" (`on-surface`) for absolute legibility and authority.

### The "No-Line" Rule
**Explicit Instruction:** Designers are prohibited from using 1px solid borders to define sections. Sectioning must be achieved through background shifts (e.g., a `surface-container-low` panel sitting on a `surface` background) or vertical rhythm.

### Surface Hierarchy & Nesting
Instead of a flat grid, use the surface tiers to create "nested" depth:
- **Base Layer:** `surface` (#fcf9f8) - The primary canvas.
- **Secondary Panels:** `surface-container-low` (#f6f3f2) - For sidebar or auxiliary navigation.
- **Interactive Cards:** `surface-container-lowest` (#ffffff) - To create a subtle "pop" against the cream base.
- **Deep Modals:** `surface-container-highest` (#e4e2e1) - Reserved for heavy contrast interactions.

### The "Glass & Signature" Rule
- **Glassmorphism:** For floating action buttons or over-image navigation, use `surface` with 70% opacity and a `20px` backdrop-blur. 
- **Signature Gradients:** Use a subtle linear gradient (135°) from `primary` (#7d562d) to `primary-container` (#eab786) for high-impact CTAs to mimic the lustrous sheen of rose gold.

---

## 3. Typography: The High-Contrast Dialogue
This system thrives on the juxtaposition of a timeless Serif and a hyper-modern Sans-Serif.

*   **The Voice (Serif - Noto Serif):** Used for `display` and `headline` scales. This provides the "High-End Salon" authority. Use it for client names, service titles, and big analytical numbers.
*   **The Engine (Sans-Serif - Manrope):** Used for `title`, `body`, and `label` scales. Manrope’s geometric clarity ensures that dense scheduling data remains readable and feels "tech-forward."

**Editorial Tip:** Use `display-lg` for daily revenue or total bookings, but track the letter-spacing to `-0.02em` to keep it feeling tight and premium.

---

## 4. Elevation & Depth: Tonal Layering
Traditional shadows are often too "dirty" for a beauty-focused UI. We use **Tonal Layering** to convey hierarchy.

- **The Layering Principle:** Place a `surface-container-lowest` (#ffffff) card on a `surface-container-low` (#f6f3f2) section. The delta in luminance creates a soft, natural lift.
- **Ambient Shadows:** When an element must float (like a popover), use a "Rose Glow" shadow: `0px 12px 32px rgba(124, 83, 87, 0.06)`. This uses the `secondary` token color to ensure the shadow feels like warm ambient light.
- **The Ghost Border:** If accessibility requires a stroke, use `outline-variant` at 20% opacity. Never use a 100% opaque border.

---

## 5. Component Styling

### Buttons
- **Primary (The Rose Gold):** Gradient fill (Primary to Primary-Container). Roundedness: `md` (0.375rem). No shadow, but a 1px inner-glow in `on-primary-fixed` at 10% opacity.
- **Secondary (The Minimalist):** Transparent fill with a `ghost-border` of `outline`. Text in `secondary`.
- **Tertiary:** Text-only in `secondary`, all-caps, with `0.05em` letter-spacing.

### Input Fields
- **Styling:** Forgo the 4-sided box. Use a `surface-container-highest` bottom-border (2px) on a `surface-container-low` background. 
- **States:** On focus, the bottom border transitions to `primary` (Rose Gold).

### Cards & Lists (The Editorial Feed)
- **Constraint:** Forbid the use of divider lines. 
- **Pattern:** Separate list items using a `16px` vertical gap. Use a `surface-container-low` background on hover to define the interaction zone.
- **Imagery:** Every card (e.g., a service or staff profile) must feature a high-aspect-ratio image with `xl` (0.75rem) rounded corners.

### Specialized Components
- **The Service Carousel:** A horizontally scrolling list of services using `surface-container-lowest` cards, featuring overlapping `display-sm` typography for the price.
- **The Status Chip:** Use `secondary-container` (#fdc7cb) with `on-secondary-container` text for "Confirmed" statuses. The contrast is soft, not jarring.

---

## 6. Do’s and Don’ts

### Do:
- **Do use asymmetrical margins.** Allow a photo of a lash set to sit 24px from the left, while the text sits 48px from the left.
- **Do use "Surface Dim" for inactive states.** It feels more intentional than a simple "greyed out" opacity.
- **Do embrace white space.** If a screen feels "empty," it’s working. Luxury is the luxury of space.

### Don’t:
- **Don’t use black (#000000).** Use `on-surface` (#1b1c1c) for deep charcoal contrast.
- **Don’t use "System" shadows.** Avoid any shadow that looks "heavy" or "muddy."
- **Don’t use standard dividers.** If you need to separate content, use a `32px` or `48px` whitespace jump from the spacing scale.
- **Don't use `full` roundedness for everything.** Keep `full` (9999px) for pill-chips only; use `md` or `lg` for structural elements to maintain a professional, architectural feel.