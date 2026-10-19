"""Shared helpers for the app-design tools.

Every script loads the design through `load_design()`, so all tools see the
same merged view of design/index.yaml, design/decisions/*.yaml,
design/model/*.yaml and design/phases/*.yaml.

Only dependency: PyYAML.
"""
from __future__ import annotations

import hashlib
import re
import sys
from dataclasses import dataclass, field
from pathlib import Path

try:
    import yaml
except ImportError:  # pragma: no cover
    sys.exit("PyYAML is required: pip install pyyaml")

TOOLS_DIR = Path(__file__).resolve().parent
SEMANTIC_TYPES_FILE = TOOLS_DIR / "semantic-types.yaml"

MODEL_KEYS = {
    "actors", "use_cases", "external_systems", "org_constraints", "entities",
    "roles", "ownership", "components", "stack", "approved_dependencies",
    "hotspots", "conventions", "integrations", "operations", "flows",
}
LIST_KEYS = MODEL_KEYS - {"stack", "operations"}

AREAS = ["scope", "actors", "external", "data", "security", "architecture",
         "integrations", "operations", "phases"]
STATUSES = {"open", "decided", "deferred", "superseded"}
SOURCES = {"user", "inferred", "default"}
TASK_STATUSES = {"planned", "in_progress", "blocked", "ready_to_merge", "done", "needs_review"}

ID_RE = re.compile(r"^[A-Z][A-Z0-9]*-[A-Za-z0-9_*]+(-[A-Za-z0-9_]+)*$")
REF_RE = re.compile(r"\b(?:ACT|UC|EXT|ORG|ENT|ROLE|OWN|CMP|INT|FLOW|PHASE|P\d+|"
                    r"SCOPE|DATA|SEC|ARCH|OPS|PERF|DEC|CR)-[A-Za-z0-9_]+(?:-[A-Za-z0-9_]+)*\b")


def find_root(start: Path | None = None) -> Path:
    """Walk up from `start` until a directory containing design/index.yaml."""
    p = (start or Path.cwd()).resolve()
    for cand in [p, *p.parents]:
        if (cand / "design" / "index.yaml").exists():
            return cand
    sys.exit("No design/index.yaml found in this directory or its parents. "
             "Run init_project.py first.")


def read_yaml(path: Path):
    try:
        with open(path, encoding="utf-8") as fh:
            return yaml.safe_load(fh) or {}
    except yaml.YAMLError as exc:
        sys.exit(f"YAML error in {path}: {exc}")


@dataclass
class Design:
    root: Path
    index: dict
    decisions: list = field(default_factory=list)
    model: dict = field(default_factory=dict)
    phases: list = field(default_factory=list)
    origin: dict = field(default_factory=dict)  # id -> file it came from
    load_errors: list = field(default_factory=list)

    # ---- convenience -------------------------------------------------
    def items(self, key):
        return self.model.get(key) or []

    @property
    def environments(self):
        return list(self.index.get("environments") or [])

    def all_tasks(self):
        for ph in self.phases:
            for t in ph.get("tasks") or []:
                yield ph, t

    def by_id(self):
        out = {}
        for d in self.decisions:
            out[d.get("id")] = d
        for key in LIST_KEYS:
            for it in self.items(key):
                if isinstance(it, dict) and it.get("id"):
                    out[it["id"]] = it
        for ph in self.phases:
            out[ph.get("id")] = ph
            for t in ph.get("tasks") or []:
                out[t.get("id")] = t
        return out

    def stack_database(self):
        stack = self.model.get("stack") or {}
        db = stack.get("database")
        if isinstance(db, dict):
            db = db.get("name")
        return (db or "").lower()


