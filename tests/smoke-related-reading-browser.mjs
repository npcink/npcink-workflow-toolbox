#!/usr/bin/env node
// Environment-dependent real Gutenberg smoke. Retrieval is a deterministic
// fixture; target REST reads, editing and native save use real WordPress.
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { existsSync, mkdirSync, writeFileSync, unlinkSync } from 'node:fs';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const { chromium } = require(require.resolve('playwright', { paths: String(process.env.NODE_PATH || '').split(':').filter(Boolean) }));
assert.equal(process.env.NPCINK_RELATED_READING_SANDBOX, '1', 'Explicit disposable-sandbox acknowledgement is required');
const root = process.env.WP_PATH;
assert.ok(root, 'WP_PATH must identify a disposable local test installation');
const base = process.env.WP_BASE_URL || 'http://127.0.0.1:8936';
const php = process.env.WP_CLI_PHP || 'php';
function wp(...args) {
 return execFileSync(php, ['-d', 'display_errors=0', '-d', 'error_reporting=8191', process.env.WP_CLI_BIN || '/opt/homebrew/bin/wp', '--path=' + root, '--no-color', ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
}
assert.equal(wp('option', 'get', 'home').replace(/\/$/, ''), base.replace(/\/$/, ''), 'CLI database and browser URL must identify the same isolated site');
const fixtureIds = [];
const token = randomBytes(24).toString('hex');
const loginFile = `${root}/reading-login-${token}.php`;
const artifacts = process.env.SMOKE_ARTIFACT_DIR || 'tests/artifacts';
mkdirSync(artifacts, { recursive: true });
let browser;
let page;
try {
 const draftId = Number(wp('post', 'create', '--post_type=post', '--post_status=draft', '--post_title=Reading smoke draft', '--post_content=<!-- wp:paragraph --><p>Original draft body for the related reading test.</p><!-- /wp:paragraph -->', '--porcelain'));
 fixtureIds.push(draftId);
 const targetId = Number(wp('post', 'create', '--post_type=post', '--post_status=publish', '--post_title=Related reading fixture guide', '--post_content=Public guide fixture.', '--porcelain'));
 fixtureIds.push(targetId);
 const targetUrl = wp('eval', `echo get_permalink(${targetId});`);
 writeFileSync(loginFile, `<?php if (!hash_equals('${token}', (string) ($_GET['token'] ?? ''))) { http_response_code(403); exit; } require __DIR__.'/wp-load.php'; $users=get_users(array('role'=>'administrator','number'=>1)); wp_set_current_user($users[0]->ID); wp_set_auth_cookie($users[0]->ID); wp_safe_redirect(admin_url('post.php?post=${draftId}&action=edit')); exit;`);
 browser = await chromium.launch({ headless: true, ...(existsSync('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome') ? { executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' } : {}) });
 page = await browser.newPage();
 page.setDefaultTimeout(15000);
 const errors = [];
 page.on('pageerror', (error) => errors.push(error.message));
 const requests = [];
 await page.route('**/*', async (route) => {
  const request = route.request();
  const url = decodeURIComponent(request.url());
  if (url.includes('/editor/content-support') && request.method() === 'POST') {
   const payload = request.postDataJSON(); requests.push(payload);
   const sections = payload.intent === 'related_content' ? { related_content: { artifact_type: 'related_reading_candidates.v1', status: 'ready', candidate_source: 'cloud_vector', results: [{ id: String(targetId), post_id: targetId, title: 'Related reading fixture guide', target_url: targetUrl, candidate_relevance: 'strong', target_ref: { post_id: targetId, post_type: 'post', status: 'publish' } }] } } : {};
   await route.fulfill({ json: { artifact_type: 'editor_content_support_flow', intent: payload.intent, post_context: { post_id: draftId }, sections, write_posture: 'suggestion_only', direct_wordpress_write: false } });
   return;
  }
  await route.continue();
 });
 await page.goto(`${base}/${loginFile.split('/').pop()}?token=${token}`, { waitUntil: 'domcontentloaded' });
 await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId());
 await page.evaluate(() => {
  wp.data.dispatch('core/editor').disablePublishSidebar?.();
  wp.data.dispatch('core/preferences').set('core/edit-post', 'welcomeGuide', false);
  wp.data.dispatch('core/edit-post').openGeneralSidebar('npcink-toolbox-editor-content-support/npcink-content-support-sidebar');
 });
 const closeGuide = page.getByRole('button', { name: 'Close', exact: true });
 if (await closeGuide.isVisible().catch(() => false)) await closeGuide.click();
 await page.getByRole('button', { name: 'Related reading', exact: true }).click();
 await page.getByRole('checkbox', { name: 'Related reading fixture guide', exact: true }).check();
 const original = wp('post', 'get', String(draftId), '--field=post_content');
 const groupCount = () => page.evaluate(() => wp.data.select('core/block-editor').getBlocks().filter((b) => b.name === 'core/group').length);
 // Locked root and newly unpublished targets fail without editor mutations.
 await page.evaluate(() => wp.data.dispatch('core/block-editor').updateSettings({ templateLock: 'all' }));
 await page.getByRole('button', { name: 'Insert selected reading', exact: true }).click();
 await page.getByText('Could not insert the reading list. Check for existing links, changed articles or editor restrictions, then refresh recommendations.', { exact: true }).first().waitFor();
 assert.equal(await groupCount(), 0);
 await page.evaluate(() => wp.data.dispatch('core/block-editor').updateSettings({ templateLock: false }));
 wp('post', 'update', String(targetId), '--post_status=draft');
 const unpublishedRead = page.waitForResponse((response) => decodeURIComponent(response.url()).includes('/wp/v2/posts/' + targetId) && response.request().method() === 'GET');
 await page.getByRole('button', { name: 'Insert selected reading', exact: true }).click();
 assert.equal((await (await unpublishedRead).json()).status, 'draft', 'the fresh REST read sees the unpublished target');
 await page.waitForFunction(() => [...document.querySelectorAll('button')].some((b) => b.textContent === 'Insert selected reading' && !b.disabled));
 assert.equal(await groupCount(), 0);
 wp('post', 'update', String(targetId), '--post_status=publish');
 await page.getByRole('button', { name: 'Insert selected reading', exact: true }).click();
 await page.getByText('Reading list added to the editor. Save normally when you are ready.', { exact: true }).first().waitFor();
 assert.equal(await groupCount(), 1);
 assert.equal(wp('post', 'get', String(draftId), '--field=post_content'), original, 'insertion does not persist database content');
 await page.getByRole('button', { name: 'Undo reading list', exact: true }).click();
 assert.equal(await groupCount(), 0, 'unchanged list Undo removes only the added group');
 await page.getByRole('checkbox', { name: 'Related reading fixture guide', exact: true }).check();
 await page.getByRole('button', { name: 'Insert selected reading', exact: true }).click();
 await page.getByText('Reading list added to the editor. Save normally when you are ready.', { exact: true }).first().waitFor();
 // A second insertion of the same target is rejected using the live blocks.
 await page.getByRole('checkbox', { name: 'Related reading fixture guide', exact: true }).check();
 await page.getByRole('button', { name: 'Insert selected reading', exact: true }).click();
 await page.getByText('Could not insert the reading list. Check for existing links, changed articles or editor restrictions, then refresh recommendations.', { exact: true }).first().waitFor();
 assert.equal(await groupCount(), 1);
 // Protect the author's edits from plugin Undo.
 await page.evaluate(() => {
  const group = wp.data.select('core/block-editor').getBlocks().find((b) => b.name === 'core/group');
  wp.data.dispatch('core/block-editor').updateBlockAttributes(group.innerBlocks[0].clientId, { content: 'Author edited reading heading' });
 });
 await page.getByRole('button', { name: 'Undo reading list', exact: true }).click();
 await page.getByText('The reading list was changed or is locked. Use the editor Undo control to preserve your edits.', { exact: true }).first().waitFor();
 assert.equal(await groupCount(), 1);
 assert.equal(wp('post', 'get', String(draftId), '--field=post_content'), original);
 await page.screenshot({ path: `${artifacts}/related-reading-editor.png`, fullPage: true });
 // Invoke WordPress's own save transaction, the same store action used by Save draft.
 await page.evaluate(async () => { await wp.data.dispatch('core/editor').savePost(); });
 const saved = wp('post', 'get', String(draftId), '--field=post_content');
 assert.ok(saved.includes('Author edited reading heading') && saved.includes('Related reading fixture guide') && saved.includes('Original draft body'));
 assert.equal(wp('post', 'get', String(draftId), '--field=post_status'), 'draft');
 const listValidity = await page.evaluate(() => wp.blocks.parse(wp.data.select('core/editor').getEditedPostContent()).every((b) => b.isValid));
 assert.equal(listValidity, true);
 assert.equal(requests.find((r) => r.intent === 'related_content').context_scope, 'full_article');
 assert.deepEqual(errors, []);
 writeFileSync(`${artifacts}/related-reading-browser.json`, JSON.stringify({ passed: true, wordpress: wp('core', 'version'), retrieval: 'deterministic_fixture_not_quality_evidence', checks: ['real_checkbox_selection', 'native_group_insertion', 'database_unchanged_before_save', 'unchanged_group_undo', 'duplicate_rejected', 'locked_root_rejected', 'unpublished_target_rejected', 'author_edits_preserved', 'native_save_persists_draft', 'valid_gutenberg_serialization'], pageErrors: errors }, null, 2));
 console.log('PASS: real Gutenberg reading-list insertion, Undo, deduplication, author edit protection and native save');
} catch (error) {
 if (page) { console.error((await page.locator('body').innerText()).slice(-10000)); await page.screenshot({ path: `${artifacts}/related-reading-failure.png`, fullPage: true }).catch(() => {}); }
 throw error;
} finally {
 if (browser) await browser.close();
 if (existsSync(loginFile)) unlinkSync(loginFile);
 for (const id of fixtureIds.reverse()) wp('post', 'delete', String(id), '--force');
}
