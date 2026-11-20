# 1.0.0 local release candidate — freeze record

## Final 1.0.0 preflight · stage 7

The final local release set is `.dev-tools/wordpress-org/release-1.0.0-final/`. Its `trunk/` and `tags/1.0.0/` directories contain ten identical plugin files each. The code matches RC2 byte for byte, and the readme matches the stage 6 listing copy. The eight directory graphics are in `assets/`, including the new screenshot of the synthetic queue with the normal status label. The manifest lists all 28 SVN paths and their SHA-256 values.

The official WordPress.org readme validator and Plugin Check on the unpacked final ZIP passed. Version, stable tag, widget identity, directory layout, ZIP integrity and screenshot captions were verified. This is the package for any separately approved publication. The older RC1 and RC2 references below document the earlier test runs.

```text
ZIP: .dev-tools/wordpress-org/release-1.0.0-final/advanced-dashboard-for-woocommerce-1.0.0.zip
SHA-256: a5273b0b842ad7222346ef37467ba1231c45700a0ea29c918e5b6c88c75ece83
Manifest: .dev-tools/wordpress-org/release-1.0.0-final/manifest.json
```

## WordPress.org presentation · stage 6

The local review set is in `.dev-tools/wordpress-org/1.0.0/`. `assets/` contains two icons, two banners and four screenshots captured in Chromium from the RC2 dashboard with synthetic data. `source/` contains editable SVGs, `previous/` holds the four original public graphics and the prior RC2 readme, and `preview.html` shows the new artwork, copy and archived graphics together. `manifest.json` records intended additions and replacements, dimensions, file sizes and hashes.

