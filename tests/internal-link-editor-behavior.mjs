import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../assets/editor-content-support.js', import.meta.url), 'utf8');
const windowObject = {
	location: { href: 'https://example.test/wp-admin/post.php' },
	wp: {},
};
windowObject.window = windowObject;
vm.runInNewContext(source, { window: windowObject, URL, Object, Array, String, Number, Boolean }, { filename: 'editor-content-support.js' });

const helpers = windowObject.NpcinkToolboxInternalLinkHelpers;
assert.ok(helpers, 'internal-link helpers are available');

function richTextValue(html) {
	const formats = [];
	let text = '';
	let cursor = 0;
	const anchorPattern = /<a\s+[^>]*href=["']([^"']+)["'][^>]*>(.*?)<\/a>/gi;
	let match;
	while ((match = anchorPattern.exec(html))) {
		const before = html.slice(cursor, match.index).replace(/<[^>]+>/g, '');
		text += before;
		for (let index = 0; index < before.length; index += 1) formats.push([]);
		const linkedText = match[2].replace(/<[^>]+>/g, '');
		text += linkedText;
		for (let index = 0; index < linkedText.length; index += 1) {
			formats.push([{ type: 'core/link', attributes: { url: match[1] } }]);
		}
		cursor = match.index + match[0].length;
	}
	const after = html.slice(cursor).replace(/<[^>]+>/g, '');
	text += after;
	for (let index = 0; index < after.length; index += 1) formats.push([]);
	return { text, formats, html };
}

const richText = {
	create: ({ html }) => richTextValue(html),
	applyFormat: (value, format, start, end) => ({ ...value, applied: { format, start, end } }),
	toHTMLString: ({ value }) => {
		const { start, end, format } = value.applied;
		return value.text.slice(0, start) + '<a href="' + format.attributes.url + '">' + value.text.slice(start, end) + '</a>' + value.text.slice(end);
	},
};

const candidate = {
	targetUrl: 'https://example.test/related/',
	canApplyToEditor: true,
	sourceMatch: {
		block_client_id: 'block-1',
		matched_text: 'Workflow',
		text_offset: 6,
		expected_text: 'Intro Workflow guidance.',
	},
};
const block = { clientId: 'block-1', attributes: { content: 'Intro Workflow guidance.' }, innerBlocks: [] };

function richTextData(html) {
	return {
		toHTMLString: () => html,
		toString: () => html.replace(/<[^>]+>/g, ''),
	};
}

const prepared = helpers.prepareInternalLinkApplication(candidate, block, [block], richText);
assert.equal(prepared.error, undefined);
assert.equal(prepared.appliedContent, 'Intro <a href="https://example.test/related/">Workflow</a> guidance.');
assert.equal(prepared.matchedText, 'Workflow');

const genericAnchor = {
	...candidate,
	canApplyToEditor: false,
	sourceMatch: { ...candidate.sourceMatch, matched_text: '文章', expected_text: 'Intro 文章 guidance.' },
};
assert.equal(helpers.prepareInternalLinkApplication(genericAnchor, block, [block], richText).error, 'missing_exact_match');

const selectedTextSelector = {
	getSelectionStart: () => ({ clientId: 'block-1', attributeKey: 'content', offset: 6 }),
	getSelectionEnd: () => ({ clientId: 'block-1', attributeKey: 'content', offset: 14 }),
	getBlock: () => block,
};
assert.equal(helpers.internalLinkSelectedText(selectedTextSelector), 'Workflow');
assert.equal(helpers.internalLinkSelectedText({ ...selectedTextSelector, getSelectionEnd: () => ({ clientId: 'block-2', attributeKey: 'content', offset: 14 }) }), '');

const richTextDataBlock = { ...block, attributes: { content: richTextData('Intro Workflow guidance.') } };
const preparedRichTextData = helpers.prepareInternalLinkApplication(candidate, richTextDataBlock, [richTextDataBlock], richText);
assert.equal(preparedRichTextData.error, undefined);
assert.equal(preparedRichTextData.appliedContent, prepared.appliedContent);

const stale = { ...block, attributes: { content: 'Intro changed Workflow guidance.' } };
assert.equal(helpers.prepareInternalLinkApplication(candidate, stale, [stale], richText).error, 'stale_block');

const linkedPhrase = { ...block, attributes: { content: 'Intro <a href="https://example.test/other">Workflow</a> guidance.' } };
const linkedCandidate = { ...candidate, sourceMatch: { ...candidate.sourceMatch, expected_text: 'Intro Workflow guidance.' } };
assert.equal(helpers.prepareInternalLinkApplication(linkedCandidate, linkedPhrase, [linkedPhrase], richText).error, 'range_already_linked');

