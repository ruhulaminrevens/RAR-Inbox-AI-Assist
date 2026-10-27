#!/usr/bin/env python3
"""Build release ZIPs: the WordPress plugin, the Chrome extension, and SHA256SUMS.

Usage: python3 scripts/package.py [--out dist]

- Plugin ZIP has the folder rar-inbox-ai-assist/ at its root (WordPress "Upload Plugin").
- Extension ZIP has manifest.json at its root (unzip, then Chrome "Load unpacked").
- Versions in the plugin header, RAR_IAA_VERSION, manifest.json, lib/api.js and CHANGELOG.md
  must agree, otherwise the build stops.
- Refuses to package files that look like secrets (*.pem, *.key, wp-config.php, .env).
"""
from pathlib import Path
import argparse
import hashlib
import json
import re
import sys
import zipfile

root = Path(__file__).resolve().parents[1]
plugin_dir = root / 'plugin' / 'rar-inbox-ai-assist'
ext_dir = root / 'extension'
FORBIDDEN = re.compile(r'(\.pem|\.key|\.env|wp-config\.php|\.DS_Store|\.zip)$', re.I)
SECRET_TEXT = re.compile(r'(sk-ant-[A-Za-z0-9_-]{10,}|sk-[A-Za-z0-9]{32,}|iaa_[a-z0-9]{8}_[A-Za-z0-9]{40}|-----BEGIN [A-Z ]*PRIVATE KEY-----)')


def versions():
    main = (plugin_dir / 'rar-inbox-ai-assist.php').read_text(encoding='utf-8')
    found = {
        'plugin header': re.search(r'^\s*\*\s*Version:\s*([0-9.]+)', main, re.M).group(1),
        'RAR_IAA_VERSION': re.search(r"RAR_IAA_VERSION',\s*'([0-9.]+)'", main).group(1),
        'manifest.json': json.loads((ext_dir / 'manifest.json').read_text(encoding='utf-8'))['version'],
        'lib/api.js': re.search(r"VERSION = '([0-9.]+)'", (ext_dir / 'lib' / 'api.js').read_text(encoding='utf-8')).group(1),
    }
    changelog = (root / 'CHANGELOG.md').read_text(encoding='utf-8')
    if len(set(found.values())) != 1:
        sys.exit(f'Version mismatch: {found}')
    v = next(iter(found.values()))
    if f'[{v}]' not in changelog:
        sys.exit(f'CHANGELOG.md has no [{v}] entry')
    return v


def files(base):
    for p in sorted(base.rglob('*')):
        if p.is_file() and not any(part.startswith('.') for part in p.relative_to(base).parts):
            if FORBIDDEN.search(p.name):
                sys.exit(f'Refusing to package {p.relative_to(root)}')
            if p.suffix in ('.php', '.js', '.json', '.css', '.html', '.txt', '.md') and SECRET_TEXT.search(p.read_text(encoding='utf-8', errors='ignore')):
                sys.exit(f'Possible secret in {p.relative_to(root)}')
            yield p


def build(zip_path, base, prefix):
    with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        n = 0
        for p in files(base):
            info = zipfile.ZipInfo(prefix + p.relative_to(base).as_posix(), date_time=(2026, 1, 1, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o644 << 16
            z.writestr(info, p.read_bytes())
            n += 1
    return n


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--out', default=str(root / 'dist'))
    out = Path(ap.parse_args().out)
    out.mkdir(parents=True, exist_ok=True)
    v = versions()
    targets = [
        (out / f'rar-inbox-ai-assist-{v}.zip', plugin_dir, 'rar-inbox-ai-assist/'),
        (out / f'rar-inbox-ai-assist-extension-{v}.zip', ext_dir, ''),
    ]
    sums = []
    for zip_path, base, prefix in targets:
        n = build(zip_path, base, prefix)
        digest = hashlib.sha256(zip_path.read_bytes()).hexdigest()
        sums.append(f'{digest}  {zip_path.name}')
        print(f'{zip_path.name}: {n} files, {zip_path.stat().st_size} bytes')
    (out / 'SHA256SUMS').write_text('\n'.join(sums) + '\n', encoding='utf-8')
    print('\n'.join(sums))


if __name__ == '__main__':
    main()
