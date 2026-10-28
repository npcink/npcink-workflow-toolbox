import assert from 'node:assert/strict';
import vm from 'node:vm';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, readdirSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { createFreshCloudProbe } from './fresh-cloud-probe.mjs';
import { editorBlockSnapshot } from './editor-block-snapshot.mjs';
import { recommendationCloudObservation } from './recommendation-cloud-observation.mjs';

import { readEditorContentSupportBundle } from './editor-content-support-sources.mjs';

const source = readEditorContentSupportBundle();
const windowObject = {
	location: { href: 'https://example.test/wp-admin/post.php' },
	wp: {},
};
windowObject.window = windowObject;
vm.runInNewContext(source, { window: windowObject, URL, Object, Array, String, Number, Boolean }, { filename: 'editor-content-support.js' });

const helpers = windowObject.NpcinkToolboxInternalLinkHelpers;
assert.ok(helpers, 'internal-link helpers are available');
assert.equal(helpers.internalLinkContractValue('cloud_vector'), 'cloud_vector');
assert.equal(helpers.internalLinkContractValue(' cloud_vector_evidence '), 'cloud_vector_evidence');
assert.equal(helpers.recommendationCountBucket('candidate_count', 0), 'candidate_count_0');
assert.equal(helpers.recommendationCountBucket('candidate_count', 3), 'candidate_count_1_3');
assert.equal(helpers.recommendationCountBucket('candidate_count', 8), 'candidate_count_4_8');
assert.equal(helpers.recommendationCountBucket('candidate_count', 9), 'candidate_count_9_plus');

const sourcePreview = helpers.internalLinkSourcePreview({
	expected_text: 'This paragraph introduces a safe internal link anchor in its original sentence.',
	matched_text: 'internal link anchor',
	text_offset: 39,
}, 80);
assert.equal(sourcePreview.match, 'internal link anchor');
assert.equal(sourcePreview.before + sourcePreview.match + sourcePreview.after, 'This paragraph introduces a safe internal link anchor in its original sentence.');
assert.equal(sourcePreview.clippedBefore, false);
assert.equal(sourcePreview.clippedAfter, false);
assert.equal(helpers.internalLinkSourcePreview({ expected_text: 'No matching phrase.', matched_text: 'missing' }), null);

