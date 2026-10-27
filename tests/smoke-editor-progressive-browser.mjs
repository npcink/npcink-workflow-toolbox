#!/usr/bin/env node
/**
 * Browser smoke for the local progressive editor recommendation surface.
 *
 * This is intentionally not part of composer test:all. It needs a running local
 * WordPress site, WP-CLI access, and Playwright.
 */

import { execFileSync } from 'node:child_process';
import { editorBlockSnapshot } from './editor-block-snapshot.mjs';
import { recommendationCloudObservation } from './recommendation-cloud-observation.mjs';
import { createFreshCloudProbe } from './fresh-cloud-probe.mjs';
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
	$fixture_title = 'Npcink browser smoke fixture ' . wp_generate_uuid4();
	$fixture_source = get_post(${sourcePostId});
	if ($fixture_source instanceof WP_Post) {
		$fixture_content = (string) $fixture_source->post_content;
		$fixture_title = (string) $fixture_source->post_title . ' [smoke]';
	}
	$post_id = wp_insert_post(array(
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_author'  => $user->ID,
		'post_title'   => $fixture_title,
		'post_content' => wp_slash($fixture_content),
	), true);
	if (is_wp_error($post_id)) {
		http_response_code(500);
		exit($post_id->get_error_message());
	}
}
if (${sourcePostId} > 0 && ${requestedPostId} === 0 && (string) get_post_field('post_content', $post_id) !== $fixture_content) { wp_delete_post($post_id, true); http_response_code(500); exit('real_body_copy_mismatch'); }
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
	const family = String(payload.source_action_id || '').startsWith('related_content_') ? 'related_content' : 'internal_links';
	const version = family + '.' + (process.env.NPCINK_RECOMMENDATION_VERSION || 'v1');
	assert(payload.ranking_version === version, `${label} carries the expected ranking version.`);
	assert(['cloud_vector', 'cloud_vector_rerank', 'local_fallback', 'no_evidence', 'unavailable', 'unknown'].includes(payload.retrieval_source), `${label} carries a bounded retrieval source.`);
	assert(!Object.hasOwn(payload, 'rerank_applied') || typeof payload.rerank_applied === 'boolean', `${label} preserves a boolean or absent rerank observation.`);
	if (/(?:_copy|_open|_applied_to_editor)$/.test(payload.source_action_id)) {
		assert(Number.isInteger(payload.candidate_rank) && payload.candidate_rank >= 1 && payload.candidate_rank <= 8, `${label} carries a bounded candidate rank.`);
		assert(['low', 'medium', 'high', 'unknown'].includes(payload.score_bucket), `${label} carries a bounded score bucket.`);
	}
	if (/_impression$/.test(payload.source_action_id)) assert(!Object.hasOwn(payload, 'candidate_rank'), `${label} has no fabricated candidate rank.`);
	assert(payload.source_object_type === 'recommendation_session' && Boolean(payload.source_object_id), `${label} preserves session identity.`);
	assert(Array.isArray(payload.evidence_ref_ids) && payload.evidence_ref_ids.length === 0, `${label} omits content and candidate evidence references.`);
	assert(!/"(?:content|post_content|anchor|anchor_text|source_match|provider_output|prompt|title|url|source_excerpt|score|quality_score)"\s*:/i.test(serialized) && String(payload.operator_note || '') === '', `${label} carries no raw content, anchor, source-match, note, or Provider fields.`);
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
const allowUnconfiguredCloud = process.env.NPCINK_INTERNAL_LINK_ALLOW_UNCONFIGURED_CLOUD === '1';
const nativeSaveSmoke = process.env.NPCINK_INTERNAL_LINK_NATIVE_SAVE_SMOKE === '1';
const requireFreshCloud = process.env.NPCINK_INTERNAL_LINK_REQUIRE_FRESH_CLOUD === '1';
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
let freshCloudProbe = null;
let cloudObservationEmitted = false;
let page = null;
try {
	const viewport = {
		width: parseInt(env('SMOKE_VIEWPORT_WIDTH', '1440'), 10) || 1440,
		height: parseInt(env('SMOKE_VIEWPORT_HEIGHT', '1000'), 10) || 1000,
	};
	const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport });
	await context.grantPermissions(['clipboard-read', 'clipboard-write'], { origin: baseUrl }).catch(() => {});
	page = await context.newPage();
	// Browser smokes stand in for a present, confirmed operator, so accept the
	// reviewed window.confirm dialogs the hardened flows now require.
	page.on('dialog', (dialog) => dialog.accept());
	const requests = [];
	const consoleErrors = [];
	const networkErrors = [];
	const developerFeedback = [];
	const feedbackReceipts = [];
	if (requireFreshCloud) {
		freshCloudProbe = createFreshCloudProbe(wpPath(), baseUrl);
		await page.route('**/npcink-toolbox/v1/editor/content-support', (route) => {
			const request = route.request();
			if (new URL(request.url()).origin !== new URL(baseUrl).origin || request.method() !== 'POST' || request.postDataJSON()?.intent !== 'internal_links') return route.continue();
			return route.continue({ headers: { ...request.headers(), ...freshCloudProbe.headers } });
		});
	}
	await page.route('**/npcink-toolbox/v1/agent-feedback', async (route) => {
		const request = route.request();
		if (request.method() !== 'POST') return route.continue();
		const payload = request.postDataJSON();
		if (!/^(?:internal_link|related_content)_/.test(payload.source_action_id || '')) return route.continue();
		assertMetadataOnlyFeedback(payload, 'Observed ' + payload.source_action_id);
		// Test transport annotation only: never count developer traffic as human adoption.
		payload.source_reason_codes = [...new Set(['developer_smoke', ...(payload.source_reason_codes || [])])];
		assert(payload.source_reason_codes.length <= 12, 'The developer marker does not truncate feedback metadata.');
		developerFeedback.push(payload);
		await route.continue({ postData: JSON.stringify(payload) });
	});
	page.on('console', (message) => {
		if (message.type() === 'error') consoleErrors.push(message.text());
	});
	page.on('pageerror', (error) => consoleErrors.push(error.message || String(error)));
	page.on('requestfailed', (request) => {
		const code = request.failure()?.errorText || 'request_failed';
		if (code !== 'net::ERR_ABORTED') networkErrors.push({ status: 0, url: request.url(), code });
	});
	page.on('response', async (response) => {
		if (response.request().method() === 'POST' && new URL(response.url()).pathname.endsWith('/npcink-toolbox/v1/agent-feedback')) {
			const receipt = await response.json().catch(() => ({}));
			feedbackReceipts.push({ status: response.status(), accepted: receipt.accepted_for_eval === true, event_id: receipt.feedback_event_id || '' });
		}
		if (response.status() >= 400) {
			const entry = { status: response.status(), url: response.url(), code: '' };
			networkErrors.push(entry);
			const data = await response.json().catch(() => ({}));
			entry.code = typeof data.code === 'string' ? data.code : '';
		}
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
			const editorBlocksBefore = await page.evaluate(editorBlockSnapshot);
			let editorContentBefore = await page.evaluate(() => {
				const selector = window.wp.data.select('core/editor');
				return selector && typeof selector.getEditedPostContent === 'function' ? selector.getEditedPostContent() : '';
			});
				const responsePromise = page.waitForResponse((response) => {
				const request = response.request();
				return response.url().includes('/wp-json/npcink-toolbox/v1/editor/content-support')
					&& String(request.postData() || '').includes('internal_links');
			}, { timeout: 45000 });
			const started = performance.now();
			let internalLinkResponse, internalLinkPayload;
			try {
				await page.getByRole('button', { name: /Run Site citation suggestions|运行 站内引用建议/i }).click({ timeout: 30000 });
				internalLinkResponse = await responsePromise;
				internalLinkPayload = await internalLinkResponse.json();
			} catch (error) {
				// A click failure must also consume the pending response wait rejection.
				responsePromise.catch(() => {});
				cloudObservationEmitted = true;
				console.log('INFO: internal_link_cloud_observation=' + JSON.stringify(recommendationCloudObservation({
					httpStatus: internalLinkResponse?.status(), elapsedMs: performance.now() - started,
					requestBody: internalLinkResponse?.request().postData(), errorName: error.name,
					phase: internalLinkResponse ? 'invalid_json' : 'request_failed',
				})));
				throw error;
			}
			const internalLinkSection = internalLinkPayload && internalLinkPayload.sections ? internalLinkPayload.sections.internal_links || {} : {};
			cloudObservationEmitted = true;
			console.log('INFO: internal_link_cloud_observation=' + JSON.stringify(recommendationCloudObservation({
				section: internalLinkSection, httpStatus: internalLinkResponse.status(),
				elapsedMs: performance.now() - started, requestBody: internalLinkResponse.request().postData(),
			})));

			if (requireFreshCloud && ['cloud_vector_evidence', 'no_cloud_evidence'].includes(internalLinkSection.retrieval_status)) {
				assert(['miss', 'bypass'].includes(internalLinkSection.source_knowledge?.cache_status), 'Fresh-Cloud acceptance rejects cache hits or unknown cache evidence.');
			}
					await page.waitForSelector('text=/Site citation suggestions|站内引用建议/', { timeout: 30000 });
					const adjustmentDisclosure = page.locator('.npcink-toolbox-editor-support__citation-adjustments');
					assert(await adjustmentDisclosure.count() === 1, 'Recommendation preferences are available in one low-frequency disclosure.');
				const internalLinkImpression = await waitForFeedbackAction(requests, 'internal_link_impression');
				assertMetadataOnlyFeedback(internalLinkImpression, 'Internal-link impression');
				assert((internalLinkImpression.source_reason_codes || []).some((code) => /^applicable_count_/.test(code)), 'Internal-link impression records a bounded applicable-count denominator.');
			assert(internalLinkResponse.status() >= 200 && internalLinkResponse.status() < 300, 'Internal-link editor request returns a successful HTTP status.');
			assert(internalLinkSection.direct_wordpress_write === false, 'Internal-link browser response disables direct WordPress writes.');
			assert(internalLinkSection.editor_transaction && internalLinkSection.editor_transaction.schema === 'current_article_multi_link_result.v1', 'Internal-link browser response exposes the current-article transaction contract.');

			const candidateCount = Array.isArray(internalLinkSection.recommendation_candidates) ? internalLinkSection.recommendation_candidates.length : 0;
            if (process.env.NPCINK_RECOMMENDATION_VERSION === 'v2') {
                const knowledge = internalLinkSection.source_knowledge || {};
                const quality = knowledge.result_grouping?.recommendation_quality || {};
                assert(knowledge.status === 'ready' && knowledge.retrieval_readiness?.status === 'ready', 'v2 acceptance requires healthy Cloud readiness, including empty results.');
                assert(quality.policy_version === 'reading_task_abstention.v2' && typeof quality.abstained === 'boolean', 'v2 acceptance carries the explicit qualification receipt.');
                assert(!quality.abstained || (candidateCount === 0 && internalLinkSection.fallback_used === false), 'Healthy abstention has zero candidates and no fallback backfill.');
                if (process.env.NPCINK_INTERNAL_LINK_EXPECT_ABSTAIN === '1') assert(quality.abstained && candidateCount === 0, 'The negative article abstains instead of returning candidates.');
            }

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
			const applicableRows = page.locator('.npcink-toolbox-editor-support__internal-link-item.is-applicable');
			const applicableCount = await applicableRows.count();
			const readiness = await page.evaluate((section) => {
				const helpers = window.NpcinkToolboxInternalLinkHelpers;
				const currentBlocks = window.wp.data.select('core/block-editor').getBlocks();
				const candidates = helpers.dedupeInternalLinkCandidates(section.recommendation_candidates.map((item) => ({
					id: item.id, targetUrl: item.target_url || item.target_ref.url || item.source_candidate_ref,
					targetPostId: Number(item.target_ref.post_id), targetStatus: item.target_ref.status,
					targetPostType: item.target_ref.post_type, anchorText: item.anchor_or_context,
					sourceMatch: item.source_match, candidateRelevance: item.candidate_relevance,
					candidateSource: item.candidate_source || section.candidate_source,
					retrievalStatus: section.retrieval_status, canApplyToEditor: item.can_apply_to_editor === true,
				}))).map((candidate, index) => Object.assign({}, candidate, { candidateRank: index + 1 })).filter((candidate) => candidate.candidateRelevance !== 'weak');
				return candidates.map((candidate, index) => {
					const state = helpers.internalLinkCandidateEligibility(candidate, currentBlocks, window.wp.richText);
					return { id: candidate.id, rank: candidate.candidateRank, ready: state.canApply, reasons: state.reasonCodes };
				});
			}, { ...internalLinkSection, recommendation_candidates: internalLinkSection.recommendation_candidates || [] });
			console.log(`INFO: internal_link_live_readiness=${JSON.stringify(readiness)}`);
			mkdirSync(env('SMOKE_ARTIFACT_DIR', 'tests/artifacts'), { recursive: true });
			await page.screenshot({ path: `${env('SMOKE_ARTIFACT_DIR', 'tests/artifacts')}/editor-internal-link-readiness-${activePostId}.png`, fullPage: true });
			for (const item of readiness) {
				const row = page.locator(`.npcink-toolbox-editor-support__internal-links [data-candidate-rank="${item.rank}"]`);
				if (await row.count() === 0) {
					assert(!item.ready, 'A hidden candidate is never silently applicable: ' + item.id);
					continue;
				}
				if (!await row.isVisible()) await row.locator('xpath=ancestor::details[1]').locator('summary').click();
				assert((await row.getAttribute('class')).includes('is-applicable') === item.ready, 'Visible readiness agrees with the current Apply checks: ' + item.id);
				if (!item.ready) assert(await row.getByRole('button', { name: /Add citation|添加引用/i }).count() === 0, 'Blocked candidates have no Add citation action: ' + item.id);
				if (item.reasons.includes('range_already_linked')) assert(/already contains a link|已有链接|已经包含链接/.test(await row.innerText()), 'Existing linked phrases show the concrete reason.');
			}
			const expectedBucket = await page.evaluate((count) => window.NpcinkToolboxInternalLinkHelpers.recommendationCountBucket('applicable_count', count), applicableCount);
			assert(internalLinkImpression.source_reason_codes.includes(expectedBucket), 'Impression readiness uses the same live denominator as the UI.');
			if (process.env.NPCINK_INTERNAL_LINK_LIVE_STATE_SMOKE === '1') {
				assert(readiness.some((item) => item.reasons.includes('range_already_linked')), 'The real article covers existing linked phrases.');
				const ready = readiness.find((item) => item.ready);
				assert(Boolean(ready), 'The real article also has an independently applicable phrase.');
				const retrievalCountBefore = requests.filter((request) => request.url.includes('/editor/content-support') && String(request.body).includes('internal_links')).length;
				const original = await page.evaluate(({ id, section }) => {
					const candidate = section.recommendation_candidates.find((item) => item.id === id);
					const match = candidate.source_match;
					const editor = window.wp.data.select('core/block-editor');
					const block = editor.getBlock(match.block_client_id);
					const h = window.NpcinkToolboxInternalLinkHelpers;
					const content = h.internalLinkBlockContent(block);
					const value = window.wp.richText.create({ html: content });
					const range = h.internalLinkMatchRange(value.text, match.matched_text, match.text_offset);
					const linked = window.wp.richText.applyFormat(value, { type: 'core/link', attributes: { url: 'https://example.test/existing-reference' } }, range.start, range.end);
					window.wp.data.dispatch('core/block-editor').updateBlockAttributes(block.clientId, { content: window.wp.richText.toHTMLString({ value: linked }) });
					return { clientId: block.clientId, content };
				}, { id: ready.id, section: internalLinkSection });
				await page.waitForFunction((rank) => document.querySelector(`[data-candidate-rank="${rank}"]`)?.classList.contains('is-reference-only'), ready.rank);
				assert(wpPostContent(activePostId) === databaseContentBefore, 'A native format-only edit changes readiness without persisting content.');
				await page.evaluate((snapshot) => window.wp.data.dispatch('core/block-editor').updateBlockAttributes(snapshot.clientId, { content: snapshot.content }), original);
				await page.waitForFunction((rank) => document.querySelector(`[data-candidate-rank="${rank}"]`)?.classList.contains('is-applicable'), ready.rank);
				await page.evaluate(({ original, raw }) => {
					const api = window.wp.richText;
					const h = window.NpcinkToolboxInternalLinkHelpers;
					const value = api.create({ html: original.content });
					const range = h.internalLinkMatchRange(value.text, raw.source_match.matched_text, raw.source_match.text_offset);
					const start = range.start >= 4 ? 0 : range.end;
					const end = Math.min(value.text.length, start + 4);
					if (end <= start || (start < range.end && end > range.start)) throw new Error('No independent existing phrase for the same-target check.');
					const linked = api.applyFormat(value, { type: 'core/link', attributes: { url: raw.target_url || raw.target_ref.url } }, start, end);
					window.wp.data.dispatch('core/block-editor').updateBlockAttributes(original.clientId, { content: api.toHTMLString({ value: linked }) });
				}, { original, raw: internalLinkSection.recommendation_candidates.find((item) => item.id === ready.id) });
				const changedRow = page.locator(`[data-candidate-rank="${ready.rank}"]`);
				await page.waitForFunction((rank) => document.querySelector(`[data-candidate-rank="${rank}"]`)?.classList.contains('is-reference-only'), ready.rank);
				assert(/already links to this target|已经链接|已链接|已有指向该目标/.test(await changedRow.innerText()), 'An existing link elsewhere to the same target blocks duplicate insertion.');
				await page.evaluate((snapshot) => window.wp.data.dispatch('core/block-editor').updateBlockAttributes(snapshot.clientId, { content: snapshot.content }), original);
				await page.waitForFunction((rank) => document.querySelector(`[data-candidate-rank="${rank}"]`)?.classList.contains('is-applicable'), ready.rank);
				assert(wpPostContent(activePostId) === databaseContentBefore, 'Both existing-link checks leave the real article database content unchanged.');
				assert(requests.filter((request) => request.url.includes('/editor/content-support') && String(request.body).includes('internal_links')).length === retrievalCountBefore, 'Live readiness does not fetch or reorder new recommendations.');
				editorContentBefore = await page.evaluate(() => window.wp.data.select('core/editor').getEditedPostContent());
				pass('Restoring an existing-link format restores readiness without fetching new recommendations.');
			}
			const copyButton = page.locator('.npcink-toolbox-editor-support__internal-link-item button').filter({ hasText: /Copy link|复制链接/ }).first();
				if (await copyButton.count()) {
					await copyButton.click();
					await page.waitForSelector('text=/Link copied|链接已复制/', { timeout: 10000 });
					const internalLinkCopyFeedback = await waitForFeedbackAction(requests, 'internal_link_copy');
					assertMetadataOnlyFeedback(internalLinkCopyFeedback, 'Internal-link copy');
					assert(Number.isInteger(internalLinkCopyFeedback.candidate_rank) && internalLinkCopyFeedback.candidate_rank >= 1 && internalLinkCopyFeedback.candidate_rank <= 8, 'Copied candidate retains its displayed position.');
					assert(internalLinkCopyFeedback.source_object_id === internalLinkImpression.source_object_id, 'Internal-link copy correlates to its recommendation impression session.');
					pass('A reviewed internal-link URL can be copied manually.');
			}

			let appliedCount = 0;
			let rejectedCount = 0;
			let undoPerformed = false;
			let nativeSavePerformed = false;
			let nativeSaveFeedback = null;
			if (applicableCount > 0) {
				assert(await applicableRows.first().getByRole('button', { name: /Add citation|添加引用/i }).count() === 1, 'Applicable rows expose one direct Add citation action below the candidate content.');
				await page.getByRole('button', { name: /Batch select|批量选择/i }).click();
				const checkboxLocator = page.locator('.npcink-toolbox-editor-support__internal-link-item.is-applicable input[type="checkbox"]');
				assert(await checkboxLocator.count() === applicableCount, 'Checkboxes appear only after entering explicit batch-selection mode.');
				const selectionCount = Math.min(2, applicableCount);
				for (let index = 0; index < selectionCount; index += 1) {
					await checkboxLocator.nth(index).check();
				}
				const editorContentAfterSelection = await page.evaluate(() => window.wp.data.select('core/editor').getEditedPostContent());
				assert(editorContentAfterSelection === editorContentBefore, 'Selecting internal-link suggestions does not mutate editor content.');
				assert(wpPostContent(activePostId) === databaseContentBefore, 'Selecting internal-link suggestions does not persist WordPress content.');
				const checkedRanks = await page.locator('.is-applicable:has(input:checked)').evaluateAll((rows) => rows.map((row) => Number(row.dataset.candidateRank)));
				assert(checkedRanks.length === selectionCount, 'The selected DOM rows identify every reviewed candidate.');
				const requestIndexBeforeApply = requests.length;
				await page.getByRole('button', { name: /应用 \d+ 条内链|Apply \d+ links/i }).click();
				await page.waitForSelector('text=/已在当前编辑器应用/', { timeout: 10000 });
				const resultText = await page.locator('.components-notice, .npcink-toolbox-editor-support__notice').filter({ hasText: /已在当前编辑器应用/ }).last().innerText();
				const counts = resultText.match(/应用\s*(\d+)\s*条，拒绝\s*(\d+)\s*条/);
				appliedCount = counts ? parseInt(counts[1], 10) : 0;
				rejectedCount = counts ? parseInt(counts[2], 10) : 0;
				const editorContentAfterApply = await page.evaluate(() => window.wp.data.select('core/editor').getEditedPostContent());
					assert(appliedCount > 0 && editorContentAfterApply !== editorContentBefore, 'Explicit Apply changes only the visible Gutenberg editor state.');
					for (const rank of checkedRanks) {
						const expected = readiness.find((item) => item.rank === rank);
						assert(Boolean(expected && expected.ready), 'Only current ready candidates enter Apply.');
					}

					const internalLinkApplyFeedback = await waitForFeedbackAction(requests, 'internal_link_applied_to_editor');
					assert(internalLinkApplyFeedback.source_object_id === internalLinkImpression.source_object_id, 'Internal-link Apply correlates to its recommendation impression session.');
					const rankDeadline = Date.now() + 5000;
					while (agentFeedbackRequests(requests).filter((request) => JSON.parse(request.body || '{}').source_action_id === 'internal_link_applied_to_editor').length < appliedCount && Date.now() < rankDeadline) {
						await new Promise((resolve) => setTimeout(resolve, 100));
					}
					const appliedRankEvents = agentFeedbackRequests(requests).map((request) => JSON.parse(request.body || '{}')).filter((payload) => payload.source_action_id === 'internal_link_applied_to_editor');
					assert(new Set(appliedRankEvents.map((payload) => payload.candidate_rank)).size === appliedCount, 'Every applied candidate retains its distinct displayed position.');
					const appliedRanks = appliedRankEvents.map((event) => event.candidate_rank);
					assert(appliedRanks.every((rank) => checkedRanks.includes(rank)), 'Applied metadata ranks retain their original candidate identities.');
					await page.waitForFunction((ranks) => ranks.every((rank) => document.querySelector(`[data-candidate-rank="${rank}"]`)?.classList.contains('is-reference-only')), appliedRanks);
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
					assert(nativeSaveFeedback.source_reason_codes.some((code) => /^candidate_rank_/.test(code)), 'Native save retains bounded applied candidate positions.');
					assert(nativeSaveFeedback.source_object_type === 'recommendation_session', 'Native-save confirmation keeps recommendation-session identity.');
					assert(nativeSaveFeedback.source_object_id === internalLinkImpression.source_object_id, 'Native-save confirmation correlates to the original recommendation impression session.');
					const databaseContentAfterSave = wpPostContent(activePostId);
					assert(databaseContentAfterSave !== databaseContentBefore && /<a\s/i.test(databaseContentAfterSave), 'WordPress post_content changes only after the explicit native save.');
					assert(databaseContentAfterSave === editorContentAfterApply, 'Native save persists the complete reviewed editor content exactly.');
					nativeSavePerformed = true;
				} else {
					await page.getByRole('button', { name: /撤销本次内链应用|Undo current internal-link application/i }).click();
					await page.waitForSelector('text=/本次应用的内链已从当前编辑器撤销/', { timeout: 10000 });
					const editorContentAfterUndo = await page.evaluate(() => window.wp.data.select('core/editor').getEditedPostContent());
					const undoComparison = await page.evaluate(({ before, after, blocksBefore }) => ({
						blocks_equal: window.wp.blocks.serialize(window.wp.data.select('core/block-editor').getBlocks()) === blocksBefore.serialized,
						raw_equal: before === after,
						canonical_equal: window.wp.blocks.serialize(window.wp.blocks.parse(before)) === window.wp.blocks.serialize(window.wp.blocks.parse(after)),
					}), { before: editorContentBefore, after: editorContentAfterUndo, blocksBefore: editorBlocksBefore });
					const blocksAfterUndo = await page.evaluate(editorBlockSnapshot);
					undoComparison.identities_equal = JSON.stringify(blocksAfterUndo.identities) === JSON.stringify(editorBlocksBefore.identities);
					console.log(`INFO: internal_link_undo_comparison=${JSON.stringify(undoComparison)}`);
					assert(undoComparison.blocks_equal && undoComparison.identities_equal, 'Explicit Undo restores the visible Gutenberg editor state.');
					await page.waitForFunction((ranks) => ranks.every((rank) => document.querySelector(`[data-candidate-rank="${rank}"]`)?.classList.contains('is-applicable')), checkedRanks);
					assert(wpPostContent(activePostId) === databaseContentBefore, 'Explicit Undo does not persist post_content.');
					undoPerformed = true;
				}
			} else {
				if (requireInternalLinkApply) {
					fail('The content-rich article did not return an exact source match required for Apply.');
				}
				pass('Review-only acceptance allows no Apply when the response has no exact source match.');
				const emptyStateCount = await page.locator('text=/No internal link candidates returned|没有安全、具体且可匹配的锚文本|只能复制链接或打开文章检查|暂未找到|暂无可直接插入项/').count();
				assert(emptyStateCount > 0 || candidateCount === 0, 'Weak or empty internal-link results show a bounded Chinese empty/review-only state.');
				assert(await page.getByRole('button', { name: /应用所选内链|Apply selected internal links/i }).isDisabled().catch(() => true), 'Batch Apply stays unavailable without an exact source match.');
			}

			const artifactDir = env('SMOKE_ARTIFACT_DIR', 'tests/artifacts');
			mkdirSync(artifactDir, { recursive: true });
			const screenshotPath = `${artifactDir}/editor-internal-link-batch-${activePostId}.png`;
			await page.screenshot({ path: screenshotPath, fullPage: true });
			const toolboxNetworkErrors = networkErrors.filter((entry) => /npcink-toolbox|npcink-cloud-addon/i.test(entry.url));
			const cloudUnavailable = internalLinkSection.retrieval_status === 'cloud_unavailable'
				|| internalLinkSection.candidate_source === 'cloud_unavailable';
			if (allowUnconfiguredCloud && cloudUnavailable && applicableCount === 0) {
				const expectedErrors = toolboxNetworkErrors.filter((entry) => entry.status === 400
					&& entry.code === 'cloud_runtime_unconfigured'
					&& new URL(entry.url).pathname === '/wp-json/npcink-toolbox/v1/agent-feedback');
				assert(expectedErrors.length === toolboxNetworkErrors.length, 'Unconfigured-Cloud acceptance permits only the exact feedback transport configuration error.');
				assert(wpPostContent(activePostId) === databaseContentBefore, 'Cloud-unavailable review leaves WordPress content unchanged.');
				pass('Fail-closed acceptance only: Cloud retrieval, feedback ingestion, and editor Apply remain unverified.');
			} else {
				assert(!cloudUnavailable, 'Configured-Cloud acceptance requires available Cloud retrieval; HTTP 200 alone cannot pass.');
				assert(toolboxNetworkErrors.length === 0, 'Internal-link browser flow has no Toolbox or Cloud Addon HTTP errors.');
			}
			if (!cloudUnavailable) {
				const receiptDeadline = Date.now() + 10000;
				while (feedbackReceipts.length < developerFeedback.length && Date.now() < receiptDeadline) await new Promise((resolve) => setTimeout(resolve, 100));
				assert(developerFeedback.length > 0 && feedbackReceipts.length === developerFeedback.length, 'Every developer-marked recommendation event has a Cloud receipt.');
				assert(feedbackReceipts.every((receipt) => receipt.status === 200 && receipt.accepted && receipt.event_id), 'Cloud accepts every metadata-only event and returns its identity.');
				assert(developerFeedback.every((event) => event.source_reason_codes.includes('anchor_eligibility_v3')), 'Current internal-link events retain the bounded eligibility version.');
			}
			console.log(`INFO: internal_link_browser_receipt=${JSON.stringify({
				post_id: parseInt(activePostId, 10),
				http_status: internalLinkResponse.status(),
				retrieval_status: internalLinkSection.retrieval_status || '',
				candidate_source: internalLinkSection.candidate_source || '',
				candidate_count: candidateCount,
				feedback_events: developerFeedback.map((payload) => ({ action: payload.source_action_id, session_id: payload.source_object_id, ranking_version: payload.ranking_version, candidate_rank: payload.candidate_rank, score_bucket: payload.score_bucket, retrieval_source: payload.retrieval_source, rerank_applied: payload.rerank_applied, reason_codes: payload.source_reason_codes })),
				feedback_receipts: feedbackReceipts,
				anchors: candidateReview.map((candidate) => ({ anchor_text: candidate.anchor_text, has_source_match: candidate.has_source_match })),
				cloud_anchor_evidence: cloudAnchorEvidence,
				applicable_count: applicableCount,
				live_readiness: readiness.map((item) => ({ rank: item.rank, ready: item.ready, reason_codes: item.reasons })),
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
		if (internalLinkBatchSmoke && !cloudObservationEmitted) {
			console.log('INFO: internal_link_cloud_observation=' + JSON.stringify(recommendationCloudObservation({ phase: 'editor_setup_failed', errorName: error.name })));
		}
		await captureDiagnostics(page, requests, error);
		throw error;
	}
} finally {
	freshCloudProbe?.cleanup();
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
