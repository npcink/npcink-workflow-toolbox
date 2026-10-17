/** Bounded development smoke evidence, including failures before acceptance. */
import { createHash } from 'node:crypto';

const knownCodes = new Set([
	'http_request_failed', 'cloud_runtime_unconfigured', 'rest_cookie_invalid_nonce',
	'npcink_cloud_runtime_timeout', 'npcink_cloud_runtime_failed', 'npcink_cloud_runtime_unconfigured',
]);
const choice = (value, allowed) => allowed.includes(value) ? value : 'unknown';
const duration = (value) => Number.isFinite(value) && value >= 0 && value <= 600000 ? Math.round(value) : null;

export function recommendationCloudObservation({ section = {}, httpStatus, elapsedMs, requestBody, phase = 'response', errorName = '' } = {}) {
	const knowledge = section?.source_knowledge || {};
	const code = typeof knowledge.code === 'string' ? knowledge.code : (typeof section?.error_code === 'string' ? section.error_code : '');
	// Classify locally; do not export arbitrary provider errors, messages, URLs or bodies.
	const message = typeof knowledge.message === 'string' ? knowledge.message : '';
	const hint = (code + ' ' + message).toLowerCase();
	let errorClass = 'none';
	if (phase !== 'response') errorClass = phase === 'editor_setup_failed' ? 'editor_setup_failed' : (phase === 'invalid_json' ? 'invalid_json' : (errorName === 'TimeoutError' ? 'browser_timeout' : 'browser_request_failed'));
	else if (code || ['error', 'failed'].includes(knowledge.status) || section?.retrieval_status === 'cloud_unavailable' || (httpStatus >= 400)) {
		errorClass = /timed?[_ ]?out|timeout|curl error 28/.test(hint) ? 'timeout'
			: /resolve host|resolution|dns|curl error 6/.test(hint) ? 'dns'
				: /ssl|certificate|tls/.test(hint) ? 'tls'
					: /connection|connect|curl error 7/.test(hint) ? 'connection'
						: /unconfigured/.test(hint) ? 'unconfigured' : 'unclassified';
	}
	return {
		contract: 'recommendation_cloud_smoke_observation.v1',
		gold_source: 'none_developer_smoke',
		phase: choice(phase, ['response', 'invalid_json', 'request_failed', 'editor_setup_failed']),
		http_status: Number.isInteger(httpStatus) && httpStatus >= 100 && httpStatus <= 599 ? httpStatus : null,
		elapsed_from_click_ms: duration(elapsedMs),
		request_sha256: typeof requestBody === 'string' && requestBody ? createHash('sha256').update(requestBody).digest('hex') : null,
		retrieval_status: choice(section?.retrieval_status, ['cloud_vector_evidence', 'no_cloud_evidence', 'cloud_unavailable']),
		cloud_status: choice(knowledge.status, ['ready', 'error', 'failed', 'running', 'queued']),
		cache_status: choice(knowledge.cache_status, ['hit', 'miss', 'bypass']),
		candidate_count: Array.isArray(section?.recommendation_candidates) ? section.recommendation_candidates.length : null,
		error_code: knownCodes.has(code) ? code : (code ? 'unlisted' : null),
		error_code_sha256: code ? createHash('sha256').update(code).digest('hex') : null,
		error_class: errorClass,
		error_class_is_heuristic: true,
	};
}
