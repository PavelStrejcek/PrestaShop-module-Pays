"""Check the installable ZIP and production exclusions using a disposable repo."""
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest
import zipfile


SCRIPT = Path(__file__).resolve().parents[1] / 'scripts' / 'build-package.sh'


class PackageTest(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.root = Path(self.directory.name) / 'source with spaces'
        self.root.mkdir()
        self.production = {
            'pays_ps.php': "<?php $this->version = '2.0.1';\n",
            'index.php': '<?php\n',
            'logo.png': 'logo',
            'settings-template.php': '<?php // defaults\n',
            'LICENSE': 'license',
            'changelog.txt': 'changes',
            'controllers/front/confirm.php': '<?php // callback\n',
            'model/Callback.php': '<?php // model\n',
            'install/db_structure.sql': 'CREATE TABLE example;',
            'uninstall/uninstall.sql': 'DROP TABLE example;',
            'upgrade/upgrade-2.0.1.php': '<?php // upgrade\n',
            'upgrade/sql/upgrade-2.0.0.sql': 'ALTER TABLE example;',
            'translations/cs.php': '<?php // translation\n',
            'views/templates/hook/admin_order.tpl': '<table></table>',
            'views/js/front.js': '// frontend',
            'views/css/front.css': 'body {}',
            'views/img/payment.png': 'image',
        }
        self.docker = {
            'docker/compose.yaml': 'services:\n',
            'docker/dev.sh': '#!/usr/bin/env bash\n',
            'docker/index.php': '<?php\n',
            'docker/.htaccess': 'Require all denied\n',
        }
        development = {
            'README.md': 'development documentation',
            '.gitattributes': '/tests export-ignore\n/scripts export-ignore\n/docker/.env export-ignore\n',
            '.gitignore': 'dist/\nsettings.php\n.env\ndocker/.env\n',
            '.github/workflows/tests.yml': 'CI',
            '.gitlab-ci.yml': 'CI',
            'tests/unit.php': '<?php // test\n',
            'doc/old-manual.odt': 'old manual',
            'scripts/another-tool.sh': '#!/bin/sh\n',
        }
        for name, content in {**self.production, **self.docker, **development}.items():
            path = self.root / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text(content)
        shutil.copyfile(SCRIPT, self.root / 'scripts/build-package.sh')
        self.git('init', '-q')
        self.git('add', '.')
        self.git('-c', 'user.name=Package Test', '-c', 'user.email=test@example.invalid',
                 '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=/dev/null',
                 'commit', '-qm', 'fixture')

    def git(self, *args):
        return subprocess.run(['git', '-C', str(self.root), *args], check=True,
                              text=True, capture_output=True)

    def build(self, *args):
        # Run from outside the repository to verify path handling.
        return subprocess.run(['bash', str(self.root / 'scripts/build-package.sh'), *args],
                              cwd=self.directory.name, text=True, capture_output=True,
                              timeout=30)

    def test_zip_has_exact_production_files_and_excludes_docker_by_default(self):
        (self.root / 'settings.php').write_text('private configuration')
        (self.root / '.env').write_text('private credentials')
        (self.root / 'docker/.env').write_text('private docker credentials')
        (self.root / 'model/local-debug.php').write_text('untracked local file')
        # Even a tracked local config at the repository root is excluded.
        self.git('add', '-f', 'settings.php')
        self.git('-c', 'user.name=Package Test', '-c', 'user.email=test@example.invalid',
                 '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=/dev/null',
                 'commit', '-qm', 'local config fixture')
        result = self.build()
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertFalse((self.root / 'dist/pays_ps-2.0.1-docker.zip').exists())
        with zipfile.ZipFile(self.root / 'dist/pays_ps-2.0.1.zip') as archive:
            self.assertIsNone(archive.testzip())
            files = {name for name in archive.namelist() if not name.endswith('/')}
            self.assertEqual({'pays_ps/' + name for name in self.production}, files)
            for name, content in self.production.items():
                self.assertEqual(content.encode(), archive.read('pays_ps/' + name))

    def test_with_docker_creates_separate_archive_and_never_packs_docker_env(self):
        self.assertEqual(0, self.build().returncode)
        clean_zip = (self.root / 'dist/pays_ps-2.0.1.zip').read_bytes()
        (self.root / 'docker/.env').write_text('DB_PASSWORD=secret\n')
        self.git('add', '-f', 'docker/.env')
        self.git('-c', 'user.name=Package Test', '-c', 'user.email=test@example.invalid',
                 '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=/dev/null',
                 'commit', '-qm', 'accidentally tracked docker env')
        result = self.build('--with-docker')
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual(clean_zip, (self.root / 'dist/pays_ps-2.0.1.zip').read_bytes())
        expected = {**self.production, **self.docker}
        with zipfile.ZipFile(self.root / 'dist/pays_ps-2.0.1-docker.zip') as archive:
            self.assertIsNone(archive.testzip())
            files = {name for name in archive.namelist() if not name.endswith('/')}
            self.assertEqual({'pays_ps/' + name for name in expected}, files)
            self.assertNotIn('pays_ps/docker/.env', files)

        custom_dir = Path(self.directory.name) / 'docker-out'
        self.assertEqual(0, self.build(str(custom_dir), '--with-docker').returncode)
        self.assertTrue((custom_dir / 'pays_ps-2.0.1-docker.zip').exists())
        self.assertEqual(2, self.build('--unknown-option').returncode)

    def test_uses_committed_version_and_content_with_custom_output_directory(self):
        (self.root / 'pays_ps.php').write_text("<?php $this->version = '9.9.9';\n")
        output = Path(self.directory.name) / 'release packages'
        result = self.build(str(output))
        self.assertEqual(0, result.returncode, result.stderr)
        with zipfile.ZipFile(output / 'pays_ps-2.0.1.zip') as archive:
            self.assertEqual(self.production['pays_ps.php'].encode(),
                             archive.read('pays_ps/pays_ps.php'))
        self.assertFalse((output / 'pays_ps-9.9.9.zip').exists())

    def test_version_extraction_reads_large_module_without_broken_pipe(self):
        source = self.root / 'pays_ps.php'
        source.write_text(self.production['pays_ps.php'] + '// padding\n' * 100000)
        self.git('add', 'pays_ps.php')
        self.git('-c', 'user.name=Package Test', '-c', 'user.email=test@example.invalid',
                 '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=/dev/null',
                 'commit', '-qm', 'large module fixture')
        result = self.build()
        self.assertEqual(0, result.returncode, result.stderr)

    def test_failed_build_preserves_existing_archive_and_removes_temporary_file(self):
        self.assertEqual(0, self.build().returncode)
        archive = self.root / 'dist/pays_ps-2.0.1.zip'
        original = archive.read_bytes()
        self.git('update-index', '--force-remove', 'logo.png')
        # Create the missing-file commit without changing source files on disk.
        tree = self.git('write-tree').stdout.strip()
        commit = self.git('-c', 'user.name=Package Test', '-c', 'user.email=test@example.invalid',
                          '-c', 'commit.gpgsign=false', 'commit-tree', tree, '-m', 'missing logo')
        self.git('update-ref', 'HEAD', commit.stdout.strip())
        result = self.build()
        self.assertNotEqual(0, result.returncode)
        self.assertEqual(original, archive.read_bytes())
        self.assertEqual([], list(archive.parent.glob('.pays_ps-*.zip')))


if __name__ == '__main__':
    unittest.main()
