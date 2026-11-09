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

// Execute the installed handlers' real try/catch/finally paths. A declaration
// inside try previously threw ReferenceError in finally on every search.
for (const name of ['runAutoImageRecommendations', 'runImageSearch']) {
 const begin = source.indexOf(`\t\tasync function ${name}(`);
 const finish = source.indexOf('\n\t\tasync function ', begin + 10);
 assert.ok(begin >= 0 && finish > begin);
 for (const scenario of ['success', 'failure', 'cancelled', 'superseded', 'stale']) {
  const signal = new AbortController().signal;
  const ended = [];
  const sequence = {current:0};
  let starts = 0;
  const noOp = () => {};
  const deps = Object.fromEntries(['setImageRetryAction', 'setImageRunning', 'setImageCompletionRunning', 'setImageError', 'setImageGuidance', 'setImageResultForSearchMode', 'setImagePreviewLightbox', 'setImageSelectionError', 'setImageQuery', 'submitImplicitAgentFeedback', 'writeCachedImageResult'].map(key => [key, noOp]));
  Object.assign(deps, {
   imagePicker:null, imageMode:'featured', postContext:{}, imageQuery:'coffee',
   imageSearchModeRef:{current:'source'}, imageSourceRequestSeqRef:sequence,
   normalizeImagePickerOptions:()=>({mode:'featured',context:{},imageUse:'featured_image'}),
   imagePickerRequestContext:()=>({}), mediaQualitySessionId:()=>'synthetic-session',
   imageSearchCacheKey:()=>'synthetic-key', readCachedImageResult:()=>null,
   hasScopedImageContext:()=>true, imageResultForSearchMode:()=>null,
   imageFastSearchQuery:()=>'coffee', imageRefreshVariant:()=>'',
   buildImageVisualContext:()=>({}), imagePickerContextOverride:()=>({}),
   beginContentRequest:()=>{ starts++; return signal; },
   endContentRequest:value=>ended.push(value),
   postImageCandidatesFastFirst:async (_payload, actualSignal)=>{
    assert.equal(actualSignal, signal);
    if (scenario === 'stale') sequence.current++;
    if (scenario === 'failure') throw new Error('Synthetic provider failure');
    if (['cancelled','superseded'].includes(scenario)) throw {code:`npcink_toolbox_request_${scenario}`};
    return {images:[{}]};
   },
   withMediaQualitySession:value=>value, extractImageCandidates:value=>value.images,
   imageAutoFallbackQueries:()=>[], IMAGE_SOURCE_AUTO_FALLBACK_MAX_ATTEMPTS:2,
   imageCandidateResultNeedsCompletion:()=>false, completeImageSourceCandidates:async()=>{},
   editorMediaSearchFeedbackPayload:()=>({}), formatImageErrorMessage:()=>'', __:text=>text,
  });
  const handler = new Function(...Object.keys(deps), source.slice(begin, finish) + `\nreturn ${name};`)(...Object.values(deps));
  await handler();
  assert.equal(starts, 1, `${name}/${scenario}: one request starts.`);
  assert.deepEqual(ended, [signal], `${name}/${scenario}: finally releases the same request without throwing.`);
 }
}
console.log('Image search lifecycle: automatic/manual success, failure, cancellation, supersession and stale responses release their request.');
