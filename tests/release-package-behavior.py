"""Exercise the actual distribution copy and final ZIP guard with dirty inputs."""
import importlib.util
from pathlib import Path
import subprocess
import tempfile
import zipfile

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('release_package', ROOT / 'scripts/check-release-package.py')
package = importlib.util.module_from_spec(spec)
spec.loader.exec_module(package)

with tempfile.TemporaryDirectory(prefix='toolbox-release-test-') as temp:
    source = Path(temp) / 'source'
    target = Path(temp) / package.SLUG
    source.mkdir()
    for name in package.ROOT_FILES:
        (source / name).write_text('fixture')
    (source / 'assets').mkdir()
    (source / 'assets/editor.js').write_text('/* runtime */')
    for name in ['output/playwright/article.txt', 'artifacts/trace.json', '.local-evidence/receipt.json',
                 'includes/debug.log', '.env', '.env.production', 'assets/browser.zip']:
        path = source / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text('development fixture must not ship')
    subprocess.run(['rsync', '-a', '--exclude-from=' + str(ROOT / '.distignore'),
                    str(source) + '/', str(target) + '/'], check=True)
    assert package.verify(target) == 4
    archive = Path(temp) / 'plugin.zip'
    with zipfile.ZipFile(archive, 'w') as output:
        for path in target.rglob('*'):
            if path.is_file():
                output.write(path, package.SLUG + '/' + path.relative_to(target).as_posix())
    assert package.verify(archive) == 4
    for name in ['output/article.png', 'assets/debug.log', 'includes/secret.json',
                 'modules/local-automation-runtime/tests/fixture.php', 'unexpected.php']:
        path = target / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text('must reject')
        try:
            package.verify(target)
        except ValueError:
            pass
        else:
            raise AssertionError('Accepted non-runtime file: ' + name)
        path.unlink()
        # Remove empty development directories too; they are not distribution roots.
        while path.parent != target and not any(path.parent.iterdir()):
            path.parent.rmdir()
            path = path.parent
    (target / 'includes').mkdir(exist_ok=True)
    (target / 'includes/linked.php').symlink_to(source / next(iter(package.ROOT_FILES)))
    try:
        package.verify(target)
    except ValueError:
        pass
    else:
        raise AssertionError('Accepted symlink')
    for member in ['../escape.php', package.SLUG + '/includes/../secret.php',
                   package.SLUG + '/output/trace.json', package.SLUG + '/.env']:
        with zipfile.ZipFile(archive, 'w') as output:
            for name in package.ROOT_FILES:
                output.writestr(package.SLUG + '/' + name, 'required runtime file')
            output.writestr(member, 'must reject')
        try:
            package.verify(archive)
        except ValueError:
            pass
        else:
            raise AssertionError('Accepted unsafe ZIP member: ' + member)
print('Release packaging: dirty-tree exclusions, runtime files, symlinks and final ZIP rejection passed.')
