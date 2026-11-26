import { execFileSync } from 'node:child_process';
import { existsSync, statSync } from 'node:fs';
import { join, resolve } from 'node:path';

export function requireSmokeWordPressPath(env = process.env) {
	const configured = (env.WP_PATH || '').trim();
	if (!configured) throw new Error('Set WP_PATH to the authorized test WordPress root.');
	const root = resolve(configured);
	if (!existsSync(join(root, 'wp-load.php')) || !statSync(join(root, 'wp-load.php')).isFile()) {
		throw new Error('WP_PATH must be a WordPress root containing wp-load.php.');
	}
	return root;
}

export function requireSmokeBaseUrl(env = process.env) {
	const configured = (env.WP_BASE_URL || '').trim();
	if (!configured) throw new Error('Set WP_BASE_URL to the authorized test WordPress URL.');
	if (/^https?:\/\/[^/@?#\\\s]+(?:\/[^?#\\\s]*)?$/i.test(configured)) {
		try {
			return new URL(configured).href.replace(/\/+$/, '');
		} catch {
			// Malformed URLs use the same diagnostic, without echoing their input.
		}
	}
	throw new Error('WP_BASE_URL must be an HTTP(S) site URL without credentials, query, or fragment.');
}

export function runSmokeWpCli(root, args, { phpArgs = ['-d', 'display_errors=0', '-d', 'error_reporting=8191'], ...options } = {}, env = process.env) {
	const wp = env.WP_CLI_BIN || 'wp';
	let script = wp;
	if (!wp.includes('/')) {
		try {
			// PHP treats its script argument as a file, not a PATH command.
			script = execFileSync('which', [wp], { env, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
		} catch {
			throw new Error('Install WP-CLI on PATH or set WP_CLI_BIN to its PHP entry point.');
		}
	}
	const socketArgs = env.WP_DB_SOCKET ? ['-d', `mysqli.default_socket=${env.WP_DB_SOCKET}`] : [];
	return execFileSync(env.WP_CLI_PHP || 'php', [
		...phpArgs, ...socketArgs, '-f', script, '--', `--path=${root}`, '--no-color', ...args,
	], { env, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'], ...options }).trim();
}
