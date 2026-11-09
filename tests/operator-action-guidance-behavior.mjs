// The real metadata renderer must not offer an admin-only action to authors.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../assets/editor-content-support.js', import.meta.url), 'utf8');
const flagExpression = source.match(/const canSubmitMetadataHandoff = (.+);/)[1];
const canSubmit = new Function('config', 'return ' + flagExpression + ';');
for (const value of [undefined, false, '', '0', 'false', 'true']) {
 assert.equal(canSubmit({canSubmitMetadataHandoff:value}), false, 'Missing and false-like localized capabilities fail closed.');
}
for (const value of [true, '1']) {
 assert.equal(canSubmit({canSubmitMetadataHandoff:value}), true, 'Boolean and WordPress-localized administrator capabilities remain usable.');
}
const start = source.indexOf('\tfunction renderMetadataHandoffControl(');
assert.ok(start >= 0);
const end = source.indexOf('\n\tfunction ', start + 10);
const createElement = (type, props, ...children) => ({type, props, children});
const render = new Function('createElement', '__', 'metadataRecommendedExcerpt', 'metadataDeltaTermItems', 'metadataSelectionHasValue', 'renderMetadataTermChoices', 'Button', source.slice(start, end) + '\nreturn renderMetadataHandoffControl;')(
 createElement, text => text, () => '', () => [], selection => Boolean(selection.category_ids?.length), () => null, 'button');
const buttons = tree => !tree || typeof tree !== 'object' ? [] : [
 ...(tree.type === 'button' ? [tree] : []), ...tree.children.flatMap(buttons),
];
for (const canSubmit of [undefined, false, 'true']) {
 const tree = render({}, {canSubmit, selection:{category_ids:[12]}});
 assert.equal(buttons(tree).length, 0, 'Authors and missing configuration expose no proposal action.');
 assert.match(tree.children.join(''), /WordPress Categories or Tags panel/);
 assert.match(tree.children.join(''), /administrator permission/);
}
let submissions = 0;
const controls = {canSubmit:true, selection:{category_ids:[12]}, submit:()=>{ submissions += 1; }};
const action = buttons(render({}, controls))[0];
assert.equal(action.props.disabled, false);
action.props.onClick();
assert.equal(submissions, 1, 'The existing administrator handoff remains usable.');
assert.equal(buttons(render({}, {...controls, selection:{}}))[0].props.disabled, true);
assert.equal(buttons(render({}, {...controls, running:true}))[0].props.disabled, true);
console.log('Metadata guidance: author fallback, fail-closed configuration and administrator proposal controls passed.');
