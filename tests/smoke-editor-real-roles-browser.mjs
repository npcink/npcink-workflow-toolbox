// Optional installed-site acceptance; requires explicit local WP/CLI settings.
// Real roles, cookies, installed assets, Toolbox authorization and native save.
// Server provider fixtures isolate upstream availability after authorization;
// browser feedback is suppressed to keep synthetic events out of quality data.
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { createHash, randomBytes } from 'node:crypto';
import { createRequire } from 'node:module';
import { mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';

for (const key of ['WP_PATH', 'WP_CLI_PHP', 'WP_CLI_BIN', 'WP_DB_SOCKET']) assert.ok(process.env[key], `Set ${key}.`);
const require = createRequire(import.meta.url);
const { chromium } = require(require.resolve('playwright', process.env.NODE_PATH ? { paths: [process.env.NODE_PATH] } : undefined));
function cli(args) {
	try {
		return execFileSync(process.env.WP_CLI_PHP, ['-d', 'display_errors=stderr', '-d', 'error_reporting=8191', '-d', `mysqli.default_socket=${process.env.WP_DB_SOCKET}`, process.env.WP_CLI_BIN, `--path=${process.env.WP_PATH}`, '--no-color', ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'], timeout: 60000 }).trim();
	} catch {
		throw new Error('WordPress CLI acceptance step failed; arguments/output withheld to protect session credentials.');
	}
}
const evaluate = (code) => JSON.parse(cli(['eval', code]));
const sha = (value) => createHash('sha256').update(value).digest('hex');
const marker = `role-smoke-${randomBytes(6).toString('hex')}`;
const output = new URL('../output/playwright/', import.meta.url);
mkdirSync(output, { recursive: true });
const receiptPath = new URL('editor-real-roles-evidence.json', output);
rmSync(receiptPath, { force: true });
const users = [];
const posts = [];
const sessions = [];
let browser;
let page;
let receipt;
let providerFixture;
const state = (id) => evaluate(`echo wp_json_encode(array('content'=>get_post_field('post_content',${id}),'status'=>get_post_status(${id}),'categories'=>wp_get_post_categories(${id}),'tags'=>wp_get_post_tags(${id},array('fields'=>'ids'))));`);
const governance = () => evaluate(`global $wpdb;$r=array();foreach(array('proposals','audit_log') as $s){$t=$wpdb->prefix.'npcink_governance_core_'.$s;$rows=$wpdb->get_results("SELECT * FROM $t ORDER BY id",ARRAY_A);if($wpdb->last_error){exit(1);}$r[$s]=array('count'=>count($rows),'sha256'=>hash('sha256',wp_json_encode($rows)));}echo wp_json_encode($r);`);
async function openSidebar() {
	await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId());
	await page.waitForFunction(() => wp.plugins.getPlugin('npcink-toolbox-editor-content-support'));
	await page.evaluate(() => {
		wp.data.dispatch('core/editor').lockPostAutosaving('real-role-smoke');
		wp.data.dispatch('core/preferences').set('core/edit-post', 'welcomeGuide', false);
		const dispatch = wp.data.dispatch('core/editor');
		if (dispatch.openGeneralSidebar) dispatch.openGeneralSidebar('npcink-toolbox-editor-content-support/npcink-content-support-sidebar');
		else wp.data.dispatch('core/edit-post').openGeneralSidebar('npcink-toolbox-editor-content-support/npcink-content-support-sidebar');
	});
	await page.locator('.npcink-toolbox-editor-support__flow').first().waitFor();
}
async function runFlow(label, intent) {
	await toolList();
	const [response] = await Promise.all([
		page.waitForResponse((response) => new URL(response.url()).pathname.endsWith('/editor/content-support') && response.request().method() === 'POST' && response.request().postDataJSON()?.intent === intent, { timeout: 180000 }),
		page.locator('.npcink-toolbox-editor-support__flow').filter({ hasText: label }).locator('.npcink-toolbox-editor-support__flow-actions').getByRole('button').first().click(),
	]);
	assert.equal(response.status(), 200, `${intent}: real REST authorization succeeds.`);
	console.log(`PASS: real ${intent} response.`);
	return response.json();
}
async function toolList() {
	const back = page.getByRole('button', { name: /Tool list|工具列表/ });
	if (await back.count()) await back.click();
}
async function rest(path, data) {
	return page.evaluate(async ({ path, data }) => {
		const response = await fetch(`/wp-json/npcink-toolbox/v1/${path}`, { method: 'POST', credentials: 'same-origin', signal: AbortSignal.timeout(60000), headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.NpcinkToolboxEditorSupport.nonce }, body: JSON.stringify(data) });
		return { status: response.status, body: await response.json() };
	}, { path, data });
}
try {
	const site = evaluate(`$cats=get_terms(array('taxonomy'=>'category','hide_empty'=>false,'exclude'=>array((int)get_option('default_category')),'number'=>1));$tags=get_terms(array('taxonomy'=>'post_tag','hide_empty'=>false,'orderby'=>'count','order'=>'DESC','number'=>1));$img=null;foreach(get_posts(array('post_type'=>'attachment','post_mime_type'=>'image','posts_per_page'=>200)) as $a){if(is_file((string)get_attached_file($a->ID))){$img=array('id'=>$a->ID,'url'=>wp_get_attachment_url($a->ID),'alt'=>get_post_meta($a->ID,'_wp_attachment_image_alt',true),'title'=>$a->post_title);break;}}echo wp_json_encode(array('root'=>realpath(NPCINK_TOOLBOX_DIR),'url'=>home_url(),'category'=>$cats[0]??null,'tag'=>$tags[0]??null,'image'=>$img));`);
	assert.equal(site.root, new URL('..', import.meta.url).pathname.replace(/\/$/, ''));
	assert.ok(site.category && site.tag && site.image, 'Existing taxonomy and image fixtures required.');
	assert.ok(/\.local$/.test(new URL(site.url).hostname), 'This destructive fixture cleanup is local-site only.');
	const baseline = governance();
	for (const role of ['author', 'editor', 'administrator']) {
		const id = evaluate(`add_filter('pre_wp_mail','__return_true');$id=wp_insert_user(array('user_login'=>'${marker}-${role}','user_pass'=>wp_generate_password(48,true,true),'user_email'=>'${marker}-${role}@example.invalid','role'=>'${role}'));if(is_wp_error($id)){exit(1);}echo wp_json_encode($id);`);
		users.push(id);
		cli(['user', 'meta', 'update', String(id), 'npcink_role_smoke', marker]);
		const post = Number(cli(['post', 'create', '--post_status=draft', `--post_author=${id}`, `--post_title=${marker} ${site.category.name} ${site.tag.name}`, `--post_content=<!-- wp:paragraph --><p>${site.category.name} ${site.tag.name}：合成场景，桌面上的咖啡杯。</p><!-- /wp:paragraph -->\n<!-- wp:image {"id":${site.image.id},"sizeSlug":"large","linkDestination":"none"} --><figure class="wp-block-image size-large"><img src="${site.image.url}" alt="" class="wp-image-${site.image.id}"/></figure><!-- /wp:image -->`, '--porcelain']));
		assert.ok(post > 0);
		posts.push(post);
		console.log(`SETUP: temporary ${role} and owned draft created.`);
	}
	// Provider fixtures run only after the real REST capability/object checks.
	// They neither replace REST responses nor change any user's capabilities.
	const muDir = `${process.env.WP_PATH}/wp-content/mu-plugins`;
	mkdirSync(muDir, { recursive: true });
	const providerFixturePath = `${muDir}/${marker}.php`;
	writeFileSync(providerFixturePath, `<?php
$role_smoke_guard = static function () {
 return isset($_SERVER['HTTP_X_NPCINK_ROLE_SMOKE']) && hash_equals('${marker}', (string) $_SERVER['HTTP_X_NPCINK_ROLE_SMOKE']) && '${marker}' === get_user_meta(get_current_user_id(), 'npcink_role_smoke', true);
};
add_filter('npcink_toolbox_site_knowledge_cloud_request', static function ($handled, $payload) use ($role_smoke_guard) {
 if (!$role_smoke_guard()) { return $handled; }
 $media = 'media_library_search' === ($payload['input']['intent'] ?? '');
 return array('status'=>'ready','results'=>$media ? array(array('post_id'=>${site.image.id},'source_id'=>${site.image.id},'post_type'=>'attachment','source_type'=>'media','title'=>'Synthetic role acceptance candidate','url'=>wp_get_attachment_url(${site.image.id}),'score'=>0.9)) : array(),'media_evidence_items'=>array());
}, 1, 2);
add_filter('npcink_toolbox_image_source_cloud_request', static function ($handled) use ($role_smoke_guard) {
 if (!$role_smoke_guard()) { return $handled; }
 $url=wp_get_attachment_url(${site.image.id});
 return array('status'=>'ready','images'=>array(array('id'=>'role-smoke-candidate','title'=>'Synthetic provider candidate','regular_url'=>$url,'full_url'=>$url,'thumbnail_url'=>$url,'width'=>1200,'height'=>800,'provider'=>'role_smoke','source_type'=>'image_source','source_url'=>'https://example.invalid/role-smoke','license_review_status'=>'human_review_required')));
}, 1);
add_filter('pre_http_request', static function ($pre) use ($role_smoke_guard) {
 return $role_smoke_guard() ? new WP_Error('role_smoke_provider_not_evaluated','Synthetic role acceptance does not evaluate hosted models.') : $pre;
}, 1);
add_action('set_transient', static function ($key) use ($role_smoke_guard) {
 if (!$role_smoke_guard()) { return; }
 $uid=get_current_user_id();$keys=get_user_meta($uid,'npcink_role_smoke_cache',true);$keys=is_array($keys)?$keys:array();$keys[]=$key;update_user_meta($uid,'npcink_role_smoke_cache',array_values(array_unique($keys)));
});
`, { mode: 0o600, flag: 'wx' });
	providerFixture = providerFixturePath;
	browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_BIN || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
	const results = [];
	for (const [index, role] of ['author', 'editor', 'administrator'].entries()) {
		const id = users[index];
		const post = posts[index];
		const original = state(post);
		const auth = evaluate(`$u=get_user_by('id',${id});wp_set_current_user($u->ID);$e=time()+1800;$t=WP_Session_Tokens::get_instance($u->ID)->create($e);echo wp_json_encode(array('user'=>$u->ID,'token'=>$t,'roles'=>$u->roles,'edit_posts'=>current_user_can('edit_posts'),'manage_options'=>current_user_can('manage_options'),'cookies'=>array(array('name'=>LOGGED_IN_COOKIE,'value'=>wp_generate_auth_cookie($u->ID,$e,'logged_in',$t)),array('name'=>AUTH_COOKIE,'value'=>wp_generate_auth_cookie($u->ID,$e,'auth',$t)),array('name'=>SECURE_AUTH_COOKIE,'value'=>wp_generate_auth_cookie($u->ID,$e,'secure_auth',$t)))));`);
		sessions.push(auth);
		assert.deepEqual(auth.roles, [role]);
		assert.equal(auth.edit_posts, true);
		assert.equal(auth.manage_options, role === 'administrator');
		const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 1100 }, extraHTTPHeaders: { 'X-Npcink-Role-Smoke': marker } });
		await context.addCookies(auth.cookies.map((cookie) => ({ ...cookie, url: site.url, httpOnly: true, sameSite: 'Lax', secure: cookie.name.startsWith('wordpress_sec_') })));
		page = await context.newPage();
		page.on('dialog', (dialog) => dialog.accept());
		const errors = [];
		const assets = [];
		const requests = [];
		page.on('pageerror', (error) => errors.push(error.message));
		page.on('request', (request) => { if (request.url().includes('/wp-json/')) requests.push({ path: new URL(request.url()).pathname, method: request.method() }); });
		page.on('response', (response) => {
			const name = new URL(response.url()).pathname.split('/npcink-workflow-toolbox/assets/')[1];
			if (name && /^editor-content-support(?:\.min)?\.js$/.test(name)) assets.push(response.body().then((body) => sha(body) === sha(readFileSync(new URL('../assets/' + name, import.meta.url)))).catch(() => false));
		});
		await page.route('**/wp-json/npcink-toolbox/v1/agent-feedback', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: '{"accepted":true}' }));
		// Only temporary drafts may be saved. Keep global preferences/media and
		// autosave out of this test; the explicit native save below is real.
		await page.route('**/wp-json/wp/v2/**', (route) => {
			const request = route.request();
			if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method()) && !new URL(request.url()).pathname.endsWith(`/posts/${post}`)) return route.abort();
			return route.continue();
		});
		await page.goto(`${site.url}/wp-admin/post.php?post=${post}&action=edit`, { waitUntil: 'domcontentloaded' });
		assert.ok(!page.url().includes('wp-login.php'));
		await openSidebar();
		assert.equal(await page.evaluate(() => window.NpcinkToolboxEditorSupport.canSubmitMetadataHandoff), role === 'administrator' ? '1' : '');
		const roleResult = { role };
		// Taxonomy shortcuts are hidden; exercise retained transport authorization
		// directly and keep native WordPress taxonomy save/readback below.
		for (const [label, intent, field] of [[/Category suggestions|分类建议/, 'category_suggestions', 'category_candidates'], [/Tag suggestions|标签建议/, 'tag_suggestions', 'tag_candidates']]) {
			assert.equal(await page.locator('.npcink-toolbox-editor-support__flow').filter({ hasText: label }).count(), 0);
			const response = await rest('editor/content-support', { intent, post_id: post, title: `${site.category.name} ${site.tag.name}`, content: `${site.category.name} ${site.tag.name}` });
			assert.equal(response.status, 200, `${role}: retained ${intent} REST authorization succeeds.`);
			const candidates = response.body.sections.summary_terms_optimization[field];
			assert.ok(candidates.length > 0, `${role}: existing ${field} returned.`);
			roleResult[intent] = true;
		}
		const plan = await rest('flows/content-metadata-apply-plan', { post_id: post, category_ids: [site.category.term_id], tag_ids: [site.tag.term_id] });
		assert.equal(plan.status, role === 'administrator' ? 200 : 403);
		roleResult.metadata_plan_status = plan.status;
		const foreign = await rest('editor/content-support', { intent: 'category_suggestions', post_id: posts[index === 0 ? 1 : 0], title: 'Synthetic foreign draft', content: site.category.name });
		assert.equal(foreign.status, role === 'author' ? 403 : 200);
		if (role === 'author') assert.equal(foreign.body.code, 'npcink_toolbox_editor_post_context_forbidden');
		roleResult.foreign_draft_status = foreign.status;
		console.log(`PASS: ${role}: metadata plan ${plan.status}; foreign draft ${foreign.status}.`);
		// Exercise the installed image modal and its real site-media REST route.
		await toolList();
		await page.locator('.npcink-toolbox-editor-support__flow').filter({ hasText: /Article images|文章配图/ }).getByRole('button').click();
		const inspector = page.locator('.npcink-toolbox-editor-support__image-inspector');
		await inspector.waitFor();
		await inspector.locator('input[type="search"]').fill(site.image.title);
		const [imageResponse] = await Promise.all([
			page.waitForResponse((response) => new URL(response.url()).pathname.endsWith('/image-candidates') && response.request().method() === 'POST', { timeout: 30000 }),
			page.getByRole('button', { name: /Search site media|搜索站内媒体/ }).click(),
		]);
		assert.equal(imageResponse.status(), 200);
		const imagePayload = await imageResponse.json();
		assert.ok(Array.isArray(imagePayload.images));
		assert.equal(imagePayload.images.length, role === 'author' ? 0 : 1, 'Actual object permissions filter another user’s attachment.');
		roleResult.site_media_candidates = imagePayload.images.length;
		await page.getByRole('button', { name: /External image library|外部图库/ }).click();
		const [externalResponse] = await Promise.all([
			page.waitForResponse((response) => new URL(response.url()).pathname.endsWith('/image-candidates') && response.request().method() === 'POST', { timeout: 30000 }),
			page.getByRole('button', { name: /Search image sources|搜索图片来源/ }).click(),
		]);
		assert.equal(externalResponse.status(), 200);
		assert.equal((await externalResponse.json()).images.length, 1);
		await page.locator('.npcink-toolbox-editor-support__image-card').first().waitFor();
		roleResult.external_image_candidates = 1;
		console.log(`PASS: ${role}: real image route with server provider fixture.`);
		await page.locator('.npcink-toolbox-editor-support__image-modal .components-modal__header button').click();
		await inspector.waitFor({ state: 'hidden' });
		const preflight = await runFlow(/Article check|文章检查/, 'publish_preflight');
		roleResult.article_review_status = preflight.sections.article_language_review?.status;
		await page.locator('.npcink-toolbox-editor-support__discoverability-media button').filter({ hasText: /Preview contextual ALT|预览上下文 ALT/ }).click();
		const alt = page.locator('[data-toolbox-contextual-alt-input]');
		await alt.waitFor();
		assert.ok(await alt.inputValue());
		assert.deepEqual(state(post), original, 'Preview leaves database content/terms unchanged.');
		const editedAlt = `人工审阅咖啡杯 ${role}`;
		await alt.fill(editedAlt);
		const nativeWritesBefore = requests.filter((r) => /\/wp\/v2\/(posts|media)\//.test(r.path) && r.method !== 'GET').length;
		await page.getByRole('button', { name: /Apply ALT to draft|应用 ALT 到草稿|将 ALT 应用到草稿/ }).click();
		await page.waitForFunction((value) => wp.data.select('core/block-editor').getBlocks().some((b) => b.name === 'core/image' && b.attributes.alt === value), editedAlt);
		assert.deepEqual(state(post), original, 'Apply changes visible editor state only.');
		assert.equal(requests.filter((r) => /\/wp\/v2\/(posts|media)\//.test(r.path) && r.method !== 'GET').length, nativeWritesBefore);
		// Choose existing terms through WordPress's actual native panels.
		await page.evaluate(() => {
			const dispatch = wp.data.dispatch('core/editor');
			if (dispatch.openGeneralSidebar) dispatch.openGeneralSidebar('edit-post/document');
			else wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/document');
		});
		const nativePanel = page.locator('.interface-interface-skeleton__sidebar');
		await nativePanel.getByRole('button', { name: /^(Categories|分类目录)$/ }).click();
		await nativePanel.getByRole('checkbox', { name: site.category.name, exact: true }).check();
		await nativePanel.getByRole('button', { name: /^(Tags|标签)$/ }).click();
		await nativePanel.getByRole('combobox').fill(site.tag.name);
		await nativePanel.getByRole('option', { name: site.tag.name, exact: true }).click();
		await page.waitForFunction((tag) => wp.data.select('core/editor').getEditedPostAttribute('tags').includes(tag), site.tag.term_id);
		await page.screenshot({ path: new URL(`editor-native-taxonomy-${role}.png`, output).pathname, fullPage: true });
		assert.deepEqual(state(post), original);
		await page.evaluate(async () => { await wp.data.dispatch('core/editor').savePost(); });
		await page.waitForFunction(() => { const editor = wp.data.select('core/editor'); return !editor.isSavingPost() && editor.didPostSaveRequestSucceed(); });
		const saved = state(post);
		assert.ok(saved.content.includes(editedAlt));
		assert.equal(saved.status, 'draft');
		assert.deepEqual([...saved.categories].sort(), [...new Set([...original.categories, site.category.term_id])].sort());
		assert.deepEqual(saved.tags, [site.tag.term_id]);
		await page.reload({ waitUntil: 'domcontentloaded' });
		await openSidebar();
		assert.ok(await page.evaluate((value) => wp.data.select('core/block-editor').getBlocks().some((b) => b.name === 'core/image' && b.attributes.alt === value), editedAlt));
		assert.equal(evaluate(`echo wp_json_encode(get_post_meta(${site.image.id},'_wp_attachment_image_alt',true));`), site.image.alt);
		assert.deepEqual(governance(), baseline, 'No Core proposal or audit row was added/changed.');
		assert.ok(!requests.some((r) => /proposals\/from-plan|approve-and-execute|contextual-alt-audit/.test(r.path)));
		assert.deepEqual(errors, []);
		assert.ok(assets.length > 0 && (await Promise.all(assets)).every(Boolean));
		await page.screenshot({ path: new URL(`editor-real-role-${role}.png`, output).pathname, fullPage: true });
		results.push({ ...roleResult, native_save_reload: true, attachment_alt_unchanged: true, core_unchanged: true, installed_assets_verified: true });
		console.log(`PASS: ${role}: real permissions, native taxonomy selection, image authorization, ALT apply/save/reload; Core and media ALT unchanged.`);
		await context.close();
		page = null;
	}
	receipt = { contract: 'editor_real_roles_acceptance.v1', date: new Date().toISOString(), revision: execFileSync('git', ['rev-parse', 'HEAD'], { encoding: 'utf8' }).trim(), editor_source_sha256: sha(readFileSync(new URL('../assets/editor-content-support.js', import.meta.url))), results, substituted_toolbox_responses: false, server_provider_fixture: true, hosted_model_calls_blocked: true, injected_assets_or_capabilities: false, feedback_suppressed: true, native_taxonomy_selection: 'WordPress Categories checkbox and existing Tags autocomplete option', production_deployed: false };
} catch (error) {
	if (page) await page.screenshot({ path: new URL('editor-real-roles-failure.png', output).pathname, fullPage: true }).catch(() => {});
	throw error;
} finally {
	const cleanup = await Promise.allSettled([
		browser ? browser.close() : Promise.resolve(),
		...posts.map((post) => Promise.resolve().then(() => cli(['post', 'delete', String(post), '--force']))),
		...sessions.map((auth) => Promise.resolve().then(() => cli(['eval', `$m=WP_Session_Tokens::get_instance(${auth.user});$m->destroy(${JSON.stringify(auth.token)});if($m->verify(${JSON.stringify(auth.token)})){exit(1);}`]))),
		...users.map((user) => Promise.resolve().then(() => cli(['eval', `$keys=get_user_meta(${user},'npcink_role_smoke_cache',true);foreach(is_array($keys)?$keys:array() as $key){delete_transient($key);if(false!==get_transient($key)){exit(1);}}`]))),
	]);
	// Delete users only after posts and their sessions have been removed.
	const userCleanup = await Promise.allSettled(users.map((user) => Promise.resolve().then(() => cli(['user', 'delete', String(user), '--yes']))));
	if (providerFixture) rmSync(providerFixture, { force: true });
	assert.ok(cleanup.every((result) => result.status === 'fulfilled'), 'Every cleanup step must succeed.');
	assert.ok(userCleanup.every((result) => result.status === 'fulfilled'), 'Every temporary user must be removed.');
	assert.equal(evaluate(`echo wp_json_encode(count(get_users(array('search'=>'${marker}*','search_columns'=>array('user_login')))));`), 0);
	if (posts.length) assert.equal(evaluate(`echo wp_json_encode(count(get_posts(array('post__in'=>array(${posts.join(',')}),'post_status'=>'any','numberposts'=>-1))));`), 0);
	if (receipt) writeFileSync(receiptPath, JSON.stringify({ ...receipt, temporary_posts_users_sessions_removed: true }, null, 2) + '\n');
	console.log('CLEANUP: temporary drafts, users, sessions, tracked transients and provider fixture removed.');
}
