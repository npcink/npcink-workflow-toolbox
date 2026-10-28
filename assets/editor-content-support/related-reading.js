/** Pure current-article reading-list construction and conservative Undo. */
(function () {
	'use strict';

	const { canonicalInternalLinkUrl, internalLinkUrlIsSafe, internalLinkBlocksContainUrl } = window.NpcinkToolboxInternalLinkHelpers || {};

	function relatedReadingSelection(candidates, selected, blocks, currentPostId, sourcePostId, richTextApi) {
		const fail = (reason) => ({ items: [], reason });
		if ([canonicalInternalLinkUrl, internalLinkUrlIsSafe, internalLinkBlocksContainUrl].some((helper) => typeof helper !== 'function')) return fail('editor_unavailable');
		if (!Number.isInteger(currentPostId) || currentPostId < 1 || currentPostId !== sourcePostId) return fail('article_changed');
		if (!richTextApi || typeof richTextApi.create !== 'function') return fail('editor_unavailable');
		if (!Array.isArray(candidates) || !Array.isArray(selected) || !selected.length || selected.length > 8) return fail('invalid_selection');
		if (new Set(selected).size !== selected.length) return fail('invalid_selection');
		const chosen = candidates.filter((item) => selected.includes(item.id));
		if (chosen.length !== selected.length) return fail('invalid_selection');
		const seenIds = new Set();
		const seenUrls = new Set();
		for (const item of chosen) {
			const url = canonicalInternalLinkUrl(item.targetUrl);
			if (!/^https?:\/\//i.test(String(item.targetUrl || '')) || !internalLinkUrlIsSafe(item.targetUrl)
				|| !Number.isInteger(item.targetPostId) || item.targetPostId < 1 || item.targetPostId === currentPostId
				|| item.targetStatus !== 'publish' || !['post', 'page'].includes(item.targetPostType)
				|| item.candidateRelevance === 'weak' || !String(item.title || '').trim()) return fail('invalid_target');
			if (seenIds.has(item.targetPostId) || seenUrls.has(url) || internalLinkBlocksContainUrl(blocks, item.targetUrl, richTextApi)) return fail('already_linked');
			seenIds.add(item.targetPostId);
			seenUrls.add(url);
		}
		return { items: chosen, reason: '' };
	}

	function buildRelatedReadingGroup(items, title, createBlock) {
		const escape = (value) => String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
		return createBlock('core/group', {}, [
			createBlock('core/heading', { level: 3, content: escape(title) }),
			createBlock('core/list', { ordered: false }, items.map((item) => createBlock('core/list-item', {
				content: '<a href="' + escape(item.targetUrl) + '">' + escape(item.title) + '</a>',
			}))),
		]);
	}

	function relatedReadingCanUndo(blocks, receipt, currentPostId, serialize) {
		if (!receipt || currentPostId !== receipt.postId || typeof serialize !== 'function') return false;
		const group = (Array.isArray(blocks) ? blocks : []).find((block) => block.clientId === receipt.clientId);
		return Boolean(group && serialize([group]) === receipt.snapshot);
	}

	window.NpcinkToolboxRelatedReading = Object.freeze({ relatedReadingSelection, buildRelatedReadingGroup, relatedReadingCanUndo });
})();
