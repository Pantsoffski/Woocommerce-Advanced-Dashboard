/* Run after installing Playwright locally. No requests to a real store. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { execFileSync } = require('node:child_process');
const { chromium } = require('../.dev-tools/node_modules/playwright');
const project = path.resolve(__dirname, '..');
const php = process.env.PHP_BINARY || path.join(project, '.dev-tools/php/php.exe');
const fixture = JSON.parse(execFileSync(php, [path.join(__dirname, 'render-fixture.php')], { encoding: 'utf8' }));
const css = fs.readFileSync(path.join(project, 'assets/dashboard.css'));
const js = fs.readFileSync(path.join(project, 'assets/dashboard.js'));
let mode = 'ready';
let requests = [];
let checks = 0;
const check = (value, message) => { assert(value, message); checks++; console.log(`PASS: ${message}`); };
const server = http.createServer((req, res) => {
	const url = new URL(req.url, 'http://localhost');
	if (url.searchParams.has('rest_route')) {
		res.setHeader('Content-Type', 'application/json');
		if (url.searchParams.get('rest_route').endsWith('/operations')) { res.end(JSON.stringify(fixture.operations)); return; }
		requests.push({ url, nonce: req.headers['x-wp-nonce'] });
		if (mode === 'error') { res.statusCode = 403; res.end(JSON.stringify({ message: 'Session expired <img src=x onerror=alert(1)>' })); return; }
		if (mode === 'invalid') { res.end(JSON.stringify({ state: 'ready', rows: [] })); return; }
		if (mode === 'unavailable') { res.end(JSON.stringify({ state: 'unavailable', message: 'Analytics import pending', rows: [], counts: fixture.data.counts })); return; }
		const data = structuredClone(fixture.data);
		if (mode === 'empty') {
			data.empty = true;
			data.rows.forEach((row) => ['net_revenue', 'orders_count', 'num_items_sold'].forEach((key) => { row[key] = 0; row.formatted[key] = '0'; }));
			data.totals = structuredClone(data.rows[0]);
		}
		res.end(JSON.stringify(data)); return;
	}
	if (url.pathname === '/dashboard.css') { res.setHeader('Content-Type', 'text/css'); res.end(css); return; }
	if (url.pathname === '/dashboard.js') { res.setHeader('Content-Type', 'application/javascript'); res.end(js); return; }
	const closed = url.searchParams.has('closed');
	const hidden = url.searchParams.has('hidden');
	const widget = url.searchParams.has('invalid-locale') ? fixture.html.replace('data-locale="pl-PL"', 'data-locale="invalid_locale"') : fixture.html;
	res.setHeader('Content-Type', 'text/html; charset=utf-8');
	res.end(`<!doctype html><html lang="en"><head><link rel="stylesheet" href="/dashboard.css"><style>body{font:13px system-ui;margin:20px;background:#f0f0f1;color:#1d2327}.postbox{max-width:1040px;background:#fff;border:1px solid #c3c4c7;padding:16px;box-sizing:border-box}h2{font-size:16px;margin:0 0 16px}.closed .adwc{display:none}.button{padding:6px 10px}select,input{font:inherit}.button-primary{background:#2271b1;color:#fff;border:1px solid #2271b1;border-radius:3px}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:8px}.striped tbody tr:nth-child(odd){background:#f6f7f7}</style></head><body><h1>Dashboard · test fixture</h1><div id="advanced-dashboard-admin-widget" class="postbox${closed ? ' closed' : ''}"${hidden ? ' style="display:none"' : ''}><h2>Advanced Dashboard for WooCommerce</h2>${widget}</div><script src="/dashboard.js"></script></body></html>`);
});
(async () => {
	await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
	const origin = `http://127.0.0.1:${server.address().port}`;
	const browser = await chromium.launch({ headless: true });
	try {
		const page = await browser.newPage({ viewport: { width: 1100, height: 1500 } });
		const errors = [];
		const external = [];
		page.on('pageerror', (error) => errors.push(error.message));
		page.on('request', (request) => { if (!request.url().startsWith(origin)) external.push(request.url()); });
		await page.goto(origin);
		await page.locator('.adwc-report').waitFor({ state: 'visible' });
		check(await page.locator('.adwc-line').count() === 3, 'Three local SVG charts render');
		check(requests[0].nonce === 'valid-nonce' && requests[0].url.searchParams.get('range') === 'month', 'Authenticated request preserves plain-permalink rest_route');
		check(await page.locator('tbody tr').count() === 7, 'Accessible table contains all daily values');
		check((await page.locator('[data-total="net_revenue"]').textContent()).includes('6 690,50'), 'Money uses store currency formatting');
		const plot = page.locator('.adwc-plot').first();
		await plot.focus(); await page.keyboard.press('Home');
		check((await page.locator('.adwc-point').first().textContent()).includes('1 340,50'), 'Home inspects first day with exact value');
		await page.keyboard.press('ArrowRight');
		check((await page.locator('.adwc-point').first().textContent()).includes('0,00'), 'ArrowRight inspects a zero-sales day');
		await page.keyboard.press('End');
		check((await page.locator('.adwc-point').first().textContent()).includes('900,00'), 'End inspects last day');
		await page.screenshot({ path: path.join(project, '.dev-tools/dashboard-desktop.png'), fullPage: true });
		await page.setViewportSize({ width: 360, height: 1100 });
		await page.waitForFunction(() => document.querySelector('svg').viewBox.baseVal.width < 400);
		check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Narrow widget does not overflow the page');
		check(!(await page.locator('svg').first().getAttribute('viewBox')).includes('NaN'), 'Responsive chart has finite coordinates');
		await page.locator('.adwc-table-details > summary').click();
		await page.screenshot({ path: path.join(project, '.dev-tools/dashboard-mobile.png'), fullPage: true });
		await page.selectOption('#adwc-range', 'custom');
		check(await page.locator('#adwc-from').isEnabled() && await page.locator('#adwc-to').isVisible(), 'Custom range enables labelled date inputs');
		await page.fill('#adwc-from', '2026-11-07'); await page.fill('#adwc-to', '2026-11-13');
		await page.locator('.adwc-filters button[type=submit]').click();
		await page.locator('.adwc-report').waitFor({ state: 'visible' });
		check(requests.at(-1).url.searchParams.get('from') === '2026-11-07' && requests.at(-1).url.searchParams.get('to') === '2026-11-13', 'Custom dates are sent to the validated endpoint');
		mode = 'empty'; await page.locator('.adwc-filters button[type=submit]').click();
		await page.locator('.adwc-status[data-state=empty]').waitFor();
		check(await page.locator('.adwc-line').count() === 3, 'Empty store retains readable zero charts');
		mode = 'unavailable'; await page.locator('.adwc-filters button[type=submit]').click();
		await page.locator('.adwc-status[data-state=unavailable]').waitFor();
		check(await page.locator('.adwc-report').isHidden() && await page.locator('.adwc-counts').isVisible(), 'Unavailable Analytics withholds graphs and preserves operational counts');
		mode = 'error'; await page.locator('.adwc-filters button[type=submit]').click();
		await page.locator('.adwc-status[data-state=error]').waitFor();
		check(await page.locator('.adwc-status img').count() === 0 && (await page.locator('.adwc-status').textContent()).includes('<img'), 'Error text cannot inject HTML');
		mode = 'invalid'; await page.locator('.adwc-filters button[type=submit]').click();
		await page.locator('.adwc-status[data-state=error]').waitFor();
		check(await page.locator('.adwc-report').isHidden(), 'Malformed endpoint response does not display stale graphs');
		mode = 'ready'; requests = [];
		await page.goto(`${origin}/?closed=1`); await page.waitForLoadState('networkidle');
		check(requests.length === 0, 'Collapsed widget requests no data');
		await page.locator('.postbox').evaluate((element) => element.classList.remove('closed'));
		await page.locator('.adwc-report').waitFor({ state: 'visible' });
		check(requests.length === 1, 'Expanding widget loads data once');
		requests = []; await page.goto(`${origin}/?hidden=1`); await page.waitForLoadState('networkidle');
		check(requests.length === 0, 'Hidden widget requests no data');
		await page.locator('.postbox').evaluate((element) => element.style.display = 'block');
		await page.locator('.adwc-report').waitFor({ state: 'visible' });
		check(requests.length === 1, 'Showing widget loads data without reloading WordPress');
		await page.goto(`${origin}/?invalid-locale=1`);
		await page.locator('.adwc-report').waitFor({ state: 'visible' });
		check(await page.locator('.adwc-counts').isVisible(), 'Custom invalid locale falls back without hiding charts or counts');
		check(errors.length === 0 && external.length === 0, 'No browser exceptions or external asset/data requests');
		console.log(`\n${checks} browser checks passed. Rendered view and mocked API; not a live WordPress integration.`);
	} finally { await browser.close(); }
})().catch((error) => { console.error(error); process.exitCode = 1; }).finally(() => server.close());
