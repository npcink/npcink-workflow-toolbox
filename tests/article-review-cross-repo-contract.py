"""Optional offline bridge: real Toolbox -> Addon -> Cloud validator/finalizer -> Toolbox.

Uses only synthetic input and the existing PHP behavior-test stubs. No HTTP/model
call, WordPress site, credentials, or runtime evidence is implied. The consumer
loads the behavior harness and deliberately uses its array-parameter
WP_REST_Request stub; it does not instantiate the real WordPress REST class.
"""
import argparse
import json
from pathlib import Path
import subprocess
import sys

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--addon-root', type=Path, required=True)
parser.add_argument('--cloud-root', type=Path, required=True)
parser.add_argument('--php', default='php')
parser.add_argument('--extended', action='store_true', help='Include logic and risk offline bridge cases')
parser.add_argument('--browser', action='store_true', help='Read synthetic browser payload on stdin; return report JSON')
args = parser.parse_args()
root = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(args.cloud_root.resolve()))
from app.domain.article_review.contract import build_result, validate_request  # noqa: E402
from app.domain.runtime.models import RuntimeRequest  # noqa: E402

consumer_php = r'''
ob_start(); require 'tests/editor-content-support-behavior.php'; ob_end_clean();
$input=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$npcink_toolbox_article_review_response=$input['response'];
$r=new WP_REST_Request($input['payload']);
$method=new ReflectionMethod($editor_service,'editor_article_cloud_language_review');
$before=count($GLOBALS['npcink_toolbox_article_review_inputs']);
$section=$method->invoke($editor_service,$r);
$delta=count($GLOBALS['npcink_toolbox_article_review_inputs'])-$before;
$context=(new ReflectionMethod($editor_service,'editor_post_context'))->invoke($editor_service,$r);
$local=(new ReflectionMethod($editor_service,'editor_article_checkup_section'))->invoke($editor_service,$context);
$sections=$preflight->build_sections($context,array(),array(),$local);
echo json_encode(array('section'=>$section,'sections'=>$sections,'call_count_delta'=>$delta,'request'=>end($GLOBALS['npcink_toolbox_article_review_inputs'])),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_LINE_TERMINATORS);
'''
addon_php = r'''
require 'tests/helpers.php'; maca_load_addon_classes();
$r=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$p=Npcink_Cloud_Runtime_Request_Guards::normalize_toolbox_article_review_request($r);
if(is_wp_error($p)) { fwrite(STDERR,'Addon rejected bridge snapshot'); exit(1); }
echo json_encode($p,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_LINE_TERMINATORS);
'''

def php(code, payload, cwd):
    return json.loads(subprocess.check_output([args.php, '-d', 'error_reporting=22527', '-r', code],
        input=json.dumps(payload, ensure_ascii=False).encode(), cwd=cwd))


