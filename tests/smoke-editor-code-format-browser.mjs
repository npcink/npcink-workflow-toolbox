import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { createRequire } from 'node:module';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

// Native WordPress scripts in an isolated browser, with no WordPress database or real REST calls.
const require = createRequire(import.meta.url);
const { chromium } = require(require.resolve('playwright', { paths: [process.env.NODE_PATH || process.cwd()] }));
assert.ok(process.env.WP_PATH, 'Set WP_PATH to a WordPress source root.');
const includes = join(process.env.WP_PATH, 'wp-includes');
const root = fileURLToPath(new URL('..', import.meta.url));
const manifest = JSON.parse(execFileSync('php', ['-r', 'echo json_encode(require $argv[1]);', join(includes, 'assets/script-loader-packages.php')]));
const browser = await chromium.launch({ channel: process.env.BROWSER_CHANNEL || undefined });
try {
	const page = await browser.newPage({ viewport: { width: 390, height: 760 } });
	const errors = [];
	page.on('pageerror', (error) => errors.push(error.message));
	await page.route('http://code-format-fixture.test/**', (route) => route.fulfill({ contentType: 'text/html', body: '<main id="sidebar" style="width:248px"></main>' }));
	await page.goto('http://code-format-fixture.test/');
	const loaded = new Set();
	async function load(id) {
		if (loaded.has(id)) return;
		loaded.add(id);
		const file = `${id.replace(/^wp-/, '')}.js`;
		if (id === 'react-dom' || id === 'react-jsx-runtime') await load('react');
		for (const dependency of manifest[file]?.dependencies ?? []) await load(dependency);
		const path = [`js/dist/${file}`, `js/dist/vendor/${file}`, `js/dist/vendor/${id}.js`].map((name) => join(includes, name)).find(existsSync);
		assert.ok(path, `Missing WordPress dependency ${id}`);
		await page.addScriptTag({ path });
		if (id === 'lodash') await page.evaluate(() => { window.lodash = window._; });
	}
	await load('wp-block-library');
	await load('wp-components');
	await page.addStyleTag({ path: join(includes, 'css/dist/components/style.css') });
	await page.addStyleTag({ path: join(root, 'assets/editor-content-support.css') });
	const longString = 'a'.repeat(360);
	const source = `const z=(x)=>x<3&&x>0;\nconst s="${longString}";`;
	const candidate = `const z = (x) => x < 3 && x > 0;\nconst s = "${longString}";\n`;
	await page.evaluate(({ source, candidate }) => {
		wp.blockLibrary.registerCoreBlocks();
		const content = wp.richText.toHTMLString({ value: wp.richText.create({ text: source }), preserveWhiteSpace: true });
		const code = wp.blocks.createBlock('core/code', { content });
		const paragraph = wp.blocks.createBlock('core/paragraph', { content: 'Untouched' });
		wp.data.registerStore('core/editor', { reducer: (state = 42) => state, selectors: { getCurrentPostId: (state) => state } });
		wp.data.dispatch('core/block-editor').resetBlocks([code, paragraph]);
		wp.data.dispatch('core/block-editor').selectBlock(code.clientId);
		window.NpcinkToolboxEditorSupport = { codeFormatPilot: '1', restUrl: '/toolbox', nonce: 'fixture' };
		window.payloads = [];
		wp.apiFetch = async ({ data }) => {
			window.payloads.push(data);
			return { contract_version: 'code_format_candidate.v1', language: 'javascript', persisted: false, direct_wordpress_write: false, candidate, status: 'CHANGED' };
		};
	}, { source, candidate });
	await page.addScriptTag({ path: join(root, 'assets/editor-content-format.js') });
	await page.evaluate(() => {
		wp.element.createRoot(document.querySelector('#sidebar')).render(wp.element.createElement(window.NpcinkToolboxContentFormat.Control));
	});
	const first = () => page.getByRole('button', { name: 'Format this code block…' });
	await first().click();
	const dialog = page.getByRole('dialog', { name: 'Format this code block' });
	await dialog.waitFor();
	assert.equal(await dialog.evaluate((node) => node.contains(document.activeElement)), true);
	await page.keyboard.press('Escape');
	await dialog.waitFor({ state: 'hidden' });
	await page.waitForFunction(() => document.activeElement?.textContent === 'Format this code block…');
	await first().click();
	await page.getByRole('combobox', { name: 'Code language' }).selectOption('javascript');
	await page.getByRole('button', { name: 'Preview with Cloud' }).click();
	const apply = page.getByRole('button', { name: 'Apply to this code block' });
	await apply.waitFor();
	assert.equal(await page.getByLabel('After formatting').textContent(), candidate);
	for (const [label, text] of [['Before formatting', source], ['After formatting', candidate]]) {
		const comparison = page.getByLabel(label);
		assert.equal(await comparison.textContent(), text);
		assert.ok(await comparison.evaluate((node) => node.scrollWidth <= node.clientWidth));
		await comparison.focus();
		assert.equal(await comparison.evaluate((node) => document.activeElement === node), true);
	}
	const blocks = () => page.evaluate(() => wp.data.select('core/block-editor').getBlocks().map(({ attributes }) => wp.richText.create({ html: attributes.content }).text));
	assert.deepEqual(await blocks(), [source, 'Untouched']);
	if (process.env.CODE_FORMAT_SCREENSHOT) await page.screenshot({ path: process.env.CODE_FORMAT_SCREENSHOT });
	await apply.click();
	await page.getByText('Code replaced in the editor.', { exact: false }).waitFor();
	assert.deepEqual(await blocks(), [candidate, 'Untouched']);
	assert.deepEqual(await page.evaluate(() => window.payloads), [{ intent: 'format_code_block', post_id: 42, language: 'javascript', code: source }]);
	assert.deepEqual(errors, []);
	console.log('PASS: native Gutenberg Code, markup-sensitive text, preview and single-block apply against fake REST.');
} finally { await browser.close(); }
