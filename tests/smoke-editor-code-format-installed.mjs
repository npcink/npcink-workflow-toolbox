import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';

// This smoke signs into a disposable WordPress site. Do not point it at shared content.
const require = createRequire(import.meta.url);
const { chromium } = require(require.resolve('playwright', { paths: [process.env.NODE_PATH || process.cwd()] }));
const base = new URL(process.env.CODE_FORMAT_TEST_URL);
const postId = Number(process.env.CODE_FORMAT_TEST_POST_ID);
assert.ok(['127.0.0.1', 'localhost'].includes(base.hostname));
assert.ok(Number.isSafeInteger(postId) && postId > 0);
const user = process.env.CODE_FORMAT_TEST_USER;
const password = process.env.CODE_FORMAT_TEST_PASSWORD;
assert.ok(user && password);
const sourceFile = process.env.CODE_FORMAT_TEST_SOURCE_FILE;
const source = sourceFile ? readFileSync(sourceFile, 'utf8') : 'const z=(x)=>x<3&&x>0;';
const language = process.env.CODE_FORMAT_TEST_LANGUAGE || 'javascript';
assert.ok(['javascript', 'css', 'json'].includes(language));
const browser = await chromium.launch({ channel: process.env.BROWSER_CHANNEL || undefined });
try {
	const page = await browser.newPage({ viewport: { width: 1280, height: 850 } });
	page.setDefaultTimeout(12000);
	const codeResponses = [];
	page.on('response', (response) => {
		if (response.request().method() === 'POST' && response.url().includes('/editor/content-support')) {
			const data = response.request().postDataJSON();
			if (data.intent === 'format_code_block') codeResponses.push(response);
		}
	});
	await page.goto(new URL('/wp-login.php', base).href);
	await page.locator('#user_login').fill(user);
	await page.locator('#user_pass').fill(password);
	await Promise.all([page.waitForURL('**/wp-admin/**'), page.locator('#wp-submit').click()]);
	await page.goto(new URL(`/wp-admin/post.php?post=${postId}&action=edit`, base).href);
	await page.waitForFunction(() => window.wp?.data?.select('core/block-editor')?.getBlocks()?.length === 2);
	await page.evaluate(() => {
		wp.data.dispatch('core/preferences').set('core/edit-post', 'welcomeGuide', false);
		const id = 'npcink-toolbox-editor-content-support/npcink-content-support-sidebar';
		const open = wp.data.dispatch('core/editor').openGeneralSidebar || wp.data.dispatch('core/edit-post').openGeneralSidebar;
		open(id);
	});
	const readState = () => page.evaluate(() => ({
		blocks: wp.data.select('core/block-editor').getBlocks().map(({ name, attributes }) => [name, wp.richText.create({ html: attributes.content }).text]),
		dirty: wp.data.select('core/editor').isEditedPostDirty(),
	}));
	const persisted = () => page.evaluate((id) => wp.apiFetch({ path: `/wp/v2/posts/${id}?context=edit&_fields=content` }).then((post) => post.content.raw), postId);
	const selectBlock = (index) => page.evaluate((i) => {
		const editor = wp.data.select('core/block-editor');
		wp.data.dispatch('core/block-editor').selectBlock(editor.getBlocks()[i].clientId);
	}, index);
	const original = await readState();
	assert.deepEqual(original.blocks, [['core/code', source], ['core/paragraph', 'Untouched adjacent paragraph']]);
	const saved = await persisted();
	const open = page.getByRole('button', { name: 'Format this code block…' });
	await selectBlock(1);
	await open.waitFor({ state: 'hidden' });
	await selectBlock(0);
	await open.click();
	await page.getByRole('combobox', { name: 'Code language' }).selectOption(language);
	await page.getByRole('button', { name: 'Preview with Cloud' }).click();
	await page.waitForFunction(() => !!document.querySelector('[aria-label="After formatting"]') || Array.from(document.querySelectorAll('[role="status"]')).some((node) => /code preview is unavailable/i.test(node.textContent)));
	assert.equal(codeResponses.length, 1);
	const response = codeResponses[0];
	assert.equal(response.status(), 200, await response.text());
	assert.ok(response.headers()['cache-control']?.split(',').some((directive) => directive.trim() === 'no-store'));
	assert.deepEqual(response.request().postDataJSON(), { intent: 'format_code_block', post_id: postId, language, code: source });
	const candidate = await response.json();
	assert.equal(candidate.persisted, false);
	assert.equal(candidate.status, 'CHANGED');
	assert.equal(candidate.language, language);
	const expected = sourceFile ? candidate.candidate : 'const z = (x) => x < 3 && x > 0;\n';
	assert.notEqual(expected, source);
	assert.equal(await page.getByLabel('Before formatting').textContent(), source);
	assert.equal(await page.getByLabel('After formatting').textContent(), expected);
	assert.deepEqual(await readState(), original);
	assert.equal(await persisted(), saved);
	if (process.env.CODE_FORMAT_SCREENSHOT) await page.screenshot({ path: process.env.CODE_FORMAT_SCREENSHOT });
	await page.getByRole('button', { name: 'Apply to this code block' }).click();
	await page.getByText('Code replaced in the editor.', { exact: false }).waitFor();
	assert.deepEqual(await readState(), { blocks: [['core/code', expected], original.blocks[1]], dirty: true });
	assert.equal(await persisted(), saved);
	await page.getByRole('button', { name: 'Undo', exact: true }).click();
	await page.waitForFunction(() => !wp.data.select('core/editor').isEditedPostDirty());
	assert.deepEqual(await readState(), original);
	assert.equal(await persisted(), saved);
	console.log('PASS: installed WordPress, signed Addon and Cloud preview, selected-block application, native Undo and no saved mutation.');
} finally { await browser.close(); }