// Toolkit offsets count Unicode code points; previews must show the same
// occurrence that Apply resolves, including repeated anchors after emoji.
for (const text of [
	'JavaScript 用于页面交互；这里用 JavaScript 处理异步请求。',
	'😀 JavaScript 用于页面交互；这里用 JavaScript 处理异步请求。',
	'😀🚀 JavaScript 用于页面交互；这里用 JAVASCRIPT 处理异步请求。',
]) {
	const start = text.toLowerCase().lastIndexOf('javascript');
	const match = {
		expected_text: text,
		matched_text: 'JavaScript',
		text_offset: Array.from(text.slice(0, start)).length,
	};
	const range = helpers.internalLinkMatchRange(text, match.matched_text, match.text_offset);
	const preview = helpers.internalLinkSourcePreview(match, 180);
	assert.equal(range.start, start, 'Apply resolves the supplied second occurrence');
	assert.equal(preview.before, text.slice(0, range.start), 'review preview uses the same occurrence as Apply');
	assert.equal(preview.match, text.slice(range.start, range.end), 'preview preserves the actual matched casing');
	assert.equal(preview.after, text.slice(range.end));
	assert.equal(preview.before + preview.match + preview.after, text);
}
const fallbackPreview = helpers.internalLinkSourcePreview({ expected_text: '😀 JavaScript and JavaScript.', matched_text: 'JavaScript', text_offset: 999 }, 180);
assert.equal(fallbackPreview.before, '😀 ', 'preview preserves the matcher fallback when the supplied offset misses');
for (const textOffset of [undefined, null, NaN, -1, 1.5, '999', '3']) {
	const preview = helpers.internalLinkSourcePreview({ expected_text: '😀 JavaScript and JavaScript.', matched_text: 'JavaScript', text_offset: textOffset });
	assert.equal(preview.before, '😀 ', 'invalid or non-matching offsets retain Apply\'s first-occurrence fallback');
}
for (const [prefixLength, suffixLength, clippedBefore, clippedAfter] of [
	[0, 160, false, true], [100, 100, true, true], [160, 0, true, false],
]) {
	const prefix = '😀 JavaScript ' + '前'.repeat(prefixLength);
	const text = prefix + ' JavaScript ' + '后'.repeat(suffixLength);
	const preview = helpers.internalLinkSourcePreview({ expected_text: text, matched_text: 'JavaScript', text_offset: String(Array.from(prefix).length + 1) }, 80);
	assert.equal(preview.match, 'JavaScript');
	assert.equal(preview.before, text.slice(prefix.length + 1 - preview.before.length, prefix.length + 1), 'clipped context precedes the second occurrence');
	assert.equal(preview.after, text.slice(prefix.length + 11, prefix.length + 11 + preview.after.length), 'clipped context follows the second occurrence');
	assert.equal(preview.before.length + preview.match.length + preview.after.length, 80);
	assert.equal(preview.clippedBefore, clippedBefore);
	assert.equal(preview.clippedAfter, clippedAfter);
}

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
assert.equal(helpers.internalLinkBatchPreflight({ ...preflightBase, sourceMatch: { ...preflightBase.sourceMatch, block_name: 'core/code' } }).reason_codes[0], 'code_block_match_not_eligible');
assert.equal(helpers.internalLinkBatchPreflight({ ...preflightBase, sourceMatch: { ...preflightBase.sourceMatch, block_name: 'core-code' } }).reason_codes[0], 'code_block_match_not_eligible');
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
const telemetrySnapshots = helpers.internalLinkTelemetrySnapshots(batchResult.updates);
assert.equal(telemetrySnapshots.length, 1);
assert.equal(Object.prototype.hasOwnProperty.call(telemetrySnapshots[0], 'appliedContent'), false);
assert.match(telemetrySnapshots[0].appliedFingerprint, /^[a-z0-9]+_[a-z0-9]+$/);
assert.equal(helpers.internalLinkSavedStateChanged([{ ...batchBlock, attributes: { content: batchResult.updates[0].appliedContent } }], telemetrySnapshots), false);
assert.equal(helpers.internalLinkSavedStateChanged([{ ...batchBlock, attributes: { content: batchResult.updates[0].appliedContent + ' edited' } }], telemetrySnapshots), true);
assert.equal(helpers.internalLinkSavedStateChanged([], telemetrySnapshots), true);
const applyFeedbackSeed = helpers.internalLinkApplyFeedbackSeed({}, 'internal_link_results_session', 2);
assert.equal(applyFeedbackSeed.source_object_type, 'recommendation_session');
assert.equal(applyFeedbackSeed.source_object_id, 'internal_link_results_session');
assert.deepEqual(Array.from(applyFeedbackSeed.source_reason_codes), ['anchor_eligibility_v3', 'applied_count_1_3']);
const savedUnchangedFeedback = helpers.internalLinkSavedFollowupPayload(applyFeedbackSeed, false);
assert.equal(savedUnchangedFeedback.source_action_id, 'internal_link_saved_unchanged');
assert.equal(savedUnchangedFeedback.local_outcome, 'accepted');
assert.equal(savedUnchangedFeedback.source_object_type, 'recommendation_session');
assert.equal(savedUnchangedFeedback.source_object_id, 'internal_link_results_session');
assert.deepEqual(Array.from(savedUnchangedFeedback.source_reason_codes), ['anchor_eligibility_v3', 'applied_count_1_3', 'saved_without_editor_changes']);
const historicalSeed = { ...applyFeedbackSeed, source_reason_codes: ['anchor_eligibility_v2', 'applied_count_1_3'] };
assert.ok(helpers.internalLinkSavedFollowupPayload(historicalSeed, false).source_reason_codes.includes('anchor_eligibility_v2'), 'Historical seeds retain the policy used at Apply');
assert.ok(!helpers.internalLinkSavedFollowupPayload(historicalSeed, false).source_reason_codes.includes('anchor_eligibility_v3'));
const savedEditedFeedback = helpers.internalLinkSavedFollowupPayload(savedUnchangedFeedback, true);
assert.equal(savedEditedFeedback.source_action_id, 'internal_link_saved_edited');
assert.equal(savedEditedFeedback.local_outcome, 'edited_before_accept');
assert.deepEqual(Array.from(savedEditedFeedback.feedback_labels), ['good_but_needs_human_draft']);

