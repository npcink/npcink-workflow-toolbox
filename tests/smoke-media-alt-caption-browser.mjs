#!/usr/bin/env node
/**
 * Optional browser smoke for the Media ALT/Caption admin review UI.
 *
 * This opens a real local WordPress admin page, builds a bounded review set
 * from explicit real attachment ids, and checks operator-facing handoff state.
 * Controlled browser responses exercise proposal receipts and visual failure
 * states; no real Core proposal, model request or media write is made.
 */

import { execFileSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { existsSync, mkdirSync, readdirSync, unlinkSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

function pass(message) {
	console.log(`PASS: ${message}`);
}

function fail(message) {
	throw new Error(`FAIL: ${message}`);
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
			fail(`Playwright is not available. Install it or set NODE_PATH to the bundled runtime before running this smoke. ${fallbackError.message || error.message}`);
		}
	}
}

function env(name, fallback) {
	return process.env[name] || fallback;
}

function intEnv(name, fallback) {
	const parsed = parseInt(env(name, String(fallback)), 10);
	return Number.isInteger(parsed) && parsed >= 0 ? parsed : fallback;
}

function wpPath() {
	return env('WP_PATH', '/Users/muze/Local Sites/magick-ai/app/public');
}

function wpCli(args, options = {}) {
	const php = env('WP_CLI_PHP', `${process.env.HOME}/Library/Application Support/Local/lightning-services/php-8.5.3+1/bin/darwin-arm64/bin/php`);
	const wp = env('WP_CLI_BIN', '/opt/homebrew/bin/wp');
	const socket = env('WP_DB_SOCKET', `${process.env.HOME}/Library/Application Support/Local/run/NPb24Zg9g/mysql/mysqld.sock`);
	return execFileSync(
		php,
		[
			'-d',
			'display_errors=0',
			'-d',
			'error_reporting=8191',
			'-d',
			`mysqli.default_socket=${socket}`,
			wp,
			`--path=${wpPath()}`,
			'--no-color',
			...args,
		],
		{
			encoding: 'utf8',
			stdio: ['ignore', 'pipe', 'pipe'],
			...options,
		}
	).trim();
}

function parseAttachmentIds() {
	const raw = env('MEDIA_ALT_CAPTION_BROWSER_ATTACHMENT_IDS', '7774,769,767,766,765');
	const ids = raw
		.split(',')
		.map((value) => parseInt(value.trim(), 10))
		.filter((value) => Number.isInteger(value) && value > 0);
	if (!ids.length) {
		fail('MEDIA_ALT_CAPTION_BROWSER_ATTACHMENT_IDS did not contain any positive attachment ids.');
	}
	return ids;
}

function verifyAttachmentIds(ids) {
	const code = `
$ids = array_filter(array_map('absint', explode(',', '${ids.join(',')}')));
$valid = array();
foreach ($ids as $id) {
	$post = get_post($id);
	$mime = get_post_mime_type($id);
	if ($post && 'attachment' === $post->post_type && is_string($mime) && str_starts_with($mime, 'image/')) {
		$valid[] = $id;
	}
}
echo wp_json_encode(array_values($valid));
`;
	const output = wpCli(['eval', code], { stdio: ['ignore', 'pipe', 'pipe'] });
	let valid;
	try {
		valid = JSON.parse(output.replace(/Deprecated:[\s\S]*?\n/g, '').trim());
	} catch (error) {
		fail(`Could not parse attachment validation output. ${error.message}`);
	}
	assert(Array.isArray(valid) && valid.length === ids.length, 'Smoke attachment ids exist and are image attachments.');
}

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
wp_safe_redirect(admin_url('admin.php?page=npcink-toolbox&toolbox_tab=tools&toolbox_tool=media-alt-caption-review'));
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

