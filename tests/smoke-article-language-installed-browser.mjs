// Optional real installed-plugin acceptance. Sends one synthetic preflight to
// the site's configured Cloud. Never injects assets or substitutes a response.
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { createRequire } from 'node:module';
import { mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';

for (const key of ['WP_PATH', 'WP_CLI_PHP', 'WP_CLI_BIN', 'WP_DB_SOCKET']) {
	assert.ok(process.env[key], `Set ${key} for this optional local acceptance.`);
}
const require = createRequire(import.meta.url);
const { chromium } = require(require.resolve('playwright', process.env.NODE_PATH ? { paths: [process.env.NODE_PATH] } : undefined));
function cli(args) {
	try {
		return execFileSync(process.env.WP_CLI_PHP, ['-d', 'display_errors=stderr', '-d', `mysqli.default_socket=${process.env.WP_DB_SOCKET}`, process.env.WP_CLI_BIN, `--path=${process.env.WP_PATH}`, '--no-color', ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'], timeout: 60000 }).trim();
	} catch {
		// execFileSync errors include arguments, which may contain session tokens.
		throw new Error('WordPress CLI acceptance step failed; command and output withheld.');
	}
}
const sha = (value) => createHash('sha256').update(value).digest('hex');
const output = new URL('../output/playwright/', import.meta.url);
mkdirSync(output, { recursive: true });
rmSync(new URL('article-review-context-installed-evidence.json', output), { force: true });
const fixtureFields = ['post_title', 'post_excerpt', 'post_content', 'post_status', 'post_modified', 'post_modified_gmt'];
const fixture = (id) => JSON.parse(cli(['post', 'get', String(id), `--fields=${fixtureFields.join(',')}`, '--format=json']));
const source = '<!-- wp:paragraph -->\n<p>合成原始草稿，不保存浏览器中的测试编辑。</p>\n<!-- /wp:paragraph -->';
let auth;
let postId;
let browser;
let page;
let receipt;
try {
	const installed = JSON.parse(cli(['eval', 'echo wp_json_encode(array("toolbox"=>defined("NPCINK_TOOLBOX_DIR")?realpath(NPCINK_TOOLBOX_DIR):null,"addon"=>defined("NPCINK_CLOUD_ADDON_DIR")?realpath(NPCINK_CLOUD_ADDON_DIR):null,"facade"=>function_exists("npcink_cloud_addon_execute_toolbox_article_review_runtime")));']));
	assert.equal(installed.toolbox, new URL('..', import.meta.url).pathname.replace(/\/$/, ''), 'Installed Toolbox must be this checkout.');
	assert.ok(installed.addon && installed.facade, 'The active installed Addon must expose the review facade.');
	auth = JSON.parse(cli(['eval', '$u=get_users(array("role"=>"administrator","number"=>1))[0]; $e=time()+1800; $t=WP_Session_Tokens::get_instance($u->ID)->create($e); echo wp_json_encode(array("user"=>$u->ID,"token"=>$t,"url"=>home_url(),"cookies"=>array(array("name"=>LOGGED_IN_COOKIE,"value"=>wp_generate_auth_cookie($u->ID,$e,"logged_in",$t)),array("name"=>AUTH_COOKIE,"value"=>wp_generate_auth_cookie($u->ID,$e,"auth",$t)),array("name"=>SECURE_AUTH_COOKIE,"value"=>wp_generate_auth_cookie($u->ID,$e,"secure_auth",$t)))));']));
	postId = Number(cli(['post', 'create', '--post_type=post', '--post_status=draft', '--post_title=Article language installed disposable smoke', `--post_content=${source}`, '--porcelain']));
	assert.ok(Number.isSafeInteger(postId) && postId > 0);
	const before = fixture(postId);
	browser = await chromium.launch({ headless: true });
	const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, ignoreHTTPSErrors: true });
	await context.addCookies(auth.cookies.map((cookie) => ({ ...cookie, url: auth.url, httpOnly: true, sameSite: 'Lax', secure: cookie.name.startsWith('wordpress_sec_') })));
	page = await context.newPage();
	const errors = [];
	const writes = [];
	let preferenceAttempts = 0;
	let calls = 0;
	let progressiveRequests = 0;
	let lastInput;
	const assetChecks = [];
	page.on('pageerror', (error) => errors.push(error.message));
	page.on('response', (response) => {
		const name = new URL(response.url()).pathname.split('/npcink-workflow-toolbox/assets/')[1];
		if (name && /^editor-content-(?:support|format)(?:\.min)?\.js$/.test(name)) {
			assetChecks.push((async () => {
				assert.equal(response.status(), 200);
				assert.equal(sha(await response.body()), sha(readFileSync(new URL('../assets/' + name, import.meta.url))));
				return name;
			})().then((name) => ({ name }), () => ({ failed: true })));
		}
	});
	// Block native REST writes before opening the editor; autosave is also
	// locked below. No result or installed asset route is intercepted.
	await page.route('**/wp-json/wp/v2/**', (route) => {
		if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(route.request().method())) {
			if (new URL(route.request().url()).pathname.endsWith('/users/me')) preferenceAttempts++;
			else writes.push(new URL(route.request().url()).pathname);
			return route.abort();
		}
		return route.continue();
	});
	await page.route('**/editor/content-support*', (route) => {
		const input = route.request().postDataJSON();
		if (input.intent === 'progressive_recommendations') {
			progressiveRequests++;
			return route.continue();
		}
		lastInput = input;
		calls++;
		if (calls !== 1 || lastInput.intent !== 'publish_preflight') return route.abort();
		return route.continue();
	});
	await page.goto(`${auth.url}/wp-admin/post.php?post=${postId}&action=edit`, { waitUntil: 'domcontentloaded' });
	assert.ok(!page.url().includes('wp-login.php'));
	await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId());
	await page.waitForFunction(() => wp.plugins.getPlugin('npcink-toolbox-editor-content-support'));
	await page.evaluate(() => {
		wp.data.dispatch('core/editor').lockPostAutosaving('article-review-context-installed-smoke');
		if (wp.data.select('core/preferences')?.get('core/edit-post', 'welcomeGuide')) wp.data.dispatch('core/preferences').set('core/edit-post', 'welcomeGuide', false);
		const dispatch = wp.data.dispatch('core/editor');
		if (dispatch.openGeneralSidebar) dispatch.openGeneralSidebar('npcink-toolbox-editor-content-support/npcink-content-support-sidebar');
		else wp.data.dispatch('core/edit-post').openGeneralSidebar('npcink-toolbox-editor-content-support/npcink-content-support-sidebar');
		wp.data.dispatch('core/editor').editPost({ title: '合成语句检查草稿', excerpt: '仅供开发验收的合成摘要。' });
		wp.data.dispatch('core/block-editor').resetBlocks([
			wp.blocks.createBlock('core/paragraph', { content: '😀这是一段  合成测试文本，，用于检查提示。' }),
			wp.blocks.createBlock('core/paragraph', { content: '2025年甲项目总支出为10万元。' }),
			wp.blocks.createBlock('core/paragraph', { content: '上文同一年同一项目的全部总支出为12万元，两处均包含所有费用。' }),
			wp.blocks.createBlock('core/code', { content: 'excluded-code，，' }),
			wp.blocks.createBlock('core/image', { url: 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==', alt: '多媒体保持原样' }),
		]);
	});
	const editorState = () => page.evaluate(() => ({ content: wp.data.select('core/editor').getEditedPostContent(), title: wp.data.select('core/editor').getEditedPostAttribute('title'), excerpt: wp.data.select('core/editor').getEditedPostAttribute('excerpt') }));
	const original = await editorState();
	assert.match(await page.locator('.npcink-toolbox-editor-support').innerText(), /运行时会将.*发送至 Cloud/);
	const responseReady = page.waitForResponse((response) => new URL(response.url()).pathname.endsWith('/editor/content-support') && response.request().method() === 'POST' && response.request().postDataJSON()?.intent === 'publish_preflight', { timeout: 180000 });
	await page.getByRole('button', { name: /(?:Run|运行|开始|执行).*?(?:Publish preflight|发布预检)/ }).click();
	const response = await responseReady;
	assert.equal(response.status(), 200);
	const report = (await response.json()).sections.article_language_review;
	console.log(`Cloud response: status=${report.status}; run_id=${report.run_id}; findings=${report.items.length}.`);
	assert.equal(calls, 1);
	assert.ok(['ready', 'partial'].includes(report.status), `Unexpected review state: ${report.status}`);
	assert.equal(report.invalid_finding_count, 0);
	assert.equal(report.checks.language.status, 'completed');
	assert.deepEqual(report.checks.logic, { status: 'completed', reason_code: 'none' });
	assert.deepEqual(report.checks.content_risk, { status: 'completed', reason_code: 'none' });
	assert.match(report.run_id, /^run_[a-z0-9]+$/);
	assert.ok(report.items.some((item) => item.type === 'punctuation'), 'The synthetic repeated punctuation should yield an author review hint.');
	assert.ok(report.items.some((item) => item.check === 'logic' && item.evidence_refs.length >= 2), 'The synthetic conflicting totals should yield verified paired logic evidence.');
	assert.deepEqual(lastInput.article_review_snapshot.checks, ['language', 'logic', 'content_risk']);
	assert.equal(lastInput.article_review_snapshot.coverage.excluded, 2);
	assert.equal(lastInput.article_review_snapshot.blocks.find((block) => block.kind === 'body').text, '😀这是一段  合成测试文本，，用于检查提示。');
	assert.ok(!JSON.stringify(lastInput.article_review_snapshot).includes('excluded-code'));
	const review = page.locator('.npcink-toolbox-editor-support__article-language-review');
	await review.waitFor();
	const quotes = await review.locator('blockquote').allTextContents();
	assert.deepEqual(quotes, report.items.flatMap((item) => item.evidence_refs.map((ref) => ref.quote)));
	for (const item of report.items) for (const ref of item.evidence_refs) {
		const block = lastInput.article_review_snapshot.blocks.find((candidate) => candidate.client_id === ref.client_id);
		assert.ok(block);
		assert.equal(block.text.slice(ref.start, ref.end), ref.quote, 'UTF-16 quote must equal the actual submitted text.');
	}
	assert.equal(await review.locator('blockquote').first().evaluate((node) => getComputedStyle(node).whiteSpace), 'pre-wrap');
	assert.equal(await review.getByRole('textbox').count(), 0);
	const buttons = review.getByRole('button');
	assert.ok(await buttons.count() > 0);
	for (const button of await buttons.all()) assert.match(await button.innerText(), /Locate in draft|定位到正文/);
	const logic = report.items.find((item) => item.check === 'logic' && item.evidence_refs.length >= 2);
	const secondQuote = logic.evidence_refs[1];
	const position = report.items.flatMap((item) => item.evidence_refs).findIndex((ref) => ref === secondQuote);
	await buttons.nth(position).click();
	assert.equal(await page.evaluate(() => wp.data.select('core/block-editor').getSelectedBlock()?.clientId), secondQuote.client_id);
	await buttons.first().click();
	assert.equal(await page.evaluate(() => wp.data.select('core/block-editor').getSelectedBlock()?.name), 'core/paragraph');
	assert.deepEqual(await editorState(), original);
	await page.screenshot({ path: new URL('article-review-context-installed-ready.png', output).pathname, fullPage: true });
	await page.evaluate(() => {
		const block = wp.data.select('core/block-editor').getBlocks()[0];
		wp.data.dispatch('core/block-editor').updateBlockAttributes(block.clientId, { content: block.attributes.content + '编辑' });
		wp.data.dispatch('core/block-editor').updateBlockAttributes(block.clientId, { content: block.attributes.content });
	});
	await review.getByText('草稿已变化，当前报告只能阅读，不能定位。', { exact: true }).waitFor();
	for (const button of await buttons.all()) assert.ok(await button.isDisabled());
	assert.deepEqual(await editorState(), original);
	assert.deepEqual(fixture(postId), before, 'Database title/excerpt/content/status/timestamps must remain unchanged.');
	assert.deepEqual(writes, []);
	assert.deepEqual(errors, []);
	const assetResults = await Promise.all(assetChecks);
	assert.ok(assetResults.every((result) => result.name), 'Every observed installed asset must match the checkout.');
	const assets = [...new Set(assetResults.map((result) => result.name))];
	assert.ok(assets.some((name) => name.startsWith('editor-content-support.')));
	assert.ok(assets.some((name) => name.startsWith('editor-content-format.')));
	receipt = {
		contract: 'article_language_installed_browser_evidence.v1',
		date: new Date().toISOString(),
		toolbox_revision: execFileSync('git', ['rev-parse', 'HEAD'], { cwd: installed.toolbox, encoding: 'utf8' }).trim(),
		addon_revision: execFileSync('git', ['rev-parse', 'HEAD'], { cwd: installed.addon, encoding: 'utf8' }).trim(),
		preflight_requests: calls,
		progressive_requests: progressiveRequests,
		run_id: report.run_id,
		status: report.status,
		findings: report.items.length,
		kinds: report.items.map((item) => item.type),
		invalid_findings: report.invalid_finding_count,
		checks: report.checks,
		actual_installed_assets_verified: assets,
		substituted_results: false,
		injected_assets: false,
		exact_utf16_quotes: true,
		excluded_code_and_image: true,
		read_only_navigation: true,
		edit_undo_retires_report: true,
		editor_title_excerpt_content_unchanged: true,
		database_fixture_unchanged: true,
		post_write_attempts: writes.length,
		blocked_preference_attempts: preferenceAttempts,
		page_errors: errors.length,
		customer_content_sent: false,
		production_deployed: false,
	};
	console.log(`PASS: installed assets + real preflight; ${report.items.length} exact-quote hints; read-only navigation, stale protection and unchanged draft verified. Cloud run ${report.run_id}.`);
} catch (error) {
	if (page) console.log('Installed editor diagnostic:', await page.evaluate(() => ({ scripts: Array.from(document.scripts).map((script) => script.src ? new URL(script.src).pathname : '').filter((path) => /editor-content|edit-post/.test(path)), plugins: window.wp?.plugins?.getPlugins().map((plugin) => plugin.name), localized: Boolean(window.NpcinkToolboxEditorSupport) })));
	throw error;
} finally {
	// Independent cleanup attempts so a browser-close failure cannot leak a draft
	// or prevent destruction of this one temporary session.
	const cleanup = await Promise.allSettled([
		browser ? browser.close() : Promise.resolve(),
		Promise.resolve().then(() => { if (postId) cli(['post', 'delete', String(postId), '--force']); }),
		Promise.resolve().then(() => { if (auth) cli(['eval', `$m=WP_Session_Tokens::get_instance(${Number(auth.user)});$m->destroy(${JSON.stringify(auth.token)});if($m->verify(${JSON.stringify(auth.token)})){exit(1);}`]); }),
	]);
	for (const result of cleanup) if (result.status === 'rejected') console.error('Cleanup failed; inspect the local fixture/session without printing credentials.');
	assert.ok(cleanup.every((result) => result.status === 'fulfilled'), 'All cleanup steps must succeed.');
	if (receipt) {
		receipt.disposable_draft_deleted = true;
		receipt.temporary_session_destroyed = true;
		writeFileSync(new URL('article-review-context-installed-evidence.json', output), JSON.stringify(receipt, null, 2) + '\n');
	}
	console.log('CLEANUP: disposable draft and temporary session removed.');
}
