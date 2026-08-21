#!/usr/bin/env node
/**
 * Competitor-Analysis render service (Playwright).
 *
 * Renders one URL in a real headless Chromium — executes JS, waits for network to
 * settle, scrolls to trigger lazy-loading, and CAPTURES the JSON XHR/fetch responses
 * the SPA makes (the product data that never lands in the DOM). Prints a single JSON
 * object to stdout so the PHP crawler can read the rendered DOM AND the raw APIs:
 *
 *   { "url": "...", "status": 200, "html": "<rendered DOM>", "apis": [ { "url": "...", "body": "..." } ] }
 *
 * Usage:  node render.js "<url>"
 * Env:    RENDER_TIMEOUT_MS (default 25000), RENDER_UA (user-agent), RENDER_MAX_APIS (default 40)
 *
 * Setup:  cd tools/competitor_render && npm install && npx playwright install chromium
 * Degrades gracefully: on any failure it prints {error:...} and the PHP side falls
 * back to the plain Chrome --dump-dom render.
 */
'use strict';

(async () => {
  const url = process.argv[2];
  const out = { url: url || '', status: 0, html: '', apis: [] };
  if (!url || !/^https?:\/\//i.test(url)) {
    out.error = 'invalid url';
    process.stdout.write(JSON.stringify(out));
    return;
  }

  const TIMEOUT = parseInt(process.env.RENDER_TIMEOUT_MS || '25000', 10);
  const MAX_APIS = parseInt(process.env.RENDER_MAX_APIS || '40', 10);
  const UA = process.env.RENDER_UA
    || 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 '
     + '(KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';

  let chromium;
  try {
    ({ chromium } = require('playwright'));
  } catch (e) {
    out.error = 'playwright not installed';
    process.stdout.write(JSON.stringify(out));
    return;
  }

  let browser;
  try {
    browser = await chromium.launch({
      headless: true,
      args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
    });
    const ctx = await browser.newContext({ userAgent: UA, ignoreHTTPSErrors: true });
    const page = await ctx.newPage();

    // Capture JSON API responses (the data behind the SPA) — deduped, size-bounded.
    const seen = new Set();
    page.on('response', async (resp) => {
      try {
        if (out.apis.length >= MAX_APIS) return;
        const ct = String(resp.headers()['content-type'] || '');
        if (!/json/i.test(ct)) return;
        const u = resp.url();
        if (seen.has(u)) return;
        seen.add(u);
        const body = await resp.text();
        if (body && body.length > 40 && body.length < 2000000) {
          out.apis.push({ url: u, body: body });
        }
      } catch (e) { /* ignore a single unreadable response */ }
    });

    const resp = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: TIMEOUT });
    out.status = resp ? resp.status() : 0;
    try { await page.waitForLoadState('networkidle', { timeout: 8000 }); } catch (e) {}

    // Bounded scroll: trigger lazy-loaded product cards / infinite scroll.
    for (let i = 0; i < 4; i++) {
      try {
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        await page.waitForTimeout(700);
      } catch (e) { break; }
    }
    try { await page.waitForLoadState('networkidle', { timeout: 4000 }); } catch (e) {}

    out.html = await page.content();
  } catch (e) {
    out.error = String((e && e.message) || e);
  } finally {
    if (browser) { try { await browser.close(); } catch (e) {} }
  }

  process.stdout.write(JSON.stringify(out));
})();
