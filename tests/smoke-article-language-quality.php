<?php
/** Development-only WP-CLI evaluation. No posts, options, plugin mounts or provider settings change. */
if (!defined('WP_CLI') || !WP_CLI) throw new RuntimeException('WP-CLI only');
$fixture=getenv('NPCINK_ARTICLE_QUALITY_CASES') ?: dirname(__DIR__) . '/docs/article-language-quality-cases-v1.json';
$output=getenv('NPCINK_ARTICLE_QUALITY_OUTPUT');
$selected=array_filter(explode(',',getenv('NPCINK_ARTICLE_QUALITY_IDS')?:''));
if (!$fixture || !$output || file_exists($output)) throw new RuntimeException('New output and frozen fixture required');
$source=json_decode(file_get_contents($fixture),true,512,JSON_THROW_ON_ERROR);
if (($source['contract']??'')!=='article_language_quality_cases.v1'||($source['source_policy']??'')!=='synthetic_no_customer_content'||($source['checks']??null)!==array('language')||count($source['cases'])>64) throw new RuntimeException('Bounded synthetic fixture required');
$ids=array_column($source['cases'],'id');
if (count($ids)!==count(array_unique($ids)) || array_diff($selected,$ids)) throw new RuntimeException('Unique known case ids required');
$requests=array();
add_filter('wp_insert_post_data',static function(){throw new RuntimeException('Evaluation forbids post writes');},PHP_INT_MAX);
add_action('pre_post_update',static function(){throw new RuntimeException('Evaluation forbids post updates');},PHP_INT_MAX);
add_filter('pre_http_request',static function($pre,$args,$url)use(&$requests){
    $parts=wp_parse_url($url);$body=json_decode($args['body']??'',true);
    if (!in_array($parts['host']??'',array('127.0.0.1','localhost'),true)||($parts['port']??0)!==18010||($parts['path']??'')!=='/v1/runtime/execute'||($body['ability_name']??'')!=='npcink-toolbox/review-article') return new WP_Error('evaluation_http_blocked','Only existing loopback article runtime is allowed');
    $requests[]=array('signed'=>!empty($args['headers']['X-Npcink-Signature']),'no_store'=>($body['storage_mode']??'')==='no_store','retry_max'=>$body['retry_max']??null,'checks'=>$body['input']['checks']??null);
    return $pre;
},PHP_INT_MAX,3);
$service=new Npcink_Toolbox\Rest_Editor_Content_Support(new Npcink_Toolbox\Provider_Client(new Npcink_Toolbox\Settings()),new Npcink_Toolbox\Publish_Preflight_Service());
$method=new ReflectionMethod($service,'editor_article_cloud_language_review');$method->setAccessible(true);
$results=array();
foreach($source['cases'] as $case){
    if($selected&&!in_array($case['id'],$selected,true))continue;
    $blocks=array_map(static fn($b)=>array('kind'=>$b['kind'],'text'=>$b['text'],'client_id'=>'synthetic-'.$b['block_ref']),$case['blocks']);
    $request=new WP_REST_Request('POST','/npcink-toolbox/v1/editor/content-support');
    $request->set_param('article_review_snapshot',array('blocks'=>$blocks,'coverage'=>array('eligible'=>count($blocks),'excluded'=>0,'omitted'=>0,'truncated'=>0)));
    $before=count($requests);$started=microtime(true);$report=$method->invoke($service,$request);
    $types=array_column($report['items'],'type');
    $usable=in_array($report['status'],array('ready','partial'),true);
    $hit=$usable&&($case['expectation']==='hint'?(bool)array_intersect($case['expected_kinds'],$types):count($types)===0);
    $row=array('case_id'=>$case['id'],'group'=>$case['group'],'expectation'=>$case['expectation'],'calls'=>count($requests)-$before,'elapsed_seconds'=>round(microtime(true)-$started,3),'expected_outcome'=>$hit,'report'=>$report);
    $results[]=$row;
    file_put_contents($output,wp_json_encode(array('contract'=>'article_language_quality_run.v1','fixture_sha256'=>hash_file('sha256',$fixture),'toolbox_root'=>NPCINK_TOOLBOX_DIR,'results'=>$results,'requests'=>$requests,'wordpress_source_writes'=>0,'no_automatic_retry'=>true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n",LOCK_EX);
    chmod($output,0600);
    echo wp_json_encode(array('case_id'=>$case['id'],'status'=>$report['status'],'run_id'=>$report['run_id'],'findings'=>count($types),'expected_outcome'=>$hit))."\n";flush();
}
