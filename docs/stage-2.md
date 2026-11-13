# Stage 2: real integration and operational queue

13 November 2026. Local changes only; public version/stable tag remain **0.2.5**. No commit, push, SVN, deployment, publication or production-store access. Stage B started only after a positive real WP/WC integration result.

## Local review

The isolated store is running at **http://127.0.0.1:38971/wp-admin/index.php**.

- Synthetic administrator: `adwc_admin`; password: `adwc-local-review-only`.
- Synthetic shop manager: `adwc_manager`; same local-only password.
- Only synthetic products, order/refund records and test users are present. No production customer data was copied.
- Docker project `adwc-stage2-local`, database `adwc_stage2_isolated`, dedicated `database`, `wordpress`, `woocommerce` volumes and a dedicated network. No database port is published; web traffic is bound to `127.0.0.1:38971` only.
- The plugin is mounted from this workspace read-only, so local file edits are visible in the sandbox. WooCommerce runs from its own project volume. WordPress outgoing HTTP, automatic updates, WP-Cron and mail are disabled in this sandbox. Its network configuration and services do not modify other Docker projects.
- The final review fixture is in **CPT mode**, with 54 synthetic original orders and two refund records. Custom status `wc-needs-review` is provided by the sandbox MU plugin. The normal queue defaults to processing/on-hold/pending; the test administrator also selected the custom status.
- Start again with `./tests/integration/start.ps1`. Stop without deleting test data using `docker compose -f tests/integration/compose.yaml stop db wordpress`. Do not run fixture scripts against another installation.

The widget retains its original WordPress ID and placement/visibility preferences. It may appear below existing widgets; it can be moved using the normal dashboard controls. No layout preference is forced by the plugin.

## Stage 1 repairs

1. **In-process REST values are not JSON-decoded arrays.** The real Analytics response retained `stdClass` interval buckets and subtotals, causing the old normalizer to throw. Known object levels are now converted before validation; the original isolated suite gained a regression check for the real shape.
2. **Analytics namespaces are lazy-loaded.** Inside the plugin's HTTP namespace, the initial route registry did not yet contain `wc-analytics`. The early availability check incorrectly withheld valid reports. A native in-process `OPTIONS` request now loads the WooCommerce route/schema if needed, without running an aggregate query. The provider still verifies support for `date_type=date_created` before querying.

Actual query parameters and independent synthetic day totals were checked with the store's `woocommerce_date_type` deliberately set to **date_paid**. The plugin explicitly passes **date_created** and leaves the merchant's setting unchanged.

The actual three metrics were verified: `net_revenue` includes fees and reflects discounts/refunds, excludes shipping and taxes; `orders_count` excludes refund records but includes original orders in Analytics' included statuses; `num_items_sold` follows Analytics' line/refund quantities. Full and amount-only refunds were exercised. Refund adjustments use refund creation day, not the parent's creation day. Store-local midnight, end-of-day, spring/autumn DST and year boundaries were included.

Availability/currency checks were exercised with genuine zero data, pending imports, disabled Analytics, current and stale scheduled checkpoints, mixed-currency orders and an excluded foreign-currency order. A cancelled/excluded EUR order did **not** block valid PLN charts. Checks remain conservative for global import failures and extension-specific exclusions; they cannot certify every historical amount or replace an import audit. No order scan or financial fallback was added.

## Operational behavior

- A separate authenticated `GET /advanced-dashboard-for-woocommerce/v1/operations` loads independently of chart date filters and Analytics availability. Three global counters use `wc_orders_count()` for processing, on-hold and pending.
- The queue uses **one `wc_get_orders()` query**: original orders only, selected registered statuses, `date_created < now − threshold`, ascending creation date, `limit=10`, `paginate=false`. The response is capped again at ten even if an extension expands query results. There is no history enumeration, pagination total, metadata scan, custom SQL, new table or cron.
- Each row includes only number, status, store-local creation date, elapsed age and `WC_Order::get_edit_order_url()`. HPOS and CPT editor links were opened in actual wp-admin. No customer names, email addresses, addresses, payment information or line items are returned.
- `edit_shop_orders` is required before any queue/count access; each row also checks `edit_shop_order` for its ID. If order-level capabilities hide rows among the first ten candidates, the list can contain fewer than ten; the plugin never scans further to fill it. Empty messaging refers to editable matching orders.
- Age is elapsed time from **creation**, never modification time or inferred time in status. The UI states that an older order is not automatically incorrect or late. Counts cover all ages and are separate from the age-filtered queue.
- Default threshold **72 hours**; accepted values are whole hours **1–8760**. Selected statuses default to processing/on-hold/pending. Registered custom statuses can be explicitly included. Standard completed/cancelled/refunded/failed/draft/trash statuses are excluded from the open-queue selector. At most twenty statuses can be selected; invalid/removed saved statuses are safely filtered on read.
- `POST /advanced-dashboard-for-woocommerce/v1/operations/preferences` requires the current cookie session, `wp_rest` nonce, capability and strict value/status validation. It writes only the current user's **site-specific** `adwc_operational_preferences` option through `update_user_option(..., false)`. No target user ID is accepted. Other dashboard preferences are untouched. The panel never writes orders.
- Production UI uses native HTML/JS/CSS and the existing local SVG runtime. Error/status regions, labelled controls, keyboard access and scoped responsive styles remain. Number/status/error values are inserted using `textContent`; edit URLs are restricted to same-origin HTTP(S) before DOM assignment.

## Verification results

