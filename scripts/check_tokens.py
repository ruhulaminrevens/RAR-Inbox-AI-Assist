#!/usr/bin/env python3
"""Design-token rule (UI Spec §10): colours live only in token declarations.

- panel.css: no literal colours (hex, rgb/rgba, hsl/hsla); every var() is a token from tokens.css.
- admin.css: literals only inside `--iaa-*: value;` declarations, and each copied value must equal
  the light-theme value in tokens.css.
"""
from pathlib import Path
import re
import sys

root = Path(__file__).resolve().parents[1]
tokens_css = (root / 'extension/sidepanel/tokens.css').read_text(encoding='utf-8')
defined = set(re.findall(r'(--iaa-[a-z0-9-]+)\s*:', tokens_css))
light = {}
for name, value in re.findall(r'(--iaa-[a-z0-9-]+)\s*:\s*([^;]+);', tokens_css):
    light.setdefault(name, value.strip())
literal = re.compile(r'#[0-9a-fA-F]{3,8}\b|\brgba?\(|\bhsla?\(')
errors = []
for rel in ('extension/sidepanel/panel.css', 'plugin/rar-inbox-ai-assist/assets/admin.css'):
    text = (root / rel).read_text(encoding='utf-8')
    for n, line in enumerate(text.splitlines(), 1):
        line = re.sub(r'/\*.*?\*/', '', line)
        decl = re.match(r'\s*(--iaa-[a-z0-9-]+)\s*:\s*([^;]+);', line)
        if decl and rel.endswith('admin.css'):
            name, value = decl.group(1), decl.group(2).strip()
            if name not in light:
                errors.append(f'{rel}:{n}: {name} is not a token in tokens.css')
            elif value.lower() != light[name].lower():
                errors.append(f'{rel}:{n}: {name} = {value}, tokens.css has {light[name]}')
            continue
        if literal.search(line):
            errors.append(f'{rel}:{n}: literal colour; use a --iaa-* token')
        if rel.startswith('extension'):
            for var in re.findall(r'var\((--[a-z0-9-]+)', line):
                if var not in defined:
                    errors.append(f'{rel}:{n}: unknown token {var}')
if errors:
    print('\n'.join(errors))
    sys.exit(1)
print(f'Design tokens PASS ({len(defined)} tokens defined)')
