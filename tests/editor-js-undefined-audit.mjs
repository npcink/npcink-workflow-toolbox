#!/usr/bin/env node
/**
 * Zero-dependency undefined-call audit for the editor content-support bundle.
 *
 * The 2026-10 split-session advisory review caught a real runtime break
 * (main bundle calling internalLinkBlockContent, which had moved to a part
 * without joining the namespace): bare-vm tests never execute React
 * selectors, so neither the behavior suite nor CI could see it. This audit
 * statically proves the complementary property: every function the bundle
 * calls is either defined in the same file, destructured from a part
 * namespace that really exports it, or a known browser global. It is
 * deliberately conservative — a flat name union only masks shadowed locals
 * (false negatives), and any name it flags is a genuinely missing
 * definition at call position.
 */
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

import { PART_ORDER, readEditorContentSupportSources } from './editor-content-support-sources.mjs';

const BROWSER_GLOBALS = new Set([
	'window', 'document', 'fetch', 'URL', 'URLSearchParams', 'FormData', 'AbortController',
	'setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'requestAnimationFrame',
	'requestIdleCallback', 'location', 'navigator', 'localStorage', 'sessionStorage',
	'history', 'console', 'Date', 'Math', 'JSON', 'Object', 'Array', 'String', 'Number',
	'Boolean', 'RegExp', 'Error', 'TypeError', 'RangeError', 'Promise', 'Set', 'Map',
	'WeakMap', 'WeakSet', 'Symbol', 'Proxy', 'Reflect', 'Intl', 'BigInt', 'parseInt',
	'parseFloat', 'isNaN', 'isFinite', 'encodeURIComponent', 'decodeURIComponent',
	'alert', 'confirm', 'prompt', 'addEventListener', 'removeEventListener',
	'dispatchEvent', 'CustomEvent', 'Event', 'KeyboardEvent', 'MouseEvent',
	'MutationObserver', 'DOMParser', 'Node', 'Element', 'HTMLElement', 'FileReader',
	'Blob', 'File', 'performance', 'queueMicrotask', 'structuredClone', 'crypto',
	'self', 'globalThis', 'btoa', 'atob',
]);

const CALL_KEYWORDS = new Set([
	'if', 'for', 'while', 'switch', 'catch', 'return', 'typeof', 'new', 'function',
	'do', 'else', 'void', 'delete', 'instanceof', 'yield', 'await', 'async', 'in', 'of',
]);

const REGEX_PRECEDING_KEYWORDS = new Set([
	'return', 'case', 'typeof', 'instanceof', 'in', 'of', 'do', 'else', 'yield', 'await', 'new',
]);

/**
 * Blank comments, string contents, and regex-literal bodies while keeping
 * template-literal interpolation bodies live (calls inside `${...}` are
 * exactly the missing-namespace class this audit exists for). The output
 * preserves the original length so reported line numbers match the source
 * file. Regex literals use the operand-position heuristic: a slash starts
 * a regex only after an operator, an opener, file start, or one of the
 * keywords that may directly precede a regex literal.
 */
