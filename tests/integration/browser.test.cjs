/* Real browser against the isolated WP/WC sandbox. No API mocks. */
const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');
const { execFileSync } = require('node:child_process');
const { chromium } = require('../../.dev-tools/node_modules/playwright');
const root = path.resolve(__dirname, '../..');
const origin = process.env.ADWC_TEST_ORIGIN || 'http://127.0.0.1:38971';
const compose = path.join(__dirname, 'compose.yaml');
const cli = (...args) => execFileSync('docker', ['compose', '-f', compose, ...(process.env.ADWC_TEST_PROJECT ? ['-p', process.env.ADWC_TEST_PROJECT, '-f', path.join(__dirname, process.env.ADWC_TEST_COMPOSE_OVERRIDE || 'hpos-preflight.yaml')] : []), 'run', '--rm', 'cli', 'wp', ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'], timeout: 120000 });
let checks = 0;
const check = (value, message) => { assert(value, message); checks++; console.log(`PASS: ${message}`); };
const isSummary = (response) => response.url().includes('advanced-dashboard-for-woocommerce/v1/summary') || response.url().includes('advanced-dashboard-for-woocommerce%2Fv1%2Fsummary');
async function login(page, user) {
	await page.goto(`${origin}/wp-login.php`);
	await page.fill('#user_login', user);
	await page.fill('#user_pass', 'adwc-local-review-only');
	await Promise.all([page.waitForURL((url) => !url.pathname.includes('wp-login.php')), page.click('#wp-submit')]);
}
async function rest(page, suffix = '', nonce = true) {
	return page.evaluate(async ({ suffix, nonce }) => {
		const root = document.querySelector('#advanced-dashboard-admin-widget .adwc');
		const url = new URL(root ? root.dataset.endpoint : '/?rest_route=/advanced-dashboard-for-woocommerce/v1/summary', location.href);
		if (suffix) new URLSearchParams(suffix).forEach((value, key) => url.searchParams.set(key, value));
		const headers = nonce && root ? { 'X-WP-Nonce': nonce === true ? root.dataset.nonce : nonce } : {};
		const response = await fetch(url, { headers, credentials: 'same-origin' });
		return { status: response.status, body: await response.json(), cache: response.headers.get('cache-control') };
	}, { suffix, nonce });
}
async function operation(page, method = 'GET', body = null, nonce = true) {
	return page.evaluate(async ({ method, body, nonce }) => {
		const root = document.querySelector('.adwc');
		const panel = document.querySelector('.adwc-operations');
		const endpoint = panel ? (method === 'POST' ? panel.dataset.preferences : panel.dataset.endpoint) : `/?rest_route=/advanced-dashboard-for-woocommerce/v1/operations${method === 'POST' ? '/preferences' : ''}`;
		const headers = { 'Content-Type': 'application/json' };
		if (nonce && root) headers['X-WP-Nonce'] = nonce === true ? root.dataset.nonce : nonce;
		const response = await fetch(endpoint, { method, headers, credentials: 'same-origin', ...(body ? { body: JSON.stringify(body) } : {}) });
		return { status: response.status, data: await response.json() };
	}, { method, body, nonce });
}
(async () => {
	const browser = await chromium.launch({ headless: true });
	const errors = [];
	try {
		const context = await browser.newContext({ viewport: { width: 1500, height: 1100 } });
		await context.route('**/*', (route) => new URL(route.request().url()).origin === origin ? route.continue() : route.abort());
		const page = await context.newPage(); page.on('pageerror', (error) => errors.push(error.message));
		page.on('dialog', async (dialog) => { errors.push(dialog.message()); await dialog.dismiss(); });
		page.on('response', async (response) => {
			if (isSummary(response)) {
				const data = await response.json().catch(() => ({}));
				if (data.state !== 'ready') console.log('HTTP summary state:', response.status(), data.state, data.message);
			}
		});
		await login(page, 'adwc_admin');
		const widget = page.locator('#advanced-dashboard-admin-widget');
		await widget.scrollIntoViewIfNeeded();
		await page.waitForFunction(() => document.querySelector('.adwc-status')?.dataset.state && document.querySelector('.adwc-status').dataset.state !== 'loading', { timeout: 60000 });
		check(await page.locator('.adwc-report').isVisible(), 'Initial real HTTP chart state: ' + await page.locator('.adwc-status').textContent());
		check(await page.locator('.adwc-line').count() === 3, 'Real wp-admin renders all three SVG charts');
		if (process.argv.includes('--operations')) {
			await operation(page, 'POST', { age_hours: 72, statuses: ['wc-processing', 'wc-on-hold', 'wc-pending', 'wc-needs-review'] });
			await page.reload(); await page.locator('.adwc-report').waitFor({ state: 'visible' });
			await page.waitForFunction(() => document.querySelector('.adwc-operations-status')?.dataset.state === 'ready');
			check(await page.locator('.adwc-order-list li').count() === 10 && await page.locator('[data-count="pending"]').textContent() !== '', 'Real operational panel shows ten oldest orders and three counters');
			check(await page.locator('.adwc-order-list img').count() === 0 && (await page.locator('.adwc-order-list').textContent()).includes('<img'), 'Hostile synthetic order number is rendered as text, never HTML');
			await page.locator('.adwc-queue-details > summary').click();
			const firstLink = page.locator('.adwc-order-list a').first();
			const href = await firstLink.getAttribute('href');
			const editor = await context.newPage();
			const editResponse = await editor.goto(href);
			check(editResponse.status() === 200 && await editor.locator('#woocommerce-order-data').count() === 1, 'Operational link opens the actual order editor');
			await editor.close();
			await page.fill('#adwc-age', '120');
			await page.locator('.adwc-preferences summary').click();
			for (const input of await page.locator('.adwc-preferences input[type=checkbox]').all()) {
				if (await input.getAttribute('value') === 'wc-needs-review') await input.check(); else await input.uncheck();
			}
			await page.locator('.adwc-preferences button').click();
			await page.locator('.adwc-operations-status[data-state=saved]').waitFor();
			check((await operation(page)).data.preferences.age_hours === 120 && (await page.locator('.adwc-order-list').textContent()).includes('Needs review <synthetic>') && await page.locator('.adwc-order-list synthetic').count() === 0, 'Preference form saves threshold and custom status without HTML injection');
			await page.reload(); await page.locator('.adwc-report').waitFor({ state: 'visible' });
			await page.locator('.adwc-operations-status[data-state=ready]').waitFor({ state: 'attached' });
			check(await page.locator('#adwc-age').inputValue() === '120', 'Saved threshold persists after reloading real wp-admin');
			check((await operation(page, 'POST', { age_hours: 0, statuses: ['wc-processing'] })).status === 400 && (await operation(page, 'POST', { age_hours: 72, statuses: ['wc-nonexistent'] })).status === 400, 'HTTP preference endpoint validates age and status');
			check((await operation(page, 'POST', { age_hours: 72, statuses: ['wc-processing'] }, false)).status === 403 && (await operation(page, 'GET', null, 'bad')).status === 403, 'Operational read/write rejects missing or invalid nonce');
			await page.fill('#adwc-age', '8760'); await page.locator('.adwc-preferences button').click();
			await page.locator('.adwc-operations-status[data-state=saved]').waitFor();
			check(await page.locator('.adwc-order-list').isHidden() && (await page.locator('.adwc-operations-status').textContent()).includes('No editable orders'), 'Threshold change produces a clear real empty-queue state');
			await operation(page, 'POST', { age_hours: 72, statuses: ['wc-processing', 'wc-on-hold', 'wc-pending', 'wc-needs-review'] });
			await page.reload(); await page.locator('.adwc-report').waitFor({ state: 'visible' });
			cli('option', 'update', 'woocommerce_analytics_enabled', 'no');
			try {
				await page.reload();
				await page.locator('.adwc-status[data-state=unavailable]').waitFor();
				await page.locator('.adwc-queue-details > summary').click();
				await page.locator('.adwc-order-list').waitFor({ state: 'visible' });
				check(await page.locator('.adwc-report').isHidden() && await page.locator('.adwc-counts').isVisible(), 'Real disabled Analytics withholds charts while operational queue and counters remain available');
			} finally { cli('option', 'update', 'woocommerce_analytics_enabled', 'yes'); }
			await page.reload(); await page.locator('.adwc-report').waitFor({ state: 'visible' });
		}
		check(page.url().startsWith(`${origin}/wp-admin/`), 'Widget stays on the main WordPress dashboard');
		let response = await rest(page, 'range=7');
		check(response.status === 200 && response.body.state === 'ready' && response.body.rows.length === 7, 'Authenticated HTTP REST returns seven real daily buckets');
		check(response.cache.includes('no-store') && !JSON.stringify(response.body).includes('synthetic@example.test') && !('billing' in response.body), 'HTTP response is private and contains no customer/order details');
		check((await rest(page, '', false)).status === 403 && (await rest(page, '', 'invalid')).status === 403, 'HTTP REST rejects missing and invalid nonces');
		check((await rest(page, 'range=custom&from=2026-11-13&to=2026-11-01')).status === 400, 'HTTP REST rejects reversed dates');
		for (const range of ['7', '30', '90', 'month']) {
			await page.selectOption('#adwc-range', range);
			const pending = page.waitForResponse(isSummary);
			await page.locator('.adwc-filters button').click();
			const data = await (await pending).json();
			await page.locator('.adwc-report').waitFor({ state: 'visible' });
			check(data.state === 'ready' && await page.locator('tbody tr').count() === data.rows.length, `Real UI/table/Analytics agree for range ${range}`);
		}
		await page.selectOption('#adwc-range', 'custom');
		await page.fill('#adwc-from', '2026-10-24'); await page.fill('#adwc-to', '2026-10-26');
		const pending = page.waitForResponse(isSummary); await page.locator('.adwc-filters button').click();
		response = await (await pending).json();
		check(response.state === 'ready' && response.rows.length === 3 && response.timezone === 'Europe/Warsaw', 'Real custom range crosses DST in store timezone');
		await page.locator('.adwc-report').waitFor({ state: 'visible' });
		await widget.evaluate((node) => { node.style.width = '1050px'; node.style.maxWidth = 'none'; });
		await page.screenshot({ path: path.join(root, '.dev-tools/integration/wp-wide.png'), fullPage: true });
		await widget.evaluate((node) => { node.style.width = '330px'; });
		await page.locator('.adwc-plot').first().focus(); await page.keyboard.press('Home');
		check((await page.locator('.adwc-point').first().textContent()).includes('2026'), 'Real wp-admin chart supports keyboard day inspection');
		check(await widget.evaluate((node) => node.scrollWidth <= node.clientWidth + 2), 'Real narrow wp-admin widget has no horizontal overflow');
		await page.screenshot({ path: path.join(root, '.dev-tools/integration/wp-narrow.png'), fullPage: true });
		const requests = [];
		page.on('request', (request) => { if (request.url().includes('/plugins/advanced-dashboard-for-woocommerce/') || request.url().includes('/advanced-dashboard-for-woocommerce/v1/')) requests.push(request.url()); });
		await page.goto(`${origin}/wp-admin/profile.php`); await page.waitForLoadState('networkidle');
		check(requests.length === 0 && await page.locator('.adwc').count() === 0, 'Other real admin screen loads no plugin assets or financial data');
		await context.close();
		for (const loginName of ['adwc_manager', 'adwc_subscriber']) {
			const roleContext = await browser.newContext();
			const rolePage = await roleContext.newPage();
			await login(rolePage, loginName);
			if (loginName === 'adwc_manager') {
				await rolePage.locator('.adwc-report').waitFor({ state: 'visible', timeout: 60000 });
				check((await rest(rolePage)).status === 200, 'Shop manager can load actual widget and REST data');
			} else {
				check(await rolePage.locator('.adwc').count() === 0 && (await rest(rolePage)).status === 403 && (await operation(rolePage)).status === 403, 'Subscriber receives neither widget nor protected REST data');
			}
			await roleContext.close();
		}
		const anonymous = await browser.newContext();
		const anonResponse = await anonymous.request.get(`${origin}/?rest_route=/advanced-dashboard-for-woocommerce/v1/summary`);
		check(anonResponse.status() === 403, 'Anonymous REST access is denied'); await anonymous.close();
		if (process.argv.includes('--without-wc')) {
			cli('plugin', 'deactivate', 'woocommerce');
			try {
				const missingContext = await browser.newContext(); const missingPage = await missingContext.newPage();
				await login(missingPage, 'adwc_admin');
				check(await missingPage.locator('.adwc').count() === 0 && (await missingPage.locator('body').textContent()).includes('needs an active WooCommerce'), 'Real WooCommerce absence is handled without fatal or widget data');
				await missingContext.close();
			} finally { cli('plugin', 'activate', 'woocommerce'); }
		}
		check(errors.length === 0, 'Real dashboard plugin produces no browser exceptions');
		console.log(`\n${checks} REAL browser integration checks passed.`);
	} finally { await browser.close(); }
})().catch((error) => { console.error(error); process.exitCode = 1; });
