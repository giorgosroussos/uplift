#!/usr/bin/env python3
"""Generate everything under build/ from design/ (the single source of truth).

Usage:
    python tools/design/build.py                 # docs, diagrams, packs, CLAUDE rules, waves
    python tools/design/build.py --out /tmp/x    # same, into another directory (used by CI)
    python tools/design/build.py brief P2-T4     # task brief = pack slice + handoff notes

Generation is deterministic (no timestamps, sorted output), so CI can rebuild
and compare byte-for-byte. Never edit files under build/ by hand.
"""
from __future__ import annotations

import argparse
import re
import shutil
import sys
from pathlib import Path

sys.dont_write_bytecode = True  # keep tools/design/ free of __pycache__
sys.path.insert(0, str(Path(__file__).resolve().parent))
from design_lib import (as_list, ent_name, estimate_tokens, find_root, load_design,  # noqa: E402
                        load_progress, load_semantic_types, mid, mlabel, natural_sorted,
                        source_hash)

# ======================================================================
# helpers
# ======================================================================

class Ctx:
    def __init__(self, d):
        self.d = d
        self.by_id = d.by_id()
        self.version = d.index.get("design_version", "0.0.0")
        self.hash = source_hash(d.root)
        self.sem = load_semantic_types()
        self.db = d.stack_database()
        self.owner = {}
        for c in d.items("components"):
            for e in as_list(c.get("owns")):
                self.owner[e] = c["id"]
        par = d.index.get("parallelism") or {}
        self.max_parallel = int(par.get("max_parallel", 3))
        self.ts_migrations = bool(par.get("timestamped_migrations", True))
        budgets = d.index.get("budgets") or {}
        self.pack_budget = int(budgets.get("pack_tokens", 8000))
        self.brief_budget = int(budgets.get("brief_tokens", 4000))

    def title(self, i):
        it = self.by_id.get(i) or {}
        return it.get("title") or it.get("name") or it.get("description") or ""

    def header(self, title, kind="Generated document"):
        return (f"<!-- GENERATED from design/ — do not edit. Rebuild with tools/design/build.py -->\n"
                f"# {title}\n\n"
                f"_{kind} · design v{self.version} · source {self.hash}_\n")

    def storage(self, t):
        s = self.sem.get(t) or {}
        return (s.get("db") or {}).get(self.db) or s.get("storage") or t


def bullets(items, prefix="- "):
    return "\n".join(f"{prefix}{i}" for i in items) if items else "_none_"


def table(headers, rows):
    if not rows:
        return "_none_"
    esc = lambda v: str(v if v is not None else "").replace("|", "\\|").replace("\n", " ")
    out = ["| " + " | ".join(headers) + " |", "|" + "---|" * len(headers)]
    out += ["| " + " | ".join(esc(c) for c in r) + " |" for r in rows]
    return "\n".join(out)


def mermaid(src):
    return f"```mermaid\n{src.rstrip()}\n```"


def deferred_marker(it):
    return " _(deferred)_" if it.get("status") == "deferred" else ""


# ======================================================================
# diagrams
# ======================================================================

def diagram_context(c: Ctx, trust=False):
    d = c.d
    name = (d.index.get("project") or {}).get("name", "System")
    lines = ["flowchart LR"]
    if trust:
        lines.append('  subgraph TRUST["Trusted zone (our system)"]')
        lines.append(f'    SYS["{mlabel(name)}"]')
        lines.append("  end")
    else:
        lines.append(f'  SYS["{mlabel(name)}"]')
    for a in d.items("actors"):
        shape = f'(["{mlabel(a.get("name", a["id"]))}"])' if a.get("kind") == "system" \
            else f'["👤 {mlabel(a.get("name", a["id"]))}"]'
        lines.append(f"  {mid(a['id'])}{shape} --> SYS")
    for x in d.items("external_systems"):
        label = mlabel(x.get("name", x["id"]) + (" (TBD vendor)" if x.get("status") == "deferred" else ""))
        lines.append(f'  {mid(x["id"])}[["{label}"]]')
        direction = x.get("direction", "outbound")
        arrow = {"outbound": f"SYS --> {mid(x['id'])}",
                 "inbound": f"{mid(x['id'])} --> SYS",
                 "both": f"SYS <--> {mid(x['id'])}"}.get(direction, f"SYS --- {mid(x['id'])}")
        lines.append("  " + arrow)
    return "\n".join(lines)


def diagram_use_cases(c: Ctx, ucs=None):
    d = c.d
    ucs = ucs if ucs is not None else d.items("use_cases")
    lines = ["flowchart LR"]
    actors = {a for u in ucs for a in as_list(u.get("actors"))}
    for a in natural_sorted(actors):
        lines.append(f'  {mid(a)}["👤 {mlabel(c.title(a) or a)}"]')
    lines.append('  subgraph SYSTEM["System"]')
    for u in ucs:
        lines.append(f'    {mid(u["id"])}(["{mlabel(u["id"] + ": " + u.get("title", ""))}"])')
    lines.append("  end")
    for u in ucs:
        for a in as_list(u.get("actors")):
            lines.append(f"  {mid(a)} --- {mid(u['id'])}")
    return "\n".join(lines)


REL = {"one-to-one": "||--||", "one-to-many": "||--o{", "many-to-one": "}o--||",
       "many-to-many": "}o--o{"}


def diagram_er(c: Ctx, entities):
    lines = ["erDiagram"]
    ids = {e["id"] for e in entities}
    for e in entities:
        lines.append(f"  {mid(ent_name(e['id']))} {{")
        for at in e.get("attributes") or []:
            key = {"primary": " PK", "foreign": " FK"}.get(at.get("key"), "")
            if at.get("ref") and not key:
                key = " FK"
            lines.append(f"    {mid(at.get('type', 'string'))} {mid(at.get('name'))}{key}")
        lines.append("  }")
    for e in entities:
        for rel in e.get("relations") or []:
            if rel.get("to") in ids:
                lines.append(f"  {mid(ent_name(e['id']))} {REL.get(rel.get('type'), '||--o{')} "
                             f"{mid(ent_name(rel['to']))} : \"{mlabel(rel.get('label', 'has'))}\"")
    return "\n".join(lines)


