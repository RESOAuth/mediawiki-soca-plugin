#!/usr/bin/env python3
"""Build a deterministic installable ZIP, checksum and SPDX dependency manifest."""
import hashlib
import json
import zipfile
from pathlib import Path

root = Path(__file__).resolve().parents[1]
manifest = json.loads((root / 'extension.json').read_text())
version = manifest['version']
out = root / 'build'
out.mkdir(exist_ok=True)
archive = out / f'SOCA-{version}.zip'
files = []
for name in ['src', 'sql', 'i18n', 'lib/soca/src', 'maintenance', 'vendor', 'docs']:
    files.extend(p for p in (root / name).rglob('*') if p.is_file())
files.extend(root / p for p in ['extension.json', 'composer.json', 'composer.lock', 'README.md', 'LICENSE', 'SECURITY.md'])
with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as bundle:
    for path in sorted(set(files)):
        if path.is_symlink() or path.suffix in {'.pem', '.key'}:
            raise ValueError('A secret or symlink cannot enter the package')
        entry = zipfile.ZipInfo('SOCA/' + path.relative_to(root).as_posix(), (1980, 1, 1, 0, 0, 0))
        entry.compress_type = zipfile.ZIP_DEFLATED
        entry.external_attr = 0o100644 << 16
        bundle.writestr(entry, path.read_bytes())
digest = hashlib.sha256(archive.read_bytes()).hexdigest()
(out / f'{archive.name}.sha256').write_text(f'{digest}  {archive.name}\n')
packages = []
for dependency in json.loads((root / 'composer.lock').read_text())['packages']:
    packages.append({'SPDXID': 'SPDXRef-' + dependency['name'].replace('/', '-'), 'name': dependency['name'],
                     'versionInfo': dependency['version'], 'downloadLocation': dependency['dist']['url'],
                     'licenseConcluded': 'NOASSERTION', 'licenseDeclared': ' AND '.join(dependency.get('license', ['NOASSERTION'])),
                     'filesAnalyzed': False})
sbom = {'spdxVersion': 'SPDX-2.3', 'dataLicense': 'CC0-1.0', 'SPDXID': 'SPDXRef-DOCUMENT',
        'name': f'SOCA-MediaWiki-{version}', 'documentNamespace': f'https://github.com/RESOAuth/mediawiki-soca-plugin/sbom/{digest}',
        'creationInfo': {'creators': ['Organization: RESOAuth'], 'created': '2026-10-21T00:00:00Z'}, 'packages': packages}
(out / f'SOCA-{version}.spdx.json').write_text(json.dumps(sbom, sort_keys=True, indent=2) + '\n')
print(f'{archive.name}: {digest}')
