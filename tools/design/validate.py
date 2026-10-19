#!/usr/bin/env python3
"""Validate the design under design/.

Usage:
    python tools/design/validate.py              # normal check
    python tools/design/validate.py --baseline   # strict check required before a baseline

Exit code 1 if there are errors. Warnings never fail the run, except that
in --baseline mode the warnings marked [baseline] become errors.
"""
from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

sys.dont_write_bytecode = True  # keep tools/design/ free of __pycache__
sys.path.insert(0, str(Path(__file__).resolve().parent))
from design_lib import (AREAS, ID_RE, SOURCES, STATUSES, TASK_STATUSES, as_list,  # noqa: E402
                        find_root, load_design, load_progress, load_semantic_types,
                        parse_duration, read_yaml, SEMANTIC_TYPES_FILE, topo_cycles)


class Report:
    def __init__(self, baseline: bool):
        self.baseline = baseline
        self.errors, self.warnings = [], []

    def error(self, where, msg):
        self.errors.append(f"{where}: {msg}")

    def warn(self, where, msg):
        self.warnings.append(f"{where}: {msg}")

    def gate(self, where, msg):
        """A problem that is fine during design but blocks a baseline."""
        (self.error if self.baseline else self.warn)(where, f"[baseline] {msg}")


def check(d, r: Report):
    for e in d.load_errors:
        r.error("load", e)
    by_id = d.by_id()
    envs = d.environments
    sem = load_semantic_types()
    heur = read_yaml(SEMANTIC_TYPES_FILE).get("name_heuristics", [])

    def exists(ref, where, kinds=None):
        if ref is None:
            return
        if isinstance(ref, str) and ref.endswith("-*"):
            return
        if ref not in by_id:
            r.error(where, f"reference to unknown id '{ref}'")
        elif kinds and not any(str(ref).startswith(k + "-") for k in kinds):
            r.error(where, f"'{ref}' is not a {'/'.join(kinds)}")

    # ---- index -------------------------------------------------------
    idx = d.index
    proj = idx.get("project") or {}
    for f in ("name", "summary", "category"):
        if not proj.get(f):
            r.gate("index.yaml", f"project.{f} is missing")
    if not proj.get("non_goals"):
        r.gate("index.yaml", "project.non_goals is empty; non-goals are the main guard "
                             "against scope drift")
    if not envs:
        r.error("index.yaml", "environments list is empty")
    if not idx.get("settings_confirmed"):
        r.gate("index.yaml", "settings_confirmed is not true (language, environments, "
                             "parallelism defaults have not been confirmed)")

    # ---- unique ids --------------------------------------------------
    seen = {}
    def register(i, where):
        if not i:
            r.error(where, "item without id")
            return
        if not ID_RE.match(str(i)):
            r.error(where, f"id '{i}' does not match PREFIX-name")
        if i in seen:
            r.error(where, f"duplicate id '{i}' (also in {seen[i]})")
        seen[i] = where
    for dec in d.decisions:
        register(dec.get("id"), d.origin.get(dec.get("id"), "decisions"))
    for key in ("actors", "use_cases", "external_systems", "org_constraints", "entities",
                "roles", "ownership", "components", "integrations", "flows"):
        for it in d.items(key):
            register(it.get("id"), d.origin.get(it.get("id"), key))
    for ph in d.phases:
        register(ph.get("id"), d.origin.get(ph.get("id"), "phases"))
        for t in ph.get("tasks") or []:
            register(t.get("id"), f"{ph.get('id')}/tasks")

    # ---- provenance (any item may carry source/confirmed) ------------
    def provenance(it, where):
        src = it.get("source")
        if src is None:
            return
        if src not in SOURCES:
            r.error(where, f"source '{src}' not in {sorted(SOURCES)}")
        if src in ("inferred", "default") and not it.get("confirmed"):
            r.gate(where, f"source={src} but not confirmed by the engineer")
    for i, it in by_id.items():
        if isinstance(it, dict):
            provenance(it, i)

    # ---- decisions ---------------------------------------------------
    topics = {}
    for dec in d.decisions:
        w = dec.get("id", "decision")
        if dec.get("area") not in AREAS:
            r.error(w, f"area '{dec.get('area')}' not in {AREAS}")
        st = dec.get("status")
        if st not in STATUSES:
            r.error(w, f"status '{st}' not in {sorted(STATUSES)}")
        if not dec.get("title"):
            r.error(w, "title is missing")
        if st == "decided" and not dec.get("decision"):
            r.error(w, "status=decided but 'decision' is empty")
        if dec.get("source") is None:
            r.error(w, "source is missing (user | inferred | default)")
        if dec.get("source") == "user" and not dec.get("user_statement"):
            r.warn(w, "source=user but user_statement is empty; keep the engineer's own words")
        if st == "deferred":
            if not dec.get("deferred_to"):
                r.error(w, "deferred without deferred_to")
            if not dec.get("prepare_now"):
                r.warn(w, "deferred without prepare_now; nothing keeps the door open")
        if st == "open":
            r.warn(w, "decision is still open")
        if dec.get("supersedes"):
            exists(dec["supersedes"], w)
            old = by_id.get(dec["supersedes"])
            if old and old.get("status") != "superseded":
                r.error(w, f"supersedes {dec['supersedes']} but that decision is not "
                           "marked superseded")
        for c in as_list(dec.get("constraints")) + as_list(dec.get("prepare_now")):
            if not re.match(r"^(MUST|MUST NOT|SHOULD|SHOULD NOT)\b", str(c)):
                r.warn(w, f"constraint should start with MUST/MUST NOT/SHOULD: '{c}'")
        for a in as_list(dec.get("affects")):
            exists(a, w)
        if dec.get("topic") and st != "superseded":
            topics[dec["topic"]] = dec

    # ---- actors / use cases -----------------------------------------
    for a in d.items("actors"):
        if a.get("kind") not in ("human", "system"):
            r.error(a.get("id"), "kind must be human or system")
    uc_ids = {u["id"] for u in d.items("use_cases") if u.get("id")}
    for u in d.items("use_cases"):
        w = u.get("id")
        for a in as_list(u.get("actors")):
            exists(a, w, ["ACT"])
        if u.get("priority") not in ("must", "should", "could"):
            r.error(w, "priority must be must | should | could")
        if u.get("priority") == "must":
            if not u.get("acceptance_criteria"):
                r.gate(w, "must-priority use case without acceptance_criteria")
            if not u.get("main_flow"):
                r.gate(w, "must-priority use case without main_flow")

    # ---- external systems -------------------------------------------
    for x in d.items("external_systems"):
        w = x.get("id")
        deferred = x.get("status") == "deferred"
        if deferred and not x.get("deferred_to"):
            r.error(w, "deferred external system without deferred_to")
        if deferred and not x.get("requirements"):
            r.warn(w, "deferred external system without requirements for the later choice")
        b = x.get("bindings") or {}
        for env in envs:
            if env not in b:
                r.error(w, f"no binding for environment '{env}'")
            elif (b[env] or {}).get("mode") == "tbd" and not deferred:
                r.error(w, f"binding for '{env}' is tbd but the system is not deferred")
        for env in b:
            if env not in envs:
                r.error(w, f"binding for unknown environment '{env}'")
        for env, bind in b.items():
            mode = (bind or {}).get("mode")
            if mode == "live" and env != "production":
                r.warn(w, f"live binding outside production ('{env}')")
            for k in ("config",):
                if bind and bind.get(k) and re.search(r"[:=]|\bsk_|password", str(bind[k])):
                    r.error(w, f"bindings.{env}.{k} looks like a secret value; store only "
                               "variable names")
        for uc in as_list(x.get("used_by")):
            exists(uc, w, ["UC"])

    # ---- entities ----------------------------------------------------
    used_entities = set()
    personal_entities = set()
    money_used = False
    tax_used = False
    for e in d.items("entities"):
        w = e.get("id")
        if not str(w).startswith("ENT-"):
            r.error(w, "entity ids start with ENT-")
        attrs = e.get("attributes") or []
        if not attrs:
            r.warn(w, "entity has no attributes")
        names = set()
        for at in attrs:
            n, t = at.get("name"), at.get("type")
            aw = f"{w}.{n}"
            if n in names:
                r.error(aw, "duplicate attribute")
            names.add(n)
            if t not in sem:
                r.error(aw, f"unknown type '{t}' (see semantic-types.yaml)")
            if at.get("ref"):
                exists(at["ref"], aw, ["ENT"])
            if at.get("sensitivity") not in (None, "public", "internal", "personal", "sensitive"):
                r.error(aw, "sensitivity must be public | internal | personal | sensitive")
            if at.get("sensitivity") in ("personal", "sensitive"):
                personal_entities.add(w)
            if t == "money":
                money_used = True
            if t == "tax_rate":
                tax_used = True
            if at.get("type_confirmed"):
                continue
            for h in heur:
                if n and re.search(h["pattern"], str(n), re.I) and t not in h["expected"]:
                    r.error(aw, f"name suggests {h['expected']} but type is '{t}'; fix the "
                                "type or set type_confirmed: true")
                    break
        for rel in e.get("relations") or []:
            exists(rel.get("to"), w, ["ENT"])
            if rel.get("type") not in ("one-to-one", "one-to-many", "many-to-many", "many-to-one"):
                r.error(w, f"relation type '{rel.get('type')}' is invalid")
        st = e.get("states")
        if st:
            states = set()
            for tr in st.get("transitions") or []:
                m = re.fullmatch(r"\s*(\w+)\s*->\s*(\w+)\s*", str(tr))
                if not m:
                    r.error(w, f"transition '{tr}' must look like 'a -> b'")
                    continue
                states.update(m.groups())
            if st.get("initial") and st["initial"] not in states:
                r.error(w, "initial state does not appear in any transition")
        uses = as_list(e.get("used_by"))
        for uc in uses:
            exists(uc, w, ["UC"])
        if uses:
            used_entities.add(w)
        else:
            r.warn(w, "entity is not used by any use case")
        if w in personal_entities and not e.get("retention"):
            r.gate(w, "entity holds personal data but has no retention policy")

    for topic in (["money_representation", "currency", "rounding"] if money_used else []) + \
                 (["rounding"] if tax_used else []):
        if topic not in topics:
            r.gate("data", f"money/tax attributes exist but there is no decision with "
                           f"topic '{topic}'")

    # ---- security ----------------------------------------------------
    roles = d.items("roles")
    permitted = set()
    for ro in roles:
        w = ro.get("id")
        exists(ro.get("from_actor"), w, ["ACT"])
        for p in as_list(ro.get("permissions")):
            if p == "UC-*":
                permitted |= uc_ids
            else:
                exists(p, w, ["UC"])
                permitted.add(p)
        if not ro.get("permissions"):
            r.warn(w, "role has no permissions")
    if roles:
        for uc in sorted(uc_ids - permitted):
            r.error(uc, "no role is permitted to perform this use case")
    owned_rule = set()
    for o in d.items("ownership"):
        exists(o.get("entity"), o.get("id"), ["ENT"])
        for ro in as_list(o.get("applies_to")):
            exists(ro, o.get("id"), ["ROLE"])
        owned_rule.add(o.get("entity"))
    if roles:
        for e in d.items("entities"):
            if e["id"] in personal_entities and e["id"] not in owned_rule \
                    and not e.get("ownership_not_needed"):
                r.gate(e["id"], "personal data without an ownership rule (or "
                                "ownership_not_needed: <reason>)")

    # ---- architecture ------------------------------------------------
    comps = d.items("components")
    owner = {}
    implemented = set()
    for c in comps:
        w = c.get("id")
        for e in as_list(c.get("owns")):
            exists(e, w, ["ENT"])
            if e in owner:
                r.error(w, f"{e} is already owned by {owner[e]}")
            owner[e] = w
        for uc in as_list(c.get("implements")):
            exists(uc, w, ["UC"])
            implemented.add(uc)
        for dep in as_list(c.get("may_depend_on")):
            exists(dep, w, ["CMP"])
    if comps:
        for e in d.items("entities"):
            if e["id"] not in owner:
                r.error(e["id"], "entity is not owned by any component")
        for uc in sorted(uc_ids - implemented):
            r.error(uc, "use case is not implemented by any component")
        graph = {c["id"]: as_list(c.get("may_depend_on")) for c in comps}
        for cyc in topo_cycles(graph):
            r.error("components", "dependency cycle: " + " -> ".join(cyc))

    deps = d.items("approved_dependencies")
    hosting = topics.get("hosting")
    for dep in deps:
        if not dep.get("version") or str(dep.get("version")).lower() in ("latest", "*"):
            r.error(f"dependency {dep.get('name')}", "pin an exact version")
        if dep.get("provider_specific") and hosting and hosting.get("status") == "deferred":
            r.error(f"dependency {dep.get('name')}",
                    "provider-specific dependency while hosting is deferred")
    stack = d.model.get("stack") or {}
    for item in stack.get("items") or []:
        if not item.get("version") or str(item.get("version")).lower() == "latest":
            r.error(f"stack {item.get('name')}", "pin a version")

    # ---- integrations ------------------------------------------------
    integrated = set()
    for it in d.items("integrations"):
        w = it.get("id")
        exists(it.get("external"), w, ["EXT"])
        exists(it.get("component"), w, ["CMP"])
        integrated.add(it.get("external"))
        if not (it.get("interface") or {}).get("operations"):
            r.gate(w, "integration without interface operations")
        fail = it.get("failure") or {}
        if not fail.get("timeout"):
            r.gate(w, "failure.timeout missing")
        if not fail.get("when_unavailable"):
            r.gate(w, "failure.when_unavailable missing (this is a business decision)")
        for inb in it.get("inbound") or []:
            if not inb.get("verify_signature"):
                r.warn(w, f"inbound '{inb.get('webhook')}' does not verify signatures")
            if not inb.get("idempotent"):
                r.warn(w, f"inbound '{inb.get('webhook')}' is not idempotent")
    if d.items("integrations"):
        for x in d.items("external_systems"):
            if x["id"] not in integrated:
                r.gate(x["id"], "external system has no integration design (area 7)")

    # ---- flows -------------------------------------------------------
    for fl in d.items("flows"):
        for p in as_list(fl.get("participants")):
            exists(p, fl.get("id"))
        if not fl.get("mermaid"):
            r.error(fl.get("id"), "flow without mermaid source")

    # ---- operations --------------------------------------------------
    ops = d.model.get("operations") or {}
    if ops:
        rpo = parse_duration(ops.get("rpo"))
        freq = parse_duration((ops.get("backups") or {}).get("frequency"))
        if ops.get("rpo") and rpo is None:
            r.error("operations", f"cannot parse rpo '{ops.get('rpo')}' (use 15m, 1h, 1d)")
        if rpo and freq and freq > rpo:
            r.error("operations", f"backups every {ops['backups']['frequency']} cannot meet "
                                  f"RPO {ops['rpo']}")
        if personal_entities and "production" in envs and not ops.get("backups"):
            r.gate("operations", "production holds personal data but no backup policy")

    # ---- phases & tasks ---------------------------------------------
    phase_graph, in_phase = {}, set()
    for ph in d.phases:
        w = ph.get("id")
        if not str(w).startswith("PHASE-"):
            r.error(w, "phase ids start with PHASE-")
        if not ph.get("goal"):
            r.error(w, "goal missing")
        for key, kinds in (("use_cases", ["UC"]), ("entities", ["ENT"]),
                           ("components", ["CMP"]), ("depends_on", ["PHASE"])):
            for ref in as_list(ph.get(key)):
                exists(ref, w, kinds)
        for ref in as_list(ph.get("decisions")):
            exists(ref, w)
            dec = by_id.get(ref)
            if dec and dec.get("status") == "open" and not ph.get("depends_on"):
                r.gate(w, f"first-wave phase depends on open decision {ref}")
        if ph.get("environment") and ph["environment"] not in envs:
            r.error(w, f"unknown environment '{ph['environment']}'")
        in_phase |= set(as_list(ph.get("use_cases")))
        phase_graph[w] = as_list(ph.get("depends_on"))
        tasks = ph.get("tasks") or []
        if not tasks:
            r.warn(w, "phase has no tasks")
        task_ids = {t.get("id") for t in tasks}
        tgraph = {}
        for t in tasks:
            tw = f"{w}/{t.get('id')}"
            tgraph[t.get("id")] = as_list(t.get("depends_on"))
            for dep in as_list(t.get("depends_on")):
                if dep not in task_ids:
                    dep_obj = by_id.get(dep)
                    if dep_obj is None:
                        r.error(tw, f"depends on unknown task '{dep}'")
            touches = t.get("touches") or {}
            for ref in as_list(touches.get("writes")) + as_list(touches.get("reads")):
                exists(ref, tw, ["CMP", "ENT"])
            for ref in as_list(t.get("use_cases")):
                exists(ref, tw, ["UC"])
        for cyc in topo_cycles(tgraph):
            r.error(w, "task dependency cycle: " + " -> ".join(cyc))
    for cyc in topo_cycles(phase_graph):
        r.error("phases", "phase dependency cycle: " + " -> ".join(cyc))
    if d.phases:
        for u in d.items("use_cases"):
            if u.get("priority") == "must" and u["id"] not in in_phase:
                r.gate(u["id"], "must-priority use case is not assigned to any phase")
    for dec in d.decisions:
        if dec.get("status") == "deferred" and dec.get("deferred_to", "").startswith("PHASE-"):
            exists(dec["deferred_to"], dec["id"], ["PHASE"])
    for x in d.items("external_systems"):
        if x.get("status") == "deferred" and str(x.get("deferred_to", "")).startswith("PHASE-"):
            exists(x["deferred_to"], x["id"], ["PHASE"])

    # ---- progress (read-only sanity) ---------------------------------
    for tid, p in load_progress(d.root).items():
        if tid not in by_id:
            r.warn(f"progress/{tid}", "progress for a task that is not in the design")
        if p.get("status") not in TASK_STATUSES:
            r.error(f"progress/{tid}", f"status '{p.get('status')}' not in {sorted(TASK_STATUSES)}")


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--baseline", action="store_true", help="strict mode for a baseline")
    ap.add_argument("--root", type=Path, default=None)
    ap.add_argument("--quiet", action="store_true", help="only print errors")
    args = ap.parse_args()

    root = args.root or find_root()
    d = load_design(root)
    rep = Report(args.baseline)
    check(d, rep)

    if rep.errors:
        print(f"ERRORS ({len(rep.errors)})")
        for e in rep.errors:
            print("  ✗", e)
    if rep.warnings and not args.quiet:
        print(f"WARNINGS ({len(rep.warnings)})")
        for w in rep.warnings:
            print("  !", w)
    mode = "baseline" if args.baseline else "design"
    if not rep.errors:
        print(f"OK ({mode} mode): {len(rep.warnings)} warning(s)")
    sys.exit(1 if rep.errors else 0)


if __name__ == "__main__":
    main()