def diagram_states(e):
    st = e.get("states") or {}
    lines = ["stateDiagram-v2"]
    if st.get("initial"):
        lines.append(f"  [*] --> {st['initial']}")
    for tr in st.get("transitions") or []:
        a, b = [s.strip() for s in str(tr).split("->")]
        lines.append(f"  {a} --> {b}")
    return "\n".join(lines)


def diagram_components(c: Ctx):
    d = c.d
    lines = ["flowchart TB"]
    for comp in d.items("components"):
        lines.append(f'  {mid(comp["id"])}["{mlabel(comp["id"])}<br/>{mlabel(comp.get("responsibility", ""))}"]')
    for comp in d.items("components"):
        for dep in as_list(comp.get("may_depend_on")):
            lines.append(f"  {mid(comp['id'])} --> {mid(dep)}")
    for it in d.items("integrations"):
        x = c.by_id.get(it.get("external")) or {}
        lines.append(f'  {mid(it["external"])}[["{mlabel(x.get("name", it["external"]))}"]]')
        lines.append(f"  {mid(it['component'])} -. {mlabel((it.get('interface') or {}).get('name', 'adapter'))} .-> {mid(it['external'])}")
    return "\n".join(lines)


def diagram_containers(c: Ctx):
    d = c.d
    stack = d.model.get("stack") or {}
    lines = ["flowchart LR"]
    for a in d.items("actors"):
        if a.get("kind") == "human":
            lines.append(f'  {mid(a["id"])}["👤 {mlabel(a.get("name", a["id"]))}"]')
    lines.append('  subgraph SYS["System"]')
    for item in stack.get("items") or []:
        if item.get("role") in ("frontend", "backend", "worker", "database", "cache", "storage", "queue"):
            lines.append(f'    {mid(item["role"])}["{mlabel(item["role"])}<br/>{mlabel(item.get("name"))} {mlabel(item.get("version", ""))}"]')
    lines.append("  end")
    roles = {i.get("role") for i in stack.get("items") or []}
    entry = "frontend" if "frontend" in roles else "backend"
    for a in d.items("actors"):
        if a.get("kind") == "human" and entry in roles:
            lines.append(f"  {mid(a['id'])} --> {entry}")
    if "frontend" in roles and "backend" in roles:
        lines.append("  frontend --> backend")
    for r in ("database", "cache", "storage", "queue"):
        if r in roles and "backend" in roles:
            lines.append(f"  backend --> {r}")
    for x in d.items("external_systems"):
        lines.append(f'  {mid(x["id"])}[["{mlabel(x.get("name", x["id"]))}"]]')
        if "backend" in roles:
            lines.append(f"  backend --- {mid(x['id'])}")
    return "\n".join(lines)


def diagram_deployment(c: Ctx, env):
    d = c.d
    lines = ["flowchart LR", f'  subgraph ENV["{mlabel(env)}"]', '    APP["Application"]']
    stack = d.model.get("stack") or {}
    for item in stack.get("items") or []:
        if item.get("role") in ("database", "cache", "storage", "queue"):
            lines.append(f'    {mid(item["role"])}[("{mlabel(item.get("name"))}")]')
            lines.append(f"    APP --> {mid(item['role'])}")
    lines.append("  end")
    for x in d.items("external_systems"):
        b = ((x.get("bindings") or {}).get(env) or {})
        mode = b.get("mode", "?")
        tool = f" · {b['tool']}" if b.get("tool") else ""
        lines.append(f'  {mid(x["id"])}[["{mlabel(x.get("name", x["id"]))}<br/>{mlabel(mode + tool)}"]]')
        lines.append(f"  APP --- {mid(x['id'])}")
    return "\n".join(lines)


def diagram_phases(c: Ctx):
    lines = ["flowchart LR"]
    for ph in c.d.phases:
        lines.append(f'  {mid(ph["id"])}["{mlabel(ph["id"])}<br/>{mlabel(ph.get("goal", ""))}"]')
    for ph in c.d.phases:
        for dep in as_list(ph.get("depends_on")):
            lines.append(f"  {mid(dep)} --> {mid(ph['id'])}")
    return "\n".join(lines)


def diagram_waves(waves, label):
    lines = ["flowchart LR"]
    prev = None
    for i, wave in enumerate(waves, 1):
        lines.append(f'  subgraph W{i}["{label} {i}"]')
        for t in wave:
            lines.append(f'    {mid(t)}["{mlabel(t)}"]')
        lines.append("  end")
        if prev:
            lines.append(f"  W{prev} --> W{i}")
        prev = i
    return "\n".join(lines)


# ======================================================================
# parallel execution waves
# ======================================================================

def task_footprint(c: Ctx, t):
    tc = t.get("touches") or {}
    comps = set()
    for ref in as_list(tc.get("writes")):
        comps.add(c.owner.get(ref, ref) if ref.startswith("ENT-") else ref)
    return comps, set(as_list(tc.get("hotspots"))), bool(tc.get("migrations"))


def conflicts(c: Ctx, a, b):
    ca, ha, ma = a
    cb, hb, mb = b
    if ca & cb:
        return "writes " + ", ".join(sorted(ca & cb))
    if ha & hb:
        return "hotspot " + ", ".join(sorted(ha & hb))
    if ma and mb and not c.ts_migrations:
        return "both create migrations"
    return None


