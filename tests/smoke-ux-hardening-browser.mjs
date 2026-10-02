#!/usr/bin/env node
/**
 * Browser fixture smoke for the hardened operator flows in admin.js.
 *
 * Loads the real admin JavaScript in a small DOM with mocked REST responses and
 * verifies the review guards added by the UX hardening pass:
 *   1. Start optimization requires an accepted confirm dialog before any
 *      manifest confirmation request leaves the browser.
 *   2. A second click while the foreground run is active cannot start a
 *      parallel run (re-entry guard).
 *   3. Whole-batch restore reports per-item failures as an error instead of
 *      claiming universal success.
 * No WordPress, Cloud, Adapter, or Core records are created.
 */

import { createRequire } from 'node:module';
import { existsSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
import { resolve } from 'node:path';

function pass(message) {
	console.log(`PASS: ${message}`);
}

function fail(message) {
	console.error(`FAIL: ${message}`);
	process.exit(1);
}

function assert(condition, message) {
	if (!condition) {
		fail(message);
	}
	pass(message);
}

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
			fail(`Playwright is not available. Install it or set NODE_PATH to the bundled runtime. ${fallbackError.message || error.message}`);
		}
	}
}

const { chromium } = await loadPlaywright();
const browserOptions = {
	headless: process.env.HEADLESS !== '0',
};
const chrome = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
if (process.env.BROWSER_EXECUTABLE) {
	browserOptions.executablePath = process.env.BROWSER_EXECUTABLE;
} else if (existsSync(chrome)) {
	browserOptions.executablePath = chrome;
}