const otherBlock = { clientId: 'block-2', attributes: { content: '<a href="https://example.test/related#section">Existing target</a>' }, innerBlocks: [] };
assert.equal(helpers.prepareInternalLinkApplication(candidate, block, [block, otherBlock], richText).error, 'target_already_linked');

const densityBlocks = [
	block,
	{ clientId: 'links', attributes: { content: '<a href="https://example.test/a">A</a> gap <a href="https://example.test/b">B</a>' }, innerBlocks: [] },
];
assert.equal(helpers.prepareInternalLinkApplication(candidate, block, densityBlocks, richText).error, 'article_link_density_reached');
assert.equal(helpers.internalLinkCount(richText.create({ html: '<a href="https://external.test/a">External</a>' })), 0);

const longBlock = {
	clientId: 'block-1',
	attributes: { content: '<a href="https://example.test/a">A</a> <a href="https://example.test/b">B</a> ' + 'x'.repeat(2100) + ' Workflow' },
	innerBlocks: [],
};
const longCandidate = {
	...candidate,
	sourceMatch: { ...candidate.sourceMatch, text_offset: 2104, expected_text: 'A B ' + 'x'.repeat(2100) + ' Workflow' },
};
assert.equal(helpers.prepareInternalLinkApplication(longCandidate, longBlock, [longBlock], richText).error, 'block_link_density_reached');

const deduped = helpers.dedupeInternalLinkCandidates([
	{ targetUrl: 'https://example.test/a/', sourceMatch: { block_client_id: 'one', matched_text: 'Alpha' } },
	{ targetUrl: 'https://EXAMPLE.test/a#top', sourceMatch: { block_client_id: 'two', matched_text: 'Beta' } },
	{ targetUrl: 'https://example.test/b', sourceMatch: { block_client_id: 'one', matched_text: 'Alpha' } },
]);
assert.equal(deduped.length, 1);

const undo = { appliedContent: prepared.appliedContent };
assert.equal(helpers.canUndoInternalLink({ attributes: { content: prepared.appliedContent } }, undo), true);
assert.equal(helpers.canUndoInternalLink({ attributes: { content: richTextData(prepared.appliedContent) } }, undo), true);
assert.equal(helpers.canUndoInternalLink({ attributes: { content: prepared.appliedContent + ' changed' } }, undo), false);

const unicodeRange = helpers.internalLinkMatchRange('😀 Intro WORKFLOW guidance.', 'Workflow', 8);
assert.equal(unicodeRange.start, 9);
assert.equal(unicodeRange.end, 17);
assert.equal(unicodeRange.text, 'WORKFLOW');

const preflightBase = {
	anchorText: '内容工作流',
	targetUrl: 'https://example.test/target',
	targetStatus: 'publish',
	targetPostIds: [2001],
	ranges: [[0, 6]],
	retrievalStatus: 'cloud_vector_evidence',
	candidateSource: 'cloud_vector',
	sourceMatch: { block_client_id: 'block-1', block_name: 'core/paragraph', matched_text: '内容工作流', expected_text: '内容工作流', text_offset: 0 },
	currentText: '内容工作流',
};
const eligiblePreflight = helpers.internalLinkBatchPreflight(preflightBase);
assert.equal(eligiblePreflight.outcome, 'eligible');
assert.equal(eligiblePreflight.reason_codes.length, 0);
assert.equal(helpers.internalLinkBatchPreflight({ ...preflightBase, targetPostIds: [2001, 2001] }).reason_codes[0], 'duplicate_target');
assert.equal(helpers.internalLinkBatchPreflight({ ...preflightBase, ranges: [[0, 6], [4, 8]] }).reason_codes[0], 'overlapping_source_ranges');
assert.equal(helpers.internalLinkBatchPreflight({ ...preflightBase, currentText: 'changed' }).reason_codes[0], 'stale_editor_block');
assert.equal(helpers.internalLinkBatchPreflight({ ...preflightBase, anchorText: '文章', sourceMatch: { ...preflightBase.sourceMatch, matched_text: '文章' } }).reason_codes[0], 'generic_anchor');
assert.equal(helpers.internalLinkBatchPreflight({ ...preflightBase, retrievalStatus: 'no_cloud_evidence', candidateSource: 'local_fallback' }).outcome, 'review_only');

console.log('PASS: internal-link editor apply, batch preflight, duplicate protection, stale protection, and undo safety');