const feedbackContext = helpers.recommendationFeedbackContext({
	sections: {
		internal_links: {
			retrieval_status: 'cloud_vector_evidence',
			candidate_source: 'cloud_vector',
			fallback_used: false,
			recommendation_candidates: [
				{
					id: 'feedback-alpha',
					anchor_or_context: 'Alpha',
					can_apply_to_editor: true,
					candidate_source: 'cloud_vector',
					target_ref: { post_id: 3001, status: 'publish', post_type: 'post', url: 'https://example.test/alpha/' },
					source_match: alphaCandidate.sourceMatch,
				},
				{
					id: 'feedback-weak',
					anchor_or_context: '弱相关候选',
					can_apply_to_editor: true,
					candidate_relevance: 'weak',
					candidate_source: 'cloud_vector',
					target_ref: { post_id: 3002, status: 'publish', post_type: 'post', url: 'https://example.test/weak/' },
					source_match: { block_client_id: 'batch-block', matched_text: '弱相关候选' },
				},
			],
		},
	},
}, 'internal_links', [batchBlock], richText);
assert.equal(feedbackContext.candidateCount, 2);
assert.equal(feedbackContext.applicableCount, 1);
for (const [candidateSource, retrievalStatus, expectedCount] of [
	['cloud_vector', 'cloud_vector_evidence', 1],
	['local_fallback', 'no_cloud_evidence', 0],
	['cloud_unavailable', 'cloud_unavailable', 0],
	['local_fallback', 'cloud_vector_evidence', 0],
	['cloud_vector', '', 0],
]) {
	const context = helpers.recommendationFeedbackContext({ sections: { internal_links: {
		candidate_source: candidateSource,
		retrieval_status: retrievalStatus,
		recommendation_candidates: [{
			id: 'cached-eligibility', can_apply_to_editor: true,
			candidate_source: candidateSource,
			anchor_or_context: 'Alpha',
			target_ref: { post_id: 3001, status: 'publish', post_type: 'post', url: 'https://example.test/alpha/' },
			source_match: alphaCandidate.sourceMatch,
		}],
	} } }, 'internal_links', [batchBlock], richText);
	assert.equal(context.candidateCount, 1, 'Reference candidates remain available');
	assert.equal(context.applicableCount, expectedCount, 'Cached Apply flags cannot override retrieval evidence');
}
assert.deepEqual(Array.from(feedbackContext.reasonCodes), [
	'candidate_count_1_3',
	'applicable_count_1_3',
	'retrieval_cloud_vector_evidence',
	'source_cloud_vector',
	'fallback_not_used',
]);

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

// The visible readiness projection must reuse Apply, including format-only edits.
const liveCandidate = { ...alphaCandidate, candidateRank: 6, scoreBucket: 'high' };
const linkedLiveBlock = { ...batchBlock, attributes: { content: '<a href="https://outside.test/reference">Alpha</a> connects Beta clearly.' } };
const liveCases = [
 { blocks: [batchBlock], candidate: liveCandidate, reason: '' },
 { blocks: [linkedLiveBlock], candidate: liveCandidate, reason: 'range_already_linked' },
 { blocks: [batchBlock, { clientId: 'existing-target', attributes: { content: '<a href="https://example.test/alpha/#part">Existing reference</a>' } }], candidate: liveCandidate, reason: 'target_already_linked' },
 { blocks: [{ ...batchBlock, attributes: { content: 'Alpha now connects Beta clearly.' } }], candidate: liveCandidate, reason: 'stale_editor_block' },
 { blocks: [batchBlock], candidate: { ...liveCandidate, sourceMatch: { ...liveCandidate.sourceMatch, block_name: 'core/code' } }, reason: 'code_block_match_not_eligible' },
 { blocks: [batchBlock], candidate: { ...liveCandidate, targetUrl: 'https://outside.test/target' }, reason: 'invalid_internal_url' },
 { blocks: [batchBlock], candidate: { ...liveCandidate, candidateSource: 'local_fallback', retrievalStatus: 'no_cloud_evidence' }, reason: 'fallback_must_not_be_labeled_vector' },
 { blocks: [batchBlock, ...densityBlocks.slice(1)], candidate: liveCandidate, reason: 'article_link_density_reached' },
 { blocks: [longBlock], candidate: { ...liveCandidate, ...longCandidate, anchorText: 'Workflow' }, reason: 'block_link_density_reached' },
];
for (const { blocks: liveBlocks, candidate: current, reason } of liveCases) {
 const before = JSON.stringify({ liveBlocks, current });
 const readiness = helpers.internalLinkCandidateEligibility(current, liveBlocks, richText);
 const actual = helpers.prepareInternalLinkBatchApplication([current], liveBlocks, richText);
 assert.equal(readiness.canApply, actual.applied_count === 1, 'Readiness agrees with Apply: ' + reason);
 assert.equal(readiness.reasonCodes[0] || '', reason);
 assert.equal(JSON.stringify({ liveBlocks, current }), before, 'Readiness never edits blocks, rank or candidate order');
}
assert.equal(helpers.internalLinkCandidateEligibility({ ...liveCandidate, candidateRelevance: 'weak' }, [batchBlock], richText).canApply, false);
assert.equal(helpers.internalLinkCandidateEligibility({ ...liveCandidate, targetPostType: 'attachment' }, [batchBlock], richText).canApply, false);
assert.equal(helpers.internalLinkCandidateEligibility(liveCandidate, [batchBlock], undefined).canApply, false);
assert.equal(helpers.internalLinkCandidateEligibility(liveCandidate, [linkedLiveBlock], richText).canApply, false);
assert.equal(helpers.internalLinkCandidateEligibility(liveCandidate, [batchBlock], richText).canApply, true, 'Undoing a format-only link restores readiness');
const livePayload = { sections: { internal_links: {
 ranking_version: 'internal_links.v1', candidate_source: 'cloud_vector', retrieval_status: 'cloud_vector_evidence',
 recommendation_candidates: [{ id: 'alpha', label: 'Alpha target', anchor_or_context: 'Alpha', can_apply_to_editor: true,
  target_ref: { post_id: 3001, status: 'publish', post_type: 'post', url: liveCandidate.targetUrl }, source_match: liveCandidate.sourceMatch }],
} } };
assert.equal(helpers.recommendationFeedbackContext(livePayload, 'internal_links', [batchBlock], richText).applicableCount, 1);
assert.equal(helpers.recommendationFeedbackContext(livePayload, 'internal_links', [linkedLiveBlock], richText).applicableCount, 0);
const liveImpression = helpers.recommendationImpressionFeedbackPayload(livePayload, 'internal_links', 'live-session', [linkedLiveBlock], richText);
assert.ok(liveImpression.source_reason_codes.includes('applicable_count_0'));
assert.ok(liveImpression.source_reason_codes.includes('anchor_eligibility_v3'));
assert.equal(liveImpression.ranking_version, 'internal_links.v1');
assert.equal(liveImpression.evidence_ref_ids.length, 0);
assert.equal(/"(?:content|anchor_text|source_match|blocks)"\s*:/.test(JSON.stringify(liveImpression)), false, 'Readiness telemetry stays metadata-only');
console.log('PASS: live readiness, existing links, editor changes, rank preservation and impression denominator');

