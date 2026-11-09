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
const parts = ['submit','stop','question','status','result','answer','run-id','meta','pack','sources','completeness'];
root.nodes = Object.fromEntries(parts.map(p => [`[data-toolbox-smart-ask-${p}]`, new Node()]));
const node = p => root.nodes[`[data-toolbox-smart-ask-${p}]`];
node('sources').nodes = { ol: new Node('ol') };
const option = new Node(); option.attrs['data-endpoint'] = 'site-knowledge/answers';
node('pack').options = [option]; node('pack').selectedIndex = 0;
node('question').value = 'What is in the source?';
node('submit').textContent = 'Ask';
const document = { querySelectorAll: () => [root], createElement: tag => new Node(tag), createTextNode: text => ({textContent:text}) };
let reply;
let pending = false;
let transportCalls = 0;
let lastSignal;
const timers = new Map();
let timerId = 0;
const postJson = async (_base, _path, _payload, signal) => {
 transportCalls += 1; lastSignal = signal;
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
reply = new Error('Normalized Cloud failure'); reply.data = {diagnostic:{run_id:'run_diagnostic'},status:502};
await node('submit').handlers.click();
assert.equal(node('run-id').textContent, 'run_diagnostic', 'Normalized Toolbox errors retain the Cloud correlation ID in support details.');
assert.equal(node('result').hidden, false);
reply = fixture('https://site.test/source'); await node('submit').handlers.click();
node('pack').handlers.change();
assert.equal(node('result').hidden, true); assert.equal(node('sources').hidden, true);
assert.equal(timers.size, 0, 'Success and failure clear the request deadline.');

pending = true;
const waiting = node('submit').handlers.click();
assert.equal(node('stop').hidden, false);
assert.equal(node('question').disabled, true);
const callsBeforeDuplicate = transportCalls;
await node('submit').handlers.click();
assert.equal(transportCalls, callsBeforeDuplicate, 'Double clicks never start another request.');
node('stop').handlers.click();
await waiting;
assert.equal(lastSignal.aborted, true);
assert.match(node('status').textContent, /Stopped waiting.*Cloud may still be processing/);
assert.equal(node('stop').hidden, true);
assert.equal(node('submit').disabled, false);
assert.equal(node('question').disabled, false);
assert.equal(node('pack').disabled, false);
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
option.attrs['data-endpoint'] = 'site-ops/insight-pack-qa';
reply = {ok:true, answer:'Fresh summary', run_id:'run_fresh'};
await node('submit').handlers.click();
assert.equal(lastSignal.aborted, false, 'A user retry gets a fresh request signal.');
assert.equal(node('answer').textContent, 'Fresh summary');
assert.equal(node('run-id').textContent, 'run_fresh');
assert.equal(node('sources').hidden, true);
assert.equal(timers.size, 0);

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
