// Lab-only browser check for the ok_exchange365_mailer test matrix.
//
// Logs into the lab backend, opens System > Configuration and proves that the
// Exchange 365 client secret configured in TYPO3_CONF_VARS is shown masked, never
// in plain text. Writes screenshots and a JSON result next to the matrix report.
//
//   LAB_URL=https://… ADMIN_USER=… ADMIN_PASSWORD=… CHECK_SECRET=… OUT_DIR=… MAJOR=14 \
//     node Build/testing/browser/backend-check.mjs
//
// Exit code 0 = pass, 1 = fail. Never prints the secret.

import { chromium } from 'playwright-core';
import { mkdirSync, writeFileSync } from 'node:fs';

const env = (name) => {
  const value = process.env[name] ?? '';
  if (value === '') {
    console.error(`backend-check: ${name} is not set`);
    process.exit(1);
  }
  return value;
};

const labUrl = env('LAB_URL').replace(/\/$/, '');
const secret = env('CHECK_SECRET');
const outDir = env('OUT_DIR');
const major = env('MAJOR');
mkdirSync(outDir, { recursive: true });

const shot = (page, name) => page.screenshot({ path: `${outDir}/v${major}-${name}.png`, fullPage: true });
const result = { major, steps: [] };
const step = (name, ok, detail = '') => {
  result.steps.push({ name, ok, detail });
  console.log(`backend-check: ${ok ? 'ok  ' : 'FAIL'} ${name}${detail ? ` - ${detail}` : ''}`);
  return ok;
};

// Module menu entries differ per major: data attributes on 12+, li ids on 9-11.
const MODULE_SELECTORS = [
  '[data-modulemenu-identifier="system_config"]',
  '#system_config a',
  'a#system_config',
  '[data-moduleroute-identifier="system_config"]',
  '[data-modulename="system_config"]',
];

// *.ddev.site normally resolves through public DNS, which is not always reachable
// (WSL, offline). The DDEV router listens on localhost, so map it directly.
const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP *.ddev.site 127.0.0.1'] });
let passed = false;

try {
  const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();

  await page.goto(`${labUrl}/typo3/`, { waitUntil: 'networkidle' });
  await page.fill('#t3-username', env('ADMIN_USER'));
  await page.fill('#t3-password', env('ADMIN_PASSWORD'));
  await Promise.all([
    page.waitForLoadState('networkidle'),
    page.click('#t3-login-submit'),
  ]);
  await page.waitForTimeout(1500);
  const loggedIn = (await page.locator('#t3-username').count()) === 0;
  await shot(page, 'backend');
  if (!step('backend login', loggedIn)) {
    throw new Error('login failed');
  }

  let opened = false;
  for (const selector of MODULE_SELECTORS) {
    const entry = page.locator(selector).first();
    if ((await entry.count()) > 0) {
      await entry.click();
      opened = true;
      break;
    }
  }
  if (!step('open System > Configuration', opened, opened ? '' : 'module menu entry not found')) {
    throw new Error('module not found');
  }

  // The module renders in the content iframe on every major. Wait until that
  // frame shows the configuration module and its markup has stopped changing.
  const findModuleFrame = async () => {
    for (const frame of page.frames()) {
      if (frame === page.mainFrame() || !/config/.test(frame.url())) {
        continue;
      }
      const html = await frame.content().catch(() => '');
      if (html.includes('TYPO3_CONF_VARS') && /searchString|searchValue/.test(html)) {
        return frame;
      }
    }
    return null;
  };
  const settledHtml = async () => {
    let previous = -1;
    for (let i = 0; i < 40; i++) {
      await page.waitForTimeout(250);
      const frame = await findModuleFrame();
      const html = frame ? await frame.content().catch(() => '') : '';
      if (frame && html.length === previous) {
        return { frame, html };
      }
      previous = frame ? html.length : -1;
    }
    return { frame: null, html: '' };
  };

  let { frame: moduleFrame, html } = await settledHtml();
  if (!step('configuration tree loaded', moduleFrame !== null)) {
    throw new Error('configuration tree not found');
  }

  // TYPO3 9-11 search server-side and only render matching branches expanded;
  // 12+ render the whole tree and filter in the browser.
  const legacySearch = moduleFrame.locator('input[name="searchString"]');
  if (!html.includes('transport_exchange365_clientSecret') && (await legacySearch.count()) > 0) {
    await legacySearch.fill('exchange365');
    await legacySearch.press('Enter');
    await page.waitForTimeout(1000);
    ({ frame: moduleFrame, html } = await settledHtml());
    html = html ?? '';
  }
  if (moduleFrame) {
    const search = moduleFrame.locator('input[name="searchValue"]');
    if ((await search.count()) > 0) {
      await search.fill('exchange365');
      await page.waitForTimeout(800);
    }
  }
  await shot(page, 'config-module');

  const keyShown = html.includes('transport_exchange365_clientSecret');
  const leaked = html.includes(secret);
  // The masked value must belong to the clientSecret entry itself - the core also
  // blinds the database password with asterisks elsewhere in the same tree.
  const masked = /transport_exchange365_clientSecret[\s\S]{0,400}?\*{6}/.test(html);

  step('clientSecret key visible in tree', keyShown);
  step('clientSecret never shown in plain text', !leaked, leaked ? 'PLAIN TEXT SECRET IN PAGE' : '');
  step('clientSecret shown masked', masked);

  passed = keyShown && !leaked && masked;
} catch (error) {
  step('unexpected error', false, String(error?.message ?? error).split('\n')[0]);
} finally {
  await browser.close();
  result.passed = passed;
  writeFileSync(`${outDir}/v${major}-browser.json`, JSON.stringify(result, null, 2));
}

process.exit(passed ? 0 : 1);