// Known compound/token fragments must fail identically in readiness and Apply.
let anchorBoundaryCases = [
 ['那么现在还有必要为自己搭建个人网站吗？', '搭建个人', 'anchor_incomplete_compound'],
 ['为自己建立独立博客。', '建立独立', 'anchor_incomplete_compound'],
 ['那么现在还有必要为自己搭建个人网站吗？', '搭建个人网站', ''],
 ['面向个人提供服务。', '面向个人', ''],
 ['修改元素的HTML内容。', '的HTML', 'anchor_dependent_fragment'],
 ['修改元素的HTML内容。', 'HTML内容', ''],
 ['了解css的用法。', 'css的', 'anchor_dependent_fragment'],
 ['参考 background-attachment 属性。', 'background', 'anchor_partial_identifier'],
 ['参考 background-attachment 属性。', 'background-attachment', ''],
 ['学习 JavaScript 教程。', 'JavaScript', ''],
 ['学习 Github 教程。', 'Github', ''],
 ['例如 mysite.com/category/my-category/ 路径。', 'category', ''],
];
if (process.env.NPCINK_LINK_ANCHOR_FIXTURES) {
 const fixture = JSON.parse(readFileSync(process.env.NPCINK_LINK_ANCHOR_FIXTURES, 'utf8'));
 assert.equal(fixture.contract, 'internal_link_anchor_eligibility_controls.v2');
 assert.equal(fixture.gold_source, 'developer_controls_not_human_gold');
 assert.ok(Array.isArray(fixture.cases) && fixture.cases.length >= 12);
 anchorBoundaryCases = fixture.cases.map(row => {
  assert.ok(typeof row.text === 'string' && typeof row.anchor === 'string' && row.text.includes(row.anchor));
  assert.ok(['', 'anchor_incomplete_compound', 'anchor_dependent_fragment', 'anchor_partial_identifier', 'anchor_reference_requires_review'].includes(row.expected_reason));
  return [row.text, row.anchor, row.expected_reason];
 });
}
const solutionCaptionCases = [
 ['解决办法：', '解决办法', 'anchor_reference_requires_review'],
 ['解决办法', '解决办法', 'anchor_reference_requires_review'],
 ['参考：解决办法', '解决办法', 'anchor_reference_requires_review'],
 ['解决办法：', '解决办法：', 'anchor_reference_requires_review'],
 ['本文介绍解决办法和注意事项。', '解决办法', ''],
 ['缓存解决办法：', '缓存解决办法', ''],
 ['解决办法：清除缓存。', '清除缓存', ''],
];
for (const [text, anchor, expectedReason] of [...anchorBoundaryCases, ...solutionCaptionCases]) {
 const current = batchCandidate('boundary', anchor, Array.from(text.slice(0, text.indexOf(anchor))).length, 3003, 'https://example.test/target/', text);
 const blocks = [{ ...batchBlock, attributes: { content: text } }];
 const before = JSON.stringify({ current, blocks });
 const ready = helpers.internalLinkCandidateEligibility(current, blocks, richText);
 const applied = helpers.prepareInternalLinkBatchApplication([current], blocks, richText);
 assert.equal(ready.reasonCodes[0] || '', expectedReason, 'Anchor boundary: ' + anchor);
 assert.equal(ready.canApply, !expectedReason);
 assert.equal(applied.applied_count, expectedReason ? 0 : 1);
 assert.equal(ready.anchorEligibilityVersion, 'internal_link_anchor_eligibility.v3');
 assert.equal(applied.anchor_eligibility_version, ready.anchorEligibilityVersion);
 if (expectedReason) assert.equal(helpers.prepareInternalLinkApplication(current, blocks[0], blocks, richText).error, expectedReason);
 assert.equal(JSON.stringify({ current, blocks }), before, 'Do not rewrite anchors or editor text');
}


