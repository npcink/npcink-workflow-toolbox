#!/usr/bin/env node
/** Browser smoke for one confirmed exact-manifest foreground optimization batch. */

import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';

function env(name, fallback) {
	return process.env[name] || fallback;
}

function assert(condition, message) {
	if (!condition) throw new Error(message);
	console.log(`PASS: ${message}`);
}

function wpCli(args) {
	return execFileSync(
		env('WP_CLI_PHP', `${process.env.HOME}/Library/Application Support/Local/lightning-services/php-8.5.3+1/bin/darwin-arm64/bin/php`),
		[
			'-d', 'display_errors=0', '-d', 'error_reporting=8191',
			'-d', `mysqli.default_socket=${env('WP_DB_SOCKET', `${process.env.HOME}/Library/Application Support/Local/run/NPb24Zg9g/mysql/mysqld.sock`)}`,
			env('WP_CLI_BIN', '/opt/homebrew/bin/wp'),
			`--path=${env('WP_PATH', '/Users/muze/Local Sites/magick-ai/app/public')}`,
			'--no-color', ...args,
		],
		{ encoding: 'utf8' }
	).trim();
}

async function loadPlaywright() {
	try {
		return await import('playwright');
	} catch (error) {
		const require = createRequire(import.meta.url);
		const resolved = require.resolve('playwright', { paths: String(process.env.NODE_PATH || '').split(':').filter(Boolean) });
		const module = await import(pathToFileURL(resolved).href);
		return module.chromium ? module : module.default;
	}
}

function authCookies(baseUrl) {
	const cookieJson = wpCli(['eval', `
$users=get_users(array('role'=>'administrator','number'=>1,'orderby'=>'ID','order'=>'ASC'));
$user=$users ? $users[0] : null; if (!$user) { exit(1); }
$expiration=time()+DAY_IN_SECONDS;
echo wp_json_encode(array(
	array('name'=>AUTH_COOKIE,'value'=>wp_generate_auth_cookie($user->ID,$expiration,'auth')),
	array('name'=>SECURE_AUTH_COOKIE,'value'=>wp_generate_auth_cookie($user->ID,$expiration,'secure_auth')),
	array('name'=>LOGGED_IN_COOKIE,'value'=>wp_generate_auth_cookie($user->ID,$expiration,'logged_in'))
));`]);
	const { hostname, protocol } = new URL(baseUrl);
	return JSON.parse(cookieJson).map((cookie) => ({
		name: String(cookie.name || ''), value: String(cookie.value || ''), domain: hostname, path: '/',
		httpOnly: true, secure: protocol === 'https:', sameSite: 'Lax',
	}));
}

function forbiddenRequests(requests) {
	return requests.filter((request) => /proposals|governance-core|approve-and-execute|run-read-ability/.test(request.url));
}

const baseUrl = env('WP_BASE_URL', 'https://magick-ai.local').replace(/\/$/, '');
const fixtureDate = '2001-01-03';
const artifactDir = env('SMOKE_ARTIFACT_DIR', 'build/smoke');
const screenshotPath = `${artifactDir}/media-optimization-exact-manifest-browser.png`;
mkdirSync(artifactDir, { recursive: true });

