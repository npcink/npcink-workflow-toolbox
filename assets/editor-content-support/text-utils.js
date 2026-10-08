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
		// DOMParser documents are inert by specification: no script
		// execution and no resource fetches, unlike innerHTML on a
		// detached div, where inline handlers and image loads in block
		// HTML can still fire while the text is extracted.
		if (typeof window !== 'undefined' && typeof window.DOMParser === 'function') {
			const parsed = new window.DOMParser().parseFromString(source, 'text/html');
			return String(parsed.body.textContent || '').replace(/\s+/g, ' ').trim();
		}
		// Best-effort tag stripping for environments without DOMParser:
		// malformed markup with '>' inside attribute values can leak tag text.
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