def compute_waves(c: Ctx, nodes: dict, deps: dict, footprint: dict):
    """Greedy wave planning. Returns (waves, notes) where notes explain serialisation."""
    done, waves, notes = set(), [], []
    remaining = natural_sorted(nodes)
    guard = 0
    while remaining and guard < 1000:
        guard += 1
        ready = [n for n in remaining if all(dep in done or dep not in nodes for dep in deps.get(n, []))]
        if not ready:
            notes.append("unresolvable dependencies: " + ", ".join(remaining))
            break
        wave = []
        for n in ready:
            if len(wave) >= c.max_parallel:
                notes.append(f"{n} waits: max_parallel={c.max_parallel}")
                continue
            clash = next(((m, conflicts(c, footprint[n], footprint[m])) for m in wave
                          if conflicts(c, footprint[n], footprint[m])), None)
            if clash:
                notes.append(f"{n} serialised after {clash[0]} ({clash[1]})")
                continue
            wave.append(n)
        waves.append(wave)
        done |= set(wave)
        remaining = [n for n in remaining if n not in done]
    return waves, notes


def phase_waves(c: Ctx, ph):
    tasks = {t["id"]: t for t in ph.get("tasks") or []}
    deps = {i: as_list(t.get("depends_on")) for i, t in tasks.items()}
    fp = {i: task_footprint(c, t) for i, t in tasks.items()}
    return compute_waves(c, tasks, deps, fp)


def project_waves(c: Ctx):
    phases = {p["id"]: p for p in c.d.phases}
    deps = {i: as_list(p.get("depends_on")) for i, p in phases.items()}
    fp = {}
    for i, p in phases.items():
        comps = set(as_list(p.get("components")))
        hot, mig = set(), False
        for t in p.get("tasks") or []:
            tc, th, tm = task_footprint(c, t)
            comps |= tc
            hot |= th
            mig = mig or tm
        fp[i] = (comps, hot, mig)
    return compute_waves(c, phases, deps, fp)


# ======================================================================
# constraints & acceptance criteria (shared by packs and briefs)
# ======================================================================

def standing_constraints(c: Ctx):
    """prepare_now of every deferred decision / external system: apply from day one."""
    out = []
    for dec in c.d.decisions:
        if dec.get("status") == "deferred":
            out += [f"{x}  ({dec['id']}, deferred)" for x in as_list(dec.get("prepare_now"))]
    for x in c.d.items("external_systems"):
        if x.get("status") == "deferred":
            out += [f"{p}  ({x['id']}, deferred)" for p in as_list(x.get("prepare_now"))]
    return out


def scoped_constraints(c: Ctx, decisions, entities, components, external_ids):
    out = []
    for i in decisions:
        dec = c.by_id.get(i) or {}
        if dec.get("status") == "decided" and not dec.get("global"):
            out += [f"{x}  ({i})" for x in as_list(dec.get("constraints"))]
    types_used = set()
    for e in entities:
        attrs = e.get("attributes") or []
        types_used |= {a.get("type") for a in attrs}
        if e.get("states"):
            out.append(f"MUST reject {e['id']} state transitions that are not listed in its "
                       f"state model")
        pii = [a["name"] for a in attrs if a.get("sensitivity") in ("personal", "sensitive")]
        if pii:
            out.append(f"MUST NOT write {e['id']} fields {', '.join(pii)} to logs or error messages")
        enc = [a["name"] for a in attrs if a.get("sensitivity") == "sensitive"]
        if enc:
            out.append(f"MUST encrypt {e['id']} fields {', '.join(enc)} at rest")
    for t in natural_sorted(t for t in types_used if t):
        rule = (c.sem.get(t) or {}).get("rule")
        if rule:
            out.append(f"{rule}  (type {t})")
    ent_ids = {e["id"] for e in entities}
    for o in c.d.items("ownership"):
        if o.get("entity") in ent_ids:
            out.append(f"MUST enforce {o['id']} on every read and write of {o['entity']}: "
                       f"{o.get('rule')}")
    for comp_id in components:
        comp = c.by_id.get(comp_id) or {}
        allowed = as_list(comp.get("may_depend_on"))
        out.append(f"{comp_id} MUST only depend on: {', '.join(allowed) if allowed else 'no other component'}")
    for it in c.d.items("integrations"):
        if it.get("component") in components:
            f = it.get("failure") or {}
            iface = (it.get("interface") or {}).get("name", "its interface")
            out.append(f"MUST access {it['external']} only through {iface} in {it['component']}")
            if f.get("timeout"):
                out.append(f"MUST apply timeout {f['timeout']} to every {it['external']} call"
                           + (f"; retries: {f['retries']}" if f.get("retries") else ""))
            if f.get("idempotency_key"):
                out.append(f"MUST send idempotency key ({f['idempotency_key']}) on every "
                           f"{it['external']} write so retries never duplicate effects")
            for inb in it.get("inbound") or []:
                if inb.get("verify_signature"):
                    out.append(f"MUST verify the signature of webhook '{inb.get('webhook')}' "
                               f"before processing")
                if inb.get("idempotent"):
                    out.append(f"MUST process webhook '{inb.get('webhook')}' idempotently")
    return out


def acceptance(c: Ctx, use_cases, entities, components, extra):
    out = []
    for u in use_cases:
        for ac in as_list(u.get("acceptance_criteria")):
            out.append(f"[{u['id']}] {ac}")
    ent_ids = {e["id"] for e in entities}
    for o in c.d.items("ownership"):
        if o.get("entity") in ent_ids:
            for ro in as_list(o.get("applies_to")):
                out.append(f"[{o['id']}] Given a {ro} user, When they read or modify an "
                           f"{o['entity']} record the rule does not grant them ({o.get('rule')}), "
                           f"Then access is denied and nothing changes")
    for e in entities:
        if e.get("states"):
            out.append(f"[{e['id']}] Given any state, When a transition not in the state model "
                       f"is attempted, Then it is rejected and the state is unchanged")
    for it in c.d.items("integrations"):
        if it.get("component") in components and (it.get("failure") or {}).get("when_unavailable"):
            out.append(f"[{it['id']}] Given {it['external']} is unavailable or times out, "
                       f"Then: {it['failure']['when_unavailable']}")
    out += [f"[phase] {x}" for x in as_list(extra)]
    return out


