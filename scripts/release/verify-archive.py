"""Verify an accepted ZIP against git archive without extracting either archive."""

import stat
import sys
import zipfile
from pathlib import PurePosixPath


def entries(archive):
    result = {}
    for entry in archive.infolist():
        name = entry.filename
        path = PurePosixPath(name)
        mode = entry.external_attr >> 16
        if (not name or name in result or name.startswith('/') or '\\' in name
                or ':' in name or '\x00' in name or '..' in path.parts
                or name.rstrip('/') != str(path) or entry.flag_bits & 1
                or stat.S_ISLNK(mode)
                or stat.S_IFMT(mode) not in (0, stat.S_IFREG, stat.S_IFDIR)):
            raise ValueError('Unsafe, duplicate or unsupported ZIP entry')
        result[name] = entry
    return result


def verify(expected_path, actual_path):
    with zipfile.ZipFile(expected_path) as expected, zipfile.ZipFile(actual_path) as actual:
        wanted, received = entries(expected), entries(actual)
        if wanted.keys() != received.keys():
            raise ValueError('Source archive has missing or unexpected paths')
        for name, original in wanted.items():
            candidate = received[name]
            if (original.file_size != candidate.file_size
                    or original.is_dir() != candidate.is_dir()
                    or (original.external_attr >> 16) != (candidate.external_attr >> 16)):
                raise ValueError('Source archive entry size or permissions differ')
            # Stream bounded, equally-sized entries. ZipFile also validates each CRC.
            with expected.open(original) as left, actual.open(candidate) as right:
                while True:
                    chunk = left.read(1024 * 1024)
                    if chunk != right.read(1024 * 1024):
                        raise ValueError('Source archive file contents differ')
                    if not chunk:
                        break


if __name__ == '__main__':
    if len(sys.argv) != 3:
        raise SystemExit('Usage: verify-archive.py EXPECTED.zip ACCEPTED.zip')
    try:
        verify(*sys.argv[1:])
    except (OSError, ValueError, RuntimeError, zipfile.BadZipFile) as error:
        raise SystemExit(str(error)) from error
    print('PASS source archive paths, permissions and file contents match Git')
