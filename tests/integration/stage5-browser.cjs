/* Focused real-browser freeze check on the new three-order upgrade store. */
const assert = require('node:assert/strict');
const { chromium } = require('../../.dev-tools/node_modules/playwright');
(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto('http://127.0.0.1:38976/wp-login.php');
    await page.fill('#user_login', 'adwc_admin');
    await page.fill('#user_pass', 'adwc-local-review-only');
    await Promise.all([page.waitForURL(url => !url.pathname.includes('wp-login.php')), page.click('#wp-submit')]);
    const widget = page.locator('#advanced-dashboard-admin-widget');
    if (await widget.isHidden()) {
      await page.click('#show-settings-link');
      await page.check('#advanced-dashboard-admin-widget-hide');
    }
    if ((await widget.getAttribute('class')).split(' ').includes('closed')) await widget.locator('.handlediv').click();
    await page.locator('.adwc-report').waitFor({ state: 'visible' });
    assert.equal(await page.locator('.adwc-plot').count(), 3);
    for (const range of ['7', '30', '90']) {
      await page.selectOption('#adwc-range', range);
      const pending = page.waitForResponse(r => r.url().includes('advanced-dashboard-for-woocommerce') && (r.url().includes('summary')));
      await page.locator('.adwc-filters button').click();
      const response = await pending;
      const data = await response.json();
      assert.equal(response.status(), 200);
      assert.equal(data.state, 'ready');
      assert.equal(data.rows.length, Number(range));
      assert.equal(data.totals.orders_count, 3);
    }
    await page.locator('.adwc-plot').first().focus();
    await page.keyboard.press('Home');
    assert.match(await page.locator('.adwc-point').first().textContent(), /2026/);
    await page.setViewportSize({ width: 390, height: 900 });
    assert(await widget.evaluate(node => node.scrollWidth <= node.clientWidth + 2));
    assert.deepEqual(errors, []);
    console.log('PASS real installed 1.0.0: three SVG charts, 7/30/90-day HTTP totals, keyboard, narrow layout; no browser exceptions.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
