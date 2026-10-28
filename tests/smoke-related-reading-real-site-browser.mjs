#!/usr/bin/env node
// Real local-site Cloud retrieval; writes only one disposable draft. No response fixtures.
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { randomBytes, createHash } from 'node:crypto';
import { existsSync, mkdirSync, writeFileSync, unlinkSync } from 'node:fs';
import { createRequire } from 'node:module';
import { createFreshCloudProbe } from './fresh-cloud-probe.mjs';
const require = createRequire(import.meta.url);
const { chromium } = require(require.resolve('playwright', { paths: String(process.env.NODE_PATH || '').split(':').filter(Boolean) }));
const root = process.env.WP_PATH;
const base = process.env.WP_BASE_URL;
const sourceId = Number(process.env.SOURCE_POST_ID);
assert.ok(root && base && Number.isInteger(sourceId) && sourceId > 0, 'WP_PATH, WP_BASE_URL and positive SOURCE_POST_ID required');
assert.ok(new URL(base).hostname.endsWith('.local'), 'This acceptance harness is restricted to a local site');
function wp(...args) {
 return execFileSync(process.env.WP_CLI_PHP || 'php', ['-d', 'display_errors=0', '-d', 'error_reporting=8191', '-d', `mysqli.default_socket=${process.env.WP_DB_SOCKET || ''}`, process.env.WP_CLI_BIN || '/opt/homebrew/bin/wp', '--path=' + root, '--no-color', ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
}
assert.equal(wp('option', 'get', 'home').replace(/\/$/, ''), base.replace(/\/$/, ''), 'CLI and browser target the same local site');
const source = JSON.parse(wp('post', 'get', String(sourceId), '--format=json'));
assert.equal(source.post_status, 'publish');
const sourceHash = createHash('sha256').update(source.post_content).digest('hex');
const artifactDir = process.env.SMOKE_ARTIFACT_DIR || 'tests/artifacts';
mkdirSync(artifactDir, { recursive: true });
const suffix = randomBytes(10).toString('hex');
const loginFile = `${root}/npcink-reading-real-${suffix}.php`;
const token = randomBytes(24).toString('hex');
let draftId = 0;
let browser;
let page;
let probe;
const evidence = { source_post_id: sourceId, source_title: source.post_title, retrieval: 'real_cloud_no_response_mock', events: [], feedback_receipts: [], page_errors: [], checks: [] };
const feedbackErrors = [];
try {
 // Source text is read by PHP, never interpolated into shell code or published.
 draftId = Number(wp('eval', `$p=get_post(${sourceId}); echo wp_insert_post(wp_slash(array('post_type'=>'post','post_status'=>'draft','post_author'=>1,'post_title'=>$p->post_title,'post_excerpt'=>$p->post_excerpt,'post_content'=>$p->post_content)));`));
 assert.ok(draftId > 0);
 evidence.draft_post_id = draftId;
 writeFileSync(loginFile, `<?php if (!hash_equals('${token}', (string) ($_GET['token'] ?? ''))) { http_response_code(403); exit; } require __DIR__.'/wp-load.php'; $users=get_users(array('role'=>'administrator','number'=>1)); wp_set_current_user($users[0]->ID); wp_set_auth_cookie($users[0]->ID); wp_safe_redirect(admin_url('post.php?post=${draftId}&action=edit')); exit;`, { mode: 0o600, flag: 'wx' });
 probe = createFreshCloudProbe(root, base);
 browser = await chromium.launch({ headless: true, ...(existsSync('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome') ? { executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' } : {}) });
 const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 1000 } });
 page = await context.newPage();
 page.setDefaultTimeout(20000);
 page.on('pageerror', (error) => evidence.page_errors.push(error.message));
 await page.route('**/npcink-toolbox/v1/editor/content-support', (route) => {
  if (route.request().postDataJSON()?.intent !== 'related_content') return route.continue();
  return route.continue({ headers: { ...route.request().headers(), ...probe.headers } });
 });
 await page.route('**/npcink-toolbox/v1/agent-feedback', (route) => {
  const payload = route.request().postDataJSON();
  if (!/^(?:related_content|internal_link)_/.test(payload.source_action_id || '')) return route.continue();
  payload.source_reason_codes = [...new Set(['developer_smoke', ...(payload.source_reason_codes || [])])];
  if (payload.source_reason_codes.length > 12) {
   feedbackErrors.push('developer marker would exceed the reason-code bound');
   return route.abort();
  }
  evidence.events.push({ action: payload.source_action_id, reason_codes: payload.source_reason_codes });
  return route.continue({ postData: JSON.stringify(payload) });
 });
 page.on('response', async (response) => {
  if (response.url().endsWith('/agent-feedback') && /^(?:related_content|internal_link)_/.test(response.request().postDataJSON()?.source_action_id || '')) {
   const data = await response.json().catch(() => ({}));
   evidence.feedback_receipts.push({ http_status: response.status(), accepted: data.accepted_for_eval === true, event_id: data.feedback_event_id || '' });
  }
 });
 await page.goto(`${base}/${loginFile.split('/').pop()}?token=${token}`, { waitUntil: 'domcontentloaded' });
 await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId());
 const closeGuide = page.getByRole('button', { name: /^(Close|关闭)$/ });
 if (await closeGuide.isVisible().catch(() => false)) await closeGuide.click();
 await page.evaluate(() => wp.data.dispatch('core/edit-post').openGeneralSidebar('npcink-toolbox-editor-content-support/npcink-content-support-sidebar'));
 await page.getByRole('button', { name: /^(Body links|正文内链)$/ }).waitFor();
 await page.screenshot({ path: `${artifactDir}/related-reading-real-menu.png`, fullPage: true });
 const started = performance.now();
 const pending = page.waitForResponse((r) => r.url().includes('/editor/content-support') && r.request().postDataJSON()?.intent === 'related_content', { timeout: 60000 });
 // A click/setup failure must not leave an unhandled response-wait rejection.
 pending.catch(() => {});
 await page.getByRole('button', { name: /^(Related reading|相关阅读)$/ }).click();
 const response = await pending;
 const payload = await response.json();
 evidence.http_status = response.status();
 evidence.elapsed_ms = Math.round(performance.now() - started);
 writeFileSync(`${artifactDir}/related-reading-real-response.json`, JSON.stringify(payload, null, 2));
 assert.equal(response.status(), 200);
 const section = payload.sections.related_content;
 evidence.cache_status = section.cache_status;
 evidence.ranking_version = section.ranking_version;
 evidence.candidates = (section.results || []).map((c) => ({ id: c.post_id, title: c.title, relevance: c.candidate_relevance, url: c.target_url }));
 assert.equal(section.status, 'ready');
 assert.equal(section.candidate_source, 'cloud_vector');
 assert.ok(['miss', 'bypass'].includes(section.cache_status), 'fresh Cloud retrieval is required');
 assert.ok(section.results.length > 0 && section.results.length <= 8);
 const selected = await page.evaluate(({ section, draftId, sourceId }) => {
  const candidates = section.results.map((c) => ({ id: String(c.post_id), title: c.title, targetUrl: c.target_url, targetPostId: c.post_id, targetStatus: c.target_ref.status, targetPostType: c.target_ref.post_type, candidateRelevance: c.candidate_relevance }));
  const blocks = wp.data.select('core/block-editor').getBlocks();
  return candidates.filter((c) => c.targetPostId !== sourceId && !window.NpcinkToolboxRelatedReading.relatedReadingSelection(candidates, [c.id], blocks, draftId, draftId, wp.richText).reason).slice(0, 2);
 }, { section, draftId, sourceId });
 assert.ok(selected.length, 'at least one real non-self, not-already-linked candidate is selectable');
 evidence.selected_ids = selected.map((c) => c.targetPostId);
 const boxes = page.getByRole('checkbox');
 async function selectItems() {
  for (const item of selected) await boxes.nth(section.results.findIndex((c) => String(c.post_id) === item.id)).check();
 }
 const before = wp('post', 'get', String(draftId), '--field=post_content');
 const countGroups = () => page.evaluate(() => wp.data.select('core/block-editor').getBlocks().filter((b) => b.name === 'core/group').length);
 const beforeGroups = await countGroups();
 const notice = page.locator('.components-notice__content');
 await selectItems();
 await page.getByRole('button', { name: /^(Insert selected reading|插入所选相关阅读)$/ }).click();
 await notice.filter({ hasText: /Reading list added|相关阅读列表已加入/ }).first().waitFor();
 assert.equal(await countGroups(), beforeGroups + 1);
 assert.equal(wp('post', 'get', String(draftId), '--field=post_content'), before);
 evidence.checks.push('real_candidates_selected', 'insert_only_visible_editor', 'database_unchanged_before_save');
 await page.screenshot({ path: `${artifactDir}/related-reading-real-inserted.png`, fullPage: true });
 await page.getByRole('button', { name: /^(Undo reading list|撤销相关阅读列表)$/ }).click();
 assert.equal(await countGroups(), beforeGroups);
 assert.equal(wp('post', 'get', String(draftId), '--field=post_content'), before);
 evidence.checks.push('undo_restores_group_count_without_saving');
 await selectItems();
 await page.getByRole('button', { name: /^(Insert selected reading|插入所选相关阅读)$/ }).click();
 await notice.filter({ hasText: /Reading list added|相关阅读列表已加入/ }).first().waitFor();
 const editorContent = await page.evaluate(() => wp.data.select('core/editor').getEditedPostContent());
 await page.evaluate(async () => { await wp.data.dispatch('core/editor').savePost(); });
 assert.equal(wp('post', 'get', String(draftId), '--field=post_content'), editorContent);
 assert.equal(wp('post', 'get', String(draftId), '--field=post_status'), 'draft');
 for (const item of selected) assert.ok(editorContent.includes(item.targetUrl.replace(/&/g, '&amp;')));
 evidence.checks.push('native_save_persists_complete_draft_only');
 await page.reload({ waitUntil: 'domcontentloaded' });
 await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId());
 const reloaded = await page.evaluate(() => wp.blocks.serialize(wp.data.select('core/block-editor').getBlocks()));
 const savedCanonical = await page.evaluate((content) => wp.blocks.serialize(wp.blocks.parse(content)), editorContent);
 assert.equal(reloaded, savedCanonical);
 evidence.checks.push('reload_preserves_saved_blocks');
 const feedbackDeadline = Date.now() + 10000;
 while (evidence.feedback_receipts.length < evidence.events.length && Date.now() < feedbackDeadline) await new Promise((resolve) => setTimeout(resolve, 100));
 assert.deepEqual(feedbackErrors, []);
 assert.ok(evidence.events.length > 0 && evidence.events.length === evidence.feedback_receipts.length);
 assert.ok(evidence.feedback_receipts.every((receipt) => receipt.http_status === 200 && receipt.accepted && receipt.event_id));
 evidence.checks.push('developer_feedback_accepted');
 assert.equal(createHash('sha256').update(JSON.parse(wp('post', 'get', String(sourceId), '--format=json')).post_content).digest('hex'), sourceHash);
 evidence.checks.push('published_source_unchanged');
 assert.deepEqual(evidence.page_errors, []);
 evidence.passed = true;
 console.log(JSON.stringify({ passed: true, candidates: section.results.length, selected: selected.length, elapsed_ms: evidence.elapsed_ms, checks: evidence.checks }));
} catch (error) {
 evidence.passed = false;
 evidence.failure = error.message;
 if (page) { console.error((await page.locator('body').innerText().catch(() => '')).slice(-7000)); await page.screenshot({ path: `${artifactDir}/related-reading-real-failure.png`, fullPage: true }).catch(() => {}); }
 throw error;
} finally {
 const cleanupErrors = [];
 for (const cleanup of [
  async () => { if (browser) await browser.close(); },
  () => probe?.cleanup(),
  () => { if (existsSync(loginFile)) unlinkSync(loginFile); },
  () => { if (draftId) { wp('post', 'delete', String(draftId), '--force'); evidence.draft_removed = true; } },
 ]) { try { await cleanup(); } catch (error) { cleanupErrors.push(error.message); } }
 if (cleanupErrors.length) { evidence.passed = false; evidence.cleanup_errors = cleanupErrors; }
 writeFileSync(`${artifactDir}/related-reading-real-receipt.json`, JSON.stringify(evidence, null, 2));
 if (cleanupErrors.length) throw new Error('Acceptance cleanup failed: ' + cleanupErrors.join('; '));
}