def entity_block(c: Ctx, e):
    rows = []
    for at in e.get("attributes") or []:
        notes = []
        if at.get("key"):
            notes.append(at["key"])
        if at.get("ref"):
            notes.append(f"→ {at['ref']}")
        if at.get("required"):
            notes.append("required")
        if at.get("unique"):
            notes.append("unique")
        if at.get("sensitivity") and at["sensitivity"] not in ("public", "internal"):
            notes.append(at["sensitivity"])
        rows.append([at.get("name"), at.get("type"), c.storage(at.get("type")), ", ".join(notes)])
    parts = [f"#### {e['id']} — {e.get('description', '')}",
             f"Owned by {c.owner.get(e['id'], '—')} · retention: {e.get('retention', '—')} · "
             f"audit: {'yes' if e.get('audit') else 'no'}",
             table(["attribute", "type", "storage", "notes"], rows)]
    rels = [f"{r.get('type')} → {r.get('to')}" + (f" (on delete {r['on_delete']})" if r.get("on_delete") else "")
            for r in e.get("relations") or []]
    if rels:
        parts.append("Relations: " + "; ".join(rels))
    st = e.get("states")
    if st:
        parts.append(f"States: initial `{st.get('initial')}`; allowed transitions: "
                     + ", ".join(f"`{t}`" for t in st.get("transitions") or []))
    return "\n\n".join(parts)


def use_case_block(u, full=True):
    parts = [f"#### {u['id']} — {u.get('title', '')} ({u.get('priority', '?')})",
             f"Actors: {', '.join(as_list(u.get('actors')))} · Goal: {u.get('goal', '')}"]
    if full and u.get("main_flow"):
        parts.append("Main flow:\n" + "\n".join(f"{i}. {s}" for i, s in enumerate(u["main_flow"], 1)))
    if full and u.get("exceptions"):
        parts.append("Exceptions:\n" + bullets(u["exceptions"]))
    return "\n\n".join(parts)


def interface_block(it):
    iface = it.get("interface") or {}
    f = it.get("failure") or {}
    parts = [f"#### {iface.get('name', 'Interface')} ({it['id']}: {it.get('component')} ↔ {it.get('external')})",
             f"Mechanism: {it.get('mechanism', '—')}",
             "Operations:\n" + bullets([f"`{o}`" for o in as_list(iface.get("operations"))])]
    if it.get("inbound"):
        parts.append("Inbound:\n" + bullets([
            f"webhook `{i.get('webhook')}` · verify signature: {i.get('verify_signature', False)} · "
            f"idempotent: {i.get('idempotent', False)}" for i in it["inbound"]]))
    parts.append(f"Failure policy: timeout {f.get('timeout', '—')}, retries {f.get('retries', '—')}, "
                 f"idempotency key {f.get('idempotency_key', '—')}; when unavailable: "
                 f"{f.get('when_unavailable', '—')}")
    return "\n\n".join(parts)


# ======================================================================
# CLAUDE rules (always loaded: keep it small)
# ======================================================================

def claude_rules(c: Ctx):
    d = c.d
    p = d.index.get("project") or {}
    stack = d.model.get("stack") or {}
    lines = [
        "<!-- GENERATED from design/ — do not edit. Rebuild with tools/design/build.py -->",
        f"# Design rules — {p.get('name', '')} (design v{c.version} · {c.hash})",
        "",
        p.get("summary", ""),
        "",
        "## How you work in this repository",
        "1. You work on one task id at a time. Get your brief with "
        "`python tools/design/build.py brief <TASK-ID>` and read `build/briefs/<TASK-ID>.md`. "
        "It contains everything the task needs. Do not read design/ wholesale; open a single "
        "design item only when the brief references it and you need detail.",
        "2. design/, build/ (except build/briefs/), tools/design/ and this file are the source of "
        "truth. Never edit them; edits are blocked.",
        "3. If the spec is unclear, contradictory or missing something, STOP. Do not improvise. "
        "Set your task to `blocked` and write `progress/requests/CR-<TASK-ID>-<n>.yaml` (e.g. CR-P1-T1-1, so parallel tasks never collide) "
        "(id, raised_by: implementation-agent, task, problem, affects, proposal, status: open).",
        "4. Record progress only in `progress/tasks/<TASK-ID>.yaml`: status "
        "(in_progress | blocked | ready_to_merge), commit, and handoff (max 5 lines: what now "
        "exists and where). No narrative logs; history lives in git.",
        "5. A task is finished when every acceptance criterion in the brief has a passing test "
        "(including the negative ones), lint and architecture checks pass, and "
        "`python tools/design/validate.py` passes. Then set status `ready_to_merge` and stop.",
        "6. Never merge, rebase, cherry-pick, pull or resolve conflicts. The engineer integrates.",
        "7. Never deploy to production or use production credentials.",
        "8. Reference the task id in every commit message, e.g. `P2-T3: add payment gateway mock`.",
        "",
        "## Stack",
    ]
    lines += [f"- {i.get('role', '')}: {i.get('name')} {i.get('version', '')}".rstrip()
              for i in stack.get("items") or []] or ["- not decided yet"]
    if stack.get("style"):
        lines.append(f"- architecture style: {stack['style']}")
    lines += ["", "## Global constraints"]
    glob = [f"MUST NOT build: {ng}  (non-goal)" for ng in as_list(p.get("non_goals"))]
    glob += [f"{o.get('constraint') or o.get('text')}  ({o['id']})" for o in d.items("org_constraints")]
    for dec in d.decisions:
        if dec.get("global") and dec.get("status") == "decided":
            glob += [f"{x}  ({dec['id']})" for x in as_list(dec.get("constraints"))]
    deps = d.items("approved_dependencies")
    if deps:
        glob.append("MUST NOT add runtime dependencies outside the approved list: "
                    + ", ".join(f"{x['name']}@{x['version']}" for x in deps if x.get("scope", "runtime") == "runtime")
                    + ". New ones need a change request.")
    if d.items("external_systems"):
        glob.append("MUST read every external endpoint and credential from configuration; "
                    "never hardcode them")
        glob.append("MUST NOT perform live payments, send real emails or call live external "
                    "systems outside production")
    if d.items("hotspots"):
        glob.append("Shared hotspot files (touch only when your task lists them): "
                    + ", ".join(f"`{h}`" for h in d.items("hotspots")))
    lines += [f"- {g}" for g in glob] or ["- none"]
    if d.items("conventions"):
        lines += ["", "## Conventions"] + [f"- {x}" for x in d.items("conventions")]
    return "\n".join(lines) + "\n"


