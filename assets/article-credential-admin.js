(function () {
	'use strict';
	const root = document.getElementById('npcink-article-credential-admin');
	const settings = window.NpcinkArticleCredentialAdmin;
	if (!root || !settings) return;
	const { __, sprintf } = window.wp.i18n;
	const start = root.querySelector('[data-scan]');
	const stop = root.querySelector('[data-stop]');
	const status = root.querySelector('[data-status]');
	const table = root.querySelector('[data-results]');
	const rows = table.tBodies[0];
	let active;
	let cursor;
	let scanned;
	let skipped;
	let found;
	let failed = false;
	const progress = () => sprintf(__('Checked: %1$d. Skipped: %2$d. Articles with possible credentials: %3$d.', 'npcink-workflow-toolbox'), scanned, skipped, found);

	function addItems(items) {
		for (const item of items) {
			const row = rows.insertRow();
			const article = row.insertCell();
			const label = sprintf(__('Edit article #%d', 'npcink-workflow-toolbox'), item.id);
			const url = new URL(item.edit_url, window.location.href);
			if (url.origin === window.location.origin && /^https?:$/.test(url.protocol)) {
				const link = document.createElement('a');
				link.href = url.href;
				link.target = '_blank';
				link.rel = 'noopener noreferrer';
				link.textContent = label;
				article.append(link);
			} else {
				article.textContent = label;
			}
			const list = document.createElement('ul');
			for (const { field, type } of item.findings) {
				if (!settings.locations[field] || !settings.labels[type]) continue;
				const finding = document.createElement('li');
				const label = settings.advisory.includes(type)
					? sprintf(__('%s (advisory only)', 'npcink-workflow-toolbox'), settings.labels[type]) : settings.labels[type];
				finding.textContent = `${settings.locations[field]}: ${label}`;
				list.append(finding);
			}
			row.insertCell().append(list);
		}
	}

	start.disabled = false;
	stop.addEventListener('click', () => active?.abort());
	start.addEventListener('click', async () => {
		if (active) return;
		if (!failed) {
			rows.replaceChildren();
			table.hidden = true;
			cursor = { after: 0, upper: 0 };
			scanned = skipped = found = 0;
		}
		active = new AbortController();
		start.disabled = true;
		stop.parentElement.hidden = false;
		status.textContent = __('Scanning saved articles...', 'npcink-workflow-toolbox');
		try {
			let done = false;
			while (!done) {
				const response = await fetch(settings.url, {
					method: 'POST', credentials: 'same-origin', cache: 'no-store', signal: active.signal,
					body: new URLSearchParams({ action: settings.action, nonce: settings.nonce, ...cursor }),
				});
				if (!response.ok) throw new Error();
				const { success, data } = await response.json();
				if (!success || (!data.done && data.after <= cursor.after)) throw new Error();
				if (active.signal.aborted) break;
				addItems(data.items);
				cursor = { after: data.after, upper: data.upper };
				scanned += data.scanned;
				skipped += data.skipped;
				found += data.items.length;
				table.hidden = !found;
				status.textContent = progress();
				done = data.done;
			}
			failed = !done;
			const summary = !done ? __('Scan stopped. Results are partial.', 'npcink-workflow-toolbox')
				: (skipped ? __('Scan finished with gaps.', 'npcink-workflow-toolbox')
					: (found ? __('Scan finished.', 'npcink-workflow-toolbox') : __('No potential credentials found in the checked fields.', 'npcink-workflow-toolbox')));
			status.textContent = `${summary} ${progress()}`;
		} catch {
			failed = true;
			status.textContent = `${active.signal.aborted ? __('Scan stopped. Results are partial.', 'npcink-workflow-toolbox') : __('Scan interrupted. Resume to finish checking the remaining articles.', 'npcink-workflow-toolbox')} ${progress()}`;
		} finally {
			active = null;
			start.textContent = failed ? __('Resume scan', 'npcink-workflow-toolbox') : __('Scan site articles', 'npcink-workflow-toolbox');
			start.disabled = false;
			stop.parentElement.hidden = true;
			start.focus();
		}
	});
}());
