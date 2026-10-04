/**
 * Internal-link cluster for the editor content-support bundle.
 *
 * Loaded after text-utils.js and before assets/editor-content-support.js
 * through the npcink-toolbox-editor-content-support-internal-links handle.
 * Every function is pure editor-state logic: rich-text APIs and block trees
 * arrive as parameters, no WordPress requests, no translations, no DOM side
 * effects. The frozen window.NpcinkToolboxInternalLinkHelpers namespace and
 * its member list predate this split; the main bundle merges the remaining
 * feedback builders on top of it.
 */
(function () {
	'use strict';

	const { plainTextFromHtml, truncateText } = (typeof window !== 'undefined' && window.NpcinkToolboxTextHelpers) || {};

	function canonicalInternalLinkUrl(value) {
		try {
			const parsed = new URL(String(value || '').trim(), window.location && window.location.href ? window.location.href : undefined);
			if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') return '';
			parsed.hash = '';
			parsed.hostname = parsed.hostname.toLowerCase();
			parsed.pathname = parsed.pathname.replace(/\/+$/, '') || '/';
			return parsed.toString();
		} catch (error) {
			return '';
		}
	}

	function internalLinkUrlIsSafe(value) {
		const canonical = canonicalInternalLinkUrl(value);
		const current = canonicalInternalLinkUrl(window.location && window.location.href);
		if (!canonical || !current) return false;
		return new URL(canonical).origin === new URL(current).origin;
	}

	function internalLinkAnchorIsSpecific(value) {
		const normalized = String(value || '').trim().replace(/[\p{P}\p{Z}\s]+/gu, '').toLocaleLowerCase();
		const genericAnchors = ['主题', '文章', '内容', '这里', '本文', '本页', 'topic', 'article', 'content', 'here', 'this', 'post', 'page', 'link', 'readmore'];
		return Array.from(normalized).length >= 4 && !genericAnchors.includes(normalized);
	}

	function internalLinkRangesOverlap(ranges) {
		const normalized = (Array.isArray(ranges) ? ranges : [])
			.filter((range) => Array.isArray(range) && range.length === 2 && Number.isInteger(Number(range[0])) && Number.isInteger(Number(range[1])))
			.map((range) => [Number(range[0]), Number(range[1])])
			.filter((range) => range[0] >= 0 && range[1] > range[0])
			.sort((left, right) => left[0] - right[0]);
		return normalized.some((range, index) => index > 0 && range[0] < normalized[index - 1][1]);
	}

	function internalLinkBatchPreflight(input) {
		const candidate = input && typeof input === 'object' ? input : {};
		const reject = (reason) => ({ outcome: 'rejected', reason_codes: [reason] });
		if (!internalLinkUrlIsSafe(candidate.targetUrl)) return reject('invalid_internal_url');
		if (candidate.targetStatus !== 'publish') return reject('target_not_public');
		if (candidate.targetAlreadyLinked === true) return reject('target_already_linked');
		const rawTargetPostIds = Array.isArray(candidate.targetPostIds) ? candidate.targetPostIds : [];
		const targetPostIds = rawTargetPostIds.map(Number).filter((id) => Number.isInteger(id) && id > 0);
		if (!targetPostIds.length || targetPostIds.length !== rawTargetPostIds.length) return reject('target_not_public');
		if (new Set(targetPostIds).size !== targetPostIds.length) return reject('duplicate_target');
		if (internalLinkRangesOverlap(candidate.ranges)) return reject('overlapping_source_ranges');
		if (candidate.retrievalStatus !== 'cloud_vector_evidence' || candidate.candidateSource !== 'cloud_vector') {
			return { outcome: 'review_only', reason_codes: ['fallback_must_not_be_labeled_vector'] };
		}
		const match = candidate.sourceMatch && typeof candidate.sourceMatch === 'object' ? candidate.sourceMatch : null;
		if (!match || !match.matched_text || !match.expected_text || !match.block_client_id || match.text_offset === '' || match.text_offset === null || match.text_offset === undefined || !Number.isInteger(Number(match.text_offset))) return reject('missing_exact_source_match');
		if (match.block_name === 'core/heading') return reject('heading_match_not_eligible');
		if (['core/code', 'core-code'].indexOf(match.block_name) >= 0) return reject('code_block_match_not_eligible');
		if (!internalLinkAnchorIsSpecific(candidate.anchorText)) return reject('generic_anchor');
		if (String(candidate.anchorText) !== String(match.matched_text)) return reject('missing_exact_source_match');
		if (String(match.expected_text || '') !== String(candidate.currentText || '')) return reject('stale_editor_block');
		return { outcome: 'eligible', reason_codes: [] };
	}

	function internalLinkMatchRange(text, phrase, textOffset) {
		const source = String(text || '');
		const needle = String(phrase || '');
		if (!needle) return null;
		const characterOffset = Number.isInteger(Number(textOffset)) ? Math.max(0, Number(textOffset)) : -1;
		const expectedStart = characterOffset >= 0 ? Array.from(source).slice(0, characterOffset).join('').length : -1;
		const matchesAtExpectedOffset = expectedStart >= 0
			&& source.slice(expectedStart, expectedStart + needle.length).toLocaleLowerCase() === needle.toLocaleLowerCase();
		const start = matchesAtExpectedOffset ? expectedStart : source.toLocaleLowerCase().indexOf(needle.toLocaleLowerCase());
		return start >= 0 ? { start, end: start + needle.length, text: source.slice(start, start + needle.length) } : null;
	}

	function internalLinkRangeHasLink(value, start, end) {
		const formats = value && Array.isArray(value.formats) ? value.formats : [];
		return formats.slice(start, end).some((entries) => Array.isArray(entries) && entries.some((format) => format && format.type === 'core/link'));
	}

	function internalLinkBlockContent(block) {
		const content = block && block.attributes ? block.attributes.content : null;
		if (typeof content === 'string') return content;
		if (content && typeof content.toHTMLString === 'function') {
			return String(content.toHTMLString());
		}
		return '';
	}

	function internalLinkBlocksContainUrl(blocksToInspect, targetUrl, richTextApi) {
		const canonicalTarget = canonicalInternalLinkUrl(targetUrl);
		if (!canonicalTarget || !richTextApi || !richTextApi.create) return false;
		let found = false;
		function inspect(block) {
			if (found || !block || typeof block !== 'object') return;
			const content = internalLinkBlockContent(block);
			if (content) {
				const value = richTextApi.create({ html: content });
				const formats = value && Array.isArray(value.formats) ? value.formats : [];
				found = formats.some((entries) => Array.isArray(entries) && entries.some((format) => {
					return format && format.type === 'core/link' && canonicalInternalLinkUrl(format.attributes && format.attributes.url) === canonicalTarget;
				}));
			}
			(Array.isArray(block.innerBlocks) ? block.innerBlocks : []).forEach(inspect);
		}
		(Array.isArray(blocksToInspect) ? blocksToInspect : []).forEach(inspect);
		return found;
	}

	function internalLinkCount(value) {
		const formats = value && Array.isArray(value.formats) ? value.formats : [];
		const siteHost = canonicalInternalLinkUrl(window.location && window.location.href).replace(/^https?:\/\//, '').split('/')[0];
		let count = 0;
		let insideLink = false;
		formats.forEach((entries) => {
			const linked = Array.isArray(entries) && entries.some((format) => {
				if (!format || format.type !== 'core/link') return false;
				const linkHost = canonicalInternalLinkUrl(format.attributes && format.attributes.url).replace(/^https?:\/\//, '').split('/')[0];
				return Boolean(siteHost && linkHost === siteHost);
			});
			if (linked && !insideLink) count += 1;
			insideLink = linked;
		});
		return count;
	}

	function internalLinkEditorPolicy(blocksToInspect, sourceBlockId, richTextApi) {
		let articleCharacters = 0;
		let articleLinkCount = 0;
		let sourceBlockLinkCount = 0;
		function inspect(block) {
			if (!block || typeof block !== 'object') return;
			const content = internalLinkBlockContent(block);
			if (content && richTextApi && richTextApi.create) {
				const value = richTextApi.create({ html: content });
				const linkCount = internalLinkCount(value);
				articleCharacters += Array.from(String(value && value.text || '')).length;
				articleLinkCount += linkCount;
				if (String(block.clientId || '') === String(sourceBlockId || '')) sourceBlockLinkCount += linkCount;
			}
			(Array.isArray(block.innerBlocks) ? block.innerBlocks : []).forEach(inspect);
		}
		(Array.isArray(blocksToInspect) ? blocksToInspect : []).forEach(inspect);
		const maximumLinks = Math.min(8, Math.max(2, Math.ceil(articleCharacters / 1000)));
		return {
			articleCharacters,
			articleLinkCount,
			sourceBlockLinkCount,
			maximumLinks,
			canApply: articleLinkCount < maximumLinks && sourceBlockLinkCount < 2,
			error: articleLinkCount >= maximumLinks ? 'article_link_density_reached' : (sourceBlockLinkCount >= 2 ? 'block_link_density_reached' : ''),
		};
	}

	function prepareInternalLinkApplication(candidate, block, allBlocks, richTextApi) {
		const match = candidate && candidate.sourceMatch;
		const url = String(candidate && candidate.targetUrl || '').trim();
		if (!candidate || candidate.canApplyToEditor !== true || !match || !match.block_client_id || !match.matched_text || !canonicalInternalLinkUrl(url) || !richTextApi || !richTextApi.create || !richTextApi.applyFormat || !richTextApi.toHTMLString) {
			return { error: 'missing_exact_match' };
		}
		const current = internalLinkBlockContent(block);
		if (!current || plainTextFromHtml(current) !== String(match.expected_text || '')) {
			return { error: 'stale_block' };
		}
		const value = richTextApi.create({ html: current });
		const range = internalLinkMatchRange(value && value.text, match.matched_text, match.text_offset);
		if (!range) return { error: 'missing_phrase' };
		if (internalLinkRangeHasLink(value, range.start, range.end)) return { error: 'range_already_linked' };
		if (internalLinkBlocksContainUrl(allBlocks, url, richTextApi)) return { error: 'target_already_linked' };
		const editorPolicy = internalLinkEditorPolicy(allBlocks, match.block_client_id, richTextApi);
		if (!editorPolicy.canApply) return { error: editorPolicy.error, editorPolicy };
		const formatted = richTextApi.applyFormat(value, { type: 'core/link', attributes: { url } }, range.start, range.end);
		return {
			appliedContent: richTextApi.toHTMLString({ value: formatted }),
			content: current,
			matchedText: range.text,
		};
	}

	function internalLinkSelectedText(blockEditorSelector) {
		if (!blockEditorSelector || typeof blockEditorSelector.getSelectionStart !== 'function' || typeof blockEditorSelector.getSelectionEnd !== 'function' || typeof blockEditorSelector.getBlock !== 'function') {
			return '';
		}
		const start = blockEditorSelector.getSelectionStart() || {};
		const end = blockEditorSelector.getSelectionEnd() || {};
		if (!start.clientId || start.clientId !== end.clientId || !start.attributeKey || start.attributeKey !== end.attributeKey || !Number.isInteger(start.offset) || !Number.isInteger(end.offset) || end.offset <= start.offset) {
			return '';
		}
		const block = blockEditorSelector.getBlock(start.clientId);
		const attribute = block && block.attributes ? block.attributes[start.attributeKey] : '';
		const text = attribute && typeof attribute.toString === 'function' ? attribute.toString() : String(attribute || '');
		return truncateText(text.slice(start.offset, end.offset).replace(/\s+/g, ' ').trim(), 80);
	}

	function canUndoInternalLink(block, undoState) {
		return Boolean(undoState && internalLinkBlockContent(block) === undoState.appliedContent);
	}

	function internalLinkFindBlock(blocksToInspect, clientId) {
		let found = null;
		function inspect(block) {
			if (found || !block || typeof block !== 'object') return;
			if (String(block.clientId || '') === String(clientId || '')) {
				found = block;
				return;
			}
			(Array.isArray(block.innerBlocks) ? block.innerBlocks : []).forEach(inspect);
		}
		(Array.isArray(blocksToInspect) ? blocksToInspect : []).forEach(inspect);
		return found;
	}

	function internalLinkCloneBlocks(blocksToClone) {
		return (Array.isArray(blocksToClone) ? blocksToClone : []).map((block) => Object.assign({}, block, {
			attributes: Object.assign({}, block && block.attributes ? block.attributes : {}),
			innerBlocks: internalLinkCloneBlocks(block && block.innerBlocks),
		}));
	}

	function internalLinkBatchReason(error) {
		const reasons = {
			missing_exact_match: 'missing_exact_source_match',
			stale_block: 'stale_editor_block',
			missing_phrase: 'missing_exact_source_match',
			range_already_linked: 'range_already_linked',
			target_already_linked: 'target_already_linked',
			article_link_density_reached: 'article_link_density_reached',
			block_link_density_reached: 'block_link_density_reached',
		};
		return reasons[error] || error || 'apply_unavailable';
	}

	function prepareInternalLinkBatchApplication(candidates, allBlocks, richTextApi) {
		const selected = Array.isArray(candidates) ? candidates : [];
		const baseResult = {
			schema: 'current_article_multi_link_result.v1',
			write_posture: 'native_editor_commit',
			direct_wordpress_write: false,
			persisted: false,
			selected_count: selected.length,
			applied_count: 0,
			rejected_count: selected.length,
			items: [],
			updates: [],
			undo: null,
		};
		if (!selected.length) return baseResult;
		if (selected.length > 8) {
			baseResult.items = selected.map((candidate, index) => ({
				id: String(candidate && candidate.id || index + 1),
				outcome: 'rejected',
				reason_codes: ['selection_limit_exceeded'],
			}));
			return baseResult;
		}

		const workingBlocks = internalLinkCloneBlocks(allBlocks);
		const entries = selected.map((candidate, index) => {
			const match = candidate && candidate.sourceMatch && typeof candidate.sourceMatch === 'object' ? candidate.sourceMatch : {};
			const block = internalLinkFindBlock(workingBlocks, match.block_client_id);
			const currentContent = internalLinkBlockContent(block);
			const value = currentContent && richTextApi && richTextApi.create ? richTextApi.create({ html: currentContent }) : null;
			const range = internalLinkMatchRange(value && value.text, match.matched_text, match.text_offset);
			const preflight = internalLinkBatchPreflight({
				anchorText: candidate && candidate.anchorText,
				targetUrl: candidate && candidate.targetUrl,
				targetStatus: candidate && candidate.targetStatus,
				targetPostIds: [candidate && candidate.targetPostId],
				ranges: range ? [[range.start, range.end]] : [],
				retrievalStatus: candidate && candidate.retrievalStatus,
				candidateSource: candidate && candidate.candidateSource,
				sourceMatch: match,
				currentText: plainTextFromHtml(currentContent),
			});
			return {
				candidate,
				index,
				id: String(candidate && candidate.id || index + 1),
				match,
				range,
				target: canonicalInternalLinkUrl(candidate && candidate.targetUrl),
				targetIdentity: Number(candidate && candidate.targetPostId) > 0
					? 'post:' + String(Number(candidate.targetPostId))
					: 'url:' + canonicalInternalLinkUrl(candidate && candidate.targetUrl),
				preflight,
			};
		});

		const targetCounts = {};
		entries.forEach((entry) => {
			if (entry.targetIdentity !== 'url:') targetCounts[entry.targetIdentity] = (targetCounts[entry.targetIdentity] || 0) + 1;
		});
		const overlapIds = {};
		entries.forEach((entry, index) => {
			if (!entry.range || !entry.match.block_client_id) return;
			entries.slice(index + 1).forEach((other) => {
				if (!other.range || String(entry.match.block_client_id) !== String(other.match.block_client_id)) return;
				if (entry.range.start < other.range.end && other.range.start < entry.range.end) {
					overlapIds[entry.index] = true;
					overlapIds[other.index] = true;
				}
			});
		});

		const outcomes = [];
		const eligible = [];
		entries.forEach((entry) => {
			let reason = '';
			if (entry.preflight.outcome !== 'eligible') reason = entry.preflight.reason_codes[0] || 'apply_unavailable';
			if (!reason && targetCounts[entry.targetIdentity] > 1) reason = 'duplicate_target';
			if (!reason && overlapIds[entry.index]) reason = 'overlap_conflict';
			if (!reason && !entry.range) reason = 'missing_exact_source_match';
			if (reason) {
				outcomes[entry.index] = { id: entry.id, outcome: 'rejected', reason_codes: [reason] };
			} else {
				eligible.push(entry);
			}
		});

		eligible.sort((left, right) => {
			const blockOrder = String(left.match.block_client_id).localeCompare(String(right.match.block_client_id));
			return blockOrder || right.range.start - left.range.start;
		});
		const originalByBlock = {};
		eligible.forEach((entry) => {
			const block = internalLinkFindBlock(workingBlocks, entry.match.block_client_id);
			if (block && !Object.prototype.hasOwnProperty.call(originalByBlock, entry.match.block_client_id)) {
				originalByBlock[entry.match.block_client_id] = internalLinkBlockContent(block);
			}
			const prepared = prepareInternalLinkApplication(entry.candidate, block, workingBlocks, richTextApi);
			if (prepared.error) {
				outcomes[entry.index] = { id: entry.id, outcome: 'rejected', reason_codes: [internalLinkBatchReason(prepared.error)] };
				return;
			}
			block.attributes.content = prepared.appliedContent;
			outcomes[entry.index] = { id: entry.id, outcome: 'applied', reason_codes: [] };
		});

		const appliedCount = outcomes.filter((item) => item && item.outcome === 'applied').length;
		const updates = Object.keys(originalByBlock).map((blockClientId) => {
			const block = internalLinkFindBlock(workingBlocks, blockClientId);
			return {
				blockClientId,
				content: originalByBlock[blockClientId],
				appliedContent: internalLinkBlockContent(block),
			};
		}).filter((update) => update.content !== update.appliedContent);
		return Object.assign({}, baseResult, {
			applied_count: appliedCount,
			rejected_count: selected.length - appliedCount,
			items: outcomes,
			updates,
			undo: appliedCount ? { blocks: updates } : null,
		});
	}

	function canUndoInternalLinkBatch(blocksToInspect, undoState) {
		const snapshots = undoState && Array.isArray(undoState.blocks) ? undoState.blocks : [];
		return Boolean(snapshots.length && snapshots.every((snapshot) => {
			const block = internalLinkFindBlock(blocksToInspect, snapshot.blockClientId);
			return internalLinkBlockContent(block) === snapshot.appliedContent;
		}));
	}
	function recommendationCountBucket(prefix, count) {
		const total = Math.max(0, parseInt(count || 0, 10) || 0);
		if (total === 0) {
			return prefix + '_0';
		}
		if (total <= 3) {
			return prefix + '_1_3';
		}
		if (total <= 8) {
			return prefix + '_4_8';
		}
		return prefix + '_9_plus';
	}
	function internalLinkTelemetryFingerprint(value) {
		const text = String(value || '');
		let hash = 2166136261;
		for (let index = 0; index < text.length; index += 1) {
			hash ^= text.charCodeAt(index);
			hash = Math.imul(hash, 16777619);
		}
		return text.length.toString(36) + '_' + (hash >>> 0).toString(36);
	}
	function internalLinkTelemetrySnapshots(updates) {
		return (Array.isArray(updates) ? updates : []).map((update) => ({
			blockClientId: String(update && update.blockClientId || ''),
			appliedFingerprint: internalLinkTelemetryFingerprint(update && update.appliedContent),
		}));
	}
	function internalLinkSavedStateChanged(blocks, snapshots) {
		return (Array.isArray(snapshots) ? snapshots : []).some((snapshot) => {
			const block = internalLinkFindBlock(blocks, snapshot.blockClientId);
			return !block || internalLinkTelemetryFingerprint(internalLinkBlockContent(block)) !== snapshot.appliedFingerprint;
		});
	}
	function internalLinkContractValue(value) {
		return typeof value === 'string' ? value.trim() : '';
	}
	function dedupeInternalLinkCandidates(items) {
		const seenTargets = {};
		const seenPlacements = {};
		return (Array.isArray(items) ? items : []).filter((item) => {
			const target = canonicalInternalLinkUrl(item && item.targetUrl);
			const match = item && item.sourceMatch || {};
			const placement = [String(match.block_client_id || ''), String(match.matched_text || '').toLocaleLowerCase()].join(':');
			if ((target && seenTargets[target]) || (placement !== ':' && seenPlacements[placement])) return false;
			if (target) seenTargets[target] = true;
			if (placement !== ':') seenPlacements[placement] = true;
			return true;
		});
	}
	function internalLinkSourcePreview(sourceMatch, maxLength) {
		const match = sourceMatch && typeof sourceMatch === 'object' ? sourceMatch : {};
		const sourceText = String(match.expected_text || '').trim();
		const matchedText = String(match.matched_text || '').trim();
		const limit = Math.max(80, Number(maxLength || 180));
		if (!sourceText || !matchedText) return null;

		let offset = Number(match.text_offset);
		if (!Number.isInteger(offset) || offset < 0 || sourceText.slice(offset, offset + matchedText.length).toLocaleLowerCase() !== matchedText.toLocaleLowerCase()) {
			offset = sourceText.toLocaleLowerCase().indexOf(matchedText.toLocaleLowerCase());
		}
		if (offset < 0) return null;

		const available = Math.max(0, limit - matchedText.length);
		let start = sourceText.length <= limit ? 0 : Math.max(0, offset - Math.floor(available / 2));
		const end = sourceText.length <= limit ? sourceText.length : Math.min(sourceText.length, start + limit);
		start = Math.max(0, end - limit);
		return {
			before: sourceText.slice(start, offset),
			match: sourceText.slice(offset, offset + matchedText.length),
			after: sourceText.slice(offset + matchedText.length, end),
			clippedBefore: start > 0,
			clippedAfter: end < sourceText.length,
		};
	}

	if (typeof window !== 'undefined') {
		window.NpcinkToolboxInternalLinkHelpers = Object.freeze({
			canonicalInternalLinkUrl,
			internalLinkUrlIsSafe,
			internalLinkAnchorIsSpecific,
			internalLinkRangesOverlap,
			internalLinkBatchPreflight,
			internalLinkMatchRange,
			internalLinkRangeHasLink,
			internalLinkBlocksContainUrl,
			internalLinkCount,
			internalLinkEditorPolicy,
			dedupeInternalLinkCandidates,
			prepareInternalLinkApplication,
			prepareInternalLinkBatchApplication,
			internalLinkSelectedText,
			canUndoInternalLink,
			canUndoInternalLinkBatch,
			internalLinkContractValue,
			recommendationCountBucket,
			internalLinkTelemetrySnapshots,
			internalLinkSavedStateChanged,
			internalLinkSourcePreview,
		});
	}
}());