export function stripNonCode(source) {
	const length = source.length;
	let out = '';
	let index = 0;
	let mode = 'code';
	let previousSignificant = '';
	let previousWord = '';
	const templateStack = [];
	let interpolationDepth = 0;

	const appendCode = (char) => {
		out += char;
		if (/\s/.test(char)) return;
		if (/[\w$]/.test(char)) {
			previousWord += char;
		} else {
			previousSignificant = char;
			previousWord = '';
		}
	};

	while (index < length) {
		const char = source[index];
		const next = source[index + 1];

		if (mode === 'tpl') {
			if (char === '\\' && next !== undefined) {
				out += '  ';
				index += 2;
				continue;
			}
			if (char === '`') {
				out += '`';
				mode = 'code';
				previousSignificant = '`';
				previousWord = '';
				index += 1;
				continue;
			}
			if (char === '$' && next === '{') {
				out += '${';
				templateStack.push(interpolationDepth);
				mode = 'code';
				interpolationDepth = 0;
				previousSignificant = '{';
				previousWord = '';
				index += 2;
				continue;
			}
			out += ' ';
			index += 1;
			continue;
		}

		if (char === '/' && next === '/') {
			const end = source.indexOf('\n', index);
			const stop = end === -1 ? length : end;
			out += ' '.repeat(stop - index);
			index = stop;
			continue;
		}
		if (char === '/' && next === '*') {
			const end = source.indexOf('*/', index + 2);
			const stop = end === -1 ? length : end + 2;
			out += ' '.repeat(stop - index);
			index = stop;
			continue;
		}
		if (char === '"' || char === "'") {
			let end = index + 1;
			while (end < length && source[end] !== char) {
				if (source[end] === '\\') end += 1;
				end += 1;
			}
			const stop = Math.min(end + 1, length);
			out += char + ' '.repeat(Math.max(0, stop - index - 2)) + char;
			index = stop;
			previousSignificant = char;
			previousWord = '';
			continue;
		}
		if (char === '`') {
			out += '`';
			mode = 'tpl';
			previousSignificant = '`';
			previousWord = '';
			index += 1;
			continue;
		}
		if (char === '/') {
			const startsRegex = previousSignificant === ''
				|| '([{,;=:?!&|+-*%~^<>'.includes(previousSignificant)
				|| REGEX_PRECEDING_KEYWORDS.has(previousWord);
			if (startsRegex) {
				let end = index + 1;
				let inCharacterClass = false;
				while (end < length) {
					const regexChar = source[end];
					if (regexChar === '\\') { end += 2; continue; }
					if (regexChar === '[') inCharacterClass = true;
					else if (regexChar === ']') inCharacterClass = false;
					else if (regexChar === '/' && !inCharacterClass) break;
					else if (regexChar === '\n') break;
					end += 1;
				}
				const stop = Math.min(end + 1, length);
				out += ' '.repeat(stop - index);
				index = stop;
				continue;
			}
		}
		if (char === '{') {
			interpolationDepth += 1;
			appendCode(char);
			index += 1;
			continue;
		}
		if (char === '}' && interpolationDepth === 0 && templateStack.length) {
			out += '}';
			mode = 'tpl';
			interpolationDepth = templateStack.pop();
			previousSignificant = '}';
			previousWord = '';
			index += 1;
			continue;
		}
		if (char === '}') {
			interpolationDepth = Math.max(0, interpolationDepth - 1);
		}
		appendCode(char);
		index += 1;
	}
	return out;
}

function addBindingNames(defined, memberText) {
	// `{ prop: alias }` binds `alias`; nested patterns contribute every
	// inner identifier; defaults and rests strip away.
	const binding = memberText.includes(':') ? memberText.split(':').pop() : memberText;
	const cleaned = binding.split('=')[0].replace(/\.\.\./g, '');
	for (const name of cleaned.match(/[A-Za-z_$][\w$]*/g) || []) {
		defined.add(name);
	}
}

export function collectDefinedNames(stripped) {
	const defined = new Set();
	for (const match of stripped.matchAll(/\bfunction\s+([A-Za-z_$][\w$]*)\s*\(/g)) {
		defined.add(match[1]);
	}
	for (const match of stripped.matchAll(/\bclass\s+([A-Za-z_$][\w$]*)/g)) {
		defined.add(match[1]);
	}
	for (const match of stripped.matchAll(/\b(?:const|let|var)\s+([A-Za-z_$][\w$]*)/g)) {
		defined.add(match[1]);
	}
	for (const match of stripped.matchAll(/\b(?:const|let|var)\s*\{([^}]*)\}\s*=/g)) {
		for (const member of match[1].split(',')) addBindingNames(defined, member);
	}
	for (const match of stripped.matchAll(/\b(?:const|let|var)\s*\[([^\]]*)\]\s*=/g)) {
		for (const member of match[1].split(',')) addBindingNames(defined, member);
	}
	// Flat union of parameter names (named, anonymous, arrow, and catch
	// params, including one-level destructured bindings): flat-union only
	// masks shadowed locals; it never invents a definition for a missing
	// call target.
	const paramLists = [];
	for (const match of stripped.matchAll(/\bfunction\s+[A-Za-z_$][\w$]*\s*\(([^)]*)\)/g)) paramLists.push(match[1]);
	for (const match of stripped.matchAll(/\bfunction\s*\(([^)]*)\)/g)) paramLists.push(match[1]);
	for (const match of stripped.matchAll(/\(([^()]*)\)\s*=>/g)) paramLists.push(match[1]);
	for (const list of paramLists) {
		for (const param of list.split(',')) addBindingNames(defined, param.split('=')[0]);
	}
	for (const match of stripped.matchAll(/(?:^|[^\w$.])([A-Za-z_$][\w$]*)\s*=>/g)) {
		defined.add(match[1]);
	}
	for (const match of stripped.matchAll(/\bcatch\s*\(\s*([A-Za-z_$][\w$]*)/g)) {
		defined.add(match[1]);
	}
	return defined;
}

