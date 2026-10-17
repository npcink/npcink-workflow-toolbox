/** Opt-in local smoke cache bypass. No product setting, cache deletion or runtime API. */
import { randomBytes } from 'node:crypto';
import { existsSync, mkdirSync, unlinkSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

export function createFreshCloudProbe(wpPath, baseUrl) {
	const host = new URL(baseUrl).hostname;
	if (!['localhost', '127.0.0.1'].includes(host) && !host.endsWith('.local')) throw Error('Fresh Cloud probes require a local development site.');
	const token = randomBytes(24).toString('hex');
	const directory = join(wpPath, 'wp-content', 'mu-plugins');
	mkdirSync(directory, { recursive: true });
	const path = join(directory, `npcink-smoke-fresh-cloud-${randomBytes(8).toString('hex')}.php`);
	writeFileSync(path, `<?php
if (!defined('ABSPATH') || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
 || !str_ends_with((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/npcink-toolbox/v1/editor/content-support')
 || !hash_equals('${token}', (string) ($_SERVER['HTTP_X_NPCINK_SMOKE_FRESH_CLOUD'] ?? ''))) { return; }
add_action('all', static function ($hook) {
 if (str_starts_with($hook, 'pre_transient_npcink_toolbox_editor_site_knowledge_')) {
  // null short-circuits get_transient but is not an array cache hit for Toolbox.
  add_filter($hook, '__return_null', PHP_INT_MAX);
 }
}, PHP_INT_MIN, 1);
`, { mode: 0o600, flag: 'wx' });
	return {
		headers: { 'X-Npcink-Smoke-Fresh-Cloud': token },
		cleanup() { if (existsSync(path)) unlinkSync(path); },
	};
}