def bridge(payload):
    # The test facade captures the actual request made by the product consumer.
    empty = {'contract_version': 'article_review_cloud_result.v1', 'snapshot_sha256': '',
        'checks': {'language': {'status': 'completed', 'reason_code': 'none'},
        'logic': {'status': 'not_checked', 'reason_code': 'not_supported'},
        'content_risk': {'status': 'not_checked', 'reason_code': 'not_supported'}},
        'findings': [], 'invalid_finding_count': 0, 'result_limit_reached': False}
    captured = php(consumer_php, {'payload': payload, 'response': {'data': {'result': empty}}}, root)
    assert captured['call_count_delta'] == 1, 'Valid snapshot must dispatch exactly once'
    request = captured['request']
    assert [b['text'] for b in request['blocks']] == [b['text'] for b in payload['article_review_snapshot']['blocks']]
    runtime = php(addon_php, request, args.addon_root)
    assert 'contract_version' not in runtime['input'], 'Cloud input rejects outer envelope fields'
    runtime['site_id'] = 'site_synthetic_article_bridge'
    runtime['input_payload'] = runtime.pop('input')
    snapshot = validate_request(RuntimeRequest(**runtime))
    findings = []
    for block in snapshot.blocks:
        if '，，' in block.text:
            quote = block.text[block.text.index('，，'):] if args.browser else '，，'
            findings.append({'kind': 'punctuation', 'evidence': [
                {'block_ref': block.block_ref, 'quote': quote, 'occurrence': 0}],
                'reason': '重复标点可能有意，请结合上下文核对。',
                'review_direction': '请作者确认是否符合表达意图。'})
    if 'logic' in snapshot.checks:
        bodies = [b for b in snapshot.blocks if b.kind == 'body' and '总支出' in b.text]
        if len(bodies) >= 2:
            findings.append({'kind': 'numeric_consistency', 'scope': 'across_blocks',
                'evidence': [{'block_ref': b.block_ref, 'quote': b.text, 'occurrence': 0} for b in bodies[:2]],
                'reason': '合成离线协议用例：同一口径的数字需作者核对。', 'review_direction': '核对原始账目。'})
    if 'content_risk' in snapshot.checks:
        for b in snapshot.blocks:
            if '成人色情' in b.text:
                findings.append({'kind': 'sexual_context_review', 'context': 'commercial',
                    'evidence': [{'block_ref': b.block_ref, 'quote': b.text, 'occurrence': 0}],
                    'reason': '合成离线协议用例：成人内容推广需结合站点政策人工复核。',
                    'review_direction': '由作者判断语境和适用政策。'})
    raw = {'findings': findings[:12]}
    if snapshot.checks != ['language']:
        raw['checks'] = {c: {'status': 'completed', 'reason_code': 'none'} for c in snapshot.checks}
    result = build_result({'output_text': json.dumps(raw, ensure_ascii=False)}, runtime['input_payload'])
    projected = php(consumer_php, {'payload': payload, 'response': {'status': 'ok',
        'run_id': 'synthetic-offline-bridge', 'data': {'result': result}}}, root)
    section = projected['section']
    assert section['status'] in ('ready', 'partial')
    assert len(section['items']) == len(result['findings'])
    assert section['checks']['logic']['status'] == ('completed' if 'logic' in snapshot.checks else 'not_checked')
    assert section['direct_wordpress_write'] is False
    return section, projected['sections']

if args.browser:
    payload = json.load(sys.stdin)
    language, sections = bridge(payload)
    sections['article_language_review'] = language
    print(json.dumps({'artifact_type': 'editor_content_support_flow', 'intent': 'publish_preflight',
        'sections': sections}, ensure_ascii=False))
else:
    for text in ['😀原文，，  保留。\n<x>\u2028\u2029', '原文，，重复引文，，保留。', '正确原文。', '😀' * 800]:
        section, _ = bridge({'article_review_snapshot': {'blocks': [{'kind': 'body', 'text': text,
            'client_id': 'synthetic-block'}], 'coverage': {'eligible': 1}}})
        if text.count('，，') > 1:
            assert section['items'][0]['source_location']['status'] == 'unavailable'
    print('PASS: four synthetic Toolbox/Addon/Cloud round trips; exact Unicode, UTF-16, repeated quotes, zero findings and read-only projection.')

if args.extended:
    cases = [
        ['2025年甲项目总支出10万元。', '同年同一项目总支出12万元。'],
        ['成人色情影片付费点播。'],
    ]
    for texts in cases:
        section, _ = bridge({'article_review_snapshot': {'checks': ['language', 'logic', 'content_risk'],
            'blocks': [{'kind': 'body', 'client_id': f'synthetic-{i}', 'text': t} for i, t in enumerate(texts)],
            'coverage': {'eligible': len(texts)}}})
        assert section['items'] and section['items'][0]['check'] in ('logic', 'content_risk')
    print('PASS: extended logic/risk round trips keep category, two exact body locations and context.')
