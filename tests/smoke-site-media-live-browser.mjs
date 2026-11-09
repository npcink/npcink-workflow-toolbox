// Optional live local-site acceptance. No search response, provider result,
// capability or installed asset substitution. Never rebuilds an index.
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { createHash, randomBytes } from 'node:crypto';
import { createRequire } from 'node:module';
import { mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';

for (const key of ['WP_PATH', 'WP_CLI_PHP', 'WP_CLI_BIN', 'WP_DB_SOCKET']) assert.ok(process.env[key], `Set ${key}.`);
const require = createRequire(import.meta.url);
const { chromium } = require(require.resolve('playwright', process.env.NODE_PATH ? { paths: [process.env.NODE_PATH] } : undefined));
const sha = value => createHash('sha256').update(value).digest('hex');
function cli(args) {
	try {
		return execFileSync(process.env.WP_CLI_PHP, ['-d', 'display_errors=stderr', '-d', 'error_reporting=8191', '-d', `mysqli.default_socket=${process.env.WP_DB_SOCKET}`, process.env.WP_CLI_BIN, `--path=${process.env.WP_PATH}`, '--no-color', ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'], timeout: 60000 }).trim();
	} catch { throw new Error('WordPress CLI step failed; credentials and arguments withheld.'); }
}
const evaluate = code => JSON.parse(cli(['eval', code]));
const output = new URL('../output/playwright/', import.meta.url);
mkdirSync(output, { recursive: true });
const receiptPath = new URL('site-media-live-evidence.json', output);
rmSync(receiptPath, { force: true });
const marker = `media-live-${randomBytes(8).toString('hex')}`;
const timingPath = `/tmp/${marker}.jsonl`;
const governance = () => evaluate(`global $wpdb;$out=array();foreach(array('proposals','audit_log')as$s){$rows=$wpdb->get_results('SELECT * FROM '.$wpdb->prefix.'npcink_governance_core_'.$s.' ORDER BY id',ARRAY_A);if($wpdb->last_error){exit(1);}$out[$s]=hash('sha256',wp_json_encode($rows));}echo wp_json_encode($out);`);
let auth, postId, observerPath, browser, page, receipt;
try {
	const site = evaluate(`echo wp_json_encode(array('url'=>home_url(),'root'=>realpath(NPCINK_TOOLBOX_DIR)));`);
	assert.ok(/\.local$/.test(new URL(site.url).hostname), 'Disposable acceptance requires a .local site.');
	assert.equal(site.root, new URL('..', import.meta.url).pathname.replace(/\/$/, ''));
	const baseline = governance();
	auth = evaluate(`$u=get_users(array('role'=>'administrator','number'=>1))[0];$e=time()+1800;$t=WP_Session_Tokens::get_instance($u->ID)->create($e);echo wp_json_encode(array('user'=>$u->ID,'token'=>$t,'cookies'=>array(array('name'=>LOGGED_IN_COOKIE,'value'=>wp_generate_auth_cookie($u->ID,$e,'logged_in',$t)),array('name'=>AUTH_COOKIE,'value'=>wp_generate_auth_cookie($u->ID,$e,'auth',$t)),array('name'=>SECURE_AUTH_COOKIE,'value'=>wp_generate_auth_cookie($u->ID,$e,'secure_auth',$t)))));`);
	postId = Number(cli(['post', 'create', '--post_status=draft', `--post_author=${auth.user}`, `--post_title=${marker}`, '--post_content=<!-- wp:paragraph --><p>WordPress 编辑工作台与文章配图，仅供开发验收。</p><!-- /wp:paragraph -->', '--porcelain']));
	assert.ok(postId > 0);
	const original = cli(['post', 'get', String(postId), '--field=post_content']);
	const muDir = `${process.env.WP_PATH}/wp-content/mu-plugins`;
	mkdirSync(muDir, { recursive: true });
	writeFileSync(timingPath, '', { mode: 0o600, flag: 'wx' });
	const candidateObserver = `${muDir}/${marker}.php`;
	// Observer only: preserve request/response values and emit safe span fields.
	writeFileSync(candidateObserver, `<?php
$media_live_guard=static function(){return isset($_SERVER['HTTP_X_NPCINK_MEDIA_LIVE'])&&hash_equals('${marker}',(string)$_SERVER['HTTP_X_NPCINK_MEDIA_LIVE'])&&${auth.user}===get_current_user_id();};
$media_live_starts=array();
add_filter('pre_http_request',static function($pre,$args,$url)use($media_live_guard,&$media_live_starts){if($media_live_guard()){$media_live_starts[hash('sha256',$url)]=microtime(true);}return $pre;},PHP_INT_MAX,3);
add_action('http_api_debug',static function($response,$context,$transport,$args,$url)use($media_live_guard,&$media_live_starts){
 if(!$media_live_guard()||'response'!==$context||'/v1/runtime/execute'!==wp_parse_url($url,PHP_URL_PATH)){return;}
 $input=json_decode((string)($args['body']??''),true);$body=is_wp_error($response)?array():json_decode(wp_remote_retrieve_body($response),true);$result=$body['data']['result']??array();
 $row=array('ability'=>$input['ability_name']??null,'elapsed_ms'=>round(1000*(microtime(true)-($media_live_starts[hash('sha256',$url)]??microtime(true)))),'timeout_seconds'=>$args['timeout']??null,'status'=>is_wp_error($response)?$response->get_error_code():wp_remote_retrieve_response_code($response),'run_id'=>$body['data']['run_id']??$body['run_id']??null,'result_status'=>$result['status']??null,'results'=>array_map(static fn($i)=>array_intersect_key($i,array_flip(array('post_id','source_id','source_type'))),$result['results']??array()));
 if(is_file('${timingPath}')){file_put_contents('${timingPath}',wp_json_encode($row)."\\n",FILE_APPEND|LOCK_EX);}
},10,5);
`, { mode: 0o600, flag: 'wx' });
	observerPath = candidateObserver;
	browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_BIN || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
	const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 1100 }, extraHTTPHeaders: { 'X-Npcink-Media-Live': marker } });
	await context.addCookies(auth.cookies.map(cookie => ({ ...cookie, url: site.url, httpOnly: true, sameSite: 'Lax', secure: cookie.name.startsWith('wordpress_sec_') })));
	page = await context.newPage();
	const errors = [], writes = [], assets = [];
	let searches = 0;
	page.on('dialog', dialog => dialog.accept());
	page.on('pageerror', error => errors.push(error.message));
	page.on('response', response => {
		const name = new URL(response.url()).pathname.split('/npcink-workflow-toolbox/assets/')[1];
		if (name && /^editor-content-support(?:\.min)?\.js$/.test(name)) assets.push(response.body().then(body => sha(body) === sha(readFileSync(new URL('../assets/' + name, import.meta.url)))).catch(() => false));
	});
	page.on('request', request => {
		const path = new URL(request.url()).pathname;
		if (path.endsWith('/image-candidates') && request.method() === 'POST') searches++;
		if (path.includes('/wp-json/') && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method())) writes.push(path);
	});
	await page.route('**/wp-json/npcink-toolbox/v1/agent-feedback', route => route.fulfill({ status: 200, contentType: 'application/json', body: '{"accepted":true}' }));
	await page.route('**/wp-json/npcink-toolbox/v1/editor/content-support', route => route.abort()); // Prevent unrelated automatic prefetch.
	await page.route('**/wp-json/wp/v2/**', route => {
		if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(route.request().method()) && !new URL(route.request().url()).pathname.endsWith(`/posts/${postId}`)) return route.abort();
		return route.continue();
	});
	await page.goto(`${site.url}/wp-admin/post.php?post=${postId}&action=edit`, { waitUntil: 'domcontentloaded' });
	await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId());
	await page.waitForFunction(() => wp.plugins.getPlugin('npcink-toolbox-editor-content-support'));
	await page.evaluate(() => {
		wp.data.dispatch('core/editor').lockPostAutosaving('site-media-live');
		wp.data.dispatch('core/preferences').set('core/edit-post', 'welcomeGuide', false);
		const dispatch=wp.data.dispatch('core/editor');
		if(dispatch.openGeneralSidebar) dispatch.openGeneralSidebar('npcink-toolbox-editor-content-support/npcink-content-support-sidebar');
		else wp.data.dispatch('core/edit-post').openGeneralSidebar('npcink-toolbox-editor-content-support/npcink-content-support-sidebar');
	});
	await page.evaluate(() => wp.data.dispatch('core/block-editor').selectBlock(wp.data.select('core/block-editor').getBlocks()[0].clientId));
	await page.getByRole('button', { name: /Find image for selected paragraph|为当前段落找配图/ }).click();
	const inspector = page.locator('.npcink-toolbox-editor-support__image-inspector');
	await inspector.waitFor();
	await inspector.locator('input[type="search"]').fill(process.env.MEDIA_SEARCH_QUERY || 'WordPress AI 插件系列配图');
	const started=Date.now();
	const [response] = await Promise.all([
		page.waitForResponse(response => new URL(response.url()).pathname.endsWith('/image-candidates') && response.request().method()==='POST', { timeout: 50000 }),
		page.getByRole('button', { name: /Search site media|搜索站内媒体/ }).click(),
	]);
	const elapsed=Date.now()-started;
	assert.equal(response.status(),200);
	const result=await response.json();
	assert.equal(searches,1,'One explicit live query; no automatic retry.');
	assert.equal(cli(['post','get',String(postId),'--field=post_content']),original);
	assert.ok(Array.isArray(result.images));
	let adopted=false;
	if(result.images.length) {
		const first=result.images[0];
		const imageId=Number(first.attachment_id);
		assert.ok(imageId>0);
		const mediaBefore=cli(['post','get',String(imageId),'--format=json']);
		const mediaAltBefore=evaluate(`echo wp_json_encode(get_post_meta(${imageId},'_wp_attachment_image_alt',true));`);
		await page.locator('.npcink-toolbox-editor-support__image-card').first().click();
		await page.getByRole('button',{name:/Use existing media|使用现有媒体/}).click();
		await page.waitForFunction(id=>wp.data.select('core/block-editor').getBlocks().some(b=>b.name==='core/image'&&b.attributes.id===id),imageId);
		assert.equal(cli(['post','get',String(postId),'--field=post_content']),original,'Adoption does not save.');
		await page.evaluate(async()=>{await wp.data.dispatch('core/editor').savePost();});
		await page.waitForFunction(()=>{const e=wp.data.select('core/editor');return !e.isSavingPost()&&e.didPostSaveRequestSucceed();});
		await page.reload({waitUntil:'domcontentloaded'});
		await page.waitForFunction(id=>window.wp?.data?.select('core/block-editor')?.getBlocks().some(b=>b.name==='core/image'&&b.attributes.id===id),imageId);
		assert.equal(cli(['post','get',String(imageId),'--format=json']),mediaBefore);
		assert.equal(evaluate(`echo wp_json_encode(get_post_meta(${imageId},'_wp_attachment_image_alt',true));`),mediaAltBefore);
		assert.equal(cli(['post','get',String(postId),'--field=post_status']),'draft');
		adopted=true;
	}
	assert.deepEqual(governance(),baseline);
	assert.deepEqual(errors,[]);
	assert.ok(assets.length>0&&(await Promise.all(assets)).every(Boolean));
	assert.ok(!writes.some(path=>/proposals\/from-plan|approve-and-execute|\/media\//.test(path)));
	await page.screenshot({path:new URL('site-media-live.png',output).pathname,fullPage:true});
	const spans=readFileSync(timingPath,'utf8').trim().split('\n').filter(Boolean).map(line=>JSON.parse(line));
	const rawIds=[...new Set(spans.flatMap(span=>span.results.map(item=>Number(item.source_id||item.post_id))).filter(Boolean))];
	const localIdentity=rawIds.length?evaluate(`$out=array();foreach(array(${rawIds.join(',')})as$id){$p=get_post($id);$out[]=array('id'=>$id,'exists'=>(bool)$p,'type'=>$p?$p->post_type:null);}echo wp_json_encode($out);`):[];
	receipt={contract:'site_media_live_browser.v1',acceptance:adopted?'complete':'partial_no_local_candidate',date:new Date().toISOString(),revision:execFileSync('git',['rev-parse','HEAD'],{encoding:'utf8'}).trim(),source_sha256:sha(readFileSync(new URL('../assets/editor-content-support.js',import.meta.url))),elapsed_ms:elapsed,http_status:response.status(),result_status:result.status,candidate_count:result.images.length,live_search_requests:searches,spans,local_identity:localIdentity,native_adopt_save_reload:adopted,core_unchanged:true,substituted_runtime:false,feedback_suppressed:true,production_deployed:false};
	console.log(`${adopted?'COMPLETE':'PARTIAL (no local candidate)'}: ${elapsed}ms; ${result.images.length} local candidates; adopt/save/reload=${adopted}.`);
} catch(error) {
	if(page) await page.screenshot({path:new URL('site-media-live-failure.png',output).pathname,fullPage:true}).catch(()=>{});
	throw error;
} finally {
	const cleanup=await Promise.allSettled([
		browser?browser.close():Promise.resolve(),
		Promise.resolve().then(()=>{if(postId)cli(['post','delete',String(postId),'--force']);}),
		Promise.resolve().then(()=>{if(auth)cli(['eval',`$m=WP_Session_Tokens::get_instance(${auth.user});$m->destroy(${JSON.stringify(auth.token)});if($m->verify(${JSON.stringify(auth.token)})){exit(1);}`]);}),
	]);
	if(observerPath)rmSync(observerPath,{force:true});
	rmSync(timingPath,{force:true});
	assert.ok(cleanup.every(item=>item.status==='fulfilled'),'All cleanup must succeed.');
	if(postId)assert.equal(evaluate(`echo wp_json_encode((bool)get_post(${postId}));`),false);
	if(receipt)writeFileSync(receiptPath,JSON.stringify({...receipt,temporary_draft_session_observer_removed:true},null,2)+'\n');
	console.log('CLEANUP: temporary draft, session and timing observer removed.');
}
