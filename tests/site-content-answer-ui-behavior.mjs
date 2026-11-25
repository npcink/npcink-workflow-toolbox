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
 focus() { this.focused = true; }
 querySelector(s) { return this.nodes[s]; }
}
const root = new Node();
const parts = ['submit','stop','stop-note','question','status','result','answer','run-id','meta','basis','sources','completeness','continue','recheck','new','original','previous','label'];
root.nodes = Object.fromEntries(parts.map(p => [`[data-toolbox-smart-ask-${p}]`, new Node()]));
const node = p => root.nodes[`[data-toolbox-smart-ask-${p}]`];
node('sources').nodes = { ol: new Node('ol') };
root.attrs['data-source-hint'] = 'site_ops';
node('question').value = 'What is in the source?';
node('submit').textContent = 'Ask';
const document = { querySelectorAll: () => [root], createElement: tag => new Node(tag), createTextNode: text => ({textContent:text}) };
let reply;
let pending = false;
let transportCalls = 0;
let lastSignal;
let lastPayload;
const timers = new Map();
let timerId = 0;
const postJson = async (_base, path, payload, signal) => {
 transportCalls += 1; lastSignal = signal; lastPayload = payload; assert.equal(path, 'smart-ask');
 if (pending) return new Promise((_resolve, reject) => signal.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')), {once:true}));
 if (reply instanceof Error) throw reply; return reply;
};
const initialize = new Function('document','window','config','t','postJson','setMarkdownContent','setTimeout','clearTimeout', code + '\nreturn initSmartAsk;');
initialize(document, {location:{origin:'https://site.test'}}, {restUrl:'/wp-json/'}, text => text, postJson, (el,text) => {el.textContent=text;},
 (fn, delay) => { const id = ++timerId; timers.set(id, {fn,delay}); return id; }, id => timers.delete(id))();
function fixture(url, partial = false) {
 return {answer:'<img src=x onerror=bad()>\n[1]',run_id:'run_ui',citations:[{id:'source-1',quote:'<script>bad()</script>'}],grounded_evidence:{status:partial?'partial':'available',sources:[{id:'source-1',title:'<b>Untrusted title</b>',url}]}};
}
reply = fixture('https://site.test/source');
await node('submit').handlers.click();
assert.equal(lastPayload.source_hint, 'site_ops');
assert.equal(node('answer').textContent, reply.answer);
assert.equal(node('answer').children.length, 0, 'Answer HTML is never parsed.');
assert.equal(node('answer').style.whiteSpace, 'pre-wrap');
let row = node('sources').nodes.ol.children[0];
assert.equal(row.children[0].textContent, '<b>Untrusted title</b>');
assert.equal(row.children.at(-1).textContent, '<script>bad()</script>');
const link = row.children.find(x => x.tag === 'a');
assert.equal(link.href, 'https://site.test/source'); assert.equal(link.rel, 'noopener noreferrer');
function newQuestion(value = 'What is in the source?') {
 node('new').handlers.click();
 node('question').value = value;
 node('question').handlers.input();
}
for (const url of ['javascript:bad()', 'https://other.test/source', 'invalid']) {
 newQuestion();
 reply = fixture(url, true); await node('submit').handlers.click();
 row = node('sources').nodes.ol.children[0];
 assert.equal(row.children.some(x => x.tag === 'a'), false, 'Unsafe or foreign source URLs stay inert.');
 assert.match(node('completeness').textContent, /partial/);
}
newQuestion();
reply = new Error('Unavailable'); reply.data = {run_id:'run_failed',status:503};
await node('submit').handlers.click();
assert.equal(node('sources').hidden, true); assert.equal(node('sources').nodes.ol.children.length, 0);
assert.equal(node('answer').textContent, ''); assert.equal(node('run-id').textContent, 'run_failed');
assert.equal(node('submit').disabled, false);
assert.equal(node('stop-note').hidden, true, 'Stop guidance is absent after a failed request.');
reply = new Error('Normalized Cloud failure'); reply.data = {diagnostic:{run_id:'run_diagnostic'},status:502};
await node('submit').handlers.click();
assert.equal(node('run-id').textContent, 'run_diagnostic', 'Normalized Toolbox errors retain the Cloud correlation ID in support details.');
assert.equal(node('result').hidden, false);
reply = fixture('https://site.test/source'); await node('submit').handlers.click();
newQuestion();
assert.equal(node('result').hidden, true); assert.equal(node('sources').hidden, true);
assert.equal(timers.size, 0, 'Success and failure clear the request deadline.');

pending = true;
const waiting = node('submit').handlers.click();
assert.equal(node('stop').hidden, false);
assert.equal(node('stop-note').hidden, false, 'Stop guidance appears only while waiting.');
assert.equal(node('question').disabled, true);
const callsBeforeDuplicate = transportCalls;
await node('submit').handlers.click();
assert.equal(transportCalls, callsBeforeDuplicate, 'Double clicks never start another request.');
node('stop').handlers.click();
await waiting;
assert.equal(lastSignal.aborted, true);
assert.match(node('status').textContent, /Stopped waiting.*Cloud may still be processing/);
assert.equal(node('stop').hidden, true);
assert.equal(node('stop-note').hidden, true, 'Stop guidance leaves with the stop control.');
assert.equal(node('submit').disabled, false);
assert.equal(node('question').disabled, false);
assert.equal(root.nodes['[data-toolbox-smart-ask-pack]'], undefined, 'No data-package selector is required.');
assert.equal(node('result').hidden, true, 'Stopping never exposes the previous answer.');
assert.equal(timers.size, 0);

const expired = node('submit').handlers.click();
const deadline = [...timers.values()][0];
assert.equal(deadline.delay, 90000, 'The browser wait is bounded without extending the Cloud budget.');
deadline.fn();
await expired;
assert.match(node('status').textContent, /took too long.*Cloud may still be processing/);
assert.equal(node('submit').disabled, false);
assert.equal(timers.size, 0);
pending = false;
assert.equal(node('stop-note').hidden, true, 'Timeout also clears stop guidance.');

reply = {ok:true, answer:'Fresh summary', run_id:'run_fresh', answer_basis:'site_summary'};
await node('submit').handlers.click();
assert.equal(lastSignal.aborted, false, 'A user retry gets a fresh request signal.');
assert.equal(node('answer').textContent, 'Fresh summary');
assert.equal(node('run-id').textContent, 'run_fresh');
assert.match(node('basis').textContent, /bounded site summary/);
assert.equal(node('sources').hidden, true);
assert.equal(timers.size, 0);

for (const [basis, label] of [['clarification',/has not been checked/], ['general_guidance',/has not been checked/]]) {
 newQuestion();
 reply = {ok:true, answer:'A short response', run_id:'run_short', answer_basis:basis};
 await node('submit').handlers.click();
 assert.match(node('basis').textContent, label);
 assert.equal(node('basis').hidden, false);
 assert.equal(node('sources').hidden, true);
 assert.equal(node('answer').textContent, 'A short response');
}

newQuestion('Why does my upload fail?');
reply = {ok:true, answer:'How large is the file?', run_id:'run_clarify', answer_basis:'clarification'};
await node('submit').handlers.click();
assert.equal(node('recheck').hidden, true, 'Clarification did not check the site.');
node('continue').handlers.click();
node('question').value = '18 MB in Media Library';
node('question').handlers.input();
reply = new Error('Unavailable'); reply.data = {run_id:'run_failed_followup'};
await node('submit').handlers.click();
assert.deepEqual(lastPayload, {question:'Why does my upload fail?', source_hint:'', details:['18 MB in Media Library']});
assert.equal(node('previous').hidden, false);
assert.equal(node('answer').textContent, 'How large is the file?');
assert.equal(node('question').value, '18 MB in Media Library');
assert.equal(node('run-id').textContent, 'run_clarify');
assert.match(node('meta').textContent, /run_failed_followup/);
reply = {ok:true, answer:'WordPress reports 8 MB', run_id:'run_upload', answer_basis:'site_summary'};
await node('submit').handlers.click();
assert.equal(node('recheck').hidden, false);
reply = {ok:true, answer:'WordPress now reports 32 MB', run_id:'run_recheck', answer_basis:'site_summary'};
await node('recheck').handlers.click();
assert.deepEqual(lastPayload, {question:'Why does my upload fail?', source_hint:'', details:['18 MB in Media Library'], recheck:true});
assert.equal(node('answer').textContent, reply.answer);
assert.equal(node('previous').hidden, true);
assert.equal(node('continue').hidden, true);
assert.equal(node('recheck').hidden, true);
newQuestion('How many published articles?');
assert.equal(node('result').hidden, true);
await node('submit').handlers.click();
assert.deepEqual(lastPayload, {question:'How many published articles?', source_hint:''}, 'Starting a new question drops all prior details and entry hints.');

const transportStart = source.indexOf('\tasync function postJson(');
const transportEnd = source.indexOf('\n\tasync function ', transportStart + 10);
let fetchOptions;
const realPostJson = new Function('fetch','joinRestUrl','config', source.slice(transportStart, transportEnd) + '\nreturn postJson;')(
 async (_url, options) => { fetchOptions = options; return {ok:true,json:async()=>({ok:true})}; }, (base,path)=>base+path, {nonce:'test'});
const controller = new AbortController();
await realPostJson('/wp-json/', 'site-ops/insight-pack-qa', {question:'Test'}, controller.signal);
assert.equal(fetchOptions.signal, controller.signal, 'The real transport forwards cancellation to fetch.');
assert.equal(fetchOptions.headers['X-WP-Nonce'], 'test');
console.log('Smart Ask UI: inert sources, failure correlation, cancellation, timeout, duplicate suppression and fresh retry passed.');
