# Stage 1 implementation record

Local development work, 13 November 2026. The public version and stable tag remain **0.2.5**. This is not a published release. No commit, push, deployment, store migration or data cleanup is part of this change.

**Historical record:** the NOT RUN integration results below describe the end of stage 1. Stage 2 subsequently ran real CPT and HPOS tests, repaired in-process REST object normalization and lazy Analytics route loading, and added the operational queue. See [stage-2.md](stage-2.md) for the current result.

## Preserved installation identity

- Slug/text domain: `advanced-dashboard-for-woocommerce`.
- Main plugin file: `pp-woocommerce-advanced-dashboard.php`.
- Dashboard widget ID: `advanced-dashboard-admin-widget`.
- No activation/uninstall migration, option deletion or user preference writes. WordPress continues to own widget placement, visibility and collapse state.
- The plugin remains on `/wp-admin/index.php`. It does not register a separate WooCommerce screen or force a dashboard column layout.

## Data contract

The authenticated plugin endpoint is `GET /advanced-dashboard-for-woocommerce/v1/summary`. It requires `view_woocommerce_reports` and a current `wp_rest` nonce. Operational counts additionally require `edit_shop_orders`. The response contains only daily aggregates, localized labels, data state and optional operational counts. Errors do not include exceptions, SQL, paths or order/customer records. Financial responses use `Cache-Control: no-store, private`.

The provider makes an **in-process REST request** to `/wc-analytics/reports/revenue/stats`, preserving the current user and WooCommerce permissions. The actual registered route must accept `date_type=date_created`; otherwise the widget reports unavailable Analytics. It requests day buckets, ascending dates, one page of 100 buckets and only the three required measures. No remote HTTP call is needed at runtime.

- `net_revenue`: WooCommerce Analytics net sales, after discounts and refund effects, without tax or shipping; order fees remain included in the Analytics definition. This is neither cash received nor a profit measure.
- `orders_count`: original order records, excluding refund records. A fully refunded order remains counted if its current status is included by Analytics settings.
- `num_items_sold`: net line-item quantity from Analytics, including its refund adjustments. Amount-only refunds do not necessarily contain returned quantities. Full-refund allocation follows the installed WooCommerce version.
- All order buckets use **order creation date**, regardless of the merchant's separate Analytics payment/completion-date preference. Refund adjustments use **refund creation date**, even if the parent order predates the selected range.
- Included statuses follow WooCommerce Analytics excluded-status settings and its excluded-status filter. The active included statuses are shown below the report. Pending, failed and cancelled are excluded by the built-in defaults; processing, on-hold, completed and refunded are included unless settings change.
- Presets include today. Boundaries are inclusive local `00:00:00`–`23:59:59`, using `wp_timezone()` and immutable calendar-day arithmetic. Custom ranges are limited to **90 inclusive days**, cannot be reversed or future-dated and use strict ISO calendar dates. Missing days are filled only after a structurally valid report reconciles with its totals.
- Values use `wc_price()` with the store currency and store formatting; HTML is stripped before serialization. Daily/total quantities use localized formatting. The financial axis names the currency explicitly.

API signatures and definitions were checked against the current stable **WooCommerce 11.3.0** source, not just old Reports documentation:

