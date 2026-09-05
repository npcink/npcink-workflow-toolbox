#!/usr/bin/env node
/** Browser proof for the one-click media optimization check and preview flow. */

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

const baseUrl = env('WP_BASE_URL', 'https://magick-ai.local').replace(/\/$/, '');
const fixtureDate = '2001-01-02';
let browser = null;
let page = null;
let attachmentId = 0;
let sourceBytes = null;
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
	$path=trailingslashit($upload['path']).'toolbox-one-click-preview-'.wp_generate_password(8,false,false).'.png';
	if (!function_exists('imagecreatetruecolor')) throw new RuntimeException('GD is unavailable.');
	$im=imagecreatetruecolor(640,360); $bg=imagecolorallocate($im,32,94,150); imagefilledrectangle($im,0,0,640,360,$bg);
	if (!imagepng($im,$path)) throw new RuntimeException('Fixture image write failed.');
	$inserted=wp_insert_attachment(array('post_mime_type'=>'image/png','post_title'=>'Toolbox one-click preview','post_status'=>'inherit','post_date'=>'${fixtureDate} 12:00:00','post_date_gmt'=>'${fixtureDate} 12:00:00'),$path,0,true);
	if (is_wp_error($inserted)) throw new RuntimeException($inserted->get_error_message()); $id=(int)$inserted;
	require_once ABSPATH.'wp-admin/includes/image.php'; $metadata=wp_generate_attachment_metadata($id,$path);
	if (!is_array($metadata) || (!wp_update_attachment_metadata($id,$metadata) && wp_get_attachment_metadata($id)!==$metadata)) throw new RuntimeException('Attachment metadata generation failed.');
	echo $id;
} catch (Throwable $error) { if ($id>0) wp_delete_attachment($id,true); elseif ($path && file_exists($path)) wp_delete_file($path); fwrite(STDERR,$error->getMessage()); exit(1); }` ]));
	assert(attachmentId > 0, 'Temporary browser-smoke attachment was created after Playwright launched.');
	sourceBytes = readFileSync(wpCli(['eval', `echo get_attached_file(${attachmentId});`]));

	const context = await browser.newContext({ ignoreHTTPSErrors: true });
	await context.addCookies(authCookies(baseUrl));
	await context.route('**/wp-json/npcink-toolbox/v1/media-optimization-health', async (route) => {
		await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ready: true }) });
	});
	const artifact = {
		artifact_id: 'art_0123456789abcdef0123456789abcdef',
		expires_at: '2099-01-01T00:00:00Z',
		mime_type: 'image/png',
		format: 'png',
		width: 640,
		height: 360,
		filesize_bytes: sourceBytes.length,
		sha256: createHash('sha256').update(sourceBytes).digest('hex'),
		suggested_filename: 'toolbox-browser-preview.png',
		filename_basis: 'browser_fixture',
		processing_warnings: [],
		transform_facts: { fixture: true },
	};
	await context.route(/\/wp-json\/npcink-toolbox\/v1\/media-derivative-preview(?:\/.*)?$/, async (route) => {
		const requestUrl = new URL(route.request().url());
		if (route.request().method() === 'POST' && requestUrl.pathname.endsWith('/media-derivative-preview')) {
			await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ run_id: 'browser-preview-run' }) });
			return;
		}
		if (requestUrl.pathname.endsWith('/media-derivative-preview/browser-preview-run/result')) {
			await route.fulfill({
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify({
					cloud_result: { artifact },
					local_review: {
						method: 'POST',
						endpoint: `${baseUrl}/wp-json/npcink-toolbox/v1/media-derivative-local-review/${artifact.artifact_id}`,
						artifact,
					},
				}),
			});
			return;
		}
		await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ status: 'completed' }) });
	});
	await context.route('**/wp-json/npcink-toolbox/v1/media-derivative-local-review/*', async (route) => {
		await route.fulfill({ status: 200, contentType: 'image/png', body: sourceBytes });
	});
	page = await context.newPage();
	page.on('request', (request) => requests.push({ url: request.url(), method: request.method(), postData: request.postData() || '' }));
	await page.goto(`${baseUrl}/wp-admin/admin.php?page=npcink-toolbox&tab=image&tool=batch-optimize`, { waitUntil: 'domcontentloaded', timeout: 45000 });
	assert(!page.url().includes('wp-login.php'), 'Browser opened the Toolbox admin surface as an administrator.');
	await page.waitForFunction(() => Boolean(window.NpcinkToolbox?.restUrl), null, { timeout: 15000 });

	await page.evaluate(({ date }) => {
		const form = document.querySelector('form[data-toolbox-tool-panel="media-batch-optimize"]');
		if (!(form instanceof HTMLFormElement)) throw new Error('One-click media optimization form is missing.');
		const setValue = (name, value) => {
			const field = form.querySelector(`[name="${name}"]`);
			if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement)) throw new Error(`Missing field: ${name}`);
			field.value = String(value); field.dispatchEvent(new Event('change', { bubbles: true }));
		};
		setValue('batch_scope_preset', 'custom');
		setValue('batch_image_type', 'png');
		setValue('batch_date_from', date);
		setValue('batch_date_to', date);
		const nativeCreate = URL.createObjectURL.bind(URL);
		const nativeRevoke = URL.revokeObjectURL.bind(URL);
		window.__npcinkMediaObjectUrls = { created: [], revoked: [] };
		URL.createObjectURL = (blob) => { const url = nativeCreate(blob); window.__npcinkMediaObjectUrls.created.push(url); return url; };
		URL.revokeObjectURL = (url) => { window.__npcinkMediaObjectUrls.revoked.push(String(url)); return nativeRevoke(url); };
	}, { date: fixtureDate });

	const start = page.locator('[data-toolbox-submit-media-batch-proposals]');
	assert(await start.isHidden(), 'Start optimization is hidden before the read-only check.');
	await page.locator('[data-toolbox-build-media-batch-plan]').click();
	await page.waitForFunction((id) => {
		const form = document.querySelector('form[data-toolbox-tool-panel="media-batch-optimize"]');
		const states = form?.__npcinkMediaDerivativeBatchStates;
		return Array.isArray(states) && states.some((state) => Number(state?.batchCandidate?.attachment_id) === id && state?.localReviewStatus === 'verified');
	}, attachmentId, { timeout: 30000 });

	const ui = await page.evaluate((id) => {
		const form = document.querySelector('form[data-toolbox-tool-panel="media-batch-optimize"]');
		const state = form.__npcinkMediaDerivativeBatchStates.find((item) => Number(item?.batchCandidate?.attachment_id) === id);
		return {
			batchId: form.__npcinkMediaOptimizationBatch?.batch_id || '',
			localReviewStatus: state.localReviewStatus,
			artifactKeys: Object.keys(state.localReview?.artifact || {}),
			objectUrls: window.__npcinkMediaObjectUrls,
			startButtons: document.querySelectorAll('[data-toolbox-submit-media-batch-proposals]:not([hidden])').length,
			comparisonImages: Array.from(document.querySelectorAll('[data-toolbox-media-batch-plan] img')).map((image) => [image.naturalWidth, image.naturalHeight]),
		};
	}, attachmentId);
	const expectedLocalArtifactKeys = ['artifact_id','expires_at','mime_type','format','width','height','filesize_bytes','sha256','suggested_filename','filename_basis','processing_warnings','transform_facts'];
	assert(ui.batchId && ui.localReviewStatus === 'verified', 'The local manifest check produced one verified representative preview.');
	assert(JSON.stringify(ui.artifactKeys) === JSON.stringify(expectedLocalArtifactKeys), 'The preview uses the exact Addon-verified local12 artifact, including transform_facts.');
	assert(ui.startButtons === 1, 'Exactly one Start optimization confirmation is visible after sample verification.');
	assert(ui.comparisonImages.length === 2 && ui.comparisonImages.every(([width, height]) => width === 640 && height === 360), 'Original and optimized preview images decode at the same dimensions.');
	assert(ui.objectUrls.created.length >= 1 && JSON.stringify(ui.objectUrls.created) === JSON.stringify(ui.objectUrls.revoked), 'Every preview object URL is revoked after image settlement.');
	assert(requests.some((request) => request.method === 'POST' && new URL(request.url).pathname.endsWith('/npcink-toolbox/v1/media-optimization-manifest')), 'The browser builds the candidate list through the local read-only manifest route.');
	assert(requests.some((request) => request.method === 'POST' && new URL(request.url).pathname.endsWith('/npcink-toolbox/v1/media-optimization-batches')), 'The browser freezes the exact local batch manifest before confirmation.');
	assert(requests.some((request) => request.url.includes('/npcink-toolbox/v1/media-derivative-preview')), 'The browser checks the representative sample through the Cloud preview route.');
	assert(requests.some((request) => request.method === 'POST' && request.url.includes('/npcink-toolbox/v1/media-derivative-local-review/')), 'The browser reads verified preview bytes through same-origin POST.');
	assert(!requests.some((request) => /read-requests|run-read-ability|proposals|approve-and-execute/.test(request.url)), 'The read-only check creates no Core read authorization, proposal, or execution request.');
} catch (error) {
	if (page) {
		const artifactDir = env('SMOKE_ARTIFACT_DIR', 'build/smoke');
		mkdirSync(artifactDir, { recursive: true });
		const screenshotPath = `${artifactDir}/media-derivative-preview-browser-failure.png`;
		await page.screenshot({ path: screenshotPath, fullPage: true }).catch(() => {});
		const resultText = await page.locator('form[data-toolbox-tool-panel="media-batch-optimize"] .npcink-toolbox__result').innerText().catch(() => '');
		const stateDebug = await page.evaluate(() => {
			const form = document.querySelector('form[data-toolbox-tool-panel="media-batch-optimize"]');
			return form && Array.isArray(form.__npcinkMediaDerivativeBatchStates) ? form.__npcinkMediaDerivativeBatchStates : [];
		}).catch(() => []);
		console.error(`FAIL: Media derivative browser screenshot: ${screenshotPath}`);
		console.error(`FAIL: Media derivative browser result: ${resultText.replace(/\s+/g, ' ').trim().slice(0, 1200)}`);
		console.error(`FAIL: Media derivative browser states: ${JSON.stringify(stateDebug, null, 2)}`);
		console.error(`FAIL: Media derivative browser REST requests: ${JSON.stringify(requests.filter((request) => request.url.includes('/wp-json/')), null, 2)}`);
	}
	throw error;
} finally {
	try { if (browser) await browser.close(); }
	finally {
		if (attachmentId > 0) {
			wpCli(['eval', `$id=${attachmentId}; $batches=get_option('npcink_toolbox_media_optimization_batches',array()); $batches=array_values(array_filter((array)$batches,static function($batch)use($id){foreach((array)($batch['items']??array())as$item){if($id===(int)($item['attachment_id']??0))return false;}return true;})); update_option('npcink_toolbox_media_optimization_batches',$batches,false); if(!wp_delete_attachment($id,true)){fwrite(STDERR,'Attachment cleanup failed.');exit(1);}`]);
		}
	}
}
