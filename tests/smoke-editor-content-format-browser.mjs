import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { mkdirSync, writeFileSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium } = require(require.resolve('playwright', { paths: [process.env.NODE_PATH || '/Users/muze/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules'] }));
const root = process.env.WP_PATH || '/Users/muze/Local Sites/magick-toolbox/app/public';
const php = process.env.WP_CLI_PHP || '/Users/muze/Library/Application Support/Local/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php';
const socket = process.env.WP_DB_SOCKET || '/Users/muze/Library/Application Support/Local/run/0rmB6u8JC/mysql/mysqld.sock';
function cli(args) {
	return execFileSync(php, ['-d', `mysqli.default_socket=${socket}`, '/opt/homebrew/bin/wp', `--path=${root}`, '--no-color', ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
}
const auth = JSON.parse(cli(['eval', '$u=get_users(array("role"=>"administrator","number"=>1))[0]; $e=time()+1800; $t=WP_Session_Tokens::get_instance($u->ID)->create($e); echo wp_json_encode(array("user"=>$u->ID,"token"=>$t,"cookie"=>wp_generate_auth_cookie($u->ID,$e,"logged_in",$t),"admin_cookie"=>wp_generate_auth_cookie($u->ID,$e,"auth",$t),"admin_name"=>AUTH_COOKIE,"name"=>LOGGED_IN_COOKIE,"url"=>home_url()));']));
const sourcePostId = Number(process.env.FORMAT_SOURCE_POST_ID || 0);
const preservationFixture = process.env.FORMAT_PRESERVATION_FIXTURE === '1';
const structuralFixture = sourcePostId || preservationFixture;
const readSource = () => JSON.parse(cli(['post', 'get', String(sourcePostId), '--fields=post_content', '--format=json'])).post_content;
const longText = ('这是需要保持原有文字及语序的完整句子'.repeat(3) + '，<strong>重要<em>内容</em></strong>参考<a href="https://example.test/guide?x=1&amp;y=2">原链接</a>和<code>中文AI</code>，适用于WordPress正文。').repeat(8);
const richSource = `<!-- wp:paragraph -->\n<p>${longText}</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:table -->\n<figure class="wp-block-table"><table class="has-fixed-layout"><tbody><tr><td>中文AI</td><td>1.20</td></tr></tbody></table></figure>\n<!-- /wp:table -->\n\n<!-- wp:code -->\n<pre class="wp-block-code"><code>中文AI &lt;x&gt;</code></pre>\n<!-- /wp:code -->`;
const source = sourcePostId ? readSource() : preservationFixture ? richSource : '<!-- wp:paragraph -->\n<p>中文AI工具适用于WordPress正文。</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>第二段AI文字。</p>\n<!-- /wp:paragraph -->';
const postId = Number(cli(['post', 'create', '--post_type=post', '--post_status=draft', '--post_title=Content formatting disposable smoke', `--post_content=${source}`, '--porcelain']));
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
	page.on('pageerror', (error) => errors.push(error.message));
	await page.route('**/wp-json/wp/v2/**', async (route) => {
		if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(route.request().method())) { writes.push(route.request().url()); return route.abort(); }
		return route.continue();
	});
	await page.goto(`${auth.url}/wp-admin/post.php?post=${postId}&action=edit`, { waitUntil: 'domcontentloaded' });
	assert.ok(!page.url().includes('wp-login.php'), 'Temporary admin session must reach the editor.');
	await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId());
	await page.waitForFunction(() => window.NpcinkToolboxContentFormat && wp.plugins.getPlugin('npcink-toolbox-editor-content-support'));
	await page.evaluate(() => {
		wp.data.dispatch('core/editor').lockPostAutosaving('content-format-smoke');
		if (wp.data.select('core/preferences')?.get('core/edit-post', 'welcomeGuide')) wp.data.dispatch('core/preferences').set('core/edit-post', 'welcomeGuide', false);
		const dispatch = wp.data.dispatch('core/editor');
		if (dispatch.openGeneralSidebar) dispatch.openGeneralSidebar('npcink-toolbox-editor-content-support/npcink-content-support-sidebar');
		else wp.data.dispatch('core/edit-post').openGeneralSidebar('npcink-toolbox-editor-content-support/npcink-content-support-sidebar');
	});
	const button = page.getByRole('button', { name: '整理', exact: true });
	await page.evaluate(() => {
		const source = '<!-- wp:paragraph -->\n<p>介绍。地址：<a href="/buy">详情</a></p>\n<!-- /wp:paragraph -->';
		const candidate = '<!-- wp:paragraph -->\n<p>介绍。</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:list -->\n<ul class="wp-block-list"><!-- wp:list-item -->\n<li>地址：<a href="/buy">详情</a></li>\n<!-- /wp:list-item --></ul>\n<!-- /wp:list -->';
		const blocks = wp.blocks.parse(source);
		const result = { contract_version: 'content_format_candidate.v2', format: 'html', status: 'CHANGED', candidate,
			visible_characters_preserved: true, direct_wordpress_write: false, persisted: false };
		if (!NpcinkToolboxContentFormat.prepare(blocks, source, result).length) throw new Error('Native list fixture must prepare.');
		for (const bad of [candidate.replace('/buy', '/other'), candidate.replace('介绍', '删改'), candidate.replace('<li>', '<li onclick="alert(1)">')]) {
			let rejected = false;
			try { NpcinkToolboxContentFormat.prepare(blocks, source, { ...result, candidate: bad }); } catch { rejected = true; }
			if (!rejected) throw new Error('Unsafe structure candidate accepted.');
		}
		const richSource = '<!-- wp:paragraph -->\n<p>原文<strong>重点</strong>。<code>中文AI</code>保持。</p>\n<!-- /wp:paragraph -->';
		const richCandidate = richSource.replace('。<code>', '。</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><code>');
		if (!NpcinkToolboxContentFormat.prepare(wp.blocks.parse(richSource), richSource, { ...result, candidate: richCandidate }).length) throw new Error('Safe rich paragraph split rejected.');
		for (const bad of [richCandidate.replace('中文AI', '中文 AI'), richCandidate.replace('<strong>', '<em>').replace('</strong>', '</em>')]) {
			let rejected = false;
			try { NpcinkToolboxContentFormat.prepare(wp.blocks.parse(richSource), richSource, { ...result, candidate: bad }); } catch { rejected = true; }
			if (!rejected) throw new Error('Inline code or emphasis mutation accepted.');
		}
	});
	const notice = page.locator('.npcink-toolbox-editor-format .components-notice__content');
	try { await button.waitFor({ timeout: 10000 }); } catch (error) {
		console.log('Editor diagnostic:', await page.locator('.npcink-toolbox-editor-format').evaluate((el) => el.outerHTML), errors);
		throw error;
	}
	const baseline = await page.evaluate(() => ({ content: wp.data.select('core/editor').getEditedPostContent(), protected: wp.data.select('core/block-editor').getBlocks().filter((b) => b.name !== 'core/paragraph').map((b) => [b.clientId, wp.blocks.serialize([b])]) }));
	const responsePromise = page.waitForResponse((r) => r.url().includes('/editor/content-support') && r.request().postDataJSON()?.intent === 'format_content');
	await button.click();
	const response = await responsePromise;
	const result = await response.json();
	assert.equal(response.status(), 200, JSON.stringify(result));
	assert.equal(result.status, structuralFixture ? 'PARTIAL' : 'CHANGED');
	await notice.waitFor();
	console.log('Formatting response notice:', await notice.innerText());
	if (!(await notice.innerText()).includes(structuralFixture ? '段落、列表或换行已整理' : '已调整文字间距')) {
		console.log('Block schema diagnostic:', await page.evaluate((candidate) => ({ before: wp.data.select('core/block-editor').getBlocks().map((b) => [b.name, Object.keys(b.attributes)]), after: wp.blocks.parse(candidate).map((b) => [b.name, Object.entries(b.attributes).filter(([k]) => k !== 'content'), b.innerBlocks.map((i) => [i.name, Object.keys(i.attributes)])]) }), result.candidate));
		throw new Error('Editor rejected formatting candidate.');
	}
	await notice.filter({ hasText: structuralFixture ? '段落、列表或换行已整理' : '已调整文字间距' }).waitFor();
	const applied = await page.evaluate(() => ({ content: wp.data.select('core/editor').getEditedPostContent(), ids: wp.data.select('core/block-editor').getBlocks().map((b) => b.clientId) }));
	assert.equal(applied.content, result.candidate);
	const retained = await page.evaluate(() => wp.data.select('core/block-editor').getBlocks().map((b) => [b.clientId, wp.blocks.serialize([b])]));
	for (const item of baseline.protected) assert.ok(retained.some((next) => next[0] === item[0] && next[1] === item[1]), 'Unchanged block identity/content must survive.');
	if (sourcePostId) {
		assert.equal(result.structural_changes, 5);
		assert.equal((result.candidate.match(/<!-- wp:list-item -->/g) || []).length, 3);
		assert.ok(!/[\u3400-\u9fff]\* [\u3400-\u9fff]/.test(result.candidate), 'Feature items must not remain glued together.');
	}
	if (preservationFixture) {
		assert.equal(result.structural_changes, 3);
		assert.equal((result.candidate.match(/<!-- wp:paragraph -->/g) || []).length, 4);
		assert.ok(result.candidate.includes('<td>中文AI</td><td>1.20</td>'));
		assert.ok(result.candidate.includes('<code>中文AI &lt;x&gt;</code>'));
		assert.equal((result.candidate.match(/<strong>重要<em>内容<\/em><\/strong>/g) || []).length, 8);
		assert.equal((result.candidate.match(/<code>中文AI<\/code>/g) || []).length, 8);
	}
	assert.ok(result.inserted_spaces > 0);
	assert.equal(JSON.parse(cli(['post', 'get', String(postId), '--fields=post_content', '--format=json'])).post_content, source);
	console.log('PASS: Real signed Cloud response updates visible body, preserves block IDs, database unchanged.');
	mkdirSync(new URL('../build/smoke/', import.meta.url), { recursive: true });
	await page.screenshot({ path: new URL('../build/smoke/content-format-desktop.png', import.meta.url).pathname, fullPage: true });
	const repeatPromise = page.waitForResponse((r) => r.url().includes('/editor/content-support') && r.request().postDataJSON()?.intent === 'format_content');
	await button.click();
	const repeat = await (await repeatPromise).json();
	await notice.filter({ hasText: structuralFixture ? '可处理的文字无需调整' : '正文无需调整' }).waitFor();
	assert.ok(repeat.candidate === result.candidate, 'Repeated formatting must be idempotent.');
	writeFileSync(new URL('../build/smoke/content-format-eval.json', import.meta.url), JSON.stringify({ contract: 'content_format_eval.v1', cases: [{ id: sourcePostId ? 'mixed-article' : preservationFixture ? 'long-paragraph-table-code' : 'plain-paragraphs', source: baseline.content, candidate: result.candidate, repeat_candidate: repeat.candidate, expected_list_items: sourcePostId ? 3 : 0, expected_glued_markers: 0, ...(preservationFixture ? { expected_paragraphs: 4 } : {}) }] }));
	await page.evaluate(() => wp.data.dispatch('core/editor').undo());
	assert.equal(await page.evaluate(() => wp.data.select('core/editor').getEditedPostContent()), baseline.content);
	await page.evaluate(() => wp.data.dispatch('core/editor').redo());
	assert.equal(await page.evaluate(() => wp.data.select('core/editor').getEditedPostContent()), applied.content);
	console.log('PASS: Native editor undo/redo treats both paragraphs as one transaction.');
	await page.getByRole('button', { name: '撤销本次整理', exact: true }).click();
	assert.equal(await page.evaluate(() => wp.data.select('core/editor').getEditedPostContent()), baseline.content);
	console.log('PASS: One-click formatting undo restores exact body.');

	let release;
	let received;
	const pending = new Promise((resolve) => { received = resolve; });
	await page.route('**/editor/content-support*', async (route) => {
		if (route.request().postDataJSON()?.intent !== 'format_content') return route.continue();
		received();
		await new Promise((resolve) => { release = resolve; });
		return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(result) });
	});
	await button.click();
	let pendingTimer;
	try { await Promise.race([pending, new Promise((_, reject) => { pendingTimer = setTimeout(() => reject(new Error('Delayed fixture did not intercept request.')), 10000); })]); }
	finally { clearTimeout(pendingTimer); }
	await page.evaluate(() => {
		const block = wp.data.select('core/block-editor').getBlocks()[0];
		wp.data.dispatch('core/block-editor').updateBlockAttributes(block.clientId, { content: '等待时新增正文。' });
	});
	const edited = await page.evaluate(() => wp.data.select('core/editor').getEditedPostContent());
	release();
	await notice.filter({ hasText: '等待期间正文已修改，本次结果未应用。' }).waitFor();
	assert.equal(await page.evaluate(() => wp.data.select('core/editor').getEditedPostContent()), edited);
	console.log('PASS: Late response cannot overwrite subsequent edits.');
	await page.unroute('**/editor/content-support*');
	await page.route('**/editor/content-support*', (route) => route.request().postDataJSON()?.intent === 'format_content'
		? route.fulfill({ status: 502, contentType: 'application/json', body: JSON.stringify({ code: 'npcink_content_format_cloud', message: '云端整理暂不可用，原文未改动。' }) }) : route.continue());
	await button.click();
	await notice.filter({ hasText: '云端整理暂不可用，原文未改动。' }).waitFor();
	assert.equal(await page.evaluate(() => wp.data.select('core/editor').getEditedPostContent()), edited);
	assert.deepEqual(writes, []);
	assert.deepEqual(errors, []);
	console.log('PASS: Cloud failure preserves text; no post-write requests or page errors.');
	await page.setViewportSize({ width: 390, height: 844 });
	// Gutenberg closes the active sidebar when entering its mobile breakpoint.
	await page.waitForFunction(() => wp.data.select('core/viewport').isViewportMatch('< medium'));
	await button.waitFor({ state: 'hidden', timeout: 10000 });
	await page.evaluate(() => {
		const editor = wp.data.dispatch('core/editor');
		const sidebar = editor.openGeneralSidebar ? editor : wp.data.dispatch('core/edit-post');
		sidebar.openGeneralSidebar('npcink-toolbox-editor-content-support/npcink-content-support-sidebar');
	});
	await button.waitFor({ timeout: 10000 });
	const box = await button.boundingBox();
	assert.ok(box && box.x >= 0 && box.x + box.width <= 391);
	await page.screenshot({ path: new URL('../build/smoke/content-format-mobile.png', import.meta.url).pathname, fullPage: true });
	console.log('PASS: Formatting control fits mobile viewport.');
} catch (error) {
	if (diagnosticPage) console.log('Formatting notice:', await diagnosticPage.locator('.npcink-toolbox-editor-format').innerText().catch(() => 'not available'));
	throw error;
} finally {
	if (browser) await browser.close();
	cli(['post', 'delete', String(postId), '--force']);
	cli(['eval', `WP_Session_Tokens::get_instance(${Number(auth.user)})->destroy(${JSON.stringify(auth.token)});`]);
	if (sourcePostId) assert.ok(readSource() === source, 'Original article must remain unchanged.');
	console.log(`CLEANUP: Deleted disposable draft ${postId} and temporary login session.`);
}
