# Customer motion and reward lifecycle — 2026-10-30

## Latest refinement: continuous motion, clipping and QR pass

This follow-up supersedes the earlier finite-only decoration notes below. Customer ambient light, balance/QR-button reflections and the QR perimeter now loop while visible. The pause/resume control persists locally; reduced motion, hidden-tab suspension and offscreen reflection pausing remain supported. The QR itself never rotates, pulses or receives an overlay.

- Corrected narrow header layouts, wrapping reward categories, summary heading/amount layout, reward metadata, smallest-screen card layout and long profile fields/edit controls. The QR dialog scrolls within the dynamic viewport, with 44px close controls and a static, high-resolution code with a four-module quiet zone.
- `node scripts/review-customer-continuous.mjs`: response-only long-name/reward fixtures at 320, 390 and 768px passed clipping/overflow checks. Offscreen reflections pause. The real locally issued QR decoded correctly with ZXing; the dialog remained inside a 320×568 viewport, including an expanded alternative token; Escape closes it. Results/screenshots: `continuous/`.
- Existing visual/interaction review passed, including 320/768/1440px customer/admin overflow checks, profile edits, QR expiry/error recovery, eligibility and filters. Administrative sidebar remains unchanged.
- Browser lifecycle tests still passed, now explicitly verifying persistent pause/resume and continuous effects rather than incorrectly requiring every animation to finish.
- Latest fresh-context 4× CPU sample ran for 7.2 seconds: **5 continuous animations**, **16.7ms median**, **33.4ms p95**, **50.1ms max frame**, **no observed long tasks**, and **no unfinished finite entrance animations**. See `browser-performance.json`. This is simulation, not a physical low-end phone guarantee.
- Typecheck, targeted ESLint and production build passed. Font runtime-path warnings remain as documented below.
- Latest static Impeccable pass: **0 primary findings**, **92 advisories** (51 color, 35 type, 6 radius), reviewed against the approved customer palette and the explicit animated-glass request. Artifact: `continuous/detector.json`. No administrative visual changes or backend lifecycle changes in this follow-up.

## Delivered

- Native, finite customer animations: reflected glass light, balance count-up, membership progress, staggered entrances, touch feedback, reward filtering/unlocking, QR entrance and reservation confirmation.
- No added animation library. Content remains visible without animation; reduced motion and hidden-tab suspension are supported.
- Retry UUID reuse and successful-operation debounce prevent duplicate reservations after a lost response or double tap.
- Complete active reservations plus paginated historical canjes; partial refresh avoids unused catalogs and duplicate history payloads. Added lifecycle lookup indexes, applied locally on MySQL.
- Correct exact QR expiry boundary, late-confirmation committed expiry/refund, late-cancellation status and closed-operation replay messaging.

## Reservation flow

1. Reserving deducts the reward's points and stock once, records a snapshot and holds the reservation for 24 hours. Qualifying membership points are unchanged.
2. The customer sees it in **Mis Canjes Activos**. Staff sees it in **Fidelidad y recompensas → Historial de canjes**, including the pending filter, and in the customer's administrative record.
3. Staff confirmation moves it to confirmed/history with no second deduction. Confirmation is final; ordinary cancellation does not refund a delivered reward.
4. Cancellation before expiry or expiry returns the original points and stock once, frees the usage limit and retains the historical record. Late cancellation is recorded as expired.
5. Visible customer pages refresh at expiry and periodically for staff changes; hidden tabs do not poll. Scheduled `loyalty:expire` handles unattended expiry every five minutes when the deployment scheduler is actually running.

## Verification

- Full PHP suite: **416 tests, 3,827 assertions passed**. After the final history-metadata-only adjustment, focused lifecycle suite: **31 tests, 230 assertions passed**.
- Coverage includes debit/refund idempotency, replayed tokens, customer isolation, manipulated request fields, insufficient/negative points, stock, customer limits, birthday/date restrictions, Honduras date boundaries, reward edits/snapshots, delivery, cancellation, exact expiry, batch expiry beyond 100 rows, history pagination and strict QR expiry.
- `node scripts/test-customer-mobile.mjs`: disconnected retry/UUID reuse, double taps, automatic expiry/refund/history, staff confirmation refresh, hidden-tab suspension and reduced motion passed. Fixture responses do not mutate customer balances.
- `node scripts/review-loyalty-ui.mjs`: customer access/home/rewards/profile/QR reviewed; filters, eligibility, single-field profile editing and expired-QR/error states passed. No application JavaScript errors. Expected mocked QR HTTP 503 is intentional.
- Customer/admin responsive checks at 320, 768 and 1440px reported no horizontal document overflow; original administrative sidebar retained. Captures: `docs/screenshots/loyalty-stitch/`.
- Fresh Chromium context, 390×844 touch viewport, 4× CPU slowdown: median frame **16.7ms**, p95 **33.3ms**, max **33.4ms**, no observed long tasks and no animations left running after the sequence. See `browser-performance.json`. This is a local simulation, not proof of physical-phone performance or network/load latency.
- Typecheck, targeted ESLint and production build passed. Vite warns that absolute `/fonts/stitch/*.woff2` paths are left for runtime resolution; all 18 local font files exist and browser review reported no unexpected resource failures. Deploy the public font assets with the build.
- Impeccable detector completed on customer components/layout/pages and motion CSS: **0 primary findings**, **147 advisories** (49 type, 91 color, 7 radius). These concern the approved customer Stitch token world versus administrative frontmatter; reviewed without restyling the approved surfaces. `detector.json` preserves the findings. Static detection is not a full contrast/screen-reader audit.

## Remaining operational/security limits

- **Critical:** the previously requested email + new password + confirmation reset has no identity verification. Anyone who knows a customer's email can take over that account. It remains unchanged per the explicit prior decision, but should not ship publicly without verified recovery.
- Production cron/task scheduler is not installed or verified here. `php artisan schedule:list` confirms the five-minute expiry definition, not execution in production. Do not start the full scheduler casually: it also runs payroll and daily close tasks.
- Reward discounts/services are still applied manually at checkout; confirming delivery does not automatically apply a discount to a sale.
- No automatic staff notification for a new reservation; staff must consult the loyalty area/customer record.
- No physical low-end phone validation or genuine concurrent MySQL load/locking test. Sequential stock/idempotency tests do not establish production concurrency performance.
- Changes are local, uncommitted and unpushed. Back up production data before applying the new index migration.
