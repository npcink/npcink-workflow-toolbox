import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { mkdirSync, readFileSync } from 'node:fs';

for (const key of ['WP_PATH', 'WP_CLI_PHP', 'WP_CLI_BIN', 'WP_DB_SOCKET', 'ARTICLE_REVIEW_PYTHON', 'ARTICLE_REVIEW_ADDON_ROOT', 'ARTICLE_REVIEW_CLOUD_ROOT']) {
	assert.ok(process.env[key], `Set ${key} before running this optional Local browser acceptance script.`);
}
const require = createRequire(import.meta.url);
const { chromium } = require(require.resolve('playwright', process.env.NODE_PATH ? { paths: [process.env.NODE_PATH] } : undefined));
const root = process.env.WP_PATH;
const php = process.env.WP_CLI_PHP;
const socket = process.env.WP_DB_SOCKET;
function cli(args) {
	return execFileSync(php, ['-d', 'display_errors=stderr', '-d', `mysqli.default_socket=${socket}`, process.env.WP_CLI_BIN, `--path=${root}`, '--no-color', ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
}
const auth = JSON.parse(cli(['eval', '$u=get_users(array("role"=>"administrator","number"=>1))[0]; $e=time()+1800; $t=WP_Session_Tokens::get_instance($u->ID)->create($e); echo wp_json_encode(array("user"=>$u->ID,"token"=>$t,"cookie"=>wp_generate_auth_cookie($u->ID,$e,"logged_in",$t),"admin_cookie"=>wp_generate_auth_cookie($u->ID,$e,"auth",$t),"admin_name"=>AUTH_COOKIE,"name"=>LOGGED_IN_COOKIE,"url"=>home_url()));']));
const source = '<!-- wp:paragraph -->\n<p>合成原始草稿，不保存浏览器中的测试编辑。</p>\n<!-- /wp:paragraph -->';
const postId = Number(cli(['post', 'create', '--post_type=post', '--post_status=draft', '--post_title=Article language disposable smoke', `--post_content=${source}`, '--porcelain']));
let browser;
let diagnosticPage;
try {
	browser = await chromium.launch({ headless: true });
	const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, ignoreHTTPSErrors: true });
	const secureAuth = JSON.parse(cli(['eval', `echo wp_json_encode(array("name"=>SECURE_AUTH_COOKIE,"value"=>wp_generate_auth_cookie(${Number(auth.user)},time()+1800,"secure_auth",${JSON.stringify(auth.token)})));`]));
	await context.addCookies([{ ...secureAuth, url: auth.url, httpOnly: true, sameSite: 'Lax', secure: auth.url.startsWith('https:') }]);
	await context.addCookies([{ name: auth.name, value: auth.cookie, url: auth.url, httpOnly: true, sameSite: 'Lax' }]);
	await context.addCookies([{ name: auth.admin_name, value: auth.admin_cookie, url: auth.url, httpOnly: true, sameSite: 'Lax' }]);
	const page = await context.newPage();
	diagnosticPage = page;
	const errors = [];
	const writes = [];
	const blockedPreferences = [];
	page.on('pageerror', (error) => errors.push(error.message));
	// Keep asset requests scoped to this candidate without changing site mounts.
	await page.route('**/npcink-workflow-toolbox/assets/**', (route) => {
		const relative = new URL(route.request().url()).pathname.split('/npcink-workflow-toolbox/')[1].replace(/\.min\.(js|css)$/, '.$1');
		return route.fulfill({ path: new URL('../' + relative, import.meta.url).pathname });
	});
	await page.route('**/wp-json/wp/v2/**', async (route) => {
		if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(route.request().method())) {
			const url = route.request().url();
			(new URL(url).pathname.endsWith('/users/me') ? blockedPreferences : writes).push(url);
			return route.abort();
		}
		return route.continue();
	});
	await page.route('**/npcink-toolbox/v1/**', (route) => route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ code: 'offline_browser_smoke', message: 'Offline acceptance fixture' }) }));
	await page.goto(`${auth.url}/wp-admin/post.php?post=${postId}&action=edit`, { waitUntil: 'domcontentloaded' });
	assert.ok(!page.url().includes('wp-login.php'), 'Temporary admin session must reach the editor.');
	await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId());
	// The shared site's old plugin symlink may be absent. Inject this candidate
	// into this browser only; do not activate plugins or change shared mounts.
	await page.evaluate((url) => { window.NpcinkToolboxEditorSupport = { restUrl: url + '/wp-json/npcink-toolbox/v1/', locale: 'zh_CN' }; }, auth.url);
	for (const handle of ['npcink-toolbox-editor-content-format', 'npcink-toolbox-editor-content-support']) {
		const messages = JSON.parse(readFileSync(new URL(`../languages/npcink-workflow-toolbox-zh_CN-${handle}.json`, import.meta.url), 'utf8')).locale_data.messages;
		await page.evaluate((messages) => wp.i18n.setLocaleData(messages, 'npcink-workflow-toolbox'), messages);
	}
	for (const part of ['editor-content-support/text-utils.js', 'editor-content-support/internal-links.js', 'editor-content-support/related-reading.js', 'editor-content-support/audio-preferences.js', 'editor-content-format.js', 'editor-content-support.js']) {
		await page.addScriptTag({ content: readFileSync(new URL('../assets/' + part, import.meta.url), 'utf8') });
	}
	await page.addStyleTag({ content: readFileSync(new URL('../assets/editor-content-support.css', import.meta.url), 'utf8') });
	await page.waitForFunction(() => wp.plugins.getPlugin('npcink-toolbox-editor-content-support'));

	await page.evaluate(() => {
		wp.data.dispatch('core/editor').lockPostAutosaving('article-language-smoke');
		if (wp.data.select('core/preferences')?.get('core/edit-post', 'welcomeGuide')) wp.data.dispatch('core/preferences').set('core/edit-post', 'welcomeGuide', false);
		const dispatch = wp.data.dispatch('core/editor');
		if (dispatch.openGeneralSidebar) dispatch.openGeneralSidebar('npcink-toolbox-editor-content-support/npcink-content-support-sidebar');
		else wp.data.dispatch('core/edit-post').openGeneralSidebar('npcink-toolbox-editor-content-support/npcink-content-support-sidebar');
	});

	const exactText = '😀原文，，  保留。\n<x>\u2028';
	await page.evaluate(() => {
		wp.data.dispatch('core/editor').editPost({ title: '合成语句检查草稿', excerpt: '摘要  原样。' });
		wp.data.dispatch('core/block-editor').resetBlocks([
			wp.blocks.createBlock('core/paragraph', { content: '😀原文，，  保留。<br>&lt;x&gt;\u2028' }),
			wp.blocks.createBlock('core/code', { content: 'excluded-code，，' }),
			wp.blocks.createBlock('core/image', { url: 'https://example.test/synthetic.png', alt: '多媒体原样' }),
		]);
	});
	const collectorSource = readFileSync(new URL('../assets/editor-content-support.js', import.meta.url), 'utf8');
	const collectorFunctions = collectorSource.slice(collectorSource.indexOf('function articleReviewPlainText('), collectorSource.indexOf('function renderArticleLanguageReviewItems('));
	const bounds = await page.evaluate((source) => {
		const collect = new Function(source + ';return articleReviewSnapshot;')();
		const editor = { getEditedPostAttribute: () => '' };
		const paragraph = (text) => ({ name: 'core/paragraph', clientId: 'synthetic', attributes: { content: text } });
		const surrogate = collect(editor, [paragraph('中'.repeat(1599) + '😀尾')]);
		const count = collect(editor, Array.from({ length: 82 }, () => paragraph('原文')));
		const total = collect(editor, Array.from({ length: 25 }, () => paragraph('中'.repeat(1600))));
		const inert = collect(editor, [paragraph('<script>坏脚本</script>正常  原文<br>换行')]);
		return { surrogate: surrogate.blocks[0].text, surrogateCoverage: surrogate.coverage, count: count.blocks.length, omitted: count.coverage.omitted, units: total.blocks.reduce((n, b) => n + b.text.length, 0), inert: inert.blocks[0].text };
	}, collectorFunctions);
	assert.equal(bounds.surrogate, '中'.repeat(1599));
	assert.equal(bounds.surrogateCoverage.truncated, 1);
	assert.equal(bounds.count, 80);
	assert.equal(bounds.omitted, 2);
	assert.equal(bounds.units, 30000);
	assert.equal(bounds.inert, '正常  原文\n换行');
	console.log('PASS: real collector preserves exact whitespace, excludes script text and enforces block/UTF-16 limits without broken surrogates.');
	const original = await page.evaluate(() => wp.data.select('core/editor').getEditedPostContent());
	let mode = 'ready';
	let release;
	let arrived;
	let pending = Promise.resolve();
	let lastInput;
	const bridgePython = process.env.ARTICLE_REVIEW_PYTHON;
	assert.ok(bridgePython && process.env.ARTICLE_REVIEW_ADDON_ROOT && process.env.ARTICLE_REVIEW_CLOUD_ROOT, 'Supply the candidate Python, Addon and Cloud paths.');
	await page.route('**/editor/content-support*', async (route) => {
		const input = route.request().postDataJSON();
		if (input?.intent !== 'publish_preflight') return route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ message: 'Offline smoke: unrelated flow disabled.' }) });
		lastInput = input;
		if (arrived) arrived();
		await pending;

		let report;
		try { report = JSON.parse(execFileSync(bridgePython, [new URL('./article-review-cross-repo-contract.py', import.meta.url).pathname, '--addon-root', process.env.ARTICLE_REVIEW_ADDON_ROOT, '--cloud-root', process.env.ARTICLE_REVIEW_CLOUD_ROOT, '--browser'], { input: JSON.stringify(input), encoding: 'utf8' })); } catch (error) {
			return route.fulfill({ status: 500, contentType: 'application/json', body: JSON.stringify({ message: 'Offline cross-repository bridge failed.' }) });
		}
		if (mode === 'unavailable') report.sections.article_language_review = { status: 'unavailable', message: 'Cloud 中文语句提示暂不可用；本地发布前检查仍可继续。' };
		if (mode === 'refused') {
			report.sections.article_language_review.status = 'partial';
			report.sections.article_language_review.checks.content_risk = { status: 'refused', reason_code: 'provider_refused' };
		}
		if (mode === 'partial') {
			report.sections.article_language_review.status = 'partial';
			report.sections.article_language_review.invalid_finding_count = 1;
			report.sections.article_language_review.result_limit_reached = true;
		}
		return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(report) });
	});
	const review = page.locator('.npcink-toolbox-editor-support__article-language-review');
	const locate = () => review.getByRole('button', { name: /Locate in draft|定位到正文/ }).first();
	const start = async () => {
		// Return from the result screen through the existing menu.
		if (!(await page.getByRole('button', { name: /(?:Run|运行|开始|执行).*?(?:Publish preflight|发布预检)/ }).count())) {
			await page.getByRole('button', { name: /Back to tools|返回工具/ }).click();
		}
		await page.getByRole('button', { name: /(?:Run|运行|开始|执行).*?(?:Publish preflight|发布预检)/ }).click();
	};
	assert.match(await page.locator('.npcink-toolbox-editor-support').innerText(), /运行时会将.*发送至 Cloud/);
	await start();
	await review.waitFor();
	await locate().waitFor();
	assert.equal(lastInput.article_review_snapshot.blocks.find((b) => b.kind === 'body').text, exactText);
	assert.equal(lastInput.article_review_snapshot.coverage.excluded, 2);
	assert.ok(!JSON.stringify(lastInput.article_review_snapshot).includes('excluded-code'));
	assert.equal(await review.locator('blockquote').textContent(), exactText.slice(4));
	assert.equal(await review.locator('blockquote').evaluate((node) => getComputedStyle(node).whiteSpace), 'pre-wrap');
	assert.equal(await review.getByRole('button').count(), 1);
	assert.equal(await review.getByRole('textbox').count(), 0);
	assert.match(await review.innerText(), /逻辑：本次范围分析完成/);
	await locate().click();
	assert.equal(await page.evaluate(() => wp.data.select('core/block-editor').getSelectedBlock()?.name), 'core/paragraph');
	assert.equal(await page.evaluate(() => wp.data.select('core/editor').getEditedPostContent()), original);
	// Two synchronous changes with identical final text must still retire the report.
	await page.evaluate(() => {
		const block = wp.data.select('core/block-editor').getBlocks()[0];
		wp.data.dispatch('core/block-editor').updateBlockAttributes(block.clientId, { content: block.attributes.content + '编辑' });
		wp.data.dispatch('core/block-editor').updateBlockAttributes(block.clientId, { content: block.attributes.content });
	});
	await review.getByText('草稿已变化，当前报告只能阅读，不能定位。', { exact: true }).waitFor();
	assert.ok(await locate().isDisabled());
	assert.equal(await page.evaluate(() => wp.data.select('core/editor').getEditedPostContent()), original);
	console.log('PASS: exact text, exclusions, read-only navigation, and edit/undo cannot revive old report.');

	await start(); await locate().waitFor();
	assert.ok(!(await locate().isDisabled()));
	await page.evaluate(() => {
		const blocks = wp.data.select('core/block-editor').getBlocks();
		wp.data.dispatch('core/block-editor').resetBlocks(wp.blocks.parse(wp.blocks.serialize(blocks)));
	});
	await page.waitForFunction(() => document.querySelector('.npcink-toolbox-editor-support__article-language-review button')?.disabled);
	console.log('PASS: replacing block identity with identical text invalidates navigation.');

	// Freeze a response, edit while it is running, then release the old result.
	pending = new Promise((resolve) => { release = resolve; });
	const requestArrived = new Promise((resolve) => { arrived = resolve; });
	await start(); await requestArrived;
	await page.evaluate(() => wp.data.dispatch('core/editor').editPost({ title: '作者继续编辑的合成标题' }));
	release(); pending = Promise.resolve(); arrived = null;
	await locate().waitFor();
	assert.ok(await locate().isDisabled());
	console.log('PASS: in-flight draft edit leaves the returned report stale.');

	// Every cross-paragraph quote gets its own read-only location and expires together.
	await page.evaluate(() => wp.data.dispatch('core/block-editor').resetBlocks([
		wp.blocks.createBlock('core/paragraph', { content: '2025年甲项目总支出10万元。' }),
		wp.blocks.createBlock('core/paragraph', { content: '同年同一项目总支出12万元。' }),
	]));
	await start(); await locate().waitFor();
	assert.equal(await review.getByRole('button').count(), 2);
	const crossOriginal = await page.evaluate(() => wp.data.select('core/editor').getEditedPostContent());
	await review.getByRole('button').nth(1).click();
	assert.match(await page.evaluate(() => wp.data.select('core/block-editor').getSelectedBlock().attributes.content), /12万元/);
	assert.equal(await page.evaluate(() => wp.data.select('core/editor').getEditedPostContent()), crossOriginal);
	await page.evaluate(() => wp.data.dispatch('core/editor').editPost({ excerpt: '更新摘要' }));
	await page.waitForFunction(() => [...document.querySelectorAll('.npcink-toolbox-editor-support__article-language-review button')].every((button) => button.disabled));
	console.log('PASS: both cross-paragraph locations are read-only and expire together.');
	mode = 'partial';
	await start(); await locate().waitFor();
	assert.match(await review.innerText(), /已隐藏 1 条/);
	assert.match(await review.innerText(), /数量上限/);
	assert.match(await review.innerText(), /部分内容|不完整/);
	mode = 'refused';
	await start(); await locate().waitFor();
	assert.match(await review.innerText(), /成人内容语境风险：未能分析（拒绝）/);
	mode = 'unavailable';
	const beforeUnavailable = await page.evaluate(() => wp.data.select('core/editor').getEditedPostContent());
	await start();
	await review.getByText('Cloud 中文语句提示暂不可用；本地发布前检查仍可继续。', { exact: true }).waitFor();
	assert.equal(await review.getByRole('button').count(), 0);
	assert.equal(await review.getByRole('textbox').count(), 0);
	assert.equal(await page.evaluate(() => wp.data.select('core/editor').getEditedPostContent()), beforeUnavailable);
	assert.deepEqual(writes, []);
	assert.deepEqual(errors, []);
	assert.equal(JSON.parse(cli(['post', 'get', String(postId), '--fields=post_content', '--format=json'])).post_content, source);
	mkdirSync(new URL('../output/playwright/', import.meta.url), { recursive: true });
	await page.screenshot({ path: new URL('../output/playwright/article-language-unavailable.png', import.meta.url).pathname, fullPage: true });
	console.log('PASS: partial/limit/error feedback is explicit; no editor mutations or post-write requests; database source unchanged.');
} catch (error) {
	if (diagnosticPage) console.log('Browser diagnostic:', (await diagnosticPage.locator('body').innerText()).slice(-14000));
	throw error;
} finally {
	if (browser) await browser.close();
	cli(['post', 'delete', String(postId), '--force']);
	cli(['eval', `WP_Session_Tokens::get_instance(${Number(auth.user)})->destroy(${JSON.stringify(auth.token)});`]);
	console.log(`CLEANUP: Deleted disposable draft ${postId} and temporary login session.`);
}
