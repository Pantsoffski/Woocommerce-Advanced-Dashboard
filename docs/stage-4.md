# Stage 4: compatibility, real upgrade and local candidate

13 November 2026. **PASS** for the bounded scope below. Technically ready for final release preparation on the tested configurations; **not approved for publication**. Version and Stable tag remain **0.2.5**. No commit, push, SVN, tag, deployment, production access or production data copy. No UI, metric, queue-selection or runtime data-access changes in this stage.

## Tested minimums and API requirements

The conservative advertised floor is **WordPress 7.0.8 / WooCommerce 11.2.2 / PHP 8.2.35**. This exact older combination passed real integration in both CPT and HPOS. These are tested support minimums, not a claim that every API first appeared in these releases. Lower patches and arbitrary combinations have not been certified.

| Dependency | Actual use and decision |
| --- | --- |
| WordPress | REST, capabilities/nonces, dashboard/user options, `wp_timezone()` / `wp_date()` (available since 5.3), and core `Requires Plugins` dependency handling (since 6.5). There is no reason to retain the former untested WP 6.2 support claim. The older tested release line is 7.0, pinned to 7.0.8. |
| WooCommerce | `wc_get_orders`, `wc_get_order`, `wc_orders_count`, authoritative CRUD dates/currency/editor links, Analytics revenue statistics with explicit `date_type=date_created`, ReportsSync and FeaturesUtil. Analytics route/schema and import support are checked at runtime. Optional scheduler/status-policy methods use `is_callable` guards. The tested WC 11.2.2 itself requires WP 7.0 and PHP 7.4; we make a narrower tested promise. |
| PHP | DateTimeImmutable, Throwable, null coalescing and ordinary classes; no new runtime Composer dependency. The code's syntax alone is not proof of PHP 7.4 compatibility. PHP 8.2.35 was chosen as the older still security-supported tested runtime, with PHP 8.3.36 already covered by stage 3. |

The current/previous WooCommerce release-line policy informed the bounded choice; it does not require a third-party plugin to support every combination. PHP 8.2 security support ends **31 December 2026**. Reconsider this floor if release preparation crosses that date.

