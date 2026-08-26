#!/usr/bin/env node
/**
 * Real-browser smoke for one reviewed featured-image adoption.
 *
 * The browser mocks only suggestion reads and feedback. The adoption request
 * reaches the real WordPress REST server, while a temporary MU filter serves
 * deterministic PNG bytes. It does not call Cloud or a real provider.
 */

import { randomBytes } from 'node:crypto';
import { existsSync, mkdirSync, unlinkSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';

function pass(message) {
	console.log(`PASS: ${message}`);
}

function assert(condition, message) {
	if (!condition) {
		throw new Error(message);
	}
	pass(message);
}

function env(name, fallback) {
	return process.env[name] || fallback;
}

async function loadPlaywright() {
	try {
		return await import('playwright');
	} catch (error) {
		const require = createRequire(import.meta.url);
		const paths = String(process.env.NODE_PATH || '').split(':').filter(Boolean);
		const resolved = require.resolve('playwright', { paths });
		const module = await import(pathToFileURL(resolved).href);
		return module.chromium ? module : module.default;
	}
}

function wpPath() {
	return env('WP_PATH', '/Users/muze/Local Sites/magick-ai/app/public');
}

function createLoginHelper(baseUrl) {
	const token = randomBytes(24).toString('hex');
	const fileName = `npcink-toolbox-image-adoption-login-${randomBytes(8).toString('hex')}.php`;
	const filePath = `${wpPath().replace(/\/$/, '')}/${fileName}`;
	writeFileSync(filePath, `<?php
declare(strict_types=1);
$expected = '${token}';
if (!isset($_GET['token']) || !hash_equals($expected, (string) $_GET['token'])) {
	http_response_code(403);
	exit('forbidden');
}
require __DIR__ . '/wp-load.php';
$action = isset($_GET['action']) ? sanitize_key((string) $_GET['action']) : 'login';
if ('cleanup' === $action) {
	$post_id = absint($_GET['post_id'] ?? 0);
	$requested_attachment_id = absint($_GET['attachment_id'] ?? 0);
	$featured_attachment_id = $post_id > 0 ? absint(get_post_thumbnail_id($post_id)) : 0;
	foreach (array_unique(array_filter(array($requested_attachment_id, $featured_attachment_id))) as $attachment_id) {
		if ('attachment' === get_post_type($attachment_id)) {
			wp_delete_attachment($attachment_id, true);
		}
	}
	if ($post_id > 0) {
		wp_delete_post($post_id, true);
	}
	echo wp_json_encode(array(
		'post_deleted' => $post_id <= 0 || !get_post($post_id),
		'attachment_deleted' => $requested_attachment_id <= 0 || !get_post($requested_attachment_id),
	));
	exit;
}
$users = get_users(array('role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC'));
$user = $users ? $users[0] : null;
if (!$user) {
	http_response_code(500);
	exit('no_admin_user');
}
wp_set_current_user($user->ID);
wp_set_auth_cookie($user->ID, false, is_ssl());
$post_id = wp_insert_post(array(
	'post_type' => 'post',
	'post_status' => 'draft',
	'post_author' => $user->ID,
	'post_title' => '图片采用 REST 浏览器验收（临时）',
	'post_excerpt' => '验证浏览器 REST 环境中的单图采用。',
	'post_content' => '<!-- wp:paragraph --><p>自然光下的安静工作空间，用于验证特色图片采用。</p><!-- /wp:paragraph -->',
), true);
if (is_wp_error($post_id)) {
	http_response_code(500);
	exit($post_id->get_error_message());
}
wp_safe_redirect(admin_url('post.php?post=' . absint($post_id) . '&action=edit'));
exit;
`);

	return {
		url: `${baseUrl}/${fileName}?token=${token}`,
		cleanupUrl: (postId, attachmentId) => `${baseUrl}/${fileName}?token=${token}&action=cleanup&post_id=${parseInt(postId, 10) || 0}&attachment_id=${parseInt(attachmentId, 10) || 0}`,
		cleanup: () => {
			try {
				unlinkSync(filePath);
			} catch (error) {
				// Cleanup races must not hide the primary browser result.
			}
		},
	};
}

function createImageDownloadFixture() {
	const directory = `${wpPath().replace(/\/$/, '')}/wp-content/mu-plugins`;
	mkdirSync(directory, { recursive: true });
	const filePath = `${directory}/npcink-toolbox-image-adoption-browser-${randomBytes(8).toString('hex')}.php`;
	writeFileSync(filePath, `<?php
/** Temporary deterministic image download for the browser adoption smoke. */
$npcink_browser_wp_tempnam_preloaded = function_exists('wp_tempnam');
add_filter(
	'pre_http_request',
	static function ($preempt, array $parsed_args, string $url) {
		if ('https://example.com/npcink-browser-adoption.png' !== $url) {
			return $preempt;
		}
		$filename = (string) ($parsed_args['filename'] ?? '');
		$bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=', true);
		if ('' === $filename || !is_string($bytes) || false === file_put_contents($filename, $bytes)) {
			return new WP_Error('npcink_browser_fixture_write_failed', 'Could not write the deterministic browser image.');
		}
		return array(
			'headers' => array('content-type' => 'image/png'),
			'body' => '',
			'response' => array('code' => 200, 'message' => 'OK'),
			'cookies' => array(),
			'filename' => $filename,
		);
	},
	10,
	3
);
add_filter(
	'rest_post_dispatch',
	static function ($response, $server, WP_REST_Request $request) use ($npcink_browser_wp_tempnam_preloaded) {
		if ('/npcink-toolbox/v1/strong-local-confirmation/image-adoption' === $request->get_route() && $response instanceof WP_HTTP_Response) {
			$response->header('X-Npcink-Smoke-Wp-Tempnam-Preloaded', $npcink_browser_wp_tempnam_preloaded ? '1' : '0');
		}
		return $response;
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
				// Cleanup races must not hide the primary browser result.
			}
		},
	};
}

function imagePayload() {
	const previewSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="960" height="540"><rect width="960" height="540" fill="#dbeafe"/><rect x="90" y="90" width="780" height="360" rx="24" fill="#ffffff"/><text x="480" y="285" text-anchor="middle" font-family="Arial" font-size="44" fill="#172033">REST adoption fixture</text></svg>';
	const previewUrl = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(previewSvg)}`;
	return {
		artifact_type: 'image_source_candidates',
		status: 'ready',
		provider_mode: 'browser_fixture',
		resolved_provider: 'browser_fixture',
		candidate_count: 1,
		images: [{
			id: 'browser-adoption-candidate',
			title: 'Browser adoption fixture',
			alt: '自然光下的安静工作空间',
			description: 'Deterministic local browser adoption fixture.',
			regular_url: previewUrl,
			thumbnail_url: previewUrl,
			download_url: 'https://example.com/npcink-browser-adoption.png',
			source_url: 'https://example.com/npcink-browser-adoption-source',
			provider: 'browser_fixture',
			source_type: 'image_source',
			license_review_status: 'reviewed',
			suggested_filename: 'npcink-browser-adoption.png',
			width: 1,
			height: 1,
		}],
		write_posture: 'suggestion_only',
		direct_wordpress_write: false,
	};
}

async function captureDiagnostics(page, error) {
	const artifactDir = env('SMOKE_ARTIFACT_DIR', 'build/smoke');
	mkdirSync(artifactDir, { recursive: true });
	const screenshotPath = `${artifactDir}/editor-image-adoption-browser-failure.png`;
	await page.screenshot({ path: screenshotPath, fullPage: true }).catch(() => {});
	const pageText = await page.locator('body').innerText({ timeout: 2000 }).catch(() => '');
	console.error(`FAIL: Image adoption browser screenshot: ${screenshotPath}`);
	console.error(`FAIL: Image adoption browser URL: ${page.url()}`);
	console.error(`FAIL: Image adoption browser visible text: ${pageText.replace(/\s+/g, ' ').trim().slice(0, 1400)}`);
	console.error(`FAIL: Image adoption browser error: ${error && error.message ? error.message : String(error || 'unknown error')}`);
}

async function dismissEditorOverlays(page) {
	for (let index = 0; index < 3; index += 1) {
		if (!(await page.locator('.components-modal__screen-overlay').count().catch(() => 0))) {
			return;
		}
		await page.keyboard.press('Escape').catch(() => {});
		await page.waitForTimeout(250);
	}
}

const { chromium } = await loadPlaywright();
const baseUrl = env('WP_BASE_URL', 'https://magick-ai.local').replace(/\/$/, '');
const artifactDir = env('SMOKE_ARTIFACT_DIR', 'build/smoke');
const screenshotPath = `${artifactDir}/editor-image-adoption-browser.png`;
mkdirSync(artifactDir, { recursive: true });

const browserOptions = { headless: process.env.HEADLESS !== '0' };
const chrome = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
if (process.env.BROWSER_EXECUTABLE) {
	browserOptions.executablePath = process.env.BROWSER_EXECUTABLE;
} else if (existsSync(chrome)) {
	browserOptions.executablePath = chrome;
}

let browser = null;
let page = null;
let loginHelper = null;
let downloadFixture = null;
let postId = 0;
let attachmentId = 0;
let caughtError = null;
try {
	downloadFixture = createImageDownloadFixture();
	browser = await chromium.launch(browserOptions);
	const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 1100 } });
	const adoptionRequests = [];
	const confirmationMessages = [];
	await context.route('**/wp-json/npcink-toolbox/v1/image-candidates', async (route) => {
		await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(imagePayload()) });
	});
	await context.route('**/wp-json/npcink-toolbox/v1/agent-feedback', async (route) => {
		await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ status: 'accepted', write_posture: 'metadata_only' }) });
	});

	page = await context.newPage();
	page.on('request', (request) => {
		if (request.url().includes('/wp-json/npcink-toolbox/v1/strong-local-confirmation/image-adoption')) {
			adoptionRequests.push(request.postDataJSON());
		}
	});
	page.on('dialog', async (dialog) => {
		confirmationMessages.push(dialog.message());
		await dialog.accept();
	});

	loginHelper = createLoginHelper(baseUrl);
	await page.goto(loginHelper.url, { waitUntil: 'domcontentloaded', timeout: 45000 });
	postId = parseInt(new URL(page.url()).searchParams.get('post') || '0', 10);
	assert(postId > 0, 'The smoke created one disposable editor draft.');
	await page.waitForFunction(() => window.wp && window.wp.data && window.wp.data.dispatch, null, { timeout: 30000 });
	await dismissEditorOverlays(page);
	await page.evaluate(() => {
		const target = 'npcink-toolbox-editor-content-support/npcink-content-support-sidebar';
		for (const store of ['core/edit-post', 'core/interface', 'core/editor']) {
			try {
				const dispatch = window.wp.data.dispatch(store);
				if (dispatch && typeof dispatch.openGeneralSidebar === 'function') {
					dispatch.openGeneralSidebar(target);
					return;
				}
			} catch (error) {
				// Older editor builds do not expose every store.
			}
		}
	});

	await page.waitForSelector('.npcink-toolbox-editor-support__flow', { timeout: 30000 });
	const imageFlow = page.locator('.npcink-toolbox-editor-support__flow').filter({ hasText: /AI recommended featured image|AI 推荐特色图/ });
	assert(await imageFlow.count() === 1, 'The editor exposes the featured-image recommendation entry.');
	await imageFlow.locator('button').click();
	await page.waitForSelector('.npcink-toolbox-editor-support__image-modal', { timeout: 30000 });
	await page.waitForFunction(() => document.querySelectorAll('.npcink-toolbox-editor-support__image-card').length === 1, null, { timeout: 10000 });
	const candidate = page.locator('.npcink-toolbox-editor-support__image-card').first();
	await candidate.click();
	assert(await candidate.getAttribute('aria-pressed') === 'true', 'The deterministic image candidate is selected for review.');

	const adoptButton = page.getByRole('button', { name: /^Adopt$|^采用$/ });
	const responsePromise = page.waitForResponse((response) => response.url().includes('/wp-json/npcink-toolbox/v1/strong-local-confirmation/image-adoption') && response.request().method() === 'POST', { timeout: 30000 });
	await adoptButton.click();
	const adoptionResponse = await responsePromise;
	const adoption = await adoptionResponse.json();
	attachmentId = parseInt(adoption.attachment_id || '0', 10) || 0;

	assert(confirmationMessages.length === 1, 'Adoption requires one explicit browser confirmation.');
	assert(adoptionRequests.length === 1 && adoptionRequests[0].action === 'import_and_set_featured' && adoptionRequests[0].confirmed_action === 'import_and_set_featured', 'The browser sends one action-bound adoption request.');
	assert(adoptionResponse.status() === 200, 'The real browser REST adoption request succeeds.');
	assert(adoptionResponse.headers()['x-npcink-smoke-wp-tempnam-preloaded'] === '0', 'The HTTP request proves wp_tempnam() was not preloaded in the browser REST environment.');
	assert(adoption.artifact_type === 'single_article_image_adoption_result.v1' && adoption.status === 'completed', 'The real REST route returns the completed adoption contract.');
	assert(attachmentId > 0 && parseInt(adoption.featured_media || '0', 10) === attachmentId, 'WordPress created one attachment and set it as the article featured image.');
	await page.waitForSelector('.npcink-toolbox-editor-support__adoption-result.is-adopted', { timeout: 10000 });
	assert(await page.getByText(/Featured image adopted|已采用为特色图/).count() === 1, 'The editor displays the successful adoption result.');
	assert(await page.locator('.npcink-toolbox-editor-support__selected-image .components-notice.is-error').count() === 0, 'The successful REST response leaves no adoption error notice.');
	assert(await page.getByText(/此站点遇到了致命错误|faq-troubleshooting/).count() === 0, 'The editor never displays a raw WordPress fatal-error page.');
	const editorFeaturedMedia = await page.evaluate(() => parseInt(window.wp.data.select('core/editor').getEditedPostAttribute('featured_media') || '0', 10));
	assert(editorFeaturedMedia === attachmentId, 'The Gutenberg editor synchronizes to the adopted featured attachment.');
	await page.screenshot({ path: screenshotPath, fullPage: true });
	pass(`Image adoption browser screenshot: ${screenshotPath}`);
	const cleanupResponse = await page.goto(loginHelper.cleanupUrl(postId, attachmentId), { waitUntil: 'domcontentloaded', timeout: 10000 });
	const cleanupResult = await cleanupResponse.json();
	assert(cleanupResult.post_deleted === true && cleanupResult.attachment_deleted === true, 'The smoke removes its temporary article and imported attachment.');
	postId = 0;
	attachmentId = 0;
} catch (error) {
	caughtError = error;
	if (page) {
		await captureDiagnostics(page, error);
	}
} finally {
	if (loginHelper) {
		if (page && postId > 0) {
			await page.goto(loginHelper.cleanupUrl(postId, attachmentId), { waitUntil: 'domcontentloaded', timeout: 10000 }).catch(() => {});
		}
		loginHelper.cleanup();
	}
	if (downloadFixture) {
		downloadFixture.cleanup();
	}
	if (browser) {
		await browser.close();
	}
}

if (caughtError) {
	throw caughtError;
}

pass(`Editor image adoption browser smoke completed at ${baseUrl}.`);
