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

// The external fast-source deadline used to abort valid 15-second semantic
// media retrieval. Exercise the real timeout helper with a deterministic clock.
const timeoutStart = source.indexOf('\tasync function postJsonWithTimeout(');
const timeoutEnd = source.indexOf('\n\tfunction ', source.indexOf('\tasync function postImageCandidatesFastFirst('));
assert.ok(timeoutStart >= 0 && timeoutEnd > timeoutStart);
for (const provider of ['auto', 'site_media']) {
 const timers = [];
 let request;
 const clock = {AbortController, setTimeout:(callback,delay)=>{ timers.push({callback,delay}); return timers.length; }, clearTimeout:()=>{}};
 const fetchResponse = (_url, options) => new Promise((resolve,reject)=>{
  request={options,resolve,reject};
  options.signal.addEventListener('abort',()=>reject({name:'AbortError'}));
 });
 const constants = source.match(/const (?:IMAGE_SOURCE_FAST_TIMEOUT_MS|SITE_MEDIA_SEARCH_TIMEOUT_MS) = \d+;/g).join('\n');
 const helper = new Function('window','fetch','config','__','joinRestUrl','parseJsonResponse', constants+'\n'+source.slice(timeoutStart,timeoutEnd)+'\nreturn postImageCandidatesFastFirst;')(clock,fetchResponse,{restUrl:'/test/',nonce:'test'},text=>text,(base,path)=>base+path,async value=>value);
 const controller = new AbortController();
 const waiting = helper({provider},controller.signal);
 assert.equal(timers.length,1);
 assert.equal(timers[0].delay,provider === 'site_media'?45000:8000);
 if(provider === 'site_media') {
  assert.ok(timers[0].delay>16000,'A valid 16-second Cloud search must remain reachable.');
  request.resolve({images:[{id:'existing-media'}]});
  assert.equal((await waiting).images.length,1);
 } else {
  timers[0].callback();
  await assert.rejects(waiting,error=>error.code==='npcink_toolbox_image_source_timeout');
 }
 const canceled = new AbortController();
 const canceling = helper({provider},canceled.signal);
 canceled.abort();
 await assert.rejects(canceling,error=>error.code==='npcink_toolbox_request_cancelled');
 const expiring = helper({provider},new AbortController().signal);
 timers.at(-1).callback();
 await assert.rejects(expiring,error=>error.code==='npcink_toolbox_image_source_timeout');
}
console.log('Image deadlines: semantic media query/evidence budget, external fast timeout, expiry and cancellation passed.');

// Exercise the real compact article-check report, including degradation and
// precise evidence disclosure. These tests do not call a model or write a post.
const extract = name => {
 const begin = source.indexOf(`\tfunction ${name}(`);
 assert.ok(begin >= 0);
 return source.slice(begin, source.indexOf('\n\tfunction ', begin + 10));
};
const sprintf = (template, ...values) => template.replace(/%(?:(\d+)\$)?[ds]/g, (_match, position) => String(position ? values[Number(position)-1] : values.shift()));
const walk = tree => Array.isArray(tree) ? tree.flatMap(walk) : !tree || typeof tree !== 'object' ? [] : [tree, ...tree.children.flatMap(walk)];
const visibleText = tree => Array.isArray(tree) ? tree.map(visibleText).join(' ') : tree && typeof tree === 'object' ? tree.type === 'details' ? visibleText(tree.children[0]) : tree.children.map(visibleText).join(' ') : String(tree ?? '');
const scopeReport = new Function('createElement','__','sprintf',extract('renderArticleCheckScope')+'\nreturn renderArticleCheckScope;')(createElement,text=>text,sprintf);
const localSection = {coverage:{paragraphs_checked:24,paragraphs_in_context:30,issues_shown:12,issues_found:20,partial:true,context_truncated:true}};
for (const state of ['completed','partial','failed','refused','unavailable','not_checked']) {
 const sections = {local_article_checkup:localSection,article_language_review:{status:state === 'completed' ? 'ready' : state,checks:Object.fromEntries(['language','logic','content_risk'].map(key=>[key,{status:state,reason_code:state === 'failed' ? 'timeout' : 'ok'}])),coverage:{blocks_checked:80,omitted:2},invalid_finding_count:1,result_limit_reached:true}};
 const tree = scopeReport(sections,{articleReview:{stale:true}});
 assert.match(visibleText(tree),/24 \/ 30/);
 assert.match(visibleText(tree),/部分完成/);
 assert.match(visibleText(tree),/没有提示不代表文章正确、安全或可以发布/);
 assert.match(visibleText(tree),/草稿已变化/);
 assert.ok(!visibleText(tree).includes('已隐藏'), 'Long counts are disclosed on demand.');
 const expected = {completed:'语句：完成',partial:'语句：部分完成',failed:'语句：失败（超时）',refused:'语句：未完成（拒绝）',unavailable:'语句：暂不可用',not_checked:'语句：未检查'}[state];
 assert.ok(visibleText(tree).includes(expected), `Top scope discloses ${state}.`);
 assert.equal(walk(tree).filter(node=>node.type === 'button').length,0);
}
assert.match(visibleText(scopeReport({},{})),/本地重复标点：未检查/);
let located = 0;
const languageReport = new Function('createElement','__','Button','locateArticleReviewIssue',extract('renderArticleLanguageReviewItems')+'\nreturn renderArticleLanguageReviewItems;')(createElement,text=>text,'button',()=>located++);
const evidenceQuote = '原文  空格\n换行 🧪';
const finding = {id:'f1',check:'logic',issue:'Two claims conflict.',edit_direction:'Verify the stated condition.',evidence_refs:[{quote:evidenceQuote,unique:true,client_id:'b1'},{quote:'另一段的原文。',unique:false}]};
for (const stale of [false,true]) {
 const tree = languageReport({items:[finding]},{articleReview:{stale,isCurrent:()=>!stale}});
 assert.match(visibleText(tree),/Two claims conflict/);
 assert.match(visibleText(tree),/Verify the stated condition/);
 assert.ok(!visibleText(tree).includes(evidenceQuote), 'Long evidence starts collapsed.');
 assert.equal(walk(tree).find(node=>node.type === 'blockquote').children[0],evidenceQuote,'Evidence whitespace and Unicode remain exact.');
 assert.equal(walk(tree).filter(node=>node.type === 'blockquote').length,2,'Both logic evidence quotes remain available.');
 const action = walk(tree).find(node=>node.type === 'button');
 assert.equal(action.props.disabled,stale);
 action.props.onClick();
}
assert.equal(located,1,'Only current and unique evidence can locate; no content write action exists.');
const allFlows = new Function('__',source.match(/const flows = (\[[\s\S]*?\n\t\]);/)[0]+'\nreturn flows;')(text=>text);
assert.deepEqual(allFlows.filter(flow=>flow.defaultVisible !== false).map(flow=>flow.intent),['format_content','publish_preflight','internal_links','image_candidates']);
console.log('Article check: four defaults, honest completion/degradation, concise issues, exact collapsed paired evidence and stale navigation passed.');
