#!/usr/bin/env node
/**
 * Browser smoke for the editor image recommendation modal.
 *
 * The browser intercepts only image candidate REST reads and returns
 * deterministic suggestion-only fixtures. It does not call Cloud or a real
 * provider, import media, create Core proposals, or write WordPress content.
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
	const fileName = `npcink-toolbox-image-modal-login-${randomBytes(8).toString('hex')}.php`;
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
	if ($post_id > 0) {
		wp_delete_post($post_id, true);
	}
	echo 'deleted';
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
	'post_type'    => 'post',
	'post_status'  => 'draft',
	'post_author'  => $user->ID,
	'post_title'   => '图片推荐弹窗浏览器验收（临时）',
	'post_excerpt' => '一篇关于独立开发者如何规划专注工作空间的文章。',
	'post_content' => '<!-- wp:heading --><h2 class="wp-block-heading">安静而高效的独立工作空间</h2><!-- /wp:heading --><!-- wp:paragraph --><p>自然光从窗边进入，木质书桌上放着笔记本电脑、绿植和一杯咖啡，整体风格克制而真实。</p><!-- /wp:paragraph -->',
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
		cleanupUrl: (postId) => `${baseUrl}/${fileName}?token=${token}&action=cleanup&post_id=${parseInt(postId, 10) || 0}`,
		cleanupFile: () => {
			try {
				unlinkSync(filePath);
			} catch (error) {
				// A cleanup race must not hide the primary smoke result.
			}
		},
	};
}

function svgDataUrl(label, color) {
	const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="960" height="540"><rect width="960" height="540" fill="${color}"/><rect x="90" y="90" width="780" height="360" rx="32" fill="white" fill-opacity=".9"/><text x="480" y="285" text-anchor="middle" font-family="Arial" font-size="46" fill="#172033">${label}</text></svg>`;
	return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
}

function imageCandidate(id, label, color, sourceType = 'image_source') {
	const url = svgDataUrl(label, color);
	return {
		id,
		title: label,
		alt_description: `${label} preview`,
		description: `${label} deterministic browser fixture`,
		regular_url: url,
		full_url: url,
		thumbnail_url: url,
		width: 960,
		height: 540,
		provider: sourceType === 'ai_generated' ? 'ai_generated' : 'browser_fixture',
		source_type: sourceType,
		source_url: 'https://example.test/browser-fixture',
		license_review_status: 'human_review_required',
		generation_model: sourceType === 'ai_generated' ? 'browser-smoke-model' : '',
		generation_provider: sourceType === 'ai_generated' ? 'browser-smoke-provider' : '',
		generation_prompt: sourceType === 'ai_generated' ? label : '',
	};
}

function sourcePayload() {
	const colors = ['#dbeafe', '#dcfce7', '#fef3c7', '#fce7f3', '#ede9fe', '#cffafe', '#fee2e2', '#e2e8f0', '#ecfccb'];
	return {
		artifact_type: 'image_source_candidates',
		status: 'ready',
		provider_mode: 'auto',
		resolved_provider: 'browser_fixture',
		candidate_count: 9,
		images: colors.map((color, index) => imageCandidate(`source-${index + 1}`, `Source candidate ${index + 1}`, color)),
		write_posture: 'suggestion_only',
		direct_wordpress_write: false,
	};
}

function siteMediaPayload() {
	return {
		artifact_type: 'image_source_candidates',
		status: 'ready',
		provider_mode: 'site_media',
		resolved_provider: 'site_media',
		candidate_count: 1,
		images: [
			{
				...imageCandidate('site-media-501', '站内木质书桌工作空间', '#dcfce7', 'owned'),
				attachment_id: 501,
				provider: 'site_media',
				provider_origin: 'wordpress_local',
				match_reason: '图片中的木质书桌、笔记本电脑和自然光与文章描述一致。',
				suggested_alt: '自然光下摆放笔记本电脑和绿植的木质书桌',
				seo_suggestions: {
					alt: '自然光下摆放笔记本电脑和绿植的木质书桌',
				},
				media_fingerprint: 'sha256:browser-fixture-501',
				evidence_reuse: 'site_knowledge_projection',
				requires_local_review: true,
				direct_wordpress_write: false,
			},
		],
		write_posture: 'suggestion_only',
		direct_wordpress_write: false,
	};
}

function mediaBriefPayload() {
	return {
		artifact_type: 'image_visual_brief',
		status: 'ready',
		prompt_candidates: [
			{
				id: 'editorial_workspace',
				label: 'Editorial workspace',
				localized_label: '真实工作场景',
				localized_prompt: '自然窗光下的家庭工作空间，木质书桌上有笔记本电脑、研究笔记、绿植和咖啡，不出现文字或 Logo。',
				prompt: 'A home workspace in natural window light with a wooden desk, laptop, research notes, plant and coffee, without visible text or logos.',
			},
		],
	};
}

function generationPayload(requestBody, generationIndex) {
	const count = Math.max(1, Math.min(4, parseInt(requestBody.n || '2', 10) || 2));
	const prefix = generationIndex === 1 ? 'Generated candidate' : 'Revised candidate';
	const colors = generationIndex === 1 ? ['#bfdbfe', '#bbf7d0', '#fde68a', '#fecdd3'] : ['#c4b5fd', '#a5f3fc', '#fed7aa', '#fecaca'];
	return {
		artifact_type: 'image_source_candidates',
		status: 'ready',
		provider_mode: 'ai_generated',
		hosted_profile: 'browser-smoke-profile',
		model_id: 'browser-smoke-model',
		candidate_count: count,
		images: colors.slice(0, count).map((color, index) => ({
			...imageCandidate(`${generationIndex === 1 ? 'generated' : 'revised'}-${index + 1}`, `${prefix} ${index + 1}`, color, 'ai_generated'),
			generation_prompt: String(requestBody.prompt || ''),
		})),
		handoff: {
			final_writes: 'core_proposal_required',
			direct_wordpress_write: false,
		},
		write_posture: 'suggestion_only',
		direct_wordpress_write: false,
	};
}

function forbiddenWriteRequests(requests) {
	return requests.filter((request) => {
		if (!['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method)) {
			return false;
		}
		if (/\/wp-json\/wp\/v2\/(posts|media)/.test(request.url)) {
			return true;
		}
		return /proposals|approve-and-execute|governance-core|openclaw-adapter|local-admin-consent|media-derivative/i.test(request.url);
	});
}

async function captureDiagnostics(page, requests, error) {
	const artifactDir = env('SMOKE_ARTIFACT_DIR', 'build/smoke');
	mkdirSync(artifactDir, { recursive: true });
	const screenshotPath = `${artifactDir}/editor-image-recommendation-browser-failure.png`;
	await page.screenshot({ path: screenshotPath, fullPage: true }).catch(() => {});
	const pageText = await page.locator('body').innerText({ timeout: 2000 }).catch(() => '');
	console.error(`FAIL: Image recommendation browser smoke diagnostic screenshot: ${screenshotPath}`);
	console.error(`FAIL: Image recommendation browser smoke current URL: ${page.url()}`);
	console.error(`FAIL: Image recommendation browser smoke page title: ${await page.title().catch(() => '')}`);
	console.error(`FAIL: Image recommendation browser smoke wp-json requests: ${requests.length}`);
	console.error(`FAIL: Image recommendation browser smoke visible text sample: ${pageText.replace(/\s+/g, ' ').trim().slice(0, 1400)}`);
	console.error(`FAIL: Image recommendation browser smoke error: ${error && error.message ? error.message : String(error || 'unknown error')}`);
}

async function dismissEditorOverlays(page) {
	for (let index = 0; index < 3; index += 1) {
		const overlayCount = await page.locator('.components-modal__screen-overlay').count().catch(() => 0);
		if (!overlayCount) {
			return;
		}
		await page.keyboard.press('Escape').catch(() => {});
		await page.waitForTimeout(250);
	}
}

const { chromium } = await loadPlaywright();
const baseUrl = env('WP_BASE_URL', 'https://magick-ai.local').replace(/\/$/, '');
const artifactDir = env('SMOKE_ARTIFACT_DIR', 'build/smoke');
const screenshotPath = `${artifactDir}/editor-image-recommendation-browser.png`;
const libraryScreenshotPath = `${artifactDir}/editor-site-media-evidence-browser.png`;
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
let postId = 0;
let caughtError = null;
const requests = [];
try {
	browser = await chromium.launch(browserOptions);
	const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 1100 } });
	const generationRequests = [];
	const libraryRequests = [];
	let generationIndex = 0;

	await context.route('**/wp-json/npcink-toolbox/v1/image-candidates', async (route) => {
		const request = route.request();
		const body = request.postDataJSON();
		requests.push({ method: request.method(), url: request.url(), body: request.postData() || '' });
		if (body.provider === 'site_media') {
			libraryRequests.push(body);
			await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(siteMediaPayload()) });
			return;
		}
		await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(sourcePayload()) });
	});
	await context.route('**/wp-json/npcink-toolbox/v1/ai/image-generation', async (route) => {
		const request = route.request();
		const body = request.postDataJSON();
		generationIndex += 1;
		generationRequests.push(body);
		requests.push({ method: request.method(), url: request.url(), body: request.postData() || '' });
		await new Promise((resolve) => setTimeout(resolve, 250));
		await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(generationPayload(body, generationIndex)) });
	});
	await context.route('**/wp-json/npcink-toolbox/v1/flows/media-brief', async (route) => {
		const request = route.request();
		requests.push({ method: request.method(), url: request.url(), body: request.postData() || '' });
		await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(mediaBriefPayload()) });
	});
	await context.route('**/wp-json/npcink-toolbox/v1/agent-feedback', async (route) => {
		const request = route.request();
		requests.push({ method: request.method(), url: request.url(), body: request.postData() || '' });
		await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ status: 'accepted', write_posture: 'metadata_only' }) });
	});

	page = await context.newPage();
	page.on('request', (request) => {
		const url = request.url();
		if (!url.includes('/wp-json/') || /npcink-toolbox\/v1\/(image-candidates|ai\/image-generation|agent-feedback)/.test(url)) {
			return;
		}
		requests.push({ method: request.method(), url, body: request.postData() || '' });
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
	assert(await imageFlow.count() === 1, 'The editor exposes one featured-image recommendation entry.');
	await imageFlow.locator('button').click();

	await page.waitForSelector('.npcink-toolbox-editor-support__image-modal', { timeout: 30000 });
	assert(await page.locator('.npcink-toolbox-editor-support__image-results').count() === 1, 'The modal renders the image grid in the left results pane.');
	assert(await page.locator('.npcink-toolbox-editor-support__image-inspector').count() === 1, 'The modal renders mode and review controls in the right inspector.');
	assert(await page.getByRole('button', { name: /AI generation|AI 生成/ }).count() === 1, 'Featured-image mode exposes the AI-generation option.');
	assert(await page.locator('.npcink-toolbox-editor-support__image-card').count() === 0, 'The default site-media mode waits for an explicit operator search.');
	await page.getByRole('button', { name: /External image library|外部图库/ }).click();
	const externalQuery = '自然光下的独立工作空间';
	await page.locator('.npcink-toolbox-editor-support__image-inspector input[type="search"]').fill(externalQuery);
	await page.getByRole('button', { name: /Search image sources|搜索图片来源/ }).click();
	await page.waitForFunction(() => document.querySelectorAll('.npcink-toolbox-editor-support__image-card').length > 0, null, { timeout: 10000 });
	let externalSearchFeedback = null;
	for (let index = 0; index < 20 && !externalSearchFeedback; index += 1) {
		externalSearchFeedback = requests
			.filter((item) => item.url.includes('/npcink-toolbox/v1/agent-feedback'))
			.map((item) => {
				try {
					return JSON.parse(item.body || '{}');
				} catch (error) {
					return {};
				}
			})
			.find((item) => item.source_runtime === 'image_candidates' && item.source_action_id === 'media_search_completed') || null;
		if (!externalSearchFeedback) {
			await page.waitForTimeout(100);
		}
	}
	assert(externalSearchFeedback && externalSearchFeedback.source_object_type === 'media_search_session', 'External image-source search emits a correlated metadata-only completion event.');
	assert(externalSearchFeedback.source_object_id && externalSearchFeedback.handoff_id.includes(externalSearchFeedback.source_object_id), 'External image-source search uses a random quality session identifier.');

	await page.getByRole('button', { name: /Site media library|站内媒体库/ }).click();
	const libraryQuery = '自然光下的木质书桌和笔记本电脑';
	await page.locator('.npcink-toolbox-editor-support__image-inspector input[type="search"]').fill(libraryQuery);
	await page.getByRole('button', { name: /Search site media|搜索站内媒体/ }).click();
	await page.waitForFunction(() => document.querySelectorAll('.npcink-toolbox-editor-support__image-card').length === 1, null, { timeout: 10000 });
	const manualLibraryRequest = libraryRequests[libraryRequests.length - 1] || {};
	assert(manualLibraryRequest.provider === 'site_media' && manualLibraryRequest.query === libraryQuery, 'Site-media mode sends the reviewed natural-language local-library search request.');
	assert(await page.getByText(/Why this image matches|为什么匹配这张图片/).count() === 1, 'The selected site-media candidate shows its semantic match reason.');
		assert(await page.getByText(/Suggested ALT text|建议的 ALT 文本/).count() === 1, 'The selected site-media candidate shows the reused ALT suggestion.');
		assert(await page.locator('.npcink-toolbox-editor-support__image-evidence-item p').filter({ hasText: '自然光下摆放笔记本电脑和绿植的木质书桌' }).count() === 1, 'The visible ALT suggestion comes from the candidate evidence.');
		assert(await page.getByRole('button', { name: /^(Adopt|采用|Import only|仅导入)$/ }).count() === 0, 'Candidate review exposes no retired local image-adoption action.');
		const mediaSearchFeedback = requests
			.filter((item) => item.url.includes('/npcink-toolbox/v1/agent-feedback'))
			.map((item) => {
				try {
					return JSON.parse(item.body || '{}');
				} catch (error) {
					return {};
				}
			})
			.filter((item) => item.source_action_id === 'media_search_completed')
			.at(-1);
		assert(mediaSearchFeedback && mediaSearchFeedback.source_object_type === 'media_search_session', 'Site-media search emits one correlated metadata-only completion event.');
		assert(mediaSearchFeedback.handoff_id.includes(mediaSearchFeedback.source_object_id), 'The search handoff uses the random quality session instead of an attachment identifier.');
		assert(mediaSearchFeedback.feedback_labels.includes('media_search_has_results'), 'The search completion event records that local candidates were returned.');
		assert(mediaSearchFeedback.source_reason_codes.includes('result_count_1_3'), 'The search completion event stores only a coarse result-count bucket.');
		assert(Array.isArray(mediaSearchFeedback.evidence_ref_ids) && mediaSearchFeedback.evidence_ref_ids.length === 0, 'The search completion event omits attachment and candidate identifiers.');
		assert(!JSON.stringify(mediaSearchFeedback).includes(libraryQuery), 'The metadata-only search event does not include the natural-language query.');
		assert(!JSON.stringify(mediaSearchFeedback).includes('自然光下摆放笔记本电脑和绿植的木质书桌'), 'The metadata-only search event does not include the ALT suggestion.');
		await page.screenshot({ path: libraryScreenshotPath, fullPage: true });
	pass(`Site-media evidence browser smoke screenshot: ${libraryScreenshotPath}`);

	await page.getByRole('button', { name: /AI generation|AI 生成/ }).click();
	const promptInput = page.locator('#npcink-toolbox-editor-support-image-prompt');
	await promptInput.fill('');
	assert(await page.locator('.npcink-toolbox-editor-support__image-options').count() === 0, 'Generation options stay hidden until a visual description exists.');
	await page.getByRole('button', { name: /Generate visual description|生成画面描述/ }).click();
	await page.waitForFunction(() => document.querySelector('#npcink-toolbox-editor-support-image-prompt')?.value.includes('自然窗光'), null, { timeout: 10000 });
	assert(await page.locator('.npcink-toolbox-editor-support__image-options').count() === 1, 'Generation options appear after a visual description is ready.');
	const promptLabel = page.locator('label[for="npcink-toolbox-editor-support-image-prompt"]');
	assert(/Visual description|画面描述/.test(await promptLabel.innerText()), 'The primary editable field is labeled as the visual description.');
	const advancedPromptDetails = page.locator('details.npcink-toolbox-editor-support__cloud-details').filter({ hasText: /Advanced information|高级信息/ });
	assert(await advancedPromptDetails.count() === 1, 'A paired English provider prompt is available only as advanced information.');
	assert(await advancedPromptDetails.evaluate((element) => !element.open), 'The English provider prompt is collapsed by default.');
	assert((await promptInput.inputValue()).includes('自然窗光'), 'The paired Chinese visual description remains the single primary review value.');
	const directionButton = page.locator('[data-toolbox-ai-prompt-direction="true"]').first();
	await directionButton.click();
	assert((await promptInput.inputValue()).includes('自然窗光'), 'Selecting a generation direction keeps the Chinese visual description in the primary field.');
	assert(!(await promptInput.inputValue()).includes('A home workspace'), 'Selecting a generation direction never falls back to the English model prompt in the primary field.');
	const prompt = '自然窗光下的家庭工作空间，木质书桌、笔记本电脑、绿植和咖啡，不要文字或 Logo。';
	await promptInput.fill(prompt);
	await page.waitForFunction(() => !Array.from(document.querySelectorAll('details')).some((element) => /Advanced information|高级信息/.test(element.textContent || '')), null, { timeout: 10000 });
	assert(await page.getByText(/converted to English|转换为英文/).count() === 0, 'Request-time translation stays out of the primary editing interface.');
	await page.getByRole('button', { name: /Request hosted image|请求托管图片/ }).click();
	assert(await directionButton.isDisabled(), 'Generation direction controls are disabled while a hosted image request is running.');
	assert(await page.getByRole('button', { name: /Site media library|站内媒体库/ }).isDisabled(), 'Image mode controls are disabled while a hosted image request is running.');
	assert(await page.locator('.npcink-toolbox-editor-support__image-card').count() === 0, 'AI generation does not show stale source candidates while the hosted request is running.');
	assert(await page.getByText(/No image-source candidates found|未找到图片来源候选/).count() === 0, 'AI generation never renders the image-source empty state.');
	await page.waitForFunction(() => document.querySelectorAll('.npcink-toolbox-editor-support__image-card').length === 2, null, { timeout: 10000 });

	assert(generationRequests.length === 1, 'One reviewed hosted-image request is made.');
	assert(generationRequests[0].prompt === prompt, 'The request contains the reviewed prompt.');
	assert(generationRequests[0].n === 2, 'The default candidate count remains two.');
	assert(generationRequests[0].prompt_reviewed_by_operator === true, 'The request records operator prompt review.');
	assert(generationRequests[0].prompt_source_locale === 'zh_CN', 'The request records the reviewed source prompt locale.');
	assert(generationRequests[0].prompt_translation_mode === 'required', 'A manually reviewed Chinese description requests one Cloud translation before image generation.');
	assert(generationRequests[0].provider_prompt_reviewed_by_operator === false, 'The request does not claim that the operator reviewed the request-time English translation.');
	assert(generationRequests[0].aspect_ratio === '16:9' && generationRequests[0].resolution === 'high', 'The default aspect ratio and quality are sent explicitly.');
	const generationFeedback = requests
		.filter((item) => item.url.includes('/npcink-toolbox/v1/agent-feedback'))
		.map((item) => {
			try {
				return JSON.parse(item.body || '{}');
			} catch (error) {
				return {};
			}
		})
		.find((item) => item.source_action_id === 'ai_image_generation_requested');
	assert(generationFeedback && generationFeedback.source_reason_codes.includes('prompt_locale_zh_cn'), 'AI image generation feedback records only the prompt locale classification.');
	assert(generationFeedback.source_reason_codes.includes('prompt_translation_required'), 'AI image generation feedback records only the translation mode classification.');
	assert(!JSON.stringify(generationFeedback).includes(prompt), 'AI image generation feedback does not include the reviewed prompt body.');

	const firstCard = page.locator('.npcink-toolbox-editor-support__image-card').first();
	await firstCard.click();
	assert(await firstCard.getAttribute('aria-pressed') === 'true', 'The first generated candidate becomes the reviewed selection.');
	assert(await page.getByText('browser-smoke-profile / browser-smoke-model / browser-smoke-provider').count() === 1, 'Selected generated image details show the resolved hosted profile and model.');
	assert(await page.locator('[data-toolbox-editor-ai-image-regenerate="true"]').count() === 1, 'Generated selections expose semantic regeneration controls.');

	await firstCard.getByRole('button', { name: /Preview image larger|放大预览图片/ }).click();
	assert(await page.locator('.npcink-toolbox-editor-support__image-preview-modal').count() === 1, 'Large preview replaces the recommendation modal content.');
	assert(await page.locator('.npcink-toolbox-editor-support__image-workspace').count() === 0, 'The recommendation workspace is hidden while the large preview is open.');
	await page.getByRole('button', { name: /Close preview|关闭预览/ }).click();
	assert(await page.locator('.npcink-toolbox-editor-support__image-workspace').count() === 1, 'Closing the preview returns to the candidate selection.');

	await page.getByRole('button', { name: /More specific|更具体/ }).click();
	await page.waitForFunction(() => document.querySelectorAll('.npcink-toolbox-editor-support__image-card').length === 4, null, { timeout: 10000 });
	assert(generationRequests.length === 2, 'One explicit semantic regeneration request is made.');
	assert(generationRequests[1].regeneration_mode === 'more_specific', 'The regeneration mode is sent to the existing image runtime.');
	assert(generationRequests[1].prompt_translation_mode === 'preplanned_pair', 'The English regeneration prompt is treated as an already paired provider prompt.');
	assert(generationRequests[1].provider_prompt_reviewed_by_operator === false, 'A generated English regeneration prompt is not falsely marked as operator-reviewed.');
	assert(await promptInput.inputValue() === prompt, 'Semantic regeneration preserves the reviewed Chinese visual description.');
	assert(!(await promptInput.inputValue()).includes('Regenerate this host-generated image'), 'The English regeneration prompt stays out of the primary visual-description field.');
	assert(await page.locator('.npcink-toolbox-editor-support__image-card').count() === 4, 'Regeneration preserves the two existing candidates and adds two revised candidates.');

	await page.screenshot({ path: screenshotPath, fullPage: true });
	pass(`Image recommendation browser smoke screenshot: ${screenshotPath}`);
	assert(forbiddenWriteRequests(requests).length === 0, 'Candidate review does not call WordPress write, Core proposal, Adapter execute, local consent, or media routes.');
} catch (error) {
	caughtError = error;
	if (page) {
		await captureDiagnostics(page, requests, error);
	}
} finally {
	if (loginHelper) {
		if (postId > 0 && page) {
			await page.goto(loginHelper.cleanupUrl(postId), { waitUntil: 'domcontentloaded', timeout: 10000 }).catch((error) => {
				console.error(`WARN: Could not delete temporary image-modal smoke post ${postId}. ${error.message || error}`);
			});
		}
		loginHelper.cleanupFile();
	}
	if (browser) {
		await browser.close();
	}
}

if (caughtError) {
	throw caughtError;
}

pass(`Editor image recommendation browser smoke completed at ${baseUrl}.`);
