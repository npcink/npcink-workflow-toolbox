(function (wp) {
	'use strict';

	const i18n = (wp && wp.i18n) || {};
	const __ = i18n.__ || ((value) => value);
	const createElement = (wp && wp.element && wp.element.createElement) || null;
	const Button = (wp && wp.components && wp.components.Button) || 'button';

	// Editor part file under the JED translation policy: this handle owns
	// its zh_CN catalog, so even these load-time option labels resolve.

	const AUDIO_PREFERENCE_STORAGE_KEY = 'npcink_toolbox_audio_preferences_v1';

	const AUDIO_PREFERENCE_DEFAULTS = {
		tone: 'calm',
		pace: 'normal',
		handling: 'skip_code',
		focus: 'product_names',
	};

	const AUDIO_PREFERENCE_OPTIONS = {
		tone: [
			{ value: 'calm', label: __('Calm', 'npcink-workflow-toolbox'), instruction: __('Tone: calm and steady.', 'npcink-workflow-toolbox') },
			{ value: 'formal', label: __('Formal', 'npcink-workflow-toolbox'), instruction: __('Tone: formal and clear.', 'npcink-workflow-toolbox') },
			{ value: 'casual', label: __('Relaxed', 'npcink-workflow-toolbox'), instruction: __('Tone: relaxed and natural.', 'npcink-workflow-toolbox') },
			{ value: 'expressive', label: __('Expressive', 'npcink-workflow-toolbox'), instruction: __('Tone: expressive but not exaggerated.', 'npcink-workflow-toolbox') },
		],
		pace: [
			{ value: 'normal', label: __('Normal', 'npcink-workflow-toolbox'), instruction: __('Pace: normal.', 'npcink-workflow-toolbox') },
			{ value: 'slow', label: __('Slower', 'npcink-workflow-toolbox'), instruction: __('Pace: slightly slower for listening clarity.', 'npcink-workflow-toolbox') },
			{ value: 'fast', label: __('Faster', 'npcink-workflow-toolbox'), instruction: __('Pace: slightly faster while staying clear.', 'npcink-workflow-toolbox') },
		],
		handling: [
			{ value: 'skip_code', label: __('Skip code', 'npcink-workflow-toolbox'), instruction: __('Content handling: skip code blocks and inline code fragments.', 'npcink-workflow-toolbox') },
			{ value: 'read_code', label: __('Read code', 'npcink-workflow-toolbox'), instruction: __('Content handling: read important code fragments when they are useful to listeners.', 'npcink-workflow-toolbox') },
			{ value: 'skip_tables', label: __('Skip tables', 'npcink-workflow-toolbox'), instruction: __('Content handling: skip tables and dense lists.', 'npcink-workflow-toolbox') },
		],
		focus: [
			{ value: 'product_names', label: __('Product names', 'npcink-workflow-toolbox'), instruction: __('Focus: pronounce product names clearly.', 'npcink-workflow-toolbox') },
			{ value: 'numbers', label: __('Numbers', 'npcink-workflow-toolbox'), instruction: __('Focus: pronounce numbers and units clearly.', 'npcink-workflow-toolbox') },
			{ value: 'headings', label: __('Heading pauses', 'npcink-workflow-toolbox'), instruction: __('Focus: add clear pauses around headings.', 'npcink-workflow-toolbox') },
		],
	};

	function isAudioIntent(intent) {
		return ['article_narration', 'article_audio_summary'].indexOf(String(intent || '')) >= 0;
	}

	function normalizeAudioPreferences(source) {
		const raw = source && typeof source === 'object' ? source : {};
		const next = Object.assign({}, AUDIO_PREFERENCE_DEFAULTS);
		Object.keys(AUDIO_PREFERENCE_OPTIONS).forEach((field) => {
			const value = String(raw[field] || next[field] || '').trim();
			const allowed = AUDIO_PREFERENCE_OPTIONS[field].some((option) => option.value === value);
			if (allowed) {
				next[field] = value;
			}
		});
		return next;
	}

	function readAudioPreferences() {
		try {
			if (typeof window === 'undefined' || !window.localStorage) {
				return normalizeAudioPreferences({});
			}
			return normalizeAudioPreferences(JSON.parse(window.localStorage.getItem(AUDIO_PREFERENCE_STORAGE_KEY) || '{}'));
		} catch (error) {
			return normalizeAudioPreferences({});
		}
	}

	function writeAudioPreferences(preferences) {
		try {
			if (typeof window !== 'undefined' && window.localStorage) {
				window.localStorage.setItem(AUDIO_PREFERENCE_STORAGE_KEY, JSON.stringify(normalizeAudioPreferences(preferences)));
			}
		} catch (error) {
			// Browser storage can be disabled; keep preferences in component state.
		}
	}

	function audioPreferenceInstruction(preferences, note) {
		const normalized = normalizeAudioPreferences(preferences);
		const parts = [];
		Object.keys(AUDIO_PREFERENCE_OPTIONS).forEach((field) => {
			const match = AUDIO_PREFERENCE_OPTIONS[field].find((option) => option.value === normalized[field]);
			if (match && match.instruction) {
				parts.push(match.instruction);
			}
		});
		const extra = String(note || '').trim();
		if (extra) {
			parts.push(__('Extra request: ', 'npcink-workflow-toolbox') + extra);
		}
		return parts.join(' ');
	}

	function renderAudioPreferenceControls(preferences, onChange, disabled) {
		const normalized = normalizeAudioPreferences(preferences);
		const groups = [
			{ field: 'tone', label: __('Tone', 'npcink-workflow-toolbox') },
			{ field: 'pace', label: __('Pace', 'npcink-workflow-toolbox') },
			{ field: 'handling', label: __('Content handling', 'npcink-workflow-toolbox') },
			{ field: 'focus', label: __('Focus', 'npcink-workflow-toolbox') },
		];
		return createElement(
			'div',
			{ className: 'npcink-toolbox-editor-support__audio-preferences' },
			createElement('strong', null, __('Narration preferences', 'npcink-workflow-toolbox')),
			groups.map((group) => createElement(
				'div',
				{ key: group.field, className: 'npcink-toolbox-editor-support__audio-preference-group' },
				createElement('span', null, group.label),
				createElement(
					'div',
					{ className: 'npcink-toolbox-editor-support__audio-preference-options' },
					AUDIO_PREFERENCE_OPTIONS[group.field].map((option) => {
						const selected = normalized[group.field] === option.value;
						return createElement(
							Button,
							{
								key: option.value,
								type: 'button',
								variant: selected ? 'primary' : 'secondary',
								className: 'npcink-toolbox-editor-support__audio-preference-option' + (selected ? ' is-selected' : ''),
								disabled: Boolean(disabled),
								'aria-pressed': selected,
								onClick: () => onChange && onChange(group.field, option.value),
							},
							option.label
						);
					})
				)
			))
		);
	}

	if (typeof window !== 'undefined') {
		window.NpcinkToolboxAudioPreferences = Object.freeze({
			isAudioIntent,
			normalizeAudioPreferences,
			readAudioPreferences,
			writeAudioPreferences,
			audioPreferenceInstruction,
			renderAudioPreferenceControls,
			AUDIO_PREFERENCE_STORAGE_KEY,
			AUDIO_PREFERENCE_DEFAULTS,
			AUDIO_PREFERENCE_OPTIONS,
		});
	}
}(window.wp || {}));
