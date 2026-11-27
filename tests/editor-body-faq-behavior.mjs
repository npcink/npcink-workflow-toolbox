import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const script = readFileSync(new URL('../assets/editor-content-support.js', import.meta.url), 'utf8');
const control = script.slice(script.indexOf('\n\tfunction bodyFaqSource()'), script.indexOf('\n\tfunction internalLinkBatchReasonLabel('));
const clip = [];
const pending = [];
const insertions = [];
let canInsert = true;
let rejectInsertion = false;
let nextClientId = 0;
const originalBlocks = [{ name: 'core/paragraph', attributes: { content: 'The current body.' } }];
let editorBlocks = originalBlocks;
let postId = 77;
let body = '<p>The current body.</p>';
let subscription;
let cleanup;
let cursor;
const state = [];
const harness = {
	data: {
		select: (store) => store === 'core/block-editor'
			? { getBlocks: () => editorBlocks, canInsertBlockType: (name) => canInsert || name !== 'core/paragraph' }
			: { getCurrentPostId: () => postId, getEditedPostAttribute: () => body },
		dispatch(store) {
			assert.equal(store, 'core/block-editor', 'FAQ adoption only dispatches to block editor state');
			return { insertBlocks(blocks, index, root, select) {
				assert.equal(index, editorBlocks.length);
				assert.equal(root, undefined);
				assert.equal(select, false);
				insertions.push(blocks);
				if (rejectInsertion) return;
				editorBlocks = [...editorBlocks, ...blocks];
				body += blocks.map(({ attributes }) => attributes.content).join('');
				subscription();
			} };
		},
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
	postJsonWithTimeout: (route, payload, timeout, timeoutMessage, timeoutCode, signal) => new Promise((resolve, reject) => pending.push({ route, payload, timeout, timeoutMessage, timeoutCode, signal, resolve, reject })),
	copyTextToClipboard: async (text) => { clip.push(text); },
	createBlock: (name, attributes) => ({ name, attributes, clientId: `fixture-${++nextClientId}` }),
	richText: {
		create(input) { assert.deepEqual(Object.keys(input), ['text']); return input; },
		toHTMLString: ({ value }) => value.text.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;'),
	},
	createElement: (type, props, ...children) => ({ type, props, children }),
	Modal: 'Modal', Button: 'Button', CheckboxControl: 'CheckboxControl', Notice: 'Notice',
	__: (value) => value, window: { AbortController },
};
const preview = vm.runInNewContext(control + '\nBodyFaqPreview;', harness);
const hasFinalFaq = vm.runInNewContext(control + '\nhasFinalBodyFaqSection;', harness);
const manualFaq = [
	...originalBlocks,
	{ name: 'core/heading', attributes: { level: 2, content: 'FAQ' } },
	{ name: 'core/heading', attributes: { level: 3, content: 'How does it work?' } },
	{ name: 'core/paragraph', attributes: { content: 'One paragraph answer.' } },
];
assert.equal(hasFinalFaq(manualFaq, 'Frequently asked questions'), true, 'Reuse a recognizable handwritten final FAQ');
assert.equal(hasFinalFaq([...manualFaq, ...originalBlocks], 'Frequently asked questions'), false, 'Do not attach new questions to an ambiguous tail');
assert.equal(hasFinalFaq([...manualFaq, { name: 'core/heading', attributes: { level: 2, content: 'Conclusion' } }], 'Frequently asked questions'), false, 'Keep a later section separate');
const render = () => { cursor = 0; return preview({ onClose: () => {} }); };
const find = (tree, test) => {
	if (Array.isArray(tree)) return tree.map((node) => find(node, test)).find(Boolean);
	if (!tree || typeof tree !== 'object') return null;
	if (test(tree)) return tree;
	return find(tree.children, test);
};
const button = (label) => find(render(), (node) => node.type === 'Button' && node.children.includes(label));
const start = () => button('Generate FAQ').props.onClick();
const insert = () => button('Add selected FAQ to the end');
const checkFirst = () => find(render(), (node) => node.type === 'CheckboxControl').props.onChange(true);
const item = { question: 'Question?', answer: 'Answer.', evidence: 'The current body.' };
const response = (items) => ({ contract_version: 'editor_body_faq.v1', status: items.length ? 'ready' : 'empty', post_id: postId, items });

assert.match(JSON.stringify(render()), /including unsaved edits/);
assert.equal(pending.length, 0, 'Opening the preview never sends text');
const first = start();
assert.equal(button('Generate FAQ').props.disabled, true);
assert.equal(pending[0].route, '/editor/content-support');
assert.equal(pending[0].timeout, 45000, 'FAQ gives the Addon HTTP wait time to return a result');
assert.deepEqual(JSON.parse(JSON.stringify(pending[0].payload)), { intent: 'body_faq', post_id: 77, content: body });
pending[0].resolve(response([item, { ...item, question: 'Skip?' }]));
await first;
assert.equal(button('Copy selected FAQ').props.disabled, true);
assert.equal(insert().props.disabled, true);
checkFirst();
assert.equal(insertions.length, 0, 'Checkbox selection does not edit the article');
assert.equal(insert().props.variant, 'primary');
const copy = button('Copy selected FAQ');
const staleInsert = insert();
assert.equal(copy.props.variant, 'secondary');
await copy.props.onClick();
assert.deepEqual(clip, ['Question?\nAnswer.'], 'Copy only selected Q&A, not its evidence');

body = '<p>Edited.</p>';
subscription();
body = '<p>The current body.</p>';
subscription();
assert.equal(button('Copy selected FAQ'), undefined, 'Editing and undo still retires the old preview');
await copy.props.onClick();
staleInsert.props.onClick();
assert.equal(clip.length, 1);
assert.equal(insertions.length, 0);

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

const timedOut = start();
pending[4].reject({ code: 'npcink_toolbox_faq_timeout', message: 'FAQ generation timed out. Nothing was written.' });
await timedOut;
assert.match(JSON.stringify(render()), /FAQ generation timed out\. Nothing was written\./);
assert.equal(pending[4].timeoutCode, 'npcink_toolbox_faq_timeout');
assert.match(pending[4].timeoutMessage, /Nothing was written/);
assert.equal(pending.length, 5, 'A timeout does not automatically resend the body');
assert.equal(button('Generate FAQ').props.disabled, false, 'The author can retry manually');

body = '<p> </p>';
await start();
assert.equal(pending.length, 5, 'Empty text does not trigger a request');
body = '<p>Other article text.</p>';
const fifth = start();
cleanup();
assert.equal(pending[5].signal.aborted, true, 'Closing the preview aborts the outstanding browser request');
pending[5].resolve(response([item]));
await fifth;

cleanup = null;
render();
const htmlLookingItem = { ...item, question: 'What does <demo> mean?', answer: 'Literal <demo> & text.' };
const sixth = start();
pending[6].resolve(response([htmlLookingItem, { ...item, question: 'Do not add?' }]));
await sixth;
checkFirst();
canInsert = false;
insert().props.onClick();
assert.equal(insertions.length, 0, 'Preflight checks both block types before one insertion');
assert.match(JSON.stringify(render()), /Could not add the FAQ/);
assert.equal(button('Copy selected FAQ').props.disabled, false);
canInsert = true;
rejectInsertion = true;
insert().props.onClick();
assert.equal(editorBlocks, originalBlocks);
assert.match(JSON.stringify(render()), /Could not add the FAQ/);
rejectInsertion = false;
const approved = insert();
approved.props.onClick();
assert.equal(editorBlocks[0], originalBlocks[0], 'Existing content remains untouched');
assert.deepEqual(editorBlocks.slice(1).map(({ name, attributes }) => [name, attributes.level, attributes.content]), [
	['core/heading', 2, 'Frequently asked questions'],
	['core/heading', 3, 'Q: What does &lt;demo&gt; mean?'],
	['core/paragraph', undefined, 'A: Literal &lt;demo&gt; &amp; text.'],
]);
assert.match(JSON.stringify(render()), /FAQ added to the end/);
approved.props.onClick();
assert.equal(insertions.length, 2, 'Repeated invocation cannot append the same result again');
assert.equal(pending.length, 7, 'Insertion does not make any new request');

const seventh = start();
pending[7].resolve(response([item]));
await seventh;
checkFirst();
insert().props.onClick();
assert.equal(editorBlocks.filter(({ name, attributes }) => name === 'core/heading' && attributes.level === 2).length, 1, 'A final FAQ section is reused');
assert.equal(insertions[2].length, 2, 'Only a Q&A pair is appended to an existing final section');

console.log('Editor body FAQ UI behavior passed.');