def load_design(root: Path) -> Design:
    ddir = root / "design"
    d = Design(root=root, index=read_yaml(ddir / "index.yaml"))

    for f in sorted((ddir / "decisions").glob("*.yaml")):
        data = read_yaml(f)
        for dec in data.get("decisions") or []:
            d.decisions.append(dec)
            d.origin[dec.get("id")] = f.relative_to(root).as_posix()

    for f in sorted((ddir / "model").glob("*.yaml")):
        data = read_yaml(f)
        for key, value in data.items():
            if key not in MODEL_KEYS:
                d.load_errors.append(f"{f.name}: unknown top-level key '{key}'")
                continue
            if key in LIST_KEYS:
                d.model.setdefault(key, [])
                d.model[key].extend(value or [])
                for it in value or []:
                    if isinstance(it, dict) and it.get("id"):
                        d.origin[it["id"]] = f.relative_to(root).as_posix()
            else:
                if key in d.model:
                    d.load_errors.append(f"{f.name}: '{key}' defined in more than one file")
                d.model[key] = value or {}

    for f in sorted((ddir / "phases").glob("*.yaml")):
        ph = read_yaml(f)
        if ph:
            d.phases.append(ph)
            d.origin[ph.get("id")] = f.relative_to(root).as_posix()
    d.phases.sort(key=lambda p: _natural_key(p.get("id", "")))
    return d


def _natural_key(s):
    return [int(t) if t.isdigit() else t for t in re.split(r"(\d+)", s)]


def natural_sorted(ids):
    return sorted(ids, key=_natural_key)


def source_hash(root: Path) -> str:
    """Stable hash of everything under design/ (the single source of truth)."""
    h = hashlib.sha256()
    for f in sorted((root / "design").rglob("*")):
        if f.is_file():
            h.update(f.relative_to(root).as_posix().encode())
            h.update(f.read_bytes())
    return h.hexdigest()[:12]


def load_semantic_types():
    return read_yaml(SEMANTIC_TYPES_FILE).get("types", {})


def parse_duration(value) -> float | None:
    """'15m' -> 900, '1h' -> 3600, '1d' -> 86400. Returns seconds or None."""
    if value is None:
        return None
    m = re.fullmatch(r"\s*(\d+(?:\.\d+)?)\s*(s|m|min|h|d|w)\s*", str(value))
    if not m:
        return None
    n, unit = float(m.group(1)), m.group(2)
    return n * {"s": 1, "m": 60, "min": 60, "h": 3600, "d": 86400, "w": 604800}[unit]


def as_list(v):
    if v is None:
        return []
    return v if isinstance(v, list) else [v]


def topo_cycles(graph: dict) -> list:
    """Return a list of cycles (each a list of node ids) in a dependency graph."""
    cycles, state, stack = [], {}, []

    def visit(n):
        state[n] = 1
        stack.append(n)
        for m in graph.get(n, []):
            if state.get(m) == 1:
                cycles.append(stack[stack.index(m):] + [m])
            elif state.get(m) is None and m in graph:
                visit(m)
        stack.pop()
        state[n] = 2

    for n in graph:
        if state.get(n) is None:
            visit(n)
    return cycles


def estimate_tokens(text: str) -> int:
    return len(text) // 4


# ---------------------------------------------------------------- mermaid
def mid(s: str) -> str:
    """Mermaid-safe node id."""
    return re.sub(r"[^A-Za-z0-9_]", "_", str(s))


def mlabel(s: str) -> str:
    """Mermaid-safe quoted label text."""
    return str(s).replace('"', "#quot;").replace("\n", " ")


def ent_name(ent_id: str) -> str:
    return re.sub(r"^ENT-", "", ent_id)


# ---------------------------------------------------------------- progress
def load_progress(root: Path) -> dict:
    out = {}
    tdir = root / "progress" / "tasks"
    if tdir.exists():
        for f in tdir.glob("*.yaml"):
            data = read_yaml(f)
            if data.get("id"):
                out[data["id"]] = data
    return out


def load_requests(root: Path) -> list:
    rdir = root / "progress" / "requests"
    if not rdir.exists():
        return []
    return [read_yaml(f) for f in sorted(rdir.glob("*.yaml"))]
