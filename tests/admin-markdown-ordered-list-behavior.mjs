// Behavior test for the admin safe Markdown renderer (ordered lists from Cloud answers).
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const source = readFileSync(path.join(root, 'assets/admin.js'), 'utf8');

function extract(name) {
	const start = source.indexOf('\tfunction ' + name + '(');
	if (start < 0) throw new Error('missing ' + name);
	const next = source.indexOf('\n\tfunction ', start + 10);
	return source.slice(start, next < 0 ? undefined : next);
}
const renderSafeMarkdown = new Function(
	[extract('escapeHtml'), extract('formatInlineMarkdown'), extract('renderSafeMarkdown'), 'return renderSafeMarkdown;'].join('\n')
)();

let failures = 0;
function check(ok, label) {
	if (!ok) { failures += 1; console.error('FAIL: ' + label); }
}
const count = (html, needle) => html.split(needle).length - 1;

const answer = [
	'结论：先处理旧文和内链。',
	'',
	'1. 清老文章',
	'',
	'做什么：挑 10 篇最旧的文章更新。',
	'为什么：长期未更新。',
	'怎么做：先看标题样本。',
	'',
	'2. 补站内互相链接',
	'',
	'做什么：给相关文章互相加链接。',
	'   缩进的补充说明',
	'- 子要点 A',
	'- 子要点 B',
	'',
	'3. 补 SEO 描述',
	'做什么：给重点页写描述。',
	'',
	'以上仅供参考。',
].join('\n');
const html = renderSafeMarkdown(answer);
check(count(html, '<ol') === 1, 'consecutive numbered items share one <ol>');
check(count(html, '<li>') >= 5 && (html.match(/<ol[^>]*>(.*?)<\/ol>/s) || [''])[0].split('<li>').length - 1 === 5, 'three items plus two nested bullets');
check(!/ start=/.test(html), 'list starting at 1 has no start attribute');
check(html.indexOf('<p class="npcink-toolbox__md-p">结论') === 0, 'leading conclusion is a paragraph before the list');
check(html.includes('做什么：挑 10 篇最旧的文章更新。') && html.indexOf('做什么：挑') < html.indexOf('2. ') + 1e9, 'continuation lines stay inside the item');
check(/<\/ol><p class="npcink-toolbox__md-p">以上仅供参考。<\/p>$/.test(html), 'closing paragraph after the list is not swallowed');
check(html.includes('缩进的补充说明'), 'indented continuation kept');

const split = renderSafeMarkdown('## 第一部分\n\n1. 甲\n\n## 第二部分\n\n2. 乙\n3. 丙');
check(count(split, '<ol') === 2 && split.includes('<ol class="npcink-toolbox__md-list" start="2">'), 'list resumed after a heading honors source number via start');

const evil = renderSafeMarkdown('1. <img src=x onerror=alert(1)>\n\n做什么：<script>alert(1)</script>\n\n2. ok');
check(!evil.includes('<img') && !evil.includes('<script') && evil.includes('&lt;script&gt;'), 'list content is HTML-escaped first');

const bullets = renderSafeMarkdown('- a\n- b');
check(bullets === '<ul class="npcink-toolbox__md-list"><li>a</li><li>b</li></ul>', 'plain bullet lists unchanged');

if (failures) { process.exit(1); }
console.log('All admin markdown ordered-list behavior checks passed.');
