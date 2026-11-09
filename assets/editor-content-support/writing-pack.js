(function () {
	'use strict';
	// Retained for recovery tests; not enqueued while source_drafting is paused.
	function create(dependencies) {
		const { extractKnowledgeItems, writingSupportReasonText, isHttpUrl, __, sprintf, createElement, createBlock, Button, SelectControl, TextControl, TextareaControl, CheckboxControl, Notice, Modal, Spinner, readableItemText, formatMetaLabel, truncateText, renderItems, extractWritingSupportItems, hostedOutputObject, renderHostedAiDiagnostics, renderCoreHandoffReceipt, emptyWritingPackBrief, emptyDraftReviewFeedback } = dependencies;
		const DRAFT_REVIEW_ISSUES = [
			{ value: 'fact_accuracy', label: __('Fact accuracy', 'npcink-workflow-toolbox') },
			{ value: 'site_tone', label: __('Site tone', 'npcink-workflow-toolbox') },
			{ value: 'structure', label: __('Structure', 'npcink-workflow-toolbox') },
			{ value: 'source_similarity', label: __('Too similar to the source', 'npcink-workflow-toolbox') },
			{ value: 'rights_attribution', label: __('Rights or attribution', 'npcink-workflow-toolbox') },
		];
		function sourceArticleEvidenceItems(section) {
			const extraction = section && typeof section === 'object' ? section : {};
			const hasExactExtraction = Boolean(extraction.requested_url || extraction.resolved_url || extraction.url_match);
			if (hasExactExtraction) {
				return [{
					name: extraction.title || __('Exact source extraction preview', 'npcink-workflow-toolbox'),
					detail: [
						sprintf(__('URL match: %s', 'npcink-workflow-toolbox'), extraction.url_match || __('unavailable', 'npcink-workflow-toolbox')),
						extraction.body_readiness === 'ready' ? __('Article body: ready for drafting', 'npcink-workflow-toolbox') : __('Article body: insufficient; drafting is blocked', 'npcink-workflow-toolbox'),
						extraction.coverage && extraction.coverage.level ? sprintf(__('Coverage: %s (bounded preview; completeness is not guaranteed)', 'npcink-workflow-toolbox'), extraction.coverage.level) : __('Bounded preview; completeness is not guaranteed.', 'npcink-workflow-toolbox'),
						extraction.char_count ? sprintf(__('%d characters', 'npcink-workflow-toolbox'), extraction.char_count) : '',
						extraction.word_count ? sprintf(__('%d words', 'npcink-workflow-toolbox'), extraction.word_count) : '',
					].filter(Boolean).join(' · '),
					url: extraction.resolved_url || extraction.requested_url || '',
					evidence_refs: [extraction.content_hash ? 'external_source_hash:' + extraction.content_hash : 'external_source:exact_url'],
					action_policy: 'operator_review_only_no_copy_or_publish',
				}];
			}

			return extractKnowledgeItems(extraction).map((item, index) => ({
				name: item.title || sprintf(__('External source %d', 'npcink-workflow-toolbox'), index + 1),
				detail: [
					item.reader_status === 'ready' ? __('Cloud reader returned a bounded readable excerpt.', 'npcink-workflow-toolbox') : __('Only a bounded search snippet was available; coverage may be incomplete.', 'npcink-workflow-toolbox'),
					truncateText(item.reader_excerpt || item.snippet || '', 360),
				].filter(Boolean).join(' · '),
				url: item.url || '',
				evidence_refs: item.url ? ['external_source:' + item.url] : ['external_source:reader'],
				action_policy: 'operator_review_only_no_copy_or_publish',
			}));
		}

		function sourceAdaptationValueItems(value, label) {
			const values = Array.isArray(value) ? value : (value ? [value] : []);
			return values.map((item, index) => {
				const detail = readableItemText(item && typeof item === 'object' ? (item.detail || item.reason || item.direction || item.heading || item.text || item.value || item) : item, '');
				const name = item && typeof item === 'object' && (item.name || item.label || item.title)
					? readableItemText(item.name || item.label || item.title, '')
					: sprintf(__('%1$s %2$d', 'npcink-workflow-toolbox'), label, index + 1);
				return {
					name,
					detail,
					evidence_refs: ['source_adaptation_review:v1'],
					action_policy: 'operator_review_only_no_insert',
				};
			}).filter((item) => item.name || item.detail);
		}

		function sourceAdaptationReviewGroups(section) {
			const output = hostedOutputObject(section || {});
			return [
				{ key: 'source-summary', label: __('Chinese source summary', 'npcink-workflow-toolbox'), items: sourceAdaptationValueItems(output.source_summary_zh || output.source_summary, __('Summary', 'npcink-workflow-toolbox')) },
				{ key: 'style-signals', label: __('Site style and coverage signals', 'npcink-workflow-toolbox'), items: sourceAdaptationValueItems(output.site_style_signals, __('Signal', 'npcink-workflow-toolbox')) },
				{ key: 'adaptation-directions', label: __('Adaptation directions', 'npcink-workflow-toolbox'), items: sourceAdaptationValueItems(output.adaptation_directions, __('Direction', 'npcink-workflow-toolbox')) },
				{ key: 'suggested-outline', label: __('Suggested outline', 'npcink-workflow-toolbox'), items: sourceAdaptationValueItems(output.suggested_outline, __('Section', 'npcink-workflow-toolbox')) },
				{ key: 'facts-to-verify', label: __('Facts to verify', 'npcink-workflow-toolbox'), items: sourceAdaptationValueItems(output.facts_to_verify, __('Check', 'npcink-workflow-toolbox')) },
				{ key: 'copyright-attribution', label: __('Copyright and attribution review', 'npcink-workflow-toolbox'), items: sourceAdaptationValueItems(output.copyright_and_attribution, __('Review item', 'npcink-workflow-toolbox')) },
			].filter((group) => group.items.length);
		}

		function articleWritingPackItems(value, label) {
			const raw = value && typeof value === 'object' && !Array.isArray(value) && Object.prototype.hasOwnProperty.call(value, 'value') ? value.value : value;
			const values = Array.isArray(raw) ? raw : (raw === null || typeof raw === 'undefined' || raw === '' ? [] : [raw]);
			return values.map((item, index) => {
				const objectItem = item && typeof item === 'object' && !Array.isArray(item) ? item : {};
				const name = readableItemText(objectItem.claim || objectItem.title || objectItem.heading || objectItem.angle || objectItem.name || objectItem.label || (values.length === 1 ? label : sprintf(__('%1$s %2$d', 'npcink-workflow-toolbox'), label, index + 1)), '');
				const detail = [
					objectItem.value,
					objectItem.detail,
					objectItem.reason,
					objectItem.purpose,
					objectItem.evidence_basis ? __('Evidence: ', 'npcink-workflow-toolbox') + readableItemText(objectItem.evidence_basis, '') : '',
					objectItem.verification_status ? __('Verification: ', 'npcink-workflow-toolbox') + readableItemText(objectItem.verification_status, '') : '',
					!objectItem.claim && !objectItem.title && !objectItem.heading && !objectItem.angle && !objectItem.name && !objectItem.label ? readableItemText(item, '') : '',
				].filter(Boolean).join(' · ');
				return {
					name,
					detail,
					evidence_refs: ['article_writing_pack:v1'],
					action_policy: 'operator_review_only_no_insert',
				};
			}).filter((item) => item.name || item.detail);
		}

		function articleWritingPackGroups(pack) {
			if (!pack || typeof pack !== 'object') {
				return [];
			}
			const inputs = pack.inputs || {};
			const brief = inputs.editorial_brief || {};
			const research = pack.research_basis || {};
			const adaptation = pack.site_adaptation || {};
			const plan = pack.writing_plan || {};
			const risk = pack.risk_review || {};
			const admission = pack.generation_admission || {};
			return [
				{ key: 'writing-pack-audience', label: __('Inferred audience', 'npcink-workflow-toolbox'), items: articleWritingPackItems(brief.audience, __('Audience', 'npcink-workflow-toolbox')) },
				{ key: 'writing-pack-goal', label: __('Article goal and reader problem', 'npcink-workflow-toolbox'), items: articleWritingPackItems([brief.article_goal, brief.reader_problem].filter(Boolean), __('Editorial direction', 'npcink-workflow-toolbox')) },
				{ key: 'writing-pack-focus', label: __('Inferred focus points', 'npcink-workflow-toolbox'), items: articleWritingPackItems(brief.focus_points, __('Focus', 'npcink-workflow-toolbox')) },
				{ key: 'writing-pack-facts', label: __('Source fact ledger', 'npcink-workflow-toolbox'), items: articleWritingPackItems(research.fact_ledger, __('Fact', 'npcink-workflow-toolbox')) },
				{ key: 'writing-pack-overlap', label: __('Existing-site overlap map', 'npcink-workflow-toolbox'), items: articleWritingPackItems(adaptation.overlap_map, __('Overlap', 'npcink-workflow-toolbox')) },
				{ key: 'writing-pack-style', label: __('Site style and terminology signals', 'npcink-workflow-toolbox'), items: articleWritingPackItems(adaptation.site_style_signals, __('Signal', 'npcink-workflow-toolbox')) },
				{ key: 'writing-pack-angle', label: __('Distinct article angle', 'npcink-workflow-toolbox'), items: articleWritingPackItems(adaptation.unique_angle, __('Angle', 'npcink-workflow-toolbox')) },
				{ key: 'writing-pack-titles', label: __('Title directions', 'npcink-workflow-toolbox'), items: articleWritingPackItems(plan.title_directions, __('Title direction', 'npcink-workflow-toolbox')) },
				{ key: 'writing-pack-promise', label: __('Reader promise and content type', 'npcink-workflow-toolbox'), items: articleWritingPackItems([plan.reader_promise, plan.content_type].filter(Boolean), __('Plan', 'npcink-workflow-toolbox')) },
				{ key: 'writing-pack-outline', label: __('Writing outline', 'npcink-workflow-toolbox'), items: articleWritingPackItems(plan.outline, __('Section', 'npcink-workflow-toolbox')) },
				{ key: 'writing-pack-verification', label: __('Facts to verify', 'npcink-workflow-toolbox'), items: articleWritingPackItems(research.verification_items || risk.fact_risks, __('Check', 'npcink-workflow-toolbox')) },
				{ key: 'writing-pack-rights', label: __('Rights and similarity review', 'npcink-workflow-toolbox'), items: articleWritingPackItems([].concat(risk.rights_risks || [], risk.similarity_risks || []), __('Review item', 'npcink-workflow-toolbox')) },
				{ key: 'writing-pack-admission', label: __('Article-generation admission', 'npcink-workflow-toolbox'), items: articleWritingPackItems({
					value: [
						sprintf(__('Status: %s', 'npcink-workflow-toolbox'), admission.status || __('needs review', 'npcink-workflow-toolbox')),
						(admission.blocking_reasons || []).length ? __('Blocking reasons: ', 'npcink-workflow-toolbox') + admission.blocking_reasons.map(formatMetaLabel).join(', ') : '',
						(admission.missing_required_fields || []).length ? __('Missing required fields: ', 'npcink-workflow-toolbox') + admission.missing_required_fields.join(', ') : '',
						__('Full article generation is not enabled in this stage. Review and confirm the writing pack first.', 'npcink-workflow-toolbox'),
					].filter(Boolean).join(' · '),
				}, __('Admission', 'npcink-workflow-toolbox')) },
			].filter((group) => group.items.length);
		}

		function writingPackFieldValue(value) {
			const raw = value && typeof value === 'object' && !Array.isArray(value) && Object.prototype.hasOwnProperty.call(value, 'value') ? value.value : value;
			if (Array.isArray(raw)) {
				return raw.map((item) => readableItemText(item && typeof item === 'object' ? (item.heading || item.title || item.claim || item.value || item) : item, '')).filter(Boolean).join('\n');
			}
			return readableItemText(raw, '');
		}

		function writingPackBriefFromArtifact(pack) {
			const inputs = pack && pack.inputs ? pack.inputs : {};
			const brief = inputs.editorial_brief || {};
			const adaptation = pack && pack.site_adaptation ? pack.site_adaptation : {};
			const plan = pack && pack.writing_plan ? pack.writing_plan : {};
			return {
				audience: writingPackFieldValue(brief.audience),
				article_goal: writingPackFieldValue(brief.article_goal),
				reader_problem: writingPackFieldValue(brief.reader_problem),
				focus_points: writingPackFieldValue(brief.focus_points),
				unique_angle: writingPackFieldValue(adaptation.unique_angle),
				title_directions: writingPackFieldValue(plan.title_directions),
				reader_promise: writingPackFieldValue(plan.reader_promise),
				content_type: writingPackFieldValue(plan.content_type),
				outline: writingPackFieldValue(plan.outline),
				operator_instruction: writingPackFieldValue(brief.operator_instruction),
			};
		}

		function writingPackLines(value) {
			return String(value || '').split(/\r\n|\r|\n/).map((item) => item.trim()).filter(Boolean).slice(0, 20);
		}

		function reviewedWritingPackFromEditor(pack, brief) {
			if (!pack || typeof pack !== 'object') {
				return null;
			}
			const reviewed = JSON.parse(JSON.stringify(pack));
			reviewed.inputs = reviewed.inputs || {};
			reviewed.inputs.editorial_brief = reviewed.inputs.editorial_brief || {};
			reviewed.site_adaptation = reviewed.site_adaptation || {};
			reviewed.writing_plan = reviewed.writing_plan || {};
			const operatorField = (value) => ({ value, source: 'operator_reviewed_editorial_brief', operator_confirmed: true });
			reviewed.inputs.editorial_brief.audience = operatorField(String(brief.audience || '').trim());
			reviewed.inputs.editorial_brief.article_goal = operatorField(String(brief.article_goal || '').trim());
			reviewed.inputs.editorial_brief.reader_problem = operatorField(String(brief.reader_problem || '').trim());
			reviewed.inputs.editorial_brief.focus_points = operatorField(writingPackLines(brief.focus_points));
			reviewed.inputs.editorial_brief.operator_instruction = operatorField(String(brief.operator_instruction || '').trim());
			reviewed.site_adaptation.unique_angle = operatorField(String(brief.unique_angle || '').trim());
			reviewed.writing_plan.title_directions = writingPackLines(brief.title_directions);
			reviewed.writing_plan.reader_promise = operatorField(String(brief.reader_promise || '').trim());
			reviewed.writing_plan.content_type = operatorField(String(brief.content_type || '').trim());
			reviewed.writing_plan.outline = writingPackLines(brief.outline).map((heading) => ({ heading }));
			return reviewed;
		}

		function writingPackCanConfirm(pack, brief) {
			if (!pack || typeof pack !== 'object' || !String(pack.content_fingerprint || '').trim()) {
				return false;
			}
			const requiredText = ['audience', 'article_goal', 'unique_angle', 'outline'];
			if (requiredText.some((field) => !String(brief[field] || '').trim()) || !writingPackLines(brief.focus_points).length) {
				return false;
			}
			if (pack.input_mode === 'url_reference' || pack.input_mode === 'mixed') {
				const facts = pack.research_basis && Array.isArray(pack.research_basis.fact_ledger) ? pack.research_basis.fact_ledger : [];
				const sources = pack.inputs && Array.isArray(pack.inputs.source_materials) ? pack.inputs.source_materials : [];
				return Boolean(facts.length && sources.length);
			}
			return true;
		}

		function articleDraftPreviewGroups(draft) {
			if (!draft || typeof draft !== 'object') {
				return [];
			}
			const sections = Array.isArray(draft.sections) ? draft.sections : [];
			return [
				{ key: 'article-draft-title', label: __('Draft title and excerpt', 'npcink-workflow-toolbox'), items: articleWritingPackItems([{ title: draft.title || __('Untitled draft', 'npcink-workflow-toolbox'), detail: draft.excerpt || '' }], __('Draft', 'npcink-workflow-toolbox')) },
				{ key: 'article-draft-sections', label: __('Draft sections', 'npcink-workflow-toolbox'), items: sections.map((section, index) => ({
					name: readableItemText(section.heading || sprintf(__('Section %d', 'npcink-workflow-toolbox'), index + 1), ''),
					detail: readableItemText(section.body, ''),
					evidence_refs: Array.isArray(section.supporting_fact_refs) ? section.supporting_fact_refs : [],
					action_policy: 'operator_review_only_no_insert',
				})) },
				{ key: 'article-draft-verification', label: __('Draft verification notes', 'npcink-workflow-toolbox'), items: articleWritingPackItems(draft.verification_notes, __('Verify', 'npcink-workflow-toolbox')) },
				{ key: 'article-draft-attribution', label: __('Source and attribution notes', 'npcink-workflow-toolbox'), items: articleWritingPackItems(draft.source_attribution_notes, __('Source note', 'npcink-workflow-toolbox')) },
			].filter((group) => group.items.length);
		}

		function articleDraftReviewCanRegenerate(feedback) {
			const value = feedback && typeof feedback === 'object' ? feedback : {};
			return ['usable_after_changes', 'not_usable'].indexOf(value.status) >= 0 && (Boolean(String(value.notes || '').trim()) || (Array.isArray(value.issue_codes) && value.issue_codes.length > 0));
		}

		function articleDraftPreviewText(draft) {
			if (!draft || typeof draft !== 'object') {
				return '';
			}
			const lines = [];
			if (draft.title) {
				lines.push(String(draft.title).trim());
			}
			if (draft.excerpt) {
				lines.push('', String(draft.excerpt).trim());
			}
			(Array.isArray(draft.sections) ? draft.sections : []).forEach((section) => {
				const heading = String(section && section.heading ? section.heading : '').trim();
				const body = String(section && section.body ? section.body : '').trim();
				if (heading || body) {
					lines.push('', heading, body);
				}
			});
			return lines.filter((line, index) => line || index > 0).join('\n').trim();
		}

		function articleDraftPreviewPlanInput(draft) {
			const source = draft && typeof draft === 'object' ? draft : {};
			const sections = (Array.isArray(source.sections) ? source.sections : [])
				.map((section) => ({
					heading: String(section && section.heading ? section.heading : '').trim(),
					body: String(section && section.body ? section.body : '').trim(),
				}))
				.filter((section) => section.heading || section.body);
			const contentMarkdown = sections.map((section) => {
				const parts = [];
				if (section.heading) {
					parts.push('## ' + section.heading);
				}
				if (section.body) {
					parts.push(section.body);
				}
				return parts.join('\n\n');
			}).join('\n\n').trim();
			const verificationNotes = (Array.isArray(source.verification_notes) ? source.verification_notes : []).map((item) => String(item || '').trim()).filter(Boolean);
			const sourceNotes = (Array.isArray(source.source_attribution_notes) ? source.source_attribution_notes : []).map((item) => String(item || '').trim()).filter(Boolean);

			return {
				title: String(source.title || '').trim(),
				topic: String(source.title || '').trim(),
				content_markdown: contentMarkdown,
				excerpt: String(source.excerpt || '').trim(),
				risk_level: 'medium',
				article_outline: {
					title: String(source.title || '').trim(),
					sections: sections.map((section) => section.heading).filter(Boolean),
				},
				article_draft_candidate: {
					content_markdown: contentMarkdown,
					used_sources: sourceNotes,
					unverified_claims: verificationNotes,
					needs_human_input: verificationNotes,
				},
				used_sources: sourceNotes,
				unverified_claims: verificationNotes,
				needs_review: verificationNotes,
			};
		}

		function articleDraftBlockContent(value) {
			return String(value || '')
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#039;');
		}

		function articleDraftPreviewBlocks(draft) {
			if (!createBlock || !draft || typeof draft !== 'object') {
				return [];
			}
			const draftBlocks = [];
			(Array.isArray(draft.sections) ? draft.sections : []).forEach((section) => {
				const heading = String(section && section.heading ? section.heading : '').trim();
				const body = String(section && section.body ? section.body : '').trim();
				if (heading) {
					draftBlocks.push(createBlock('core/heading', { level: 2, content: articleDraftBlockContent(heading) }));
				}
				if (body) {
					draftBlocks.push(createBlock('core/paragraph', { content: articleDraftBlockContent(body) }));
				}
			});
			return draftBlocks;
		}

		function renderArticleDraftReview(draft, controls) {
			if (!controls || !draft || typeof draft !== 'object') {
				return null;
			}
			const feedback = controls.feedback && typeof controls.feedback === 'object' ? controls.feedback : emptyDraftReviewFeedback();
			const issues = Array.isArray(feedback.issue_codes) ? feedback.issue_codes : [];
			return createElement(
				'section',
				{ className: 'npcink-toolbox-editor-support__draft-review' },
				createElement('h4', null, __('Review this draft', 'npcink-workflow-toolbox')),
				createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, __('This feedback stays in the current editor session and is sent only when you request another draft. It is not saved as review history.', 'npcink-workflow-toolbox')),
				createElement(SelectControl, {
					label: __('Draft usability', 'npcink-workflow-toolbox'),
					value: feedback.status || '',
					disabled: Boolean(controls.running),
					options: [
						{ value: '', label: __('Choose a review result', 'npcink-workflow-toolbox') },
						{ value: 'usable', label: __('Usable', 'npcink-workflow-toolbox') },
						{ value: 'usable_after_changes', label: __('Usable after changes', 'npcink-workflow-toolbox') },
						{ value: 'not_usable', label: __('Not usable', 'npcink-workflow-toolbox') },
					],
					onChange: (value) => controls.setStatus && controls.setStatus(value),
				}),
				createElement('strong', { className: 'npcink-toolbox-editor-support__draft-review-label' }, __('Main issues', 'npcink-workflow-toolbox')),
				createElement(
					'div',
					{ className: 'npcink-toolbox-editor-support__draft-review-issues' },
					DRAFT_REVIEW_ISSUES.map((issue) => createElement(CheckboxControl, {
						key: issue.value,
						label: issue.label,
						checked: issues.indexOf(issue.value) >= 0,
						disabled: Boolean(controls.running),
						onChange: (checked) => controls.toggleIssue && controls.toggleIssue(issue.value, checked),
					}))
				),
				createElement(TextareaControl, {
					label: __('Revision notes', 'npcink-workflow-toolbox'),
					value: feedback.notes || '',
					rows: 3,
					disabled: Boolean(controls.running),
					placeholder: __('Describe what should change in the next draft.', 'npcink-workflow-toolbox'),
					onChange: (value) => controls.setNotes && controls.setNotes(value),
				}),
				createElement(
					'div',
					{ className: 'npcink-toolbox-editor-support__draft-review-actions' },
					createElement(Button, {
						type: 'button',
						variant: 'secondary',
						isBusy: Boolean(controls.running),
						disabled: Boolean(controls.running) || !articleDraftReviewCanRegenerate(feedback),
						onClick: () => controls.regenerate && controls.regenerate(),
					}, __('Regenerate from feedback', 'npcink-workflow-toolbox')),
					createElement(Button, {
						type: 'button',
						variant: controls.editorBodyEmpty ? 'primary' : 'secondary',
						disabled: Boolean(controls.running) || !Boolean(controls.editorBodyEmpty) || !Boolean(controls.draftApproved),
						onClick: () => controls.loadIntoEditor && controls.loadIntoEditor(draft),
					}, __('Load into empty editor', 'npcink-workflow-toolbox')),
					createElement(Button, {
						type: 'button',
						variant: controls.editorBodyEmpty ? 'secondary' : 'primary',
						disabled: Boolean(controls.running),
						onClick: () => controls.copy && controls.copy(draft),
					}, __('Copy draft', 'npcink-workflow-toolbox')),
					createElement(Button, {
						type: 'button',
						variant: 'tertiary',
						isBusy: Boolean(controls.coreHandoffRunning),
						disabled: Boolean(controls.running) || Boolean(controls.coreHandoffRunning) || Boolean(controls.coreHandoffSubmitted) || !Boolean(controls.draftApproved),
						onClick: () => controls.submitToCore && controls.submitToCore(draft),
					}, controls.coreHandoffSubmitted ? __('Submitted to Core review', 'npcink-workflow-toolbox') : __('Create WordPress draft through Core', 'npcink-workflow-toolbox'))
				),
				createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, controls.editorBodyEmpty
					? (controls.draftApproved
						? __('Loading changes only the visible Gutenberg editor. Use WordPress Save draft or Publish to persist it.', 'npcink-workflow-toolbox')
						: __('Mark the current draft as usable before loading it. Drafts that need changes must be regenerated and reviewed again.', 'npcink-workflow-toolbox'))
					: __('The current article already has body content. Loading is blocked; copy the draft for manual review instead.', 'npcink-workflow-toolbox')),
				createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, __('Core review creates a new WordPress post with status draft only. Toolbox does not approve, execute, or publish it.', 'npcink-workflow-toolbox')),
				controls.coreHandoffResult ? renderCoreHandoffReceipt(controls.coreHandoffResult.handoff_receipt) : null,
				controls.coreHandoffError ? createElement(Notice, { status: 'error', isDismissible: false }, controls.coreHandoffError.message || __('The WordPress draft could not be submitted to Core review.', 'npcink-workflow-toolbox')) : null,
				controls.status ? createElement(Notice, { status: controls.status.status || 'success', isDismissible: false }, controls.status.message) : null
			);
		}

		function renderWritingPackEditor(inputMode, brief, options) {
			const controls = options || {};
			const field = (name, label, multiline, rows) => {
				const Component = multiline ? TextareaControl : TextControl;
				return createElement(Component, {
					key: name,
					label,
					value: brief[name] || '',
					disabled: Boolean(controls.disabled),
					rows: rows || 3,
					onChange: (value) => controls.onFieldChange && controls.onFieldChange(name, value),
				});
			};
			const primaryFields = [
				field('audience', __('Audience', 'npcink-workflow-toolbox')),
				field('article_goal', __('Article goal', 'npcink-workflow-toolbox'), true, 2),
				field('focus_points', __('Focus points (one per line)', 'npcink-workflow-toolbox'), true, 4),
				field('unique_angle', __('Distinct angle', 'npcink-workflow-toolbox'), true, 2),
				field('outline', __('Outline headings (one per line)', 'npcink-workflow-toolbox'), true, 6),
			];
			const optionalFields = [
				field('reader_problem', __('Reader problem', 'npcink-workflow-toolbox'), true, 2),
				field('title_directions', __('Title directions (one per line)', 'npcink-workflow-toolbox'), true, 3),
				field('reader_promise', __('Reader promise', 'npcink-workflow-toolbox'), true, 2),
				field('content_type', __('Content type', 'npcink-workflow-toolbox')),
				field('operator_instruction', __('Additional writing guidance', 'npcink-workflow-toolbox'), true, 3),
			];
			return createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__writing-direction-form' },
				primaryFields,
				createElement(
					'details',
					{ className: 'npcink-toolbox-editor-support__writing-options' },
					createElement('summary', null, __('More writing preferences', 'npcink-workflow-toolbox')),
					createElement('div', null, optionalFields)
				)
			);
		}

		function renderWritingPackStarterBrief(brief, controls) {
			const options = controls || {};
			const field = (name, label, rows) => createElement(TextareaControl, {
				key: name,
				label,
				value: brief[name] || '',
				rows,
				disabled: Boolean(options.disabled),
				onChange: (value) => options.onFieldChange && options.onFieldChange(name, value),
			});
			return createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__starter-brief' },
				createElement(TextControl, {
					label: __('Audience', 'npcink-workflow-toolbox'),
					value: brief.audience || '',
					disabled: Boolean(options.disabled),
					onChange: (value) => options.onFieldChange && options.onFieldChange('audience', value),
				}),
				field('article_goal', __('Article goal', 'npcink-workflow-toolbox'), 2),
				field('focus_points', __('Focus points (one per line)', 'npcink-workflow-toolbox'), 4),
				createElement(
					'details',
					{ className: 'npcink-toolbox-editor-support__writing-options' },
					createElement('summary', null, __('Add more requirements', 'npcink-workflow-toolbox')),
					createElement('div', null,
						field('reader_problem', __('Reader problem', 'npcink-workflow-toolbox'), 2),
						field('operator_instruction', __('Additional writing guidance', 'npcink-workflow-toolbox'), 3)
					)
				)
			);
		}

		function sourceWorkflowHost(value) {
			try {
				return new URL(String(value || '')).hostname.replace(/^www\./, '');
			} catch (error) {
				return '';
			}
		}

		function sourceWorkflowRelatedItems(section) {
			return extractKnowledgeItems(section).slice(0, 8).map((item, index) => {
				const support = item && item.writing_support && typeof item.writing_support === 'object' ? item.writing_support : {};
				const evidence = support.evidence_source && typeof support.evidence_source === 'object' ? support.evidence_source : {};
				return {
					key: String(item && (item.post_id || item.id || item.url) ? (item.post_id || item.id || item.url) : index),
					title: evidence.title || item.title || __('Untitled existing post', 'npcink-workflow-toolbox'),
					reason: writingSupportReasonText(item && item.reason ? item.reason : '') || __('Review whether the new article repeats this coverage or needs a clearer angle.', 'npcink-workflow-toolbox'),
					url: evidence.url || item.url || '',
				};
			});
		}

		function renderSourceWorkflowStepper(currentStep, availableStep, onStepChange) {
			const steps = [
				{ step: 1, label: __('Provide materials', 'npcink-workflow-toolbox') },
				{ step: 2, label: __('Confirm direction', 'npcink-workflow-toolbox') },
				{ step: 3, label: __('Review draft', 'npcink-workflow-toolbox') },
			];
			return createElement(
				'ol',
				{ className: 'npcink-toolbox-editor-support__source-stepper', 'aria-label': __('Drafting steps', 'npcink-workflow-toolbox') },
				steps.map((item) => {
					const available = item.step <= availableStep;
					const active = item.step === currentStep;
					return createElement(
						'li',
						{ key: item.step, className: (active ? 'is-active ' : '') + (item.step < availableStep ? 'is-complete' : '') },
						createElement(
							'button',
							{
								type: 'button',
								disabled: !available,
								'aria-current': active ? 'step' : undefined,
								onClick: () => available && onStepChange(item.step),
							},
							createElement('span', null, item.step),
							createElement('strong', null, item.label)
						)
					);
				})
			);
		}

		function renderSourceExtractionSummary(section) {
			const extraction = section && typeof section === 'object' ? section : {};
			if (!extraction.requested_url && !extraction.resolved_url && !extraction.title) {
				return null;
			}
			const ready = extraction.status === 'ready' && extraction.url_match === 'matched' && extraction.body_ready === true;
			const sourceUrl = extraction.resolved_url || extraction.requested_url || '';
			return createElement(
				'section',
				{ className: 'npcink-toolbox-editor-support__source-summary ' + (ready ? 'is-ready' : 'is-blocked') },
				createElement(
					'div',
					{ className: 'npcink-toolbox-editor-support__source-summary-head' },
					createElement('strong', null, ready ? __('Source ready', 'npcink-workflow-toolbox') : __('Source needs attention', 'npcink-workflow-toolbox')),
					createElement('span', null, sourceWorkflowHost(sourceUrl))
				),
				createElement('h3', null, extraction.title || __('External article', 'npcink-workflow-toolbox')),
				createElement('p', null, ready
					? __('Enough article text was captured to prepare a writing direction.', 'npcink-workflow-toolbox')
					: __('The article body could not be confirmed. Try another public article URL.', 'npcink-workflow-toolbox')),
				createElement(
					'details',
					{ className: 'npcink-toolbox-editor-support__source-technical-details' },
					createElement('summary', null, __('Source preview and technical details', 'npcink-workflow-toolbox')),
					createElement('div', null, renderItems(sourceArticleEvidenceItems(extraction), __('No readable source evidence returned.', 'npcink-workflow-toolbox')))
				)
			);
		}

		function renderSourceRelatedArticles(section) {
			const items = sourceWorkflowRelatedItems(section);
			return createElement(
				'section',
				{ className: 'npcink-toolbox-editor-support__source-related' },
				createElement('div', { className: 'npcink-toolbox-editor-support__source-section-head' },
					createElement('h3', null, __('Related site articles', 'npcink-workflow-toolbox')),
					createElement('span', null, String(items.length))
				),
				items.length ? createElement(
					'ul',
					null,
					items.map((item) => createElement(
						'li',
						{ key: item.key },
						createElement('strong', null, item.title),
						createElement('p', null, item.reason),
						item.url && isHttpUrl(item.url) ? createElement('a', { href: item.url, target: '_blank', rel: 'noopener noreferrer' }, __('Open article', 'npcink-workflow-toolbox')) : null
					))
				) : createElement('p', { className: 'npcink-toolbox-editor-support__muted' }, __('No related site articles were found.', 'npcink-workflow-toolbox'))
			);
		}

		function renderSourceWritingPackDetails(pack) {
			const detailBlocks = [];
			articleWritingPackGroups(pack).forEach((group) => {
				detailBlocks.push(createElement('h4', { key: group.key + '-title' }, group.label));
				detailBlocks.push(renderItems(group.items, __('No review items returned.', 'npcink-workflow-toolbox')));
			});
			if (!detailBlocks.length) {
				return null;
			}
			return createElement(
				'details',
				{ className: 'npcink-toolbox-editor-support__source-review-details' },
				createElement('summary', null, __('Source, risks, and detailed evidence', 'npcink-workflow-toolbox')),
				createElement('div', null, detailBlocks)
			);
		}

		function renderSourceDraftStep(draft, controls) {
			if (!draft || typeof draft !== 'object') {
				return null;
			}
			const sections = Array.isArray(draft.sections) ? draft.sections : [];
			return createElement(
				'div',
				{ className: 'npcink-toolbox-editor-support__source-draft-step' },
				createElement(
					'article',
					{ className: 'npcink-toolbox-editor-support__draft-preview' },
					createElement('h2', null, draft.title || __('Untitled draft', 'npcink-workflow-toolbox')),
					draft.excerpt ? createElement('p', { className: 'npcink-toolbox-editor-support__draft-excerpt' }, draft.excerpt) : null,
					sections.map((section, index) => createElement(
						'section',
						{ key: index },
						createElement('h3', null, section.heading || sprintf(__('Section %d', 'npcink-workflow-toolbox'), index + 1)),
						createElement('p', null, section.body || '')
					)),
					createElement(
						'details',
						{ className: 'npcink-toolbox-editor-support__source-review-details' },
						createElement('summary', null, __('Verification and source notes', 'npcink-workflow-toolbox')),
						createElement('div', null,
							renderItems(articleWritingPackItems(draft.verification_notes, __('Verify', 'npcink-workflow-toolbox')), __('No verification notes returned.', 'npcink-workflow-toolbox')),
							renderItems(articleWritingPackItems(draft.source_attribution_notes, __('Source note', 'npcink-workflow-toolbox')), __('No source notes returned.', 'npcink-workflow-toolbox'))
						)
					)
				),
				renderArticleDraftReview(draft, controls)
			);
		}
		function renderSourceWorkflowModal(context) {
			const { sourceWorkflowModalOpen, result, sourceDraftComplete, sourceAdaptationComplete, sourceWorkflowStep, writingPackInputMode, running, updateWritingPackInputMode, setSourceWorkflowStep, sourceArticleUrl, setSourceArticleUrl, setWritingPackBrief, setWritingPackConfirmed, setResult, writingPackBrief, updateWritingPackBriefField, sourceExtractionSection, sourceExtractionComplete, sourceExtractionReady, runFlow, sourceWritingPackBlocked, currentWritingPackArtifact, submitContentImplicitFeedback, resultControls, setSourceWorkflowModalOpen, requestNotice, error } = context;
			if (!sourceWorkflowModalOpen) {
				return null;
			}
			const sections = result && result.sections ? result.sections : {};
			const availableStep = sourceDraftComplete ? 3 : (sourceAdaptationComplete ? 2 : 1);
			const currentStep = Math.min(sourceWorkflowStep, availableStep);
			const requiresUrl = writingPackInputMode === 'url_reference' || writingPackInputMode === 'mixed';
			const draft = sections.article_draft_preview || null;
			let stageContent = null;
			let footerActions = null;

			if (currentStep === 1) {
				stageContent = createElement(
					'section',
					{ className: 'npcink-toolbox-editor-support__source-stage' },
					createElement('div', { className: 'npcink-toolbox-editor-support__source-stage-heading' },
						createElement('h2', null, __('Provide materials', 'npcink-workflow-toolbox')),
						createElement('p', null, __('Choose a reference URL, a short brief, or both.', 'npcink-workflow-toolbox'))
					),
					createElement(SelectControl, {
						label: __('How do you want to start?', 'npcink-workflow-toolbox'),
						value: writingPackInputMode,
						disabled: Boolean(running),
						options: [
							{ value: 'url_reference', label: __('Reference URL', 'npcink-workflow-toolbox') },
							{ value: 'manual_brief', label: __('Manual brief', 'npcink-workflow-toolbox') },
							{ value: 'mixed', label: __('Reference URL + manual brief', 'npcink-workflow-toolbox') },
						],
						onChange: (value) => {
							updateWritingPackInputMode(value);
							setSourceWorkflowStep(1);
						},
					}),
					requiresUrl ? createElement(TextControl, {
						label: __('Public article URL', 'npcink-workflow-toolbox'),
						type: 'url',
						value: sourceArticleUrl,
						placeholder: 'https://example.com/article',
						disabled: Boolean(running),
						onChange: (value) => {
							setSourceArticleUrl(value);
							if (writingPackInputMode === 'url_reference') {
								setWritingPackBrief(emptyWritingPackBrief());
							}
							setWritingPackConfirmed(false);
							setSourceWorkflowStep(1);
							setResult(null);
						},
					}) : null,
					writingPackInputMode !== 'url_reference' ? renderWritingPackStarterBrief(writingPackBrief, {
						disabled: Boolean(running),
						onFieldChange: updateWritingPackBriefField,
					}) : null,
					renderSourceExtractionSummary(sourceExtractionSection)
				);
				footerActions = createElement(
					'div',
					{ className: 'npcink-toolbox-editor-support__source-footer-actions' },
					sourceExtractionComplete && !sourceExtractionReady && writingPackInputMode !== 'manual_brief' ? createElement(Button, {
						type: 'button',
						variant: 'tertiary',
						disabled: Boolean(running),
						onClick: () => {
							updateWritingPackInputMode('manual_brief');
							setSourceWorkflowStep(1);
						},
					}, __('Use a manual brief instead', 'npcink-workflow-toolbox')) : null,
					createElement(Button, {
						type: 'button',
						variant: 'primary',
						isBusy: running === 'source_adaptation_review',
						disabled: Boolean(running),
						onClick: () => {
							if (sourceExtractionReady || writingPackInputMode === 'manual_brief') {
								runFlow('source_adaptation_review', { sourceStage: 'research_plan', preserveResult: true });
								return;
							}
							runFlow('source_adaptation_review', { sourceStage: 'extract', forceRefresh: sourceExtractionComplete });
						},
					}, running === 'source_adaptation_review'
						? __('Running', 'npcink-workflow-toolbox')
						: (sourceExtractionReady || writingPackInputMode === 'manual_brief'
							? __('Continue to writing direction', 'npcink-workflow-toolbox')
							: (sourceExtractionComplete
								? __('Retry source', 'npcink-workflow-toolbox')
								: __('Read source', 'npcink-workflow-toolbox'))))
				);
			}

			if (currentStep === 2) {
				stageContent = createElement(
					'section',
					{ className: 'npcink-toolbox-editor-support__source-stage' },
					createElement('div', { className: 'npcink-toolbox-editor-support__source-stage-heading' },
						createElement('h2', null, __('Confirm writing direction', 'npcink-workflow-toolbox')),
						createElement('p', null, __('Review the audience, goal, angle, and outline. Edit them before generating a draft.', 'npcink-workflow-toolbox'))
					),
					sourceWritingPackBlocked ? createElement(Notice, { status: 'warning', isDismissible: false }, __('The current source or brief is not sufficient for drafting. Review the detailed evidence or return to the source step.', 'npcink-workflow-toolbox')) : null,
					createElement(
						'div',
						{ className: 'npcink-toolbox-editor-support__source-direction-layout' },
						createElement(
							'div',
							{ className: 'npcink-toolbox-editor-support__source-direction-main' },
							renderWritingPackEditor(writingPackInputMode, writingPackBrief, {
								disabled: Boolean(running),
								onFieldChange: updateWritingPackBriefField,
							}),
							renderSourceWritingPackDetails(currentWritingPackArtifact)
						),
						renderSourceRelatedArticles(sections.source_site_context)
					)
				);
				footerActions = createElement(
					'div',
					{ className: 'npcink-toolbox-editor-support__source-footer-actions' },
					createElement(Button, {
						type: 'button',
						variant: 'tertiary',
						disabled: Boolean(running),
						onClick: () => setSourceWorkflowStep(1),
					}, __('Back to source', 'npcink-workflow-toolbox')),
					createElement('div', { className: 'npcink-toolbox-editor-support__source-footer-primary' },
						createElement(Button, {
							type: 'button',
							variant: 'secondary',
							isBusy: running === 'source_adaptation_review',
							disabled: Boolean(running),
							onClick: () => runFlow('source_adaptation_review', { sourceStage: 'research_plan', forceRegenerate: true, preserveResult: true }),
						}, __('Rebuild direction', 'npcink-workflow-toolbox')),
						createElement(Button, {
							type: 'button',
							variant: 'primary',
							isBusy: running === 'source_adaptation_review',
							disabled: Boolean(running) || sourceWritingPackBlocked || !writingPackCanConfirm(currentWritingPackArtifact, writingPackBrief),
							onClick: () => {
								setWritingPackConfirmed(true);
								submitContentImplicitFeedback('generate_article_draft_preview', 'accepted', ['writing_pack_operator_confirmed']);
								runFlow('source_adaptation_review', { sourceStage: 'draft', confirmWritingPack: true, preserveResult: true });
							},
						}, __('Confirm direction and generate draft', 'npcink-workflow-toolbox'))
					)
				);
			}

			if (currentStep === 3) {
				stageContent = createElement(
					'section',
					{ className: 'npcink-toolbox-editor-support__source-stage' },
					createElement('div', { className: 'npcink-toolbox-editor-support__source-stage-heading' },
						createElement('h2', null, __('Review draft', 'npcink-workflow-toolbox')),
						createElement('p', null, __('Review the draft and choose how to continue. Nothing is saved or published automatically.', 'npcink-workflow-toolbox'))
					),
					renderSourceDraftStep(draft, resultControls.draftReview)
				);
				footerActions = createElement(
					'div',
					{ className: 'npcink-toolbox-editor-support__source-footer-actions' },
					createElement(Button, {
						type: 'button',
						variant: 'tertiary',
						disabled: Boolean(running),
						onClick: () => setSourceWorkflowStep(2),
					}, __('Back to direction', 'npcink-workflow-toolbox'))
				);
			}

			return createElement(
				Modal,
				{
					title: __('Draft from source materials', 'npcink-workflow-toolbox'),
					onRequestClose: () => setSourceWorkflowModalOpen(false),
					className: 'npcink-toolbox-editor-support__source-workflow-modal',
				},
				createElement(
					'div',
					{ className: 'npcink-toolbox-editor-support__source-workflow' },
					renderSourceWorkflowStepper(currentStep, availableStep, setSourceWorkflowStep),
					createElement(
						'div',
						{ className: 'npcink-toolbox-editor-support__source-body' },
						running === 'source_adaptation_review' ? createElement(
							'div',
							{ className: 'npcink-toolbox-editor-support__source-running' },
							createElement(Spinner, null),
							createElement('span', null, __('Preparing article materials...', 'npcink-workflow-toolbox'))
						) : null,
						requestNotice ? createElement(Notice, { status: requestNotice.kind === 'info' ? 'info' : 'warning', isDismissible: false }, requestNotice.message) : null,
						error ? createElement(Notice, { status: 'error', isDismissible: false }, error) : null,
						stageContent
					),
					createElement(
						'div',
						{ className: 'npcink-toolbox-editor-support__source-footer' },
						createElement('p', null, __('Toolbox prepares review material only. Final saving and publishing stay with WordPress and Core.', 'npcink-workflow-toolbox')),
						footerActions
					)
				)
			);
		}

		return {
			sourceArticleEvidenceItems,
			sourceAdaptationValueItems,
			sourceAdaptationReviewGroups,
			articleWritingPackItems,
			articleWritingPackGroups,
			writingPackFieldValue,
			writingPackBriefFromArtifact,
			writingPackLines,
			reviewedWritingPackFromEditor,
			writingPackCanConfirm,
			articleDraftPreviewGroups,
			articleDraftReviewCanRegenerate,
			articleDraftPreviewText,
			articleDraftPreviewPlanInput,
			articleDraftBlockContent,
			articleDraftPreviewBlocks,
			renderArticleDraftReview,
			renderWritingPackEditor,
			renderWritingPackStarterBrief,
			sourceWorkflowHost,
			sourceWorkflowRelatedItems,
			renderSourceWorkflowStepper,
			renderSourceExtractionSummary,
			renderSourceRelatedArticles,
			renderSourceWritingPackDetails,
			renderSourceDraftStep,
			renderSourceWorkflowModal,
		};
	}
	window.NpcinkToolboxWritingPack = Object.freeze({ create });
})();