let browser = null;
let page = null;
let attachmentId = 0;
const requests = [];
try {
	const { chromium } = await loadPlaywright();
	const launch = { headless: process.env.HEADLESS !== '0' };
	if (process.env.BROWSER_EXECUTABLE) launch.executablePath = process.env.BROWSER_EXECUTABLE;
	else if (existsSync('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome')) launch.executablePath = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
	browser = await chromium.launch(launch);
	attachmentId = Number(wpCli(['eval', `
$id=0; $path=''; try {
	$upload=wp_upload_dir(); if (!empty($upload['error'])) throw new RuntimeException((string)$upload['error']);
	$path=trailingslashit($upload['path']).'toolbox-exact-manifest-'.wp_generate_password(8,false,false).'.png';
	if (!function_exists('imagecreatetruecolor')) throw new RuntimeException('GD is unavailable.');
	$im=imagecreatetruecolor(640,360); $bg=imagecolorallocate($im,24,116,92); imagefilledrectangle($im,0,0,640,360,$bg);
	if (!imagepng($im,$path)) throw new RuntimeException('Fixture image write failed.');
	$inserted=wp_insert_attachment(array('post_mime_type'=>'image/png','post_title'=>'Toolbox exact manifest','post_status'=>'inherit','post_date'=>'${fixtureDate} 12:00:00','post_date_gmt'=>'${fixtureDate} 12:00:00'),$path,0,true);
	if (is_wp_error($inserted)) throw new RuntimeException($inserted->get_error_message()); $id=(int)$inserted;
	require_once ABSPATH.'wp-admin/includes/image.php'; $metadata=wp_generate_attachment_metadata($id,$path); wp_update_attachment_metadata($id,$metadata); echo $id;
} catch (Throwable $error) { if ($id>0) wp_delete_attachment($id,true); elseif ($path && file_exists($path)) wp_delete_file($path); fwrite(STDERR,$error->getMessage()); exit(1); }` ]));
	assert(attachmentId > 0, 'The smoke created one disposable Media Library image.');
	const sourceBytes = readFileSync(wpCli(['eval', `echo get_attached_file(${attachmentId});`]));
	const artifact = {
		artifact_id: 'art_fedcba9876543210fedcba9876543210', expires_at: '2099-01-01T00:00:00Z',
		mime_type: 'image/png', format: 'png', width: 640, height: 360, filesize_bytes: sourceBytes.length,
		sha256: createHash('sha256').update(sourceBytes).digest('hex'),
		suggested_filename: 'toolbox-exact-manifest.png', filename_basis: 'browser_fixture',
		processing_warnings: [], transform_facts: { fixture: true },
	};

	const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 1100 } });
	await context.addCookies(authCookies(baseUrl));
	await context.route('**/wp-json/npcink-toolbox/v1/media-optimization-health', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ready: true }) }));
	let previewCount = 0;
	await context.route(/\/wp-json\/npcink-toolbox\/v1\/media-derivative-preview(?:\/.*)?$/, async (route) => {
		const requestUrl = new URL(route.request().url());
		if (route.request().method() === 'POST' && requestUrl.pathname.endsWith('/media-derivative-preview')) {
			previewCount += 1;
			await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ run_id: `exact-manifest-run-${previewCount}` }) });
			return;
		}
		const runMatch = requestUrl.pathname.match(/exact-manifest-run-(\d+)/);
		const runNumber = runMatch ? Number(runMatch[1]) : 0;
		if (requestUrl.pathname.endsWith('/result')) {
			const payload = runNumber === 1
				? { cloud_result: { artifact }, local_review: { method: 'POST', endpoint: `${baseUrl}/wp-json/npcink-toolbox/v1/media-derivative-local-review/${artifact.artifact_id}`, artifact } }
				: { optimization: { status: 'skipped', decision_reasons: ['browser_fixture_skip'] } };
			await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(payload) });
			return;
		}
		await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ status: 'completed' }) });
	});
	await context.route('**/wp-json/npcink-toolbox/v1/media-derivative-local-review/*', (route) => route.fulfill({ status: 200, contentType: 'image/png', body: sourceBytes }));

	page = await context.newPage();
	page.on('request', (request) => {
		if (request.url().includes('/wp-json/')) requests.push({ url: request.url(), method: request.method(), body: request.postData() || '' });
	});
	await page.goto(`${baseUrl}/wp-admin/admin.php?page=npcink-toolbox&tab=image&tool=batch-optimize`, { waitUntil: 'domcontentloaded', timeout: 45000 });
	assert(!page.url().includes('wp-login.php'), 'The browser opened Toolbox as an administrator.');
	await page.waitForFunction(() => Boolean(window.NpcinkToolbox?.restUrl), null, { timeout: 15000 });
	await page.evaluate(({ date }) => {
		const form = document.querySelector('form[data-toolbox-tool-panel="media-batch-optimize"]');
		const setValue = (name, value) => {
			const field = form.querySelector(`[name="${name}"]`); field.value = value; field.dispatchEvent(new Event('change', { bubbles: true }));
		};
		setValue('batch_scope_preset', 'custom'); setValue('batch_image_type', 'png'); setValue('batch_date_from', date); setValue('batch_date_to', date);
	}, { date: fixtureDate });
	await page.locator('[data-toolbox-build-media-batch-plan]').click();
	await page.waitForFunction((id) => {
		const form = document.querySelector('form[data-toolbox-tool-panel="media-batch-optimize"]');
		const states = form?.__npcinkMediaDerivativeBatchStates;
		return Array.isArray(states) && states.some((state) => Number(state?.batchCandidate?.attachment_id) === id && state?.localReviewStatus === 'verified');
	}, attachmentId, { timeout: 30000 });
	const frozen = await page.evaluate(() => {
		const form = document.querySelector('form[data-toolbox-tool-panel="media-batch-optimize"]');
		const batch = form.__npcinkMediaOptimizationBatch;
		const start = form.querySelector('[data-toolbox-submit-media-batch-proposals]');
		return { batchId: batch.batch_id, manifestDigest: batch.manifest_digest, itemCount: batch.items.length, startReady: !start.hidden && !start.disabled };
	});
	assert(/^media_opt_[A-Za-z0-9]+$/.test(frozen.batchId) && frozen.manifestDigest && frozen.itemCount === 1, 'The check freezes one exact manifest and digest.');
	assert(frozen.startReady, 'One Start optimization action becomes available after sample verification.');
	await page.locator('[data-toolbox-submit-media-batch-proposals]').click();
	await page.waitForFunction(() => {
		const form = document.querySelector('form[data-toolbox-tool-panel="media-batch-optimize"]');
		return form?.__npcinkMediaOptimizationBatch?.status === 'completed';
	}, null, { timeout: 30000 });
	const completed = await page.evaluate(() => document.querySelector('form[data-toolbox-tool-panel="media-batch-optimize"]').__npcinkMediaOptimizationBatch);
	assert(completed.summary.skipped === 1 && completed.summary.success === 0, 'Foreground execution records the deterministic safe skip and completes the batch.');
	const confirmationRequests = requests.filter((request) => request.method === 'POST' && request.url.endsWith(`/${frozen.batchId}/confirm`));
	const confirmationBody = JSON.parse(confirmationRequests[0]?.body || '{}');
	assert(confirmationRequests.length === 1 && confirmationBody.confirm === true && confirmationBody.manifest_digest === frozen.manifestDigest, 'The browser sends one confirmation bound to the frozen manifest digest.');
	const completionRequests = requests.filter((request) => request.method === 'POST' && request.url.includes(`/${frozen.batchId}/items/${attachmentId}/complete`));
	assert(completionRequests.length === 1 && JSON.parse(completionRequests[0].body).status === 'skipped', 'The browser completes the exact item in the foreground without a media write.');
	assert(forbiddenRequests(requests).length === 0, 'The exact-manifest flow does not call Core proposals, approval execution, or Adapter read routes.');
	await page.screenshot({ path: screenshotPath, fullPage: true });
	console.log(`PASS: Exact-manifest browser screenshot: ${screenshotPath}`);
} catch (error) {
	if (page) {
		const failurePath = `${artifactDir}/media-optimization-exact-manifest-browser-failure.png`;
		await page.screenshot({ path: failurePath, fullPage: true }).catch(() => {});
		const text = await page.locator('body').innerText().catch(() => '');
		console.error(`FAIL: Exact-manifest browser screenshot: ${failurePath}`);
		console.error(`FAIL: Exact-manifest browser visible text: ${text.replace(/\s+/g, ' ').trim().slice(0, 1400)}`);
		console.error(`FAIL: Exact-manifest browser REST requests: ${JSON.stringify(requests, null, 2)}`);
	}
	throw error;
} finally {
	if (browser) await browser.close();
	if (attachmentId > 0) {
		wpCli(['eval', `$id=${attachmentId}; $batches=get_option('npcink_toolbox_media_optimization_batches',array()); $batches=array_values(array_filter((array)$batches,static function($batch)use($id){foreach((array)($batch['items']??array())as$item){if($id===(int)($item['attachment_id']??0))return false;}return true;})); update_option('npcink_toolbox_media_optimization_batches',$batches,false); if(!wp_delete_attachment($id,true)){fwrite(STDERR,'Attachment cleanup failed.');exit(1);}`]);
	}
}

console.log(`PASS: Exact-manifest foreground browser smoke completed at ${baseUrl}.`);
