#!/usr/bin/env python3
"""CI gate for the design.

Usage:
    python tools/design/ci_check.py                     # validate + build freshness
    python tools/design/ci_check.py --base origin/main  # + version/changelog discipline

Checks:
1. The design validates (strict mode once a baseline exists).
2. build/ is exactly what build.py generates from design/ (briefs excluded).
3. With --base: after a baseline, any change under design/ must come with a
   new design_version and a CHANGELOG.md entry. This catches changes made
   outside the tool, including manual ones.
"""
from __future__ import annotations

import argparse
import filecmp
import subprocess
import sys
import tempfile
from pathlib import Path

sys.dont_write_bytecode = True  # keep tools/design/ free of __pycache__
sys.path.insert(0, str(Path(__file__).resolve().parent))
from design_lib import find_root, read_yaml  # noqa: E402
from version import read_version  # noqa: E402

TOOLS = Path(__file__).resolve().parent


def run(cmd, **kw):
    return subprocess.run(cmd, text=True, capture_output=True, **kw)


def diff_trees(a: Path, b: Path, rel=""):
    out = []
    cmp = filecmp.dircmp(a, b)
    out += [f"{rel}{x} (only in generated)" for x in cmp.left_only]
    out += [f"{rel}{x} (only in build/)" for x in cmp.right_only if x != "briefs"]
    _, mismatch, errors = filecmp.cmpfiles(a, b, cmp.common_files, shallow=False)
    out += [f"{rel}{x} (differs)" for x in mismatch + errors]
    for sub in cmp.common_dirs:
        if sub == "briefs":
            continue
        out += diff_trees(a / sub, b / sub, f"{rel}{sub}/")
    return out


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--base", help="git ref to compare against, e.g. origin/main")
    ap.add_argument("--root", type=Path)
    args = ap.parse_args()
    root = args.root or find_root()
    failed = False

    baselined = bool(read_yaml(root / "design" / "index.yaml").get("baseline"))
    cmd = [sys.executable, str(TOOLS / "validate.py"), "--root", str(root), "--quiet"]
    if baselined:
        cmd.append("--baseline")
    res = run(cmd)
    print(res.stdout.strip())
    if res.returncode != 0:
        failed = True

    with tempfile.TemporaryDirectory() as tmp:
        res = run([sys.executable, str(TOOLS / "build.py"), "--root", str(root), "--out", tmp])
        if res.returncode != 0:
            print(res.stdout, res.stderr)
            failed = True
        elif not (root / "build").exists():
            print("✗ build/ is missing: run tools/design/build.py and commit the result")
            failed = True
        else:
            stale = diff_trees(Path(tmp), root / "build")
            if stale:
                failed = True
                print("✗ build/ is out of date with design/ (run tools/design/build.py):")
                for s in stale:
                    print("   ", s)
            else:
                print("✓ build/ matches design/")

    if args.base and baselined:
        changed = run(["git", "diff", "--name-only", f"{args.base}...HEAD", "--", "design/"],
                      cwd=root).stdout.split()
        if changed:
            old_index = run(["git", "show", f"{args.base}:design/index.yaml"], cwd=root).stdout
            old_v = read_version(old_index) if old_index else "0.0.0"
            new_v = read_version((root / "design" / "index.yaml").read_text(encoding="utf-8"))
            if old_v == new_v:
                failed = True
                print(f"✗ design/ changed but design_version is still {new_v} "
                      "(use tools/design/version.py bump)")
            if "design/CHANGELOG.md" not in changed:
                failed = True
                print("✗ design/ changed without a CHANGELOG.md entry")
            if not failed:
                print(f"✓ design change recorded: v{old_v} -> v{new_v}")

    sys.exit(1 if failed else 0)


if __name__ == "__main__":
    main()
