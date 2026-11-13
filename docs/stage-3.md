# Stage 3 — UX and limited release preflight

13 November 2026. Local work only. Version and Stable tag remain **0.2.5**. No commit, push, SVN, deploy, tag or publication package was created. Production Ordin.pl was not accessed.

## Result and UX

The real dashboard was inspected in local Chromium at 1440px and 390px viewport widths. Widget height fell from **2608 to 1553px** on desktop and **2810 to 1682px** on the narrow screen (about 40%). Measurements include the real current-month fixture, with the native disclosures initially closed. Screenshots and JSON measurements are under ignored `.dev-tools/preflight/before-*` and `after-*`.

- All three SVG charts remain visible, with 180px drawing height instead of 220px and less space between charts. Text and exact keyboard values retain their font sizes. SVG coordinates and axis-label positions were adjusted with the height.
- A short net-sales definition stays visible. Detailed accounting semantics, status scope and snapshot information are in a native `details` disclosure.
- The bounded ten-order queue is initially collapsed under “Review matching orders (N)”. Its native summary works with keyboard Enter; expanding it exposes the original links and rows. It does not change order selection, threshold or saved preferences. Empty/error messages remain outside the disclosure.
- Removed the redundant period before the queue timezone separator at presentation time. No API metric or queue-query semantics changed.
- Original widget ID, WordPress position/visibility/collapse preferences and per-user operational preferences are retained. No automatic dashboard layout changes or new preference writes were introduced.

Price output was compared against the actual `wc_price()` result with the current WordPress locale **en_US**, and with both left/dot/comma and right-space/comma/space WooCommerce formatting configurations. Original store settings were restored in `finally`. The existing `zł137.00`-style output was correct for this store's English locale and configured currency position; a PLN currency code does not imply Polish formatting. A fully translated Polish language-pack UI was not tested.

## Security and standards

Plugin Check and PHPCS/WPCS were installed locally under ignored `.dev-tools/preflight`, without global tool installation. Plugin Check runs inside the isolated main sandbox. Composer plugins and scripts were disabled during tool installation. Production has no new library dependency.

- **Plugin Check: PASS**, final result “Checks complete. No errors found.” Scope: runtime plugin source; developer tools, tests, docs, Git files and PHPCS configuration excluded explicitly. The initial full-source scan correctly identified release hygiene issues: missing Tested up to, excess readme tags, and development dotfiles. Added the genuinely tested WordPress 7.1.4 metadata, reduced tags to five and added `.distignore`. No final release artifact was built or validated.
- **PHPCS 3.13.6 / WPCS 3.4.1: PASS**, zero errors and warnings using `phpcs.xml.dist` on runtime PHP. Formatting, PHPDoc and line endings were cleaned up. Template-local variables received the plugin prefix, including `$status`, which WPCS treats as a WordPress global. Functional regression was repeated after formatting.
- The ruleset explicitly recognizes WooCommerce's `view_woocommerce_reports`, `edit_shop_orders` and `edit_shop_order` capabilities; these are registered by WooCommerce, not misspelled core capabilities.
- Narrow, explained annotations cover the native WooCommerce status-policy hook and the existing bounded Analytics refund-ID integrity query. The SQL placeholders are generated exclusively from `%s` tokens; all dates/status values are passed to `prepare()` as an argument array. The static analyzer cannot infer the dynamic placeholder count. Direct SQL/no-cache advisories are genuine architectural observations, not SQL-injection findings: this lookup has no public API equivalent, returns at most 101 IDs and deliberately reads fresh state so a cache cannot mask missing refund imports. No blanket security-check exclusion was added.
- Review and tests confirmed capability/nonce checks before order access or preference writes, strict threshold/date/status validation, current-user-only preferences, private REST responses, DOM `textContent`, same-origin editor links and no customer contact fields. Native widget data/assets remain dashboard-scoped. No confirmed unresolved security defect was found in the present scope.

Raw tool reports and local verification logs remain under `.dev-tools/preflight/`; they are not publication content.

## Regression and compatibility