The public assets listing at SVN revision 3814029 contained two icons, one standard banner and one screenshot. The WordPress.org plugin API identified the public version as 0.2.5. Both endpoints were read over HTTPS. [Public asset directory](https://plugins.svn.wordpress.org/advanced-dashboard-for-woocommerce/assets/) · [WordPress.org asset conventions](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/)

The English `readme.txt` was prepared for the listing and copied to the review set. The installed RC2 passed Plugin Check with the revised readme mounted read-only for validation. The local parser checked headers, FAQ and upgrade notice. Numbered screenshot captions were validated separately because the local parser has no directory Markdown renderer. The contributor login was checked against the public WordPress.org record. PNG structure, prescribed dimensions and preview rendering passed.

All packaged RC2 code matched the workspace. The frozen RC2 archive retains its original SHA-256 and readme. Any future authorized release would pair the revised readme with that code and place the eight catalog PNGs in the top-level WordPress.org `assets/` directory. This review set stays local.

## RC2 · 20 November 2026

**READY for owner review.** RC2 is the current local candidate. WordPress continues to recognize the plugin as version 1.0.0. RC1 remains archived with its original checksum.

- Verified empty reports retain the three zero totals and the daily table. A single status message replaces the flat charts. Error and unavailable responses still withhold the report.
- Moving the mouse selects the nearest day and displays its exact value. Pointer events are grouped by animation frame and the display updates only when the selected day changes. Touch and keyboard selection are retained. Hover leaves focus and the keyboard live announcement alone.
- The chart help retains the metric and refund definitions. The order review help holds the details about counts, selection, age and timezone. The warning that age alone does not mean an order is late stays visible next to the queue controls.

| Check | Result |
| --- | --- |
| Real Chromium in the existing CPT and HPOS stores, posts sync off | **PASS** at 1440, 768 and 390 px. Populated and empty ranges, HTTP 400, return to populated range, mouse hover, simulated touch, keyboard and native disclosures. No horizontal overflow or browser exceptions. |
| Actual Analytics disabled in CPT | **PASS**. Report withheld and operations remained available. Original sandbox option restored. |
| PHP / mocked browser / syntax / WPCS | **PASS**, 78 PHP and 24 Chromium checks. Changed PHP/JS passed syntax checks. WPCS clean. |
| ZIP on existing CPT package sandbox | **PASS**. The ten installed files match the manifest. Plugin Check reported no errors. Header, dependency, HPOS declaration and real Analytics read passed. |
| Full matrix, physical touch hardware, screen-reader audit and fresh legacy upgrade rehearsal | **NOT RUN** for RC2. The RC1 record below documents the separate real 0.2.5 update test. |

The only runtime files that differ from RC1 are `assets/dashboard.js`, `assets/dashboard.css` and `templates/dashboard.php`. ZIP CRC, version metadata, the ten-file allowlist and file SHA-256 values were verified. No publication action was taken.

```text
Build: python tests/build-local-candidate.py --version 1.0.0 --rc 2
ZIP: .dev-tools/release-candidate/1.0.0/rc2/advanced-dashboard-for-woocommerce-1.0.0-rc2.zip
Size: 24924 bytes
SHA-256: c5370d99747f7189c5eecbb122c6ff8f417dd05099a3cca16a1eb776505e0c9b
Manifest: .dev-tools/release-candidate/1.0.0/rc2/manifest.json
Screenshots: .dev-tools/rc2/screenshots/
```

The stage 7 record above supersedes this candidate for publication.

## RC1 · 20 November 2026

**READY for owner review.** RC1 supersedes the 13 November local package documented below. The stage 1–4 records describe the versions and decisions at the time of each stage. References to 0.2.5 in the current readme document upgrade compatibility and release history.

The WordPress.org contributor field now uses the verified login `piotr-pesta`. The plugin header keeps Piotr Pesta as the author and links to the plugin repository and the author's WordPress.org profile. The public readme describes installation and upgrade. The ZIP comment records the local status and the requirement for separate publication approval. The license remains GPLv2 or later. [WordPress.org readme format](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/) · [Author's WordPress.org profile](https://profiles.wordpress.org/piotr-pesta/)

The visual review caught trailing commas in the English chart-axis dates and a note that implied historical custom ranges included today. Axis labels now use a localized formatter without the year. The date caption applies to every preset and custom range. The three charts, queue, permissions and data semantics retain their prior behavior.

| Check | Result |
| --- | --- |
| Real Chromium on existing CPT review store | **PASS**. Seven screenshots cover 1440, 768 and 390 px, expanded queue, custom range, valid zero results and HTTP 400 for reversed dates. Keyboard chart navigation and the queue disclosure work. No overlapping or clipped SVG labels, horizontal overflow or page exceptions at those widths. Collapsed widget heights were 1553, 1537 and 1682 px. |
| ZIP contents and bytes | **PASS**. 24,639 bytes with the same ten distribution paths listed below. CRC and SHA-256 verified. Installed files in both new sandboxes exactly match the manifest. |
| Plugin Check on installed RC1 / WPCS / syntax | **PASS**. Plugin Check 2.2.0 reports no errors, PHPCS/WPCS reports zero errors and warnings, and the changed PHP/JS files pass syntax checks. |
| Installed ZIP in CPT and HPOS | **PASS**. WP 7.0.8 / WC 11.2.2 / PHP 8.2.35. Live SVG charts, nonzero Analytics totals, queue counters and editor links verified in both modes. HPOS synchronization with posts was off and the three new synthetic orders had no legacy order posts. |
| Native 0.2.5 → 1.0.0 update with RC1 | **PASS**. A separate CPT sandbox activated the original 0.2.5. `Plugin_Upgrader` installed the local ZIP, removed old assets, and the test verified standard reactivation, widget identity and native preferences, settings and selected fields of three synthetic orders. |
| Targeted browser regression | **PASS**, 21 mock API checks in addition to the real browser runs above. |
| Full compatibility matrix, high-volume testing, third-party integrations and full assistive-technology audit | **NOT RUN** for RC1. See the earlier verification scope below. |

The initial installation needed writable `wp-content/upgrade` directories in the new containers. The first HPOS fixture also exposed missing WooCommerce tables after HPOS was selected before WooCommerce activation. WooCommerce's table initializer corrected the new sandbox. The subsequent populated smoke test passed. These adjustments affected only RC1 test infrastructure.

```text
Build: python tests/build-local-candidate.py --version 1.0.0 --rc 1
ZIP: .dev-tools/release-candidate/1.0.0/rc1/advanced-dashboard-for-woocommerce-1.0.0-rc1.zip
SHA-256: 602aa1a60c8b4aed23eace34c3153db71f02940a43ceec68be23fec92dc35441
Manifest: .dev-tools/release-candidate/1.0.0/rc1/manifest.json
Screenshots: .dev-tools/rc1/screenshots/
Logs: .dev-tools/rc1/
```

For any future, separately authorized publication use the RC1 artifact and SHA-256 above in place of the historical values in the instructions below. The RC1 work ended locally. The owner still needs to make the publication decision and accept the verification limits.

## Historical snapshot · 13 November 2026

13 November 2026. **Local preparation PASS. Publication NOT AUTHORIZED.** This document is not permission to commit, push, use SVN, deploy or publish. None of those actions was performed. Production Ordin.pl was not accessed. Stop after this stage.

## Frozen scope and identity

Version, Stable tag, changelog and upgrade notice are **1.0.0**. Slug/text domain `advanced-dashboard-for-woocommerce`, main file `pp-woocommerce-advanced-dashboard.php`, widget ID `advanced-dashboard-admin-widget`, WooCommerce dependency and the non-admin `before_woocommerce_init` HPOS declaration are preserved. No functional/UI changes in this stage: the eight other runtime files match the stage 4 manifest byte-for-byte.

- Three local responsive SVG charts on the main WordPress dashboard, with keyboard inspection and a daily table. No CDN, application framework or added external service.
- Daily Analytics net sales (after discounts/refunds, excluding tax/shipping, including fees), original-order count, and net product quantity. Explicit order creation date; refunds on refund creation day; included statuses follow Analytics settings. Amount-only refunds need not return quantities. This is not profit or received cash.
- Store-local calendar-day boundaries, 7/30/90-day and current-month presets, strict custom ranges up to 90 inclusive days. WooCommerce/WordPress currency and number formatting.
- Honest zero/unavailable/error states and conservative currency/import/integrity checks. No conversion, financial history-scan fallback, automatic import or repair.
- Processing/on-hold/pending counters and at most ten oldest editable matching orders, selected registered open/custom statuses, creation age, editor links. Per-user/site threshold 1–8760 hours, default 72. Age is not time in status or a verdict that an order is late.
- Capability/nonce checks, validated dates/preferences, escaped HTML and text-only DOM values, private REST responses and dashboard-scoped assets. No customer contact fields, order modifications, new tables or crons. Original native dashboard preferences retained.

## Confirmed configurations and limitations

| Configuration | Evidence |
| --- | --- |
| WP 7.0.8 / WC 11.2.2 / PHP 8.2.35 | Stage 4 real CPT and HPOS, posts sync off; exact tested minimums. Stage 5 final installed ZIP, real 0.2.5 → 1.0.0 upgrade, API/format/browser checks in CPT. |
| WP 7.1.4 / WC 11.3.0 / PHP 8.3.36 | Stages 2–4 CPT and HPOS, posts sync off. Stage 5 read-only HPOS smoke verifies final source headers/dependency, non-admin feature registry and real aggregate read. |

No claim for lower patches or arbitrary combinations. The earlier WP 7.1.4 / WC 11.3.0 PHP 8.2.35 smoke was limited, not another full matrix row. PHP 8.2 security support ends 31 December 2026; reconsider the floor if publication is delayed beyond that date (stage 4 records the primary reference).

Analytics internals can change. Matching counts/import state cannot certify every historical amount or extension-specific direct write. Recent changes may lag Analytics; third-party exclusions can conservatively withhold reports. Mixed/unknown currencies are withheld, never combined. More than 100 relevant refund records requires a shorter range. The one existing prepared refund-ID lookup reads only `wc_order_stats`, at most 101 IDs; it does not depend on legacy posts.

**NOT RUN:** multisite, broad third-party currency/import extension matrix, high-volume load, lower versions, a full assistive-technology audit, fully translated Polish language-pack UI, complete 2017 stack. No fresh broad matrix was run in stage 5. Earlier fixtures/test suites are preserved.

## Final checks

| Check | Result |
| --- | --- |
| Metadata and .distignore/allowlist | **PASS**, Version/Stable tag/changelog 1.0.0; unchanged tested minimums, dependency and HPOS registration |
| Installed final ZIP / manifest | **PASS**, exact ten-file set and every SHA-256 matches; no extra installed files |
| Plugin Check 2.2.0 on installed final ZIP | **PASS**, “Checks complete. No errors found.” No source directory/file exclusions |
| PHPCS 3.13.6 / WPCS 3.4.1 | **PASS**, zero errors/warnings in runtime PHP |
| PHP isolated / mocked Chromium | **PASS**, 78 / 21; explicitly not integration tests |
| Real 0.2.5 → 1.0.0 WordPress updater | **PASS**, original code loaded, baseline established, final ZIP replaces files and removes obsolete code, standard reactivation succeeds, fresh process reads version 1.0.0 |
| Upgrade persistence | **PASS**, widget ID, order/hidden/closed/layout options, selected order fields/metadata/item counts including modification timestamps, WC settings and legacy sentinel retained; new 96-hour preference survives reactivation |
| Final installed package real API/price smoke | **PASS**, 7/30/90 days, actual wc_price formatting in two configurations restored afterward; operational queue available |
| Real Chromium on installed 1.0.0 | **PASS**, three SVGs; HTTP 7/30/90-day results each reconcile to three synthetic original orders; keyboard day inspection, narrow layout, no browser exceptions |
| Current HPOS source smoke | **PASS**, 1.0.0 headers/core WooCommerce dependency enforcement/non-admin declaration and aggregate read; no fixture changes |
| PHP/JS syntax and git whitespace | **PASS** |
| Unresolved in-scope functional FAIL | **None** |
| Publication, SVN, deploy, production-store checks | **NOT RUN**, expressly outside authorization |

Upgrade uses new Docker project **adwc-stage5-release**, localhost **38976**, dedicated database/volumes, WP 7.0.8 / WC 11.2.2 / PHP 8.2.35, CPT. WordPress HTTP/mail/cron/auto-updates remain blocked/disabled. Original 0.2.5 comes from the tracked files at HEAD `c362dc63d60995ce5d9a1f0a00a019453455ef22`, reconstructed without checkout/reset. Only three new synthetic orders were used. Existing stage 2–4 stores/data were not reseeded or removed. The new store remains running for review.

Test setup note: WooCommerce's initial activation wizard redirected the first admin-context command before the baseline ran. The harness now requires the saved baseline before replacement; after clearing the one-time wizard condition the complete prepare/replace/post-files/verify sequence passed. Core's single-plugin updater silently deactivates before replacing files, so normal reactivation is tested explicitly. Historical-plugin WP-CLI admin menu/ActionScheduler notices and blocked update-check warnings are setup noise; no browser exception or final-package Plugin Check failure remains. There was no candidate functional fix.

Raw reports: `.dev-tools/stage5/` (`wpcs.json`, `unit.log`, `mock-browser.log`, `upgrade-*.log`, `package.log`, `compat.log`, `preflight.log`, `plugin-check.log`, `real-browser.log`, `current-hpos.log`). Reproduction uses base compose plus `tests/integration/stage5-compose.yaml`, explicit project `adwc-stage5-release`, and guarded `stage5-upgrade.php`, `stage5-package.php`, `stage5-compat.php`. The upgrade preparation is one-time: do not reseed or rerun replacement against preserved review fixtures. `stage5-browser.cjs` only targets localhost 38976 and changes native visibility/collapse through WordPress UI after persistence verification.

## Frozen artifact

```text
.dev-tools/release-candidate/1.0.0/advanced-dashboard-for-woocommerce-local-rc-1.0.0.zip
SHA-256: cadf8999a659a3d1d033ec4d1031027b95ab47336ab6d0f706b46524899e1225
Manifest: .dev-tools/release-candidate/1.0.0/manifest.json
```

Single root `advanced-dashboard-for-woocommerce/`, exactly:

```text
assets/dashboard.css
assets/dashboard.js
includes/class-adwc-analytics.php
includes/class-adwc-date-range.php
includes/class-adwc-operations.php
includes/class-adwc-plugin.php
index.php
pp-woocommerce-advanced-dashboard.php
readme.txt
templates/dashboard.php
```

The builder applies `.distignore`, requires this exact allowlist, rejects symlinks/known synthetic credentials/private-key markers, validates CRC and writes file hashes. Runtime content was reviewed; no secrets were found. Tests, fixtures, logs, tools/vendor, documentation and Git/configuration files are excluded. The prior 0.2.5 ZIP/manifest are retained. The 1.0.0 archive comment/readme and this freeze record make its local-candidate status explicit. Rebuild with `python tests/build-local-candidate.py --version 1.0.0`; every runtime change invalidates the freeze and requires a new hash, checks and acceptance.

## Future SVN publication procedure — instructions only

**Do not execute during this stage.** Obtain separate, explicit owner authorization for publication, verify committer access and the repository URL, and recheck current WordPress.org instructions. Any change to candidate wording or other packaged content requires a new inspected hash and package checks before approval. WordPress.org generates its own ZIP, whose archive bytes need not equal this local ZIP; compare extracted runtime file hashes.

After that authorization, use a fresh dedicated SVN working copy, never this Git tree as an SVN working copy. Example PowerShell preparation (paths stay in this workspace):

```powershell
$workspace = 'C:\Users\piotr\WordPress Pluginy\Advanced Dashboard for WooCommerce'
$candidateZip = Join-Path $workspace '.dev-tools\release-candidate\1.0.0\advanced-dashboard-for-woocommerce-local-rc-1.0.0.zip'
if ((Get-FileHash -LiteralPath $candidateZip -Algorithm SHA256).Hash.ToLowerInvariant() -ne 'cadf8999a659a3d1d033ec4d1031027b95ab47336ab6d0f706b46524899e1225') { throw 'Candidate changed: stop and revalidate' }
$svnRoot = Join-Path $workspace '.dev-tools\svn-publication-1.0.0'
# Require this path to be absent before checkout; do not overwrite another working copy.
svn checkout --depth immediates https://plugins.svn.wordpress.org/advanced-dashboard-for-woocommerce/ $svnRoot
svn update --set-depth infinity "$svnRoot\trunk"
svn update --set-depth empty "$svnRoot\tags"
```

Extract the approved ZIP into a fresh inspection directory. Copy its **inner ten-file plugin directory contents** into `trunk`, without another slug directory. Compare against the manifest. Review existing trunk files; schedule each obsolete tracked file (including the old classes/scripts/CSS if present) with `svn delete -- <verified-path>`. Preserve repository-level assets and existing tags. Never use a recursive deletion assembled across shells. Add the new runtime paths with `svn add --force -- <verified-paths>`; inspect `svn status` and `svn diff`, including deleted files. Ensure no unversioned runtime files or accidental tools remain.

Only when trunk exactly matches the approved runtime manifest and `tags/1.0.0` does not exist:

```powershell
svn copy "$svnRoot\trunk" "$svnRoot\tags\1.0.0"
svn status $svnRoot
svn diff $svnRoot
# This commit is the publication action. Requires the separate owner approval above.
svn commit "$svnRoot\trunk" "$svnRoot\tags\1.0.0" -m 'Release 1.0.0'
```

Version and Stable tag must be 1.0.0 in trunk/tag before committing. Do not overwrite an existing tag. Complete any WordPress.org Release Confirmation request if enabled, then check the public version/readme and downloaded file manifest. Stop and investigate mismatches; do not silently republish changed bytes under the same version. [Official SVN instructions](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/) and [release confirmations](https://developer.wordpress.org/plugins/wordpress-org/release-confirmation-emails/) describe the publication mechanism. No SVN command above was executed here.

## Manual acceptance and stop point

- [ ] Owner explicitly approves publication of the identified content/hash and the tested support floor; reassess PHP 8.2 support if delayed.
- [ ] Human desktop/narrow review, keyboard/table/disclosure review, prices/timezone/status wording and a representative shop's Analytics expectations accepted.
- [ ] Accept documented freshness/multi-currency/refund/high-volume limits and NOT RUN areas, or request focused checks separately.
- [ ] Decide whether to remove local-candidate wording before publication; if changed, rebuild/recheck and approve the replacement hash.
- [ ] Reconfirm package hashes, repository identity/permissions, absent 1.0.0 SVN tag and clean reviewed SVN diff in the separately authorized publication task.

**Ready as a complete local candidate: YES. Published or authorized to publish: NO.** Stage 5 ends here; no further work is started automatically.

Changed in this stage: `pp-woocommerce-advanced-dashboard.php`, `readme.txt`, `tests/build-local-candidate.py`; added `tests/integration/stage5-compose.yaml`, `stage5-upgrade.php`, `stage5-package.php`, `stage5-compat.php`, `stage5-browser.cjs` and this document. `.distignore` and all functional PHP/JS/CSS/templates remain unchanged. Local generated artifacts/logs are ignored by Git. Existing uncommitted stage 1–4 work remains intact.
