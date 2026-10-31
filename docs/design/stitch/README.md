# Approved Stitch references

- **Admin:** only the content of Fidelidad y recompensas is reproduced. The application's existing sidebar is not replaced.
- **Customer:** Inicio, Recompensas, Mi perfil, login, registration and password form use their complete approved visual identity.
- Names, balances, limits, rewards and dates in the HTML are synthetic reference data, not database seeds.
- Dynamic values, permission checks and server validation are binding. QR images are generated locally from expiring authenticated identification tokens.
- `resources/css/stitch/*.css` contains compiled and scoped utility styles plus the original glass treatments. The app never executes the exported inline scripts or loads Tailwind from a CDN.
- Images are frozen in `public/images/stitch/`. Stock portraits are not used for actual client identity.
- Fonts are served locally from `public/fonts/stitch/`, with their licenses, to match the reference without relying on a third-party font request at runtime.

Run `node scripts/review-loyalty-ui.mjs` against the local app on port 8010 to capture reference and real screenshots, test customer login, QR generation and the profile editor. Visual fixtures are injected into browser responses only; they never change the database.
