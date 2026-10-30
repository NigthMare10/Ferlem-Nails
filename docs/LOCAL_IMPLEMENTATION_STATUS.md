# Local implementation and validation

Initial implementation was local-only. The follow-up request explicitly authorizes push to `main` and deployment after validation; see the release notes below for the actual outcome.

## Baseline

- Git starting revision: `12e06cd` (clean working tree).
- Read-only hosting source snapshot imported: operational modules absent locally.
- No production secrets, database contents or customer uploads imported.
- Snapshot location: approved OpenCode temporary directory, `lemus-source-review`.

## Implemented locally

- Service-level fixed/percentage discounts, reasons, server-side calculations, price-change confirmation checks and receipt detail.
- Service/promotion catalog separation and configurable points per service.
- Employee presentation order, recurrent meals/date exceptions, calendar visibility and availability validation.
- Additional per-user permissions without changing roles; effective inherited permissions and audited owner-only grants.
- Service editing for scheduled appointments, preserved historical prices, duration/assignment/conflict validation and stale-form protection.
- Separate customer registration/login/recovery/verification, profile, private re-encoded photo, favorites, linked appointments and history.
- Loyalty points ledger, unique sale-item awards, reversals, tier progression, reward reservations, stock, expiration and delivery confirmation.
- Temporary QR identification, pending-appointment association, post-payment awards, camera lifecycle handling and manual-code fallback.
- Responsive adjustments, modal/scroll lifecycle protections, lazy page/QR/camera loading and availability query reduction.

## Automated validation

- **301 PHP tests, 2,702 assertions, zero failures/errors/skips** on PHP 8.4 and isolated in-memory SQLite.
- **16 Playwright tests passing**, Chromium desktop and mobile emulation, including a 320/360/390/430/768/1024/1440 px matrix.
- TypeScript validation and Vite build passing.
- Composer and npm audits: no known vulnerability advisories reported after compatible dependency updates.
- Windows QA/test helpers validated; they select a PHP runtime with the required image and SQLite capabilities. Default XAMPP PHP 8.2 is not used.
- No tests were disabled to obtain a green result. Older expectations were reconciled with the imported production contracts: employee notifications, expense reports, individual appointment segments and no rescheduling after a booking begins. New security tests verify the stricter shared-checkout permission and customer isolation.

## Review boundaries

This is **not a production release approval**. Real-device camera/scroll checks, Safari, actual MySQL lock contention, real email delivery and pixel-level design acceptance remain outside the local automated validation. The customer profile follows the reference composition but does not embed its example customer's photographs.

See [LOCAL_QA_GUIDE.md](LOCAL_QA_GUIDE.md) for accounts, startup, manual review, business rules, implementation paths and deployment prerequisites. No deployment was performed.