| Check | Result |
| --- | --- |
| Existing isolated PHP / mocked browser regression | PASS — 78 PHP and 21 Chromium checks |
| Real CPT data / operations / browser | PASS — 166 read/verify checks, 33 operational checks, 29 real browser checks |
| Real HPOS, posts synchronization disabled | PASS — 171 data checks, 33 operational checks, 28 real browser checks; no legacy order-post copy |
| HPOS zero-order Analytics | PASS — existing empty-store suite before seeding |
| Real desktop/narrow UX and keyboard disclosures | PASS — no horizontal overflow, charts still keyboard inspectable, queue expandable by Enter |
| Alternative configuration | PASS — PHP **8.2.35**, WP 7.1.4 / WC 11.3.0; native 7/30/90-day reports, queue and price-format smoke |
| Main tested configuration | WP **7.1.4**, WC **11.3.0**, PHP **8.3.36** |
| Upgrade identity/preferences simulation | PASS — same historical slug, main filename, text domain and widget ID; deactivate/reactivate retains dashboard order, hidden/closed state and layout; original settings restored |
| Syntax / whitespace | PASS — runtime PHP lint, JS syntax, `git diff --check` |

The upgrade check simulates the preferences stored by a 0.2.5 installation; it is **not** an end-to-end execution of the 2017 plugin on its historical WP/WC/PHP stack. The original tracked main file and widget identifier were inspected. WordPress stores scalar user options as strings, so the comparison uses the persisted pre-activation baseline, not PHP integer fixture types. No runtime preference-loss bug was found.

The original CPT fixture and sandbox were not reset or removed. A **separate** project `adwc-stage3-hpos`, with its own database/volumes, hosts HPOS checks at **http://127.0.0.1:38972**. The original review store remains **http://127.0.0.1:38971/wp-admin/index.php**. Both use synthetic users/data only. Extra PHP 8.2 testing used a one-off CLI container, not a change to the running main service.

Reproduction additions:

```powershell
$env:PLAYWRIGHT_BROWSERS_PATH = (Join-Path (Get-Location) '.dev-tools/browsers')
node tests/integration/ux.test.cjs
& './.dev-tools/php/php.exe' .dev-tools/preflight/vendor/bin/phpcs --standard=phpcs.xml.dist
docker compose -f tests/integration/compose.yaml run --rm cli wp plugin check advanced-dashboard-for-woocommerce --exclude-directories=.dev-tools,.git,tests,docs --exclude-files=.gitattributes,.gitignore,.distignore,phpcs.xml.dist
docker compose -f tests/integration/compose.yaml -f tests/integration/php82.yaml run --rm cli wp eval-file wp-content/plugins/advanced-dashboard-for-woocommerce/tests/integration/preflight.php
```

Existing stage 2 suites are preserved. `browser.test.cjs` accepts `ADWC_TEST_ORIGIN` and `ADWC_TEST_PROJECT` for the additional sandbox. Set them to `http://127.0.0.1:38972` and `adwc-stage3-hpos` for HPOS, and unset them for the original store. Native queue visibility assertions now explicitly open its disclosure.

## Remaining limits and release checklist

No unresolved functional FAIL remains. **NOT RUN:** historical old-stack upgrade, minimum advertised WP 6.2 / PHP 7.4 combinations, multisite, full screen-reader audit, high-volume stores, actual third-party currency/import extensions and the final publication artifact.

The code is ready for **local visual review**, not approved for publication. Before a release:

- [ ] Decide the supported minimum WP/WC/PHP versions from actual tests; the current minimum metadata predates this limited matrix.
- [ ] Decide whether/when to add the HPOS compatibility declaration. Both storage modes passed in this matrix; no universal claim or declaration was added.
- [ ] Accept the limited upgrade simulation or run a historical-stack upgrade rehearsal if that is a release requirement.
- [ ] Perform human visual/accessibility acceptance, including translated locale UI if required.
- [ ] Review `.distignore`, build a candidate outside this task, and rerun Plugin Check on that candidate; source-tree exclusions are not proof of package hygiene.
- [ ] Only after release approval choose a new Version/Stable tag, changelog, tag and publication procedure. These remain untouched here.

No acceptance decision is needed to use the local improvements. Release metadata/support policy and publication require a separate decision; no next stage was started.

## Changed files in stage 3

UX: `assets/dashboard.js`, `assets/dashboard.css`, `templates/dashboard.php`.

Standards/readability: main PHP and all four `includes/class-adwc-*.php` classes (formatting, comments, explicit equivalent ternary, scoped analyzer annotations); `readme.txt` (five tags and verified Tested up to; Version/Stable tag unchanged).

Added: `.distignore`, `phpcs.xml.dist`, this document, `tests/integration/ux.test.cjs`, `preflight.php`, `php82.yaml`, `hpos-preflight.yaml`. Updated `tests/integration/browser.test.cjs` for disclosures and optional sandbox targeting. Earlier stage 1/2 uncommitted edits/deletions remain intact.
