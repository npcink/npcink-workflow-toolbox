#!/usr/bin/env node
/**
 * Browser smoke for per-occurrence contextual article image ALT review.
 *
 * This is intentionally outside composer test:all. It needs a running local
 * WordPress site, WP-CLI access, and Playwright.
 */

import { execFileSync } from 'node:child_process';
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

async function waitForCount(readCount, expected, timeoutMs = 10000) {
	const started = Date.now();
	while (Date.now() - started < timeoutMs) {
		if (readCount() >= expected) {
			return;
		}
		await new Promise((resolve) => setTimeout(resolve, 100));
	}
	throw new Error(`Timed out waiting for ${expected} matching browser event(s).`);
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

function wpCli(args) {
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
		{ encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }
	).trim();
}

function createLoginHelper(baseUrl, postId) {
	const token = randomBytes(24).toString('hex');
	const fileName = `npcink-toolbox-contextual-alt-login-${randomBytes(8).toString('hex')}.php`;
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
wp_safe_redirect(admin_url('post.php?post=${parseInt(postId, 10)}&action=edit'));
exit;
`);
	return {
		url: `${baseUrl}/${fileName}?token=${token}`,
		cleanup: () => {
			try {
				unlinkSync(filePath);
			} catch (error) {
				// A cleanup race must not hide the primary smoke result.
			}
		},
	};
}

function createFixture() {
	const attachments = JSON.parse(wpCli([
		'post',
		'list',
		'--post_type=attachment',
		'--post_mime_type=image',
		'--post_status=inherit',
		'--posts_per_page=1',
		'--fields=ID,guid',
		'--format=json',
	]));
	assert(Array.isArray(attachments) && attachments.length === 1, 'A local image attachment is available for the contextual ALT fixture.');
	const attachmentId = parseInt(attachments[0].ID, 10);
	const imageUrl = String(attachments[0].guid || '');
	assert(attachmentId > 0 && imageUrl, 'The fixture attachment exposes an id and URL.');
	const attachmentAlt = wpCli(['eval', `echo (string) get_post_meta(${attachmentId}, '_wp_attachment_image_alt', true);`]);
	const existingAlt = '人工填写的蓝色陶瓷杯 ALT';
	const contentParts = [];
	for (let index = 1; index <= 13; index += 1) {
		const alt = index <= 10 ? '' : existingAlt;
		contentParts.push(`<!-- wp:heading --><h2 class="wp-block-heading">咖啡杯场景 ${index}</h2><!-- /wp:heading -->`);
		contentParts.push(`<!-- wp:paragraph --><p>第 ${index} 张图用于说明工作日咖啡杯在桌面上的具体使用场景。</p><!-- /wp:paragraph -->`);
		contentParts.push(`<!-- wp:image {"id":${attachmentId},"sizeSlug":"large","linkDestination":"none"} --><figure class="wp-block-image size-large"><img src="${imageUrl}" alt="${alt}" class="wp-image-${attachmentId}"/></figure><!-- /wp:image -->`);
	}
	const content = contentParts.join('\n');
	const postId = parseInt(wpCli([
		'post',
		'create',
		'--post_type=post',
		'--post_status=draft',
		'--post_title=上下文 ALT SEO 浏览器验收（临时）',
		`--post_content=${content}`,
		'--porcelain',
	]), 10);
	assert(postId > 0, 'The browser smoke created its temporary contextual ALT article.');
	return { postId, attachmentId, attachmentAlt, existingAlt };
}

const fixture = createFixture();
const postId = fixture.postId;

const { chromium } = await loadPlaywright();
const baseUrl = env('WP_BASE_URL', 'https://magick-ai.local').replace(/\/$/, '');
const artifactDir = env('SMOKE_ARTIFACT_DIR', 'build/smoke');
mkdirSync(artifactDir, { recursive: true });
const screenshotPath = `${artifactDir}/contextual-alt-seo-editor.png`;
const failurePath = `${artifactDir}/contextual-alt-seo-editor-failure.png`;
const browserOptions = { headless: process.env.HEADLESS !== '0' };
const chrome = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
if (existsSync(chrome)) {
	browserOptions.executablePath = chrome;
}

let browser = null;
let page = null;
let loginHelper = null;
try {
	browser = await chromium.launch(browserOptions);
	const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 1800 } });
	page = await context.newPage();
	const requests = [];
	const contextualResponses = [];
	const feedbackPayloads = [];
	await page.route('**/wp-json/npcink-toolbox/v1/agent-feedback', async (route) => {
		const request = route.request();
		try {
			feedbackPayloads.push(request.postDataJSON());
		} catch (error) {
			feedbackPayloads.push(JSON.parse(request.postData() || '{}'));
		}
		await route.fulfill({
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify({ accepted: true }),
		});
	});
	page.on('request', (request) => {
		if (request.url().includes('/wp-json/')) {
			requests.push({ url: request.url(), body: request.postData() || '' });
		}
	});
	page.on('response', async (response) => {
		if (response.url().includes('/wp-json/npcink-toolbox/v1/editor/content-support')) {
			try {
				const payload = await response.json();
				if (payload && payload.intent === 'image_alt_suggestions') {
					contextualResponses.push(payload);
				}
			} catch (error) {
				// The explicit response assertions below report malformed payloads.
			}
		}
	});

	loginHelper = createLoginHelper(baseUrl, postId);
	await page.goto(loginHelper.url, { waitUntil: 'domcontentloaded', timeout: 45000 });
	await page.waitForFunction(() => window.wp && window.wp.data && window.wp.data.dispatch, null, { timeout: 30000 });
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
	const standaloneAltFlow = page.locator('.npcink-toolbox-editor-support__flow').filter({ hasText: /Article image ALT|文章图片 ALT/ });
	assert(await standaloneAltFlow.count() === 0, 'Editor does not expose a separate Article Image ALT workflow.');
	const discoverabilityFlow = page.locator('.npcink-toolbox-editor-support__flow').filter({ hasText: /Publish preflight|发布预检/ });
	assert(await discoverabilityFlow.count() === 1, 'Editor uses the existing SEO-aware Publish preflight workflow as the ALT entry point.');
	await discoverabilityFlow.locator('button').click({ timeout: 30000 });
	await page.waitForSelector('.npcink-toolbox-editor-support__discoverability-media', { timeout: 45000 });
	await page.locator('.npcink-toolbox-editor-support__discoverability-media button').filter({ hasText: /Preview contextual ALT|预览上下文 ALT/ }).click({ timeout: 30000 });

	await page.waitForSelector('.npcink-toolbox-editor-support__contextual-alt-card', { timeout: 30000 });
	let cards = page.locator('.npcink-toolbox-editor-support__contextual-alt-card');
	assert(await cards.count() === 10, 'The first review page contains exactly ten image occurrences.');
	let inputs = page.locator('[data-toolbox-contextual-alt-input]');
	assert(await inputs.count() === 10, 'The first page exposes ten contextual ALT drafts.');
	const firstPageValues = await inputs.evaluateAll((elements) => elements.map((element) => element.value));
	assert(firstPageValues.every(Boolean), 'Every first-page occurrence receives a local contextual ALT draft.');

	await waitForCount(() => contextualResponses.length, 1);
	const firstSection = contextualResponses[0].sections.image_alt_suggestions;
	assert(firstSection.provider_execution === 'none' && firstSection.occurrence_offset === 0 && firstSection.total_occurrence_count === 13, 'Initial ALT review is cache-only and reports the complete occurrence count.');
	const untouchedAltValues = await page.evaluate(() => window.wp.data.select('core/block-editor').getBlocks()
		.filter((block) => block.name === 'core/image')
		.map((block) => String(block.attributes.alt || '')));
	assert(untouchedAltValues.length === 13 && untouchedAltValues.slice(0, 10).every((alt) => alt === '') && untouchedAltValues.slice(10).every((alt) => alt === fixture.existingAlt), 'Preview does not automatically change Gutenberg ALT values.');

	const customAlt = '人工审阅后的第一个咖啡杯场景';
	await inputs.nth(0).fill(customAlt);
	await page.locator('[data-toolbox-contextual-alt-decorative]').nth(1).check();
	await page.locator('button').filter({ hasText: /Next page|下一页/ }).click();
	await page.waitForFunction(() => document.body.textContent.includes('Page 2 of 2') || document.body.textContent.includes('第 2 页'));
	cards = page.locator('.npcink-toolbox-editor-support__contextual-alt-card');
	assert(await cards.count() === 3, 'The second review page contains the remaining three occurrences.');
	assert(await page.getByText(/Existing ALT preserved|已有 ALT 已保留/).count() === 3, 'Existing ALT values on the second page are visibly preserved and read-only.');
	const applyButton = page.locator('button').filter({ hasText: /Apply ALT to draft|应用 ALT 到草稿|将 ALT 应用到草稿/ });
	assert(await applyButton.isEnabled(), 'Final Apply remains available on a page with no editable item because earlier reviewed pages contain changes.');

	await waitForCount(() => contextualResponses.length, 2);
	let contextualRequests = requests.filter((request) => request.url.includes('/wp-json/npcink-toolbox/v1/editor/content-support') && request.body.includes('image_alt_suggestions'));
	const requestPayloads = contextualRequests.map((request) => JSON.parse(request.body));
	assert(requestPayloads[0].media_items.length === 10 && requestPayloads[1].media_items.length === 3, 'Each ALT request carries only its current occurrence page.');
	assert(requestPayloads[0].occurrence_offset === 0 && requestPayloads[1].occurrence_offset === 10 && requestPayloads.every((payload) => payload.visual_recognition_consent === false), 'Paging sends stable offsets and never grants implicit visual-recognition consent.');

	await page.locator('button').filter({ hasText: /Previous page|上一页/ }).click();
	await page.waitForFunction(() => document.body.textContent.includes('Page 1 of 2') || document.body.textContent.includes('第 1 页'));
	inputs = page.locator('[data-toolbox-contextual-alt-input]');
	assert(await inputs.nth(0).inputValue() === customAlt && await page.locator('[data-toolbox-contextual-alt-decorative]').nth(1).isChecked(), 'Edited ALT and decorative state survive cross-page navigation.');
	await page.locator('button').filter({ hasText: /Next page|下一页/ }).click();
	await page.waitForFunction(() => document.body.textContent.includes('Page 2 of 2') || document.body.textContent.includes('第 2 页'));
	await page.locator('button').filter({ hasText: /Apply ALT to draft|应用 ALT 到草稿|将 ALT 应用到草稿/ }).click();
	await page.waitForSelector('text=/applied to the current editor|应用到当前编辑器/', { timeout: 30000 });

	const appliedBlocks = await page.evaluate(() => window.wp.data.select('core/block-editor').getBlocks()
		.filter((block) => block.name === 'core/image')
		.map((block) => ({ alt: String(block.attributes.alt || ''), decorative: Boolean(block.attributes.metadata && block.attributes.metadata.npcink && block.attributes.metadata.npcink.decorative) })));
	assert(appliedBlocks.length === 13 && appliedBlocks[0].alt === customAlt && appliedBlocks[1].alt === '' && appliedBlocks[1].decorative, 'Confirmed review applies the edited ALT and persists the decorative marker in Gutenberg state.');
	assert(appliedBlocks.slice(2, 10).every((block) => block.alt) && appliedBlocks.slice(10).every((block) => block.alt === fixture.existingAlt), 'Final Apply fills only reviewed empty core/image ALT and preserves every existing ALT.');
	assert(!requests.some((request) => /reviewed-action-intents|contextual-alt-audit|approve-and-execute/.test(request.url)), 'Native editor ALT apply sends no Core audit or hidden execution request.');
	await waitForCount(
		() => feedbackPayloads.filter((payload) => payload && payload.source_action_id === 'alt_suggestion_applied_to_editor').length,
		10
	);
	const appliedFeedback = feedbackPayloads.find((payload) => payload && payload.source_action_id === 'alt_suggestion_applied_to_editor');
	assert(
		appliedFeedback
			&& Array.isArray(appliedFeedback.feedback_labels)
			&& appliedFeedback.feedback_labels.includes('alt_suggestion_applied')
			&& Array.isArray(appliedFeedback.evidence_ref_ids)
			&& appliedFeedback.evidence_ref_ids.length === 0,
		'ALT draft apply emits one metadata-only quality event without evidence identifiers.'
	);
	assert(!requests.some((request) => /\/wp-json\/wp\/v2\/(posts|media)\//.test(request.url) && request.body), 'The apply action does not save the post or mutate media through wp/v2.');
	const attachmentAltAfter = wpCli(['eval', `echo (string) get_post_meta(${fixture.attachmentId}, '_wp_attachment_image_alt', true);`]);
	assert(attachmentAltAfter === fixture.attachmentAlt, 'The contextual article ALT apply leaves attachment-global media ALT unchanged.');

	const savedEditedAlt = `${customAlt}（保存前微调）`;
	await page.evaluate((nextAlt) => {
		const imageBlock = window.wp.data.select('core/block-editor').getBlocks()
			.find((block) => block.name === 'core/image' && String(block.attributes.alt || '') !== '人工填写的蓝色陶瓷杯 ALT');
		window.wp.data.dispatch('core/block-editor').updateBlockAttributes(imageBlock.clientId, { alt: nextAlt });
	}, savedEditedAlt);
	await page.evaluate(async () => {
		await window.wp.data.dispatch('core/editor').savePost();
	});
	await page.waitForFunction(
		() => {
			const editor = window.wp.data.select('core/editor');
			return !editor.isSavingPost() && editor.didPostSaveRequestSucceed();
		},
		null,
		{ timeout: 30000 }
	);
	await waitForCount(
		() => feedbackPayloads.filter((payload) => payload && payload.source_action_id === 'alt_saved_edited').length,
		1
	);
	const savedFeedback = feedbackPayloads.find((payload) => payload && payload.source_action_id === 'alt_saved_edited');
	assert(
		savedFeedback
			&& Array.isArray(savedFeedback.feedback_labels)
			&& savedFeedback.feedback_labels.includes('alt_saved_edited')
			&& savedFeedback.source_object_id === appliedFeedback.source_object_id,
		'Successful native WordPress save correlates the edited ALT outcome to its apply event.'
	);
	const serializedFeedback = JSON.stringify(feedbackPayloads);
	assert(!serializedFeedback.includes(customAlt) && !serializedFeedback.includes(savedEditedAlt), 'ALT quality events contain no suggested or saved ALT text.');
	const savedPostContent = wpCli(['post', 'get', String(postId), '--field=post_content']);
	assert(savedPostContent.includes(savedEditedAlt), 'The temporary article persisted the edited block ALT through the native WordPress save.');
	assert(!feedbackPayloads.some((payload) => payload && payload.source_action_id === 'alt_saved_unchanged' && payload.source_object_id === appliedFeedback.source_object_id), 'The edited saved occurrence is not misclassified as unchanged while other accepted occurrences keep their own outcomes.');
	const attachmentAltAfterSave = wpCli(['eval', `echo (string) get_post_meta(${fixture.attachmentId}, '_wp_attachment_image_alt', true);`]);
	assert(attachmentAltAfterSave === fixture.attachmentAlt, 'Saving the article still leaves attachment-global media ALT unchanged.');

	await page.screenshot({ path: screenshotPath, fullPage: true });
	pass(`Contextual ALT screenshot: ${screenshotPath}`);
} catch (error) {
	if (page) {
		await page.screenshot({ path: failurePath, fullPage: true }).catch(() => {});
	}
	console.error(`FAIL: ${error.message || error}`);
	console.error(`FAIL: Diagnostic screenshot: ${failurePath}`);
	process.exitCode = 1;
} finally {
	if (loginHelper) {
		loginHelper.cleanup();
	}
	try {
		wpCli(['post', 'delete', String(postId), '--force']);
		pass(`Temporary contextual ALT article ${postId} was deleted.`);
	} catch (error) {
		console.error(`WARN: Could not delete temporary contextual ALT article ${postId}. ${error.message || error}`);
	}
	if (browser) {
		await browser.close();
	}
}