Primary references checked during this stage: [WooCommerce version support policy](https://developer.woocommerce.com/docs/contribution/contributing/version-support-policy/), [PHP supported versions](https://www.php.net/supported-versions.php), [WordPress dependency headers](https://make.wordpress.org/core/2024/03/05/introducing-plugin-dependencies-in-wordpress-6-5/). Versions were also checked against official download metadata and the installed core/plugin headers.

Main PHP/readme minimum headers now match the tested floor. `Requires Plugins: woocommerce`, the original filename, slug/text domain and widget ID are retained. `WC tested up to: 11.3.0` and readme `Tested up to: 7.1.4` describe the existing newer test result. Actual WordPress parsing and dependency validation passed: active WooCommerce is accepted, a filtered missing dependency returns `plugin_missing_dependencies` without changing installed-plugin options. Core enforces the dependency slug; the WC version header is WooCommerce metadata, not a general WordPress version-enforcement mechanism.

## HPOS final review

**PASS.** The main file registers `FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true )` in `before_woocommerce_init`, guarded by class availability and outside all admin-only conditions.

- All order reads use WooCommerce CRUD/query/count APIs, including currency/integrity probes, the bounded operational queue and per-order editor links/capabilities. No order writes, posts/postmeta SQL, WP_Query fallback or posts synchronization dependency.
- The existing prepared `wc_order_stats` refund-ID integrity query remains the sole direct SQL read. It reads the Analytics lookup table, returns at most 101 IDs, and does not calculate a second financial total. Its behavior passed CPT and HPOS tests; it does not require legacy order posts.
- The feature registry reports compatibility in real non-admin WP-CLI processes on both version combinations and both storage modes. A test-only MU probe additionally confirmed `X-ADWC-HPOS-Declared: yes` on actual anonymous frontend HTTP requests in all three new sandboxes. The probe is excluded from the ZIP.
- HPOS tests explicitly assert posts synchronization is **disabled** and a source order has **no** legacy `shop_order` post copy. CPT and HPOS use separate databases/volumes.

## Real upgrade from original 0.2.5

**PASS**, in separate project `adwc-stage4-upgrade`, localhost **38974**, on the older tested stack. No original stage 2/3 fixture was used or changed.

The six original tracked plugin files were reconstructed byte-for-byte using `git show` from repository HEAD `c362dc63d60995ce5d9a1f0a00a019453455ef22`; no checkout/reset was performed. The actual original 0.2.5 was installed and activated. Its original PHP class and dashboard registration executed in admin context, confirming the historical widget ID. Three synthetic orders were created under this installation and imported into Analytics.

The original code does not define its own persistent preference schema. The baseline therefore includes native dashboard order/hidden/closed/layout options, relevant WooCommerce timezone/currency/date-type options, an unrelated legacy sentinel, and selected order fields/metadata/item counts. Original dashboard rendering and its obsolete report/chart implementation were not exercised; a 2017 stack was not needed to test file replacement and persistence.

`Plugin_Upgrader::upgrade()` genuinely unpacked and replaced the old plugin directory from the final local ZIP using a sandbox-only update transient, without incrementing 0.2.5. It removed obsolete `classes.php` and the old assets. WordPress's single-plugin updater performed its normal silent deactivation; the test then used standard `activate_plugin()` and checked a fresh PHP process. This reactivation is explicit in the test, rather than assuming the updater preserves active state.

Confirmed after replacement and again after deactivate/reactivate:

- Original widget ID `advanced-dashboard-admin-widget`; unchanged native order, visibility, collapsed state and column preference.
- Selected persisted order fields/metadata/item counts equal the baseline, including modification timestamps; the test does not claim a full database byte comparison.
- WooCommerce settings and unrelated sentinel retained; Analytics reads the same three synthetic orders.
- New user queue preference (96 hours) retained across reactivation. No migration/deletion hook was introduced.

## Local candidate and package inspection

**PASS.** `tests/build-local-candidate.py` applies the current `.distignore`, requires an exact runtime allowlist, rejects symlinks and known development credential/private-key markers, validates ZIP integrity and writes per-file SHA-256 hashes. No `.distignore` change was necessary.

Artifact, excluded from Git under `.dev-tools`:

```text
.dev-tools/release-candidate/advanced-dashboard-for-woocommerce-local-rc-0.2.5.zip
SHA-256: ea215182ad411b22114afd1fb96731cac4dc1153215da267da41cb515eca430c
Manifest: .dev-tools/release-candidate/manifest.json
```

Exactly ten files under one `advanced-dashboard-for-woocommerce/` root: main PHP, index.php, readme, two assets, four classes and dashboard template. No tools, tests, synthetic credentials/fixtures, logs, docs, Git files, local configuration or vendor directory. The reviewed runtime files contain no secret material. The archive comment and readme explicitly mark this as local inspection only, not for publication.

The ZIP was installed into ordinary writable plugin volumes in the new CPT/HPOS stores and used by the real upgrade. `stage4-package.php` compared all installed files and the ZIP with the manifest in **all three stores**, including absence of extra files. **Plugin Check 2.2.0 ran against that installed package**, without source-tree file/directory exclusions: `Success: Checks complete. No errors found.` This is not just a source scan.

## Regression results

| Check | Result |
| --- | --- |
| WP 7.0.8 / WC 11.2.2 / PHP 8.2.35, true empty CPT and HPOS | **PASS**, 3 real checks each before seeding |
| Older CPT, synthetic data / operations / real Chromium | **PASS**, 169 / 33 / 29 checks; includes actual WooCommerce deactivation/reactivation |
| Older HPOS, sync off, synthetic data / operations / real Chromium | **PASS**, 171 / 33 / 28 checks |
| Earlier stage 1/2 isolated regression | **PASS**, 78 PHP checks and 21 mocked Chromium checks; these are not integration tests |
| Current WP 7.1.4 / WC 11.3.0 / PHP 8.3.36 | **PASS**, targeted final header/dependency/non-admin HPOS registry and real aggregate read in existing CPT/HPOS stores; full earlier-stage fixture suites retained, not reseeded |
| Real old-file replacement and reactivation | **PASS**, separate new store, original registration/preferences/orders retained |
| Final ZIP hashes/content / Plugin Check on installed ZIP | **PASS**, all ten files match in three stores; no Plugin Check errors |
| PHPCS 3.13.6 / WPCS 3.4.1 | **PASS**, zero errors/warnings in runtime PHP |
| PHP lint, JS syntax, whitespace | **PASS**, runtime and new test PHP lint, JS syntax and `git diff --check` |
| Unresolved functional failures in this scope | **None** |
| Lower versions, arbitrary mixed version combinations, multisite, broad third-party extension matrix, high-volume load, full assistive-technology audit, full historical 2017 stack | **NOT RUN**; no compatibility claim for these areas |

The unchanged real suites cover explicit creation-date semantics despite a paid-date shop setting, daily Analytics agreement, DST and range boundaries, zero/error/import states, refunds/cancellations/status policy, included versus excluded foreign currency, three SVG charts, wide/narrow widget, capabilities, nonce, escaping, preferences/editor links and dashboard-only loading. The floor stores contain the same several-dozen synthetic-order scenarios as the earlier suites. No production data or paid service was used.

## Isolation and reproduction notes

New Docker projects: `adwc-stage4-floor` (**38973**, CPT), `adwc-stage4-floor-hpos` (**38975**, HPOS), `adwc-stage4-upgrade` (**38974**, CPT upgrade). Each has its own database, WordPress, plugins and WooCommerce volumes; localhost-only web binding, blocked external WordPress HTTP/mail, disabled cron/auto-updates. Existing `adwc-stage2-local` and `adwc-stage3-hpos` stores and fixture records remain intact. All sandboxes remain available; no cleanup/down-volume removal was performed.

Use the base `tests/integration/compose.yaml` plus `stage4-compose.yaml` and an explicit project name/`ADWC_STAGE4_PORT`. The PHP 8.2 image does not pin the core: the official WordPress 7.0.8 archive was copied **after** Docker image initialization and Apache restarted before installation. WooCommerce came from the official 11.2.2 archive. Do not recreate/reseed existing stores just to run read-only assertions.

Resolved setup/test-harness issues, not outstanding plugin defects: initial image initialization raced core copying (mixed core files); copied directories needed writable sandbox-only ownership; a plugin-directory mount prevented the real updater from removing old files (replaced with a writable parent plugins volume); ZIP/manifest must be shared with CLI via explicit read-only mounts. Original-plugin WP-CLI admin context also emits core menu/update-check notices with external HTTP blocked; the candidate's completed browser regression has no browser exceptions. No system-wide tools/settings were changed.

Example final checks against the already provisioned floor CPT store:

```powershell
$env:ADWC_STAGE4_PORT = '38973'
docker compose -p adwc-stage4-floor -f tests/integration/compose.yaml -f tests/integration/stage4-compose.yaml run --rm cli wp eval-file /adwc-tests/stage4-compat.php
docker compose -p adwc-stage4-floor -f tests/integration/compose.yaml -f tests/integration/stage4-compose.yaml run --rm cli wp eval-file /adwc-tests/stage4-package.php
docker compose -p adwc-stage4-floor -f tests/integration/compose.yaml -f tests/integration/stage4-compose.yaml run --rm cli wp plugin check advanced-dashboard-for-woocommerce
$env:ADWC_TEST_PROJECT = 'adwc-stage4-floor'
$env:ADWC_TEST_ORIGIN = 'http://127.0.0.1:38973'
$env:ADWC_TEST_COMPOSE_OVERRIDE = 'stage4-compose.yaml'
$env:PLAYWRIGHT_BROWSERS_PATH = (Join-Path (Get-Location) '.dev-tools/browsers')
node tests/integration/browser.test.cjs --operations --without-wc
```

The browser suite changes **synthetic** sandbox preferences/options and restores WooCommerce availability. The upgrade `prepare`/`replace` phases are one-time destructive file-replacement tests restricted to the new upgrade sandbox; do not rerun against an existing review store. Raw reports remain under ignored `.dev-tools/stage4/`.

## Changed files and final release decisions

Runtime changes are limited to main PHP (tested minimum headers and HPOS hook) and readme (tested minimums/support scope). Added this document, the candidate builder, `stage4-compose.yaml`, `stage4-compat.php`, `stage4-package.php`, `stage4-probe.php` and `stage4-upgrade.php`. Updated `tests/integration/browser.test.cjs` only to accept the compose override; existing assertions remain. Earlier stage 1-3 uncommitted edits/deletions were preserved.

- [x] Tested floor and dependency headers; HPOS declaration on admin and non-admin paths.
- [x] CPT and HPOS without posts synchronization; real old-file upgrade; inspected local ZIP.
- [x] Targeted regression, WPCS, package Plugin Check; no unresolved in-scope FAIL.
- [ ] Release owner accepts this limited support matrix and short remaining PHP 8.2 security window; no additional acceptance is needed for the already authorized local work.
- [ ] Human final visual/accessibility acceptance and any required extension-specific checks remain release decisions.
- [ ] In a separately authorized release task choose a new Version/Stable tag and changelog, rebuild the final package and repeat package checks after metadata changes. Current 0.2.5 ZIP must not be published as the new release.

**Ready for final release preparation: YES, within the tested matrix. Ready to publish this artifact: NO.** No subsequent stage was started.