- [Revenue statistics controller](https://github.com/woocommerce/woocommerce/blob/11.3.0/plugins/woocommerce/src/Admin/API/Reports/Revenue/Stats/Controller.php)
- [Order statistics store](https://github.com/woocommerce/woocommerce/blob/11.3.0/plugins/woocommerce/src/Admin/API/Reports/Orders/Stats/DataStore.php)
- [Reports import status](https://github.com/woocommerce/woocommerce/blob/11.3.0/plugins/woocommerce/src/Admin/ReportsSync.php)
- [Scheduled/import failure handling](https://github.com/woocommerce/woocommerce/blob/11.3.0/plugins/woocommerce/src/Internal/Admin/Schedulers/OrdersScheduler.php)
- [Analytics status settings](https://github.com/woocommerce/woocommerce/blob/11.3.0/plugins/woocommerce/src/Internal/Admin/Settings.php)

These are internal WooCommerce surfaces and may evolve. Availability checks are deliberate. Older installations without the required route show an explanation instead of silently changing the report's date semantics.

## Integrity and performance limits

Analytics does not expose a trustworthy currency dimension in this aggregate endpoint. The widget therefore **withholds all chart totals for mixed or unknown currencies**, rather than combining them or pretending to convert them.

For the selected date range, two bounded-result `wc_get_orders()` queries compare all included original order counts with store-currency counts and the Analytics count. They request one ID plus pagination totals, not order objects for an entire history. A further bounded query checks up to 100 refund records and their parent currencies, including parents outside the range. More refund records require a shorter range. No order enumeration is used to calculate financial or quantity aggregates.

Because the endpoint lacks a refund-record count, one prepared, range-limited **read of IDs only** from `wc_order_stats` reconciles the refund set (maximum 101 IDs). It is not a second source of financial totals. This is the plugin's only direct SQL. It never reads `posts` or `postmeta`; the source checks use the authoritative WooCommerce CRUD store. Lookup failures withhold the report and restore database error handling.

Detected historical-import coverage gaps, incomplete/running imports, recorded failed imports, scheduled-sync checkpoint gaps, source/Analytics count differences, missing or stale refund IDs, missing tables and malformed responses never become a false zero. Scheduled synchronization checks both the modified-date and ID checkpoint, including updates to older orders. Third-party test-order/import exclusions may produce a conservative unavailable state when the source count differs.

**Limits of freshness checks:** matching counts and import state cannot prove every historical amount is current. Same-count edits, external direct writes and extension-specific import/conversion filters need real integration validation. The UI identifies an Analytics snapshot and warns that recent changes may lag. No history scan, automatic import/repair, extra cron job or custom financial cache is introduced. Aggregates use WooCommerce's own report cache and invalidation; the widget loads only when visible and open. Query cost still depends on the store and its storage/indexes; high-volume profiling is pending.

## Presentation and security

- Three responsive SVG charts, authored locally without a chart dependency or application framework. Separate axes avoid mixing money with counts. Negative refunds and flat zero series are supported.
- Each chart has one keyboard stop; Left/Right and Home/End expose exact dates and values. A native disclosure contains a semantic daily table. Live status/error text, visible focus, touch/pointer selection, forced-color handling and reduced information density at narrow widths are included. No animation.
- Every CSS selector is scoped to the preserved widget ID. Assets are enqueued only for an authorized main-dashboard user with WooCommerce available. Other admin screens receive no widget data or assets. There is no `admin_head` output.
- PHP escapes attributes/HTML/URLs at output. JS uses DOM creation and `textContent`, not injected HTML. Endpoint date fields are validated against fixed presets and strict calendar dates before queries run. WooCommerce absence creates only an administrator-facing dashboard notice, without front-end output.
- PHP 7.4+ / WordPress 6.2+ are the working minimums. Execution tests below used PHP 8.3. No broader PHP or WordPress version matrix has been run.

## Verification record

| Check | Result | Scope |
| --- | --- | --- |
| PHP syntax | PASS | Main file, includes, template and test scripts, PHP 8.3.36 |
| JS syntax / whitespace | PASS | `node --check`, `git diff --check` |
| PHP behavior | PASS | 56 checks against API doubles: missing WooCommerce, capabilities, nonce, dates/DST/leap/year boundary, empty/negative data, imports, currency, refunds, redaction, escaping, asset scope |
| Chromium UI | PASS | 21 checks: three SVGs, plain REST permalinks, exact localized values, keyboard inspection, 360px layout, form, empty/error/unavailable responses, hidden/collapsed loading, locale fallback, no external requests or browser exceptions |
| Visual inspection | PASS | Desktop and 360px screenshots of the real view with a deterministic fixture and minimal wp-admin-like host CSS |
| Live WordPress/WooCommerce REST + database | NOT RUN | No prepared local WP/WC/database installation; Docker access was unavailable |
| HPOS authoritative tables / legacy posts mode | NOT RUN | No integration environment; no verified HPOS claim or `FeaturesUtil` compatibility declaration |
| High-volume performance, full accessibility audit, real multi-currency extensions | NOT RUN | Requires a local representative store and assistive technology checks |

Reproduce the isolated checks from the repository root:

```powershell
php tests/run.php
node --check assets/dashboard.js
git diff --check
# Install browser test tools inside the ignored project directory:
npm install --prefix .dev-tools --no-audit --no-fund --save-exact playwright@1.58.2
$env:PLAYWRIGHT_BROWSERS_PATH = (Join-Path (Get-Location) '.dev-tools\browsers')
& '.\.dev-tools\node_modules\.bin\playwright.cmd' install chromium
$env:PHP_BINARY = 'C:\path\to\php.exe'
node tests/dashboard.test.cjs
```

The test PHP doubles never connect to a store. Browser tests bind only `127.0.0.1`, mock the API and write screenshots under ignored `.dev-tools/`. Test PHP entry points refuse web execution. Do not package `.dev-tools/`, tests or this development record as runtime dependencies.

## Remaining work

Before release: execute a real WordPress/WooCommerce matrix in HPOS mode with posts synchronization disabled and in legacy mode. Compare charts to Analytics for partial/full/amount-only refunds, failed/retried imports, status changes, plain permalinks, timezone changes and multi-currency extensions. Profile large stores; validate screen readers and keyboard behavior inside actual wp-admin. Only then decide on HPOS declarations, supported-version metadata and a release version.

The expanded operational queue remains a separate next stage. Stage 1 retains only processing/on-hold counts.

## File manifest

| Change | Files |
| --- | --- |
| Modified | `.gitignore`, `pp-woocommerce-advanced-dashboard.php`, `readme.txt` |
| Removed | `classes.php`, `scripts.js`, `advanced-dashboard-style.css` |
| Added runtime | `includes/class-adwc-plugin.php`, `includes/class-adwc-date-range.php`, `includes/class-adwc-analytics.php`, `templates/dashboard.php`, `assets/dashboard.js`, `assets/dashboard.css` |
| Added verification/docs | `tests/stubs.php`, `tests/run.php`, `tests/render-fixture.php`, `tests/dashboard.test.cjs`, `docs/stage-1.md` |

`index.php` is unchanged. Local tools and screenshots under `.dev-tools/` are ignored by Git.