function createRuntimeFilter() {
	const directory = `${wpPath().replace(/\/$/, '')}/wp-content/mu-plugins`;
	mkdirSync(directory, { recursive: true });
	const filePath = `${directory}/npcink-toolbox-media-alt-smoke-runtime-${randomBytes(8).toString('hex')}.php`;
	writeFileSync(filePath, `<?php
/**
 * Temporary Media ALT/Caption browser-smoke runtime filter.
 *
 * This file is created and removed by tests/smoke-media-alt-caption-browser.mjs.
 */
add_filter(
	'npcink_toolbox_hosted_ai_site_helper_cloud_request',
	static function ( $handled, array $runtime_payload, array $input ) {
		if ( 'media_alt_suggestions' !== (string) ( $input['intent'] ?? '' ) ) {
			return $handled;
		}

		return array(
			'status' => 'ready',
			'run_id' => 'media_alt_caption_browser_smoke',
			'result' => array(
				'status' => 'ready',
				'model_id' => 'local_browser_smoke_no_cloud_runtime',
				'output_text' => 'Browser smoke: build the metadata-only review set locally and confirm operator handoff controls.',
			),
		);
	},
	10,
	3
);
`);
	return {
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

function forbiddenExecutionRequests(requests) {
	return requests.filter((request) => {
		if (!/POST|PUT|PATCH|DELETE/.test(request.method)) {
			return false;
		}
		return /approve-and-execute|\/proposals\/[^/]+\/execute|media-derivative-runs|local-admin-consent|\/wp\/v2\/media/i.test(request.url);
	});
}

async function openAltCaptionPanel(page, baseUrl) {
	const paths = [
		'/wp-admin/admin.php?page=npcink-toolbox&toolbox_tab=tools&toolbox_tool=media-alt-caption-review',
		'/wp-admin/tools.php?page=npcink-toolbox&toolbox_tab=tools&toolbox_tool=media-alt-caption-review',
	];
	for (const path of paths) {
		await page.goto(`${baseUrl}${path}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
		const count = await page.locator('[data-toolbox-media-alt-caption-review]').count();
		if (count > 0) {
			return `${baseUrl}${path}`;
		}
	}
	const body = await page.locator('body').innerText({ timeout: 5000 }).catch(() => '');
	fail(`Could not find the Media ALT/Caption admin panel. Last URL: ${page.url()}. Body: ${body.slice(0, 800)}`);
}

async function captureDiagnostics(page, requests, error) {
	const artifactDir = env('SMOKE_ARTIFACT_DIR', 'tests/artifacts');
	mkdirSync(artifactDir, { recursive: true });
	const screenshotPath = `${artifactDir}/media-alt-caption-browser-failure.png`;
	await page.screenshot({ path: screenshotPath, fullPage: true }).catch(() => {});
	const pageText = await page.locator('body').innerText({ timeout: 2000 }).catch(() => '');
	console.error(`FAIL: Media ALT/Caption browser smoke diagnostic screenshot: ${screenshotPath}`);
	console.error(`FAIL: Media ALT/Caption browser smoke current URL: ${page.url()}`);
	console.error(`FAIL: Media ALT/Caption browser smoke page title: ${await page.title().catch(() => '')}`);
	console.error(`FAIL: Media ALT/Caption browser smoke wp-json requests: ${requests.length}`);
	console.error(`FAIL: Media ALT/Caption browser smoke visible text sample: ${pageText.replace(/\s+/g, ' ').trim().slice(0, 1200)}`);
	console.error(`FAIL: Media ALT/Caption browser smoke error: ${error && error.message ? error.message : String(error || 'unknown error')}`);
}

async function captureViewportScreenshot(page, screenshotPath) {
	try {
		await page.screenshot({ path: screenshotPath, fullPage: false, animations: 'disabled', timeout: 15000 });
		return;
	} catch (error) {
		// Some system Chrome builds hang on Playwright screenshots; fall back to CDP.
	}
	const client = await page.context().newCDPSession(page);
	const result = await client.send('Page.captureScreenshot', {
		format: 'png',
		fromSurface: true,
		captureBeyondViewport: false,
	});
	writeFileSync(screenshotPath, Buffer.from(result.data, 'base64'));
	await client.detach().catch(() => {});
}

async function verifyContextualEntry(page, context, baseUrl) {
	const toolboxUrl = `${baseUrl}/wp-admin/admin.php?page=npcink-toolbox&toolbox_tab=tools`;
	const artifactDir = resolve('output/playwright/media-alt-contextual-entry');
	mkdirSync(artifactDir, { recursive: true });
	await page.goto(toolboxUrl, { waitUntil: 'domcontentloaded' });
	assert(await page.locator('[data-toolbox-media-alt-caption-review]').count() === 0, 'Default image page does not render the ALT workbench.');
	const entry = page.locator('[data-toolbox-tool-panel="media-library"]');
	const supportLink = entry.locator('a[href*="toolbox_tool=media-alt-caption-review"]');
	assert(await entry.isVisible() && !await supportLink.isVisible(), 'Default entry leads to the Media Library; the ALT support link is folded.');
	for (const width of [1440, 390]) {
		await page.setViewportSize({ width, height: 1000 });
		assert(await entry.evaluate(element => element.scrollWidth <= element.clientWidth), `Default image entry fits ${width}px.`);
		await page.screenshot({ path: `${artifactDir}/default-${width}.png`, fullPage: true });
	}
	await entry.locator('summary').click();
	await supportLink.click();
	let form = page.locator('[data-toolbox-media-alt-caption-review]');
	await form.waitFor({ state: 'visible' });
	assert(await form.isVisible() && await form.locator('button[type="submit"]').isDisabled(), 'Explicit support page waits for image selection.');
	assert(!await form.locator('input[name="attachment_ids"]').isVisible() && !await form.locator('select[name="sample_size"]').isVisible(), 'Attachment IDs and sample controls are folded by default.');
	await form.locator('[data-toolbox-alt-support] > summary').click();
	await form.locator('.npcink-toolbox__alt-sample-options > summary').click();
	await page.waitForFunction(() => !document.querySelector('[data-toolbox-media-alt-caption-review] button[type="submit"]').disabled);
	assert(await form.locator('button[type="submit"]').isEnabled(), 'Explicitly opening sampling enables the retained bounded sample.');
	await form.locator('.npcink-toolbox__alt-sample-options > summary').click();
	await page.waitForFunction(() => document.querySelector('[data-toolbox-media-alt-caption-review] button[type="submit"]').disabled);
	assert(await form.locator('button[type="submit"]').isDisabled(), 'Closing sampling with no selection disables generation again.');
	await page.setViewportSize({ width: 1440, height: 1000 });
	await form.locator('a[href*="upload.php"]').click();
	const checkboxes = page.locator('#the-list tr:has(.npcink_toolbox_alt) input[name="media[]"]');
	await checkboxes.first().waitFor({ state: 'attached' });
	const count = Math.min(2, await checkboxes.count());
	assert(count > 0, 'Media Library lists images with contextual ALT actions.');
	const selected = [];
	for (let index = 0; index < count; index += 1) {
		selected.push(await checkboxes.nth(index).inputValue());
		await checkboxes.nth(index).check();
	}
	await page.locator('#bulk-action-selector-top').selectOption('npcink_toolbox_batch_alt');
	await Promise.all([page.waitForURL(/toolbox_tool=media-alt-caption-review/), page.locator('#doaction').click()]);
	form = page.locator('[data-toolbox-media-alt-caption-review]');
	assert(new URL(page.url()).searchParams.get('attachment_ids') === selected.join(','), 'Native Media Library bulk action carries exactly the selected images.');
	assert(await form.locator('button[type="submit"]').isEnabled() && !await form.locator('input[name="attachment_ids"]').isVisible(), 'Selected-image page is ready with technical inputs still folded.');
	assert((await form.locator('[data-toolbox-selected-attachment-summary]').innerText()).includes(String(count)), 'Selection summary shows an image count.');
	const selectedUrl = page.url();
	await page.reload({ waitUntil: 'domcontentloaded' });
	assert(await form.locator('input[name="attachment_ids"]').inputValue() === selected.join(', '), 'Reload preserves the selected images.');
	for (const width of [1440, 390]) {
		await page.setViewportSize({ width, height: 1000 });
		assert(await form.evaluate(element => element.scrollWidth <= element.clientWidth), `Selected-image page fits ${width}px.`);
		await page.screenshot({ path: `${artifactDir}/selected-${width}.png`, fullPage: true });
	}
	visualScenario = 'ready';
	await form.locator('button[type="submit"]').click();
	await form.locator('[data-toolbox-media-alt-caption-item]').first().waitFor({ state: 'visible' });
	assert(await form.evaluate(element => element.scrollWidth <= element.clientWidth), 'Mobile selected-image generation works with support details folded and without overflow.');
	await page.screenshot({ path: `${artifactDir}/preview-390.png`, fullPage: true });
	visualScenario = 'legacy';
	await form.locator('[data-toolbox-alt-support] > summary').click();
	await form.locator('input[name="attachment_ids"]').fill('');
	assert(await form.locator('button[type="submit"]').isDisabled(), 'Clearing the support selection does not refill IDs from the URL or start a sample.');
	const noJs = await browser.newContext({ ignoreHTTPSErrors: true, javaScriptEnabled: false, storageState: await context.storageState() });
	try {
		const noJsPage = await noJs.newPage();
		await noJsPage.goto(toolboxUrl, { waitUntil: 'domcontentloaded' });
		assert(await noJsPage.locator('[data-toolbox-media-alt-caption-review]').count() === 0, 'Default ALT workbench is absent without JavaScript.');
		await noJsPage.goto(selectedUrl, { waitUntil: 'domcontentloaded' });
		assert(await noJsPage.locator('input[name="attachment_ids"]').inputValue() === selected.join(', '), 'Server-rendered selected IDs survive without JavaScript.');
		assert(await noJsPage.locator('[data-toolbox-media-alt-caption-review] noscript').isVisible(), 'No-JS view explains that generation and sampling require JavaScript.');
	} finally {
		await noJs.close();
	}
	await page.goto(`${toolboxUrl}&toolbox_tool=image-settings`, { waitUntil: 'domcontentloaded' });
	assert(await page.locator('[data-toolbox-tool-panel="image-settings"]').isVisible(), 'Existing watermark and backup settings URL remains usable.');
	await page.goto(`${toolboxUrl}&toolbox_tool=unknown`, { waitUntil: 'domcontentloaded' });
	assert(await page.locator('[data-toolbox-tool-panel="media-library"]').isVisible() && await page.locator('[data-toolbox-media-alt-caption-review]').count() === 0, 'Unknown tools fall back to the Media Library entry.');
}

const { chromium } = await loadPlaywright();
const baseUrl = env('WP_BASE_URL', 'https://magick-ai.local').replace(/\/$/, '');
const attachmentIds = parseAttachmentIds();
const screenshotPath = resolve(env('MEDIA_ALT_CAPTION_BROWSER_SCREENSHOT', 'build/smoke/media-alt-caption-browser.png'));
const expectedReviewRowsMin = intEnv('MEDIA_ALT_CAPTION_BROWSER_EXPECT_REVIEW_ROWS_MIN', attachmentIds.length);
const expectedCaptionOnlyMin = intEnv('MEDIA_ALT_CAPTION_BROWSER_EXPECT_CAPTION_ONLY_MIN', 1);
const expectedContextRowsMin = intEnv('MEDIA_ALT_CAPTION_BROWSER_EXPECT_CONTEXT_ROWS_MIN', 4);
const expectedReadyRowsMin = intEnv('MEDIA_ALT_CAPTION_BROWSER_EXPECT_READY_ROWS_MIN', 0);
verifyAttachmentIds(attachmentIds);
const thumbnails = JSON.parse(wpCli(['eval', `echo wp_json_encode(array_map(static function ($id) { return wp_get_attachment_image_url($id, 'thumbnail'); }, array(${attachmentIds.join(',')})));`]));

const browserOptions = {
	headless: process.env.HEADLESS !== '0',
};
if (process.env.BROWSER_EXECUTABLE) {
	browserOptions.executablePath = process.env.BROWSER_EXECUTABLE;
} else {
	const playwrightChrome = findPlaywrightChromiumExecutable(chromium);
	if (playwrightChrome) {
		browserOptions.executablePath = playwrightChrome;
	} else {
		const normalChrome = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
		if (existsSync(normalChrome)) {
			browserOptions.executablePath = normalChrome;
		}
	}
}

const browser = await chromium.launch(browserOptions);
let loginHelper = null;
let runtimeFilter = null;
let page = null;
const requests = [];
let visualScenario = 'legacy';
const pageErrors = [];
const visualCases = [];
try {
	runtimeFilter = createRuntimeFilter();
	const context = await browser.newContext({ ignoreHTTPSErrors: true });
	page = await context.newPage();
	page.on('pageerror', (error) => pageErrors.push(error.message));
	await page.route('**/wp-json/npcink-toolbox/v1/ai/site-helpers', async (route) => {
		const visual = visualScenario !== 'legacy';
		const availableIds = !visual ? attachmentIds : visualScenario === 'ready' ? attachmentIds.slice(0, 2) : visualScenario === 'partial' ? attachmentIds.slice(0, 1) : [];
		const selectedItems = availableIds.map((attachmentId, index) => ({
			attachment_id: attachmentId,
			title: `Browser smoke image ${index + 1}`,
			thumbnail_url: thumbnails[attachmentIds.indexOf(attachmentId)] || '',
			current_alt: '',
			current_alt_status: 'missing',
			review_reasons: ['missing_alt'],
			alt_candidates: !visual && index === attachmentIds.length - 1 ? [] : [`Reviewed browser smoke image ${index + 1}`],
			candidate_review_status: visual ? 'ready_for_review' : index === attachmentIds.length - 1 ? 'caption_review_only' : 'needs_context_confirmation',
			candidate_confidence: 'medium',
			candidate_quality_score: 80,
			candidate_quality_tier: 'review',
			automation_recommendation: 'human_review',
			candidate_fact_types: [visual ? 'visual_fact' : 'metadata_fact'],
			needs_context_confirmation: !visual && index !== attachmentIds.length - 1,
			needs_human_visual_check: true,
			source_item_id: `media-alt-caption:${attachmentId}`,
			evidence_refs: [`browser-smoke:${attachmentId}`],
		}));
		await route.fulfill({
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify({
				artifact_type: 'hosted_ai_site_helper',
				intent: 'media_alt_suggestions',
				status: visual ? visualScenario : 'ready',
				model_id: 'browser_smoke_fixture',
				media_alt_caption_review_set: {
					contract_version: 'media_alt_caption_review_set.v1',
					selected_items: selectedItems,
					blocked_items: visual && visualScenario !== 'ready' ? [{ attachment_id: attachmentIds[1], blocked_reason: visualScenario === 'processing' ? 'visual_evidence_pending' : 'visual_evidence_unavailable' }] : [],
					...(visual ? { visual_review: { status: visualScenario, unavailable_count: ['partial', 'unavailable'].includes(visualScenario) ? 1 : 0, pending_count: visualScenario === 'processing' ? 1 : 0 } } : {}),
					eligibility_summary: {
						scanned_count: attachmentIds.length,
						local_preview_candidate_count: visual ? selectedItems.length : Math.max(0, attachmentIds.length - 1),
						context_confirmation_count: visual ? 0 : Math.max(0, attachmentIds.length - 1),
						caption_review_only_count: visual ? 0 : attachmentIds.length ? 1 : 0,
						visual_evidence_request_count: visual ? (visualScenario === 'ready' ? 0 : 1) : Math.max(0, attachmentIds.length - 1),
						blocked_count: visual && visualScenario !== 'ready' ? 1 : 0,
					},
					image_context_evidence_request: { items: [] },
				},
			}),
		});
	});
	await page.route('**/wp-json/npcink-openclaw-adapter/v1/run-read-ability', async (route) => {
		const payload = JSON.parse(route.request().postData() || '{}');
		const input = payload.input || {};
		await route.fulfill({
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify({
				result: {
					data: {
						artifact_type: 'media_alt_apply_plan',
						contract_version: 'media_alt_apply_plan.v1',
						proposal_mode: 'single',
						dry_run: true,
						commit_execution: false,
						direct_wordpress_write: false,
						authorization: { classification: 'core_proposal_required' },
						write_actions: [{ target_ability_id: 'npcink-abilities-toolkit/update-media-details', input }],
					},
				},
			}),
		});
	});
	await page.route('**/wp-json/npcink-openclaw-adapter/v1/proposals/from-plan', async (route) => {
		const payload = JSON.parse(route.request().postData() || '{}');
		const attachmentId = Number(payload.plan_input?.attachment_id || 0);
		await route.fulfill({
			status: 201,
			contentType: 'application/json',
			body: JSON.stringify({
				proposal_count: 1,
				commit_execution: false,
				proposals: [{
					proposal_id: `fixture-media-alt-${attachmentId}`,
					status: 'pending',
					ability_id: 'npcink-abilities-toolkit/update-media-details',
				}],
			}),
		});
	});
	page.on('request', (request) => {
		const url = request.url();
		if (url.includes('/wp-json/')) {
			requests.push({ method: request.method(), url, body: request.postData() || '' });
		}
	});

	try {
		loginHelper = createLoginHelper(baseUrl);
		await page.goto(loginHelper.url, { waitUntil: 'domcontentloaded', timeout: 45000 });
		await verifyContextualEntry(page, context, baseUrl);
		await page.setViewportSize({ width: 1440, height: 1000 });
		await openAltCaptionPanel(page, baseUrl);
		await page.waitForSelector('[data-toolbox-media-alt-caption-review]:not([hidden])', { timeout: 30000 });
		const form = page.locator('[data-toolbox-media-alt-caption-review]');
		const submit = form.locator('button[type="submit"]');
		assert(await submit.isDisabled(), 'Empty ALT support page does not silently sample images.');
		await form.locator('[data-toolbox-alt-support] > summary').click();
		await form.locator('[data-toolbox-selected-attachment-ids]').fill(attachmentIds.join(', '));
		assert(await submit.isEnabled(), 'Selecting explicit images enables ALT drafts.');
		await form.locator('.npcink-toolbox__alt-sample-options').evaluate((details) => {
			if (details instanceof HTMLDetailsElement) {
				details.open = true;
			}
		});
		await form.locator('select[name="sample_size"]').selectOption('10');
		await form.locator('select[name="review_set_limit"]').selectOption('5');
		await form.locator('select[name="media_filter"]').selectOption('all_recent');
		assert(await form.evaluate(element => element.checkValidity()), 'ALT support inputs satisfy native form validation.');
		await submit.click();
		await page.waitForSelector('[data-toolbox-media-alt-caption-item]', { timeout: 45000 });
		await page.waitForFunction(
			(expectations) => {
				const rows = Array.from(document.querySelectorAll('.npcink-toolbox__alt-review-row'));
				const statuses = rows.map((row) => row.__npcinkMediaAltCaptionItem && row.__npcinkMediaAltCaptionItem.candidate_review_status).filter(Boolean);
				const captionOnlyCount = parseInt(document.querySelector('[data-toolbox-media-alt-caption-caption-only]')?.getAttribute('data-toolbox-media-alt-caption-caption-only') || '0', 10) || 0;
				return rows.length >= Math.max(1, expectations.reviewRowsMin - expectations.captionOnlyMin)
					&& statuses.filter((status) => status === 'caption_review_only').length === 0
					&& captionOnlyCount >= expectations.captionOnlyMin
					&& statuses.filter((status) => status === 'needs_context_confirmation').length >= expectations.contextRowsMin
					&& statuses.filter((status) => status === 'ready_for_review').length >= expectations.readyRowsMin;
			},
			{
				reviewRowsMin: expectedReviewRowsMin,
				captionOnlyMin: expectedCaptionOnlyMin,
				contextRowsMin: expectedContextRowsMin,
				readyRowsMin: expectedReadyRowsMin,
			},
			{ timeout: 15000 }
		);

		const initial = await page.evaluate(() => {
			const rows = Array.from(document.querySelectorAll('.npcink-toolbox__alt-review-row'));
			const handoffButton = document.querySelector('[data-toolbox-media-alt-caption-handoff]');
			const selectedCount = document.querySelector('[data-toolbox-media-alt-caption-selected-count]');
			const text = document.querySelector('[data-toolbox-media-alt-caption-review] .npcink-toolbox__result')?.innerText || '';
			return {
				text,
				rowCount: rows.length,
				captionOnlyRows: parseInt(document.querySelector('[data-toolbox-media-alt-caption-caption-only]')?.getAttribute('data-toolbox-media-alt-caption-caption-only') || '0', 10) || 0,
				captionOnlyRowsInMainList: rows.filter((row) => row.__npcinkMediaAltCaptionItem && row.__npcinkMediaAltCaptionItem.candidate_review_status === 'caption_review_only').length,
				contextRows: rows.filter((row) => row.__npcinkMediaAltCaptionItem && row.__npcinkMediaAltCaptionItem.candidate_review_status === 'needs_context_confirmation').length,
				readyRows: rows.filter((row) => row.__npcinkMediaAltCaptionItem && row.__npcinkMediaAltCaptionItem.candidate_review_status === 'ready_for_review').length,
				contextConfirmInputs: document.querySelectorAll('[data-toolbox-media-alt-caption-context-confirmed]').length,
				visualConfirmInputs: document.querySelectorAll('[data-toolbox-media-alt-caption-visual-confirmed]').length,
				initialSelectedCount: selectedCount ? selectedCount.textContent : '',
				handoffDisabled: handoffButton instanceof HTMLButtonElement ? handoffButton.disabled : null,
				readyLabelVisible: text.includes('Ready to update'),
				reviewRowsLabelVisible: /ALT draft rows|ALT 草稿行|ALT 草稿/.test(text),
				captionNotAppliedVisible: /Caption-only items \(not part of ALT review\)|说明文字.*不属于 ALT 审核|Caption-only/.test(text),
				contextWarningVisible: /Location or proper-name context must be confirmed or removed before this draft can be selected|地点或专名上下文.*确认|确认地点或专名上下文/.test(text),
				noWriteNoticeVisible: /does not approve, execute, poll, or write media metadata|never approves, executes, or updates media|不会批准、执行|不会.*更新媒体/.test(text),
			};
		});

		assert(initial.rowCount >= Math.max(1, expectedReviewRowsMin - expectedCaptionOnlyMin), 'Review UI renders the expected actionable ALT rows.');
		if (expectedCaptionOnlyMin > 0) {
			assert(initial.captionOnlyRows >= expectedCaptionOnlyMin, 'Review UI counts caption-only rows in a separate disclosure.');
			assert(initial.captionOnlyRowsInMainList === 0, 'Caption-only rows are excluded from the actionable ALT list.');
			assert(initial.captionNotAppliedVisible, 'Caption-only rows are clearly separated from ALT review.');
		} else {
			pass('Caption-only row assertions are not required for this sample.');
		}
		assert(initial.contextRows >= expectedContextRowsMin, 'Review UI labels location/proper-name rows as context-confirmation rows.');
		assert(initial.readyRows >= expectedReadyRowsMin, 'Review UI renders the expected ready-for-review rows.');
		if (expectedContextRowsMin > 0) {
			assert(initial.contextConfirmInputs >= expectedContextRowsMin, 'Review UI exposes explicit context confirmation controls.');
		}
		assert(initial.visualConfirmInputs === initial.rowCount, 'Every missing ALT row exposes explicit visual confirmation.');
		assert(initial.initialSelectedCount === '0', 'Initial ALT handoff count is zero before visual confirmation.');
		assert(initial.handoffDisabled === true, 'ALT handoff button starts disabled before visual confirmation.');
		assert(initial.reviewRowsLabelVisible && !initial.readyLabelVisible, 'Summary counts ALT draft rows without implying they are ready to update.');
		if (expectedContextRowsMin > 0) {
			assert(initial.contextWarningVisible, 'Context rows tell operators to confirm or remove location/proper-name context.');
		}
		assert(initial.noWriteNoticeVisible, 'Review shell keeps the no-write boundary visible.');

		await page.evaluate(() => {
			document.querySelectorAll('[data-toolbox-media-alt-caption-item]').forEach((input) => {
				if (input instanceof HTMLInputElement) {
					input.checked = true;
					input.dispatchEvent(new Event('change', { bubbles: true }));
				}
			});
		});
		const afterSelectingRows = await page.evaluate(() => ({
			selectedCount: document.querySelector('[data-toolbox-media-alt-caption-selected-count]')?.textContent || '',
			handoffDisabled: document.querySelector('[data-toolbox-media-alt-caption-handoff]')?.disabled ?? null,
		}));
		assert(afterSelectingRows.selectedCount === '0' && afterSelectingRows.handoffDisabled === true, 'Selecting rows alone does not authorize Core submission.');

		await page.evaluate(() => {
			const row = document.querySelector('.npcink-toolbox__alt-review-row');
			const visual = row?.querySelector('[data-toolbox-media-alt-caption-visual-confirmed]');
			const context = row?.querySelector('[data-toolbox-media-alt-caption-context-confirmed]');
			if (context instanceof HTMLInputElement) {
				context.checked = true;
				context.dispatchEvent(new Event('change', { bubbles: true }));
			}
			if (visual instanceof HTMLInputElement) {
				visual.checked = true;
				visual.dispatchEvent(new Event('change', { bubbles: true }));
			}
		});
		const afterVisualReview = await page.evaluate(() => ({
			selectedCount: document.querySelector('[data-toolbox-media-alt-caption-selected-count]')?.textContent || '',
			handoffDisabled: document.querySelector('[data-toolbox-media-alt-caption-handoff]')?.disabled ?? null,
		}));
		assert(afterVisualReview.selectedCount === '1' && afterVisualReview.handoffDisabled === false, 'One selected row becomes eligible only after visual and required context confirmation.');
		await page.locator('[data-toolbox-media-alt-caption-handoff]').click();
		await page.waitForSelector('.npcink-toolbox__handoff-receipt', { timeout: 15000 });
		const receiptText = await page.locator('.npcink-toolbox__handoff-receipt').first().innerText();
		assert(/fixture-media-alt-/.test(receiptText), 'Toolbox renders the Core proposal receipt after submission.');
		const submittedRowState = await page.evaluate(() => {
			const row = document.querySelector('.npcink-toolbox__alt-review-row');
			const selection = row?.querySelector('[data-toolbox-media-alt-caption-item]');
			return {
				selectionDisabled: selection instanceof HTMLInputElement ? selection.disabled : false,
				selectedCount: document.querySelector('[data-toolbox-media-alt-caption-selected-count]')?.textContent || '',
			};
		});
		assert(submittedRowState.selectionDisabled && submittedRowState.selectedCount === '0', 'Submitted rows are locked to prevent duplicate Core proposals without a re-scan.');
		assert(forbiddenExecutionRequests(requests).length === 0, 'Toolbox proposal handoff does not approve, execute, poll, use local consent, or write media.');
		assert(requests.some((request) => request.method === 'POST' && request.url.includes('/wp-json/npcink-toolbox/v1/ai/site-helpers')), 'Browser smoke builds the review set through the Toolbox site-helper route.');
		assert(requests.some((request) => request.method === 'POST' && request.url.includes('/wp-json/npcink-openclaw-adapter/v1/run-read-ability') && request.body.includes('build-media-alt-apply-plan')), 'Browser smoke builds the shared Toolkit missing ALT plan through Adapter.');
		assert(requests.some((request) => request.method === 'POST' && request.url.includes('/wp-json/npcink-openclaw-adapter/v1/proposals/from-plan') && request.body.includes('build-media-alt-apply-plan')), 'Browser smoke submits the shared plan to Core through Adapter.');

		mkdirSync(resolve(screenshotPath, '..'), { recursive: true });
		await captureViewportScreenshot(page, screenshotPath);
		pass(`Media ALT/Caption browser smoke screenshot: ${screenshotPath}`);
		for (const width of [1440, 900]) {
			await page.setViewportSize({ width, height: 1000 });
			for (const scenario of ['ready', 'partial', 'unavailable', 'processing']) {
				visualScenario = scenario;
				await submit.click();
				await page.waitForFunction((expected) => {
					const root = document.querySelector('[data-toolbox-media-alt-caption-review] .npcink-toolbox__result');
					const text = root?.innerText || '';
					const rows = root?.querySelectorAll('.npcink-toolbox__alt-review-row').length || 0;
					if (expected === 'ready') return rows === 2;
					if (expected === 'partial') return rows === 1 && /could not be recognized|部分图片识别失败/.test(text);
					if (expected === 'unavailable') return rows === 0 && /could not be recognized|部分图片识别失败/.test(text);
					return rows === 0 && /still processing|仍在识别中/.test(text);
				}, scenario);
				const state = await form.locator('.npcink-toolbox__result').evaluate((root) => ({
					rows: root.querySelectorAll('.npcink-toolbox__alt-review-row').length,
					failureVisible: Array.from(root.querySelectorAll('.npcink-toolbox__result-notice')).some((el) => !el.closest('details') && /could not be recognized|部分图片识别失败/.test(el.textContent)),
					pendingVisible: Array.from(root.querySelectorAll('.npcink-toolbox__result-notice')).some((el) => !el.closest('details') && /still processing|仍在识别中/.test(el.textContent)),
					handoffEnabled: root.querySelector('[data-toolbox-media-alt-caption-handoff]')?.disabled === false,
					overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
				}));
				assert(!state.handoffEnabled && !state.overflow, `${width}px ${scenario}: no unreviewed submission or horizontal overflow.`);
				assert(state.failureVisible === ['partial', 'unavailable'].includes(scenario) && state.pendingVisible === (scenario === 'processing'), `${width}px ${scenario}: incomplete and failed recognition have distinct visible notices.`);
				await form.locator('.npcink-toolbox__result').scrollIntoViewIfNeeded();
				const caseScreenshot = screenshotPath.replace(/\.png$/, `-${width}-${scenario}.png`);
				await captureViewportScreenshot(page, caseScreenshot);
				visualCases.push({ width, scenario, ...state, screenshot: caseScreenshot });
			}
		}
		assert(pageErrors.length === 0, 'Visual recognition browser cases have no page JavaScript errors.');
		assert(forbiddenExecutionRequests(requests).length === 0, 'Visual failure checks introduce no execution, polling or media writes.');
		writeFileSync(screenshotPath.replace(/\.png$/, '.json'), JSON.stringify({ fixtureOnly: true, visualCases, pageErrors }, null, 2));
	} catch (error) {
		console.error(`Page JavaScript errors: ${JSON.stringify(pageErrors)}`);
		await captureDiagnostics(page, requests, error);
		throw error;
	}
} finally {
	if (loginHelper) {
		loginHelper.cleanup();
	}
	if (runtimeFilter) {
		runtimeFilter.cleanup();
	}
	await browser.close();
}

pass(`Media ALT/Caption browser smoke completed at ${baseUrl}.`);
