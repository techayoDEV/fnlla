import importlib.util
from pathlib import Path
import stat
import tempfile
import unittest
import warnings
import zipfile

spec = importlib.util.spec_from_file_location(
    'verify_archive', Path(__file__).resolve().parents[2] / 'scripts/release/verify-archive.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class ArchiveAcceptanceTest(unittest.TestCase):
    def archive(self, path, entries, compression=zipfile.ZIP_STORED, mode=stat.S_IFREG | 0o644):
        with zipfile.ZipFile(path, 'w', compression=compression) as archive:
            for name, content in entries:
                info = zipfile.ZipInfo(name)
                info.external_attr = mode << 16
                info.compress_type = compression
                archive.writestr(info, content)

    def test_equal_contents_with_different_compression_are_accepted(self):
        with tempfile.TemporaryDirectory(prefix='fnlla-archive-test-') as root:
            expected, actual = Path(root) / 'expected.zip', Path(root) / 'actual.zip'
            files = [('VERSION', '2.2.0\n'), ('src/example.php', '<?php ' * 100)]
            self.archive(expected, files)
            self.archive(actual, list(reversed(files)), zipfile.ZIP_DEFLATED)
            self.assertNotEqual(expected.read_bytes(), actual.read_bytes())
            module.verify(expected, actual)

    def test_changed_missing_extra_duplicate_and_unsafe_entries_are_rejected(self):
        scenarios = [[], [('VERSION', '2.1.3\n')], [('VERSION', '2.2.0\n'), ('extra', '')],
                     [('VERSION', '2.2.0\n')] * 2, [('../VERSION', '')], [('/VERSION', '')],
                     [('C:/VERSION', '')], [('src\\VERSION', '')], [('src/./VERSION', '')]]
        with tempfile.TemporaryDirectory(prefix='fnlla-archive-test-') as root:
            expected, actual = Path(root) / 'expected.zip', Path(root) / 'actual.zip'
            self.archive(expected, [('VERSION', '2.2.0\n')])
            for files in scenarios:
                with self.subTest(files=files), warnings.catch_warnings():
                    warnings.simplefilter('ignore', UserWarning)
                    self.archive(actual, files)
                    with self.assertRaises(ValueError):
                        module.verify(expected, actual)
            for mode in [stat.S_IFLNK | 0o777, stat.S_IFREG | 0o755]:
                self.archive(actual, [('VERSION', '2.2.0\n')], mode=mode)
                with self.assertRaises(ValueError):
                    module.verify(expected, actual)


if __name__ == '__main__':
    unittest.main()
