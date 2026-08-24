#!/usr/bin/env node
/**
 * Browser smoke for the local progressive editor recommendation surface.
 *
 * This is intentionally not part of composer test:all. It needs a running local
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

function fail(message) {
	throw new Error(message);
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

function wpPath() {
	return env('WP_PATH', '/Users/muze/Local Sites/npcink/app/public');
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

function wpPostContent(postId) {
	const output = wpCli(['post', 'get', String(parseInt(postId, 10) || 0), '--fields=post_content', '--format=json']);
	const parsed = JSON.parse(output || '{}');
	return String(parsed.post_content || '');
}

function anchorEvidenceSummary(value, path = '', depth = 0, output = []) {
	if (!value || typeof value !== 'object' || depth > 8 || output.length >= 24) return output;
	Object.entries(value).forEach(([key, item]) => {
		if (output.length >= 24) return;
		const nextPath = path ? `${path}.${key}` : key;
		if (['anchor_or_context', 'suggested_anchor_text', 'anchor_text'].includes(key) && typeof item === 'string') {
			output.push({ path: nextPath, value: item.slice(0, 120) });
			return;
		}
		if (key === 'source_match' && item && typeof item === 'object') {
			output.push({ path: nextPath, matched_text: String(item.matched_text || '').slice(0, 120), has_block_client_id: Boolean(item.block_client_id) });
			return;
		}
		anchorEvidenceSummary(item, nextPath, depth + 1, output);
	});
	return output;
}

function createLoginHelper(baseUrl, postId, fixtureSourcePostId = 0) {
	const token = randomBytes(24).toString('hex');
	const fileName = `npcink-toolbox-browser-smoke-login-${randomBytes(8).toString('hex')}.php`;
	const filePath = `${wpPath().replace(/\/$/, '')}/${fileName}`;
	const requestedPostId = parseInt(postId, 10) || 0;
	const sourcePostId = parseInt(fixtureSourcePostId, 10) || 0;
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
$post_id = ${requestedPostId};
if ($post_id <= 0) {
	$fixture_content = 'This temporary draft exists only for the editor progressive browser smoke. It should be deleted by the smoke cleanup.';
	$fixture_source = get_post(${sourcePostId});
	if ($fixture_source instanceof WP_Post) {
		$fixture_content = (string) $fixture_source->post_content;
	}
	$post_id = wp_insert_post(array(
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_author'  => $user->ID,
		'post_title'   => 'Npcink browser smoke fixture ' . wp_generate_uuid4(),
		'post_content' => $fixture_content,
	), true);
	if (is_wp_error($post_id)) {
		http_response_code(500);
		exit($post_id->get_error_message());
	}
}
wp_safe_redirect(admin_url('post.php?post=' . absint($post_id) . '&action=edit'));
exit;
`);
	return {
		url: `${baseUrl}/${fileName}?token=${token}`,
		cleanupUrl: (cleanupPostId) => `${baseUrl}/${fileName}?token=${token}&action=cleanup&post_id=${parseInt(cleanupPostId, 10) || 0}`,
		cleanupFile: () => {
			try {
				unlinkSync(filePath);
			} catch (error) {
				// The smoke should not fail only because cleanup raced a local server.
			}
		},
	};
}

function progressiveRequests(requests) {
	return requests.filter((request) => request.url.includes('/wp-json/npcink-toolbox/v1/editor/content-support'));
}

function agentFeedbackRequests(requests) {
	return requests.filter((request) => request.url.includes('/wp-json/npcink-toolbox/v1/agent-feedback'));
}

function feedbackPayloadForAction(requests, action) {
	for (const request of agentFeedbackRequests(requests)) {
		const payload = JSON.parse(request.body || '{}');
		if (payload.source_action_id === action) return payload;
	}
	return null;
}

async function waitForFeedbackAction(requests, action, timeoutMs = 10000) {
	const start = Date.now();
	while (Date.now() - start < timeoutMs) {
		const payload = feedbackPayloadForAction(requests, action);
		if (payload) return payload;
		await new Promise((resolve) => setTimeout(resolve, 100));
	}
	fail(`Timed out waiting for Agent feedback action ${action}.`);
}

function assertMetadataOnlyFeedback(payload, label) {
	const serialized = JSON.stringify(payload || {});
	assert(payload && payload.redaction_status === 'metadata_only', `${label} declares metadata-only redaction.`);
	assert(Array.isArray(payload.evidence_ref_ids) && payload.evidence_ref_ids.length === 0, `${label} omits content and candidate evidence references.`);
	assert(!/"(?:content|post_content|anchor|anchor_text|source_match|provider_output)"\s*:/i.test(serialized) && String(payload.operator_note || '') === '', `${label} carries no raw content, anchor, source-match, note, or Provider fields.`);
}

function forbiddenRequests(requests) {
	return requests.filter((request) => {
		if (!request.url.includes('/wp-json/')) {
			return false;
		}
		if (request.url.includes('/wp-json/npcink-toolbox/v1/editor/content-support')) {
			return false;
		}
		return /proposal|adapter|governance-core|ai\/content-support|cloud/i.test(request.url);
	});
}

async function captureDiagnostics(page, requests, error) {
	const artifactDir = env('SMOKE_ARTIFACT_DIR', 'tests/artifacts');
	mkdirSync(artifactDir, { recursive: true });
	const screenshotPath = `${artifactDir}/editor-progressive-browser-failure.png`;
	await page.screenshot({ path: screenshotPath, fullPage: true }).catch(() => {});
	const pageText = await page.locator('body').innerText({ timeout: 2000 }).catch(() => '');
	console.error(`FAIL: Browser smoke diagnostic screenshot: ${screenshotPath}`);
	console.error(`FAIL: Browser smoke current URL: ${page.url()}`);
	console.error(`FAIL: Browser smoke page title: ${await page.title().catch(() => '')}`);
	console.error(`FAIL: Browser smoke wp-json requests: ${requests.length}`);
	console.error(`FAIL: Browser smoke visible text sample: ${pageText.replace(/\s+/g, ' ').trim().slice(0, 1200)}`);
	console.error(`FAIL: Browser smoke error: ${error && error.message ? error.message : String(error || 'unknown error')}`);
}

async function waitForProgressiveRequest(requests, count, timeoutMs = 7000) {
	const start = Date.now();
	while (Date.now() - start < timeoutMs) {
		if (progressiveRequests(requests).length >= count) {
			return;
		}
		await new Promise((resolve) => setTimeout(resolve, 100));
	}
	fail(`Timed out waiting for ${count} progressive content-support request(s). Saw ${progressiveRequests(requests).length}.`);
}

async function clickButtonWithText(page, pattern) {
	await page.locator('button').filter({ hasText: pattern }).first().click({ timeout: 30000 });
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
const baseUrl = env('WP_BASE_URL', 'https://npcink.local').replace(/\/$/, '');
const requestedPostId = process.env.POST_ID || '';
const internalLinkBatchSmoke = process.env.NPCINK_INTERNAL_LINK_BATCH_SMOKE === '1';
const requireInternalLinkApply = process.env.NPCINK_INTERNAL_LINK_REQUIRE_APPLY === '1';
const nativeSaveSmoke = process.env.NPCINK_INTERNAL_LINK_NATIVE_SAVE_SMOKE === '1';
const fixtureSourcePostId = parseInt(env('NPCINK_INTERNAL_LINK_FIXTURE_SOURCE_POST_ID', '0'), 10) || 0;
let activePostId = requestedPostId;
if (nativeSaveSmoke && requestedPostId) {
	fail('Native-save acceptance must use a disposable draft; omit POST_ID.');
}
if (nativeSaveSmoke && !fixtureSourcePostId) {
	fail('Native-save acceptance requires NPCINK_INTERNAL_LINK_FIXTURE_SOURCE_POST_ID.');
}
const browserOptions = {
	headless: process.env.HEADLESS !== '0',
};
if (process.env.BROWSER_EXECUTABLE) {
	browserOptions.executablePath = process.env.BROWSER_EXECUTABLE;
} else {
	const chrome = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
	if (existsSync(chrome)) {
		browserOptions.executablePath = chrome;
	}
}

const browser = await chromium.launch(browserOptions);
let loginHelper = null;
let page = null;
try {
	const viewport = {
		width: parseInt(env('SMOKE_VIEWPORT_WIDTH', '1440'), 10) || 1440,
		height: parseInt(env('SMOKE_VIEWPORT_HEIGHT', '1000'), 10) || 1000,
	};
	const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport });
	await context.grantPermissions(['clipboard-read', 'clipboard-write'], { origin: baseUrl }).catch(() => {});
	page = await context.newPage();
	const requests = [];
	const consoleErrors = [];
	const networkErrors = [];
	page.on('console', (message) => {
		if (message.type() === 'error') consoleErrors.push(message.text());
	});
	page.on('pageerror', (error) => consoleErrors.push(error.message || String(error)));
	page.on('response', (response) => {
		if (response.status() >= 400) networkErrors.push({ status: response.status(), url: response.url() });
	});
	page.on('request', (request) => {
		const url = request.url();
		if (!url.includes('/wp-json/')) {
			return;
		}
		requests.push({
			method: request.method(),
			url,
			body: request.postData() || '',
		});
	});

	try {
		loginHelper = createLoginHelper(baseUrl, requestedPostId, fixtureSourcePostId);
		await page.goto(loginHelper.url, { waitUntil: 'domcontentloaded', timeout: 45000 });
		activePostId = new URL(page.url()).searchParams.get('post') || activePostId;
		await page.waitForFunction(() => window.wp && window.wp.data && window.wp.data.dispatch, null, { timeout: 30000 });
		await dismissEditorOverlays(page);
		await page.evaluate(() => {
			const target = 'npcink-toolbox-editor-content-support/npcink-content-support-sidebar';
			const stores = ['core/edit-post', 'core/interface', 'core/editor'];
			for (let index = 0; index < stores.length; index += 1) {
				try {
					const dispatch = window.wp.data.dispatch(stores[index]);
					if (dispatch && typeof dispatch.openGeneralSidebar === 'function') {
						dispatch.openGeneralSidebar(target);
						return;
					}
				} catch (error) {
					// Older editor builds may not expose every store.
				}
			}
		});
		await waitForProgressiveRequest(requests, 1);
		await page.waitForSelector('text=/Run fixed support flows|围绕当前草稿运行固定支持流程/', { timeout: 30000 });
		const defaultProgressiveCardCount = await page.locator('text=/Fast recommendations|快速推荐|Local suggestions are ready|本地建议已就绪/').count();
		assert(defaultProgressiveCardCount === 0, 'Successful local progressive recommendations stay hidden by default.');
		const defaultLocalSuggestionsButtonCount = await page.locator('button').filter({ hasText: /Local suggestions|本地建议/ }).count();
		assert(defaultLocalSuggestionsButtonCount === 0, 'Successful local progressive recommendations do not add a default Local suggestions button.');

		const firstRequest = progressiveRequests(requests)[0];
		const firstPayload = JSON.parse(firstRequest.body || '{}');
		assert(firstRequest.method === 'POST', 'Automatic prefetch uses POST /editor/content-support.');
		assert(firstPayload.intent === 'progressive_recommendations', 'Automatic prefetch sends the progressive_recommendations intent.');
		assert(!firstPayload.proposal_id && !firstPayload.adapter_rest_url && !firstPayload.write_confirmed, 'Automatic prefetch does not send proposal handoff controls.');
		assert(forbiddenRequests(requests).length === 0, 'Automatic prefetch does not call Cloud, Adapter, or Core proposal routes.');

		if (internalLinkBatchSmoke) {
			assert(Boolean(activePostId), 'Internal-link browser smoke has a real post id.');
			const databaseContentBefore = wpPostContent(activePostId);
			const editorContentBefore = await page.evaluate(() => {
				const selector = window.wp.data.select('core/editor');
				return selector && typeof selector.getEditedPostContent === 'function' ? selector.getEditedPostContent() : '';
			});
			const relatedResponsePromise = page.waitForResponse((response) => {
				const request = response.request();
				return response.url().includes('/wp-json/npcink-toolbox/v1/editor/content-support')
					&& String(request.postData() || '').includes('related_articles');
			}, { timeout: 45000 });
			await page.getByRole('button', { name: /Run Find related articles|运行 查找相关文章|Run 查找相关文章/i }).click({ timeout: 30000 });
			const relatedResponse = await relatedResponsePromise;
			const relatedPayload = await relatedResponse.json();
			const relatedSection = relatedPayload && relatedPayload.sections ? relatedPayload.sections.related_articles || {} : {};
			const relatedCandidates = Array.isArray(relatedSection.recommendation_candidates) && relatedSection.recommendation_candidates.length
				? relatedSection.recommendation_candidates
				: (Array.isArray(relatedSection.items) ? relatedSection.items : []);
				await page.waitForSelector('text=/Recommended related articles|推荐相关文章/', { timeout: 30000 });
				const relatedImpression = await waitForFeedbackAction(requests, 'related_article_impression');
				assertMetadataOnlyFeedback(relatedImpression, 'Related-article impression');
				assert((relatedImpression.source_reason_codes || []).some((code) => /^candidate_count_/.test(code)), 'Related-article impression records a bounded candidate-count denominator.');
			assert(relatedResponse.status() >= 200 && relatedResponse.status() < 300, 'Related-articles editor request returns a successful HTTP status.');
			assert(relatedSection.direct_wordpress_write === false, 'Related-articles browser response disables direct WordPress writes.');
			assert(['cloud_vector_evidence', 'no_cloud_evidence', 'only_current_post', 'cloud_unavailable'].includes(String(relatedSection.retrieval_status || '')), 'Related-articles response labels its retrieval status explicitly.');
			assert(['cloud_vector', 'none', 'local_fallback'].includes(String(relatedSection.candidate_source || '')), 'Related-articles response labels its candidate source explicitly.');
			const relatedCopyButton = page.locator('.npcink-toolbox-editor-support__related-articles button').filter({ hasText: /Copy link|复制链接/ }).first();
			let relatedLinkCopied = false;
				if (await relatedCopyButton.count()) {
					await relatedCopyButton.click();
					await page.waitForSelector('text=/Link copied|链接已复制/', { timeout: 10000 });
					const relatedCopyFeedback = await waitForFeedbackAction(requests, 'related_article_copy');
					assertMetadataOnlyFeedback(relatedCopyFeedback, 'Related-article copy');
					assert(relatedCopyFeedback.source_object_id === relatedImpression.source_object_id, 'Related-article copy correlates to its recommendation impression session.');
					relatedLinkCopied = true;
				pass('A reviewed related-article URL can be copied manually.');
			} else {
				const relatedEmptyStateCount = await page.locator('text=/Cloud 相关文章检索暂不可用|暂未找到相关已发布文章|Cloud 只命中了当前文章/').count();
				assert(relatedEmptyStateCount > 0 || relatedCandidates.length === 0, 'Related-articles empty results show a bounded Chinese state.');
			}
			assert(wpPostContent(activePostId) === databaseContentBefore, 'Related-article review and copy do not persist WordPress content.');
			await page.getByText(/工具列表|Tool list/i, { exact: true }).click({ timeout: 10000 });
			await page.waitForSelector('text=/Run fixed support flows|围绕当前草稿运行固定支持流程/', { timeout: 10000 });
			const responsePromise = page.waitForResponse((response) => {
				const request = response.request();
				return response.url().includes('/wp-json/npcink-toolbox/v1/editor/content-support')
					&& String(request.postData() || '').includes('internal_links');
			}, { timeout: 45000 });
			await page.getByRole('button', { name: /Run Find internal links|运行 查找内链|Run 查找内链/i }).click({ timeout: 30000 });
			const internalLinkResponse = await responsePromise;
			const internalLinkPayload = await internalLinkResponse.json();
			const internalLinkSection = internalLinkPayload && internalLinkPayload.sections ? internalLinkPayload.sections.internal_links || {} : {};
				await page.waitForSelector('text=/Recommended internal links|推荐内链/', { timeout: 30000 });
				const internalLinkImpression = await waitForFeedbackAction(requests, 'internal_link_impression');
				assertMetadataOnlyFeedback(internalLinkImpression, 'Internal-link impression');
				assert((internalLinkImpression.source_reason_codes || []).some((code) => /^applicable_count_/.test(code)), 'Internal-link impression records a bounded applicable-count denominator.');
			assert(internalLinkResponse.status() >= 200 && internalLinkResponse.status() < 300, 'Internal-link editor request returns a successful HTTP status.');
			assert(internalLinkSection.direct_wordpress_write === false, 'Internal-link browser response disables direct WordPress writes.');
			assert(internalLinkSection.editor_transaction && internalLinkSection.editor_transaction.schema === 'current_article_multi_link_result.v1', 'Internal-link browser response exposes the current-article transaction contract.');

			const candidateCount = Array.isArray(internalLinkSection.recommendation_candidates) ? internalLinkSection.recommendation_candidates.length : 0;
			const candidateReview = (Array.isArray(internalLinkSection.recommendation_candidates) ? internalLinkSection.recommendation_candidates : []).map((candidate) => ({
				id: candidate.id || '',
				anchor_text: candidate.anchor_or_context || '',
				anchor_quality_status: candidate.anchor_quality_status || '',
				can_apply_to_editor: candidate.can_apply_to_editor === true,
				has_source_match: Boolean(candidate.source_match && candidate.source_match.matched_text),
				target_post_id: candidate.target_ref && candidate.target_ref.post_id || 0,
				target_status: candidate.target_ref && candidate.target_ref.status || '',
				target_post_type: candidate.target_ref && candidate.target_ref.post_type || '',
				candidate_source: candidate.candidate_source || internalLinkSection.candidate_source || '',
			}));
			const cloudAnchorEvidence = anchorEvidenceSummary(internalLinkSection.source_knowledge || {});
			console.log(`INFO: internal_link_candidate_review=${JSON.stringify(candidateReview)}`);
			console.log(`INFO: internal_link_cloud_anchor_evidence=${JSON.stringify(cloudAnchorEvidence)}`);
			const checkboxLocator = page.locator('.npcink-toolbox-editor-support__internal-link-card input[type="checkbox"]');
			const applicableCount = await checkboxLocator.count();
			const copyButton = page.locator('.npcink-toolbox-editor-support__internal-link-card button').filter({ hasText: /Copy link|复制链接/ }).first();
				if (await copyButton.count()) {
					await copyButton.click();
					await page.waitForSelector('text=/Link copied|链接已复制/', { timeout: 10000 });
					const internalLinkCopyFeedback = await waitForFeedbackAction(requests, 'internal_link_copy');
					assertMetadataOnlyFeedback(internalLinkCopyFeedback, 'Internal-link copy');
					assert(internalLinkCopyFeedback.source_object_id === internalLinkImpression.source_object_id, 'Internal-link copy correlates to its recommendation impression session.');
					pass('A reviewed internal-link URL can be copied manually.');
			}

			let appliedCount = 0;
			let rejectedCount = 0;
			let undoPerformed = false;
			let nativeSavePerformed = false;
			let nativeSaveFeedback = null;
			if (applicableCount > 0) {
				const selectionCount = Math.min(2, applicableCount);
				for (let index = 0; index < selectionCount; index += 1) {
					await checkboxLocator.nth(index).check();
				}
				const editorContentAfterSelection = await page.evaluate(() => window.wp.data.select('core/editor').getEditedPostContent());
				assert(editorContentAfterSelection === editorContentBefore, 'Selecting internal-link suggestions does not mutate editor content.');
				assert(wpPostContent(activePostId) === databaseContentBefore, 'Selecting internal-link suggestions does not persist WordPress content.');
				const requestIndexBeforeApply = requests.length;
				await page.getByRole('button', { name: /应用所选内链|Apply selected internal links/i }).click();
				await page.waitForSelector('text=/已在当前编辑器应用/', { timeout: 10000 });
				const resultText = await page.locator('.components-notice, .npcink-toolbox-editor-support__notice').filter({ hasText: /已在当前编辑器应用/ }).last().innerText();
				const counts = resultText.match(/应用\s*(\d+)\s*条，拒绝\s*(\d+)\s*条/);
				appliedCount = counts ? parseInt(counts[1], 10) : 0;
				rejectedCount = counts ? parseInt(counts[2], 10) : 0;
				const editorContentAfterApply = await page.evaluate(() => window.wp.data.select('core/editor').getEditedPostContent());
					assert(appliedCount > 0 && editorContentAfterApply !== editorContentBefore, 'Explicit Apply changes only the visible Gutenberg editor state.');
					const internalLinkApplyFeedback = await waitForFeedbackAction(requests, 'internal_link_applied_to_editor');
					assert(internalLinkApplyFeedback.source_object_id === internalLinkImpression.source_object_id, 'Internal-link Apply correlates to its recommendation impression session.');
					assert(wpPostContent(activePostId) === databaseContentBefore, 'Explicit Apply does not persist post_content before native Update or Publish.');
				const applyRequests = requests.slice(requestIndexBeforeApply);
				const directWriteRequests = applyRequests.filter((request) => /\/wp-json\/wp\/v2\/(posts|pages)\/\d+/i.test(request.url) && /POST|PUT|PATCH/i.test(request.method));
				assert(directWriteRequests.length === 0, 'Toolbox Apply sends no direct WordPress post write request.');
				if (nativeSaveSmoke) {
					await page.evaluate(async () => {
						const dispatch = window.wp.data.dispatch('core/editor');
						if (!dispatch || typeof dispatch.savePost !== 'function') {
							throw new Error('WordPress core/editor savePost is unavailable.');
						}
						await dispatch.savePost();
					});
					nativeSaveFeedback = await waitForFeedbackAction(requests, 'internal_link_saved_unchanged', 20000);
					assertMetadataOnlyFeedback(nativeSaveFeedback, 'Internal-link native-save confirmation');
					assert(nativeSaveFeedback.source_object_type === 'recommendation_session', 'Native-save confirmation keeps recommendation-session identity.');
					assert(nativeSaveFeedback.source_object_id === internalLinkImpression.source_object_id, 'Native-save confirmation correlates to the original recommendation impression session.');
					const databaseContentAfterSave = wpPostContent(activePostId);
					assert(databaseContentAfterSave !== databaseContentBefore && /<a\s/i.test(databaseContentAfterSave), 'WordPress post_content changes only after the explicit native save.');
					nativeSavePerformed = true;
				} else {
					await page.getByRole('button', { name: /撤销本次内链应用|Undo current internal-link application/i }).click();
					await page.waitForSelector('text=/本次应用的内链已从当前编辑器撤销/', { timeout: 10000 });
					const editorContentAfterUndo = await page.evaluate(() => window.wp.data.select('core/editor').getEditedPostContent());
					assert(editorContentAfterUndo === editorContentBefore, 'Explicit Undo restores the visible Gutenberg editor state.');
					assert(wpPostContent(activePostId) === databaseContentBefore, 'Explicit Undo does not persist post_content.');
					undoPerformed = true;
				}
			} else {
				if (requireInternalLinkApply) {
					fail('The content-rich article did not return an exact source match required for Apply.');
				}
				pass('Review-only acceptance allows no Apply when the response has no exact source match.');
				const emptyStateCount = await page.locator('text=/No internal link candidates returned|没有安全、具体且可匹配的锚文本|只能复制链接或打开文章检查|暂未找到/').count();
				assert(emptyStateCount > 0 || candidateCount === 0, 'Weak or empty internal-link results show a bounded Chinese empty/review-only state.');
				assert(await page.getByRole('button', { name: /应用所选内链|Apply selected internal links/i }).isDisabled().catch(() => true), 'Batch Apply stays unavailable without an exact source match.');
			}

			const artifactDir = env('SMOKE_ARTIFACT_DIR', 'tests/artifacts');
			mkdirSync(artifactDir, { recursive: true });
			const screenshotPath = `${artifactDir}/editor-internal-link-batch-${activePostId}.png`;
			await page.screenshot({ path: screenshotPath, fullPage: true });
			const toolboxNetworkErrors = networkErrors.filter((entry) => /npcink-toolbox|npcink-cloud-addon/i.test(entry.url));
			assert(toolboxNetworkErrors.length === 0, 'Internal-link browser flow has no Toolbox or Cloud Addon HTTP errors.');
			console.log(`INFO: internal_link_browser_receipt=${JSON.stringify({
				post_id: parseInt(activePostId, 10),
				related_articles: {
					http_status: relatedResponse.status(),
					retrieval_status: relatedSection.retrieval_status || '',
					candidate_source: relatedSection.candidate_source || '',
					candidate_count: relatedCandidates.length,
					link_copied: relatedLinkCopied,
					direct_wordpress_write: relatedSection.direct_wordpress_write,
				},
				http_status: internalLinkResponse.status(),
				retrieval_status: internalLinkSection.retrieval_status || '',
				candidate_source: internalLinkSection.candidate_source || '',
				candidate_count: candidateCount,
				anchors: candidateReview.map((candidate) => ({ anchor_text: candidate.anchor_text, has_source_match: candidate.has_source_match })),
				cloud_anchor_evidence: cloudAnchorEvidence,
				applicable_count: applicableCount,
				applied_count: appliedCount,
				rejected_count: rejectedCount,
				undo_performed: undoPerformed,
				native_save_performed: nativeSavePerformed,
				native_save_feedback: nativeSaveFeedback ? nativeSaveFeedback.source_action_id : '',
				direct_wordpress_write: internalLinkSection.direct_wordpress_write,
				wordpress_write: wpPostContent(activePostId) !== databaseContentBefore,
				console_error_count: consoleErrors.length,
				console_errors: consoleErrors.slice(0, 5),
				network_error_count: networkErrors.length,
				network_errors: networkErrors.slice(0, 5),
				viewport,
				screenshot: screenshotPath,
			})}`);
		}
	} catch (error) {
		await captureDiagnostics(page, requests, error);
		throw error;
	}
} finally {
	if (loginHelper) {
		if (!requestedPostId && activePostId && page) {
			await page.goto(loginHelper.cleanupUrl(activePostId), { waitUntil: 'domcontentloaded', timeout: 10000 }).catch((error) => {
				console.error(`WARN: Could not delete temporary browser smoke post ${activePostId}. ${error.message || error}`);
			});
		}
		loginHelper.cleanupFile();
	}
	await browser.close();
}

pass(`Browser smoke completed for post ${activePostId} at ${baseUrl}.`);
