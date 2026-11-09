#!/usr/bin/env python3
"""Verify the runtime-only distribution directory or final ZIP without extraction."""
import argparse
from pathlib import Path, PurePosixPath
import stat
import zipfile

SLUG = 'npcink-workflow-toolbox'
ROOT_FILES = {SLUG + '.php', 'readme.txt', 'uninstall.php'}
SUFFIXES = {'assets': {'.js', '.css'}, 'includes': {'.php'},
            'languages': {'.json', '.po', '.mo', '.pot'}, 'modules': {'.php'}}


def check_entry(name, is_directory=False, is_symlink=False):
    path = PurePosixPath(name)
    if (not name or path.is_absolute() or '\\' in name
            or any(part in {'', '.', '..'} or part.startswith('.') for part in name.rstrip('/').split('/'))
            or is_symlink):
        raise ValueError('Unsafe package path: ' + name)
    parts = path.parts
    if len(parts) == 1 and name in ROOT_FILES and not is_directory:
        return
    allowed = parts[0] in SUFFIXES
    if parts[0] == 'modules':
        allowed = parts == ('modules', 'local-automation-runtime', 'module.php') or parts[:3] == ('modules', 'local-automation-runtime', 'src') or (
            is_directory and parts in [('modules',), ('modules', 'local-automation-runtime')])
    if not allowed or (not is_directory and path.suffix not in SUFFIXES[parts[0]]):
        raise ValueError('Non-runtime package path: ' + name)


def verify(target):
    names = set()
    if target.is_dir():
        for path in target.rglob('*'):
            name = path.relative_to(target).as_posix()
            check_entry(name, path.is_dir(), path.is_symlink())
            if path.is_file():
                names.add(name)
    else:
        with zipfile.ZipFile(target) as archive:
            seen = set()
            for entry in archive.infolist():
                if entry.filename in seen:
                    raise ValueError('Duplicate ZIP entry: ' + entry.filename)
                seen.add(entry.filename)
                if entry.filename == SLUG + '/' and entry.is_dir():
                    continue
                if not entry.filename.startswith(SLUG + '/'):
                    raise ValueError('Unexpected ZIP root: ' + entry.filename)
                name = entry.filename[len(SLUG) + 1:]
                check_entry(name, entry.is_dir(), stat.S_ISLNK(entry.external_attr >> 16))
                if not entry.is_dir():
                    names.add(name)
            if archive.testzip() is not None:
                raise ValueError('Corrupt ZIP member')
    if not ROOT_FILES.issubset(names):
        raise ValueError('Missing required plugin root files')
    return len(names)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('target', type=Path)
    args = parser.parse_args()
    try:
        count = verify(args.target)
    except (ValueError, OSError, zipfile.BadZipFile) as error:
        parser.exit(1, 'Release package rejected: ' + str(error) + '\n')
    print(f'Release package runtime-only inventory passed: {count} files.')
