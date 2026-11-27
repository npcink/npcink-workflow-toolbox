import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const script = readFileSync(new URL('../assets/editor-content-format.js', import.meta.url), 'utf8');
const code = 'const x=1';
const candidate = 'const x = 1;\n';

for (const scenario of ['apply', 'close', 'edit-undo', 'post-switch', 'skip', 'invalid', 'off', 'nested', 'wrong-type']) {
	let block = { name: 'core/code', attributes: { content: code }, innerBlocks: [] };
	if (scenario === 'wrong-type') block.name = 'core/paragraph';
	let postId = 42;
	let listener;
	let cleanup;
	let request;
	const updates = [];
	let stop;
	const states = new Map();
	let cursor;
	let refs = [];
	const data = {
		select: (name) => name === 'core/editor' ? { getCurrentPostId: () => postId } : {
			getSelectedBlockClientId: () => 'target', getBlock: () => block,
			getBlockRootClientId: () => scenario === 'nested' ? 'parent' : '',
			getBlockEditingMode: () => 'default',
		},
		useSelect(callback) { return callback(this.select); },
		subscribe: (callback) => { listener = callback; return () => { stop = true; }; },
		dispatch: () => ({ updateBlockAttributes: (...args) => updates.push(args) }),
	};
	const wp = {
		data,
		components: { Button: 'button', Modal: 'modal', SelectControl: 'select' },
		blocks: { serialize: JSON.stringify, parse: JSON.parse },
		richText: { create: ({ html, text }) => ({ text: html ?? text }), toHTMLString: ({ value }) => value.text },
		element: {
			createElement: (type, props, ...children) => ({ type, props, children }),
			useState(value) { const key = cursor++; if (!states.has(key)) states.set(key, value); return [states.get(key), (next) => states.set(key, next)]; },
			useRef(value) { const key = cursor++; return refs[key] ??= { current: value }; },
			useEffect(effect) { cleanup ??= effect(); },
		},
		apiFetch: async ({ data: body }) => {
			request = body;
			return { contract_version: 'code_format_candidate.v1', language: 'javascript', persisted: false,
				direct_wordpress_write: scenario === 'invalid', candidate: scenario === 'skip' ? code : candidate,
				status: scenario === 'skip' ? 'SKIPPED' : 'CHANGED' };
		},
	};
	const window = { wp, setTimeout, clearTimeout, NpcinkToolboxEditorSupport: { codeFormatPilot: scenario === 'off' ? '0' : '1', restUrl: '/toolbox', nonce: 'test' } };
	vm.runInNewContext(script, { window, AbortController, TextEncoder });
	const render = (component, props) => { cursor = 0; return component(props); };
	const control = render(window.NpcinkToolboxContentFormat.Control, {});
	const entry = control.children.find((child) => child?.type?.name === 'CodeFormat');
	if (scenario === 'off') { assert.equal(entry, undefined); continue; }
	states.clear();
	refs = [];
	const format = render(entry.type, entry.props);
	if (scenario === 'nested' || scenario === 'wrong-type') { assert.equal(format, null); continue; }
	format.children[0].props.onClick();
	const modal = render(entry.type, entry.props).children[1];
	states.clear();
	refs = [];
	cleanup = undefined;
	let view = render(modal.type, modal.props);
	assert.equal(view.children.at(-1).props.disabled, true);
	view.children[0].props.onChange('javascript');
	view = render(modal.type, modal.props);
	await view.children.at(-1).props.onClick();
	assert.deepEqual(JSON.parse(JSON.stringify(request)), { intent: 'format_code_block', post_id: 42, language: 'javascript', code });
	assert.deepEqual(updates, [], 'Cloud preview must not change the editor.');
	view = render(modal.type, modal.props);
	if (scenario === 'skip' || scenario === 'invalid') {
		assert.equal(view.children.at(-1).children[0], 'Preview with Cloud');
		cleanup();
		continue;
	}
	assert.equal(view.children.at(-1).children[0], 'Apply to this code block');
	if (scenario === 'close') { view.children.at(-3).props.onClick(); cleanup(); assert.ok(stop); continue; }
	if (scenario === 'edit-undo') {
		const saved = block;
		block = { ...block };
		listener();
		block = saved;
		listener();
	}
	if (scenario === 'post-switch') { postId = 43; listener(); }
	view.children.at(-1).props.onClick();
	assert.deepEqual(JSON.parse(JSON.stringify(updates)), scenario === 'apply' ? [['target', { content: candidate }]] : []);
	cleanup();
}

console.log('PASS: contextual code preview, no preview writes, explicit single-block application and stale-result refusal.');
