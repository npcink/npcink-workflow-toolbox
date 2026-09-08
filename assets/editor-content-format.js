(function (wp) {
	'use strict';

	const { createElement: h, useState, useRef, useEffect } = wp.element;
	const { Button, Notice } = wp.components;
	const permitted = ['core/paragraph', 'core/heading', 'core/list', 'core/list-item', 'core/quote', 'core/code', 'core/preformatted'];

	function onlyInsertedSpaces(source, candidate) {
		let i = 0;
		for (let j = 0; j < candidate.length; j++) {
			if (i < source.length && source[i] === candidate[j]) i++;
			else if (candidate[j] !== ' ') return false;
		}
		return i === source.length;
	}

	function equalAttributes(a, b) {
		const keys = Array.from(new Set([...Object.keys(a), ...Object.keys(b)]));
		return keys.every((key) => key === 'content' || JSON.stringify(a[key]) === JSON.stringify(b[key]));
	}

	function prepare(sourceBlocks, source, result) {
		if (result?.contract_version === 'content_format_candidate.v2') return prepareStructure(sourceBlocks, source, result);
		if (!result || result.contract_version !== 'content_format_candidate.v1' || result.format !== 'html'
			|| !['CHANGED', 'UNCHANGED', 'PARTIAL', 'REVIEW'].includes(result.status) || result.visible_characters_preserved !== true
			|| result.direct_wordpress_write !== false || result.persisted !== false
			|| typeof result.candidate !== 'string' || result.candidate.length > 200000
			|| !onlyInsertedSpaces(source, result.candidate) || wp.blocks.serialize(sourceBlocks) !== source) {
			throw new Error('整理结果不符合正文保护要求，原文未改动。');
		}
		const updates = [];
		if (result.status === 'REVIEW') {
			if (result.candidate !== source) throw new Error('整理结果需要复核，原文未改动。');
			return updates;
		}
		function visit(before, after) {
			if (before.length !== after.length) throw new Error('区块结构发生变化，原文未改动。');
			return before.map((block, index) => {
				const next = after[index];
				// Keep unchanged protected subtrees, including their original client IDs.
				if (wp.blocks.serialize([block]) === wp.blocks.serialize([next])) return block;
				if (!permitted.includes(block.name) || block.name !== next.name || block.isValid === false || next.isValid === false
					|| !equalAttributes(block.attributes, next.attributes)) throw new Error('正文包含暂不支持整理的区块，原文未改动。');
				const original = block.attributes.content;
				const replacement = next.attributes.content;
				if (String(original ?? '') !== String(replacement ?? '')) {
					if (['core/code', 'core/preformatted'].includes(block.name)
						|| !onlyInsertedSpaces(String(original ?? ''), String(replacement ?? ''))) throw new Error('受保护的内容发生变化，原文未改动。');
					updates.push({ clientId: block.clientId, before: original, after: replacement });
				}
				return { ...block, attributes: { ...block.attributes, ...next.attributes }, innerBlocks: visit(block.innerBlocks || [], next.innerBlocks || []) };
			});
		}
		const updated = visit(sourceBlocks, wp.blocks.parse(result.candidate));
		if (wp.blocks.serialize(updated) !== result.candidate) throw new Error('整理结果无法完整还原为编辑器区块，原文未改动。');
		return updates;
	}

	function prepareStructure(sourceBlocks, source, result) {
		if (result.format !== 'html' || result.visible_characters_preserved !== true || result.direct_wordpress_write !== false
			|| result.persisted !== false || typeof result.candidate !== 'string' || result.candidate.length > 200000
			|| !['CHANGED', 'UNCHANGED', 'PARTIAL', 'REVIEW'].includes(result.status) || wp.blocks.serialize(sourceBlocks) !== source) {
			throw new Error('整理结果不符合正文保护要求，原文未改动。');
		}
		const changed = source !== result.candidate;
		if (changed !== ['CHANGED', 'PARTIAL'].includes(result.status)) throw new Error('整理状态不一致，原文未改动。');
		if (!changed) return [];
		const after = wp.blocks.parse(result.candidate);
		function projection(blocks) {
			const tokens = [];
			const append = (kind, value) => {
				if (kind === 'text' && tokens.at(-1)?.[0] === kind) tokens.at(-1)[1] += value;
				else tokens.push([kind, value]);
			};
			function inline(html) {
				const doc = new DOMParser().parseFromString(html, 'text/html');
				function walk(node, linked = false) {
					if (node.nodeType === 3) { append(linked ? 'protected_text' : 'text', node.nodeValue); return; }
					if (node.nodeType !== 1) throw new Error('正文包含未支持的结构。');
					const attrs = Array.from(node.attributes).map((a) => [a.name, a.value]).sort();
					if (node.tagName === 'BR' && !attrs.length) return;
					if (!['A', 'STRONG', 'EM', 'B', 'I', 'S', 'DEL', 'CODE'].includes(node.tagName)) throw new Error('正文包含未支持的结构。');
					append('tag', JSON.stringify([node.tagName, attrs]));
					node.childNodes.forEach((child) => walk(child, linked || ['A', 'CODE'].includes(node.tagName)));
					append('close', node.tagName);
				}
				doc.body.childNodes.forEach((node) => walk(node));
			}
			const plain = (block, keys) => Object.entries(block.attributes).every(([key, value]) => keys.includes(key)
				|| (key === 'values' && value === '') || (['dropCap', 'ordered', 'reversed'].includes(key) && value === false));
			for (const block of blocks) {
				if (block.isValid === false) throw new Error('整理结果包含无效区块。');
				if (block.name === 'core/paragraph' && plain(block, ['content']) && !block.innerBlocks.length) inline(block.attributes.content);
				else if (block.name === 'core/list' && plain(block, []) && !block.attributes.ordered
					&& block.innerBlocks.every((item) => item.name === 'core/list-item' && item.isValid !== false && plain(item, ['content']) && !item.innerBlocks.length)) {
					block.innerBlocks.forEach((item) => inline(item.attributes.content));
				} else append('protected', wp.blocks.serialize([block]));
			}
			return tokens;
		}
		const beforeTokens = projection(sourceBlocks);
		const afterTokens = projection(after);
		if (beforeTokens.length !== afterTokens.length || beforeTokens.some(([kind, value], index) => {
			const next = afterTokens[index];
			return kind !== next[0] || (kind === 'text' ? !onlyInsertedSpaces(value, next[1]) : value !== next[1]);
		})) throw new Error('整理结果改变了原文或受保护内容，原文未改动。');
		const originals = new Map();
		sourceBlocks.forEach((block) => {
			const key = wp.blocks.serialize([block]);
			if (!originals.has(key)) originals.set(key, []);
			originals.get(key).push(block);
		});
		const preserved = after.map((block) => originals.get(wp.blocks.serialize([block]))?.shift() || block);
		if (wp.blocks.serialize(preserved) !== result.candidate) throw new Error('整理结果无法还原为有效区块。');
		return [{ structure: true, before: sourceBlocks, after: preserved }];
	}

	function snapshot() {
		const editor = wp.data.select('core/editor');
		const blocks = wp.data.select('core/block-editor').getBlocks();
		const ids = (items) => items.map((item) => [item.clientId, ids(item.innerBlocks || [])]);
		return { postId: editor.getCurrentPostId(), content: editor.getEditedPostContent(), blocks, identity: JSON.stringify(ids(blocks)) };
	}

	function apply(updates, direction) {
		if (updates[0]?.structure) {
			wp.data.dispatch('core/block-editor').replaceBlocks(wp.data.select('core/block-editor').getBlocks().map((block) => block.clientId), updates[0][direction]);
			return;
		}
		const attributes = {};
		updates.forEach((item) => { attributes[item.clientId] = { content: item[direction] }; });
		wp.data.dispatch('core/block-editor').updateBlockAttributes(updates.map((item) => item.clientId), attributes, true);
	}

	function Control({ disabled = false }) {
		const [busy, setBusy] = useState(false);
		const [notice, setNotice] = useState(null);
		const [undo, setUndo] = useState(null);
		const active = useRef(null);
		useEffect(() => () => { if (active.current) active.current.abort(); active.current = null; }, []);

		async function format() {
			if (active.current || disabled) return;
			const source = snapshot();
			if (!source.postId || !source.content.trim()) { setNotice({ status: 'warning', text: '正文为空，无需整理。' }); return; }
			const controller = new AbortController();
			active.current = controller;
			setBusy(true);
			setNotice(null);
			const timeout = window.setTimeout(() => controller.abort(), 35000);
			// Any content or article change during the request invalidates this response,
			// including edits that the author subsequently undoes back to the same text.
			let stale = false;
			const unsubscribe = wp.data.subscribe(() => {
				const current = snapshot();
				if (current.postId !== source.postId || current.content !== source.content || current.identity !== source.identity) stale = true;
			});
			try {
				const config = window.NpcinkToolboxEditorSupport;
				const result = await wp.apiFetch({
					url: config.restUrl.replace(/\/$/, '') + '/editor/content-support', method: 'POST',
					headers: { 'X-WP-Nonce': config.nonce }, signal: controller.signal,
					data: { intent: 'format_content', post_id: source.postId, content: source.content },
				});
				if (active.current !== controller) return;
				if (stale || result.post_id !== source.postId) throw new Error('等待期间正文已修改，本次结果未应用。');
				const updates = prepare(source.blocks, source.content, result);
				unsubscribe();
				if (updates.length) {
					apply(updates, 'after');
					setUndo({ postId: source.postId, content: snapshot().content, identity: snapshot().identity, updates });
					setNotice({ status: 'success', text: result.contract_version === 'content_format_candidate.v2'
						? (result.structural_changes > 0 ? '段落、列表或换行已整理，链接与媒体保持不变。' : '已调整文字间距，段落与列表未变。')
						: (result.status === 'PARTIAL' ? '可处理的文字已整理，受保护内容保持不变。' : '正文已整理。') });
				} else {
					setNotice({ status: 'info', text: result.status === 'REVIEW' ? '可处理的文字无需调整，其余内容已跳过，原文未改动。' : '正文无需调整。' });
				}
			} catch (error) {
				if (active.current === controller) setNotice({ status: 'warning', text: error.name === 'AbortError' ? '整理请求超时，原文未改动。' : (error.message || '整理失败，原文未改动。') });
			} finally {
				unsubscribe();
				window.clearTimeout(timeout);
				if (active.current === controller) { active.current = null; setBusy(false); }
			}
		}

		function restore() {
			const current = snapshot();
			if (!undo || current.postId !== undo.postId || current.content !== undo.content || current.identity !== undo.identity
				|| (!undo.updates[0]?.structure && undo.updates.some((item) => String(wp.data.select('core/block-editor').getBlock(item.clientId)?.attributes.content ?? '') !== String(item.after ?? '')))) {
				setNotice({ status: 'warning', text: '正文已有后续修改，请使用编辑器的撤销。' });
				setUndo(null);
				return;
			}
			apply(undo.updates, 'before');
			setUndo(null);
			setNotice({ status: 'info', text: '已撤销本次整理。' });
		}

		return h('div', { className: 'npcink-toolbox-editor-format' },
			h('div', { className: 'npcink-toolbox-editor-support__flow' },
				h(Button, { variant: 'secondary', icon: 'editor-alignleft', 'aria-label': busy ? '整理中' : '整理', disabled: disabled || busy, isBusy: busy, onClick: format }, busy ? '整理中' : '整理'),
				undo ? h(Button, { icon: 'undo', label: '撤销本次整理', showTooltip: true, disabled: busy, onClick: restore }) : null),
			notice ? h(Notice, { status: notice.status, isDismissible: true, onRemove: () => setNotice(null) }, notice.text) : null);
	}

	window.NpcinkToolboxContentFormat = { Control, prepare, onlyInsertedSpaces };
})(window.wp);
