"""Local review rejects stale, incomplete, untriaged and provider-failed evidence."""
import copy
import importlib.util
import io
import json
from pathlib import Path
import tempfile
from types import SimpleNamespace
import unittest
import urllib.error
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('local_qwen_review', ROOT/'scripts/local-qwen-review.py')
reviewer = importlib.util.module_from_spec(spec)
spec.loader.exec_module(reviewer)
FROZEN = dict(base_sha='a'*40, head_sha='b'*40, diff_sha256='c'*64)


class LocalReviewTests(unittest.TestCase):
    def setUp(self):
        self.receipt = dict(contract='local_qwen_code_review.v1', success=True, model='qwen3.8-max',
                            **FROZEN, review=dict(summary='Reviewed', findings=[dict(
                                id='F1', priority='P2', file='sample.py', problem='A real defect', fix='Correct it')]))

    def verify(self, receipt=None, body='## AI Review Triage\n- F1 accept: verified behavior\n'):
        with patch.object(reviewer, 'snapshot', return_value=(FROZEN, b'diff')):
            reviewer.verify(receipt or self.receipt, body, FROZEN['head_sha'])

    def test_exact_review_accepts_finding_triage(self):
        self.verify()
        self.verify(body='## AI Review Triage\n- [x] F1 fix: regression added\n## Risk\n')

    def test_deepseek_review_keeps_identity_completeness_and_triage_checks(self):
        receipt = copy.deepcopy(self.receipt)
        receipt['model'] = 'deepseek-v4.1-flash-1017'
        self.verify(receipt)
        for key, value in [('base_sha', 'd'*40), ('head_sha', 'd'*40),
                           ('diff_sha256', 'e'*64), ('success', False)]:
            with self.subTest(key=key), self.assertRaises(ValueError):
                self.verify(dict(receipt, **{key: value}))
        with self.assertRaises(ValueError):
            self.verify(receipt, body='## AI Review Triage\n')
        receipt['review']['findings'] = None
        with self.assertRaises(ValueError):
            self.verify(receipt)

    def test_configured_deepseek_request_generates_verifiable_receipt(self):
        response = json.dumps({'choices': [{'finish_reason': 'stop', 'message': {'content':
            json.dumps({'summary': 'Reviewed', 'findings': []})}}]}).encode()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory); config = root/'config.json'; output = root/'review.json'
            config.write_text(json.dumps(dict(provider='qwen-shared', custom_providers={
                'qwen-shared': dict(model='deepseek-v4.1-flash-1017',
                                    url='https://example.invalid/v1', auth_token='not-a-real-token')})))
            with patch.object(reviewer, 'snapshot', return_value=(FROZEN, b'diff')), patch.object(
                    reviewer, 'git', return_value=b''), patch.object(reviewer.urllib.request,
                    'urlopen', return_value=io.BytesIO(response)) as request:
                reviewer.run('base', 'head', output, config)
                sent = json.loads(request.call_args.args[0].data)
                self.assertEqual(sent['model'], 'deepseek-v4.1-flash-1017')
                request.assert_called_once()
                self.assertEqual(request.call_args.args[0].get_header('User-agent'), 'Npcink-Local-Review/1.0')
                receipt = json.loads(output.read_text())
                self.assertEqual(receipt['model'], sent['model'])
                reviewer.verify(receipt, '', FROZEN['head_sha'])
            self.assertFalse(Path(str(output)+'.failure.json').exists())
            self.assertNotIn('not-a-real-token', output.read_text())

    def test_authorized_gpt_review_keeps_identity_completeness_and_triage_checks(self):
        receipt = copy.deepcopy(self.receipt)
        receipt['model'] = 'gpt-6.1-sol'
        self.verify(receipt)
        for key, value in [('base_sha', 'd'*40), ('head_sha', 'd'*40),
                           ('diff_sha256', 'e'*64), ('success', False)]:
            with self.subTest(key=key), self.assertRaises(ValueError):
                self.verify(dict(receipt, **{key: value}))
        with self.assertRaises(ValueError):
            self.verify(receipt, body='## AI Review Triage\n')
        receipt['review']['findings'] = None
        with self.assertRaises(ValueError):
            self.verify(receipt)

    def test_configured_authorized_gpt_request_generates_verifiable_receipt(self):
        response = json.dumps({'choices': [{'finish_reason': 'stop', 'message': {'content':
            json.dumps({'summary': 'Reviewed', 'findings': []})}}]}).encode()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory); config = root/'config.json'; output = root/'review.json'
            config.write_text(json.dumps(dict(provider='qwen-shared', custom_providers={
                'qwen-shared': dict(model='gpt-6.1-sol',
                                    url='https://example.invalid/v1', auth_token='not-a-real-token')})))
            with patch.object(reviewer, 'snapshot', return_value=(FROZEN, b'diff')), patch.object(
                    reviewer, 'git', return_value=b''), patch.object(reviewer.urllib.request,
                    'urlopen', return_value=io.BytesIO(response)) as request:
                reviewer.run('base', 'head', output, config)
                sent = json.loads(request.call_args.args[0].data)
                self.assertEqual(sent['model'], 'gpt-6.1-sol')
                request.assert_called_once()
                self.assertEqual(request.call_args.args[0].get_header('User-agent'), 'Npcink-Local-Review/1.0')
                receipt = json.loads(output.read_text())
                self.assertEqual(receipt['model'], sent['model'])
                reviewer.verify(receipt, '', FROZEN['head_sha'])
            self.assertFalse(Path(str(output)+'.failure.json').exists())
            self.assertNotIn('not-a-real-token', output.read_text())

    def test_unsupported_model_rejected_before_provider_call(self):
        for model in ['other-model', '', None, 123, 'deepseek\nother', ' deepseek-v4', 'gpt-other', 'gpt-6.1-sol-other', ' gpt-6.1-sol', 'gpt-6.1-sol\nother']:
            with self.subTest(model=model), tempfile.TemporaryDirectory() as directory:
                root = Path(directory); config = root/'config.json'; output = root/'review.json'
                config.write_text(json.dumps(dict(model=model, url='https://example.invalid/v1',
                                                 auth_token='not-a-real-token')))
                with patch.object(reviewer, 'snapshot', return_value=(FROZEN, b'diff')), patch.object(
                        reviewer, 'git', return_value=b''), patch.object(
                        reviewer.urllib.request, 'urlopen') as request:
                    with self.assertRaises(ValueError):
                        reviewer.run('base', 'head', output, config)
                    request.assert_not_called()
                with self.assertRaises(ValueError):
                    self.verify(dict(self.receipt, model=model))
                self.assertFalse(output.exists())

    def test_wrong_head_diff_model_and_failed_review_rejected(self):
        for key, value in [('head_sha','d'*40), ('diff_sha256','e'*64), ('model','other'), ('success',False)]:
            receipt = dict(self.receipt, **{key:value})
            with self.subTest(key=key), self.assertRaises(ValueError): self.verify(receipt)

    def test_triage_elsewhere_or_empty_reason_rejected(self):
        for body in ['- F1 accept: elsewhere', '## AI Review Triage\n## Risk\n- F1 fix: elsewhere',
                     '## AI Review Triage\n- F1 accept:  \n']:
            with self.subTest(body=body), self.assertRaises(ValueError):self.verify(body=body)

    def test_empty_findings_is_complete_but_missing_or_duplicate_is_not(self):
        receipt = copy.deepcopy(self.receipt);receipt['review']['findings']=[];self.verify(receipt)
        for findings in [None,{},[self.receipt['review']['findings'][0]]*2]:
            receipt['review']['findings']=findings
            with self.assertRaises(ValueError): self.verify(receipt)

    def test_invalid_finding_reports_a_safe_contract_path(self):
        for finding, code, path in [
                ({'id': 'F1', 'priority': 'P2', 'file': 'x', 'problem': 'x', 'fix': 'x'}, 'finding_id_duplicate', 'findings[1].id'),
                ({'id': 'bad', 'priority': 'P2', 'file': 'x', 'problem': 'x', 'fix': 'x'}, 'finding_id_invalid', 'findings[0].id'),
                ({'id': 'F1', 'priority': 'P9', 'file': 'x', 'problem': 'x', 'fix': 'x'}, 'finding_priority_invalid', 'findings[0].priority'),
                ({'id': 'F1', 'priority': 'P2', 'file': '', 'problem': 'x', 'fix': 'x'}, 'finding_field_missing', 'findings[0].file'),
        ]:
            receipt = copy.deepcopy(self.receipt)
            receipt['review']['findings'] = [self.receipt['review']['findings'][0], finding] if code == 'finding_id_duplicate' else [finding]
            with self.subTest(code=code), self.assertRaises(reviewer.ReviewValidationError) as raised:
                reviewer.validate_review(receipt['review'])
            self.assertEqual(raised.exception.code, code)
            self.assertEqual(raised.exception.path, path)

    def test_provider_error_does_not_write_pass_receipt_or_leak_body(self):
        with tempfile.TemporaryDirectory() as directory:
            root=Path(directory);config=root/'config.json';output=root/'review.json';failure=Path(str(output)+'.failure.json')
            config.write_text(json.dumps(dict(provider='qwen',custom_providers=dict(qwen=dict(
                model='qwen3.8-max',url='https://example.invalid/v1',auth_token='not-a-real-token')))))
            with patch.object(reviewer,'snapshot',return_value=(FROZEN,b'diff')), patch.object(
                reviewer,'git',return_value=b''), patch.object(reviewer.urllib.request,'urlopen',side_effect=
                    urllib.error.HTTPError('https://example.invalid',400,'secret-provider-body',{},None)):
                with self.assertRaisesRegex(ValueError,'AI HTTP 400; no review delivered') as error:
                    reviewer.run('base','head',output,config)
            self.assertNotIn('secret',str(error.exception));self.assertFalse(output.exists())
            receipt=json.loads(failure.read_text())
            self.assertEqual(receipt['failure']['code'], 'provider_http_error')
            self.assertEqual(receipt['failure']['provider_status'], 400)
            self.assertNotIn('secret', failure.read_text())

    def test_invalid_provider_findings_write_only_safe_failure_metadata(self):
        response = json.dumps({'choices': [{'finish_reason': 'stop', 'message': {'content': json.dumps({
            'summary': 'Reviewed', 'findings': [{'id': 'F1', 'priority': 'P9', 'file': 'x', 'problem': 'x', 'fix': 'x'}]})}}]}).encode()
        with tempfile.TemporaryDirectory() as directory:
            root=Path(directory);config=root/'config.json';output=root/'review.json';failure=Path(str(output)+'.failure.json')
            config.write_text(json.dumps(dict(model='qwen3.8-max',url='https://example.invalid/v1',auth_token='not-a-real-token')))
            with patch.object(reviewer,'snapshot',return_value=(FROZEN,b'diff')), patch.object(
                    reviewer,'git',return_value=b''), patch.object(reviewer.subprocess,'run',return_value=SimpleNamespace(returncode=0)), patch.object(
                    reviewer.urllib.request,'urlopen',return_value=io.BytesIO(response)):
                with self.assertRaisesRegex(ValueError,'finding priority is invalid'):
                    reviewer.run('base','head',output,config)
            self.assertFalse(output.exists())
            receipt=json.loads(failure.read_text())
            self.assertEqual(receipt['failure']['code'], 'finding_priority_invalid')
            self.assertEqual(receipt['failure']['path'], 'findings[0].priority')
            self.assertEqual(receipt['response']['bytes'], len(response))
            self.assertEqual(receipt['response']['sha256'], __import__('hashlib').sha256(response).hexdigest())
            self.assertNotIn('P9', failure.read_text())

    def test_transport_error_writes_safe_failure_metadata(self):
        with tempfile.TemporaryDirectory() as directory:
            root=Path(directory);config=root/'config.json';output=root/'review.json';failure=Path(str(output)+'.failure.json')
            config.write_text(json.dumps(dict(model='qwen3.8-max',url='https://example.invalid/v1',auth_token='not-a-real-token')))
            with patch.object(reviewer,'snapshot',return_value=(FROZEN,b'diff')), patch.object(
                    reviewer,'git',return_value=b''), patch.object(reviewer.subprocess,'run',return_value=SimpleNamespace(returncode=0)), patch.object(
                    reviewer.urllib.request,'urlopen',side_effect=ConnectionResetError('provider endpoint detail')):
                with self.assertRaisesRegex(ValueError,'AI transport failed; no review delivered') as error:
                    reviewer.run('base','head',output,config)
            self.assertNotIn('provider endpoint detail', str(error.exception))
            receipt=json.loads(failure.read_text())
            self.assertEqual(receipt['failure']['code'], 'provider_transport_error')
            self.assertIsNone(receipt['response'])

    def test_config_json_error_is_not_mislabeled_as_provider_response(self):
        with tempfile.TemporaryDirectory() as directory:
            root=Path(directory);config=root/'config.json';output=root/'review.json'
            config.write_text('{not-json')
            with patch.object(reviewer,'snapshot',return_value=(FROZEN,b'diff')), patch.object(reviewer,'git',return_value=b''):
                with self.assertRaisesRegex(ValueError,'local AI config is not valid JSON'):
                    reviewer.run('base','head',output,config)
            self.assertFalse(Path(str(output)+'.failure.json').exists())

    def test_timeout_writes_a_distinct_failure_code(self):
        with tempfile.TemporaryDirectory() as directory:
            root=Path(directory);config=root/'config.json';output=root/'review.json';failure=Path(str(output)+'.failure.json')
            config.write_text(json.dumps(dict(model='qwen3.8-max',url='https://example.invalid/v1',auth_token='not-a-real-token')))
            with patch.object(reviewer,'snapshot',return_value=(FROZEN,b'diff')), patch.object(
                    reviewer,'git',return_value=b''), patch.object(reviewer.subprocess,'run',return_value=SimpleNamespace(returncode=0)), patch.object(
                    reviewer.urllib.request,'urlopen',side_effect=TimeoutError('timeout detail')):
                with self.assertRaisesRegex(ValueError,'AI request timed out; no review delivered'):
                    reviewer.run('base','head',output,config)
            receipt=json.loads(failure.read_text())
            self.assertEqual(receipt['failure']['code'], 'provider_timeout')
            self.assertNotIn('timeout detail', failure.read_text())

    def test_oversized_response_records_only_the_consumed_prefix(self):
        response = b'x' * 2_000_001
        with tempfile.TemporaryDirectory() as directory:
            root=Path(directory);config=root/'config.json';output=root/'review.json';failure=Path(str(output)+'.failure.json')
            config.write_text(json.dumps(dict(model='qwen3.8-max',url='https://example.invalid/v1',auth_token='not-a-real-token')))
            with patch.object(reviewer,'snapshot',return_value=(FROZEN,b'diff')), patch.object(
                    reviewer,'git',return_value=b''), patch.object(reviewer.subprocess,'run',return_value=SimpleNamespace(returncode=0)), patch.object(
                    reviewer.urllib.request,'urlopen',return_value=io.BytesIO(response)):
                with self.assertRaisesRegex(ValueError,'AI response exceeds 2000000 bytes'):
                    reviewer.run('base','head',output,config)
            receipt=json.loads(failure.read_text())
            self.assertEqual(receipt['failure']['code'], 'response_oversized')
            self.assertEqual(receipt['response']['bytes'], 2_000_000)
            self.assertFalse(receipt['response']['complete'])
            self.assertEqual(receipt['response']['hash_bytes'], 4096)
            self.assertFalse(receipt['response']['hash_complete'])
            self.assertEqual(receipt['response']['sha256'], __import__('hashlib').sha256(response[:4096]).hexdigest())

    def test_existing_failure_receipt_gets_a_unique_sidecar(self):
        with tempfile.TemporaryDirectory() as directory:
            output=Path(directory)/'review.json';first=Path(str(output)+'.failure.json')
            first.write_text('previous')
            created=reviewer.write_failure_receipt(output, FROZEN, 'qwen3.8-max',
                                                   'provider_transport_error', 'provider',
                                                   'provider connection failed')
            self.assertEqual(created, Path(str(first)+'.1'))
            self.assertEqual(first.read_text(), 'previous')

    def test_incomplete_response_has_a_distinct_failure_code(self):
        response = json.dumps({'choices': [{'finish_reason': 'length', 'message': {'content': '{}'}}]}).encode()
        with tempfile.TemporaryDirectory() as directory:
            root=Path(directory);config=root/'config.json';output=root/'review.json';failure=Path(str(output)+'.failure.json')
            config.write_text(json.dumps(dict(model='qwen3.8-max',url='https://example.invalid/v1',auth_token='not-a-real-token')))
            with patch.object(reviewer,'snapshot',return_value=(FROZEN,b'diff')), patch.object(
                    reviewer,'git',return_value=b''), patch.object(reviewer.subprocess,'run',return_value=SimpleNamespace(returncode=0)), patch.object(
                    reviewer.urllib.request,'urlopen',return_value=io.BytesIO(response)):
                with self.assertRaisesRegex(ValueError,'incomplete AI response'):
                    reviewer.run('base','head',output,config)
            receipt=json.loads(failure.read_text())
            self.assertEqual(receipt['failure']['code'], 'response_incomplete')
            self.assertNotIn('provider_status', receipt['failure'])

    def test_existing_output_not_overwritten(self):
        with tempfile.TemporaryDirectory() as directory:
            output=Path(directory)/'review.json';output.write_text('preserve')
            with self.assertRaisesRegex(ValueError,'new output required'):
                reviewer.run('base','head',output,Path('unused'))
            self.assertEqual(output.read_text(),'preserve')

    def test_binary_context_is_bounded_and_receipt_binds_every_byte(self):
        import os
        import subprocess
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            def actual_git(*args):
                return subprocess.check_output(['git', *args], cwd=root)
            actual_git('init', '-q')
            actual_git('config', 'user.email', 'test@example.invalid')
            actual_git('config', 'user.name', 'Test')
            (root/'asset.png').write_bytes(b'\0' + os.urandom(300000))
            (root/'source.py').write_text('before = 1\n')
            actual_git('add', 'asset.png', 'source.py')
            actual_git('commit', '-qm', 'base')
            base = actual_git('rev-parse', 'HEAD').decode().strip()
            (root/'asset.png').write_bytes(b'\0' + os.urandom(300000))
            (root/'source.py').write_text('after = 2\n')
            actual_git('add', 'asset.png', 'source.py')
            actual_git('commit', '-qm', 'head')
            with patch.object(reviewer, 'git', side_effect=actual_git):
                frozen, diff = reviewer.snapshot(base, 'HEAD')
                context, binaries = reviewer.review_context(frozen)
                self.assertGreater(len(diff), 220000)
                self.assertLess(len(context), 2000)
                self.assertNotIn('GIT binary patch', context)
                self.assertIn('-before = 1', context)
                self.assertIn('+after = 2', context)
                self.assertIn('BINARY CONTENT NOT REVIEWED', context)
                self.assertEqual(binaries, ['asset.png'])
                self.assertIn(actual_git('rev-parse', 'HEAD:asset.png').decode().strip(), context)
                receipt = dict(contract='local_qwen_code_review.v1', success=True,
                               model='qwen3.8-max', **frozen,
                               review=dict(summary='Reviewed text; binary separate', findings=[]))
                reviewer.verify(receipt, '', frozen['head_sha'])
                (root/'asset.png').write_bytes(b'changed\0')
                actual_git('add', 'asset.png'); actual_git('commit', '-qm', 'changed binary')
                changed, _ = reviewer.snapshot(base, 'HEAD')
                self.assertNotEqual(changed['diff_sha256'], frozen['diff_sha256'])
                with self.assertRaises(ValueError):
                    reviewer.verify(receipt, '', changed['head_sha'])
                actual_git('rm', 'asset.png'); actual_git('commit', '-qm', 'delete binary')
                deleted, _ = reviewer.snapshot(base, 'HEAD')
                deleted_context, deleted_binaries = reviewer.review_context(deleted)
                self.assertEqual(deleted_binaries, ['asset.png'])
                self.assertIn('deleted file mode', deleted_context)

    def test_text_budget_still_blocks_without_provider_call(self):
        with tempfile.TemporaryDirectory() as directory, patch.object(
                reviewer, 'snapshot', return_value=(FROZEN, b'diff')), patch.object(
                reviewer, 'git', return_value=b''), patch.object(
                reviewer, 'review_context', return_value=('x'*220001, [])), patch.object(
                reviewer.urllib.request, 'urlopen') as request:
            with self.assertRaisesRegex(ValueError, 'review exceeds text budget'):
                reviewer.run('base', 'head', Path(directory)/'review.json', Path('unused'))
            request.assert_not_called()


if __name__=='__main__':unittest.main()
