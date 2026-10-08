import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

// Behavior test for the inert plain-text extraction in text-utils.js.
//
// The browser path must go through DOMParser (inert by specification:
// no script execution, no resource fetches) instead of innerHTML on a
// detached div. Node has no DOMParser, so this harness supplies a
// minimal recording stub: if the implementation ever falls back to
// innerHTML assignment or createElement, the stub records it and the
// test fails loudly. The no-window path keeps the regex fallback.

const partPath = fileURLToPath(new URL('../assets/editor-content-support/text-utils.js', import.meta.url));
const source = fs.readFileSync(partPath, 'utf8');

function loadHelpers(windowStub) {
	windowStub.window = windowStub;
	vm.runInNewContext(source, { window: windowStub }, { filename: 'text-utils.js' });
	return windowStub.NpcinkToolboxTextHelpers;
}

// 1. Browser path: DOMParser is used; innerHTML is never assigned.
const dangerous = '<p>Hello  <b>world</b></p><img src="https://evil.test/x.png" onerror="window.__pwned = true"><svg onload="alert(1)"></svg><script>window.__pwned = true;</script>';
const touches = [];
const windowStub = {
	document: {
		createElement(tag) {
			touches.push('createElement:' + tag);
			const node = { set innerHTML(value) { touches.push('innerHTML-assigned'); } };
			return node;
		},
	},
	DOMParser: class {
		parseFromString(html) {
			touches.push('DOMParser:' + html.length);
			// Strip nothing; emulate body.textContent for the sample shapes
			// this test feeds (script/svg/img content contributes no text).
			const text = html
				.replace(/<script[\s\S]*?<\/script>/g, '')
				.replace(/<[^>]+>/g, ' ');
			return { body: { textContent: text } };
		}
	},
};
const browserHelpers = loadHelpers(windowStub);
assert.ok(browserHelpers, 'helpers exported on window');
assert.equal(browserHelpers.plainTextFromHtml(dangerous), 'Hello world', 'browser path extracts text and drops markup');
assert.ok(touches.some((t) => t.startsWith('DOMParser:')), 'DOMParser was used');
assert.ok(!touches.includes('innerHTML-assigned'), 'innerHTML was never assigned');
assert.ok(!touches.some((t) => t.startsWith('createElement:')), 'no detached element was created');

// 2. A window without document/DOMParser proves the exported surface
// falls back to regex stripping instead of throwing.
const bareWindow = {};
vm.runInNewContext(source, { window: bareWindow }, { filename: 'text-utils.js' });
// bareWindow has no document and no DOMParser: the helper must degrade
// to the regex fallback rather than throw.
const fallbackHelpers = bareWindow.NpcinkToolboxTextHelpers;
assert.equal(
	fallbackHelpers.plainTextFromHtml('<p>Fallback <em>strips</em> tags</p>'),
	'Fallback strips tags',
	'no-DOMParser window falls back to regex stripping'
);

console.log('text-utils inert extraction behavior: ok');
