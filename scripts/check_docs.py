#!/usr/bin/env python3
"""Standard-library documentation checks; NOT application tests."""
from pathlib import Path
import re
root=Path(__file__).resolve().parents[1]
for n in ('01_PRD','02_AppFlow','03_UI_Spec','04_TRD','05_Schema','06_Plan'):
    assert (root/'docs'/f'{n}.md').is_file(), n
for file in root.rglob('*.md'):
    if {'node_modules','dist','.git'} & set(file.relative_to(root).parts): continue
    for link in re.findall(r'\]\(([^)]+)\)',file.read_text(encoding='utf-8')):
        if link.startswith(('https://','http://','mailto:','#','app://')): continue
        relative=link.split('#',1)[0].split('?',1)[0]
        if relative and not (file.parent/relative).exists():
            raise AssertionError(f'{file.relative_to(root)}: broken link {link}')
plan=(root/'docs/06_Plan.md').read_text(encoding='utf-8')
assert 'G2 — Pre-pilot readiness' in plan
assert 'G3 — General release' in plan
assert 'G2 — Release' not in plan
assert 'not yet approved for production' in (root/'README.md').read_text(encoding='utf-8')
print('Documentation integrity PASS (not application tests)')
