// Read-only visual/keyboard preflight against the existing synthetic store.
const { chromium } = require('../../.dev-tools/node_modules/playwright');
const path = require('node:path');
const fs = require('node:fs');
const assert = require('node:assert/strict');
(async () => {
	const browser = await chromium.launch();
	try {
		const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
		await page.goto('http://127.0.0.1:38971/wp-login.php');
		await page.fill('#user_login', 'adwc_admin'); await page.fill('#user_pass', 'adwc-local-review-only');
		await Promise.all([page.waitForURL((url) => !url.pathname.includes('wp-login.php')), page.click('#wp-submit')]);
		await page.locator('.adwc-report').waitFor({ state: 'visible' });
		await page.locator('.adwc-operations-status[data-state=ready]').waitFor({ state: 'attached' });
		const results = [];
		for (const width of [1440, 390]) {
			await page.setViewportSize({ width, height: 1000 });
			const widget = page.locator('#advanced-dashboard-admin-widget');
			await widget.scrollIntoViewIfNeeded();
			await widget.screenshot({ path: path.resolve(`.dev-tools/preflight/${process.argv.includes('--before') ? 'before' : 'after'}-${width}.png`) });
			const metrics = await widget.evaluate((node) => ({ width: node.clientWidth, height: node.clientHeight, overflow: node.scrollWidth > node.clientWidth + 2 }));
			assert(!metrics.overflow);
			await page.locator('.adwc-plot').first().focus(); await page.keyboard.press('Home'); await page.keyboard.press('ArrowRight');
			assert((await page.locator('.adwc-point').first().textContent()).length > 0);
			if (!process.argv.includes('--before')) {
				const disclosure = page.locator('.adwc-queue-details');
				assert.equal(await disclosure.getAttribute('open'), null);
				await disclosure.locator('summary').focus(); await page.keyboard.press('Enter');
				assert.notEqual(await disclosure.getAttribute('open'), null);
				assert(await page.locator('.adwc-order-list a').first().isVisible());
				await disclosure.locator('summary').press('Enter');
			}
			results.push({ viewport: width, ...metrics });
		}
		fs.writeFileSync(path.resolve(`.dev-tools/preflight/${process.argv.includes('--before') ? 'before' : 'after'}-layout.json`), JSON.stringify(results, null, 2));
		console.log(JSON.stringify(results));
	} finally { await browser.close(); }
})().catch((error) => { console.error(error); process.exitCode = 1; });