export function auditSource(label, source) {
	const stripped = stripNonCode(source);
	const defined = collectDefinedNames(stripped);
	const missing = [];
	for (const match of stripped.matchAll(/([A-Za-z_$][\w$]*)\s*\(/g)) {
		const name = match[1];
		const head = stripped.slice(0, match.index).replace(/\s+$/, '');
		if (head.endsWith('.')) continue; // property access, not a free identifier
		if (CALL_KEYWORDS.has(name) || defined.has(name) || BROWSER_GLOBALS.has(name)) continue;
		if (missing.some((entry) => entry.startsWith(`${name} `))) continue;
		const line = stripped.slice(0, match.index).split('\n').length;
		missing.push(`${name} (line ~${line})`);
	}
	return { label, missing };
}

/**
 * Every name the main bundle destructures from a `window.NpcinkToolbox*Helpers`
 * namespace must actually be exported by a part's frozen namespace assignment;
 * otherwise the binding is silently undefined at runtime even though the
 * destructured name looks "defined" to the call audit.
 */
export function auditNamespaceMembership(mainSource, partSources) {
	const exportedMembers = new Map();
	for (const part of partSources) {
		for (const match of part.matchAll(/window\.(NpcinkToolbox\w*Helpers)\s*=\s*Object\.freeze\(\{([\s\S]*?)\n\t\t\}\)/g)) {
			const namespace = match[1];
			const members = exportedMembers.get(namespace) || new Set();
			for (const line of match[2].split('\n')) {
				const member = line.match(/^\s*([A-Za-z_$][\w$]*),\s*$/);
				if (member) members.add(member[1]);
			}
			exportedMembers.set(namespace, members);
		}
	}
	let failures = 0;
	for (const match of mainSource.matchAll(/const\s*\{([^}]*)\}\s*=\s*\(typeof window [^\n]*window\.(NpcinkToolbox\w*Helpers)\)/g)) {
		const namespace = match[2];
		const exported = exportedMembers.get(namespace);
		for (const member of match[1].split(',')) {
			const binding = (member.includes(':') ? member.split(':').pop() : member).split('=')[0].trim();
			if (!/^[A-Za-z_$][\w$]*$/.test(binding)) continue;
			if (!exported || !exported.has(binding)) {
				failures += 1;
				console.error(`FAIL: the main bundle destructures ${binding} from window.${namespace}, but no part exports it.`);
			}
		}
	}
	return failures;
}

export function runAudit(sources) {
	let failures = 0;
	for (const entry of sources) {
		const { label, missing } = auditSource(entry.label, entry.source);
		if (missing.length) {
			failures += missing.length;
			console.error(`FAIL: ${label} calls undefined functions: ${missing.join(', ')}`);
		} else {
			console.log(`PASS: ${label} has no undefined call targets.`);
		}
	}
	return failures;
}

const isDirectRun = process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href;
if (isDirectRun) {
	// The shared reader throws when a part file exists on disk but is not
	// registered, so this audit can never silently skip an unscanned part.
	const sources = readEditorContentSupportSources();
	const entries = [
		...PART_ORDER.map((name, index) => ({ label: `assets/editor-content-support/${name}`, source: sources[index] })),
		{ label: 'assets/editor-content-support.js', source: sources[sources.length - 1] },
	];
	const failures = runAudit(entries)
		+ auditNamespaceMembership(sources[sources.length - 1], sources.slice(0, -1));
	if (failures) {
		console.error(`Editor JS undefined-call audit failed: ${failures} finding(s).`);
		process.exit(1);
	}
	console.log('Editor JS undefined-call audit passed.');
}
