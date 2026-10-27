"""Local review rejects stale, incomplete, untriaged and provider-failed evidence."""
import copy
import importlib.util
import json
from pathlib import Path
import tempfile
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

    def test_provider_error_does_not_write_pass_receipt_or_leak_body(self):
        with tempfile.TemporaryDirectory() as directory:
            root=Path(directory);config=root/'config.json';output=root/'review.json'
            config.write_text(json.dumps(dict(provider='qwen',custom_providers=dict(qwen=dict(
                model='qwen3.8-max',url='https://example.invalid/v1',auth_token='not-a-real-token')))))
            with patch.object(reviewer,'snapshot',return_value=(FROZEN,b'diff')), patch.object(
                reviewer,'git',return_value=b''), patch.object(reviewer.urllib.request,'urlopen',side_effect=
                    urllib.error.HTTPError('https://example.invalid',400,'secret-provider-body',{},None)):
                with self.assertRaisesRegex(ValueError,'Qwen HTTP 400; no review delivered') as error:
                    reviewer.run('base','head',output,config)
            self.assertNotIn('secret',str(error.exception));self.assertFalse(output.exists())

    def test_existing_output_not_overwritten(self):
        with tempfile.TemporaryDirectory() as directory:
            output=Path(directory)/'review.json';output.write_text('preserve')
            with self.assertRaisesRegex(ValueError,'new output required'):
                reviewer.run('base','head',output,Path('unused'))
            self.assertEqual(output.read_text(),'preserve')


if __name__=='__main__':unittest.main()
