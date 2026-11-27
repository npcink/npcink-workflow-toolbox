(function (wp) {
	'use strict';
	if (!wp?.plugins || !wp?.data || !wp?.element || !wp?.components) return;
	const { createElement: h, Fragment, useMemo, useRef, useState } = wp.element;
	const { Button, Modal, Notice } = wp.components;
	const { __, sprintf } = wp.i18n;
	const config = window.NpcinkArticleCredentialRules;
	if (!config) return;
	const rules = Object.entries(config.rules).map(([id, rule]) => ({ id, ...rule, regex: new RegExp(rule.pattern, rule.flags + 'g') }));
	const encoder = new TextEncoder();
	const editableFields = { 'core/code': 'content', 'core/paragraph': 'content', 'core/heading': 'content', 'core/list-item': 'content' };
	const maxFindings = 30;
	const emptyMessage = __('No potential credentials found in the checked fields.', 'npcink-workflow-toolbox');
	const partialMessage = __('Credential check incomplete. Some content or findings could not be shown.', 'npcink-workflow-toolbox');
	const rotationMessage = __('If already shared, revoke or rotate the credential. Replacing it here does not invalidate it.', 'npcink-workflow-toolbox');
	const hitCode = 'npcink_article_credential_check_hit';
	const limitCode = 'npcink_article_credential_check_limit';
	const faqTitle = __('FAQ from article body', 'npcink-workflow-toolbox');
	const RichTextData = wp.richText?.RichTextData;
	const isRichText = (value) => Boolean(RichTextData && value instanceof RichTextData);
	const fieldText = (value) => isRichText(value) ? value.toHTMLString({ preserveWhiteSpace: true }) : (typeof value === 'string' ? value : '');

	function decode(text) {
		// Decode only character references via a detached textarea; never pass markup to the parser.
		return text.replace(/&(?:#[0-9]{1,7}|#x[0-9a-f]{1,6}|[a-z][a-z0-9]{1,31});/gi, (entity) => {
			const field = document.createElement('textarea');
			field.innerHTML = entity;
			return field.value;
		});
	}

	function withoutMarkup(text) {
		// Keep the same block separators as the PHP projection without rendering article HTML.
		return text.replace(/<!--(?:[^-]|-(?!->))*-->|<\/?[a-z][\w:-]*(?=[\s/>])(?:[^<>"']|"[^"]*"|'[^']*')*>/gi, (tag) =>
			/^<(?:br(?=[\s/>])|\/(?:p|h[1-6]|li|div|pre|blockquote|td|th|tr|figcaption)\s*>)/i.test(tag) ? '\n' : '');
	}

	function matchesEither(text, projected, rule) {
		return matches(text, rule).length || (projected !== text && matches(projected, rule).length);
	}

	function matches(text, rule, firstOnly = true) {
		const decoded = decode(text);
		const found = [];
		rule.regex.lastIndex = 0;
		for (const match of text.matchAll(rule.regex)) {
			found.push(decoded !== text ? null : { start: match.index, end: match.index + match[0].length });
			if (firstOnly || found.length > maxFindings) break;
		}
		rule.regex.lastIndex = 0;
		const count = (value) => {
			let size = 0;
			for (const match of value.matchAll(rule.regex)) {
				if (match && ++size > maxFindings) break;
			}
			return size;
		};
		if (decoded !== text && count(text) < count(decoded)) {
			found.push(null);
		}
		return found;
	}

	function transferFields(payload) {
		const fields = [{ kind: 'body', text: payload.content }];
		if (payload.intent === 'publish_preflight') fields.push(
			{ kind: 'title', text: payload.title }, { kind: 'excerpt', text: payload.excerpt },
			{ kind: 'selection', text: payload.selected_text }, { kind: 'selection', text: payload.selected_block_text },
			{ kind: 'instruction', text: payload.user_instruction }, ...(payload.article_review_snapshot?.blocks || []).slice(0, 80)
		);
		return fields;
	}

	function transferStatus(payload) {
		let bytes = 0;
		for (const { kind, text, client_id: clientId } of transferFields(payload)) {
			if (typeof text !== 'string') continue;
			bytes += encoder.encode(text).length;
			if (bytes > config.maxBytes * 2) return limitCode;
			const projected = kind === 'body' && !clientId ? withoutMarkup(text) : text;
			if (rules.some((rule) => !rule.advisory && matchesEither(text, projected, rule))) return hitCode;
		}
		return '';
	}

	function hasAdvisory(fields) {
		return fields.some((text) => {
			if (typeof text !== 'string' || text.length > config.maxBytes || encoder.encode(text).length > config.maxBytes) return false;
			const projected = withoutMarkup(text);
			return rules.some((rule) => rule.advisory && matchesEither(text, projected, rule));
		});
	}

	function Advisory({ bodyOnly = false, selection = '' } = {}) {
		const { title, excerpt, content } = useSource();
		const flagged = useMemo(() => hasAdvisory(bodyOnly ? [content] : [title, excerpt, content, selection]), [bodyOnly, title, excerpt, content, selection]);
		return flagged ? h(Notice, { status: 'warning', isDismissible: false }, __('Possible password or token assignment. Review before sending; this warning does not block sending.', 'npcink-workflow-toolbox')) : null;
	}

	function guardTransfer(path, payload) {
		if (path.replace(/^\//, '') !== 'editor/content-support' || !['publish_preflight', 'body_faq'].includes(payload?.intent)) return;
		const code = transferStatus(payload);
		if (code) throw Object.assign(new Error(code === limitCode
			? __('The article is too large for the credential check. Nothing was sent to Cloud.', 'npcink-workflow-toolbox')
			: __('Possible credential found. Review it with Check credentials before sending the article to Cloud.', 'npcink-workflow-toolbox')), { code });
	}

	function matchingValues(text, rule, accept) {
		rule.regex.lastIndex = 0;
		for (const [value, captured] of text.matchAll(rule.regex)) if (accept(captured ?? value) === false) break;
	}

	function transferFindings(payload, blocks) {
		const rows = new Map();
		const byBlock = new Map();
		const body = new Map();
		let partial = false;
		let bytes = 0;
		const bodyLocation = __('Article body (manual review)', 'npcink-workflow-toolbox');
		const push = (item) => {
			const key = `${item.block?.clientId || item.location}:${item.rule.id}:${item.span?.start ?? 'manual'}`;
			if (rows.has(key)) return;
			if (rows.size < maxFindings) rows.set(key, item);
			else partial = true;
		};
		for (const { kind, text, client_id: clientId } of transferFields(payload)) {
			if (typeof text !== 'string') continue;
			bytes += encoder.encode(text).length;
			if (bytes > config.maxBytes * 2) { partial = true; break; }
			const projected = kind === 'body' && !clientId ? withoutMarkup(text) : text;
			for (const rule of rules) {
				if (!matchesEither(text, projected, rule)) continue;
				if (kind === 'title' || kind === 'excerpt' || kind === 'selection' || kind === 'instruction') {
					const locations = { title: __('Title', 'npcink-workflow-toolbox'), excerpt: __('Excerpt', 'npcink-workflow-toolbox'), selection: __('Selected text', 'npcink-workflow-toolbox'), instruction: __('Instruction', 'npcink-workflow-toolbox') };
					push({ location: locations[kind], rule });
				} else if (clientId) {
					if (!byBlock.has(clientId)) byBlock.set(clientId, new Set());
					byBlock.get(clientId).add(rule);
				} else {
					const values = new Set();
					const collect = (value) => {
						values.add(value);
						if (values.size <= maxFindings) return true;
						partial = true;
						return false;
					};
					matchingValues(text, rule, collect);
					const decoded = decode(text);
					if (decoded !== text) matchingValues(decoded, rule, collect);
					if (projected !== text) matchingValues(decode(projected), rule, collect);
					body.set(rule, { values, located: new Set() });
				}
			}
		}
		const stack = [...blocks].reverse();
		let index = 0;
		let blockBytes = 0;
		while (stack.length && (body.size || byBlock.size)) {
			const block = stack.pop();
			const location = sprintf(__('Block %d', 'npcink-workflow-toolbox'), ++index);
			const attrs = block.attributes || {};
			const field = editableFields[block.name];
			const value = fieldText(attrs[field]);
			const serialized = JSON.stringify(attrs);
			blockBytes += encoder.encode(serialized).length;
			if (index > 5000 || blockBytes > config.maxBytes) { partial = true; break; }
			const projected = withoutMarkup(value);
			for (const [rule, { values, located }] of body) {
				const editable = block.isValid !== false && rule.replacement && !/[<>]/.test(value);
				const covered = new Set();
				if (editable) for (const span of matches(value, rule, false)) {
					if (!span) continue;
					const exact = value.slice(span.start, span.end);
					if (!values.has(exact)) continue;
					covered.add(exact);
					located.add(exact);
					push({ location, rule, block, field, span });
				}
				const locate = (exact) => {
					if (!values.has(exact)) return;
					located.add(exact);
					if (!covered.has(exact)) push({ location, rule, block });
				};
				matchingValues(decode(serialized), rule, locate);
				if (projected !== value) matchingValues(decode(projected), rule, locate);
			}
			for (const rule of byBlock.get(block.clientId) || []) {
				if (![...rows.values()].some((row) => row.block === block && row.rule === rule)) push({ location, rule, block });
			}
			byBlock.delete(block.clientId);
			for (let i = (block.innerBlocks || []).length - 1; i >= 0; i--) stack.push(block.innerBlocks[i]);
		}
		for (const [rule, { values, located }] of body) if ([...values].some((value) => !located.has(value))) push({ location: bodyLocation, rule });
		for (const unlocated of byBlock.values()) for (const rule of unlocated) push({ location: bodyLocation, rule });
		return { items: [...rows.values()], partial };
	}

	function scan({ title, excerpt, content, blocks }) {
		const items = [];
		const unknown = new Set();
		let partial = [title, excerpt, content].some((text) => text.length > config.maxBytes || encoder.encode(text).length > config.maxBytes);
		if (partial) return { items, partial };
		const push = (item) => {
			if (items.length < maxFindings) items.push(item);
			else partial = true;
		};
		for (const [text, location] of [[title, __('Title', 'npcink-workflow-toolbox')], [excerpt, __('Excerpt', 'npcink-workflow-toolbox')]]) {
			const projected = withoutMarkup(text);
			for (const rule of rules) if (matchesEither(text, projected, rule)) push({ location, rule });
		}
		const stack = [...blocks].reverse();
		let index = 0;
		let bytes = 0;
		while (stack.length && !partial) {
			const block = stack.pop();
			const attrs = block.attributes || {};
			const field = editableFields[block.name];
			const location = sprintf(__('Block %d', 'npcink-workflow-toolbox'), ++index);
			const serialized = JSON.stringify(attrs);
			bytes += encoder.encode(serialized).length;
			if (bytes > config.maxBytes || index > 5000) { partial = true; break; }
			const text = fieldText(attrs[field]);
			const projected = withoutMarkup(text);
			const editable = block.isValid !== false && !/[<>]/.test(text);
			for (const rule of rules) {
				const inBlock = matches(serialized, rule);
				const inField = matches(text, rule, !rule.replacement || !editable);
				if (!inBlock.length && !inField.length && (projected === text || !matches(projected, rule).length)) continue;
				unknown.add(rule.id);
				if (editable && rule.replacement && inField.length) {
					for (const span of inField) push({ location, rule, block, field, span });
					const otherAttrs = { ...attrs };
					delete otherAttrs[field];
					if (matches(JSON.stringify(otherAttrs), rule).length) push({ location, rule, block });
				} else push({ location, rule, block });
			}
			for (let i = (block.innerBlocks || []).length - 1; i >= 0; i--) stack.push(block.innerBlocks[i]);
		}
		const projected = withoutMarkup(content);
		for (const rule of rules) {
			if (!unknown.has(rule.id) && matchesEither(content, projected, rule)) push({ location: __('Article body (manual review)', 'npcink-workflow-toolbox'), rule });
		}
		return { items, partial };
	}

	function useSource() {
		return wp.data.useSelect((select) => {
			const editor = select('core/editor');
			const blockEditor = select('core/block-editor');
			return {
				postId: editor.getCurrentPostId(),
				title: String(editor.getEditedPostAttribute('title') || ''),
				excerpt: String(editor.getEditedPostAttribute('excerpt') || ''),
				content: String(editor.getEditedPostAttribute('content') || ''),
				blocks: blockEditor.getBlocks(),
			};
		}, []);
	}

	function applyFinding(item, postId, data = wp.data) {
		const editor = data.select('core/editor');
		const blockEditor = data.select('core/block-editor');
		if (!item.span || !item.rule.replacement || editor.getCurrentPostId() !== postId || editor.isSavingPost() || editor.isPostLocked?.()) return false;
		const { clientId, name } = item.block;
		const current = blockEditor.getBlock(clientId);
		const original = item.block.attributes[item.field];
		const currentValue = current?.attributes[item.field];
		const text = fieldText(original);
		if (!current || current.name !== name || current.isValid === false || fieldText(currentValue) !== text || isRichText(currentValue) !== isRichText(original)
			|| blockEditor.canEditBlock?.(clientId) === false || blockEditor.getBlockEditingMode?.(clientId) === 'disabled') return false;
		const { start, end } = item.span;
		const next = text.slice(0, start) + item.rule.replacement + text.slice(end);
		data.dispatch('core/block-editor').updateBlockAttributes(clientId, {
			[item.field]: isRichText(original) ? RichTextData.fromPlainText(next) : next,
		});
		return true;
	}

	function Findings({ report: { items, partial }, postId, onLocate }) {
		const [message, setMessage] = useState('');
		return h(Fragment, null,
			partial ? h(Notice, { status: 'warning', isDismissible: false }, partialMessage) : null,
			message ? h('p', { role: 'status' }, message) : null,
			items.length ? h(Fragment, null,
				h('p', null, rotationMessage),
				h('ol', null, items.map((item, index) => h('li', { key: `${item.block?.clientId || item.location}-${item.rule.id}-${item.span?.start ?? index}` },
					h('strong', null, `${item.location}: ${item.rule.advisory ? sprintf(__('%s (advisory only)', 'npcink-workflow-toolbox'), item.rule.label) : item.rule.label}`),
					item.span ? h('p', null, __('Masked value', 'npcink-workflow-toolbox'), ' → ', h('code', null, item.rule.replacement)) : null,
					item.block ? h(Button, { variant: 'tertiary', onClick: () => {
						wp.data.dispatch('core/block-editor').selectBlock(item.block.clientId);
						onLocate();
					} }, __('Locate block', 'npcink-workflow-toolbox')) : null,
					item.span ? h(Button, { variant: 'secondary', onClick: () => setMessage(applyFinding(item, postId)
						? __('Replaced in the editor. Review and save the article manually.', 'npcink-workflow-toolbox')
						: __('The block changed or cannot be edited. Run the check again.', 'npcink-workflow-toolbox'))
					}, __('Replace this value', 'npcink-workflow-toolbox')) : null,
				))),
			) : (!partial ? h('p', null, emptyMessage) : null),
		);
	}

	function Review({ close }) {
		const source = useSource();
		const { title, excerpt, content, blocks } = source;
		const report = useMemo(() => scan(source), [title, excerpt, content, blocks]);
		return h(Modal, { title: __('Article credential check', 'npcink-workflow-toolbox'), onRequestClose: close },
			h(Findings, { report, postId: source.postId, onLocate: close }));
	}

	function captureBlock(error, payload) {
		if (error?.code !== hitCode || !payload) return null;
		return {
			postId: payload.post_id,
			// REST can recognize a format the browser missed. Require a source edit
			// before offering another explicit attempt, even if local rules are clear.
			serverFields: transferStatus(payload) === hitCode ? null : transferFields(payload).map(({ text }) => text),
		};
	}

	function recoveryStatus(blocked, payload) {
		if (Number(payload.post_id) !== Number(blocked.postId)) return 'different_post';
		const localStatus = transferStatus(payload);
		if (localStatus) return localStatus;
		const fields = transferFields(payload);
		if (blocked.serverFields?.length === fields.length && fields.every(({ text }, i) => text === blocked.serverFields[i])) return 'server_hit';
		return '';
	}

	function TransferRecovery({ blocked, readPayload, onContinue, onCancel, faq = false }) {
		const [reviewing, setReviewing] = useState(false);
		const [editing, setEditing] = useState(false);
		const resumed = useRef(false);
		const source = useSource();
		const payload = useMemo(readPayload, [readPayload, source.postId, source.title, source.excerpt, source.content, source.blocks]);
		const status = useMemo(() => recoveryStatus(blocked, payload), [blocked, payload]);
		const report = useMemo(() => reviewing && status === hitCode ? transferFindings(payload, source.blocks) : null, [reviewing, status, payload, source.blocks]);
		if (status === 'different_post') return faq ? h(Button, { variant: 'tertiary', onClick: onCancel }, __('Cancel', 'npcink-workflow-toolbox')) : null;
		const continueLabel = faq ? __('Continue generating FAQ', 'npcink-workflow-toolbox') : __('Continue article review', 'npcink-workflow-toolbox');
		const message = status === 'server_hit'
			? __('WordPress blocked the request, but the browser could not locate the credential. Review and edit the content before trying again.', 'npcink-workflow-toolbox')
			: status === limitCode
				? __('The article is too large for the credential check. Nothing was sent to Cloud.', 'npcink-workflow-toolbox')
				: status ? __('Possible credentials found. Nothing was sent to Cloud.', 'npcink-workflow-toolbox')
					: __('No known credential formats found in the content to send.', 'npcink-workflow-toolbox');
		const backToEditor = () => { setReviewing(false); setEditing(true); };
		const content = h(Fragment, null,
			h(Notice, { status: status ? 'warning' : 'info', isDismissible: false }, message),
			report ? h(Findings, { report, postId: source.postId, onLocate: backToEditor }) : null,
			status === hitCode && !reviewing ? h(Button, { variant: 'secondary', onClick: () => setReviewing(true) }, __('Review and fix', 'npcink-workflow-toolbox')) : null,
			!status ? h(Button, { variant: 'primary', onClick: () => {
				if (resumed.current || recoveryStatus(blocked, readPayload())) return;
				resumed.current = true;
				onContinue();
			} }, continueLabel) : null,
			reviewing || (faq && !editing && status && status !== hitCode) ? h(Button, { variant: 'tertiary', onClick: backToEditor }, __('Return to editor', 'npcink-workflow-toolbox')) : null,
			faq && editing && !reviewing ? h(Button, { variant: 'tertiary', onClick: onCancel }, __('Cancel', 'npcink-workflow-toolbox')) : null);
		return reviewing || (faq && !editing) ? h(Modal, {
			title: reviewing ? __('Article credential check', 'npcink-workflow-toolbox') : faqTitle,
			onRequestClose: reviewing ? () => setReviewing(false) : onCancel,
		}, content) : content;
	}

	function Control({ disabled = false } = {}) {
		const [opened, setOpened] = useState(false);
		return h(Fragment, null,
			h(Button, { variant: 'secondary', disabled, onClick: () => setOpened(true) }, __('Check credentials', 'npcink-workflow-toolbox')),
			opened ? h(Review, { close: () => setOpened(false) }) : null,
		);
	}

	function PublishWarning() {
		const source = useSource();
		const { title, excerpt, content, blocks } = source;
		const report = useMemo(() => scan(source), [title, excerpt, content, blocks]);
		if (!report.items.length && !report.partial) return null;
		return h(PrePublish, { title: __('Article credential check', 'npcink-workflow-toolbox'), initialOpen: true },
			h(Notice, { status: 'warning', isDismissible: false }, report.items.length
				? __('Possible credentials found in this article.', 'npcink-workflow-toolbox') : partialMessage), h(Control));
	}

	const PrePublish = wp.editor?.PluginPrePublishPanel;
	if (PrePublish) wp.plugins.registerPlugin('npcink-article-credential-check', {
		render: () => wp.data.useSelect((select) => select('core/editor').isPublishSidebarOpened(), []) ? h(PublishWarning) : null,
	});
	window.NpcinkToolboxCredentialCheck = Object.freeze({ Advisory, Control, TransferRecovery, captureBlock, recoveryStatus, transferFindings, scan, applyFinding, guardTransfer, hasAdvisory });
}(window.wp));
