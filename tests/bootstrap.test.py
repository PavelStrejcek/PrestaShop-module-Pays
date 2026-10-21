"""Exercise the shell bootstrap without Docker, network access, or real sleeps."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest


DOCKER = r'''#!/usr/bin/env python3
import json, os, sys
from pathlib import Path
args = sys.argv[1:]
state_file = Path(os.environ['BOOTSTRAP_STATE'])
state = json.loads(state_file.read_text()) if state_file.exists() else {'calls': [], 'probes': 0}
state['calls'].append(args)
mode = os.environ['BOOTSTRAP_MODE']
status = 0
if 'up' in args:
    status = 42 if mode == 'up_failed' else 0
elif 'ps' in args:
    print('exited' if mode == 'exited' else 'running')
elif 'logs' in args:
    print('diagnostic installer output')
elif 'exec' in args and 'sh' in args:
    state['probes'] += 1
    status = 0 if mode in ['ready', 'test_failed'] and state['probes'] > 1 else 1
elif 'exec' in args and any('tests/integration.php' in a for a in args):
    status = 7 if mode == 'test_failed' else 0
state_file.write_text(json.dumps(state))
sys.exit(status)
'''


class BootstrapTest(unittest.TestCase):
    def run_bootstrap(self, mode):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            shutil.copyfile(Path(__file__).with_name('docker.sh'), root / 'docker.sh')
            binaries = root / 'bin'
            binaries.mkdir()
            (binaries / 'docker').write_text(DOCKER)
            (binaries / 'docker').chmod(0o755)
            (binaries / 'sleep').write_text('#!/bin/sh\nexit 0\n')
            (binaries / 'sleep').chmod(0o755)
            state_file = root / 'state.json'
            environment = dict(os.environ, PATH=str(binaries) + os.pathsep + os.environ['PATH'],
                               BOOTSTRAP_STATE=str(state_file), BOOTSTRAP_MODE=mode)
            result = subprocess.run(['bash', str(root / 'docker.sh')], env=environment,
                                    text=True, capture_output=True, timeout=60)
            return result, json.loads(state_file.read_text())

    def test_exited_installer_fails_without_polling_for_nine_minutes(self):
        result, state = self.run_bootstrap('exited')
        self.assertNotEqual(0, result.returncode)
        self.assertLessEqual(state['probes'], 1)
        self.assertIn('diagnostic installer output', result.stdout)

    def test_startup_failure_preserves_exit_status(self):
        result, _ = self.run_bootstrap('up_failed')
        self.assertEqual(42, result.returncode)

    def test_running_but_unready_shop_still_times_out(self):
        result, state = self.run_bootstrap('unready')
        self.assertNotEqual(0, result.returncode)
        self.assertGreater(state['probes'], 1)
        self.assertIn('PrestaShop did not finish installation', result.stderr)

    def test_ready_shop_runs_both_suites(self):
        result, state = self.run_bootstrap('ready')
        self.assertEqual(0, result.returncode, result.stderr)
        commands = [' '.join(call) for call in state['calls']]
        self.assertTrue(any('tests/unit.php' in command for command in commands))
        self.assertTrue(any('tests/integration.php' in command for command in commands))

    def test_integration_failure_preserves_exit_status(self):
        result, _ = self.run_bootstrap('test_failed')
        self.assertEqual(7, result.returncode)


if __name__ == '__main__':
    unittest.main()
