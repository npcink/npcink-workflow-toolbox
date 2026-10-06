import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const partDir = fileURLToPath(new URL('../assets/editor-content-support/', import.meta.url));
const mainPath = fileURLToPath(new URL('../assets/editor-content-support.js', import.meta.url));

// Dependency order, mirroring the wp_enqueue_script dependency chain in
// includes/Editor_Content_Support.php. A part file on disk that is not
// listed here fails loudly instead of loading in a wrong order.
export const PART_ORDER = [
	'text-utils.js',
	'internal-links.js',
	'audio-preferences.js',
];

export function readEditorContentSupportSources() {
	const partNames = fs.existsSync(partDir)
		? fs.readdirSync(partDir).filter((name) => name.endsWith('.js'))
		: [];
	const unregistered = partNames.filter((name) => !PART_ORDER.includes(name));
	if (unregistered.length) {
		throw new Error(`Unregistered editor content-support parts: ${unregistered.join(', ')}`);
	}
	return [...PART_ORDER, ''].map((name) => (name ? path.join(partDir, name) : mainPath))
		.map((filePath) => fs.readFileSync(filePath, 'utf8'));
}

export function readEditorContentSupportBundle() {
	return readEditorContentSupportSources().join('\n');
}
