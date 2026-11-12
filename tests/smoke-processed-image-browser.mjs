#!/usr/bin/env node
/** Installed single-image UI proof with deterministic Cloud/proposal responses. */

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

	const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 1000 } });
	await context.addCookies(authCookies(baseUrl));
	const artifact = {
		artifact_id: 'art_0123456789abcdef0123456789abcdef', expires_at: new Date(Date.now() + 300000).toISOString(),
		mime_type: 'image/png', format: 'png', width: 640, height: 360, filesize_bytes: sourceBytes.length,
		sha256: createHash('sha256').update(sourceBytes).digest('hex'), suggested_filename: 'processed.png',
		filename_basis: { owner: 'wordpress_write_ability_final', strategy: 'format_checksum', final_sanitize_unique_required: true },
		processing_warnings: [], transform_facts: { source_checksum: 'sha256:' + createHash('sha256').update(sourceBytes).digest('hex'), source_filesize_bytes: sourceBytes.length, watermark_applied: false, crop_applied: false, resize_applied: false }
	};
	const localReview = { method: 'POST', endpoint: `${baseUrl}/wp-json/npcink-toolbox/v1/media-derivative-local-review/${artifact.artifact_id}`, artifact };
	let requestedInput;
	let previewRequests = 0;
	let expectedReplacement = false;
	await context.route('**/wp-json/npcink-toolbox/v1/processed-image-preview', async route => {
		requestedInput = route.request().postDataJSON();
		previewRequests++;
		artifact.transform_facts.watermark_applied = requestedInput.task === 'watermark';
		await route.fulfill({ status: 202, contentType: 'application/json', body: JSON.stringify({ run_id: 'browser-preview-run', source_attachment_id: attachmentId, source_media_fingerprint: artifact.transform_facts.source_checksum }) });
	});
	await context.route('**/wp-json/npcink-toolbox/v1/media-derivative-preview/**', async route => {
		await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(route.request().url().endsWith('/result') ? { cloud_result: { artifact }, local_review: localReview } : { cloud_run: { status: 'completed' } }) });
	});
	await context.route('**/wp-json/npcink-toolbox/v1/media-derivative-local-review/*', async route => {
		assert(route.request().method() === 'POST', 'Local image review uses nonce-gated POST.');
		await route.fulfill({ status: 200, contentType: 'image/png', body: sourceBytes });
	});
	let saveRequests = 0;
	await context.route('**/wp-json/npcink-openclaw-adapter/v1/proposals', async route => {
		const body = route.request().postDataJSON();
		assert(body.input.commit === false && body.input.dry_run === true, 'Save requests cannot commit or approve writes.');
		if (expectedReplacement) {
			assert(body.ability_id === 'npcink-abilities-toolkit/adopt-cloud-media-derivative' && body.input.attachment_id === attachmentId && body.input.expected_current_media_fingerprint === artifact.transform_facts.source_checksum && !('source_attachment_id' in body.input), 'Replacement preserves the attachment identity and reviewed source fingerprint.');
		} else {
			assert(body.ability_id === 'npcink-abilities-toolkit/import-cloud-media-derivative' && body.input.source_attachment_id === attachmentId, 'Default save still creates a new-attachment proposal.');
		}
		saveRequests++;
		await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ proposal: { proposal_id: 'browser-new-media', status: 'pending' } }) });
	});
	page = await context.newPage();
	const errors = [];
	page.on('pageerror', error => errors.push(String(error)));
	page.on('request', request => requests.push({ url: request.url(), method: request.method() }));
	const tools = `${baseUrl}/wp-admin/admin.php?page=npcink-toolbox&toolbox_tab=tools`;
	async function choose(form) {
		await form.locator('[data-processed-choose]').click();
		await page.locator('.media-modal input[type="search"]').fill('Toolbox one-click preview');
		const item = page.locator(`.media-modal .attachment[data-id="${attachmentId}"]`);
		await item.waitFor({ state: 'visible' });
		await item.click();
		await page.locator('.media-modal .media-button-select').click();
	}
	for (const task of ['compression', 'watermark']) {
		await page.goto(tools + '&toolbox_tool=' + (task === 'compression' ? 'media-batch-optimize' : 'image-settings'), { waitUntil: 'networkidle' });
		const form = page.locator(`[data-toolbox-processed-image="${task}"]`);
		assert(await form.isVisible(), `${task}: working surface visible.`);
		assert(!(await form.locator('[data-processed-replace]').isChecked()), 'Replacement defaults to unchecked.');
		assert(await form.locator('[data-processed-preview]').isDisabled(), 'Preview requires a selected image.');
		assert(!(await form.locator('details').evaluate(node => node.open)), 'More settings starts folded.');
		await choose(form);
		await form.locator('[data-processed-preview]').click();
		await form.locator('[data-processed-save]:not([hidden]):not([disabled])').waitFor();
		assert(requestedInput.task === task && requestedInput.input.attachment_id === attachmentId, 'Preview binds one selected image and task.');
		assert(task !== 'compression' || !requestedInput.input.watermark, 'Compression does not inherit saved watermark defaults.');
		assert(await form.locator('[data-processed-comparison] img').evaluateAll(nodes => nodes.every(node => node.naturalWidth === 640 && node.naturalHeight === 360)), 'Original and processed result both decode.');
		assert((await form.locator('.description').last().innerText()).length > 0, 'Storage costs remain visible beside the save flow.');
		if (task === 'compression') {
			assert(/并不比原图小|not smaller than the original/.test(await form.locator('[data-processed-storage]').innerText()), 'Non-smaller compression is disclosed before saving.');
		}
		assert(saveRequests === (task === 'compression' ? 0 : 2), 'Preview does not submit a save request.');
		mkdirSync('output/playwright/processed-image', { recursive: true });
		for (const width of [1440, 390]) {
			await page.setViewportSize({ width, height: 1000 });
			assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${task} ${width}px has no horizontal overflow.`);
			await page.screenshot({ path: `output/playwright/processed-image/${task}-${width}.png`, fullPage: true });
		}
		await page.setViewportSize({ width: 1440, height: 1000 });
		await form.locator('details > summary').click();
		await form.locator('[name="quality"]').fill('83');
		assert(await form.locator('[data-processed-save]').isHidden(), 'Changing options invalidates the reviewed result.');
		await form.locator('[data-processed-preview]').click();
		await form.locator('[data-processed-save]:not([hidden]):not([disabled])').waitFor();
		await form.locator('[data-processed-save]').click();
		await form.locator('[data-processed-status] a[href*="proposal_id=browser-new-media"]').waitFor();
		assert(await form.locator('[data-processed-save]').isHidden(), 'Successful submission cannot be sent twice.');
		assert((await form.locator('[data-processed-status]').innerText()).match(/尚未|not been added/), 'Pending proposal is never shown as a saved image.');
		await form.locator('[data-processed-preview]').click();
		await form.locator('[data-processed-save]:not([hidden]):not([disabled])').waitFor();
		const beforeToggle = previewRequests;
		await form.locator('[data-processed-replace]').check();
		assert(await form.locator('[data-processed-save]').isEnabled() && beforeToggle === previewRequests, 'Switching the save destination keeps the reviewed preview without another Cloud request.');
		assert(/影响|affected/.test(await form.locator('[data-processed-destination]').innerText()), 'Replacement discloses effects on other image uses.');
		assert(/替换|replacement/i.test(await form.locator('[data-processed-save]').innerText()), 'Primary action explicitly requests replacement.');
		await page.screenshot({ path: `output/playwright/processed-image/${task}-replace-1440.png`, fullPage: true });
		expectedReplacement = true;
		await form.locator('[data-processed-save]').click();
		await form.locator('[data-processed-status] a[href*="proposal_id=browser-new-media"]').waitFor();
		assert(/审批|approval/.test(await form.locator('[data-processed-status]').innerText()), 'Replacement stays pending approval and does not claim the original changed.');
		expectedReplacement = false;
	}
	assert(errors.length === 0, `No browser JavaScript errors: ${errors.join('; ')}`);
	assert(!requests.some(request => /approve-and-execute|commit-preflight|\/execute|media-optimization-batches/.test(request.url)), 'Toolbox never approves, executes or starts a batch.');
	assert(readFileSync(wpCli(['eval', `echo get_attached_file(${attachmentId});`])).equals(sourceBytes), 'Preview and save request preserve original file bytes.');
} catch (error) {
	if (page) {
		mkdirSync('output/playwright/processed-image', { recursive: true });
		await page.screenshot({ path: 'output/playwright/processed-image/failure.png', fullPage: true }).catch(() => {});
		console.error(await page.locator('[data-processed-status]').allTextContents());
	}
	throw error;
} finally {
	if (browser) await browser.close();
	if (attachmentId > 0) wpCli(['eval', `if(!wp_delete_attachment(${attachmentId},true)){exit(1);}`]);
}
