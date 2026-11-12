#!/usr/bin/env node
/** Installed regression for retired backend ALT entries; no model or write actions. */
import { randomBytes } from 'node:crypto';
import { existsSync, mkdirSync, readdirSync, unlinkSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
function fail(message) { throw new Error(message); }
function assert(condition, message) { if (!condition) fail(message); console.log(`PASS: ${message}`); }
async function loadPlaywright() {
	try {
		return await import('playwright');
	} catch (error) {
		const require = createRequire(import.meta.url);
		const paths = String(process.env.NODE_PATH || '').split(':').filter(Boolean);
		try {
			const resolved = require.resolve('playwright', { paths });
			const module = await import(pathToFileURL(resolved).href);
			return module.chromium ? module : module.default;
		} catch (fallbackError) {
			fail(`Playwright is not available. Install it or set NODE_PATH to the bundled runtime before running this smoke. ${fallbackError.message || error.message}`);
		}
	}
}

function env(name, fallback) {
	return process.env[name] || fallback;
}

function wpPath() { return env('WP_PATH', '/Users/muze/Local Sites/magick-ai/app/public'); }
function createLoginHelper(baseUrl) {
	const token = randomBytes(24).toString('hex');
	const fileName = `npcink-toolbox-media-alt-smoke-login-${randomBytes(8).toString('hex')}.php`;
	const filePath = `${wpPath().replace(/\/$/, '')}/${fileName}`;
	writeFileSync(filePath, `<?php
declare(strict_types=1);
$expected = '${token}';
if (!isset($_GET['token']) || !hash_equals($expected, (string) $_GET['token'])) {
	http_response_code(403);
	exit('forbidden');
}
require __DIR__ . '/wp-load.php';
$users = get_users(array('role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC'));
$user = $users ? $users[0] : null;
if (!$user) {
	http_response_code(500);
	exit('no_admin_user');
}
wp_set_current_user($user->ID);
wp_set_auth_cookie($user->ID, false, is_ssl());
wp_safe_redirect(admin_url('admin.php?page=npcink-toolbox&toolbox_tab=tools'));
exit;
`);
	return {
		url: `${baseUrl}/${fileName}?token=${token}`,
		cleanup: () => {
			try {
				unlinkSync(filePath);
			} catch (error) {
				// The smoke should not fail only because cleanup raced a local server.
			}
		},
	};
}

function findPlaywrightChromiumExecutable(chromium) {
	const candidates = [];
	try {
		candidates.push(chromium.executablePath());
	} catch (error) {
		// Some bundled runtimes can resolve Playwright without an installed browser.
	}
	const cacheRoot = `${process.env.HOME}/Library/Caches/ms-playwright`;
	if (existsSync(cacheRoot)) {
		const revisions = readdirSync(cacheRoot)
			.filter((entry) => /^chromium-\d+$/.test(entry))
			.sort((left, right) => parseInt(right.replace('chromium-', ''), 10) - parseInt(left.replace('chromium-', ''), 10));
		for (const revision of revisions) {
			candidates.push(`${cacheRoot}/${revision}/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing`);
		}
	}
	return candidates.find((candidate) => candidate && existsSync(candidate)) || '';
}

const { chromium } = await loadPlaywright();
const baseUrl = env('WP_BASE_URL', 'https://magick-ai.local').replace(/\/$/, '');
const artifactDir = env('SMOKE_ARTIFACT_DIR', 'output/playwright/backend-alt-retirement');
mkdirSync(artifactDir, { recursive: true });
const executablePath = process.env.BROWSER_EXECUTABLE || findPlaywrightChromiumExecutable(chromium);
const browser = await chromium.launch({ headless: true, ...(executablePath ? { executablePath } : {}) });
const login = createLoginHelper(baseUrl);
const unexpected = [];
const errors = [];
let page;
try {
 const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 1000 } });
 page = await context.newPage();
 page.on('pageerror', error => errors.push(error.message));
 await page.route('**/wp-json/**', async route => {
  const url = route.request().url();
  if (/npcink-toolbox.*(?:ai\/site-helpers|media-alt|proposals)|npcink-ai.*proposals/.test(url)) {
   unexpected.push(url); await route.abort(); return;
  }
  await route.continue();
 });
 await page.goto(login.url, { waitUntil: 'networkidle' });
 const toolsUrl = `${baseUrl}/wp-admin/admin.php?page=npcink-toolbox&toolbox_tab=tools`;
 async function checkSettings(label) {
  assert(await page.locator('[data-toolbox-tool-panel="image-settings"]:visible').count() === 1, `${label}: image settings are active.`);
  assert(await page.locator('[data-toolbox-media-backup-retention]').count() === 1, `${label}: backup settings remain.`);
  assert(await page.locator('[data-toolbox-watermark-library]').count() === 1, `${label}: watermark settings remain.`);
  assert(await page.locator('[data-toolbox-media-alt-caption-review], [data-toolbox-tool-panel="media-library"], a[href*="toolbox_tool=media-alt-caption-review"]').count() === 0, `${label}: no backend ALT workbench or shortcut exists.`);
 }
 await checkSettings('Default URL');
 for (const query of ['media-alt-caption-review', 'media-alt-caption-review&attachment_ids=282285,282284', 'media-library', 'unknown', 'image-settings', 'media-batch-optimize']) {
  await page.goto(`${toolsUrl}&toolbox_tool=${query}`, { waitUntil: 'networkidle' });
  await checkSettings(query);
 }
 for (const width of [1440, 390]) {
  await page.setViewportSize({ width, height: 1000 });
  await page.goto(toolsUrl, { waitUntil: 'networkidle' });
  assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${width}px settings have no horizontal overflow.`);
  await page.screenshot({ path: `${artifactDir}/settings-${width}.png`, fullPage: true });
 }
 await page.setViewportSize({ width: 1440, height: 1000 });
 await page.goto(`${baseUrl}/wp-admin/upload.php?mode=list`, { waitUntil: 'networkidle' });
 assert(await page.locator('option[value="npcink_toolbox_batch_alt"], .npcink_toolbox_alt, a[href*="toolbox_tool=media-alt-caption-review"]').count() === 0, 'Media Library has no ALT bulk or row action.');
 const attachmentLink = await page.locator('#the-list .column-title strong a').first().getAttribute('href');
 assert(!!attachmentLink, 'A real attachment is available to check details.');
 await page.goto(new URL(attachmentLink, baseUrl).href, { waitUntil: 'networkidle' });
 assert(await page.locator('[id*="npcink_toolbox_ai_image_optimization"], .npcink-toolbox-media-library-action, a[href*="toolbox_tool=media-alt-caption-review"]').count() === 0, 'Attachment details have no Toolbox ALT action.');
 const noJs = await browser.newContext({ ignoreHTTPSErrors: true, javaScriptEnabled: false, storageState: await context.storageState() });
 const plainPage = await noJs.newPage();
 await plainPage.goto(`${toolsUrl}&toolbox_tool=media-alt-caption-review&attachment_ids=282285`, { waitUntil: 'load' });
 assert(await plainPage.locator('[data-toolbox-tool-panel="image-settings"]:visible').count() === 1 && await plainPage.locator('[data-toolbox-media-alt-caption-review]').count() === 0, 'Without JavaScript the retired URL renders settings only.');
 await noJs.close();
 assert(unexpected.length === 0, 'Retired entries send no AI, ALT planning or proposal requests.');
 assert(errors.length === 0, `No browser JavaScript errors: ${errors.join('; ')}`);
} catch (error) {
 if (page) await page.screenshot({ path: `${artifactDir}/failure.png`, fullPage: true }).catch(() => {});
 console.error(error); process.exitCode = 1;
} finally { login.cleanup(); await browser.close(); }
