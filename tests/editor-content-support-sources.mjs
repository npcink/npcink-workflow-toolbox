import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const partDir = fileURLToPath(new URL('../assets/editor-content-support/', import.meta.url));
const mainPath = fileURLToPath(new URL('../assets/editor-content-support.js', import.meta.url));

// Part filenames must sort in dependency order before the main bundle loads.
export function readEditorContentSupportSources() {
	const partNames = fs.existsSync(partDir)
		? fs.readdirSync(partDir).filter((name) => name.endsWith('.js')).sort()
		: [];
	return [...partNames.map((name) => path.join(partDir, name)), mainPath]
		.map((filePath) => fs.readFileSync(filePath, 'utf8'));
}

export function readEditorContentSupportBundle() {
	return readEditorContentSupportSources().join('\n');
}