# ======================================================================
# packs & briefs
# ======================================================================

def phase_scope(c: Ctx, ph):
    ucs = [c.by_id[i] for i in as_list(ph.get("use_cases")) if i in c.by_id]
    ents = [c.by_id[i] for i in as_list(ph.get("entities")) if i in c.by_id]
    comps = as_list(ph.get("components"))
    ext_ids = sorted({x["id"] for x in c.d.items("external_systems")
                      if set(as_list(x.get("used_by"))) & set(as_list(ph.get("use_cases")))})
    return ucs, ents, comps, ext_ids


def render_scope_sections(c: Ctx, env, ucs, ents, comps, ext_ids, decisions, extra_ac, out_of_scope):
    s = []
    s.append("## Out of scope (do not build)\n\n" + bullets(as_list(out_of_scope)))
    s.append("## Use cases\n\n" + ("\n\n".join(use_case_block(u) for u in ucs) or "_none_"))
    s.append("## Entities\n\n" + ("\n\n".join(entity_block(c, e) for e in ents) or "_none_"))
    ifaces = [it for it in c.d.items("integrations") if it.get("component") in comps]
    if ifaces:
        s.append("## Interfaces\n\n" + "\n\n".join(interface_block(it) for it in ifaces))
    if ext_ids and env:
        rows = []
        for x_id in ext_ids:
            x = c.by_id[x_id]
            b = (x.get("bindings") or {}).get(env) or {}
            rows.append([x_id, x.get("name"), b.get("mode"), b.get("tool", ""), b.get("config", "")])
        s.append(f"## External systems in `{env}`\n\n"
                 + table(["id", "name", "mode", "tool", "config vars"], rows))
    cons = scoped_constraints(c, decisions, ents, comps, ext_ids)
    stand = standing_constraints(c)
    s.append("## Constraints\n\n" + bullets(cons)
             + ("\n\nStanding constraints from deferred decisions (apply now):\n\n" + bullets(stand) if stand else ""))
    s.append("## Acceptance criteria (each needs a passing test)\n\n"
             + bullets(acceptance(c, ucs, ents, comps, extra_ac)))
    return s


def render_pack(c: Ctx, ph):
    ucs, ents, comps, ext_ids = phase_scope(c, ph)
    waves, notes = phase_waves(c, ph)
    s = [f"<!-- GENERATED — do not edit -->\n# Pack {ph['id']} — {ph.get('goal', '')}\n\n"
         f"_design v{c.version} · {c.hash}_"]
    s.append(f"Depends on: {', '.join(as_list(ph.get('depends_on'))) or 'none'} · "
             f"environment: {ph.get('environment', 'development')} · components: "
             f"{', '.join(comps) or '—'}")
    trows = []
    for t in ph.get("tasks") or []:
        tc = t.get("touches") or {}
        trows.append([t["id"], t.get("title"), ", ".join(as_list(t.get("depends_on"))) or "—",
                      ", ".join(as_list(tc.get("writes"))) or "—"])
    s.append("## Tasks\n\n" + table(["task", "title", "depends on", "writes"], trows)
             + "\n\nExecution waves: " + " → ".join("[" + ", ".join(w) + "]" for w in waves))
    s += render_scope_sections(c, ph.get("environment", "development"), ucs, ents, comps, ext_ids,
                               as_list(ph.get("decisions")), ph.get("acceptance_criteria"),
                               ph.get("out_of_scope"))
    return "\n\n".join(s) + "\n"


def render_brief(c: Ctx, task_id):
    ph, task = None, None
    for p, t in c.d.all_tasks():
        if t.get("id") == task_id:
            ph, task = p, t
    if task is None:
        sys.exit(f"Unknown task {task_id}")
    progress = load_progress(c.d.root)
    pucs, pents, pcomps, _ = phase_scope(c, ph)
    tc = task.get("touches") or {}
    writes = set(as_list(tc.get("writes")))
    reads = set(as_list(tc.get("reads")))
    comps = [x for x in pcomps if x in writes] or [x for x in writes if x.startswith("CMP-")]
    ent_ids = set(as_list(task.get("entities"))) | {x for x in writes | reads if x.startswith("ENT-")}
    ent_ids |= {e["id"] for e in pents if c.owner.get(e["id"]) in comps}
    ents = [c.by_id[i] for i in natural_sorted(ent_ids) if i in c.by_id]
    uc_ids = set(as_list(task.get("use_cases")))
    if not uc_ids:
        impl = {u for comp in comps for u in as_list((c.by_id.get(comp) or {}).get("implements"))}
        uc_ids = {u["id"] for u in pucs} & impl
    ucs = [c.by_id[i] for i in natural_sorted(uc_ids)]
    ext_ids = sorted({x["id"] for x in c.d.items("external_systems")
                      if set(as_list(x.get("used_by"))) & uc_ids})
    env = ph.get("environment", "development")

    s = [f"<!-- GENERATED — do not edit -->\n# Brief {task_id} — {task.get('title', '')}\n\n"
         f"_phase {ph['id']} · design v{c.version} · {c.hash}_"]
    if task.get("description"):
        s.append(task["description"])
    s.append(f"Writes: {', '.join(sorted(writes)) or '—'} · reads (via interfaces only): "
             f"{', '.join(sorted(reads)) or '—'} · migrations: {'yes' if tc.get('migrations') else 'no'}"
             + (f" · hotspots: {', '.join(as_list(tc.get('hotspots')))}" if tc.get("hotspots") else ""))
    hand = []
    for dep in as_list(task.get("depends_on")):
        p = progress.get(dep) or {}
        status = p.get("status", "planned")
        hand.append(f"**{dep}** ({status})" + ("" if status in ("done", "ready_to_merge") else
                                               " ⚠ not finished: do not start before it is done"))
        hand += [f"  - {h}" for h in as_list(p.get("handoff"))]
    s.append("## Handoff from dependencies\n\n" + ("\n".join(hand) if hand else "_no dependencies_"))
    s += render_scope_sections(c, env, ucs, ents, comps, ext_ids, as_list(ph.get("decisions")),
                               task.get("acceptance_criteria"), ph.get("out_of_scope"))
    s.append(f"Wider context, only if needed: `build/packs/{ph['id']}.md`.")
    return "\n\n".join(s) + "\n"