const inlineFragment = batchCandidate('inline-compound', '搭建个人', 3, 3003, 'https://example.test/target/', '为自己搭建个人网站。');
const inlineFragmentBlocks = [{ ...batchBlock, attributes: { content: '为自己搭建<strong>个人</strong>网站。' } }];
inlineFragment.sourceMatch.expected_text = windowObject.NpcinkToolboxTextHelpers.plainTextFromHtml(inlineFragmentBlocks[0].attributes.content);
assert.equal(helpers.internalLinkCandidateEligibility(inlineFragment, inlineFragmentBlocks, richText).reasonCodes[0], 'anchor_incomplete_compound');
assert.equal(inlineFragmentBlocks[0].attributes.content, '为自己搭建<strong>个人</strong>网站。', 'Fragment rejection preserves inline markup');
console.log('PASS: anchor eligibility v3 controls: ' + (anchorBoundaryCases.length + solutionCaptionCases.length));
console.log('PASS: internal-link editor batch apply, partial results, preflight, stale protection, and transaction undo safety');

const relatedMetadataPayload = { sections: { related_content: {
 status: 'ready', ranking_version: 'related_content.v1', retrieval_source: 'cloud_vector', rerank_applied: false,
 results: [{ post_id: 4, title: 'One', url: 'https://example.test/one', score_bucket: 'high' }, { post_id: 9, title: 'Two', url: 'https://example.test/two', score_bucket: 'medium' }],
} } };
const relatedContext = helpers.recommendationFeedbackContext(relatedMetadataPayload, 'related_content');
assert.ok(relatedContext.reasonCodes.includes('retrieval_ready'), 'Provider ready status survives the related-content feedback projection');
assert.ok(relatedContext.reasonCodes.includes('source_cloud_vector'), 'Known metadata retrieval source must not become source_unknown');
assert.equal(relatedContext.candidateCount, 2, 'Related impressions count their own result shape');
assert.equal(relatedContext.applicableCount, 0);
const relatedImpression = helpers.recommendationImpressionFeedbackPayload(relatedMetadataPayload, 'related_content', 'related-session');
assert.equal(relatedImpression.ranking_version, 'related_content.v1');
assert.equal(relatedImpression.rerank_applied, false);
assert.equal(relatedImpression.candidate_rank, undefined, 'Impressions have no singular candidate position');
assert.equal(relatedImpression.evidence_ref_ids.length, 0);
const candidateMetadata = helpers.recommendationFeedbackMetadata(relatedMetadataPayload, 'related_content', { candidateRank: 2, scoreBucket: 'medium', title: 'private title', targetUrl: 'https://private.test' });
assert.equal(candidateMetadata.candidate_rank, 2);
assert.equal(candidateMetadata.score_bucket, 'medium');
assert.equal(JSON.stringify(candidateMetadata).includes('private'), false);
const rankedApplySeed = helpers.internalLinkApplyFeedbackSeed({ sections: { internal_links: { ranking_version: 'internal_links.v1', retrieval_source: 'cloud_vector' } } }, 'ranked-session', 2, [{ candidateRank: 2 }, { candidateRank: 6 }]);
const rankedSave = helpers.internalLinkSavedFollowupPayload(rankedApplySeed, false);
assert.equal(rankedSave.ranking_version, 'internal_links.v1');
assert.ok(rankedSave.source_reason_codes.includes('candidate_rank_2'));
assert.ok(rankedSave.source_reason_codes.includes('candidate_rank_6'));
console.log('Recommendation family counts, position, version and saved-rank privacy: ok');
const positionEvent = { handoff_id: 'same-session-copy', ranking_version: 'internal_links.v1', candidate_rank: 2 };
assert.notEqual(helpers.implicitAgentFeedbackKey(positionEvent), helpers.implicitAgentFeedbackKey({ ...positionEvent, candidate_rank: 6 }), 'Distinct candidate positions are not deduplicated together');
assert.equal(helpers.implicitAgentFeedbackKey(positionEvent), helpers.implicitAgentFeedbackKey({ ...positionEvent }), 'Repeated clicks on the same candidate remain deduplicated');
assert.notEqual(helpers.implicitAgentFeedbackKey({ handoff_id: 'same-session-save', source_reason_codes: ['candidate_rank_2'] }), helpers.implicitAgentFeedbackKey({ handoff_id: 'same-session-save', source_reason_codes: ['candidate_rank_6'] }));

