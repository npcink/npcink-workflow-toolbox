import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const config = JSON.parse(execFileSync('php', ['tests/article-credential-check-behavior.php', '--rules']));
const window = { NpcinkArticleCredentialRules: config, wp: { plugins: {}, data: {}, element: {}, components: {}, i18n: { __: (x) => x, sprintf: (x, n) => x.replace('%d', n) } } };
const document = { createElement: () => ({ set innerHTML(value) { this.value = value.replace('&#95;', '_'); } }) };
vm.runInNewContext(readFileSync('assets/article-credential-editor.js', 'utf8'), { window, document, TextEncoder });
const { scan, applyFinding, guardTransfer, captureBlock, recoveryStatus, transferFindings, hasAdvisory } = window.NpcinkToolboxCredentialCheck;
const key = 'sk_' + 'live_' + 'a'.repeat(24);
const token = 'gh' + 'p_' + 'b'.repeat(36);
const block = { clientId: 'c1', name: 'core/code', attributes: { content: `first=${key}; second=${token}` } };
const link = { clientId: 'c2', name: 'core/paragraph', attributes: { content: `<a href="https://example.invalid/?key=${key}">link</a>` } };
const encoded = { clientId: 'c3', name: 'core/paragraph', attributes: { content: token.replace('_', '&#95;') } };
const source = { title: '', excerpt: '', content: '', blocks: [block, link, encoded] };
const { items, partial } = scan(source);
assert.equal(partial, false);
assert.equal(items.length, 4);
assert.equal(items[2].span, undefined, 'HTML attributes are manual only');
assert.equal(items[3].span, null, 'Encoded text is manual only');
assert.equal(scan({ ...source, content: 'a'.repeat(config.maxBytes + 1) }).partial, true);

let current = block;
let postId = 7;
let canEdit = true;
let updates = 0;
const data = {
	select: (store) => store === 'core/editor' ? { getCurrentPostId: () => postId, isSavingPost: () => false } : { getBlock: () => current, canEditBlock: () => canEdit },
	dispatch: () => ({ updateBlockAttributes: (id, value) => {
		assert.equal(id, 'c1');
		assert.equal(value.content, `first=YOUR_API_KEY; second=${token}`);
		current = { ...current, attributes: value };
		updates++;
	} }),
};
assert.equal(applyFinding(items[0], 7, data), true);
assert.equal(applyFinding(items[1], 7, data), false, 'Old snapshot cannot edit the changed block');
current = block;
canEdit = false;
assert.equal(applyFinding(items[0], 7, data), false);
canEdit = true;
postId++;
assert.equal(applyFinding(items[0], 7, data), false);
assert.equal(updates, 1);

for (const payload of [{ intent: 'body_faq', content: key }, { intent: 'publish_preflight', article_review_snapshot: { blocks: [{ text: key }] } }]) {
	assert.throws(() => guardTransfer('editor/content-support', payload), (error) => error.code === 'npcink_article_credential_check_hit' && !error.message.includes(key));
}
assert.doesNotThrow(() => guardTransfer('/editor/content-support', { intent: 'body_faq', content: 'YOUR_API_KEY' }));
assert.doesNotThrow(() => guardTransfer('/editor/content-support', { intent: 'format_content', content: key }));
const advisory = 'password = "not-a-real-value"';
assert.equal(hasAdvisory([advisory]), true);
assert.equal(hasAdvisory(['password = "YOUR_PASSWORD"']), false);
assert.equal(scan({ ...source, title: advisory, blocks: [] }).items[0].rule.advisory, true);
assert.doesNotThrow(() => guardTransfer('editor/content-support', { intent: 'body_faq', content: advisory }));
assert.throws(() => guardTransfer('editor/content-support', { intent: 'publish_preflight', content: advisory, selected_block_text: key }), { code: 'npcink_article_credential_check_hit' });

const faq = { post_id: 7, intent: 'body_faq', content: `<pre>${key}</pre>`, title: token };
const unrelated = { ...link, attributes: { content: `<a href="https://example.invalid/?key=${token}">link</a>` } };
const faqReport = transferFindings(faq, [block, unrelated]);
assert.equal(faqReport.items.length, 1, 'FAQ lists only the credential in the outbound body, not title or unrelated block attributes');
assert.equal(faqReport.items[0].block.clientId, 'c1');
assert.ok(faqReport.items[0].span);
const manual = transferFindings({ ...faq, content: `<a href="https://example.invalid/?key=${token}">link</a>` }, [unrelated]);
assert.equal(manual.items[0].block.clientId, 'c2');
assert.equal(manual.items[0].span, undefined, 'A matching link attribute remains manual');
const snapshot = { ...faq, intent: 'publish_preflight', content: '', article_review_snapshot: { blocks: [{ kind: 'body', client_id: 'c1', text: key }] } };
assert.deepEqual(Array.from(transferFindings(snapshot, [block]).items, ({ location }) => location), ['Title', 'Block 1']);
assert.equal(transferFindings(faq, []).items[0].location, 'Article body (manual review)');
const hit = { code: 'npcink_article_credential_check_hit' };
const blocked = captureBlock(hit, faq);
assert.equal(blocked.serverFields, null);
assert.equal(recoveryStatus(blocked, faq), hit.code);
assert.equal(recoveryStatus(blocked, { ...faq, post_id: 8 }), 'different_post');
assert.equal(recoveryStatus(blocked, { ...faq, content: '<pre>YOUR_API_KEY</pre>' }), '');
const serverOnly = { ...faq, content: 'initial body' };
const serverBlocked = captureBlock(hit, serverOnly);
assert.equal(recoveryStatus(serverBlocked, { ...serverOnly, title: 'Title edit' }), 'server_hit', 'Editing a field FAQ does not send is not a remediation');
assert.equal(recoveryStatus(serverBlocked, { ...serverOnly, content: 'revised body' }), '');
assert.equal(captureBlock({ code: 'provider_error' }, faq), null);
console.log('Article credential editor behavior: ok');