# ======================================================================
# human documents
# ======================================================================

def decisions_for(c: Ctx, area):
    return [x for x in c.d.decisions if x.get("area") == area]


def decision_summary_table(decs):
    return table(["id", "title", "status", "decision"],
                 [[x["id"], x.get("title"), x.get("status") + ("" if x.get("source") == "user" else f" ({x.get('source')})"),
                   x.get("decision") or ("→ " + str(x.get("deferred_to")) if x.get("status") == "deferred" else "")]
                  for x in decs])


def doc_readme(c: Ctx):
    d = c.d
    p = d.index.get("project") or {}
    b = d.index.get("baseline")
    open_ = [x for x in d.decisions if x.get("status") == "open"]
    deferred = [x for x in d.decisions if x.get("status") == "deferred"] + \
        [x for x in d.items("external_systems") if x.get("status") == "deferred"]
    s = [c.header(f"{p.get('name', 'Design')} — overview")]
    s.append(p.get("summary", ""))
    s.append(f"**Status:** " + (f"baselined at v{b.get('version')} ({b.get('date')})" if b else "in design (no baseline yet)"))
    s.append("## Documents\n\n" + bullets([
        "[Scope](scope.md)", "[Actors and use cases](use-cases.md)",
        "[External systems](external-systems.md)", "[Data](data.md)", "[Security](security.md)",
        "[Architecture](architecture.md)", "[Integrations](integrations.md)",
        "[Operations](operations.md)", "[Phases](phases.md)", "[All decisions](decisions.md)"]))
    s.append("## Context\n\n" + mermaid(diagram_context(c)))
    s.append("## Open decisions\n\n" + bullets([f"{x['id']} — {x.get('title')}" for x in open_]))
    s.append("## Deferred\n\n" + bullets([f"{x['id']} — {x.get('title') or x.get('name')} → "
                                         f"{x.get('deferred_to')}" for x in deferred]))
    return "\n\n".join(s) + "\n"


def doc_scope(c: Ctx):
    p = c.d.index.get("project") or {}
    sc = p.get("scale") or {}
    s = [c.header("Scope")]
    s.append(f"**Category:** {p.get('category', '—')}")
    s.append("## Problem\n\n" + str(p.get("problem", "—")))
    s.append("## Goals\n\n" + bullets(as_list(p.get("goals"))))
    s.append("## Non-goals\n\n" + bullets(as_list(p.get("non_goals"))))
    s.append("## Scale\n\n" + table(["dimension", "expected"], [[k, v] for k, v in sc.items()]))
    s.append("## Project constraints\n\n" + bullets(as_list(p.get("constraints"))))
    s.append("## Environments\n\n" + bullets(c.d.environments))
    s.append("## Decisions\n\n" + decision_summary_table(decisions_for(c, "scope")))
    return "\n\n".join(s) + "\n"


def doc_use_cases(c: Ctx):
    d = c.d
    s = [c.header("Actors and use cases")]
    s.append("## Actors\n\n" + table(["id", "name", "kind", "authenticated", "count", "description"],
             [[a["id"], a.get("name"), a.get("kind"), a.get("authenticated"), a.get("count"),
               a.get("description")] for a in d.items("actors")]))
    s.append("## Use case diagram\n\n" + mermaid(diagram_use_cases(c)))
    s.append("## Use cases")
    for u in d.items("use_cases"):
        block = use_case_block(u)
        if u.get("acceptance_criteria"):
            block += "\n\nAcceptance criteria:\n" + bullets(u["acceptance_criteria"])
        if u.get("user_statement"):
            block += f"\n\n> Engineer: “{u['user_statement']}”"
        s.append(block)
    return "\n\n".join(s) + "\n"


def doc_external(c: Ctx):
    d = c.d
    envs = d.environments
    s = [c.header("External systems and constraints")]
    s.append("## Context diagram\n\n" + mermaid(diagram_context(c)))
    for x in d.items("external_systems"):
        block = [f"### {x['id']} — {x.get('name')}{deferred_marker(x)}",
                 f"{x.get('purpose', '')} · direction: {x.get('direction')} · owner: {x.get('owner')}"
                 + (f" · vendor: {x.get('vendor')}" if x.get("vendor") else "")]
        if x.get("imposed"):
            block.append("Imposed:\n" + bullets(x["imposed"]))
        if x.get("requirements"):
            block.append("Requirements for the choice:\n" + bullets(x["requirements"]))
        if x.get("prepare_now"):
            block.append("Prepare now:\n" + bullets(x["prepare_now"]))
        b = x.get("bindings") or {}
        block.append(table(["environment", "mode", "tool", "config vars"],
                           [[e, (b.get(e) or {}).get("mode"), (b.get(e) or {}).get("tool", ""),
                             (b.get(e) or {}).get("config", "")] for e in envs]))
        s.append("\n\n".join(block))
    s.append("## Organisational constraints\n\n" + table(["id", "constraint"],
             [[o["id"], o.get("text")] for o in d.items("org_constraints")]))
    s.append("## Decisions\n\n" + decision_summary_table(decisions_for(c, "external")))
    return "\n\n".join(s) + "\n"