// RichTextData may hold cyclic caches: snapshot the WordPress serialization, not those caches.
const cyclicRichText = { toHTMLString: () => '<strong><em>Real article text</em></strong>' };
cyclicRichText.children = { strong: { children: { em: cyclicRichText } } };
const cyclicBlock = { clientId: 'real-rich-text', name: 'core/paragraph', attributes: { content: cyclicRichText }, innerBlocks: [] };
const snapshotWp = { data: { select: () => ({ getBlocks: () => [cyclicBlock] }) }, blocks: { serialize: (blocks) => blocks[0].attributes.content.toHTMLString() } };
assert.throws(() => JSON.stringify(cyclicBlock), /circular/i);
const cyclicSnapshot = editorBlockSnapshot(snapshotWp);
assert.equal(cyclicSnapshot.serialized, '<strong><em>Real article text</em></strong>');
assert.equal(cyclicSnapshot.identities[0].clientId, 'real-rich-text');
assert.doesNotThrow(() => JSON.stringify(cyclicSnapshot));
console.log('PASS: editor Undo snapshots preserve serialized content and identities with cyclic RichTextData');

const changedIdentity = { ...cyclicBlock, clientId: 'changed-block' };
const changedWp = { ...snapshotWp, data: { select: () => ({ getBlocks: () => [changedIdentity] }) } };
assert.notDeepEqual(editorBlockSnapshot(changedWp).identities, cyclicSnapshot.identities);
const serializedChangedWp = { ...snapshotWp, blocks: { serialize: () => '<em>Changed article text</em>' } };
assert.notEqual(editorBlockSnapshot(serializedChangedWp).serialized, cyclicSnapshot.serialized);
const explicitRelatedContext = helpers.recommendationFeedbackContext({ sections: { related_content: { status: 'ready', source_status: 'no_cloud_evidence', candidate_source: 'local_fallback', retrieval_source: 'cloud_vector', results: [] } } }, 'related_content');
assert.ok(explicitRelatedContext.reasonCodes.includes('retrieval_no_cloud_evidence'));
assert.ok(explicitRelatedContext.reasonCodes.includes('source_local_fallback'), 'Explicit evidence wins over fallback metadata');
const unknownRelatedContext = helpers.recommendationFeedbackContext({ sections: { related_content: { results: [] } } }, 'related_content');
assert.ok(unknownRelatedContext.reasonCodes.includes('retrieval_unknown') && unknownRelatedContext.reasonCodes.includes('source_unknown'), 'Absent evidence remains unknown');

const unavailable = recommendationCloudObservation({ section: { retrieval_status: 'cloud_unavailable', source_knowledge: { status: 'error', code: 'http_request_failed', message: 'cURL error 28 timeout at https://private.test/?key=private-secret', provider_output: 'private-secret' } }, httpStatus: 200, elapsedMs: 20001.6, requestBody: '{"content":"private-secret"}' });
assert.equal(unavailable.error_class, 'timeout');
assert.equal(unavailable.error_code, 'http_request_failed');
assert.equal(unavailable.elapsed_from_click_ms, 20002);
assert.equal(unavailable.http_status, 200, 'HTTP 200 is not mistaken for Cloud availability');
assert.equal(unavailable.retrieval_status, 'cloud_unavailable');
assert.match(unavailable.request_sha256, /^[a-f0-9]{64}$/);
assert.ok(!JSON.stringify(unavailable).includes('private-secret') && !JSON.stringify(unavailable).includes('private.test'));
const unlisted = recommendationCloudObservation({ section: { source_knowledge: { code: 'private-secret', message: 'private-secret', status: 'error' } }, elapsedMs: -1 });
assert.equal(unlisted.error_code, 'unlisted');
assert.equal(unlisted.elapsed_from_click_ms, null);
assert.ok(!JSON.stringify(unlisted).includes('private-secret'));
const waitFailure = recommendationCloudObservation({ phase: 'request_failed', errorName: 'TimeoutError', elapsedMs: 45000 });
assert.equal(waitFailure.error_class, 'browser_timeout');
assert.equal(waitFailure.http_status, null);
const parseFailure = recommendationCloudObservation({ phase: 'invalid_json', httpStatus: 502 });
assert.equal(parseFailure.error_class, 'invalid_json');
const healthy = recommendationCloudObservation({ section: { retrieval_status: 'cloud_vector_evidence', recommendation_candidates: [], source_knowledge: { status: 'ready', cache_status: 'hit' } }, httpStatus: 200 });
assert.equal(healthy.error_class, 'none');
assert.equal(healthy.cache_status, 'hit');
assert.equal(healthy.candidate_count, 0);
console.log('PASS: bounded Cloud observations retain unavailable/timeout/parse failures without raw error or request data');

