/**
 * Pure text helpers for the editor content-support bundle.
 *
 * Loaded before assets/editor-content-support.js through the
 * npcink-toolbox-editor-content-support-text-utils script handle.
 * This part carries no translations and no editor state.
 */
(function () {
	'use strict';

	function normalizeText(value) {
		if (value && typeof value === 'object' && value.raw !== undefined) {
			return String(value.raw || '');
		}
		return String(value || '');
	}

	function plainTextFromHtml(value) {
		const source = String(value || '');
		if (!source) {
			return '';
		}
		if (typeof window !== 'undefined' && window.document) {
			const container = window.document.createElement('div');
			container.innerHTML = source;
			return String(container.textContent || container.innerText || '').replace(/\s+/g, ' ').trim();
		}
		return source.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
	}

	function truncateText(value, maxLength) {
		const text = String(value || '').trim();
		if (!text || text.length <= maxLength) {
			return text;
		}
		return text.slice(0, maxLength - 1).trim() + '...';
	}

	if (typeof window !== 'undefined') {
		window.NpcinkToolboxTextHelpers = Object.freeze({
			normalizeText,
			plainTextFromHtml,
			truncateText,
		});
	}
}());
