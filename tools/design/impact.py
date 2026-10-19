#!/usr/bin/env python3
"""Show what an amendment would affect.

Usage:
    python tools/design/impact.py UC-004 ENT-Order
    python tools/design/impact.py --request CR-012     # uses the request's `affects`

Follows every reference in the design (affects, used_by, owns, implements,
phase/task lists, touches, refs...) in both directions, then reports affected
items, phases and tasks, and the progress status of each task, so completed
work that must be re-checked is obvious.
"""
from __future__ import annotations

import argparse
import re
import sys
from collections import defaultdict
from pathlib import Path

sys.dont_write_bytecode = True  # keep tools/design/ free of __pycache__
sys.path.insert(0, str(Path(__file__).resolve().parent))
from design_lib import (REF_RE, find_root, load_design, load_progress, load_requests,  # noqa: E402
                        natural_sorted)


def collect_refs(obj, out):
    if isinstance(obj, dict):
        for v in obj.values():
            collect_refs(v, out)
    elif isinstance(obj, list):
        for v in obj:
            collect_refs(v, out)
    elif isinstance(obj, str):
        out.update(m.group(0) for m in REF_RE.finditer(obj))


def build_graph(d):
    by_id = d.by_id()
    fwd = defaultdict(set)
    for i, item in by_id.items():
        refs = set()
        if isinstance(item, dict):
            body = {k: v for k, v in item.items() if k not in ("id", "tasks")}
            collect_refs(body, refs)
        refs.discard(i)
        fwd[i] |= {r for r in refs if r in by_id}
    for ph in d.phases:
        for t in ph.get("tasks") or []:
            fwd[t["id"]].add(ph["id"])
            fwd[ph["id"]].add(t["id"])
    rev = defaultdict(set)
    for a, bs in fwd.items():
        for b in bs:
            rev[b].add(a)
    return by_id, fwd, rev


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("ids", nargs="*")
    ap.add_argument("--request")
    ap.add_argument("--root", type=Path)
    args = ap.parse_args()
    root = args.root or find_root()
    d = load_design(root)
    ids = list(args.ids)
    if args.request:
        req = next((r for r in load_requests(root) if r.get("id") == args.request), None)
        if not req:
            sys.exit(f"request {args.request} not found in progress/requests/")
        ids += list(req.get("affects") or [])
        print(f"{args.request}: {req.get('problem')}\n")
    if not ids:
        sys.exit("give at least one id")

    by_id, fwd, rev = build_graph(d)
    unknown = [i for i in ids if i not in by_id]
    if unknown:
        print("unknown ids:", ", ".join(unknown))

    # Direct dependants: items that reference a changed item (one hop).
    affected = set()
    for i in ids:
        affected |= rev.get(i, set())
    affected -= set(ids)
    affected = {x for x in affected if not x.startswith("PHASE-") and not re.match(r"P\d+-T", x)}

    # Tasks are affected when they write/read a changed item or the component
    # that owns it, or name a changed use case/entity. Phases follow from tasks
    # plus phases that list a changed item directly.
    owner = {e: c["id"] for c in d.items("components") for e in (c.get("owns") or [])}
    targets = set(ids) | {owner[i] for i in ids if i in owner}
    tasks, phases = set(), set()
    for ph in d.phases:
        lists = set()
        for key in ("use_cases", "entities", "components", "decisions"):
            lists |= set(ph.get(key) or [])
        if lists & set(ids):
            phases.add(ph["id"])
        for t in ph.get("tasks") or []:
            tc = t.get("touches") or {}
            scope = set(tc.get("writes") or []) | set(tc.get("reads") or [])
            named = set(t.get("use_cases") or []) | set(t.get("entities") or [])
            if scope & targets or named & set(ids):
                tasks.add(t["id"])
                phases.add(ph["id"])

    print("Changed:", ", ".join(natural_sorted(ids)))
    groups = defaultdict(list)
    for x in affected:
        groups[x.split("-")[0]].append(x)
    print("\nDirectly affected design items:")
    for k in sorted(groups):
        print(f"  {k}: " + ", ".join(natural_sorted(groups[k])))
    if not groups:
        print("  none")
    print("\nAffected phases:", ", ".join(natural_sorted(phases)) or "none")

    prog = load_progress(root)
    print("\nAffected tasks:")
    recheck = []
    for t in natural_sorted(tasks):
        st = (prog.get(t) or {}).get("status", "planned")
        flag = ""
        if st in ("done", "ready_to_merge"):
            flag = "  ← completed work: mark needs_review after the amendment"
            recheck.append(t)
        print(f"  {t}: {st}{flag}")
    if not tasks:
        print("  none")
    kind = "major" if recheck else "minor"
    print(f"\nSuggested version bump: {kind}"
          + (" (completed work is invalidated)" if recheck else " (no completed work affected)"))


if __name__ == "__main__":
    main()