def doc_data(c: Ctx):
    d = c.d
    ents = d.items("entities")
    s = [c.header("Data")]
    s.append("## Entity–relationship diagram\n\n" + (mermaid(diagram_er(c, ents)) if ents else "_none_"))
    for e in ents:
        block = entity_block(c, e)
        if e.get("states"):
            block += "\n\n" + mermaid(diagram_states(e))
        s.append(block)
    s.append("## Decisions\n\n" + decision_summary_table(decisions_for(c, "data")))
    return "\n\n".join(s) + "\n"


def doc_security(c: Ctx):
    d = c.d
    ucs = d.items("use_cases")
    roles = d.items("roles")
    s = [c.header("Security and compliance")]
    s.append("## Trust boundaries\n\n" + mermaid(diagram_context(c, trust=True)))
    s.append("## Roles\n\n" + table(["id", "from actor", "MFA"],
             [[r["id"], r.get("from_actor"), r.get("mfa", "—")] for r in roles]))
    rows = []
    for u in ucs:
        row = [f"{u['id']} {u.get('title', '')}"]
        for r in roles:
            perms = as_list(r.get("permissions"))
            row.append("✓" if (u["id"] in perms or "UC-*" in perms) else "")
        rows.append(row)
    s.append("## Permission matrix\n\n" + table(["use case"] + [r["id"] for r in roles], rows))
    s.append("## Ownership rules\n\n" + table(["id", "entity", "rule", "applies to"],
             [[o["id"], o.get("entity"), o.get("rule"), ", ".join(as_list(o.get("applies_to")))]
              for o in d.items("ownership")]))
    sens = []
    for e in d.items("entities"):
        for a in e.get("attributes") or []:
            if a.get("sensitivity") in ("personal", "sensitive"):
                sens.append([e["id"], a["name"], a["sensitivity"], e.get("retention", "—")])
    s.append("## Personal and sensitive data\n\n" + table(["entity", "field", "class", "retention"], sens))
    s.append("## Decisions\n\n" + decision_summary_table(decisions_for(c, "security")))
    return "\n\n".join(s) + "\n"


def doc_architecture(c: Ctx):
    d = c.d
    stack = d.model.get("stack") or {}
    s = [c.header("Architecture and stack")]
    s.append(f"**Style:** {stack.get('style', '—')} · **database:** {stack.get('database', '—')}")
    s.append("## Containers\n\n" + mermaid(diagram_containers(c)))
    s.append("## Stack\n\n" + table(["role", "technology", "version", "why"],
             [[i.get("role"), i.get("name"), i.get("version"), i.get("why", "")] for i in stack.get("items") or []]))
    if d.items("components"):
        s.append("## Components\n\n" + mermaid(diagram_components(c)))
        s.append(table(["id", "responsibility", "owns", "implements", "may depend on"],
                       [[x["id"], x.get("responsibility"), ", ".join(as_list(x.get("owns"))),
                         ", ".join(as_list(x.get("implements"))), ", ".join(as_list(x.get("may_depend_on")))]
                        for x in d.items("components")]))
    s.append("## Approved dependencies\n\n" + table(["name", "version", "scope", "provider-specific"],
             [[x.get("name"), x.get("version"), x.get("scope", "runtime"), x.get("provider_specific", False)]
              for x in d.items("approved_dependencies")]))
    s.append("## Conventions\n\n" + bullets(d.items("conventions")))
    s.append("## Hotspots (shared files)\n\n" + bullets([f"`{h}`" for h in d.items("hotspots")]))
    s.append("## Decisions\n\n" + decision_summary_table(decisions_for(c, "architecture")))
    return "\n\n".join(s) + "\n"


def doc_integrations(c: Ctx):
    d = c.d
    s = [c.header("Integrations")]
    for it in d.items("integrations"):
        s.append(interface_block(it))
    flows = d.items("flows")
    if flows:
        s.append("## Flows")
        for fl in flows:
            s.append(f"### {fl['id']} — {fl.get('title', '')}\n\n" + mermaid(fl.get("mermaid", "")))
    s.append("## Decisions\n\n" + decision_summary_table(decisions_for(c, "integrations")))
    return "\n\n".join(s) + "\n"


def doc_operations(c: Ctx):
    d = c.d
    ops = d.model.get("operations") or {}
    s = [c.header("Deployment and operations")]
    for env in d.environments:
        s.append(f"## Environment: {env}\n\n" + mermaid(diagram_deployment(c, env)))
    targets = [[k, ops.get(k)] for k in ("rpo", "rto", "availability", "alerts_to", "release") if ops.get(k)]
    s.append("## Targets\n\n" + table(["target", "value"], targets))
    if ops.get("backups"):
        s.append("## Backups\n\n" + table(["setting", "value"], [[k, v] for k, v in ops["backups"].items()]))
    if (ops.get("ci") or {}).get("stages"):
        s.append("## CI/CD stages\n\n" + "\n".join(f"{i}. {x}" for i, x in enumerate(ops["ci"]["stages"], 1)))
    hosting = next((x for x in d.decisions if x.get("topic") == "hosting"), None)
    if hosting:
        s.append(f"## Hosting — {hosting['id']}{deferred_marker(hosting)}\n\n"
                 + (hosting.get("decision") or "Not decided yet.")
                 + ("\n\n### Infrastructure requirements\n\n" + bullets(as_list(hosting.get("requirements")))
                    if hosting.get("requirements") else ""))
    s.append("## Decisions\n\n" + decision_summary_table(decisions_for(c, "operations")))
    return "\n\n".join(s) + "\n"


