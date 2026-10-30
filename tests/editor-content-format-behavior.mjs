import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const serialize = (blocks) => JSON.stringify(blocks.map(({ name, attributes, innerBlocks }) => ({ name, attributes, innerBlocks })));
const sourceBlocks = [{ clientId: 'keep-id', name: 'core/paragraph', attributes: { content: '中文AI' }, innerBlocks: [] }];
const source = serialize(sourceBlocks);
const candidate = source.replace('中文AI', '中文 AI');
const wp = { element: {}, components: {}, blocks: { serialize, parse: JSON.parse } };
const context = vm.createContext({ window: { wp } });
const script = fs.readFileSync(new URL('../assets/editor-content-format.js', import.meta.url), 'utf8');
vm.runInContext(script, context);
const { prepare, onlyInsertedSpaces } = context.window.NpcinkToolboxContentFormat;
const result = { contract_version: 'content_format_candidate.v1', format: 'html', status: 'CHANGED', visible_characters_preserved: true, direct_wordpress_write: false, persisted: false, candidate };
const updates = prepare(sourceBlocks, source, result);
assert.equal(updates.length, 1);
assert.equal(updates[0].clientId, 'keep-id');
assert.equal(updates[0].before, '中文AI');
assert.equal(updates[0].after, '中文 AI');
assert.equal(sourceBlocks[0].attributes.content, '中文AI');
assert.equal(onlyInsertedSpaces('a b', 'ab'), false);
assert.equal(onlyInsertedSpaces('中文AI\r\n', '中文 AI\n'), false);
assert.equal(onlyInsertedSpaces('中文AI', '中文XAI'), false);
for (const [before, after] of [['WordPress', 'Word Press'], ['12.50%', '1 2.50%'], ['A-B', 'A -B'], ['中文AI', '中文  AI'], ['，原文', '， 原文']]) assert.equal(onlyInsertedSpaces(before, after), false, 'Spacing cannot split words, numbers, or punctuation.');
for (const [before, after] of [['原文，保留。。', '原文，保留。'], ['价格12.50%', '价格12.5%'], ['“引号”', '"引号"'], ['A—B…', 'A-B...'], ['先甲后乙', '先乙后甲']]) {
	assert.equal(onlyInsertedSpaces(before, after), false, 'Words, punctuation, symbols, numbers and order cannot be corrected by formatting.');
}
for (const patch of [{ status: 'REVIEW' }, { persisted: true }, { direct_wordpress_write: true }, { candidate: '' }, { candidate: candidate.replace('paragraph', 'image') }]) assert.throws(() => prepare(sourceBlocks, source, { ...result, ...patch }));
const code = [{ ...sourceBlocks[0], name: 'core/code' }];
for (const name of ['core/gallery', 'core/image', 'vendor/custom']) {
	const protectedBlock = { clientId: 'protected-id', name, attributes: { caption: '图片AI' }, innerBlocks: [] };
	const mixed = [...sourceBlocks, protectedBlock];
	const mixedSource = serialize(mixed);
	const partial = { ...result, status: 'PARTIAL', candidate: mixedSource.replace('中文AI', '中文 AI') };
	const changes = prepare(mixed, mixedSource, partial);
	assert.equal(changes.length, 1);
	assert.equal(changes[0].clientId, 'keep-id');
	assert.throws(() => prepare(mixed, mixedSource, { ...partial, candidate: partial.candidate.replace('图片AI', '图片 AI') }));
	assert.equal(prepare(mixed, mixedSource, { ...result, status: 'REVIEW', candidate: mixedSource }).length, 0);
}
assert.throws(() => prepare(code, serialize(code), { ...result, candidate: serialize(code).replace('中文AI', '中文 AI') }));
for (const forbidden of ['savePost(', 'saveEntityRecord(', 'resetBlocks(', '/wp/v2/posts', 'localStorage', 'sessionStorage']) assert.equal(script.includes(forbidden), false, forbidden);

// Exercise the actual click handler: sub-500ms edits/undo and post switches
// must discard a delayed response without any block mutation.
for (const change of ['edit', 'edit-undo', 'identity', 'post']) {
	let blocks = sourceBlocks;
	let body = source;
	let postId = 42;
	let listener;
	let finish;
	let mutations = 0;
	let unsubscribed = false;
	const notices = [];
	const testWp = {
		...wp,
		element: {
			createElement: (type, props, ...children) => ({ type, props, children }),
			useState: (value) => [value, (next) => notices.push(next)],
			useRef: () => ({ current: null }), useEffect: () => {},
		},
		data: {
			select: (store) => store === 'core/editor' ? { getCurrentPostId: () => postId, getEditedPostContent: () => body } : { getBlocks: () => blocks },
			subscribe: (callback) => { listener = callback; return () => { unsubscribed = true; }; },
			dispatch: () => ({ updateBlockAttributes: () => mutations++, replaceBlocks: () => mutations++ }),
		},
		apiFetch: () => new Promise((resolve) => { finish = resolve; }),
	};
	const harness = vm.createContext({ window: { wp: testWp, setTimeout, clearTimeout, NpcinkToolboxEditorSupport: { restUrl: '/toolbox', nonce: 'test' } }, AbortController });
	vm.runInContext(script, harness);
	const control = harness.window.NpcinkToolboxContentFormat.Control({});
	const pending = control.children[1].children[0].props.onClick();
	if (change === 'post') postId = 43;
	else {
		blocks = [{ ...sourceBlocks[0], clientId: change === 'identity' ? 'new-id' : 'keep-id' }];
		if (change !== 'identity') body = source + '编辑';
	}
	listener();
	if (change === 'edit-undo') { blocks = sourceBlocks; body = source; listener(); }
	finish({ ...result, post_id: 42 });
	await pending;
	assert.equal(mutations, 0, change);
	assert.ok(notices.some((value) => value?.text?.includes('changed while waiting')), change);
	assert.ok(unsubscribed, 'Subscription is released after a rejected result.');
}
console.log('PASS: Formatting candidate preflight, stable block IDs, immutable snapshots and no persistence paths.');