const probeRoot = mkdtempSync(join(tmpdir(), 'npcink-fresh-probe-'));
try {
	assert.throws(() => createFreshCloudProbe(probeRoot, 'https://production.example'), /local development/);
	const probe = createFreshCloudProbe(probeRoot, 'https://example.local');
	const probeDirectory = join(probeRoot, 'wp-content', 'mu-plugins');
	const probeFile = join(probeDirectory, readdirSync(probeDirectory)[0]);
	const phpProbe = `define('ABSPATH', '/test/'); $_SERVER['REQUEST_METHOD']=$argv[3]; $_SERVER['REQUEST_URI']=$argv[4]; $_SERVER['HTTP_X_NPCINK_SMOKE_FRESH_CLOUD']=$argv[2]; $actions=[]; $filters=[]; function add_action($tag,$fn){$GLOBALS['actions'][$tag]=$fn;} function add_filter($tag,$fn){$GLOBALS['filters'][$tag]=$fn;} require $argv[1]; if(isset($actions['all'])){($actions['all'])('pre_transient_other_cache'); ($actions['all'])('pre_transient_npcink_toolbox_editor_site_knowledge_abc');} echo json_encode(array_keys($filters));`;
	const invoke = (token, method = 'POST', uri = '/wp-json/npcink-toolbox/v1/editor/content-support') => JSON.parse(execFileSync('php', ['-r', phpProbe, probeFile, token, method, uri], { encoding: 'utf8' }));
	assert.deepEqual(invoke('wrong-token'), []);
	assert.deepEqual(invoke(probe.headers['X-Npcink-Smoke-Fresh-Cloud'], 'GET'), []);
	assert.deepEqual(invoke(probe.headers['X-Npcink-Smoke-Fresh-Cloud'], 'POST', '/wp-json/other'), []);
	assert.deepEqual(invoke(probe.headers['X-Npcink-Smoke-Fresh-Cloud']), ['pre_transient_npcink_toolbox_editor_site_knowledge_abc']);
	probe.cleanup();
	assert.equal(readdirSync(probeDirectory).length, 0);
	console.log('PASS: fresh Cloud probe is local-only, request-token scoped, cache-specific and removable');
} finally { rmSync(probeRoot, { recursive: true, force: true }); }

const setupFailure = recommendationCloudObservation({ phase: 'editor_setup_failed', errorName: 'TimeoutError' });
assert.equal(setupFailure.error_class, 'editor_setup_failed');
assert.equal(setupFailure.retrieval_status, 'unknown', 'An editor login/setup failure is not evidence of Cloud unavailability');

for (const family of ['internal_links', 'related_content']) {
 const payload = { sections: { [family]: { ranking_version: family + '.v2', status: 'ready',
  retrieval_source: 'no_evidence', fallback_used: false, results: [], recommendation_candidates: [],
  result_grouping: { recommendation_quality: { abstained: true } },
 } } };
 const event = helpers.recommendationImpressionFeedbackPayload(payload, family, 'empty-' + family);
 assert.equal(event.ranking_version, family + '.v2');
 assert.equal(event.retrieval_source, 'no_evidence');
 assert.equal(event.candidate_rank, undefined);
 assert.ok(event.source_reason_codes.includes('candidate_count_0'));
 assert.ok(event.source_reason_codes.includes('recommendation_abstained'));
 assert.ok(event.source_reason_codes.includes('fallback_not_used'));
 assert.equal(event.evidence_ref_ids.length, 0);
 assert.equal(/"(?:content|anchor|title|url|score)"\s*:/.test(JSON.stringify(event)), false);
}
console.log('PASS: versioned healthy abstention feedback has zero denominator and no fabricated rank');

