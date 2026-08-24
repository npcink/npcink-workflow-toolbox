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
	applyFormat: (value, format, start, end) => {
		const formats = value.formats.map((entries, index) => index >= start && index < end ? entries.concat([format]) : entries.slice());
		return { ...value, formats };
	},
	toHTMLString: ({ value }) => {
		let output = '';
		let activeUrl = '';
		for (let index = 0; index < value.text.length; index += 1) {
			const link = (value.formats[index] || []).find((format) => format && format.type === 'core/link');
			const nextUrl = link ? link.attributes.url : '';
			if (activeUrl && activeUrl !== nextUrl) output += '</a>';
			if (nextUrl && activeUrl !== nextUrl) output += '<a href="' + nextUrl + '">';
			output += value.text[index];
			activeUrl = nextUrl;
		}
		if (activeUrl) output += '</a>';
		return output;
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
assert.equal(helpers.internalLinkBatchPreflight({ ...preflightBase, targetUrl: 'https://outside.test/target' }).reason_codes[0], 'invalid_internal_url');

function batchCandidate(id, anchorText, offset, targetPostId, targetUrl, expectedText = 'Alpha connects Beta clearly.') {
	return {
		id,
		anchorText,
		targetUrl,
		targetPostId,
		targetStatus: 'publish',
		targetPostType: 'post',
		candidateSource: 'cloud_vector',
		retrievalStatus: 'cloud_vector_evidence',
		canApplyToEditor: true,
		sourceMatch: {
			block_client_id: 'batch-block',
			block_name: 'core/paragraph',
			matched_text: anchorText,
			text_offset: offset,
			expected_text: expectedText,
		},
	};
}

const batchBlock = { clientId: 'batch-block', attributes: { content: 'Alpha connects Beta clearly.' }, innerBlocks: [] };
const alphaCandidate = batchCandidate('alpha', 'Alpha', 0, 3001, 'https://example.test/alpha/');
const betaCandidate = batchCandidate('beta', 'Beta', 15, 3002, 'https://example.test/beta/');
const batchResult = helpers.prepareInternalLinkBatchApplication([alphaCandidate, betaCandidate], [batchBlock], richText);
assert.equal(batchResult.schema, 'current_article_multi_link_result.v1');
assert.equal(batchResult.write_posture, 'native_editor_commit');
assert.equal(batchResult.direct_wordpress_write, false);
assert.equal(batchResult.persisted, false);
assert.equal(batchResult.applied_count, 2);
assert.equal(batchResult.rejected_count, 0);
assert.equal(batchResult.updates.length, 1);
assert.equal(batchResult.updates[0].appliedContent, '<a href="https://example.test/alpha/">Alpha</a> connects <a href="https://example.test/beta/">Beta</a> clearly.');
assert.equal(helpers.canUndoInternalLinkBatch([{ ...batchBlock, attributes: { content: batchResult.updates[0].appliedContent } }], batchResult.undo), true);
assert.equal(helpers.canUndoInternalLinkBatch([{ ...batchBlock, attributes: { content: batchResult.updates[0].appliedContent + ' edited' } }], batchResult.undo), false);

const partialResult = helpers.prepareInternalLinkBatchApplication([
	alphaCandidate,
	batchCandidate('stale-beta', 'Beta', 15, 3003, 'https://example.test/stale/', 'Alpha used to connect Beta.'),
], [batchBlock], richText);
assert.equal(partialResult.applied_count, 1);
assert.equal(partialResult.rejected_count, 1);
assert.equal(partialResult.items[1].reason_codes[0], 'stale_editor_block');

const duplicateResult = helpers.prepareInternalLinkBatchApplication([
	alphaCandidate,
	batchCandidate('duplicate', 'Beta', 15, 3001, 'https://example.test/?p=3001'),
], [batchBlock], richText);
assert.equal(duplicateResult.applied_count, 0);
assert.deepEqual(Array.from(duplicateResult.items, (item) => item.reason_codes[0]), ['duplicate_target', 'duplicate_target']);

const overlappingResult = helpers.prepareInternalLinkBatchApplication([
	batchCandidate('whole', 'Alpha connects Beta', 0, 3010, 'https://example.test/whole/'),
	betaCandidate,
], [batchBlock], richText);
assert.equal(overlappingResult.applied_count, 0);
assert.deepEqual(Array.from(overlappingResult.items, (item) => item.reason_codes[0]), ['overlap_conflict', 'overlap_conflict']);

const overLimitResult = helpers.prepareInternalLinkBatchApplication(Array.from({ length: 9 }, (_, index) => ({ ...alphaCandidate, id: 'limit-' + index })), [batchBlock], richText);
assert.equal(overLimitResult.applied_count, 0);
assert.equal(overLimitResult.rejected_count, 9);
assert.equal(overLimitResult.items[0].reason_codes[0], 'selection_limit_exceeded');

const fallbackResult = helpers.prepareInternalLinkBatchApplication([{ ...alphaCandidate, candidateSource: 'local_fallback', retrievalStatus: 'no_cloud_evidence' }], [batchBlock], richText);
assert.equal(fallbackResult.applied_count, 0);
assert.equal(fallbackResult.items[0].reason_codes[0], 'fallback_must_not_be_labeled_vector');

console.log('PASS: internal-link editor batch apply, partial results, preflight, stale protection, and transaction undo safety');
