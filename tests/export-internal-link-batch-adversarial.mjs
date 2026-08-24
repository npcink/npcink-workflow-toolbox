import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const args = Object.fromEntries(process.argv.slice(2).filter((arg) => arg.includes('=')).map((arg) => arg.split(/=(.*)/s, 2)));
const evalLab = process.env.NPCINK_EVAL_LAB_PATH || path.resolve(root, '../npcink-eval-lab');
const fixturePath = path.resolve(root, args.fixture || path.join(evalLab, 'link-recommendation/fixtures/batch-adversarial.v1.json'));
const outputPath = path.resolve(root, args.output || 'build/eval/link-batch-adversarial-results.json');

const source = fs.readFileSync(path.join(root, 'assets/editor-content-support.js'), 'utf8');
const windowObject = { location: { href: 'https://example.test/wp-admin/post.php' }, wp: {} };
windowObject.window = windowObject;
vm.runInNewContext(source, { window: windowObject, URL, Object, Array, String, Number, Boolean, Set }, { filename: 'editor-content-support.js' });
const helpers = windowObject.NpcinkToolboxInternalLinkHelpers;
if (!helpers || typeof helpers.internalLinkBatchPreflight !== 'function') {
	throw new Error('Toolbox internal-link batch preflight helper is unavailable.');
}

const fixture = JSON.parse(fs.readFileSync(fixturePath, 'utf8'));
if (fixture.contract !== 'link_batch_adversarial_fixture.v1' || !Array.isArray(fixture.cases)) {
	throw new Error('Invalid link_batch_adversarial_fixture.v1 input.');
}

function normalizedInput(input) {
	const value = input && typeof input === 'object' ? input : {};
	const anchor = String(value.anchor || '内容工作流');
	const sourceMatch = value.source_match === false ? null : {
		block_client_id: 'fixture-block',
		block_name: String(value.block_name || 'core/paragraph'),
		matched_text: anchor,
		expected_text: String(value.expected_text || anchor),
		text_offset: 0,
	};
	return {
		anchorText: anchor,
		targetUrl: String(value.target_url || 'https://example.test/target'),
		targetStatus: String(value.target_status || 'publish'),
		targetAlreadyLinked: value.existing_target === true,
		targetPostIds: Array.isArray(value.target_post_ids) ? value.target_post_ids : [2001],
		ranges: Array.isArray(value.ranges) ? value.ranges : [[0, Array.from(anchor).length]],
		retrievalStatus: String(value.retrieval_status || 'cloud_vector_evidence'),
		candidateSource: String(value.candidate_source || 'cloud_vector'),
		sourceMatch,
		currentText: String(value.current_text || value.expected_text || anchor),
	};
}

const artifact = {
	contract: 'link_batch_adversarial_results.v1',
	write_posture: 'eval_only_no_wordpress_write',
	results: fixture.cases.map((testCase) => ({
		id: String(testCase.id || ''),
		...helpers.internalLinkBatchPreflight(normalizedInput(testCase.input)),
	})),
};

fs.mkdirSync(path.dirname(outputPath), { recursive: true });
fs.writeFileSync(outputPath, JSON.stringify(artifact, null, 2) + '\n');
console.log(`Internal-link batch adversarial artifact written: ${outputPath}`);
