# Implementation verification

## Scope

- Administrative fidelity content is inside the original `ConfigurationLayout`. The existing `.app-sidebar` and its permission-aware loyalty link remain in place; no alternate administrative shell is shipped.
- Customer access, home, rewards and profile use the approved Stitch typography, glass materials, spacing, imagery and three-destination navigation. Source HTML is frozen alongside this file.
- Real account data replaces synthetic names, balances and rewards. Availability and redemption limits come from the application. Stock portraits are not substituted for the customer's identity. Reference QR placeholders are replaced with a genuine expiring QR; redemption delivery continues to be confirmed by staff.

## Checks

- `npm run build`
- `npm run typecheck`
- ESLint on the changed customer and loyalty components
- `php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit --filter 'Customer|Loyalty'`: **13 tests, 116 assertions passed**. The CLI driver is enabled for this process only; no global PHP configuration or production database is changed.
- `node scripts/review-loyalty-ui.mjs`: real customer/admin login, QR generation, profile editor, isolated field submission, QR expiry/regeneration failure, reward eligibility and filtering, administrative dialog and tabs.
- Browser checks at **320, 768 and 1440px**: no horizontal document overflow. No JavaScript errors or missing production assets. A simulated 503 response is intentional for the QR failure-state test.
- Captures at the reference **390 × 884px** are saved in `docs/screenshots/loyalty-stitch/`. Visual fixtures modify intercepted browser responses only; they do not seed or alter the database.

## Known inherited security constraint

The previously requested email-only password replacement remains inherently unsafe: knowing an account's email is not proof of ownership. This design task does not make that earlier flow safe for public deployment.

## Reference accessibility constraint

The approved reference uses small white text over the rose login button; the light end of that original gradient does not meet AA text contrast. It was retained for fidelity rather than silently claiming an accessibility pass. Focus visibility, form labels, errors, reduced motion and native dialog keyboard behavior are preserved.
