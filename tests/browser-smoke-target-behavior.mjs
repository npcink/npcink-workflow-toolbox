import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { delimiter, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { requireSmokeBaseUrl, requireSmokeWordPressPath, runSmokeWpCli } from './browser-smoke-target.mjs';

const sandbox = mkdtempSync(join(tmpdir(), 'npcink-browser-smoke-target-'));
try {
	const root = join(sandbox, 'test site');
	mkdirSync(root);
	writeFileSync(join(root, 'wp-load.php'), '<?php');
	assert.equal(requireSmokeWordPressPath({ WP_PATH: root }), root);
	assert.throws(() => requireSmokeWordPressPath({}), /Set WP_PATH/);
	assert.throws(() => requireSmokeWordPressPath({ WP_PATH: sandbox }), /containing wp-load.php/);
	assert.equal(requireSmokeBaseUrl({ WP_BASE_URL: 'https://example.test/site/' }), 'https://example.test/site');
	assert.throws(() => requireSmokeBaseUrl({}), /Set WP_BASE_URL/);
	for (const url of ['ftp://example.test', 'https://@example.test', 'https://user:pass@example.test', 'https://example.test/?token=secret', 'https://example.test?', 'https://example.test#', 'https://[invalid']) {
		assert.throws(() => requireSmokeBaseUrl({ WP_BASE_URL: url }), /without credentials/);
	}

	const wp = join(sandbox, 'wp');
	writeFileSync(wp, '#!/bin/sh\nexit 99\n', { mode: 0o700 });
	const php = join(sandbox, 'fake php');
	writeFileSync(php, '#!/bin/sh\nprintf "%s\\n" "$@"\n', { mode: 0o700 });
	const env = { PATH: [sandbox, '/usr/bin', '/bin'].join(delimiter), WP_CLI_PHP: php };
	const args = [`--path=${root}`, '--no-color', 'core', 'version'];
	assert.deepEqual(runSmokeWpCli(root, ['core', 'version'], {}, env).split('\n'), [
		'-d', 'display_errors=0', '-d', 'error_reporting=8191', '-f', wp, '--', ...args,
	]);
	const socket = join(sandbox, 'mysql socket');
	assert.deepEqual(runSmokeWpCli(root, ['core', 'version'], { phpArgs: [] }, { ...env, PATH: '', WP_CLI_BIN: wp, WP_DB_SOCKET: socket }).split('\n'), [
		'-d', `mysqli.default_socket=${socket}`, '-f', wp, '--', ...args,
	]);

	function assertEarlyFailure(name, config, expected) {
		const entry = fileURLToPath(new URL(`./smoke-${name}-browser.mjs`, import.meta.url));
		const result = spawnSync(process.execPath, [entry], { env: config, encoding: 'utf8', timeout: 5000 });
		assert.ifError(result.error);
		assert.equal(result.status, 1, name);
		assert.match(result.stderr, expected, name);
	}
	for (const name of ['editor-content-format', 'editor-contextual-alt', 'editor-image-recommendation', 'editor-progressive', 'media-alt-caption', 'media-derivative-preview', 'media-optimization-exact-manifest', 'processed-image', 'site-ops-cloud-detail']) {
		assertEarlyFailure(name, {}, /Set WP_PATH/);
		if (name !== 'editor-content-format') {
			assertEarlyFailure(name, { WP_PATH: root }, /Set WP_BASE_URL/);
		}
	}
} finally {
	rmSync(sandbox, { recursive: true, force: true });
}
console.log('Browser smoke target validation passed.');
