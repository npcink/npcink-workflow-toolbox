import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const script = readFileSync(new URL('../assets/editor-content-support.js', import.meta.url), 'utf8');
const control = script.slice(script.indexOf('\n\tfunction bodyFaqSource()'), script.indexOf('\n\tfunction internalLinkBatchReasonLabel('));
const clip = [];
const pending = [];
let postId = 77;
let body = '<p>The current body.</p>';
let subscription;
let cleanup;
let cursor;
const state = [];
const harness = {
	data: {
		select: () => ({ getCurrentPostId: () => postId, getEditedPostAttribute: () => body }),
		subscribe(callback) { subscription = callback; return () => { subscription = null; }; },
	},
	useState(initial) {
		const key = cursor++;
		if (!(key in state)) state[key] = initial;
		return [state[key], (value) => { state[key] = typeof value === 'function' ? value(state[key]) : value; }];
	},
	useRef(initial) { return state[cursor] ?? (state[cursor] = { current: initial }); },
	useEffect(effect) { if (!cleanup) cleanup = effect(); },
	normalizeText: (text) => text || '',
	plainTextFromHtml: (text) => text.replace(/<[^>]+>/g, '').trim(),
	postJsonWithTimeout: (route, payload, _timeout, _message, _code, signal) => new Promise((resolve, reject) => pending.push({ route, payload, signal, resolve, reject })),
	copyTextToClipboard: async (text) => { clip.push(text); },
	createElement: (type, props, ...children) => ({ type, props, children }),
	Modal: 'Modal', Button: 'Button', CheckboxControl: 'CheckboxControl', Notice: 'Notice',
	__: (value) => value, window: { AbortController },
};
const preview = vm.runInNewContext(control + '\nBodyFaqPreview;', harness);
const render = () => { cursor = 0; return preview({ onClose: () => {} }); };
const find = (tree, test) => {
	if (Array.isArray(tree)) return tree.map((node) => find(node, test)).find(Boolean);
	if (!tree || typeof tree !== 'object') return null;
	if (test(tree)) return tree;
	return find(tree.children, test);
};
const button = (label) => find(render(), (node) => node.type === 'Button' && node.children.includes(label));
const start = () => button('Generate FAQ').props.onClick();
const item = { question: 'Question?', answer: 'Answer.', evidence: 'The current body.' };
const response = (items) => ({ contract_version: 'editor_body_faq.v1', status: items.length ? 'ready' : 'empty', post_id: postId, items });

assert.match(JSON.stringify(render()), /including unsaved edits/);
assert.equal(pending.length, 0, 'Opening the preview never sends text');
const first = start();
assert.equal(button('Generate FAQ').props.disabled, true);
assert.equal(pending[0].route, '/editor/content-support');
assert.deepEqual(JSON.parse(JSON.stringify(pending[0].payload)), { intent: 'body_faq', post_id: 77, content: body });
pending[0].resolve(response([item, { ...item, question: 'Skip?' }]));
await first;
assert.equal(button('Copy selected FAQ').props.disabled, true);
find(render(), (node) => node.type === 'CheckboxControl').props.onChange(true);
const copy = button('Copy selected FAQ');
await copy.props.onClick();
assert.deepEqual(clip, ['Question?\nAnswer.'], 'Copy only selected Q&A, not its evidence');

body = '<p>Edited.</p>';
subscription();
body = '<p>The current body.</p>';
subscription();
assert.equal(button('Copy selected FAQ'), undefined, 'Editing and undo still retires the old preview');
await copy.props.onClick();
assert.equal(clip.length, 1);

const second = start();
postId = 78;
subscription();
assert.equal(pending[1].signal.aborted, true);
const third = start();
pending[1].resolve(response([item]));
await second;
assert.equal(button('Copy selected FAQ'), undefined, 'Late response on switched article cannot be copied');
pending[2].resolve(response([]));
await third;
assert.match(JSON.stringify(render()), /No FAQ additions supported/);

const fourth = start();
pending[3].reject(new Error('internal provider details'));
await fourth;
assert.match(JSON.stringify(render()), /temporarily unavailable/);
assert.doesNotMatch(JSON.stringify(render()), /internal provider details/);
assert.equal(button('Copy selected FAQ'), undefined);

body = '<p> </p>';
await start();
assert.equal(pending.length, 4, 'Empty text does not trigger a request');
body = '<p>Other article text.</p>';
const fifth = start();
cleanup();
assert.equal(pending[4].signal.aborted, true, 'Closing the preview aborts the outstanding browser request');
pending[4].resolve(response([item]));
await fifth;

console.log('Editor body FAQ UI behavior passed.');