const browser = await chromium.launch(browserOptions);
try {
	const page = await browser.newPage();
	page.on('pageerror', (error) => fail(`Fixture page error: ${String(error).slice(0, 400)}`));
	await page.setContent(`
		<!doctype html>
		<html>
			<body>
				<form data-toolbox-media-derivative>
					<div class="npcink-toolbox__result is-empty" hidden></div>
					<label><input type="checkbox" data-toolbox-media-batch-candidate="101" checked /> first.png</label>
					<label><input type="checkbox" data-toolbox-media-batch-candidate="102" checked /> second.png</label>
					<button type="button" data-toolbox-submit-media-batch-proposals>Start optimization</button>
					<button type="button" data-toolbox-restore-media-batch-all>Restore whole batch</button>
					<div data-toolbox-media-batch-progress hidden></div>
				</form>
			</body>
		</html>
	`);
	await page.evaluate(() => {
		window.wp = { i18n: { __: (text) => String(text) } };
		window.NpcinkToolbox = {
			restUrl: 'https://fixture.local/wp-json/npcink-toolbox/v1',
			adapterRestUrl: 'https://fixture.local/wp-json/npcink-openclaw-adapter/v1',
			nonce: 'fixture',
			labels: { running: 'Running...', error: 'Request failed.' },
		};
		window.__uxSmokeRequests = [];
		window.__uxSmokeConfirmGate = null;
		const jsonResponse = (status, body) => Promise.resolve({
			ok: status >= 200 && status < 300,
			status,
			json: () => Promise.resolve(body),
		});
		const batchShape = (items) => ({
			batch_id: 'media_opt_fixture',
			manifest_digest: 'fixture-digest',
			status: 'running',
			items,
			summary: { success: 0, skipped: 0, failed: 0, bytes_saved: 0 },
		});
		window.__uxSmokeSetBatch = () => {
			const form = document.querySelector('form[data-toolbox-media-derivative]');
			form.__npcinkMediaOptimizationBatch = Object.assign(batchShape([
				{ attachment_id: 101, title: 'first.png', status: 'pending', cloud_request_input: {} },
				{ attachment_id: 102, title: 'second.png', status: 'pending', cloud_request_input: {} },
			]), { status: 'pending' });
		};
		window.fetch = async (url, options) => {
			const requestUrl = String(url);
			window.__uxSmokeRequests.push({
				url: requestUrl,
				method: String(options && options.method || 'GET'),
				body: String(options && options.body || ''),
			});
			if (requestUrl.endsWith('/media-optimization-health')) {
				return jsonResponse(200, { ready: true });
			}
			if (/media-optimization-batches\/[^/]+\/confirm$/.test(requestUrl)) {
				if (window.__uxSmokeConfirmGate) {
					await window.__uxSmokeConfirmGate.promise;
				}
				return jsonResponse(200, batchShape([]));
			}
			if (/items\/101\/complete$/.test(requestUrl)) {
				return jsonResponse(200, batchShape([]));
			}
			if (/items\/102\/complete$/.test(requestUrl)) {
				return jsonResponse(200, batchShape([]));
			}
			if (/items\/101\/restore$/.test(requestUrl)) {
				return jsonResponse(200, batchShape([]));
			}
			if (/items\/102\/restore$/.test(requestUrl)) {
				return jsonResponse(500, { code: 'fixture_restore_failed', message: 'Fixture restore failure.' });
			}
			return jsonResponse(200, { status: 'completed' });
		};
		window.__uxSmokeSetBatch();
	});
	await page.addScriptTag({ path: resolve('assets/admin.js') });

	const confirmRequests = () => page.evaluate(() => window.__uxSmokeRequests.filter((request) => /media-optimization-batches\/[^/]+\/confirm$/.test(request.url)));
	const resultText = () => page.evaluate(() => {
		const node = document.querySelector('.npcink-toolbox__result');
		return node ? node.textContent : '';
	});

	// 1. Dismissing the reviewed confirm dialog sends no manifest confirmation.
	let dialogs = [];
	page.on('dialog', async (dialog) => {
		dialogs.push({ type: dialog.type(), message: dialog.message() });
		await dialog.dismiss().catch(() => {});
	});
	await page.click('[data-toolbox-submit-media-batch-proposals]');
	await page.waitForTimeout(200);
	assert(dialogs.length === 1 && dialogs[0].type === 'confirm', 'Start optimization asks for a reviewed confirm dialog first.');
	assert((await confirmRequests()).length === 0, 'Dismissing the confirm dialog sends no manifest confirmation request.');

	// 2. Accepting starts exactly one run; a click while the run is active cannot start a second.
	dialogs = [];
	page.removeAllListeners('dialog');
	page.on('dialog', async (dialog) => {
		dialogs.push({ type: dialog.type() });
		await dialog.accept().catch(() => {});
	});
	await page.evaluate(() => {
		window.__uxSmokeConfirmGate = { promise: new Promise((resolveGate) => { window.__uxSmokeOpenGate = resolveGate; }) };
	});
	await page.click('[data-toolbox-submit-media-batch-proposals]');
	await page.waitForTimeout(300);
	await page.click('[data-toolbox-submit-media-batch-proposals]');
	await page.waitForTimeout(300);
	assert(dialogs.length === 1, 'A second click while the foreground run is active is rejected without another dialog.');
	assert((await confirmRequests()).length === 1, 'The active run keeps exactly one manifest confirmation request.');
	await page.evaluate(() => window.__uxSmokeOpenGate());
	await page.waitForTimeout(500);

	// 3. A failing whole-batch restore reports the failing item as an error.
	dialogs = [];
	page.removeAllListeners('dialog');
	page.on('dialog', async (dialog) => {
		dialogs.push({ type: dialog.type(), message: dialog.message() });
		await dialog.accept().catch(() => {});
	});
	await page.evaluate(() => {
		window.__uxSmokeSetBatch();
		const form = document.querySelector('form[data-toolbox-media-derivative]');
		form.__npcinkMediaOptimizationBatch.items = [
			{ attachment_id: 101, title: 'first.png', status: 'completed', restore_status: '' },
			{ attachment_id: 102, title: 'second.png', status: 'completed', restore_status: '' },
		];
	});
	await page.click('[data-toolbox-restore-media-batch-all]');
	await page.waitForTimeout(500);
	assert(dialogs.length === 1 && dialogs[0].message.includes('2'), 'Whole-batch restore confirms the exact item count before running.');
	const restoreOutcome = await resultText();
	assert(restoreOutcome.includes('1') && restoreOutcome.toLowerCase().includes('could not be restored'), 'A failing whole-batch restore reports the per-item failure count instead of claiming success.');
	assert((await page.evaluate(() => window.__uxSmokeRequests.filter((request) => /\/restore$/.test(request.url)).length)) === 2, 'Both restore attempts were made despite the isolated failure.');
	pass('UX hardening browser fixture smoke completed.');
} finally {
	await browser.close();
}
