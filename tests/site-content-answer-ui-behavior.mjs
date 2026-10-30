// Execute the real Smart Ask handler with an inert DOM and controlled transport.
import { readFileSync } from 'node:fs';
import assert from 'node:assert/strict';
const source = readFileSync(new URL('../assets/admin.js', import.meta.url), 'utf8');
const start = source.indexOf('\tfunction initSmartAsk(');
const end = source.indexOf('\n\tfunction ', start + 10);
const code = source.slice(start, end);
class Node {
 constructor(tag = 'div') { this.tag = tag; this.textContent = ''; this.children = []; this.attrs = {}; this.handlers = {}; this.style = {}; this.hidden = true; this.classList = {add(){},remove(){},toggle(){}}; }
 append(...children) { this.children.push(...children); }
 replaceChildren() { this.children = []; }
 setAttribute(k, v) { this.attrs[k] = v; }
 getAttribute(k) { return this.attrs[k] || ''; }
 addEventListener(k, fn) { this.handlers[k] = fn; }
 querySelector(s) { return this.nodes[s]; }
}
const root = new Node();
const parts = ['submit','question','status','result','answer','run-id','meta','pack','sources','completeness'];
root.nodes = Object.fromEntries(parts.map(p => [`[data-toolbox-smart-ask-${p}]`, new Node()]));
const node = p => root.nodes[`[data-toolbox-smart-ask-${p}]`];
node('sources').nodes = { ol: new Node('ol') };
const option = new Node(); option.attrs['data-endpoint'] = 'site-knowledge/answers';
node('pack').options = [option]; node('pack').selectedIndex = 0;
node('question').value = 'What is in the source?';
node('submit').textContent = 'Ask';
const document = { querySelectorAll: () => [root], createElement: tag => new Node(tag), createTextNode: text => ({textContent:text}) };
let reply;
const postJson = async () => { if (reply instanceof Error) throw reply; return reply; };
const initialize = new Function('document','window','config','t','postJson','setMarkdownContent', code + '\nreturn initSmartAsk;');
initialize(document, {location:{origin:'https://site.test'}}, {restUrl:'/wp-json/'}, text => text, postJson, (el,text) => {el.textContent=text;})();
function fixture(url, partial = false) {
 return {answer:'<img src=x onerror=bad()>\n[1]',run_id:'run_ui',citations:[{id:'source-1',quote:'<script>bad()</script>'}],grounded_evidence:{status:partial?'partial':'available',sources:[{id:'source-1',title:'<b>Untrusted title</b>',url}]}};
}
reply = fixture('https://site.test/source');
await node('submit').handlers.click();
assert.equal(node('answer').textContent, reply.answer);
assert.equal(node('answer').children.length, 0, 'Answer HTML is never parsed.');
assert.equal(node('answer').style.whiteSpace, 'pre-wrap');
let row = node('sources').nodes.ol.children[0];
assert.equal(row.children[0].textContent, '<b>Untrusted title</b>');
assert.equal(row.children.at(-1).textContent, '<script>bad()</script>');
const link = row.children.find(x => x.tag === 'a');
assert.equal(link.href, 'https://site.test/source'); assert.equal(link.rel, 'noopener noreferrer');
for (const url of ['javascript:bad()', 'https://other.test/source', 'invalid']) {
 reply = fixture(url, true); await node('submit').handlers.click();
 row = node('sources').nodes.ol.children[0];
 assert.equal(row.children.some(x => x.tag === 'a'), false, 'Unsafe or foreign source URLs stay inert.');
 assert.match(node('completeness').textContent, /partial/);
}
reply = new Error('Unavailable'); reply.data = {run_id:'run_failed',status:503};
await node('submit').handlers.click();
assert.equal(node('sources').hidden, true); assert.equal(node('sources').nodes.ol.children.length, 0);
assert.equal(node('answer').textContent, ''); assert.equal(node('run-id').textContent, 'run_failed');
assert.equal(node('submit').disabled, false);
reply = fixture('https://site.test/source'); await node('submit').handlers.click();
node('pack').handlers.change();
assert.equal(node('result').hidden, true); assert.equal(node('sources').hidden, true);
console.log('Site answer UI: inert content, source URL bounds, partial state, failure correlation and stale-source reset passed.');