Tested stack: **WordPress 7.1.4, WooCommerce 11.3.0, PHP 8.3.36, MariaDB Docker image `mariadb:lts`**, local Chromium 145 via Playwright. All fixtures are synthetically created through WP/WC APIs inside the supplied sandbox. The PHP integration scripts refuse writes unless CLI, `ADWC_INTEGRATION_SANDBOX`, the exact isolated database name and the local environment type match.

| Suite | CPT | HPOS (posts sync disabled) |
| --- | --- | --- |
| Real chart/data integration, populated | PASS — 169 checks | PASS — 171 checks, including no legacy order post copy |
| Real empty Analytics | PASS — 3 checks | PASS — 3 checks |
| Real operational/preferences integration | PASS — 33 checks | PASS — 32 checks; same user-option API, before the extra submitted-user-ID assertion was added |
| Real empty queue/counters | PASS — 10 checks | PASS — 10 checks |
| Real browser/HTTP/chart/panel/editor | PASS — 29 checks, including absent WooCommerce and an operational queue with disabled Analytics | PASS — 27 checks; absent WooCommerce additionally passed in the earlier 20-check stage A browser run |

Additional regression: **78 isolated PHP checks** and the **original 21 mocked Chromium checks**. All original 56 PHP + 21 browser assertions remain. PHP/JS syntax and `git diff --check`: PASS. Test selector/fixture corrections were made where adding a second form/disclosure invalidated overly broad selectors; the original behavior assertions were retained.

Security checks covered administrator/shop manager/subscriber/report-only roles, missing/invalid nonces, invalid thresholds/statuses, per-user preference isolation, ignored submitted target-user IDs, customer-data omission and HTML/JS injection through synthetic order numbers/custom status labels. The operational panel remains usable when chart Analytics is unavailable. A recent synthetic order modification did not change its creation age, and panel reads/preferences did not modify order creation/modification times.

Measured local queue requests with 54 original orders: HPOS **29 SQL statements / about 7 ms SQL**, CPT **18 statements / about 5 ms SQL**, including hydration/counters/permissions. Empty queues used 2–3 statements. These are local `SAVEQUERIES` observations, not full-request benchmarks or million-order performance claims. The queue selection itself was checked to contain `LIMIT 10`. Native WooCommerce caches are retained.

No unresolved functional FAIL remains in the final runs. Initial integration failures were the two repaired stage 1 issues above; later synthetic-tax fixture and browser-selector errors were corrected in the tests.

**NOT RUN / limits:** other WP/WC/PHP versions, multisite integration, real third-party currency/import/order-number extensions, very large stores, full assistive-technology audit and a production release/migration matrix. Both authoritative storage modes were tested, but no universal HPOS claim or compatibility declaration was added.

## Reproduction

From this repository, after `./tests/integration/start.ps1`:

```powershell
$compose = 'tests/integration/compose.yaml'
$fixture = 'wp-content/plugins/advanced-dashboard-for-woocommerce/tests/integration'
# Fresh fixture in the currently selected, explicitly isolated storage mode:
docker compose -f $compose run --rm cli wp eval-file "$fixture/stage-a.php" reset
docker compose -f $compose run --rm cli wp eval-file "$fixture/stage-a.php" empty
docker compose -f $compose run --rm cli wp eval-file "$fixture/stage-a.php" seed
docker compose -f $compose run --rm cli wp eval-file "$fixture/stage-b.php"
$env:PLAYWRIGHT_BROWSERS_PATH = (Join-Path (Get-Location) '.dev-tools\browsers')
node tests/integration/browser.test.cjs --operations --without-wc
& '.\.dev-tools\php\php.exe' tests/run.php
node tests/dashboard.test.cjs
```

To exercise the other mode, first run the guarded `reset` in the **current** mode, then set `woocommerce_custom_orders_table_data_sync_enabled` to `no` and `woocommerce_custom_orders_table_enabled` to `yes` (HPOS) or `no` (CPT) through this compose project's CLI, then rerun `empty`/`seed`/operational/browser suites. Never switch authority on an unrelated store or delete anything outside the marked synthetic fixture. Run `stage-b.php empty` after `reset` to verify a zero-order queue (requires a prior positive stage A result).

The fixture seed also creates `adwc_manager` and `adwc_subscriber`. Operational tests add `adwc_report_only`. An intentionally hostile display-number filter is enabled by the operational test for browser escaping checks; remove its synthetic option after testing using this local CLI: `wp option delete adwc_synthetic_xss_number`. This does not alter orders.

## Files changed during stage 2

- Runtime modified: `pp-woocommerce-advanced-dashboard.php`, `includes/class-adwc-plugin.php`, `includes/class-adwc-analytics.php`, `templates/dashboard.php`, `assets/dashboard.js`, `assets/dashboard.css`.
- Runtime added: `includes/class-adwc-operations.php`.
- Verification modified: `tests/run.php`, `tests/stubs.php`, `tests/render-fixture.php`, `tests/dashboard.test.cjs`.
- Integration added: `tests/integration/compose.yaml`, `sandbox.php`, `start.ps1`, `stage-a.php`, `stage-b.php`, `browser.test.cjs`.
- Docs: added this file; updated `docs/stage-1.md` as a historical record and `readme.txt` with the operational section and limited integration results.
- No further runtime files were deleted in stage 2. The uncommitted stage 1 deletions remain intact. Tools, downloaded WooCommerce, logs and screenshots remain ignored under `.dev-tools/`.

Stop here: release work, expanded integrations and further operational features are outside this stage.