// Render the real sidebar section with WordPress element stubs. Keep this
// test-only hook out of the shipped helper API.
const renderWindow = {
 location: windowObject.location,
 wp: {
  element: { createElement: (type, props, ...children) => ({ type, props: props || {}, children: children.flat(Infinity).filter((child) => child !== null && child !== false) }), useState() {}, useEffect() {}, useRef() {} },
  data: { useSelect() {} },
  plugins: { registerPlugin() {} },
  editor: { PluginSidebar: 'sidebar' },
  richText,
 },
};
const renderHook = '\tplugins.registerPlugin(PLUGIN_NAME, {';
assert.ok(source.includes(renderHook));
vm.runInNewContext(source.replace(renderHook, '\twindow.renderInternalLinksForTest = renderInternalLinkCandidateSection;\n' + renderHook), { window: renderWindow, URL }, { filename: 'editor-citation-render.js' });
function descendants(node, predicate, insideDetails = false) {
 if (!node || typeof node !== 'object') return [];
 const hidden = insideDetails || node.type === 'details';
 return (predicate(node, hidden) ? [node] : []).concat(node.children.flatMap((child) => descendants(child, predicate, hidden)));
}
function renderedText(node) {
 return typeof node === 'string' ? node : node && node.children ? node.children.map(renderedText).join(' ') : '';
}
const renderItems = Array.from({ length: 9 }, (_, index) => ({
 id: String(index + 1), label: 'Article ' + String(index + 1),
 candidate_relevance: 'review', candidate_source: 'cloud_vector',
 target_ref: { post_id: 4000 + index, status: 'publish', post_type: 'post', url: 'https://example.test/article-' + index + '/' },
 can_apply_to_editor: index === 1, anchor_or_context: index === 1 ? 'Alpha' : '',
 source_match: index === 1 ? alphaCandidate.sourceMatch : null,
}));
const renderSection = { retrieval_status: 'cloud_vector_evidence', recommendation_candidates: renderItems };
const renderActions = [];
const renderControls = { internalLinks: { blocks: [batchBlock],
 open: (item) => renderActions.push('open:' + item.id),
 copy: (item) => renderActions.push('copy:' + item.id),
 applyBatch: (items) => renderActions.push('apply:' + items.map((item) => item.id).join(',')),
} };
const rendered = renderWindow.renderInternalLinksForTest(renderSection, renderControls);
const articleRows = descendants(rendered, (node, hidden) => node.type === 'li' && !hidden);
assert.deepEqual(articleRows.map((row) => row.props['data-candidate-rank']), [1, 2, 3, 4, 5, 6, 7, 8], 'Insertion readiness never moves a lower-ranked article ahead or folds useful candidates');
for (const row of articleRows) {
 assert.equal(descendants(row, (node) => node.type === 'button' && renderedText(node) === 'Open article').length, 1);
 assert.equal(descendants(row, (node) => node.type === 'button' && renderedText(node) === 'Copy link').length, 1);
}
assert.equal(descendants(articleRows[0], (node) => renderedText(node) === 'Add citation').length, 0, 'An article without an anchor remains available without gaining Apply');
for (const label of ['Open article', 'Copy link']) descendants(articleRows[0], (node) => node.type === 'button' && renderedText(node) === label)[0].props.onClick();
descendants(articleRows[1], (node) => node.type === 'button' && renderedText(node) === 'Add citation')[0].props.onClick();
assert.deepEqual(renderActions, ['open:1', 'copy:1', 'apply:2']);
assert.equal(descendants(articleRows[1], (node, hidden) => node.type === 'blockquote' && !hidden).length, 1, 'Apply context stays visible for author review');
assert.equal(descendants(articleRows[0], (node, hidden) => node.props.className === 'npcink-toolbox-editor-support__candidate-policy' && !hidden).length, 0, 'Insertion diagnostics are secondary to article selection');
const batchRendered = renderWindow.renderInternalLinksForTest(renderSection, { internalLinks: { ...renderControls.internalLinks, batchMode: true, selected: { '1': true, '2': true } } });
const applySelected = descendants(batchRendered, (node) => node.type === 'button' && renderedText(node).startsWith('Apply '))[0];
applySelected.props.onClick();
assert.equal(renderActions.at(-1), 'apply:2', 'Batch selections still exclude articles that fail insertion checks');
const weakRendered = renderWindow.renderInternalLinksForTest({ ...renderSection, recommendation_candidates: renderItems.map((item, index) => ({ ...item, candidate_relevance: index === 0 ? 'weak' : 'review' })) }, renderControls);
assert.deepEqual(descendants(weakRendered, (node, hidden) => node.type === 'li' && !hidden).map((row) => row.props['data-candidate-rank']), [2, 3, 4, 5, 6, 7, 8, 9], 'Weak candidates stay outside the main list');
console.log('PASS: article-first recommendation rendering preserves ranking, manual actions, visible Apply context and live insertion guards');
