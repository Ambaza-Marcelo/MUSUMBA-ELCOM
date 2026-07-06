#!/usr/bin/env python3
"""Fix Blade directives incorrectly wrapped in @lang('messages....')."""
import re
from pathlib import Path

ROOT = Path(__file__).parent / "resources" / "views"

# @lang('messages.@endif') etc.
SIMPLE = [
    ("@lang('messages.@endif')", "@endif"),
    ("@lang('messages.@else')", "@else"),
    ("@lang('messages.@endforeach')", "@endforeach"),
]

# @lang('messages.@directive...')'  -> unescape \' in condition
LANG_DIRECTIVE = re.compile(
    r"@lang\('messages\.((?:&nbsp;)?@(if|elseif|foreach|include)(?:\([^)]*(?:\\'[^)]*)*\))?|@endif|@else|@endforeach)'\)"
)

# Broken split: @lang('messages.@if($var') rest  -> merge
SPLIT_IF = re.compile(
    r"@lang\('messages\.@if\(([^')]+)'\)\s*([^@<]+?)(?=@else|@elseif|@endif|</td>)",
    re.DOTALL,
)


def fix_split_if(content: str) -> str:
    """Fix @lang('messages.@if($x') < 24) patterns."""
    def repl(m):
        var = m.group(1).replace("\\'", "'")
        rest = m.group(2).strip()
        return f"@if({var}{rest}"

    prev = None
    while prev != content:
        prev = content
        content = SPLIT_IF.sub(repl, content)
    return content


def fix_lang_wrappers(content: str) -> str:
    for old, new in SIMPLE:
        content = content.replace(old, new)

    def repl(m):
        inner = m.group(1).replace("\\'", "'")
        return inner

    prev = None
    while prev != content:
        prev = content
        content = LANG_DIRECTIVE.sub(repl, content)

    # @lang('messages.@include(\'...\')')
    content = re.sub(
        r"@lang\('messages\.(@include\([^']+(?:\\'[^']+)*\))'\)",
        lambda m: m.group(1).replace("\\'", "'"),
        content,
    )

    return fix_split_if(content)


def main():
    changed_files = []
    for path in ROOT.rglob("*.blade.php"):
        text = path.read_text(encoding="utf-8")
        fixed = fix_lang_wrappers(text)
        if fixed != text:
            path.write_text(fixed, encoding="utf-8")
            changed_files.append(str(path.relative_to(ROOT.parent.parent)))

    print(f"Fixed {len(changed_files)} files:")
    for f in sorted(changed_files):
        print(f"  - {f}")


if __name__ == "__main__":
    main()
