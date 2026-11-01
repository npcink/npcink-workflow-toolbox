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
                with self.assertRaisesRegex(ValueError,'Qwen HTTP 400; no review delivered') as error:
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
                with self.assertRaisesRegex(ValueError,'Qwen transport failed; no review delivered') as error:
                    reviewer.run('base','head',output,config)
            self.assertNotIn('provider endpoint detail', str(error.exception))
            receipt=json.loads(failure.read_text())
            self.assertEqual(receipt['failure']['code'], 'provider_transport_error')
            self.assertIsNone(receipt['response'])

    def test_existing_output_not_overwritten(self):
        with tempfile.TemporaryDirectory() as directory:
            output=Path(directory)/'review.json';output.write_text('preserve')
            with self.assertRaisesRegex(ValueError,'new output required'):
                reviewer.run('base','head',output,Path('unused'))
            self.assertEqual(output.read_text(),'preserve')

    def test_binary_translation_keeps_full_diff_without_utf8_snapshot(self):
        diff = b'Binary translation change\nGIT binary patch\nliteral 4\n'
        response = json.dumps({'choices': [{'finish_reason': 'stop', 'message': {
            'content': json.dumps({'summary': 'Reviewed', 'findings': []})}}]}).encode()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory); config = root/'config.json'; output = root/'review.json'
            config.write_text(json.dumps(dict(model='qwen3.8-max',
                url='https://example.invalid/v1', auth_token='not-a-real-token')))
            def git_result(*args):
                return b'languages/test.mo\0' if args[0] == 'diff' else b'\xde\x12\x04\x95'
            with patch.object(reviewer, 'snapshot', return_value=(FROZEN, diff)), patch.object(
                    reviewer, 'git', side_effect=git_result) as git_mock, patch.object(
                    reviewer.subprocess, 'run', return_value=SimpleNamespace(returncode=0)), patch.object(
                    reviewer.urllib.request, 'urlopen', return_value=io.BytesIO(response)) as request:
                reviewer.run('base', 'head', output, config)
            self.assertFalse(any(call.args[0] == 'show' for call in git_mock.call_args_list))
            self.assertIn(diff.decode(), json.loads(request.call_args.args[0].data)['messages'][1]['content'])
            self.assertTrue(json.loads(output.read_text())['success'])


if __name__=='__main__':unittest.main()
