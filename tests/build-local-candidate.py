"""Build an inspection-only archive from .distignore; never publish or alter version metadata."""
from pathlib import Path
import argparse
import re
import hashlib
import json
import zipfile

root = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('--version', default='1.0.0', choices=['0.2.5', '1.0.0'])
parser.add_argument('--rc', type=int, choices=[1, 2], help='Build a numbered candidate in its own directory')
options = parser.parse_args()
version = options.version
assert re.search(r'Version: ' + re.escape(version) + r'\s', (root / 'pp-woocommerce-advanced-dashboard.php').read_text())
assert 'Stable tag: ' + version in (root / 'readme.txt').read_text()
output = root / '.dev-tools' / 'release-candidate' / version
if options.rc:
    output /= f'rc{options.rc}'
output.mkdir(parents=True, exist_ok=True)
rules = [line.strip().lstrip('/') for line in (root / '.distignore').read_text().splitlines()
         if line.strip() and not line.lstrip().startswith('#')]
expected = {'pp-woocommerce-advanced-dashboard.php', 'index.php', 'readme.txt',
            'assets/dashboard.js', 'assets/dashboard.css', 'templates/dashboard.php',
            'includes/class-adwc-plugin.php', 'includes/class-adwc-analytics.php',
            'includes/class-adwc-date-range.php', 'includes/class-adwc-operations.php'}
files = []
for path in root.rglob('*'):
    relative = path.relative_to(root).as_posix()
    if any(relative == rule or relative.startswith(rule + '/') for rule in rules):
        continue
    if path.is_symlink():
        raise RuntimeError(f'Symlink is not allowed: {relative}')
    if path.is_file():
        files.append(relative)
if set(files) != expected:
    raise RuntimeError(f'Unexpected candidate content: extra={set(files)-expected}, missing={expected-set(files)}')
name = f'{version}-rc{options.rc}' if options.rc else f'local-rc-{version}'
archive = output / f'advanced-dashboard-for-woocommerce-{name}.zip'
slug = 'advanced-dashboard-for-woocommerce'
manifest = {}
with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED) as bundle:
    bundle.comment = f'LOCAL CANDIDATE {version}. PUBLICATION REQUIRES SEPARATE OWNER APPROVAL.'.encode('ascii')
    for relative in sorted(files):
        data = (root / relative).read_bytes()
        # Reject known synthetic credentials, executable test fixtures and private-key material.
        for forbidden in (b'adwc-local-', b'ADWC_INTEGRATION_SANDBOX', b'BEGIN PRIVATE KEY', b'synthetic@example.test'):
            if forbidden in data:
                raise RuntimeError(f'Forbidden development/secret material in {relative}')
        manifest[relative] = {'bytes': len(data), 'sha256': hashlib.sha256(data).hexdigest()}
        entry = zipfile.ZipInfo(f'{slug}/{relative}', (2026, 11, 13, 0, 0, 0))
        entry.compress_type = zipfile.ZIP_DEFLATED
        entry.external_attr = 0o100644 << 16
        bundle.writestr(entry, data)
with zipfile.ZipFile(archive) as bundle:
    assert bundle.testzip() is None
    assert set(bundle.namelist()) == {f'{slug}/{p}' for p in expected}
report = {'purpose': 'Local inspection only; not for publication', 'archive': archive.name,
          'sha256': hashlib.sha256(archive.read_bytes()).hexdigest(), 'files': manifest}
(output / 'manifest.json').write_text(json.dumps(report, indent=2) + '\n', encoding='utf-8')
print(json.dumps({'archive': str(archive), 'files': len(files), 'sha256': report['sha256']}))
