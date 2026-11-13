# 1.0.0 local release candidate — freeze record

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
