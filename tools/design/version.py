#!/usr/bin/env python3
"""Design versions, baseline and changelog.

Usage:
    python tools/design/version.py show
    python tools/design/version.py baseline --summary "First baseline"
    python tools/design/version.py bump minor --summary "Add awaiting_stock state" --request CR-012
    python tools/design/version.py bump major --summary "Switch to multi-currency"

Rules:
- `baseline` runs the strict validator first and refuses on errors. It sets
  the version to 1.0.0 (or the next major after a previous baseline).
- After a baseline, every change to design/ needs a `bump`:
    minor = additions/clarifications that do not invalidate completed work
    major = changes that invalidate completed work (impact.py tells you)
    patch = wording only, no meaning change
- Every bump adds an entry to design/CHANGELOG.md. CI rejects design changes
  without a version change and a changelog entry.
"""
from __future__ import annotations

import argparse
import datetime as dt
import re
import subprocess
import sys
from pathlib import Path

sys.dont_write_bytecode = True  # keep tools/design/ free of __pycache__
sys.path.insert(0, str(Path(__file__).resolve().parent))
from design_lib import find_root  # noqa: E402


def read_version(index_text):
    m = re.search(r"^design_version:\s*['\"]?([0-9]+\.[0-9]+\.[0-9]+)", index_text, re.M)
    return m.group(1) if m else "0.0.0"


def set_field(text, key, value):
    """Replace a top-level scalar/flow field in index.yaml, preserving the rest of the file."""
    pat = re.compile(rf"^{key}:.*$", re.M)
    line = f"{key}: {value}"
    return pat.sub(line, text, count=1) if pat.search(text) else text.rstrip() + "\n" + line + "\n"


def add_changelog(root, version, summary, request=None):
    path = root / "design" / "CHANGELOG.md"
    old = path.read_text(encoding="utf-8") if path.exists() else "# Design changelog\n"
    head, _, rest = old.partition("\n")
    entry = f"\n## v{version} — {dt.date.today().isoformat()}\n\n- {summary}" + \
            (f" ({request})" if request else "") + "\n"
    path.write_text(head + "\n" + entry + rest, encoding="utf-8")


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = ap.add_subparsers(dest="cmd", required=True)
    sub.add_parser("show")
    b = sub.add_parser("baseline")
    b.add_argument("--summary", required=True)
    u = sub.add_parser("bump")
    u.add_argument("level", choices=["major", "minor", "patch"])
    u.add_argument("--summary", required=True)
    u.add_argument("--request", help="change request id, e.g. CR-012")
    ap.add_argument("--root", type=Path)
    args = ap.parse_args()

    root = args.root or find_root()
    idx_path = root / "design" / "index.yaml"
    text = idx_path.read_text(encoding="utf-8")
    cur = read_version(text)
    major, minor, patch = (int(x) for x in cur.split("."))

    if args.cmd == "show":
        print(cur)
        return

    if args.cmd == "baseline":
        tools = Path(__file__).resolve().parent
        res = subprocess.run([sys.executable, str(tools / "validate.py"), "--baseline",
                              "--root", str(root)])
        if res.returncode != 0:
            sys.exit("Baseline refused: fix the errors above first.")
        new = f"{max(major + (1 if major >= 1 else 0), 1)}.0.0"
        text = set_field(text, "design_version", new)
        text = set_field(text, "baseline", "{version: " + new + ", date: '"
                         + dt.date.today().isoformat() + "'}")
        idx_path.write_text(text, encoding="utf-8")
        add_changelog(root, new, f"Baseline: {args.summary}")
        print(f"baselined at v{new}")
        return

    if args.level == "major":
        new = f"{major + 1}.0.0"
    elif args.level == "minor":
        new = f"{major}.{minor + 1}.0"
    else:
        new = f"{major}.{minor}.{patch + 1}"
    idx_path.write_text(set_field(text, "design_version", new), encoding="utf-8")
    add_changelog(root, new, args.summary, args.request)
    print(f"{cur} -> {new}")


if __name__ == "__main__":
    main()