def doc_phases(c: Ctx):
    d = c.d
    s = [c.header("Implementation phases")]
    if not d.phases:
        return "\n\n".join(s + ["_No phases defined yet._"]) + "\n"
    s.append("## Phase map\n\n" + mermaid(diagram_phases(c)))
    pw, pnotes = project_waves(c)
    s.append("## Phases that can run in parallel\n\n" + mermaid(diagram_waves(pw, "Wave"))
             + ("\n\n" + bullets(pnotes) if pnotes else ""))
    cov = []
    for u in d.items("use_cases"):
        where = [p["id"] for p in d.phases if u["id"] in as_list(p.get("use_cases"))]
        cov.append([u["id"], u.get("title"), u.get("priority"), ", ".join(where) or "—"])
    s.append("## Use case coverage\n\n" + table(["use case", "title", "priority", "phases"], cov))
    for ph in d.phases:
        waves, notes = phase_waves(c, ph)
        block = [f"## {ph['id']} — {ph.get('goal', '')}",
                 f"Depends on: {', '.join(as_list(ph.get('depends_on'))) or 'none'} · "
                 f"use cases: {', '.join(as_list(ph.get('use_cases'))) or '—'} · "
                 f"environment: {ph.get('environment', 'development')}",
                 table(["task", "title", "depends on", "writes", "migrations"],
                       [[t["id"], t.get("title"), ", ".join(as_list(t.get("depends_on"))) or "—",
                         ", ".join(as_list((t.get("touches") or {}).get("writes"))) or "—",
                         "yes" if (t.get("touches") or {}).get("migrations") else ""]
                        for t in ph.get("tasks") or []]),
                 "Task waves:\n\n" + mermaid(diagram_waves(waves, "Wave"))]
        if notes:
            block.append("Serialised:\n" + bullets(notes))
        block.append("Out of scope:\n" + bullets(as_list(ph.get("out_of_scope"))))
        s.append("\n\n".join(block))
    return "\n\n".join(s) + "\n"


def doc_decisions(c: Ctx):
    s = [c.header("Decision record")]
    for area in ["scope", "actors", "external", "data", "security", "architecture",
                 "integrations", "operations", "phases"]:
        decs = decisions_for(c, area)
        if not decs:
            continue
        s.append(f"## {area.capitalize()}")
        for x in decs:
            block = [f"### {x['id']} — {x.get('title')} · {x.get('status')}"
                     + (f" · source: {x.get('source')}" if x.get("source") != "user" else "")]
            if x.get("user_statement"):
                block.append(f"> Engineer: “{x['user_statement']}”")
            if x.get("decision"):
                block.append(f"**Decision:** {x['decision']}")
            if x.get("rationale"):
                block.append(f"**Why:** {x['rationale']}")
            if x.get("alternatives_rejected"):
                alts = []
                for a in as_list(x["alternatives_rejected"]):
                    alts.append(", ".join(f"{k}: {v}" for k, v in a.items()) if isinstance(a, dict) else str(a))
                block.append("**Rejected:**\n" + bullets(alts))
            if x.get("status") == "deferred":
                block.append(f"**Deferred to:** {x.get('deferred_to')} · trigger: {x.get('trigger', '—')}")
                if x.get("requirements"):
                    block.append("**Requirements:**\n" + bullets(as_list(x["requirements"])))
            cons = as_list(x.get("constraints")) + as_list(x.get("prepare_now"))
            if cons:
                block.append("**Constraints for implementation:**\n" + bullets([f"`{k}`" for k in cons]))
            meta = []
            if x.get("affects"):
                meta.append("affects " + ", ".join(as_list(x["affects"])))
            if x.get("supersedes"):
                meta.append("supersedes " + x["supersedes"])
            if x.get("since"):
                meta.append("since v" + str(x["since"]))
            if meta:
                block.append("_" + " · ".join(meta) + "_")
            s.append("\n\n".join(block))
    return "\n\n".join(s) + "\n"


DOCS = {
    "README.md": doc_readme, "scope.md": doc_scope, "use-cases.md": doc_use_cases,
    "external-systems.md": doc_external, "data.md": doc_data, "security.md": doc_security,
    "architecture.md": doc_architecture, "integrations.md": doc_integrations,
    "operations.md": doc_operations, "phases.md": doc_phases, "decisions.md": doc_decisions,
}


# ======================================================================
# main
# ======================================================================

def write(path: Path, text: str):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(text, encoding="utf-8")


def build_all(c: Ctx, out: Path):
    for sub in ("docs", "packs"):
        if (out / sub).exists():
            shutil.rmtree(out / sub)
    for name, fn in DOCS.items():
        write(out / "docs" / name, fn(c))
    warnings = []
    for ph in c.d.phases:
        text = render_pack(c, ph)
        write(out / "packs" / f"{ph['id']}.md", text)
        tok = estimate_tokens(text)
        if tok > c.pack_budget:
            warnings.append(f"pack {ph['id']} is ~{tok} tokens (budget {c.pack_budget}); split the phase")
    write(out / "CLAUDE.design.md", claude_rules(c))
    return warnings


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("command", nargs="?", default="all", choices=["all", "brief"])
    ap.add_argument("task", nargs="?")
    ap.add_argument("--root", type=Path)
    ap.add_argument("--out", type=Path, help="output directory (default: <root>/build)")
    args = ap.parse_args()

    root = args.root or find_root()
    c = Ctx(load_design(root))
    out = args.out or root / "build"

    if args.command == "brief":
        if not args.task:
            sys.exit("usage: build.py brief <TASK-ID>")
        text = render_brief(c, args.task)
        path = out / "briefs" / f"{args.task}.md"
        write(path, text)
        tok = estimate_tokens(text)
        print(f"wrote {path.relative_to(root) if path.is_relative_to(root) else path} (~{tok} tokens)")
        if tok > c.brief_budget:
            print(f"warning: brief exceeds budget {c.brief_budget}; consider splitting the task")
        return

    warnings = build_all(c, out)
    print(f"built design v{c.version} ({c.hash}) into {out}")
    for w in warnings:
        print("warning:", w)


if __name__ == "__main__":
    main()
