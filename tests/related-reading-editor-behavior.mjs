import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readEditorContentSupportBundle } from './editor-content-support-sources.mjs';

const source = readEditorContentSupportBundle();
const window = { location: { href: 'https://example.test/wp-admin/post.php' }, wp: {} };
vm.runInNewContext(source, { window, URL });
const { relatedReadingSelection: select, buildRelatedReadingGroup: build, relatedReadingCanUndo: canUndo } = window.NpcinkToolboxRelatedReading;
const candidate = { id: '88', title: 'A <guide> & "notes"', targetPostId: 88, targetStatus: 'publish', targetPostType: 'post', targetUrl: 'https://example.test/guide/?a=1&b=2', candidateRelevance: 'strong' };
const richText = { create: ({ html }) => ({ formats: [...html.matchAll(/href="([^"]+)"/g)].map((m) => [{ type: 'core/link', attributes: { url: m[1].replace(/&amp;/g, '&') } }]) }) };
const check = (changes = {}, ids = ['88'], blocks = [], current = 31, origin = 31) => select([{ ...candidate, ...changes }], ids, blocks, current, origin, richText).reason;
assert.equal(check(), '');
assert.equal(select([candidate], ['88'], [], 31, 31).reason, 'editor_unavailable');
assert.equal(check({}, [], []), 'invalid_selection');
assert.equal(check({}, ['missing']), 'invalid_selection');
assert.equal(check({}, ['88', '88']), 'invalid_selection');
assert.equal(check({}, ['88'], [], 32), 'article_changed');
for (const changes of [{ targetPostId: 31 }, { targetPostId: 0 }, { targetPostId: 88.5 }, { targetStatus: 'draft' }, { targetPostType: 'attachment' }, { targetUrl: 'javascript:alert(1)' }, { targetUrl: 'https://outside.test/guide/' }, { candidateRelevance: 'weak' }, { title: '' }]) assert.equal(check(changes), 'invalid_target');
assert.equal(select(Array(9).fill(candidate), Array.from({ length: 9 }, (_, i) => String(i)), [], 31, 31, richText).reason, 'invalid_selection');
const duplicate = { ...candidate, id: '89' };
assert.equal(select([candidate, duplicate], ['88', '89'], [], 31, 31, richText).reason, 'already_linked');
let sequence = 0;
const createBlock = (name, attributes, innerBlocks = []) => ({ name, attributes, innerBlocks, clientId: 'block-' + ++sequence });
const group = build([candidate], '相关阅读', createBlock);
assert.equal(group.name, 'core/group');
assert.equal(group.innerBlocks[0].name, 'core/heading');
assert.equal(group.innerBlocks[0].attributes.level, 3);
assert.equal(group.innerBlocks[1].name, 'core/list');
assert.equal(group.innerBlocks[1].innerBlocks[0].attributes.content, '<a href="https://example.test/guide/?a=1&amp;b=2">A &lt;guide&gt; &amp; &quot;notes&quot;</a>');
assert.equal(check({}, ['88'], [group]), 'already_linked');
const serialize = JSON.stringify;
const receipt = { postId: 31, clientId: group.clientId, snapshot: serialize([group]) };
assert.equal(canUndo([group], receipt, 31, serialize), true);
assert.equal(canUndo([{ name: 'core/paragraph' }, group], receipt, 31, serialize), true);
assert.equal(canUndo([group], receipt, 32, serialize), false);
assert.equal(canUndo([], receipt, 31, serialize), false);
group.innerBlocks[0].attributes.content = 'Author edited heading';
assert.equal(canUndo([group], receipt, 31, serialize), false);

// Exercise the real candidate renderer, including checkbox callbacks.
window.wp = {
 element: { createElement: (type, props, ...children) => ({ type, props: props || {}, children: children.flat(Infinity).filter((x) => x !== null && x !== false) }), useState() {}, useEffect() {}, useRef() {} },
 data: { useSelect() {} }, plugins: { registerPlugin() {} }, editor: { PluginSidebar: 'sidebar' },
 components: { CheckboxControl: 'checkbox', Button: 'button' },
};
const hook = '\tplugins.registerPlugin(PLUGIN_NAME, {';
vm.runInNewContext(source.replace(hook, '\twindow.renderReading = renderRelatedContentCandidateSection;\n' + hook), { window, URL });
const nodes = (node) => !node || typeof node !== 'object' ? [] : [node, ...node.children.flatMap(nodes)];
const section = { status: 'ready', artifact_type: 'related_reading_candidates.v1', candidate_source: 'cloud_vector', results: [{ post_id: 88, title: 'Guide', url: candidate.targetUrl, target_ref: { post_id: 88, post_type: 'post', status: 'publish' } }] };
let toggled = null;
let inserted = false;
let tree = nodes(window.renderReading(section, { relatedContent: { selected: { 88: true }, toggle: (item, checked) => { toggled = [item.id, checked]; }, insert: () => { inserted = true; } } }));
const checkbox = tree.find((x) => x.type === 'checkbox');
assert.ok(checkbox);
checkbox.props.onChange(false);
assert.deepEqual(toggled, ['88', false]);
const insertButton = tree.find((x) => x.type === 'button' && x.children.includes('Insert selected reading'));
assert.equal(insertButton.props.disabled, false);
insertButton.props.onClick();
assert.equal(inserted, true);
tree = nodes(window.renderReading({ ...section, status: 'failed' }, {}));
assert.equal(tree.some((x) => x.type === 'checkbox'), false);
assert.equal(tree.some((x) => x.children.includes('Insert selected reading')), false);
console.log('PASS: related reading selection, escaped Gutenberg list, conservative Undo and real checkbox renderer');
